<?php
namespace App\Core;

use App\Models\StorageSettings;

/**
 * Client WebDAV minimal pour lire/écrire/supprimer le contenu des fichiers d'un Group Folder
 * (voir NextcloudGroupFolderService) — distinct de NextcloudCalDavClient (calendriers) et
 * NextcloudService (API OCS, quotas/comptes), même identifiants admin déjà configurés.
 *
 * Le compte technique (admin) accède au Group Folder via son PROPRE espace DAV
 * (/remote.php/dav/files/{admin}/{mountpoint}/...) — un Group Folder se monte automatiquement
 * dans l'espace de tout compte membre du groupe assigné, l'admin y compris (voir
 * NextcloudGroupFolderService::ensureFamilyFolder, qui l'ajoute au groupe pour cette raison —
 * vérifié contre une instance réelle : PROPFIND renvoie 404 tant que l'admin n'est pas membre).
 *
 * ETag : Nextcloud renvoie l'ETag canonique du fichier dans l'en-tête "OC-ETag" (PUT) ou
 * "ETag" (GET) — utilisé pour une lecture conditionnelle (If-None-Match) : un 304 Not Modified
 * sert le cache local disposable sans retransférer le contenu, tout en détectant une
 * modification faite directement depuis Nextcloud hors FamilyBoard.
 */
class NextcloudFileClient
{
    private const TIMEOUT = 30;

