<?php
declare(strict_types=1);
/* Tasks and meetings: routes. */

/* ------------------------------------------------------------------ tasks -- */
const TASK_SELECT = "SELECT t.*, a.first_name a_first, a.last_name a_last, a.has_photo a_photo, b.first_name b_first, b.last_name b_last,
    (SELECT COUNT(*) FROM task_items i WHERE i.task_id=t.id) items, (SELECT COUNT(*) FROM task_items i WHERE i.task_id=t.id AND i.done=1) items_done,
    (SELECT COUNT(*) FROM task_comments c WHERE c.task_id=t.id) comments, (SELECT COUNT(*) FROM task_comments c WHERE c.task_id=t.id AND c.kind='evidence') evidence,
    (SELECT c.name FROM chapters c WHERE c.id=t.chapter_id) ch_name, (SELECT o.name FROM org_units o WHERE o.id=t.org_unit_id) unit_name
  FROM tasks t JOIN users a ON a.id=t.assignee_id JOIN users b ON b.id=t.assigned_by";

function task_shape(array $t): array {
  $open = in_array($t['status'], ['open', 'in_progress', 'submitted'], true);
  return ['id' => (int) $t['id'], 'title' => $t['title'], 'details' => (string) $t['details'], 'priority' => $t['priority'], 'due_on' => $t['due_on'], 'status' => $t['status'],
    'requires_approval' => (int) $t['requires_approval'] === 1, 'overdue' => $open && $t['due_on'] && $t['due_on'] < work_today(),
    'assignee' => ['id' => (int) $t['assignee_id'], 'first_name' => $t['a_first'], 'last_name' => $t['a_last'], 'has_photo' => (int) $t['a_photo']],
    'by' => ['id' => (int) $t['assigned_by'], 'name' => trim($t['b_first'] . ' ' . $t['b_last'])], 'from_report' => str_starts_with((string) $t['source'], 'report:') ? (int) substr($t['source'], 7) : null,
    'from_meeting' => str_starts_with((string) $t['source'], 'meeting:') ? (int) substr($t['source'], 8) : null,
    'items' => (int) $t['items'], 'items_done' => (int) $t['items_done'], 'comments' => (int) $t['comments'], 'created_at' => $t['created_at'], 'completed_at' => $t['completed_at'],
    'ref' => sprintf('WDOS-TASK-%05d', (int) $t['id']), 'scope' => !empty($t['ch_name']) ? 'Chapter: ' . $t['ch_name'] : (!empty($t['unit_name']) ? $t['unit_name'] : 'Personal'), 'evidence' => (int) ($t['evidence'] ?? 0),
    'network' => $t['network'], 'chapter_id' => !empty($t['chapter_id']) ? (int) $t['chapter_id'] : null, 'parent_id' => !empty($t['parent_id']) ? (int) $t['parent_id'] : null];
}
function task_get(int $id): array { $t = row(TASK_SELECT . ' WHERE t.id=?', [$id]); if (!$t) fail(404, 'That task was not found.', 'not_found'); return $t; }
function task_rights(array $u, array $t): array {
  $mine = (int) $t['assignee_id'] === (int) $u['id']; $manage = task_can_manage($u, $t);
  if (!$mine && !$manage) fail(403, 'This task is outside your area.', 'forbidden');
  return ['mine' => $mine, 'manage' => $manage];
}

