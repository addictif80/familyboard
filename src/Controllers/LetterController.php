<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Family;
use App\Models\Letter;
use App\Models\LetterTemplate;

class LetterController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('letters');
        $user     = Session::user();
        $familyId = (int)$user['family_id'];

        $family    = Family::findById($familyId);
        $letters   = Letter::getByFamily($familyId);
        $templates = LetterTemplate::getByFamily($familyId);

        require BASE_PATH . '/templates/letters/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            $id = Letter::create((int)$user['family_id'], (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            Letter::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            Letter::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    public function createTemplate(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            if ($name === '') return ['success' => false, 'error' => 'Nom du modèle requis.'];
            $variables = is_array($data['variables'] ?? null) ? array_map('strval', $data['variables']) : [];
            $id = LetterTemplate::create(
                (int)$user['family_id'],
                (int)$user['id'],
                $name,
                trim($data['subject'] ?? ''),
                (string)($data['body'] ?? ''),
                $variables
            );
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteTemplate(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            LetterTemplate::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    /** Valide et normalise les champs d'un courrier, calcule le nom d'affichage du destinataire. */
    private function validated(array $data): ?array
    {
        $lastName   = trim($data['recipient_last_name'] ?? '');
        $firstName  = trim($data['recipient_first_name'] ?? '');
        $address    = trim($data['recipient_address'] ?? '');
        $postalCity = trim($data['recipient_postal_city'] ?? '');
        $subject    = trim($data['subject'] ?? '');
        $body       = (string)($data['body'] ?? '');
        if ($lastName === '' || $address === '' || $postalCity === '' || $subject === '' || trim(strip_tags($body)) === '') {
            return null;
        }
        $civility = in_array($data['civility'] ?? '', ['Madame', 'Monsieur'], true) ? $data['civility'] : '';
        $displayName = trim($civility . ' ' . $firstName . ' ' . $lastName);

        return [
            'civility'                     => $civility,
            'recipient_last_name'          => $lastName,
            'recipient_first_name'         => $firstName,
            'recipient_display_name'       => $displayName,
            'recipient_complement'         => trim($data['recipient_complement'] ?? ''),
            'recipient_address'            => $address,
            'recipient_address_complement' => trim($data['recipient_address_complement'] ?? ''),
            'recipient_postal_city'        => $postalCity,
            'place'                        => trim($data['place'] ?? ''),
            'subject'                      => $subject,
            'body'                         => $body,
        ];
    }
}
