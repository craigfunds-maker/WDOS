-- WDOS migration 008 · Referral links, referral activation (3-Day) and open-call activation (7-Day).
-- One schema change per statement (TiDB rule). Programme content is data, loaded from the current WDOS.

CREATE TABLE IF NOT EXISTS referral_links (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(24) NOT NULL,
  owner_id BIGINT NOT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'member',
  network VARCHAR(8) NOT NULL,
  org_unit_id BIGINT NULL,
  position_code VARCHAR(40) NOT NULL DEFAULT '',
  area_label VARCHAR(120) NOT NULL DEFAULT '',
  label VARCHAR(160) NOT NULL DEFAULT '',
  campaign VARCHAR(160) NOT NULL DEFAULT '',
  active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_ref_code (code),
  KEY ix_ref_owner (owner_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_visits (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  link_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_ref_visits (link_id, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referrals (
  user_id BIGINT NOT NULL PRIMARY KEY,
  link_id BIGINT NULL,
  referrer_id BIGINT NULL,
  category VARCHAR(24) NOT NULL DEFAULT '',
  referrer_name VARCHAR(160) NOT NULL DEFAULT '',
  position_code VARCHAR(40) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY ix_referrals_link (link_id),
  KEY ix_referrals_referrer (referrer_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activations (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  route VARCHAR(8) NOT NULL,
  network VARCHAR(8) NOT NULL,
  position_code VARCHAR(40) NOT NULL,
  org_unit_id BIGINT NOT NULL,
  area_label VARCHAR(120) NOT NULL DEFAULT '',
  referrer_name VARCHAR(160) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'in_progress',
  deadline DATE NOT NULL,
  extension_status VARCHAR(12) NOT NULL DEFAULT '',
  extension_reason VARCHAR(600) NOT NULL DEFAULT '',
  return_note VARCHAR(1000) NOT NULL DEFAULT '',
  outcome VARCHAR(20) NOT NULL DEFAULT '',
  outcome_note VARCHAR(1000) NOT NULL DEFAULT '',
  disclosed VARCHAR(1000) NOT NULL DEFAULT '',
  started_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  decided_at DATETIME NULL,
  decided_by BIGINT NULL,
  KEY ix_act_user (user_id),
  KEY ix_act_status (route, status)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activation_days (
  route VARCHAR(8) NOT NULL,
  day_no INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  instruction VARCHAR(1000) NOT NULL DEFAULT '',
  content TEXT NULL,
  notice VARCHAR(1000) NOT NULL DEFAULT '',
  questions TEXT NULL,
  acks TEXT NULL,
  origin VARCHAR(12) NOT NULL DEFAULT 'starter',
  PRIMARY KEY (route, day_no)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activation_progress (
  activation_id BIGINT NOT NULL,
  day_no INT NOT NULL,
  answers TEXT NULL,
  acked VARCHAR(400) NOT NULL DEFAULT '',
  reached_end TINYINT NOT NULL DEFAULT 0,
  seconds INT NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  PRIMARY KEY (activation_id, day_no)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS readiness_items (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(14) NOT NULL,
  seq INT NOT NULL,
  section VARCHAR(4) NOT NULL DEFAULT '',
  section_title VARCHAR(190) NOT NULL DEFAULT '',
  body VARCHAR(1500) NOT NULL,
  KEY ix_readiness (kind, seq)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS readiness_responses (
  activation_id BIGINT NOT NULL PRIMARY KEY,
  statements TEXT NULL,
  short_answers TEXT NULL,
  clarification_note VARCHAR(1500) NOT NULL DEFAULT '',
  confirmations VARCHAR(200) NOT NULL DEFAULT '',
  readiness VARCHAR(16) NOT NULL DEFAULT '',
  signature_name VARCHAR(160) NOT NULL DEFAULT '',
  submitted_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_staging (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tbl VARCHAR(80) NOT NULL,
  data MEDIUMTEXT NOT NULL,
  loaded_at DATETIME NOT NULL,
  KEY ix_staging (tbl)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starter Day 1 for both routes: the three documents the current WDOS app ships as the Day 1 reading.
-- The remaining days, their questions and the readiness assessment are loaded from the current WDOS on the setup page.
INSERT IGNORE INTO activation_days (route,day_no,title,instruction,content,notice,questions,acks,origin) VALUES
('ref3',1,'Day 1 reading','Read each document to the end. The Next button becomes available once you have reached the end of all of them.','[{"doc":"/assets/docs/leadership-manual.html","title":"Volunteer Leadership Manual"},{"doc":"/assets/docs/code-of-conduct.html","title":"Code of Conduct"},{"doc":"/assets/docs/volunteer-agreement.html","title":"Volunteer Leadership Agreement"}]','','[]','[]','starter'),
('open7',1,'Day 1 reading','Read each document to the end. The Next button becomes available once you have reached the end of all of them.','[{"doc":"/assets/docs/leadership-manual.html","title":"Volunteer Leadership Manual"},{"doc":"/assets/docs/code-of-conduct.html","title":"Code of Conduct"},{"doc":"/assets/docs/volunteer-agreement.html","title":"Volunteer Leadership Agreement"}]','','[]','[]','starter');
