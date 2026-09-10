<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Vault;

class VaultController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('vault');
        $this->requireAdmin();
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $entries = Vault::getEntries($familyId);
        $trustees = Vault::getTrustees($familyId);
        require BASE_PATH . '/templates/vault/index.php';
    }

    public function createEntry(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validatedEntry($_POST ?: $this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le titre est requis.'];
            $id = Vault::createEntry((int)$user['family_id'], (int)$user['id'], $d, $_FILES['file'] ?? null);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateEntry(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $entry = $this->ownedEntry((int)$params['id']);
            if (!$entry) return ['success' => false, 'error' => 'Entrée introuvable.'];
            $data = $_POST ?: $this->jsonInput();
            $d = $this->validatedEntry($data);
            if (!$d) return ['success' => false, 'error' => 'Le titre est requis.'];
            $d['remove_file'] = !empty($data['remove_file']);
            Vault::updateEntry((int)$params['id'], (int)$user['family_id'], $d, $_FILES['file'] ?? null);
            return ['success' => true];
        });
    }

    public function deleteEntry(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $entry = $this->ownedEntry((int)$params['id']);
            if (!$entry) return ['success' => false];
            Vault::deleteEntry((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function serveFile(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $entry = $this->ownedEntry((int)$params['id']);
        if (!$entry || !$entry['file_path']) { http_response_code(404); echo 'Introuvable.'; return; }
        $path = BASE_PATH . $entry['file_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        header('Content-Type: ' . $entry['file_mime']);
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($entry['file_original']));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function createTrustee(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Nom et e-mail valide requis.'];
            }
            $trustee = Vault::createTrustee((int)$user['family_id'], (int)$user['id'], [
                'name' => $name,
                'email' => $email,
                'relationship' => trim($data['relationship'] ?? '') ?: null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);
            $trustee['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/vault-access/' . $trustee['access_token'];
            return ['success' => true, 'trustee' => $trustee];
        });
    }

    public function deleteTrustee(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $trustee = $this->ownedTrustee((int)$params['id']);
            if (!$trustee) return ['success' => false];
            Vault::deleteTrustee((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function decideTrustee(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $trustee = $this->ownedTrustee((int)$params['id']);
            if (!$trustee) return ['success' => false];
            $approve = !empty($this->jsonInput()['approve']);
            Vault::decideAccess((int)$params['id'], (int)$user['family_id'], $approve, (int)$user['id']);
            return ['success' => true];
        });
    }

    public function revokeTrustee(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $trustee = $this->ownedTrustee((int)$params['id']);
            if (!$trustee) return ['success' => false];
            Vault::revokeAccess((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    private function ownedEntry(int $id): ?array
    {
        $user = Session::user();
        $entry = Vault::getEntryById($id);
        if (!$entry || (int)$entry['family_id'] !== (int)$user['family_id']) return null;
        return $entry;
    }

    private function ownedTrustee(int $id): ?array
    {
        $user = Session::user();
        $trustee = Vault::getTrusteeById($id);
        if (!$trustee || (int)$trustee['family_id'] !== (int)$user['family_id']) return null;
        return $trustee;
    }

    private function validatedEntry(array $data): ?array
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') return null;
        $category = array_key_exists($data['category'] ?? '', Vault::CATEGORIES) ? $data['category'] : 'autre';
        return [
            'category' => $category,
            'title' => $title,
            'content' => trim($data['content'] ?? '') ?: null,
        ];
    }

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
