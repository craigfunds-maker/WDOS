-- v109.53: each person's own time zone, so meeting times reach her in her own time (Azeez, 24 Nov 2026: "if we schedule a
-- meeting in Nigeria time, it should show the time for other countries in their own countries").
-- The phone tells WDOS its time zone when the app opens. Empty means unknown: WDOS then uses her country's main time zone.
-- Data impact: one new column, empty for everyone. No existing data changes. No index needed (read with the person's row).
-- Rollback: ALTER TABLE users DROP COLUMN tz;
ALTER TABLE users ADD COLUMN tz VARCHAR(60) NOT NULL DEFAULT ''
