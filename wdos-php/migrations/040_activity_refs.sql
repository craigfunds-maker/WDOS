-- v109.25: every leader action in the activity record points at the thing it was about (a task, meeting, report, call,
-- message or person), so HQ can open it. Two new nullable columns and an index; nothing existing changes.
-- Rollback: ALTER TABLE activity_log DROP INDEX ix_activity_actor, DROP COLUMN ref_type, DROP COLUMN ref_id;
ALTER TABLE activity_log ADD COLUMN ref_type VARCHAR(20) NULL, ADD COLUMN ref_id BIGINT NULL, ADD KEY ix_activity_actor (actor_id, created_at);
