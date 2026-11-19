-- v109.23: members with no date of birth are asked for it by email (and in the app and by phone), at most twice, 14 days apart.
-- Rollback: DROP TABLE dob_asks;
CREATE TABLE IF NOT EXISTS dob_asks (
  user_id BIGINT NOT NULL PRIMARY KEY,
  asks INT NOT NULL DEFAULT 0,
  last_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
