<?php
// Local test router only: stands in for the .htaccess rules.
$root = realpath('/home/claude/wdos-php') ?: '/home/claude/wdos-php';   // CI links this path to the checkout: compare real paths
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/privacy/?$#', $path)) { header('Content-Type: text/html'); readfile($root . '/privacy.html'); return true; }
if (preg_match('#^/api(/|$)#', $path)) { require $root . '/api/index.php'; return true; }
if (preg_match('#^/(lib|migrations)/|config\.local\.php#', $path)) { http_response_code(403); echo 'forbidden'; return true; }
$f = realpath($root . $path);
if ($f && is_file($f) && str_starts_with($f, $root)) {
  $types = ["webp"=>"image/webp",'js'=>'text/javascript','css'=>'text/css','html'=>'text/html','json'=>'application/json','svg'=>'image/svg+xml','png'=>'image/png','woff2'=>'font/woff2','webmanifest'=>'application/manifest+json'];
  header('Content-Type: ' . ($types[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream')); readfile($f); return true;
}
header('Content-Type: text/html'); readfile($root . '/index.html'); return true;
