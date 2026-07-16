<?php
namespace App\Models;

use App\Core\Database;

/**
 * Connexion d'un membre à sa propre instance Kresus (auto-hébergée, une
 * instance par membre — Kresus ne gère pas nativement plusieurs utilisateurs
 * isolés dans une même instance). FamilyBoard ne stocke jamais les identifiants
 * bancaires : seuls l'URL de l'instance et ses identifiants Basic Auth (posés
 * par le reverse-proxy) sont conservés ici.
 *
 * L'endpoint /api/v1/all n'est pas documenté officiellement par Kresus : à
 * vérifier après chaque montée de version (voir deploy/kresus/README.md).
 */
class KresusAccount
{
    private const ALL_ENDPOINT = '/api/v1/all';

    public static function getByUser(int $userId): ?array
    {
        return Database::fetch('SELECT * FROM kresus_accounts WHERE user_id=?', [$userId]);
    }

    /** Toutes les connexions actives, tous membres confondus (pour le cron). */
    public static function getAllActive(): array
    {
        return Database::fetchAll(
            'SELECT ka.*, u.name as user_name, f.timezone
             FROM kresus_accounts ka
             JOIN users u ON u.id = ka.user_id
             JOIN families f ON f.id = ka.family_id'
        );
    }

    public static function connect(int $familyId, int $userId, string $kresusUrl, ?string $username, ?string $password): void
    {
        Database::execute(
            'INSERT INTO kresus_accounts (family_id, user_id, kresus_url, kresus_username, kresus_password)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE kresus_url=VALUES(kresus_url), kresus_username=VALUES(kresus_username),
                                     kresus_password=VALUES(kresus_password), last_sync_status=NULL, last_sync_error=NULL',
            [$familyId, $userId, rtrim($kresusUrl, '/'), $username ?: null, $password ?: null]
        );
    }

    public static function disconnect(int $userId): void
    {
        Database::execute('DELETE FROM kresus_accounts WHERE user_id=?', [$userId]);
    }

    private static function recordSyncResult(int $id, bool $ok, ?string $error = null): void
    {
        Database::execute(
            'UPDATE kresus_accounts SET last_sync_at=NOW(), last_sync_status=?, last_sync_error=? WHERE id=?',
            [$ok ? 'ok' : 'error', $error ? mb_substr($error, 0, 500) : null, $id]
        );
    }

    /**
     * Synchronise les transactions d'un membre depuis sa propre instance Kresus.
     * Best-effort : toute erreur (réseau, format inattendu) est journalisée sur
     * la connexion sans jamais interrompre le reste du cron.
     *
     * @return array{ok:bool,count:int,error:?string}
     */
    public static function syncOne(array $account): array
    {
        try {
            $payload = self::fetchAll($account);
        } catch (\Throwable $e) {
            self::recordSyncResult((int)$account['id'], false, $e->getMessage());
            return ['ok' => false, 'count' => 0, 'error' => $e->getMessage()];
        }

        $operations = $payload['transactions'] ?? $payload['operations'] ?? null;
        if (!is_array($operations)) {
            $error = 'Réponse Kresus inattendue (pas de transactions).';
            self::recordSyncResult((int)$account['id'], false, $error);
            return ['ok' => false, 'count' => 0, 'error' => $error];
        }

        $categoryNames = [];
        foreach ($payload['categories'] ?? [] as $cat) {
            if (isset($cat['id'], $cat['label'])) $categoryNames[$cat['id']] = $cat['label'];
        }

        $count = 0;
        foreach ($operations as $op) {
            $opId = $op['id'] ?? $op['operationId'] ?? null;
            $amount = (float)($op['amount'] ?? 0);
            $title  = trim($op['label'] ?? $op['rawLabel'] ?? 'Transaction Kresus');
            $date   = $op['date'] ?? $op['debitDate'] ?? null;
            if ($opId === null || !$date || $amount === 0.0) continue;
            $extId = 'kresus-' . $account['user_id'] . '-' . $opId;

            $categoryId = null;
            if (isset($op['categoryId'], $categoryNames[$op['categoryId']])) {
                $categoryId = Budget::matchOrCreateCategory((int)$account['family_id'], $categoryNames[$op['categoryId']]);
            }

            $inserted = Budget::createFromExternal((int)$account['family_id'], (int)$account['user_id'], [
                'category_id' => $categoryId,
                'title'       => $title,
                'amount'      => abs($amount),
                'type'        => $amount >= 0 ? 'income' : 'expense',
                'date'        => substr($date, 0, 10),
                'source'      => 'kresus',
                'external_id' => $extId,
            ]);
            if ($inserted) $count++;
        }

        self::recordSyncResult((int)$account['id'], true, null);
        return ['ok' => true, 'count' => $count, 'error' => null];
    }

    /** @throws \RuntimeException */
    private static function fetchAll(array $account): array
    {
        $url = $account['kresus_url'] . self::ALL_ENDPOINT;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        if (!empty($account['kresus_username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $account['kresus_username'] . ':' . ($account['kresus_password'] ?? ''));
        }
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) throw new \RuntimeException('Connexion à Kresus impossible : ' . $curlError);
        if ($status < 200 || $status >= 300) throw new \RuntimeException("Kresus a répondu HTTP {$status}");

        $data = json_decode($body, true);
        if (!is_array($data)) throw new \RuntimeException('Réponse Kresus non-JSON.');

        return $data;
    }

    /** Ping simple pour valider les identifiants au moment de la connexion. */
    public static function testConnection(string $kresusUrl, ?string $username, ?string $password): ?string
    {
        try {
            self::fetchAll([
                'kresus_url'      => rtrim($kresusUrl, '/'),
                'kresus_username' => $username,
                'kresus_password' => $password,
            ]);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
