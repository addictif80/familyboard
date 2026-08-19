<?php
namespace App\Core;

use App\Models\VaultwardenSettings;

/**
 * Client minimal pour l'API d'administration d'une instance Vaultwarden auto-hébergée
 * (https://github.com/dani-garcia/vaultwarden), utilisé uniquement pour déclencher
 * l'invitation par e-mail d'un membre FamilyBoard à créer son coffre — jamais pour lire ou
 * manipuler le contenu d'un coffre, auquel FamilyBoard n'a de toute façon jamais accès (chiffré
 * de bout en bout côté client, mot de passe maître inconnu de FamilyBoard).
 *
 * Le panneau d'administration Vaultwarden s'authentifie par un jeton unique (ADMIN_TOKEN, défini
 * dans la configuration du serveur Vaultwarden) : POST /admin avec ce jeton retourne un cookie de
 * session admin, réutilisé ensuite pour les appels à /admin/invite. Cette API n'est pas
 * officiellement documentée pour un usage programmatique tiers — un changement de version de
 * Vaultwarden peut en modifier le comportement ; le bouton "Tester la connexion" du panneau
 * /admin sert justement à vérifier que ça fonctionne toujours contre l'instance réelle.
 */
class Vaultwarden
{
    private const TIMEOUT = 15;

    /**
     * Authentifie le jeton admin et retourne le cookie de session à réutiliser, ou null si le
     * jeton est invalide / l'instance est injoignable.
     */
    private static function login(string $baseUrl, string $token): ?string
    {
        $ch = curl_init($baseUrl . '/admin');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['token' => $token]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) return null;
        // Connexion réussie : redirection (302) vers /admin avec un cookie de session posé.
        // Un jeton invalide renvoie 200 (la page de login elle-même), sans cookie.
        if ($status !== 302 && $status !== 200) return null;
        if (!preg_match('/Set-Cookie:\s*(VW_ADMIN=[^;]+)/i', $response, $m)) return null;
        return $m[1];
    }

    /**
     * Déclenche l'invitation Vaultwarden pour cet e-mail (mode SIGNUPS_ALLOWED=false +
     * INVITATIONS_ALLOWED=true attendu côté serveur) : Vaultwarden envoie lui-même le mail
     * d'invitation via son propre SMTP, FamilyBoard ne voit jamais le mot de passe maître choisi.
     */
    public static function inviteUser(string $email): array
    {
        $settings = VaultwardenSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => 'Vaultwarden non configuré (URL/jeton admin manquants).'];
        }

        $cookie = self::login($settings['url'], $settings['token']);
        if (!$cookie) {
            return ['ok' => false, 'error' => 'Connexion au panneau admin Vaultwarden refusée — vérifiez l\'URL et le jeton.'];
        }

        $ch = curl_init($settings['url'] . '/admin/invite');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['email' => $email]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Cookie: ' . $cookie],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'error' => ''];
        }
        return ['ok' => false, 'error' => "Échec de l'invitation Vaultwarden (HTTP $status)" . ($body ? " : " . mb_strimwidth(strip_tags((string)$body), 0, 200, '…') : '')];
    }

    /** Vérifie uniquement que l'URL et le jeton admin permettent de se connecter — n'invite personne. */
    public static function testConnection(): array
    {
        $settings = VaultwardenSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => 'URL et jeton admin requis.'];
        }
        $cookie = self::login($settings['url'], $settings['token']);
        if (!$cookie) {
            return ['ok' => false, 'error' => 'Connexion refusée — vérifiez l\'URL et le jeton admin.'];
        }
        return ['ok' => true, 'error' => ''];
    }
}
