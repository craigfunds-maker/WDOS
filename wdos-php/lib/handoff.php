<?php
declare(strict_types=1);
/* Routes for the production handoff of 11 November 2026. Shared functions are in handoff_fns.php. */

/* ------------------------------------------------ D01 to D14 decision register -- */
route('GET', '/decisions', function () { require_hq(); return ['items' => decisions_state(), 'history' => rows("SELECT d.code, d.decision, d.approver, d.approved_on, d.version, d.created_at, CONCAT(u.first_name,' ',u.last_name) recorder FROM decisions d LEFT JOIN users u ON u.id=d.recorded_by ORDER BY d.id DESC LIMIT 60")]; });
route('POST', '/decisions/(D\d\d)', function ($code) {
  $me = require_hq(); if (!isset(DECISIONS[$code])) fail(404, 'That decision is not in the register.', 'not_found');
  $decision = str_in('decision', 600); $approver = str_in('approver', 160); $on = (string) (body()['approved_on'] ?? '');
  if (mb_strlen($decision) < 5) fail(422, 'Write the decision management took.', 'decision');
  if (mb_strlen($approver) < 3) fail(422, 'Name the person who approved it.', 'approver');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $on) || $on > gmdate('Y-m-d', time() + 86400)) fail(422, 'Give the date it was approved.', 'date');
  $v = (int) val('SELECT COALESCE(MAX(version),0) FROM decisions WHERE code=?', [$code]) + 1;
  q('INSERT INTO decisions (code,decision,approver,approved_on,version,recorded_by,created_at) VALUES (?,?,?,?,?,?,?)', [$code, $decision, $approver, $on, $v, (int) $me['id'], now()]);
  log_activity((int) $me['id'], null, 'decision_recorded', "$code v$v · $approver");
  return ['ok' => true, 'version' => $v];
});

