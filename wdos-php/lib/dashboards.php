<?php
declare(strict_types=1);
/* HQ and volunteer-leadership dashboards, members, seats, announcements.
 * Every number here is a count of real rows. Nothing is estimated or padded. */

function day_series(string $whereSql, array $params, int $days): array {
  $start = gmdate('Y-m-d', time() - ($days - 1) * 86400);
  $got = [];
  foreach (rows("SELECT DATE(u.created_at) d, u.network n, COUNT(*) c FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id
                 WHERE u.status<>'pending_profile' AND u.network IS NOT NULL AND u.created_at>=? AND ($whereSql) GROUP BY DATE(u.created_at), u.network", array_merge([$start . ' 00:00:00'], $params)) as $r) {
    $got[$r['d']][$r['n']] = (int) $r['c'];
  }
  $out = [];
  for ($i = 0; $i < $days; $i++) {
    $d = gmdate('Y-m-d', strtotime($start . ' UTC') + $i * 86400);
    $out[] = ['date' => $d, 'WGMN' => $got[$d]['WGMN'] ?? 0, 'WNNN' => $got[$d]['WNNN'] ?? 0];
  }
  return $out;
}
function people_brief(string $whereSql, array $params, int $limit): array {
  return rows("SELECT u.id, u.first_name, u.last_name, u.member_no, u.network, u.country_code, u.state_region, u.has_photo, u.created_at
               FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status<>'pending_profile' AND u.network IS NOT NULL AND ($whereSql) ORDER BY u.id DESC LIMIT $limit", $params);
}

route('GET', '/hq/dashboard', function () {
  require_hq();
  $days = (int) ($_GET['days'] ?? 30); if (!in_array($days, [7, 30, 90], true)) $days = 30;
  $from = gmdate('Y-m-d 00:00:00', time() - ($days - 1) * 86400);
  $prev = gmdate('Y-m-d 00:00:00', time() - (2 * $days - 1) * 86400);
  $t = row("SELECT COUNT(*) total, COALESCE(SUM(network='WGMN'),0) wgmn, COALESCE(SUM(network='WNNN'),0) wnnn,
                   COALESCE(SUM(created_at>=?),0) new_now, COALESCE(SUM(created_at>=? AND created_at<?),0) new_prev,
                   COUNT(DISTINCT country_code) countries, COALESCE(SUM(last_seen_at>=?),0) active_7d
            FROM users WHERE status<>'pending_profile' AND network IS NOT NULL", [$from, $prev, $from, gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
  $labels = ['country' => 'Country', 'state_region' => 'State or region', 'lga' => 'LGA or district', 'community_cluster' => 'Community cluster', 'chapter' => 'Chapter'];
  $byLevel = [];
  foreach (rows('SELECT p.level, s.network, COUNT(*) c FROM seats s JOIN positions p ON p.code=s.position_code WHERE s.ends_at IS NULL GROUP BY p.level, s.network') as $r) $byLevel[$r['level']][$r['network']] = (int) $r['c'];
  $levels = [];
  foreach ($labels as $k => $l) $levels[] = ['label' => $l, 'WGMN' => $byLevel[$k]['WGMN'] ?? 0, 'WNNN' => $byLevel[$k]['WNNN'] ?? 0];
  return [
    'days' => $days,
    'kpis' => ['members' => (int) $t['total'], 'wgmn' => (int) $t['wgmn'], 'wnnn' => (int) $t['wnnn'], 'new_now' => (int) $t['new_now'], 'new_prev' => (int) $t['new_prev'],
      'countries' => (int) $t['countries'], 'active_7d' => (int) $t['active_7d'],
      'leaders' => (int) val('SELECT COUNT(DISTINCT user_id) FROM seats WHERE ends_at IS NULL'),
      'push_devices' => (int) val('SELECT COUNT(*) FROM push_subscriptions'),
      'awaiting_profile' => (int) val("SELECT COUNT(*) FROM users WHERE status='pending_profile'")],
    'series' => day_series('1=1', [], $days),
    'by_country' => rows("SELECT u.country_code code, ou.name, CAST(COALESCE(SUM(u.network='WGMN'),0) AS SIGNED) WGMN, CAST(COALESCE(SUM(u.network='WNNN'),0) AS SIGNED) WNNN, COUNT(*) total
                          FROM users u JOIN org_units ou ON ou.level='country' AND ou.country_code=u.country_code
                          WHERE u.status<>'pending_profile' AND u.network IS NOT NULL GROUP BY u.country_code, ou.name ORDER BY total DESC, ou.name LIMIT 8"),
    'seats_by_level' => $levels,
    'recent' => people_brief('1=1', [], 7),
    'activity' => rows("SELECT a.id, a.action, a.detail, a.created_at, COALESCE(NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),'A member') AS subject, a.subject_id
                        FROM activity_log a LEFT JOIN users s ON s.id=a.subject_id ORDER BY a.id DESC LIMIT 8"),
  ];
});

route('GET', '/hq/members', function () {
  require_hq();
  $w = ["1=1"]; $p = [];
  if (($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR u.member_no LIKE ? OR u.phone LIKE ?)"; $like = '%' . $s . '%'; array_push($p, $like, $like, $like, $like); }
  if (in_array($_GET['network'] ?? '', ['WGMN', 'WNNN'], true)) { $w[] = 'u.network=?'; $p[] = $_GET['network']; }
  if (preg_match('/^[A-Z]{2}$/', (string) ($_GET['country'] ?? ''))) { $w[] = 'u.country_code=?'; $p[] = $_GET['country']; }
  $kind = $_GET['kind'] ?? '';
  if ($kind === 'leaders') $w[] = 'EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)';
  if ($kind === 'hq') $w[] = "u.role IN ('hq_admin','super_admin')";
  if ($kind === 'suspended') $w[] = "u.status='suspended'";
  $where = implode(' AND ', $w);
  $page = max(1, (int) ($_GET['page'] ?? 1)); $size = 20;
  $total = (int) val("SELECT COUNT(*) FROM users u WHERE $where", $p);
  $items = rows("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.member_no, u.network, u.country_code, u.state_region, u.role, u.status, u.has_photo, u.created_at, u.last_seen_at,
                 (SELECT CONCAT(CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END, ', ', CASE WHEN s.area_label<>'' THEN s.area_label ELSE o.name END)
                    FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id WHERE s.user_id=u.id AND s.ends_at IS NULL ORDER BY p.rank_no LIMIT 1) AS seat_title
                 FROM users u WHERE $where ORDER BY u.id DESC LIMIT $size OFFSET " . (($page - 1) * $size), $p);
  return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $size)), 'size' => $size];
});

route('GET', '/hq/members/(\d+)', function ($id) {
  require_hq();
  $u = row('SELECT * FROM users WHERE id=?', [(int) $id]);
  if (!$u) fail(404, 'That member was not found.', 'not_found');
  return ['member' => public_user($u) + ['last_seen_at' => $u['last_seen_at'], 'signup_method' => $u['signup_method'], 'org_unit_id' => (int) $u['org_unit_id'],
      'devices' => (int) val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=?', [(int) $id]),
      'fingerprints' => (int) val('SELECT COUNT(*) FROM webauthn_credentials WHERE user_id=?', [(int) $id])],
    'positions' => rows('SELECT code, title_wgmn, title_wnnn, level FROM positions ORDER BY rank_no'),
    'history' => rows("SELECT s.id, s.status, s.starts_at, s.ends_at, s.network, s.area_label, o.name unit, CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END title
                       FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id WHERE s.user_id=? ORDER BY s.id DESC LIMIT 10", [(int) $id])];
});

route('POST', '/hq/members/(\d+)', function ($id) {
  $me = require_hq(); $id = (int) $id;
  $u = row('SELECT * FROM users WHERE id=?', [$id]);
  if (!$u) fail(404, 'That member was not found.', 'not_found');
  $b = body();
  if (isset($b['status']) && in_array($b['status'], ['active', 'suspended'], true) && $u['status'] !== 'pending_profile') {
    if ($id === (int) $me['id']) fail(422, 'You cannot suspend your own account.', 'self');
    if ($u['role'] === 'super_admin' && $me['role'] !== 'super_admin') fail(403, 'Only a super administrator can change this account.', 'forbidden');
    q('UPDATE users SET status=? WHERE id=?', [$b['status'], $id]);
    if ($b['status'] === 'suspended') q('DELETE FROM sessions WHERE user_id=?', [$id]);
    log_activity((int) $me['id'], $id, $b['status'] === 'suspended' ? 'suspended' : 'reinstated');
    // an inactive member does not hold a seat in her chapter; on reinstatement WDOS places her again. The history is kept both ways.
    if ($b['status'] === 'suspended') chapter_leave($id, 'inactive'); else { $fresh = row('SELECT * FROM users WHERE id=?', [$id]); if ($fresh) chapter_place_user($fresh); }
  }
  if (isset($b['role']) && in_array($b['role'], ['member', 'hq_admin'], true)) {
    if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator can give or remove HQ access.', 'forbidden');
    if ($id === (int) $me['id']) fail(422, 'You cannot change your own access.', 'self');
    q('UPDATE users SET role=? WHERE id=?', [$b['role'], $id]);
    log_activity((int) $me['id'], $id, $b['role'] === 'hq_admin' ? 'hq_access_given' : 'hq_access_removed');
    notify($id, 'account', $b['role'] === 'hq_admin' ? 'You now have HQ access' : 'Your HQ access was removed', 'Sign out and back in to see the change.', '#/');
    after_response(fn() => push_drain(5));
  }
  return ['ok' => true];
});

/* WDOS never stores or shows an existing password. A reset makes a new temporary one, shown once. */
route('POST', '/hq/members/(\d+)/reset-password', function ($id) {
  $me = require_hq(); $id = (int) $id;
  $u = row('SELECT id, role FROM users WHERE id=?', [$id]);
  if (!$u) fail(404, 'That member was not found.', 'not_found');
  if ($u['role'] === 'super_admin' && $me['role'] !== 'super_admin') fail(403, 'Only a super administrator can change this account.', 'forbidden');
  $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; $temp = '';
  for ($i = 0; $i < 10; $i++) $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  q('UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
  q('DELETE FROM sessions WHERE user_id=?', [$id]);
  log_activity((int) $me['id'], $id, 'password_reset');
  notify($id, 'account', 'WODDI HQ reset your password', 'Sign in with the temporary password HQ gave you, then choose your own in Settings.', '#/settings');
  after_response(fn() => push_drain(5));
  return ['ok' => true, 'temporary_password' => $temp];
});

route('POST', '/hq/members/(\d+)/seat', function ($id) {
  $me = require_hq(); $id = (int) $id; $b = body();
  $u = row("SELECT * FROM users WHERE id=? AND status<>'pending_profile'", [$id]);
  if (!$u) fail(404, 'That member was not found.', 'not_found');
  q('UPDATE seats SET ends_at=? WHERE user_id=? AND ends_at IS NULL', [now(), $id]);   // one open seat per person: close before opening
  if (!empty($b['vacate'])) { log_activity((int) $me['id'], $id, 'seat_closed'); return ['ok' => true]; }
  $pos = row('SELECT * FROM positions WHERE code=?', [str_in('position', 40)]);
  if (!$pos) fail(422, 'Choose a position.', 'position');
  $unit = row('SELECT * FROM org_units WHERE id=?', [(int) ($b['org_unit_id'] ?? 0)]);
  if (!$unit || $unit['level'] === 'hq') fail(422, 'Choose the country or state this seat covers.', 'unit');
  $area = str_in('area_label', 120);
  if (in_array($pos['level'], ['lga', 'community_cluster', 'chapter'], true) && $area === '' && empty($b['chapter_id'])) fail(422, 'Name the LGA, city, cluster or chapter this seat covers.', 'area');
  $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : $u['network'];
  $status = in_array($b['status'] ?? '', ['appointed', 'acting', 'pending'], true) ? $b['status'] : 'appointed';
  $chap = (int) ($b['chapter_id'] ?? 0) ? row('SELECT * FROM chapters WHERE id=?', [(int) $b['chapter_id']]) : null;
  if ($chap && $chap['network'] !== $net) fail(422, 'That chapter belongs to the other network.', 'chapter');
  $hfs = !empty($b['he_for_she']);
  // He-for-She Champions serve as deputies, assistants or administrative secretaries only
  if ($hfs && !preg_match('/^(deputy_|assistant_)|_secretary$/', $pos['code'])) fail(422, 'A He-for-She Champion can be appointed as a deputy, an assistant or an administrative secretary only.', 'he_for_she');
  $title = seat_open($id, $pos['code'], $net, (int) $unit['id'], $chap && $area === '' ? $chap['name'] : $area, $status, (int) $me['id']);
  q('UPDATE seats SET chapter_id=?, he_for_she=? WHERE user_id=? AND ends_at IS NULL', [$chap ? (int) $chap['id'] : null, $hfs ? 1 : 0, $id]);
  if ($hfs) { q("UPDATE users SET participant_class='he_for_she' WHERE id=?", [$id]); chapter_leave($id, 'he_for_she'); }
  after_response(fn() => push_drain(5));
  return ['ok' => true, 'title' => $title];
});

route('POST', '/hq/members/(\\d+)/details', function ($id) {
  $me = require_hq(); $id = (int) $id; $b = body();
  $u = row('SELECT * FROM users WHERE id=?', [$id]);
  if (!$u) fail(404, 'That member was not found.', 'not_found');
  $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : $u['network'];
  $country = row("SELECT id, name, country_code FROM org_units WHERE level='country' AND id=?", [(int) ($b['country_id'] ?? 0)]);
  if (!$country) fail(422, 'Choose the country.', 'country');
  $unit = (int) $country['id']; $state = str_in('state_region', 120);
  if ($sid = (int) ($b['state_id'] ?? 0)) { $s = row('SELECT id, name FROM org_units WHERE id=? AND parent_id=?', [$sid, (int) $country['id']]); if ($s) { $unit = (int) $s['id']; $state = $s['name']; } }
  q('UPDATE users SET network=?, country_code=?, org_unit_id=?, state_region=?, lga=?, community=? WHERE id=?', [$net, $country['country_code'], $unit, $state, str_in('lga', 120), str_in('community', 120), $id]);
  log_activity((int) $me['id'], $id, 'details_corrected', trim(($net ?? '') . ' · ' . $state . ', ' . $country['name'], ' ·,'));
  chapter_replace_after_change($id);
  return ['ok' => true];
});

route('GET', '/hq/announcements', function () {
  require_hq();
  return ['items' => rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) author, (SELECT COUNT(*) FROM announcement_acks k WHERE k.announcement_id=a.id) acks,
                                  (SELECT GROUP_CONCAT(t.lang ORDER BY t.lang) FROM announcement_texts t WHERE t.announcement_id=a.id) langs FROM announcements a JOIN users u ON u.id=a.author_id ORDER BY a.id DESC LIMIT 30")];
});
route('POST', '/hq/announcements', function () { return announcement_send(require_hq()); });
/* Everyone reads the announcements addressed to her. */
route('GET', '/announcements', function () {
  $u = require_user(); $leader = current_seat((int) $u['id']) ? 1 : 0;
  $w = is_hq($u) ? '1=1' : "(a.audience_network='ALL' OR a.audience_network=?) AND (a.audience_role='all' OR ?=1) AND (a.audience_country IS NULL OR a.audience_country=?)
        AND (a.audience_state='' OR a.audience_state=?) AND (a.audience_lga='' OR a.audience_lga=?) AND (a.audience_community='' OR a.audience_community=?) AND (a.audience_chapter IS NULL OR a.audience_chapter=?) AND (a.audience_lang='' OR a.audience_lang=?) AND (a.audience_user IS NULL OR a.audience_user=" . (int) $u['id'] . ")";
  $lang = (string) ($u['lang'] ?? 'en');                     // her own language where that version was written
  return ['items' => rows("SELECT a.id, COALESCE(t.title, a.title) title, COALESCE(t.body, a.body) body, a.audience_network, a.audience_role, a.audience_chapter, a.tone, COALESCE(a.sent_at, a.created_at) created_at,
                                  EXISTS (SELECT 1 FROM announcement_acks k WHERE k.announcement_id=a.id AND k.user_id=?) acked
                           FROM announcements a LEFT JOIN announcement_texts t ON t.announcement_id=a.id AND t.lang=? WHERE a.sent_at IS NOT NULL AND $w ORDER BY a.id DESC LIMIT 20",
    is_hq($u) ? [(int) $u['id'], $lang] : [(int) $u['id'], $lang, (string) $u['network'], $leader, (string) $u['country_code'], $u['state_region'], $u['lga'], $u['community'], (int) ($u['chapter_id'] ?? 0), (string) ($u['lang'] ?? 'en')])];
});

/* ------------------------------------------------ volunteer leadership -- */
function leader_scope(array $seat): array {
  if (!empty($seat['chapter_id'])) return ['u.chapter_id=? AND u.network=?', [(int) $seat['chapter_id'], $seat['network']]];   // a chapter leader sees her own chapter only
  $w = 'ou.path LIKE ? AND u.network=?'; $p = [$seat['unit_path'] . '%', $seat['network']];
  if ($seat['position_level'] === 'lga') { $w .= ' AND u.lga=?'; $p[] = $seat['area_label']; }
  if (in_array($seat['position_level'], ['community_cluster', 'chapter'], true)) { $w .= ' AND u.community=?'; $p[] = $seat['area_label']; }
  return [$w, $p];
}
/** Whose leadership view is being asked for: the signed-in leader's own, or (HQ only, read-only) the leader named in ?as=. */
function leader_view(array $u, string $noSeat): array {
  $as = (int) ($_GET['as'] ?? 0);
  if ($as && $as !== (int) $u['id']) {
    if (!is_hq($u)) fail(403, 'Only WODDI HQ can open another leader\'s dashboard.', 'forbidden');
    $p = row('SELECT id, first_name, last_name, has_photo, member_no, network, country_code FROM users WHERE id=?', [$as]); $seat = $p ? current_seat($as) : null;
    if (!$seat) fail(404, 'That person does not hold a leadership seat.', 'no_seat');
    return [$seat, $p];
  }
  $seat = current_seat((int) $u['id']); if (!$seat) fail(403, $noSeat, 'no_seat');
  return [$seat, $u];
}
route('GET', '/leader/dashboard', function () {
  $u = require_user(); [$seat, $who] = leader_view($u, 'This dashboard opens when WODDI HQ records your leadership seat.');
  [$w, $p] = leader_scope($seat);
  $days = (int) ($_GET['days'] ?? 30); if (!in_array($days, [7, 30, 90], true)) $days = 30;
  $from = gmdate('Y-m-d 00:00:00', time() - ($days - 1) * 86400); $prev = gmdate('Y-m-d 00:00:00', time() - (2 * $days - 1) * 86400);
  $t = row("SELECT COUNT(*) total, COALESCE(SUM(u.created_at>=?),0) new_now, COALESCE(SUM(u.created_at>=? AND u.created_at<?),0) new_prev,
                   COALESCE(SUM(u.has_photo=1),0) with_photo, COALESCE(SUM(u.last_seen_at>=?),0) active_7d
            FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND $w", array_merge([$from, $prev, $from, gmdate('Y-m-d H:i:s', time() - 7 * 86400)], $p));
  $col = $seat['position_level'] === 'country' ? 'u.state_region' : ($seat['position_level'] === 'state_region' ? 'u.lga' : 'u.community');
  $team = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.last_seen_at, s.status, s.area_label, o.name unit, pp.rank_no,
                       CASE WHEN s.network='WNNN' THEN pp.title_wnnn ELSE pp.title_wgmn END title
                FROM seats s JOIN positions pp ON pp.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id JOIN users u ON u.id=s.user_id
                WHERE s.ends_at IS NULL AND s.network=? AND o.path LIKE ? AND pp.rank_no>? AND s.user_id<>? ORDER BY pp.rank_no, o.name LIMIT 12",
    [$seat['network'], $seat['unit_path'] . '%', (int) $seat['rank_no'], (int) $u['id']]);
  $above = row("SELECT u.id, u.first_name, u.last_name, u.has_photo, CASE WHEN s.network='WNNN' THEN pp.title_wnnn ELSE pp.title_wgmn END title, o.name unit
                FROM seats s JOIN positions pp ON pp.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id JOIN users u ON u.id=s.user_id
                WHERE s.ends_at IS NULL AND s.network=? AND pp.rank_no<? AND ? LIKE CONCAT(o.path,'%') AND s.user_id<>? ORDER BY pp.rank_no DESC LIMIT 1",
    [$seat['network'], (int) $seat['rank_no'], $seat['unit_path'], (int) $u['id']]);
  return [
    'days' => $days,
    'seat' => ['title' => seat_title($seat), 'network' => $seat['network'], 'status' => $seat['status'], 'level' => $seat['position_level'], 'unit' => $seat['unit_name'], 'country_code' => $seat['unit_country'], 'since' => $seat['starts_at']],
    'kpis' => ['members' => (int) $t['total'], 'new_now' => (int) $t['new_now'], 'new_prev' => (int) $t['new_prev'], 'with_photo' => (int) $t['with_photo'], 'active_7d' => (int) $t['active_7d'], 'team' => count($team)],
    'series' => day_series($w, $p, $days),
    'breakdown_label' => $seat['position_level'] === 'country' ? 'State or region' : ($seat['position_level'] === 'state_region' ? 'LGA or district' : 'Community'),
    'breakdown' => rows("SELECT COALESCE(NULLIF($col,''),'Not stated') label, COUNT(*) total FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND $w GROUP BY label ORDER BY total DESC, label LIMIT 8", $p),
    'recent' => people_brief($w, $p, 7),
    'team' => $team, 'reports_to' => $above, 'workspace' => leader_workspace($u, $seat),
    'person' => ['id' => (int) $who['id'], 'first_name' => $who['first_name'], 'last_name' => $who['last_name'], 'has_photo' => (int) $who['has_photo'], 'member_no' => $who['member_no'], 'network' => $seat['network'],
      'country_code' => $who['country_code'], 'country' => country_name((string) $who['country_code']), 'seat' => ['title' => seat_title($seat), 'network' => $seat['network']]], 'viewing' => (int) $who['id'] !== (int) $u['id'],
  ];
});
route('GET', '/leader/members', function () {
  $u = require_user(); [$seat] = leader_view($u, 'This list opens when WODDI HQ records your leadership seat.');
  // a He-for-She Champion works through assigned tasks, meetings and approved reports; the appointment does not open the personal roster
  if (!empty($seat['he_for_she']) && !is_hq($u)) fail(403, 'The member roster is not part of a He-for-She appointment. Your tasks, meetings and reports are open to you.', 'he_for_she');
  // a Campus Ambassador is an outreach role at a tertiary institution: referral links and outreach, not a member roster
  if ($seat['position_code'] === 'campus_ambassador' && !is_hq($u)) fail(403, 'A Campus Ambassador works through outreach and referral links. The member roster belongs to the supervising leader.', 'campus');
  [$w, $p] = leader_scope($seat);
  if (($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w .= " AND (CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.member_no LIKE ?)"; $p[] = "%$s%"; $p[] = "%$s%"; }
  $page = max(1, (int) ($_GET['page'] ?? 1)); $size = 20;
  $total = (int) val("SELECT COUNT(*) FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND $w", $p);
  return ['items' => rows("SELECT u.id, u.first_name, u.last_name, u.member_no, u.phone, u.network, u.country_code, u.state_region, u.lga, u.community, u.has_photo, u.created_at, u.last_seen_at
                           FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND $w ORDER BY u.id DESC LIMIT $size OFFSET " . (($page - 1) * $size), $p),
    'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $size)), 'scope' => seat_title($seat)];
});
