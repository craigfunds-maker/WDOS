<?php // LOCAL TEST ONLY: run the work automations (meeting reminders) now.
foreach (['core','push','notify_i18n','notify','inspire_fns','reporting_fns','work_fns','journey_fns','amend_fns','handoff_fns'] as $f) require "/home/claude/wdos-php/lib/$f.php";
$d = []; work_automations($d); echo json_encode($d), "\n";