/* ------------------------------------------------ confidential cases -- */
route('GET', '/cases/options', function () { require_user(); return ['categories' => CASE_CATEGORIES]; });
route('POST', '/cases', function () {
  $u = require_user(); throttle('case:' . (int) $u['id'], 6, 86400);
  $cat = (string) (body()['category'] ?? ''); if (!isset(CASE_CATEGORIES[$cat])) fail(422, 'Choose what this is about.', 'category');
  $text = str_in('body', 6000); if (mb_strlen($text) < 10) fail(422, 'Tell us what happened, in your own words.', 'body');
  $aboutText = str_in('about', 190); $about = null;
  // the person named, when she can be matched: used only to keep the case away from her, never shown back to the reporter
  if ($aboutText !== '') {
    $m = rows("SELECT id FROM users WHERE member_no=? OR CONCAT(first_name,' ',last_name)=? LIMIT 2", [$aboutText, $aboutText]);
    if (count($m) === 1) $about = (int) $m[0]['id'];
  }
  if (!empty(body()['about_my_leader']) && !$about) {                         // "my chapter leader", without having to know a name
    $lid = (int) val('SELECT s.user_id FROM seats s WHERE s.chapter_id=? AND s.ends_at IS NULL AND s.he_for_she=0 ORDER BY s.id LIMIT 1', [(int) ($u['chapter_id'] ?? 0)]);
    if ($lid) { $about = $lid; if ($aboutText === '') $aboutText = 'My chapter leader'; }
  }
  throttle_hit('case:' . (int) $u['id']);
  $c = case_open((int) $u['id'], $cat, $about, $aboutText, $text);
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'ref' => $c['ref']];
});
route('GET', '/my/cases', function () {
  $u = require_user();
  return ['items' => array_map(fn($c) => ['ref' => $c['ref'], 'category' => CASE_CATEGORIES[$c['category']] ?? $c['category'], 'status' => $c['status'], 'status_text' => CASE_STATUS[$c['status']] ?? $c['status'], 'outcome' => $c['outcome'], 'created_at' => $c['created_at'], 'updated_at' => $c['updated_at']],
    rows('SELECT * FROM cases WHERE reporter_id=? ORDER BY id DESC LIMIT 40', [(int) $u['id']]))];
});
route('GET', '/hq/cases', function () {
  $me = require_hq(); $id = (int) $me['id'];
  // a handler never sees a case that is about her, or one she filed herself
  $items = rows("SELECT c.*, CONCAT(r.first_name,' ',r.last_name) reporter, r.member_no, r.network, CONCAT(h.first_name,' ',h.last_name) handler
                 FROM cases c JOIN users r ON r.id=c.reporter_id LEFT JOIN users h ON h.id=c.handled_by
                 WHERE (c.about_user_id IS NULL OR c.about_user_id<>?) AND c.reporter_id<>? ORDER BY FIELD(c.status,'received','in_review','resolved','closed'), c.id DESC LIMIT 200", [$id, $id]);
  $out = [];
  foreach ($items as $c) $out[] = ['id' => (int) $c['id'], 'ref' => $c['ref'], 'category' => CASE_CATEGORIES[$c['category']] ?? $c['category'], 'category_key' => $c['category'], 'about' => $c['about_text'], 'about_matched' => (bool) $c['about_user_id'],
    'body' => $c['body'], 'status' => $c['status'], 'status_text' => CASE_STATUS[$c['status']] ?? $c['status'], 'outcome' => $c['outcome'], 'reporter' => $c['reporter'], 'member_no' => $c['member_no'], 'network' => $c['network'],
    'handler' => $c['handler'], 'created_at' => $c['created_at'], 'updated_at' => $c['updated_at'], 'notes' => rows("SELECT n.note, n.created_at, CONCAT(u.first_name,' ',u.last_name) author FROM case_notes n JOIN users u ON u.id=n.author_id WHERE n.case_id=? ORDER BY n.id", [(int) $c['id']])];
  // WNNN members at or near 30 (D08): a list for HQ, never an automatic move
  $ages = rows("SELECT id, first_name, last_name, member_no, country_code, dob, age_notice_at FROM users WHERE status='active' AND network='WNNN' AND participant_class='member' AND dob IS NOT NULL AND DATE_ADD(dob, INTERVAL 30 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY dob LIMIT 200");
  return ['items' => $out, 'statuses' => CASE_STATUS, 'as_of' => now(),
    'age_transition' => array_map(fn($a) => ['id' => (int) $a['id'], 'name' => trim($a['first_name'] . ' ' . $a['last_name']), 'member_no' => $a['member_no'], 'country' => country_name($a['country_code']), 'turns_30_on' => gmdate('Y-m-d', strtotime($a['dob'] . ' +30 years')), 'told' => (bool) $a['age_notice_at'], 'leader' => (bool) val('SELECT 1 FROM seats WHERE user_id=? AND ends_at IS NULL', [(int) $a['id']])], $ages)];
});
route('POST', '/hq/cases/(\d+)', function ($id) {
  $me = require_hq(); $c = row('SELECT * FROM cases WHERE id=?', [(int) $id]);
  if (!$c || !case_may_handle($me, $c)) fail(404, 'That case was not found.', 'not_found');       // the same answer whether it is missing or hers: nothing is disclosed
  $b = body(); $st = (string) ($b['status'] ?? $c['status']); if (!isset(CASE_STATUS[$st])) fail(422, 'Choose a status.', 'status');
  $outcome = array_key_exists('outcome', $b) ? str_in('outcome', 1000) : $c['outcome']; $note = str_in('note', 2000);
  if (in_array($st, ['resolved', 'closed'], true) && mb_strlen($outcome) < 5) fail(422, 'Write what was decided, in words the member will read.', 'outcome');
  q('UPDATE cases SET status=?, outcome=?, handled_by=?, updated_at=? WHERE id=?', [$st, $outcome, (int) $me['id'], now(), (int) $id]);
  if ($note !== '') q('INSERT INTO case_notes (case_id,author_id,note,created_at) VALUES (?,?,?,?)', [(int) $id, (int) $me['id'], $note, now()]);
  log_activity((int) $me['id'], null, 'case_updated', $c['ref'] . ' · ' . $st);
  if ($st !== $c['status']) { notify((int) $c['reporter_id'], 'account', 'Your private report ' . $c['ref'] . ' was updated', 'It is now: ' . CASE_STATUS[$st] . '. Open Help to read more.', '#/help'); after_response(fn() => push_drain(5)); }
  return ['ok' => true];
});
/* A WNNN member who has reached 30 is moved to WGMN by HQ. The Person ID, history and referrals stay; her WNNN appointment is not carried over. */
route('POST', '/hq/age-transition/(\d+)', function ($id) {
  $me = require_hq(); $u = row("SELECT * FROM users WHERE id=? AND network='WNNN'", [(int) $id]);
  if (!$u || !$u['dob']) fail(404, 'That WNNN member was not found.', 'not_found');
  if (age_on($u['dob']) < 30) fail(422, 'She has not reached 30 yet.', 'age');
  $seat = current_seat((int) $id);
  if ($seat && empty(body()['confirm_seat'])) fail(409, 'She holds the WNNN seat ' . seat_title($seat) . '. Her seat will become vacant. Confirm to continue.', 'seat');
  q("UPDATE seats SET ends_at=?, end_reason='age_transition' WHERE user_id=? AND ends_at IS NULL", [now(), (int) $id]);
  q("UPDATE users SET network='WGMN' WHERE id=?", [(int) $id]);
  chapter_replace_after_change((int) $id);
  log_activity((int) $me['id'], (int) $id, 'age_transition', 'WNNN to WGMN at 30');
  notify((int) $id, 'account', 'Welcome to WGMN, ' . $u['first_name'], 'You have moved from WNNN to the WODDI Good Mother Network. Your Person ID, your records and your history came with you.', '#/');
  after_response(fn() => push_drain(5));
  return ['ok' => true];
});

/* ------------------------------------------------ membership lifecycle and own data -- */
route('GET', '/me/lifecycle', function () {
  $u = require_user(); [$k, $t] = lifecycle_of($u);
  $labels = ['registered' => 'You registered', 'membership_paused' => 'You paused your membership', 'membership_resumed' => 'You came back', 'suspended' => 'Restricted by WODDI HQ', 'reinstated' => 'Restored by WODDI HQ', 'details_corrected' => 'Your details were corrected', 'age_transition' => 'You moved from WNNN to WGMN', 'journey_activated' => 'You completed your seven days'];
  $hist = []; foreach (rows('SELECT action, created_at FROM activity_log WHERE subject_id=? AND action IN (' . implode(',', array_fill(0, count($labels), '?')) . ') ORDER BY id DESC LIMIT 30', array_merge([(int) $u['id']], array_keys($labels))) as $r) $hist[] = ['what' => $labels[$r['action']], 'at' => $r['created_at']];
  return ['state' => $k, 'text' => $t, 'paused' => !empty($u['paused_at']), 'history' => $hist, 'is_leader' => (bool) current_seat((int) $u['id'])];
});
route('POST', '/me/pause', function () { $u = require_user(); member_pause($u, !empty(body()['pause'])); return ['ok' => true, 'paused' => !empty(body()['pause'])]; });
route('POST', '/me/withdraw', function () {
  $u = require_user(); $pw = (string) (body()['password'] ?? '');
  if ($u['password_hash'] && !password_verify($pw, $u['password_hash'])) { throttle('wd:' . (int) $u['id'], 5, 900); throttle_hit('wd:' . (int) $u['id']); fail(403, 'That PIN or password is not correct.', 'bad_credentials'); }
  member_withdraw($u, str_in('reason', 200));
  if (!empty(body()['erase'])) { try { case_open((int) $u['id'], 'privacy', null, '', 'The member closed her membership and asked for her personal information to be removed. This request needs a privacy review before anything is deleted.'); } catch (Throwable $e) { error_log('[wdos erase] ' . $e->getMessage()); } }
  session_end(); after_response(fn() => push_drain(10));
  return ['ok' => true];
});
route('GET', '/me/export', function () { $u = require_user(); throttle('exp:' . (int) $u['id'], 10, 3600); throttle_hit('exp:' . (int) $u['id']); log_activity((int) $u['id'], (int) $u['id'], 'own_data_downloaded'); return member_export($u); });

/* ------------------------------------------------ protected registration drafts -- */
/* The phone keeps only an opaque token. The details sit here for two days and are removed when the membership is made. The PIN is never part of a draft. */
route('POST', '/public/draft', function () {
  throttle('draft:' . client_ip(), 240, 3600); throttle_hit('draft:' . client_ip());
  $tok = (string) (body()['token'] ?? ''); $d = body()['data'] ?? null; if (!is_array($d)) fail(422, 'Nothing to keep.', 'data');
  $keep = []; foreach (['network', 'step', 'first_name', 'last_name', 'dob', 'contact', 'country_id', 'state', 'lga', 'community', 'ward', 'source'] as $k) if (isset($d[$k]) && is_scalar($d[$k])) $keep[$k] = mb_substr((string) $d[$k], 0, 160);
  $json = json_encode($keep, JSON_UNESCAPED_UNICODE); $exp = gmdate('Y-m-d H:i:s', time() + 2 * 86400);
  if (strlen($tok) >= 32 && val('SELECT 1 FROM reg_drafts WHERE token_hash=?', [hash('sha256', $tok)])) q('UPDATE reg_drafts SET data=?, expires_at=? WHERE token_hash=?', [$json, $exp, hash('sha256', $tok)]);
  else { $tok = token(32); q('INSERT INTO reg_drafts (token_hash,data,created_at,expires_at) VALUES (?,?,?,?)', [hash('sha256', $tok), $json, now(), $exp]); }
  return ['ok' => true, 'token' => $tok];
});
route('POST', '/public/draft/read', function () {
  throttle('draftr:' . client_ip(), 60, 3600); throttle_hit('draftr:' . client_ip());
  $tok = (string) (body()['token'] ?? ''); $r = strlen($tok) >= 32 ? row('SELECT data FROM reg_drafts WHERE token_hash=? AND expires_at>?', [hash('sha256', $tok), now()]) : null;
  return ['data' => $r ? (json_decode($r['data'], true) ?: (object) []) : null];
});
route('POST', '/public/draft/forget', function () { $tok = (string) (body()['token'] ?? ''); if (strlen($tok) >= 32) q('DELETE FROM reg_drafts WHERE token_hash=?', [hash('sha256', $tok)]); return ['ok' => true]; });
route('GET', '/public/sources', fn() => ['sources' => SOURCES]);

/* ------------------------------------------------ where members heard of WODDI -- */
route('GET', '/hq/sources', function () {
  require_hq();
  $r = rows("SELECT CASE WHEN u.source='' THEN 'not_stated' ELSE u.source END src, u.network, COUNT(*) registered, COALESCE(SUM(j.status='active'),0) activated, COALESCE(SUM(u.chapter_id IS NOT NULL),0) placed
             FROM users u LEFT JOIN member_journeys j ON j.user_id=u.id WHERE u.role='member' AND u.network IS NOT NULL GROUP BY src, u.network ORDER BY registered DESC");
  $camp = rows("SELECT campaign, COUNT(*) registered FROM users WHERE campaign<>'' GROUP BY campaign ORDER BY registered DESC LIMIT 40");
  return ['as_of' => now(), 'labels' => SOURCES + ['referral' => 'An invitation link', 'not_stated' => 'Not stated'], 'rows' => array_map(fn($x) => ['source' => (string) $x['src'], 'network' => (string) $x['network'], 'registered' => (int) $x['registered'], 'activated' => (int) $x['activated'], 'placed' => (int) $x['placed']], $r), 'campaigns' => $camp];
});

/* ------------------------------------------------ appointment states (Q20) -- */
route('POST', '/hq/members/(\d+)/seat-state', function ($id) {
  $me = require_hq(); $s = row('SELECT * FROM seats WHERE user_id=? AND ends_at IS NULL ORDER BY id DESC LIMIT 1', [(int) $id]);
  if (!$s) fail(404, 'This member holds no seat.', 'not_found');
  $to = (string) (body()['state'] ?? ''); $note = str_in('note', 200);
  $live = ['appointed' => 'Appointed', 'acting' => 'Acting', 'pending' => 'Candidate', 'on_leave' => 'On leave', 'suspended' => 'Suspended'];
  $ends = ['resigned' => 'Resigned', 'replaced' => 'Replaced', 'removed' => 'Appointment ended'];
  if (isset($live[$to])) {
    if (in_array($to, ['on_leave', 'suspended'], true) && mb_strlen($note) < 3) fail(422, 'Give the reason.', 'note');
    q('UPDATE seats SET status=?, status_note=? WHERE id=?', [$to, $note, (int) $s['id']]);
  } elseif (isset($ends[$to])) {
    q('UPDATE seats SET ends_at=?, end_reason=?, status_note=? WHERE id=?', [now(), $to, $note, (int) $s['id']]);
    // open tasks she was given as a leader go back to the people who assigned them
    foreach (rows("SELECT DISTINCT assigned_by FROM tasks WHERE assignee_id=? AND assigned_by<>? AND status IN ('open','in_progress','submitted')", [(int) $id, (int) $id]) as $t) notify((int) $t['assigned_by'], 'task', 'A leader you gave tasks to has left her seat', 'Open your tasks to reassign what is still open.', '#/tasks');
  } else fail(422, 'Choose a state.', 'state');
  log_activity((int) $me['id'], (int) $id, 'seat_state', ($live[$to] ?? $ends[$to]) . ($note !== '' ? ' · ' . $note : ''));
  notify((int) $id, 'appointment', 'Your leadership appointment: ' . ($live[$to] ?? $ends[$to]), in_array($to, ['on_leave', 'suspended'], true) || isset($ends[$to]) ? 'Your leadership dashboard is closed while this applies. Your membership is not affected.' : 'Your leadership dashboard is open.', '#/');
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'state' => $to];
});
route('GET', '/hq/members/(\d+)/seat-state', function ($id) {
  require_hq(); $s = row('SELECT status, status_note, starts_at FROM seats WHERE user_id=? AND ends_at IS NULL ORDER BY id DESC LIMIT 1', [(int) $id]);
  return ['seat' => $s, 'history' => rows('SELECT position_code, network, area_label, status, starts_at, ends_at, end_reason, status_note FROM seats WHERE user_id=? ORDER BY id DESC LIMIT 20', [(int) $id])];
});

/* ------------------------------------------------ meeting replies (Q34) -- */
route('POST', '/meetings/(\d+)/rsvp', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id);
  if (!is_hq($u) && !meeting_is_invited($u, $m)) fail(403, 'This meeting is for another unit.', 'forbidden');
  $a = (string) (body()['answer'] ?? ''); if (!in_array($a, ['yes', 'no', 'maybe'], true)) fail(422, 'Choose yes, no or maybe.', 'answer');
  if ($m['status'] !== 'scheduled') fail(409, 'This meeting is no longer open for replies.', 'transition');
  q('DELETE FROM meeting_rsvps WHERE meeting_id=? AND user_id=?', [(int) $id, (int) $u['id']]);
  q('INSERT INTO meeting_rsvps (meeting_id,user_id,answer,answered_at) VALUES (?,?,?,?)', [(int) $id, (int) $u['id'], $a, now()]);
  return ['ok' => true, 'answer' => $a];
});
route('GET', '/meetings/(\d+)/rsvp', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id);
  if (!is_hq($u) && !meeting_is_invited($u, $m)) fail(403, 'This meeting is for another unit.', 'forbidden');
  $c = ['yes' => 0, 'no' => 0, 'maybe' => 0]; foreach (rows('SELECT answer, COUNT(*) n FROM meeting_rsvps WHERE meeting_id=? GROUP BY answer', [(int) $id]) as $r) $c[$r['answer']] = (int) $r['n'];
  return ['mine' => (string) (val('SELECT answer FROM meeting_rsvps WHERE meeting_id=? AND user_id=?', [(int) $id, (int) $u['id']]) ?? ''), 'counts' => $c];
});

