<?php
/* Shared hosting (cPanel / QServers) only. Copy this file to config.local.php and
 * fill it in. On Render, leave this alone and use the Environment tab instead:
 * the names are the same. Never upload config.local.php to GitHub. */
return [
  'APP_URL'     => 'https://your-domain.org',
  'DB_HOST'     => 'localhost',
  'DB_PORT'     => '3306',
  'DB_NAME'     => 'cpaneluser_wdos',
  'DB_USER'     => 'cpaneluser_wdos',
  'DB_PASS'     => '',
  'DB_SSL'      => '0',          // 1 only for TiDB Cloud
  'ADMIN_KEY'   => '',           // long random text: protects /api/setup and /api/cron
  'VAPID_PUBLIC'  => '',
  'VAPID_PRIVATE' => '',
  'VAPID_SUBJECT' => 'mailto:it@thewoddi.org',
  'GOOGLE_CLIENT_ID'     => '',
  'GOOGLE_CLIENT_SECRET' => '',
  'BREVO_API_KEY' => '',
  'MAIL_FROM'     => '',
  'MAIL_FROM_NAME' => 'WODDI HQ',
  // Voice calls (Cloudflare Realtime). TURN helps calls connect through strict networks; the SFU relay carries group
  // calls of more than 8 people. Leave empty to switch either off.
  'CF_TURN_KEY_ID'        => '',
  'CF_TURN_KEY_API_TOKEN' => '',
  'CF_SFU_APP_ID'    => '',
  'CF_SFU_APP_TOKEN' => '',
  'BIG_CALL_MAX'     => '150',     // people on one relay call, the caller included
];
