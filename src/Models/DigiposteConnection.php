<?php
namespace App\Models;

use App\Core\Crypto;
use App\Core\DigiposteClient;
use App\Core\OcrHelper;
use App\Core\Database;

class DigiposteConnection
{
    public static function getByUser(int $userId): ?array
    {
        $row = Database::fetch('SELECT * FROM digiposte_connections WHERE user_id=?', [$userId]);
        if (!$row) return null;
        $row['access_token'] = Crypto::decrypt($row['access_token_enc']);
        $row['refresh_token'] = $row['refresh_token_enc'] ? Crypto::decrypt($row['refresh_token_enc']) : null;
        return $row;
    }

    public static function save(int $userId, string $accessToken, ?string $refreshToken, ?int $expiresIn, ?string $accountLabel = null): void
    {
        $expiresAt = $expiresIn ? (new \DateTime())->modify("+{$expiresIn} seconds")->format('Y-m-d H:i:s') : null;
        Database::execute(
            'INSERT INTO digiposte_connections (user_id, access_token_enc, refresh_token_enc, token_expires_at, account_label) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE access_token_enc=VALUES(access_token_enc), refresh_token_enc=VALUES(refresh_token_enc),
             token_expires_at=VALUES(token_expires_at), account_label=COALESCE(VALUES(account_label), account_label), last_sync_error=NULL',
            [$userId, Crypto::encrypt($accessToken), $refreshToken ? Crypto::encrypt($refreshToken) : null, $expiresAt, $accountLabel]
        );
    }

    /** Utilisateurs connectés dont le dernier import date de plus de $intervalMinutes (ou
     *  jamais synchronisés) — utilisé par le sync périodique du cron. */
    public static function getDueForSync(int $intervalMinutes): array
    {
        return Database::fetchAll(
            'SELECT u.* FROM digiposte_connections dc JOIN users u ON u.id=dc.user_id
             WHERE dc.last_synced_at IS NULL OR dc.last_synced_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$intervalMinutes]
        );
    }

    public static function disconnect(int $userId): void
    {
        Database::execute('DELETE FROM digiposte_connections WHERE user_id=?', [$userId]);
    }

    private static function markSynced(int $userId, ?string $error): void
    {
        Database::execute('UPDATE digiposte_connections SET last_synced_at=NOW(), last_sync_error=? WHERE user_id=?', [$error, $userId]);
    }

    private static function alreadyImported(int $userId, string $digiposteDocId): bool
    {
        return (bool)Database::fetch(
            'SELECT id FROM digiposte_imported_documents WHERE user_id=? AND digiposte_document_id=?',
            [$userId, $digiposteDocId]
        );
    }

    private static function recordImported(int $userId, string $digiposteDocId, int $documentId): void
    {
        Database::execute(
            'INSERT INTO digiposte_imported_documents (user_id, digiposte_document_id, document_id) VALUES (?,?,?)',
            [$userId, $digiposteDocId, $documentId]
        );
    }

    /**
     * Récupère un jeton d'accès valide (rafraîchi si besoin) pour un utilisateur connecté, ou
     * null si non connecté / rafraîchissement impossible (jeton révoqué côté Digiposte...).
     */
    private static function validAccessToken(array $connection): ?string
    {
        $expiresAt = $connection['token_expires_at'] ? new \DateTime($connection['token_expires_at']) : null;
        $needsRefresh = $expiresAt && $expiresAt <= (new \DateTime())->modify('+60 seconds');
        if (!$needsRefresh) return $connection['access_token'];

        if (!$connection['refresh_token']) return null;
        $tokens = DigiposteClient::refreshAccessToken($connection['refresh_token']);
        if (!$tokens) return null;

        self::save((int)$connection['user_id'], $tokens['access_token'], $tokens['refresh_token'] ?? $connection['refresh_token'], $tokens['expires_in']);
        return $tokens['access_token'];
    }

    /**
     * Importe les documents Digiposte pas encore connus de ce compte dans le module Documents de
     * sa famille. Utilisée aussi bien par l'action "Importer maintenant" (à la demande) que par
     * le sync périodique du cron. Ne fait jamais échouer l'appelant : toute erreur est
     * enregistrée dans last_sync_error plutôt que propagée.
     */
    public static function syncUser(array $user): array
    {
        $connection = self::getByUser((int)$user['id']);
        if (!$connection) return ['imported' => 0, 'error' => 'not_connected'];

        $accessToken = self::validAccessToken($connection);
        if (!$accessToken) {
            self::markSynced((int)$user['id'], 'Connexion expirée — reconnectez votre coffre Digiposte.');
            return ['imported' => 0, 'error' => 'token_expired'];
        }

        $documents = DigiposteClient::listDocuments($accessToken);
        if ($documents === null) {
            self::markSynced((int)$user['id'], 'Service Digiposte injoignable pour le moment.');
            return ['imported' => 0, 'error' => 'unreachable'];
        }

        $imported = 0;
        foreach ($documents as $doc) {
            if (self::alreadyImported((int)$user['id'], $doc['id'])) continue;
            try {
                $file = DigiposteClient::downloadDocument($accessToken, $doc['id']);
                if (!$file) continue;

                [$filePath, $mime] = OcrHelper::saveRemoteFile($file['bytes'], $file['mime'] ?: $doc['mime'], 'documents', (int)$user['family_id'], OcrHelper::DISPUTE_DOC_MIMES);

                $documentId = Database::insert(
                    'INSERT INTO documents (family_id, user_id, title, doc_type, tags, file_path, file_original, file_mime) VALUES (?,?,?,?,?,?,?,?)',
                    [(int)$user['family_id'], (int)$user['id'], $doc['name'], 'other', 'digiposte', $filePath, $doc['name'], $mime]
                );
                self::recordImported((int)$user['id'], $doc['id'], (int)$documentId);
                $imported++;
            } catch (\Throwable $e) {
                error_log('Digiposte import error (user ' . $user['id'] . ', doc ' . $doc['id'] . '): ' . $e->getMessage());
            }
        }

        self::markSynced((int)$user['id'], null);
        return ['imported' => $imported, 'error' => null];
    }
}
