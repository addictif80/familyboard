<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Warranty;
use App\Models\Notification;

class WarrantyController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('warranties');
        $user     = Session::user();
        $familyId = $user['family_id'];
        $search   = trim($_GET['q'] ?? '');
        $warranties     = Warranty::getAll($familyId, $search);
        $expiringSoon   = Warranty::getExpiringSoon($familyId, 90);
        require BASE_PATH . '/templates/warranties/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user     = Session::user();
            $data     = $_POST ?: $this->jsonInput();
            $file     = $_FILES['file'] ?? null;
            $id = Warranty::create($user['family_id'], $user['id'], $data, $file);
            Notification::notifyFamily($user['family_id'], $user['id'], 'warranties', 'Nouvelle garantie',
                $user['name'] . ' a ajouté : ' . $data['title'], BASE_URL . '/warranties');
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user     = Session::user();
            $data     = $_POST ?: $this->jsonInput();
            $file     = $_FILES['file'] ?? null;
            Warranty::update((int)$params['id'], $user['family_id'], $data, $file);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            Warranty::delete((int)$params['id'], $user['family_id']);
            return ['success' => true];
        });
    }

    /** Stream the stored file to the browser */
    public function serveFile(array $params): void
    {
        $this->requireAuth();
        $user     = Session::user();
        $warranty = Warranty::findById((int)$params['id'], $user['family_id']);

        if (!$warranty || !$warranty['file_path']) {
            http_response_code(404);
            echo 'Fichier introuvable.';
            return;
        }

        $path = BASE_PATH . $warranty['file_path'];
        if (!file_exists($path)) {
            http_response_code(404);
            echo 'Fichier introuvable.';
            return;
        }

        $mime = $warranty['file_mime'] ?: mime_content_type($path);
        $name = $warranty['file_original'] ?: basename($path);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /** Run OCR on a freshly uploaded file (multipart, field = file) */
    public function ocr(array $params): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu.']);
            return;
        }

        $file = $_FILES['file'];
        $text = Warranty::runOcr($file['tmp_name'], $file['type']);

        if ($text === '') {
            $info = Warranty::ocrInfo();
            $missing = [];
            if (!$info['tesseract']) $missing[] = 'tesseract-ocr tesseract-ocr-fra';
            if (!$info['pdftotext']) $missing[] = 'poppler-utils';
            $hint = $missing
                ? 'Binaires introuvables. Installez : apt install ' . implode(' ', $missing)
                : 'Binaires trouvés mais OCR a retourné un résultat vide (image trop petite ou illisible ?).';
            echo json_encode([
                'success' => false,
                'error'   => $hint,
                'debug'   => $info,
                'text'    => '',
            ]);
            return;
        }

        echo json_encode(['success' => true, 'text' => $text]);
    }

    /** OCR diagnostic endpoint — returns binary paths and PHP config */
    public function ocrCheck(array $params): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');
        echo json_encode(Warranty::ocrInfo());
    }

    /** Search (AJAX — returns JSON for dynamic filtering) */
    public function search(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $q     = trim($_GET['q'] ?? '');
            $items = Warranty::getAll($user['family_id'], $q);
            return ['success' => true, 'items' => $items];
        });
    }
}
