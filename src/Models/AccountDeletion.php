<?php
namespace App\Models;

use App\Core\Database;

/** Suppression de compte utilisateur / famille, avec nettoyage des fichiers orphelins. */
class AccountDeletion
{
    /** Supprime le compte d'un utilisateur qui reste dans une famille non vide. */
    public static function deleteUser(int $userId, int $familyId): void
    {
        $filePaths = self::userFilePaths($userId);

        // project_materials.user_id et cameras.user_id n'ont pas de ON DELETE en base (ce sont des
        // ressources partagées de la famille) : on les réassigne au lieu de casser la suppression.
        $fallback = Database::fetch(
            "SELECT id FROM users WHERE family_id=? AND id!=? ORDER BY (role='admin') DESC, id LIMIT 1",
            [$familyId, $userId]
        );
        if ($fallback) {
            Database::execute('UPDATE project_materials SET user_id=? WHERE user_id=?', [$fallback['id'], $userId]);
            Database::execute('UPDATE cameras SET user_id=? WHERE user_id=?', [$fallback['id'], $userId]);
        }

        Database::execute('DELETE FROM users WHERE id=?', [$userId]);
        self::deleteFiles($filePaths);
    }

    /** Transfère le rôle admin à un autre membre puis supprime le compte de l'admin sortant. */
    public static function transferAdminAndDelete(int $outgoingAdminId, int $newAdminId, int $familyId): void
    {
        Database::execute("UPDATE users SET role='admin' WHERE id=? AND family_id=?", [$newAdminId, $familyId]);
        self::deleteUser($outgoingAdminId, $familyId);
    }

    /** Supprime toute la famille : tous les membres et toutes les données, en cascade. */
    public static function deleteFamily(int $familyId): void
    {
        $filePaths = self::familyFilePaths($familyId);

        Database::execute('DELETE FROM families WHERE id=?', [$familyId]);

        self::deleteFiles($filePaths);
        self::removeDirRecursive(BASE_PATH . '/storage/documents/' . $familyId);
        self::removeDirRecursive(BASE_PATH . '/storage/warranties/' . $familyId);
        self::removeDirRecursive(BASE_PATH . '/storage/voice/' . $familyId);
    }

    private const OWNED_FILE_TABLES = [
        'documents'         => 'file_path',
        'warranties'        => 'file_path',
        'posts'             => 'image_path',
        'messages'          => 'audio_path',
        'comm_log_messages' => 'audio_path',
    ];

    private static function userFilePaths(int $userId): array
    {
        $paths = [];
        $me = Database::fetch('SELECT avatar FROM users WHERE id=?', [$userId]);
        if (!empty($me['avatar'])) $paths[] = $me['avatar'];
        foreach (self::OWNED_FILE_TABLES as $table => $col) {
            foreach (Database::fetchAll("SELECT `$col` as p FROM `$table` WHERE user_id=?", [$userId]) as $row) {
                if (!empty($row['p'])) $paths[] = $row['p'];
            }
        }
        return $paths;
    }

    private static function familyFilePaths(int $familyId): array
    {
        $paths = [];
        foreach (Database::fetchAll('SELECT avatar FROM users WHERE family_id=?', [$familyId]) as $row) {
            if (!empty($row['avatar'])) $paths[] = $row['avatar'];
        }
        $tables = self::OWNED_FILE_TABLES + ['babies' => 'avatar', 'contacts' => 'avatar'];
        foreach ($tables as $table => $col) {
            foreach (Database::fetchAll("SELECT `$col` as p FROM `$table` WHERE family_id=?", [$familyId]) as $row) {
                if (!empty($row['p'])) $paths[] = $row['p'];
            }
        }
        return $paths;
    }

    private static function deleteFiles(array $paths): void
    {
        foreach (array_unique($paths) as $rel) {
            $abs = BASE_PATH . $rel;
            if (is_file($abs)) @unlink($abs);
        }
    }

    private static function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
