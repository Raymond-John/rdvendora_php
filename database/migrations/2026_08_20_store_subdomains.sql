-- Store subdomain support uses the existing stores.store_slug column
-- (VARCHAR(100), UNIQUE KEY already in database/schema.sql).
-- No new table is required.
--
-- Optional: backfill empty/invalid slugs for older rows (run once on Hostinger phpMyAdmin):

UPDATE stores
SET store_slug = CONCAT('store-', id)
WHERE store_slug IS NULL OR store_slug = '' OR store_slug REGEXP '[^a-z0-9-]';

-- Ensure uniqueness index exists (safe if already present):
-- ALTER TABLE stores ADD UNIQUE KEY store_slug (store_slug);
