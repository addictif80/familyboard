<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Follow;
use App\Models\Notification;
use App\Models\User;

/** Abonnements entre membres d'une même famille — jamais un compte co-parent, ni d'un membre
 *  d'une autre famille. Le "réseau social" du mur familial reste cloisonné par famille. */
class FollowController extends BaseController
{
    public function request(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $targetId = (int)($this->jsonInput()['user_id'] ?? 0);
            $target = $this->validTarget($targetId, $user);
            if (!$target) return ['success' => false, 'error' => 'Membre introuvable.'];

            Follow::request((int)$user['id'], $targetId);
            Notification::create($targetId, 'wall', 'Nouvelle demande d\'abonnement',
                $user['name'] . ' souhaite vous suivre.', BASE_URL . '/wall');
            return ['success' => true];
        });
    }

    public function accept(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $followerId = (int)($this->jsonInput()['user_id'] ?? 0);
            if (Follow::status($followerId, (int)$user['id']) !== 'pending') {
                return ['success' => false, 'error' => 'Demande introuvable.'];
            }
            Follow::accept($followerId, (int)$user['id']);
            Notification::create($followerId, 'wall', 'Abonnement accepté',
                $user['name'] . ' a accepté votre demande d\'abonnement.', BASE_URL . '/wall');
            return ['success' => true];
        });
    }

    /** Sert à la fois à refuser une demande reçue et à se désabonner d'un abonnement accepté —
     *  dans les deux cas, l'appelant est le "follower" de la ligne à retirer. */
    public function removeAsFollower(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $targetId = (int)($this->jsonInput()['user_id'] ?? 0);
            Follow::remove((int)$user['id'], $targetId);
            return ['success' => true];
        });
    }

    /** Retire un de mes abonnés (l'appelant est le "followee" de la ligne à retirer). */
    public function removeFollower(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $followerId = (int)($this->jsonInput()['user_id'] ?? 0);
            Follow::remove($followerId, (int)$user['id']);
            return ['success' => true];
        });
    }

    /** Un id de membre valide : même famille, pas co-parent, pas soi-même. */
    private function validTarget(int $targetId, array $user): ?array
    {
        if ($targetId === (int)$user['id']) return null;
        $target = User::findById($targetId);
        if (!$target || $target['family_id'] !== $user['family_id'] || $target['role'] === 'coparent') return null;
        return $target;
    }
}
