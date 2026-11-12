<?php
/* Restore a WDOS backup into an EMPTY database. Command line only; a web request gets nothing.
   Use: php lib/restore_cli.php wdos-backup-YYYYMMDD-HHMMSS.jsonl.gz
   Before running: point the configuration at the new, empty database and run the migrations (setup, action "migrate").
   It refuses to run when the database already holds people, so a live database can never be overwritten by mistake.
   It ends by comparing the number of rows in every table with the numbers written in the backup. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/core.php';
$file = $argv[1] ?? ''; if ($file === '' || !is_readable($file)) { fwrite(STDERR, "Give the path of the backup file.\n"); exit(2); }
if ((int) val('SELECT COUNT(*) FROM users') > 0) { fwrite(STDERR, "This database already holds people. Restore only into a new, empty database.\n"); exit(3); }
$h = gzopen($file, 'rb'); $meta = json_decode((string) gzgets($h), true);
if (($meta['format'] ?? '') !== 'wdos-backup-1') { fwrite(STDERR, "This is not a WDOS backup file.\n"); exit(4); }
$pdo = db(); $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); $have = array_flip(array_column(rows('SHOW TABLES'), array_key_first(rows('SHOW TABLES')[0])));
$cleared = []; $skipped = []; $stmts = [];
while (($line = gzgets($h)) !== false) {
  $j = json_decode($line, true); if (!$j) continue; $t = $j['t']; $r = $j['r'];
  if (!isset($have[$t])) { $skipped[$t] = ($skipped[$t] ?? 0) + 1; continue; }
  if (!isset($cleared[$t])) { if ($t !== 'migrations') $pdo->exec("DELETE FROM `$t`"); $cleared[$t] = true; }      // seed rows the migrations made are replaced by the backup's own
  if ($t === 'migrations') continue;                                                                             // the new database keeps its own record of migrations
  foreach ($r as $k => $v) if (is_array($v) && isset($v['$b64'])) $r[$k] = base64_decode($v['$b64']);
  $cols = array_keys($r); $key = $t . '|' . implode(',', $cols);
  $stmts[$key] ??= $pdo->prepare("INSERT INTO `$t` (`" . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
  $stmts[$key]->execute(array_values($r));
}
gzclose($h); $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); $bad = 0;
foreach ($meta['tables'] as $t => $n) {
  if ($t === 'migrations') continue;
  if (!isset($have[$t])) { echo "MISSING TABLE  $t (the backup has $n rows; run the migrations first)\n"; $bad++; continue; }
  $got = (int) val("SELECT COUNT(*) FROM `$t`"); if ($got !== (int) $n) { echo "DIFFERENT      $t: backup $n, restored $got\n"; $bad++; }
}
echo 'Backup of ' . $meta['made_at'] . ' (build ' . $meta['build'] . '): ' . count($meta['tables']) . ' tables, ' . array_sum($meta['tables']) . " rows.\n";
echo $bad ? "RESTORE NOT CLEAN: $bad table(s) differ.\n" : "RESTORE CLEAN: every table has the same number of rows as the backup.\n";
exit($bad ? 1 : 0);
