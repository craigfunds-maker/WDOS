-- WDOS migration 006 · Tasks and meetings.
-- One schema change per statement (TiDB rule).

CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  details TEXT NULL,
  priority VARCHAR(8) NOT NULL DEFAULT 'normal',
  due_on DATE NULL,
  requires_approval TINYINT NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'open',
  assigned_by BIGINT NOT NULL,
  assignee_id BIGINT NOT NULL,
  org_unit_id BIGINT NULL,
  network VARCHAR(8) NULL,
  source VARCHAR(40) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  KEY ix_tasks_assignee (assignee_id, status),
  KEY ix_tasks_by (assigned_by, status),
  KEY ix_tasks_unit (org_unit_id, status),
  KEY ix_tasks_source (source)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_items (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT NOT NULL,
  label VARCHAR(190) NOT NULL,
  done TINYINT NOT NULL DEFAULT 0,
  KEY ix_task_items (task_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comments (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  body VARCHAR(1500) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_task_comments (task_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meetings (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  mode VARCHAR(12) NOT NULL DEFAULT 'virtual',
  location VARCHAR(400) NOT NULL DEFAULT '',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  agenda TEXT NULL,
  host_id BIGINT NOT NULL,
  audience VARCHAR(12) NOT NULL DEFAULT 'leaders',
  network VARCHAR(8) NOT NULL DEFAULT 'ALL',
  org_unit_id BIGINT NULL,
  unit_path VARCHAR(190) NOT NULL DEFAULT '',
  scope_level VARCHAR(24) NOT NULL DEFAULT '',
  scope_area VARCHAR(120) NOT NULL DEFAULT '',
  scope_label VARCHAR(190) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'scheduled',
  minutes TEXT NULL,
  created_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  KEY ix_meetings_time (starts_at),
  KEY ix_meetings_host (host_id, starts_at)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_attendance (
  meeting_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  status VARCHAR(8) NOT NULL,
  marked_by BIGINT NOT NULL,
  marked_at DATETIME NOT NULL,
  PRIMARY KEY (meeting_id, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
