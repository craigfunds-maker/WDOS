<?php
declare(strict_types=1);
/* Bring members and leaders across from the current (Supabase) WDOS.
 * Input: rows from the export query on the setup page, sent in small batches.
 * Passwords arrive as the existing bcrypt hashes and are stored as they are,
 * so every member keeps the password she already uses. Nothing is ever shown.
 * Rules: an e-mail that already exists here is left untouched; HQ access is
 * never granted by an import; a dry run does everything inside a transaction
 * and rolls it back. */

const IMPORT_POSITIONS = ['country_rep', 'deputy_country_rep', 'state_coordinator', 'assistant_state_coordinator', 'state_secretary', 'district_coordinator',
  'assistant_district_coordinator', 'cluster_coordinator', 'assistant_cluster_coordinator', 'chapter_lead', 'assistant_chapter_lead'];

function imp_norm(string $s): string {
  $s = mb_strtolower(trim($s));
  $s = preg_replace('/\b(state|region|province|county)\b/u', ' ', $s);
  return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
}
function imp_geo(): array {
  static $g = null;
  if ($g) return $g;
  $g = ['code' => [], 'name' => [], 'states' => []];
  foreach (rows("SELECT id, name, country_code, path FROM org_units WHERE level='country'") as $c) { $g['code'][$c['country_code']] = $c; $g['name'][imp_norm($c['name'])] = $c; }
  $alias = json_decode((string) @file_get_contents(__DIR__ . '/countries.json'), true) ?: [];
  foreach ($alias as $name => $code) if (isset($g['code'][$code])) $g['name'][imp_norm((string) $name)] = $g['code'][$code];
  foreach (rows("SELECT id, parent_id, name FROM org_units WHERE level='state_region'") as $s) $g['states'][(int) $s['parent_id']][imp_norm($s['name'])] = (int) $s['id'];
  return $g;
}
function imp_country(string ...$cands): ?array {
  $g = imp_geo();
  foreach ($cands as $c) {
    $c = trim($c); if ($c === '') continue;
    if (strlen($c) === 2 && isset($g['code'][strtoupper($c)])) return $g['code'][strtoupper($c)];
    if (isset($g['name'][imp_norm($c)])) return $g['name'][imp_norm($c)];
  }
  return null;
}
/** Find a state/region under a country; create it only when it comes from the official structure. */
function imp_state(array $country, string $name, bool $official, array &$sum): ?int {
  static $made = [];
  $key = imp_norm($name); if ($key === '') return null;
  $g = imp_geo(); $cid = (int) $country['id'];
  if (isset($g['states'][$cid][$key])) return $g['states'][$cid][$key];
  if (isset($made[$cid][$key])) return $made[$cid][$key];
  if (!$official) return null;
  $id = ins("INSERT INTO org_units (parent_id,level,name,country_code,path) VALUES (?,?,?,?,'')", [$cid, 'state_region', mb_substr(trim($name), 0, 160), $country['country_code']]);
  q('UPDATE org_units SET path=? WHERE id=?', [$country['path'] . $id . '/', $id]);
  $made[$cid][$key] = $id; $sum['new_units'][] = trim($name) . ', ' . $country['name'];
  return $id;
}
function imp_time(?string $s): ?string { $t = $s ? strtotime($s) : false; return $t ? gmdate('Y-m-d H:i:s', $t) : null; }

