<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Camera;
use App\Models\Family;

class CameraController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $cameras = Camera::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/cameras/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $id = Camera::create($user['family_id'], $user['id'], $this->jsonInput());
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $cam  = Camera::getById($id);
            if (!$cam || $cam['family_id'] !== $user['family_id']) return ['success' => false];
            Camera::update($id, $this->jsonInput());
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id   = (int)$params['id'];
            $cam  = Camera::getById($id);
            if (!$cam || $cam['family_id'] !== $user['family_id']) return ['success' => false];
            Camera::delete($id);
            return ['success' => true];
        });
    }

}
