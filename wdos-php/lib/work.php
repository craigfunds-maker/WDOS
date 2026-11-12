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
  if (count($ids) > 60) fail(422, 'Assign to 60 people at most in one go.', 'assignees');
  foreach ($ids as $aid) if (!work_may_assign($u, $aid)) fail(403, 'You can only assign tasks to yourself or within your own area.', 'scope');
  // scope: a task can belong to one chapter; a delegated task keeps a link to the task it came from, and still has one accountable owner
  $chapterId = (int) ($b['chapter_id'] ?? 0) ?: null; $parentId = (int) ($b['parent_id'] ?? 0) ?: null;
  if ($parentId) { $par = row('SELECT * FROM tasks WHERE id=?', [$parentId]); if (!$par || ((int) $par['assignee_id'] !== (int) $u['id'] && (int) $par['assigned_by'] !== (int) $u['id'] && !is_hq($u))) fail(403, 'You can delegate only a task that is yours.', 'parent'); if (!$chapterId && $par['chapter_id']) $chapterId = (int) $par['chapter_id']; }
  if ($chapterId) { $c = row('SELECT * FROM chapters WHERE id=?', [$chapterId]); if (!$c || !in_array(chapter_access($u, $c), ['hq', 'leader'], true)) fail(403, 'That chapter is outside your area.', 'chapter'); }
  $due = work_date(str_in('due_on', 10)); $items = array_slice(array_filter(array_map(fn($x) => mb_substr(trim((string) $x), 0, 190), is_array($b['checklist'] ?? null) ? $b['checklist'] : [])), 0, 20);
  $made = [];
  foreach ($ids as $aid) {
    $tid = task_create((int) $u['id'], $aid, $title, str_in('details', 4000), str_in('priority', 8), $due, !empty($b['requires_approval']) && $aid !== (int) $u['id']);
    foreach ($items as $label) q('INSERT INTO task_items (task_id,label) VALUES (?,?)', [$tid, $label]);
    if ($chapterId || $parentId) q('UPDATE tasks SET chapter_id=?, parent_id=? WHERE id=?', [$chapterId, $parentId, $tid]);
    $made[] = $tid;
  }
  log_activity((int) $u['id'], count($ids) === 1 ? $ids[0] : null, 'task_created', $title . (count($ids) > 1 ? ' (' . count($ids) . ' people)' : ''));
  after_response(fn() => push_drain(40));
  return ['ok' => true, 'id' => $made[0], 'created' => count($made)];
});
route('GET', '/tasks/(\d+)', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $rights = task_rights($u, $t);
  return ['task' => task_shape($t), 'rights' => $rights,
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
  $me = work_name($u); $title = mb_substr($t['title'], 0, 110); $url = '#/tasks/' . $id;
  $other = (int) $u['id'] === (int) $t['assignee_id'] ? (int) $t['assigned_by'] : (int) $t['assignee_id'];
  if ($other !== (int) $u['id']) {
    $msg = ['in_progress' => $from === 'submitted' ? ["Sent back: $title", "$me wrote: $note"] : ["Started: $title", "$me has started this task."], 'submitted' => ["Submitted for review: $title", "$me asks you to approve completion."],
      'completed' => ["Completed: $title", $from === 'submitted' ? "$me approved the completion." : "$me marked it completed."], 'cancelled' => ["Cancelled: $title", "$me cancelled this task."], 'open' => ["Reopened: $title", "$me reopened this task."]][$to];
    notify($other, 'task', $msg[0], $msg[1], $url); after_response(fn() => push_drain(10));
  }
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
    'host' => trim($m['h_first'] . ' ' . $m['h_last']), 'host_id' => (int) $m['host_id'], 'audience' => $m['audience'], 'network' => $m['network'], 'scope_label' => $m['scope_label'],
    'status' => $m['status'], 'minutes' => (string) $m['minutes'], 'link' => preg_match('#https?://\S+#i', $m['location'], $lk) ? rtrim($lk[0], '.,)') : '', 'is_link' => (bool) preg_match('#^https?://\S+$#i', trim($m['location']))];
}
function meeting_audiences(array $u): array {
  if (is_hq($u)) return ['leaders:ALL' => 'All volunteer leaders, both networks', 'leaders:WGMN' => 'WGMN leadership', 'leaders:WNNN' => 'WNNN leadership', 'hq_team:ALL' => 'WODDI HQ team'];
  $seat = work_seat($u); if (!$seat) return [];
  return ['leaders:' . $seat['network'] => 'My leadership team', 'members:' . $seat['network'] => 'Members in my area'];
}

