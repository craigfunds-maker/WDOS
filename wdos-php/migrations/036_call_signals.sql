-- v109.14: voice calls without Jitsi. The two phones talk directly (WebRTC); WDOS only passes the short
-- connection messages between them (the offer and the answer). Rows are deleted when the call ends.
-- Rollback: DROP TABLE voice_call_signals;
CREATE TABLE IF NOT EXISTS voice_call_signals (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  call_id BIGINT NOT NULL,
  from_id BIGINT NOT NULL,
  kind VARCHAR(12) NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_vcs_call (call_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
