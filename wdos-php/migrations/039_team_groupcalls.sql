-- v109.24: a leader's team. Meetings with chosen leaders, and group voice calls (up to 8 people, direct audio).
-- New tables only; nothing existing is changed. Rollback: DROP TABLE meeting_invitees, voice_groups, voice_group_people, voice_group_signals;
CREATE TABLE IF NOT EXISTS meeting_invitees (
  meeting_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  PRIMARY KEY (meeting_id, user_id),
  KEY ix_mi_user (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS voice_groups (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  host_id BIGINT NOT NULL,
  title VARCHAR(120) NOT NULL DEFAULT '',
  status VARCHAR(8) NOT NULL DEFAULT 'live',
  hq_ring DATETIME NULL,
  hq_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  KEY ix_vg_status (status, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS voice_group_people (
  group_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'ringing',
  invited_by BIGINT NOT NULL,
  invited_at DATETIME NOT NULL,
  joined_at DATETIME NULL,
  left_at DATETIME NULL,
  PRIMARY KEY (group_id, user_id),
  KEY ix_vgp_user (user_id, status)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS voice_group_signals (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT NOT NULL,
  from_id BIGINT NOT NULL,
  to_id BIGINT NOT NULL,
  kind VARCHAR(8) NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_vgs (group_id, to_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
