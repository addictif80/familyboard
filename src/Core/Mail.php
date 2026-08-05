<?php
namespace App\Core;

use App\Models\SmtpSettings;
use App\Models\EmailLog;

class Mail
{
    /**
     * $attachments : liste de ['filename' => string, 'content' => string (données brutes,
     * pas encore encodées), 'mime' => string].
     */
    public static function send(
        int $familyId,
        string $to,
        string $toName,
        string $subject,
        string $htmlBody,
        string $type = 'manual',
        ?int $sentBy = null,
        array $attachments = []
    ): bool {
        $settings = SmtpSettings::get();
        if (!$settings) {
            EmailLog::add($familyId, $sentBy, $to, $toName, $subject, $htmlBody, $type, false, 'Configuration SMTP manquante.');
            return false;
        }

        [$ok, $error] = self::sendViaSMTP($settings, $to, $toName, $subject, $htmlBody, $attachments);
        EmailLog::add($familyId, $sentBy, $to, $toName, $subject, $htmlBody, $type, $ok, $error);
        return $ok;
    }

    /**
     * Returns [bool $success, string $errorMessage]
     */
    private static function sendViaSMTP(array $s, string $to, string $toName, string $subject, string $body, array $attachments = []): array
    {
        $port   = (int)$s['port'];
        $host   = $s['host'];
        $prefix = $s['encryption'] === 'ssl' ? 'ssl://' : '';

        // Vérification TLS activée par défaut : la désactiver exposerait les identifiants SMTP
        // (AUTH LOGIN) à une interception réseau (MITM) sur CHAQUE email envoyé.
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ]]);

        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$socket) {
            return [false, "Connexion impossible à {$host}:{$port} – {$errstr} (errno {$errno})"];
        }
        stream_set_timeout($socket, 15);

        $banner = fgets($socket, 512);
        if (!$banner || !str_starts_with($banner, '220')) {
            fclose($socket);
            return [false, "Bannière SMTP inattendue : " . trim((string)$banner)];
        }

        $ehlo = gethostname() ?: 'localhost';
        self::cmd($socket, "EHLO {$ehlo}");

        if ($s['encryption'] === 'tls') {
            $tlsR = self::cmd($socket, 'STARTTLS');
            if (!str_starts_with($tlsR, '220')) {
                fclose($socket);
                return [false, "STARTTLS refusé : " . trim($tlsR)];
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return [false, 'Négociation TLS échouée (certificat ou protocole incompatible)'];
            }
            self::cmd($socket, "EHLO {$ehlo}");
        }

        // AUTH LOGIN
        self::raw($socket, "AUTH LOGIN\r\n");
        $r1 = fgets($socket, 512);
        if (!$r1 || !str_starts_with($r1, '334')) {
            fclose($socket);
            return [false, "AUTH LOGIN refusé : " . trim((string)$r1) . " (le serveur supporte peut-être AUTH PLAIN ou XOAUTH2 uniquement)"];
        }
        self::raw($socket, base64_encode($s['username']) . "\r\n");
        $r2 = fgets($socket, 512);
        if (!$r2 || !str_starts_with($r2, '334')) {
            fclose($socket);
            return [false, "Identifiant rejeté : " . trim((string)$r2)];
        }
        self::raw($socket, base64_encode($s['password']) . "\r\n");
        $authResp = fgets($socket, 512);
        if (!$authResp || !str_starts_with($authResp, '235')) {
            fclose($socket);
            return [false, "Authentification échouée : " . trim((string)$authResp)];
        }

        // MAIL FROM
        $mfR = self::cmd($socket, "MAIL FROM:<{$s['from_email']}>");
        if (!str_starts_with($mfR, '250')) {
            fclose($socket);
            return [false, "MAIL FROM rejeté : " . trim($mfR)];
        }

        // RCPT TO
        $rtR = self::cmd($socket, "RCPT TO:<{$to}>");
        if (!str_starts_with($rtR, '250') && !str_starts_with($rtR, '251')) {
            fclose($socket);
            return [false, "RCPT TO rejeté : " . trim($rtR) . " (relais refusé ou adresse invalide)"];
        }

        // DATA
        $dataStart = self::cmd($socket, 'DATA');
        if (!str_starts_with($dataStart, '354')) {
            fclose($socket);
            return [false, "DATA refusé : " . trim($dataStart)];
        }

        // Dot-stuffing: lines starting with '.' must be escaped as '..'
        $safeBody = preg_replace('/^\.(?=.)/m', '..', $body);

        if ($attachments) {
            // Base64 n'utilise jamais '.', donc les parties jointes n'ont pas besoin de
            // dot-stuffing — seul le corps HTML (déjà traité ci-dessus) en a besoin.
            $boundary = 'fbnd_' . bin2hex(random_bytes(12));
            $headers = implode("\r\n", [
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
                'From: ' . $s['from_name'] . ' <' . $s['from_email'] . '>',
                'To: ' . $toName . ' <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'X-Mailer: FamilyBoard',
            ]);
            $mime = "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$safeBody}\r\n";
            foreach ($attachments as $att) {
                $mime .= "--{$boundary}\r\n"
                    . 'Content-Type: ' . ($att['mime'] ?? 'application/octet-stream') . '; name="' . $att['filename'] . '"' . "\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . 'Content-Disposition: attachment; filename="' . $att['filename'] . '"' . "\r\n\r\n"
                    . chunk_split(base64_encode($att['content']))
                    . "\r\n";
            }
            $mime .= "--{$boundary}--\r\n";
            self::raw($socket, $headers . "\r\n\r\n" . $mime . "\r\n.\r\n");
        } else {
            $headers = implode("\r\n", [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $s['from_name'] . ' <' . $s['from_email'] . '>',
                'To: ' . $toName . ' <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'X-Mailer: FamilyBoard',
            ]);
            self::raw($socket, $headers . "\r\n\r\n" . $safeBody . "\r\n.\r\n");
        }
        $dataResp = fgets($socket, 512);
        self::raw($socket, "QUIT\r\n");
        fclose($socket);

        if (!$dataResp || !str_starts_with($dataResp, '250')) {
            return [false, "Message refusé : " . trim((string)$dataResp)];
        }
        return [true, ''];
    }

    /**
     * Send a command, read the full (possibly multi-line) response, return last line.
     */
    private static function cmd($socket, string $command): string
    {
        self::raw($socket, $command . "\r\n");
        $last = '';
        while ($line = fgets($socket, 512)) {
            $last = $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $last;
    }

    private static function raw($socket, string $data): void
    {
        fputs($socket, $data);
    }

    public static function notifyUser(array $user, string $subject, string $html): bool
    {
        return self::send($user['family_id'], $user['email'], $user['name'], $subject, $html);
    }

    /**
     * Send a real test email and return step-by-step SMTP responses.
     * Returns ['ok' => bool, 'steps' => [...], 'error' => string]
     */
    public static function sendTest(array $s, string $to, string $toName): array
    {
        $subject = '[FamilyBoard] Email de test – ' . date('d/m/Y H:i');
        $body    = '<p>Bonjour ' . htmlspecialchars($toName) . ',</p>'
                 . '<p>Cet email confirme que la configuration SMTP de <strong>FamilyBoard</strong> fonctionne correctement.</p>'
                 . '<p><em>Envoyé le ' . date('d/m/Y à H:i') . ' depuis ' . $s['host'] . '</em></p>';

        $port   = (int)$s['port'];
        $host   = $s['host'];
        $prefix = $s['encryption'] === 'ssl' ? 'ssl://' : '';
        $steps  = [];

        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
        ]]);

        $errno = 0; $errstr = '';
        $socket = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            $steps[] = ['label' => 'Connexion', 'ok' => false, 'detail' => "{$host}:{$port} – {$errstr}"];
            return ['ok' => false, 'steps' => $steps, 'error' => "Connexion impossible : {$errstr}"];
        }
        stream_set_timeout($socket, 15);

        $banner = fgets($socket, 512);
        $steps[] = ['label' => 'Connexion',    'ok' => str_starts_with((string)$banner, '220'), 'detail' => trim((string)$banner)];
        $ehlo = gethostname() ?: 'localhost';
        $ehloR = self::cmd($socket, "EHLO {$ehlo}");
        $steps[] = ['label' => 'EHLO',         'ok' => str_starts_with($ehloR, '250'), 'detail' => trim($ehloR)];

        if ($s['encryption'] === 'tls') {
            $tlsR = self::cmd($socket, 'STARTTLS');
            $tlsOk = str_starts_with($tlsR, '220');
            $steps[] = ['label' => 'STARTTLS', 'ok' => $tlsOk, 'detail' => trim($tlsR)];
            if ($tlsOk) {
                $cryptoOk = (bool)stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $steps[] = ['label' => 'Handshake TLS', 'ok' => $cryptoOk, 'detail' => $cryptoOk ? 'OK' : 'Échec'];
                if (!$cryptoOk) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => 'TLS échoué']; }
                self::cmd($socket, "EHLO {$ehlo}");
            }
        }

        self::raw($socket, "AUTH LOGIN\r\n");
        $r1 = fgets($socket, 512);
        $steps[] = ['label' => 'AUTH LOGIN',      'ok' => str_starts_with((string)$r1, '334'), 'detail' => trim((string)$r1)];
        if (!str_starts_with((string)$r1, '334')) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => trim((string)$r1)]; }

        self::raw($socket, base64_encode($s['username']) . "\r\n");
        $r2 = fgets($socket, 512);
        $steps[] = ['label' => 'Identifiant',     'ok' => str_starts_with((string)$r2, '334'), 'detail' => trim((string)$r2)];
        if (!str_starts_with((string)$r2, '334')) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => trim((string)$r2)]; }

        self::raw($socket, base64_encode($s['password']) . "\r\n");
        $r3 = fgets($socket, 512);
        $authOk = str_starts_with((string)$r3, '235');
        $steps[] = ['label' => 'Authentification','ok' => $authOk, 'detail' => trim((string)$r3)];
        if (!$authOk) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => trim((string)$r3)]; }

        $mfR = self::cmd($socket, "MAIL FROM:<{$s['from_email']}>");
        $mfOk = str_starts_with($mfR, '250');
        $steps[] = ['label' => 'MAIL FROM',       'ok' => $mfOk, 'detail' => trim($mfR)];
        if (!$mfOk) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => "MAIL FROM rejeté : " . trim($mfR)]; }

        $rtR = self::cmd($socket, "RCPT TO:<{$to}>");
        $rtOk = str_starts_with($rtR, '250') || str_starts_with($rtR, '251');
        $steps[] = ['label' => 'RCPT TO',         'ok' => $rtOk, 'detail' => trim($rtR)];
        if (!$rtOk) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => "RCPT TO rejeté : " . trim($rtR)]; }

        $dataStart = self::cmd($socket, 'DATA');
        $steps[] = ['label' => 'DATA',            'ok' => str_starts_with($dataStart, '354'), 'detail' => trim($dataStart)];
        if (!str_starts_with($dataStart, '354')) { fclose($socket); return ['ok' => false, 'steps' => $steps, 'error' => "DATA refusé : " . trim($dataStart)]; }

        $hdrs = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $s['from_name'] . ' <' . $s['from_email'] . '>',
            'To: ' . $toName . ' <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'X-Mailer: FamilyBoard',
        ]);
        self::raw($socket, $hdrs . "\r\n\r\n" . $body . "\r\n.\r\n");
        $dataResp = fgets($socket, 512);
        $dataOk   = str_starts_with((string)$dataResp, '250');
        $steps[]  = ['label' => 'Remise message', 'ok' => $dataOk, 'detail' => trim((string)$dataResp)];

        self::raw($socket, "QUIT\r\n");
        fclose($socket);

        return [
            'ok'    => $dataOk,
            'steps' => $steps,
            'error' => $dataOk ? '' : "Message refusé : " . trim((string)$dataResp),
        ];
    }

    /**
     * Test the SMTP connection step by step without sending a real email.
     * Returns ['ok' => bool, 'steps' => [['label','ok','detail']], 'error' => string]
     */
    public static function testConnection(array $s): array
    {
        $steps  = [];
        $port   = (int)$s['port'];
        $host   = $s['host'];
        $prefix = $s['encryption'] === 'ssl' ? 'ssl://' : '';

        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ]]);

        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            $steps[] = ['label' => 'Connexion', 'ok' => false, 'detail' => "{$host}:{$port} – {$errstr}"];
            return ['ok' => false, 'steps' => $steps, 'error' => "Connexion impossible à {$host}:{$port} – {$errstr}"];
        }
        stream_set_timeout($socket, 10);

        $banner = fgets($socket, 512);
        $steps[] = ['label' => 'Connexion', 'ok' => str_starts_with((string)$banner, '220'), 'detail' => trim((string)$banner)];

        $ehlo = gethostname() ?: 'localhost';
        self::raw($socket, "EHLO {$ehlo}\r\n");
        $ehloLast = '';
        while ($l = fgets($socket, 512)) { $ehloLast = $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        $steps[] = ['label' => 'EHLO', 'ok' => str_starts_with($ehloLast, '250'), 'detail' => trim($ehloLast)];

        if ($s['encryption'] === 'tls') {
            $tlsR  = self::cmd($socket, 'STARTTLS');
            $tlsOk = str_starts_with($tlsR, '220');
            $steps[] = ['label' => 'STARTTLS', 'ok' => $tlsOk, 'detail' => trim($tlsR)];
            if ($tlsOk) {
                $cryptoOk = (bool)stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $steps[] = ['label' => 'Handshake TLS', 'ok' => $cryptoOk, 'detail' => $cryptoOk ? 'OK' : 'Échec'];
                if (!$cryptoOk) {
                    fclose($socket);
                    return ['ok' => false, 'steps' => $steps, 'error' => 'TLS handshake échoué'];
                }
                self::cmd($socket, "EHLO {$ehlo}");
            }
        }

        self::raw($socket, "AUTH LOGIN\r\n");
        $r1 = fgets($socket, 512);
        $steps[] = ['label' => 'AUTH LOGIN', 'ok' => str_starts_with((string)$r1, '334'), 'detail' => trim((string)$r1)];
        if (!str_starts_with((string)$r1, '334')) {
            fclose($socket);
            return ['ok' => false, 'steps' => $steps, 'error' => trim((string)$r1)];
        }

        self::raw($socket, base64_encode($s['username']) . "\r\n");
        $r2 = fgets($socket, 512);
        $steps[] = ['label' => 'Identifiant', 'ok' => str_starts_with((string)$r2, '334'), 'detail' => trim((string)$r2)];
        if (!str_starts_with((string)$r2, '334')) {
            fclose($socket);
            return ['ok' => false, 'steps' => $steps, 'error' => trim((string)$r2)];
        }

        self::raw($socket, base64_encode($s['password']) . "\r\n");
        $r3 = fgets($socket, 512);
        $authOk = str_starts_with((string)$r3, '235');
        $steps[] = ['label' => 'Authentification', 'ok' => $authOk, 'detail' => trim((string)$r3)];

        self::raw($socket, "QUIT\r\n");
        fclose($socket);

        return [
            'ok'    => $authOk,
            'steps' => $steps,
            'error' => $authOk ? '' : "Authentification refusée : " . trim((string)$r3),
        ];
    }
}
