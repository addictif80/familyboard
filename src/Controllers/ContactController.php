<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Contact;
use App\Models\NameDay;

class ContactController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('contacts');
        $user = Session::user();
        Contact::seedEmergency($user['family_id'], $user['id']);
        $contacts = Contact::getByFamily($user['family_id']);
        foreach ($contacts as &$c) {
            $c['name_day'] = NameDay::forName($c['first_name']);
        }
        unset($c);
        require BASE_PATH . '/templates/contacts/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (empty($data['first_name'])) return ['success' => false, 'error' => 'Prénom requis'];
            if (isset($data['color'])) $data['color'] = $this->safeColor($data['color']);
            $id = Contact::create($user['family_id'], $user['id'], $data);
            return ['success' => true, 'contact' => Contact::getById($id)];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user    = Session::user();
            $id      = (int)$params['id'];
            $contact = Contact::getById($id);
            if (!$contact || $contact['family_id'] !== $user['family_id']) return ['success' => false, 'error' => 'Non autorisé'];
            if ($contact['is_system']) return ['success' => false, 'error' => 'Ce contact système ne peut pas être modifié.'];
            $data = $this->jsonInput();
            if (isset($data['color'])) $data['color'] = $this->safeColor($data['color']);
            Contact::update($id, $data);
            return ['success' => true, 'contact' => Contact::getById($id)];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user    = Session::user();
            $id      = (int)$params['id'];
            $contact = Contact::getById($id);
            if (!$contact || $contact['family_id'] !== $user['family_id']) return ['success' => false, 'error' => 'Non autorisé'];
            if ($contact['is_system']) return ['success' => false, 'error' => 'Ce contact système ne peut pas être supprimé.'];
            // Delete avatar file if any
            if ($contact['avatar']) {
                \App\Core\FileStore::deleteByRoute($contact['avatar']);
            }
            Contact::delete($id);
            return ['success' => true];
        });
    }

    public function uploadAvatar(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user    = Session::user();
            $id      = (int)$params['id'];
            $contact = Contact::getById($id);
            if (!$contact || $contact['family_id'] !== $user['family_id']) return ['success' => false, 'error' => 'Non autorisé'];

            $path = $this->uploadFamilyImage('avatar', 'avatar');
            if (!$path) return ['success' => false, 'error' => $this->lastUploadError ?: 'Fichier invalide ou trop volumineux'];

            // Delete old avatar
            if ($contact['avatar']) {
                \App\Core\FileStore::deleteByRoute($contact['avatar']);
            }
            Contact::updateAvatar($id, $path);
            return ['success' => true, 'avatar' => $path];
        });
    }

    /** GET /contacts/:id/vcard  — télécharge un contact en .vcf */
    public function downloadVcard(array $params): void
    {
        $this->requireAuth();
        $user    = Session::user();
        $id      = (int)$params['id'];
        $contact = Contact::getById($id);
        if (!$contact || $contact['family_id'] !== $user['family_id']) {
            http_response_code(403);
            exit;
        }
        $fn = trim($contact['first_name'] . '_' . $contact['last_name']) ?: 'contact';
        $fn = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fn);
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fn . '.vcf"');
        echo Contact::toVcard($contact);
    }

    /** GET /contacts/export.vcf — tous les contacts de la famille en un seul fichier */
    public function exportVcf(array $params): void
    {
        $this->requireAuth();
        $user     = Session::user();
        $contacts = Contact::getByFamily($user['family_id']);
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="contacts_famille.vcf"');
        foreach ($contacts as $c) {
            echo Contact::toVcard($c);
        }
    }

    /** POST /api/contacts/import-vcf — import groupé depuis un fichier .vcf (export standard de
     *  Google Contacts, iCloud, Outlook ou du répertoire du téléphone — voir /migration). */
    public function importVcf(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error' => 'Fichier manquant ou invalide.'];
            }
            if ($_FILES['file']['size'] > 2 * 1024 * 1024) {
                return ['success' => false, 'error' => 'Fichier trop volumineux (max 2 Mo).'];
            }
            $content = file_get_contents($_FILES['file']['tmp_name']);
            if ($content === false || !str_contains($content, 'BEGIN:VCARD')) {
                return ['success' => false, 'error' => "Ce fichier ne semble pas être un fichier .vcf valide."];
            }
            $count = Contact::importVcf((int)$user['family_id'], (int)$user['id'], $content);
            return $count > 0
                ? ['success' => true, 'count' => $count]
                : ['success' => false, 'error' => 'Aucun contact exploitable trouvé dans ce fichier.'];
        });
    }
}
