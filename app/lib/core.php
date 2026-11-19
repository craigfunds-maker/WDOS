<?php
/* WODDI Institute (PHP). Core: config, database, sessions, security, notifications, points. */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

function cfg(?string $key = null, $default = null) {
    static $c = null;
    if ($c === null) {
        $f = APP_ROOT . '/config.php';
        $c = is_file($f) ? (require $f) : [];
    }
    if ($key === null) return $c;
    $v = $c;
    foreach (explode('.', $key) as $k) { if (!is_array($v) || !array_key_exists($k, $v)) return $default; $v = $v[$k]; }
    return $v;
}
function installed(): bool { return is_file(APP_ROOT . '/config.php') && cfg('db.name'); }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $d = cfg('db');
    $dsn = !empty($d['socket']) ? "mysql:unix_socket={$d['socket']};dbname={$d['name']};charset=utf8mb4"
        : "mysql:host={$d['host']};port=" . ($d['port'] ?? 3306) . ";dbname={$d['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute(array_values($p)); return $s; }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

function schema(): array { static $s = null; if ($s === null) $s = json_decode((string)file_get_contents(APP_ROOT . '/content/schema.json'), true) ?: []; return $s; }
function uuid4(): string { $b = random_bytes(16); $b[6] = chr(ord($b[6]) & 0x0f | 0x40); $b[8] = chr(ord($b[8]) & 0x3f | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4)); }
function now(): string { return gmdate('Y-m-d H:i:s'); }

/* Insert a row. Unknown columns are dropped, a uuid primary key is filled in, arrays become JSON. */
function ins(string $table, array $data): string {
    $t = schema()[$table] ?? null;
    if ($t) {
        $data = array_intersect_key($data, $t['cols']);
        foreach ($t['pk'] as $pk) if (!isset($data[$pk]) && ($t['cols'][$pk]['k'] ?? '') === 'uuid') $data[$pk] = uuid4();
        if (isset($t['cols']['created_at']) && !isset($data['created_at'])) $data['created_at'] = now();
    }
    foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = json_encode($v, JSON_UNESCAPED_UNICODE); elseif (is_bool($v)) $data[$k] = $v ? 1 : 0; }
    $cols = array_keys($data);
    q('INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $data);
    $pk = $t['pk'][0] ?? 'id';
    return (string)($data[$pk] ?? db()->lastInsertId());
}
function upd(string $table, array $data, string $where, array $p = []): int {
    $t = schema()[$table] ?? null;
    if ($t) $data = array_intersect_key($data, $t['cols']);
    if (!$data) return 0;
    foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = json_encode($v, JSON_UNESCAPED_UNICODE); elseif (is_bool($v)) $data[$k] = $v ? 1 : 0; }
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
    return q("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $p))->rowCount();
}
function has_table(string $t): bool { return isset(schema()[$t]) || in_array($t, ['auth_accounts', 'password_resets', 'migration_log'], true); }

/* ── Output helpers ── */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base(): string { return rtrim((string)cfg('base_url', ''), '/'); }
function url(string $path = '', array $qs = []): string { $u = base() . '/' . ltrim($path, '/'); return $qs ? $u . '?' . http_build_query($qs) : $u; }
function asset(string $f): string { return url('assets/' . $f) . '?v=' . APP_VERSION; }
function redirect(string $path, array $qs = []): never { header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path, $qs))); exit; }
function jdec($v, $default = []) { if (is_array($v)) return $v; $d = json_decode((string)$v, true); return is_array($d) ? $d : $default; }
function json_out($data, int $code = 200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function ago(?string $ts): string {
    if (!$ts) return '';
    $d = time() - strtotime($ts . ' UTC');
    if ($d < 60) return 'just now'; if ($d < 3600) return floor($d / 60) . ' min ago'; if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 604800) return floor($d / 86400) . ' d ago'; return gmdate('j M Y', strtotime($ts . ' UTC'));
}
function initials(?string $n): string { $p = preg_split('/\s+/', trim((string)$n)) ?: []; $s = ''; foreach (array_slice($p, 0, 2) as $w) $s .= mb_strtoupper(mb_substr($w, 0, 1)); return $s ?: 'W'; }
function post(string $k, $d = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $d; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

/* ── Session, CSRF, flash ── */
function boot_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('woddi_sess');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
    $dir = APP_ROOT . '/storage/sessions';
    if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
    session_start();
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(20)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($t) || !hash_equals(csrf(), $t)) { http_response_code(419); exit('Your session expired. Go back, refresh the page and try again.'); }
}
function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}

