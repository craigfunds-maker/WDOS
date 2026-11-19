<?php
/* Public landing page at the site root for signed-out visitors.
   It is the landing page of the live site, unchanged: assets/landing.js draws it and assets/landing-lang.js adds
   the language bar. The short script below connects its buttons, figures and translations to this PHP site. */
declare(strict_types=1);
$st = ['learners' => 0, 'certified' => 0, 'gmn' => count(catalog('gmn')), 'nnn' => count(catalog('nnn'))];
try {
    $st['learners'] = (int)val("SELECT COUNT(*) FROM users WHERE COALESCE(role,'student') NOT IN ('admin','super_admin')");
    $floor = (int)(jdec(val("SELECT `value` FROM site_flags WHERE `key` = 'landing_stats'"))['certified_floor'] ?? 0);
    $st['certified'] = max($floor, (int)val('SELECT COUNT(DISTINCT uid) FROM certificates WHERE COALESCE(revoked,0) = 0'));
} catch (Throwable $e) {}
$dark = ($_COOKIE['theme'] ?? '') === 'dark'; $b = base();
header('Content-Type: text/html; charset=utf-8'); header('X-Content-Type-Options: nosniff');
?><!doctype html>
<html lang="en" class="<?= $dark ? 'dark' : '' ?>"<?= $dark ? ' data-theme="dark"' : '' ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>WODDI Institute: Free Skills, Leadership and Income Training for African Women</title>
<meta name="description" content="Learn practical skills, discover your potential and build your future. Free courses, mentors and certificates for women across Africa, in your language.">
<meta name="theme-color" content="#2A0F2B"><link rel="manifest" href="<?= url('manifest.json') ?>"><link rel="icon" href="<?= asset('icon-192.png') ?>">
<base href="<?= e($b) ?>/">
<link rel="stylesheet" href="<?= asset('fonts/fonts.css') ?>"><link rel="stylesheet" href="<?= asset('font-awesome/css/all.min.css') ?>">
<style>*,*::before,*::after{box-sizing:border-box}html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#fff;color:#1a1020}img{max-width:100%}button{font:inherit;cursor:pointer}a{color:inherit}#landing{pointer-events:auto;min-height:100vh}</style>
</head>
<body>
<div id="app"><noscript><div style="max-width:640px;margin:60px auto;padding:24px;text-align:center"><h1>WODDI Institute</h1><p>Become the woman you were designed to be.</p><p><a href="<?= url('register') ?>">Create a free account</a> or <a href="<?= url('login') ?>">sign in</a>.</p></div></noscript></div>
<script>
(function () {
  var B = <?= json_encode($b) ?>, S = <?= json_encode($st) ?>;
  window.AppState = { theme: <?= json_encode($dark ? 'dark' : 'light') ?> };
  window.openAuth = function (mode, net) { location.href = B + (mode === 'signin' ? '/login' : '/register' + (net ? '?net=' + net : '')); };
  window.openGuestMarketplace = function (cat) { location.href = B + '/market' + (cat ? '?q=' + encodeURIComponent(cat) : ''); };
  window.toggleTheme = function () {
    var d = AppState.theme !== 'dark'; AppState.theme = d ? 'dark' : 'light';
    document.documentElement.classList.toggle('dark', d); if (d) document.documentElement.setAttribute('data-theme', 'dark'); else document.documentElement.removeAttribute('data-theme');
    document.cookie = 'theme=' + AppState.theme + ';path=/;max-age=31536000;samesite=lax';
  };
  /* course counts come from this site's own catalogue */
  window.getAllSkills = function () { return new Array(S.gmn); };
  window.WODDI_WNNN_BLUEPRINT_COURSES = new Array(Math.min(4, S.nnn)); window.WODDI_WNNN_ELECTIVE_COURSES = new Array(Math.max(0, S.nnn - 4));
  /* the landing script asks its data client for live figures and saved translations: answer from this site */
  function chain(lang) { var q = { select: function () { return q; }, eq: function (k, v) { if (k === 'lang') lang = v; return q; }, limit: function () { return fetch(B + '/api/i18n?lang=' + encodeURIComponent(lang || '')).then(function (r) { return r.json(); }).then(function (j) { return { data: j.rows || [] }; }).catch(function () { return { data: [] }; }); } }; return q; }
  window._sb = { rpc: function () { return Promise.resolve({ data: { learners: S.learners, certified: S.certified } }); }, from: function () { return chain(''); } };
  var realFetch = window.fetch.bind(window);
  window.fetch = function (u, o) { if (typeof u === 'string' && u.indexOf('/.netlify/functions/translate') === 0) u = B + '/api/translate'; return realFetch(u, o); };
  /* old addresses inside the page */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]'); if (!a) return; var h = a.getAttribute('href');
    var map = { '/marketplace': '/market', 'verify.html': '/verify', 'membership.html': '/membership', 'volunteer.html': '/volunteer', '/privacy.html': '/privacy' };
    if (map[h]) { e.preventDefault(); location.href = B + map[h]; }
    if (h === '#top') { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
  }, true);
})();
</script>
<script src="<?= asset('landing.js') ?>"></script>
<script src="<?= asset('landing-lang.js') ?>"></script>
<script>try { renderLanding(); } catch (e) { console.error(e); document.getElementById('app').innerHTML = '<div style="max-width:640px;margin:60px auto;padding:24px;text-align:center"><h1>WODDI Institute</h1><p><a href="<?= url('register') ?>">Create a free account</a> or <a href="<?= url('login') ?>">sign in</a>.</p></div>'; }</script>
</body>
</html>
