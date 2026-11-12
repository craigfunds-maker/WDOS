-- WDOS migration 017 · Production handoff of 11 November 2026 (Q01 to Q48, D01 to D14, T01 to T36).
-- Decision register, confidential cases, protected registration drafts, safe retries, annual calendar,
-- meeting replies, the 80 percent workflow catalogue, where members heard of WODDI, and membership lifecycle dates.
-- Rollback: every statement here only adds. Dropping the new tables and columns returns the schema to migration 016.
CREATE TABLE IF NOT EXISTS decisions (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(8) NOT NULL,
  decision VARCHAR(600) NOT NULL,
  approver VARCHAR(160) NOT NULL,
  approved_on DATE NOT NULL,
  version INT NOT NULL DEFAULT 1,
  recorded_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_decisions_code (code, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cases (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(24) NOT NULL,
  reporter_id BIGINT NOT NULL,
  category VARCHAR(24) NOT NULL,
  about_user_id BIGINT NULL,
  about_text VARCHAR(190) NOT NULL DEFAULT '',
  body TEXT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'received',
  outcome VARCHAR(1000) NOT NULL DEFAULT '',
  handled_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY ux_cases_ref (ref),
  KEY ix_cases_reporter (reporter_id),
  KEY ix_cases_status (status, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_notes (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT NOT NULL,
  author_id BIGINT NOT NULL,
  note VARCHAR(2000) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_case_notes (case_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reg_drafts (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  data TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY ix_reg_drafts_exp (expires_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS idem_keys (
  key_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  programme VARCHAR(40) NOT NULL DEFAULT '',
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  network VARCHAR(8) NOT NULL DEFAULT 'ALL',
  country_code VARCHAR(2) NOT NULL DEFAULT '',
  note VARCHAR(600) NOT NULL DEFAULT '',
  reminded VARCHAR(8) NOT NULL DEFAULT '',
  created_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_calendar (starts_on)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_rsvps (
  meeting_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  answer VARCHAR(8) NOT NULL,
  answered_at DATETIME NOT NULL,
  PRIMARY KEY (meeting_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_catalogue (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  workflow_id VARCHAR(12) NOT NULL,
  area VARCHAR(60) NOT NULL,
  workflow_name VARCHAR(190) NOT NULL,
  steps VARCHAR(400) NOT NULL DEFAULT '',
  trigger_text VARCHAR(190) NOT NULL DEFAULT '',
  outcome VARCHAR(190) NOT NULL DEFAULT '',
  frequency VARCHAR(40) NOT NULL DEFAULT '',
  manual_minutes_baseline INT NULL,
  eligible_weight DECIMAL(8,2) NULL,
  digitally_executable VARCHAR(8) NOT NULL DEFAULT 'no',
  exception_coverage VARCHAR(8) NOT NULL DEFAULT 'no',
  used_in_production VARCHAR(8) NOT NULL DEFAULT 'no',
  test_id VARCHAR(40) NOT NULL DEFAULT '',
  verified_by VARCHAR(120) NOT NULL DEFAULT '',
  verification_date DATE NULL,
  source_system VARCHAR(40) NOT NULL DEFAULT 'WDOS',
  updated_at DATETIME NOT NULL,
  UNIQUE KEY ux_workflow (workflow_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN source VARCHAR(24) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN campaign VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN paused_at DATETIME NULL;
ALTER TABLE users ADD COLUMN withdrawn_at DATETIME NULL;
ALTER TABLE users ADD COLUMN age_notice_at DATETIME NULL;
ALTER TABLE seats ADD COLUMN status_note VARCHAR(200) NOT NULL DEFAULT '';
ALTER TABLE seats ADD COLUMN end_reason VARCHAR(24) NOT NULL DEFAULT ''