/* ── Current user ── */
function me(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $uid = $_SESSION['uid'] ?? null;
    $u = $uid ? row('SELECT * FROM users WHERE uid = ?', [$uid]) : null;
    if ($u && ($u['status'] ?? '') === 'banned') { unset($_SESSION['uid']); $u = null; }
    return $u;
}
function uid(): ?string { $m = me(); return $m['uid'] ?? null; }
function is_admin(): bool { $m = me(); return $m && in_array($m['role'] ?? '', ['admin', 'super_admin'], true); }
function is_staff(): bool { $m = me(); return $m && in_array($m['role'] ?? '', ['admin', 'super_admin', 'moderator', 'instructor', 'tutor'], true); }
function net(): string { $m = me(); return (($m['pathway'] ?? 'gmn') === 'nnn') ? 'nnn' : 'gmn'; }
function net_name(?string $n = null): string { return ($n ?? net()) === 'nnn' ? 'WODDI Nurture NextGen Network' : 'WODDI Good Mother Network'; }
function net_code(?string $n = null): string { return ($n ?? net()) === 'nnn' ? 'WNNN' : 'WGMN'; }
function require_login(): array { $m = me(); if (!$m) { $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? ''; redirect('login'); } return $m; }
function require_admin(): array { $m = require_login(); if (!is_admin()) { http_response_code(403); exit('Staff access only.'); } return $m; }
function display_name(?array $u): string { return trim((string)(($u['display_name'] ?? '') ?: ($u['name'] ?? '') ?: 'Member')); }
function first_name(?array $u): string { $p = preg_split('/\s+/', display_name($u)); return $p[0] ?? 'Member'; }

/* ── Settings and feature flags ── */
function setting(string $key, $default = null) {
    static $s = null;
    if ($s === null) { $s = []; try { foreach (rows('SELECT `key`, `value` FROM system_settings') as $r) $s[$r['key']] = $r['value']; } catch (Throwable $e) {} }
    if (!array_key_exists($key, $s)) return $default;
    $v = $s[$key]; $j = json_decode((string)$v, true);
    return (json_last_error() === JSON_ERROR_NONE && !is_array($j) && $j !== null) ? $j : ($v ?? $default);
}
function flag(string $key, bool $default = true): bool {
    static $f = null;
    if ($f === null) { $f = []; try { foreach (rows('SELECT `key`, enabled FROM feature_flags') as $r) $f[$r['key']] = (bool)$r['enabled']; } catch (Throwable $e) {} }
    return $f[$key] ?? $default;
}
/* Google sign-in client ID: from config.php, or the google_gis switch that came across from the old site. */
function google_client_id(): string {
    if ($c = (string)cfg('google_client_id', '')) return $c;
    try { $j = jdec(val("SELECT `value` FROM site_flags WHERE `key` = 'google_gis'")); return !empty($j['on']) ? (string)($j['client_id'] ?? '') : ''; } catch (Throwable $e) { return ''; }
}
function admissions_open(): bool {
    $o = (string)setting('admission_override', 'auto');
    if ($o === 'open') return true; if ($o === 'closed') return false;
    $m = (int)gmdate('n'); $d = (int)gmdate('j');
    return in_array($m, [2, 6, 9], true) && $d >= 1 && $d <= 14;
}

/* ── Notifications (in-app, plus phone push) ── */
function notify(string $uid, string $title, string $body = '', string $link = '', string $kind = 'info', bool $push = true): void {
    try {
        ins('notifications', ['uid' => $uid, 'user_id' => $uid, 'kind' => $kind, 'type' => $kind, 'title' => $title, 'body' => $body, 'text' => $body, 'link_url' => $link, 'link' => $link, 'unread' => 1, 'read' => 0]);
    } catch (Throwable $e) { error_log('notify: ' . $e->getMessage()); }
    if ($push) { require_once APP_ROOT . '/lib/push.php'; try { push_to_user($uid, $title, $body, $link ? url($link) : url('')); } catch (Throwable $e) { error_log('push: ' . $e->getMessage()); } }
}
function unread_count(string $uid): int {
    try { return (int)val('SELECT COUNT(*) FROM notifications WHERE (uid = ? OR user_id = ?) AND read_at IS NULL AND COALESCE(`read`,0) = 0', [$uid, $uid]); } catch (Throwable $e) { return 0; }
}

