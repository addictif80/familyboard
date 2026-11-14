<?php
namespace App\Core;

use App\Models\FamilyNextcloudFolder;
use App\Models\StoredFile;

/**
 * Remet au propre, sur le Group Folder Nextcloud lui-même, les fichiers écrits avant
 * l'introduction (1) d'un nom de fichier lisible (FileStore::nextcloudFilename — auparavant
 * bin2hex(random_bytes(16))) et (2) d'un nom de dossier de module en français
 * (FileStore::nextcloudFolder — auparavant la clé interne anglaise, ex. "warranties" au lieu de
 * "garanties"). Renomme/déplace via WebDAV MOVE (voir NextcloudFileClient::moveFile) puis
 * répercute le nouveau chemin dans stored_files — sans cette seconde étape, FamilyBoard
 * chercherait toujours le fichier sous son ANCIEN chemin et le perdrait (404) : un renommage fait
 * manuellement depuis l'interface Nextcloud romprait le lien de la même façon, c'est pourquoi ce
 * rattrapage doit passer par FamilyBoard et non par un renommage manuel.
 *
 * Idempotent : looksAlreadyCorrect() reconnaît un chemin déjà conforme (dossier ET nom de
 * fichier) et le saute, pour qu'un second lancement n'empile pas les suffixes anti-collision à
 * l'infini ni ne redéplace ce qui l'a déjà été. Une fois tous les fichiers d'un ancien dossier
 * anglais déplacés, ce dossier devenu vide est supprimé (uniquement s'il est vide — voir
 * NextcloudFileClient::deleteFolderIfEmpty).
 */
class NextcloudFileRename
{
    public function __construct(private bool $apply) {}

    public function run(): array
    {
        $stats = ['renamed' => 0, 'already_ok' => 0, 'failed' => 0, 'folders_cleaned' => 0];
        $log = [];
        $failures = [];

        $rows = Database::fetchAll("SELECT * FROM stored_files WHERE storage_backend = 'nextcloud' ORDER BY family_id, id");
        $folderCache = [];
        // (family_id, ancien dossier) -> true — anciens dossiers de module à essayer de nettoyer
        // une fois tous leurs fichiers déplacés.
        $oldFoldersToClean = [];

        foreach ($rows as $row) {
            $familyId = (int)$row['family_id'];
            if (!array_key_exists($familyId, $folderCache)) {
                $folderCache[$familyId] = FamilyNextcloudFolder::findByFamily($familyId);
            }
            $groupFolder = $folderCache[$familyId];
            if (!$groupFolder) {
                $stats['failed']++;
                $failures[] = "famille #{$familyId} : Group Folder introuvable (stored_files#{$row['id']}).";
                continue;
            }

            $currentDir = dirname($row['path']);
            $currentBasename = basename($row['path']);
            $ext = pathinfo($row['path'], PATHINFO_EXTENSION);
            $desiredDir = FileStore::nextcloudFolder($row['module']);

            if ($currentDir === $desiredDir && self::looksAlreadyReadable($currentBasename, $row['original_name'])) {
                $stats['already_ok']++;
                continue;
            }

            $newPath = $desiredDir . '/' . FileStore::nextcloudFilename($row['original_name'], $ext);

            if (!$this->apply) {
                $stats['renamed']++;
                $log[] = "[simulation] stored_files#{$row['id']} : {$row['path']} -> $newPath";
                continue;
            }

            $move = NextcloudFileClient::moveFile($groupFolder['mount_point'], $row['path'], $newPath);
            if (!$move['ok']) {
                $stats['failed']++;
                $failures[] = "stored_files#{$row['id']} : " . $move['error'];
                continue;
            }

            StoredFile::markMigratedToNextcloud((int)$row['id'], $newPath, $move['etag']);
            FileStore::repointReference($row['module'], $row['path'], $newPath);
            if ($currentDir !== $desiredDir && $currentDir !== '.') {
                $oldFoldersToClean[$familyId . '|' . $currentDir] = [$groupFolder['mount_point'], $currentDir];
            }
            $stats['renamed']++;
            $log[] = "OK stored_files#{$row['id']} : {$row['path']} -> $newPath";
        }

        if ($this->apply) {
            foreach ($oldFoldersToClean as [$mountPoint, $oldDir]) {
                if (NextcloudFileClient::deleteFolderIfEmpty($mountPoint, $oldDir)) {
                    $stats['folders_cleaned']++;
                    $log[] = "Dossier vidé supprimé : $mountPoint/$oldDir";
                }
            }
        }

        return ['stats' => $stats, 'log' => $log, 'failures' => $failures, 'apply' => $this->apply];
    }

    /** Vrai si $currentBasename est déjà de la forme "<radical dérivé de $originalName>_<8 hex>.ext"
     *  (produite par FileStore::nextcloudFilename), qu'il ait été posé par cet outil lors d'un
     *  précédent passage ou directement à l'upload après le correctif. Le dossier est vérifié
     *  séparément par l'appelant (comparaison directe, FileStore::nextcloudFolder() étant une
     *  simple table de correspondance déterministe). */
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
