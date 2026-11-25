<?php
/* v109.45: the WODDI Envoy Appointment Acceptance & Commitment Form, in WDOS (Azeez, 22 Nov 2026).
 * "It must pop up when they sign in and persist; nothing must work until they fill and accept. Their details are filled from
 * what WDOS already has, and all they need do is tick Part B and Part C. Any place where WDOS has none of their information,
 * they fill. HQ must see who has filled and accepted. Follow-up emails should follow."
 * The paper form's parts: A envoy details, B appointment acceptance (3 ticks), C commitment to WODDI standards (8 ticks),
 * D first alignment and practical contribution, E declaration and signature, F WODDI acknowledgement (HQ only).
 * The server refuses an envoy's other requests until her form is accepted: hiding pages would not be enough. */

const EA_B = [
  'I confirm that I have received and read the WODDI Envoy Appointment & Engagement Pack and accept my appointment as a WODDI Envoy.',
  'I understand that the appointment is honorary, non-salaried and strategic in nature, and does not create an employment, partnership or general agency relationship with WODDI.',
  'I understand that I may only bind, commit or formally represent WODDI where specific written authorisation has been given for the matter concerned.',
];
const EA_C = [
  'Uphold the mission, values, reputation and dignity of WODDI.',
  'Act with integrity, professionalism, respect and good judgement.',
  'Work within the approved role and authority boundaries of the WODDI Envoy appointment.',
  'Protect confidential, personal or restricted information from unauthorised use or disclosure.',
  'Disclose any actual or potential conflict of interest relevant to my role or any WODDI engagement.',
  'Use WODDI’s name, identity, materials and Envoy title only for approved purposes.',
  'Not raise funds, make commitments, announce partnerships or issue public statements on behalf of WODDI without appropriate approval.',
  'Inform WODDI HQ if I am no longer able or willing to continue in the role.',
];
const EA_NETWORKS = ['WGMN', 'WNNN', 'Cross-cutting'];

function ea_record(int $uid): ?array {
  try { return row('SELECT * FROM envoy_acceptances WHERE user_id=?', [$uid]) ?: null; } catch (PDOException $e) { return null; }
}
/** An active envoy whose form is not yet accepted. */
function ea_needed(array $u): bool {
  if (($u['role'] ?? '') !== 'envoy' || ($u['status'] ?? '') !== 'active') return false;
  try { return !val('SELECT 1 FROM envoy_acceptances WHERE user_id=?', [(int) $u['id']]); } catch (PDOException $e) { return false; }   // before migration 047
}
/** Part A as WDOS already knows it. Anything empty here is what she fills. */
function ea_prefill(array $u): array {
  $e = row('SELECT * FROM envoys WHERE user_id=?', [(int) $u['id']]) ?: [];
  $cc = $u['country_code'] ?: ($e['country_code'] ?? null);
  $email = (string) $u['email']; if (preg_match('/\.(invalid|test|local|example)$/i', $email)) $email = '';
  $net = in_array($u['network'] ?? '', ['WGMN', 'WNNN'], true) ? $u['network'] : '';
  $des = trim((string) ($u['designation'] ?? ''));
  return [
    'full_name' => trim($u['first_name'] . ' ' . $u['last_name']),
    'country' => $cc ? country_name($cc) : '',
    'profession' => '',
    'organisation' => trim((string) ($e['organisation'] ?? '')),
    'email' => $email,
    'phone' => trim((string) $u['phone']),
    'network' => $net,
    'effective_date' => !empty($e['created_at']) ? substr((string) $e['created_at'], 0, 10) : substr((string) $u['created_at'], 0, 10),
    'focus' => trim((string) ($e['reach_plan'] ?? '')),
    'designation' => $des !== '' ? $des : 'WODDI Envoy',
    'state_region' => trim((string) ($e['state_region'] ?? $u['state_region'] ?? '')),
  ];
}
function ea_clean(string $s, int $max): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, $max); }

/** Server rule: until her form is accepted, an envoy can only read and send the form, sign out, set her language and see her notices. */
function ea_guard(string $method, string $path): void {
  if (preg_match('#^/(health|config|me|me/lang|auth/.+|envoy/acceptance(/.*)?|envoy/join(-info)?|ref/.+|photo/\d+|birthday/.+|voice/.+|privacy|meetings/\d+/(reply|go))$#', $path)) return;      // v109.58: signed links from meeting emails
  if ($method === 'GET' && preg_match('#^/notifications(/count)?$#', $path)) return;
  $u = current_user(); if (!$u || !ea_needed($u)) return;
  fail(403, 'Please complete and accept your WODDI Envoy Appointment Acceptance & Commitment Form first.', 'envoy_acceptance');
}

