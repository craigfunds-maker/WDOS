-- WDOS migration 021 · Leader terms, newsletters and talk shows (three-day finalisation checklist, Day 1 lifecycle and communications).
-- seats.term_ends_on: the last day of an appointment, when HQ sets one. NULL = no fixed term, exactly as before.
-- announcements.kind: 'announcement' or 'newsletter'. meetings.kind: 'meeting' or 'talk_show'.
-- Data impact: none on existing rows. Every seat keeps no term; every announcement and meeting keeps its present kind.
-- Rollback: three added columns. Dropping them returns the schema to migration 020; older code never reads them.
ALTER TABLE seats ADD COLUMN term_ends_on DATE NULL;
ALTER TABLE announcements ADD COLUMN kind VARCHAR(12) NOT NULL DEFAULT 'announcement';
ALTER TABLE meetings ADD COLUMN kind VARCHAR(12) NOT NULL DEFAULT 'meeting'
