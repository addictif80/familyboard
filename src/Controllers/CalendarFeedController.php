<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\CalendarFeed;

class CalendarFeedController extends BaseController
{
    /** GET /calendar-feed/:token.ics — public, sans session : une app calendrier de téléphone
     *  interroge ce lien seule, en arrière-plan, ce qu'aucune authentification par cookie ne
     *  permettrait. La sécurité repose entièrement sur l'imprévisibilité du jeton (voir
     *  CalendarFeed::regenerateToken()), comme tous les autres liens publics de l'app
     *  (coffre-fort, listes, carnet de santé...). */
    public function ics(array $params): void
    {
        $token = preg_replace('/\.ics$/', '', $params['token'] ?? '');
        $user = CalendarFeed::findUserByToken($token);
        if (!$user) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="familyboard.ics"');
        echo CalendarFeed::buildIcs($user);
    }

    /** POST /api/calendar-feed/token — génère le jeton au premier appel, le renvoie sinon
     *  (idempotent : rouvrir la page des réglages ne doit pas invalider le lien déjà donné à
     *  son téléphone). */
    public function getToken(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $token = CalendarFeed::getOrCreateToken((int)$user['id']);
            return ['success' => true, 'url' => $this->feedUrl($token)];
        });
    }

    /** POST /api/calendar-feed/regenerate — invalide l'ancien lien (ex. téléphone perdu/revendu)
     *  et en génère un nouveau : toute app déjà abonnée avec l'ancien jeton cesse de recevoir
     *  des mises à jour. */
    public function regenerate(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $token = CalendarFeed::regenerateToken((int)$user['id']);
            return ['success' => true, 'url' => $this->feedUrl($token)];
        });
    }

    private function feedUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . BASE_URL . '/calendar-feed/' . $token . '.ics';
    }
}
