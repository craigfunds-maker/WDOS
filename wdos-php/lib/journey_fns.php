<?php
declare(strict_types=1);
/* Structure, referral and activation: shared functions. Loaded before every route, because joining
 * (in api/index.php) records the referral and opens the activation. Wording that a candidate reads is
 * taken from the current WDOS; programme content (days, questions, assessment) is data. */

const ACT_ROUTES = ['ref3' => ['label' => '3-Day Referral Activation', 'days' => 3], 'open7' => ['label' => '7-Day Activation', 'days' => 7]];
const ACT_NOTICE = 'Referral and activation are important steps in the process, but they do not guarantee appointment. Activation helps assess readiness for the role.';
const REF_CATS = ['envoy' => 'A WODDI Envoy', 'focal_person' => 'A WODDI focal person or referral lead', 'volunteer_leader' => 'An existing WODDI volunteer leader', 'individual' => 'An individual who knows WODDI', 'other' => 'Other'];
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
    if ($link && $link['kind'] !== 'envoy' && $link['network'] !== $f['network']) $link = null;                     // a WGMN link cannot place someone in WNNN; an envoy's link serves both networks
    $position = $link && $link['position_code'] !== '' ? $link['position_code'] : (string) ($ref['position'] ?? '');
    $pos = $position !== '' ? row('SELECT * FROM positions WHERE code=?', [$position]) : null;
    $cat = $link && $link['kind'] === 'envoy' ? 'envoy' : (isset(REF_CATS[$ref['category'] ?? '']) ? $ref['category'] : ($link ? 'volunteer_leader' : ''));
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
    $out[] = ['day' => (int) $d['day_no'], 'title' => $d['title'], 'instruction' => $d['instruction'], 'content' => jn_json($d['content']), 'notice' => $d['notice'], 'questions' => jn_json($d['questions']), 'acks' => jn_json($d['acks']), 'needs_profile' => (int) ($d['needs_profile'] ?? 0) === 1, 'purpose' => (string) ($d['purpose'] ?? '')];
  }
  return $out;
}
function readiness_form(string $network = '', bool $forHq = false): array {
  $f = ['statements' => [], 'questions' => [], 'confirmations' => []];
  foreach (rows('SELECT * FROM readiness_items ORDER BY kind, seq, id') as $r) {
    $k = ['statement' => 'statements', 'question' => 'questions', 'confirmation' => 'confirmations'][$r['kind']] ?? null; if (!$k) continue;
    if (($r['network'] ?? '') !== '' && $network !== '' && $r['network'] !== $network) continue;   // statements are written per network
    $x = ['seq' => (int) $r['seq'], 'section' => $r['section'], 'section_title' => $r['section_title'], 'text' => $r['body']];
    if ($forHq && $k === 'questions') $x += ['reviewer_note' => (string) ($r['reviewer_note'] ?? ''), 'critical' => (int) ($r['critical'] ?? 0) === 1];
    $f[$k][] = $x;
  }
  return $f;
}
/** Is the whole programme for this route loaded, so a candidate can finish? */
function activation_content_ready(string $route): array {
  $days = (int) val('SELECT COUNT(*) FROM activation_days WHERE route=?', [$route]);
  $assess = (int) val("SELECT COUNT(*) FROM readiness_items WHERE kind='statement'");
  return ['days_loaded' => $days, 'days_expected' => ACT_ROUTES[$route]['days'], 'assessment' => $assess > 0, 'complete' => $days >= ACT_ROUTES[$route]['days'] && $assess > 0];
}

/* v109.34: the system scores every referral activation (Azeez, 20 Nov 2026: "the system score them in the hq dashboard; the state
 * envoy also see their scores; they are not made a volunteer leader until they pass the 3 days activation and HQ approve").
 * Out of 100, worked out from what she actually did, so HQ and the envoy see the same number and why:
 *   30  the three days, 10 for each day completed
 *   10  finished on time (assessment submitted by the deadline)
 *   25  understanding: the readiness statements she answered "Yes, I understand" (a "need clarification" counts half)
 *   25  written answers: each short answer counts in full from about 120 characters, less if shorter
 *   10  readiness: ready 10, interested 5, not now 0
 * The pass mark is 60. HQ can appoint only a candidate who has passed. */
