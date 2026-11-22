<?php
declare(strict_types=1);
/* v109.46: the leadership map: shared functions, loaded before every route (WDOS answers a route as soon as it is declared,
 * so the leader dashboard, the envoy dashboard and the scheduled jobs need these before their own files load).
 * The routes are in leadmap.php.
 *
 * The leadership map (Azeez, 22 Nov 2026).
 * "In the HQ dashboard we should be able to select country, state, LGA, community cluster (ward), chapter and the equivalent in other
 * countries, to see the countries at a glance, the leaders, where the leaders come from, their activities ... where a seat is vacant it
 * should show vacant ... a unique link for that particular seat ... the envoy of that state should see the structure in her state ...
 * leaders see the leaders under them, and the leadership above them ... KPIs and a leaderboard of volunteer leaders."
 *
 * Sources: WODDI Global Network Leadership Structures (WDOS levels 0 to 6) and the Official Communication Flow (Chapter to Community Cluster
 * to LGA/District to State/Regional to Country to HQ; the State Envoy supports both state teams and is not their supervisor).
 *
 * Every place is a path: country, state or region, LGA or district, ward or community cluster, chapter. A seat is expected at every place in
 * view (principal and assistant, per network). It is filled when a serving leader's seat sits there, and vacant otherwise. Nothing is stored
 * for a vacancy; it is worked out from the seats and the official place lists each time, so the map can never disagree with the seats.
 * Who may see what is decided here, on the server, for every request. */

/* The 81 countries WODDI's structure was drawn up for (moved here from structure.php in v109.46, so the map can use it before the routes load). */
const STRUCTURE_COUNTRIES = 'AE AO AT AU BE BF BI BJ BR BW CA CD CF CG CH CI CM CN CV DE DJ DZ EG ER ES ET FR GA GB GH GM GN GQ GW IE IN IT JM KE KM LR LS LY MA MG ML MR MU MW MY MZ NA NE NG NL NO NZ PT QA RW SA SC SD SE SL SN SO SS ST SZ TD TG TN TR TT TZ UG US ZA ZM ZW';
const LM_LEVELS = ['country', 'state_region', 'lga', 'community_cluster', 'chapter'];
const LM_SEATS = ['country' => ['country_rep', 'deputy_country_rep'], 'state_region' => ['state_coordinator', 'assistant_state_coordinator'],
  'lga' => ['district_coordinator', 'assistant_district_coordinator'], 'community_cluster' => ['cluster_coordinator', 'assistant_cluster_coordinator'], 'chapter' => ['chapter_lead']];
const LM_ALIAS = ['city_coordinator' => 'district_coordinator', 'assistant_city_coordinator' => 'assistant_district_coordinator'];
/* Titles by the Leadership Structures document: [Nigeria WGMN, other countries WGMN, Nigeria WNNN, other countries WNNN]. Country titles stay
   with the positions table, which follows the System rules choice for decision D01. */
const LM_TITLES = [
  'state_coordinator' => ['State Coordinator', 'Regional Coordinator', 'State Lead', 'Regional Lead'],
  'assistant_state_coordinator' => ['Assistant State Coordinator', 'Assistant Regional Coordinator', 'Assistant State Lead', 'Assistant Regional Lead'],
  'district_coordinator' => ['LGA Coordinator', 'District Coordinator', 'LGA Lead', 'District Lead'],
  'assistant_district_coordinator' => ['Assistant LGA Coordinator', 'Assistant District Coordinator', 'Assistant LGA Lead', 'Assistant District Lead'],
  'cluster_coordinator' => ['Ward/Community Cluster Coordinator', 'Community Cluster Coordinator', 'Ward/Community Cluster Lead', 'Community Cluster Lead'],
  'assistant_cluster_coordinator' => ['Assistant Ward/Community Cluster Coordinator', 'Assistant Community Cluster Coordinator', 'Assistant Ward/Community Cluster Lead', 'Assistant Community Cluster Lead'],
  'chapter_lead' => ['Chapter Coordinator', 'Chapter Coordinator', 'Chapter Lead', 'Chapter Lead'],
  'assistant_chapter_lead' => ['Assistant Chapter Coordinator', 'Assistant Chapter Coordinator', 'Assistant Chapter Lead', 'Assistant Chapter Lead'],
];
/* Leaderboard score. Shown to everyone who sees the board, so the ranking can be checked by hand. */
const LM_SCORE = ['leaders_brought' => 10, 'members_brought' => 2, 'meetings_attended' => 3, 'punctual' => 2, 'meetings_held' => 5, 'reports_on_time' => 5, 'reports_late' => 2,
  'tasks_on_time' => 3, 'tasks_late' => 1, 'programmes' => 4, 'reports_overdue' => -3, 'tasks_overdue' => -1];
const LM_PUNCTUAL_MIN = 10;          // joined within ten minutes of the start counts as on time

