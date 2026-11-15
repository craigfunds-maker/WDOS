<?php // LOCAL TEST ONLY: run the daily automatic work now.
foreach (['core','push','notify_i18n','notify','work_fns','journey_fns','amend_fns','handoff_fns'] as $f) require "/home/claude/wdos-php/lib/$f.php";
q("DELETE FROM cron_runs WHERE job='age_transition'"); $d = []; handoff_automations($d); echo json_encode($d), "\n";
