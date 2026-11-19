<?php
/* Page layout and small UI builders. */
declare(strict_types=1);

const NAV_MAIN = [['', 'Home', 'fa-house'], ['learn', 'Learn', 'fa-graduation-cap'], ['community', 'Community', 'fa-comments'], ['mentor', 'Mentor', 'fa-robot'], ['me', 'Profile', 'fa-user']];
const NAV_MORE = [['radio', 'Radio', 'fa-radio'], ['market', 'Market', 'fa-store'], ['journey', 'Journey', 'fa-road'], ['learn?mine=1', 'My Courses', 'fa-book-open-reader'], ['progress', 'Progress', 'fa-chart-line'], ['certificates', 'Certs', 'fa-certificate'], ['points?tab=board', 'Leaderboard', 'fa-trophy'], ['community?tab=events', 'Events', 'fa-calendar-days'], ['support', 'Support', 'fa-life-ring'], ['settings', 'Settings', 'fa-gear']];

function ic(string $name, string $extra = ''): string { return '<i class="fa-solid ' . $name . ($extra ? ' ' . $extra : '') . '" aria-hidden="true"></i>'; }
function pill(string $text, string $cls = ''): string { return '<span class="pill ' . $cls . '">' . e($text) . '</span>'; }
function bar(int $pct): string { return '<div class="bar"><i style="width:' . max(0, min(100, $pct)) . '%"></i></div>'; }
function avatar(?array $u, string $cls = ''): string {
    $src = $u['avatar_url'] ?? ($u['avatar'] ?? '');
    if ($src && preg_match('#^(https?://|/)#', (string)$src)) return '<img class="av ' . $cls . '" src="' . e($src) . '" alt="">';
    return '<span class="av ' . $cls . '">' . e(initials(display_name($u))) . '</span>';
}
function tabs(array $items, string $current, string $baseUrl, string $param = 'tab'): string {
    $h = '<div class="seg">';
    foreach ($items as $k => $label) $h .= '<a class="' . ($k === $current ? 'on' : '') . '" href="' . e($baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $param . '=' . $k) . '">' . e($label) . '</a>';
    return $h . '</div>';
}
function empty_state(string $icon, string $title, string $sub = '', string $cta = ''): string {
    return '<div class="card col center"><span class="ic n">' . ic($icon) . '</span><p class="bold">' . e($title) . '</p>' . ($sub ? '<p class="mut sm">' . e($sub) . '</p>' : '') . $cta . '</div>';
}

