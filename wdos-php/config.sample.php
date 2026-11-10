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
];
