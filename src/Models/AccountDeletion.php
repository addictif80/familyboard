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

        // project_materials.user_id n'a pas de ON DELETE en base (ce sont des
        // ressources partagées de la famille) : on le réassigne au lieu de casser la suppression.
        $fallback = Database::fetch(
            "SELECT id FROM users WHERE family_id=? AND id!=? ORDER BY (role='admin') DESC, id LIMIT 1",
            [$familyId, $userId]
        );
        if ($fallback) {
            Database::execute('UPDATE project_materials SET user_id=? WHERE user_id=?', [$fallback['id'], $userId]);
        }

        Database::execute('DELETE FROM users WHERE id=?', [$userId]);
        self::deleteFiles($filePaths);
    }

    /**
     * Supprime un accès co-parent (par l'admin de la famille ou par le co-parent lui-même) —
     * contrairement à deleteUser(), le contenu créé (documents, événements, journal, photos,
     * liens proposés) n'est jamais supprimé : le compte est détaché (user_id -> NULL) en
     * gardant le nom en clair, comme album_photos.uploader_name pour les ajouts via lien
     * public. Envoie d'abord un rapport PDF de toute l'activité du compte, pendant qu'elle
     * est encore reconstituable.
     */
    public static function deleteCoparent(int $userId): void
    {
        $user = Database::fetch('SELECT * FROM users WHERE id=?', [$userId]);
        if (!$user) return;

        try {
            \App\Models\CoparentReport::sendFinalReport($user);
        } catch (\Throwable $e) {
            error_log('Coparent report error: ' . $e->getMessage());
        }

        $name = $user['name'];
        Database::execute('UPDATE documents SET user_id=NULL, former_user_name=? WHERE user_id=?', [$name, $userId]);
        Database::execute('UPDATE events SET user_id=NULL, former_user_name=? WHERE user_id=?', [$name, $userId]);
        Database::execute('UPDATE comm_log_messages SET user_id=NULL, former_user_name=? WHERE user_id=?', [$name, $userId]);
        Database::execute('UPDATE album_photos SET user_id=NULL, uploader_name=COALESCE(uploader_name, ?) WHERE user_id=?', [$name, $userId]);
        Database::execute('UPDATE portal_links SET submitted_by=NULL, former_submitted_by_name=? WHERE submitted_by=?', [$name, $userId]);

        // Avatar personnel : supprimé (ce n'est pas du contenu familial, contrairement au
        // reste) — le fichier ci-dessus n'est jamais touché par cette méthode.
        if (!empty($user['avatar'])) {
            $abs = BASE_PATH . $user['avatar'];
            if (is_file($abs)) @unlink($abs);
        }

        Database::execute('DELETE FROM users WHERE id=?', [$userId]);
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
