<?php
declare(strict_types=1);
/* v109.29: WODDI State Envoys from a register (Azeez, 19 Nov 2026: the Nigeria state envoys register; "use their phone number
 * without +234 and I should be able to bulk create sign-in details for them").
 * Each envoy signs in with her phone number in its local form (0803..., not +234 803...). No password is set here: HQ gives the
 * sign-in details in one go from WODDI Envoys > Sign-in details for many envoys (the Leader logins page, envoys only).
 * A number already on WDOS is not added twice; the existing account is named so HQ can decide. Several envoys in one state is fine. */

/** $rows: [['state' => 'Abia', 'name' => 'Prof. Angela ...', 'phone' => '0806...'], ...]. Returns what was added and what was not. */
function envoy_import_rows(array $rows, int $byId, string $cc = 'NG', string $net = 'WGMN'): array {
  $country = row("SELECT id, name FROM org_units WHERE level='country' AND country_code=?", [$cc]);
  if (!$country) fail(422, 'That country is not in WDOS.', 'country');
  $added = []; $skipped = [];
  foreach ($rows as $r) {
    $name = trim(preg_replace('/\s+/', ' ', (string) ($r['name'] ?? ''))); $state = trim((string) ($r['state'] ?? ''));
    $phone = envoy_local_phone((string) ($r['phone'] ?? ''), $cc);
    if (mb_strlen($name) < 2 || strlen(ltrim($phone, '+')) < 8) { $skipped[] = ['name' => $name, 'state' => $state, 'why' => 'No usable name or phone number']; continue; }
    $vs = phone_variants($phone);
    $have = row('SELECT id, first_name, last_name, role FROM users WHERE phone IN (' . implode(',', array_fill(0, count($vs), '?')) . ') OR email IN (' . implode(',', array_fill(0, count($vs), '?')) . ') LIMIT 1',
      array_merge($vs, array_map('phone_login_name', $vs)));
    if ($have) { $skipped[] = ['name' => $name, 'state' => $state, 'why' => 'This phone number is already on WDOS: ' . trim($have['first_name'] . ' ' . $have['last_name']) . ' (' . $have['role'] . ')', 'user_id' => (int) $have['id']]; continue; }
    $unitName = strtoupper($state) === 'FCT' ? 'Federal Capital Territory' : $state;
    $unit = row("SELECT id, name FROM org_units WHERE parent_id=? AND (name=? OR name=?) LIMIT 1", [(int) $country['id'], $unitName, $state . ' State']);
    // the title stays with the name as it was recorded ("Prof. Angela", "Hajia Halima"); one-word names have no surname
    $parts = explode(' ', $name); $titles = ['prof', 'prof.', 'dr', 'dr.', 'mrs', 'mrs.', 'mr', 'mr.', 'ms', 'ms.', 'miss', 'rev', 'rev.', 'hajia', 'haj', 'haj.', 'hajiya', 'alhaja', 'barr', 'barr.', 'madam', 'chief', 'engr', 'engr.', 'pastor', 'hon', 'hon.'];
    $lead = []; while (count($parts) > 1 && in_array(strtolower($parts[0]), $titles, true)) $lead[] = array_shift($parts);
    $first = trim(implode(' ', $lead) . ' ' . array_shift($parts)); $last = implode(' ', $parts);
    $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,phone,role,status,signup_method,must_change_password,country_code,network,org_unit_id,state_region,designation,created_at)
               VALUES (?,NULL,?,?,?,'envoy','active','staff',1,?,?,?,?,?,?)",
      [phone_login_name($phone), mb_substr($first, 0, 80), mb_substr($last, 0, 80), $phone, $cc, $net, $unit ? (int) $unit['id'] : (int) $country['id'], $unit ? $unit['name'] : $unitName, mb_substr('WODDI State Envoy, ' . ($unit ? $unit['name'] : $unitName), 0, 160), now()]);
    $lid = ins("INSERT INTO referral_links (code,owner_id,kind,network,label,campaign,created_at) VALUES (?,?,'envoy',?,?,'envoy',?)", [ref_new_code(), $id, $net, 'WODDI State Envoy: ' . $name, now()]);
    q('INSERT INTO envoys (user_id,link_id,country_code,status,created_by,created_at) VALUES (?,?,?,?,?,?)', [$id, $lid, $cc, 'active', $byId, now()]);
    log_activity($byId, $id, 'envoy_created', 'State envoy, ' . ($unit ? $unit['name'] : $unitName) . ' (from a register)');
    $added[] = ['id' => $id, 'name' => $name, 'state' => $unit ? $unit['name'] : $unitName, 'phone' => $phone, 'state_found' => (bool) $unit];
  }
  return ['added' => $added, 'skipped' => $skipped];
}

/** HQ adds envoys from a register: rows of state, name and phone. */
route('POST', '/hq/envoys/import', function () {
  $me = require_hq(); $rows = body()['rows'] ?? null;
  if (!is_array($rows) || !$rows) fail(422, 'Give the envoys to add.', 'rows');
  if (count($rows) > 500) fail(422, 'Add 500 envoys at most at once.', 'rows');
  $cc = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) (body()['country'] ?? 'NG')), 0, 2)) ?: 'NG';
  $net = in_array(body()['network'] ?? '', ['WGMN', 'WNNN'], true) ? body()['network'] : 'WGMN';
  $r = envoy_import_rows(array_map(fn($x) => is_array($x) ? $x : [], $rows), (int) $me['id'], $cc, $net);
  log_activity((int) $me['id'], null, 'envoys_imported', count($r['added']) . ' added, ' . count($r['skipped']) . ' not added');
  return ['ok' => true] + $r;
});
