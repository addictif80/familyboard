<?php
namespace App\Models;

use App\Core\Database;

/** Suppression de compte utilisateur / famille, avec nettoyage des fichiers orphelins. */
class AccountDeletion
{
    /**
     * Supprime le compte d'un utilisateur qui reste dans une famille non vide — membre
     * classique ou co-parent. Le contenu créé (documents, événements, journal parental,
     * photos, liens proposés) n'est jamais supprimé, quel que soit qui initie la suppression :
     * le compte est détaché de son contenu (user_id -> NULL, nom gardé en clair) plutôt que
     * supprimé en cascade, et tracé dans deleted_users pour qu'un administrateur système
     * puisse retrouver et, sur demande, purger définitivement ces données plus tard (voir
     * purgeDeletedUserData()). Un compte co-parent reçoit en plus un rapport PDF de toute son
     * activité par e-mail, avant la suppression, pendant qu'elle est encore reconstituable.
     *
     * $deletedBy : 'self' | 'family_admin' | 'system_admin' — qui a initié la suppression.
     */
    public static function deleteUser(int $userId, int $familyId, string $deletedBy = 'self'): void
    {
        $user = Database::fetch('SELECT * FROM users WHERE id=?', [$userId]);
        if (!$user) return;

        if ($user['role'] === 'coparent') {
            try {
                \App\Models\CoparentReport::sendFinalReport($user);
            } catch (\Throwable $e) {
                error_log('Coparent report error: ' . $e->getMessage());
            }
        }

        // project_materials.user_id n'a pas de ON DELETE en base (ce sont des ressources
        // partagées de la famille, pas un contenu personnel) : réassigné à un autre membre
        // plutôt que conservé nominativement, contrairement au reste ci-dessous.
        $fallback = Database::fetch(
            "SELECT id FROM users WHERE family_id=? AND id!=? ORDER BY (role='admin') DESC, id LIMIT 1",
            [$familyId, $userId]
        );
        if ($fallback) {
            Database::execute('UPDATE project_materials SET user_id=? WHERE user_id=?', [$fallback['id'], $userId]);
        }

        Database::execute(
            'INSERT INTO deleted_users (original_user_id, family_id, name, email, role, deleted_by) VALUES (?,?,?,?,?,?)',
            [$userId, $familyId, $user['name'], $user['email'], $user['role'], $deletedBy]
        );

        $name = $user['name'];
        Database::execute('UPDATE documents SET user_id=NULL, former_user_id=?, former_user_name=? WHERE user_id=?', [$userId, $name, $userId]);
        Database::execute('UPDATE events SET user_id=NULL, former_user_id=?, former_user_name=? WHERE user_id=?', [$userId, $name, $userId]);
        Database::execute('UPDATE comm_log_messages SET user_id=NULL, former_user_id=?, former_user_name=? WHERE user_id=?', [$userId, $name, $userId]);
        Database::execute('UPDATE album_photos SET user_id=NULL, former_user_id=?, uploader_name=COALESCE(uploader_name, ?) WHERE user_id=?', [$userId, $name, $userId]);
        Database::execute('UPDATE portal_links SET submitted_by=NULL, former_submitted_by_id=?, former_submitted_by_name=? WHERE submitted_by=?', [$userId, $name, $userId]);

        // Avatar personnel : supprimé (ce n'est pas du contenu familial, contrairement au
        // reste, qui est préservé ci-dessus).
        if (!empty($user['avatar'])) {
            $abs = BASE_PATH . $user['avatar'];
            if (is_file($abs)) @unlink($abs);
        }

        Database::execute('DELETE FROM users WHERE id=?', [$userId]);
    }

    /** Alias explicite pour les appels côté suppression d'un accès co-parent — même logique
     *  que deleteUser() (qui gère déjà le rapport PDF selon le rôle), $familyId retrouvé
     *  automatiquement puisqu'un co-parent est toujours rattaché à la famille qui l'a invité. */
    public static function deleteCoparent(int $userId, string $deletedBy = 'self'): void
    {
        $user = Database::fetch('SELECT family_id FROM users WHERE id=?', [$userId]);
        if (!$user) return;
        self::deleteUser($userId, (int)$user['family_id'], $deletedBy);
    }

    /**
     * Purge définitive des données conservées d'un compte supprimé — action irréversible,
     * réservée à un administrateur système (voir AdminController::purgeDeletedUser()).
     * Supprime aussi les fichiers associés (documents, pièces jointes du journal, photos,
     * aperçus de liens).
     */
    public static function purgeDeletedUserData(int $deletedUserRowId): void
    {
        $row = Database::fetch('SELECT * FROM deleted_users WHERE id=?', [$deletedUserRowId]);
        if (!$row || $row['purged_at']) return;
        $uid = (int)$row['original_user_id'];

        foreach (['documents' => 'file_path', 'comm_log_messages' => 'audio_path'] as $table => $fileCol) {
            foreach (Database::fetchAll("SELECT `$fileCol` as p FROM `$table` WHERE former_user_id=?", [$uid]) as $r) {
                if (!empty($r['p'])) {
                    $abs = BASE_PATH . $r['p'];
                    if (is_file($abs)) @unlink($abs);
                }
            }
        }
        foreach (Database::fetchAll('SELECT image_path FROM album_photos WHERE former_user_id=?', [$uid]) as $r) {
            if (!empty($r['image_path'])) {
                $abs = BASE_PATH . $r['image_path'];
                if (is_file($abs)) @unlink($abs);
            }
        }
        foreach (Database::fetchAll('SELECT image_path FROM portal_links WHERE former_submitted_by_id=?', [$uid]) as $r) {
            if (!empty($r['image_path'])) {
                $abs = BASE_PATH . $r['image_path'];
                if (is_file($abs)) @unlink($abs);
            }
        }

        Database::execute('DELETE FROM documents WHERE former_user_id=?', [$uid]);
        Database::execute('DELETE FROM events WHERE former_user_id=?', [$uid]);
        Database::execute('DELETE FROM comm_log_messages WHERE former_user_id=?', [$uid]);
        Database::execute('DELETE FROM album_photos WHERE former_user_id=?', [$uid]);
        Database::execute('DELETE FROM portal_links WHERE former_submitted_by_id=?', [$uid]);

        Database::execute('UPDATE deleted_users SET purged_at=NOW() WHERE id=?', [$deletedUserRowId]);
    }

    /** Comptes supprimés d'une famille (ou de toutes), avec leur statut de purge — pour
     *  l'onglet admin système « Comptes supprimés ». */
    public static function getDeletedUsers(): array
    {
        return Database::fetchAll(
            'SELECT du.*, f.name as family_name
             FROM deleted_users du JOIN families f ON f.id = du.family_id
             ORDER BY du.purged_at IS NOT NULL, du.deleted_at DESC'
        );
    }

    /** Transfère le rôle admin à un autre membre puis supprime le compte de l'admin sortant. */
    public static function transferAdminAndDelete(int $outgoingAdminId, int $newAdminId, int $familyId): void
    {
        Database::execute("UPDATE users SET role='admin' WHERE id=? AND family_id=?", [$newAdminId, $familyId]);
        self::deleteUser($outgoingAdminId, $familyId, 'self');
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
