<?php
declare(strict_types=1);
/* v109.25: logins for many leaders at once (Azeez, 19 Nov 2026: "integrate the feature where I can create logins for all of them in
 * a single go in pdf from hq dashboard").
 * HQ chooses a country, a network and the levels (country, state or region, LGA, cluster, chapter, WODDI Envoys), ticks the people,
 * and WDOS gives each a new temporary password. The passwords are made here on the server, returned once in the reply and never
 * stored readable: only their hash is kept, as for any password. The page turns the reply into a PDF on HQ's own computer.
 * Each person must choose her own password at first sign-in, is signed out of other devices, and gets a notification.
 * HQ staff logins are never changed here (Staff and logins does that, super administrator only). */

const BULK_MAX = 300;
const BULK_LEVELS = ['country' => 'Country', 'state_region' => 'State or region', 'lga' => 'LGA or district', 'community_cluster' => 'Community cluster', 'chapter' => 'Chapter', 'envoy' => 'WODDI Envoys'];

/** The people HQ may issue logins to, for the choices made. */
function bulk_people(string $cc, string $net, array $levels, ?array $only = null): array {
  $out = [];
  $seatLevels = array_values(array_intersect(array_keys(BULK_LEVELS), array_diff($levels, ['envoy'])));
  if ($seatLevels) {
    $w = "s.ends_at IS NULL AND u.status='active' AND u.role NOT IN ('hq_admin','super_admin') AND p.level IN (" . implode(',', array_fill(0, count($seatLevels), '?')) . ')'; $p = $seatLevels;
    if ($cc !== '') { $w .= ' AND o.country_code=?'; $p[] = $cc; }
    if ($net !== '') { $w .= ' AND s.network=?'; $p[] = $net; }
    if ($only !== null) { if (!$only) return []; $w .= ' AND u.id IN (' . implode(',', array_map('intval', $only)) . ')'; }
    foreach (rows("SELECT u.*, s.network seat_network, s.area_label, p.rank_no, p.level plevel, CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END title, o.name unit_name, o.country_code cc,
                          (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.user_id=u.id) prints
                   FROM seats s JOIN users u ON u.id=s.user_id JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id
                   WHERE $w ORDER BY p.rank_no, s.network, o.name, u.first_name LIMIT 2000", $p) as $r) {
      if (isset($out[(int) $r['id']])) continue;
      $out[(int) $r['id']] = signin_shape($r) + ['position' => $r['title'] . ', ' . ($r['area_label'] !== '' ? $r['area_label'] : $r['unit_name']), 'network' => $r['seat_network'], 'level' => $r['plevel'], 'country' => $r['cc'], 'rank' => (int) $r['rank_no']];
    }
  }
  if (in_array('envoy', $levels, true)) {
    try {
      $w = "u.role='envoy' AND u.status='active' AND e.status='active'"; $p = [];
      if ($cc !== '') { $w .= ' AND (e.country_code=? OR u.country_code=?)'; $p[] = $cc; $p[] = $cc; }
      if ($net !== '') { $w .= ' AND (u.network=? OR u.network IS NULL OR u.network=\'\')'; $p[] = $net; }
      if ($only !== null) $w .= ' AND u.id IN (' . implode(',', array_map('intval', $only ?: [0])) . ')';
      foreach (rows("SELECT u.*, e.country_code ecc, (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.user_id=u.id) prints FROM users u JOIN envoys e ON e.user_id=u.id WHERE $w ORDER BY u.state_region, u.first_name LIMIT 1000", $p) as $r) {
        if (isset($out[(int) $r['id']])) continue;
        $out[(int) $r['id']] = signin_shape($r) + ['position' => $r['designation'] !== '' ? $r['designation'] : 'WODDI Envoy', 'network' => (string) $r['network'], 'level' => 'envoy', 'country' => $r['ecc'] ?: $r['country_code'], 'rank' => 99];
      }
    } catch (PDOException $e) { /* envoys arrive with migration 024 */ }
  }
  foreach ($out as &$x) {
    $x['can_sign_in'] = $x['signs_in_with'] !== '' && !preg_match('/\.(test|local|example)$/', $x['signs_in_with']) && !str_contains($x['signs_in_with'], '@deleted.');
    $x['google'] = str_starts_with((string) $x['password'], 'None. Signs in with Google');
  }
  return array_values($out);
}
function bulk_filters(): array {
  $b = array_merge($_GET, body());
  $cc = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($b['country'] ?? '')), 0, 2));
  $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : '';
  $lv = $b['levels'] ?? 'country,state_region'; $levels = array_values(array_intersect(array_keys(BULK_LEVELS), is_array($lv) ? $lv : explode(',', (string) $lv)));
  if (!$levels) fail(422, 'Choose at least one level.', 'levels');
  return [$cc, $net, $levels];
}

