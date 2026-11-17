<?php
declare(strict_types=1);
/* v109.12: the AI check of handed-in work (tasks HQ gave, and reports). See migrations/034_work_checks.sql.
 * The check is careful on purpose: it flags only clear gaps between what was asked and what was handed in. When no AI
 * lane answers, nothing is flagged and the work follows the normal human review. */

/** The words of a Word, text or CSV file (first 6,000 characters). PDF and pictures are not read: the check says so. */
function wc_file_text(int $fileId, string $mime): string {
  $f = row('SELECT size FROM doc_files WHERE id=?', [$fileId]); if (!$f || (int) $f['size'] > 8000000) return '';
  $bytes = ''; foreach (rows('SELECT bytes FROM doc_chunks WHERE file_id=? ORDER BY seq', [$fileId]) as $c) $bytes .= $c['bytes'];
  if (in_array($mime, ['text/plain', 'text/csv'], true)) return mb_substr($bytes, 0, 6000);
  if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'wc'); file_put_contents($tmp, $bytes); $z = new ZipArchive(); $out = '';
    if ($z->open($tmp) === true) { $x = (string) $z->getFromName('word/document.xml'); $z->close(); $x = preg_replace('#</w:p>#', "\n", $x); $out = html_entity_decode(strip_tags((string) $x), ENT_QUOTES | ENT_XML1); }
    @unlink($tmp); return mb_substr(trim((string) preg_replace("/\n{3,}/", "\n\n", $out)), 0, 6000);
  }
  return '';
}
function wc_files_block(string $type, int $id): string {
  $out = [];
  foreach (doc_list($type, $id) as $d) {
    $txt = wc_file_text((int) $d['id'], (string) $d['mime']);
    $out[] = '- ' . $d['name'] . ($txt !== '' ? "\n  Content:\n" . $txt : ' (content not readable by the check: ' . $d['mime'] . ')');
  }
  return $out ? implode("\n", $out) : '(no file attached)';
}
const WC_SYSTEM = 'You check volunteer work for WODDI, a women\'s development organisation. Compare WHAT WAS ASKED with WHAT WAS HANDED IN. '
  . 'Be fair and careful: volunteers are often rural women writing briefly on phones. Flag the work ONLY when something the assignment clearly asked for is missing, '
  . 'empty, off-topic, or contradicts itself. Do not flag style, spelling, brevity or formatting. If a file\'s content could not be read, do not assume it is wrong. '
  . 'Answer with JSON only: {"verdict":"ok"|"needs_correction","summary":"one short sentence","points":["each correction as a plain instruction she can act on"]}. '
  . 'At most 5 points. Write in simple English.';

/** Runs the check and stores it. Returns the stored row, or null when the AI could not answer (nothing is flagged then). */
function wc_run(string $kind, int $itemId, int $ownerId, string $asked, string $handed, array $names = []): ?array {
  if (!class_exists('Ai') || !Ai::enabled()) return null;
  $res = Ai::chat('work_check', WC_SYSTEM, "WHAT WAS ASKED:\n" . mb_substr($asked, 0, 5000) . "\n\nWHAT WAS HANDED IN:\n" . mb_substr($handed, 0, 9000),
    ['class' => 'text', 'user_id' => $ownerId, 'ttl' => 0, 'max_tokens' => 500, 'names' => $names, 'prefer' => 'huggingface']);
  if (empty($res['ok'])) return null;
  $j = null; if (preg_match('/\{.*\}/s', (string) $res['text'], $m)) $j = json_decode($m[0], true);
  if (!is_array($j) || !in_array($j['verdict'] ?? '', ['ok', 'needs_correction'], true)) return null;
  $points = array_values(array_filter(array_map(fn($p) => mb_substr(trim((string) $p), 0, 300), (array) ($j['points'] ?? []))));
  if ($j['verdict'] === 'needs_correction' && !$points) return null;   // a flag with nothing to correct is not useful
  q("UPDATE work_checks SET status='superseded' WHERE kind=? AND item_id=? AND status='open'", [$kind, $itemId]);
  $id = ins('INSERT INTO work_checks (kind,item_id,owner_id,verdict,summary,points,lane,model,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
    [$kind, $itemId, $ownerId, $j['verdict'], mb_substr((string) ($j['summary'] ?? ''), 0, 600), json_encode(array_slice($points, 0, 5)), (string) ($res['lane'] ?? ''), mb_substr((string) ($res['model'] ?? ''), 0, 120), $j['verdict'] === 'ok' ? 'passed' : 'open', now()]);
  return row('SELECT * FROM work_checks WHERE id=?', [$id]);
}
function wc_out(?array $c): ?array {
  if (!$c) return null;
  return ['id' => (int) $c['id'], 'verdict' => $c['verdict'], 'summary' => $c['summary'], 'points' => json_decode((string) $c['points'], true) ?: [], 'status' => $c['status'],
    'lane' => $c['lane'], 'reminders' => (int) $c['reminders'], 'created_at' => $c['created_at'], 'decided_at' => $c['decided_at']];
}
function wc_latest(string $kind, int $itemId): ?array { return wc_out(row('SELECT * FROM work_checks WHERE kind=? AND item_id=? ORDER BY id DESC LIMIT 1', [$kind, $itemId])); }
function wc_points_text(array $c): string { return implode("\n", array_map(fn($p) => '• ' . $p, json_decode((string) $c['points'], true) ?: [])); }