/* ------------------------------------------------ annual calendar (Q35) -- */
route('GET', '/calendar', function () {
  $u = require_user(); $year = (int) ($_GET['year'] ?? gmdate('Y')); if ($year < 2020 || $year > 2100) $year = (int) gmdate('Y');
  [$w, $p] = calendar_where($u);
  $ev = rows("SELECT id, title, programme, starts_on, ends_on, network, country_code, note FROM calendar_events WHERE $w AND starts_on<=? AND ends_on>=? ORDER BY starts_on, id", array_merge($p, ["$year-12-31", "$year-01-01"]));
  return ['year' => $year, 'can_edit' => is_hq($u), 'programmes' => PROGRAMMES, 'as_of' => now(),
    'items' => array_map(fn($e) => ['id' => (int) $e['id'], 'title' => $e['title'], 'programme' => $e['programme'], 'programme_name' => PROGRAMMES[$e['programme']] ?? '', 'starts_on' => $e['starts_on'], 'ends_on' => $e['ends_on'], 'network' => $e['network'], 'country' => $e['country_code'] !== '' ? country_name($e['country_code']) : '', 'note' => $e['note']], $ev)];
});
route('POST', '/calendar', function () {
  $me = require_hq(); $b = body(); $title = str_in('title', 190); if (mb_strlen($title) < 3) fail(422, 'Give the event a name.', 'title');
  $from = (string) ($b['starts_on'] ?? ''); $to = (string) ($b['ends_on'] ?? '') ?: $from;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) fail(422, 'Give the date, and an end date that is not before it.', 'date');
  $prog = isset(PROGRAMMES[$b['programme'] ?? '']) ? $b['programme'] : 'Other'; $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : 'ALL';
  $cc = strtoupper(str_in('country_code', 2)); if ($cc !== '' && country_name($cc) === '') fail(422, 'That country is not in WDOS.', 'country');
  $id = ins('INSERT INTO calendar_events (title,programme,starts_on,ends_on,network,country_code,note,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)', [$title, $prog, $from, $to, $net, $cc, str_in('note', 600), (int) $me['id'], now()]);
  log_activity((int) $me['id'], null, 'calendar_added', $title . ' · ' . $from);
  return ['ok' => true, 'id' => $id];
});
route('POST', '/calendar/(\d+)/delete', function ($id) { $me = require_hq(); $t = val('SELECT title FROM calendar_events WHERE id=?', [(int) $id]); if ($t === null) fail(404, 'That date was not found.', 'not_found'); q('DELETE FROM calendar_events WHERE id=?', [(int) $id]); log_activity((int) $me['id'], null, 'calendar_removed', (string) $t); return ['ok' => true]; });

