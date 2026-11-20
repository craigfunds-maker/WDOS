-- v109.37: sign-in drive, envoy launch pack, missing-detail asks (Azeez, 20 Nov 2026).
-- Rollback: DROP TABLE signin_packs; DROP TABLE detail_asks;
CREATE TABLE IF NOT EXISTS signin_packs (
  user_id BIGINT NOT NULL PRIMARY KEY,
  sent_at DATETIME NULL,
  sent_by BIGINT NULL,
  packs INT NOT NULL DEFAULT 0,
  nudges INT NOT NULL DEFAULT 0,
  nudged_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS detail_asks (
  user_id BIGINT NOT NULL PRIMARY KEY,
  asks INT NOT NULL DEFAULT 0,
  last_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
