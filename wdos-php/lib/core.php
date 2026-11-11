<?php
declare(strict_types=1);
/* WDOS core: configuration, database, JSON in/out, sessions.
 * Configuration comes from environment variables (Render) or from
 * config.local.php beside index.html (shared hosting / cPanel). */

const WDOS_BUILD = 'v93.1';

function cfg(string $key, ?string $default = null): ?string {
  static $local = null;
  if ($local === null) {
    $file = __DIR__ . '/../config.local.php';
    $local = is_file($file) ? (array) require $file : [];
  }
  $v = getenv($key);
  if ($v !== false && $v !== '') return $v;
  if (isset($local[$key]) && $local[$key] !== '') return (string) $local[$key];
  return $default;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    cfg('DB_HOST', '127.0.0.1'), cfg('DB_PORT', '3306'), cfg('DB_NAME', 'wdos'));
  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::ATTR_STRINGIFY_FETCHES => false,
  ];
  if (cfg('DB_SSL', '0') === '1') {           // TiDB Cloud needs TLS
    $ca = cfg('DB_SSL_CA');
    if (!$ca) foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/cert.pem'] as $c) {
      if (is_file($c)) { $ca = $c; break; }
    }
    if ($ca) $opt[PDO::MYSQL_ATTR_SSL_CA] = $ca;
  }
  $pdo = new PDO($dsn, cfg('DB_USER', 'root'), cfg('DB_PASS', ''), $opt);
  $pdo->exec("SET time_zone = '+00:00'");
  return $pdo;
}
function q(string $sql, array $p = []): PDOStatement {
  $st = db()->prepare($sql);
  foreach (array_values($p) as $i => $v) {
    $st->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR));
  }
  $st->execute();
  return $st;
}
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function ins(string $sql, array $p = []): int { q($sql, $p); return (int) db()->lastInsertId(); }
function now(): string { return gmdate('Y-m-d H:i:s'); }

/* ---- encoding helpers --------------------------------------------------- */
function b64u(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }
function token(int $bytes = 32): string { return b64u(random_bytes($bytes)); }

/* ---- request / response ------------------------------------------------- */
function body(): array {
  static $b = null;
  if ($b === null) { $b = json_decode((string) file_get_contents('php://input'), true); if (!is_array($b)) $b = []; }
  return $b;
}
function str_in(string $k, int $max = 190): string {
  $v = body()[$k] ?? '';
  return is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
}
$GLOBALS['wdos_after'] = [];
/** Run work after the reply has reached the browser (push sends, e-mail). */
function after_response(callable $fn): void { $GLOBALS['wdos_after'][] = $fn; }
function out($data, int $code = 200): never {
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  if ($GLOBALS['wdos_after']) {
    ignore_user_abort(true);
    header('Content-Length: ' . strlen($json));
    header('Connection: close');
    echo $json;
    while (ob_get_level() > 0) ob_end_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    foreach ($GLOBALS['wdos_after'] as $fn) { try { $fn(); } catch (Throwable $e) { error_log('[wdos after] ' . $e->getMessage()); } }
  } else {
    echo $json;
  }
  exit;
}
function fail(int $code, string $message, string $error = 'error', array $extra = []): never {
  $GLOBALS['wdos_after'] = [];
  out(['ok' => false, 'error' => $error, 'message' => $message] + $extra, $code);
}
function is_https(): bool {
  return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}
