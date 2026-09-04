<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Baby;
use App\Models\FamilyChild;

class BabyController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $user = Session::user();
        $babies = Baby::getAllByFamily($user['family_id']);
        $pageTitle = 'Bébé';
        $extraJs = ['baby.js'];
        require BASE_PATH . '/templates/baby/index.php';
    }

    // ── Babies CRUD ───────────────────────────────────────────

    public function createBaby(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            if ($name === '') return ['success' => false, 'error' => 'Nom requis'];
            // Rattaché au registre familial central (voir App\Models\FamilyChild) : réutilise
            // une fiche existante du même nom (ex. déjà créée dans un autre module) ou en crée
            // une nouvelle, réutilisable ensuite dans Suivi scolaire/Suivi nounou/Garde alternée.
            $familyChildId = FamilyChild::findOrCreateByName((int)$user['family_id'], (int)$user['id'], $name);
            $id = Baby::create($user['family_id'], $name, $familyChildId);
            $baby = Baby::findById($id);
            return ['success' => true, 'baby' => $baby];
        });
    }

    public function updateBaby(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $baby = Baby::findById($id);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (empty($data['name'])) return ['success' => false, 'error' => 'Nom requis'];
            Baby::update($id, $data);
            return ['success' => true, 'baby' => Baby::findById($id)];
        });
    }

    public function uploadBabyAvatar(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $baby = Baby::findById($id);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            $path = $this->uploadImage('avatar');
            if (!$path) return ['success' => false, 'error' => 'Upload échoué'];
            Baby::updateAvatar($id, $path);
            return ['success' => true, 'path' => $path];
        });
    }

    public function deleteBaby(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $baby = Baby::findById($id);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            Baby::delete($id);
            return ['success' => true];
        });
    }

    // ── Pregnancy ─────────────────────────────────────────────

    public function upsertPregnancy(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = isset($params['baby_id']) ? (int)$params['baby_id'] : null;
            if ($babyId) {
                $baby = Baby::findById($babyId);
                if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            }
            $data = $this->jsonInput();
            $id = Baby::upsertPregnancy($user['family_id'], $babyId ?: null, $data);
            return ['success' => true, 'pregnancy' => Baby::getPregnancyById($id)];
        });
    }

    public function getPregnancy(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = isset($params['baby_id']) ? (int)$params['baby_id'] : null;
            $pregnancy = Baby::getPregnancy($user['family_id'], $babyId ?: null);
            if (!$pregnancy) return ['pregnancy' => null, 'consultations' => []];
            $consultations = Baby::getConsultationsByPregnancy($pregnancy['id']);
            foreach ($consultations as &$c) {
                $c['images'] = [];
                if ($c['image_ids']) {
                    $ids   = explode(',', $c['image_ids']);
                    $paths = explode('||', $c['image_paths']);
                    $names = explode('||', $c['image_names']);
                    foreach ($ids as $i => $imgId) {
                        $c['images'][] = ['id' => $imgId, 'path' => $paths[$i], 'name' => $names[$i]];
                    }
                }
                unset($c['image_ids'], $c['image_paths'], $c['image_names']);
            }
            return ['pregnancy' => $pregnancy, 'consultations' => $consultations];
        });
    }

    // ── Pregnancy consultations ───────────────────────────────

    public function createPregnancyConsultation(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $pregnancyId = (int)$params['pregnancy_id'];
            $pregnancy = Baby::getPregnancyById($pregnancyId);
            if (!$pregnancy || $pregnancy['family_id'] !== $user['family_id']) return ['success' => false];

            $data = $_POST ?: $this->jsonInput();
            if (empty($data['consult_date'])) return ['success' => false, 'error' => 'Date requise'];

            $id = Baby::createPregnancyConsultation($pregnancyId, $user['family_id'], $data);

            if (!empty($_FILES)) {
                for ($i = 0; $i < 10; $i++) {
                    $field = 'image_' . $i;
                    if (!isset($_FILES[$field])) break;
                    $path = $this->uploadImage($field);
                    if ($path) {
                        Baby::addPregnancyImage($id, $user['family_id'], $path, $_FILES[$field]['name']);
                    }
                }
            }
            return ['success' => true, 'id' => $id];
        });
    }

    public function deletePregnancyConsultation(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $c = Baby::getPregnancyConsultationById($id);
            if (!$c || $c['family_id'] !== $user['family_id']) return ['success' => false];
            Baby::deletePregnancyConsultation($id);
            return ['success' => true];
        });
    }

    public function deletePregnancyImage(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $img = Baby::getPregnancyImageById($id);
            if (!$img || $img['family_id'] !== $user['family_id']) return ['success' => false];
            $fullPath = BASE_PATH . $img['file_path'];
            if (file_exists($fullPath)) @unlink($fullPath);
            Baby::deletePregnancyImage($id);
            return ['success' => true];
        });
    }

    // ── Baby events ───────────────────────────────────────────

    public function apiEvents(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = (int)$params['baby_id'];
            $baby = Baby::findById($babyId);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return [];
            $date = $_GET['date'] ?? date('Y-m-d');
            $events = Baby::getEventsByDay($babyId, $date);
            $sleeps = Baby::getSleepsByDay($babyId, $date);
            $activeSleep = Baby::getActiveSleep($babyId);
            $stats = Baby::getDailyStats($babyId, $date);
            return compact('events', 'sleeps', 'activeSleep', 'stats');
        });
    }

    public function createEvent(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = (int)$params['baby_id'];
            $baby = Baby::findById($babyId);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            $allowed = ['feeding', 'stool', 'urine', 'diaper', 'bath'];
            if (!in_array($data['type'] ?? '', $allowed, true)) return ['success' => false, 'error' => 'Type invalide'];
            if (empty($data['event_at'])) $data['event_at'] = date('Y-m-d H:i:s');
            $id = Baby::createEvent($babyId, $user['family_id'], $data);
            return ['success' => true, 'event' => Baby::getEventById($id)];
        });
    }

    public function updateEvent(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Baby::getEventById($id);
            if (!$event || $event['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            Baby::updateEvent($id, $data);
            return ['success' => true, 'event' => Baby::getEventById($id)];
        });
    }

    public function deleteEvent(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Baby::getEventById($id);
            if (!$event || $event['family_id'] !== $user['family_id']) return ['success' => false];
            Baby::deleteEvent($id);
            return ['success' => true];
        });
    }

    // ── Sleeps ────────────────────────────────────────────────

    public function createSleep(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = (int)$params['baby_id'];
            $baby = Baby::findById($babyId);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            $startAt = $data['start_at'] ?? date('Y-m-d H:i:s');
            $endAt = $data['end_at'] ?? null;
            $id = Baby::createSleep($babyId, $user['family_id'], $startAt, $endAt, $data['notes'] ?? null);
            return ['success' => true, 'sleep' => Baby::getSleepById($id)];
        });
    }

    public function updateSleep(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $sleep = Baby::getSleepById($id);
            if (!$sleep || $sleep['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            Baby::updateSleep($id, $data['start_at'], $data['end_at'] ?? null, $data['notes'] ?? null);
            return ['success' => true, 'sleep' => Baby::getSleepById($id)];
        });
    }

    public function endSleep(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $sleep = Baby::getSleepById($id);
            if (!$sleep || $sleep['family_id'] !== $user['family_id']) return ['success' => false];
            $endAt = $this->jsonInput()['end_at'] ?? date('Y-m-d H:i:s');
            Baby::endSleep($id, $endAt);
            return ['success' => true, 'sleep' => Baby::getSleepById($id)];
        });
    }

    public function deleteSleep(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $sleep = Baby::getSleepById($id);
            if (!$sleep || $sleep['family_id'] !== $user['family_id']) return ['success' => false];
            Baby::deleteSleep($id);
            return ['success' => true];
        });
    }

    // ── Post-birth consultations ──────────────────────────────

    public function apiConsultations(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = (int)$params['baby_id'];
            $baby = Baby::findById($babyId);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return [];
            return Baby::getConsultationsByBaby($babyId);
        });
    }

    public function createConsultation(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $babyId = (int)$params['baby_id'];
            $baby = Baby::findById($babyId);
            if (!$baby || $baby['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (empty($data['consult_at'])) return ['success' => false, 'error' => 'Date requise'];
            $id = Baby::createConsultation($babyId, $user['family_id'], $data);
            return ['success' => true, 'consultation' => Baby::getConsultationById($id)];
        });
    }

    public function updateConsultation(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $c = Baby::getConsultationById($id);
            if (!$c || $c['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            Baby::updateConsultation($id, $data);
            return ['success' => true, 'consultation' => Baby::getConsultationById($id)];
        });
    }

    public function deleteConsultation(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('baby');
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $c = Baby::getConsultationById($id);
            if (!$c || $c['family_id'] !== $user['family_id']) return ['success' => false];
            Baby::deleteConsultation($id);
            return ['success' => true];
        });
    }
}
