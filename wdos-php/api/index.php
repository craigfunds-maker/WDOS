<?php
declare(strict_types=1);
/* WDOS API. Every /api/... request lands here (see .htaccess). */
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/push.php';
require __DIR__ . '/../lib/notify_i18n.php';
require __DIR__ . '/../lib/notify.php';
require __DIR__ . '/../lib/webauthn.php';
require __DIR__ . '/../lib/inspire_fns.php';
require __DIR__ . '/../lib/reporting_fns.php';
require __DIR__ . '/../lib/work_fns.php';
require __DIR__ . '/../lib/photos.php';
require __DIR__ . '/../lib/journey_fns.php';
require __DIR__ . '/../lib/amend_fns.php';
require __DIR__ . '/../lib/handoff_fns.php';
require __DIR__ . '/../lib/ops_fns.php';

set_exception_handler(function (Throwable $e) {
  error_log('[wdos] ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
  if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json'); }
  $db = $e instanceof PDOException;
  echo json_encode(['ok' => false, 'error' => $db ? 'database' : 'server',
    'message' => $db ? 'WDOS could not reach its database. If this is a new deployment, open /api/setup to finish setting it up.' : 'Something went wrong on the server. Please try again.']);
  exit;
});

$METHOD = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$PATH = '/' . trim((string) preg_replace('#^.*?/api#', '', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');

function route(string $method, string $pattern, callable $fn): void {
  global $METHOD, $PATH;
  if ($METHOD !== $method) return;
  if (!preg_match('#^' . $pattern . '$#', $PATH, $m)) return;
  if ($method !== 'GET') require_app_header();
  array_shift($m);
  $res = $fn(...$m);
  out($res ?? ['ok' => true]);
}

/* ---------------------------------------------------------------- setup -- */
if (str_starts_with($PATH, '/setup')) { require __DIR__ . '/../lib/setup.php'; exit; }

/* ----------------------------------------------------------------- misc -- */
route('GET', '/health', fn() => ['ok' => true, 'build' => WDOS_BUILD, 'db' => (bool) val('SELECT 1'), 'time' => now()]);

route('GET', '/config', function () { require_once __DIR__ . '/../lib/migrate.php'; ensure_migrated(); photos_seed_once(); after_response(fn() => insp_photos_tick()); return [
  'build' => WDOS_BUILD,
  'google' => (bool) cfg('GOOGLE_CLIENT_ID') && (bool) cfg('GOOGLE_CLIENT_SECRET'),
  'push' => push_configured(), 'vapid' => cfg('VAPID_PUBLIC', ''),
  'mail' => mail_configured(),
]; });

route('GET', '/geo/countries', fn() => ['countries' => rows("SELECT id, name, country_code AS code,
  (SELECT COUNT(*) FROM org_units c WHERE c.parent_id=ou.id) AS children FROM org_units ou WHERE level='country' ORDER BY name")]);
route('GET', '/geo/children/(\d+)', fn($id) => ['units' => rows('SELECT id, name, level FROM org_units WHERE parent_id=? ORDER BY name', [(int) $id])]);

/* ----------------------------------------------------------------- auth -- */
function validate_profile_fields(bool $withPassword): array {
  $net = strtoupper(str_in('network', 8));
  if (!in_array($net, ['WGMN', 'WNNN'], true)) fail(422, 'Choose the network you are joining.', 'network');
  $first = str_in('first_name', 80); $last = str_in('last_name', 80);
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter your first name and surname.', 'name');
  $phone = preg_replace('/[^\d+]/', '', str_in('phone', 32));
  // Both are compulsory (decision of the IT Lead, 12 November 2026): an email address to sign in with, and a phone number to be reached on.
  if (strlen(ltrim($phone, '+')) < 8 || strlen(ltrim($phone, '+')) > 15) fail(422, 'Enter your phone number with the country code, for example +234 801 234 5678.', 'phone');
  $dob = str_in('dob', 10); $d = DateTime::createFromFormat('Y-m-d', $dob);
  if (!$d || $d->format('Y-m-d') !== $dob) fail(422, 'Enter your date of birth.', 'dob');
  $age = age_on($dob); $fits = network_for_age($age);
  if ($fits === null) fail(422, 'WODDI networks are open to women aged 18 and above.', 'age');
  if ($fits !== $net) {
    fail(422, $net === 'WNNN'
      ? 'WNNN is for ages 18 to 29. At ' . $age . ', your network is WGMN (30 and above).'
      : 'WGMN is for ages 30 and above. At ' . $age . ', your network is WNNN (18 to 29).', 'age_network', ['suggest' => $fits]);
  }
  $country = row("SELECT id, country_code FROM org_units WHERE level='country' AND id=?", [(int) (body()['country_id'] ?? 0)]);
  if (!$country) fail(422, 'Choose your country.', 'country');
  $unitId = (int) $country['id']; $state = str_in('state_region', 120);
  $stateId = (int) (body()['state_id'] ?? 0);
  if ($stateId) {
    $s = row('SELECT id, name FROM org_units WHERE id=? AND parent_id=?', [$stateId, (int) $country['id']]);
    if ($s) { $unitId = (int) $s['id']; $state = $s['name']; }
  }
  // in the diaspora the state layer is optional: a city can stand directly under the country
  if ($state === '' && !is_diaspora($country['country_code'])) fail(422, 'Enter your state or region.', 'state');
  $pw = null;
  if ($withPassword) {
    $pw = (string) (body()['password'] ?? '');
    if (strlen($pw) < 6) fail(422, 'Choose a password or PIN of at least 6 characters.', 'password');
    if (preg_match('/^(\d)\1+$/', $pw) || in_array($pw, ['123456', '1234567', '12345678', '654321', '012345'], true)) fail(422, 'That PIN is too easy to guess. Choose another.', 'password');
  }
  if (empty(body()['consent'])) fail(422, 'Please confirm you agree to the Code of Conduct.', 'consent');
  return ['network' => $net, 'first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'dob' => $dob,
    'country_code' => $country['country_code'], 'org_unit_id' => $unitId, 'state_region' => $state,
    'lga' => str_in('lga', 120), 'community' => str_in('community', 120), 'password' => $pw];
}
function finish_signup(int $id, array $f): void {
  if (preg_match('/^[a-z]{2,3}$/', (string) (body()['lang'] ?? ''))) q('UPDATE users SET lang=? WHERE id=?', [body()['lang'], $id]);      // first, so the welcome arrives in her language
  $no = sprintf('%s-%s-%06d', $f['network'], $f['country_code'], $id);
  q('UPDATE users SET member_no=? WHERE id=?', [$no, $id]);
  $netName = $f['network'] === 'WNNN' ? 'WODDI Nurture NextGen Network' : 'WODDI Good Mother Network';
  notify($id, 'welcome', 'Welcome to ' . $f['network'] . ', ' . $f['first_name'], "Your membership number is $no. You are now part of the $netName. Add your photograph and switch on notifications to finish setting up.", '#/settings');
  notify_hq('registration', 'New ' . $f['network'] . ' member: ' . $f['first_name'] . ' ' . $f['last_name'], $f['state_region'] . ', ' . country_name($f['country_code']) . ". Membership number $no.", '#/members/' . $id);
  log_activity($id, $id, 'registered', $f['network'] . ' · ' . $f['state_region'] . ', ' . $f['country_code']);
  $aid = referral_attach($id, $f, body()['referral'] ?? null);
  // automatic from here: a chapter near her, and the seven-day journey. A leadership candidate follows the referral activation instead.
  $fresh = row('SELECT * FROM users WHERE id=?', [$id]); if ($fresh) chapter_place_user($fresh);
  if (!$aid) mj_start($id);
  try { source_save($id); if (!empty(body()['draft'])) q('DELETE FROM reg_drafts WHERE token_hash=?', [hash('sha256', (string) body()['draft'])]); } catch (PDOException $e) { /* before migration 017 */ }
  try { volunteer_interest_save($id, (string) (body()['volunteer_interest'] ?? '')); } catch (PDOException $e) { /* before migration 020 */ }
  if (($ward = str_in('ward', 120)) !== '') q('UPDATE users SET ward=? WHERE id=?', [$ward, $id]);      // the official ward, kept apart from the WODDI community
  after_response(fn() => push_drain(20));
}

/** The sign-in name of an account made with a phone number and no email address. */
function phone_login_name(string $phone): string { return 'p' . ltrim($phone, '+') . '@phone.wdos.invalid'; }

route('POST', '/auth/register', function () {
  throttle('reg:' . client_ip(), 12, 3600);
  $email = strtolower(str_in('email', 190));
  $f = validate_profile_fields(true);
  // The same request sent again after a lost reply returns the account it already made, not an error and not a second account (T04, T29).
  $idem = (string) (body()['idem'] ?? '');
  if (($again = idem_existing($idem, $f['password']))) { session_start_for((int) $again['id'], 'password'); return ['ok' => true, 'user' => public_user($again), 'resumed' => true]; }
  // A phone number is compulsory. An email address is asked for, but a woman who has none joins with her phone number alone and signs in
  // with it (No Woman Is Left Out). No code is sent to either.
  $method = 'email';
  if ($email === '') {
    $method = 'phone'; $email = phone_login_name(preg_replace('/[^\d+]/', '', $f['phone']));
    if (val('SELECT id FROM users WHERE email=?', [$email])) fail(409, 'This phone number already signs in a member. Sign in instead, or add your own email address to join with the same phone.', 'phone_taken');
  } else {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'That email address is not complete. Check it and try again.', 'email');
    if (val('SELECT id FROM users WHERE email=?', [$email])) fail(409, 'An account with this email already exists. Sign in instead.', 'email_taken');
  }
  throttle_hit('reg:' . client_ip());
  $id = ins('INSERT INTO users (email,password_hash,first_name,last_name,phone,dob,network,country_code,org_unit_id,state_region,lga,community,role,status,signup_method,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$email, password_hash($f['password'], PASSWORD_DEFAULT), $f['first_name'], $f['last_name'], $f['phone'], $f['dob'], $f['network'], $f['country_code'], $f['org_unit_id'], $f['state_region'], $f['lga'], $f['community'], 'member', 'active', $method, now()]);
  idem_keep($idem, $id);
  finish_signup($id, $f);
  session_start_for($id, 'password');
  return ['ok' => true, 'user' => public_user(row('SELECT * FROM users WHERE id=?', [$id]))];
});

/* A Google sign-up arrives with only a name and e-mail: this captures the rest, network included. */
route('POST', '/auth/complete-profile', function () {
  $u = require_user();
  if ($u['status'] !== 'pending_profile') fail(409, 'Your profile is already complete.', 'already_complete');
  $f = validate_profile_fields(false);
  q("UPDATE users SET first_name=?, last_name=?, phone=?, dob=?, network=?, country_code=?, org_unit_id=?, state_region=?, lga=?, community=?, status='active' WHERE id=?",
    [$f['first_name'], $f['last_name'], $f['phone'], $f['dob'], $f['network'], $f['country_code'], $f['org_unit_id'], $f['state_region'], $f['lga'], $f['community'], (int) $u['id']]);
  finish_signup((int) $u['id'], $f);
  return ['ok' => true, 'user' => public_user(row('SELECT * FROM users WHERE id=?', [(int) $u['id']]))];
});

route('POST', '/auth/login', function () {
  $email = strtolower(str_in('email', 190)); $pw = (string) (body()['password'] ?? '');
  throttle('login:' . $email, 8, 900); throttle('login-ip:' . client_ip(), 40, 900);
  $u = row('SELECT * FROM users WHERE email=?', [$email]);
  if (!$u && $email !== '' && !str_contains($email, '@')) {                 // a phone number: the account made with it, or the only account that carries it
    $ph = preg_replace('/[^\d+]/', '', $email);
    $u = row('SELECT * FROM users WHERE email=?', [phone_login_name($ph)]);
    if (!$u && strlen(ltrim($ph, '+')) >= 8) { $m = rows('SELECT * FROM users WHERE phone=? LIMIT 2', [$ph]); if (count($m) === 1) $u = $m[0]; }
  }
  if (!$u || !$u['password_hash'] || !password_verify($pw, $u['password_hash'])) {
    throttle_hit('login:' . $email); throttle_hit('login-ip:' . client_ip());
    fail(401, $u && !$u['password_hash'] ? 'This account was created with Google. Use Continue with Google.' : 'Those sign-in details are not correct.', 'bad_credentials');
  }
  if ($u['status'] === 'suspended') fail(403, 'This account is suspended. Contact WODDI HQ.', 'suspended');
  if ($u['status'] === 'withdrawn') fail(403, 'This membership was closed at your request. Write to WODDI HQ to come back.', 'withdrawn');
  session_start_for((int) $u['id'], 'password');
  return ['ok' => true, 'user' => public_user($u)];
});

route('POST', '/auth/logout', function () { session_end(); return ['ok' => true]; });

route('GET', '/me', function () {
  $u = current_user();
  return ['user' => $u ? public_user($u) : null];
});

/* Google sign-in: plain OAuth redirect, so it needs no extra script on the page. */
if ($METHOD === 'GET' && $PATH === '/auth/google/start') {
  if (!cfg('GOOGLE_CLIENT_ID')) { header('Location: ' . app_origin() . '/#/login?e=google_off'); exit; }
  $net = strtoupper((string) ($_GET['network'] ?? ''));
  [$sid, $state] = wa_challenge('google', null, in_array($net, ['WGMN', 'WNNN'], true) ? $net : '');
  header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => cfg('GOOGLE_CLIENT_ID'), 'redirect_uri' => app_origin() . '/api/auth/google/callback', 'response_type' => 'code',
    'scope' => 'openid email profile', 'state' => $sid . '.' . $state, 'prompt' => 'select_account']));
  exit;
}
if ($METHOD === 'GET' && $PATH === '/auth/google/callback') {
  $back = function (string $e): never { header('Location: ' . app_origin() . '/#/login?e=' . $e); exit; };
  [$sid, $state] = array_pad(explode('.', (string) ($_GET['state'] ?? ''), 2), 2, '');
  $c = row("SELECT * FROM auth_challenges WHERE id=? AND purpose='google' AND expires_at>?", [$sid, now()]);
  if (!$c || !hash_equals($c['challenge'], $state) || empty($_GET['code'])) $back('google_failed');
  q('DELETE FROM auth_challenges WHERE id=?', [$sid]);
  $ch = curl_init('https://oauth2.googleapis.com/token');
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => http_build_query([
    'code' => $_GET['code'], 'client_id' => cfg('GOOGLE_CLIENT_ID'), 'client_secret' => cfg('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => app_origin() . '/api/auth/google/callback', 'grant_type' => 'authorization_code'])]);
  $tok = json_decode((string) curl_exec($ch), true); curl_close($ch);
  $claims = isset($tok['id_token']) ? json_decode(b64u_dec(explode('.', $tok['id_token'])[1] ?? ''), true) : null;
  if (!is_array($claims) || empty($claims['sub']) || empty($claims['email']) || ($claims['aud'] ?? '') !== cfg('GOOGLE_CLIENT_ID')) $back('google_failed');
  $email = strtolower($claims['email']);
  $u = row('SELECT * FROM users WHERE google_sub=?', [$claims['sub']]);
  if (!$u) {
    $u = row('SELECT * FROM users WHERE email=?', [$email]);
    if ($u) {
      if (empty($claims['email_verified'])) $back('google_unverified');
      q('UPDATE users SET google_sub=? WHERE id=?', [$claims['sub'], (int) $u['id']]);
    }
  }
  if (!$u) {
    $id = ins("INSERT INTO users (email,google_sub,first_name,last_name,network,role,status,signup_method,created_at) VALUES (?,?,?,?,?,'member','pending_profile','google',?)",
      [$email, $claims['sub'], mb_substr((string) ($claims['given_name'] ?? ''), 0, 80), mb_substr((string) ($claims['family_name'] ?? ''), 0, 80), $c['data'] ?: null, now()]);
    $u = row('SELECT * FROM users WHERE id=?', [$id]);
  }
  if (in_array($u['status'], ['suspended', 'withdrawn'], true)) $back('suspended');
  session_start_for((int) $u['id'], 'google');
  header('Location: ' . app_origin() . '/#/');
  exit;
}

/* Fingerprint (passkeys) */
route('POST', '/webauthn/register/options', fn() => wa_register_options(require_user()));
route('POST', '/webauthn/register/verify', function () {
  $u = require_user(); $r = wa_register_verify($u, body());
  notify((int) $u['id'], 'account', 'Fingerprint sign-in switched on', 'A device was set up to sign in to your WDOS account without a password. If this was not you, remove it in Settings and change your password.', '#/settings');
  return $r;
});
route('POST', '/webauthn/login/options', fn() => wa_login_options(str_in('email', 190)));
route('POST', '/webauthn/login/verify', function () {
  throttle('wa:' . client_ip(), 30, 900); throttle_hit('wa:' . client_ip());
  $u = wa_login_verify(body());
  session_start_for((int) $u['id'], 'fingerprint');
  return ['ok' => true, 'user' => public_user($u)];
});
route('GET', '/webauthn/credentials', fn() => ['credentials' => rows('SELECT id, label, created_at, last_used_at FROM webauthn_credentials WHERE user_id=? ORDER BY id', [(int) require_user()['id']])]);
route('POST', '/webauthn/credentials/(\d+)/delete', function ($id) { q('DELETE FROM webauthn_credentials WHERE id=? AND user_id=?', [(int) $id, (int) require_user()['id']]); });

/* Forgotten password (e-mail link when Brevo is configured; HQ reset always works) */
route('POST', '/auth/forgot', function () {
  $email = strtolower(str_in('email', 190));
  throttle('forgot:' . client_ip(), 6, 3600); throttle_hit('forgot:' . client_ip());
  if (!mail_configured()) return ['ok' => true, 'sent' => false, 'message' => 'Email is not switched on yet. Ask WODDI HQ to reset your password from Members.'];
  $u = row('SELECT id, first_name, email FROM users WHERE email=?', [$email]);
  if ($u) {
    $tok = token(32);
    q('INSERT INTO password_resets (token_hash,user_id,expires_at) VALUES (?,?,?)', [hash('sha256', $tok), (int) $u['id'], gmdate('Y-m-d H:i:s', time() + 3600)]);
    $link = app_origin() . '/#/reset/' . $tok;
    after_response(fn() => send_mail($u['email'], $u['first_name'], 'Reset your WDOS password',
      '<p>Hello ' . htmlspecialchars($u['first_name']) . ',</p><p>Use this link within one hour to choose a new WDOS password:</p><p><a href="' . $link . '">' . $link . '</a></p><p>If you did not ask for this, ignore this message.</p><p>WODDI HQ</p>'));
  }
  return ['ok' => true, 'sent' => true, 'message' => 'If that email has a WDOS account, a reset link is on its way.'];
});
route('POST', '/auth/reset', function () {
  $pw = (string) (body()['password'] ?? '');
  if (strlen($pw) < 8) fail(422, 'Choose a password of at least 8 characters.', 'password');
  $r = row('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>?', [hash('sha256', str_in('token', 120)), now()]);
  if (!$r) fail(400, 'This reset link has expired. Ask for a new one.', 'reset_expired');
  q('UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), (int) $r['user_id']]);
  q('UPDATE password_resets SET used_at=? WHERE token_hash=?', [now(), $r['token_hash']]);
  q('DELETE FROM sessions WHERE user_id=?', [(int) $r['user_id']]);
  notify((int) $r['user_id'], 'account', 'Your password was changed', 'If this was not you, contact WODDI HQ straight away.', '#/settings');
});

/* -------------------------------------------------------------- profile -- */
route('POST', '/me', function () {
  $u = require_user();
  $first = str_in('first_name', 80) ?: $u['first_name']; $last = str_in('last_name', 80) ?: $u['last_name'];
  $phone = preg_replace('/[^\d+]/', '', str_in('phone', 32)) ?: $u['phone'];
  q('UPDATE users SET first_name=?, last_name=?, phone=?, lga=?, community=? WHERE id=?', [$first, $last, $phone, str_in('lga', 120), str_in('community', 120), (int) $u['id']]);
  if (array_key_exists('ward', body())) q('UPDATE users SET ward=? WHERE id=?', [str_in('ward', 120), (int) $u['id']]);
  chapter_replace_after_change((int) $u['id']);
  return ['ok' => true, 'user' => public_user(row('SELECT * FROM users WHERE id=?', [(int) $u['id']]))];
});
route('POST', '/me/password', function () {
  $u = require_user(); $new = (string) (body()['new_password'] ?? '');
  // Recovery without a code: a member who has just signed in with her fingerprint or face on her own phone may choose a new PIN without the old one.
  $viaPasskey = (bool) val("SELECT 1 FROM sessions WHERE token_hash=? AND method='fingerprint' AND created_at>=?", [hash('sha256', (string) ($_COOKIE[SESSION_COOKIE] ?? '')), gmdate('Y-m-d H:i:s', time() - 900)]);
  if ($u['password_hash'] && !$viaPasskey && !password_verify((string) (body()['current_password'] ?? ''), $u['password_hash'])) fail(401, 'Your current password is not correct.', 'bad_credentials');
  if (strlen($new) < 6) fail(422, 'Choose a password or PIN of at least 6 characters.', 'password');
  q('UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), (int) $u['id']]);
  notify((int) $u['id'], 'account', 'Your password was changed', 'If this was not you, contact WODDI HQ straight away.', '#/settings');
  after_response(fn() => push_drain(5));
});
route('POST', '/me/photo', function () { $u = require_user(); photo_from_upload((int) $u['id']); return ['ok' => true, 'v' => time()]; });
/* HQ sets or replaces a member's photograph, for leaders who send theirs by WhatsApp or email. */
route('POST', '/hq/members/(\d+)/photo', function ($id) {
  $me = require_hq(); if (!val('SELECT 1 FROM users WHERE id=?', [(int) $id])) fail(404, 'That member was not found.', 'not_found');
  photo_from_upload((int) $id); log_activity((int) $me['id'], (int) $id, 'photo_set', '');
  return ['ok' => true, 'v' => time()];
});
if ($METHOD === 'GET' && preg_match('#^/library/(\d+)/file$#', $PATH, $m)) {
  $u = require_user(); require_once __DIR__ . '/../lib/work_fns.php';
  $it = row('SELECT * FROM library_items WHERE id=?', [(int) $m[1]]);
  $can = $it && $it['bytes'] !== null && (is_hq($u) || ((int) $it['archived'] === 0 && ($it['audience'] === 'all' || ($it['audience'] === 'leaders' && current_seat((int) $u['id'])))));
  if (!$can) { http_response_code(404); exit; }
  header('Content-Type: ' . $it['mime']); header('X-Content-Type-Options: nosniff'); header('Content-Disposition: ' . (in_array($it['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true) ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $it['file_name'] ?: 'document') . '"');
  header('Cache-Control: private, max-age=3600'); echo $it['bytes']; exit;
}
if ($METHOD === 'GET' && $PATH === '/inspiration/photo') {
  require_user();
  $p = row('SELECT mime, bytes FROM message_photos WHERE image_key=?', [(string) ($_GET['k'] ?? '')]);
  if (!$p) { http_response_code(404); exit; }
  header('Content-Type: ' . $p['mime']); header('Cache-Control: private, max-age=2592000, immutable'); echo $p['bytes']; exit;
}
if ($METHOD === 'GET' && preg_match('#^/photo/(\d+)$#', $PATH, $m)) {
  require_user();
  $p = row('SELECT mime, bytes FROM user_photos WHERE user_id=?', [(int) $m[1]]);
  if (!$p) { http_response_code(404); exit; }
  header('Content-Type: ' . $p['mime']); header('Cache-Control: private, max-age=600'); echo $p['bytes']; exit;
}

/* -------------------------------------------------------- notifications -- */
route('GET', '/notifications', function () {
  $u = require_user(); $before = (int) ($_GET['before'] ?? 0);
  $sql = 'SELECT id, kind, title, body, url, read_at, created_at FROM notifications WHERE user_id=?' . ($before ? ' AND id<?' : '') . ' ORDER BY id DESC LIMIT 30';
  return ['items' => rows($sql, $before ? [(int) $u['id'], $before] : [(int) $u['id']]),
    'unread' => (int) val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [(int) $u['id']])];
});
/* The bell polls this: one cheap indexed read. */
route('GET', '/notifications/pulse', function () {
  $u = require_user(); $after = (int) ($_GET['after'] ?? 0);
  after_response('automations_tick'); after_response(fn() => insp_photos_tick()); after_response(fn() => mail_drain(8));
  return ['unread' => (int) val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [(int) $u['id']]),
    'fresh' => $after ? rows('SELECT id, kind, title, body, url, created_at FROM notifications WHERE user_id=? AND id>? ORDER BY id DESC LIMIT 5', [(int) $u['id'], $after]) : [],
    'latest' => (int) (val('SELECT MAX(id) FROM notifications WHERE user_id=?', [(int) $u['id']]) ?? 0)];
});
route('POST', '/notifications/read', function () {
  $u = require_user(); $ids = body()['ids'] ?? null;
  if (is_array($ids) && $ids) {
    $ids = array_slice(array_map('intval', $ids), 0, 100);
    q('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL AND id IN (' . implode(',', $ids) . ')', [now(), (int) $u['id']]);
  } else {
    q('UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL', [now(), (int) $u['id']]);
  }
  return ['ok' => true, 'unread' => (int) val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [(int) $u['id']])];
});
route('GET', '/notifications/prefs', function () {
  $u = require_user(); $p = notify_prefs((int) $u['id']); $out = [];
  foreach (NOTIFY_KINDS as $k => $label) {
    if (in_array($k, ['registration', 'digest'], true) && !is_hq($u)) continue;
    $out[] = ['kind' => $k, 'label' => $label] + $p[$k];
  }
  return ['prefs' => $out, 'devices' => (int) val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=?', [(int) $u['id']]), 'push_configured' => push_configured()];
});
route('POST', '/notifications/prefs', function () {
  $u = require_user(); $k = str_in('kind', 24);
  if (!isset(NOTIFY_KINDS[$k])) fail(422, 'Unknown notification type.', 'kind');
  q('DELETE FROM notification_prefs WHERE user_id=? AND kind=?', [(int) $u['id'], $k]);
  q('INSERT INTO notification_prefs (user_id,kind,in_app,push) VALUES (?,?,?,?)', [(int) $u['id'], $k, empty(body()['in_app']) ? 0 : 1, empty(body()['push']) ? 0 : 1]);
});
route('POST', '/push/subscribe', function () {
  $u = require_user(); $s = body()['subscription'] ?? [];
  $ep = (string) ($s['endpoint'] ?? ''); $p = (string) ($s['keys']['p256dh'] ?? ''); $a = (string) ($s['keys']['auth'] ?? '');
  if (!str_starts_with($ep, 'https://') || $p === '' || $a === '') fail(422, 'This browser did not give WDOS a usable push address.', 'subscription');
  $h = hash('sha256', $ep);
  $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
  // keep the same row when this browser is already known, so pushes already queued for it still arrive
  if (val('SELECT 1 FROM push_subscriptions WHERE endpoint_hash=?', [$h])) q('UPDATE push_subscriptions SET user_id=?, p256dh=?, auth=?, user_agent=?, fail_count=0 WHERE endpoint_hash=?', [(int) $u['id'], $p, $a, $ua, $h]);
  else q('INSERT INTO push_subscriptions (user_id,endpoint_hash,endpoint,p256dh,auth,user_agent,created_at) VALUES (?,?,?,?,?,?,?)', [(int) $u['id'], $h, $ep, $p, $a, $ua, now()]);
});
route('POST', '/push/unsubscribe', function () {
  $u = require_user();
  q('DELETE FROM push_subscriptions WHERE user_id=? AND endpoint_hash=?', [(int) $u['id'], hash('sha256', str_in('endpoint', 1000))]);
});
route('POST', '/push/test', function () {
  $u = require_user();
  if (!push_configured()) fail(503, 'Push keys are not set on the server yet. Open /api/setup to create them.', 'push_off');
  if (!(int) val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=?', [(int) $u['id']])) fail(409, 'Switch on notifications on this device first.', 'no_device');
  $res = push_test_user((int) $u['id'], 'WDOS notifications are working', 'This is the test you asked for. You will receive announcements and reminders here.');
  return ['ok' => true, 'devices' => $res, 'sent' => count(array_filter($res, fn($r) => $r['ok']))];
});
route('POST', '/push/drain', function () { require_hq(); return push_drain(60); });
/* The service worker asks for this if a push ever arrives without a readable body. */
route('GET', '/push/latest', function () {
  $u = require_user();
  return ['item' => row('SELECT id, title, body, url FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 1', [(int) $u['id']])];
});

/* Scheduled work. Point a cron (cPanel) or cron-job.org (Render) at this URL every 5 minutes. */
if ($METHOD === 'GET' && $PATH === '/cron') {
  $key = (string) ($_GET['key'] ?? '');
  if (!cfg('ADMIN_KEY') || !hash_equals((string) cfg('ADMIN_KEY'), $key)) { http_response_code(403); echo 'forbidden'; exit; }
  q("DELETE FROM app_settings WHERE k='auto_tick'"); q("INSERT INTO app_settings (k,v) VALUES ('auto_tick',?)", [(string) time()]);
  $auto = run_automations(); run_inspiration_automations($auto); rp_reminders_daily($auto); work_automations($auto);
  out(['ok' => true, 'automations' => $auto, 'push' => push_drain(150), 'time' => now()]);
}

/* ------------------------------------------------------------ dashboards -- */
require __DIR__ . '/../lib/inspire.php';
require __DIR__ . '/../lib/dashboards.php';
require __DIR__ . '/../lib/reporting.php';
require __DIR__ . '/../lib/work.php';
require __DIR__ . '/../lib/structure.php';
require __DIR__ . '/../lib/journey.php';
require __DIR__ . '/../lib/comms.php';
require __DIR__ . '/../lib/amend.php';
require __DIR__ . '/../lib/handoff.php';
require __DIR__ . '/../lib/staff.php';
require __DIR__ . '/../lib/guide.php';
require __DIR__ . '/../lib/ops.php';

fail(404, 'No such WDOS endpoint.', 'not_found');
