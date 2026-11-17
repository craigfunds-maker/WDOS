-- v109.12: the AI check of handed-in work. When a volunteer leader hands in a task HQ gave her, or submits a report,
-- the AI service (Hugging Face first) reads what was asked and what was handed in. If something clear is missing it
-- flags the work, lists the corrections, sends it back to her and reminds her until she hands it in again.
-- HQ can always overrule the check and mark the work as done.
CREATE TABLE IF NOT EXISTS work_checks (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(8) NOT NULL,
  item_id BIGINT NOT NULL,
  owner_id BIGINT NOT NULL,
  verdict VARCHAR(20) NOT NULL,
  summary VARCHAR(600) NOT NULL DEFAULT '',
  points TEXT NULL,
  lane VARCHAR(20) NOT NULL DEFAULT '',
  model VARCHAR(120) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'open',
  reminders INT NOT NULL DEFAULT 0,
  reminded_at DATETIME NULL,
  decided_by BIGINT NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY ix_wc_item (kind, item_id, id),
  KEY ix_wc_open (status, verdict, reminded_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
