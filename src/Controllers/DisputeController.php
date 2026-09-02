<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\DisputeAccessLog;
use App\Models\DisputeCase;
use App\Models\DisputeShare;

class DisputeController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('disputes');
        $user = Session::user();
        $familyId = (int)$user['family_id'];

        $disputes = DisputeCase::getByFamily($familyId);
        $selectedId = (int)($_GET['id'] ?? 0);
        $selected = null;
        $documents = [];
        $exchanges = [];
        $share = null;
        $accessLog = [];
        if ($selectedId) {
            $selected = DisputeCase::getById($selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) {
                $selected = null;
            }
        } elseif ($disputes) {
            $selected = $disputes[0];
            $selectedId = (int)$selected['id'];
        }
        if ($selected) {
            $documents = DisputeCase::getDocuments($selectedId);
            $exchanges = DisputeCase::getExchanges($selectedId);
            $share = DisputeShare::getByDispute($selectedId);
            if ($user['role'] === 'admin') {
                $accessLog = DisputeAccessLog::getByDispute($selectedId);
            }
        }

        require BASE_PATH . '/templates/disputes/index.php';
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $d = $this->validated($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            $id = DisputeCase::create((int)$user['family_id'], (int)$user['id'], $d);
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
            DisputeCase::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function setStatus(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $status = $this->jsonInput()['status'] ?? '';
            DisputeCase::setStatus((int)$params['id'], (int)$user['family_id'], $status);
            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            DisputeCase::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    // ── Pièces jointes ────────────────────────────────────────────

    public function uploadDocument(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Dossier introuvable.'];
            }
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $message = match ($file['error'] ?? null) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux pour la configuration actuelle du serveur.',
                    UPLOAD_ERR_PARTIAL => "Envoi interrompu (connexion coupée en cours d'envoi) — réessayez.",
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => "Erreur serveur : impossible d'écrire le fichier temporaire.",
                    default => 'Aucun fichier reçu.',
                };
                return ['success' => false, 'error' => $message];
            }
            try {
                $id = DisputeCase::addDocument((int)$dispute['id'], (int)$user['family_id'], (int)$user['id'], $file);
            } catch (\RuntimeException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteDocument(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) return ['success' => false];
            DisputeCase::deleteDocument((int)$params['docId'], (int)$dispute['id']);
            return ['success' => true];
        });
    }

    public function serveFile(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $dispute = DisputeCase::getById((int)$params['id']);
        if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) {
            http_response_code(404); echo 'Introuvable.'; return;
        }
        $this->streamDocument((int)$params['docId'], (int)$dispute['id']);
    }

    // ── Traçabilité des échanges ─────────────────────────────────

    public function addExchange(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Dossier introuvable.'];
            }
            $data = $this->jsonInput();
            $type = $data['type'] ?? '';
            $contact = trim($data['contact_info'] ?? '');
            $date = trim($data['exchange_date'] ?? '');
            if (!in_array($type, ['telephone', 'email', 'courrier'], true) || $contact === '' || $date === '') {
                return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            }
            $id = DisputeCase::addExchange((int)$dispute['id'], (int)$user['id'], [
                'type' => $type,
                'contact_info' => $contact,
                'exchange_date' => $date,
                'notes' => trim($data['notes'] ?? ''),
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteExchange(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) return ['success' => false];
            DisputeCase::deleteExchange((int)$params['exchangeId'], (int)$dispute['id']);
            return ['success' => true];
        });
    }

    // ── Partage public (lien, sans compte, lecture seule) ────────

    public function share(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) return ['success' => false];
            $share = DisputeShare::getOrCreate((int)$dispute['id'], (int)$user['id']);
            $share['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/share/dispute/' . $share['token'];
            return ['success' => true, 'share' => $share];
        });
    }

    public function regenerateShare(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) return ['success' => false];
            if (!DisputeShare::getByDispute((int)$dispute['id'])) return ['success' => false];
            $share = DisputeShare::regenerate((int)$dispute['id']);
            $share['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/share/dispute/' . $share['token'];
            return ['success' => true, 'share' => $share];
        });
    }

    public function revokeShare(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $dispute = DisputeCase::getById((int)$params['id']);
            if (!$dispute || (int)$dispute['family_id'] !== (int)$user['family_id']) return ['success' => false];
            DisputeShare::revoke((int)$dispute['id']);
            return ['success' => true];
        });
    }

    /** Page publique, sans compte, lecture seule — chaque ouverture est journalisée
     *  (date/heure/IP), consultable par les administrateurs de la famille depuis le dossier. */
    public function publicView(array $params): void
    {
        $token = $params['token'] ?? '';
        $share = DisputeShare::findValidByToken($token);
        if (!$share) {
            http_response_code(404);
            $dispute = null;
            require BASE_PATH . '/templates/disputes/public.php';
            return;
        }
        DisputeAccessLog::record((int)$share['dispute_id'], $_SERVER['REMOTE_ADDR'] ?? null);

        // Volontairement limité aux détails + pièces jointes : la traçabilité des échanges
        // (numéros, e-mails, adresses postales) n'est jamais exposée sur le lien public.
        $dispute = DisputeCase::getById((int)$share['dispute_id']);
        $documents = DisputeCase::getDocuments((int)$share['dispute_id']);
        $token = $share['token'];
        require BASE_PATH . '/templates/disputes/public.php';
    }

    public function publicServeFile(array $params): void
    {
        $share = DisputeShare::findValidByToken($params['token'] ?? '');
        if (!$share) { http_response_code(404); echo 'Introuvable.'; return; }
        $this->streamDocument((int)$params['docId'], (int)$share['dispute_id']);
    }

    private function streamDocument(int $docId, int $disputeId): void
    {
        $doc = DisputeCase::getDocumentById($docId, $disputeId);
        if (!$doc) { http_response_code(404); echo 'Introuvable.'; return; }
        $path = BASE_PATH . $doc['file_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        header('Content-Type: ' . $doc['file_mime']);
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($doc['file_original']));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    private function validated(array $data): ?array
    {
        $title = trim($data['title'] ?? '');
        $opposingParty = trim($data['opposing_party'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        if ($title === '' || $opposingParty === '' || $startDate === '') return null;
        $d = \DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$d || $d->format('Y-m-d') !== $startDate) return null;
        return [
            'title' => $title,
            'opposing_party' => $opposingParty,
            'start_date' => $startDate,
            'details' => (string)($data['details'] ?? ''),
        ];
    }

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
