<?php
namespace App\Core;

use App\Models\CozyImportedFile;
use App\Models\Document;
use App\Models\FamilyCozyConnection;

/**
 * Import automatique des documents Cozy (dossier Administratif rempli par les Konnectors) dans
 * le module Documents — appelé par cron.php (throttlé, voir cette classe utilisée comme
 * NextcloudCalendarSync). Rapatrie chaque nouveau fichier via FileStore::save('documents', ...)
 * (donc stocké comme un upload classique — Nextcloud si activé, comme le reste) puis le supprime
 * côté Cozy : Cozy n'est jamais qu'un sas de transit, jamais un second entrepôt permanent.
 *
 * Nécessite le backend Nextcloud actif (storage_nextcloud_files_enabled=1) pour fonctionner :
 * FileStore::save() route alors vers l'écriture WebDAV (file_get_contents), jamais
 * move_uploaded_file() — qui échouerait sur le fichier temporaire synthétique créé ici (il n'a
 * jamais transité par une requête HTTP). Sans Nextcloud actif, l'import échoue proprement
 * (exception capturée, famille ignorée, pas de crash) plutôt que d'écrire n'importe quoi.
 */
class CozyDocumentSync
{
    public static function syncAllFamilies(): void
    {
        foreach (FamilyCozyConnection::getConnected() as $connection) {
            try {
                self::syncFamily($connection);
            } catch (\Throwable $e) {
                error_log('CozyDocumentSync famille #' . $connection['family_id'] . ' : ' . $e->getMessage());
                FamilyCozyConnection::markError((int)$connection['id'], $e->getMessage());
            }
        }
    }

    private static function syncFamily(array $connection): void
    {
        $connection = self::ensureFreshToken($connection);
        if (!$connection) return;

        $familyId = (int)$connection['family_id'];
        $adminId = self::familyAdminId($familyId);
        if (!$adminId) return; // famille sans administrateur résolu : rien à attribuer, on réessaiera au prochain passage

        foreach (CozyFileSync::listAdministratifFiles($connection) as $file) {
            if (CozyImportedFile::isImported($familyId, $file['id'])) continue;
            self::importOne($connection, $familyId, $adminId, $file);
        }

        FamilyCozyConnection::markSynced((int)$connection['id']);
    }

    private static function importOne(array $connection, int $familyId, int $adminId, array $file): void
    {
        $content = CozyFileSync::download($connection, $file['id']);
        if ($content === null) return; // réessaiera au prochain passage

        $tmpPath = tempnam(sys_get_temp_dir(), 'cozy');
        file_put_contents($tmpPath, $content);
        try {
            $fakeUpload = [
                'name'     => $file['name'],
                'type'     => $file['mime'] ?: 'application/pdf',
                'tmp_name' => $tmpPath,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($content),
            ];
            $title = pathinfo($file['name'], PATHINFO_FILENAME) ?: $file['name'];
            $documentId = Document::create($familyId, $adminId, ['title' => $title, 'doc_type' => 'auto'], $fakeUpload);

            CozyImportedFile::markImported($familyId, $file['id'], $documentId);
            CozyFileSync::trash($connection, $file['id']);
        } catch (\Throwable $e) {
            error_log('CozyDocumentSync import fichier ' . $file['id'] . ' (famille #' . $familyId . ') échoué : ' . $e->getMessage());
        } finally {
            @unlink($tmpPath);
        }
    }

    private static function familyAdminId(int $familyId): ?int
    {
        $row = Database::fetch("SELECT id FROM users WHERE family_id = ? AND role = 'admin' ORDER BY id LIMIT 1", [$familyId]);
        return $row ? (int)$row['id'] : null;
    }

    /** Rafraîchit le jeton d'accès s'il expire dans moins de 5 minutes — renvoie la connexion
     *  (avec le jeton à jour) prête à l'emploi, ou null si le rafraîchissement échoue (connexion
     *  marquée en erreur, à reconnecter par la famille). */
    private static function ensureFreshToken(array $connection): ?array
    {
        if ($connection['token_expires_at'] && strtotime($connection['token_expires_at']) > time() + 300) {
            return $connection;
        }

        $r = CozyOAuthClient::refreshToken(
            $connection['cozy_domain'], $connection['client_id'], $connection['client_secret'], $connection['refresh_token']
        );
        if (!$r['ok']) {
            FamilyCozyConnection::markError((int)$connection['id'], 'Rafraîchissement du jeton OAuth Cozy échoué : ' . $r['error']);
            return null;
        }
        FamilyCozyConnection::storeTokens((int)$connection['id'], $r['access_token'], $r['refresh_token'], $r['expires_in']);
        return FamilyCozyConnection::findByFamily((int)$connection['family_id']);
    }
}
