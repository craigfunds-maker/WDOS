-- v109.22: a record of every automated greeting and inspiration send, for the HQ dashboard:
-- what went out, to how many people, and by which channel (in the app, phone notification, email).
-- Rollback: DROP TABLE auto_sends;
CREATE TABLE IF NOT EXISTS auto_sends (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL,
  label VARCHAR(190) NOT NULL,
  in_app INT NOT NULL DEFAULT 0,
  push INT NOT NULL DEFAULT 0,
  email INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY ix_auto_sends (created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
