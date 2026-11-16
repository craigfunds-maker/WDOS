-- v109.3: everything from the old (Supabase) WDOS is kept inside WDOS, row for row, so nothing is lost while each
-- kind of record is mapped into its new home. HQ can search it; files from the old storage are kept with it.
CREATE TABLE IF NOT EXISTS legacy_records (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_table VARCHAR(80) NOT NULL,
  source_id VARCHAR(80) NOT NULL,
  person_email VARCHAR(190) NULL,
  created_at DATETIME NULL,
  payload LONGTEXT NOT NULL,
  imported_at DATETIME NOT NULL,
  UNIQUE KEY ux_legacy (source_table, source_id),
  KEY ix_legacy_email (person_email),
  KEY ix_legacy_table (source_table, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS legacy_files (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(80) NOT NULL,
  path VARCHAR(400) NOT NULL,
  file_id BIGINT NULL,
  size BIGINT NOT NULL DEFAULT 0,
  note VARCHAR(190) NOT NULL DEFAULT '',
  imported_at DATETIME NOT NULL,
  UNIQUE KEY ux_legacy_file (bucket, path(250))
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS legacy_runs (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_table VARCHAR(80) NOT NULL,
  remote_rows INT NOT NULL DEFAULT 0,
  kept_rows INT NOT NULL DEFAULT 0,
  ran_at DATETIME NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT ''
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
