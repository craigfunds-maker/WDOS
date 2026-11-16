-- v109.2: minutes of a leader video meeting carry the WDOS people who attended (ticked from those who opened the room),
-- plus a minutes document and a recording, linked through doc_links (owner_type 'meeting'). Additive only.
ALTER TABLE meeting_requests ADD COLUMN attended_ids TEXT NULL;
