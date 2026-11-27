<?php
namespace App\Models;

use App\Core\Database;

class Referral
{
    /** Trace un parrainage à l'inscription d'une nouvelle famille — idempotent (une famille ne
     *  peut être parrainée qu'une seule fois, contrainte uq_referred en base). Retourne l'id créé,
     *  ou null si déjà tracé / auto-parrainage. */
    public static function create(int $referrerFamilyId, int $referredFamilyId): ?int
    {
        if ($referrerFamilyId === $referredFamilyId) return null;
        try {
            return Database::insert(
                'INSERT INTO referrals (referrer_family_id, referred_family_id) VALUES (?,?)',
                [$referrerFamilyId, $referredFamilyId]
            );
        } catch (\Throwable) {
            return null; // uq_referred : déjà parrainée par quelqu'un d'autre
        }
    }

    public static function getByReferrer(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT r.*, f.name as referred_family_name
             FROM referrals r JOIN families f ON f.id = r.referred_family_id
             WHERE r.referrer_family_id=? ORDER BY r.created_at DESC',
            [$familyId]
        );
    }

    public static function countByReferrer(int $familyId): int
    {
        $row = Database::fetch('SELECT COUNT(*) c FROM referrals WHERE referrer_family_id=?', [$familyId]);
        return (int)($row['c'] ?? 0);
    }

    public static function getAll(): array
    {
        return Database::fetchAll(
            'SELECT r.*, fr.name as referrer_family_name, fd.name as referred_family_name
             FROM referrals r
             JOIN families fr ON fr.id = r.referrer_family_id
             JOIN families fd ON fd.id = r.referred_family_id
             ORDER BY r.created_at DESC LIMIT 500'
        );
    }

    public static function markRewarded(int $id): void
    {
        Database::execute('UPDATE referrals SET reward_granted_at=NOW() WHERE id=?', [$id]);
    }
}
