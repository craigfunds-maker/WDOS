-- v109.35: keep the reason an email failed, so HQ can see it and fix it (Azeez, 20 Nov 2026).
-- Rollback: ALTER TABLE mail_outbox DROP COLUMN last_error;
ALTER TABLE mail_outbox ADD COLUMN last_error VARCHAR(300) NOT NULL DEFAULT '';
