-- WDOS migration 018 · Meetings run from HQ: who opened the meeting link, and which reminders have gone out.
-- Rollback: adds only. Dropping the table returns the schema to migration 017.
CREATE TABLE IF NOT EXISTS meeting_joins (
  meeting_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  joined_at DATETIME NOT NULL,
  PRIMARY KEY (meeting_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
