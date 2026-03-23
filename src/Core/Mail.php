<?php
namespace App\Core;

use App\Models\SmtpSettings;

class Mail
{
    public static function send(int $familyId, string $to, string $toName, string $subject, string $htmlBody, string $type = 'manual', ?int $sentBy = null): bool
    {
        $settings = SmtpSettings::getByFamily($familyId);
        if (!$settings) return false;

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
            'To: ' . $toName . ' <' . $to . '>',
            'X-Mailer: FamilyBoard',
        ];

        $ok = self::sendViaSMTP($settings, $to, $toName, $subject, $htmlBody, $headers);

        \App\Models\EmailLog::add($familyId, $sentBy, $to, $toName, $subject, $htmlBody, $type, $ok);

        return $ok;
    }

    private static function sendViaSMTP(array $s, string $to, string $toName, string $subject, string $body, array $extraHeaders): bool
    {
        $port = (int)$s['port'];
        $host = $s['host'];

        $prefix = match($s['encryption']) {
            'ssl' => 'ssl://',
            'tls' => '',
            default => '',
        };

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        if (!$socket) return false;

        $read = fgets($socket, 512);
        if (!str_starts_with($read, '220')) { fclose($socket); return false; }

        $ehlo = gethostname() ?: 'localhost';
        fputs($socket, "EHLO $ehlo\r\n");
        $resp = '';
        while ($line = fgets($socket, 512)) {
            $resp .= $line;
            if ($line[3] === ' ') break;
        }

        if ($s['encryption'] === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            fgets($socket, 512);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO $ehlo\r\n");
            while ($line = fgets($socket, 512)) {
                if ($line[3] === ' ') break;
            }
        }

        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket, 512);
        fputs($socket, base64_encode($s['username']) . "\r\n");
        fgets($socket, 512);
        fputs($socket, base64_encode($s['password']) . "\r\n");
        $authResp = fgets($socket, 512);
        if (!str_starts_with($authResp, '235')) { fclose($socket); return false; }

        fputs($socket, "MAIL FROM:<{$s['from_email']}>\r\n");
        fgets($socket, 512);
        fputs($socket, "RCPT TO:<$to>\r\n");
        fgets($socket, 512);
        fputs($socket, "DATA\r\n");
        fgets($socket, 512);

        $headers = implode("\r\n", $extraHeaders);
        $message = "$headers\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n\r\n$body";
        fputs($socket, $message . "\r\n.\r\n");
        $dataResp = fgets($socket, 512);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return str_starts_with($dataResp, '250');
    }

    public static function notifyUser(array $user, string $subject, string $html): bool
    {
        return self::send($user['family_id'], $user['email'], $user['name'], $subject, $html);
    }
}
