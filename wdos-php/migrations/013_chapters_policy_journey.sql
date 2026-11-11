-- WDOS migration 013 · Founder's implementation amendments of 11 November 2026.
-- Chapter as the final operational level, automatic placement, versioned policy rules,
-- the automated seven-day member journey, chapter spaces, He-for-She appointments and wider audiences.

CREATE TABLE IF NOT EXISTS chapters (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL DEFAULT '',
  network VARCHAR(8) NOT NULL,
  country_code CHAR(2) NOT NULL,
  state_region VARCHAR(120) NOT NULL DEFAULT '',
  lga VARCHAR(120) NOT NULL DEFAULT '',
  community VARCHAR(120) NOT NULL DEFAULT '',
  zone VARCHAR(120) NOT NULL DEFAULT '',
  seq INT NOT NULL DEFAULT 1,
  name VARCHAR(190) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'developing',
  members INT NOT NULL DEFAULT 0,
  merged_into BIGINT NULL,
  activated_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_ch_place (network, country_code, state_region, lga, community, seq),
  KEY ix_ch_status (status)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapter_members (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chapter_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  network VARCHAR(8) NOT NULL,
  placed_by VARCHAR(24) NOT NULL DEFAULT 'auto',
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  end_reason VARCHAR(40) NOT NULL DEFAULT '',
  KEY ix_cm_chapter (chapter_id, ended_at),
  KEY ix_cm_user (user_id, ended_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN chapter_id BIGINT NULL;
ALTER TABLE users ADD KEY ix_users_chapter (chapter_id);
ALTER TABLE users ADD COLUMN lang VARCHAR(5) NOT NULL DEFAULT 'en';
ALTER TABLE users ADD COLUMN participant_class VARCHAR(16) NOT NULL DEFAULT 'member';
ALTER TABLE users ADD KEY ix_users_place (network, country_code, lga, chapter_id);
ALTER TABLE users ADD KEY ix_users_phone (phone);

ALTER TABLE seats ADD COLUMN chapter_id BIGINT NULL;
ALTER TABLE seats ADD COLUMN he_for_she TINYINT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS policy_rules (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  rule_key VARCHAR(60) NOT NULL,
  rule_value VARCHAR(255) NOT NULL,
  effective_from DATE NOT NULL,
  note VARCHAR(400) NOT NULL DEFAULT '',
  changed_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_policy (rule_key, effective_from, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_journeys (
  user_id BIGINT NOT NULL PRIMARY KEY,
  status VARCHAR(16) NOT NULL DEFAULT 'in_progress',
  started_at DATETIME NOT NULL,
  deadline DATE NOT NULL,
  extensions INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  reasons TEXT NULL,
  decided_at DATETIME NULL,
  reminded_on DATE NULL,
  KEY ix_mj_status (status, deadline)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_journey_steps (
  user_id BIGINT NOT NULL,
  day_no INT NOT NULL,
  answers TEXT NULL,
  done_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, day_no)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapter_posts (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chapter_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'post',
  body TEXT NOT NULL,
  reports INT NOT NULL DEFAULT 0,
  hidden_at DATETIME NULL,
  hidden_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_cp (chapter_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapter_post_reports (
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  reason VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (post_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE announcements ADD COLUMN audience_state VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE announcements ADD COLUMN audience_lga VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE announcements ADD COLUMN audience_community VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE announcements ADD COLUMN audience_chapter BIGINT NULL;
ALTER TABLE announcements ADD COLUMN audience_lang VARCHAR(5) NOT NULL DEFAULT '';

-- Titles as the African structure document gives them (WGMN chapter leaders are Coordinators).
UPDATE positions SET title_wgmn='Chapter Coordinator', title_wnnn='Chapter Lead' WHERE code='chapter_lead';
UPDATE positions SET title_wgmn='Assistant Chapter Coordinator', title_wnnn='Assistant Chapter Lead' WHERE code='assistant_chapter_lead';
-- Diaspora city level, administrative secretaries and the WNNN Campus Ambassador (an outreach role, not a seventh tier).
INSERT INTO positions (code,title_wgmn,title_wnnn,level,rank_no) VALUES
('city_coordinator','City Coordinator','City Lead','lga',6),
('assistant_city_coordinator','Assistant City Coordinator','Assistant City Lead','lga',7),
('country_secretary','Country Administrative Secretary','Country Administrative Secretary','country',2),
('district_secretary','LGA Administrative Secretary','LGA Administrative Secretary','lga',7),
('cluster_secretary','Community Cluster Administrative Secretary','Community Cluster Administrative Secretary','community_cluster',9),
('chapter_secretary','Chapter Administrative Secretary','Chapter Administrative Secretary','chapter',11),
('campus_ambassador','Campus Ambassador','Campus Ambassador','chapter',12);

-- The rules in force today. Each later change is a new row, so the history is kept.
INSERT INTO policy_rules (rule_key,rule_value,effective_from,note,created_at) VALUES
('chapter_min_active','30','2026-11-11','A chapter below this number is Developing.',NOW()),
('chapter_plan_next','40','2026-11-11','From this number WDOS raises a next-chapter planning alert.',NOW()),
('chapter_capacity','50','2026-11-11','At this number a chapter is At Capacity and automatic placement into it stops.',NOW()),
('chapter_auto_activate','eligible','2026-11-11','eligible: reaching the minimum marks the chapter eligible for activation. active: it becomes active by itself. Awaiting a management decision.',NOW()),
('chapter_auto_create','on','2026-11-11','on: WDOS opens the next chapter by itself when none has room. off: the member waits with a pending placement.',NOW()),
('journey_days','7','2026-11-11','Length of the member journey from registration to the automatic outcome.',NOW()),
('journey_unlock','daily','2026-11-11','daily: one new step opens each day. open: every step is open from the first day.',NOW()),
('journey_extension_days','7','2026-11-11','Extra days given automatically when the journey is not finished by its last day.',NOW()),
('journey_required_referrals','0','2026-11-11','Registered referrals needed for activation. 0 means the three referrals are encouraged, not compulsory.',NOW()),
('wnnn_country_title','Country Lead','2026-11-11','African structure document says Country Lead, diaspora document says Country Representative. Awaiting a management decision.',NOW())
