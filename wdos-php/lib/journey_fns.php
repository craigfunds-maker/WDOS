<?php
declare(strict_types=1);
/* Structure, referral and activation: shared functions. Loaded before every route, because joining
 * (in api/index.php) records the referral and opens the activation. Wording that a candidate reads is
 * taken from the current WDOS; programme content (days, questions, assessment) is data. */

const ACT_ROUTES = ['ref3' => ['label' => '3-Day Referral Activation', 'days' => 3], 'open7' => ['label' => '7-Day Activation', 'days' => 7]];
const ACT_NOTICE = 'Referral and activation are important steps in the process, but they do not guarantee appointment. Activation helps assess readiness for the role.';
const REF_CATS = ['focal_person' => 'A WODDI focal person or referral lead', 'volunteer_leader' => 'An existing WODDI volunteer leader', 'individual' => 'An individual who knows WODDI', 'other' => 'Other'];
const ACT_OUTCOMES = ['selected' => 'Selected', 'reserve' => 'Reserve', 'further_review' => 'Further review', 'alternative_role' => 'Alternative role offered', 'not_selected' => 'Not selected'];

function jn_json(?string $s): array { $d = json_decode((string) $s, true); return is_array($d) ? $d : []; }
function position_title(string $code, string $network): string {
  $p = row('SELECT title_wgmn, title_wnnn FROM positions WHERE code=?', [$code]);
  return $p ? ($network === 'WNNN' ? $p['title_wnnn'] : $p['title_wgmn']) : '';
}

/** Open a leadership seat (closing any seat the person already holds). New seats wait for the leader to accept the Agreement. */
function seat_open(int $userId, string $position, string $network, int $unitId, string $area, string $status, int $byId): string {
  q('UPDATE seats SET ends_at=? WHERE user_id=? AND ends_at IS NULL', [now(), $userId]);
  q("INSERT INTO seats (user_id,position_code,network,org_unit_id,area_label,status,appointed_by,starts_at,acceptance) VALUES (?,?,?,?,?,?,?,?,'pending')", [$userId, $position, $network, $unitId, $area, $status, $byId, now()]);
  $title = position_title($position, $network) . ', ' . ($area !== '' ? $area : (string) val('SELECT name FROM org_units WHERE id=?', [$unitId]));
  log_activity($byId, $userId, 'appointed', $title);
  notify($userId, 'appointment', 'You have been appointed ' . $title, 'WODDI HQ has recorded your leadership seat. Open WDOS to read and accept the Volunteer Leadership Agreement.', '#/appointment');
  return $title;
}
/** What is still waiting for this person. Safe before the tables exist (first load after an update). */
function person_todo(array $u, ?array $seat): array {
  $t = ['appointment' => false, 'onboarding' => 0, 'activation' => false];
  try {
    if ($seat && ($seat['acceptance'] ?? '') !== '') {
      $t['appointment'] = $seat['acceptance'] === 'pending';
      $t['onboarding'] = (int) val('SELECT COUNT(*) FROM onboarding_steps s WHERE s.is_active=1 AND s.required=1 AND NOT EXISTS (SELECT 1 FROM onboarding_progress p WHERE p.user_id=? AND p.step_code=s.code)', [(int) $u['id']]);
    }
    if (!$seat && !is_hq($u)) $t['activation'] = (bool) val('SELECT 1 FROM activations WHERE user_id=? LIMIT 1', [(int) $u['id']]);
  } catch (PDOException $e) { /* tables arrive with the next migration run */ }
  return $t;
}

/* ---- referral links -------------------------------------------------------- */
function ref_new_code(): string {
  do { $c = ''; $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; for ($i = 0; $i < 8; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)]; } while (val('SELECT 1 FROM referral_links WHERE code=?', [$c]));
  return $c;
}
function ref_link_shape(array $l): array {
  $unit = $l['org_unit_id'] ? (string) val('SELECT name FROM org_units WHERE id=?', [(int) $l['org_unit_id']]) : '';
  return ['id' => (int) $l['id'], 'code' => $l['code'], 'kind' => $l['kind'], 'network' => $l['network'], 'position' => $l['position_code'], 'role' => $l['position_code'] !== '' ? position_title($l['position_code'], $l['network']) : '',
    'place' => trim(($l['area_label'] !== '' ? $l['area_label'] . ', ' : '') . $unit, ', '), 'org_unit_id' => $l['org_unit_id'] ? (int) $l['org_unit_id'] : null, 'area_label' => $l['area_label'],
    'label' => $l['label'], 'campaign' => $l['campaign'], 'active' => (int) $l['active'] === 1, 'created_at' => $l['created_at']];
}
function ref_link_by_code(string $code): ?array {
  $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
  return $code === '' ? null : row('SELECT l.*, u.first_name o_first, u.last_name o_last FROM referral_links l JOIN users u ON u.id=l.owner_id WHERE l.code=? AND l.active=1', [$code]);
}
/** A leader's own membership link: one per leader, made the first time it is asked for. */
function ref_member_link(array $u, array $seat): array {
  $l = row("SELECT * FROM referral_links WHERE owner_id=? AND kind='member' AND network=? AND campaign='' ORDER BY id LIMIT 1", [(int) $u['id'], $seat['network']]);
  if (!$l) {
    $id = ins("INSERT INTO referral_links (code,owner_id,kind,network,org_unit_id,area_label,label,created_at) VALUES (?,?,'member',?,?,?,?,?)", [ref_new_code(), (int) $u['id'], $seat['network'], (int) $seat['org_unit_id'], (string) $seat['area_label'], 'Join as a member', now()]);
    $l = row('SELECT * FROM referral_links WHERE id=?', [$id]);
  }
  return $l;
}