/* ------------------------------------------------ the 80 percent instrument -- */
route('GET', '/hq/workflows', function () {
  require_hq(); workflow_seed_once();
  $items = rows('SELECT * FROM workflow_catalogue ORDER BY workflow_id');
  return ['items' => $items, 'score' => workflow_score($items), 'as_of' => now()];
});
route('POST', '/hq/workflows', function () {
  $me = require_hq(); $b = body(); $wid = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($b['workflow_id'] ?? '')));
  if ($wid === '') fail(422, 'Give the workflow an ID, for example W31.', 'id');
  $yn = fn($k) => in_array($b[$k] ?? '', ['yes', 'no', 'part'], true) ? $b[$k] : 'no';
  $wt = ($b['eligible_weight'] ?? '') === '' || $b['eligible_weight'] === null ? null : max(0, min(1000, (float) $b['eligible_weight']));
  $mins = ($b['manual_minutes_baseline'] ?? '') === '' || $b['manual_minutes_baseline'] === null ? null : max(0, (int) $b['manual_minutes_baseline']);
  $vd = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($b['verification_date'] ?? '')) ? $b['verification_date'] : null; $vb = str_in('verified_by', 120);
  if (($vb === '') !== ($vd === null)) fail(422, 'A verification needs both the name of the person and the date.', 'verify');
  $f = [str_in('area', 60), str_in('workflow_name', 190), str_in('steps', 400), str_in('trigger_text', 190), str_in('outcome', 190), str_in('frequency', 40), $mins, $wt, $yn('digitally_executable'), $yn('exception_coverage'), $yn('used_in_production'), str_in('test_id', 40), $vb, $vd, str_in('source_system', 40) ?: 'WDOS', now()];
  if (mb_strlen($f[1]) < 3) fail(422, 'Name the workflow.', 'name');
  if (val('SELECT 1 FROM workflow_catalogue WHERE workflow_id=?', [$wid])) q('UPDATE workflow_catalogue SET area=?, workflow_name=?, steps=?, trigger_text=?, outcome=?, frequency=?, manual_minutes_baseline=?, eligible_weight=?, digitally_executable=?, exception_coverage=?, used_in_production=?, test_id=?, verified_by=?, verification_date=?, source_system=?, updated_at=? WHERE workflow_id=?', array_merge($f, [$wid]));
  else q('INSERT INTO workflow_catalogue (area,workflow_name,steps,trigger_text,outcome,frequency,manual_minutes_baseline,eligible_weight,digitally_executable,exception_coverage,used_in_production,test_id,verified_by,verification_date,source_system,updated_at,workflow_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($f, [$wid]));
  log_activity((int) $me['id'], null, 'workflow_saved', $wid);
  return ['ok' => true];
});

