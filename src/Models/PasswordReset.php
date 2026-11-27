<?php
namespace App\Models;

use App\Core\Database;

/** Jetons de réinitialisation de mot de passe — même schéma que Invitation (jeton opaque
 *  à usage unique, expiration courte) mais volontairement séparé : les deux évoluent pour
 *  des besoins différents et ne doivent jamais partager une table. */
class PasswordReset
{
    public static function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        // Bien plus court que les 7 jours d'une invitation familiale : une réinitialisation de
        // mot de passe est une action sensible, la fenêtre d'exploitation d'un lien intercepté
        // (boîte mail partagée, lien resté ouvert...) doit rester minime.
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        Database::insert(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)',
            [$userId, $token, $expires]
        );
        return $token;
    }

    public static function getByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT pr.*, u.email, u.name FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL',
            [$token]
        );
    }

    public static function markUsed(string $token): void
    {
        Database::execute('UPDATE password_resets SET used_at = NOW() WHERE token = ?', [$token]);
    }
}
