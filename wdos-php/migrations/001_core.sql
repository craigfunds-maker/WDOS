-- WDOS on PHP + TiDB/MySQL · migration 001 · core tables
-- One schema change per statement (TiDB rule). No stored procedures, so this
-- also runs unchanged on shared-hosting MySQL / MariaDB.

CREATE TABLE IF NOT EXISTS users (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  member_no VARCHAR(24) NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  google_sub VARCHAR(64) NULL,
  first_name VARCHAR(80) NOT NULL DEFAULT '',
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  phone VARCHAR(32) NOT NULL DEFAULT '',
  dob DATE NULL,
  network VARCHAR(8) NULL,
  country_code CHAR(2) NULL,
  org_unit_id BIGINT NULL,
  state_region VARCHAR(120) NOT NULL DEFAULT '',
  lga VARCHAR(120) NOT NULL DEFAULT '',
  community VARCHAR(120) NOT NULL DEFAULT '',
  role VARCHAR(24) NOT NULL DEFAULT 'member',
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  signup_method VARCHAR(16) NOT NULL DEFAULT 'email',
  has_photo TINYINT NOT NULL DEFAULT 0,
  must_change_password TINYINT NOT NULL DEFAULT 0,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_member_no (member_no),
  UNIQUE KEY uq_users_google (google_sub),
  KEY ix_users_created (created_at),
  KEY ix_users_network (network, created_at),
  KEY ix_users_unit (org_unit_id),
  KEY ix_users_country (country_code)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_photos (
  user_id BIGINT NOT NULL PRIMARY KEY,
  mime VARCHAR(40) NOT NULL,
  bytes MEDIUMBLOB NOT NULL,
  updated_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id BIGINT NOT NULL,
  method VARCHAR(16) NOT NULL DEFAULT 'password',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY ix_sessions_user (user_id),
  KEY ix_sessions_exp (expires_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_attempts (bucket, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_challenges (
  id CHAR(32) NOT NULL PRIMARY KEY,
  purpose VARCHAR(24) NOT NULL,
  user_id BIGINT NULL,
  challenge VARCHAR(255) NOT NULL,
  data VARCHAR(500) NOT NULL DEFAULT '',
  expires_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id BIGINT NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webauthn_credentials (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  credential_id VARCHAR(255) NOT NULL,
  public_key TEXT NOT NULL,
  alg INT NOT NULL DEFAULT -7,
  sign_count BIGINT NOT NULL DEFAULT 0,
  label VARCHAR(80) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_cred (credential_id),
  KEY ix_cred_user (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_units (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT NULL,
  level VARCHAR(24) NOT NULL,
  name VARCHAR(160) NOT NULL,
  country_code CHAR(2) NULL,
  path VARCHAR(190) NOT NULL DEFAULT '',
  KEY ix_units_parent (parent_id, name),
  KEY ix_units_path (path),
  KEY ix_units_country (country_code, level)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS positions (
  code VARCHAR(40) NOT NULL PRIMARY KEY,
  title_wgmn VARCHAR(80) NOT NULL,
  title_wnnn VARCHAR(80) NOT NULL,
  level VARCHAR(24) NOT NULL,
  rank_no INT NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seats (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  position_code VARCHAR(40) NOT NULL,
  network VARCHAR(8) NOT NULL,
  org_unit_id BIGINT NOT NULL,
  area_label VARCHAR(120) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'appointed',
  appointed_by BIGINT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  KEY ix_seats_user (user_id, ends_at),
  KEY ix_seats_unit (org_unit_id, ends_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  kind VARCHAR(24) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(600) NOT NULL DEFAULT '',
  url VARCHAR(255) NOT NULL DEFAULT '',
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_notif_user (user_id, id),
  KEY ix_notif_unread (user_id, read_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_prefs (
  user_id BIGINT NOT NULL,
  kind VARCHAR(24) NOT NULL,
  in_app TINYINT NOT NULL DEFAULT 1,
  push TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, kind)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  endpoint TEXT NOT NULL,
  p256dh VARCHAR(140) NOT NULL,
  auth VARCHAR(60) NOT NULL,
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  last_ok_at DATETIME NULL,
  fail_count INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_push_endpoint (endpoint_hash),
  KEY ix_push_user (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_outbox (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  subscription_id BIGINT NOT NULL,
  payload VARCHAR(1200) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'queued',
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY ix_outbox (status, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  author_id BIGINT NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  audience_network VARCHAR(8) NOT NULL DEFAULT 'ALL',
  audience_role VARCHAR(12) NOT NULL DEFAULT 'all',
  audience_country CHAR(2) NULL,
  recipients INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY ix_ann_created (created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT NULL,
  subject_id BIGINT NULL,
  action VARCHAR(40) NOT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY ix_activity (created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_runs (
  job VARCHAR(40) NOT NULL,
  ran_on DATE NOT NULL,
  ran_at DATETIME NOT NULL,
  result VARCHAR(190) NOT NULL DEFAULT '',
  PRIMARY KEY (job, ran_on)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  k VARCHAR(60) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