const ACT_PASS = 60;
function activation_score(array $a): array {
  $days = max(1, (int) (ACT_ROUTES[$a['route']]['days'] ?? 3));
  $done = (int) val('SELECT COUNT(*) FROM activation_progress WHERE activation_id=? AND completed_at IS NOT NULL', [(int) $a['id']]);
  $parts = ['days' => min(30, (int) round(30 * min($done, $days) / $days)), 'on_time' => 0, 'understanding' => 0, 'answers' => 0, 'readiness' => 0];
  $r = row('SELECT * FROM readiness_responses WHERE activation_id=?', [(int) $a['id']]);
  if ($r) {
    $deadlineEnd = (new DateTimeImmutable($a['deadline'] . ' 23:59:59', new DateTimeZone('Africa/Lagos')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    if ($done >= $days && $r['submitted_at'] <= $deadlineEnd) $parts['on_time'] = 10;
    $st = jn_json($r['statements']); if ($st) { $pts = 0; foreach ($st as $v) $pts += $v === 'yes' ? 1 : ($v === 'clarify' ? 0.5 : 0); $parts['understanding'] = (int) round(25 * $pts / count($st)); }
    $sa = jn_json($r['short_answers']); if ($sa) { $q = 0; foreach ($sa as $v) $q += min(1, mb_strlen(trim((string) $v)) / 120); $parts['answers'] = (int) round(25 * $q / count($sa)); }
    $parts['readiness'] = ['ready' => 10, 'interested' => 5][$r['readiness']] ?? 0;
  }
  $score = array_sum($parts); $final = (bool) $r;
  return ['score' => $score, 'pass_mark' => ACT_PASS, 'final' => $final, 'passed' => $final && $score >= ACT_PASS, 'days_done' => $done, 'parts' => $parts];
}
/** HQ approves: the candidate becomes a volunteer leader in the seat she applied for, and she and the person who referred her are told
 *  in the app, on the phone and by email. */
function activation_approve(array $a, int $byId): string {
  $uid = (int) $a['user_id'];
  $title = seat_open($uid, $a['position_code'], $a['network'], (int) $a['org_unit_id'], $a['area_label'], 'appointed', $byId);
  q("UPDATE activations SET status='completed', outcome='selected', decided_at=COALESCE(decided_at,?), decided_by=COALESCE(decided_by,?) WHERE id=?", [now(), $byId, (int) $a['id']]);
  $u = row('SELECT * FROM users WHERE id=?', [$uid]);
  $site = function_exists('app_origin') ? app_origin() : '';
  if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL) && !preg_match('/\.(invalid|test|local|example)$/', $u['email'])) {
    $body = '<p>Dear ' . htmlspecialchars($u['first_name']) . ',</p><p>You passed your 3-Day Referral Activation and WODDI Headquarters has approved you as <b>' . htmlspecialchars($title) . '</b>.</p>'
      . '<p>Sign in to WDOS to read and accept your Volunteer Leadership Agreement. Your leader dashboard opens straight after.</p><p>No Woman Is Left Out.</p>';
    mail_queue($u['email'], trim($u['first_name'] . ' ' . $u['last_name']), 'You are now a WODDI volunteer leader', mail_wrap('Congratulations, you are approved', $body, 'Open WDOS', $site . '/#/appointment'));
  }
  $ref = row('SELECT r.referrer_id, e.email, e.first_name FROM referrals r JOIN users e ON e.id=r.referrer_id WHERE r.user_id=?', [$uid]);
  if ($ref) {
    $who = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    notify((int) $ref['referrer_id'], 'activation', $who . ' is now a volunteer leader', 'HQ approved her as ' . $title . '. Thank you for bringing her in.', '#/');
    if (filter_var($ref['email'], FILTER_VALIDATE_EMAIL) && !preg_match('/\.(invalid|test|local|example)$/', $ref['email']))
      mail_queue($ref['email'], (string) $ref['first_name'], $who . ' is now a WODDI volunteer leader', mail_wrap('Your candidate was approved', '<p>Dear ' . htmlspecialchars((string) $ref['first_name']) . ',</p><p>' . htmlspecialchars($who) . ' passed her 3-Day Referral Activation and WODDI Headquarters approved her as <b>' . htmlspecialchars($title) . '</b>.</p><p>Thank you for bringing her in.</p>', 'Open my dashboard', $site . '/#/'));
  }
  after_response(function () { mail_drain(6); push_drain(10); });
  return $title;
}

/* v109.38: the person who referred a candidate hears how she is doing by email, in the app and on the phone (Azeez, 20 Nov 2026:
 * "email to woddi envoy if the people they refer are inactive, not logged in, passed activation, appointed to a position or not
 * considered for a position"). Appointment is told by activation_approve. Each piece of news goes once, kept in envoy_alerts,
 * and every email carries a button that opens a follow-up message to the candidate with a suggested text. */
