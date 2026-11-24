<?php // LOCAL TEST ONLY: run the daily leader-term job now.
foreach (['core','push','notify_i18n','notify','timezones','work_fns','journey_fns','inspire_fns','amend_fns','handoff_fns','ops_fns'] as $f) require "/home/claude/wdos-php/lib/$f.php";
q("DELETE FROM cron_runs WHERE job='seat_terms'"); $d = []; ops_automations($d); echo json_encode($d), "\n";
