-- WDOS migration 016 · UI/UX prototype and implementation handoff of 11 November 2026.
-- Approved message templates in every language, evidence on tasks, an announcement to one member, and the volunteer step.
CREATE TABLE IF NOT EXISTS message_templates (
  tkey VARCHAR(40) NOT NULL,
  lang VARCHAR(5) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(600) NOT NULL,
  updated_by BIGINT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tkey, lang)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE task_comments ADD COLUMN kind VARCHAR(12) NOT NULL DEFAULT 'comment';
ALTER TABLE task_comments ADD COLUMN url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE announcements ADD COLUMN audience_user BIGINT NULL;
ALTER TABLE users ADD COLUMN volunteer_since DATETIME NULL