const REF_NEWS = [
  'nosign'  => ['{name} has not signed in for 3 days', 'She joined through your link but has not opened WDOS in 3 days. Send her a short follow-up message.'],
  'stalled' => ['{name} did not finish her activation', 'Her 3-day activation time ended before she finished. Send her a short follow-up message to encourage her.'],
  'passed'  => ['{name} passed her activation', 'She scored {x} of 100. WODDI HQ will now decide on her seat.'],
  'outcome' => ['{name} was not appointed this time', 'WODDI HQ recorded this outcome: {x}. You can send her a short message.'],
];
const REF_MAIL_WORDS = [
  'en' => ['Dear', 'Send her a message'], 'fr' => ['Chère', 'Lui envoyer un message'], 'pt' => ['Cara', 'Enviar-lhe uma mensagem'],
  'sw' => ['Mpendwa', 'Mtumie ujumbe'], 'ar' => ['عزيزتي', 'أرسلي لها رسالة'],
];
function referrer_tell(int $candidateId, string $kind, string $ref, string $x = ''): bool {
  if (!isset(REF_NEWS[$kind])) return false;
  try {
    $rid = (int) val('SELECT referrer_id FROM referrals WHERE user_id=? AND referrer_id IS NOT NULL', [$candidateId]); if (!$rid) return false;
    $c = row('SELECT first_name, last_name FROM users WHERE id=?', [$candidateId]);
    $e = row("SELECT id, email, first_name, role, lang FROM users WHERE id=? AND status='active'", [$rid]);
    if (!$c || !$e) return false;
    if (q('INSERT IGNORE INTO envoy_alerts (envoy_id,user_id,kind,ref,sent_at) VALUES (?,?,?,?,?)', [$rid, $candidateId, mb_substr('ref_' . $kind, 0, 16), mb_substr($ref, 0, 32), now()])->rowCount() === 0) return false;
    $name = trim($c['first_name'] . ' ' . $c['last_name']);
    $title = str_replace('{name}', $name, REF_NEWS[$kind][0]); $body = str_replace('{x}', $x, REF_NEWS[$kind][1]);
    $url = '#/inbox/to/' . $candidateId . '?f=' . $kind . '&n=' . rawurlencode((string) $c['first_name']);
    notify($rid, 'activation', $title, $body, $url);
    if (filter_var($e['email'], FILTER_VALIDATE_EMAIL) && !preg_match('/\.(invalid|test|local|example)$/i', (string) $e['email'])) {
      $lang = in_array($e['lang'] ?? '', ['fr', 'pt', 'sw', 'ar'], true) ? $e['lang'] : 'en';
      [$st, $sb] = note_in($lang, $title, $body) ?? [$title, $body]; [$dear, $btn] = REF_MAIL_WORDS[$lang];
      mail_queue((string) $e['email'], (string) $e['first_name'], $st, mail_wrap($st, '<p>' . $dear . ' ' . htmlspecialchars((string) $e['first_name']) . ',</p><p>' . htmlspecialchars($sb) . '</p>', $btn, app_url() . '/' . $url));
    }
    return true;
  } catch (Throwable $err) { error_log('[wdos referrer news] ' . $err->getMessage()); return false; }
}
/** Daily from 10:00: candidates who stopped signing in, and activations whose time ran out without a submission. Only recent ones, so
 *  the first run does not send old history. "Not signed in" can come once a week, three times at most; the rest once. */
function run_referral_news(DateTimeImmutable $d): string {
  $today = $d->format('Y-m-d'); $n = 0; $week = $d->format('o\WW');
  $base = "FROM referrals r JOIN users u ON u.id=r.user_id JOIN activations a ON a.id=(SELECT MAX(a2.id) FROM activations a2 WHERE a2.user_id=u.id)
    WHERE r.referrer_id IS NOT NULL AND u.status='active' AND NOT EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)";
  foreach (rows("SELECT u.id $base AND a.status IN ('in_progress','returned') AND a.deadline>=? AND r.created_at<? AND r.created_at>? AND (u.last_seen_at IS NULL OR u.last_seen_at<?)
      AND (SELECT COUNT(*) FROM envoy_alerts x WHERE x.envoy_id=r.referrer_id AND x.user_id=u.id AND x.kind='ref_nosign')<3 LIMIT 500",
      [$today, gmdate('Y-m-d H:i:s', time() - 3 * 86400), gmdate('Y-m-d H:i:s', time() - 45 * 86400), gmdate('Y-m-d H:i:s', time() - 3 * 86400)]) as $r)
    $n += referrer_tell((int) $r['id'], 'nosign', $week) ? 1 : 0;
  $since = $d->modify('-7 days')->format('Y-m-d');
  foreach (rows("SELECT u.id, a.id act $base AND a.status IN ('in_progress','returned','incomplete') AND a.submitted_at IS NULL AND a.deadline<? AND a.deadline>=? LIMIT 500", [$today, $since]) as $r)
    $n += referrer_tell((int) $r['id'], 'stalled', 'a' . $r['act']) ? 1 : 0;
  return "$n told";
}