/* ── Growth Points ── */
const LEVELS = [['Seedling', 0], ['Sprout', 250], ['Rising Leader', 600], ['Change Maker', 1500], ['Legacy Builder', 4000]];
function level_for(int $pts): string { $n = 'Seedling'; foreach (LEVELS as $l) if ($pts >= $l[1]) $n = $l[0]; return $n; }
function points_rules(): array {
    $d = ['rooting_module' => 10, 'blueprint_lesson' => 10, 'course_module' => 20, 'certificate' => 50, 'market_boost_cost' => 100, 'market_boost_days' => 7, 'social_showcase_cost' => 250];
    try { $r = row("SELECT `value` FROM site_flags WHERE `key` = 'points_rules'"); if ($r) $d = array_merge($d, jdec($r['value'])); } catch (Throwable $e) {}
    return $d;
}
function points(string $uid): int { return (int)(val('SELECT points FROM growth_profiles WHERE uid = ?', [$uid]) ?? 0); }
/* Awards points once per reason. Returns the points given (0 if this reason was already rewarded). */
function award(string $uid, int $pts, string $reason): int {
    if ($pts === 0) return 0;
    if ($pts > 0 && val('SELECT 1 FROM growth_transactions WHERE uid = ? AND reason = ? LIMIT 1', [$uid, $reason])) return 0;
    ins('growth_transactions', ['uid' => $uid, 'points' => $pts, 'reason' => $reason]);
    $cur = row('SELECT points FROM growth_profiles WHERE uid = ?', [$uid]);
    $new = max(0, (int)($cur['points'] ?? 0) + $pts);
    if ($cur) upd('growth_profiles', ['points' => $new, 'level_name' => level_for($new), 'updated_at' => now()], 'uid = ?', [$uid]);
    else ins('growth_profiles', ['uid' => $uid, 'points' => $new, 'level_name' => level_for($new), 'badges' => [], 'streak_days' => 0, 'updated_at' => now()]);
    if ($pts > 0) $_SESSION['cheer'] = ['pts' => $pts, 'reason' => $reason];
    return $pts;
}
function log_event(string $uid, string $kind, string $ref = '', array $meta = []): void {
    try { ins('learning_events', ['uid' => $uid, 'kind' => $kind, 'ref' => $ref, 'meta' => $meta, 'source' => 'php']); } catch (Throwable $e) {}
}
function touch_user(string $uid): void {
    if (($_SESSION['seen_at'] ?? 0) > time() - 300) return;
    $_SESSION['seen_at'] = time();
    try { upd('users', ['last_seen' => now()], 'uid = ?', [$uid]); } catch (Throwable $e) {}
}

/* ── Email (Brevo when a key is set, otherwise the server's own mail) ── */
function send_mail(string $to, string $subject, string $html): bool {
    $from = cfg('mail_from', 'no-reply@woddiinstitute.com'); $name = cfg('mail_name', 'WODDI Institute');
    $key = cfg('brevo_key');
    if ($key) {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['sender' => ['name' => $name, 'email' => $from], 'to' => [['email' => $to]], 'subject' => $subject, 'htmlContent' => $html])]);
        curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $ok = $code >= 200 && $code < 300;
    } else {
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: $name <$from>\r\n");
    }
    try { ins('email_log', ['to_email' => $to, 'sender' => $from, 'subject' => $subject, 'status' => $ok ? 'sent' : 'failed', 'kind' => 'app']); } catch (Throwable $e) {}
    return (bool)$ok;
}
function http_json(string $url, array $payload, array $headers = [], int $timeout = 25): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers), CURLOPT_POSTFIELDS => json_encode($payload)]);
    $r = curl_exec($ch); curl_close($ch);
    return $r ? json_decode($r, true) : null;
}
