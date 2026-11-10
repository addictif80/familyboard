<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\StoredFile;

/**
 * Point de lecture unique des fichiers enregistrés via App\Core\FileStore::saveImage() — avatars,
 * photos (mur, album, bébé, contact, co-parent, profil) déplacés hors de public/uploads/ (qui
 * était servi directement par Apache, sans contrôle d'accès, voir la mission "fichiers des
 * modules"). Remplace l'URL brute "/public/uploads/<hex>.<ext>" par une route "/files/{id}"
 * qui repasse systématiquement par ce contrôleur avant de streamer le fichier.
 */
class FileController extends BaseController
{
    public function serve(array $params): void
    {
        $this->requireAuth(true); // un co-parent doit pouvoir voir les avatars/photos de la famille qui l'a invité
        $user = Session::user();
        $row = StoredFile::findById((int)$params['id']);

        if (!$row || (int)$row['family_id'] !== (int)$user['family_id']) {
            http_response_code(404);
            echo 'Introuvable.';
            return;
        }

        $path = BASE_PATH . $row['path'];
        if (!file_exists($path)) {
            http_response_code(404);
            echo 'Introuvable.';
            return;
        }

        $mime = $row['mime'] ?: (mime_content_type($path) ?: 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }
}