route('GET', '/tasks', function () {
  $u = require_user(); $scope = (string) ($_GET['scope'] ?? 'mine'); $id = (int) $u['id'];
  $w = 't.assignee_id=?'; $p = [$id];
  if ($scope === 'assigned') { $w = 't.assigned_by=? AND t.assignee_id<>?'; $p = [$id, $id]; }
  if ($scope === 'area') {
    if (is_hq($u)) { $w = '1=1'; $p = []; }
    else { $seat = work_seat($u); if (!$seat) { $w = '1=0'; $p = []; } else { $w = 't.network=? AND t.org_unit_id IN (SELECT id FROM org_units WHERE path LIKE ?) AND t.assignee_id<>?'; $p = [$seat['network'], $seat['unit_path'] . '%', $id]; } }
  }
  if ($cid = (int) ($_GET['chapter'] ?? 0)) {                     // every task of one chapter, for those who may see that chapter's roster
    $c = row('SELECT * FROM chapters WHERE id=?', [$cid]); if (!$c || !in_array(chapter_access($u, $c), ['hq', 'leader'], true)) fail(403, 'That chapter is outside your area.', 'forbidden');
    $w = 't.chapter_id=?'; $p = [$cid];
  }
  $st = (string) ($_GET['status'] ?? '');
  if ($st === 'active') $w .= ' AND t.status IN ' . TASK_OPEN;
  elseif ($st === 'overdue') { $w .= ' AND t.status IN ' . TASK_OPEN . ' AND t.due_on<?'; $p[] = work_today(); }
  elseif (in_array($st, ['open', 'in_progress', 'submitted', 'completed', 'cancelled'], true)) { $w .= ' AND t.status=?'; $p[] = $st; }
  if (($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w .= ' AND t.title LIKE ?'; $p[] = "%$s%"; }
  $total = (int) val("SELECT COUNT(*) FROM tasks t WHERE $w", $p);
  $list = rows(TASK_SELECT . " WHERE $w ORDER BY (t.status IN " . TASK_OPEN . ") DESC, (t.due_on IS NULL), t.due_on, t.id DESC LIMIT 100", $p);
  $c = row("SELECT COALESCE(SUM(assignee_id=? AND status IN " . TASK_OPEN . "),0) mine, COALESCE(SUM(assignee_id=? AND status IN " . TASK_OPEN . " AND due_on<?),0) overdue,
                   COALESCE(SUM(assigned_by=? AND assignee_id<>? AND status='submitted'),0) to_approve, COALESCE(SUM(assigned_by=? AND assignee_id<>? AND status IN " . TASK_OPEN . "),0) sent
            FROM tasks WHERE assignee_id=? OR assigned_by=?", [$id, $id, work_today(), $id, $id, $id, $id, $id, $id]);
  return ['items' => array_map('task_shape', $list), 'total' => $total, 'counts' => array_map('intval', $c), 'chapter_scoped' => (int) val("SELECT COUNT(*) FROM tasks t WHERE $w AND t.chapter_id IS NOT NULL", $p),
    'escalations' => (int) val("SELECT COUNT(*) FROM tasks t WHERE $w AND t.status IN " . TASK_OPEN . " AND t.due_on<?", array_merge($p, [work_today()])), 'can_assign' => is_hq($u) || (bool) work_seat($u), 'priorities' => TASK_PRIORITIES];
});
route('GET', '/tasks/people', function () {
  $u = require_user(); [$w, $p] = work_people_sql($u);
  if (($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w .= " AND CONCAT(u.first_name,' ',u.last_name) LIKE ?"; $p[] = "%$s%"; }
  $list = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.role,
      (SELECT CONCAT(CASE WHEN s.network='WNNN' THEN pp.title_wnnn ELSE pp.title_wgmn END, ', ', CASE WHEN s.area_label<>'' THEN s.area_label ELSE o.name END)
       FROM seats s JOIN positions pp ON pp.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id WHERE s.user_id=u.id AND s.ends_at IS NULL ORDER BY pp.rank_no LIMIT 1) title
    FROM users u WHERE u.status='active' AND u.id<>? AND $w ORDER BY u.first_name, u.last_name LIMIT 40", array_merge([(int) $u['id']], $p));
  foreach ($list as &$x) { $x['title'] = $x['title'] ?: (in_array($x['role'], ['hq_admin', 'super_admin'], true) ? 'WODDI HQ' : ''); unset($x['role']); }
  return ['people' => $list];
});
route('POST', '/tasks', function () {
  $u = require_user(); $b = body(); $title = str_in('title', 190);
  if (mb_strlen($title) < 3) fail(422, 'The task title must be at least 3 characters.', 'title');
  $ids = array_values(array_unique(array_map('intval', is_array($b['assignees'] ?? null) ? $b['assignees'] : [(int) ($b['assignee_id'] ?? $u['id'])])));
  if (!$ids) $ids = [(int) $u['id']];
  $cap = is_hq($u) ? 500 : 100; if (count($ids) > $cap) fail(422, "Assign to $cap people at most in one go. Narrow the group by network, level or region.", 'assignees');
  foreach ($ids as $aid) if (!work_may_assign($u, $aid)) fail(403, 'You can only assign tasks to yourself or within your own area.', 'scope');
  // scope: a task can belong to one chapter; a delegated task keeps a link to the task it came from, and still has one accountable owner
  $chapterId = (int) ($b['chapter_id'] ?? 0) ?: null; $parentId = (int) ($b['parent_id'] ?? 0) ?: null;
  if ($parentId) { $par = row('SELECT * FROM tasks WHERE id=?', [$parentId]); if (!$par || ((int) $par['assignee_id'] !== (int) $u['id'] && (int) $par['assigned_by'] !== (int) $u['id'] && !is_hq($u))) fail(403, 'You can delegate only a task that is yours.', 'parent'); if (!$chapterId && $par['chapter_id']) $chapterId = (int) $par['chapter_id']; }
  if ($chapterId) { $c = row('SELECT * FROM chapters WHERE id=?', [$chapterId]); if (!$c || !in_array(chapter_access($u, $c), ['hq', 'leader'], true)) fail(403, 'That chapter is outside your area.', 'chapter'); }
  $due = work_date(str_in('due_on', 10)); $items = array_slice(array_filter(array_map(fn($x) => mb_substr(trim((string) $x), 0, 190), is_array($b['checklist'] ?? null) ? $b['checklist'] : [])), 0, 20);
  $files = []; foreach (array_slice(array_map('intval', is_array($b['files'] ?? null) ? $b['files'] : []), 0, 10) as $fid) if (val('SELECT 1 FROM doc_files WHERE id=? AND uploaded_by=?', [$fid, (int) $u['id']])) $files[] = $fid;
  $made = [];
  foreach ($ids as $aid) {
    $tid = task_create((int) $u['id'], $aid, $title, str_in('details', 4000), str_in('priority', 8), $due, !empty($b['requires_approval']) && $aid !== (int) $u['id']);
    foreach ($items as $label) q('INSERT INTO task_items (task_id,label) VALUES (?,?)', [$tid, $label]);
    if ($chapterId || $parentId) q('UPDATE tasks SET chapter_id=?, parent_id=? WHERE id=?', [$chapterId, $parentId, $tid]);
    foreach ($files as $fid) doc_link($fid, 'task', $tid, 'brief', (int) $u['id']);
    $made[] = $tid;
  }
  log_activity((int) $u['id'], count($ids) === 1 ? $ids[0] : null, 'task_created', $title . (count($ids) > 1 ? ' (' . count($ids) . ' people)' : ''), 'task', $made[0] ?? null);
  after_response(fn() => push_drain(40));
  return ['ok' => true, 'id' => $made[0], 'created' => count($made)];
});
route('GET', '/tasks/(\d+)', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $rights = task_rights($u, $t);
  return ['task' => task_shape($t), 'rights' => $rights, 'check' => function_exists('wc_latest') ? wc_latest('task', (int) $id) : null,
    'items' => rows('SELECT id, label, done FROM task_items WHERE task_id=? ORDER BY id', [(int) $id]),
    'comments' => rows('SELECT c.id, c.body, c.kind, c.url, c.created_at, c.user_id, u.first_name, u.last_name, u.has_photo FROM task_comments c JOIN users u ON u.id=c.user_id WHERE c.task_id=? ORDER BY c.id', [(int) $id])];
});
route('POST', '/tasks/(\d+)/status', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $r = task_rights($u, $t); $to = str_in('to', 12); $from = $t['status']; $note = str_in('note', 1000);
  $approval = (int) $t['requires_approval'] === 1; $ok = false;
  if ($to === 'in_progress' && $from === 'open' && $r['mine']) $ok = true;
  if ($to === 'in_progress' && $from === 'submitted' && $r['manage']) { $ok = true; if (mb_strlen($note) < 3) fail(422, 'Say what needs to be corrected.', 'note'); }           // send back
  if ($to === 'submitted' && in_array($from, ['open', 'in_progress'], true) && $r['mine'] && $approval) $ok = true;
  if ($to === 'completed' && in_array($from, ['open', 'in_progress'], true) && $r['mine']) { if ($approval && !$r['manage']) fail(403, 'This task requires approval. Only the assigner or a leader over the unit can complete it.', 'approval'); $ok = true; }
  if ($to === 'completed' && $from === 'submitted') { if (!$r['manage']) fail(403, 'This task requires approval. Only the assigner or a leader over the unit can complete it.', 'approval'); $ok = true; }
  if ($to === 'cancelled' && in_array($from, ['open', 'in_progress', 'submitted'], true)) { if (!$r['manage']) fail(403, 'Only the assigner or a leader over the unit can cancel a task.', 'forbidden'); $ok = true; }
  if ($to === 'open' && in_array($from, ['completed', 'cancelled'], true)) { if (!$r['manage']) fail(403, 'Only the assigner or a leader over the unit can reopen a task.', 'forbidden'); $ok = true; }
  if (!$ok) fail(409, "That change is not allowed from the task's current status.", 'transition');
  if ($to === 'completed' && (int) $t['items'] > (int) $t['items_done']) fail(422, 'Tick every checklist item before completing the task.', 'checklist');
  q('UPDATE tasks SET status=?, updated_at=?, completed_at=? WHERE id=?', [$to, now(), $to === 'completed' ? now() : null, (int) $id]);
  if ($note !== '') q('INSERT INTO task_comments (task_id,user_id,body,created_at) VALUES (?,?,?,?)', [(int) $id, (int) $u['id'], $note, now()]);
  log_activity((int) $u['id'], (int) $u['id'] === (int) $t['assignee_id'] ? (int) $t['assigned_by'] : (int) $t['assignee_id'], 'task_status', mb_substr($t['title'], 0, 120) . ': ' . str_replace('_', ' ', $from) . ' to ' . str_replace('_', ' ', $to), 'task', (int) $id);
  $me = work_name($u); $title = mb_substr($t['title'], 0, 110); $url = '#/tasks/' . $id;
  $other = (int) $u['id'] === (int) $t['assignee_id'] ? (int) $t['assigned_by'] : (int) $t['assignee_id'];
  if ($other !== (int) $u['id']) {
    $msg = ['in_progress' => $from === 'submitted' ? ["Sent back: $title", "$me wrote: $note"] : ["Started: $title", "$me has started this task."], 'submitted' => ["Submitted for review: $title", "$me asks you to approve completion."],
      'completed' => ["Completed: $title", $from === 'submitted' ? "$me approved the completion." : "$me marked it completed."], 'cancelled' => ["Cancelled: $title", "$me cancelled this task."], 'open' => ["Reopened: $title", "$me reopened this task."]][$to];
    notify($other, 'task', $msg[0], $msg[1], $url); after_response(fn() => push_drain(10));
  }
  // finished work handed in: the person who gave the task is emailed, and HQ's inbox too when HQ gave it
  if (in_array($to, ['submitted', 'completed'], true) && (int) $u['id'] === (int) $t['assignee_id'] && (int) $t['assigned_by'] !== (int) $u['id']) {
    $files = doc_list('task', (int) $id); $html = '<p><b>' . htmlspecialchars($me) . '</b> ' . ($to === 'submitted' ? 'submitted' : 'completed') . ' the task <b>' . htmlspecialchars($t['title']) . '</b>.</p>' . ($files ? '<p>Attached: ' . htmlspecialchars(implode(', ', array_column(array_filter($files, fn($f) => $f['role'] === 'work'), 'name')) ?: 'none') . '</p>' : '') . ($note !== '' ? '<p>' . htmlspecialchars($note) . '</p>' : '');
    person_mail((int) $t['assigned_by'], 'WDOS task ' . ($to === 'submitted' ? 'submitted' : 'completed') . ': ' . mb_substr($t['title'], 0, 110), 'Work was handed in', $html, $url);
    if (val("SELECT 1 FROM users WHERE id=? AND role IN ('hq_admin','super_admin')", [(int) $t['assigned_by']])) { hq_mail('WDOS task ' . ($to === 'submitted' ? 'submitted' : 'completed') . ': ' . mb_substr($t['title'], 0, 100) . ' · ' . $me, 'Work was handed in to HQ', $html, $url); notify_hq('task', ($to === 'submitted' ? 'Submitted for review: ' : 'Completed: ') . mb_substr($t['title'], 0, 100), $me, $url); }
  }
  // v109.12: work HQ gave is checked by the AI service after the reply; clear gaps send it back with corrections
  if (in_array($to, ['submitted', 'completed'], true) && (int) $u['id'] === (int) $t['assignee_id'] && function_exists('wc_check_task')) after_response(fn() => wc_check_task((int) $id));
  return ['ok' => true, 'status' => $to];
});
/* Evidence: what shows the work was done. A short note, with a link to a document or photograph where there is one. */
route('POST', '/tasks/(\d+)/evidence', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $r = task_rights($u, $t); $note = str_in('note', 1500); $url = trim(str_in('url', 500));
  if (!$r['mine'] && !$r['manage']) fail(403, 'This task is outside your area.', 'forbidden');
  if (mb_strlen($note) < 3) fail(422, 'Say what was done.', 'note');
  if ($url !== '' && !preg_match('#^https://#', $url)) fail(422, 'Give a link that starts with https://', 'url');
  q("INSERT INTO task_comments (task_id,user_id,body,kind,url,created_at) VALUES (?,?,?,'evidence',?,?)", [(int) $id, (int) $u['id'], $note, $url, now()]);
  if ((int) $t['assigned_by'] !== (int) $u['id']) notify((int) $t['assigned_by'], 'task', 'Evidence added: ' . mb_substr($t['title'], 0, 100), mb_substr($note, 0, 200), '#/tasks/' . $id);
  after_response(fn() => push_drain(10));
  return ['ok' => true];
});
route('POST', '/tasks/(\d+)/comment', function ($id) {
  $u = require_user(); $t = task_get((int) $id); task_rights($u, $t); $body = str_in('body', 1500);
  if (mb_strlen($body) < 1) fail(422, 'Write the comment first.', 'body');
  q('INSERT INTO task_comments (task_id,user_id,body,created_at) VALUES (?,?,?,?)', [(int) $id, (int) $u['id'], $body, now()]);
  log_activity((int) $u['id'], (int) $u['id'] === (int) $t['assignee_id'] ? (int) $t['assigned_by'] : (int) $t['assignee_id'], 'task_comment', mb_substr($t['title'], 0, 100) . ': ' . mb_substr($body, 0, 120), 'task', (int) $id);
  foreach (array_unique([(int) $t['assignee_id'], (int) $t['assigned_by']]) as $who) if ($who !== (int) $u['id']) notify($who, 'task', work_name($u) . ' commented on: ' . mb_substr($t['title'], 0, 90), mb_substr($body, 0, 200), '#/tasks/' . $id);
  after_response(fn() => push_drain(10));
});
route('POST', '/tasks/(\d+)/item', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $r = task_rights($u, $t); $label = str_in('label', 190);
  if (!$r['manage'] && (int) $t['assigned_by'] !== (int) $t['assignee_id']) fail(403, 'Only the assigner or a leader over the unit can edit task details.', 'forbidden');
  if (mb_strlen($label) < 2) fail(422, 'Write the checklist item first.', 'label');
  if ((int) $t['items'] >= 20) fail(422, 'A task holds 20 checklist items at most.', 'max');
  q('INSERT INTO task_items (task_id,label) VALUES (?,?)', [(int) $id, $label]);
});
route('POST', '/tasks/(\d+)/item/(\d+)', function ($id, $iid) {
  $u = require_user(); $t = task_get((int) $id); task_rights($u, $t);
  if (!in_array($t['status'], ['open', 'in_progress'], true)) fail(409, 'The checklist is closed while the task is ' . str_replace('_', ' ', $t['status']) . '.', 'transition');
  q('UPDATE task_items SET done=? WHERE id=? AND task_id=?', [empty(body()['done']) ? 0 : 1, (int) $iid, (int) $id]);
});

