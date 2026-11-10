<?php
declare(strict_types=1);
/* Referral links, the candidate's activation journey, and HQ's activation register. */

/* ---------------------------------------------------------------- public -- */
route('GET', '/positions', fn() => ['positions' => rows('SELECT code, title_wgmn, title_wnnn, level FROM positions ORDER BY rank_no'), 'categories' => REF_CATS, 'notice' => ACT_NOTICE]);
route('GET', '/ref/([A-Za-z0-9]{4,24})', function ($code) {
  $l = ref_link_by_code($code);
  if (!$l) fail(404, 'This invitation link is not active. Ask the person who sent it for a new link, or join WODDI directly.', 'bad_link');
  if (empty($_GET['peek'])) { throttle('refv:' . client_ip(), 60, 3600); throttle_hit('refv:' . client_ip()); q('INSERT INTO referral_visits (link_id,created_at) VALUES (?,?)', [(int) $l['id'], now()]); }
  $s = ref_link_shape($l);
  return ['link' => ['code' => $s['code'], 'kind' => $s['kind'], 'network' => $s['network'], 'position' => $s['position'], 'role' => $s['role'], 'place' => $s['place'], 'owner' => trim($l['o_first'] . ' ' . $l['o_last'])], 'notice' => ACT_NOTICE];
});

