-- Migration des comptes créés avant l'intégration LDAP (voir App\Core\LdapService,
-- AdminController::analyzeStorageLdapMigration/resolveStorageLdapMigration) : le mot de passe
-- LDAP ne peut être posé qu'à la prochaine connexion réussie du membre (FamilyBoard n'a jamais
-- accès au mot de passe en clair en dehors de cet instant) — ldap_resolved_username porte la
-- décision de l'admin pour un conflit de uid (adopter un compte existant, ou un uid alternatif),
-- appliquée automatiquement dès que ce prochain login déclenche le provisioning.
ALTER TABLE users ADD COLUMN IF NOT EXISTS ldap_resolved_username VARCHAR(60) NULL DEFAULT NULL;
