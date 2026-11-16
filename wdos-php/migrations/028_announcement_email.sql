-- v109.3: an announcement can also go by email (in each member's language), and its reminder follows the same choice
ALTER TABLE announcements ADD COLUMN by_email TINYINT NOT NULL DEFAULT 0;
