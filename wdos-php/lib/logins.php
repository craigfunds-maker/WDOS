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