/** A place name for matching: case, spaces, hyphens, dots and apostrophes ignored ("Ibiono Ibom" is the official "Ibiono-Ibom"). */
function lm_k(?string $s): string { return (string) preg_replace("/[\\s\\-\\.'’,]+/u", '', mb_strtolower(trim((string) $s))); }
function lm_slug(string $s): string { return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-'); }
function lm_title(string $code, string $net, string $cc): string {
  $code = LM_ALIAS[$code] ?? $code;
  if (isset(LM_TITLES[$code])) return LM_TITLES[$code][($net === 'WNNN' ? 2 : 0) + ($cc === 'NG' ? 0 : 1)];
  if ($net === 'WNNN' && in_array($code, ['country_rep', 'deputy_country_rep'], true) && function_exists('policy')) {      // decision D01, as set under System rules
    $t = trim(policy('wnnn_country_title')); if ($t !== '') return ($code === 'deputy_country_rep' ? 'Deputy ' : '') . $t;
  }
  return position_title($code, $net);
}
function lm_level_label(string $level, string $cc): string {
  $ng = $cc === 'NG' || $cc === '';
  return ['country' => 'Country', 'state_region' => $ng ? 'State' : 'Region', 'lga' => $ng ? 'LGA' : 'District', 'community_cluster' => $ng ? 'Ward / Community Cluster' : 'Community Cluster', 'chapter' => 'Chapter'][$level] ?? $level;
}

/* ---- places ------------------------------------------------------------------------------------------------ */
/** A place from the request: [cc, state, lga, comm, chapter]. Empty parts end the path. */
function lm_node_in(): array {
  $g = fn($k, $n) => mb_substr(trim((string) ($_GET[$k] ?? '')), 0, $n);
  $n = ['cc' => strtoupper($g('cc', 2)), 'state' => $g('state', 120), 'lga' => $g('lga', 120), 'comm' => $g('comm', 120), 'chapter' => (int) ($_GET['chapter'] ?? 0)];
  if ($n['cc'] === '') return ['cc' => '', 'state' => '', 'lga' => '', 'comm' => '', 'chapter' => 0];
  if ($n['state'] === '') { $n['lga'] = $n['comm'] = ''; $n['chapter'] = 0; }
  if ($n['lga'] === '') { $n['comm'] = ''; $n['chapter'] = 0; }
  if ($n['comm'] === '') $n['chapter'] = 0;
  return $n;
}
function lm_depth(array $n): int { return $n['cc'] === '' ? 0 : ($n['state'] === '' ? 1 : ($n['lga'] === '' ? 2 : ($n['comm'] === '' ? 3 : ($n['chapter'] ? 5 : 4)))); }
function lm_cut(array $n, int $d): array {
  $o = ['cc' => '', 'state' => '', 'lga' => '', 'comm' => '', 'chapter' => 0]; $keys = ['cc', 'state', 'lga', 'comm', 'chapter'];
  for ($i = 0; $i < $d; $i++) $o[$keys[$i]] = $n[$keys[$i]];
  return $o;
}
/** True when place $a lies inside place $b (or is $b). */
function lm_inside(array $a, array $b): bool {
  $d = lm_depth($b); if (lm_depth($a) < $d) return false;
  foreach (['cc', 'state', 'lga', 'comm'] as $i => $k) { if ($i >= $d) break; if (lm_k((string) $a[$k]) !== lm_k((string) $b[$k])) return false; }
  return $d < 5 || (int) $a['chapter'] === (int) $b['chapter'];
}
function lm_same(array $a, array $b): bool { return lm_depth($a) === lm_depth($b) && lm_inside($a, $b); }

/** The official names under a place, merged with names that already have a seat or a chapter there. */
function lm_child_names(array $n, array $nets): array {
  static $ngLgas = null; $d = lm_depth($n); $names = []; $add = function ($v) use (&$names) { $v = trim((string) $v); if ($v !== '' && !isset($names[lm_k($v)])) $names[lm_k($v)] = $v; };
  $root = dirname(__DIR__) . '/assets/geo/';
  if ($d === 1) {
    if ($n['cc'] === 'NG') foreach (rows("SELECT ou.name FROM org_units ou JOIN org_units c ON c.id=ou.parent_id WHERE ou.level='state_region' AND c.country_code='NG' ORDER BY ou.name") as $r) $add($r['name']);
    else {
      static $states = null; $states = $states ?? (json_decode((string) @file_get_contents($root . 'states.json'), true) ?: []);
      foreach ($states[$n['cc']] ?? [] as $s) $add($s);
      if (!$names && is_file($root . 'world/' . $n['cc'] . '.json')) foreach ((json_decode((string) file_get_contents($root . 'world/' . $n['cc'] . '.json'), true)['s'] ?? []) as $s) $add($s[0] ?? '');
      foreach (rows("SELECT DISTINCT state_region v FROM users WHERE country_code=? AND state_region<>'' AND status='active'", [$n['cc']]) as $r) $add($r['v']);   // a region a member gave, where no official list exists
    }
    foreach (rows("SELECT DISTINCT ou.name v FROM org_units ou WHERE ou.level='state_region' AND ou.country_code=?", [$n['cc']]) as $r) $add($r['v']);
  } elseif ($d === 2) {
    if ($n['cc'] === 'NG') { $ngLgas = $ngLgas ?? (json_decode((string) @file_get_contents($root . 'ng-lgas.json'), true) ?: []); foreach ($ngLgas as $st => $l) if (lm_k($st) === lm_k($n['state'])) foreach ($l as $x) $add($x); }
    else foreach (rows("SELECT DISTINCT lga v FROM users WHERE country_code=? AND state_region=? AND lga<>'' AND status='active'", [$n['cc'], $n['state']]) as $r) $add($r['v']);
  } elseif ($d === 3) {
    if ($n['cc'] === 'NG') { $f = $root . 'ng/' . lm_slug($n['state']) . '/' . lm_slug($n['lga']) . '.json'; if (is_file($f)) foreach (array_keys(json_decode((string) file_get_contents($f), true) ?: []) as $x) $add($x); }
    else foreach (rows("SELECT DISTINCT community v FROM users WHERE country_code=? AND state_region=? AND lga=? AND community<>'' AND status='active'", [$n['cc'], $n['state'], $n['lga']]) as $r) $add($r['v']);
  }
  // places that already have a leader or a chapter always show, even when the official list spells them differently
  $col = [1 => 'state', 2 => 'lga', 3 => 'comm'][$d] ?? null;
  if ($col) foreach (lm_seats() as $s) if ($s['depth'] > $d && lm_inside($s['place'], $n) && in_array($s['network'], $nets, true)) $add($s['place'][$col]);
  if ($d >= 1 && $d <= 3) {
    $cc = [1 => 'state_region', 2 => 'lga', 3 => 'community'][$d]; $w = 'country_code=?'; $p = [$n['cc']];
    if ($d >= 2) { $w .= ' AND state_region=?'; $p[] = $n['state']; } if ($d >= 3) { $w .= ' AND lga=?'; $p[] = $n['lga']; }
    foreach (rows("SELECT DISTINCT $cc v FROM chapters WHERE $w AND status<>'closed'", $p) as $r) $add($r['v']);
  }
  $out = array_values($names); natcasesort($out); return array_values($out);
}
/** The chapters in a ward or community cluster. */
function lm_chapters(array $n, array $nets): array {
  return rows("SELECT id, name, code, network, status, members FROM chapters WHERE country_code=? AND state_region=? AND lga=? AND community=? AND network IN ('" . implode("','", $nets) . "') AND status<>'closed' ORDER BY network, seq, name",
    [$n['cc'], $n['state'], $n['lga'], $n['comm']]);
}
/** The countries WODDI is in: the structure list, and any country with a member or a leader. */
function lm_countries(): array {
  $active = array_flip(explode(' ', STRUCTURE_COUNTRIES));
  foreach (rows("SELECT DISTINCT country_code c FROM users WHERE status='active' AND country_code<>'' AND country_code IS NOT NULL") as $r) $active[$r['c']] = true;
  foreach (lm_seats() as $s) $active[$s['place']['cc']] = true;
  return array_values(array_filter(rows("SELECT country_code code, name FROM org_units WHERE level='country' ORDER BY name"), fn($c) => isset($active[$c['code']])));
}

/* ---- seats -------------------------------------------------------------------------------------------------- */
/** Every serving seat, with its whole place worked out once per request. */
function lm_seats(bool $fresh = false): array {
  static $c = null; if ($c !== null && !$fresh) return $c; $c = [];
  foreach (rows("SELECT s.id, s.user_id, s.position_code, s.network, s.area_label, s.status, s.acceptance, s.starts_at, s.chapter_id, s.he_for_she, s.lga s_lga, s.community s_comm, s.org_unit_id,
        p.level, p.rank_no, ou.level ou_level, ou.name ou_name, ou.country_code, u.first_name, u.last_name, u.has_photo, u.last_seen_at, u.phone, u.email, u.country_code u_cc, u.state_region u_state, u.lga u_lga,
        u.community u_comm, c.state_region c_state, c.lga c_lga, c.community c_comm, c.name c_name
      FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units ou ON ou.id=s.org_unit_id JOIN users u ON u.id=s.user_id LEFT JOIN chapters c ON c.id=s.chapter_id
      WHERE s.ends_at IS NULL AND u.status='active' ORDER BY p.rank_no, s.id") as $r) {
    $lv = $r['level']; $depth = array_search($lv, LM_LEVELS, true); if ($depth === false) continue; $depth++;
    $cc = (string) $r['country_code'];
    $state = $r['ou_level'] === 'state_region' ? $r['ou_name'] : ($lv === 'state_region' ? $r['area_label'] : '');
    if ($state === '' && $depth > 2) $state = (string) ($r['c_state'] ?: $r['u_state']);
    $lga = $depth >= 3 ? (string) ($r['s_lga'] !== '' ? $r['s_lga'] : ($lv === 'lga' ? $r['area_label'] : ($r['c_lga'] ?: $r['u_lga']))) : '';
    $comm = $depth >= 4 ? (string) ($r['s_comm'] !== '' ? $r['s_comm'] : ($lv === 'community_cluster' ? $r['area_label'] : ($r['c_comm'] ?: $r['u_comm']))) : '';
    $place = ['cc' => $cc, 'state' => $depth >= 2 ? $state : '', 'lga' => $lga, 'comm' => $comm, 'chapter' => $depth === 5 ? (int) $r['chapter_id'] : 0];
    $c[] = ['id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'code' => LM_ALIAS[$r['position_code']] ?? $r['position_code'], 'position_code' => $r['position_code'], 'network' => $r['network'],
      'level' => $lv, 'depth' => $depth, 'rank' => (int) $r['rank_no'], 'place' => $place, 'status' => $r['status'], 'acceptance' => $r['acceptance'], 'since' => $r['starts_at'], 'he_for_she' => (int) $r['he_for_she'] === 1,
      'chapter_name' => (string) ($r['c_name'] ?? ''), 'area' => $r['area_label'],
      'person' => ['id' => (int) $r['user_id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'has_photo' => (int) $r['has_photo'], 'last_seen_at' => $r['last_seen_at'], 'phone' => (string) $r['phone'],
        'from' => trim(implode(', ', array_filter([$r['u_comm'], $r['u_lga'], $r['u_state']])) . ', ' . country_name((string) $r['u_cc']), ', ')]];
  }
  return $c;
}
/** The seat this person serves in, with its place, or null. */
function lm_seat_of(int $uid): ?array { foreach (lm_seats() as $s) if ($s['user_id'] === $uid && !in_array($s['status'], ['on_leave', 'suspended'], true)) return $s; return null; }

/* ---- who may see what ---------------------------------------------------------------------------------------- */
/** ['root' => the place she sees everything under, 'nets' => networks, 'role' => hq|leader|envoy, 'up' => her own place (she also sees the
 *  leadership above it), 'kpis' => may see activity figures, 'contact' => may see phone numbers, 'links' => may make seat links]. */
function lm_viewer(array $u): array {
  if (is_hq($u)) { if (!hq_can($u, 'structure') && !hq_can($u, 'members')) fail(403, 'Your HQ login does not include the leadership structure.', 'feature');
    return ['role' => 'hq', 'root' => ['cc' => '', 'state' => '', 'lga' => '', 'comm' => '', 'chapter' => 0], 'nets' => ['WGMN', 'WNNN'], 'up' => null, 'kpis' => true, 'contact' => true, 'links' => true, 'rank' => 0]; }
  if (($u['role'] ?? '') === 'envoy') {
    $e = row('SELECT country_code, state_region FROM envoys WHERE user_id=?', [(int) $u['id']]);
    $cc = (string) ($e['country_code'] ?? $u['country_code'] ?? ''); $st = (string) ($e['state_region'] ?? '') ?: (string) ($u['state_region'] ?? '');
    if ($cc === '' || $st === '') fail(403, 'Your state is not recorded yet. Ask WODDI HQ to record the state you serve as an Envoy.', 'no_state');
    $root = ['cc' => $cc, 'state' => $st, 'lga' => '', 'comm' => '', 'chapter' => 0];
    // the State Envoy supports both state teams; she is not their supervisor, so she sees seats and shares links, not their activity
    return ['role' => 'envoy', 'root' => $root, 'nets' => ['WGMN', 'WNNN'], 'up' => $root, 'kpis' => false, 'contact' => false, 'links' => true, 'rank' => 2];
  }
  $s = lm_seat_of((int) $u['id']);
  if (!$s) fail(403, 'The leadership map opens for volunteer leaders, State Envoys and WODDI HQ.', 'forbidden');
  $root = lm_cut($s['place'], $s['depth']);
  return ['role' => 'leader', 'root' => $root, 'nets' => [$s['network']], 'up' => $root, 'kpis' => true, 'contact' => true, 'links' => true, 'rank' => $s['rank'], 'seat' => $s];
}
/** May this viewer open this place? Down: anything under her root. Up: the places on the way from her root to HQ (their leaders only). */
function lm_can_open(array $v, array $n): string {
  if (lm_inside($n, $v['root'])) return 'down';
  if ($v['up'] && lm_inside($v['up'], $n)) return 'up';
  return '';
}

/* ---- the journey a leader came by, and her figures ------------------------------------------------------------- */
function lm_in(array $ids): string { $ids = array_values(array_unique(array_map('intval', $ids))); return $ids ? implode(',', $ids) : '0'; }
/** How each leader came in: who referred her (an Envoy, a leader, someone else), and her 3-Day or 7-Day activation. */
function lm_journeys(array $ids): array {
  $out = []; $in = lm_in($ids);
  foreach (rows("SELECT r.user_id, r.category, r.referrer_name, r.referrer_id, r.created_at, ru.role ref_role FROM referrals r LEFT JOIN users ru ON ru.id=r.referrer_id WHERE r.user_id IN ($in)") as $r)
    $out[(int) $r['user_id']]['referral'] = ['by' => $r['referrer_name'], 'by_id' => $r['referrer_id'] ? (int) $r['referrer_id'] : null, 'category' => $r['ref_role'] === 'envoy' ? 'envoy' : $r['category'],
      'category_label' => REF_CATS[$r['ref_role'] === 'envoy' ? 'envoy' : $r['category']] ?? '', 'at' => $r['created_at']];
  foreach (rows("SELECT a.* FROM activations a JOIN (SELECT user_id, MAX(id) mid FROM activations WHERE user_id IN ($in) GROUP BY user_id) x ON x.mid=a.id") as $a)
    $out[(int) $a['user_id']]['activation'] = ['route' => $a['route'], 'route_label' => ACT_ROUTES[$a['route']]['label'] ?? $a['route'], 'status' => $a['status'], 'outcome' => $a['outcome'],
      'started_at' => $a['started_at'], 'submitted_at' => $a['submitted_at'], 'decided_at' => $a['decided_at'], 'deadline' => $a['deadline']];
  return $out;
}
/** Each leader's figures over the period, from the records themselves. Nothing is estimated: a figure with no record is zero. */
function lm_kpis(array $ids, int $days): array {
  $out = []; if (!$ids) return $out; $in = lm_in($ids);
  $from = gmdate('Y-m-d H:i:s', time() - $days * 86400); $fromD = gmdate('Y-m-d', time() - $days * 86400); $today = work_today(); $nowS = now();
  $z = ['leaders_brought' => 0, 'leaders_total' => 0, 'candidates' => 0, 'members_brought' => 0, 'meetings_invited' => 0, 'meetings_attended' => 0, 'meetings_missed' => 0, 'joined_timed' => 0, 'punctual' => 0,
    'meetings_held' => 0, 'reports_due' => 0, 'reports_on_time' => 0, 'reports_late' => 0, 'reports_overdue' => 0, 'tasks' => 0, 'tasks_on_time' => 0, 'tasks_late' => 0, 'tasks_overdue' => 0, 'tasks_open' => 0,
    'programmes' => 0, 'people_reached' => 0, 'actions' => 0];
  foreach ($ids as $id) $out[(int) $id] = $z;
  $put = function (array $rs, array $map) use (&$out) { foreach ($rs as $r) { $id = (int) $r['id']; if (!isset($out[$id])) continue; foreach ($map as $k) $out[$id][$k] = (int) ($r[$k] ?? 0); } };
  // people she brought: leaders are referred people who now hold (or held) a seat; members are people who joined through her link without a seat in view
  $put(rows("SELECT r.referrer_id id, COUNT(DISTINCT CASE WHEN s.starts_at>=? THEN r.user_id END) leaders_brought, COUNT(DISTINCT s.user_id) leaders_total,
      COUNT(DISTINCT CASE WHEN r.position_code<>'' AND r.created_at>=? THEN r.user_id END) candidates, COUNT(DISTINCT CASE WHEN r.position_code='' AND r.created_at>=? THEN r.user_id END) members_brought
    FROM referrals r LEFT JOIN seats s ON s.user_id=r.user_id WHERE r.referrer_id IN ($in) GROUP BY r.referrer_id", [$from, $from, $from]), ['leaders_brought', 'leaders_total', 'candidates', 'members_brought']);
  // meetings called in WDOS: present or absent as marked; punctual when she joined within ten minutes of the start
  $put(rows("SELECT a.user_id id, COUNT(*) meetings_invited, SUM(a.status='present') meetings_attended, SUM(a.status='absent') meetings_missed
    FROM meeting_attendance a JOIN meetings m ON m.id=a.meeting_id WHERE a.user_id IN ($in) AND m.starts_at>=? AND m.starts_at<=? AND m.status<>'cancelled' GROUP BY a.user_id", [$from, $nowS]), ['meetings_invited', 'meetings_attended', 'meetings_missed']);
  $j = []; foreach (rows("SELECT j.user_id id, j.joined_at t, m.starts_at s FROM meeting_joins j JOIN meetings m ON m.id=j.meeting_id WHERE j.user_id IN ($in) AND m.starts_at>=? AND m.starts_at<=?", [$from, $nowS]) as $r) $j[] = $r;
  foreach (rows("SELECT j.user_id id, j.first_at t, m.starts_at s FROM meeting_request_joins j JOIN meeting_requests m ON m.id=j.request_id WHERE j.user_id IN ($in) AND m.starts_at>=? AND m.starts_at<=?", [$from, $nowS]) as $r) $j[] = $r;
  foreach ($j as $r) { $id = (int) $r['id']; $out[$id]['joined_timed']++; if (strtotime($r['t'] . ' UTC') <= strtotime($r['s'] . ' UTC') + LM_PUNCTUAL_MIN * 60) $out[$id]['punctual']++; }
  // a video meeting room she joined counts as attended where no attendance was marked for it
  foreach (rows("SELECT j.user_id id, COUNT(*) n FROM meeting_request_joins j JOIN meeting_requests m ON m.id=j.request_id WHERE j.user_id IN ($in) AND m.starts_at>=? AND m.starts_at<=? GROUP BY j.user_id", [$from, $nowS]) as $r) { $out[(int) $r['id']]['meetings_attended'] += (int) $r['n']; $out[(int) $r['id']]['meetings_invited'] += (int) $r['n']; }
  // meetings and programmes she held
  foreach (rows("SELECT host_id id, COUNT(*) n FROM meetings WHERE host_id IN ($in) AND starts_at>=? AND starts_at<=? AND status='completed' GROUP BY host_id", [$from, $nowS]) as $r) $out[(int) $r['id']]['meetings_held'] += (int) $r['n'];
  foreach (rows("SELECT requester_id id, COUNT(*) n FROM meeting_requests WHERE requester_id IN ($in) AND starts_at>=? AND starts_at<=? AND status='held' GROUP BY requester_id", [$from, $nowS]) as $r) $out[(int) $r['id']]['meetings_held'] += (int) $r['n'];
  $put(rows("SELECT user_id id, COUNT(*) programmes, COALESCE(SUM(people_reached),0) people_reached FROM activity_records WHERE user_id IN ($in) AND happened_at>=? GROUP BY user_id", [$fromD]), ['programmes', 'people_reached']);
  // reports due in the period: on time, late, or still not in
  $put(rows("SELECT owner_id id, COUNT(*) reports_due, SUM(submitted_at IS NOT NULL AND on_time=1) reports_on_time, SUM(submitted_at IS NOT NULL AND (on_time=0 OR on_time IS NULL)) reports_late,
      SUM(submitted_at IS NULL AND due_on<?) reports_overdue FROM report_instances WHERE owner_id IN ($in) AND due_on>=? AND due_on<=? AND (is_nil=0 OR is_nil IS NULL OR submitted_at IS NOT NULL) GROUP BY owner_id", [$today, $fromD, $today]),
    ['reports_due', 'reports_on_time', 'reports_late', 'reports_overdue']);
  // tasks given to her in the period, or due in it
  $put(rows("SELECT assignee_id id, COUNT(*) tasks, SUM(status='completed' AND (due_on IS NULL OR DATE(completed_at)<=due_on)) tasks_on_time, SUM(status='completed' AND due_on IS NOT NULL AND DATE(completed_at)>due_on) tasks_late,
      SUM(status IN " . TASK_OPEN . " AND due_on IS NOT NULL AND due_on<?) tasks_overdue, SUM(status IN " . TASK_OPEN . ") tasks_open
    FROM tasks WHERE assignee_id IN ($in) AND (created_at>=? OR due_on>=?) AND status<>'cancelled' GROUP BY assignee_id", [$today, $from, $fromD]), ['tasks', 'tasks_on_time', 'tasks_late', 'tasks_overdue', 'tasks_open']);
  $put(rows("SELECT actor_id id, COUNT(*) actions FROM activity_log WHERE actor_id IN ($in) AND created_at>=? GROUP BY actor_id", [$from]), ['actions']);
  foreach ($out as &$k) {
    $k['punctuality'] = $k['joined_timed'] ? (int) round(100 * $k['punctual'] / $k['joined_timed']) : null;
    $k['attendance'] = $k['meetings_invited'] ? (int) round(100 * min($k['meetings_attended'], $k['meetings_invited']) / $k['meetings_invited']) : null;
    $k['report_rate'] = $k['reports_due'] ? (int) round(100 * $k['reports_on_time'] / $k['reports_due']) : null;
    $sc = 0; foreach (LM_SCORE as $f => $w) $sc += $w * (int) $k[$f]; $k['score'] = $sc;
  }
  return $out;
}

/* ---- the map ------------------------------------------------------------------------------------------------- */
function lm_place_name(array $n): string {
  $d = lm_depth($n); if ($d === 0) return 'WODDI Headquarters';
  if ($d === 5) return (string) (val('SELECT name FROM chapters WHERE id=?', [$n['chapter']]) ?? 'Chapter');
  return $d === 1 ? country_name($n['cc']) : [2 => $n['state'], 3 => $n['lga'], 4 => $n['comm']][$d];
}
/** The expected seats at a place, filled or vacant. $viewerLinks: show the link button. */
function lm_seats_at(array $n, array $nets, array $v, bool $detail): array {
  $d = lm_depth($n); if ($d === 0) return [];
  $lv = LM_LEVELS[$d - 1]; $here = array_values(array_filter(lm_seats(), fn($s) => $s['depth'] === $d && in_array($s['network'], $nets, true) && lm_same($s['place'], $n)));
  $out = []; $used = [];
  foreach ($nets as $net) foreach (LM_SEATS[$lv] as $i => $code) {
    $hit = null; foreach ($here as $s) if (!isset($used[$s['id']]) && $s['network'] === $net && $s['code'] === $code) { $hit = $s; $used[$s['id']] = 1; break; }
    $out[] = lm_seat_card($hit, $code, $net, $n, $i === 0, $v, $detail);
  }
  foreach ($here as $s) if (!isset($used[$s['id']])) $out[] = lm_seat_card($s, $s['code'], $s['network'], $n, false, $v, $detail) + ['extra' => true];   // secretaries, a second assistant, a Campus Ambassador
  return $out;
}
function lm_seat_card(?array $s, string $code, string $net, array $n, bool $principal, array $v, bool $detail): array {
  $c = ['network' => $net, 'position' => $code, 'title' => lm_title($s ? $s['position_code'] : $code, $net, $n['cc']), 'principal' => $principal, 'filled' => (bool) $s];
  if (!$s) {
    // candidates already in their activation for this very seat
    $unit = lm_unit_find($n['cc'], $n['state']);
    $c['candidates'] = $unit ? (int) val("SELECT COUNT(*) FROM activations WHERE status IN ('in_progress','submitted','returned') AND network=? AND position_code=? AND org_unit_id=? AND LOWER(lga)=LOWER(?) AND LOWER(community)=LOWER(?) AND COALESCE(chapter_id,0)=?",
      [$net, $code, $unit, $n['lga'], $n['comm'], (int) $n['chapter']]) : 0;
    return $c;
  }
  $p = $s['person']; if (!$v['contact']) unset($p['phone']);
  return $c + ['seat_id' => $s['id'], 'status' => $s['status'], 'acceptance' => $s['acceptance'], 'since' => $s['since'], 'he_for_she' => $s['he_for_she'], 'person' => $p];
}
/** Children of a place, each with its principal seats (filled or vacant), the leaders under it and its members. */
function lm_children(array $n, array $v): array {
  $d = lm_depth($n); $nets = $v['nets'];
  if ($d >= 5) return [];
  if ($d === 0) $kids = array_map(fn($c) => ['name' => $c['name'], 'place' => ['cc' => $c['code'], 'state' => '', 'lga' => '', 'comm' => '', 'chapter' => 0]], lm_countries());
  elseif ($d === 4) $kids = array_map(fn($c) => ['name' => $c['name'], 'network' => $c['network'], 'status' => $c['status'], 'members' => (int) $c['members'], 'place' => array_merge($n, ['chapter' => (int) $c['id']])], lm_chapters($n, $nets));
  else { $key = [1 => 'state', 2 => 'lga', 3 => 'comm'][$d]; $kids = array_map(fn($x) => ['name' => $x, 'place' => array_merge($n, [$key => $x])], lm_child_names($n, $nets)); }
  if (!$kids) return [];
  $lv = LM_LEVELS[$d]; $seats = array_values(array_filter(lm_seats(), fn($s) => in_array($s['network'], $nets, true) && lm_inside($s['place'], $n)));
  // members per child, by the place each member gave
  $mem = [];
  $col = [0 => 'country_code', 1 => 'state_region', 2 => 'lga', 3 => 'community', 4 => 'chapter_id'][$d]; $w = "status='active' AND network IN ('" . implode("','", $nets) . "')"; $p = [];
  foreach ([1 => ['country_code', 'cc'], 2 => ['state_region', 'state'], 3 => ['lga', 'lga'], 4 => ['community', 'comm']] as $i => [$c2, $k2]) if ($d >= $i) { $w .= " AND $c2=?"; $p[] = $n[$k2]; }
  foreach (rows("SELECT LOWER($col) k, COUNT(*) n FROM users WHERE $w GROUP BY LOWER($col)", $p) as $r) { $kk = $d === 4 ? (string) $r['k'] : lm_k((string) $r['k']); $mem[$kk] = ($mem[$kk] ?? 0) + (int) $r['n']; }
  $out = [];
  foreach ($kids as $kid) {
    $pl = $kid['place']; $under = array_values(array_filter($seats, fn($s) => lm_inside($s['place'], $pl)));
    $at = array_values(array_filter($under, fn($s) => $s['depth'] === $d + 1));
    $slots = []; $vac = 0;
    foreach ($nets as $net) {
      if ($d === 4 && ($kid['network'] ?? $net) !== $net) continue;
      foreach (LM_SEATS[$lv] as $i => $code) {
        $hit = null; foreach ($at as $s) if ($s['network'] === $net && $s['code'] === $code) { $hit = $s; break; }
        if (!$hit) $vac++;
        $slots[] = ['network' => $net, 'title' => lm_title($code, $net, $n['cc'] ?: $pl['cc']), 'principal' => $i === 0, 'holder' => $hit ? ['id' => $hit['user_id'], 'first_name' => $hit['person']['first_name'], 'last_name' => $hit['person']['last_name'], 'has_photo' => $hit['person']['has_photo'], 'status' => $hit['status']] : null];
      }
    }
    $mk = $d === 4 ? (string) $pl['chapter'] : lm_k([0 => $pl['cc'], 1 => $pl['state'], 2 => $pl['lga'], 3 => $pl['comm']][$d]);
    if ($d === 0) $mk = lm_k($pl['cc']);
    $out[] = ['name' => $kid['name'], 'place' => $pl, 'slots' => $slots, 'vacant' => $vac, 'leaders' => count($under), 'members' => $d === 4 ? ($kid['members'] ?? 0) : ($mem[$mk] ?? 0),
      'network' => $kid['network'] ?? null, 'status' => $kid['status'] ?? null];
  }
  return $out;
}

/* ---- a link for a vacant seat ---------------------------------------------------------------------------------- */
/** The org unit a seat link points at: the country, or the state or region (made when a region has no unit yet, so its seats have a home). */
function lm_unit_find(string $cc, string $state): ?int {
  static $c = []; $k = $cc . '|' . lm_k($state); if (array_key_exists($k, $c)) return $c[$k];
  $id = $state === '' ? val("SELECT id FROM org_units WHERE level='country' AND country_code=?", [$cc])
    : val("SELECT s.id FROM org_units s JOIN org_units c ON c.id=s.parent_id WHERE s.level='state_region' AND c.country_code=? AND LOWER(s.name)=LOWER(?)", [$cc, $state]);
  return $c[$k] = $id ? (int) $id : null;
}
function lm_unit(string $cc, string $state): int {
  $c = row("SELECT id, path FROM org_units WHERE level='country' AND country_code=?", [$cc]); if (!$c) fail(422, 'That country is not in the WDOS geography yet.', 'country');
  if ($state === '') return (int) $c['id'];
  $s = val("SELECT id FROM org_units WHERE level='state_region' AND parent_id=? AND LOWER(name)=LOWER(?)", [(int) $c['id'], $state]);
  if ($s) return (int) $s;
  $id = ins("INSERT INTO org_units (parent_id,level,name,country_code,path) VALUES (?,?,?,?,'')", [(int) $c['id'], 'state_region', mb_substr($state, 0, 160), $cc]);
  q('UPDATE org_units SET path=? WHERE id=?', [$c['path'] . $id . '/', $id]);
  return $id;
}
/** Her own link for one vacant seat: made once, then the same link every time she asks. Referrals through it are credited to her. */
function lm_seat_link(array $u, string $net, string $code, array $n): array {
  $d = lm_depth($n); $lv = LM_LEVELS[$d - 1] ?? null;
  if (!$lv || !in_array($code, LM_SEATS[$lv], true)) fail(422, 'That position does not belong at this level.', 'position');
  $unit = lm_unit($n['cc'], $n['state']); $area = $d === 5 ? (string) val('SELECT name FROM chapters WHERE id=?', [$n['chapter']]) : ($d === 4 ? $n['comm'] : ($d === 3 ? $n['lga'] : ''));
  $l = row('SELECT * FROM referral_links WHERE owner_id=? AND position_code=? AND network=? AND org_unit_id=? AND LOWER(lga)=LOWER(?) AND LOWER(community)=LOWER(?) AND COALESCE(chapter_id,0)=? AND active=1 ORDER BY id LIMIT 1',
    [(int) $u['id'], $code, $net, $unit, $n['lga'], $n['comm'], (int) $n['chapter']]);
  if (!$l) {
    $title = lm_title($code, $net, $n['cc']) . ', ' . lm_place_name($n);
    $id = ins("INSERT INTO referral_links (code,owner_id,kind,network,org_unit_id,position_code,area_label,label,campaign,created_at,lga,community,chapter_id) VALUES (?,?,'leader',?,?,?,?,?,'vacant_seat',?,?,?,?)",
      [ref_new_code(), (int) $u['id'], $net, $unit, $code, mb_substr($area, 0, 120), mb_substr('Vacant seat: ' . $title, 0, 160), now(), $n['lga'], $n['comm'], $n['chapter'] ?: null]);
    $l = row('SELECT * FROM referral_links WHERE id=?', [$id]);
    log_activity((int) $u['id'], null, 'seat_link', $title, 'referral_link', $id);
  }
  return ref_link_shape($l) + ['url' => app_url() . '/#/r/' . $l['code'], 'title' => lm_title($code, $net, $n['cc']), 'place_name' => lm_place_name($n)];
}

/* ---- one leader's file: where she comes from, how she came, what she has done ------------------------------------- */

/* ---- leaderboard ------------------------------------------------------------------------------------------------ */

/* ---- vacancy alerts --------------------------------------------------------------------------------------------- */
/** The Country Representative and Deputy (or Country Lead and Deputy) of a country in one network. */
function lm_country_heads(string $cc, string $net): array { return array_values(array_filter(lm_seats(), fn($s) => $s['place']['cc'] === $cc && $s['network'] === $net && $s['depth'] === 1 && !in_array($s['status'], ['on_leave', 'suspended'], true))); }
function lm_state_envoys(string $cc, string $state): array { return array_map('intval', array_column(rows("SELECT e.user_id FROM envoys e JOIN users u ON u.id=e.user_id WHERE e.country_code=? AND LOWER(e.state_region)=LOWER(?) AND u.status='active' AND e.status<>'ended'", [$cc, $state]), 'user_id')); }
function lm_alert_once(string $k): bool { try { q('INSERT INTO seat_vacancy_alerts (k,sent_at) VALUES (?,?)', [mb_substr($k, 0, 190), now()]); return true; } catch (PDOException $e) { return false; } }
function lm_map_url(array $n): string { $q = []; foreach (['cc', 'state', 'lga', 'comm'] as $k) if ($n[$k] !== '') $q[] = $k . '=' . rawurlencode($n[$k]); if ($n['chapter']) $q[] = 'chapter=' . $n['chapter']; return '#/leadmap' . ($q ? '?' . implode('&', $q) : ''); }
/** A seat that has just become empty (a leader stepped down or was moved, and nobody holds it now): the Country Representative and Deputy of
 *  that network are told at once, each with her own link for the seat, and so is the State Envoy in Nigeria. Each seat is announced once. */
function lm_vacancy_scan(): int {
  $sent = 0; $queue = []; lm_seats(true);
  foreach (rows("SELECT s.id, s.user_id, s.position_code, s.network, s.org_unit_id, s.area_label, s.lga, s.community, s.chapter_id, s.ends_at, p.level, ou.level ou_level, ou.name ou_name, ou.country_code, c.lga c_lga, c.community c_comm, u.lga u_lga, u.community u_comm, u.state_region u_state
      FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units ou ON ou.id=s.org_unit_id JOIN users u ON u.id=s.user_id LEFT JOIN chapters c ON c.id=s.chapter_id
      WHERE s.ends_at IS NOT NULL AND s.ends_at>=? AND NOT EXISTS (SELECT 1 FROM seat_vacancy_alerts a WHERE a.k=CONCAT('end:',s.id)) ORDER BY s.id LIMIT 200", [gmdate('Y-m-d H:i:s', time() - 3 * 86400)]) as $r) {
    $code = LM_ALIAS[$r['position_code']] ?? $r['position_code']; $d = array_search($r['level'], LM_LEVELS, true); if ($d === false) { lm_alert_once('end:' . $r['id']); continue; } $d++;
    $lvl = $r['level']; if (!in_array($code, LM_SEATS[$lvl] ?? [], true)) { lm_alert_once('end:' . $r['id']); continue; }
    $state = $r['ou_level'] === 'state_region' ? $r['ou_name'] : ($lvl === 'state_region' ? $r['area_label'] : (string) $r['u_state']);
    $n = ['cc' => (string) $r['country_code'], 'state' => $d >= 2 ? $state : '', 'lga' => $d >= 3 ? ($r['lga'] ?: ($lvl === 'lga' ? $r['area_label'] : ($r['c_lga'] ?: $r['u_lga']))) : '',
      'comm' => $d >= 4 ? ($r['community'] ?: ($lvl === 'community_cluster' ? $r['area_label'] : ($r['c_comm'] ?: $r['u_comm']))) : '', 'chapter' => $d === 5 ? (int) $r['chapter_id'] : 0];
    if (lm_depth($n) !== $d) { lm_alert_once('end:' . $r['id']); continue; }
    $filled = false; foreach (lm_seats() as $s) if ($s['network'] === $r['network'] && $s['code'] === $code && lm_same($s['place'], $n)) { $filled = true; break; }
    if (!lm_alert_once('end:' . $r['id']) || $filled) continue;
    $title = lm_title($code, $r['network'], $n['cc']); $place = lm_place_name($n);
    $who = array_map(fn($s) => $s['user_id'], lm_country_heads($n['cc'], $r['network']));
    if ($d >= 2 && $n['cc'] === 'NG') $who = array_merge($who, lm_state_envoys($n['cc'], $n['state']));
    foreach (array_unique($who) as $uid) if ($uid !== (int) $r['user_id']) $queue[$uid][] = [$r['network'], $code, $n, $title, $place];
  }
  foreach ($queue as $uid => $items) {
    $p = row("SELECT * FROM users WHERE id=? AND status='active'", [$uid]); if (!$p) continue;
    if (count($items) > 3) {          // many seats at once (a reshuffle): one notice with the map, not a notice per seat
      $cc = $items[0][2]['cc'];
      notify($uid, 'vacancy', count($items) . ' leadership seats are now vacant', 'Seats in ' . implode(', ', array_slice(array_unique(array_map(fn($x) => $x[4], $items)), 0, 6)) . (count($items) > 6 ? ' and more' : '') . ' have no leader now. Open the leadership map to share a link for each one.', lm_map_url(['cc' => $cc, 'state' => '', 'lga' => '', 'comm' => '', 'chapter' => 0]));
      $sent++; continue;
    }
    foreach ($items as [$net, $code, $n, $title, $place]) {
      $link = lm_depth($n) > 1 ? lm_seat_link($p, $net, $code, $n) : null;
      notify($uid, 'vacancy', 'Vacant seat: ' . $title . ', ' . $place, $net . ' · This seat has no leader now. ' . ($link ? 'Share your link to refer a candidate: ' . $link['url'] : 'Open the leadership map to see it.'), lm_map_url($n));
      $sent++;
    }
  }
  return $sent;
}
/** Every Monday: each Country Representative and Deputy, and each Nigerian State Envoy, gets the count of vacant seats in her area. */
function lm_vacancy_digest(): int {
  $week = gmdate('o-W'); $sent = 0; lm_seats(true);
  foreach (lm_seats() as $s) {
    if ($s['depth'] !== 1 || in_array($s['status'], ['on_leave', 'suspended'], true) || !lm_alert_once('wk:' . $week . ':' . $s['user_id'])) continue;
    $v = ['nets' => [$s['network']], 'contact' => false, 'links' => false, 'kpis' => false, 'role' => 'leader', 'root' => $s['place'], 'up' => null, 'rank' => $s['rank']];
    $kids = lm_children(lm_cut($s['place'], 1), $v); $vac = array_sum(array_column($kids, 'vacant')); $own = count(array_filter(lm_seats_at(lm_cut($s['place'], 1), [$s['network']], $v, false), fn($c) => !$c['filled']));
    $none = count(array_filter($kids, fn($k) => !array_filter($k['slots'], fn($x) => $x['principal'] && $x['holder'])));
    if (!$vac && !$own) continue;
    notify($s['user_id'], 'vacancy', 'Leadership seats to fill in ' . country_name($s['place']['cc']), $s['network'] . ' · ' . $vac . ' vacant ' . strtolower(lm_level_label('state_region', $s['place']['cc'])) . ' seats; ' . $none . ' ' . strtolower(lm_level_label('state_region', $s['place']['cc'])) . 's have no principal leader yet' . ($own ? '; ' . $own . ' country seat open' : '') . '. Open the map to share a link for each one.', lm_map_url(lm_cut($s['place'], 1)));
    $sent++;
  }
  foreach (rows("SELECT e.user_id, e.country_code, e.state_region FROM envoys e JOIN users u ON u.id=e.user_id WHERE u.status='active' AND e.status<>'ended' AND e.country_code='NG' AND e.state_region<>''") as $e) {
    if (!lm_alert_once('wk:' . $week . ':e' . $e['user_id'])) continue;
    $n = ['cc' => 'NG', 'state' => $e['state_region'], 'lga' => '', 'comm' => '', 'chapter' => 0]; $v = ['nets' => ['WGMN', 'WNNN'], 'contact' => false, 'links' => false, 'kpis' => false, 'role' => 'envoy', 'root' => $n, 'up' => $n, 'rank' => 2];
    $own = count(array_filter(lm_seats_at($n, ['WGMN', 'WNNN'], $v, false), fn($c) => !$c['filled'])); $vac = array_sum(array_column(lm_children($n, $v), 'vacant'));
    if (!$own && !$vac) continue;
    notify((int) $e['user_id'], 'vacancy', 'Leadership seats to fill in ' . $e['state_region'], ($own ? $own . ' state seats are vacant across WGMN and WNNN. ' : '') . $vac . ' LGA seats are vacant. Open the map to share a link for each one.', lm_map_url($n));
    $sent++;
  }
  return $sent;
}

/** For a leader's dashboard: the leaders under her, the seats still vacant one level down, and the places with no principal leader. */
function lm_summary(int $uid): ?array {
  $s = lm_seat_of($uid); if (!$s) return null;
  $n = lm_cut($s['place'], $s['depth']);
  $v = ['nets' => [$s['network']], 'contact' => false, 'links' => false, 'kpis' => false, 'role' => 'leader', 'root' => $n, 'up' => $n, 'rank' => $s['rank']];
  $kids = $s['depth'] < 5 ? lm_children($n, $v) : [];
  $under = array_values(array_filter(lm_seats(), fn($x) => $x['network'] === $s['network'] && lm_inside($x['place'], $n) && $x['rank'] > $s['rank']));
  return ['leaders' => count(array_unique(array_column($under, 'user_id'))), 'vacant' => array_sum(array_column($kids, 'vacant')), 'places' => count($kids),
    'child_label' => $s['depth'] < 5 ? lm_level_label(LM_LEVELS[$s['depth']], $n['cc']) : null,
    'no_leader' => count(array_filter($kids, fn($k) => !array_filter($k['slots'], fn($x) => $x['principal'] && $x['holder']))),
    'active_7d' => count(array_unique(array_column(array_filter($under, fn($x) => $x['person']['last_seen_at'] && strtotime($x['person']['last_seen_at'] . ' UTC') > time() - 7 * 86400), 'user_id'))),
    'url' => lm_map_url($n)];
}
/** For a State Envoy's dashboard: the state seats of both networks, filled or vacant, and how many LGA seats are still vacant. */
function lm_envoy_summary(array $u): ?array {
  // never through lm_viewer: its refusal would end the whole dashboard request, and an envoy with no state recorded still needs her dashboard
  if (($u['role'] ?? '') !== 'envoy') return null;
  $e = row('SELECT country_code, state_region FROM envoys WHERE user_id=?', [(int) $u['id']]);
  $cc = (string) ($e['country_code'] ?? $u['country_code'] ?? ''); $st = (string) ($e['state_region'] ?? '') ?: (string) ($u['state_region'] ?? '');
  if ($cc === '' || $st === '') return null;
  $n = ['cc' => $cc, 'state' => $st, 'lga' => '', 'comm' => '', 'chapter' => 0];
  $v = ['role' => 'envoy', 'root' => $n, 'nets' => ['WGMN', 'WNNN'], 'up' => $n, 'kpis' => false, 'contact' => false, 'links' => true, 'rank' => 2]; $seats = lm_seats_at($n, $v['nets'], $v, false); $kids = lm_children($n, $v);
  return ['state' => $n['state'], 'seats' => array_map(fn($c) => ['network' => $c['network'], 'title' => $c['title'], 'filled' => $c['filled'], 'name' => $c['filled'] ? trim($c['person']['first_name'] . ' ' . $c['person']['last_name']) : null], array_filter($seats, fn($c) => empty($c['extra']))),
    'vacant_lga' => array_sum(array_column($kids, 'vacant')), 'lgas' => count($kids), 'lgas_without' => count(array_filter($kids, fn($k) => !array_filter($k['slots'], fn($x) => $x['principal'] && $x['holder']))), 'url' => lm_map_url($n)];
}
