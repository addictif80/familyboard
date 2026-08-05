<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\FamilyFriend;

class FriendController extends BaseController
{
    public function request(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $code = trim($data['code'] ?? '');
            if ($code === '') {
                return ['success' => false, 'error' => 'Veuillez indiquer un code famille.'];
            }
            return FamilyFriend::requestByCode((int)$user['family_id'], (int)$user['id'], $code);
        });
    }

    public function accept(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $ok = FamilyFriend::accept((int)$params['id'], (int)$user['family_id']);
            return ['success' => $ok];
        });
    }

    public function decline(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $ok = FamilyFriend::decline((int)$params['id'], (int)$user['family_id']);
            return ['success' => $ok];
        });
    }

    public function remove(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $ok = FamilyFriend::remove((int)$params['id'], (int)$user['family_id']);
            return ['success' => $ok];
        });
    }

    public function list(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            return [
                'accepted' => FamilyFriend::getAcceptedFor($familyId),
                'incoming' => FamilyFriend::getPendingIncoming($familyId),
                'outgoing' => FamilyFriend::getPendingOutgoing($familyId),
            ];
        });
    }
}
