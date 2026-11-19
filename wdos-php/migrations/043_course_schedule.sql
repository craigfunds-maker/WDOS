-- v109.33: Personal Development Courses open one per month for volunteer leaders (Azeez, 19 Nov 2026).
-- Course 1 opens the day she starts; course N opens N-1 months later. A course she had already started before this
-- release stays open. New table only; the backfill keeps today's learners on their real start date.
-- Rollback: DROP TABLE course_starts;  (the courses then all open again, as before)
CREATE TABLE IF NOT EXISTS course_starts (
  user_id BIGINT NOT NULL PRIMARY KEY,
  started_at DATETIME NOT NULL,
  told_upto TINYINT NOT NULL DEFAULT 1
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO course_starts (user_id, started_at, told_upto)
  SELECT user_id, MIN(completed_at), 1 FROM course_progress GROUP BY user_id;