/* --------------------------------------------------------------- meetings -- */
const MEETING_MODES = ['virtual' => 'Online', 'in_person' => 'In person', 'hybrid' => 'In person and online'];

function meeting_get(int $id): array { $m = row('SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE m.id=?', [$id]); if (!$m) fail(404, 'That meeting was not found.', 'not_found'); return $m; }
function meeting_shape(array $m): array {
  return ['id' => (int) $m['id'], 'title' => $m['title'], 'mode' => $m['mode'], 'location' => $m['location'], 'starts_at' => $m['starts_at'], 'ends_at' => $m['ends_at'], 'agenda' => (string) $m['agenda'],
    'kind' => (string) ($m['kind'] ?? 'meeting'), 'host' => trim($m['h_first'] . ' ' . $m['h_last']), 'host_id' => (int) $m['host_id'], 'audience' => $m['audience'], 'network' => $m['network'], 'scope_label' => $m['scope_label'],
    'status' => $m['status'], 'minutes' => (string) $m['minutes'], 'link' => preg_match('#https?://\S+#i', $m['location'], $lk) ? rtrim($lk[0], '.,)') : '', 'is_link' => (bool) preg_match('#^https?://\S+$#i', trim($m['location'])),
    'has_flier' => function_exists('flier_has') && flier_has($m), 'flier_image' => function_exists('flier_image_url') ? flier_image_url($m) : ''];
}
function meeting_audiences(array $u): array {
  if (is_hq($u)) return ['people:ALL' => 'People I choose', 'leaders:ALL' => 'All volunteer leaders, both networks', 'leaders:WGMN' => 'WGMN leadership', 'leaders:WNNN' => 'WNNN leadership', 'members:ALL' => 'All members, both networks', 'members:WGMN' => 'All WGMN members', 'members:WNNN' => 'All WNNN members', 'hq_team:ALL' => 'WODDI HQ team'];
  $seat = work_seat($u); if (!$seat) return [];
  return ['leaders:' . $seat['network'] => 'My leadership team', 'people:' . $seat['network'] => 'Leaders I choose', 'members:' . $seat['network'] => 'Members in my area'];
}

