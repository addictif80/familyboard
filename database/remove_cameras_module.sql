-- Module Caméras IP retiré (risque SSRF non atténuable tant que l'inscription
-- est ouverte au public : go2rtc_url et stream_url étaient réglables par tout
-- admin de famille, y compris un compte tout juste auto-inscrit).
DROP TABLE IF EXISTS cameras;
ALTER TABLE families DROP COLUMN IF EXISTS go2rtc_url;
