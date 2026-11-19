-- v109.31: what an envoy has been told about the volunteer leaders she brought in, so each alert goes once
-- (an overdue task once per task; not signing in once a week; stopping serving once per seat). New table only.
-- Rollback: DROP TABLE envoy_alerts;
CREATE TABLE IF NOT EXISTS envoy_alerts (
  envoy_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  kind VARCHAR(16) NOT NULL,
  ref VARCHAR(32) NOT NULL,
  sent_at DATETIME NOT NULL,
  PRIMARY KEY (envoy_id, user_id, kind, ref)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