route('GET', '/meetings', function () {
  $u = require_user(); $past = ($_GET['scope'] ?? 'upcoming') === 'past'; $out = [];
  $list = rows('SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE ' . ($past ? "(m.ends_at<? OR m.status<>'scheduled')" : "m.ends_at>=? AND m.status='scheduled'") . ' ORDER BY m.starts_at ' . ($past ? 'DESC' : 'ASC') . ' LIMIT 200', [now()]);
  foreach ($list as $m) { if (count($out) >= 60) break; if (is_hq($u) || meeting_is_invited($u, $m)) $out[] = meeting_shape($m); }
  return ['items' => $out, 'can_schedule' => (bool) meeting_audiences($u), 'audiences' => meeting_audiences($u), 'modes' => MEETING_MODES];
});
route('POST', '/meetings', function () {
  $u = require_user(); $aud = meeting_audiences($u); $pick = str_in('audience', 20);
  if (!$aud) fail(403, 'Meetings are scheduled by volunteer leaders and WODDI HQ.', 'forbidden');
  if (!isset($aud[$pick])) fail(422, 'Choose who is invited.', 'audience');
  $title = str_in('title', 190); if (mb_strlen($title) < 3) fail(422, 'Give the meeting a title.', 'title');
  $s = strtotime(str_in('starts_at', 40)); $e = strtotime(str_in('ends_at', 40));
  if (!$s || !$e) fail(422, 'Both start and end times are required.', 'times');
  if ($e <= $s) fail(422, 'The end time must be after the start time.', 'order');
  $mode = isset(MEETING_MODES[str_in('mode', 12)]) ? str_in('mode', 12) : 'virtual'; $loc = str_in('location', 400);
  if ($loc === '') fail(422, $mode === 'in_person' ? 'Give the address of the meeting.' : 'Paste the meeting link here.', 'location');
  [$audience, $net] = explode(':', $pick); $seat = work_seat($u);
  $mid = ins('INSERT INTO meetings (title,mode,location,starts_at,ends_at,agenda,host_id,audience,network,org_unit_id,unit_path,scope_level,scope_area,scope_label,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$title, $mode, $loc, gmdate('Y-m-d H:i:s', $s), gmdate('Y-m-d H:i:s', $e), str_in('agenda', 6000), (int) $u['id'], $audience, $net, $seat ? (int) $seat['org_unit_id'] : null, $seat ? $seat['unit_path'] : '',
     $seat ? $seat['position_level'] : '', $seat ? (string) $seat['area_label'] : '', $seat ? seat_title($seat) : $aud[$pick], 'scheduled', now()]);
  $m = meeting_get($mid); [$w, $p] = meeting_invited_sql($m);
  $join = $audience === 'members' ? "u.id IN (SELECT u.id FROM (SELECT u.id FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w) u)" : $w;
  $n = notify_many("u.id<>? AND $join", array_merge([(int) $u['id']], $p), 'meeting', 'Meeting: ' . mb_substr($title, 0, 120), 'Called by ' . work_name($u) . ' for ' . gmdate('j M Y, H:i', $s) . ' GMT. Tap to open the agenda and the ' . ($mode === 'in_person' ? 'address' : 'meeting link') . '.', '#/meetings/' . $mid);
  // e-mail to everyone invited, with the link or address in it (queued, then sent a few at a time)
  $mailed = 0;
  if (mail_configured()) {
    $when = gmdate('l j F Y, H:i', $s) . ' to ' . gmdate('H:i', $e) . ' GMT'; $link = preg_match('#https://\S+#i', $loc, $lm) ? rtrim($lm[0], '.,)') : '';
    $html = '<p><b>' . htmlspecialchars(work_name($u)) . '</b> has called a meeting.</p><table cellpadding="6" style="border-collapse:collapse;font-size:15px"><tr><td style="color:#7a6a72">Meeting</td><td><b>' . htmlspecialchars($title) . '</b></td></tr><tr><td style="color:#7a6a72">When</td><td>' . $when . '</td></tr><tr><td style="color:#7a6a72">Format</td><td>' . MEETING_MODES[$mode] . '</td></tr><tr><td style="color:#7a6a72">' . ($link ? 'Link' : 'Where') . '</td><td>'
      . ($link ? '<a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a>' . (trim(str_replace($lm[0], '', $loc)) !== '' ? '<br>' . htmlspecialchars(trim(str_replace($lm[0], '', $loc))) : '') : htmlspecialchars($loc)) . '</td></tr></table>'
      . (trim(str_in('agenda', 6000)) !== '' ? '<p style="margin-top:14px"><b>Agenda</b></p><p style="white-space:pre-wrap">' . nl2br(htmlspecialchars(mb_substr(str_in('agenda', 6000), 0, 2000))) . '</p>' : '');
    foreach (rows("SELECT u.email, u.first_name FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND u.email<>'' AND (u.id=? OR ($w)) LIMIT 800", array_merge([(int) $u['id']], $p)) as $r) {
      if (mail_queue($r['email'], $r['first_name'], 'Meeting: ' . mb_substr($title, 0, 150), mail_wrap('Dear ' . $r['first_name'] . ',', $html, $link ? 'Join the meeting' : 'Open in WDOS', $link ?: app_url() . '/#/meetings/' . $mid))) $mailed++;
    }
  }
  log_activity((int) $u['id'], null, 'meeting_scheduled', $title);
  after_response(fn() => push_drain(80)); after_response(fn() => mail_drain(15));
  return ['ok' => true, 'id' => $mid, 'invited' => $n, 'emails' => $mailed, 'mail_on' => mail_configured()];
});
route('GET', '/meetings/(\d+)', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id);
  if (!is_hq($u) && !meeting_is_invited($u, $m)) fail(403, 'This meeting is for another unit.', 'forbidden');
  $host = (int) $m['host_id'] === (int) $u['id'] || is_hq($u); $people = []; $total = 0;
  if ($host) {
    [$w, $p] = meeting_invited_sql($m);
    $total = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w)", $p);
    $people = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.state_region, u.country_code, ma.status mark FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id
                    LEFT JOIN meeting_attendance ma ON ma.meeting_id=? AND ma.user_id=u.id WHERE u.status='active' AND ($w) ORDER BY u.first_name, u.last_name LIMIT 300", array_merge([(int) $id], $p));
  }
  $c = row("SELECT COALESCE(SUM(status='present'),0) present, COALESCE(SUM(status='absent'),0) absent FROM meeting_attendance WHERE meeting_id=?", [(int) $id]);
  return ['meeting' => meeting_shape($m), 'host' => $host, 'people' => $people, 'invited' => $total, 'present' => (int) $c['present'], 'absent' => (int) $c['absent'],
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
  log_activity((int) $u['id'], null, 'meeting_closed', $m['title']);
  after_response(fn() => push_drain(30));
  return ['ok' => true, 'tasks' => $made];
});
route('POST', '/meetings/(\d+)/cancel', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] !== 'scheduled') fail(409, "That change is not allowed from the meeting's current status.", 'transition');
  q("UPDATE meetings SET status='cancelled', closed_at=? WHERE id=?", [now(), (int) $id]);
  [$w, $p] = meeting_invited_sql($m);
  $join = $m['audience'] === 'members' ? "u.id IN (SELECT u.id FROM (SELECT u.id FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w) u)" : $w;
  notify_many("u.id<>? AND $join", array_merge([(int) $u['id']], $p), 'meeting', 'Cancelled: ' . mb_substr($m['title'], 0, 120), 'This meeting will not hold. ' . work_name($u) . ' cancelled it.', '#/meetings/' . $id);
  after_response(fn() => push_drain(80));
});
