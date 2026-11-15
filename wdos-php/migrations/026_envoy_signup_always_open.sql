-- v109.1: envoy sign-up has no approval and cannot be closed (management direction, 15 Nov 2026). The rule from 025 is retired.
DELETE FROM policy_rules WHERE rule_key = 'envoy_signup'
;
ALTER TABLE meeting_requests ADD COLUMN call_domain VARCHAR(120) NULL;
INSERT INTO policy_rules (rule_key, rule_value, effective_from, note, created_at) SELECT 'video_call_server', 'meet.vpsfree.cz', '2026-11-15', 'Community Jitsi server for leader video meetings. Tested inside WDOS on 15 Nov 2026: joined in about 8 seconds and ran past 7 minutes with no cut.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM policy_rules WHERE rule_key = 'video_call_server')
;
