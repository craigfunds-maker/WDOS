<?php
declare(strict_types=1);
/* v109.25: HQ sees everything volunteer leaders do, as it happens (Azeez, 19 Nov 2026: "the hq should be able to see what every
 * leaders are doing at every point, task, calls, meetings, reports, communication flow, everything ... everything can be clicked
 * and will have its unique functionality").
 * The feed is read from the records themselves (tasks, comments, calls, group calls, meetings, attendance, reports, reviews,
 * messages, help desk, announcements, field activity, chapter posts, sign-ins, and the activity record for the rest), so nothing
 * a leader does is missed because someone forgot to log it. Each item carries what it points to, so HQ can open it.
 * HQ only. The words of private messages between leaders are shown only when HQ has switched that on (rule below), and every
 * reading is recorded. */

const HW_KINDS = ['tasks' => 'Tasks', 'calls' => 'Calls', 'meetings' => 'Meetings', 'reports' => 'Reports', 'messages' => 'Messages', 'field' => 'Field work and chapters', 'access' => 'Sign-ins', 'other' => 'Other'];
const HW_READ_DM = 'hq_read_leader_messages';      // '1' = HQ may read the words of messages between leaders

/** Leaders (serving seats) matching the filters, as a SQL condition on a user id column. */
function hw_leader_sql(string $col): array {
  $w = "EXISTS (SELECT 1 FROM seats s9 JOIN org_units o9 ON o9.id=s9.org_unit_id WHERE s9.user_id=$col AND s9.ends_at IS NULL"; $p = [];
  $cc = strtoupper(substr((string) ($_GET['country'] ?? ''), 0, 2)); $net = (string) ($_GET['network'] ?? '');
  if ($cc !== '' && preg_match('/^[A-Z]{2}$/', $cc)) { $w .= ' AND o9.country_code=?'; $p[] = $cc; }
  if (in_array($net, ['WGMN', 'WNNN'], true)) { $w .= ' AND s9.network=?'; $p[] = $net; }
  $w .= ')';
  if ($id = (int) ($_GET['leader'] ?? 0)) { $w .= " AND $col=?"; $p[] = $id; }
  return [$w, $p];
}

