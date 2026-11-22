-- v109.50: Part F (WODDI acknowledgement) is completed automatically when an envoy accepts (Azeez, 22 Nov 2026).
-- Envoys who accepted before this and are still waiting are acknowledged now, marked plainly as automatic.
-- Data impact: fills ack_name, ack_title, ack_ref, ack_at only where ack_at IS NULL. Officer-signed acknowledgements are untouched.
-- Rollback: UPDATE envoy_acceptances SET ack_name='', ack_title='', ack_ref='', ack_at=NULL WHERE ack_title='Acknowledged automatically on acceptance' AND ack_by IS NULL;
UPDATE envoy_acceptances
   SET ack_name = 'WODDI HQ',
       ack_title = 'Acknowledged automatically on acceptance',
       ack_ref = CONCAT('WODDI-ENV-', YEAR(UTC_TIMESTAMP()), '-', LPAD(user_id, 5, '0')),
       ack_at = UTC_TIMESTAMP()
 WHERE accepted_at IS NOT NULL AND ack_at IS NULL;
