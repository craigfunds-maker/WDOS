<?php
/* WODDI Institute installer and Supabase migration tool. Runs in the browser. No command line needed.
   Before config.php exists anyone can open it (that is how you install). After that only the browser
   that did the install, or a signed-in admin, can use it. */
declare(strict_types=1);
require dirname(__DIR__) . '/lib/core.php';
require APP_ROOT . '/lib/push.php';
@set_time_limit(120);
boot_session();

$installed = installed();
$allowed = !$installed || !empty($_SESSION['installer']) || is_admin();
$step = (string)($_GET['step'] ?? ($installed ? 'migrate' : 'install'));
$err = ''; $ok = '';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function guess_base(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}
function vapid_pair_ok(string $pub, string $priv): bool {
    try {
        if (strlen(b64u_dec($pub)) !== 65 || strlen(b64u_dec($priv)) !== 32) return false;
        $jwt = vapid_jwt('https://example.com/x', $pub, $priv, 'mailto:test@example.com'); [$a, $b, $c] = explode('.', $jwt); $raw = b64u_dec($c);
        $int = function (string $v): string { $v = ltrim($v, "\0"); if ($v === '' || (ord($v[0]) & 0x80)) $v = "\0" . $v; return "\x02" . chr(strlen($v)) . $v; };
        $seq = $int(substr($raw, 0, 32)) . $int(substr($raw, 32)); $der = "\x30" . chr(strlen($seq)) . $seq;
        return openssl_verify("$a.$b", $der, ec_public_pem(b64u_dec($pub)), OPENSSL_ALGO_SHA256) === 1;
    } catch (Throwable $e) { return false; }
}
function write_config(array $c): bool {
    $ok = @file_put_contents(APP_ROOT . '/config.php', "<?php\n/* WODDI Institute settings. Keep this file private. */\nreturn " . var_export($c, true) . ";\n", LOCK_EX) !== false;
    if ($ok) { @chmod(APP_ROOT . '/config.php', 0640); if (function_exists('opcache_invalidate')) @opcache_invalidate(APP_ROOT . '/config.php', true); }
    return $ok;
}
function sb(string $path, array $opt = []): array {
    $s = $_SESSION['sb'] ?? null; if (!$s) return ['code' => 0, 'body' => null, 'range' => ''];
    $ch = curl_init(rtrim($s['url'], '/') . $path); $hdr = ['apikey: ' . $s['key'], 'Authorization: Bearer ' . $s['key'], 'Accept: application/json'];
    foreach ($opt['headers'] ?? [] as $x) $hdr[] = $x; $range = '';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $hdr, CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$range) { if (stripos($line, 'content-range:') === 0) $range = trim(substr($line, 14)); return strlen($line); }]);
    if (isset($opt['post'])) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt['post'])); curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($hdr, ['Content-Type: application/json'])); }
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $e = curl_error($ch); curl_close($ch);
    return ['code' => $code, 'body' => !empty($opt['raw']) ? $raw : json_decode((string)$raw, true), 'range' => $range, 'error' => $e, 'text' => is_string($raw) ? substr($raw, 0, 300) : ''];
}
function conv($v, string $kind) {
    if ($v === null) return null;
    if (is_bool($v)) return $v ? 1 : 0;
    if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($kind === 'time') { $t = strtotime((string)$v); return $t === false ? null : gmdate('Y-m-d H:i:s', $t); }
    if ($kind === 'date') return substr((string)$v, 0, 10);
    if ($kind === 'bool') return in_array($v, [true, 1, '1', 't', 'true'], true) ? 1 : 0;
    return $v;
}
function put_rows(string $table, array $rowsIn): int {
    $t = schema()[$table]; $cols = array_keys($t['cols']); $n = 0;
    foreach (array_chunk($rowsIn, 100) as $chunk) {
        $vals = []; $ph = [];
        foreach ($chunk as $r) { $ph[] = '(' . implode(',', array_fill(0, count($cols), '?')) . ')'; foreach ($cols as $c) $vals[] = conv($r[$c] ?? null, (string)($t['cols'][$c]['k'] ?? 'text')); }
        try { q('REPLACE INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ' . implode(',', $ph), $vals); $n += count($chunk); }
        catch (Throwable $e) { /* one bad row must not stop the rest: fall back to row by row */
            foreach ($chunk as $r) { try { q('REPLACE INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_map(fn($c) => conv($r[$c] ?? null, (string)($t['cols'][$c]['k'] ?? 'text')), $cols)); $n++; } catch (Throwable $e2) { $_SESSION['mig_err'][$table] = mb_substr($e2->getMessage(), 0, 200); } }
        }
    }
    return $n;
}
function course_alias_map(): array {
    $norm = function (string $s): string { $s = preg_replace('/^(core-|laurel-[a-z]+\d+-|wnnn-[a-z]+-\d+-)/', '', $s); $s = preg_replace('/-\d+$/', '', $s); return preg_replace('/-(and|for|the|of|in|to|a)(?=-)/', '', $s); };
    $ids = array_column(json_decode((string)file_get_contents(APP_ROOT . '/content/catalog.json'), true), 'id'); $idx = [];
    foreach ($ids as $id) $idx[$norm($id)][] = $id;
    return ['ids' => array_flip($ids), 'idx' => $idx, 'norm' => $norm];
}
function course_alias(string $old, array $m): ?string {
    if (isset($m['ids'][$old])) return null; $n = ($m['norm'])($old);
    if (isset($m['idx'][$n])) { $c = $m['idx'][$n]; foreach ($c as $x) if (str_starts_with($x, 'core-') === str_starts_with($old, 'core-')) return $x; return $c[0]; }
    $hits = array_filter(array_keys($m['idx']), fn($k) => strlen($n) >= 12 && str_starts_with($k, $n)); return count($hits) === 1 ? $m['idx'][array_values($hits)[0]][0] : null;
}

/* ───────── JSON actions used by the migration screen ───────── */
if (($do = (string)($_POST['do'] ?? '')) !== '' && str_starts_with($do, 'mig-')) {
    header('Content-Type: application/json; charset=utf-8');
    $out = fn(array $d) => exit(json_encode($d));
    if (!$installed || !$allowed) $out(['ok' => false, 'error' => 'Sign in as an admin first.']);
    if (!hash_equals(csrf(), (string)($_POST['_csrf'] ?? ''))) $out(['ok' => false, 'error' => 'This page is out of date. Reload it and try again.']);
    try {
        if ($do === 'mig-start') {
            $url = rtrim(trim((string)$_POST['url']), '/'); $key = trim((string)$_POST['key']);
            if (!preg_match('#^https://[a-z0-9]+\.supabase\.co$#', $url) || strlen($key) < 40) $out(['ok' => false, 'error' => 'Check the project address and paste the full service_role key.']);
            $_SESSION['sb'] = ['url' => $url, 'key' => $key]; $_SESSION['mig_err'] = [];
            $r = sb('/rest/v1/users?select=uid&limit=1', ['headers' => ['Prefer: count=exact']]);
            if ($r['code'] < 200 || $r['code'] >= 300) { unset($_SESSION['sb']); $out(['ok' => false, 'error' => 'Supabase refused the key (code ' . $r['code'] . '). Make sure it is the service_role key, not the anon key. ' . $r['error']]); }
            $a = sb('/rest/v1/rpc/export_auth_for_php', ['post' => ['p_limit' => 1, 'p_offset' => 0]]);
            $out(['ok' => true, 'tables' => array_keys(schema()), 'users' => (int)(explode('/', $r['range'])[1] ?? 0), 'auth' => $a['code'] >= 200 && $a['code'] < 300]);
        }
        if (empty($_SESSION['sb'])) $out(['ok' => false, 'error' => 'Connect to Supabase first.']);
        if ($do === 'mig-table') {
            $t = (string)$_POST['table']; $off = max(0, (int)$_POST['offset']); $S = schema(); if (!isset($S[$t])) $out(['ok' => false, 'error' => 'Unknown table']);
            if (in_array($t, ['auth_accounts', 'password_resets', 'migration_log'], true)) $out(['ok' => true, 'done' => true, 'copied' => 0, 'total' => 0, 'skip' => true]);
            $order = implode(',', array_map(fn($k) => rawurlencode($k) . '.asc', $S[$t]['pk'])); $size = 400;
            $r = sb('/rest/v1/' . rawurlencode($t) . '?select=*' . ($order ? '&order=' . $order : '') . "&limit=$size&offset=$off", ['headers' => ['Prefer: count=exact']]);
            if ($r['code'] === 404 || ($r['code'] >= 400 && is_array($r['body']) && in_array($r['body']['code'] ?? '', ['42P01', 'PGRST205', 'PGRST106'], true))) { q('REPLACE INTO migration_log (tbl, source_rows, copied_rows, finished_at, note) VALUES (?,?,?,?,?)', [$t, 0, 0, now(), 'Not in Supabase']); $out(['ok' => true, 'done' => true, 'copied' => 0, 'total' => 0, 'skip' => true]); }
            if ($r['code'] < 200 || $r['code'] >= 300 || !is_array($r['body'])) $out(['ok' => false, 'error' => "$t: Supabase answered " . $r['code'] . ' ' . $r['text']]);
            $total = (int)(explode('/', $r['range'])[1] ?? count($r['body'])); if ($off === 0 && !empty($_POST['fresh'])) q('DELETE FROM `' . $t . '`');
            put_rows($t, $r['body']); $next = $off + count($r['body']); $done = count($r['body']) < $size || $next >= $total;
            if ($done) { $have = (int)val('SELECT COUNT(*) FROM `' . $t . '`'); q('REPLACE INTO migration_log (tbl, source_rows, copied_rows, finished_at, note) VALUES (?,?,?,?,?)', [$t, $total, $have, now(), $_SESSION['mig_err'][$t] ?? '']); $out(['ok' => true, 'done' => true, 'total' => $total, 'have' => $have, 'note' => $_SESSION['mig_err'][$t] ?? '']); }
            $out(['ok' => true, 'done' => false, 'next' => $next, 'total' => $total]);
        }
        if ($do === 'mig-auth') {
            $off = max(0, (int)$_POST['offset']); $r = sb('/rest/v1/rpc/export_auth_for_php', ['post' => ['p_limit' => 500, 'p_offset' => $off]]);
            if ($r['code'] < 200 || $r['code'] >= 300 || !is_array($r['body'])) $out(['ok' => false, 'error' => 'Sign-in accounts: Supabase answered ' . $r['code'] . ' ' . $r['text']]);
            foreach ($r['body'] as $a) { if (empty($a['uid']) || empty($a['email'])) continue; try { foreach (rows('SELECT uid FROM auth_accounts WHERE email = ? AND uid <> ?', [mb_strtolower($a['email']), $a['uid']]) as $stale) { q('DELETE FROM auth_accounts WHERE uid = ?', [$stale['uid']]); q('DELETE FROM users WHERE uid = ?', [$stale['uid']]); } q('INSERT INTO auth_accounts (uid, email, phone, password_hash, google_sub, created_at, last_sign_in) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE email = VALUES(email), phone = VALUES(phone), password_hash = COALESCE(VALUES(password_hash), password_hash), google_sub = COALESCE(VALUES(google_sub), google_sub)', [$a['uid'], mb_strtolower($a['email']), $a['phone'], $a['password_hash'], $a['google_sub'], conv($a['created_at'], 'time') ?: now(), conv($a['last_sign_in'], 'time')]); } catch (Throwable $e) { $_SESSION['mig_err']['auth_accounts'] = mb_substr($e->getMessage(), 0, 200); } }
            $done = count($r['body']) < 500; $have = (int)val('SELECT COUNT(*) FROM auth_accounts');
            if ($done) q('REPLACE INTO migration_log (tbl, source_rows, copied_rows, finished_at, note) VALUES (?,?,?,?,?)', ['auth_accounts', $off + count($r['body']), $have, now(), ($_SESSION['mig_err']['auth_accounts'] ?? '') ?: (int)val('SELECT COUNT(*) FROM auth_accounts WHERE password_hash IS NOT NULL') . ' with a password, ' . (int)val('SELECT COUNT(*) FROM auth_accounts WHERE google_sub IS NOT NULL') . ' with Google']);
            $out(['ok' => true, 'done' => $done, 'next' => $off + count($r['body']), 'have' => $have]);
        }
        if ($do === 'mig-tidy') {
            $m = course_alias_map(); $moved = 0; $left = [];
            foreach (rows('SELECT DISTINCT course_id FROM course_progress WHERE course_id IS NOT NULL') as $r) { $new = course_alias((string)$r['course_id'], $m); if ($new) { q('UPDATE course_progress SET course_id = ? WHERE course_id = ?', [$new, $r['course_id']]); $moved++; } elseif (!isset($m['ids'][$r['course_id']])) $left[] = $r['course_id']; }
            foreach (rows('SELECT DISTINCT course_key FROM certificates WHERE course_key IS NOT NULL') as $r) { $new = course_alias((string)$r['course_key'], $m); if ($new) q('UPDATE certificates SET course_key = ? WHERE course_key = ?', [$new, $r['course_key']]); }
            q("INSERT IGNORE INTO auth_accounts (uid, email, created_at) SELECT uid, LOWER(email), COALESCE(created_at, NOW()) FROM users WHERE email IS NOT NULL AND email <> '' AND uid NOT IN (SELECT uid FROM (SELECT uid FROM auth_accounts) x)");
            q('INSERT IGNORE INTO growth_profiles (uid, points, updated_at) SELECT uid, GREATEST(SUM(points), 0), NOW() FROM growth_transactions WHERE uid IS NOT NULL GROUP BY uid');
            q("INSERT IGNORE INTO users (uid, email, name, display_name, role, pathway, status, created_at, last_seen) SELECT a.uid, a.email, 'Site Admin', 'Site Admin', 'super_admin', 'gmn', 'active', NOW(), NOW() FROM auth_accounts a WHERE a.uid NOT IN (SELECT uid FROM (SELECT uid FROM users) x)");
            if ($adm = mb_strtolower(trim((string)($_POST['admin'] ?? '')))) q("UPDATE users SET role = 'super_admin' WHERE LOWER(email) = ?", [$adm]);
            $admins = (int)val("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin')");
            q('REPLACE INTO migration_log (tbl, source_rows, copied_rows, finished_at, note) VALUES (?,?,?,?,?)', ['_tidy', $moved, count($left), now(), 'Old course ids with no match: ' . implode(', ', array_slice($left, 0, 20))]);
            $out(['ok' => true, 'moved' => $moved, 'left' => $left, 'admins' => $admins]);
        }
        if ($do === 'mig-files') {
            /* Copies files that still live in Supabase Storage into /uploads and points the rows at the new copy. */
            $origin = rtrim($_SESSION['sb']['url'], '/'); $end = microtime(true) + 22; $copied = 0; $failed = 0;
            $targets = ['users' => ['avatar_url', 'avatar'], 'forum_posts' => ['image_url', 'author_avatar'], 'forum_comments' => ['author_avatar'], 'community_posts' => ['image_url', 'author_avatar'], 'mp_listings' => ['image_url', 'seller_avatar', 'images'], 'volunteer_applications' => ['cv_url', 'id_url'], 'radio' => ['audio_url', 'thumbnail_url'], 'announcements' => ['media_url'], 'al_posts' => ['image_url'], 'support_messages' => ['attachment_url'], 'expert_mentors' => ['avatar_url'], 'certificates' => ['pdf_url'], 'notifications' => ['from_user_avatar']];
            foreach ($targets as $t => $cols) foreach ($cols as $c) {
                while (microtime(true) < $end) {
                    $r = row("SELECT `$c` v FROM `$t` WHERE `$c` LIKE ? LIMIT 1", ['%' . $origin . '/storage/v1/object/%']); if (!$r) break;
                    if (!preg_match('#' . preg_quote($origin, '#') . '/storage/v1/object/(?:public/|sign/|authenticated/)?([^"\'\s?\\\\]+)#', (string)$r['v'], $mm)) break;
                    $old = $mm[0]; $path = $mm[1]; $safe = preg_replace('#[^A-Za-z0-9._/-]#', '_', rawurldecode($path)); $safe = str_replace('..', '_', $safe);
                    $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION)); $new = null;
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'mp3', 'm4a', 'mp4', 'doc', 'docx', 'svg'], true)) { $f = sb('/storage/v1/object/' . $path, ['raw' => true]); if ($f['code'] >= 200 && $f['code'] < 300 && is_string($f['body']) && $f['body'] !== '') { $dest = APP_ROOT . '/uploads/migrated/' . $safe; @mkdir(dirname($dest), 0755, true); if (@file_put_contents($dest, $f['body']) !== false) $new = url('uploads/migrated/' . $safe); } }
                    if ($new) { q("UPDATE `$t` SET `$c` = REPLACE(`$c`, ?, ?) WHERE `$c` LIKE ?", [$old, $new, '%' . $old . '%']); $copied++; }
                    else { q("UPDATE `$t` SET `$c` = REPLACE(`$c`, ?, ?) WHERE `$c` LIKE ?", [$old, str_replace('/storage/v1/object/', '/storage/v1/object-missing/', $old), '%' . $old . '%']); $failed++; }
                }
                if (microtime(true) >= $end) $out(['ok' => true, 'done' => false, 'copied' => $copied, 'failed' => $failed]);
            }
            $out(['ok' => true, 'done' => true, 'copied' => $copied, 'failed' => $failed]);
        }
        if ($do === 'mig-finish') { unset($_SESSION['sb']); $out(['ok' => true]); }
    } catch (Throwable $e) { $out(['ok' => false, 'error' => $e->getMessage()]); }
    $out(['ok' => false, 'error' => 'Unknown step']);
}

