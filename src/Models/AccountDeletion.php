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
     * $reason : motif optionnel (saisi par un admin système) — conservé avec un instantané des
     * données personnelles (page HTML, voir DataExport::buildHtmlPage()) pour permettre de
     * renvoyer le rapport plus tard, y compris après une purge définitive du contenu (voir
     * resendDeletionReport() et App\Controllers\DeletedAccountController pour la demande en
     * libre-service par l'ex-titulaire du compte).
     */
    public static function deleteUser(int $userId, int $familyId, string $deletedBy = 'self', ?string $reason = null): void
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

        // Instantané des données personnelles AVANT anonymisation du contenu ci-dessous (le
        // contenu est identifié par user_id, sur le point d'être mis à NULL) — voir docblock.
        $exportHtml = null;
        try {
            $exportData = DataExport::collect($userId, $familyId, false);
            unset($exportData['_file_paths']);
            $exportHtml = DataExport::buildHtmlPage($exportData, $user['name']);
        } catch (\Throwable $e) {
            error_log('Account deletion data export snapshot error: ' . $e->getMessage());
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
            'INSERT INTO deleted_users (original_user_id, family_id, name, email, role, deleted_by, reason, data_export_html) VALUES (?,?,?,?,?,?,?,?)',
            [$userId, $familyId, $user['name'], $user['email'], $user['role'], $deletedBy, $reason, $exportHtml]
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

        \App\Core\Mailcow::syncFamily($familyId);
    }

    /** Alias explicite pour les appels côté suppression d'un accès co-parent — même logique
     *  que deleteUser() (qui gère déjà le rapport PDF selon le rôle), $familyId retrouvé
     *  automatiquement puisqu'un co-parent est toujours rattaché à la famille qui l'a invité. */
    public static function deleteCoparent(int $userId, string $deletedBy = 'self', ?string $reason = null): void
    {
        $user = Database::fetch('SELECT family_id FROM users WHERE id=?', [$userId]);
        if (!$user) return;
        self::deleteUser($userId, (int)$user['family_id'], $deletedBy, $reason);
    }

    /**
     * Renvoie l'e-mail "compte supprimé" (motif + page de données jointe si disponible) à
     * partir d'une ligne deleted_users déjà chargée — utilisé aussi bien par l'admin système
     * (bouton "Renvoyer le rapport") que par la demande en libre-service d'un ex-titulaire de
     * compte (App\Controllers\DeletedAccountController). Fonctionne même après une purge
     * définitive du contenu : le motif et l'export sont un instantané indépendant, jamais
     * effacés par purgeDeletedUserData().
     */
    public static function resendDeletionReport(array $deletedUserRow): bool
    {
        // Compte supprimé avant l'ajout de cet instantané (ou par une voie qui n'en capture
        // pas) : rien de fiable à renvoyer. Envoyer quand même un e-mail générique imputerait
        // à tort la suppression à "un administrateur système" avec un motif "non précisé" —
        // trompeur pour un compte en réalité auto-supprimé ou retiré par son admin de famille.
        if (empty($deletedUserRow['reason']) && empty($deletedUserRow['data_export_html'])) {
            return false;
        }

        try {
            // Le texte doit refléter qui a réellement supprimé le compte : renvoyer ce rapport
            // pour une auto-suppression ou un retrait par l'admin de famille en affirmant "par
            // un administrateur système" serait faux, quelle que soit l'origine de la demande
            // de renvoi (bouton admin ou libre-service).
            $deletedByText = match ($deletedUserRow['deleted_by'] ?? '') {
                'system_admin' => ' par un administrateur système',
                'family_admin' => " par l'administrateur de votre famille",
                default        => '',
            };
            $rendered = EmailContent::render('account_deleted', [
                'user_name'  => $deletedUserRow['name'],
                'deleted_by' => $deletedByText,
                'reason'     => $deletedUserRow['reason'] ?: 'Non précisé.',
            ]);
            $html = \App\Core\EmailLayout::render($rendered['subject'], $rendered['message_html']);
            $attachments = [];
            if (!empty($deletedUserRow['data_export_html'])) {
                $attachments[] = [
                    'filename' => 'mes-donnees-' . date('Y-m-d') . '.html',
                    'content'  => $deletedUserRow['data_export_html'],
                    'mime'     => 'text/html',
                ];
            }
            return \App\Core\Mail::send(
                (int)$deletedUserRow['family_id'],
                $deletedUserRow['email'],
                $deletedUserRow['name'],
                $rendered['subject'],
                $html,
                'account_deleted',
                null,
                $attachments
            );
        } catch (\Throwable $e) {
            error_log('Resend deletion report error: ' . $e->getMessage());
            return false;
        }
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
        $mailAliasSlug = Database::fetch('SELECT mail_alias_slug FROM families WHERE id=?', [$familyId])['mail_alias_slug'] ?? null;

        // event_shares / event_share_changes / family_friends n'ont volontairement plus de
        // contrainte de clé étrangère (cf. database/add_family_sharing_no_fk.sql) : nettoyage
        // manuel ici pour éviter les lignes orphelines que le ON DELETE CASCADE gérait avant.
        Database::execute(
            'DELETE FROM event_share_changes WHERE event_share_id IN (
                SELECT id FROM (SELECT id FROM event_shares WHERE origin_family_id=? OR target_family_id=?) t
            )',
            [$familyId, $familyId]
        );
        Database::execute('DELETE FROM event_shares WHERE origin_family_id=? OR target_family_id=?', [$familyId, $familyId]);
        Database::execute('DELETE FROM family_friends WHERE requester_family_id=? OR target_family_id=?', [$familyId, $familyId]);

        Database::execute('DELETE FROM families WHERE id=?', [$familyId]);

        \App\Core\Mailcow::deleteFamilyAlias($mailAliasSlug);

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
