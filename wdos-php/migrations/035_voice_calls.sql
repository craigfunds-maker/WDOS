-- v109.13: voice calls inside WDOS. A volunteer leader can ring another leader in her own country (same network)
-- or ring WODDI HQ, where every HQ admin who is online hears it and the first to answer takes it.
-- The audio runs through the same Jitsi / JaaS room service as the video meetings, with the camera off.
-- Rollback: DROP TABLE voice_calls; (nothing else refers to it).
CREATE TABLE IF NOT EXISTS voice_calls (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  caller_id BIGINT NOT NULL,
  callee_id BIGINT NULL,
  to_hq TINYINT NOT NULL DEFAULT 0,
  room VARCHAR(80) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'ringing',
  answered_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  answered_at DATETIME NULL,
  ended_at DATETIME NULL,
  KEY ix_vc_callee (callee_id, status, created_at),
  KEY ix_vc_hq (to_hq, status, created_at),
  KEY ix_vc_caller (caller_id, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
