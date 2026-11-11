-- Bascule du stockage des fichiers des modules vers Nextcloud (Group Folders), au lieu du
-- disque local — voir App\Core\FileStore, App\Core\NextcloudGroupFolderService,
-- App\Core\NextcloudFileClient. Désactivé par défaut (storage_nextcloud_files_enabled=0) :
-- aucun changement pour une famille existante tant que l'admin n'active pas explicitement
-- cette bascule ni n'a lancé la migration (voir scripts/migrate_files_to_nextcloud.php).
--
-- Chaque famille a son propre groupe Nextcloud ("family_<id>") + son propre Group Folder
-- (quota natif indépendant, voir NextcloudGroupFolderService::applyFamilyQuota) — partagé en
-- lecture/écriture à tous ses membres LDAP, et au compte technique admin (qui doit lui-même
-- être membre du groupe pour pouvoir monter/écrire dans le dossier via WebDAV, voir vérification
-- en conditions réelles contre une instance Nextcloud 28 + app Group Folders).
CREATE TABLE IF NOT EXISTS family_nextcloud_folders (
    family_id        INT NOT NULL PRIMARY KEY,
    nextcloud_group   VARCHAR(64)  NOT NULL,
    groupfolder_id    INT          NOT NULL,
    mount_point       VARCHAR(190) NOT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- storage_backend : quel backend porte PHYSIQUEMENT le contenu de cette ligne — 'local' (disque
-- FamilyBoard, comportement historique) ou 'nextcloud' (Group Folder de la famille). Permet la
-- coexistence des deux pendant la transition (bascule progressive famille par famille via le
-- script de migration), sans jamais deviner depuis le format du chemin.
-- nextcloud_etag : ETag renvoyé par Nextcloud au dernier écrit/lu connu — utilisé pour une
-- requête GET conditionnelle (If-None-Match) à chaque lecture, afin de servir le cache local
-- disposable tout en détectant une modification faite directement depuis Nextcloud (hors
-- FamilyBoard) sans retransférer le contenu quand rien n'a changé.
ALTER TABLE stored_files ADD COLUMN IF NOT EXISTS storage_backend ENUM('local','nextcloud') NOT NULL DEFAULT 'local';
ALTER TABLE stored_files ADD COLUMN IF NOT EXISTS nextcloud_etag VARCHAR(100) NULL DEFAULT NULL;

INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_nextcloud_files_enabled', '0')
ON DUPLICATE KEY UPDATE `key` = `key`;
