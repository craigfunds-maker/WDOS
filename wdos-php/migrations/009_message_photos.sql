-- WDOS migration 009 · Photographs for the inspiration, birthday and announcement messages.
-- The Founder asked for royalty-free photographs in place of generated pictures. Each photograph is
-- fetched once from Pexels by the server and kept here, so it survives updates and the move to shared hosting.

CREATE TABLE IF NOT EXISTS message_photos (
  image_key VARCHAR(120) NOT NULL PRIMARY KEY,
  mime VARCHAR(40) NOT NULL,
  bytes MEDIUMBLOB NOT NULL,
  pexels_id BIGINT NOT NULL DEFAULT 0,
  photographer VARCHAR(160) NOT NULL DEFAULT '',
  page_url VARCHAR(255) NOT NULL DEFAULT '',
  alt VARCHAR(300) NOT NULL DEFAULT '',
  focus VARCHAR(24) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