route('GET', '/hq/leader-logins/bulk', function () {
  require_hq(); [$cc, $net, $levels] = bulk_filters();
  $countries = rows("SELECT country_code cc, name FROM org_units WHERE level='country' AND country_code IS NOT NULL ORDER BY name");
  return ['people' => bulk_people($cc, $net, $levels), 'levels' => BULK_LEVELS, 'countries' => $countries, 'max' => BULK_MAX];
});

route('POST', '/hq/leader-logins/bulk', function () {
  $me = require_hq(); [$cc, $net, $levels] = bulk_filters();
  if (empty(body()['confirm'])) fail(422, 'Confirm that their current passwords will stop working.', 'confirm');
  $ids = array_values(array_unique(array_filter(array_map('intval', is_array(body()['ids'] ?? null) ? body()['ids'] : []), fn($x) => $x > 0)));
  if (!$ids) fail(422, 'Tick the people to give logins to.', 'ids');
  if (count($ids) > BULK_MAX) fail(422, 'Give logins to ' . BULK_MAX . ' people at most at once.', 'ids');
  throttle('bulklogin:' . (int) $me['id'], 20, 3600); throttle_hit('bulklogin:' . (int) $me['id']);
  // only people who match the choices on the page: an id from anywhere else is refused, not quietly added
  $people = []; foreach (bulk_people($cc, $net, $levels, $ids) as $p) $people[$p['id']] = $p;
  $missing = array_diff($ids, array_keys($people));
  if ($missing) fail(422, 'Some of the people ticked are not in this list any more. Load the list again.', 'ids');
  $out = []; $skipped = [];
  foreach ($ids as $id) {
    $p = $people[$id];
    if (!$p['can_sign_in']) { $skipped[] = ['name' => $p['name'], 'why' => 'No email or phone number to sign in with']; continue; }
    $temp = temp_password();
    q('UPDATE users SET password_hash=?, must_change_password=1 WHERE id=? AND role NOT IN (\'hq_admin\',\'super_admin\')', [password_hash($temp, PASSWORD_DEFAULT), $id]);
    q('DELETE FROM sessions WHERE user_id=?', [$id]);
    log_activity((int) $me['id'], $id, 'password_reset', 'Login issued in a batch');
    notify($id, 'account', 'WODDI HQ gave you a new login', 'Sign in with the temporary password HQ sends you, then choose your own password.', '#/settings');
    $out[] = ['name' => $p['name'], 'position' => $p['position'], 'network' => $p['network'], 'country' => $p['country'], 'sign_in_with' => $p['signs_in_with'], 'kind' => $p['kind'], 'temporary_password' => $temp];
  }
  log_activity((int) $me['id'], null, 'logins_issued', count($out) . ' logins' . ($cc ? " · $cc" : '') . ($net ? " · $net" : '') . ' · ' . implode(', ', $levels));
  after_response(fn() => push_drain(20));
  header('Cache-Control: no-store');
  return ['ok' => true, 'issued' => $out, 'skipped' => $skipped, 'site' => app_url(), 'at' => now(), 'by' => trim($me['first_name'] . ' ' . $me['last_name'])];
});

/* v109.33: HQ adds a volunteer leader who is not in WDOS yet (Azeez, 19 Nov 2026: "create their designation and logins").
 * WDOS makes her account and records her seat. No password is made here: HQ then gives her a login on this same page,
 * which makes the temporary password and the PDF. She is asked to accept her appointment when she first signs in. */
