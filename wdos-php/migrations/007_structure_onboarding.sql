-- WDOS migration 007 · Leadership structure, appointment acceptance and onboarding.
-- One schema change per statement (TiDB rule).

ALTER TABLE seats ADD COLUMN acceptance VARCHAR(12) NOT NULL DEFAULT '';
ALTER TABLE seats ADD COLUMN accepted_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS onboarding_steps (
  code VARCHAR(40) NOT NULL PRIMARY KEY,
  seq INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  detail VARCHAR(600) NOT NULL DEFAULT '',
  mode VARCHAR(8) NOT NULL DEFAULT 'read',
  doc_url VARCHAR(255) NOT NULL DEFAULT '',
  href VARCHAR(120) NOT NULL DEFAULT '',
  min_seconds INT NOT NULL DEFAULT 20,
  required TINYINT NOT NULL DEFAULT 1,
  is_active TINYINT NOT NULL DEFAULT 1,
  origin VARCHAR(12) NOT NULL DEFAULT 'starter'
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_progress (
  user_id BIGINT NOT NULL,
  step_code VARCHAR(40) NOT NULL,
  done_at DATETIME NOT NULL,
  seconds INT NOT NULL DEFAULT 0,
  note VARCHAR(600) NOT NULL DEFAULT '',
  PRIMARY KEY (user_id, step_code)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starter steps: only the documents and the declaration that ship with the current WDOS app files.
-- The full approved step list is loaded from the current WDOS on the setup page and replaces these.
INSERT IGNORE INTO onboarding_steps (code,seq,title,detail,mode,doc_url,href,min_seconds,required,is_active,origin) VALUES
('code_of_conduct',1,'Code of Conduct','','read','/assets/docs/code-of-conduct.html','',20,1,1,'starter'),
('leadership_manual',2,'Volunteer Leadership Manual','','read','/assets/docs/leadership-manual.html','',20,1,1,'starter'),
('volunteer_agreement',3,'Volunteer Leadership Agreement','','read','/assets/docs/volunteer-agreement.html','',20,1,1,'starter'),
('conflict_of_interest',4,'Conflict of interest declaration','','declare','','',5,1,1,'starter');
