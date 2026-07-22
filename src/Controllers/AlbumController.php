<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Custody;

class AlbumController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('albums');
        $user = Session::user();
        $albums = Album::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/album/index.php';
    }

    public function show(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('albums');
        $user = Session::user();
        $album = Album::getById((int)$params['id']);
        if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
            header('Location: ' . BASE_URL . '/albums');
            exit;
        }
        $photos = AlbumPhoto::getByAlbum($album['id']);
        $canManage = $this->canManageAlbum($album, $user);
        $schedules = $user['role'] === 'admin' ? Custody::getSchedules($user['family_id']) : [];
        require BASE_PATH . '/templates/album/show.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $data  = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            if (!$title) return ['success' => false, 'error' => 'Le titre est obligatoire.'];

            $id = Album::create($user['family_id'], $user['id'], $title, trim($data['description'] ?? ''));
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }
            if (!$this->canManageAlbum($album, $user)) return ['success' => false, 'error' => 'Non autorisé.'];

            $data  = $this->jsonInput();
            $title = trim($data['title'] ?? '');
            if (!$title) return ['success' => false, 'error' => 'Le titre est obligatoire.'];

            Album::update($album['id'], $title, trim($data['description'] ?? ''));
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }
            if (!$this->canManageAlbum($album, $user)) return ['success' => false, 'error' => 'Non autorisé.'];

            foreach (AlbumPhoto::getByAlbum($album['id']) as $photo) {
                $this->deletePhotoFile($photo['image_path']);
            }
            Album::delete($album['id']);
            return ['success' => true];
        });
    }

    /** Partage/retire le partage d'un album avec un planning de garde (donc son co-parent) — admin uniquement. */
    public function share(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }

            $scheduleId = (int)($this->jsonInput()['schedule_id'] ?? 0);
            if ($scheduleId > 0) {
                $schedule = Custody::getScheduleById($scheduleId);
                if (!$schedule || (int)$schedule['family_id'] !== (int)$user['family_id']) {
                    return ['success' => false, 'error' => 'Planning introuvable.'];
                }
                Album::setCustodySchedule($album['id'], $scheduleId);
            } else {
                Album::setCustodySchedule($album['id'], null);
            }
            return ['success' => true];
        });
    }

    public function addPhoto(array $params): void
    {
        $this->requireAuth();
        $user  = Session::user();
        $album = Album::getById((int)$params['id']);
        if (!$album || (int)$album['family_id'] !== (int)$user['family_id'] || !$this->canManageAlbum($album, $user)) {
            Session::flash('error', 'Non autorisé.');
            header('Location: ' . BASE_URL . '/albums');
            exit;
        }

        $imagePath = $this->uploadImage('image');
        if (!$imagePath) {
            Session::flash('error', "L'image est trop volumineuse (max 20 Mo) ou dans un format non supporté (JPEG, PNG, GIF, WebP uniquement).");
            header('Location: ' . BASE_URL . '/albums/' . $album['id']);
            exit;
        }

        AlbumPhoto::create($album['id'], $user['id'], $imagePath, trim($_POST['caption'] ?? ''));
        header('Location: ' . BASE_URL . '/albums/' . $album['id']);
        exit;
    }

    public function deletePhoto(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $photo = AlbumPhoto::getById((int)$params['id']);
            if (!$photo || (int)$photo['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Photo introuvable.'];
            }
            if ($user['role'] !== 'admin' && (int)$photo['user_id'] !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Non autorisé.'];
            }

            $this->deletePhotoFile($photo['image_path']);
            AlbumPhoto::delete($photo['id']);
            return ['success' => true];
        });
    }

    private function canManageAlbum(array $album, array $user): bool
    {
        return $user['role'] === 'admin' || (int)$album['user_id'] === (int)$user['id'];
    }

    private function deletePhotoFile(?string $path): void
    {
        if ($path && file_exists(BASE_PATH . $path)) {
            @unlink(BASE_PATH . $path);
        }
    }
}