function import_rows(array $list, bool $commit): array {
  $sum = ['rows' => 0, 'created' => 0, 'already' => 0, 'invalid' => 0, 'with_password' => 0, 'google_only' => 0, 'no_login' => 0, 'wgmn' => 0, 'wnnn' => 0, 'no_network' => 0,
    'no_country' => 0, 'suspended' => 0, 'seats' => 0, 'seat_notes' => [], 'hq_holders' => [], 'new_units' => [], 'no_country_list' => []];
  $pdo = db(); $pdo->beginTransaction();
  try {
    foreach ($list as $r) {
      if (!is_array($r)) continue;
      $r = array_map(fn($v) => is_scalar($v) ? trim((string) $v) : '', $r);
      // Supabase writes the word "null" into the CSV for an empty value: treat it as empty (names excepted, Null is a real surname)
      foreach ($r as $k => $val) if ($k !== 'first_name' && $k !== 'last_name' && strcasecmp($val, 'null') === 0) $r[$k] = '';
      $v = fn(string $k) => $r[$k] ?? '';
      $sum['rows']++;
      $email = strtolower($v('email'));
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { $sum['invalid']++; continue; }
      if (val('SELECT id FROM users WHERE email=?', [$email])) { $sum['already']++; continue; }
      $hash = preg_match('/^\$2[aby]\$\d\d\$.{53}$/', $v('password_hash')) ? $v('password_hash') : null;
      $gsub = $v('google_sub') !== '' ? mb_substr($v('google_sub'), 0, 64) : null;
      if ($gsub && val('SELECT id FROM users WHERE google_sub=?', [$gsub])) $gsub = null;
      $net = in_array(strtoupper($v('network')), ['WGMN', 'WNNN'], true) ? strtoupper($v('network')) : null;
      $country = imp_country($v('unit_country_iso'), $v('unit_country'), $v('profile_country'));
      $stateName = $v('unit_state') !== '' ? $v('unit_state') : $v('state_region');
      $unit = null;
      if ($country) { $sid = imp_state($country, $stateName, $v('unit_state') !== '', $sum); $unit = $sid ?: (int) $country['id']; if ($sid) $stateName = (string) val('SELECT name FROM org_units WHERE id=?', [$sid]); }
      else { $sum['no_country']++; if (count($sum['no_country_list']) < 25) $sum['no_country_list'][] = $email . ($v('profile_country') !== '' ? ' (' . $v('profile_country') . ')' : ''); }
      $gone = $v('banned') === 't' || $v('banned') === 'true' || in_array(strtolower($v('status')), ['suspended', 'exited', 'removed', 'rejected', 'withdrawn', 'declined', 'inactive', 'deactivated'], true);
      $dob = $v('birth_date'); $d = DateTime::createFromFormat('Y-m-d', substr($dob, 0, 10)); $dob = ($d && $d->format('Y-m-d') === substr($dob, 0, 10)) ? substr($dob, 0, 10) : null;
      $id = ins('INSERT INTO users (email,password_hash,google_sub,first_name,last_name,phone,dob,network,country_code,org_unit_id,state_region,lga,community,role,status,signup_method,last_seen_at,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$email, $hash, $gsub, mb_substr($v('first_name'), 0, 80), mb_substr($v('last_name'), 0, 80), mb_substr(preg_replace('/[^\d+]/', '', $v('phone')), 0, 32), $dob, $net,
         $country['country_code'] ?? null, $unit, mb_substr($stateName, 0, 120), mb_substr($v('lga') !== '' ? $v('lga') : $v('unit_lga'), 0, 120), mb_substr($v('community'), 0, 120),
         'member', $gone ? 'suspended' : 'active', 'import', imp_time($v('last_sign_in_at')), imp_time($v('created_at')) ?? now()]);
      $no = mb_substr($v('membership_no'), 0, 24);
      if ($no === '' || val('SELECT id FROM users WHERE member_no=?', [$no])) $no = sprintf('%s-%s-%06d', $net ?? 'WODDI', $country['country_code'] ?? 'XX', $id);
      q('UPDATE users SET member_no=? WHERE id=?', [$no, $id]);
      $sum['created']++;
      $hash ? $sum['with_password']++ : ($gsub ? $sum['google_only']++ : $sum['no_login']++);
      $net === 'WGMN' ? $sum['wgmn']++ : ($net === 'WNNN' ? $sum['wnnn']++ : $sum['no_network']++);
      if ($gone) $sum['suspended']++;
      if ($v('hq_roles') !== '' || $v('hq_function') !== '') { if (count($sum['hq_holders']) < 60) $sum['hq_holders'][] = $email . ' (' . trim($v('hq_function') . ' ' . $v('hq_roles')) . ')'; }
      /* leadership seat */
      $role = $v('seat_role');
      if ($role !== '') {
        $who = trim($v('first_name') . ' ' . $v('last_name')) . " <$email>";
        $sc = imp_country($v('seat_country_iso'), $v('seat_country'));
        if (!in_array($role, IMPORT_POSITIONS, true)) { $sum['seat_notes'][] = "$who: role \"$role\" is not a leadership position here, not seated"; }
        elseif (!$sc) { $sum['seat_notes'][] = "$who: $role is seated at HQ or at a unit with no country, not seated"; }
        elseif (!$net) { $sum['seat_notes'][] = "$who: $role has no network on the profile, not seated"; }
        else {
          $level = (string) val('SELECT level FROM positions WHERE code=?', [$role]);
          $sunit = (int) $sc['id']; $area = '';
          if ($level !== 'country') { $sid = imp_state($sc, $v('seat_state'), true, $sum); if ($sid) $sunit = $sid; elseif ($level === 'state_region') { $sum['seat_notes'][] = "$who: $role has no state in the structure, seated at country level"; } }
          if ($level === 'lga') $area = $v('seat_lga');
          if ($level === 'community_cluster') $area = $v('seat_cluster') !== '' ? $v('seat_cluster') : $v('seat_lga');
          if ($level === 'chapter') $area = $v('seat_chapter') !== '' ? $v('seat_chapter') : ($v('seat_cluster') !== '' ? $v('seat_cluster') : $v('seat_lga'));
          if (in_array($level, ['lga', 'community_cluster', 'chapter'], true) && $area === '') $area = 'To be confirmed';
          $st = in_array($v('seat_status'), ['appointed', 'acting', 'pending'], true) ? $v('seat_status') : 'appointed';
          q('INSERT INTO seats (user_id,position_code,network,org_unit_id,area_label,status,starts_at) VALUES (?,?,?,?,?,?,?)', [$id, $role, $net, $sunit, mb_substr($area, 0, 120), $st, imp_time($v('seat_since')) ?? now()]);
          $sum['seats']++;
        }
      }
    }
    if ($commit) { if ($sum['created']) log_activity(null, null, 'import', $sum['created'] . ' accounts brought across from the current WDOS'); $pdo->commit(); } else $pdo->rollBack();
  } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  return $sum;
}