route('GET', '/envoy/acceptance', function () {
  $u = require_user(); if (($u['role'] ?? '') !== 'envoy') fail(403, 'This form is for WODDI Envoys.', 'forbidden');
  $r = ea_record((int) $u['id']);
  return ['needed' => !$r, 'prefill' => ea_prefill($u), 'part_b' => EA_B, 'part_c' => EA_C, 'networks' => EA_NETWORKS,
    'today' => hq_today()->format('Y-m-d'), 'record' => $r ? ea_public($r) : null];
});

route('POST', '/envoy/acceptance', function () {
  $u = require_user(); if (($u['role'] ?? '') !== 'envoy') fail(403, 'This form is for WODDI Envoys.', 'forbidden');
  if (ea_record((int) $u['id'])) fail(409, 'You have already accepted your appointment. Thank you.', 'already');
  $b = body(); $pre = ea_prefill($u);
  $v = fn($k, $max = 190) => ea_clean((string) ($b[$k] ?? $pre[$k] ?? ''), $max);
  $f = ['full_name' => $v('full_name'), 'country' => $v('country', 120), 'profession' => $v('profession'), 'organisation' => $v('organisation'),
    'email' => $v('email'), 'phone' => $v('phone', 40), 'network' => $v('network', 24), 'focus' => $v('focus', 1000),
    'first_30_days' => ea_clean((string) ($b['first_30_days'] ?? ''), 1000), 'support_needed' => ea_clean((string) ($b['support_needed'] ?? ''), 1000),
    'preferred_contact' => $v('preferred_contact', 40) ?: $v('phone', 40), 'signed_name' => ea_clean((string) ($b['signed_name'] ?? ''), 190)];
  $need = ['full_name' => 'your full name and title', 'country' => 'your country', 'profession' => 'your current position or profession',
    'organisation' => 'your organisation or institution', 'phone' => 'your phone or WhatsApp number', 'network' => 'your primary network',
    'focus' => 'your principal focus or area of contribution', 'first_30_days' => 'one practical contribution for your first 30 days', 'signed_name' => 'your name under the declaration'];
  foreach ($need as $k => $what) if ($f[$k] === '') fail(422, 'Please fill in ' . $what . '.', $k);
  if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) fail(422, 'That email address does not look right.', 'email');
  if (!in_array($f['network'], EA_NETWORKS, true)) fail(422, 'Please choose your primary network.', 'network');
  $pb = array_map('intval', (array) ($b['part_b'] ?? [])); $pc = array_map('intval', (array) ($b['part_c'] ?? []));
  sort($pb); sort($pc);
  if ($pb !== range(0, count(EA_B) - 1)) fail(422, 'Please tick every statement in Part B to accept your appointment.', 'part_b');
  if ($pc !== range(0, count(EA_C) - 1)) fail(422, 'Please tick every commitment in Part C.', 'part_c');
  if (empty($b['declaration'])) fail(422, 'Please tick the declaration in Part E.', 'declaration');
  $sig = (string) ($b['signature'] ?? '');
  /* v109.49: a phone that cannot draw a signature well is no barrier. She may type her full name instead; it is kept as a typed signature. */
  if (!empty($b['signature_typed'])) {
    $typed = ea_clean(strip_tags((string) ($b['signature_text'] ?? '')), 190);
    if (mb_strlen($typed) < 3 || !preg_match('/\p{L}.*\p{L}/u', $typed)) fail(422, 'Please type your full name as your signature.', 'signature');
    $sig = 'typed:' . $typed;
  } elseif (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]{200,}$#', $sig) || strlen($sig) > 400000) fail(422, 'Please sign in the signature box with your finger or mouse, or type your full name instead.', 'signature');
  q('INSERT INTO envoy_acceptances (user_id,full_name,country,profession,organisation,email,phone,network,effective_date,focus,first_30_days,support_needed,part_b,part_c,signature,signed_name,preferred_contact,accepted_at,ip)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [(int) $u['id'], $f['full_name'], $f['country'], $f['profession'], $f['organisation'], $f['email'], $f['phone'], $f['network'], $pre['effective_date'] ?: null,
     $f['focus'], $f['first_30_days'], $f['support_needed'], implode(',', $pb), implode(',', $pc), $sig, $f['signed_name'], $f['preferred_contact'], now(), mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64)]);
  // what she told us that WDOS did not know is kept on her record too
  if ($f['organisation'] !== '' && $pre['organisation'] === '') q('UPDATE envoys SET organisation=? WHERE user_id=?', [mb_substr($f['organisation'], 0, 160), (int) $u['id']]);
  if ($pre['phone'] === '' && $f['phone'] !== '') q('UPDATE users SET phone=? WHERE id=?', [$f['phone'], (int) $u['id']]);
  if ($pre['email'] === '' && $f['email'] !== '' && !val('SELECT 1 FROM users WHERE email=? AND id<>?', [$f['email'], (int) $u['id']])) q('UPDATE users SET email=? WHERE id=?', [$f['email'], (int) $u['id']]);
  log_activity((int) $u['id'], null, 'envoy_acceptance', 'Accepted the WODDI Envoy appointment', 'envoy', (int) $u['id']);
  /* v109.50: Part F is completed the moment she accepts (Azeez, 22 Nov): no envoy waits for HQ to acknowledge by hand.
   * It is marked plainly as automatic; HQ can still acknowledge again under an officer's name from her form. */
  $ref = ea_auto_ack((int) $u['id']);
  $name = trim($u['first_name'] . ' ' . $u['last_name']); $des = $pre['designation'];
  notify((int) $u['id'], 'appointment', 'Thank you for accepting your appointment', 'WODDI HQ has acknowledged it. Your official reference is ' . $ref . '.', '#/envoy-form');
  notify_hq('appointment', $name . ' accepted her envoy appointment', $des . ': the Acceptance & Commitment Form is signed and acknowledged, reference ' . $ref . '.', '#/envoys/' . (int) $u['id'] . '/form');
  $sum = ea_summary_html(ea_record((int) $u['id']));
  if (real_email($f['email'] ?: (string) $u['email'])) mail_queue($f['email'] ?: (string) $u['email'], (string) $u['first_name'], 'Your WODDI Envoy appointment: accepted',
    mail_wrap('Thank you, ' . htmlspecialchars((string) $u['first_name']), '<p>WODDI HQ has received and acknowledged your signed <b>WODDI Envoy Appointment Acceptance &amp; Commitment Form</b>.</p><p><b>Official reference:</b> ' . htmlspecialchars($ref) . '</p><p>Welcome, and thank you for standing with us. No woman is left out.</p>' . $sum, 'Open my dashboard', app_url() . '/#/'));
  mail_queue(hq_inbox(), 'WODDI HQ', 'Envoy accepted: ' . $name, mail_wrap($name . ' accepted her appointment', '<p>' . htmlspecialchars($des) . ' has signed the WODDI Envoy Acceptance &amp; Commitment Form in WDOS. Part F was completed automatically, reference ' . htmlspecialchars($ref) . '.</p>' . $sum, 'Open the form', app_url() . '/#/envoys/' . (int) $u['id'] . '/form'));
  after_response(function () { push_drain(10); mail_drain(6); });
  return ['ok' => true, 'record' => ea_public(ea_record((int) $u['id']))];
});

