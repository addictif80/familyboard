<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\NextcloudInboxFile;
use App\Models\Notification;

/**
 * Point d'entrée du Web Share Target (manifest.json "share_target") — reçoit un fichier partagé
 * depuis une autre app via le menu "Partager" de l'OS et le dépose dans la même file d'attente
 * que les dépôts Nextcloud (voir App\Core\NextcloudInboxSync), avec un remote_path préfixé
 * "local:" pour que NextcloudInboxController sache le lire sur disque plutôt que via WebDAV.
 */
class ShareTargetController extends BaseController
{
    public function receive(array $params): void
    {
        // Pas requireAuth() : cette requête est une navigation POST native générée par l'OS
        // (menu "Partager"), sans jeton CSRF disponible — on vérifie juste la session nous-même.
        // Sans risque au-delà de celui déjà accepté pour toute route authentifiée : l'entrée créée
        // reste cantonnée à la famille de la session et à l'étape "en attente de validation".
        $user = Session::user();
        if (!$user) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
        $familyId = (int)$user['family_id'];

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            // Rien à mettre en file (partage de simple texte/URL sans fichier, ou erreur
            // d'upload) — retour silencieux à l'app, il n'y a aucun fichier à proposer.
            header('Location: ' . BASE_URL . '/nextcloud-inbox');
            exit;
        }

        $dir = BASE_PATH . '/storage/share_target/' . $familyId;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $originalName = $file['name'] ?: 'Partage';
        $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            header('Location: ' . BASE_URL . '/nextcloud-inbox');
            exit;
        }

        $relativePath = 'local:/storage/share_target/' . $familyId . '/' . $safeName;
        $existing = NextcloudInboxFile::findByFamilyAndPath($familyId, $relativePath);
        if (!$existing) {
            $id = NextcloudInboxFile::create($familyId, [
                'path' => $relativePath,
                'name' => $originalName,
                'file_id' => null,
                'etag' => null,
                'size' => filesize($dest) ?: 0,
            ], (int)$user['id']);

            Notification::create(
                (int)$user['id'],
                'nextcloud_inbox',
                'Nouveau fichier partagé',
                "Vous avez partagé \"$originalName\" vers FamilyBoard — souhaitez-vous le rattacher à un module ?",
                '/nextcloud-inbox',
                'nextcloud_inbox_file',
                $id
            );
        }

        header('Location: ' . BASE_URL . '/nextcloud-inbox');
        exit;
    }
}
