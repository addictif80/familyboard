<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\StoredFile;

/**
 * Point d'entrée unique pour toute écriture/lecture/suppression de fichier appartenant à une
 * famille — enregistre chaque fichier dans stored_files (quota, consommation par module, purge
 * RGPD) et vérifie le quota familial AVANT d'accepter un nouvel upload. Les fichiers déjà sur
 * disque avant l'introduction de cette classe ne sont connus qu'après le script de rattrapage
 * (scripts/backfill_stored_files.php) — jusqu'à son exécution, totalBytesForFamily() sous-estime
 * la consommation réelle, sans bloquer personne (grandfathering).
 *
 * Deux familles de stockage :
 * - save() : documents/garanties/messages vocaux/coffre/... — délègue l'écriture bas niveau
 *   (validation MIME réelle, nom aléatoire) à OcrHelper::saveUploadedFile(), déjà hors webroot
 *   et déjà servis via un contrôleur avec contrôle d'accès. Retourne le même format qu'avant
 *   (chemin relatif à BASE_PATH) : aucun changement pour les appelants existants au-delà du
 *   paramètre $module/$userId supplémentaire.
 * - saveImage() : avatars/photos (mur, album, bébé, contact, co-parent, profil) — jusqu'ici
 *   écrits dans public/uploads/ et servis directement par Apache sans contrôle d'accès. Écrit
 *   désormais hors webroot et retourne une route "/files/{id}" (voir FileController::serve)
 *   au lieu d'un chemin : cette route est stockée telle quelle dans les colonnes existantes
 *   (avatar, image_path...) et fonctionne directement dans un <img src="..."> existant, sans
 *   modifier aucun template.
 */
class FileStore
{
    public const ROUTE_PREFIX = '/files/';