/** A task the leader handed in (submitted or completed), when HQ gave it. Runs after the reply has been sent. */
function wc_check_task(int $taskId): void {
  $t = row('SELECT t.*, a.role AS by_role FROM tasks t JOIN users a ON a.id=t.assigned_by WHERE t.id=?', [$taskId]);
  if (!$t || !in_array($t['by_role'], ['hq_admin', 'super_admin'], true) || !in_array($t['status'], ['submitted', 'completed'], true)) return;
  $asked = "Task: {$t['title']}\n" . trim((string) $t['details']) . ($t['due_on'] ? "\nDue: {$t['due_on']}" : '');
  $items = rows('SELECT label, done FROM task_items WHERE task_id=? ORDER BY id', [$taskId]);
  if ($items) $asked .= "\nChecklist:\n" . implode("\n", array_map(fn($i) => '- ' . $i['label'], $items));
  $notes = rows("SELECT body, kind, url FROM task_comments WHERE task_id=? AND user_id=? ORDER BY id", [$taskId, (int) $t['assignee_id']]);
  $handed = "Notes and evidence from the volunteer:\n" . ($notes ? implode("\n", array_map(fn($n) => '- ' . ($n['kind'] === 'evidence' ? '[evidence] ' : '') . $n['body'] . ($n['url'] ? ' (' . $n['url'] . ')' : ''), $notes)) : '(none)')
    . ($items ? "\nChecklist ticked: " . count(array_filter($items, fn($i) => (int) $i['done'] === 1)) . ' of ' . count($items) : '') . "\nFiles:\n" . wc_files_block('task', $taskId);
  $u = row('SELECT first_name, last_name FROM users WHERE id=?', [(int) $t['assignee_id']]);
  $c = wc_run('task', $taskId, (int) $t['assignee_id'], $asked, $handed, $u ? [$u['first_name'], $u['last_name']] : []);
  if (!$c || $c['verdict'] !== 'needs_correction') return;
  // sent back to her with the corrections; HQ sees the flag and can still mark it done
  q("UPDATE tasks SET status='in_progress', completed_at=NULL, updated_at=? WHERE id=? AND status IN ('submitted','completed')", [now(), $taskId]);
  q("INSERT INTO task_comments (task_id,user_id,body,kind,created_at) VALUES (?,?,?,'ai',?)", [$taskId, (int) $t['assigned_by'], "The WDOS work check found something to correct:\n" . wc_points_text($c) . "\nCorrect it and hand the task in again. HQ can also review it.", now()]);
  notify((int) $t['assignee_id'], 'task', 'Needs correction: ' . mb_substr($t['title'], 0, 100), mb_substr($c['summary'] . ' ' . wc_points_text($c), 0, 400), '#/tasks/' . $taskId);
  notify_hq('task', 'Work check flagged: ' . mb_substr($t['title'], 0, 100), mb_substr($c['summary'], 0, 200), '#/tasks/' . $taskId);
  if (function_exists('push_drain')) push_drain(10);
}
/** A report the leader submitted. Runs after the reply has been sent. */
function wc_check_report(int $reportId): void {
  $r = row('SELECT * FROM report_instances WHERE id=?', [$reportId]); if (!$r || $r['status'] !== 'submitted' || (int) $r['is_nil'] === 1) return;
  $t = rp_template($r['template']); if (!$t) return;
  $ans = json_decode((string) $r['answers'], true) ?: []; $askedL = []; $handL = [];
  foreach (($t['definition']['sections'] ?? []) as $s) {
    $askedL[] = '## ' . ($s['title'] ?? '');
    foreach (($s['fields'] ?? []) as $f) { $k = $f['key'] ?? ($f['id'] ?? ''); $askedL[] = '- ' . ($f['label'] ?? $k) . (!empty($f['required']) ? ' (required)' : ''); $v = $ans[$k] ?? ''; $handL[] = '- ' . ($f['label'] ?? $k) . ': ' . (is_scalar($v) ? (string) $v : json_encode($v)); }
  }
  $asked = "Report form: {$t['title']} for {$r['unit_label']}, period {$r['period_start']} to {$r['period_end']}\n" . implode("\n", $askedL);
  $handed = "Answers:\n" . implode("\n", $handL) . "\nFiles:\n" . wc_files_block('report', $reportId);
  $u = row('SELECT first_name, last_name FROM users WHERE id=?', [(int) $r['owner_id']]);
  $c = wc_run('report', $reportId, (int) $r['owner_id'], $asked, $handed, $u ? [$u['first_name'], $u['last_name']] : []);
  if (!$c || $c['verdict'] !== 'needs_correction') return;
  q("UPDATE report_instances SET status='returned', updated_at=? WHERE id=? AND status='submitted'", [now(), $reportId]);
  q('INSERT INTO report_reviews (report_id,version,action,note,by_user,created_at) VALUES (?,?,?,?,?,?)', [$reportId, (int) $r['version'], 'return', mb_substr("WDOS work check: " . $c['summary'] . "\n" . wc_points_text($c), 0, 1000), 0, now()]);
  notify((int) $r['owner_id'], 'report', 'Needs correction: ' . $r['report_no'], mb_substr($c['summary'] . ' ' . wc_points_text($c), 0, 400), '#/reporting/r/' . $reportId);
  notify_hq('report', 'Work check flagged: ' . $r['report_no'], mb_substr($r['unit_label'] . '. ' . $c['summary'], 0, 200), '#/reporting/r/' . $reportId);
  if (function_exists('push_drain')) push_drain(10);
}
/** Once a day: remind her of work still waiting for correction (after 2 days, then every 2 days, at most 3 times). */
function wc_reminders(): int {
  $n = 0;
  foreach (rows("SELECT * FROM work_checks WHERE status='open' AND verdict='needs_correction' AND reminders<3 AND COALESCE(reminded_at, created_at) < ?", [gmdate('Y-m-d H:i:s', time() - 2 * 86400)]) as $c) {
    $still = $c['kind'] === 'task' ? val("SELECT 1 FROM tasks WHERE id=? AND status IN ('open','in_progress')", [(int) $c['item_id']]) : val("SELECT 1 FROM report_instances WHERE id=? AND status='returned'", [(int) $c['item_id']]);
    if (!$still) { q("UPDATE work_checks SET status='corrected' WHERE id=?", [(int) $c['id']]); continue; }
    $what = $c['kind'] === 'task' ? val('SELECT title FROM tasks WHERE id=?', [(int) $c['item_id']]) : val('SELECT report_no FROM report_instances WHERE id=?', [(int) $c['item_id']]);
    notify((int) $c['owner_id'], $c['kind'] === 'task' ? 'task' : 'report', 'Reminder: correct ' . mb_substr((string) $what, 0, 100), mb_substr(wc_points_text($c), 0, 400), $c['kind'] === 'task' ? '#/tasks/' . $c['item_id'] : '#/reporting/r/' . $c['item_id']);
    q('UPDATE work_checks SET reminders=reminders+1, reminded_at=? WHERE id=?', [now(), (int) $c['id']]); $n++;
  }
  return $n;
}