function app_origin(): string {
  $u = cfg('APP_URL');
  if ($u) return rtrim($u, '/');
  return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function app_host(): string { return (string) parse_url(app_origin(), PHP_URL_HOST); }

/* ---- sessions (database-backed so they survive Render restarts) --------- */
const SESSION_COOKIE = 'wdos_sid';
const SESSION_DAYS = 30;

function session_start_for(int $userId, string $method = 'password'): void {
  $tok = token(32);
  q('INSERT INTO sessions (token_hash,user_id,method,user_agent,created_at,expires_at) VALUES (?,?,?,?,?,?)',
    [hash('sha256', $tok), $userId, $method, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250), now(), gmdate('Y-m-d H:i:s', time() + SESSION_DAYS * 86400)]);
  setcookie(SESSION_COOKIE, $tok, ['expires' => time() + SESSION_DAYS * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
  q('UPDATE users SET last_seen_at=? WHERE id=?', [now(), $userId]);
}
function session_end(): void {
  $tok = $_COOKIE[SESSION_COOKIE] ?? '';
  if ($tok) q('DELETE FROM sessions WHERE token_hash=?', [hash('sha256', $tok)]);
  setcookie(SESSION_COOKIE, '', ['expires' => 1, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
function current_user(): ?array {
  static $u = false;
  if ($u !== false) return $u;
  $u = null;
  $tok = $_COOKIE[SESSION_COOKIE] ?? '';
  if (!$tok) return null;
  $s = row('SELECT user_id FROM sessions WHERE token_hash=? AND expires_at>?', [hash('sha256', $tok), now()]);
  if (!$s) return null;
  $u = row('SELECT * FROM users WHERE id=?', [(int) $s['user_id']]);
  if ($u && $u['status'] === 'suspended') { $u = null; return null; }
  if ($u && (!$u['last_seen_at'] || strtotime($u['last_seen_at'] . ' UTC') < time() - 600)) {
    q('UPDATE users SET last_seen_at=? WHERE id=?', [now(), (int) $u['id']]);
  }
  return $u;
}
function require_user(): array { $u = current_user(); if (!$u) fail(401, 'Please sign in to continue.', 'signed_out'); return $u; }
function is_hq(array $u): bool { return in_array($u['role'], ['hq_admin', 'super_admin'], true); }
function require_hq(): array { $u = require_user(); if (!is_hq($u)) fail(403, 'This area is for WODDI HQ staff.', 'forbidden'); return $u; }
/** State-changing calls must carry our header: a plain cross-site form cannot set it. */
function require_app_header(): void {
  if (($_SERVER['HTTP_X_WDOS'] ?? '') !== '1') fail(400, 'This request did not come from the WDOS app.', 'bad_origin');
}
function throttle(string $bucket, int $max, int $seconds): void {
  $since = gmdate('Y-m-d H:i:s', time() - $seconds);
  if ((int) val('SELECT COUNT(*) FROM login_attempts WHERE bucket=? AND created_at>?', [$bucket, $since]) >= $max) {
    fail(429, 'Too many attempts. Wait a few minutes and try again.', 'throttled');
  }
}
function throttle_hit(string $bucket): void { q('INSERT INTO login_attempts (bucket,created_at) VALUES (?,?)', [$bucket, now()]); }
function client_ip(): string {
  $f = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
  return $f ? trim(explode(',', $f)[0]) : ($_SERVER['REMOTE_ADDR'] ?? '0');
}
function log_activity(?int $actor, ?int $subject, string $action, string $detail = ''): void {
  q('INSERT INTO activity_log (actor_id,subject_id,action,detail,created_at) VALUES (?,?,?,?,?)', [$actor, $subject, $action, mb_substr($detail, 0, 250), now()]);
}

/* ---- people ------------------------------------------------------------- */
function current_seat(int $userId): ?array {
  return row('SELECT s.*, p.title_wgmn, p.title_wnnn, p.level AS position_level, p.rank_no, ou.name AS unit_name, ou.path AS unit_path, ou.country_code AS unit_country
              FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units ou ON ou.id=s.org_unit_id
              WHERE s.user_id=? AND s.ends_at IS NULL ORDER BY p.rank_no LIMIT 1', [$userId]);
}
function seat_title(array $seat): string {
  $t = $seat['network'] === 'WNNN' ? $seat['title_wnnn'] : $seat['title_wgmn'];
  $place = $seat['area_label'] !== '' ? $seat['area_label'] : $seat['unit_name'];
  return $t . ', ' . $place;
}
function country_name(?string $code): string {
  if (!$code) return '';
  return (string) (val("SELECT name FROM org_units WHERE level='country' AND country_code=?", [$code]) ?? '');
}
function public_user(array $u): array {
  $seat = current_seat((int) $u['id']);
  $todo = function_exists('person_todo') ? person_todo($u, $seat) : ['appointment' => false, 'onboarding' => 0, 'activation' => false];
  return [
    'id' => (int) $u['id'], 'member_no' => $u['member_no'], 'email' => $u['email'],
    'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'phone' => $u['phone'], 'dob' => $u['dob'],
    'network' => $u['network'], 'country_code' => $u['country_code'], 'country' => country_name($u['country_code']),
    'state_region' => $u['state_region'], 'lga' => $u['lga'], 'community' => $u['community'],
    'role' => $u['role'], 'status' => $u['status'], 'has_photo' => (int) $u['has_photo'] === 1,
    'has_password' => !empty($u['password_hash']), 'must_change_password' => (int) $u['must_change_password'] === 1,
    'is_hq' => is_hq($u), 'is_leader' => (bool) $seat,
    'seat' => $seat ? ['position' => $seat['position_code'], 'title' => seat_title($seat), 'status' => $seat['status'], 'network' => $seat['network'], 'level' => $seat['position_level'], 'acceptance' => $seat['acceptance'] ?? ''] : null, 'todo' => $todo,
    'created_at' => $u['created_at'],
  ];
}
function age_on(string $dob, ?int $at = null): int {
  $d = new DateTimeImmutable($dob, new DateTimeZone('UTC'));
  return (int) $d->diff((new DateTimeImmutable('@' . ($at ?? time())))->setTimezone(new DateTimeZone('UTC')))->y;
}
/** The Founder's age rule: WNNN is 18 to 29, WGMN is 30 and above. */
function network_for_age(int $age): ?string { return $age < 18 ? null : ($age <= 29 ? 'WNNN' : 'WGMN'); }