    public static function excludedModules(): array
    {
        $raw = AppSetting::get('storage_quota_excluded_modules', 'avatar') ?: '';
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** @throws \RuntimeException si l'ajout de $additionalBytes dépasserait le quota familial
     *  (modules exclus non comptés) — ne s'applique jamais rétroactivement à un fichier déjà
     *  enregistré, seulement à un nouvel upload. */
    public static function enforceQuota(int $familyId, string $module, int $additionalBytes): void
    {
        $excluded = self::excludedModules();
        if (in_array($module, $excluded, true)) return;

        $quotaBytes = StorageQuota::familyQuotaGb() * 1024 * 1024 * 1024;
        $usedBytes = StoredFile::totalBytesForFamily($familyId, $excluded);
        if ($usedBytes + $additionalBytes > $quotaBytes) {
            throw new \RuntimeException(
                "Quota de stockage familial atteint (" . round($quotaBytes / 1024 / 1024 / 1024) . " Go) — " .
                "libérez de l'espace ou souscrivez un stockage additionnel pour continuer."
            );
        }
    }

    /**
     * Documents/garanties/messages vocaux/coffre/deals/litiges/école/wishlist/liste de
     * naissance — écrit hors webroot via OcrHelper::saveUploadedFile() puis enregistre dans
     * stored_files. @return array{0: string, 1: string, 2: string} [chemin relatif, nom
     * original, mime réel] — identique au format historique de OcrHelper::saveUploadedFile().
     */
    public static function save(string $module, int $familyId, ?int $userId, array $file, ?array $allowedMimes = null, int $maxSize = 20 * 1024 * 1024): array
    {
        self::enforceQuota($familyId, $module, (int)($file['size'] ?? 0));

        [$path, $original, $mime] = OcrHelper::saveUploadedFile($file, $module, $familyId, $allowedMimes, $maxSize);

        $sizeBytes = @filesize(BASE_PATH . $path) ?: (int)($file['size'] ?? 0);
        StoredFile::create($familyId, $userId, $module, $path, $original, $mime, $sizeBytes);

        return [$path, $original, $mime];
    }

    /** Supprime un fichier quelle que soit sa représentation en base — route "/files/{id}"
     *  (avatars/photos migrés) ou chemin relatif historique (documents/garanties/...). Utilisé
     *  par les purges génériques (AccountDeletion) qui parcourent des colonnes pouvant contenir
     *  l'un ou l'autre format selon l'ancienneté de la ligne. */
    public static function deleteByValue(?string $value): void
    {
        if (!$value) return;
        str_starts_with($value, self::ROUTE_PREFIX) ? self::deleteByRoute($value) : self::deleteByPath($value);
    }

    /** Supprime un fichier enregistré par chemin relatif (BASE_PATH . $path) — à utiliser à la
     *  place d'un unlink() direct partout où un chemin stored_files est encore manipulé tel
     *  quel (ex. documents, garanties), pour que la ligne stored_files disparaisse avec lui. */
    public static function deleteByPath(?string $path): void
    {
        if (!$path) return;
        $abs = BASE_PATH . $path;
        if (is_file($abs)) @unlink($abs);
        StoredFile::deleteByPath($path);
    }

    // ── Images web (avatars, mur, album, bébé, contact, co-parent) ──────────

    private const IMAGE_EXT_BY_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /**
     * Valide et écrit un champ $_FILES image hors webroot (storage/images/<module>/<family_id>/),
     * enregistre dans stored_files, et retourne une route "/files/{id}" à stocker directement
     * dans la colonne existante (avatar, image_path...) — fonctionne sans modification des
     * templates qui affichent déjà cette colonne dans un <img src>.
     *
     * @return array{route: string, id: int}|null null si rien n'a été soumis ou en cas de rejet
     *   (voir $lastError pour le message à afficher, même convention que BaseController::uploadImage()).
     */
    public static function saveImage(string $field, string $module, int $familyId, ?int $userId, ?string &$lastError = null): ?array
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $lastError = 'Erreur lors de l\'envoi du fichier.';
            return null;
        }
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $lastError = 'Fichier trop volumineux (' . round(UPLOAD_MAX_SIZE / 1024 / 1024) . ' Mo maximum).';
            return null;
        }

        $realType = @mime_content_type($file['tmp_name']) ?: '';
        $convertedPath = null;
        if (in_array($realType, ['image/heic', 'image/heif'], true) && class_exists(\Imagick::class)) {
            try {
                $convertedPath = tempnam(sys_get_temp_dir(), 'heic') . '.jpg';
                $img = new \Imagick($file['tmp_name']);
                $img->setImageFormat('jpeg');
                $img->writeImage($convertedPath);
                $img->destroy();
                $realType = 'image/jpeg';
            } catch (\Throwable) {
                @unlink($convertedPath);
                $lastError = 'Impossible de convertir cette photo (HEIC). Réessayez avec un JPEG/PNG.';
                return null;
            }
        }

        if (!isset(self::IMAGE_EXT_BY_MIME[$realType])) {
            if ($convertedPath) @unlink($convertedPath);
            $lastError = in_array($realType, ['image/heic', 'image/heif'], true)
                ? "Format HEIC (photo iPhone) non supporté par ce serveur."
                : 'Format d\'image non supporté (JPEG, PNG, GIF ou WEBP uniquement).';
            return null;
        }
        $sourcePath = $convertedPath ?: $file['tmp_name'];
        if (@getimagesize($sourcePath) === false) {
            if ($convertedPath) @unlink($convertedPath);
            $lastError = 'Fichier image invalide ou corrompu.';
            return null;
        }

        try {
            self::enforceQuota($familyId, $module, (int)$file['size']);
        } catch (\RuntimeException $e) {
            if ($convertedPath) @unlink($convertedPath);
            $lastError = $e->getMessage();
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::IMAGE_EXT_BY_MIME[$realType];
        $dir = BASE_PATH . '/storage/images/' . $module . '/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $dest = $dir . '/' . $filename;

        $moved = $convertedPath ? rename($convertedPath, $dest) : move_uploaded_file($file['tmp_name'], $dest);
        if (!$moved) {
            if ($convertedPath) @unlink($convertedPath);
            $lastError = 'Échec de l\'enregistrement du fichier sur le serveur.';
            return null;
        }

        $relPath = '/storage/images/' . $module . '/' . $familyId . '/' . $filename;
        $sizeBytes = @filesize($dest) ?: (int)$file['size'];
        $id = StoredFile::create($familyId, $userId, $module, $relPath, $file['name'] ?? null, $realType, $sizeBytes);

        return ['route' => self::ROUTE_PREFIX . $id, 'id' => $id];
    }

    /**
     * Copie physiquement le fichier référencé par une route "/files/{id}" vers un nouveau
     * module, avec sa propre ligne stored_files — utilisé quand un même fichier doit pouvoir
     * être supprimé indépendamment dans ses deux contextes (ex. partage d'une photo d'album
     * sur le mur : la suppression de la photo dans l'album ne doit jamais casser le post qui
     * l'a partagée, et inversement). Retourne la nouvelle route, ou null si la source est
     * introuvable.
     */
    public static function copyRoute(?string $route, string $newModule, int $familyId, ?int $userId): ?string
    {
        if (!$route || !str_starts_with($route, self::ROUTE_PREFIX)) return null;
        $sourceId = (int)substr($route, strlen(self::ROUTE_PREFIX));
        $source = StoredFile::findById($sourceId);
        if (!$source || !is_file(BASE_PATH . $source['path'])) return null;

        self::enforceQuota($familyId, $newModule, (int)$source['size_bytes']);

        $ext = pathinfo($source['path'], PATHINFO_EXTENSION);
        $dir = BASE_PATH . '/storage/images/' . $newModule . '/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        $dest = $dir . '/' . $filename;
        if (!@copy(BASE_PATH . $source['path'], $dest)) return null;

        $relPath = '/storage/images/' . $newModule . '/' . $familyId . '/' . $filename;
        $id = StoredFile::create($familyId, $userId, $newModule, $relPath, $source['original_name'], $source['mime'], (int)@filesize($dest));
        return self::ROUTE_PREFIX . $id;
    }

    /** Supprime une image enregistrée par sa route "/files/{id}" (voir saveImage()) — à utiliser
     *  à la place d'un unlink() direct quand une colonne (avatar, image_path...) a été migrée
     *  vers ce format. Sans effet si $route ne correspond pas au format attendu (ancien chemin
     *  public/uploads pas encore rattrapé, ou vide). */
    public static function deleteByRoute(?string $route): void
    {
        if (!$route || !str_starts_with($route, self::ROUTE_PREFIX)) return;
        $id = (int)substr($route, strlen(self::ROUTE_PREFIX));
        if ($id <= 0) return;
        $row = StoredFile::findById($id);
        if (!$row) return;
        $abs = BASE_PATH . $row['path'];
        if (is_file($abs)) @unlink($abs);
        StoredFile::deleteById($id);
    }
}
