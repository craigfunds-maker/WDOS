-- WDOS migration 012 · Messages with WODDI HQ, the e-mail queue, and the document library.
CREATE TABLE IF NOT EXISTS threads (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  subject VARCHAR(190) NOT NULL,
  category VARCHAR(20) NOT NULL DEFAULT 'general',
  status VARCHAR(12) NOT NULL DEFAULT 'open',
  started_by_hq TINYINT NOT NULL DEFAULT 0,
  hq_unread INT NOT NULL DEFAULT 0,
  user_unread INT NOT NULL DEFAULT 0,
  last_snippet VARCHAR(200) NOT NULL DEFAULT '',
  last_from_hq TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  last_at DATETIME NOT NULL,
  KEY ix_threads_user (user_id, last_at),
  KEY ix_threads_hq (status, last_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thread_messages (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT NOT NULL,
  sender_id BIGINT NOT NULL,
  from_hq TINYINT NOT NULL DEFAULT 0,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_thread_messages (thread_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_outbox (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  to_name VARCHAR(160) NOT NULL DEFAULT '',
  subject VARCHAR(190) NOT NULL,
  html TEXT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'queued',
  attempts INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY ix_mail_outbox (status, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_items (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(600) NOT NULL DEFAULT '',
  category VARCHAR(60) NOT NULL DEFAULT 'General',
  audience VARCHAR(10) NOT NULL DEFAULT 'leaders',
  kind VARCHAR(8) NOT NULL DEFAULT 'link',
  url VARCHAR(600) NOT NULL DEFAULT '',
  file_name VARCHAR(190) NOT NULL DEFAULT '',
  mime VARCHAR(80) NOT NULL DEFAULT '',
  bytes MEDIUMBLOB NULL,
  origin VARCHAR(12) NOT NULL DEFAULT 'hq',
  archived TINYINT NOT NULL DEFAULT 0,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_library (archived, category)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO library_items (title,description,category,audience,kind,url,origin,created_at)
SELECT 'Volunteer Leadership Manual','Understanding WODDI, our networks and our leadership.','Leadership','leaders','doc','/assets/docs/leadership-manual.html','current',NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM library_items WHERE url='/assets/docs/leadership-manual.html');
INSERT INTO library_items (title,description,category,audience,kind,url,origin,created_at)
SELECT 'Code of Conduct','The standards every WODDI member and leader keeps.','Standards','all','doc','/assets/docs/code-of-conduct.html','current',NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM library_items WHERE url='/assets/docs/code-of-conduct.html');
INSERT INTO library_items (title,description,category,audience,kind,url,origin,created_at)
SELECT 'Volunteer Leadership Agreement','The agreement a leader accepts on appointment.','Leadership','leaders','doc','/assets/docs/volunteer-agreement.html','current',NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM library_items WHERE url='/assets/docs/volunteer-agreement.html');