route('GET', '/meetings', function () {
  $u = require_user(); $past = ($_GET['scope'] ?? 'upcoming') === 'past'; $out = [];
  $list = rows('SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE ' . ($past ? "(m.ends_at<? OR m.status<>'scheduled')" : "m.ends_at>=? AND m.status='scheduled'") . ' ORDER BY m.starts_at ' . ($past ? 'DESC' : 'ASC') . ' LIMIT 200', [now()]);
  foreach ($list as $m) { if (count($out) >= 60) break; if (is_hq($u) || meeting_is_invited($u, $m)) $out[] = meeting_shape($m) + ['yes' => (int) val("SELECT COUNT(*) FROM meeting_rsvps WHERE meeting_id=? AND answer='yes'", [(int) $m['id']]), 'can_change' => (int) $m['host_id'] === (int) $u['id'] || is_hq($u)]; }
  return ['items' => $out, 'can_schedule' => (bool) meeting_audiences($u), 'audiences' => meeting_audiences($u), 'modes' => MEETING_MODES];
});
route('POST', '/meetings', function () {
  $u = require_user(); $aud = meeting_audiences($u);
  if (!$aud) fail(403, 'Meetings are scheduled by volunteer leaders and WODDI HQ.', 'forbidden');
  // v109.55: tick several groups, and add chosen people on top. The old single 'audience' still works.
  $picks = is_array(body()['audiences'] ?? null) ? array_values(array_unique(array_map(fn($x) => mb_substr((string) $x, 0, 20), body()['audiences']))) : [str_in('audience', 20)];
  $picks = array_values(array_filter($picks, fn($x) => $x !== ''));
  foreach ($picks as $x) if (!isset($aud[$x])) fail(422, 'Choose who is invited.', 'audience');
  $peopleKey = array_values(array_filter(array_keys($aud), fn($k) => str_starts_with($k, 'people:')))[0] ?? '';
  $groups = array_values(array_filter($picks, fn($x) => !str_starts_with($x, 'people:')));
  $hasPeople = is_array(body()['invitees'] ?? null) && array_filter(body()['invitees']);
  if (!$groups && !$hasPeople) fail(422, 'Tick who is invited, or add people by name.', 'audience');
  if ($hasPeople && !$peopleKey) fail(403, 'You can invite the leaders who serve under your seat.', 'invitees');
  $pick = count($groups) === 1 && !$hasPeople ? $groups[0] : (!$groups ? $peopleKey : 'mix:' . (count(array_unique(array_map(fn($g) => explode(':', $g)[1], $groups))) === 1 ? explode(':', $groups[0])[1] : 'ALL'));
  $title = str_in('title', 190); if (mb_strlen($title) < 3) fail(422, 'Give the meeting a title.', 'title');
  $s = strtotime(str_in('starts_at', 40)); $e = strtotime(str_in('ends_at', 40));
  if (!$s || !$e) fail(422, 'Both start and end times are required.', 'times');
  if ($e <= $s) fail(422, 'The end time must be after the start time.', 'order');
  $mode = isset(MEETING_MODES[str_in('mode', 12)]) ? str_in('mode', 12) : 'virtual'; $loc = str_in('location', 400);
  if ($loc === '') fail(422, $mode === 'in_person' ? 'Give the address of the meeting.' : 'Paste the meeting link here.', 'location');
  // v109.56: the flier (a picture, or words WDOS lays out as a flier), checked before anything is saved
  $fImg = (string) (body()['flier_image'] ?? ''); if ($fImg !== '') flier_check_image($fImg);
  $fHead = str_in('flier_headline', 190); $fText = str_in('flier_text', 6000);
  [$audience, $net] = explode(':', $pick); $seat = work_seat($u);
  // v109.24: a meeting with the leaders she chooses (each must be someone she may give work to)
  $invitees = [];
  if ($audience === 'people' || ($audience === 'mix' && $hasPeople)) {
    $invitees = array_values(array_unique(array_filter(array_map('intval', is_array(body()['invitees'] ?? null) ? body()['invitees'] : []), fn($x) => $x > 0 && $x !== (int) $u['id'])));
    if (!$invitees) fail(422, 'Choose who to invite.', 'invitees');
    if (count($invitees) > 200) fail(422, 'Invite 200 people at most. For more, choose a whole group.', 'invitees');
    foreach ($invitees as $iid) if (!work_may_assign($u, $iid)) fail(403, 'You can invite the leaders who serve under your seat.', 'invitees');
  }
  // HQ may keep a meeting to one country
  $only = is_hq($u) && $groups !== ['hq_team:ALL'] && (int) (body()['country_id'] ?? 0) ? row("SELECT id, name, path FROM org_units WHERE level='country' AND id=?", [(int) body()['country_id']]) : null;
  if ($only) $seat = ['org_unit_id' => (int) $only['id'], 'unit_path' => $only['path'], 'position_level' => '', 'area_label' => '', '_label' => ($aud[$pick] ?? '') . ', ' . $only['name']];
  $mid = ins('INSERT INTO meetings (title,mode,location,starts_at,ends_at,agenda,host_id,audience,network,org_unit_id,unit_path,scope_level,scope_area,scope_label,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$title, $mode, $loc, gmdate('Y-m-d H:i:s', $s), gmdate('Y-m-d H:i:s', $e), str_in('agenda', 6000), (int) $u['id'], $audience, $net, $seat ? (int) $seat['org_unit_id'] : null, $seat ? $seat['unit_path'] : '',
     $seat ? $seat['position_level'] : '', $seat ? (string) $seat['area_label'] : '', $seat ? ($seat['_label'] ?? seat_title($seat)) : ($aud[$pick] ?? ''), 'scheduled', now()]);
  foreach ($invitees as $iid) q('INSERT INTO meeting_invitees (meeting_id,user_id) VALUES (?,?)', [$mid, $iid]);
  if ($audience === 'mix') {
    foreach ($groups as $g) { [$ga, $gn] = explode(':', $g); q('INSERT INTO meeting_groups (meeting_id,audience,network) VALUES (?,?,?)', [$mid, $ga, $gn]); }
    $lab = implode(' + ', array_map(fn($g) => $aud[$g], $groups)) . ($invitees ? ($groups ? ' + ' : '') . (count($invitees) === 1 ? (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [$invitees[0]]) : count($invitees) . ' people chosen') : '') . ($only ? ', ' . $only['name'] : '');
    q('UPDATE meetings SET scope_label=? WHERE id=?', [mb_substr($lab, 0, 190), $mid]);
  }
  elseif ($invitees) q('UPDATE meetings SET scope_label=? WHERE id=?', [count($invitees) === 1 ? 'Invited: ' . (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [$invitees[0]]) : count($invitees) . ' invited leaders', $mid]);
  if ((body()['kind'] ?? '') === 'talk_show') { try { q("UPDATE meetings SET kind='talk_show' WHERE id=?", [$mid]); } catch (PDOException $e) { /* before migration 021 */ } }
  if ($fHead !== '' || $fText !== '') q('UPDATE meetings SET flier_headline=?, flier_text=? WHERE id=?', [$fHead, $fText, $mid]);
  if ($fImg !== '') flier_store_image($mid, $fImg);
  $m = meeting_get($mid); [$w, $p] = meeting_invited_sql($m);
  $join = meeting_join_sql($m, $w);
  // v109.53: each person reads the time in her own time zone
  $GLOBALS['wdos_push_image'] = flier_has($m) ? flier_image_url($m, true) : '';
  $n = notify_many_timed("u.id<>? AND $join", array_merge([(int) $u['id']], $p), 'meeting', 'Meeting: ' . mb_substr($title, 0, 120), fn($tz) => 'Called by ' . meeting_caller($u) . ' for ' . tz_when($s, $tz) . '. Tap to open the agenda and the ' . ($mode === 'in_person' ? 'address' : 'meeting link') . '.', '#/meetings/' . $mid);
  // e-mail to everyone invited (v109.58: from WODDI, with a Join button that goes through WDOS). With a flier, the email
  // waits until the host's browser has made the flier picture in each language; the timer sends it after 4 minutes at most.
  $mailed = 0; $held = false;
  if (mail_configured()) {
    if (flier_has(meeting_get($mid))) {
      $held = true; try { q('REPLACE INTO app_settings (k,v) VALUES (?,?)', ['meet_inv_wait_' . $mid, now()]); } catch (PDOException $e) { /* */ }
      $mailed = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND u.email<>'' AND (u.id=? OR ($w))", array_merge([(int) $u['id']], $p));
      if ($fHead !== '' || $fText !== '') after_response(function () use ($mid) { @set_time_limit(300); flier_translate($mid); });
    } else $mailed = meeting_invite_mail($mid);
  }
  log_activity((int) $u['id'], count($invitees) === 1 ? $invitees[0] : null, 'meeting_scheduled', $title . ($invitees ? ' · ' . (count($invitees) === 1 ? 'with ' . (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [$invitees[0]]) : count($invitees) . ' leaders invited') : ''), 'meeting', (int) $mid);
  after_response(fn() => push_drain(80)); after_response(fn() => mail_drain(15));
  return ['ok' => true, 'id' => $mid, 'invited' => $n, 'emails' => $mailed, 'mail_on' => mail_configured(), 'emails_held' => $held];
});
route('GET', '/meetings/(\d+)', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id);
  if (!is_hq($u) && !meeting_is_invited($u, $m)) fail(403, 'This meeting is for another unit.', 'forbidden');
  $host = (int) $m['host_id'] === (int) $u['id'] || is_hq($u); $people = []; $total = 0;
  if ($host) {
    [$w, $p] = meeting_invited_sql($m);
    $total = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w)", $p);
    $people = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.state_region, u.country_code, ma.status mark, mr.answer rsvp, mj.joined_at joined FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id
                    LEFT JOIN meeting_attendance ma ON ma.meeting_id=? AND ma.user_id=u.id LEFT JOIN meeting_rsvps mr ON mr.meeting_id=? AND mr.user_id=u.id LEFT JOIN meeting_joins mj ON mj.meeting_id=? AND mj.user_id=u.id
                    WHERE u.status='active' AND ($w) ORDER BY (mj.joined_at IS NULL), (mr.answer IS NULL), u.first_name, u.last_name LIMIT 300", array_merge([(int) $id, (int) $id, (int) $id], $p));
  }
  $c = row("SELECT COALESCE(SUM(status='present'),0) present, COALESCE(SUM(status='absent'),0) absent FROM meeting_attendance WHERE meeting_id=?", [(int) $id]);
  $rc = ['yes' => 0, 'maybe' => 0, 'no' => 0]; foreach (rows('SELECT answer, COUNT(*) n FROM meeting_rsvps WHERE meeting_id=? GROUP BY answer', [(int) $id]) as $r) $rc[$r['answer']] = (int) $r['n'];
  return ['meeting' => meeting_shape($m), 'flier' => flier_shape($m, flier_view_lang($u)), 'replies' => $rc, 'files' => doc_list('smeeting', (int) $id), 'host' => $host, 'people' => $people, 'invited' => $total, 'opened_link' => (int) val('SELECT COUNT(*) FROM meeting_joins WHERE meeting_id=?', [(int) $id]), 'now' => now(), 'present' => (int) $c['present'], 'absent' => (int) $c['absent'],
    'my_mark' => val('SELECT status FROM meeting_attendance WHERE meeting_id=? AND user_id=?', [(int) $id, (int) $u['id']]),
    'tasks' => array_map('task_shape', rows(TASK_SELECT . ' WHERE t.source=? ORDER BY t.id', ['meeting:' . (int) $id]))];
});
function meeting_host(array $u, array $m): void { if ((int) $m['host_id'] !== (int) $u['id'] && !is_hq($u)) fail(403, 'Only the host or WODDI HQ can change this meeting.', 'forbidden'); }
route('POST', '/meetings/(\d+)/attendance', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] === 'cancelled') fail(409, 'This meeting was cancelled.', 'transition');
  $marks = is_array(body()['marks'] ?? null) ? body()['marks'] : []; $n = 0;
  if (!$marks) fail(422, 'Mark at least one person before saving.', 'marks');
  [$w, $p] = meeting_invited_sql($m);
  foreach (array_slice($marks, 0, 400, true) as $uid => $mark) {
    $uid = (int) $uid; if (!in_array($mark, ['present', 'absent', ''], true)) continue;
    if (!val("SELECT 1 FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.id=? AND ($w)", array_merge([$uid], $p))) continue;
    q('DELETE FROM meeting_attendance WHERE meeting_id=? AND user_id=?', [(int) $id, $uid]);
    if ($mark !== '') { q('INSERT INTO meeting_attendance (meeting_id,user_id,status,marked_by,marked_at) VALUES (?,?,?,?,?)', [(int) $id, $uid, $mark, (int) $u['id'], now()]); $n++; }
  }
  return ['ok' => true, 'marked' => $n];
});
route('POST', '/meetings/(\d+)/close', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] !== 'scheduled') fail(409, "That change is not allowed from the meeting's current status.", 'transition');
  $minutes = str_in('minutes', 12000); if (mb_strlen($minutes) < 10) fail(422, 'Write what was discussed and decided.', 'minutes');
  $made = 0;
  foreach (array_slice(is_array(body()['actions'] ?? null) ? body()['actions'] : [], 0, 30) as $a) {
    $title = mb_substr(trim((string) ($a['action'] ?? '')), 0, 190); if (mb_strlen($title) < 3) continue;
    $owner = (int) ($a['owner_id'] ?? 0) ?: (int) $u['id'];
    if (!work_may_assign($u, $owner)) fail(403, 'You can only assign tasks to yourself or within your own area.', 'scope');
    task_create((int) $u['id'], $owner, $title, 'Action from the meeting "' . $m['title'] . '" held ' . gmdate('j M Y', strtotime($m['starts_at'])) . '.', 'normal', work_date((string) ($a['deadline'] ?? '')), false, 'meeting:' . (int) $id); $made++;
  }
  q("UPDATE meetings SET status='completed', minutes=?, closed_at=? WHERE id=?", [$minutes, now(), (int) $id]);
  log_activity((int) $u['id'], null, 'meeting_closed', $m['title'], 'meeting', (int) $m['id']);
  // the minutes go to everyone who was invited: in the app, by push, and by email when email is on
  $m['status'] = 'completed';
  meeting_broadcast($m, 'Minutes are ready: ' . mb_substr($m['title'], 0, 110), 'The minutes of this meeting have been recorded. Open WDOS to read them' . ($made ? " and the $made action" . ($made === 1 ? '' : 's') . ' that followed.' : '.'), false,
    '<p style="margin-top:14px"><b>Minutes</b></p><p style="white-space:pre-wrap">' . nl2br(htmlspecialchars(mb_substr($minutes, 0, 6000))) . '</p>' . meeting_attendance_html($m) . meeting_files_html((int) $id));
  after_response(fn() => push_drain(80)); after_response(fn() => mail_drain(15));
  return ['ok' => true, 'tasks' => $made];
});
route('POST', '/meetings/(\d+)/cancel', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] !== 'scheduled') fail(409, "That change is not allowed from the meeting's current status.", 'transition');
  q("UPDATE meetings SET status='cancelled', closed_at=? WHERE id=?", [now(), (int) $id]);
  [$w, $p] = meeting_invited_sql($m);
  $join = meeting_join_sql($m, $w);
  notify_many("u.id<>? AND $join", array_merge([(int) $u['id']], $p), 'meeting', 'Cancelled: ' . mb_substr($m['title'], 0, 120), 'This meeting will not hold. ' . meeting_caller($u) . ' cancelled it.', '#/meetings/' . $id);
  after_response(fn() => push_drain(80));
});

