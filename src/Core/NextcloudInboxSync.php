<?php
namespace App\Core;

use App\Models\FamilyNextcloudFolder;
use App\Models\NextcloudInboxFile;
use App\Models\Notification;
use App\Models\User;

/**
 * Détecte les fichiers déposés directement dans Nextcloud (hors FamilyBoard), dans le
 * sous-dossier dédié "à rattacher" de l'espace familial — et notifie pour proposer de les
 * rattacher à un enregistrement d'un module (voir NextcloudInboxController). Vérification
 * périodique (cron.php, throttlée) plutôt qu'un webhook Nextcloud : ce dernier n'offre rien de
 * plus rapide sans config serveur supplémentaire (workers dédiés en continu côté Nextcloud) —
 * choix explicite, pas une limitation technique qu'on ignore.
 */
class NextcloudInboxSync
{
    /** Sous-dossier à la racine de l'espace familial — en dehors des dossiers de module (voir
     *  FileStore::NEXTCLOUD_FOLDER_BY_MODULE), jamais écrit par FamilyBoard lui-même. */
    public const INBOX_FOLDER = 'a-rattacher';

    public static function syncAllFamilies(): void
    {
        if (!FileStore::nextcloudFilesEnabled()) return;
        foreach (FamilyNextcloudFolder::getAll() as $folder) {
            try {
                self::syncFamily((int)$folder['family_id'], $folder);
            } catch (\Throwable $e) {
                error_log('NextcloudInboxSync error for family ' . $folder['family_id'] . ': ' . $e->getMessage());
            }
        }
    }

    private static function syncFamily(int $familyId, array $folder): void
    {
        $items = NextcloudFileClient::listFolder($folder['mount_point'], self::INBOX_FOLDER);
        if ($items === null) return; // dossier absent ou erreur réseau : rien à faire, retenté au prochain passage

        foreach ($items as $item) {
            if (NextcloudInboxFile::findByFamilyAndPath($familyId, $item['path'])) continue; // déjà connu

            $uploader = $item['owner'] ? User::findByLdapUsername($item['owner'], $familyId) : null;
            NextcloudInboxFile::create($familyId, $item, $uploader['id'] ?? null);

            $title = '📂 Nouveau fichier déposé dans Nextcloud';
            $message = 'Le fichier « ' . $item['name'] . ' » a été déposé directement dans Nextcloud. Voulez-vous le rattacher à un module de FamilyBoard ?';
            $link = BASE_URL . '/nextcloud-inbox';
            if ($uploader) {
                Notification::create((int)$uploader['id'], 'nextcloud_inbox', $title, $message, $link);
            } else {
                // Propriétaire non résolu (compte Nextcloud sans ldap_username correspondant) :
                // les administrateurs restent le filet de sécurité.
                foreach (User::getByFamily($familyId) as $u) {
                    if ($u['role'] === 'admin') Notification::create((int)$u['id'], 'nextcloud_inbox', $title, $message, $link);
                }
            }
        }
    }
}
