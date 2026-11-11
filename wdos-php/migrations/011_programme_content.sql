-- WDOS migration 011 · Room for the programme content exactly as the current WDOS holds it.
ALTER TABLE readiness_items ADD COLUMN network VARCHAR(8) NOT NULL DEFAULT '';
ALTER TABLE readiness_items ADD COLUMN reviewer_note VARCHAR(1000) NOT NULL DEFAULT '';
ALTER TABLE readiness_items ADD COLUMN critical TINYINT NOT NULL DEFAULT 0;
ALTER TABLE activation_days ADD COLUMN needs_profile TINYINT NOT NULL DEFAULT 0;
ALTER TABLE activation_days ADD COLUMN purpose VARCHAR(1000) NOT NULL DEFAULT '';
