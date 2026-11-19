<?php
/* WODDI Institute (PHP). Front controller: every page request comes through here. */
declare(strict_types=1);
require __DIR__ . '/lib/core.php';

if (!installed()) { header('Location: install/'); exit; }
require __DIR__ . '/lib/learn.php';
require __DIR__ . '/lib/ui.php';

set_exception_handler(function (Throwable $e) {
    error_log('[woddi] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    @file_put_contents(APP_ROOT . '/storage/error.log', '[' . gmdate('c') . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
    if (!headers_sent()) http_response_code(500);
    echo '<div style="font-family:system-ui;max-width:560px;margin:60px auto;padding:24px"><h1>Something went wrong</h1><p>The team has been told. Please go back and try again.</p>' . (cfg('debug') ? '<pre style="white-space:pre-wrap">' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>' : '') . '</div>';
});

boot_session();
$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$bp = trim((string)parse_url(base(), PHP_URL_PATH), '/');
if ($bp !== '' && str_starts_with($path, $bp)) $path = trim(substr($path, strlen($bp)), '/');
if ($path === 'index.php') $path = '';
$seg = $path === '' ? [] : explode('/', $path);
$first = $seg[0] ?? '';
if (is_post() && $first !== 'api') csrf_check();
if ($u = me()) touch_user($u['uid']);

/* Old addresses from the previous site keep working: app.html, verify.html?code=..., ops.html and so on. */
if (preg_match('/^([a-z0-9-]+)\.html$/', $first, $m)) { $map = ['index' => '', 'app' => 'app', 'app-legacy' => 'app', 'dashboard' => '', 'app-auth' => 'login', 'signup' => 'register', 'admin' => 'ops', 'courses' => 'learn', 'marketplace' => 'market', 'certificate' => 'certificates', 'admissions' => 'register']; redirect($map[$m[1]] ?? $m[1], $_GET); }

if ($first === 'dashboard') redirect('', $_GET);

$routes = [
    'auth' => ['login', 'register', 'logout', 'forgot', 'reset', 'google'],
    'learn' => ['learn', 'journey', 'course'],
    'social' => ['community', 'market', 'mentor', 'radio', 'alumni'],
    'account' => ['', 'me', 'profile', 'settings', 'points', 'messages', 'support', 'certificates', 'progress', 'verify', 'delete-account'],
    'ops' => ['ops'],
    'api' => ['api'],
    'public' => ['membership', 'volunteer', 'review', 'manifest.json', 'app-manifest.json', 'privacy', 'about', 'tick', 'offline', 'app', 'app-auth'],
];
foreach ($routes as $file => $list) {
    if (in_array($first, $list, true)) { require __DIR__ . '/pages/' . $file . '.php'; exit; }
}
http_response_code(404);
page('Page not found', empty_state('fa-compass', 'We could not find that page', 'It may have moved.', '<a class="b" href="' . url('') . '">Go home</a>'));
