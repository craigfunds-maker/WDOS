<?php
/* Bring members across from the old (Supabase) WDOS from the command line, for shared hosting without the setup page:
 *   php lib/import_cli.php rows.json [commit]
 * rows.json is the JSON array of rows from migrations/EXPORT-FROM-CURRENT-WDOS.pgsql.txt. Without "commit" it is a dry run.
 * The same rules as the setup page: an e-mail that already exists is left untouched, HQ access is never granted. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['REQUEST_METHOD'] = 'CLI'; $_SERVER['REQUEST_URI'] = '/cli';
foreach (['core','push','notify_i18n','notify','inspire_fns','reporting_fns','work_fns','photos','journey_fns','amend_fns','handoff_fns','ops_fns','v109_fns','import'] as $f) require_once __DIR__ . "/$f.php";
$file = $argv[1] ?? ''; $list = json_decode((string) @file_get_contents($file), true);
if (!is_array($list)) { fwrite(STDERR, "Give the path of the rows file (a JSON array).\n"); exit(2); }
$res = import_rows($list, ($argv[2] ?? '') === 'commit');
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
