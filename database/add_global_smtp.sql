-- SMTP is configured once, system-wide, by the platform administrator —
-- not per family. Migrate the first per-family config found (if any) into
-- the global app_settings store, then drop the now-unused table.
INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_host', host FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_port', port FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_username', username FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_password', password FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_from_email', from_email FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_from_name', from_name FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO app_settings (`key`, `value`)
SELECT 'smtp_encryption', encryption FROM smtp_settings ORDER BY id ASC LIMIT 1
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

DROP TABLE IF EXISTS smtp_settings;
