<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\OcrHelper;
use App\Models\CustodyActivityLog;
use App\Models\Document;
use App\Models\User;
use App\Models\Notification;

class DocumentController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('documents');
        $user     = Session::user();
        $familyId = $user['family_id'];

        $search     = trim($_GET['q']      ?? '');
        $filterType = trim($_GET['type']   ?? '');
        $filterUser = (int)($_GET['user']  ?? 0);

        $documents    = Document::getAll($familyId, $search, $filterType, $filterUser);
        $typeCounts   = Document::getTypeCounts($familyId);
        $expiring     = Document::getExpiringsSoon($familyId, 90);
        $members      = User::getByFamily($familyId);
        $allTypes     = OcrHelper::$types;
        $custodySchedules = \App\Models\Custody::getSchedules($familyId);

        require BASE_PATH . '/templates/documents/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user   = Session::user();
            $data   = $_POST ?: $this->jsonInput();
            $file   = $_FILES['file'] ?? null;
            // Document::processFile() ignore silencieusement un fichier en échec d'envoi (elle
            // ne traite que UPLOAD_ERR_OK) — sans ce contrôle, le document serait créé SANS le
            // fichier, sans jamais prévenir l'utilisateur de la vraie raison (taille, coupure...).
            if ($file && $file['error'] !== UPLOAD_ERR_OK && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $message = match ($file['error']) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                        'Fichier trop volumineux pour la configuration actuelle du serveur.',
                    UPLOAD_ERR_PARTIAL =>
                        "Envoi interrompu (connexion coupée en cours d'envoi) — réessayez.",
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                        "Erreur serveur : impossible d'écrire le fichier temporaire.",
                    default => "Erreur d'envoi du fichier (code {$file['error']}).",
                };
                return ['success' => false, 'error' => $message];
            }
            $familyMemberIds = array_map('intval', array_column(User::getByFamily($user['family_id']), 'id'));
            $userId = (int)($data['user_id'] ?? $user['id']);
            if (!in_array($userId, $familyMemberIds, true)) $userId = $user['id'];
            if (isset($data['member_ids'])) {
                $data['member_ids'] = array_values(array_filter(
                    array_map('intval', (array)$data['member_ids']),
                    fn($id) => in_array($id, $familyMemberIds, true)
                ));
            }
            $data['custody_schedule_ids'] = $this->validateFamilyScheduleIds((array)($data['custody_schedule_ids'] ?? []), $user['family_id']);
            $id     = Document::create($user['family_id'], $userId, $data, $file);
            foreach ($data['custody_schedule_ids'] as $scheduleId) {
                CustodyActivityLog::record($scheduleId, $user['id'], 'document_uploaded', $data['title'] ?? null);
            }
            Notification::notifyFamily($user['family_id'], $user['id'], 'documents', 'Nouveau document',
                $user['name'] . ' a ajouté : ' . $data['title'], BASE_URL . '/documents');
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $data = $_POST ?: $this->jsonInput();
            $file = $_FILES['file'] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_OK && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $message = match ($file['error']) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                        'Fichier trop volumineux pour la configuration actuelle du serveur.',
                    UPLOAD_ERR_PARTIAL =>
                        "Envoi interrompu (connexion coupée en cours d'envoi) — réessayez.",
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                        "Erreur serveur : impossible d'écrire le fichier temporaire.",
                    default => "Erreur d'envoi du fichier (code {$file['error']}).",
                };
                return ['success' => false, 'error' => $message];
            }
            $familyMemberIds = array_map('intval', array_column(User::getByFamily($user['family_id']), 'id'));
            if (isset($data['user_id'])) {
                $data['user_id'] = (int)$data['user_id'];
                if (!in_array($data['user_id'], $familyMemberIds, true)) unset($data['user_id']);
            }
            if (isset($data['member_ids'])) {
                $data['member_ids'] = array_values(array_filter(
                    array_map('intval', (array)$data['member_ids']),
                    fn($id) => in_array($id, $familyMemberIds, true)
                ));
            }
            $data['custody_schedule_ids'] = $this->validateFamilyScheduleIds((array)($data['custody_schedule_ids'] ?? []), $user['family_id']);
            Document::update((int)$params['id'], $user['family_id'], $data, $file);
            foreach ($data['custody_schedule_ids'] as $scheduleId) {
                CustodyActivityLog::record($scheduleId, $user['id'], 'document_updated', $data['title'] ?? null);
            }
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            Document::delete((int)$params['id'], $user['family_id']);
            return ['success' => true];
        });
    }

    public function serveFile(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $doc  = Document::findById((int)$params['id'], $user['family_id']);

        if (!$doc || !$doc['file_path']) { http_response_code(404); echo 'Introuvable.'; return; }

        $path = BASE_PATH . $doc['file_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        $mime = $doc['file_mime'] ?: mime_content_type($path);
        $name = $doc['file_original'] ?: basename($path);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($name));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /** Run OCR + auto-classify, return text + detected type */
    public function ocr(array $params): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu.']);
            return;
        }

        $file = $_FILES['file'];
        $text = OcrHelper::run($file['tmp_name'], $file['type']);

        if ($text === '') {
            $info    = OcrHelper::info();
            $missing = [];
            if (!$info['tesseract']) $missing[] = 'tesseract-ocr tesseract-ocr-fra';
            if (!$info['pdftotext']) $missing[] = 'poppler-utils';
            echo json_encode([
                'success' => false,
                'error'   => $missing
                    ? 'Installez : apt install ' . implode(' ', $missing)
                    : 'OCR n\'a pas pu extraire de texte (image trop petite ou illisible).',
                'classified' => null,
                'text'       => '',
            ]);
            return;
        }

        $classified = OcrHelper::classify($text);

        echo json_encode([
            'success'    => true,
            'text'       => $text,
            'classified' => $classified,
        ]);
    }

    public function search(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $items = Document::getAll(
                $user['family_id'],
                trim($_GET['q']    ?? ''),
                trim($_GET['type'] ?? ''),
                (int)($_GET['user'] ?? 0)
            );
            return ['success' => true, 'items' => $items];
        });
    }
}
