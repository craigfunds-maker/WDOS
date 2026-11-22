<?php
declare(strict_types=1);
/* v109.46: the leadership map routes. Who may see what, and every figure, is worked out in leadmap_fns.php. */

route('GET', '/leadmap', function () {
  $u = require_user(); $v = lm_viewer($u); $n = lm_node_in();
  if ($n['cc'] === '' && $v['role'] !== 'hq') $n = $v['root'];          // a leader or an envoy opens on her own place
  $how = lm_can_open($v, $n); if ($how === '') fail(403, 'That part of the structure is outside your area. You can open your own area and the leadership above it.', 'scope');
  $nets = $v['nets']; if (($nf = strtoupper((string) ($_GET['network'] ?? ''))) && in_array($nf, $nets, true)) $nets = [$nf];
  $vv = array_merge($v, ['nets' => $nets]);
  $d = lm_depth($n);
  $levels = [];
  for ($i = 0; $i <= min($d, 4); $i++) {             // the pickers: at each level, the places she may choose there
    $parent = lm_cut($n, $i);
    if (lm_can_open($v, $parent) === 'up' && $i < lm_depth($v['root'])) { $levels[] = ['level' => LM_LEVELS[$i], 'label' => lm_level_label(LM_LEVELS[$i], $n['cc']), 'locked' => true, 'value' => [0 => $n['cc'], 1 => $n['state'], 2 => $n['lga'], 3 => $n['comm']][$i] ?? '', 'text' => lm_place_name(lm_cut($n, $i + 1)), 'options' => []]; continue; }
    $opts = $i === 4 ? array_map(fn($c) => ['value' => (string) $c['id'], 'label' => $c['name'] . ' (' . $c['network'] . ')'], lm_chapters($parent, $nets))
      : ($i === 0 ? array_map(fn($c) => ['value' => $c['code'], 'label' => $c['name']], lm_countries()) : array_map(fn($x) => ['value' => $x, 'label' => $x], lm_child_names($parent, $nets)));
    $levels[] = ['level' => LM_LEVELS[$i], 'label' => lm_level_label(LM_LEVELS[$i], $n['cc']), 'locked' => false, 'value' => [0 => $n['cc'], 1 => $n['state'], 2 => $n['lga'], 3 => $n['comm'], 4 => $n['chapter'] ? (string) $n['chapter'] : ''][$i], 'options' => $opts];
  }
  $above = []; for ($i = 1; $i < $d; $i++) { $pn = lm_cut($n, $i); $above[] = ['level' => LM_LEVELS[$i - 1], 'label' => lm_level_label(LM_LEVELS[$i - 1], $n['cc']), 'name' => lm_place_name($pn), 'place' => $pn, 'seats' => lm_seats_at($pn, $nets, $vv, false)]; }
  $here = lm_seats_at($n, $nets, $vv, true);
  $kids = $how === 'down' ? lm_children($n, $vv) : array_values(array_filter(lm_children($n, $vv), fn($k) => lm_can_open($v, $k['place']) !== ''));
  // the people shown on this page: their journey and, where she may see it, their figures
  $ids = []; foreach ($here as $s) if (!empty($s['person'])) $ids[] = $s['person']['id'];
  $days = (int) ($_GET['days'] ?? 30); if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
  $jr = lm_journeys($ids); $kp = $v['kpis'] && $how === 'down' ? lm_kpis($ids, $days) : [];
  foreach ($here as &$s) if (!empty($s['person'])) { $s['journey'] = $jr[$s['person']['id']] ?? null; if ($kp) $s['kpis'] = $kp[$s['person']['id']] ?? null; }
  unset($s);
  $sub = array_values(array_filter(lm_seats(), fn($s) => in_array($s['network'], $nets, true) && lm_inside($s['place'], $n) && $s['depth'] > $d));
  $vacHere = count(array_filter($here, fn($s) => !$s['filled'] && empty($s['extra']))); $vacKids = array_sum(array_column($kids, 'vacant'));
  $memW = "status='active' AND network IN ('" . implode("','", $nets) . "')"; $memP = [];
  foreach ([1 => ['country_code', 'cc'], 2 => ['state_region', 'state'], 3 => ['lga', 'lga'], 4 => ['community', 'comm']] as $i => [$c2, $k2]) if ($d >= $i) { $memW .= " AND $c2=?"; $memP[] = $n[$k2]; }
  if ($d === 5) { $memW .= ' AND chapter_id=?'; $memP[] = $n['chapter']; }
  $subIds = array_column($sub, 'user_id');
  $act = $subIds ? rows('SELECT route, COUNT(*) n FROM activations WHERE status IN (\'in_progress\',\'submitted\',\'returned\') AND user_id IN (SELECT r.user_id FROM referrals r WHERE r.referrer_id IN (' . lm_in($subIds) . ')) GROUP BY route') : [];
  return [
    'place' => $n, 'depth' => $d, 'name' => lm_place_name($n), 'level' => $d ? LM_LEVELS[$d - 1] : 'hq', 'level_label' => $d ? lm_level_label(LM_LEVELS[$d - 1], $n['cc']) : 'Headquarters',
    'child_label' => $d < 5 ? ($d === 0 ? 'Country' : lm_level_label(LM_LEVELS[$d], $n['cc'])) : null,
    'view' => $how, 'role' => $v['role'], 'networks' => $v['nets'], 'network' => count($nets) === 1 ? $nets[0] : '', 'root' => $v['root'], 'can_link' => $v['links'] && $how === 'down', 'kpis_on' => (bool) $kp, 'days' => $days,
    // v109.50: a WODDI Envoy holds no seat in the line, so nobody is "above" her; her map is her state's seats only (Azeez)
    'levels' => $levels, 'above' => $v['role'] === 'envoy' ? [] : $above, 'seats' => $here, 'children' => $kids,
    'totals' => ['leaders' => count($sub) + count(array_filter($here, fn($s) => $s['filled'])), 'vacant_here' => $vacHere, 'vacant_next' => $vacKids, 'members' => (int) val("SELECT COUNT(*) FROM users WHERE $memW", $memP),
      'activations' => array_column($act, 'n', 'route'), 'places' => count($kids)],
    'score_rules' => LM_SCORE,
  ];
});
route('POST', '/leadmap/seat-link', function () {
  $u = require_user(); $v = lm_viewer($u); $b = body();
  foreach (['cc', 'state', 'lga', 'comm', 'chapter'] as $k) $_GET[$k] = $b[$k] ?? '';
  $n = lm_node_in(); $net = strtoupper(str_in('network', 8)); $code = str_in('position', 40);
  if (!in_array($net, $v['nets'], true)) fail(403, 'That network is outside your area.', 'scope');
  if (lm_depth($n) === 0 || lm_can_open($v, $n) !== 'down') fail(403, 'You can share links for seats in your own area.', 'scope');
  if ($v['role'] === 'leader') {                   // a leader shares links for seats under her own, never for her own level or above
    $pos = row('SELECT rank_no FROM positions WHERE code=?', [$code]); if (!$pos || (int) $pos['rank_no'] <= $v['rank']) fail(403, 'You can share links for the seats under your own seat.', 'scope');
  }
  foreach (lm_seats() as $s) if ($s['network'] === $net && $s['code'] === $code && lm_same($s['place'], $n)) fail(409, 'This seat is already filled.', 'filled');
  return ['ok' => true, 'link' => lm_seat_link($u, $net, $code, $n)];
});
route('GET', '/leadmap/leader/(\d+)', function ($id) {
  $u = require_user(); $v = lm_viewer($u); $id = (int) $id; $s = lm_seat_of($id) ?? (function () use ($id) { foreach (lm_seats() as $x) if ($x['user_id'] === $id) return $x; return null; })();
  if (!$s) fail(404, 'That person does not hold a leadership seat.', 'no_seat');
  if (!$v['kpis'] || !in_array($s['network'], $v['nets'], true) || !lm_inside($s['place'], $v['root']) || ($v['role'] === 'leader' && $s['rank'] <= $v['rank'] && $id !== (int) $u['id']))
    fail(403, 'You can open the files of the leaders under your seat.', 'scope');
  $days = (int) ($_GET['days'] ?? 30); if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
  $p = row('SELECT id, first_name, last_name, has_photo, member_no, email, phone, network, country_code, state_region, lga, community, created_at, last_seen_at, designation FROM users WHERE id=?', [$id]);
  $from = gmdate('Y-m-d H:i:s', time() - $days * 86400);
  $tl = [];
  foreach (rows('SELECT action, detail, created_at FROM activity_log WHERE (actor_id=? OR subject_id=?) AND created_at>=? ORDER BY id DESC LIMIT 80', [$id, $id, $from]) as $r) $tl[] = ['at' => $r['created_at'], 'kind' => 'log', 'what' => $r['action'], 'detail' => $r['detail']];
  foreach (rows('SELECT title, status, due_on, created_at, completed_at FROM tasks WHERE assignee_id=? AND (created_at>=? OR completed_at>=?) ORDER BY id DESC LIMIT 40', [$id, $from, $from]) as $r) $tl[] = ['at' => $r['completed_at'] ?: $r['created_at'], 'kind' => 'task', 'what' => $r['status'], 'detail' => $r['title'], 'due' => $r['due_on']];
  foreach (rows('SELECT report_no, template, due_on, status, submitted_at, on_time, created_at FROM report_instances WHERE owner_id=? AND (due_on>=? OR submitted_at>=?) ORDER BY due_on DESC LIMIT 30', [$id, substr($from, 0, 10), $from]) as $r) $tl[] = ['at' => $r['submitted_at'] ?: $r['due_on'] . ' 23:59:00', 'kind' => 'report', 'what' => $r['submitted_at'] ? ((int) $r['on_time'] ? 'submitted_on_time' : 'submitted_late') : ($r['due_on'] < work_today() ? 'overdue' : 'due'), 'detail' => $r['report_no'] . ' · ' . $r['template'], 'due' => $r['due_on']];
  foreach (rows('SELECT m.title, m.starts_at, a.status FROM meeting_attendance a JOIN meetings m ON m.id=a.meeting_id WHERE a.user_id=? AND m.starts_at>=? ORDER BY m.starts_at DESC LIMIT 30', [$id, $from]) as $r) $tl[] = ['at' => $r['starts_at'], 'kind' => 'meeting', 'what' => $r['status'] ?: 'invited', 'detail' => $r['title']];
  foreach (rows('SELECT m.title, m.starts_at, j.first_at FROM meeting_request_joins j JOIN meeting_requests m ON m.id=j.request_id WHERE j.user_id=? AND m.starts_at>=? ORDER BY m.starts_at DESC LIMIT 30', [$id, $from]) as $r) $tl[] = ['at' => $r['starts_at'], 'kind' => 'meeting', 'what' => strtotime($r['first_at'] . ' UTC') <= strtotime($r['starts_at'] . ' UTC') + LM_PUNCTUAL_MIN * 60 ? 'joined_on_time' : 'joined_late', 'detail' => $r['title']];
  foreach (rows('SELECT category, what, people_reached, happened_at FROM activity_records WHERE user_id=? AND happened_at>=? ORDER BY happened_at DESC LIMIT 30', [$id, substr($from, 0, 10)]) as $r) $tl[] = ['at' => $r['happened_at'] . ' 12:00:00', 'kind' => 'programme', 'what' => $r['category'], 'detail' => $r['what'] . ($r['people_reached'] ? ' · ' . $r['people_reached'] . ' reached' : '')];
  foreach (rows('SELECT u2.first_name, u2.last_name, r.position_code, r.created_at, (SELECT 1 FROM seats s2 WHERE s2.user_id=r.user_id LIMIT 1) leader FROM referrals r JOIN users u2 ON u2.id=r.user_id WHERE r.referrer_id=? AND r.created_at>=? ORDER BY r.created_at DESC LIMIT 40', [$id, $from]) as $r)
    $tl[] = ['at' => $r['created_at'], 'kind' => 'referral', 'what' => $r['leader'] ? 'leader' : ($r['position_code'] !== '' ? 'candidate' : 'member'), 'detail' => trim($r['first_name'] . ' ' . $r['last_name']) . ($r['position_code'] !== '' ? ' · ' . lm_title($r['position_code'], $s['network'], $s['place']['cc']) : '')];
  usort($tl, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
  $jr = lm_journeys([$id])[$id] ?? null; $k = lm_kpis([$id], $days)[$id];
  $chain = []; for ($i = 1; $i < $s['depth']; $i++) { $pn = lm_cut($s['place'], $i); foreach (lm_seats_at($pn, [$s['network']], $v, false) as $c) if ($c['principal'] || $c['filled']) $chain[] = ['level' => lm_level_label(LM_LEVELS[$i - 1], $s['place']['cc']), 'place' => lm_place_name($pn)] + $c; }
  if (!$v['contact']) unset($p['phone'], $p['email']);
  return ['person' => $p + ['from' => $s['person']['from'], 'country' => country_name((string) $p['country_code'])], 'seat' => ['title' => lm_title($s['position_code'], $s['network'], $s['place']['cc']), 'network' => $s['network'], 'place' => $s['place'],
      'place_name' => lm_place_name(lm_cut($s['place'], $s['depth'])), 'status' => $s['status'], 'acceptance' => $s['acceptance'], 'since' => $s['since'], 'level' => $s['level']],
    'journey' => $jr, 'kpis' => $k, 'days' => $days, 'timeline' => array_slice($tl, 0, 120), 'chain' => $chain, 'score_rules' => LM_SCORE, 'is_hq' => $v['role'] === 'hq'];
});
route('GET', '/leadmap/leaderboard', function () {
  $u = require_user(); $v = lm_viewer($u);
  if (!$v['kpis']) fail(403, 'The leaderboard is for WODDI HQ and volunteer leaders.', 'forbidden');
  $n = lm_node_in(); if ($n['cc'] === '' && $v['role'] !== 'hq') $n = $v['root'];
  if (!lm_inside($n, $v['root'])) fail(403, 'That part of the structure is outside your area.', 'scope');
  $nets = $v['nets']; if (($nf = strtoupper((string) ($_GET['network'] ?? ''))) && in_array($nf, $nets, true)) $nets = [$nf];
  $lvl = (string) ($_GET['level'] ?? ''); $days = (int) ($_GET['days'] ?? 30); if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
  $list = array_values(array_filter(lm_seats(), fn($s) => in_array($s['network'], $nets, true) && lm_inside($s['place'], $n) && ($lvl === '' || $s['level'] === $lvl) && ($v['role'] !== 'leader' || $s['rank'] > $v['rank'] || $s['user_id'] === (int) $u['id'])));
  $seen = []; $list = array_values(array_filter($list, function ($s) use (&$seen) { if (isset($seen[$s['user_id']])) return false; return $seen[$s['user_id']] = true; }));
  $k = lm_kpis(array_column($list, 'user_id'), $days); $jr = lm_journeys(array_column($list, 'user_id'));
  $rows = array_map(fn($s) => ['id' => $s['user_id'], 'first_name' => $s['person']['first_name'], 'last_name' => $s['person']['last_name'], 'has_photo' => $s['person']['has_photo'], 'last_seen_at' => $s['person']['last_seen_at'],
    'title' => lm_title($s['position_code'], $s['network'], $s['place']['cc']), 'network' => $s['network'], 'level' => $s['level'], 'cc' => $s['place']['cc'], 'place' => lm_place_name(lm_cut($s['place'], $s['depth'])),
    'from' => $s['person']['from'], 'came_by' => $jr[$s['user_id']]['referral']['category_label'] ?? '', 'route' => $jr[$s['user_id']]['activation']['route_label'] ?? '', 'kpis' => $k[$s['user_id']]], $list);
  $sort = (string) ($_GET['sort'] ?? 'score'); $keys = ['score', 'active_days', 'leaders_brought', 'members_brought', 'meetings_attended', 'punctuality', 'meetings_held', 'reports_on_time', 'tasks_on_time', 'programmes'];
  if (!in_array($sort, $keys, true)) $sort = 'score';
  usort($rows, fn($a, $b) => [($b['kpis'][$sort] ?? -1), $b['kpis']['score'], $a['last_name']] <=> [($a['kpis'][$sort] ?? -1), $a['kpis']['score'], $b['last_name']]);
  foreach ($rows as $i => &$r) $r['rank'] = $i + 1; unset($r);
  $sum = []; foreach (['active_days', 'leaders_brought', 'members_brought', 'meetings_attended', 'meetings_held', 'reports_on_time', 'reports_overdue', 'tasks_on_time', 'tasks_overdue', 'programmes', 'people_reached', 'joined_timed', 'punctual'] as $f) $sum[$f] = array_sum(array_map(fn($r) => $r['kpis'][$f], $rows));
  $sum['punctuality'] = $sum['joined_timed'] ? (int) round(100 * $sum['punctual'] / $sum['joined_timed']) : null; $sum['leaders'] = count($rows);
  return ['rows' => array_slice($rows, 0, 500), 'total' => count($rows), 'sum' => $sum, 'days' => $days, 'sort' => $sort, 'place' => $n, 'name' => lm_place_name($n), 'networks' => $v['nets'], 'score_rules' => LM_SCORE, 'punctual_min' => LM_PUNCTUAL_MIN];
});