function page(string $title, string $html, array $o = []): never {
    $me = me(); $active = $o['active'] ?? ''; $back = $o['back'] ?? null; $bare = !empty($o['bare']) || !$me;
    $dark = ($_COOKIE['theme'] ?? '') === 'dark';
    $unread = $me ? unread_count($me['uid']) : 0;
    $netLogo = asset(net() === 'nnn' ? 'logo-wnnn.png' : 'logo-wgmn.png');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
    ?><!doctype html>
<html lang="en" class="<?= $dark ? 'dark' : '' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> | WODDI Institute</title>
<meta name="theme-color" content="#2A0F2B">
<link rel="manifest" href="<?= url('manifest.json') ?>">
<link rel="icon" href="<?= asset('icon-192.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('icon-192.png') ?>">
<link rel="stylesheet" href="<?= asset('fonts/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('fa/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('app.css') ?>">
<script>window.WODDI = { base: <?= json_encode(base()) ?>, csrf: <?= json_encode(csrf()) ?>, vapid: <?= json_encode((string)cfg('vapid.public', '')) ?>, signedIn: <?= $me ? 'true' : 'false' ?> };</script>
</head>
<body class="<?= $bare ? 'bare' : 'app' ?>">
<?php if ($bare): ?>
<?= $html ?>
<?php else: ?>
<div class="sh">
  <nav class="side" aria-label="Main menu">
    <a class="brand" href="<?= url('') ?>"><img src="<?= asset('logo.jpg') ?>" alt="WODDI logo"><span><b>WODDI Institute</b><small>Learning platform</small></span></a>
    <div class="netbox"><img src="<?= $netLogo ?>" alt=""><span><small>Your network</small><b><?= e(net_name()) ?></b></span></div>
    <?php if (is_staff()): ?><a class="sb" style="background:#D4006A;color:#fff" href="<?= url('ops') ?>"><?= ic('fa-gauge-high') ?>Operations (admin)</a><?php endif; ?>
    <?php foreach (NAV_MAIN as $n): ?><a class="sb <?= $active === $n[0] ? 'on' : '' ?>" href="<?= url($n[0]) ?>"><?= ic($n[2]) ?><?= e($n[1]) ?></a><?php endforeach; ?>
    <div class="lab">More</div>
    <?php foreach (NAV_MORE as $n): ?><a class="sb <?= $active === strtok($n[0], '?') && !str_contains($n[0], '?') ? 'on' : '' ?>" href="<?= url($n[0]) ?>"><?= ic($n[2]) ?><?= e($n[1]) ?></a><?php endforeach; ?>
    <a class="sb <?= $active === 'messages' ? 'on' : '' ?>" href="<?= url('messages') ?>"><?= ic('fa-envelope') ?>Messages</a>
    <span class="grow"></span>
    <a class="sb" href="<?= url('logout') ?>"><?= ic('fa-right-from-bracket') ?>Sign out</a>
  </nav>
  <div class="main">
    <header class="top">
      <?php if ($back !== null): ?><a class="ib f" href="<?= url($back) ?>" aria-label="Back"><?= ic('fa-chevron-left') ?></a><?php else: ?><img class="mob logo" src="<?= asset('logo.jpg') ?>" alt="WODDI logo"><?php endif; ?>
      <div class="ttl"><?= e($title) ?></div>
      <a class="pill netpill" href="<?= url('settings') ?>"><img src="<?= $netLogo ?>" alt=""><?= e(net_code()) ?></a>
      <button class="ib" type="button" data-theme aria-label="Toggle dark mode"><?= ic($dark ? 'fa-sun' : 'fa-moon') ?></button>
      <button class="ib moreb" type="button" data-open="#more" aria-label="More: radio, market, journey and others"><?= ic('fa-grip') ?></button>
      <a class="ib" href="<?= url('messages') ?>" aria-label="Messages and notifications"><?= ic('fa-bell') ?><?php if ($unread): ?><span class="dot"></span><?php endif; ?></a>
      <?php if (is_staff()): ?><a class="b s" href="<?= url('ops') ?>"><?= ic('fa-gauge-high') ?> Admin</a><?php endif; ?>
      <a href="<?= url('me') ?>" aria-label="My account"><?= avatar($me) ?></a>
    </header>
    <main class="cont">
      <?php foreach (flash() as $f): ?><div class="flash <?= e($f[0]) ?>" role="status"><?= ic($f[0] === 'err' ? 'fa-circle-exclamation' : 'fa-circle-check') ?><span><?= e($f[1]) ?></span></div><?php endforeach; ?>
      <?= $html ?>
    </main>
    <?php if (empty($o['immersive'])): ?>
    <nav class="tabbar" aria-label="Main tabs">
      <?php foreach (NAV_MAIN as $n): ?><a class="tb <?= $active === $n[0] ? 'on' : '' ?>" href="<?= url($n[0]) ?>"><?= ic($n[2]) ?><?= e($n[1]) ?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
</div>
<div class="sheetwrap" id="more" hidden><button class="ov" type="button" data-close aria-label="Close"></button><div class="sheet"><span class="grab"></span><div class="row jb"><h2>More</h2><button class="ib f" type="button" data-close aria-label="Close"><?= ic('fa-xmark') ?></button></div>
  <div class="mg"><?php foreach (NAV_MORE as $n): ?><a class="mi" href="<?= url($n[0]) ?>"><?= ic($n[2]) ?><?= e($n[1]) ?></a><?php endforeach; ?><a class="mi" href="<?= url('messages') ?>"><?= ic('fa-envelope') ?>Messages</a><a class="mi" href="<?= url('alumni') ?>"><?= ic('fa-user-graduate') ?>Alumni</a><?php if (is_staff()): ?><a class="mi" href="<?= url('ops') ?>"><?= ic('fa-gauge-high') ?>Operations</a><?php endif; ?></div></div></div>
<?php if (!empty($_SESSION['cheer'])): $c = $_SESSION['cheer']; unset($_SESSION['cheer']); ?>
<div class="sheetwrap" id="cheer"><button class="ov" type="button" data-close aria-label="Close"></button><div class="sheet center"><span class="ic l big"><?= ic('fa-seedling') ?></span><p class="cheer">+<?= (int)$c['pts'] ?> Growth Points</p><p>Well done, <?= e(first_name($me)) ?>. Keep going.</p><button class="b full" type="button" data-close>Continue</button></div></div>
<?php endif; ?>
<?php endif; ?>
<script src="<?= asset('app.js') ?>" defer></script>
<?= $o['foot'] ?? '' ?>
</body>
</html><?php
    exit;
}
