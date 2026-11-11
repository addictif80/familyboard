<?php
namespace App\Models;

use App\Core\Database;

/**
 * Registre des fichiers stockés par famille (voir App\Core\FileStore, point d'entrée unique
 * d'écriture) — source de vérité pour le quota familial partagé, la consommation par module et
 * la purge RGPD.
 */
class StoredFile
{
    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM stored_files WHERE id = ?', [$id]);
    }

    public static function findByPath(string $path): ?array
    {
        return Database::fetch('SELECT * FROM stored_files WHERE path = ?', [$path]);
    }

    public static function create(int $familyId, ?int $userId, string $module, string $path, ?string $originalName, ?string $mime, int $sizeBytes, string $storageBackend = 'local', ?string $nextcloudEtag = null): int
    {
        return Database::insert(
            'INSERT INTO stored_files (family_id, user_id, module, path, original_name, mime, size_bytes, storage_backend, nextcloud_etag) VALUES (?,?,?,?,?,?,?,?,?)',
            [$familyId, $userId, $module, $path, $originalName, $mime, $sizeBytes, $storageBackend, $nextcloudEtag]
        );
    }

    /** Met à jour l'ETag Nextcloud connu après une lecture/écriture (voir FileStore, lecture
     *  conditionnelle If-None-Match pour le cache local disposable). */
    public static function updateEtag(int $id, ?string $etag): void
    {
        Database::execute('UPDATE stored_files SET nextcloud_etag = ? WHERE id = ?', [$etag, $id]);
    }

    /** Marque une ligne existante comme désormais portée par Nextcloud (voir
     *  scripts/migrate_files_to_nextcloud.php) — le chemin local historique devient le chemin
     *  relatif au Group Folder de la famille. */
    public static function markMigratedToNextcloud(int $id, string $newRemotePath, ?string $etag): void
    {
        Database::execute(
            "UPDATE stored_files SET path = ?, storage_backend = 'nextcloud', nextcloud_etag = ? WHERE id = ?",
            [$newRemotePath, $etag, $id]
        );
    }

    public static function deleteById(int $id): void
    {
        Database::execute('DELETE FROM stored_files WHERE id = ?', [$id]);
    }

    public static function deleteByPath(string $path): void
    {
        Database::execute('DELETE FROM stored_files WHERE path = ?', [$path]);
    }

    public static function deleteByFamily(int $familyId): array
    {
        $paths = array_column(Database::fetchAll('SELECT path FROM stored_files WHERE family_id = ?', [$familyId]), 'path');
        Database::execute('DELETE FROM stored_files WHERE family_id = ?', [$familyId]);
        return $paths;
    }

    /** Octets totaux comptant dans le quota familial (modules exclus — avatars... — ignorés). */
    public static function totalBytesForFamily(int $familyId, array $excludedModules = []): int
    {
        if (empty($excludedModules)) {
            $row = Database::fetch('SELECT COALESCE(SUM(size_bytes),0) AS b FROM stored_files WHERE family_id = ?', [$familyId]);
            return (int)($row['b'] ?? 0);
        }
        $placeholders = implode(',', array_fill(0, count($excludedModules), '?'));
        $row = Database::fetch(
            "SELECT COALESCE(SUM(size_bytes),0) AS b FROM stored_files WHERE family_id = ? AND module NOT IN ($placeholders)",
            [$familyId, ...$excludedModules]
        );
        return (int)($row['b'] ?? 0);
    }

    /** Détail de la consommation par module (pour l'indicateur admin/famille), modules exclus
     *  inclus dans le résultat (affichés mais signalés comme hors quota côté vue). */
    public static function breakdownByModuleForFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT module, COUNT(*) AS file_count, COALESCE(SUM(size_bytes),0) AS total_bytes
             FROM stored_files WHERE family_id = ? GROUP BY module ORDER BY total_bytes DESC',
            [$familyId]
        );
    }

    /** Familles dont la consommation (modules comptant dans le quota) dépasse leur quota —
     *  utilisé par l'alerte admin de capacité, jamais pour bloquer rétroactivement un accès. */
    public static function familiesOverQuota(int $familyQuotaGb, array $excludedModules = []): array
    {
        $excludeSql = '';
        $params = [];
        if (!empty($excludedModules)) {
            $placeholders = implode(',', array_fill(0, count($excludedModules), '?'));
            $excludeSql = "AND module NOT IN ($placeholders)";
            $params = $excludedModules;
        }
        $params[] = $familyQuotaGb * 1024 * 1024 * 1024;
        return Database::fetchAll(
            "SELECT family_id, SUM(size_bytes) AS total_bytes FROM stored_files
             WHERE 1=1 $excludeSql GROUP BY family_id HAVING total_bytes > ?",
            $params
        );
    }
}