route('POST', '/hq/leaders/add', function () {
  $me = require_hq(); $b = body();
  $first = str_in('first_name', 80); $last = str_in('last_name', 80); $email = strtolower(str_in('email', 190));
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter the first name and surname.', 'name');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address. She signs in with it.', 'email');
  if (val('SELECT 1 FROM users WHERE email=?', [$email])) fail(409, 'Someone in WDOS already uses this email. Find her in Members and give her the seat there.', 'email_taken');
  $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : fail(422, 'Choose the network.', 'network');
  $pos = row('SELECT * FROM positions WHERE code=?', [str_in('position', 40)]); if (!$pos) fail(422, 'Choose a position.', 'position');
  $unit = row('SELECT * FROM org_units WHERE id=?', [(int) ($b['org_unit_id'] ?? 0)]);
  if (!$unit || $unit['level'] === 'hq') fail(422, 'Choose the country or state this seat covers.', 'unit');
  if ($pos['level'] === 'country' && $unit['level'] !== 'country') fail(422, 'A country seat covers the whole country. Choose the country, not a state.', 'unit');
  if ($pos['level'] !== 'country' && $unit['level'] === 'country') fail(422, 'Choose the state or region this seat covers.', 'unit');
  $area = str_in('area_label', 120);
  if (in_array($pos['level'], ['lga', 'community_cluster', 'chapter'], true) && $area === '') fail(422, 'Name the LGA, city, cluster or chapter this seat covers.', 'area');
  $cc = (string) $unit['country_code'];
  $phone = str_in('phone', 32) !== '' ? envoy_local_phone(str_in('phone', 32), $cc ?: 'NG') : '';
  if ($phone !== '' && !preg_match('/^\+?\d{7,15}$/', $phone)) fail(422, 'Check the phone number.', 'phone');
  if ($phone !== '') { $v = phone_variants($phone); if (val('SELECT id FROM users WHERE phone IN (' . implode(',', array_fill(0, count($v), '?')) . ") AND status<>'removed' LIMIT 1", $v)) fail(409, 'Someone in WDOS already has this phone number. Find her in Members and give her the seat there.', 'phone_taken'); }
  // who already holds this seat: she is added either way (an assistant can be one of several), HQ is told
  $held = rows('SELECT u.first_name, u.last_name FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND s.position_code=? AND s.network=? AND s.org_unit_id=? AND s.area_label=?', [$pos['code'], $net, (int) $unit['id'], $area]);
  $state = $unit['level'] === 'country' ? '' : (string) $unit['name'];
  db()->beginTransaction();
  try {
    $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,phone,network,country_code,org_unit_id,state_region,role,status,signup_method,must_change_password,created_at) VALUES (?,NULL,?,?,?,?,?,?,?,'member','active','staff',1,?)",
      [$email, $first, $last, $phone, $net, $cc ?: null, (int) $unit['id'], $state, now()]);
    q('UPDATE users SET member_no=? WHERE id=?', [sprintf('%s-%s-%06d', $net, $cc ?: 'XX', $id), $id]);
    $title = seat_open($id, $pos['code'], $net, (int) $unit['id'], $area, 'appointed', (int) $me['id']);
    db()->commit();
  } catch (Throwable $e) { db()->rollBack(); throw $e; }
  log_activity((int) $me['id'], $id, 'leader_added', $title);
  return ['ok' => true, 'id' => $id, 'name' => "$first $last", 'title' => $title, 'country' => $cc, 'network' => $net, 'level' => $pos['level'],
    'also_held_by' => array_map(fn($r) => trim($r['first_name'] . ' ' . $r['last_name']), $held)];
});

/* v109.51: logins for a whole network in one click (Azeez, 24 Nov 2026: "create logins for all the leadership structures, network and all
 * countries for that network in a single click, it should be well arranged in pdf").
 * HQ chooses WGMN or WNNN. WDOS lists every seated leader of that network in every country, at every level, and sorts each into one of:
 *   new      no password yet: gets a temporary password
 *   pending  a temporary password was given before and has never been used: kept, unless HQ asks for new ones
 *   active   she already signs in with a password she knows: never changed here
 *   google   she signs in with Google: never changed here
 *   cannot   no email or phone number to sign in with
 * The rule that a working password is never replaced is enforced here on the server, not by the page. Passwords are made in batches
 * of at most NET_CHUNK people per request, returned once and kept only as a hash; the page builds the PDF on HQ's own computer. */
const NET_CHUNK = 100;
const NET_LEVELS = ['country', 'state_region', 'lga', 'community_cluster', 'chapter'];

