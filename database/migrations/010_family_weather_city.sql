-- Add weather city/location to family settings for the wall display
ALTER TABLE families ADD COLUMN IF NOT EXISTS weather_city VARCHAR(100) NULL DEFAULT NULL;
