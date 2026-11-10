-- WDOS migration 004 · Reporting and Accountability.
-- Report templates are data (JSON definitions), exactly as in the current WDOS, so the Founder-approved
-- templates can be loaded without code changes. Nothing here invents a template.

CREATE TABLE IF NOT EXISTS report_templates (
  code VARCHAR(40) NOT NULL PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  cadence VARCHAR(12) NOT NULL DEFAULT 'adhoc',
  requires_evidence TINYINT NOT NULL DEFAULT 0,
  definition MEDIUMTEXT NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  loaded_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_instances (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_no VARCHAR(24) NULL,
  template VARCHAR(40) NOT NULL,
  unit_key VARCHAR(190) NOT NULL,
  network VARCHAR(8) NOT NULL,
  org_unit_id BIGINT NOT NULL,
  unit_label VARCHAR(190) NOT NULL DEFAULT '',
  owner_id BIGINT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  due_on DATE NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  version INT NOT NULL DEFAULT 1,
  answers MEDIUMTEXT NULL,
  snapshot MEDIUMTEXT NULL,
  is_nil TINYINT NOT NULL DEFAULT 0,
  nil_reason VARCHAR(400) NOT NULL DEFAULT '',
  submitted_at DATETIME NULL,
  on_time TINYINT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_report_no (report_no),
  KEY ix_report_unit (unit_key, template, period_start),
  KEY ix_report_owner (owner_id, status),
  KEY ix_report_status (status, period_start)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_reviews (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT NOT NULL,
  version INT NOT NULL,
  action VARCHAR(16) NOT NULL,
  note VARCHAR(1000) NOT NULL DEFAULT '',
  by_user BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_reviews (report_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_evidence (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT NOT NULL,
  url VARCHAR(600) NOT NULL,
  note VARCHAR(300) NOT NULL DEFAULT '',
  by_user BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_evidence (report_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_contributions (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  note VARCHAR(2000) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_contrib (report_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_records (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  org_unit_id BIGINT NULL,
  network VARCHAR(8) NULL,
  category VARCHAR(40) NOT NULL,
  happened_at DATETIME NOT NULL,
  what VARCHAR(1500) NOT NULL,
  people_reached INT NOT NULL DEFAULT 0,
  people_category VARCHAR(160) NOT NULL DEFAULT '',
  result VARCHAR(1000) NOT NULL DEFAULT '',
  follow_up_action VARCHAR(400) NOT NULL DEFAULT '',
  follow_up_due DATE NULL,
  follow_up_done TINYINT NOT NULL DEFAULT 0,
  evidence_ref VARCHAR(400) NOT NULL DEFAULT '',
  escalate TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY ix_activity_user (user_id, happened_at),
  KEY ix_activity_unit (org_unit_id, happened_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issues (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  issue_no VARCHAR(24) NULL,
  raised_by BIGINT NOT NULL,
  org_unit_id BIGINT NULL,
  network VARCHAR(8) NULL,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'other',
  severity VARCHAR(12) NOT NULL DEFAULT 'amber',
  impact VARCHAR(1000) NOT NULL DEFAULT '',
  action_taken VARCHAR(1000) NOT NULL DEFAULT '',
  decision_required VARCHAR(1000) NOT NULL DEFAULT '',
  escalated_to VARCHAR(16) NOT NULL DEFAULT 'supervisor',
  due_on DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_issues_unit (org_unit_id, status),
  KEY ix_issues_status (status, severity)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
