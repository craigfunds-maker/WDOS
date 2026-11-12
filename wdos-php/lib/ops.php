<?php
/* Routes for leader terms, the operations figures and backup. Functions are in ops_fns.php. */

/* ------------------------------------------------ leader terms and replacement -- */
route('GET', '/hq/terms', function () {
  require_hq(); $today = gmdate('Y-m-d');
  $shape = fn($s) => ['seat_id' => (int) $s['id'], 'user_id' => (int) $s['user_id'], 'name' => trim($s['first_name'] . ' ' . $s['last_name']), 'title' => position_title($s['position_code'], $s['network']), 'network' => $s['network'],
    'area' => $s['area_label'] !== '' ? $s['area_label'] : (string) $s['unit_name'], 'term_ends_on' => $s['term_ends_on'], 'days_left' => $s['term_ends_on'] ? (int) floor((strtotime($s['term_ends_on']) - strtotime($today)) / 86400) : null, 'ended_at' => $s['ends_at']];
  $sel = 'SELECT s.*, u.first_name, u.last_name, ou.name unit_name FROM seats s JOIN users u ON u.id=s.user_id JOIN org_units ou ON ou.id=s.org_unit_id';
  return ['as_of' => now(), 'rule' => policy('seat_term_expiry'),
    'open_seats' => (int) val('SELECT COUNT(*) FROM seats WHERE ends_at IS NULL'), 'with_end_date' => (int) val('SELECT COUNT(*) FROM seats WHERE ends_at IS NULL AND term_ends_on IS NOT NULL'),
    'due' => array_map($shape, rows("$sel WHERE s.ends_at IS NULL AND s.term_ends_on IS NOT NULL AND s.term_ends_on<=? ORDER BY s.term_ends_on LIMIT 200", [gmdate('Y-m-d', time() + 60 * 86400)])),
    'ended' => array_map($shape, rows("$sel WHERE s.end_reason='term_expired' AND s.ends_at>=? ORDER BY s.ends_at DESC LIMIT 100", [gmdate('Y-m-d H:i:s', time() - 90 * 86400)]))];
});
route('POST', '/hq/seats/(\d+)/term', function ($id) { $me = require_hq(); return ['ok' => true, 'term_ends_on' => seat_term_set((int) $id, (string) (body()['term_ends_on'] ?? ''), (int) $me['id'])]; });
route('POST', '/hq/seats/(\d+)/end-term', function ($id) {
  $me = require_hq(); $s = row('SELECT * FROM seats WHERE id=? AND ends_at IS NULL', [(int) $id]); if (!$s) fail(404, 'That appointment is not open.', 'not_found');
  seat_term_end($s, (int) $me['id']); after_response(fn() => push_drain(5));
  return ['ok' => true];
});

/* ------------------------------------------------ operations figures for the HQ dashboard -- */
route('GET', '/hq/operations', function () {
  require_hq(); if (function_exists('workflow_seed_once')) workflow_seed_once();
  $n = fn(string $sql, array $p = []) => (int) (val($sql, $p) ?? 0); $wk = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
  $items = rows('SELECT * FROM workflow_catalogue'); $sc = workflow_score($items);
  $oldest = val("SELECT MIN(created_at) FROM cases WHERE status IN ('received','in_review')");
  return ['as_of' => now(),
    'automation' => ['workflows' => count($items), 'weighted' => count($items) - (int) $sc['unweighted'], 'verified_share' => $sc['score'], 'declared_share' => $sc['declared_score'], 'target' => 80],
    'delivery' => ['email_on' => (bool) cfg('BREVO_API_KEY') && (bool) cfg('MAIL_FROM'),
      'push_sent_7d' => $n("SELECT COUNT(*) FROM push_outbox WHERE status='sent' AND created_at>=?", [$wk]), 'push_failed_7d' => $n("SELECT COUNT(*) FROM push_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at>=?", [$wk]),
      'mail_sent_7d' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status='sent' AND created_at>=?", [$wk]), 'mail_failed_7d' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at>=?", [$wk]),
      'waiting' => $n("SELECT COUNT(*) FROM push_outbox WHERE status='queued'") + $n("SELECT COUNT(*) FROM mail_outbox WHERE status='queued'")],
    'support' => ['open_cases' => $n("SELECT COUNT(*) FROM cases WHERE status IN ('received','in_review')"), 'oldest_open_days' => $oldest ? (int) floor((time() - strtotime($oldest)) / 86400) : null, 'cases_30d' => $n('SELECT COUNT(*) FROM cases WHERE created_at>=?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)])],
    'terms' => ['due_60d' => $n('SELECT COUNT(*) FROM seats WHERE ends_at IS NULL AND term_ends_on IS NOT NULL AND term_ends_on<=?', [gmdate('Y-m-d', time() + 60 * 86400)]), 'overdue' => $n('SELECT COUNT(*) FROM seats WHERE ends_at IS NULL AND term_ends_on IS NOT NULL AND term_ends_on<?', [gmdate('Y-m-d')])],
    'uptime' => ['measured' => false, 'note' => 'WDOS cannot time its own outages. Uptime comes from the hosting dashboard or an outside monitor.'],
    'backup' => ['last' => (string) setting('backup_last', ''), 'by' => (string) setting('backup_last_by', '')]];
});

/* ------------------------------------------------ backup: everything WDOS holds, as one file, for a super administrator only --
   The file holds personal details and password hashes. It is never kept on the server: it is made on request, sent, and logged. */
route('POST', '/hq/backup', function () {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator can take a backup.', 'forbidden');
  throttle('backup:' . (int) $me['id'], 6, 3600); throttle_hit('backup:' . (int) $me['id']);
  if (empty($me['password_hash']) || !password_verify((string) (body()['password'] ?? ''), $me['password_hash'])) fail(403, 'Type your own password to take a backup.', 'password');
  @set_time_limit(600); $tables = array_values(array_diff(array_column(rows('SHOW TABLES'), array_key_first(rows('SHOW TABLES')[0])), BACKUP_SKIP));
  set_setting('backup_last', now()); set_setting('backup_last_by', trim($me['first_name'] . ' ' . $me['last_name'])); log_activity((int) $me['id'], null, 'backup_taken', count($tables) . ' tables');     // recorded first, so the file contains its own record
  $counts = []; foreach ($tables as $t) $counts[$t] = (int) val("SELECT COUNT(*) FROM `$t`");
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: application/gzip'); header('Content-Disposition: attachment; filename="wdos-backup-' . gmdate('Ymd-His') . '.jsonl.gz"'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
  $gz = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]); $out = function (string $line, int $flag = ZLIB_NO_FLUSH) use ($gz) { echo deflate_add($gz, $line, $flag); };
  $out(json_encode(['format' => 'wdos-backup-1', 'build' => WDOS_BUILD, 'made_at' => now(), 'tables' => $counts], JSON_UNESCAPED_UNICODE) . "\n");
  foreach ($tables as $t) {
    $bin = []; foreach (rows("SHOW COLUMNS FROM `$t`") as $c) if (preg_match('/blob|binary/i', $c['Type'])) $bin[] = $c['Field'];
    $st = db()->query("SELECT * FROM `$t`"); $i = 0;
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      foreach ($bin as $b) if ($r[$b] !== null) $r[$b] = ['$b64' => base64_encode($r[$b])];
      $line = json_encode(['t' => $t, 'r' => $r], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); $out($line . "\n"); if (++$i % 200 === 0) flush();
    }
    $st->closeCursor();
  }
  echo deflate_add($gz, '', ZLIB_FINISH); exit;
});
