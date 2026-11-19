-- v109.27: group calls with many people, through Cloudflare's relay (Realtime SFU). A call of up to 8 people still goes
-- phone to phone ('mesh'); a bigger one goes through the relay ('sfu'), where the caller and up to 6 others speak at a time
-- and everyone else listens and can raise a hand. New columns only, all with defaults; nothing existing changes.
-- Rollback: ALTER TABLE voice_groups DROP COLUMN mode; ALTER TABLE voice_group_people DROP COLUMN speaking, DROP COLUMN hand_at, DROP COLUMN sfu_session, DROP COLUMN sfu_published;
ALTER TABLE voice_groups ADD COLUMN mode VARCHAR(6) NOT NULL DEFAULT 'mesh';
ALTER TABLE voice_group_people ADD COLUMN speaking TINYINT NOT NULL DEFAULT 0, ADD COLUMN hand_at DATETIME NULL, ADD COLUMN sfu_session VARCHAR(64) NULL, ADD COLUMN sfu_published TINYINT NOT NULL DEFAULT 0;
