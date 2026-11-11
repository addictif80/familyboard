<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\FamilyNextcloudFolder;
use App\Models\StoredFile;

/**
 * Point d'entrée unique pour toute écriture/lecture/suppression de fichier appartenant à une
 * famille — enregistre chaque fichier dans stored_files (quota, consommation par module, purge
 * RGPD) et vérifie le quota familial AVANT d'accepter un nouvel upload. Les fichiers déjà sur
 * disque avant l'introduction de cette classe ne sont connus qu'après le script de rattrapage
 * (scripts/backfill_stored_files.php) — jusqu'à son exécution, totalBytesForFamily() sous-estime
 * la consommation réelle, sans bloquer personne (grandfathering).
 *
 * Deux backends de contenu, choisis par ligne (stored_files.storage_backend), pour permettre la
 * coexistence pendant la bascule progressive (voir scripts/migrate_files_to_nextcloud.php) :
 * - 'local' (historique) : disque FamilyBoard, hors webroot.
 * - 'nextcloud' (storage_nextcloud_files_enabled=1) : Group Folder partagé de la famille (voir
 *   NextcloudGroupFolderService), lu/écrit via WebDAV (NextcloudFileClient). Un cache local
 *   disposable (storage/nextcloud_cache/) accélère les lectures répétées — jamais traité comme
 *   une source de vérité : chaque lecture revalide sa fraîcheur par un GET conditionnel
 *   (If-None-Match), et une erreur réseau ne sert JAMAIS un cache non confirmé (choix produit :
 *   stockage indisponible → erreur visible plutôt qu'une copie potentiellement périmée).
 *
 * resolveLocalPath() est l'accesseur de lecture canonique : prend le chemin brut déjà stocké
 * dans la colonne historique d'un module (ex. documents.file_path) et renvoie un chemin local
 * prêt à être streamé, quel que soit le backend réel — c'est la méthode à utiliser à la place de
 * tout "BASE_PATH . $path" direct.
 */
class FileStore
{
    public const ROUTE_PREFIX = '/files/';

    public static function excludedModules(): array
    {
        $raw = AppSetting::get('storage_quota_excluded_modules', 'avatar') ?: '';
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function nextcloudFilesEnabled(): bool
    {
        return AppSetting::get('storage_nextcloud_files_enabled', '0') === '1';
    }

    /** @throws \RuntimeException si l'ajout de $additionalBytes dépasserait le quota familial
     *  (modules exclus non comptés) — ne s'applique jamais rétroactivement à un fichier déjà
     *  enregistré, seulement à un nouvel upload. Pré-contrôle local best-effort : avec le backend
     *  Nextcloud actif, l'application réelle et définitive du quota (y compris les fichiers
     *  ajoutés directement depuis l'interface Nextcloud) vient du quota natif du Group Folder,
     *  qui refuse un envoi en 507 — voir NextcloudFileClient::putFile(). */
    public static function enforceQuota(int $familyId, string $module, int $additionalBytes): void
    {
        $excluded = self::excludedModules();
        if (in_array($module, $excluded, true)) return;
        if (StorageQuota::familyQuotaUnlimited($familyId)) return;

        $quotaBytes = StorageQuota::familyQuotaGb($familyId) * 1024 * 1024 * 1024;
        $usedBytes = StoredFile::totalBytesForFamily($familyId, $excluded);
        if ($usedBytes + $additionalBytes > $quotaBytes) {
            throw new \RuntimeException(
                "Quota de stockage familial atteint (" . round($quotaBytes / 1024 / 1024 / 1024) . " Go) — " .
                "libérez de l'espace ou souscrivez un stockage additionnel pour continuer."
            );
        }
    }

    // ── Cache local disposable (backend Nextcloud) ──────────────────────────

    private static function cacheDir(int $familyId): string
    {
        return BASE_PATH . '/storage/nextcloud_cache/' . $familyId;
    }

    private static function writeLocalCache(int $familyId, string $remotePath, string $content): void
    {
        $dest = self::cacheDir($familyId) . '/' . $remotePath;
        $dir = dirname($dest);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dest, $content);
    }

    /**
     * Accesseur de lecture canonique — remplace tout "BASE_PATH . $path" direct. Accepte
     * indifféremment un chemin brut (documents.file_path, warranties.file_path...) ou une route
     * "/files/{id}" (posts.image_path, avatar...), et renvoie un chemin local prêt à être
     * streamé (file_exists/filesize/readfile/addFile...), en s'assurant d'abord sa fraîcheur si
     * le fichier est porté par Nextcloud. Retourne null si introuvable (local) ou si le NAS est
     * injoignable (jamais de cache non confirmé servi).
     */
    public static function resolveLocalPath(?string $value): ?string
    {
        if (!$value) return null;

        if (str_starts_with($value, self::ROUTE_PREFIX)) {
            $id = (int)substr($value, strlen(self::ROUTE_PREFIX));
            $row = $id > 0 ? StoredFile::findById($id) : null;
            if (!$row) return null;
            if ($row['storage_backend'] !== 'nextcloud') {
                $abs = BASE_PATH . $row['path'];
                return is_file($abs) ? $abs : null;
            }
            return self::ensureNextcloudCache($row);
        }

        $row = StoredFile::findByPath($value);
        if (!$row || $row['storage_backend'] !== 'nextcloud') {
            $abs = BASE_PATH . $value;
            return is_file($abs) ? $abs : null;
        }
        return self::ensureNextcloudCache($row);
    }

