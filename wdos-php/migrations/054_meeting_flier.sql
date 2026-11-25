-- v109.56: a flier for each meeting (Azeez, 25 Nov 2026): HQ uploads a flier picture or WDOS makes one from the meeting's
-- details; the flier's words are translated into each leader's own language; countdown on dashboards and by email; one-tap
-- replies from the email. Data impact: four new columns on meetings (empty for every existing meeting) and one new table.
-- Rollback: ALTER TABLE meetings DROP COLUMN flier_image, DROP COLUMN flier_mime, DROP COLUMN flier_headline, DROP COLUMN flier_text;
--           DROP TABLE meeting_flier_texts;
ALTER TABLE meetings ADD COLUMN flier_image VARCHAR(120) NOT NULL DEFAULT '', ADD COLUMN flier_mime VARCHAR(20) NOT NULL DEFAULT '', ADD COLUMN flier_headline VARCHAR(190) NOT NULL DEFAULT '', ADD COLUMN flier_text TEXT NULL;
CREATE TABLE IF NOT EXISTS meeting_flier_texts (
  meeting_id BIGINT NOT NULL,
  lang VARCHAR(5) NOT NULL,
  headline VARCHAR(255) NOT NULL DEFAULT '',
  body TEXT NULL,
  source VARCHAR(8) NOT NULL DEFAULT 'ai',
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (meeting_id, lang)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
