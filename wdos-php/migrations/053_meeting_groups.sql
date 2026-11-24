-- v109.55: a meeting for several groups at once, and chosen people on top (Azeez, 24 Nov 2026: "in Who is invited?
-- i should be able to add/tick multiple people"). A meeting with audience 'mix' is for everyone in any of its groups here,
-- plus anyone in meeting_invitees. Groups stay live: someone who joins a group later is invited too.
-- Data impact: one new table, empty. Existing meetings keep their single audience and are not changed.
-- Rollback: DROP TABLE meeting_groups; (mix meetings would then reach only their chosen people)
CREATE TABLE IF NOT EXISTS meeting_groups (
  meeting_id BIGINT NOT NULL,
  audience VARCHAR(12) NOT NULL,
  network VARCHAR(8) NOT NULL DEFAULT 'ALL',
  PRIMARY KEY (meeting_id, audience, network)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
