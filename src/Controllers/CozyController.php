<?php
namespace App\Controllers;

use App\Core\CozyOAuthClient;
use App\Core\CozyProvisioner;
use App\Core\Session;
use App\Models\CozySettings;
use App\Models\Family;
use App\Models\FamilyCozyConnection;

/**
 * Parcours de connexion d'une famille à son instance Cozy Cloud (import automatique de
 * documents) : création de l'instance (via CozyProvisioner, avec connexion par lien magique
 * activée), puis autorisation OAuth2 en un seul aller-retour (voir CozyOAuthClient) — Cozy gère
 * lui-même la connexion de la famille (lien magique) au milieu de ce même parcours si elle n'est
 * pas encore authentifiée, avant le consentement. Réservé à l'admin de famille.
 */
class CozyController extends BaseController
{
    /** Point d'entrée unique "Configurer mes factures automatiques" — avance la famille à
     *  l'étape suivante selon l'état actuel de sa connexion. */
    public function connect(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        if ($user['role'] !== 'admin') {
            Session::flash('error', 'Seul un administrateur de famille peut configurer cette fonctionnalité.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $settings = CozySettings::get();
        if (!$settings) {
            Session::flash('error', "L'import automatique de factures n'est pas encore activé sur cette plateforme.");
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $familyId = (int)$user['family_id'];
        $connection = FamilyCozyConnection::findByFamily($familyId);

        if (!$connection) {
            $family = Family::findById($familyId);
            $cozyDomain = FamilyCozyConnection::generateDomain($family['name'] ?? ('famille-' . $familyId), $settings['domain']);

            $created = CozyProvisioner::createInstance($cozyDomain, $user['email']);
            if (!$created['ok']) {
                Session::flash('error', "Création de l'instance Cozy échouée : " . ($created['error'] ?? 'erreur inconnue'));
                header('Location: ' . BASE_URL . '/settings');
                exit;
            }

            $id = FamilyCozyConnection::createProvisioning($familyId, $cozyDomain);
            FamilyCozyConnection::markPendingOnboarding($id);
            $connection = ['id' => $id, 'cozy_domain' => $cozyDomain, 'client_id' => null, 'status' => 'pending_onboarding'];
            // On enchaîne directement sur l'autorisation OAuth ci-dessous : /auth/authorize de
            // Cozy affiche lui-même l'écran de connexion (avec "connexion par lien magique",
            // activée à la création — voir CozyProvisioner) à un visiteur non authentifié, puis
            // enchaîne sur le consentement et revient tout seul vers FamilyBoard. Pas besoin
            // d'un aller-retour séparé par https://$domain/.
        }

        if ($connection['status'] === 'connected') {
            Session::flash('success', 'Cozy est déjà connecté pour votre famille.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        // Instance déjà créée (reste à se connecter/autoriser) : lance/relance l'autorisation OAuth.
        $redirectUri = self::absoluteBaseUrl() . '/cozy/oauth/callback';
        if (!$connection['client_id']) {
            $registered = CozyOAuthClient::registerClient($connection['cozy_domain'], $redirectUri);
            if (!$registered['ok']) {
                Session::flash('error', "Connexion OAuth à Cozy échouée : " . $registered['error']);
                header('Location: ' . BASE_URL . '/settings');
                exit;
            }
            FamilyCozyConnection::storeOAuthClient((int)$connection['id'], $registered['client_id'], $registered['client_secret']);
            $connection['client_id'] = $registered['client_id'];
        }

        $state = bin2hex(random_bytes(16));
        Session::set('cozy_oauth_state', $state);
        Session::set('cozy_oauth_family_id', $familyId);

        header('Location: ' . CozyOAuthClient::authorizeUrl($connection['cozy_domain'], $connection['client_id'], $redirectUri, $state));
        exit;
    }

    public function oauthCallback(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = (int)$user['family_id'];

        $expectedState = Session::get('cozy_oauth_state');
        $expectedFamily = Session::get('cozy_oauth_family_id');
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';

        if (!$code || !$state || $state !== $expectedState || $familyId !== (int)$expectedFamily) {
            Session::flash('error', 'Autorisation Cozy invalide ou expirée — recommencez.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }
        Session::delete('cozy_oauth_state');
        Session::delete('cozy_oauth_family_id');

        $connection = FamilyCozyConnection::findByFamily($familyId);
        if (!$connection || !$connection['client_id'] || !$connection['client_secret']) {
            Session::flash('error', 'Connexion Cozy introuvable — recommencez depuis les réglages.');
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        $redirectUri = self::absoluteBaseUrl() . '/cozy/oauth/callback';
        $r = CozyOAuthClient::exchangeCode($connection['cozy_domain'], $connection['client_id'], $connection['client_secret'], $code, $redirectUri);
        if (!$r['ok']) {
            FamilyCozyConnection::markError((int)$connection['id'], $r['error']);
            Session::flash('error', "Échange du jeton Cozy échoué : " . $r['error']);
            header('Location: ' . BASE_URL . '/settings');
            exit;
        }

        FamilyCozyConnection::storeTokens((int)$connection['id'], $r['access_token'], $r['refresh_token'], $r['expires_in']);
        Session::flash('success', 'Cozy est maintenant connecté : vos factures seront importées automatiquement dans Documents.');
        header('Location: ' . BASE_URL . '/settings');
        exit;
    }

    public function disconnect(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] !== 'admin') return ['success' => false, 'error' => 'Réservé à un administrateur de famille.'];

            $familyId = (int)$user['family_id'];
            $connection = FamilyCozyConnection::findByFamily($familyId);
            if (!$connection) return ['success' => true];

            $destroyed = CozyProvisioner::destroyInstance($connection['cozy_domain']);
            if (!$destroyed['ok']) {
                return ['success' => false, 'error' => "Suppression de l'instance Cozy échouée : " . ($destroyed['error'] ?? 'erreur inconnue')];
            }

            FamilyCozyConnection::deleteByFamily($familyId);
            return ['success' => true];
        });
    }

    /** URL absolue (protocole+domaine+BASE_URL) — nécessaire pour redirect_uri OAuth, que Cozy
     *  redirige depuis le navigateur de la famille (un chemin relatif n'aurait pas de sens). */
    private static function absoluteBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
    }
}
