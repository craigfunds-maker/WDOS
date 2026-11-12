-- WDOS migration 015 · Chapter and Membership Management Specification v1.0 (11 November 2026).
-- Community Cluster master data, readable chapter codes, a chapter's own target capacity, suspended and closed chapters,
-- capacity exceptions, assignment reasons and approvals, and operational zones.
CREATE TABLE IF NOT EXISTS clusters (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  country_code CHAR(2) NOT NULL,
  state_region VARCHAR(120) NOT NULL DEFAULT '',
  lga VARCHAR(120) NOT NULL DEFAULT '',
  name VARCHAR(120) NOT NULL,
  catchment VARCHAR(600) NOT NULL DEFAULT '',
  official_ward VARCHAR(120) NOT NULL DEFAULT '',
  active TINYINT NOT NULL DEFAULT 1,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_clusters (country_code, lga, name)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chapter_zones (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  network VARCHAR(8) NOT NULL,
  country_code CHAR(2) NOT NULL,
  name VARCHAR(120) NOT NULL,
  supervisor_id BIGINT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  created_by BIGINT NULL,
  KEY ix_zone_sup (supervisor_id, ends_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE chapters ADD COLUMN target_capacity INT NULL;
ALTER TABLE chapters ADD COLUMN capacity_exception TINYINT NOT NULL DEFAULT 0;
ALTER TABLE chapters ADD COLUMN zone_id BIGINT NULL;
ALTER TABLE chapters ADD COLUMN status_note VARCHAR(300) NOT NULL DEFAULT '';
ALTER TABLE chapters ADD COLUMN catchment VARCHAR(600) NOT NULL DEFAULT '';
ALTER TABLE chapter_members ADD COLUMN reason VARCHAR(200) NOT NULL DEFAULT '';
ALTER TABLE chapter_members ADD COLUMN approval_ref VARCHAR(120) NOT NULL DEFAULT '';
INSERT INTO clusters (country_code,state_region,lga,name,created_at) SELECT DISTINCT country_code, state_region, lga, community, NOW() FROM chapters WHERE community<>''
