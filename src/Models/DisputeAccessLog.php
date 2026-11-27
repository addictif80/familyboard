<?php
namespace App\Models;

use App\Core\Database;

/** Journal des ouvertures du lien public d'un dossier de litige — valeur probatoire pour le
 *  litige lui-même (savoir qui a pu consulter les pièces), donc jamais purgé automatiquement. */
class DisputeAccessLog
{
    public static function record(int $disputeId, ?string $ip): void
    {
        Database::insert(
            'INSERT INTO dispute_share_access_log (dispute_id, ip_address) VALUES (?,?)',
            [$disputeId, $ip]
        );
    }

    public static function getByDispute(int $disputeId): array
    {
        return Database::fetchAll(
            'SELECT * FROM dispute_share_access_log WHERE dispute_id=? ORDER BY accessed_at DESC',
            [$disputeId]
        );
    }
}
