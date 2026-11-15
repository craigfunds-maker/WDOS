-- v109: WODDI Envoys can sign themselves up (with email and password, or with Google). The details they give are kept here.
ALTER TABLE envoys ADD COLUMN state_region VARCHAR(120) NULL;
ALTER TABLE envoys ADD COLUMN city VARCHAR(120) NULL;
ALTER TABLE envoys ADD COLUMN organisation VARCHAR(160) NULL;
ALTER TABLE envoys ADD COLUMN reach_plan VARCHAR(1000) NULL;
ALTER TABLE envoys ADD COLUMN heard_from VARCHAR(160) NULL;
ALTER TABLE envoys ADD COLUMN source VARCHAR(12) NOT NULL DEFAULT 'hq';
INSERT INTO policy_rules (rule_key, rule_value, effective_from, note, created_at) SELECT 'envoy_signup', 'open', '2026-11-15', 'open: a new envoy gets her link at once. review: her link waits for HQ approval. closed: the sign-up page is shut.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM policy_rules WHERE rule_key = 'envoy_signup')
;
