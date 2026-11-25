-- v109.59: translating the words inside an uploaded flier picture (Azeez, 25 Nov 2026: "its the flier i want to be translated
-- to different languages"). The host's browser reads the words on the picture (OCR) with their places; they are kept here, the
-- AI translates them, and the browser paints each language's words over the picture. Data impact: two new empty columns.
-- Rollback: ALTER TABLE meetings DROP COLUMN flier_words; ALTER TABLE meeting_flier_texts DROP COLUMN words_json;
ALTER TABLE meetings ADD COLUMN flier_words MEDIUMTEXT NULL;
ALTER TABLE meeting_flier_texts ADD COLUMN words_json MEDIUMTEXT NULL
