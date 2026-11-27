<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Follow;
use App\Models\FamilyFriend;
use App\Models\Notification;
use App\Models\User;

/** Abonnements ("Amis" du mur social) entre membres d'une même famille, ou entre deux familles
 *  amies (App\Models\FamilyFriend) — jamais avec un compte co-parent, ni avec le membre d'une
 *  famille qui n'est pas amie de la mienne : l'amitié de famille reste la seule porte d'entrée
 *  vers des comptes extérieurs, pas de recherche libre parmi tous les utilisateurs FamilyBoard. */
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

    /** Un id de membre valide : même famille, ou membre d'une famille amie — jamais co-parent, jamais soi-même. */
    private function validTarget(int $targetId, array $user): ?array
    {
        if ($targetId === (int)$user['id']) return null;
        $target = User::findById($targetId);
        if (!$target || $target['role'] === 'coparent') return null;
        if ((int)$target['family_id'] === (int)$user['family_id']) return $target;
        if (FamilyFriend::areFriends((int)$user['family_id'], (int)$target['family_id'])) return $target;
        return null;
    }
}