/** Every source: [kind, SQL selecting leader, at, what, detail, with_id, ref_type, ref_id, status]. */
function hw_sources(): array {
  $n = "CONCAT(x.first_name,' ',x.last_name)";
  return [
    ['tasks', "SELECT t.assigned_by leader, t.created_at at, 'Gave a task' what, t.title detail, t.assignee_id with_id, 'task' rt, t.id rid, t.status st FROM tasks t WHERE t.created_at>=? AND {L:t.assigned_by}"],
    ['tasks', "SELECT t.assignee_id, t.completed_at, 'Finished a task', t.title, t.assigned_by, 'task', t.id, t.status FROM tasks t WHERE t.completed_at>=? AND {L:t.assignee_id}"],
    ['tasks', "SELECT t.assignee_id, t.updated_at, CASE t.status WHEN 'submitted' THEN 'Sent a task for approval' WHEN 'in_progress' THEN 'Started a task' WHEN 'cancelled' THEN 'Task cancelled' ELSE 'Updated a task' END, t.title, t.assigned_by, 'task', t.id, t.status FROM tasks t
       WHERE t.updated_at>=? AND t.status IN ('in_progress','submitted') AND {L:t.assignee_id}"],
    ['tasks', "SELECT c.user_id, c.created_at, CASE WHEN c.kind IN ('evidence','file') THEN 'Added evidence to a task' ELSE 'Commented on a task' END, CONCAT(t.title, ': ', LEFT(c.body, 140)), CASE WHEN c.user_id=t.assignee_id THEN t.assigned_by ELSE t.assignee_id END, 'task', t.id, t.status
       FROM task_comments c JOIN tasks t ON t.id=c.task_id WHERE c.created_at>=? AND {L:c.user_id}"],
    ['calls', "SELECT v.caller_id, v.created_at, CASE WHEN v.to_hq=1 THEN 'Rang WODDI HQ' ELSE 'Rang a leader' END, '', v.callee_id, 'call', v.id, v.status FROM voice_calls v WHERE v.created_at>=? AND {L:v.caller_id}"],
    ['calls', "SELECT v.callee_id, COALESCE(v.answered_at, v.created_at), CASE v.status WHEN 'ringing' THEN 'Being rung' WHEN 'answered' THEN 'Answered a call' WHEN 'ended' THEN 'Took a call' WHEN 'declined' THEN 'Declined a call' WHEN 'cancelled' THEN 'Call not answered' ELSE 'Missed a call' END, '', v.caller_id, 'call', v.id, v.status
       FROM voice_calls v WHERE v.callee_id IS NOT NULL AND v.created_at>=? AND {L:v.callee_id}"],
    ['calls', "SELECT g.host_id, g.created_at, 'Started a group call', CONCAT((SELECT COUNT(*) FROM voice_group_people p WHERE p.group_id=g.id), ' people'), NULL, 'gcall', g.id, g.status FROM voice_groups g WHERE g.created_at>=? AND {L:g.host_id}"],
    ['calls', "SELECT p.user_id, COALESCE(p.joined_at, p.invited_at), CASE p.status WHEN 'missed' THEN 'Missed a group call' WHEN 'declined' THEN 'Declined a group call' ELSE 'Joined a group call' END, '', g.host_id, 'gcall', g.id, p.status
       FROM voice_group_people p JOIN voice_groups g ON g.id=p.group_id WHERE p.user_id<>g.host_id AND p.invited_at>=? AND {L:p.user_id}"],
    ['calls', "SELECT a.actor_id, a.created_at, 'Rang a leader on her phone', '', a.subject_id, 'person', a.subject_id, '' FROM activity_log a WHERE a.action='phone_call' AND a.created_at>=? AND {L:a.actor_id}"],
    ['meetings', "SELECT m.host_id, m.created_at, 'Scheduled a meeting', CONCAT(m.title, ' · ', m.scope_label), NULL, 'meeting', m.id, m.status FROM meetings m WHERE m.created_at>=? AND {L:m.host_id}"],
    ['meetings', "SELECT j.user_id, j.joined_at, 'Opened a meeting link', m.title, m.host_id, 'meeting', m.id, m.status FROM meeting_joins j JOIN meetings m ON m.id=j.meeting_id WHERE j.joined_at>=? AND {L:j.user_id}"],
    ['meetings', "SELECT r.user_id, r.answered_at, CASE r.answer WHEN 'yes' THEN 'Will attend a meeting' WHEN 'no' THEN 'Cannot attend a meeting' ELSE 'Replied to a meeting' END, m.title, m.host_id, 'meeting', m.id, r.answer FROM meeting_rsvps r JOIN meetings m ON m.id=r.meeting_id WHERE r.answered_at>=? AND {L:r.user_id}"],
    ['meetings', "SELECT a.user_id, a.marked_at, CASE a.status WHEN 'present' THEN 'Attended a meeting' ELSE 'Absent from a meeting' END, m.title, m.host_id, 'meeting', m.id, a.status FROM meeting_attendance a JOIN meetings m ON m.id=a.meeting_id WHERE a.marked_at>=? AND {L:a.user_id}"],
    ['meetings', "SELECT q.requester_id, q.created_at, 'Asked HQ for a video meeting', CONCAT(q.request_no, ' · ', q.title), NULL, 'meetreq', q.id, q.status FROM meeting_requests q WHERE q.created_at>=? AND {L:q.requester_id}"],
    ['meetings', "SELECT q.minutes_by, q.minutes_at, 'Sent meeting minutes', CONCAT(q.request_no, ' · ', q.title), NULL, 'meetreq', q.id, q.status FROM meeting_requests q WHERE q.minutes_at>=? AND {L:q.minutes_by}"],
    ['reports', "SELECT r.owner_id, r.submitted_at, CASE WHEN r.on_time=0 THEN 'Submitted a report late' ELSE 'Submitted a report' END, CONCAT(r.report_no, ' · ', r.unit_label), NULL, 'report', r.id, r.status FROM report_instances r WHERE r.submitted_at>=? AND {L:r.owner_id}"],
    ['reports', "SELECT v.by_user, v.created_at, CASE v.action WHEN 'approve' THEN 'Approved a report' WHEN 'return' THEN 'Returned a report' ELSE 'Escalated a report' END, CONCAT(r.report_no, IF(v.note<>'', CONCAT(': ', LEFT(v.note, 120)), '')), r.owner_id, 'report', r.id, v.action
       FROM report_reviews v JOIN report_instances r ON r.id=v.report_id WHERE v.created_at>=? AND {L:v.by_user}"],
    ['reports', "SELECT r.owner_id, r.updated_at, 'Worked on a report', CONCAT(r.report_no, ' · ', r.unit_label), NULL, 'report', r.id, r.status FROM report_instances r WHERE r.updated_at>=? AND r.status IN ('draft','returned') AND r.updated_at>r.created_at AND {L:r.owner_id}"],
    ['messages', "SELECT d.sender_id, d.created_at, CASE WHEN COALESCE((SELECT b.recipients FROM dm_batches b WHERE b.id=d.batch_id), 1) > 1 THEN 'Sent a message to several people' ELSE 'Sent a message' END, d.subject, CASE WHEN t.lo_id=d.sender_id THEN t.hi_id ELSE t.lo_id END, 'dm', d.id, ''
       FROM dm_messages d JOIN dm_threads t ON t.id=d.thread_id WHERE d.created_at>=? AND d.deleted_at IS NULL AND {L:d.sender_id}"],
    ['messages', "SELECT m.sender_id, m.created_at, 'Wrote to WODDI HQ', LEFT(th.subject, 140), NULL, 'thread', th.id, th.status FROM thread_messages m JOIN threads th ON th.id=m.thread_id WHERE m.from_hq=0 AND m.created_at>=? AND {L:m.sender_id}"],
    ['messages', "SELECT a.author_id, COALESCE(a.sent_at, a.created_at), 'Sent an announcement', a.title, NULL, 'announcement', a.id, '' FROM announcements a WHERE a.created_at>=? AND {L:a.author_id}"],
    ['field', "SELECT r.user_id, r.created_at, 'Recorded field activity', CONCAT(r.what, IF(r.people_reached>0, CONCAT(' · ', r.people_reached, ' reached'), '')), NULL, 'record', r.id, IF(r.escalate=1,'escalated','') FROM activity_records r WHERE r.created_at>=? AND {L:r.user_id}"],
    ['field', "SELECT p.user_id, p.created_at, 'Posted in a chapter', LEFT(p.body, 140), NULL, 'chapter', p.chapter_id, IF(p.hidden_at IS NULL,'','hidden') FROM chapter_posts p WHERE p.created_at>=? AND {L:p.user_id}"],
    ['access', "SELECT s.user_id, s.created_at, 'Signed in', s.method, NULL, 'signin', s.user_id, '' FROM sessions s WHERE s.created_at>=? AND {L:s.user_id}"],
    ['other', "SELECT a.actor_id, a.created_at, a.action, a.detail, a.subject_id, COALESCE(a.ref_type, 'person'), COALESCE(a.ref_id, a.subject_id), '' FROM activity_log a
       WHERE a.created_at>=? AND a.action NOT IN ('task_created','task_status','task_comment','meeting_scheduled','voice_call','group_call','group_call_add','group_call_join','phone_call','report_submitted','report_reviewed','message_sent','meeting_requested','meeting_minutes','announcement','announcement_scheduled') AND {L:a.actor_id}"],
  ];
}
const HW_ACTIONS = ['registered' => 'Joined WDOS', 'team_viewed' => "Opened a leader's profile", 'agreement_accepted' => 'Accepted the Leadership Agreement', 'agreement_declined' => 'Declined the Leadership Agreement',
  'chapter_created' => 'Created a chapter', 'chapter_edited' => 'Edited a chapter', 'chapter_placement' => 'Placed a member in a chapter', 'member_activated' => 'Activated a member', 'pin_reset_by_leader' => "Reset a member's PIN",
  'message_undone' => 'Took back a message', 'meeting_closed' => 'Closed a meeting', 'meeting_notified' => 'Told people about a video meeting', 'meeting_flier' => 'Added a meeting flier', 'roster_exported' => 'Downloaded a list of members',
  'report_exported' => 'Downloaded a report', 'report_document_added' => 'Added a document to a report', 'case_opened' => 'Opened a case', 'case_updated' => 'Updated a case', 'course_passed' => 'Passed a course', 'cluster_created' => 'Created a community cluster',
  'cluster_edited' => 'Edited a community cluster', 'zone_created' => 'Created a zone', 'calendar_added' => 'Added to the calendar', 'calendar_removed' => 'Removed from the calendar', 'own_data_downloaded' => 'Downloaded her own data'];

route('GET', '/hq/watch', function () {
  require_hq();
  $days = max(1, min(90, (int) ($_GET['days'] ?? 7))); $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
  $after = (string) ($_GET['after'] ?? '');      // only what happened after this moment (live refresh)
  if ($after !== '' && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $after) && $after > $since) $since = $after;
  $kinds = array_values(array_intersect(explode(',', (string) ($_GET['kinds'] ?? '')), array_keys(HW_KINDS))); if (!$kinds) $kinds = array_keys(HW_KINDS);
  $per = $after !== '' ? 100 : 250; $items = [];
  foreach (hw_sources() as [$kind, $sql]) {
    if (!in_array($kind, $kinds, true)) continue;
    $sql = preg_replace_callback('/\{L:([a-z0-9_.]+)\}/', function ($m) use (&$lp) { [$w, $lp] = hw_leader_sql($m[1]); return $w; }, $sql);
    try { $st = db()->prepare($sql . ' ORDER BY 2 DESC LIMIT ' . $per); $st->execute(array_merge([$since], $lp));
      foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) {
      if ($after !== '' && (string) $r[1] <= $after) continue;
      $items[] = ['kind' => $kind, 'leader_id' => (int) $r[0], 'at' => (string) $r[1], 'what' => $kind === 'other' ? (HW_ACTIONS[$r[2]] ?? ucfirst(str_replace('_', ' ', (string) $r[2]))) : (string) $r[2],
        'detail' => (string) ($r[3] ?? ''), 'with_id' => $r[4] !== null ? (int) $r[4] : null, 'ref' => ['type' => (string) $r[5], 'id' => $r[6] !== null ? (int) $r[6] : null], 'status' => (string) ($r[7] ?? '')]; } }
    catch (PDOException $e) { /* a table that arrives with a later migration */ }
  }
  usort($items, fn($a, $b) => strcmp($b['at'], $a['at']) ?: ($b['ref']['id'] ?? 0) <=> ($a['ref']['id'] ?? 0));
  $items = array_slice($items, 0, $after !== '' ? 100 : 400);
  // names and seats, once each
  $ids = array_values(array_unique(array_filter(array_merge(array_column($items, 'leader_id'), array_column($items, 'with_id')))));
  $names = []; if ($ids) foreach (rows('SELECT id, first_name, last_name, has_photo, last_seen_at FROM users WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $r) $names[(int) $r['id']] = $r;
  $seats = [];
  $who = function (?int $id) use (&$names, &$seats) {
    if (!$id || !isset($names[$id])) return null;
    if (!array_key_exists($id, $seats)) { $s = current_seat($id); $seats[$id] = $s ? ['title' => seat_title($s), 'network' => $s['network'], 'country' => $s['unit_country']] : ['title' => is_hq(row('SELECT * FROM users WHERE id=?', [$id]) ?: []) ? 'WODDI HQ' : '', 'network' => '', 'country' => '']; }
    $n = $names[$id]; return ['id' => $id, 'name' => trim($n['first_name'] . ' ' . $n['last_name']), 'first_name' => $n['first_name'], 'last_name' => $n['last_name'], 'has_photo' => (int) $n['has_photo']] + $seats[$id];
  };
  foreach ($items as &$it) { $it['leader'] = $who($it['leader_id']); $it['with'] = $who($it['with_id']); $it['link'] = hw_link($it['ref']); } unset($it);
  $out = ['items' => $items, 'now' => gmdate('Y-m-d H:i:s'), 'kinds' => HW_KINDS, 'read_messages' => setting(HW_READ_DM) === '1'];
  if ($after === '') {
    $counts = array_fill_keys(array_keys(HW_KINDS), 0); foreach ($items as $it) $counts[$it['kind']]++;
    $out['counts'] = $counts;
    $out['countries'] = rows("SELECT DISTINCT o.country_code cc, c.name FROM seats s JOIN org_units o ON o.id=s.org_unit_id LEFT JOIN org_units c ON c.level='country' AND c.country_code=o.country_code WHERE s.ends_at IS NULL AND o.country_code IS NOT NULL ORDER BY c.name");
    if ($lid = (int) ($_GET['leader'] ?? 0)) {
      $u = row('SELECT id, first_name, last_name, has_photo, last_seen_at, phone FROM users WHERE id=?', [$lid]); $s = current_seat($lid);
      if ($u) $out['leader'] = ['id' => $lid, 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'has_photo' => (int) $u['has_photo'],
        'last_seen_at' => $u['last_seen_at'], 'title' => $s ? seat_title($s) : '', 'network' => $s['network'] ?? '', 'country' => $s['unit_country'] ?? '', 'has_seat' => (bool) $s,
        'phone' => trim((string) $u['phone']) !== '' && !str_contains((string) $u['phone'], 'invalid') ? trim((string) $u['phone']) : null];
    }
  }
  return $out;
});

/** Where an item opens. Pages HQ already has are linked; the rest open in the detail panel (GET /hq/watch/item). */
function hw_link(array $ref): ?string {
  $id = (int) ($ref['id'] ?? 0); if (!$id) return null;
  return match ($ref['type']) {
    'task' => '#/tasks/' . $id, 'meeting' => '#/meetings/' . $id, 'meetreq' => '#/meetings/requests/' . $id, 'report' => '#/reporting/r/' . $id,
    'thread' => '#/messages/' . $id, 'announcement' => '#/announcements', 'person' => '#/members/' . $id, 'chapter' => '#/chapters', default => null };
}

/** The detail of an item that has no page of its own: a call, a group call, a message, a field activity record, a sign-in. */
route('GET', '/hq/watch/item/([a-z]+)/(\d+)', function ($type, $id) {
  $me = require_hq(); $id = (int) $id;
  $name = fn($uid) => $uid ? (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [(int) $uid]) : '';
  $title = fn($uid) => ($s = $uid ? current_seat((int) $uid) : null) ? seat_title($s) : '';
  $dur = fn($a, $b) => $a && $b ? max(0, strtotime($b . ' UTC') - strtotime($a . ' UTC')) : null;
  switch ($type) {
    case 'call':
      $c = row('SELECT * FROM voice_calls WHERE id=?', [$id]); if (!$c) fail(404, 'That call was not found.', 'not_found');
      return ['type' => 'call', 'caller' => ['id' => (int) $c['caller_id'], 'name' => $name($c['caller_id']), 'title' => $title($c['caller_id'])],
        'callee' => $c['to_hq'] ? ['id' => $c['answered_by'] ? (int) $c['answered_by'] : null, 'name' => 'WODDI HQ' . ($c['answered_by'] ? ' (' . $name($c['answered_by']) . ')' : ''), 'title' => ''] : ['id' => (int) $c['callee_id'], 'name' => $name($c['callee_id']), 'title' => $title($c['callee_id'])],
        'status' => $c['status'], 'rang_at' => $c['created_at'], 'answered_at' => $c['answered_at'], 'ended_at' => $c['ended_at'], 'seconds' => $dur($c['answered_at'], $c['ended_at']),
        'between' => rows('SELECT id, status, created_at, answered_at, ended_at FROM voice_calls WHERE (caller_id=? AND callee_id=?) OR (caller_id=? AND callee_id=?) ORDER BY id DESC LIMIT 10', [(int) $c['caller_id'], (int) $c['callee_id'], (int) $c['callee_id'], (int) $c['caller_id']]),
        'note' => 'WDOS does not record what was said. Calls go straight between the phones.'];
    case 'gcall':
      $g = row('SELECT * FROM voice_groups WHERE id=?', [$id]); if (!$g) fail(404, 'That call was not found.', 'not_found');
      return ['type' => 'gcall', 'host' => ['id' => (int) $g['host_id'], 'name' => $name($g['host_id']), 'title' => $title($g['host_id'])], 'status' => $g['status'], 'started_at' => $g['created_at'], 'ended_at' => $g['ended_at'], 'seconds' => $dur($g['created_at'], $g['ended_at']),
        'people' => array_map(fn($p) => ['id' => (int) $p['user_id'], 'name' => $name($p['user_id']), 'title' => $title($p['user_id']) ?: 'WODDI HQ', 'status' => $p['status'], 'joined_at' => $p['joined_at'], 'left_at' => $p['left_at'], 'seconds' => $dur($p['joined_at'], $p['left_at'])],
          rows('SELECT * FROM voice_group_people WHERE group_id=? ORDER BY invited_at, user_id', [$id])),
        'note' => 'WDOS does not record what was said. Calls go straight between the phones.'];
    case 'dm':
      $d = row('SELECT d.*, t.lo_id, t.hi_id FROM dm_messages d JOIN dm_threads t ON t.id=d.thread_id WHERE d.id=?', [$id]); if (!$d) fail(404, 'That message was not found.', 'not_found');
      $to = (int) d_other($d); $read = setting(HW_READ_DM) === '1';
      $batch = $d['batch_id'] ? row('SELECT audience, recipients FROM dm_batches WHERE id=?', [(int) $d['batch_id']]) : null;
      if ($read) log_activity((int) $me['id'], (int) $d['sender_id'], 'hq_read_message', 'Message ' . $id);
      $thread = $read ? array_map(fn($m) => ['from' => $name($m['sender_id']), 'at' => $m['created_at'], 'subject' => $m['subject'], 'body' => $m['body']],
        rows('SELECT sender_id, subject, body, created_at FROM dm_messages WHERE thread_id=? AND deleted_at IS NULL AND id<=? ORDER BY id DESC LIMIT 20', [(int) $d['thread_id'], $id])) : [];
      return ['type' => 'dm', 'from' => ['id' => (int) $d['sender_id'], 'name' => $name($d['sender_id']), 'title' => $title($d['sender_id'])], 'to' => ['id' => $to, 'name' => $name($to), 'title' => $title($to)],
        'subject' => $d['subject'], 'at' => $d['created_at'], 'several' => $batch ? ['audience' => $batch['audience'], 'recipients' => (int) $batch['recipients']] : null,
        'thread_count' => (int) val('SELECT COUNT(*) FROM dm_messages WHERE thread_id=? AND deleted_at IS NULL', [(int) $d['thread_id']]),
        'can_read' => $read, 'body' => $read ? $d['body'] : null, 'thread' => $thread];
    case 'record':
      $r = row('SELECT * FROM activity_records WHERE id=?', [$id]); if (!$r) fail(404, 'That record was not found.', 'not_found');
      return ['type' => 'record', 'by' => ['id' => (int) $r['user_id'], 'name' => $name($r['user_id']), 'title' => $title($r['user_id'])], 'record' => array_intersect_key($r, array_flip(['category', 'happened_at', 'what', 'people_reached', 'people_category', 'result', 'follow_up_action', 'follow_up_due', 'follow_up_done', 'evidence_ref', 'escalate', 'created_at']))];
    case 'signin':
      $u = row('SELECT id, first_name, last_name, last_seen_at FROM users WHERE id=?', [$id]); if (!$u) fail(404, 'Not found.', 'not_found');
      return ['type' => 'signin', 'who' => ['id' => $id, 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'title' => $title($id)], 'last_seen_at' => $u['last_seen_at'],
        'sessions' => array_map(fn($s) => ['at' => $s['created_at'], 'method' => $s['method'], 'device' => hw_device((string) $s['user_agent']), 'until' => $s['expires_at']], rows('SELECT created_at, method, user_agent, expires_at FROM sessions WHERE user_id=? ORDER BY created_at DESC LIMIT 15', [$id]))];
  }
  fail(404, 'That item has no detail.', 'not_found');
});
function d_other(array $d): int { return (int) $d['lo_id'] === (int) $d['sender_id'] ? (int) $d['hi_id'] : (int) $d['lo_id']; }
function hw_device(string $ua): string {
  $os = preg_match('/Android/i', $ua) ? 'Android phone' : (preg_match('/iPhone|iPad/i', $ua) ? 'iPhone or iPad' : (preg_match('/Windows/i', $ua) ? 'Windows computer' : (preg_match('/Mac OS/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux computer' : 'Unknown device'))));
  $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : ''))));
  return trim($os . ($br ? ', ' . $br : ''));
}

/** HQ decides whether it may read the words of messages between leaders. Recorded as a policy change. */
route('POST', '/hq/watch/read-messages', function () {
  $me = require_hq(); $on = !empty(body()['on']);
  if (!in_array($me['role'], ['super_admin', 'hq_admin'], true)) fail(403, 'Only WODDI HQ administrators can change this.', 'forbidden');
  set_setting(HW_READ_DM, $on ? '1' : '0');
  log_activity((int) $me['id'], null, 'policy_changed', ($on ? 'HQ may now read' : 'HQ no longer reads') . ' the words of messages between leaders');
  return ['ok' => true, 'on' => $on];
});
