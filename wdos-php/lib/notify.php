<?php
declare(strict_types=1);
/* One place that files a notification: the in-app row, and a push for every
 * device that member has switched on, both subject to her own preferences. */

const NOTIFY_KINDS = [
  'welcome'      => 'Welcome and account messages',
  'registration' => 'New registrations (HQ)',
  'announcement' => 'Announcements from HQ',
  'appointment'  => 'Leadership appointments',
  'account'      => 'Security and password changes',
  'birthday'     => 'Birthday greetings',
  'reminder'     => 'Reminders',
  'digest'       => 'Daily HQ summary',
];

function notify_prefs(int $userId): array {
  $out = [];
  foreach (NOTIFY_KINDS as $k => $_) $out[$k] = ['in_app' => true, 'push' => true];
  foreach (rows('SELECT kind,in_app,push FROM notification_prefs WHERE user_id=?', [$userId]) as $r) {
    if (isset($out[$r['kind']])) $out[$r['kind']] = ['in_app' => (int) $r['in_app'] === 1, 'push' => (int) $r['push'] === 1];
  }
  return $out;
}
/** File one notification. Security messages always land in the app. */
function notify(int $userId, string $kind, string $title, string $body = '', string $url = '#/notifications'): ?int {
  $p = notify_prefs($userId)[$kind] ?? ['in_app' => true, 'push' => true];
  $id = null;
  if ($p['in_app'] || $kind === 'account') {
    $id = ins('INSERT INTO notifications (user_id,kind,title,body,url,created_at) VALUES (?,?,?,?,?,?)',
      [$userId, $kind, mb_substr($title, 0, 160), mb_substr($body, 0, 600), mb_substr($url, 0, 250), now()]);
  }
  if ($p['push']) {
    $payload = json_encode(['title' => mb_substr($title, 0, 110), 'body' => mb_substr($body, 0, 240), 'url' => $url, 'kind' => $kind, 'id' => $id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    q('INSERT INTO push_outbox (subscription_id,payload,created_at) SELECT id, ?, ? FROM push_subscriptions WHERE user_id=?', [$payload, now(), $userId]);
  }
  return $id;
}
/** File the same notification for many members with two statements, not thousands. */
function notify_many(string $whereSql, array $params, string $kind, string $title, string $body, string $url): int {
  $t = mb_substr($title, 0, 160); $b = mb_substr($body, 0, 600); $n = now();
  $optOutApp = "NOT EXISTS (SELECT 1 FROM notification_prefs np WHERE np.user_id=u.id AND np.kind=? AND np.in_app=0)";
  $optOutPush = "NOT EXISTS (SELECT 1 FROM notification_prefs np WHERE np.user_id=u.id AND np.kind=? AND np.push=0)";
  $count = q("INSERT INTO notifications (user_id,kind,title,body,url,created_at)
              SELECT u.id, ?, ?, ?, ?, ? FROM users u WHERE u.status='active' AND ($whereSql) AND $optOutApp",
    array_merge([$kind, $t, $b, $url, $n], $params, [$kind]))->rowCount();
  $payload = json_encode(['title' => mb_substr($title, 0, 110), 'body' => mb_substr($body, 0, 240), 'url' => $url, 'kind' => $kind], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  q("INSERT INTO push_outbox (subscription_id,payload,created_at)
     SELECT ps.id, ?, ? FROM push_subscriptions ps JOIN users u ON u.id=ps.user_id WHERE u.status='active' AND ($whereSql) AND $optOutPush",
    array_merge([$payload, $n], $params, [$kind]));
  return $count;
}
function notify_hq(string $kind, string $title, string $body, string $url): void {
  notify_many("u.role IN ('hq_admin','super_admin')", [], $kind, $title, $body, $url);
}

/* ---- e-mail through Brevo (optional: only when BREVO_API_KEY is set) ----- */
function mail_configured(): bool { return (bool) cfg('BREVO_API_KEY') && (bool) cfg('MAIL_FROM'); }
function send_mail(string $to, string $toName, string $subject, string $html): bool {
  if (!mail_configured()) return false;
  $ch = curl_init('https://api.brevo.com/v3/smtp/email');
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['api-key: ' . cfg('BREVO_API_KEY'), 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['sender' => ['email' => cfg('MAIL_FROM'), 'name' => cfg('MAIL_FROM_NAME', 'WODDI HQ')],
      'to' => [['email' => $to, 'name' => $toName ?: $to]], 'subject' => $subject, 'htmlContent' => $html])]);
  curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return $code >= 200 && $code < 300;
}

/* ---- automations: run by the cron URL, each at most once a day ----------- */
function cron_once(string $job, callable $fn): ?string {
  $today = gmdate('Y-m-d');
  try { q('INSERT INTO cron_runs (job,ran_on,ran_at) VALUES (?,?,?)', [$job, $today, now()]); }
  catch (PDOException $e) { return null; }          // already ran today
  $res = (string) $fn();
  q('UPDATE cron_runs SET result=? WHERE job=? AND ran_on=?', [mb_substr($res, 0, 180), $job, $today]);
  return $res;
}
function run_automations(): array {
  $done = [];
  // 1. Birthday greeting, on the day, to the member herself.
  $r = cron_once('birthdays', function () {
    $n = 0;
    foreach (rows("SELECT id, first_name FROM users WHERE status='active' AND dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d')=?", [gmdate('m-d')]) as $u) {
      notify((int) $u['id'], 'birthday', 'Happy birthday, ' . $u['first_name'], 'Everyone at WODDI HQ is celebrating you today. Thank you for all you give to your network.', '#/notifications'); $n++;
    }
    return "$n greeted";
  });
  if ($r !== null) $done['birthdays'] = $r;
  // 2. One nudge, three days after joining, to members with no photograph yet.
  $r = cron_once('photo_nudge', function () {
    $day = gmdate('Y-m-d', time() - 3 * 86400); $n = 0;
    foreach (rows("SELECT id, first_name FROM users WHERE status='active' AND has_photo=0 AND DATE(created_at)=?", [$day]) as $u) {
      notify((int) $u['id'], 'reminder', 'Add your photograph', $u['first_name'] . ', your profile still has no photograph. It takes a minute and helps your leaders recognise you.', '#/settings'); $n++;
    }
    return "$n nudged";
  });
  if ($r !== null) $done['photo_nudge'] = $r;
  // 3. Morning summary to HQ, only when there is something to report.
  $r = cron_once('hq_digest', function () {
    $y = gmdate('Y-m-d', time() - 86400);
    $c = row("SELECT COUNT(*) n, SUM(network='WGMN') g, SUM(network='WNNN') w FROM users WHERE DATE(created_at)=?", [$y]);
    if ((int) $c['n'] === 0) return 'nothing new';
    notify_hq('digest', $c['n'] . ' new ' . ((int) $c['n'] === 1 ? 'member' : 'members') . ' joined yesterday',
      (int) $c['g'] . ' in WGMN and ' . (int) $c['w'] . ' in WNNN. Open the dashboard for the full picture.', '#/');
    return $c['n'] . ' reported';
  });
  if ($r !== null) $done['hq_digest'] = $r;
  // 4. Housekeeping.
  $r = cron_once('cleanup', function () {
    q('DELETE FROM sessions WHERE expires_at<?', [now()]);
    q('DELETE FROM auth_challenges WHERE expires_at<?', [now()]);
    q('DELETE FROM login_attempts WHERE created_at<?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
    q("DELETE FROM push_outbox WHERE status<>'queued' AND created_at<?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
    return 'ok';
  });
  if ($r !== null) $done['cleanup'] = $r;
  return $done;
}
