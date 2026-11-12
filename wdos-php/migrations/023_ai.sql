-- WDOS migration 023 · AI service: every call is logged, results are cached, drafts are kept for a person to read.
-- Plain single statements only (TiDB). No data is changed in existing tables.
CREATE TABLE IF NOT EXISTS ai_calls (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  feature VARCHAR(40) NOT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'chat',
  lane VARCHAR(20) NOT NULL DEFAULT '',
  model VARCHAR(120) NOT NULL DEFAULT '',
  ms INT NOT NULL DEFAULT 0,
  ok TINYINT NOT NULL DEFAULT 0,
  cached TINYINT NOT NULL DEFAULT 0,
  error VARCHAR(190) NOT NULL DEFAULT '',
  in_chars INT NOT NULL DEFAULT 0,
  out_chars INT NOT NULL DEFAULT 0,
  user_id BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_ai_calls_when (created_at),
  KEY ix_ai_calls_feature (feature, created_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ai_cache (
  cache_key CHAR(64) NOT NULL PRIMARY KEY,
  feature VARCHAR(40) NOT NULL,
  lane VARCHAR(20) NOT NULL DEFAULT '',
  model VARCHAR(120) NOT NULL DEFAULT '',
  result MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY ix_ai_cache_exp (expires_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ai_outputs (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  feature VARCHAR(40) NOT NULL,
  scope_key VARCHAR(120) NOT NULL DEFAULT '',
  period VARCHAR(20) NOT NULL DEFAULT '',
  lang VARCHAR(8) NOT NULL DEFAULT 'en',
  lane VARCHAR(20) NOT NULL DEFAULT '',
  model VARCHAR(120) NOT NULL DEFAULT '',
  body MEDIUMTEXT NOT NULL,
  facts MEDIUMTEXT NULL,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_ai_outputs (feature, scope_key, period, lang)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ai_vectors (
  item_type VARCHAR(20) NOT NULL,
  item_id BIGINT NOT NULL,
  model VARCHAR(120) NOT NULL DEFAULT '',
  text_hash CHAR(64) NOT NULL,
  vec MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (item_type, item_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO policy_rules (rule_key, rule_value, effective_from, note, created_at) SELECT 'ai_features', 'on', '2026-11-12', 'on: AI drafts and summaries are offered to HQ. off: every AI feature shows its plain version only.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM policy_rules WHERE rule_key = 'ai_features');
INSERT INTO policy_rules (rule_key, rule_value, effective_from, note, created_at) SELECT 'ai_free_lane_text', 'redacted', '2026-11-12', 'redacted: text written by leaders goes to a free AI lane only after names, emails, phone numbers and links are removed. off: such text never goes to a free lane.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM policy_rules WHERE rule_key = 'ai_free_lane_text')