    private static function baseUrl(string $mountPoint): ?string
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) return null;
        return rtrim($settings['nextcloud_url'], '/') . '/remote.php/dav/files/'
            . rawurlencode($settings['nextcloud_admin_user']) . '/' . rawurlencode($mountPoint) . '/';
    }

    /** @return array{ok:bool,status:int,body:string,headers:array<string,string>,error:string} */
    private static function request(string $method, string $url, ?string $body = null, array $extraHeaders = []): array
    {
        $settings = StorageSettings::get();
        if (!$settings['nextcloud_url'] || !$settings['nextcloud_admin_user']) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'error' => 'Nextcloud non configuré.'];
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $settings['nextcloud_admin_user'] . ':' . $settings['nextcloud_admin_password'],
            CURLOPT_HTTPHEADER     => $extraHeaders,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            // Voir NextcloudService::request() — évite qu'une connexion persistante réutilisée
            // à travers un reverse-proxy mal configuré ne désynchronise requête/réponse.
            CURLOPT_FORBID_REUSE   => true,
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                return strlen($line);
            },
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'error' => 'Connexion impossible : ' . $curlError];
        }
        return [
            'ok'      => $status >= 200 && $status < 300,
            'status'  => $status,
            'body'    => $response,
            'headers' => $responseHeaders,
            'error'   => $status >= 300 ? "HTTP $status" : '',
        ];
    }

    /** Écrit le contenu, crée les dossiers intermédiaires manquants au besoin (MKCOL, idempotent
     *  — 405 = déjà existant). @return array{ok:bool,status:int,etag:?string,error:string} */
    public static function putFile(string $mountPoint, string $remotePath, string $content): array
    {
        $base = self::baseUrl($mountPoint);
        if (!$base) return ['ok' => false, 'status' => 0, 'etag' => null, 'error' => 'Nextcloud non configuré.'];

        $dir = trim(dirname($remotePath), '.');
        if ($dir !== '' && $dir !== '.') {
            self::request('MKCOL', $base . implode('/', array_map('rawurlencode', explode('/', $dir))) . '/');
        }

        $url = $base . implode('/', array_map('rawurlencode', explode('/', $remotePath)));
        $r = self::request('PUT', $url, $content, ['Content-Type: application/octet-stream']);
        if (!$r['ok']) {
            // 507 Insufficient Storage = quota du Group Folder dépassé (quota natif Nextcloud,
            // voir NextcloudGroupFolderService::applyFamilyQuota) — message dédié plutôt qu'un
            // "HTTP 507" générique.
            $error = $r['status'] === 507
                ? "Quota de stockage familial Nextcloud atteint — libérez de l'espace ou augmentez le quota familial."
                : ($r['error'] ?: 'Échec de l\'envoi vers Nextcloud.');
            return ['ok' => false, 'status' => $r['status'], 'etag' => null, 'error' => $error];
        }

        $etag = $r['headers']['oc-etag'] ?? $r['headers']['etag'] ?? null;
        if ($etag === null) {
            // Pas renvoyé par tous les serveurs/proxys — on le récupère par un PROPFIND dédié
            // plutôt que de laisser nextcloud_etag vide (empêcherait toute lecture conditionnelle).
            $p = self::request('PROPFIND', $url, '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:getetag/></d:prop></d:propfind>', ['Depth: 0']);
            if (preg_match('/<d:getetag>(.*?)<\/d:getetag>/', $p['body'], $m)) $etag = $m[1];
        }
        return ['ok' => true, 'status' => $r['status'], 'etag' => $etag !== null ? trim($etag, '"') : null, 'error' => ''];
    }

    /**
     * Lecture conditionnelle : si $cachedEtag correspond toujours à la version distante,
     * renvoie status=304 sans aucun contenu transféré (le cache local disposable reste valide).
     * Sinon renvoie le contenu + le nouvel ETag. 404 si le fichier n'existe plus côté Nextcloud.
     * @return array{status:int,body:?string,etag:?string,error:string}
     */
    public static function getFile(string $mountPoint, string $remotePath, ?string $cachedEtag): array
    {
        $base = self::baseUrl($mountPoint);
        if (!$base) return ['status' => 0, 'body' => null, 'etag' => null, 'error' => 'Nextcloud non configuré.'];

        $url = $base . implode('/', array_map('rawurlencode', explode('/', $remotePath)));
        $headers = $cachedEtag ? ['If-None-Match: "' . $cachedEtag . '"'] : [];
        $r = self::request('GET', $url, null, $headers);

        if ($r['status'] === 304) return ['status' => 304, 'body' => null, 'etag' => $cachedEtag, 'error' => ''];
        if ($r['status'] === 404) return ['status' => 404, 'body' => null, 'etag' => null, 'error' => 'Fichier introuvable sur Nextcloud.'];
        if (!$r['ok']) return ['status' => $r['status'], 'body' => null, 'etag' => null, 'error' => $r['error'] ?: 'Lecture Nextcloud échouée.'];

        $etag = $r['headers']['etag'] ?? $r['headers']['oc-etag'] ?? null;
        return ['status' => 200, 'body' => $r['body'], 'etag' => $etag !== null ? trim($etag, '"') : null, 'error' => ''];
    }

    /** Copie côté serveur (méthode WebDAV COPY) — évite un aller-retour GET+PUT complet pour
     *  dupliquer un fichier déjà sur Nextcloud (voir FileStore::copyRoute). */
    public static function copyFile(string $mountPoint, string $sourcePath, string $destPath): array
    {
        $base = self::baseUrl($mountPoint);
        if (!$base) return ['ok' => false, 'etag' => null, 'error' => 'Nextcloud non configuré.'];

        $dir = trim(dirname($destPath), '.');
        if ($dir !== '' && $dir !== '.') {
            self::request('MKCOL', $base . implode('/', array_map('rawurlencode', explode('/', $dir))) . '/');
        }

        $sourceUrl = $base . implode('/', array_map('rawurlencode', explode('/', $sourcePath)));
        $destUrl = $base . implode('/', array_map('rawurlencode', explode('/', $destPath)));
        $r = self::request('COPY', $sourceUrl, null, ['Destination: ' . $destUrl]);
        if (!$r['ok']) return ['ok' => false, 'etag' => null, 'error' => $r['error'] ?: 'Échec de la copie Nextcloud.'];

        $etag = $r['headers']['oc-etag'] ?? $r['headers']['etag'] ?? null;
        return ['ok' => true, 'etag' => $etag !== null ? trim($etag, '"') : null, 'error' => ''];
    }

    /** Idempotent : 404 (déjà absent) est traité comme un succès. */
    public static function deleteFile(string $mountPoint, string $remotePath): bool
    {
        $base = self::baseUrl($mountPoint);
        if (!$base) return false;
        $url = $base . implode('/', array_map('rawurlencode', explode('/', $remotePath)));
        $r = self::request('DELETE', $url);
        return $r['ok'] || $r['status'] === 404;
    }

    public static function testConnection(string $mountPoint): array
    {
        $base = self::baseUrl($mountPoint);
        if (!$base) return ['ok' => false, 'error' => 'Nextcloud non configuré.'];
        $r = self::request('PROPFIND', $base, '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>', ['Depth: 0']);
        if (!$r['ok']) return ['ok' => false, 'error' => $r['error'] ?: "Accès DAV refusé au dossier $mountPoint."];
        return ['ok' => true, 'error' => ''];
    }
}
