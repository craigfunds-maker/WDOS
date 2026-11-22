-- v109.46: the WODDI 3-Day and 7-Day Leadership Activation Framework (Azeez, 22 Nov 2026), run before appointment as screening.
-- Days 1 to 3: consent and profile, WDOS access, orientation, contact with the reporting officer, immediate priorities (100 points, pass 80
-- and three mandatory gates). Days 4 to 7 (7-Day route only): coverage mapping, referrals, engagement, action plan, first report and
-- compliance check (100 points, pass 80). Each task keeps its evidence, submission time, reviewer, score, remarks and resubmissions.
-- Activations already under way keep the earlier rules (scheme v1); every activation opened from now uses the framework (scheme fw).
-- Rollback: the new columns and table are additive; set scheme back to 'v1' to return a candidate to the earlier journey.
ALTER TABLE activations ADD COLUMN scheme VARCHAR(8) NOT NULL DEFAULT 'v1';
ALTER TABLE activations ADD COLUMN compliance_breach VARCHAR(600) NOT NULL DEFAULT '';
ALTER TABLE activations ADD COLUMN support_note VARCHAR(1000) NOT NULL DEFAULT '';
CREATE TABLE IF NOT EXISTS activation_tasks (
  activation_id BIGINT NOT NULL,
  task_key VARCHAR(24) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
  evidence MEDIUMTEXT NULL,
  files VARCHAR(400) NOT NULL DEFAULT '',
  auto_score INT NULL,
  score INT NULL,
  submitted_at DATETIME NULL,
  reviewer_id BIGINT NULL,
  reviewed_at DATETIME NULL,
  remarks VARCHAR(1500) NOT NULL DEFAULT '',
  resubmissions INT NOT NULL DEFAULT 0,
  officer_id BIGINT NULL,
  confirmed_by BIGINT NULL,
  confirmed_at DATETIME NULL,
  PRIMARY KEY (activation_id, task_key),
  KEY ix_at_officer (officer_id, confirmed_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
