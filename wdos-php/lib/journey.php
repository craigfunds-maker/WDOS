<?php
declare(strict_types=1);
/* Referral links, the candidate's activation journey, and HQ's activation register. */

/* ---------------------------------------------------------------- public -- */
route('GET', '/positions', fn() => ['positions' => rows('SELECT code, title_wgmn, title_wnnn, level FROM positions WHERE in_framework=1 OR ? ORDER BY rank_no, code', [empty($_GET['all']) ? 0 : 1]), 'categories' => REF_CATS, 'notice' => ACT_NOTICE]);
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
    'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'notice' => ACT_NOTICE, 'days' => $days, 'content' => activation_content_ready($a['route']), 'form' => readiness_form($a['network'], $forHq), 'profile' => ['dob' => $u['dob'], 'phone' => $u['phone'], 'email' => $u['email'], 'country' => country_name((string) $u['country_code']), 'state_region' => $u['state_region'], 'lga' => $u['lga'], 'community' => $u['community']], 'existing' => $existing];
  if (($a['scheme'] ?? 'v1') === 'fw') {      // v109.46: the Leadership Activation Framework: tasks, evidence, scores, gates
    $n = fw_place($a); $nv = ['nets' => [$a['network']], 'contact' => false, 'links' => false, 'kpis' => false, 'role' => 'leader', 'root' => $n, 'up' => $n, 'rank' => 99];
    $cov = ['place' => lm_place_name($n), 'seats' => array_map(fn($c) => ['title' => $c['title'], 'filled' => $c['filled'], 'name' => $c['filled'] ? trim($c['person']['first_name'] . ' ' . $c['person']['last_name']) : null], array_values(array_filter(lm_seats_at($n, [$a['network']], $nv, false), fn($c) => empty($c['extra'])))),
      'below' => lm_depth($n) < 5 ? array_map(fn($c) => ['name' => $c['name'], 'vacant' => $c['vacant'], 'leaders' => $c['leaders'], 'members' => $c['members'], 'principal' => ($p = array_values(array_filter($c['slots'], fn($x) => $x['principal']))[0]['holder'] ?? null) ? trim($p['first_name'] . ' ' . $p['last_name']) : null], lm_children($n, $nv)) : [],
      'below_label' => lm_depth($n) < 5 ? lm_level_label(LM_LEVELS[lm_depth($n)], $n['cc']) : ''];
    $v += ['scheme' => 'fw', 'fw' => fw_state($a), 'orientation' => FW_ORIENTATION, 'quiz_a' => fw_quiz_public(FW_QUIZ_A), 'quiz_b' => fw_quiz_public(FW_QUIZ_B), 'officers' => fw_officers($a), 'coverage' => $cov,
      'support_note' => $a['support_note'] ?? '', 'compliance_breach' => $forHq ? ($a['compliance_breach'] ?? '') : ''];
    $v['role_title'] = lm_title($a['position_code'], $a['network'], $n['cc']); $v['unit'] = lm_place_name($n);      // the structure document's title and the seat's own place
  } else $v['scheme'] = 'v1';
  if ($forHq) $v += ['outcome_note' => $a['outcome_note'], 'extension_reason' => $a['extension_reason'], 'candidate' => ['id' => (int) $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'has_photo' => (int) $u['has_photo'], 'email' => $u['email'], 'phone' => $u['phone'],
    'country_code' => $u['country_code'], 'state_region' => $u['state_region'], 'lga' => $u['lga'], 'community' => $u['community'], 'member_no' => $u['member_no'], 'seated' => (bool) current_seat((int) $u['id'])], 'position' => $a['position_code'], 'org_unit_id' => (int) $a['org_unit_id'], 'area_label' => $a['area_label']];
  return $v;
}
route('GET', '/activation', function () { $u = require_user(); $a = activation_mine((int) $u['id']); if ($a) fw_touch_access($a); return ['activation' => $a ? activation_view($a) : null]; });
/* v109.46: one task of the Leadership Activation Framework, with its evidence. A task returned for corrections can be sent again. */
route('POST', '/activation/task/([a-z]{3,20})', function ($key) {
  $u = require_user(); $a = activation_mine((int) $u['id']);
  if (!$a || ($a['scheme'] ?? '') !== 'fw') fail(404, 'You do not currently have an activation to complete.', 'none');
  if ($a['status'] === 'incomplete') fail(409, 'The time allowed for this activation has passed. You can ask for an extension.', 'incomplete');
  if ($a['status'] === 'support') fail(409, 'WODDI HQ is arranging support for you first. They will open your activation again.', 'support');
  if (!in_array($a['status'], ['in_progress', 'returned', 'submitted'], true)) fail(409, 'Your activation is already decided.', 'transition');
  if (!in_array($key, fw_keys($a['route']), true)) fail(404, 'That task is not part of your activation.', 'not_found');
  $st = fw_state($a); $t = array_column($st['tasks'], null, 'key');
  if (in_array($t[$key]['status'], ['submitted', 'verified'], true)) fail(409, 'You have already sent this task. WODDI HQ may return it to you with remarks.', 'sent');
  foreach ($st['tasks'] as $x) if ($x['day'] < FW_TASKS[$key][0] && !in_array($x['status'], ['submitted', 'verified'], true)) fail(409, 'Finish Day ' . $x['day'] . ' first: ' . $x['title'] . '.', 'order');
  [$ev, $files, $officer] = fw_take($key, body(), $a, (int) $u['id']);
  $auto = fw_auto($key, $ev, $a); $was = $t[$key]['status'];
  q('INSERT INTO activation_tasks (activation_id,task_key,status,evidence,files,auto_score,submitted_at,officer_id) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), evidence=VALUES(evidence), files=VALUES(files), auto_score=VALUES(auto_score),
     submitted_at=VALUES(submitted_at), officer_id=VALUES(officer_id), score=NULL, reviewer_id=NULL, reviewed_at=NULL, resubmissions=resubmissions+' . ($was === 'pending_corrections' ? 1 : 0) . ($key === 'contact' ? ', confirmed_at=NULL, confirmed_by=NULL' : ''),
    [(int) $a['id'], $key, 'submitted', json_encode($ev, JSON_UNESCAPED_UNICODE), implode(',', $files), $auto, now(), $officer]);
  foreach ($files as $f) try { q("INSERT INTO doc_links (file_id,owner_type,owner_id,added_by,created_at) VALUES (?,'activation',?,?,?)", [$f, (int) $a['id'], (int) $u['id'], now()]); } catch (PDOException $e) { /* already linked */ }
  $name = work_name($u); $aUrl = '#/referrals/activations/' . $a['id'];
  if ($key === 'contact') {         // her reporting officer confirms the contact in WDOS; HQ when there is no officer above the seat
    if ($officer) { notify($officer, 'activation', $name . ' says she contacted you', 'She is in her activation for ' . position_title($a['position_code'], $a['network']) . '. Confirm the contact on your dashboard if it happened.', '#/'); }
    else notify_hq('activation', 'Confirm a contact: ' . $name, 'She named WODDI HQ as her reporting officer (no leader serves above the seat yet).', $aUrl);
  }
  $after = fw_state(array_merge($a, ['status' => $a['status']]));
  $doneA = !array_filter($after['tasks'], fn($x) => $x['stage'] === 'A' && !in_array($x['status'], ['submitted', 'verified'], true));
  if ($doneA && FW_TASKS[$key][1] === 'A' && $a['route'] === 'open7') notify_hq('activation', 'Day 3 review: ' . $name, 'Days 1 to 3 are in (' . $after['stage_a'] . ' of 100 so far). She carries on with Days 4 to 7.', $aUrl);
  if ($after['final'] && $a['status'] !== 'submitted') {
    q("UPDATE activations SET status='submitted', submitted_at=?, return_note='' WHERE id=?", [now(), (int) $a['id']]);
    notify_hq('activation', ($a['route'] === 'open7' ? 'Day 7 review: ' : 'Day 3 review: ') . $name, ACT_ROUTES[$a['route']]['label'] . ', ' . position_title($a['position_code'], $a['network']) . '. Score so far ' . $after['score'] . ' of 100. Verify her evidence.', $aUrl);
    if ($after['passed']) referrer_tell((int) $a['user_id'], 'passed', 'a' . $a['id'], (string) $after['score']);
  } elseif ($a['status'] === 'returned' && !$after['corrections']) q("UPDATE activations SET status=? WHERE id=?", [$after['final'] ? 'submitted' : 'in_progress', (int) $a['id']]);
  log_activity((int) $u['id'], null, 'activation_task', FW_TASKS[$key][4], 'activation', (int) $a['id']);
  after_response(function () { push_drain(20); mail_drain(6); });
  return ['ok' => true, 'auto_score' => $auto, 'state' => fw_score(row('SELECT * FROM activations WHERE id=?', [(int) $a['id']]))];
});
/* The reporting officer (or HQ) confirms that the candidate contacted her: the mandatory reporting-relationship gate. */
route('POST', '/activation/(\d+)/confirm-contact', function ($id) {
  $u = require_user(); $t = row("SELECT t.*, a.user_id, a.status a_status FROM activation_tasks t JOIN activations a ON a.id=t.activation_id WHERE t.activation_id=? AND t.task_key='contact'", [(int) $id]);
  if (!$t) fail(404, 'There is no contact to confirm.', 'not_found');
  if (!is_hq($u) && (int) $t['officer_id'] !== (int) $u['id']) fail(403, 'Only the reporting officer she named, or WODDI HQ, can confirm this contact.', 'forbidden');
  if (!empty(body()['not_contacted'])) {
    q("UPDATE activation_tasks SET status='pending_corrections', remarks=?, confirmed_at=NULL WHERE activation_id=? AND task_key='contact'", ['Your reporting officer says you have not contacted her yet. Contact her, then send this task again.', (int) $id]);
    q("UPDATE activations SET status='returned' WHERE id=? AND status IN ('in_progress','submitted')", [(int) $id]);
    notify((int) $t['user_id'], 'activation', 'Your contact was not confirmed', 'Your reporting officer says you have not contacted her yet. Contact her and send the Day 3 task again.', '#/activation');
  } else {
    q("UPDATE activation_tasks SET confirmed_at=?, confirmed_by=? WHERE activation_id=? AND task_key='contact'", [now(), (int) $u['id'], (int) $id]);
    notify((int) $t['user_id'], 'activation', 'Your reporting officer confirmed your contact', 'Day 3 · Leadership connection is confirmed.', '#/activation');
  }
  log_activity((int) $u['id'], (int) $t['user_id'], 'activation_contact_' . (empty(body()['not_contacted']) ? 'confirmed' : 'denied'));
  after_response(fn() => push_drain(10));
  return ['ok' => true];
});
/* HQ reviews one task: verify it (with a score of her choosing, up to the task's points), or return it for corrections with remarks. */
route('POST', '/hq/activations/(\d+)/task/([a-z]{3,20})', function ($id, $key) {
  $me = require_hq(); $a = row('SELECT * FROM activations WHERE id=?', [(int) $id]); if (!$a || ($a['scheme'] ?? '') !== 'fw') fail(404, 'That activation was not found.', 'not_found');
  if (!in_array($key, fw_keys($a['route']), true)) fail(404, 'That task is not part of this activation.', 'not_found');
  $t = row('SELECT * FROM activation_tasks WHERE activation_id=? AND task_key=?', [(int) $id, $key]); if (!$t) fail(409, 'She has not sent this task yet.', 'not_sent');
  $b = body(); $act = str_in('action', 16); $rem = str_in('remarks', 1500); $pts = FW_TASKS[$key][3];
  if ($act === 'verify') {
    $sc = array_key_exists('score', $b) && $b['score'] !== '' && $b['score'] !== null ? (int) $b['score'] : null;
    if ($sc === null) $sc = (int) $t['auto_score'] + ($key === 'contact' && $t['confirmed_at'] ? 10 : 0);
    if ($sc < 0 || $sc > $pts) fail(422, 'The score for this task is between 0 and ' . $pts . '.', 'score');
    if ($key === 'contact' && !$t['confirmed_at']) { q('UPDATE activation_tasks SET confirmed_at=?, confirmed_by=? WHERE activation_id=? AND task_key=?', [now(), (int) $me['id'], (int) $id, $key]); }
    q("UPDATE activation_tasks SET status='verified', score=?, reviewer_id=?, reviewed_at=?, remarks=? WHERE activation_id=? AND task_key=?", [min($pts, $sc), (int) $me['id'], now(), $rem, (int) $id, $key]);
  } elseif ($act === 'correct') {
    if (mb_strlen($rem) < 3) fail(422, 'Write the remarks: what she should correct or add.', 'remarks');
    q("UPDATE activation_tasks SET status='pending_corrections', reviewer_id=?, reviewed_at=?, remarks=? WHERE activation_id=? AND task_key=?", [(int) $me['id'], now(), $rem, (int) $id, $key]);
    $ext = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('+3 days')->format('Y-m-d');
    q("UPDATE activations SET status='returned', deadline=GREATEST(deadline, ?) WHERE id=? AND status IN ('in_progress','submitted','returned','incomplete')", [$ext, (int) $id]);
    notify((int) $a['user_id'], 'activation', 'Pending corrections: ' . FW_TASKS[$key][4], $rem, '#/activation');
  } else fail(422, 'Unknown action.', 'action');
  log_activity((int) $me['id'], (int) $a['user_id'], 'activation_task_' . $act, FW_TASKS[$key][4] . ($rem !== '' ? ': ' . $rem : ''));
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'state' => fw_state(row('SELECT * FROM activations WHERE id=?', [(int) $id]))];
});
route('POST', '/activation/day/(\d+)', function ($n) {
  $u = require_user(); $a = activation_mine((int) $u['id']); $n = (int) $n; $b = body();
  if (!$a) fail(404, 'You do not currently have an activation to complete.', 'none');
  if (($a['scheme'] ?? '') === 'fw') fail(409, 'Your activation follows the new framework. Open My Activation to see your tasks.', 'framework');
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
  if (!$a || !in_array($a['status'], ['in_progress', 'returned'], true) || ($a['scheme'] ?? '') === 'fw') fail(409, 'There is no assessment waiting for you.', 'transition');
  $c = activation_content_ready($a['route']); if (!$c['complete']) fail(409, 'The rest of this activation is still being loaded by WODDI HQ. Your progress is saved.', 'content');
  $done = (int) val('SELECT COUNT(*) FROM activation_progress WHERE activation_id=? AND completed_at IS NOT NULL', [(int) $a['id']]);
  if ($done < $c['days_loaded']) fail(409, 'Finish every day of the activation first.', 'order');
  $f = readiness_form($a['network']); $st = []; $sa = [];
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
  $sc = activation_score(array_merge($a, ['status' => 'submitted'])); if ($sc['passed']) referrer_tell((int) $a['user_id'], 'passed', 'a' . $a['id'], (string) $sc['score']);   // v109.38
  notify_hq('activation', 'Assessment submitted: ' . work_name($u), ACT_ROUTES[$a['route']]['label'] . ', ' . position_title($a['position_code'], $a['network']) . ' (' . $a['network'] . ').', '#/referrals/activations/' . $a['id']);
  after_response(function () { push_drain(20); mail_drain(6); });
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
  if (($a['scheme'] ?? '') === 'fw') { $st = fw_state($a); return $st['decision_label'] . ($st['decision'] === 'in_progress' ? ', Day ' . $st['days_done'] . ' of ' . $st['days_total'] . ' done' : ''); }
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
      'stage' => activation_stage($a, (int) $a['done']), 'deadline' => $a['deadline'], 'extension_status' => $a['extension_status'], 'outcome' => $a['outcome'], 'started_at' => $a['started_at'],
      'score' => activation_score($a), 'seated' => (bool) current_seat((int) $a['user_id']), 'scheme' => $a['scheme'] ?? 'v1'];
  }
  return ['route' => $route, 'routes' => ACT_ROUTES, 'items' => $items, 'counts' => $c, 'content' => activation_content_ready($route), 'outcomes' => ACT_OUTCOMES];
});
route('GET', '/hq/activations/(\d+)', function ($id) {
  require_hq(); $a = row('SELECT * FROM activations WHERE id=?', [(int) $id]); if (!$a) fail(404, 'That activation was not found.', 'not_found');
  return ['activation' => activation_view($a, true) + ['score' => activation_score($a)], 'outcomes' => ACT_OUTCOMES];
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
  // v109.46 (Azeez, 22 Nov): a referred woman does the 3-Day route; only a woman with no referral does the 7-Day route
  $route = val("SELECT 1 FROM referrals WHERE user_id=? AND (link_id IS NOT NULL OR referrer_name<>'')", [(int) $u['id']]) ? 'ref3' : 'open7';
  $lv = $pos['level'];       // v109.46: the whole place of the seat she is considered for
  $place = ['lga' => in_array($lv, ['lga', 'community_cluster', 'chapter'], true) ? (str_in('lga', 120) ?: ($lv === 'lga' ? $area : (string) $u['lga'])) : '',
    'community' => in_array($lv, ['community_cluster', 'chapter'], true) ? (str_in('community', 120) ?: ($lv === 'community_cluster' ? $area : (string) $u['community'])) : '', 'chapter_id' => (int) ($b['chapter_id'] ?? 0) ?: null];
  $aid = activation_start((int) $u['id'], $route, (string) $u['network'], $pos['code'], (int) $unit['id'], $area, '', $place);
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
    if (in_array($o, ['not_selected', 'reserve', 'alternative_role'], true)) referrer_tell($uid, 'outcome', 'a' . $id . $o, ACT_OUTCOMES[$o]);   // v109.38
  } elseif (in_array($act, ['support', 'resume', 'breach'], true) && ($a['scheme'] ?? '') === 'fw') {      // v109.46: the framework's other decisions
    if ($act === 'support') { if (mb_strlen($note) < 3) fail(422, 'Write what support she needs.', 'note');
      q("UPDATE activations SET status='support', support_note=? WHERE id=?", [$note, (int) $id]); notify($uid, 'activation', 'WODDI HQ will support you', $note, '#/activation'); }
    elseif ($act === 'resume') { $ext = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('+3 days')->format('Y-m-d');
      q("UPDATE activations SET status='in_progress', support_note='', deadline=GREATEST(deadline, ?) WHERE id=?", [$ext, (int) $id]); notify($uid, 'activation', 'Your activation is open again', 'Continue from where you stopped.', '#/activation'); }
    else q('UPDATE activations SET compliance_breach=? WHERE id=?', [$note, (int) $id]);      // an empty note clears it
  } elseif ($act === 'appoint' || $act === 'approve') {
    // v109.34: only a candidate who has passed her activation becomes a volunteer leader, and only when HQ approves
    if ($act === 'appoint' && $a['outcome'] !== 'selected') fail(409, 'Record the outcome as Selected before appointing.', 'transition');
    if (!in_array($a['status'], ['submitted', 'completed'], true)) fail(409, 'She has not finished her activation yet. HQ approves after she submits her assessment.', 'transition');
    if (current_seat($uid)) fail(409, 'That person already holds a leadership seat.', 'seated');
    $sc = activation_score($a);
    if (($a['scheme'] ?? '') === 'fw') {          // v109.46: Activated only when both stages pass and HQ has verified every piece of evidence
      $st = fw_state($a);
      if (!$st['passed']) fail(409, 'She has not passed: ' . ($st['missing_gates'] ? 'missing ' . implode(', ', $st['missing_gates']) . '; ' : '') . 'Days 1 to 3 scored ' . $st['stage_a'] . ($st['stage_b'] !== null ? ', Days 4 to 7 scored ' . $st['stage_b'] : '') . ' (pass mark ' . FW_PASS . ' each)' . ($st['breach'] ? '; a compliance breach is recorded' : '') . '.', 'not_passed');
      if (!$st['verified_all']) fail(409, 'Verify each task\'s evidence first. HQ activates only when all of it is verified.', 'not_verified');
    } elseif (!$sc['passed']) fail(409, 'She has not passed the activation: score ' . $sc['score'] . ' of 100, the pass mark is ' . ACT_PASS . '. Return it to her with a note, or record another outcome.', 'not_passed');
    $res['title'] = activation_approve($a, (int) $me['id']);
  } elseif ($act === 'extension') {
    if ($a['extension_status'] !== 'requested') fail(409, 'There is no extension request to decide.', 'transition');
    if (!empty(body()['approve'])) {
      $until = work_date(str_in('until', 10)); if (!$until || $until <= gmdate('Y-m-d')) fail(422, 'Choose the new deadline, a date after today.', 'until');
      q("UPDATE activations SET extension_status='approved', status='in_progress', deadline=? WHERE id=?", [$until, (int) $id]);
      notify($uid, 'activation', 'Extension approved', 'Your journey has been extended to ' . date('j M Y', strtotime($until)) . '. Continue from wherever you stopped.', '#/activation');
    } else { q("UPDATE activations SET extension_status='declined' WHERE id=?", [(int) $id]); notify($uid, 'activation', 'Extension not approved', $note, '#/activation'); }
  } else fail(422, 'Unknown action.', 'action');
  log_activity((int) $me['id'], $uid, 'activation_' . $act, $note);
  after_response(function () { push_drain(10); mail_drain(6); });
  return $res;
});
