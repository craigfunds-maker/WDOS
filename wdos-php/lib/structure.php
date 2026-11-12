<?php
declare(strict_types=1);
/* Leadership structure (every seat in scope, filled or vacant), appointment acceptance and guided onboarding. */

const STRUCTURE_COUNTRIES = 'AE AO AT AU BE BF BI BJ BR BW CA CD CF CG CH CI CM CN CV DE DJ DZ EG ER ES ET FR GA GB GH GM GN GQ GW IE IN IT JM KE KM LR LS LY MA MG ML MR MU MW MY MZ NA NE NG NL NO NZ PT QA RW SA SC SD SE SL SN SO SS ST SZ TD TG TN TR TT TZ UG US ZA ZM ZW';
route('GET', '/structure', function () {
  $u = require_user(); $seat = work_seat($u);
  if (!is_hq($u) && !$seat) fail(403, 'The leadership structure opens for volunteer leaders and WODDI HQ.', 'forbidden');
  $path = $seat ? $seat['unit_path'] : '/'; $nets = $seat ? [$seat['network']] : ['WGMN', 'WNNN'];
  $steps = (int) val('SELECT COUNT(*) FROM onboarding_steps WHERE is_active=1 AND required=1');
  $filled = rows("SELECT s.id seat_id, s.user_id, s.network, s.position_code, s.area_label, s.status, s.acceptance, s.he_for_she, s.starts_at, p.level, p.rank_no, ou.id unit_id, ou.name unit, ou.level unit_level, ou.country_code, ou.path,
        u.first_name, u.last_name, u.has_photo, u.last_seen_at, CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END title,
        (SELECT COUNT(*) FROM onboarding_progress op JOIN onboarding_steps os ON os.code=op.step_code AND os.is_active=1 AND os.required=1 WHERE op.user_id=s.user_id) ob_done
      FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units ou ON ou.id=s.org_unit_id JOIN users u ON u.id=s.user_id
      WHERE s.ends_at IS NULL AND ou.path LIKE ? AND s.network IN ('" . implode("','", $nets) . "') ORDER BY ou.path, p.rank_no", [$path . '%']);
  $out = []; $have = [];
  foreach ($filled as $f) {
    $have[$f['network'] . '|' . $f['unit_id'] . '|' . $f['position_code']] = true;
    $out[] = ['kind' => 'filled', 'network' => $f['network'], 'level' => $f['level'], 'position' => $f['position_code'], 'title' => $f['title'], 'unit' => $f['unit'], 'unit_id' => (int) $f['unit_id'], 'country_code' => $f['country_code'],
      'area' => $f['area_label'], 'status' => $f['status'], 'acceptance' => $f['acceptance'], 'since' => $f['starts_at'], 'rank' => (int) $f['rank_no'],
      'holder' => ['id' => (int) $f['user_id'], 'first_name' => $f['first_name'], 'last_name' => $f['last_name'], 'has_photo' => (int) $f['has_photo'], 'last_seen_at' => $f['last_seen_at']],
      'onboarding' => $f['acceptance'] !== '' ? [(int) $f['ob_done'], $steps] : null];
  }
  // Vacant seats can only be named where the place itself is on the list: countries, and states where they are loaded.
  /* The 81 countries WODDI's structure was drawn up for always show their seats. The other countries of the world were added in v105 so that
     any woman can register; each joins the structure as soon as it has a member or an appointed leader. */
  $active = array_flip(explode(' ', STRUCTURE_COUNTRIES));
  foreach (rows("SELECT DISTINCT country_code c FROM users WHERE status<>'pending_profile' AND country_code<>'' UNION SELECT DISTINCT ou.country_code FROM seats s JOIN org_units ou ON ou.id=s.org_unit_id WHERE s.ends_at IS NULL") as $r) $active[$r['c']] = true;
  $lead = ['country' => 'country_rep', 'state_region' => 'state_coordinator']; $rank = ['country' => 1, 'state_region' => 3];
  foreach (rows("SELECT id, name, level, country_code FROM org_units WHERE level IN ('country','state_region') AND path LIKE ? ORDER BY path", [$path . '%']) as $ou) {
    if ($seat && $seat['position_level'] === $ou['level'] && (int) $seat['org_unit_id'] === (int) $ou['id']) continue;
    if ($ou['level'] === 'country' && !isset($active[$ou['country_code']])) continue;      // a country added for registration only shows its vacant seats once WODDI is present there
    foreach ($nets as $net) {
      if (isset($have[$net . '|' . $ou['id'] . '|' . $lead[$ou['level']]])) continue;
      $out[] = ['kind' => 'vacant', 'network' => $net, 'level' => $ou['level'], 'position' => $lead[$ou['level']], 'title' => position_title($lead[$ou['level']], $net), 'unit' => $ou['name'], 'unit_id' => (int) $ou['id'],
        'country_code' => $ou['country_code'], 'area' => '', 'status' => 'vacant', 'acceptance' => '', 'since' => null, 'rank' => $rank[$ou['level']], 'holder' => null, 'onboarding' => null];
    }
  }
  $countries = rows("SELECT country_code code, name FROM org_units WHERE level='country' AND path LIKE ? ORDER BY name", [$path . '%']);
  $countries = array_values(array_filter($countries, fn($c) => isset($active[$c['code']])));
  return ['rows' => $out, 'countries' => $countries, 'networks' => $nets, 'is_hq' => is_hq($u), 'scope' => $seat ? seat_title($seat) : 'All of WODDI'];
});

/* ------------------------------------------------- appointment acceptance -- */
route('GET', '/appointment', function () {
  $u = require_user(); $seat = work_seat($u);
  if (!$seat) return ['seat' => null];
  return ['seat' => ['title' => seat_title($seat), 'network' => $seat['network'], 'status' => $seat['status'], 'acceptance' => $seat['acceptance'] ?? '', 'accepted_at' => $seat['accepted_at'] ?? null, 'since' => $seat['starts_at']], 'doc' => '/assets/docs/volunteer-agreement.html'];
});
route('POST', '/appointment', function () {
  $u = require_user(); $seat = work_seat($u);
  if (!$seat || !in_array($seat['acceptance'] ?? '', ['pending', 'declined'], true)) fail(409, 'There is no appointment waiting for your answer.', 'none');
  $accept = !empty(body()['accept']);
  q('UPDATE seats SET acceptance=?, accepted_at=? WHERE id=?', [$accept ? 'accepted' : 'declined', $accept ? now() : null, (int) $seat['id']]);
  log_activity((int) $u['id'], (int) $u['id'], $accept ? 'agreement_accepted' : 'agreement_declined', seat_title($seat));
  notify_hq('appointment', work_name($u) . ($accept ? ' accepted the appointment' : ' declined the Agreement'), seat_title($seat) . ' (' . $seat['network'] . ').', '#/members/' . $u['id']);
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'acceptance' => $accept ? 'accepted' : 'declined'];
});

