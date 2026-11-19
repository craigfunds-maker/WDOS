<?php
declare(strict_types=1);
/* v109.24: a leader's team (Azeez, 19 Nov 2026: "as a country lead she should be able to tap and click on the leaders below her,
 * see last seen, message them, assign task to them, and even ring them on phone, see their KPIs, access their report, invite to
 * meeting; also they can have group voice call").
 * Who counts as "below her" is the rule tasks and messages already use (work_people_sql): a serving leader in the same network,
 * in her part of the structure, with a lower rank. HQ sees every serving leader. Everything is checked here, on the server;
 * a leader outside that rule gets 403 even if she types the address. */

/** The leader $id if the signed-in person may open her profile, otherwise 403. */
function team_target(array $u, int $id): array {
  if ($id === (int) $u['id']) fail(422, 'This is you. Your own figures are on your dashboard.', 'self');
  $seat = current_seat($id);
  $p = $seat ? row("SELECT id, first_name, last_name, has_photo, member_no, phone, network, country_code, last_seen_at, status FROM users WHERE id=? AND status='active'", [$id]) : null;
  if (!$p) fail(404, 'That leader was not found, or no longer holds a seat.', 'not_found');
  if (!is_hq($u)) {
    [$w, $wp] = work_people_sql($u);
    if (!val("SELECT 1 FROM users u WHERE u.id=? AND $w", array_merge([$id], $wp))) fail(403, 'You can open the leaders who serve under your seat.', 'forbidden');
  }
  return [$p, $seat];
}