/* ---- activation ------------------------------------------------------------ */
function activation_start(int $userId, string $route, string $network, string $position, int $unitId, string $area, string $referrer): int {
  $open = row("SELECT id FROM activations WHERE user_id=? AND status IN ('in_progress','returned','submitted') ORDER BY id DESC LIMIT 1", [$userId]);
  if ($open) return (int) $open['id'];
  $deadline = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('+' . ACT_ROUTES[$route]['days'] . ' days')->format('Y-m-d');
  return ins('INSERT INTO activations (user_id,route,network,position_code,org_unit_id,area_label,referrer_name,status,deadline,started_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
    [$userId, $route, $network, $position, $unitId, mb_substr($area, 0, 120), mb_substr($referrer, 0, 160), 'in_progress', $deadline, now()]);
}
/** Called when someone joins. $ref comes from the joining form: {code, position, category, name}. Never blocks the signup. */
function referral_attach(int $userId, array $f, $ref): ?int {
  if (!is_array($ref)) return null;
  try {
    $link = ref_link_by_code((string) ($ref['code'] ?? ''));
    if ($link && $link['network'] !== $f['network']) $link = null;                     // a WGMN link cannot place someone in WNNN
    $position = $link && $link['position_code'] !== '' ? $link['position_code'] : (string) ($ref['position'] ?? '');
    $pos = $position !== '' ? row('SELECT * FROM positions WHERE code=?', [$position]) : null;
    $cat = isset(REF_CATS[$ref['category'] ?? '']) ? $ref['category'] : ($link ? 'volunteer_leader' : '');
    $name = $link ? trim($link['o_first'] . ' ' . $link['o_last']) : mb_substr(trim((string) ($ref['name'] ?? '')), 0, 160);
    if (!$link && !$pos) return null;
    q('DELETE FROM referrals WHERE user_id=?', [$userId]);
    q('INSERT INTO referrals (user_id,link_id,referrer_id,category,referrer_name,position_code,created_at) VALUES (?,?,?,?,?,?,?)', [$userId, $link ? (int) $link['id'] : null, $link ? (int) $link['owner_id'] : null, $cat, $name, $pos ? $pos['code'] : '', now()]);
    $who = trim($f['first_name'] . ' ' . $f['last_name']);
    if ($link) notify((int) $link['owner_id'], 'activation', $who . ' registered through your link', $pos ? 'Referred for ' . position_title($pos['code'], $f['network']) . '. The referral activation has started.' : 'Joined ' . $f['network'] . ' as a member.', '#/referrals');
    if (!$pos) return null;
    $unitId = $link && $link['org_unit_id'] ? (int) $link['org_unit_id'] : (int) $f['org_unit_id'];
    if ($pos['level'] === 'country') $unitId = (int) (val("SELECT id FROM org_units WHERE level='country' AND country_code=?", [$f['country_code']]) ?? $unitId);
    $area = $link && $link['area_label'] !== '' ? $link['area_label'] : ($pos['level'] === 'lga' ? $f['lga'] : (in_array($pos['level'], ['community_cluster', 'chapter'], true) ? $f['community'] : ''));
    $aid = activation_start($userId, 'ref3', $f['network'], $pos['code'], $unitId, (string) $area, $name);
    notify_hq('activation', 'Referral activation started: ' . $who, position_title($pos['code'], $f['network']) . ', ' . $f['state_region'] . ', ' . country_name($f['country_code']) . ($name !== '' ? ". Referred by $name." : '.'), '#/referrals/activations/' . $aid);
    return $aid;
  } catch (Throwable $e) { error_log('[wdos referral] ' . $e->getMessage()); return null; }
}
function activation_days(string $route): array {
  $out = [];
  foreach (rows('SELECT * FROM activation_days WHERE route=? ORDER BY day_no', [$route]) as $d) {
    $out[] = ['day' => (int) $d['day_no'], 'title' => $d['title'], 'instruction' => $d['instruction'], 'content' => jn_json($d['content']), 'notice' => $d['notice'], 'questions' => jn_json($d['questions']), 'acks' => jn_json($d['acks'])];
  }
  return $out;
}
function readiness_form(): array {
  $f = ['statements' => [], 'questions' => [], 'confirmations' => []];
  foreach (rows('SELECT * FROM readiness_items ORDER BY kind, seq, id') as $r) {
    $k = ['statement' => 'statements', 'question' => 'questions', 'confirmation' => 'confirmations'][$r['kind']] ?? null; if (!$k) continue;
    $f[$k][] = ['seq' => (int) $r['seq'], 'section' => $r['section'], 'section_title' => $r['section_title'], 'text' => $r['body']];
  }
  return $f;
}
/** Is the whole programme for this route loaded, so a candidate can finish? */
function activation_content_ready(string $route): array {
  $days = (int) val('SELECT COUNT(*) FROM activation_days WHERE route=?', [$route]);
  $assess = (int) val("SELECT COUNT(*) FROM readiness_items WHERE kind='statement'");
  return ['days_loaded' => $days, 'days_expected' => ACT_ROUTES[$route]['days'], 'assessment' => $assess > 0, 'complete' => $days >= ACT_ROUTES[$route]['days'] && $assess > 0];
}