/* She pressed Join meeting in WDOS. Recorded as "opened the link", which the host can use when marking attendance. It is not proof she attended. */
route('POST', '/meetings/(\d+)/join', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id);
  if (!is_hq($u) && !meeting_is_invited($u, $m)) fail(403, 'This meeting is for another unit.', 'forbidden');
  try { q('INSERT INTO meeting_joins (meeting_id,user_id,joined_at) VALUES (?,?,?)', [(int) $id, (int) $u['id'], now()]); } catch (PDOException $e) { /* already recorded */ }
  return ['ok' => true];
});
/* HQ dashboard: the meetings coming up and the ones just held, with replies, link opens and attendance. */
route('GET', '/hq/meetings/summary', function () {
  require_hq(); $out = [];
  $list = array_merge(rows("SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE m.status='scheduled' AND m.ends_at>=? ORDER BY m.starts_at LIMIT 6", [now()]),
                      rows("SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE m.status='completed' OR (m.status='scheduled' AND m.ends_at<?) ORDER BY m.starts_at DESC LIMIT 6", [now()]));
  foreach ($list as $m) {
    [$w, $p] = meeting_invited_sql($m); $id = (int) $m['id'];
    $rs = ['yes' => 0, 'no' => 0, 'maybe' => 0]; foreach (rows('SELECT answer, COUNT(*) n FROM meeting_rsvps WHERE meeting_id=? GROUP BY answer', [$id]) as $r) $rs[$r['answer']] = (int) $r['n'];
    $c = row("SELECT COALESCE(SUM(status='present'),0) present, COALESCE(SUM(status='absent'),0) absent FROM meeting_attendance WHERE meeting_id=?", [$id]);
    $out[] = meeting_shape($m) + ['invited' => (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w)", $p), 'rsvp' => $rs,
      'opened_link' => (int) val('SELECT COUNT(*) FROM meeting_joins WHERE meeting_id=?', [$id]), 'present' => (int) $c['present'], 'absent' => (int) $c['absent'], 'upcoming' => $m['status'] === 'scheduled' && $m['ends_at'] >= now()];
  }
  return ['items' => $out, 'now' => now(), 'mail_on' => mail_configured()];
});