    private static function ensureNextcloudCache(array $row): ?string
    {
        $folder = FamilyNextcloudFolder::findByFamily((int)$row['family_id']);
        if (!$folder) return null;

        $cachePath = self::cacheDir((int)$row['family_id']) . '/' . $row['path'];
        $cachedEtag = is_file($cachePath) ? ($row['nextcloud_etag'] ?: null) : null;

        $r = NextcloudFileClient::getFile($folder['mount_point'], $row['path'], $cachedEtag);
        if ($r['status'] === 304) return $cachePath;
        if ($r['status'] === 200) {
            $dir = dirname($cachePath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($cachePath, $r['body']);
            if ($r['etag'] && $r['etag'] !== $row['nextcloud_etag']) {
                StoredFile::updateEtag((int)$row['id'], $r['etag']);
            }
            return $cachePath;
        }
        return null;
    }

    private static function deleteNextcloudContent(int $familyId, string $remotePath): void
    {
        $folder = FamilyNextcloudFolder::findByFamily($familyId);
        if ($folder) NextcloudFileClient::deleteFile($folder['mount_point'], $remotePath);
        $cache = self::cacheDir($familyId) . '/' . $remotePath;
        if (is_file($cache)) @unlink($cache);
    }

    // ── Documents/garanties/messages vocaux/coffre/... ──────────────────────

    /**
     * @return array{0: string, 1: string, 2: string} [chemin/clé relative, nom original, mime
     *  réel] — identique au format historique quel que soit le backend : pour 'local', chemin
     *  relatif à BASE_PATH ; pour 'nextcloud', chemin relatif au Group Folder de la famille.
     */
    public static function save(string $module, int $familyId, ?int $userId, array $file, ?array $allowedMimes = null, int $maxSize = 20 * 1024 * 1024): array
    {
        self::enforceQuota($familyId, $module, (int)($file['size'] ?? 0));

        if (self::nextcloudFilesEnabled()) {
            return self::saveToNextcloud($module, $familyId, $userId, $file, $allowedMimes, $maxSize);
        }

        [$path, $original, $mime] = OcrHelper::saveUploadedFile($file, $module, $familyId, $allowedMimes, $maxSize);
        $sizeBytes = @filesize(BASE_PATH . $path) ?: (int)($file['size'] ?? 0);
        StoredFile::create($familyId, $userId, $module, $path, $original, $mime, $sizeBytes);
        return [$path, $original, $mime];
    }

    private static function saveToNextcloud(string $module, int $familyId, ?int $userId, array $file, ?array $allowedMimes, int $maxSize): array
    {
        [$mimeBase, $ext] = OcrHelper::validateUpload($file, $allowedMimes, $maxSize);
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) throw new \RuntimeException('Erreur lors de la lecture du fichier envoyé.');

        $error = null;
        $folder = NextcloudGroupFolderService::ensureFamilyFolder($familyId, $error);
        if (!$folder) throw new \RuntimeException($error ?: 'Espace familial Nextcloud indisponible — réessayez plus tard.');

        $remotePath = $module . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $put = NextcloudFileClient::putFile($folder['mount_point'], $remotePath, $content);
        if (!$put['ok']) throw new \RuntimeException($put['error']);

        self::writeLocalCache($familyId, $remotePath, $content);
        StoredFile::create($familyId, $userId, $module, $remotePath, $file['name'] ?? null, $mimeBase, strlen($content), 'nextcloud', $put['etag']);

        return [$remotePath, $file['name'] ?? null, $mimeBase];
    }