/* ------------------------------------------------------------- onboarding -- */
function onboarding_state(int $userId): array {
  $steps = rows('SELECT s.code, s.seq, s.title, s.detail, s.mode, s.doc_url, s.href, s.min_seconds, s.required, p.done_at, p.note FROM onboarding_steps s
                 LEFT JOIN onboarding_progress p ON p.step_code=s.code AND p.user_id=? WHERE s.is_active=1 ORDER BY s.seq, s.code', [$userId]);
  foreach ($steps as &$s) { $s['seq'] = (int) $s['seq']; $s['min_seconds'] = (int) $s['min_seconds']; $s['required'] = (int) $s['required'] === 1; }
  return $steps;
}
route('GET', '/onboarding', function () {
  $u = require_user(); $id = (int) $u['id']; $ro = false; $who = null;
  if (($pid = (int) ($_GET['user'] ?? 0)) && $pid !== $id) {
    $their = current_seat($pid); $mine = work_seat($u);
    $above = is_hq($u) || ($their && $mine && $mine['network'] === $their['network'] && (int) $mine['rank_no'] < (int) $their['rank_no'] && str_starts_with($their['unit_path'], $mine['unit_path']));
    if (!$above) fail(403, 'This onboarding record is outside your area.', 'forbidden');
    $p = row('SELECT first_name, last_name FROM users WHERE id=?', [$pid]); if (!$p) fail(404, 'That person was not found.', 'not_found');
    $id = $pid; $ro = true; $who = work_name($p) . ($their ? ', ' . seat_title($their) : '');
  }
  return ['steps' => onboarding_state($id), 'read_only' => $ro, 'who' => $who, 'starter' => (int) val("SELECT COUNT(*) FROM onboarding_steps WHERE origin<>'starter'") === 0];
});
route('POST', '/onboarding/([a-z0-9_]{2,40})', function ($code) {
  $u = require_user(); $steps = onboarding_state((int) $u['id']); $cur = null;
  foreach ($steps as $s) if (!$s['done_at']) { $cur = $s; break; }
  if (!$cur) fail(409, 'You have completed your onboarding.', 'done');
  if ($cur['code'] !== $code) fail(409, 'Work through each step in order. The next one is: ' . $cur['title'] . '.', 'order');
  $secs = max(0, (int) (body()['seconds'] ?? 0)); $note = str_in('note', 600);
  if ($cur['mode'] === 'read' && empty(body()['reached_end'])) fail(422, 'Scroll to the end of the document to continue.', 'read');
  if ($cur['mode'] !== 'declare' && $secs < min($cur['min_seconds'], 90) - 2) fail(422, 'Take a little longer on this step before continuing.', 'time');
  if ($cur['mode'] === 'declare' && $note === '') fail(422, 'State any conflict of interest, or write "none" to confirm you have none.', 'note');
  q('INSERT INTO onboarding_progress (user_id,step_code,done_at,seconds,note) VALUES (?,?,?,?,?)', [(int) $u['id'], $code, now(), $secs, $note]);
  $left = count(array_filter($steps, fn($s) => !$s['done_at'] && $s['required'] && $s['code'] !== $code));
  if ($left === 0) { notify_hq('appointment', work_name($u) . ' completed onboarding', ($seat = work_seat($u)) ? seat_title($seat) : '', '#/members/' . $u['id']); after_response(fn() => push_drain(10)); }
  return ['ok' => true, 'left' => $left];
});
