<?php
namespace App\Controllers;

use App\Core\Session;
use App\Core\OcrHelper;
use App\Models\Document;
use App\Models\User;

class DocumentController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
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

        require BASE_PATH . '/templates/documents/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user   = Session::user();
            $data   = $_POST ?: $this->jsonInput();
            $file   = $_FILES['file'] ?? null;
            $userId = (int)($data['user_id'] ?? $user['id']);
            $id     = Document::create($user['family_id'], $userId, $data, $file);
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
            if (isset($data['user_id'])) $data['user_id'] = (int)$data['user_id'];
            Document::update((int)$params['id'], $user['family_id'], $data, $file);
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
        header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
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
