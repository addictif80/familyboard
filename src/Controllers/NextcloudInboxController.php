<?php
namespace App\Controllers;

use App\Core\NextcloudFileClient;
use App\Core\Session;
use App\Models\Deal;
use App\Models\DisputeCase;
use App\Models\Document;
use App\Models\FamilyNextcloudFolder;
use App\Models\NextcloudInboxFile;
use App\Models\SchoolStudent;
use App\Models\Warranty;

/**
 * Rattachement d'un fichier déposé directement dans Nextcloud (hors FamilyBoard) à un
 * enregistrement d'un module — voir App\Core\NextcloudInboxSync (détection). Le coffre-fort
 * n'est volontairement pas proposé : une entrée du coffre-fort est un mot de passe avec une
 * pièce jointe optionnelle, pas un fichier qu'on "crée" à partir de rien.
 */
class NextcloudInboxController extends BaseController
{
    private const MODULES = ['documents', 'warranties', 'deals', 'school', 'disputes'];

    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $isAdmin = $user['role'] === 'admin';

        $pending = NextcloudInboxFile::getPendingForUser($familyId, (int)$user['id'], $isAdmin);
        $students = SchoolStudent::getByFamily($familyId);
        $disputes = DisputeCase::getByFamily($familyId);

        require BASE_PATH . '/templates/nextcloud-inbox/index.php';
    }

    public function attach(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $familyId = (int)$user['family_id'];
            $isAdmin = $user['role'] === 'admin';
            $data = $this->jsonInput();

            $inboxFile = NextcloudInboxFile::getById((int)($data['id'] ?? 0));
            if (!$inboxFile || (int)$inboxFile['family_id'] !== $familyId || $inboxFile['status'] !== 'pending') {
                return ['success' => false, 'error' => 'Fichier introuvable ou déjà traité.'];
            }
            // Visible seulement par celui qui l'a déposé (c'est lui qui valide l'import) ou un
            // administrateur de la famille.
            if (!$isAdmin && (int)($inboxFile['added_by_user_id'] ?? 0) !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Non autorisé.'];
            }

            $module = (string)($data['module'] ?? '');
            if (!in_array($module, self::MODULES, true)) {
                return ['success' => false, 'error' => 'Module invalide.'];
            }

            $folder = FamilyNextcloudFolder::findByFamily($familyId);
            if (!$folder) return ['success' => false, 'error' => "Espace Nextcloud introuvable."];

            $file = $this->downloadAsUploadedFile($folder['mount_point'], $inboxFile['remote_path'], $inboxFile['original_name']);
            if (!$file) return ['success' => false, 'error' => 'Fichier introuvable sur Nextcloud (déjà déplacé ou supprimé ?).'];

            try {
                $recordId = $this->createRecord($module, $familyId, (int)$user['id'], $data, $file);
            } catch (\RuntimeException $e) {
                @unlink($file['tmp_name']);
                return ['success' => false, 'error' => $e->getMessage()];
            }
            @unlink($file['tmp_name']);

            NextcloudInboxFile::markResolved((int)$inboxFile['id'], 'attached', $module, $recordId);
            // Le fichier d'origine est maintenant dupliqué dans le dossier du module — le
            // retirer du dossier "à rattacher" pour qu'il ne s'accumule pas indéfiniment.
            NextcloudFileClient::deleteFile($folder['mount_point'], $inboxFile['remote_path']);

            return ['success' => true];
        });
    }

    public function dismiss(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $inboxFile = NextcloudInboxFile::getById((int)$params['id']);
            if (!$inboxFile || (int)$inboxFile['family_id'] !== (int)$user['family_id']) {
                return ['success' => false, 'error' => 'Introuvable.'];
            }
            $isAdmin = $user['role'] === 'admin';
            if (!$isAdmin && (int)($inboxFile['added_by_user_id'] ?? 0) !== (int)$user['id']) {
                return ['success' => false, 'error' => 'Non autorisé.'];
            }
            // Ignoré, jamais supprimé côté Nextcloud : le fichier reste accessible là-bas si la
            // famille veut encore s'en servir autrement.
            NextcloudInboxFile::markResolved((int)$inboxFile['id'], 'dismissed', null, null);
            return ['success' => true];
        });
    }

    /** @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null forme
     *  "$_FILES" attendue par les Model::create()/addDocument() existants. */
    private function downloadAsUploadedFile(string $mountPoint, string $remotePath, string $originalName): ?array
    {
        $result = NextcloudFileClient::getFile($mountPoint, $remotePath, null);
        if ($result['status'] !== 200 || $result['body'] === null) return null;

        $tmp = tempnam(sys_get_temp_dir(), 'ncinbox_');
        file_put_contents($tmp, $result['body']);
        return [
            'name'     => $originalName,
            'type'     => mime_content_type($tmp) ?: 'application/octet-stream',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp) ?: 0,
        ];
    }

    private function createRecord(string $module, int $familyId, int $userId, array $data, array $file): int
    {
        $title = trim((string)($data['title'] ?? '')) ?: ($file['name'] ?? 'Document');
        switch ($module) {
            case 'documents':
                return Document::create($familyId, $userId, ['title' => $title, 'doc_type' => 'auto'], $file);

            case 'warranties':
                return Warranty::create($familyId, $userId, ['title' => $title], $file);

            case 'deals':
                // category est NOT NULL en base (DEFAULT 'autre', mais un NULL explicite passe
                // outre ce défaut et ferait échouer l'insertion en mode strict) : valeur fixe.
                return Deal::create($familyId, $userId, [
                    'title' => $title, 'description' => null, 'url' => null, 'promo_code' => null,
                    'discount' => null, 'category' => 'autre', 'expires_at' => null,
                ], $file);

            case 'school':
                $studentId = (int)($data['student_id'] ?? 0);
                $student = $studentId ? SchoolStudent::getById($studentId) : null;
                if (!$student || (int)$student['family_id'] !== $familyId) {
                    throw new \RuntimeException('Choisissez un élève valide.');
                }
                $docType = in_array($data['doc_type'] ?? '', ['bulletin', 'other'], true) ? $data['doc_type'] : 'other';
                return SchoolStudent::addDocument($studentId, $familyId, $userId, $title, $docType, $file);

            case 'disputes':
                $disputeId = (int)($data['dispute_id'] ?? 0);
                $dispute = $disputeId ? DisputeCase::getById($disputeId) : null;
                if (!$dispute || (int)$dispute['family_id'] !== $familyId) {
                    throw new \RuntimeException('Choisissez un litige valide.');
                }
                return DisputeCase::addDocument($disputeId, $familyId, $userId, $file);

            default:
                throw new \RuntimeException('Module invalide.');
        }
    }
}
