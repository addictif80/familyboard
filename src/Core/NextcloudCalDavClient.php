<?php
namespace App\Core;

use App\Models\StorageSettings;

/**
 * Client CalDAV minimal pour la synchronisation à sens unique du calendrier FamilyBoard vers
 * Nextcloud (voir NextcloudCalendarSync) — distinct de NextcloudService (API OCS, utilisée
 * uniquement pour les quotas) : ici on parle DAV brut (MKCALENDAR, PUT, DELETE), avec les mêmes
 * identifiants admin déjà configurés (StorageSettings).
 *
 * Principe : le compte technique (admin) POSSÈDE un calendrier dédié par membre
 * ("familyboard-<uid>"), jamais écrit directement dans le calendrier personnel du membre — on
 * écrit toujours dans ses propres ressources, jamais dans celles d'un autre compte, ce qui évite
 * tout problème de permission. Ce calendrier est ensuite partagé en LECTURE SEULE au membre.
 *
 * Partage en lecture seule : vérifié contre une vraie instance Nextcloud — la balise
 * <oc:read-write> doit être ABSENTE du corps de la requête (sa simple présence, même à "false",
 * accorde l'écriture ; voir apps/dav/lib/DAV/Sharing/Xml/ShareRequest.php côté Nextcloud).
 */
class NextcloudCalDavClient
{
    private const TIMEOUT = 15;

    private static function adminUser(): ?string
    {
        $settings = StorageSettings::get();
        return $settings['nextcloud_admin_user'] ?: null;
    }

    private static function calendarName(string $memberUsername): string
    {
        return 'familyboard-' . $memberUsername;
    }

    private static function calendarUrl(string $memberUsername): ?string
    {
        $settings = StorageSettings::get();
        $admin = self::adminUser();
        if (!$settings['nextcloud_url'] || !$admin) return null;
        return rtrim($settings['nextcloud_url'], '/') . '/remote.php/dav/calendars/'
            . rawurlencode($admin) . '/' . rawurlencode(self::calendarName($memberUsername)) . '/';
    }

    private static function request(string $method, string $url, ?string $body = null, array $extraHeaders = []): array
    {
        $settings = StorageSettings::get();
        $admin = self::adminUser();
        if (!$settings['nextcloud_url'] || !$admin) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Nextcloud non configuré.'];
        }

        $ch = curl_init($url);
        $headers = array_merge(['Content-Type: application/xml; charset=utf-8'], $extraHeaders);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $admin . ':' . $settings['nextcloud_admin_password'],
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            // Même précaution que NextcloudService : évite un "Upgrade: h2" invalide renvoyé
            // par certains reverse-proxies une fois négocié en HTTP/2 via ALPN.
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Connexion impossible : ' . $curlError];
        }
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $response, 'error' => $status >= 300 ? "HTTP $status" : ''];
    }

    /** Crée le calendrier dédié de ce membre s'il n'existe pas déjà (idempotent : un calendrier
     *  déjà présent répond 405, traité comme un succès). */
    public static function ensureCalendar(string $memberUsername): bool
    {
        $url = self::calendarUrl($memberUsername);
        if (!$url) return false;

        $r = self::request('MKCALENDAR', $url, '<?xml version="1.0" encoding="utf-8" ?>
<mkcalendar xmlns="urn:ietf:params:xml:ns:caldav">
  <set><prop>
    <displayname xmlns="DAV:">FamilyBoard</displayname>
  </prop></set>
</mkcalendar>');
        return $r['ok'] || $r['status'] === 405; // 405 = déjà existant
    }

    /** Partage le calendrier dédié de ce membre, en lecture seule, à ce même membre — idempotent
     *  (un ré-appel sur un partage déjà existant est sans effet néfaste côté Nextcloud). */
    public static function ensureSharedReadOnly(string $memberUsername): bool
    {
        $url = self::calendarUrl($memberUsername);
        if (!$url) return false;

        $r = self::request('POST', $url, '<?xml version="1.0" encoding="utf-8" ?>
<oc:share xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <oc:set>
    <d:href>principal:principals/users/' . htmlspecialchars($memberUsername, ENT_XML1) . '</d:href>
  </oc:set>
</oc:share>');
        return $r['ok'];
    }

    public static function putEvent(string $memberUsername, string $uid, string $icsContent): bool
    {
        $url = self::calendarUrl($memberUsername);
        if (!$url) return false;
        $r = self::request('PUT', $url . rawurlencode($uid) . '.ics', $icsContent, ['Content-Type: text/calendar; charset=utf-8']);
        return $r['ok'];
    }

    public static function deleteEvent(string $memberUsername, string $uid): bool
    {
        $url = self::calendarUrl($memberUsername);
        if (!$url) return false;
        $r = self::request('DELETE', $url . rawurlencode($uid) . '.ics');
        return $r['ok'] || $r['status'] === 404; // déjà absent = résultat voulu
    }

    public static function testConnection(): array
    {
        $settings = StorageSettings::get();
        $admin = self::adminUser();
        if (!$settings['nextcloud_url'] || !$admin) {
            return ['ok' => false, 'error' => 'URL et utilisateur administrateur Nextcloud requis.'];
        }
        $r = self::request('PROPFIND', rtrim($settings['nextcloud_url'], '/') . '/remote.php/dav/calendars/' . rawurlencode($admin) . '/', '<?xml version="1.0" encoding="utf-8" ?>
<d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>', ['Depth: 1']);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => "Connexion DAV refusée (" . ($r['error'] ?: 'HTTP ' . $r['status']) . ") — vérifiez que l'app Calendrier Nextcloud est activée."];
        }
        return ['ok' => true, 'error' => ''];
    }
}