function ea_ref(int $id): string { return 'WODDI-ENV-' . hq_today()->format('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT); }
function ea_auto_ack(int $id): string {
  $ref = ea_ref($id);
  q('UPDATE envoy_acceptances SET ack_by=NULL, ack_name=?, ack_title=?, ack_ref=?, ack_at=? WHERE user_id=? AND ack_at IS NULL', ['WODDI HQ', 'Acknowledged automatically on acceptance', $ref, now(), $id]);
  log_activity($id, $id, 'envoy_acceptance_ack', 'Envoy appointment acknowledged automatically on acceptance, ref ' . $ref, 'envoy', $id);
  return (string) (val('SELECT ack_ref FROM envoy_acceptances WHERE user_id=?', [$id]) ?: $ref);
}
function ea_public(array $r): array {
  return ['full_name' => $r['full_name'], 'country' => $r['country'], 'profession' => $r['profession'], 'organisation' => $r['organisation'], 'email' => $r['email'],
    'phone' => $r['phone'], 'network' => $r['network'], 'effective_date' => $r['effective_date'], 'focus' => $r['focus'], 'first_30_days' => $r['first_30_days'],
    'support_needed' => $r['support_needed'], 'part_b' => $r['part_b'] === '' ? [] : array_map('intval', explode(',', $r['part_b'])),
    'part_c' => $r['part_c'] === '' ? [] : array_map('intval', explode(',', $r['part_c'])), 'signature' => $r['signature'], 'signed_name' => $r['signed_name'],
    'preferred_contact' => $r['preferred_contact'], 'accepted_at' => $r['accepted_at'],
    'ack' => $r['ack_at'] ? ['name' => $r['ack_name'], 'title' => $r['ack_title'], 'ref' => $r['ack_ref'], 'at' => $r['ack_at']] : null];
}
function ea_summary_html(?array $r): string {
  if (!$r) return '';
  $row = fn($k, $v) => '<tr><td style="padding:6px 10px;color:#6b5563;border-bottom:1px solid #eee;width:42%">' . htmlspecialchars($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee"><b>' . htmlspecialchars((string) $v) . '</b></td></tr>';
  return '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:10px 0">' . $row('Full name & title', $r['full_name']) . $row('Country', $r['country'])
    . $row('Position / profession', $r['profession']) . $row('Organisation', $r['organisation']) . $row('Primary network', $r['network'])
    . $row('Effective date', $r['effective_date']) . $row('Principal focus', $r['focus']) . $row('First 30 days', $r['first_30_days'])
    . $row('Accepted', substr((string) $r['accepted_at'], 0, 16) . ' UTC') . '</table><p style="font-size:13px;color:#6b5563">Parts B and C: all ' . (count(EA_B) + count(EA_C)) . ' statements ticked. Declaration signed.</p>';
}

/* ---- HQ: who has filled and accepted, the form itself, and Part F ---- */
route('GET', '/hq/envoy-acceptances', function () {
  require_hq(); $out = [];
  foreach (rows("SELECT e.user_id, e.status, e.state_region, e.country_code, e.created_at, u.first_name, u.last_name, u.designation, u.phone, u.email, u.last_seen_at, a.accepted_at, a.ack_at,
      (SELECT COUNT(*) FROM envoy_accept_nudges n WHERE n.user_id=e.user_id) nudges
      FROM envoys e JOIN users u ON u.id=e.user_id LEFT JOIN envoy_acceptances a ON a.user_id=e.user_id WHERE u.status='active' ORDER BY a.accepted_at IS NULL DESC, u.first_name") as $r)
    $out[] = ['id' => (int) $r['user_id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'designation' => (string) $r['designation'], 'state' => (string) $r['state_region'],
      'accepted_at' => $r['accepted_at'], 'acknowledged_at' => $r['ack_at'], 'reminders' => (int) $r['nudges'], 'last_seen_at' => $r['last_seen_at'], 'envoy_status' => $r['status']];
  $acc = count(array_filter($out, fn($x) => $x['accepted_at']));
  return ['envoys' => $out, 'totals' => ['envoys' => count($out), 'accepted' => $acc, 'pending' => count($out) - $acc, 'acknowledged' => count(array_filter($out, fn($x) => $x['acknowledged_at']))]];
});
route('GET', '/hq/envoy-acceptances/(\d+)', function ($id) {
  require_hq(); $u = row('SELECT * FROM users WHERE id=? AND role=\'envoy\'', [(int) $id]); if (!$u) fail(404, 'That envoy was not found.', 'not_found');
  $r = ea_record((int) $id);
  return ['envoy' => ['id' => (int) $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'designation' => (string) $u['designation']],
    'record' => $r ? ea_public($r) : null, 'prefill' => ea_prefill($u), 'part_b' => EA_B, 'part_c' => EA_C];
});
route('POST', '/hq/envoy-acceptances/(\d+)/acknowledge', function ($id) {
  $me = require_hq(); $r = ea_record((int) $id); if (!$r) fail(404, 'She has not accepted yet.', 'not_found');
  $b = body(); $name = ea_clean((string) ($b['name'] ?? trim($me['first_name'] . ' ' . $me['last_name'])), 190); $title = ea_clean((string) ($b['title'] ?? $me['designation'] ?? ''), 190);
  $ref = ea_clean((string) ($b['ref'] ?? ''), 80);
  if ($name === '' || $title === '') fail(422, 'Please give the name and title of the authorised representative.', 'name');
  if ($ref === '') $ref = ea_ref((int) $id);
  q('UPDATE envoy_acceptances SET ack_by=?, ack_name=?, ack_title=?, ack_ref=?, ack_at=? WHERE user_id=?', [(int) $me['id'], $name, $title, $ref, now(), (int) $id]);
  notify((int) $id, 'appointment', 'WODDI HQ has acknowledged your appointment', 'Official reference ' . $ref . '. Thank you for serving as a WODDI Envoy.', '#/envoy-form');
  $u = row('SELECT email, first_name FROM users WHERE id=?', [(int) $id]);
  $to = real_email($r['email']) ? $r['email'] : (string) ($u['email'] ?? '');
  if (real_email($to)) mail_queue($to, (string) $u['first_name'], 'WODDI HQ has acknowledged your Envoy appointment', mail_wrap('Your appointment is acknowledged',
    '<p>Dear ' . htmlspecialchars((string) $u['first_name']) . ',</p><p>WODDI HQ has acknowledged your signed Acceptance &amp; Commitment Form.</p><p><b>Official reference:</b> ' . htmlspecialchars($ref) . '<br><b>Acknowledged by:</b> ' . htmlspecialchars($name . ', ' . $title) . '</p><p>Thank you for serving as a WODDI Envoy.</p>', 'Open my form', app_url() . '/#/envoy-form'));
  log_activity((int) $me['id'], (int) $id, 'envoy_acceptance_ack', 'Acknowledged the envoy appointment, ref ' . $ref, 'envoy', (int) $id);
  after_response(function () { push_drain(10); mail_drain(6); });
  return ['ok' => true, 'ref' => $ref];
});
route('POST', '/hq/envoy-acceptances/remind', function () {
  require_hq(); $n = 0;
  foreach (rows("SELECT u.id FROM envoys e JOIN users u ON u.id=e.user_id LEFT JOIN envoy_acceptances a ON a.user_id=e.user_id WHERE u.status='active' AND u.role='envoy' AND a.user_id IS NULL") as $r)
    $n += ea_nudge((int) $r['id'], true) ? 1 : 0;
  after_response(function () { push_drain(20); mail_drain(12); });
  return ['ok' => true, 'reminded' => $n];
});

/* ---- follow-up: an envoy who has not accepted is reminded by email, in the app and on her phone, on days 0, 2, 5, 9 and 14 ---- */
const EA_GAPS = [0, 2, 3, 4, 5];
function ea_nudge(int $uid, bool $now = false): bool {
  $n = (int) val('SELECT COUNT(*) FROM envoy_accept_nudges WHERE user_id=?', [$uid]);
  if (!$now) {
    if ($n >= count(EA_GAPS)) return false;
    $last = val('SELECT MAX(sent_at) FROM envoy_accept_nudges WHERE user_id=?', [$uid]);
    if ($last && strtotime($last . ' UTC') > time() - EA_GAPS[$n] * 86400 + 3600) return false;
  }
  $u = row('SELECT id, email, first_name, designation FROM users WHERE id=?', [$uid]); if (!$u) return false;
  q('INSERT IGNORE INTO envoy_accept_nudges (user_id,n,sent_at) VALUES (?,?,?)', [$uid, $n + 1, now()]);
  notify($uid, 'appointment', 'Please accept your WODDI Envoy appointment', 'Open WDOS: your Acceptance & Commitment Form is ready, most of it filled in for you. Tick Parts B and C, add what is missing, and sign.', '#/');
  if (real_email((string) $u['email'])) mail_queue((string) $u['email'], (string) $u['first_name'], 'Please accept your WODDI Envoy appointment', mail_wrap('Your appointment form is waiting',
    '<p>Dear ' . htmlspecialchars((string) $u['first_name']) . ',</p><p>Your <b>WODDI Envoy Appointment Acceptance &amp; Commitment Form</b> is ready in WDOS. It opens as soon as you sign in, and most of it is already filled in from what WODDI HQ has on record.</p>'
    . '<p>All you need to do is:</p><ol><li>Check your details and add anything missing.</li><li>Tick the statements in Part B and Part C.</li><li>Write one practical contribution for your first 30 days.</li><li>Sign with your finger and press <b>Accept my appointment</b>.</li></ol><p>Your envoy dashboard opens as soon as you accept.</p>',
    'Open my form', app_url() . '/#/'));
  return true;
}
function run_envoy_accept_nudges(DateTimeImmutable $d): string {
  $n = 0;
  try {
    foreach (rows("SELECT u.id FROM envoys e JOIN users u ON u.id=e.user_id LEFT JOIN envoy_acceptances a ON a.user_id=e.user_id
        WHERE u.status='active' AND u.role='envoy' AND e.status='active' AND a.user_id IS NULL LIMIT 500") as $r) $n += ea_nudge((int) $r['id']) ? 1 : 0;
  } catch (PDOException $e) { return 'not ready'; }
  return "$n reminded";
}

/** For the app's menu: can this person make voice calls (a leader, an envoy who has accepted, or someone an envoy referred)? */
function ea_can_call(array $u): bool {
  if (is_hq($u) || work_seat($u)) return true;
  if (($u['role'] ?? '') === 'envoy') return !ea_needed($u) && (bool) val("SELECT 1 FROM envoys WHERE user_id=? AND status='active'", [(int) $u['id']]);
  return (bool) val("SELECT 1 FROM referrals r JOIN users eu ON eu.id=r.referrer_id AND eu.role='envoy' AND eu.status='active' WHERE r.user_id=? LIMIT 1", [(int) $u['id']]);
}