/** Leaders under me, all of them (the dashboard shows the first 12). */
route('GET', '/team', function () {
  $u = require_user();
  if (!is_hq($u) && !work_seat($u)) fail(403, 'Your team opens when WODDI HQ records your leadership seat.', 'no_seat');
  [$w, $p] = work_people_sql($u);
  if (is_hq($u)) $w = 'EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)';
  if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') { $w .= " AND CONCAT(u.first_name,' ',u.last_name) LIKE ?"; $p = is_hq($u) ? ["%$q%"] : array_merge($p, ["%$q%"]); } elseif (is_hq($u)) $p = [];
  $list = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.last_seen_at, (u.phone<>'' AND u.phone IS NOT NULL) has_phone,
      s.status seat_status, s.area_label, o.name unit, pp.rank_no, CASE WHEN s.network='WNNN' THEN pp.title_wnnn ELSE pp.title_wgmn END title
    FROM users u JOIN seats s ON s.user_id=u.id AND s.ends_at IS NULL JOIN positions pp ON pp.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id
    WHERE u.status='active' AND u.id<>? AND $w ORDER BY pp.rank_no, o.name, u.first_name LIMIT 300", array_merge([(int) $u['id']], $p));
  $seen = []; $out = [];
  foreach ($list as $r) { if (isset($seen[$r['id']])) continue; $seen[$r['id']] = 1;
    $out[] = ['id' => (int) $r['id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'has_photo' => (int) $r['has_photo'], 'last_seen_at' => $r['last_seen_at'],
      'title' => $r['title'], 'area' => $r['area_label'] !== '' ? $r['area_label'] : $r['unit'], 'status' => $r['seat_status'], 'has_phone' => (bool) $r['has_phone']]; }
  return ['people' => $out, 'can_call' => vc_can_call($u), 'max_group' => GC_MAX];
});

/** One leader under me: who she is, when she was last on WDOS, her figures, her tasks and reports, and what I can do. */
route('GET', '/team/(\d+)', function ($id) {
  $u = require_user(); [$p, $seat] = team_target($u, (int) $id); $id = (int) $id;
  // for HQ's record of what leaders do: opening a leader's profile, once a day per leader
  if (!is_hq($u) && !val("SELECT 1 FROM activity_log WHERE actor_id=? AND subject_id=? AND action='team_viewed' AND created_at>=?", [(int) $u['id'], $id, gmdate('Y-m-d 00:00:00')]))
    log_activity((int) $u['id'], $id, 'team_viewed', 'Opened the profile of ' . trim($p['first_name'] . ' ' . $p['last_name']), 'person', $id);
  [$w, $wp] = leader_scope($seat);
  $since30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400); $since7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400); $since90 = gmdate('Y-m-d H:i:s', time() - 90 * 86400);
  $m = row("SELECT COUNT(*) total, COALESCE(SUM(u.created_at>=?),0) new30, COALESCE(SUM(u.last_seen_at>=?),0) active7 FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND $w", array_merge([$since30, $since7], $wp));
  $leaders = (int) val("SELECT COUNT(DISTINCT s.user_id) FROM seats s JOIN positions pp ON pp.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id JOIN users x ON x.id=s.user_id
                        WHERE s.ends_at IS NULL AND x.status='active' AND s.network=? AND o.path LIKE ? AND pp.rank_no>? AND s.user_id<>?", [$seat['network'], $seat['unit_path'] . '%', (int) $seat['rank_no'], $id]);
  $today = work_today();
  $t = row("SELECT COALESCE(SUM(status IN " . TASK_OPEN . "),0) open, COALESCE(SUM(status IN " . TASK_OPEN . " AND due_on IS NOT NULL AND due_on<?),0) overdue,
                   COALESCE(SUM(status='completed' AND completed_at>=?),0) done90, COALESCE(SUM(status='completed' AND completed_at>=? AND (due_on IS NULL OR DATE(completed_at)<=due_on)),0) ontime90
            FROM tasks WHERE assignee_id=?", [$today, $since90, $since90, $id]);
  $att = row("SELECT COALESCE(SUM(ma.status='present'),0) present, COUNT(*) marked FROM meeting_attendance ma JOIN meetings mt ON mt.id=ma.meeting_id WHERE ma.user_id=? AND mt.starts_at>=?", [$id, $since90]);
  $reports = [];
  try {
    foreach (rows("SELECT r.id, r.report_no, r.template, r.period_start, r.status, r.submitted_at, r.on_time, rt.title FROM report_instances r LEFT JOIN report_templates rt ON rt.code=r.template
                   WHERE r.owner_id=? AND r.status<>'superseded' ORDER BY r.period_start DESC, r.id DESC LIMIT 6", [$id]) as $r)
      $reports[] = ['id' => (int) $r['id'], 'no' => $r['report_no'], 'title' => $r['title'] ?: $r['template'], 'period_start' => $r['period_start'], 'status' => $r['status'], 'submitted_at' => $r['submitted_at'], 'late' => $r['on_time'] !== null && (int) $r['on_time'] === 0];
  } catch (PDOException $e) { /* reporting tables arrive with their migration */ }
  $tasks = array_map(fn($x) => ['id' => (int) $x['id'], 'title' => $x['title'], 'status' => $x['status'], 'due_on' => $x['due_on'], 'overdue' => in_array($x['status'], ['open', 'in_progress', 'submitted'], true) && $x['due_on'] && $x['due_on'] < $today],
    rows('SELECT id, title, status, due_on FROM tasks WHERE assignee_id=? ORDER BY (status IN ' . TASK_OPEN . ') DESC, id DESC LIMIT 6', [$id]));
  $phone = trim((string) $p['phone']);
  return [
    'person' => ['id' => $id, 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'has_photo' => (int) $p['has_photo'], 'member_no' => $p['member_no'], 'network' => $seat['network'],
      'title' => seat_title($seat), 'since' => $seat['starts_at'], 'seat_status' => $seat['status'], 'accepted' => ($seat['acceptance'] ?? '') !== 'pending', 'last_seen_at' => $p['last_seen_at'],
      'phone' => $phone !== '' && !str_contains($phone, 'invalid') ? $phone : null],
    'kpis' => ['members' => (int) $m['total'], 'new30' => (int) $m['new30'], 'active7' => (int) $m['active7'], 'leaders' => $leaders,
      'tasks_open' => (int) $t['open'], 'tasks_overdue' => (int) $t['overdue'], 'tasks_done90' => (int) $t['done90'], 'tasks_ontime90' => (int) $t['ontime90'],
      'meetings_present90' => (int) $att['present'], 'meetings_marked90' => (int) $att['marked']],
    'reports' => $reports, 'tasks' => $tasks,
    'can' => ['message' => true, 'task' => work_may_assign($u, $id), 'meet' => (bool) meeting_audiences($u), 'call' => vc_can_call($u) && (bool) vc_people($u, '', $id), 'dashboard' => is_hq($u)],
  ];
});

/** She tapped "Call her phone": the call itself goes through the phone network, so WDOS records the tap. */
route('POST', '/team/(\d+)/phoned', function ($id) {
  $u = require_user(); [$p] = team_target($u, (int) $id);
  log_activity((int) $u['id'], (int) $id, 'phone_call', 'Rang ' . trim($p['first_name'] . ' ' . $p['last_name']) . ' on her phone', 'person', (int) $id);
  return ['ok' => true];
});

/* ---- HQ: what leaders do (Azeez, 19 Nov 2026: "it should also be captured in admin dashboard in their activities") ---- */
const LEADER_ACTIONS = ['task_created' => 'Assigned a task', 'message_sent' => 'Sent a message', 'voice_call' => 'Voice call', 'phone_call' => 'Phone call',
  'group_call' => 'Started a group call', 'group_call_add' => 'Added people to a group call', 'group_call_join' => 'Joined a group call',
  'meeting_scheduled' => 'Scheduled a meeting', 'meeting_requested' => 'Asked for a meeting', 'team_viewed' => 'Opened a leader\'s profile',
  'report_reviewed' => 'Reviewed a report', 'report_submitted' => 'Submitted a report'];
route('GET', '/hq/leader-activity', function () {
  require_hq();
  $days = max(1, min(90, (int) ($_GET['days'] ?? 30))); $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
  $w = 'a.created_at>=? AND a.action IN (' . implode(',', array_fill(0, count(LEADER_ACTIONS), '?')) . ')
        AND EXISTS (SELECT 1 FROM seats s WHERE s.user_id=a.actor_id AND s.ends_at IS NULL)'; $p = array_merge([$since], array_keys(LEADER_ACTIONS));
  $cc = strtoupper(substr((string) ($_GET['country'] ?? ''), 0, 2)); $net = (string) ($_GET['network'] ?? ''); $who = (int) ($_GET['leader'] ?? 0);
  if ($cc !== '') { $w .= ' AND EXISTS (SELECT 1 FROM seats s JOIN org_units o ON o.id=s.org_unit_id WHERE s.user_id=a.actor_id AND s.ends_at IS NULL AND o.country_code=?)'; $p[] = $cc; }
  if (in_array($net, ['WGMN', 'WNNN'], true)) { $w .= ' AND EXISTS (SELECT 1 FROM seats s WHERE s.user_id=a.actor_id AND s.ends_at IS NULL AND s.network=?)'; $p[] = $net; }
  if ($who) { $w .= ' AND a.actor_id=?'; $p[] = $who; }
  $titles = [];
  $t = function (int $id) use (&$titles) { if (!array_key_exists($id, $titles)) { $s = current_seat($id); $titles[$id] = $s ? [seat_title($s), $s['network'], $s['unit_country']] : ['', '', '']; } return $titles[$id]; };
  $items = [];
  foreach (rows("SELECT a.actor_id, a.subject_id, a.action, a.detail, a.created_at, CONCAT(u.first_name,' ',u.last_name) actor, CONCAT(x.first_name,' ',x.last_name) subject
                 FROM activity_log a JOIN users u ON u.id=a.actor_id LEFT JOIN users x ON x.id=a.subject_id WHERE $w ORDER BY a.id DESC LIMIT 200", $p) as $r) {
    [$title, $network, $country] = $t((int) $r['actor_id']);
    $items[] = ['leader_id' => (int) $r['actor_id'], 'leader' => $r['actor'], 'title' => $title, 'network' => $network, 'country' => $country, 'action' => $r['action'],
      'what' => LEADER_ACTIONS[$r['action']] ?? $r['action'], 'detail' => $r['detail'], 'with' => $r['subject'], 'at' => $r['created_at']];
  }
  $byLeader = [];
  foreach (rows("SELECT a.actor_id, CONCAT(u.first_name,' ',u.last_name) actor, a.action, COUNT(*) n, MAX(a.created_at) last FROM activity_log a JOIN users u ON u.id=a.actor_id WHERE $w GROUP BY a.actor_id, actor, a.action", $p) as $r) {
    $id = (int) $r['actor_id'];
    if (!isset($byLeader[$id])) { [$title, $network, $country] = $t($id); $byLeader[$id] = ['leader_id' => $id, 'leader' => $r['actor'], 'title' => $title, 'network' => $network, 'country' => $country, 'total' => 0, 'last' => $r['last'], 'counts' => []]; }
    $byLeader[$id]['counts'][$r['action']] = (int) $r['n']; $byLeader[$id]['total'] += (int) $r['n'];
    if ($r['last'] > $byLeader[$id]['last']) $byLeader[$id]['last'] = $r['last'];
  }
  usort($byLeader, fn($a, $b) => $b['total'] <=> $a['total']);
  $countries = rows("SELECT DISTINCT o.country_code cc, c.name FROM seats s JOIN org_units o ON o.id=s.org_unit_id LEFT JOIN org_units c ON c.level='country' AND c.country_code=o.country_code WHERE s.ends_at IS NULL AND o.country_code IS NOT NULL ORDER BY c.name");
  return ['days' => $days, 'items' => $items, 'leaders' => array_slice($byLeader, 0, 100), 'labels' => LEADER_ACTIONS, 'countries' => $countries];
});