function netlogin_people(string $net): array {
  // $net is WGMN, WNNN or ALL (both networks). Envoys are always included, as level 'envoy'; the page chooses which levels to use.
  $in = implode(',', array_fill(0, count(NET_LEVELS), '?'));
  $names = []; foreach (rows("SELECT country_code cc, name FROM org_units WHERE level='country' AND country_code IS NOT NULL") as $c) $names[$c['cc']] = $c['name'];
  $out = [];
  $wn = $net === 'ALL' ? "s.network IN ('WGMN','WNNN')" : 's.network=?';
  $rows = rows("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.password_hash, u.must_change_password, u.signup_method, u.last_seen_at,
                       s.network seat_network, s.area_label, p.rank_no, p.level plevel, CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END title,
                       o.name unit_name, o.level unit_level, o.country_code cc
                FROM seats s JOIN users u ON u.id=s.user_id JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id
                WHERE s.ends_at IS NULL AND $wn AND u.status='active' AND u.role NOT IN ('hq_admin','super_admin') AND p.level IN ($in)
                ORDER BY p.rank_no, o.name, s.area_label, u.first_name, u.last_name LIMIT 8000", array_merge($net === 'ALL' ? [] : [$net], NET_LEVELS));
  foreach ($rows as $r) {
    if (isset($out[(int) $r['id']])) continue;   // someone with two seats is listed once, under her highest seat
    $area = $r['area_label'] !== '' ? $r['area_label'] : $r['unit_name'];
    $state = $r['unit_level'] === 'country' ? '' : (string) $r['unit_name'];
    $out[(int) $r['id']] = netlogin_shape($r, $r['title'] . ', ' . $area, (string) $r['plevel'], (int) $r['rank_no'], (string) $r['cc'], $state, $names) + ['network' => $r['seat_network']];
  }
  try {
    $wn = $net === 'ALL' ? '1=1' : "(u.network=? OR u.network IS NULL OR u.network='')";
    foreach (rows("SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.password_hash, u.must_change_password, u.signup_method, u.last_seen_at, u.designation, u.state_region, u.network,
                          COALESCE(NULLIF(e.country_code,''), u.country_code) cc
                   FROM users u JOIN envoys e ON e.user_id=u.id
                   WHERE u.role='envoy' AND u.status='active' AND e.status='active' AND $wn
                   ORDER BY u.state_region, u.first_name LIMIT 4000", $net === 'ALL' ? [] : [$net]) as $r) {
      if (isset($out[(int) $r['id']])) continue;
      $out[(int) $r['id']] = netlogin_shape($r, $r['designation'] !== '' && $r['designation'] !== null ? $r['designation'] : 'WODDI Envoy', 'envoy', 99, (string) $r['cc'], (string) $r['state_region'], $names) + ['network' => (string) $r['network']];
    }
  } catch (PDOException $e) { /* envoys arrive with migration 024 */ }
  $list = array_values($out);
  usort($list, fn($a, $b) => [$a['country_name'], $a['rank'], $a['state'], $a['position'], $a['name']] <=> [$b['country_name'], $b['rank'], $b['state'], $b['position'], $b['name']]);
  return $list;
}
function netlogin_shape(array $r, string $position, string $level, int $rank, string $cc, string $state, array $names): array {
  $phoneLogin = str_ends_with((string) $r['email'], '@phone.wdos.invalid');
  $with = $phoneLogin ? (string) $r['phone'] : (string) $r['email'];
  $can = $with !== '' && !preg_match('/\.(test|local|example)$/', $with) && !str_contains($with, '@deleted.');
  if (!$can) $cat = 'cannot';
  elseif (!$r['password_hash']) $cat = ($r['signup_method'] ?? '') === 'google' ? 'google' : 'new';
  elseif ((int) $r['must_change_password'] === 1 && $r['last_seen_at'] === null) $cat = 'pending';
  else $cat = 'active';
  return ['id' => (int) $r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'position' => $position, 'level' => $level, 'rank' => $rank,
    'country' => $cc, 'country_name' => $names[$cc] ?? ($cc !== '' ? $cc : 'Country not set'), 'state' => $state,
    'sign_in_with' => $with, 'kind' => $phoneLogin ? 'Phone number' : 'Email', 'cat' => $cat, 'last_seen_at' => $r['last_seen_at']];
}
function netlogin_network(): string {
  $b = array_merge($_GET, body());
  return in_array($b['network'] ?? '', ['WGMN', 'WNNN', 'ALL'], true) ? $b['network'] : fail(422, 'Choose the network: WGMN, WNNN or both.', 'network');
}

route('GET', '/hq/leader-logins/network', function () {
  require_hq(); $net = netlogin_network();
  $people = netlogin_people($net); $cs = [];
  foreach ($people as $p) { $k = $p['country']; $cs[$k] ??= ['cc' => $k, 'name' => $p['country_name'], 'count' => 0]; $cs[$k]['count']++; }
  header('Cache-Control: no-store');
  return ['network' => $net, 'people' => $people, 'countries' => array_values($cs), 'levels' => ['country' => 'Country', 'state_region' => 'State or region', 'lga' => 'LGA, district or city',
    'community_cluster' => 'Ward, community or cluster', 'chapter' => 'Chapter', 'envoy' => 'WODDI Envoys'], 'chunk' => NET_CHUNK];
});

route('POST', '/hq/leader-logins/network', function () {
  $me = require_hq(); $net = netlogin_network(); $b = body();
  $reissue = !empty($b['reissue_unused']);
  if ($reissue && empty($b['confirm'])) fail(422, 'Confirm that the earlier temporary passwords will stop working.', 'confirm');
  $ids = array_values(array_unique(array_filter(array_map('intval', is_array($b['ids'] ?? null) ? $b['ids'] : []), fn($x) => $x > 0)));
  if (!$ids) fail(422, 'There is nobody to give a login to.', 'ids');
  if (count($ids) > NET_CHUNK) fail(422, 'Send at most ' . NET_CHUNK . ' people in one batch.', 'ids');
  throttle('netlogin:' . (int) $me['id'], 150, 3600); throttle_hit('netlogin:' . (int) $me['id']);
  $all = []; foreach (netlogin_people($net) as $p) $all[$p['id']] = $p;
  foreach ($ids as $id) {
    $p = $all[$id] ?? null;
    if (!$p) fail(422, 'Some of these people are not seated leaders of ' . ($net === 'ALL' ? 'WGMN or WNNN' : $net) . ' any more. Load the list again.', 'ids');
    if ($p['cat'] === 'active' || $p['cat'] === 'google') fail(422, $p['name'] . ' already signs in to WDOS. Her password is never replaced here.', 'in_use');
    if ($p['cat'] === 'cannot') fail(422, $p['name'] . ' has no email or phone number to sign in with.', 'no_contact');
    if ($p['cat'] === 'pending' && !$reissue) fail(422, $p['name'] . ' already has a temporary password that is not used yet.', 'pending');
  }
  $out = [];
  foreach ($ids as $id) {
    $p = $all[$id]; $temp = temp_password();
    // the WHERE repeats the server's rule, so a password she set herself is never overwritten even if she signed in a moment ago
    $n = q("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=? AND role NOT IN ('hq_admin','super_admin')
            AND (password_hash IS NULL OR (must_change_password=1 AND last_seen_at IS NULL))", [password_hash($temp, PASSWORD_DEFAULT), $id])->rowCount();
    if (!$n) continue;
    q('DELETE FROM sessions WHERE user_id=?', [$id]);
    log_activity((int) $me['id'], $id, 'password_reset', 'Login issued for the whole ' . ($net === 'ALL' ? 'WGMN and WNNN' : $net) . ' network');
    notify($id, 'account', 'WODDI HQ gave you a WDOS login', 'Sign in with the temporary password HQ sends you, then choose your own password.', '#/settings');
    $out[] = ['id' => $id, 'temporary_password' => $temp];
  }
  $where = preg_replace('/[^A-Z,]/', '', strtoupper(is_array($b['countries'] ?? null) ? implode(',', $b['countries']) : (string) ($b['countries'] ?? '')));
  log_activity((int) $me['id'], null, 'logins_issued', count($out) . ' logins · ' . ($net === 'ALL' ? 'WGMN and WNNN' : $net) . ' · ' . ($where !== '' ? $where : 'all countries') . ($reissue ? ' · unused ones replaced' : ''));
  after_response(fn() => push_drain(20));
  header('Cache-Control: no-store');
  return ['ok' => true, 'issued' => $out, 'site' => app_url(), 'at' => now(), 'by' => trim($me['first_name'] . ' ' . $me['last_name'])];
});
