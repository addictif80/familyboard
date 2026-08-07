<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\AlbumShareLink;
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
        $shareLink = $user['role'] === 'admin' ? AlbumShareLink::getByAlbum($album['id']) : null;
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

    /** Affiche/retire un album de l'onglet "Photos" du mur, avec sa portée — propriétaire ou admin famille. */
    public function setWallScope(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }
            if (!$this->canManageAlbum($album, $user)) return ['success' => false, 'error' => 'Non autorisé.'];

            $scope = $this->jsonInput()['scope'] ?? null;
            if ($scope !== null && !in_array($scope, ['personal', 'family', 'network'], true)) {
                return ['success' => false, 'error' => 'Portée invalide.'];
            }
            Album::setWallScope($album['id'], $scope);
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

            $scheduleIds = array_values(array_unique(array_map('intval', (array)($this->jsonInput()['schedule_ids'] ?? []))));
            foreach ($scheduleIds as $scheduleId) {
                $schedule = Custody::getScheduleById($scheduleId);
                if (!$schedule || (int)$schedule['family_id'] !== (int)$user['family_id']) {
                    return ['success' => false, 'error' => 'Planning introuvable.'];
                }
            }
            Album::setCustodySchedules($album['id'], $scheduleIds);
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

    // ── Lien de partage public (admin uniquement — mêmes règles que le partage classique) ──

    public function publicLinkCreate(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }

            $allowUpload = !empty($this->jsonInput()['allow_upload']);
            $existing = AlbumShareLink::getByAlbum($album['id']);
            if ($existing) AlbumShareLink::revoke($existing['id']);

            $link = AlbumShareLink::create($album['id'], $user['id'], $allowUpload);
            $link['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/album/' . $link['token'];
            return ['success' => true, 'link' => $link];
        });
    }

    public function publicLinkUpdate(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }

            $link = AlbumShareLink::getByAlbum($album['id']);
            if (!$link) return ['success' => false, 'error' => 'Aucun lien public actif.'];

            AlbumShareLink::setAllowUpload($link['id'], !empty($this->jsonInput()['allow_upload']));
            return ['success' => true];
        });
    }

    public function publicLinkRevoke(array $params): void
    {
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user  = Session::user();
            $album = Album::getById((int)$params['id']);
            if (!$album || (int)$album['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Album introuvable.'];
            }

            $link = AlbumShareLink::getByAlbum($album['id']);
            if ($link) AlbumShareLink::revoke($link['id']);
            return ['success' => true];
        });
    }

    /** Vue publique, sans connexion, d'un album partagé via lien. */
    public function publicView(array $params): void
    {
        $token = $params['token'] ?? '';
        $link  = AlbumShareLink::findValidByToken($token);
        $album = $link ? Album::getById((int)$link['album_id']) : null;
        if (!$album) http_response_code(404);
        $photos = $album ? AlbumPhoto::getByAlbum($album['id']) : [];
        require BASE_PATH . '/templates/album/public.php';
    }

    public function publicUpload(array $params): void
    {
        $token = $params['token'] ?? '';
        $this->json(function () use ($token) {
            $link = AlbumShareLink::findValidByToken($token);
            if (!$link || !$link['allow_upload']) {
                return ['success' => false, 'error' => 'Ajout de photos non autorisé.'];
            }
            $album = Album::getById((int)$link['album_id']);
            if (!$album) return ['success' => false, 'error' => 'Album introuvable.'];

            $imagePath = $this->uploadImage('image');
            if (!$imagePath) {
                return ['success' => false, 'error' => "L'image est trop volumineuse (max 20 Mo) ou dans un format non supporté (JPEG, PNG, GIF, WebP uniquement)."];
            }

            $uploaderName = trim($_POST['uploader_name'] ?? '') ?: null;
            $photoId = AlbumPhoto::create($album['id'], null, $imagePath, '', $uploaderName, (int)$link['id']);
            return ['success' => true, 'id' => $photoId, 'image_path' => $imagePath, 'uploader_name' => $uploaderName ?? 'Invité'];
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

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