    /**
     * Copie physique d'un fichier référencé par chemin brut (documents/garanties/...) vers un
     * nouveau module — pendant équivalent de copyRoute() pour les fichiers qui n'utilisent pas
     * le format route "/files/{id}" (ex. coffre-fort copiant un document source, voir
     * Vault::copyFromDocument). $originalName/$mime sont fournis explicitement par l'appelant
     * (pas relus depuis stored_files) car la source peut ne pas encore y être enregistrée — un
     * document antérieur au rattrapage stored_files, par exemple.
     * @return array{0:string,1:?string,2:?string}|null [nouveau chemin, nom original, mime]
     */
    public static function copyByPath(?string $sourcePath, string $newModule, int $familyId, ?int $userId, ?string $originalName, ?string $mime): ?array
    {
        if (!$sourcePath) return null;
        $source = StoredFile::findByPath($sourcePath);
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');

        if ($source && $source['storage_backend'] === 'nextcloud') {
            self::enforceQuota($familyId, $newModule, (int)$source['size_bytes']);
            $error = null;
            $folder = NextcloudGroupFolderService::ensureFamilyFolder($familyId, $error);
            if (!$folder) return null;

            $destPath = $newModule . '/' . $filename;
            $copy = NextcloudFileClient::copyFile($folder['mount_point'], $sourcePath, $destPath);
            if (!$copy['ok']) return null;

            $srcCache = self::cacheDir((int)$source['family_id']) . '/' . $sourcePath;
            if (is_file($srcCache)) {
                $destCache = self::cacheDir($familyId) . '/' . $destPath;
                if (!is_dir(dirname($destCache))) mkdir(dirname($destCache), 0755, true);
                @copy($srcCache, $destCache);
            }

            StoredFile::create($familyId, $userId, $newModule, $destPath, $originalName, $mime, (int)$source['size_bytes'], 'nextcloud', $copy['etag']);
            return [$destPath, $originalName, $mime];
        }

        $srcAbs = BASE_PATH . $sourcePath;
        if (!is_file($srcAbs)) return null;
        self::enforceQuota($familyId, $newModule, (int)@filesize($srcAbs));

        $destDir = BASE_PATH . '/storage/' . $newModule . '/' . $familyId;
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $dest = $destDir . '/' . $filename;
        if (!@copy($srcAbs, $dest)) return null;

        $relPath = '/storage/' . $newModule . '/' . $familyId . '/' . $filename;
        $sizeBytes = @filesize($dest) ?: 0;
        StoredFile::create($familyId, $userId, $newModule, $relPath, $originalName, $mime, $sizeBytes);
        return [$relPath, $originalName, $mime];
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

    /** Supprime un fichier enregistré par chemin relatif — à utiliser à la place d'un unlink()
     *  direct partout où un chemin stored_files est encore manipulé tel quel (ex. documents,
     *  garanties), pour que la ligne stored_files disparaisse avec lui, quel que soit le backend
     *  qui le portait. */
    public static function deleteByPath(?string $path): void
    {
        if (!$path) return;
        $row = StoredFile::findByPath($path);
        if ($row && $row['storage_backend'] === 'nextcloud') {
            self::deleteNextcloudContent((int)$row['family_id'], $path);
        } else {
            $abs = BASE_PATH . $path;
            if (is_file($abs)) @unlink($abs);
        }
        StoredFile::deleteByPath($path);
    }

    // ── Images web (avatars, mur, album, bébé, contact, co-parent) ──────────

    private const IMAGE_EXT_BY_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /**
     * Valide et écrit un champ $_FILES image hors webroot (storage/images/<module>/<family_id>/
     * en local, ou {module}/... dans le Group Folder familial si le backend Nextcloud est actif),
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

        if (self::nextcloudFilesEnabled()) {
            $content = file_get_contents($sourcePath);
            if ($convertedPath) @unlink($convertedPath);
            if ($content === false) {
                $lastError = 'Erreur lors de la lecture du fichier envoyé.';
                return null;
            }

            $error = null;
            $folder = NextcloudGroupFolderService::ensureFamilyFolder($familyId, $error);
            if (!$folder) {
                $lastError = $error ?: 'Espace familial Nextcloud indisponible — réessayez plus tard.';
                return null;
            }
            $remotePath = $module . '/' . $filename;
            $put = NextcloudFileClient::putFile($folder['mount_point'], $remotePath, $content);
            if (!$put['ok']) {
                $lastError = $put['error'];
                return null;
            }
            self::writeLocalCache($familyId, $remotePath, $content);
            $id = StoredFile::create($familyId, $userId, $module, $remotePath, $file['name'] ?? null, $realType, strlen($content), 'nextcloud', $put['etag']);
            return ['route' => self::ROUTE_PREFIX . $id, 'id' => $id];
        }

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
        if (!$source) return null;

        self::enforceQuota($familyId, $newModule, (int)$source['size_bytes']);

        $ext = pathinfo($source['path'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');

        if ($source['storage_backend'] === 'nextcloud') {
            $error = null;
            $folder = NextcloudGroupFolderService::ensureFamilyFolder($familyId, $error);
            if (!$folder) return null;
            $destPath = $newModule . '/' . $filename;
            $copy = NextcloudFileClient::copyFile($folder['mount_point'], $source['path'], $destPath);
            if (!$copy['ok']) return null;

            $srcCache = self::cacheDir((int)$source['family_id']) . '/' . $source['path'];
            if (is_file($srcCache)) {
                $destCache = self::cacheDir($familyId) . '/' . $destPath;
                if (!is_dir(dirname($destCache))) mkdir(dirname($destCache), 0755, true);
                @copy($srcCache, $destCache);
            }

            $id = StoredFile::create($familyId, $userId, $newModule, $destPath, $source['original_name'], $source['mime'], (int)$source['size_bytes'], 'nextcloud', $copy['etag']);
            return self::ROUTE_PREFIX . $id;
        }

        if (!is_file(BASE_PATH . $source['path'])) return null;
        $dir = BASE_PATH . '/storage/images/' . $newModule . '/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
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
        if ($row['storage_backend'] === 'nextcloud') {
            self::deleteNextcloudContent((int)$row['family_id'], $row['path']);
        } else {
            $abs = BASE_PATH . $row['path'];
            if (is_file($abs)) @unlink($abs);
        }
        StoredFile::deleteById($id);
    }
}
