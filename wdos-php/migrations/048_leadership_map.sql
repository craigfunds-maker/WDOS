-- v109.46: the leadership map (Azeez, 22 Nov 2026, with the WODDI Leadership Structures and Communication Flow documents).
-- A seat now records the whole of its place: the LGA or district and the ward or community cluster it serves, not only a label.
-- Seat links and activations carry the same place, so a woman referred for a vacant ward seat is appointed to that exact ward.
-- Rollback: the new columns are additive and nothing older reads them; drop them and the alerts table to undo.
ALTER TABLE seats ADD COLUMN lga VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE seats ADD COLUMN community VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE referral_links ADD COLUMN lga VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE referral_links ADD COLUMN community VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE referral_links ADD COLUMN chapter_id BIGINT NULL;
ALTER TABLE activations ADD COLUMN lga VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE activations ADD COLUMN community VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE activations ADD COLUMN chapter_id BIGINT NULL;
CREATE TABLE IF NOT EXISTS seat_vacancy_alerts (
  k VARCHAR(190) NOT NULL PRIMARY KEY,
  sent_at DATETIME NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Fill the place of the seats already held: an LGA seat's label is its LGA; a cluster seat's label is its cluster and its LGA is the holder's;
-- a chapter seat takes both from its chapter.
UPDATE seats s JOIN positions p ON p.code=s.position_code SET s.lga=s.area_label WHERE p.level='lga' AND s.lga='';
UPDATE seats s JOIN positions p ON p.code=s.position_code JOIN users u ON u.id=s.user_id SET s.community=s.area_label, s.lga=u.lga WHERE p.level='community_cluster' AND s.community='';
UPDATE seats s JOIN chapters c ON c.id=s.chapter_id SET s.lga=c.lga, s.community=c.community WHERE s.chapter_id IS NOT NULL AND s.lga='';
CREATE INDEX ix_links_seat ON referral_links (owner_id, position_code, org_unit_id);
CREATE INDEX ix_seats_place ON seats (position_code, ends_at)
;
UPDATE activations a JOIN positions p ON p.code=a.position_code SET a.lga=a.area_label WHERE p.level='lga' AND a.lga='';
UPDATE activations a JOIN positions p ON p.code=a.position_code JOIN users u ON u.id=a.user_id SET a.community=a.area_label, a.lga=u.lga WHERE p.level='community_cluster' AND a.community=''
