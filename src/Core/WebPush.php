<?php
namespace App\Core;

/**
 * Minimal Web Push implementation (RFC 8291 + RFC 8292 VAPID).
 * Requires PHP 8.1+ (openssl_pkey_derive for ECDH).
 */
class WebPush
{
    // ── Key generation ─────────────────────────────────────────

    public static function generateVapidKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $details = openssl_pkey_get_details($key);
        // Uncompressed public key: 04 || x || y
        $pub = "\x04"
             . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
             . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        // Export full PEM for storage
        openssl_pkey_export($key, $privPem);
        return [
            'public'  => self::b64u($pub),
            'private' => $privPem,
        ];
    }

    // ── Send one push notification ──────────────────────────────

    public static function send(
        string  $endpoint,
        string  $p256dh,
        string  $authKey,
        string  $vapidPublicB64u,
        string  $vapidPrivatePem,
        ?string $payload = null,
        int     $ttl = 86400
    ): bool {
        $parsed   = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        $jwt = self::vapidJwt($audience, $vapidPrivatePem);

        $headers = [
            'TTL'           => (string)$ttl,
            'Authorization' => 'vapid t=' . $jwt . ',k=' . $vapidPublicB64u,
        ];

        $body = '';
        if ($payload !== null) {
            $body = self::encrypt($payload, $p256dh, $authKey);
            $headers['Content-Type']     = 'application/octet-stream';
            $headers['Content-Encoding'] = 'aes128gcm';
            $headers['Content-Length']   = (string)strlen($body);
        }

        return self::post($endpoint, $body, $headers);
    }

    // ── VAPID JWT (ES256) ───────────────────────────────────────

    private static function vapidJwt(string $audience, string $privPem): string
    {
        $h = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $p = self::b64u(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => 'mailto:admin@familyboard.local',
        ]));
        $input = $h . '.' . $p;
        openssl_sign($input, $derSig, $privPem, OPENSSL_ALGO_SHA256);
        return $input . '.' . self::b64u(self::derSigToRaw($derSig));
    }

    // ── Payload encryption (RFC 8291 – aes128gcm) ──────────────

    private static function encrypt(string $plaintext, string $p256dhB64u, string $authKeyB64u): string
    {
        $uaPub     = self::db64u($p256dhB64u);
        $authBytes = self::db64u($authKeyB64u);

        // Ephemeral server key
        $srvKey  = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $det     = openssl_pkey_get_details($srvKey);
        $srvPub  = "\x04"
                 . str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                 . str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        // Import UA public key
        $uaPubKey      = openssl_pkey_get_public(self::rawPubToPem($uaPub));
        $sharedSecret  = openssl_pkey_derive($uaPubKey, $srvKey);
        if (!$sharedSecret) {
            throw new \RuntimeException('ECDH failed: ' . openssl_error_string());
        }

        $salt = random_bytes(16);

        // IKM (RFC 8291 §3.3)
        $ikm   = self::hkdf($authBytes, $sharedSecret, "WebPush: info\x00" . $uaPub . $srvPub, 32);
        $cek   = self::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

        // Encrypt with AES-128-GCM (tag appended)
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        // Header: salt(16) || rs(uint32 BE) || keyid_len(1) || server_pub(65)
        return $salt . pack('N', 4096) . chr(65) . $srvPub . $ciphertext . $tag;
    }

    // ── Helpers ─────────────────────────────────────────────────

    private static function hkdf(string $salt, string $ikm, string $info, int $len): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $out = '';
        $prev = '';
        for ($i = 1; strlen($out) < $len; $i++) {
            $prev = hash_hmac('sha256', $prev . $info . chr($i), $prk, true);
            $out .= $prev;
        }
        return substr($out, 0, $len);
    }

    /** Convert raw uncompressed P-256 point to PEM SubjectPublicKeyInfo */
    private static function rawPubToPem(string $raw): string
    {
        // Fixed DER header for P-256 uncompressed public key (26 bytes)
        $der = "\x30\x59\x30\x13"
             . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"   // OID ecPublicKey
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID prime256v1
             . "\x03\x42\x00"                              // BIT STRING, 66 bytes
             . $raw;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** DER ECDSA signature → raw R||S (64 bytes) for JWT */
    private static function derSigToRaw(string $der): string
    {
        $off = 2; // skip SEQUENCE tag + length byte
        if (strlen($der) > 1 && ord($der[1]) > 0x80) {
            $off += ord($der[1]) - 0x80; // long-form length
        }
        $off++;                         // INTEGER tag for r
        $rLen = ord($der[$off++]);
        $r = substr($der, $off, $rLen); $off += $rLen;
        $off++;                         // INTEGER tag for s
        $sLen = ord($der[$off++]);
        $s = substr($der, $off, $sLen);
        // Normalize to 32 bytes each
        $r = substr(str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT), -32);
        $s = substr(str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT), -32);
        return $r . $s;
    }

    public static function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function db64u(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private static function post(string $url, string $body, array $headers): bool
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = "$k: $v";
        }
        $ctx = stream_context_create(['http' => [
            'method'         => 'POST',
            'header'         => implode("\r\n", $lines),
            'content'        => $body,
            'ignore_errors'  => true,
            'timeout'        => 15,
        ]]);
        @file_get_contents($url, false, $ctx);
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('/HTTP\/\S+ (\d+)/', $h, $m)) {
                return (int)$m[1] < 400;
            }
        }
        return false;
    }
}
