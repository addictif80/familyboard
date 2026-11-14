<?php
namespace App\Core;

/**
 * Lecture/suppression des fichiers d'une instance Cozy Cloud via son API fichiers OAuth (voir
 * https://docs.cozy.io/en/cozy-stack/files/). Se limite au dossier "Administratif" (celui que
 * les Konnectors remplissent) — jamais tout l'espace fichiers Cozy de la famille.
 *
 * Limite connue : la liste des enfants d'un dossier est paginée par l'API Cozy (30 par défaut,
 * curseur `links.next`) — non géré ici (une seule page lue). À étendre si une famille dépasse 30
 * fichiers/sous-dossiers cumulés sous Administratif.
 */
class CozyFileSync
{
    private const TIMEOUT = 30;
    private const ADMIN_FOLDER_PATH = '/Administratif';

    private static function request(array $connection, string $method, string $path, bool $raw = false): array
    {
        $ch = curl_init('https://' . $connection['cozy_domain'] . $path);
        $headers = ['Authorization: Bearer ' . $connection['access_token']];
        if (!$raw) $headers[] = 'Accept: application/vnd.api+json';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $raw
            ? ['status' => $status, 'body' => $response]
            : ['status' => $status, 'body' => $response !== false ? json_decode($response, true) : null];
    }

    /** @return array<int, array{id:string, name:string, mime:?string, size:int}> */
    public static function listAdministratifFiles(array $connection): array
    {
        $root = self::request($connection, 'GET', '/files/metadata?Path=' . rawurlencode(self::ADMIN_FOLDER_PATH));
        if ($root['status'] === 404) return []; // pas encore de dossier Administratif pour cette famille
        if ($root['status'] !== 200 || !is_array($root['body'])) return [];

        $rootId = $root['body']['data']['id'] ?? null;
        if (!$rootId) return [];

        return self::walk($connection, $rootId);
    }

    private static function walk(array $connection, string $dirId): array
    {
        $files = [];
        $r = self::request($connection, 'GET', "/files/$dirId");
        if ($r['status'] !== 200 || !is_array($r['body'])) return $files;

        foreach ($r['body']['included'] ?? [] as $entry) {
            if (($entry['type'] ?? '') !== 'io.cozy.files') continue;
            $attrs = $entry['attributes'] ?? [];
            if (!empty($attrs['trashed'])) continue;

            if (($attrs['type'] ?? '') === 'directory') {
                $files = array_merge($files, self::walk($connection, $entry['id']));
            } elseif (($attrs['type'] ?? '') === 'file') {
                $files[] = [
                    'id'   => $entry['id'],
                    'name' => $attrs['name'] ?? 'fichier',
                    'mime' => $attrs['mime'] ?? null,
                    'size' => (int)($attrs['size'] ?? 0),
                ];
            }
        }
        return $files;
    }

    public static function download(array $connection, string $fileId): ?string
    {
        $r = self::request($connection, 'GET', "/files/download/$fileId", true);
        return $r['status'] === 200 ? $r['body'] : null;
    }

    /** Met le fichier à la corbeille Cozy (récupérable quelques jours côté Cozy) — jamais une
     *  suppression définitive, par précaution en cas d'échec de l'écriture côté FamilyBoard
     *  constaté après coup. */
    public static function trash(array $connection, string $fileId): bool
    {
        $r = self::request($connection, 'DELETE', "/files/$fileId");
        return in_array($r['status'], [200, 204], true);
    }
}
