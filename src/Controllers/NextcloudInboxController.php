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

            $isLocal = str_starts_with($inboxFile['remote_path'], 'local:');
            $mountPoint = null;
            if (!$isLocal) {
                $folder = FamilyNextcloudFolder::findByFamily($familyId);
                if (!$folder) return ['success' => false, 'error' => "Espace Nextcloud introuvable."];
                $mountPoint = $folder['mount_point'];
            }

            $file = $isLocal
                ? $this->readLocalUploadedFile($inboxFile['remote_path'], $inboxFile['original_name'])
                : $this->downloadAsUploadedFile($mountPoint, $inboxFile['remote_path'], $inboxFile['original_name']);
            if (!$file) return ['success' => false, 'error' => 'Fichier introuvable (déjà traité ou supprimé ?).'];

            try {
                $recordId = $this->createRecord($module, $familyId, (int)$user['id'], $data, $file);
            } catch (\RuntimeException $e) {
                @unlink($file['tmp_name']);
                return ['success' => false, 'error' => $e->getMessage()];
            }
            @unlink($file['tmp_name']);

            NextcloudInboxFile::markResolved((int)$inboxFile['id'], 'attached', $module, $recordId);
            // Le fichier d'origine est maintenant dupliqué dans le dossier du module — le
            // retirer de la file "à rattacher" pour qu'il ne s'accumule pas indéfiniment.
            if ($isLocal) {
                @unlink(BASE_PATH . substr($inboxFile['remote_path'], strlen('local:')));
            } else {
                NextcloudFileClient::deleteFile($mountPoint, $inboxFile['remote_path']);
            }

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

    /** Forme "$_FILES" pour un fichier reçu via le Web Share Target (voir ShareTargetController),
     *  stocké localement sous storage/share_target/ plutôt que sur Nextcloud — même format de
     *  retour que downloadAsUploadedFile() pour que createRecord() ne distingue pas les deux. */
    private function readLocalUploadedFile(string $remotePath, string $originalName): ?array
    {
        $path = BASE_PATH . substr($remotePath, strlen('local:'));
        if (!is_file($path)) return null;
        return [
            'name'     => $originalName,
            'type'     => mime_content_type($path) ?: 'application/octet-stream',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($path) ?: 0,
        ];
    }

    private function createRecord(string $module, int $familyId, int $userId, array $data, array $file): int
    {
        $title = trim((string)($data['title'] ?? '')) ?: ($file['name'] ?? 'Document');
        switch ($module) {
            case 'documents':
                // 'ncinbox_pending' plutôt que 'auto' : un import brut doit rester repérable comme
                // "à trier" tant que personne n'a confirmé/reclassé son vrai type — jamais deviné
                // par l'OCR (voir OcrHelper::$types, keywords volontairement vide pour ce type).
                return Document::create($familyId, $userId, ['title' => $title, 'doc_type' => 'ncinbox_pending'], $file);

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