/* ------------------------------------------------ platform health without secrets -- */
route('GET', '/hq/health', function () {
  require_hq(); $n = fn(string $sql, array $p = []) => (int) (val($sql, $p) ?? 0);
  return ['as_of' => now(), 'build' => WDOS_BUILD,
    'queues' => ['push_waiting' => $n("SELECT COUNT(*) FROM push_outbox WHERE status='queued'"), 'push_failed' => $n("SELECT COUNT(*) FROM push_outbox WHERE status NOT IN ('queued','sending','sent')"), 'mail_waiting' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status='queued'"), 'mail_failed' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status NOT IN ('queued','sending','sent')")],
    'jobs' => rows('SELECT job, MAX(ran_at) last_ran, COUNT(*) runs FROM cron_runs WHERE ran_on>=? GROUP BY job ORDER BY job', [gmdate('Y-m-d', time() - 7 * 86400)]),
    'switches' => ['email' => (bool) cfg('BREVO_API_KEY') && (bool) cfg('MAIL_FROM'), 'institute_key' => (bool) cfg('INTEGRATION_KEY')],
    'reconcile' => ['chapter_counts_off' => $n('SELECT COUNT(*) FROM chapters c WHERE c.members<>(SELECT COUNT(*) FROM chapter_members m WHERE m.chapter_id=c.id AND m.ended_at IS NULL)'),
      'members_in_two_chapters' => $n('SELECT COUNT(*) FROM (SELECT user_id FROM chapter_members WHERE ended_at IS NULL GROUP BY user_id HAVING COUNT(*)>1) x'),
      'cross_network_placements' => $n('SELECT COUNT(*) FROM chapter_members m JOIN chapters c ON c.id=m.chapter_id JOIN users u ON u.id=m.user_id WHERE m.ended_at IS NULL AND u.network<>c.network'),
      'chapters_over_capacity' => $n('SELECT COUNT(*) FROM chapters c WHERE c.capacity_exception=0 AND c.members>COALESCE(c.target_capacity, ?)', [policy_int('chapter_capacity')])],
    'drafts_held' => $n('SELECT COUNT(*) FROM reg_drafts WHERE expires_at>?', [now()]), 'open_cases' => $n("SELECT COUNT(*) FROM cases WHERE status IN ('received','in_review')")];
});

/* ------------------------------------------------ the handoff register (HQ only: it lists what is not yet built) -- */
route('GET', '/hq/handoff', function () { require_hq(); $r = json_decode((string) @file_get_contents(__DIR__ . '/handoff_register.json'), true); if (!is_array($r)) fail(500, 'The register file is missing.', 'missing'); return $r + ['decisions' => decisions_state()]; });
