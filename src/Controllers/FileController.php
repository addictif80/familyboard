<?php
namespace App\Controllers;

use App\Core\FileStore;
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
    /** stored_files est une table UNIQUE partagée par tous les modules (documents, coffre-fort,
     *  scolaire, litiges, messages vocaux...), avec un id auto-incrémenté trivialement énumérable
     *  — sans cette liste, "/files/{id}" (ne vérifiant que family_id) laisserait n'importe quel
     *  utilisateur authentifié (et surtout un accès co-parent, dont le family_id en base est
     *  celui de la famille qui l'a invité) deviner l'id d'une pièce jointe d'un AUTRE module
     *  n'ayant pourtant pas vocation à être servi par cette route. Ne garder ici que les modules
     *  réellement écrits via FileStore::saveImage()/BaseController::uploadFamilyImage().
     */
    private const ALLOWED_MODULES = ['avatar', 'album', 'wall', 'baby'];

    public function serve(array $params): void
    {
        $this->requireAuth(true); // un co-parent doit pouvoir voir les avatars/photos de la famille qui l'a invité
        $user = Session::user();
        $row = StoredFile::findById((int)$params['id']);

        if (!$row || (int)$row['family_id'] !== (int)$user['family_id'] || !in_array($row['module'], self::ALLOWED_MODULES, true)) {
            http_response_code(404);
            echo 'Introuvable.';
            return;
        }

        $path = FileStore::resolveLocalPath(FileStore::ROUTE_PREFIX . $row['id']);
        if (!$path) {
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
