<?php
declare(strict_types=1);
/* v109.12 routes: the AI check of handed-in work, and HQ's say over it. */
route('GET', '/hq/work-checks', function () {
  require_hq(); $st = str_in('status', 12) ?: 'open';
  $rows = rows("SELECT * FROM work_checks WHERE verdict='needs_correction' AND status=? ORDER BY id DESC LIMIT 200", [$st]); $out = [];
  foreach ($rows as $c) {
    $o = wc_out($c); $o['kind'] = $c['kind']; $o['item_id'] = (int) $c['item_id'];
    $o['title'] = (string) ($c['kind'] === 'task' ? val('SELECT title FROM tasks WHERE id=?', [(int) $c['item_id']]) : val("SELECT CONCAT(report_no,' · ',unit_label) FROM report_instances WHERE id=?", [(int) $c['item_id']]));
    $o['owner'] = (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [(int) $c['owner_id']]);
    $o['url'] = $c['kind'] === 'task' ? '#/tasks/' . $c['item_id'] : '#/reporting/r/' . $c['item_id']; $out[] = $o;
  }
  return ['checks' => $out, 'counts' => ['open' => (int) val("SELECT COUNT(*) FROM work_checks WHERE verdict='needs_correction' AND status='open'"), 'passed_7d' => (int) val("SELECT COUNT(*) FROM work_checks WHERE verdict='ok' AND created_at>=?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)])]];
});
/* HQ overrules the check and marks the work as done (the task completed, the report approved). */
route('POST', '/hq/work-checks/(\d+)/done', function ($id) {
  $me = require_hq(); $c = row('SELECT * FROM work_checks WHERE id=?', [(int) $id]); if (!$c) fail(404, 'That check was not found.', 'not_found');
  $note = str_in('note', 600);
  if ($c['kind'] === 'task') {
    $t = row('SELECT * FROM tasks WHERE id=?', [(int) $c['item_id']]); if (!$t) fail(404, 'That task was not found.', 'not_found');
    if ($t['status'] === 'cancelled') fail(409, 'This task was cancelled.', 'transition');
    q("UPDATE tasks SET status='completed', completed_at=?, updated_at=? WHERE id=?", [now(), now(), (int) $t['id']]);
    q('INSERT INTO task_comments (task_id,user_id,body,created_at) VALUES (?,?,?,?)', [(int) $t['id'], (int) $me['id'], 'Marked as done by HQ' . ($note !== '' ? ": $note" : '.'), now()]);
    notify((int) $t['assignee_id'], 'task', 'Done: ' . mb_substr($t['title'], 0, 110), 'HQ reviewed your work and marked it as done.', '#/tasks/' . $t['id']);
  } else {
    $r = row('SELECT * FROM report_instances WHERE id=?', [(int) $c['item_id']]); if (!$r) fail(404, 'That report was not found.', 'not_found');
    q("UPDATE report_instances SET status='approved', approved_at=?, updated_at=? WHERE id=?", [now(), now(), (int) $r['id']]);
    q('INSERT INTO report_reviews (report_id,version,action,note,by_user,created_at) VALUES (?,?,?,?,?,?)', [(int) $r['id'], (int) $r['version'], 'approve', 'Marked as done by HQ over the work check' . ($note !== '' ? ": $note" : '.'), (int) $me['id'], now()]);
    notify((int) $r['owner_id'], 'report', $r['report_no'] . ' was approved', 'HQ reviewed your report and marked it as done.', '#/reporting/r/' . $r['id']);
  }
  q("UPDATE work_checks SET status='overridden', decided_by=?, decided_at=? WHERE kind=? AND item_id=? AND status='open'", [(int) $me['id'], now(), $c['kind'], (int) $c['item_id']]);
  log_activity((int) $me['id'], (int) $c['owner_id'], 'work_check_overridden', $c['kind'] . ' ' . $c['item_id']);
  after_response(fn() => push_drain(10));
  return ['ok' => true];
});
/* HQ asks for the check to be run again (for example after reading the files herself). */
route('POST', '/hq/work-checks/(\d+)/recheck', function ($id) {
  require_hq(); $c = row('SELECT * FROM work_checks WHERE id=?', [(int) $id]); if (!$c) fail(404, 'That check was not found.', 'not_found');
  after_response(fn() => $c['kind'] === 'task' ? wc_check_task((int) $c['item_id']) : wc_check_report((int) $c['item_id']));
  return ['ok' => true, 'queued' => true];
});
