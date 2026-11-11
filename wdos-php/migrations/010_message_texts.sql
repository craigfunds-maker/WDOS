-- WDOS migration 010 · The approved write-up for each inspiration message, per language, as held in the current WDOS.
CREATE TABLE IF NOT EXISTS hq_message_texts (
  message_id BIGINT NOT NULL,
  lang VARCHAR(5) NOT NULL,
  headline VARCHAR(190) NOT NULL DEFAULT '',
  key_thought VARCHAR(600) NOT NULL DEFAULT '',
  body TEXT NULL,
  lens_wgmn TEXT NULL,
  lens_wnnn TEXT NULL,
  reflection VARCHAR(800) NOT NULL DEFAULT '',
  closing VARCHAR(600) NOT NULL DEFAULT '',
  PRIMARY KEY (message_id, lang)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
