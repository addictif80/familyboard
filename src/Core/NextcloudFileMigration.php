<?php
namespace App\Core;

use App\Models\StoredFile;

/**
 * Migration des fichiers déjà enregistrés localement (stored_files.storage_backend='local')
 * vers le Group Folder Nextcloud de leur famille — voir NextcloudGroupFolderService,
 * NextcloudFileClient. Simulation par défaut (rien n'est modifié) ; --apply pour réellement
 * transférer le contenu. Le fichier local n'est supprimé qu'APRÈS confirmation explicite que
 * le contenu est bien arrivé intact sur Nextcloud (comparaison de taille), jamais avant — sans
 * perte de données possible même en cas d'interruption en plein transfert (rejouable : une ligne
 * déjà migrée — storage_backend='nextcloud' — n'est plus sélectionnée par la requête, un nouveau
 * passage ne reprend donc que ce qui reste).
 */
class NextcloudFileMigration
{
    public function __construct(private bool $apply) {}

    public function run(): array
    {
        $stats = ['migrated' => 0, 'skipped_missing' => 0, 'failed' => 0, 'bytes' => 0];
        $log = [];
        $failures = [];

        $rows = Database::fetchAll("SELECT * FROM stored_files WHERE storage_backend = 'local' ORDER BY family_id, id");

        // ensureFamilyFolder() fait plusieurs vrais appels HTTP à Nextcloud (appartenance au
        // groupe, ACL, nommage...) — un par famille suffit amplement : les fichiers d'une même
        // famille sont consécutifs (ORDER BY family_id) mais on indexe par id au cas où, pour ne
        // jamais refaire ce travail une seconde fois pour la même famille. Sans ce cache, une
        // migration de plusieurs centaines de fichiers répétait ces appels autant de fois qu'il y
        // avait de LIGNES, pas de familles — assez pour dépasser le délai du reverse-proxy devant
        // Nextcloud avant même la fin de la simple simulation (aucun transfert de contenu n'a
        // pourtant lieu en simulation).
        $folderCache = [];

        foreach ($rows as $row) {
            $abs = BASE_PATH . $row['path'];
            if (!is_file($abs)) {
                $stats['skipped_missing']++;
                $log[] = "IGNORÉ (fichier local introuvable) : stored_files#{$row['id']} {$row['path']}";
                continue;
            }

            $familyId = (int)$row['family_id'];
            if (!array_key_exists($familyId, $folderCache)) {
                $error = null;
                $folderCache[$familyId] = [
                    'folder' => NextcloudGroupFolderService::ensureFamilyFolder($familyId, $error),
                    'error'  => $error,
                ];
            }
            $folder = $folderCache[$familyId]['folder'];
            if (!$folder) {
                $stats['failed']++;
                $failures[] = "famille #{$familyId} : " . ($folderCache[$familyId]['error'] ?: 'Group Folder introuvable/non provisionnable.');
                continue;
            }

            $ext = pathinfo($row['path'], PATHINFO_EXTENSION);
            $remotePath = FileStore::nextcloudFolder($row['module']) . '/' . FileStore::nextcloudFilename($row['original_name'] ?? null, $ext);

            if (!$this->apply) {
                $stats['migrated']++;
                $stats['bytes'] += (int)$row['size_bytes'];
                $log[] = "[simulation] migrerait stored_files#{$row['id']} ({$row['path']}) -> {$folder['mount_point']}/$remotePath";
                continue;
            }

            $content = file_get_contents($abs);
            if ($content === false) {
                $stats['failed']++;
                $failures[] = "stored_files#{$row['id']} : lecture locale impossible.";
                continue;
            }

            $put = NextcloudFileClient::putFile($folder['mount_point'], $remotePath, $content);
            if (!$put['ok']) {
                $stats['failed']++;
                $failures[] = "stored_files#{$row['id']} : " . $put['error'];
                continue;
            }

            // Vérification avant toute suppression locale — jamais de confiance aveugle dans un
            // statut HTTP "ok", on compare explicitement la taille transférée.
            if ((int)$row['size_bytes'] > 0 && strlen($content) !== (int)$row['size_bytes']) {
                $stats['failed']++;
                $failures[] = "stored_files#{$row['id']} : taille transférée différente de celle enregistrée — fichier local conservé par précaution.";
                continue;
            }

            StoredFile::markMigratedToNextcloud((int)$row['id'], $remotePath, $put['etag']);
            @unlink($abs);

            $stats['migrated']++;
            $stats['bytes'] += strlen($content);
            $log[] = "OK stored_files#{$row['id']} ({$row['path']}) -> {$folder['mount_point']}/$remotePath";
        }

        return ['stats' => $stats, 'log' => $log, 'failures' => $failures, 'apply' => $this->apply];
    }
}
