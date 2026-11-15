-- WDOS migration 024 · Report and task documents, direct messages between leaders, WODDI Envoys, HQ staff features.
-- Data impact: new tables, and new columns with defaults. No existing row changes meaning:
--   users.hq_features NULL means "every HQ feature", exactly as before. users.designation is empty.
--   mail_outbox.batch_id is empty on every existing row.
-- Rollback: drop the new tables and columns. Older code never reads them.
CREATE TABLE IF NOT EXISTS doc_files (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  mime VARCHAR(120) NOT NULL,
  size INT NOT NULL,
  sha256 CHAR(64) NOT NULL,
  chunks INT NOT NULL DEFAULT 0,
  uploaded_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_doc_files_by (uploaded_by, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS doc_chunks (
  file_id BIGINT NOT NULL,
  seq INT NOT NULL,
  bytes MEDIUMBLOB NOT NULL,
  PRIMARY KEY (file_id, seq)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS doc_links (
  file_id BIGINT NOT NULL,
  owner_type VARCHAR(16) NOT NULL,
  owner_id BIGINT NOT NULL,
  role VARCHAR(16) NOT NULL DEFAULT 'document',
  version INT NOT NULL DEFAULT 1,
  added_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  removed_at DATETIME NULL,
  PRIMARY KEY (file_id, owner_type, owner_id),
  KEY ix_doc_links_owner (owner_type, owner_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dm_threads (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lo_id BIGINT NOT NULL,
  hi_id BIGINT NOT NULL,
  lo_read BIGINT NOT NULL DEFAULT 0,
  hi_read BIGINT NOT NULL DEFAULT 0,
  lo_hidden TINYINT NOT NULL DEFAULT 0,
  hi_hidden TINYINT NOT NULL DEFAULT 0,
  last_sender BIGINT NULL,
  last_snippet VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  last_at DATETIME NOT NULL,
  UNIQUE KEY uq_dm_pair (lo_id, hi_id),
  KEY ix_dm_hi (hi_id, last_at),
  KEY ix_dm_lo (lo_id, last_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dm_messages (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT NOT NULL,
  sender_id BIGINT NOT NULL,
  batch_id BIGINT NULL,
  subject VARCHAR(190) NOT NULL DEFAULT '',
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  KEY ix_dm_msg_thread (thread_id, id),
  KEY ix_dm_msg_batch (batch_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dm_batches (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id BIGINT NOT NULL,
  subject VARCHAR(190) NOT NULL DEFAULT '',
  audience VARCHAR(600) NOT NULL DEFAULT '',
  recipients INT NOT NULL DEFAULT 0,
  undo_until DATETIME NOT NULL,
  undone_at DATETIME NULL,
  released_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_dm_batch_sender (sender_id, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dm_drafts (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  subject VARCHAR(190) NOT NULL DEFAULT '',
  body TEXT NOT NULL,
  audience VARCHAR(2000) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  KEY ix_dm_drafts_user (user_id, updated_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE mail_outbox ADD COLUMN batch_id BIGINT NULL;
ALTER TABLE users ADD COLUMN hq_features VARCHAR(400) NULL;
ALTER TABLE users ADD COLUMN designation VARCHAR(160) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN guide_seen_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS envoys (
  user_id BIGINT NOT NULL PRIMARY KEY,
  link_id BIGINT NOT NULL,
  country_code VARCHAR(2) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'active',
  created_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO policy_rules (rule_key, rule_value, effective_from, note, created_at) SELECT 'report_document_required', 'yes', '2026-11-15', 'yes: a monthly leadership report is submitted with its PDF or Word document attached.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM policy_rules WHERE rule_key = 'report_document_required');
INSERT INTO policy_rules (rule_key, rule_value, effective_from, note, created_at) SELECT 'hq_notify_email', 'woddingo@gmail.com', '2026-11-15', 'The HQ inbox that receives an email when a report, task or message reaches HQ.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM policy_rules WHERE rule_key = 'hq_notify_email')
;
CREATE TABLE IF NOT EXISTS meeting_requests (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(24) NOT NULL DEFAULT '',
  requester_id BIGINT NOT NULL,
  network VARCHAR(8) NOT NULL DEFAULT '',
  country_code VARCHAR(2) NULL,
  region VARCHAR(190) NOT NULL DEFAULT '',
  leader_name VARCHAR(190) NOT NULL DEFAULT '',
  title VARCHAR(190) NOT NULL,
  reason TEXT NOT NULL,
  attendees_text TEXT NOT NULL,
  attendee_ids VARCHAR(4000) NOT NULL DEFAULT '',
  expected_count INT NOT NULL DEFAULT 0,
  graphics TEXT NOT NULL,
  other_needs TEXT NOT NULL,
  starts_at DATETIME NOT NULL,
  duration_min INT NOT NULL DEFAULT 60,
  tz VARCHAR(60) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  hq_note TEXT NULL,
  decided_by BIGINT NULL,
  decided_at DATETIME NULL,
  room VARCHAR(80) NOT NULL DEFAULT '',
  minutes TEXT NULL,
  attendance_count INT NULL,
  attendance_names TEXT NULL,
  minutes_by BIGINT NULL,
  minutes_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_mr_requester (requester_id, created_at),
  KEY ix_mr_status (status, starts_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS meeting_request_joins (
  request_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  first_at DATETIME NOT NULL,
  last_at DATETIME NOT NULL,
  PRIMARY KEY (request_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
