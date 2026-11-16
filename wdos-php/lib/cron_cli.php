<?php
/* The 5-minute timer for shared hosting, run by cPanel Cron Jobs as:  php /home/USER/public_html/lib/cron_cli.php
 * Command line only, so the admin key never has to be written into the cron line. It runs exactly what /api/cron runs. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$local = is_file(__DIR__ . '/../config.local.php') ? (array) require __DIR__ . '/../config.local.php' : [];
$_GET['key'] = (string) (getenv('ADMIN_KEY') ?: ($local['ADMIN_KEY'] ?? ''));
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/api/cron';
require __DIR__ . '/../api/index.php';
