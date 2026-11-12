<?php
namespace App\Core;

use App\Models\FamilyNextcloudFolder;
use App\Models\StoredFile;

/**
 * Donne un nom lisible, sur le Group Folder Nextcloud lui-même, aux fichiers déjà migrés avant
 * l'introduction de FileStore::nextcloudFilename() (auparavant : bin2hex(random_bytes(16)), voir
 * historique de NextcloudFileMigration/FileStore::saveToNextcloud). Renomme via WebDAV MOVE (voir
 * NextcloudFileClient::moveFile) puis répercute le nouveau chemin dans stored_files — sans cette
 * seconde étape, FamilyBoard chercherait toujours le fichier sous son ANCIEN nom et le perdrait
 * (404) : un renommage fait manuellement depuis l'interface Nextcloud romprait le lien de la même
 * façon, c'est pourquoi ce rattrapage doit passer par FamilyBoard et non par un renommage manuel.
 *
 * Idempotent : looksAlreadyReadable() reconnaît un nom déjà dérivé de l'original (même après un
 * premier passage de cet outil) et le saute, pour qu'un second lancement n'empile pas les
 * suffixes anti-collision à l'infini.
 */
class NextcloudFileRename
{
    public function __construct(private bool $apply) {}

    public function run(): array
    {
        $stats = ['renamed' => 0, 'already_ok' => 0, 'failed' => 0];
        $log = [];
        $failures = [];

        $rows = Database::fetchAll("SELECT * FROM stored_files WHERE storage_backend = 'nextcloud' ORDER BY family_id, id");
        $folderCache = [];

        foreach ($rows as $row) {
            $familyId = (int)$row['family_id'];
            if (!array_key_exists($familyId, $folderCache)) {
                $folderCache[$familyId] = FamilyNextcloudFolder::findByFamily($familyId);
            }
            $folder = $folderCache[$familyId];
            if (!$folder) {
                $stats['failed']++;
                $failures[] = "famille #{$familyId} : Group Folder introuvable (stored_files#{$row['id']}).";
                continue;
            }

            $currentBasename = basename($row['path']);
            $ext = pathinfo($row['path'], PATHINFO_EXTENSION);
            if (self::looksAlreadyReadable($currentBasename, $row['original_name'])) {
                $stats['already_ok']++;
                continue;
            }

            $dir = dirname($row['path']);
            $newPath = ($dir !== '.' ? $dir . '/' : '') . FileStore::nextcloudFilename($row['original_name'], $ext);

            if (!$this->apply) {
                $stats['renamed']++;
                $log[] = "[simulation] stored_files#{$row['id']} : {$row['path']} -> $newPath";
                continue;
            }

            $move = NextcloudFileClient::moveFile($folder['mount_point'], $row['path'], $newPath);
            if (!$move['ok']) {
                $stats['failed']++;
                $failures[] = "stored_files#{$row['id']} : " . $move['error'];
                continue;
            }

            StoredFile::markMigratedToNextcloud((int)$row['id'], $newPath, $move['etag']);
            $stats['renamed']++;
            $log[] = "OK stored_files#{$row['id']} : {$row['path']} -> $newPath";
        }

        return ['stats' => $stats, 'log' => $log, 'failures' => $failures, 'apply' => $this->apply];
    }

    /** Vrai si $currentBasename est déjà de la forme "<radical dérivé de $originalName>_<8 hex>.ext"
     *  (produite par FileStore::nextcloudFilename), qu'il ait été posé par cet outil lors d'un
     *  précédent passage ou directement à l'upload après le correctif. */
    private static function looksAlreadyReadable(string $currentBasename, ?string $originalName): bool
    {
        if (!preg_match('/^(.*)_[0-9a-f]{8}(\.[^.]+)?$/', $currentBasename, $m)) return false;
        $currentRadical = $m[1];

        $base = $originalName ? pathinfo($originalName, PATHINFO_FILENAME) : '';
        $ascii = $base !== '' ? (iconv('UTF-8', 'ASCII//TRANSLIT', $base) ?: $base) : '';
        $ascii = preg_replace('/[^A-Za-z0-9 _-]+/', '', $ascii) ?? '';
        $ascii = trim(preg_replace('/\s+/', '_', $ascii) ?? '', '_-');
        if ($ascii === '') $ascii = 'fichier';
        $expectedRadical = mb_substr($ascii, 0, 80);

        return $currentRadical === $expectedRadical;
    }
}