/* -------------------------------------------------------- referral links -- */
route('GET', '/referrals', function () {
  $u = require_user(); $seat = work_seat($u); $hq = is_hq($u);
  if (!$hq && !$seat) fail(403, 'Referral links are for volunteer leaders and WODDI HQ.', 'forbidden');
  if ($seat) ref_member_link($u, $seat);
  $all = $hq && ($_GET['scope'] ?? '') === 'all';
  $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
  $links = rows('SELECT l.*, o.first_name o_first, o.last_name o_last,
      (SELECT COUNT(*) FROM referral_visits v WHERE v.link_id=l.id AND v.created_at>=?) visits30,
      (SELECT COUNT(*) FROM referrals r WHERE r.link_id=l.id) registered, (SELECT COUNT(*) FROM referrals r WHERE r.link_id=l.id AND r.position_code<>\'\') candidates
    FROM referral_links l JOIN users o ON o.id=l.owner_id WHERE ' . ($all ? '1=1' : 'l.owner_id=?') . ' ORDER BY l.active DESC, l.id DESC LIMIT 200', $all ? [$since] : [$since, (int) $u['id']]);
  $people = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.network, u.country_code, u.state_region, r.referrer_name, r.category, r.position_code, r.created_at, l.code, l.label, l.campaign,
      (SELECT a.status FROM activations a WHERE a.user_id=u.id ORDER BY a.id DESC LIMIT 1) activation
    FROM referrals r JOIN users u ON u.id=r.user_id LEFT JOIN referral_links l ON l.id=r.link_id WHERE " . ($all ? '1=1' : 'r.referrer_id=?') . ' ORDER BY r.created_at DESC LIMIT 200', $all ? [] : [(int) $u['id']]);
  foreach ($people as &$p) $p['role'] = $p['position_code'] !== '' ? position_title($p['position_code'], (string) $p['network']) : '';
  $pos = $hq ? rows('SELECT code, title_wgmn, title_wnnn, level FROM positions ORDER BY rank_no') : rows('SELECT code, title_wgmn, title_wnnn, level FROM positions WHERE rank_no>? ORDER BY rank_no', [(int) $seat['rank_no']]);
  return ['links' => array_map(fn($l) => ref_link_shape($l) + ['owner' => trim($l['o_first'] . ' ' . $l['o_last']), 'visits30' => (int) $l['visits30'], 'registered' => (int) $l['registered'], 'candidates' => (int) $l['candidates']], $links),
    'people' => $people, 'is_hq' => $hq, 'positions' => $pos, 'network' => $seat ? $seat['network'] : null, 'unit' => $seat ? ['id' => (int) $seat['org_unit_id'], 'name' => $seat['unit_name']] : null];
});
route('POST', '/referrals/link', function () {
  $u = require_user(); $seat = work_seat($u); $hq = is_hq($u); $b = body();
  if (!$hq && !$seat) fail(403, 'Referral links are for volunteer leaders and WODDI HQ.', 'forbidden');
  $net = $hq ? strtoupper(str_in('network', 8)) : $seat['network'];
  if (!in_array($net, ['WGMN', 'WNNN'], true)) fail(422, 'Choose the network.', 'network');
  $posCode = str_in('position', 40); $pos = $posCode !== '' ? row('SELECT * FROM positions WHERE code=?', [$posCode]) : null;
  if ($posCode !== '' && !$pos) fail(422, 'Choose the position.', 'position');
  if ($pos && !$hq && (int) $pos['rank_no'] <= (int) $seat['rank_no']) fail(403, 'You can share links for positions under your own seat.', 'scope');
  $unitId = (int) ($b['org_unit_id'] ?? 0) ?: null;
  if ($unitId) {
    $unit = row('SELECT id, path FROM org_units WHERE id=?', [$unitId]); if (!$unit) fail(422, 'Choose the country or state.', 'unit');
    if (!$hq && !str_starts_with($unit['path'], $seat['unit_path'])) fail(403, 'That place is outside your area.', 'scope');
  } elseif (!$hq) $unitId = (int) $seat['org_unit_id'];
  $area = str_in('area_label', 120);
  if ($pos && in_array($pos['level'], ['lga', 'community_cluster', 'chapter'], true) && $area === '') fail(422, 'Name the LGA, cluster or chapter this position covers.', 'area');
  $id = ins('INSERT INTO referral_links (code,owner_id,kind,network,org_unit_id,position_code,area_label,label,campaign,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
    [ref_new_code(), (int) $u['id'], $pos ? 'leader' : 'member', $net, $unitId, $pos ? $pos['code'] : '', $area, str_in('label', 160), str_in('campaign', 160) ?: ($pos ? '' : 'general'), now()]);
  return ['ok' => true, 'link' => ref_link_shape(row('SELECT * FROM referral_links WHERE id=?', [$id]))];
});
route('POST', '/referrals/link/(\d+)', function ($id) {
  $u = require_user(); $l = row('SELECT * FROM referral_links WHERE id=?', [(int) $id]);
  if (!$l || (!is_hq($u) && (int) $l['owner_id'] !== (int) $u['id'])) fail(404, 'That link was not found.', 'not_found');
  q('UPDATE referral_links SET active=? WHERE id=?', [empty(body()['active']) ? 0 : 1, (int) $id]);
});

/* ------------------------------------------------- the candidate's journey -- */
function activation_mine(int $userId): ?array {
  $a = row('SELECT * FROM activations WHERE user_id=? ORDER BY id DESC LIMIT 1', [$userId]);
  if (!$a) return null;
  $today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->format('Y-m-d');
  if (in_array($a['status'], ['in_progress', 'returned'], true) && $today > $a['deadline']) { q("UPDATE activations SET status='incomplete' WHERE id=?", [(int) $a['id']]); $a['status'] = 'incomplete'; }
  return $a;
}
function activation_view(array $a, bool $forHq = false): array {
  $u = row('SELECT * FROM users WHERE id=?', [(int) $a['user_id']]);
  $prog = []; foreach (rows('SELECT * FROM activation_progress WHERE activation_id=?', [(int) $a['id']]) as $p) $prog[(int) $p['day_no']] = $p;
  $days = activation_days($a['route']);
  foreach ($days as &$d) {
    $p = $prog[$d['day']] ?? null;
    $d['answers'] = $p ? (object) jn_json($p['answers']) : new stdClass(); $d['acked'] = $p && $p['acked'] !== '' ? explode(',', $p['acked']) : [];
    $d['reached_end'] = $p ? (int) $p['reached_end'] === 1 : false; $d['status'] = $p && $p['completed_at'] ? 'completed' : ($p ? 'in_progress' : 'not_started'); $d['completed_at'] = $p['completed_at'] ?? null; $d['seconds'] = $p ? (int) $p['seconds'] : 0;
  }
  unset($d);
  $resp = row('SELECT * FROM readiness_responses WHERE activation_id=?', [(int) $a['id']]);
  $existing = $resp ? ['statements' => (object) jn_json($resp['statements']), 'short_answers' => (object) jn_json($resp['short_answers']), 'clarification_note' => $resp['clarification_note'],
    'confirmations' => $resp['confirmations'] !== '' ? array_map('intval', explode(',', $resp['confirmations'])) : [], 'readiness' => $resp['readiness'], 'signature_name' => $resp['signature_name'], 'submitted_at' => $resp['submitted_at']] : null;
  $unit = (string) val('SELECT name FROM org_units WHERE id=?', [(int) $a['org_unit_id']]);
  $v = ['id' => (int) $a['id'], 'route' => $a['route'], 'route_label' => ACT_ROUTES[$a['route']]['label'], 'network' => $a['network'], 'role_title' => position_title($a['position_code'], $a['network']),
    'unit' => trim(($a['area_label'] !== '' ? $a['area_label'] . ', ' : '') . $unit, ', '), 'referrer' => $a['referrer_name'], 'status' => $a['status'], 'deadline' => $a['deadline'],
    'extension_status' => $a['extension_status'], 'return_note' => $a['return_note'], 'outcome' => $forHq ? $a['outcome'] : '', 'disclosed' => $a['disclosed'], 'started_at' => $a['started_at'], 'submitted_at' => $a['submitted_at'], 'decided_at' => $a['decided_at'],
    'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'notice' => ACT_NOTICE, 'days' => $days, 'content' => activation_content_ready($a['route']), 'form' => readiness_form(), 'existing' => $existing];
  if ($forHq) $v += ['outcome_note' => $a['outcome_note'], 'extension_reason' => $a['extension_reason'], 'candidate' => ['id' => (int) $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'has_photo' => (int) $u['has_photo'], 'email' => $u['email'], 'phone' => $u['phone'],
    'country_code' => $u['country_code'], 'state_region' => $u['state_region'], 'lga' => $u['lga'], 'community' => $u['community'], 'member_no' => $u['member_no'], 'seated' => (bool) current_seat((int) $u['id'])], 'position' => $a['position_code'], 'org_unit_id' => (int) $a['org_unit_id'], 'area_label' => $a['area_label']];
  return $v;
}
route('GET', '/activation', function () { $u = require_user(); $a = activation_mine((int) $u['id']); return ['activation' => $a ? activation_view($a) : null]; });
route('POST', '/activation/day/(\d+)', function ($n) {
  $u = require_user(); $a = activation_mine((int) $u['id']); $n = (int) $n; $b = body();
  if (!$a) fail(404, 'You do not currently have an activation to complete.', 'none');
  if ($a['status'] === 'incomplete') fail(409, 'The time allowed for this activation has passed. You can ask for an extension.', 'incomplete');
  if ($a['status'] !== 'in_progress') fail(409, 'Your activation days are already complete.', 'transition');
  $days = activation_days($a['route']); $day = null; foreach ($days as $d) if ($d['day'] === $n) $day = $d;
  if (!$day) fail(404, 'That day is not part of your activation.', 'not_found');
  $done = array_column(rows('SELECT day_no FROM activation_progress WHERE activation_id=? AND completed_at IS NOT NULL', [(int) $a['id']]), 'day_no');
  foreach ($days as $d) if ($d['day'] < $n && !in_array($d['day'], $done)) fail(409, 'Finish Day ' . $d['day'] . ' first.', 'order');
  if (in_array($n, $done)) return ['ok' => true];
  $answers = []; foreach ($day['questions'] as $qq) { $k = (string) ($qq['key'] ?? ''); if ($k !== '') $answers[$k] = mb_substr(trim((string) ($b['answers'][$k] ?? '')), 0, 3000); }
  $acked = array_values(array_unique(array_filter(array_map('intval', is_array($b['acked'] ?? null) ? $b['acked'] : []), fn($i) => $i >= 0 && $i < count($day['acks']))));
  $reached = !empty($b['reached_end']) || !$day['content']; $complete = !empty($b['complete']);
  if ($complete) {
    $missing = [];
    if (!$reached) $missing[] = 'the reading';
    foreach ($day['questions'] as $qq) if (($answers[(string) ($qq['key'] ?? '')] ?? '') === '') { $missing[] = 'every answer'; break; }
    if (count($acked) < count($day['acks'])) $missing[] = 'every confirmation';
    if ($missing) fail(422, 'Still needed before you can continue: ' . implode(', ', $missing) . '.', 'missing');
  }
  q('DELETE FROM activation_progress WHERE activation_id=? AND day_no=?', [(int) $a['id'], $n]);
  q('INSERT INTO activation_progress (activation_id,day_no,answers,acked,reached_end,seconds,completed_at) VALUES (?,?,?,?,?,?,?)', [(int) $a['id'], $n, json_encode($answers, JSON_UNESCAPED_UNICODE), implode(',', $acked), $reached ? 1 : 0, max(0, (int) ($b['seconds'] ?? 0)), $complete ? now() : null]);
  return ['ok' => true, 'completed' => $complete];
});
route('POST', '/activation/assessment', function () {
  $u = require_user(); $a = activation_mine((int) $u['id']); $b = body();
  if (!$a || !in_array($a['status'], ['in_progress', 'returned'], true)) fail(409, 'There is no assessment waiting for you.', 'transition');
  $c = activation_content_ready($a['route']); if (!$c['complete']) fail(409, 'The rest of this activation is still being loaded by WODDI HQ. Your progress is saved.', 'content');
  $done = (int) val('SELECT COUNT(*) FROM activation_progress WHERE activation_id=? AND completed_at IS NOT NULL', [(int) $a['id']]);
  if ($done < $c['days_loaded']) fail(409, 'Finish every day of the activation first.', 'order');
  $f = readiness_form(); $st = []; $sa = [];
  foreach ($f['statements'] as $x) { $v = (string) ($b['statements'][(string) $x['seq']] ?? ''); if (!in_array($v, ['yes', 'clarify'], true)) fail(422, 'Please choose an option for every statement.', 'statements'); $st[(string) $x['seq']] = $v; }
  foreach ($f['questions'] as $x) { $v = mb_substr(trim((string) ($b['short_answers'][(string) $x['seq']] ?? '')), 0, 2000); if ($v === '') fail(422, 'Please answer each question briefly.', 'questions'); $sa[(string) $x['seq']] = $v; }
  $conf = array_values(array_unique(array_map('intval', is_array($b['confirmations'] ?? null) ? $b['confirmations'] : [])));
  if (count($conf) < count($f['confirmations'])) fail(422, 'Please tick every confirmation.', 'confirmations');
  $ready = str_in('readiness', 16); if (!in_array($ready, ['ready', 'interested', 'not_now'], true)) fail(422, 'Please tell us how ready you feel.', 'readiness');
  $sig = str_in('signature_name', 160); if ($sig === '') fail(422, 'Please type your name as your digital confirmation.', 'signature');
  q('DELETE FROM readiness_responses WHERE activation_id=?', [(int) $a['id']]);
  q('INSERT INTO readiness_responses (activation_id,statements,short_answers,clarification_note,confirmations,readiness,signature_name,submitted_at) VALUES (?,?,?,?,?,?,?,?)',
    [(int) $a['id'], json_encode($st), json_encode($sa, JSON_UNESCAPED_UNICODE), str_in('clarification_note', 1500), implode(',', $conf), $ready, $sig, now()]);
  q("UPDATE activations SET status='submitted', submitted_at=?, return_note='' WHERE id=?", [now(), (int) $a['id']]);
  notify_hq('activation', 'Assessment submitted: ' . work_name($u), ACT_ROUTES[$a['route']]['label'] . ', ' . position_title($a['position_code'], $a['network']) . ' (' . $a['network'] . ').', '#/referrals/activations/' . $a['id']);
  after_response(fn() => push_drain(20));
  return ['ok' => true];
});
route('POST', '/activation/extension', function () {
  $u = require_user(); $a = activation_mine((int) $u['id']); $reason = str_in('reason', 600);
  if (!$a || $a['status'] !== 'incomplete' || $a['extension_status'] !== '') fail(409, 'An extension cannot be requested right now.', 'transition');
  if (mb_strlen($reason) < 5) fail(422, 'Tell us briefly why you need more time.', 'reason');
  q("UPDATE activations SET extension_status='requested', extension_reason=? WHERE id=?", [$reason, (int) $a['id']]);
  notify_hq('activation', 'Extension request: ' . work_name($u), mb_substr($reason, 0, 200), '#/referrals/activations/' . $a['id']);
  after_response(fn() => push_drain(20));
});

/* ---------------------------------------------------- HQ activation register -- */
function activation_stage(array $a, int $done): string {
  if ($a['status'] === 'completed') return 'Decided';
  if ($a['status'] === 'submitted') return 'Awaiting review';
  if ($a['status'] === 'returned') return 'Returned';
  if ($a['status'] === 'incomplete') return 'Incomplete';
  return $done ? 'In progress, Day ' . $done . ' completed' : 'Not started';
}
route('GET', '/hq/activations', function () {
  require_hq(); $route = isset(ACT_ROUTES[$_GET['route'] ?? '']) ? $_GET['route'] : 'ref3';
  $list = rows("SELECT a.*, u.first_name, u.last_name, u.has_photo, u.country_code, u.state_region, ou.name unit,
      (SELECT COUNT(*) FROM activation_progress p WHERE p.activation_id=a.id AND p.completed_at IS NOT NULL) done
    FROM activations a JOIN users u ON u.id=a.user_id JOIN org_units ou ON ou.id=a.org_unit_id WHERE a.route=? ORDER BY (a.status='submitted') DESC, (a.extension_status='requested') DESC, a.id DESC LIMIT 300", [$route]);
  $c = ['registered' => count($list), 'started' => 0, 'awaiting_review' => 0, 'decided' => 0, 'incomplete' => 0, 'selected' => 0];
  $items = [];
  foreach ($list as $a) {
    if ((int) $a['done'] > 0 || $a['status'] !== 'in_progress') $c['started']++;
    if ($a['status'] === 'submitted') $c['awaiting_review']++; if ($a['status'] === 'completed') $c['decided']++; if ($a['status'] === 'incomplete') $c['incomplete']++; if ($a['outcome'] === 'selected') $c['selected']++;
    $items[] = ['id' => (int) $a['id'], 'user_id' => (int) $a['user_id'], 'first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'has_photo' => (int) $a['has_photo'], 'network' => $a['network'], 'country_code' => $a['country_code'],
      'role' => position_title($a['position_code'], $a['network']), 'place' => trim(($a['area_label'] !== '' ? $a['area_label'] . ', ' : '') . $a['unit'], ', '), 'referrer' => $a['referrer_name'], 'status' => $a['status'],
      'stage' => activation_stage($a, (int) $a['done']), 'deadline' => $a['deadline'], 'extension_status' => $a['extension_status'], 'outcome' => $a['outcome'], 'started_at' => $a['started_at']];
  }
  return ['route' => $route, 'routes' => ACT_ROUTES, 'items' => $items, 'counts' => $c, 'content' => activation_content_ready($route), 'outcomes' => ACT_OUTCOMES];
});
route('GET', '/hq/activations/(\d+)', function ($id) {
  require_hq(); $a = row('SELECT * FROM activations WHERE id=?', [(int) $id]); if (!$a) fail(404, 'That activation was not found.', 'not_found');
  return ['activation' => activation_view($a, true), 'outcomes' => ACT_OUTCOMES];
});
route('POST', '/hq/activations/open', function () {
  $me = require_hq(); $b = body();
  $u = row("SELECT * FROM users WHERE email=? AND status='active'", [strtolower(str_in('email', 190))]);
  if (!$u) fail(404, 'No active account has that email. The applicant joins WDOS first, then HQ opens the activation.', 'not_found');
  if (current_seat((int) $u['id'])) fail(409, 'That person already holds a leadership seat.', 'seated');
  $pos = row('SELECT * FROM positions WHERE code=?', [str_in('position', 40)]); if (!$pos) fail(422, 'Choose the position.', 'position');
  $unit = row("SELECT id FROM org_units WHERE id=? AND level<>'hq'", [(int) ($b['org_unit_id'] ?? 0)]); if (!$unit) fail(422, 'Choose the country or state.', 'unit');
  $area = str_in('area_label', 120);
  if (in_array($pos['level'], ['lga', 'community_cluster', 'chapter'], true) && $area === '') fail(422, 'Name the LGA, cluster or chapter this position covers.', 'area');
  $route = str_in('route', 8) === 'ref3' ? 'ref3' : 'open7';
  $aid = activation_start((int) $u['id'], $route, (string) $u['network'], $pos['code'], (int) $unit['id'], $area, '');
  notify((int) $u['id'], 'activation', 'Your ' . ACT_ROUTES[$route]['label'] . ' is open', 'You are being considered for ' . position_title($pos['code'], (string) $u['network']) . '. Open WDOS to begin Day 1.', '#/activation');
  log_activity((int) $me['id'], (int) $u['id'], 'activation_opened', ACT_ROUTES[$route]['label']);
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'id' => $aid];
});
route('POST', '/hq/activations/(\d+)', function ($id) {
  $me = require_hq(); $a = row('SELECT * FROM activations WHERE id=?', [(int) $id]); if (!$a) fail(404, 'That activation was not found.', 'not_found');
  $act = str_in('action', 16); $note = str_in('note', 1000); $uid = (int) $a['user_id']; $res = ['ok' => true];
  if ($act === 'return') {
    if ($a['status'] !== 'submitted') fail(409, 'Only a submitted assessment can be returned.', 'transition');
    if (mb_strlen($note) < 3) fail(422, 'Write the note to the candidate.', 'note');
    $ext = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('+3 days')->format('Y-m-d');
    q("UPDATE activations SET status='returned', return_note=?, deadline=GREATEST(deadline, ?) WHERE id=?", [$note, $ext, (int) $id]);
    notify($uid, 'activation', 'A little more information is needed', 'Please read the note, correct your answers and submit again.', '#/activation');
  } elseif ($act === 'outcome') {
    if (!in_array($a['status'], ['submitted', 'completed'], true)) fail(409, 'Decide the outcome after the assessment is submitted.', 'transition');
    $o = str_in('outcome', 20); if (!isset(ACT_OUTCOMES[$o])) fail(422, 'Choose the outcome.', 'outcome');
    q("UPDATE activations SET status='completed', outcome=?, outcome_note=?, disclosed=?, decided_at=?, decided_by=? WHERE id=?", [$o, $note, str_in('disclosed', 1000), now(), (int) $me['id'], (int) $id]);
    notify($uid, 'activation', 'Your assessment has been reviewed', 'Headquarters will contact you about the next step.', '#/activation');
  } elseif ($act === 'appoint') {
    if ($a['outcome'] !== 'selected') fail(409, 'Record the outcome as Selected before appointing.', 'transition');
    if (current_seat($uid)) fail(409, 'That person already holds a leadership seat.', 'seated');
    $res['title'] = seat_open($uid, $a['position_code'], $a['network'], (int) $a['org_unit_id'], $a['area_label'], 'appointed', (int) $me['id']);
  } elseif ($act === 'extension') {
    if ($a['extension_status'] !== 'requested') fail(409, 'There is no extension request to decide.', 'transition');
    if (!empty(body()['approve'])) {
      $until = work_date(str_in('until', 10)); if (!$until || $until <= gmdate('Y-m-d')) fail(422, 'Choose the new deadline, a date after today.', 'until');
      q("UPDATE activations SET extension_status='approved', status='in_progress', deadline=? WHERE id=?", [$until, (int) $id]);
      notify($uid, 'activation', 'Extension approved', 'Your journey has been extended to ' . date('j M Y', strtotime($until)) . '. Continue from wherever you stopped.', '#/activation');
    } else { q("UPDATE activations SET extension_status='declined' WHERE id=?", [(int) $id]); notify($uid, 'activation', 'Extension not approved', $note, '#/activation'); }
  } else fail(422, 'Unknown action.', 'action');
  log_activity((int) $me['id'], $uid, 'activation_' . $act, $note);
  after_response(fn() => push_drain(10));
  return $res;
});
