-- v109.44: each member's birthday card (her photo and name on a WODDI HQ design) and her spoken greeting in a Kokoro voice.
-- One row per member per birthday. The token makes the card and the voice reachable from her email without signing in.
CREATE TABLE IF NOT EXISTS birthday_media (
  user_id INT NOT NULL,
  year SMALLINT NOT NULL,
  token CHAR(40) NOT NULL,
  lang VARCHAR(8) NOT NULL DEFAULT 'en',
  card MEDIUMBLOB NULL,
  card_at DATETIME NULL,
  card_live MEDIUMBLOB NULL,      -- the same card without balloons or headline: the dashboard animates those over it
  voice MEDIUMBLOB NULL,
  voice_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, year),
  UNIQUE KEY birthday_media_token (token)
);
