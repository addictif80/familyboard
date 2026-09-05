-- Rapport mensuel de chiffre d'affaires (paiements Stripe encaissés par la plateforme elle-
-- même, pas le budget d'une famille) pour la déclaration URSSAF de l'auto-entrepreneur
-- exploitant FamilyBoard — voir App\Core\UrssafReport et cron.php::sendUrssafReport().
-- Désactivé par défaut : n'envoie jamais rien tant que l'admin ne l'active pas explicitement.
INSERT INTO app_settings (`key`, `value`) VALUES
    ('urssaf_report_enabled', '0'),
    ('urssaf_report_day', '5'),
    ('urssaf_report_last_sent', '')
ON DUPLICATE KEY UPDATE `key` = `key`;