/* ───────── Install ───────── */
$checks = [
    ['PHP 8.0 or newer (this server has ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['MySQL driver (pdo_mysql)', extension_loaded('pdo_mysql')], ['OpenSSL, with elliptic curve keys for push', extension_loaded('openssl') && in_array('prime256v1', openssl_get_curve_names() ?: [], true)],
    ['cURL', extension_loaded('curl')], ['Multibyte strings (mbstring)', extension_loaded('mbstring')],
    ['The site folder can be written to (for config.php)', is_writable(APP_ROOT)], ['The storage folder can be written to', (is_dir(APP_ROOT . '/storage') || @mkdir(APP_ROOT . '/storage', 0755, true)) && is_writable(APP_ROOT . '/storage')], ['The uploads folder can be written to', (is_dir(APP_ROOT . '/uploads') || @mkdir(APP_ROOT . '/uploads', 0755, true)) && is_writable(APP_ROOT . '/uploads')],
];
$allOk = !in_array(false, array_column($checks, 1), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $do === 'install' && !$installed) {
    $p = fn($k, $d = '') => trim((string)($_POST[$k] ?? $d));
    $db = ['host' => $p('db_host', 'localhost'), 'port' => (int)$p('db_port', '3306') ?: 3306, 'name' => $p('db_name'), 'user' => $p('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')];
    if (preg_match('/^(.+):(\d+)$/', $db['host'], $hm)) { $db['host'] = $hm[1]; $db['port'] = (int)$hm[2]; }
    if (!hash_equals(csrf(), (string)($_POST['_csrf'] ?? ''))) $err = 'This page is out of date. Reload it and try again.';
    elseif (!$allOk) $err = 'Fix the items marked in red first.';
    else try {
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $vp = $p('vapid_public'); $vs = $p('vapid_private');
        if ($vp !== '' || $vs !== '') { if (!vapid_pair_ok($vp, $vs)) throw new RuntimeException('The two push keys do not belong together. Copy VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY again from Netlify, or leave both boxes empty to create new keys.'); $vapid = ['public' => $vp, 'private' => $vs]; }
        else $vapid = vapid_generate();
        $sql = (string)file_get_contents(APP_ROOT . '/sql/schema.sql'); $n = 0;
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) { $stmt = trim(preg_replace('/^\s*--.*$/m', '', $stmt)); if ($stmt !== '') { $pdo->exec($stmt); $n++; } }
        $email = mb_strtolower($p('admin_email')); $pw = (string)($_POST['admin_pass'] ?? '');
        $cfgArr = ['base_url' => rtrim($p('base_url', guess_base()), '/'), 'db' => $db, 'vapid' => $vapid + ['subject' => 'mailto:' . ($email ?: 'support@thewoddi.org')], 'cron_key' => bin2hex(random_bytes(20)), 'mail_from' => $p('mail_from', 'no-reply@' . preg_replace('/^www\./', '', (string)parse_url(guess_base(), PHP_URL_HOST))), 'mail_name' => 'WODDI Institute', 'debug' => false, 'admin_email' => $email];
        foreach (['brevo_key', 'gemini_key', 'google_client_id'] as $k) if ($p($k) !== '') $cfgArr[$k] = $p($k);
        if (!write_config($cfgArr)) throw new RuntimeException('Could not write config.php. In cPanel File Manager, set the site folder permission to 755 and try again.');
        if ($email !== '' && strlen($pw) >= 8) {
            $st = $pdo->prepare('SELECT uid FROM auth_accounts WHERE email = ?'); $st->execute([$email]); $uidA = $st->fetchColumn();
            if (!$uidA) { $uidA = uuid4(); $pdo->prepare('INSERT INTO auth_accounts (uid, email, password_hash, created_at) VALUES (?,?,?,UTC_TIMESTAMP())')->execute([$uidA, $email, password_hash($pw, PASSWORD_BCRYPT)]); $pdo->prepare("INSERT INTO users (uid, email, name, display_name, role, pathway, status, created_at, last_seen) VALUES (?,?,?,?,'super_admin','gmn','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$uidA, $email, 'Site Admin', 'Site Admin']); }
        }
        $pdo->exec("INSERT IGNORE INTO system_settings (`key`, value, updated_at) VALUES ('admission_override','open',UTC_TIMESTAMP()),('onboarding_gate','off',UTC_TIMESTAMP()),('journal_visibility','private',UTC_TIMESTAMP())");
        $_SESSION['installer'] = true; header('Location: ?step=migrate&fresh=1'); exit;
    } catch (Throwable $e) { $err = $e instanceof PDOException ? 'The database did not accept those details: ' . $e->getMessage() : $e->getMessage(); if (is_file(APP_ROOT . '/config.php') && !($e instanceof RuntimeException)) @unlink(APP_ROOT . '/config.php'); }
}
$base = $installed ? base() : guess_base();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Set up WODDI Institute</title>
<link rel="stylesheet" href="../assets/fonts/fonts.css">
<link rel="stylesheet" href="../assets/fa/css/all.min.css"><link rel="stylesheet" href="../assets/app.css?v=<?= APP_VERSION ?>">
<style>body{background:var(--bg)}.wrap{max-width:860px;margin:0 auto;padding:24px 16px 80px;display:flex;flex-direction:column;gap:16px}.ck{display:flex;gap:10px;align-items:center;padding:6px 0}.ck i{width:20px}.ok{color:var(--lemt)}.no{color:var(--red)}.log{max-height:340px;overflow:auto;font-size:.88em}.log table{width:100%;border-collapse:collapse}.log td{padding:6px 8px;border-bottom:1px solid var(--line)}</style></head>
<body><div class="wrap">
<div class="row"><img src="../assets/logo.jpg" alt="WODDI logo" style="width:52px;height:52px;border-radius:14px"><div><h1 style="font-size:1.5em">Set up WODDI Institute</h1><p class="mut sm">Step <?= $step === 'install' ? '1 of 2: connect the database' : '2 of 2: bring the data across from Supabase' ?></p></div></div>
<?php if ($err): ?><div class="flash err"><i class="fa-solid fa-circle-exclamation"></i><span><?= h($err) ?></span></div><?php endif; ?>

<?php if (!$allowed): ?>
<div class="card col"><h2>This site is already set up</h2><p>To open the migration tool again, sign in with an admin account first.</p><a class="b" href="<?= h(url('login')) ?>">Sign in</a></div>

<?php elseif (!$installed): ?>
<div class="card col"><h3>Server check</h3><?php foreach ($checks as $c): ?><div class="ck"><i class="fa-solid <?= $c[1] ? 'fa-circle-check ok' : 'fa-circle-xmark no' ?>"></i><span><?= h($c[0]) ?></span></div><?php endforeach; ?>
<?php if (!$allOk): ?><p class="sm no">In cPanel open "Select PHP Version", choose PHP 8.1 or newer, tick the missing extensions, then reload this page.</p><?php endif; ?></div>
<form method="post" class="card col" autocomplete="off"><input type="hidden" name="_csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="install">
  <h3>1. Database</h3><p class="mut sm">Create these in cPanel under "MySQL Databases", then type them here exactly. On QServers the names start with your cPanel username, for example <b>woddi_institute</b>.</p>
  <div class="g2"><label class="fld">Database name<input class="inp" name="db_name" required value="<?= h($_POST['db_name'] ?? '') ?>"></label><label class="fld">Database user<input class="inp" name="db_user" required value="<?= h($_POST['db_user'] ?? '') ?>"></label><label class="fld">Database password<input class="inp" type="password" name="db_pass" required></label><label class="fld">Database host<input class="inp" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>"><small>Leave as localhost on cPanel.</small></label></div>
  <h3>2. Site address</h3><label class="fld">Address of this site<input class="inp" name="base_url" value="<?= h($_POST['base_url'] ?? $base) ?>" required><small>No slash at the end. Use https.</small></label>
  <h3>3. Your admin sign-in</h3><p class="mut sm">If this email already has an account in Supabase, it becomes the super admin after the data comes across and keeps its old password. If not, a new admin account is made with the password below.</p>
  <div class="g2"><label class="fld">Admin email<input class="inp" type="email" name="admin_email" required value="<?= h($_POST['admin_email'] ?? '') ?>"></label><label class="fld">Password (8 characters or more)<input class="inp" type="password" name="admin_pass" minlength="8" required></label></div>
  <h3>4. Push notification keys</h3><p class="mut sm">To keep the phones that already allowed notifications, paste the two keys from Netlify (Site configuration, Environment variables: VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY). Leave both empty to create new keys. With new keys every learner taps "Turn on notifications" once more.</p>
  <div class="g2"><label class="fld">VAPID public key<input class="inp" name="vapid_public" value="<?= h($_POST['vapid_public'] ?? '') ?>"></label><label class="fld">VAPID private key<input class="inp" name="vapid_private" value=""></label></div>
  <h3>5. Optional keys</h3><p class="mut sm">You can add or change these later under Operations, Settings.</p>
  <div class="g2"><label class="fld">Brevo API key (email)<input class="inp" name="brevo_key"></label><label class="fld">Gemini API key (AI mentor)<input class="inp" name="gemini_key"></label><label class="fld">Google sign-in client ID<input class="inp" name="google_client_id"></label><label class="fld">Send email from<input class="inp" name="mail_from" placeholder="no-reply@woddiinstitute.com"></label></div>
  <button class="b" <?= $allOk ? '' : 'disabled' ?>><i class="fa-solid fa-check"></i> Install</button>
</form>

<?php else: $log = []; try { foreach (rows('SELECT * FROM migration_log ORDER BY tbl') as $r) $log[$r['tbl']] = $r; } catch (Throwable $e) {} ?>
<?php if (!empty($_GET['fresh'])): ?><div class="flash"><i class="fa-solid fa-circle-check"></i><span>Installed. The database tables are ready and the site is live at <a href="<?= h(url('')) ?>" style="text-decoration:underline"><?= h(url('')) ?></a>.</span></div><?php endif; ?>
<div class="card col"><h3>Bring the data across from Supabase</h3>
  <p class="sm">This copies every table, every sign-in account with its password, and the uploaded files, straight from Supabase to this server. Nothing passes through anyone else. You can run it more than once: each run refreshes the copy, so run it again on the day you switch over.</p>
  <label class="fld">Supabase project address<input class="inp" id="sb-url" value="https://cmwhxfaskxsttvjvcijv.supabase.co"></label>
  <label class="fld">service_role key<input class="inp" id="sb-key" type="password" autocomplete="off"><small>In Supabase open Project Settings, then API Keys, and copy the key named service_role (click Reveal first). It is used for this copy only and is not saved on the server.</small></label>
  <label class="fld">Admin email to make super admin<input class="inp" id="sb-admin" type="email" value="<?= h((string)cfg('admin_email', '')) ?>"></label>
  <label class="row"><input type="checkbox" id="sb-fresh"> Empty each table here before copying (use this for the final copy on switch-over day)</label>
  <div class="row wrap"><button class="b" id="go" type="button"><i class="fa-solid fa-play"></i> Start the copy</button><a class="b g" href="<?= h(url('')) ?>">Skip for now and open the site</a></div>
  <div class="bar"><i id="pbar" style="width:0;background:var(--lem)"></i></div><p class="sm bold" id="status">Not started.</p>
</div>
<div class="card col"><h3>Result</h3><p class="mut sm">Supabase rows and rows here should match. A table marked "Not in Supabase" simply does not exist there.</p><div class="log"><table id="log"><tr><td><b>Table</b></td><td><b>Supabase</b></td><td><b>Here</b></td><td><b>Note</b></td></tr>
<?php foreach ($log as $r): ?><tr><td><?= h($r['tbl']) ?></td><td><?= (int)$r['source_rows'] ?></td><td><?= (int)$r['copied_rows'] ?></td><td class="mut"><?= h($r['note']) ?></td></tr><?php endforeach; ?></table></div></div>
<div class="card col"><h3>After the copy</h3><p class="sm">1. Sign in at <a href="<?= h(url('login')) ?>" style="text-decoration:underline"><?= h(url('login')) ?></a> with your admin email.<br>2. Open Operations, then Settings, and copy the scheduled job line into cPanel Cron Jobs.<br>3. Open Operations, then Push notifications, and send yourself a test.</p></div>
<script>
(function () {
  var csrf = <?= json_encode(csrf()) ?>, st = document.getElementById('status'), bar = document.getElementById('pbar'), log = document.getElementById('log'), go = document.getElementById('go');
  function call(d) { var b = new URLSearchParams(d); b.set('_csrf', csrf); return fetch('./', { method: 'POST', body: b, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function line(t, a, b, n, bad) { var tr = document.createElement('tr'); [t, a, b, n || ''].forEach(function (x) { var td = document.createElement('td'); td.textContent = x; tr.appendChild(td); }); if (bad) tr.style.color = 'var(--red)'; log.appendChild(tr); tr.scrollIntoView({ block: 'nearest' }); }
  function fail(m) { st.textContent = 'Stopped: ' + m; st.style.color = 'var(--red)'; go.disabled = false; }
  async function retry(d) { for (var i = 0; i < 3; i++) { try { var r = await call(d); if (r.ok) return r; if (i === 2) return r; } catch (e) { if (i === 2) return { ok: false, error: 'The connection dropped. Press Start again; it carries on safely.' }; } await new Promise(function (z) { setTimeout(z, 1500); }); } }
  go.addEventListener('click', async function () {
    go.disabled = true; st.style.color = ''; st.textContent = 'Connecting to Supabase...'; while (log.rows.length > 1) log.deleteRow(1);
    var s = await retry({ do: 'mig-start', url: document.getElementById('sb-url').value, key: document.getElementById('sb-key').value }); if (!s.ok) return fail(s.error);
    var fresh = document.getElementById('sb-fresh').checked ? '1' : '', tables = s.tables, bad = 0;
    for (var i = 0; i < tables.length; i++) {
      var off = 0, r;
      do { st.textContent = 'Copying ' + tables[i] + ' (' + (i + 1) + ' of ' + tables.length + ')' + (off ? ', row ' + off : ''); r = await retry({ do: 'mig-table', table: tables[i], offset: off, fresh: fresh }); if (!r.ok) { line(tables[i], '?', '?', r.error, true); bad++; break; } off = r.next || 0; } while (!r.done);
      if (r.ok && !r.skip) { var miss = r.have < r.total; if (miss) bad++; line(tables[i], r.total, r.have, r.note || (miss ? 'Some rows did not copy' : ''), miss); } else if (r.ok && r.skip && tables[i].indexOf('auth_') !== 0 && tables[i] !== 'password_resets' && tables[i] !== 'migration_log') line(tables[i], 0, 0, 'Not in Supabase');
      bar.style.width = Math.round((i + 1) / (tables.length + 3) * 100) + '%';
    }
    st.textContent = 'Copying sign-in accounts and passwords...';
    if (!s.auth) { line('auth_accounts', '?', '?', 'The helper function export_auth_for_php is missing in Supabase, so passwords were not copied.', true); bad++; }
    else { var o = 0, a; do { a = await retry({ do: 'mig-auth', offset: o }); if (!a.ok) { line('auth_accounts', '?', '?', a.error, true); bad++; break; } o = a.next; } while (!a.done); if (a.ok) line('auth_accounts', s.users, a.have, a.have < s.users ? 'Fewer accounts than learners' : 'Passwords and Google sign-ins copied', a.have < s.users); }
    bar.style.width = Math.round((tables.length + 1) / (tables.length + 3) * 100) + '%';
    st.textContent = 'Copying uploaded photos and files...'; var fc = 0, ff = 0, f; do { f = await retry({ do: 'mig-files' }); if (!f.ok) { line('uploaded files', '', '', f.error, true); bad++; break; } fc += f.copied; ff += f.failed; st.textContent = 'Copying uploaded photos and files... ' + fc + ' done'; } while (!f.done);
    if (f.ok) line('uploaded files', fc + ff, fc, ff ? ff + ' could not be fetched' : '', ff > 0);
    bar.style.width = Math.round((tables.length + 2) / (tables.length + 3) * 100) + '%';
    st.textContent = 'Tidying up...'; var t = await retry({ do: 'mig-tidy', admin: document.getElementById('sb-admin').value }); if (!t.ok) return fail(t.error);
    line('course ids brought up to date', t.moved, '', t.left.length ? t.left.length + ' old course ids have no match in the new catalogue: ' + t.left.join(', ') : '');
    await call({ do: 'mig-finish' }); bar.style.width = '100%';
    st.textContent = bad ? 'Finished with ' + bad + ' item(s) to look at, marked in red above.' : 'Finished. Every table matches. ' + t.admins + ' admin account(s) ready.'; st.style.color = bad ? 'var(--red)' : 'var(--lemt)'; go.disabled = false;
  });
})();
</script>
<?php endif; ?>
</div></body></html>
