-- Surcharge optionnelle de l'identifiant utilisé pour les appels API Nextcloud (OCS Provisioning)
-- — distincte de users.ldap_username (le vrai uid LDAP, jamais à modifier). NULL dans l'immense
-- majorité des cas (le uid LDAP sert aussi d'identifiant Nextcloud) : ne se renseigne que pour
-- un compte où Nextcloud a dû renommer son compte interne avec un suffixe numérique suite à une
-- collision d'uid (voir App\Core\NextcloudService, docblock de classe) — situation rare,
-- observée après coup, jamais prévisible à l'avance.
ALTER TABLE users ADD COLUMN IF NOT EXISTS nextcloud_username VARCHAR(80) NULL DEFAULT NULL;
