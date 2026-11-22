-- v109.47: leadership rooms (Azeez, 22 Nov 2026: "group chat for each region ... community forum for leadership region as well must work,
-- they should be able to share link, document, photo, video"). One room for every region of the structure (country, state or region,
-- LGA or district, ward or community cluster) in each network. The volunteer leaders serving in that region are its members; the State
-- Envoy joins her country and state rooms in both networks; WODDI HQ can open every room. Each room has a group chat and a forum.
-- Attachments are WDOS files (doc_files), linked to the message. Rollback: drop the two tables; nothing else reads them.
CREATE TABLE IF NOT EXISTS room_msgs (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room VARCHAR(190) NOT NULL,
  room_net VARCHAR(8) NOT NULL DEFAULT '',
  room_place VARCHAR(600) NOT NULL DEFAULT '',
  kind VARCHAR(8) NOT NULL DEFAULT 'chat',
  parent_id BIGINT NULL,
  user_id BIGINT NOT NULL,
  title VARCHAR(190) NOT NULL DEFAULT '',
  body TEXT NOT NULL,
  files VARCHAR(400) NOT NULL DEFAULT '',
  replies INT NOT NULL DEFAULT 0,
  last_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  hidden_at DATETIME NULL,
  hidden_by BIGINT NULL,
  KEY ix_room_kind (room, kind, id),
  KEY ix_room_parent (parent_id, id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS room_reads (
  room VARCHAR(190) NOT NULL,
  user_id BIGINT NOT NULL,
  last_id BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (room, user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;
-- The days each person used WDOS, one row a day, so "most active" on the leaderboard counts real days, not a guess.
CREATE TABLE IF NOT EXISTS user_days (
  user_id BIGINT NOT NULL,
  day DATE NOT NULL,
  PRIMARY KEY (user_id, day),
  KEY ix_ud_day (day)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
