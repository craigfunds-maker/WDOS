-- WDOS migration 014 · The rest of the Founder's amendments of 11 November 2026:
-- chapter scope and delegation on tasks, official ward kept apart from the WODDI community, announcements in every
-- language with scheduling and acknowledgement, community spaces at every level with topic circles and mentorship,
-- and room for capacity by country.
ALTER TABLE tasks ADD COLUMN parent_id BIGINT NULL;
ALTER TABLE tasks ADD COLUMN chapter_id BIGINT NULL;
ALTER TABLE tasks ADD KEY ix_tasks_chapter (chapter_id, status);
ALTER TABLE users ADD COLUMN ward VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE announcements ADD COLUMN scheduled_at DATETIME NULL;
ALTER TABLE announcements ADD COLUMN sent_at DATETIME NULL;
UPDATE announcements SET sent_at=created_at WHERE sent_at IS NULL;

CREATE TABLE IF NOT EXISTS announcement_texts (
  announcement_id BIGINT NOT NULL,
  lang VARCHAR(5) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (announcement_id, lang)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_acks (
  announcement_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  acked_at DATETIME NOT NULL,
  PRIMARY KEY (announcement_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spaces (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(12) NOT NULL DEFAULT 'circle',
  name VARCHAR(160) NOT NULL,
  about VARCHAR(600) NOT NULL DEFAULT '',
  network VARCHAR(8) NOT NULL DEFAULT 'ALL',
  created_by BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  closed_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS space_members (
  space_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  joined_at DATETIME NOT NULL,
  PRIMARY KEY (space_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS space_posts (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  space_key VARCHAR(190) NOT NULL,
  user_id BIGINT NOT NULL,
  body TEXT NOT NULL,
  reports INT NOT NULL DEFAULT 0,
  held TINYINT NOT NULL DEFAULT 0,
  hidden_at DATETIME NULL,
  hidden_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_sp (space_key, id),
  KEY ix_sp_hidden (hidden_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS space_post_reports (
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (post_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE chapter_posts ADD COLUMN held TINYINT NOT NULL DEFAULT 0;
ALTER TABLE policy_rules MODIFY COLUMN rule_value VARCHAR(600) NOT NULL
;
-- Diaspora countries, so women outside Africa can register under Country, then State or Province (optional), then City, then Chapter.
INSERT INTO org_units (parent_id,level,name,country_code,path) VALUES
(1,'country','United States','US',''),(1,'country','United Kingdom','GB',''),(1,'country','Canada','CA',''),(1,'country','Ireland','IE',''),(1,'country','Germany','DE',''),
(1,'country','France','FR',''),(1,'country','Italy','IT',''),(1,'country','Spain','ES',''),(1,'country','Portugal','PT',''),(1,'country','Netherlands','NL',''),
(1,'country','Belgium','BE',''),(1,'country','Sweden','SE',''),(1,'country','Norway','NO',''),(1,'country','Switzerland','CH',''),(1,'country','Austria','AT',''),
(1,'country','Australia','AU',''),(1,'country','New Zealand','NZ',''),(1,'country','United Arab Emirates','AE',''),(1,'country','Saudi Arabia','SA',''),(1,'country','Qatar','QA',''),
(1,'country','Turkey','TR',''),(1,'country','India','IN',''),(1,'country','China','CN',''),(1,'country','Malaysia','MY',''),(1,'country','Brazil','BR',''),
(1,'country','Jamaica','JM',''),(1,'country','Trinidad and Tobago','TT','');
UPDATE org_units SET path=CONCAT('/1/',id,'/') WHERE level='country' AND path=''