/* ------------------------------------------------ v109.54: change or delete a meeting or a task --
 * Azeez, 24 Nov 2026: every action on the HQ dashboard can be changed or deleted. The host or HQ changes a meeting; whoever gave a
 * task (or HQ, or a leader over that unit) changes it. Everyone affected is told, each in her own time zone. */
route('POST', '/meetings/(\d+)/edit', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] !== 'scheduled') fail(409, 'Only a meeting that is still to be held can be changed.', 'transition');
  $title = str_in('title', 190); if (mb_strlen($title) < 3) fail(422, 'Give the meeting a title.', 'title');
  $s = strtotime(str_in('starts_at', 40)); $e = strtotime(str_in('ends_at', 40));
  if (!$s || !$e) fail(422, 'Both start and end times are required.', 'times');
  if ($e <= $s) fail(422, 'The end time must be after the start time.', 'order');
  $mode = isset(MEETING_MODES[str_in('mode', 12)]) ? str_in('mode', 12) : $m['mode']; $loc = str_in('location', 400);
  if ($loc === '') fail(422, $mode === 'in_person' ? 'Give the address of the meeting.' : 'Paste the meeting link here.', 'location');
  $moved = gmdate('Y-m-d H:i:s', $s) !== $m['starts_at'] || gmdate('Y-m-d H:i:s', $e) !== $m['ends_at'];
  $placeChanged = $loc !== $m['location'] || $mode !== $m['mode'];
  q('UPDATE meetings SET title=?, mode=?, location=?, starts_at=?, ends_at=?, agenda=? WHERE id=?', [$title, $mode, $loc, gmdate('Y-m-d H:i:s', $s), gmdate('Y-m-d H:i:s', $e), str_in('agenda', 6000), (int) $id]);
  if ($moved) q("DELETE FROM app_settings WHERE k IN (?,?,?,?,?,?,?,?)", ['meet_wk_' . $id, 'meet_3d_' . $id, 'meet_day_' . $id, 'meet_rem_' . $id, 'meet_now_' . $id, 'meet_15_' . $id, 'meet_late_' . $id, 'meet_close_' . $id]);   // the reminders go out again for the new time
  $told = 0;
  if ($moved || $placeChanged || !empty(body()['notify'])) {
    $what = $moved ? 'New time' : ($placeChanged ? ($mode === 'in_person' ? 'New address' : 'New meeting link') : 'Updated');
    $told = meeting_broadcast(meeting_get((int) $id), 'Meeting changed: ' . mb_substr($title, 0, 110), fn($tz) => $what . ': ' . tz_when($s, $tz) . '. Open WDOS for the details.', false);
    after_response(fn() => push_drain(80)); after_response(fn() => mail_drain(15));
  }
  log_activity((int) $u['id'], null, 'meeting_changed', $title . ($moved ? ' · new time' : '') . ($placeChanged ? ' · new place or link' : ''), 'meeting', (int) $id);
  return ['ok' => true, 'told' => $told];
});
route('POST', '/meetings/(\d+)/delete', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if (empty(body()['confirm'])) fail(422, 'Confirm that you want to delete this meeting.', 'confirm');
  $told = 0;
  if ($m['status'] === 'scheduled' && strtotime($m['ends_at'] . ' UTC') > time())     // still to come: those invited are told it will not hold
    $told = meeting_broadcast($m, 'Cancelled: ' . mb_substr($m['title'], 0, 120), 'This meeting will not hold. ' . meeting_caller($u) . ' cancelled it.', false);
  if (function_exists('flier_drop_image')) { flier_drop_image((string) ($m['flier_image'] ?? ''), (string) ($m['flier_mime'] ?? '')); flier_drop_renders((int) $id); }
  try { q("UPDATE doc_links SET removed_at=? WHERE owner_type='smeeting' AND owner_id=? AND removed_at IS NULL", [now(), (int) $id]); } catch (PDOException $e) { /* */ }
  foreach (['meeting_invitees', 'meeting_groups', 'meeting_flier_texts', 'meeting_rsvps', 'meeting_joins', 'meeting_attendance'] as $t) { try { q("DELETE FROM $t WHERE meeting_id=?", [(int) $id]); } catch (PDOException $e) { /* table not there */ } }
  q("UPDATE tasks SET source='' WHERE source=?", ['meeting:' . (int) $id]);   // its actions stay as tasks, with their owners and status
  q('DELETE FROM meetings WHERE id=?', [(int) $id]);
  q("DELETE FROM app_settings WHERE k IN (?,?,?,?,?,?,?,?)", ['meet_wk_' . $id, 'meet_3d_' . $id, 'meet_day_' . $id, 'meet_rem_' . $id, 'meet_now_' . $id, 'meet_15_' . $id, 'meet_late_' . $id, 'meet_close_' . $id]);
  log_activity((int) $u['id'], null, 'meeting_deleted', $m['title'] . ' · ' . $m['starts_at'] . ' UTC');
  after_response(fn() => push_drain(80));
  return ['ok' => true, 'told' => $told];
});
route('POST', '/tasks/(\d+)/edit', function ($id) {
  $u = require_user(); $t = task_get((int) $id); if (!task_can_manage($u, $t)) fail(403, 'Only whoever gave this task, a leader over that unit, or HQ can change it.', 'forbidden');
  if (in_array($t['status'], ['completed', 'cancelled'], true)) fail(409, 'A finished or cancelled task cannot be changed. Reopen it first.', 'transition');
  $title = str_in('title', 190); if (mb_strlen($title) < 3) fail(422, 'Give the task a title.', 'title');
  $pri = in_array(str_in('priority', 8), ['low', 'normal', 'high', 'urgent'], true) ? str_in('priority', 8) : $t['priority'];
  $due = str_in('due_on', 10); if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) fail(422, 'Choose a due date.', 'due');
  q('UPDATE tasks SET title=?, details=?, priority=?, due_on=?, updated_at=? WHERE id=?', [$title, str_in('details', 4000), $pri, $due === '' ? null : $due, now(), (int) $id]);
  if ((int) $t['assignee_id'] !== (int) $u['id']) notify((int) $t['assignee_id'], 'task', 'Task changed: ' . mb_substr($title, 0, 120), work_name($u) . ' changed this task' . ($due !== (string) $t['due_on'] ? ($due === '' ? '. It has no due date now.' : '. New due date: ' . $due . '.') : '.'), '#/tasks/' . $id);
  log_activity((int) $u['id'], (int) $t['assignee_id'], 'task_changed', $title, 'task', (int) $id);
  after_response(fn() => push_drain(10));
  return ['ok' => true];
});
route('POST', '/tasks/(\d+)/delete', function ($id) {
  $u = require_user(); $t = task_get((int) $id); if (!task_can_manage($u, $t)) fail(403, 'Only whoever gave this task, a leader over that unit, or HQ can delete it.', 'forbidden');
  if (empty(body()['confirm'])) fail(422, 'Confirm that you want to delete this task.', 'confirm');
  foreach (['task_items', 'task_comments'] as $x) q("DELETE FROM $x WHERE task_id=?", [(int) $id]);
  q('UPDATE tasks SET parent_id=NULL WHERE parent_id=?', [(int) $id]);
  q('DELETE FROM tasks WHERE id=?', [(int) $id]);
  if ((int) $t['assignee_id'] !== (int) $u['id'] && !in_array($t['status'], ['completed', 'cancelled'], true)) notify((int) $t['assignee_id'], 'task', 'Task removed: ' . mb_substr($t['title'], 0, 120), work_name($u) . ' removed this task. You do not need to do it.', '#/tasks');
  log_activity((int) $u['id'], (int) $t['assignee_id'], 'task_deleted', $t['title']);
  after_response(fn() => push_drain(10));
  return ['ok' => true];
});
