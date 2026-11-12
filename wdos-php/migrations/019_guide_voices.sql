-- WDOS migration 019 · The spoken guide, recorded by a person. One recording per language and step, kept in the database so it survives a redeploy.
-- Rollback: adds only. Dropping the table returns the schema to migration 018.
CREATE TABLE IF NOT EXISTS guide_audio (
  lang VARCHAR(5) NOT NULL,
  step INT NOT NULL,
  mime VARCHAR(40) NOT NULL,
  data MEDIUMBLOB NOT NULL,
  bytes INT NOT NULL,
  recorded_by BIGINT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (lang, step)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
