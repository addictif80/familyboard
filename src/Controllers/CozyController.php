<?php
namespace App\Controllers;

use App\Core\CozyOAuthClient;
use App\Core\CozyProvisioner;
use App\Core\Session;
use App\Models\CozySettings;
use App\Models\Family;
use App\Models\FamilyCozyConnection;

/**
 * Connexion d'une famille à son instance Cozy Cloud (import automatique de documents) :
 * création de l'instance si besoin (CozyProvisioner), enregistrement OAuth dynamique
 * (CozyOAuthClient) puis génération directe du jeton d'accès côté serveur
 * (CozyProvisioner::mintToken(), cozy-stack instances token-oauth) — aucune étape navigateur,
 * aucune interaction de la famille. Réservé à l'admin de famille.
 */
class CozyController extends BaseController
{
    private const SCOPE = 'io.cozy.files';

    /** "Configurer mes factures automatiques" — fait tout en un seul appel : crée l'instance si
     *  besoin, l'enregistre comme client OAuth, génère son jeton d'accès. */
    public function connect(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] !== 'admin') {
                return ['success' => false, 'error' => 'Seul un administrateur de famille peut configurer cette fonctionnalité.'];
            }

            $settings = CozySettings::get();
            if (!$settings) {
                return ['success' => false, 'error' => "L'import automatique de factures n'est pas encore activé sur cette plateforme."];
            }

            $familyId = (int)$user['family_id'];
            $connection = FamilyCozyConnection::findByFamily($familyId);

            if ($connection && $connection['status'] === 'connected') {
                return ['success' => true, 'already_connected' => true];
            }

            if (!$connection) {
                $family = Family::findById($familyId);
                $cozyDomain = FamilyCozyConnection::generateDomain($family['name'] ?? ('famille-' . $familyId), $settings['domain']);

                $created = CozyProvisioner::createInstance($cozyDomain, $user['email']);
                if (!$created['ok']) {
                    return ['success' => false, 'error' => "Création de l'instance Cozy échouée : " . ($created['error'] ?? 'erreur inconnue')];
                }

                $id = FamilyCozyConnection::createProvisioning($familyId, $cozyDomain);
                $connection = ['id' => $id, 'cozy_domain' => $cozyDomain, 'client_id' => null];
            }

            // Un placeholder suffit : cette redirect_uri n'est jamais réellement utilisée (voir
            // CozyProvisioner::mintToken(), qui génère le jeton sans aucun parcours navigateur) —
            // seule l'API d'enregistrement dynamique de Cozy en exige une (RFC 7591).
            if (!$connection['client_id']) {
                $registered = CozyOAuthClient::registerClient($connection['cozy_domain'], self::absoluteBaseUrl() . '/');
                if (!$registered['ok']) {
                    FamilyCozyConnection::markError((int)$connection['id'], $registered['error']);
                    return ['success' => false, 'error' => "Connexion OAuth à Cozy échouée : " . $registered['error']];
                }
                FamilyCozyConnection::storeOAuthClient((int)$connection['id'], $registered['client_id']);
                $connection['client_id'] = $registered['client_id'];
            }

            $token = CozyProvisioner::mintToken($connection['cozy_domain'], $connection['client_id'], self::SCOPE);
            if (!$token['ok']) {
                FamilyCozyConnection::markError((int)$connection['id'], $token['error']);
                return ['success' => false, 'error' => "Génération du jeton Cozy échouée : " . $token['error']];
            }
            FamilyCozyConnection::storeToken((int)$connection['id'], $token['access_token']);

            return ['success' => true];
        });
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

    /** URL absolue (protocole+domaine+BASE_URL) — seulement pour l'enregistrement OAuth
     *  dynamique (RFC 7591 exige une redirect_uri valide), jamais réellement utilisée en
     *  pratique (voir mintToken()). */
    private static function absoluteBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
    }
}
