-- WDOS migration 020 · What a member said about volunteering when she registered, or later from her home screen.
-- '' = no choice recorded; 'volunteer' = wants to volunteer in her chapter; otherwise the code of the leadership position she is interested in.
-- The date she became a volunteer stays in users.volunteer_since (migration 016).
-- Data impact: none on existing rows, every member starts with ''. No index: read only by the HQ volunteer report.
-- Rollback: adds one column. Dropping it returns the schema to migration 019; older code never reads it.
ALTER TABLE users ADD COLUMN volunteer_interest VARCHAR(40) NOT NULL DEFAULT ''
