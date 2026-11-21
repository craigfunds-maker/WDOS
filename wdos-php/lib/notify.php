<?php
declare(strict_types=1);
/* One place that files a notification: the in-app row, and a push for every
 * device that member has switched on, both subject to her own preferences. */

const NOTIFY_KINDS = [
  'welcome'      => 'Welcome and account messages',
  'registration' => 'New registrations (HQ)',
  'announcement' => 'Announcements from HQ',
  'inspiration'  => 'Weekly and monthly inspiration (leaders)',
  'report'       => 'Reports, reviews and issues',
  'message'      => 'Messages with WODDI HQ',
  'task'         => 'Tasks',
  'meeting'      => 'Meetings',
  'call'         => 'Calls and missed calls',
  'activation'   => 'Referrals and activation',
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
function notify(int $userId, string $kind, string $title, string $body = '', string $url = '#/notifications', array $extra = []): ?int {   // $extra: e.g. ['image' => url] for the phone notification picture
  $p = notify_prefs($userId)[$kind] ?? ['in_app' => true, 'push' => true];
  // in her own language where WDOS has the message in it
  try { if (!in_array($kind, ['account', 'message', 'welcome'], true) && val('SELECT paused_at FROM users WHERE id=?', [$userId])) return null; } catch (PDOException $e) { /* before the pause column exists */ }
  try { $lang = (string) val('SELECT lang FROM users WHERE id=?', [$userId]); if (($tr = note_in($lang !== '' ? $lang : 'en', $title, $body))) [$title, $body] = $tr; } catch (PDOException $e) { /* before the language column exists */ }
  $id = null;
  if ($p['in_app'] || $kind === 'account') {
    $id = ins('INSERT INTO notifications (user_id,kind,title,body,url,created_at) VALUES (?,?,?,?,?,?)',
      [$userId, $kind, mb_substr($title, 0, 160), mb_substr($body, 0, 600), mb_substr($url, 0, 250), now()]);
  }
  if ($p['in_app'] || $p['push']) mail_twin_note('u.id=?', [$userId], $kind, $title, $body, $url);   // v109.44
  if ($p['push']) {
    $payload = json_encode(array_merge(['title' => mb_substr($title, 0, 110), 'body' => mb_substr($body, 0, 240), 'url' => $url, 'kind' => $kind, 'id' => $id], array_intersect_key($extra, ['image' => 1])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    q('INSERT INTO push_outbox (subscription_id,payload,created_at) SELECT id, ?, ? FROM push_subscriptions WHERE user_id=?', [$payload, now(), $userId]);
  }
  return $id;
}
/** File the same notification for many members with two statements, not thousands. */
function notify_many(string $whereSql, array $params, string $kind, string $title, string $body, string $url, ?array $texts = null, bool $inner = false): int {
  // $texts: the same message in other languages, [lang => [title, body]]. Each member gets the one in her language; the rest get the main one.
  $top = !$inner;     // v109.44: the email twin is noted once, by the outer call
  if ($texts === null) { $texts = []; foreach (NOTE_LANGS as $l) if ($tr = note_in($l, $title, $body)) $texts[$l] = $tr; if ($en = note_in('en', $title, $body)) [$title, $body] = $en; }
  if ($texts) {
    if ($top) mail_twin_note($whereSql, $params, $kind, $title, $body, $url);
    $n = 0; $langs = array_keys($texts);
    foreach ($texts as $l => [$lt, $lb]) $n += notify_many("($whereSql) AND u.lang=?", array_merge($params, [$l]), $kind, $lt, $lb, $url, [], true);
    return $n + notify_many("($whereSql) AND u.lang NOT IN (" . implode(',', array_fill(0, count($langs), '?')) . ')', array_merge($params, $langs), $kind, $title, $body, $url, [], true);
  }
  $t = mb_substr($title, 0, 160); $b = mb_substr($body, 0, 600); $n = now();
  if ($top) mail_twin_note($whereSql, $params, $kind, $title, $body, $url);   // v109.44: once, for the top call
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
  if (strpos($html, 'data-woddi-logo') === false) $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#231019">' . mail_logo() . $html . '</div>';   // v109.39
  $ch = curl_init((string) cfg('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email'));
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['api-key: ' . cfg('BREVO_API_KEY'), 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['sender' => ['email' => cfg('MAIL_FROM'), 'name' => cfg('MAIL_FROM_NAME', 'WODDI HQ')],
      'to' => [['email' => $to, 'name' => $toName ?: $to]], 'subject' => $subject, 'htmlContent' => $html])]);
  $resp = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
  $ok = $code >= 200 && $code < 300;
  // v109.35: remember why it failed (Brevo's own words, never the key), so HQ sees the reason on the Email delivery page
  $GLOBALS['wdos_mail_err'] = $ok ? '' : mb_substr($code ? 'HTTP ' . $code . ': ' . ((json_decode($resp, true)['message'] ?? '') ?: mb_substr(strip_tags($resp), 0, 200)) : 'No answer from Brevo: ' . $cerr, 0, 300);
  return $ok;
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
    $n = 0; $push = 0; $mail = 0;
    foreach (rows("SELECT id, first_name, last_name, email, lang FROM users WHERE status='active' AND dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d')=?", [hq_today()->format('m-d')]) as $u) {
      if (function_exists('as_push_count')) $push += as_push_count('u.id=?', [(int) $u['id']], 'birthday');
      if (function_exists('bday_greet')) { $g = bday_greet($u); $mail += $g['mail']; }   // v109.44: her card with her photo and name, by app, phone and email
      else { notify((int) $u['id'], 'birthday', 'Happy birthday, ' . $u['first_name'], 'Everyone at WODDI HQ is celebrating you today. Thank you for all you give to your network.', '#/'); if (function_exists('birthday_mail')) $mail += birthday_mail($u); }
      $n++;
    }
    if (function_exists('as_log') && $n) as_log('birthday', 'Birthday greetings, ' . gmdate('j M Y'), $n, $push, $mail);
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

/* ---- e-mail queue: mail is written here and sent a few at a time after replies, so no page waits for it ---- */
/* v109.39: every email carries the WODDI logo at the top (Azeez, 20 Nov 2026). mail_wrap puts it in; send_mail adds it to any email
 * built another way, so nothing goes out without it. The image is served from the app, so it shows once the reader allows images. */
function mail_logo(): string {
  return '<div data-woddi-logo="1" style="text-align:center;padding:6px 0 14px"><img src="' . htmlspecialchars(app_origin() . '/assets/logos/woddi-email.png') . '" width="185" height="120" alt="WODDI, the nurturer" style="display:inline-block;width:185px;height:120px;border:0;outline:none"></div>';
}
function mail_wrap(string $heading, string $bodyHtml, string $buttonText = '', string $buttonUrl = ''): string {
  $btn = $buttonText !== '' ? '<p style="margin:24px 0"><a href="' . htmlspecialchars($buttonUrl) . '" style="background:#D4006A;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block">' . htmlspecialchars($buttonText) . '</a></p>' : '';
  return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#231019">' . mail_logo() . '<div style="border-top:6px solid #D4006A;border-bottom:3px solid #7CB518;padding:22px 4px"><h2 style="margin:0 0 14px;font-size:20px">' . htmlspecialchars($heading) . '</h2>' . $bodyHtml . $btn
    . '<p style="color:#7a6a72;font-size:12.5px;margin-top:26px">WODDI Digital Operating System. You receive this because you hold a WDOS account.</p></div></div>';
}
function mail_queue(string $to, string $name, string $subject, string $html): bool {
  if (!mail_configured() || !filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/\.(invalid|test|example|local)$/i', $to)) return false;
  q('INSERT INTO mail_outbox (to_email,to_name,subject,html,created_at) VALUES (?,?,?,?,?)', [$to, mb_substr($name, 0, 160), mb_substr($subject, 0, 190), $html, now()]);
  $GLOBALS['wdos_mailed'][strtolower($to)] = true;      // v109.44: an email twin is not sent to someone already emailed in this request
  return true;
}
function mail_drain(int $limit = 12): array {
  $sent = 0; $failed = 0;
  try {
    if (!mail_configured()) return ['sent' => 0, 'failed' => 0, 'configured' => false];
    foreach (rows("SELECT * FROM mail_outbox WHERE status='queued' ORDER BY id LIMIT " . max(1, min(40, $limit))) as $m) {
      if (q("UPDATE mail_outbox SET status='sending', attempts=attempts+1 WHERE id=? AND status='queued'", [(int) $m['id']])->rowCount() === 0) continue;
      if (send_mail($m['to_email'], $m['to_name'], $m['subject'], $m['html'])) { q("UPDATE mail_outbox SET status='sent', sent_at=?, html='' WHERE id=?", [now(), (int) $m['id']]); $sent++; }
      else {
        $err = (string) ($GLOBALS['wdos_mail_err'] ?? '');
        try { q('UPDATE mail_outbox SET status=?, last_error=? WHERE id=?', [(int) $m['attempts'] + 1 < 3 ? 'queued' : 'failed', $err, (int) $m['id']]); }
        catch (PDOException $e) { q('UPDATE mail_outbox SET status=? WHERE id=?', [(int) $m['attempts'] + 1 < 3 ? 'queued' : 'failed', (int) $m['id']]); }   // before migration 044
        $failed++; if ($failed >= 3) break;
      }
    }
  } catch (PDOException $e) { /* table arrives with the next migration run */ }
  return ['sent' => $sent, 'failed' => $failed, 'configured' => true];
}
function app_url(): string { return rtrim((string) cfg('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'wdos.onrender.com')), '/'); }


/* ---- v109.44: email twins ----------------------------------------------------------------------------------------------
 * Azeez, 21 Nov 2026: volunteer leaders and envoys "should also get email reminder for pending task, announcement, meeting,
 * voice call, due report", by email as well as push and the phone's notification bar. Every notification of those kinds to a
 * volunteer leader or a WODDI Envoy now also goes by email (a missed call, not a ringing one). Where WDOS already emailed her in
 * the same request (task and meeting reminders already do), she does not get a second one. Sent at the end of the request, in her
 * language when WDOS has the wording, with the WODDI logo. Ordinary members are not emailed for these, to keep the Brevo allowance. */
const MAIL_TWIN_KINDS = ['task', 'announcement', 'meeting', 'call', 'report', 'reminder'];
function mail_twin_note(string $where, array $params, string $kind, string $title, string $body, string $url): void {
  if (!in_array($kind, MAIL_TWIN_KINDS, true)) return;
  if ($kind === 'call' && !preg_match('/^Missed/i', $title)) return;       // a ringing call is answered in the app; a missed one is emailed
  if (empty($GLOBALS['wdos_twins'])) { $GLOBALS['wdos_twins'] = []; register_shutdown_function('mail_twin_flush'); }
  $GLOBALS['wdos_twins'][] = [$where, $params, $kind, $title, $body, $url];
}
function mail_twin_flush(): void {
  $list = $GLOBALS['wdos_twins'] ?? []; $GLOBALS['wdos_twins'] = []; if (!$list) return;
  try {
    if (!mail_configured()) return;
    $done = []; $n = 0;
    foreach ($list as [$where, $params, $kind, $title, $body, $url]) {
      $people = rows("SELECT u.id, u.email, u.first_name, u.lang FROM users u WHERE u.status='active' AND ($where)
        AND (u.role='envoy' OR EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL))
        AND NOT EXISTS (SELECT 1 FROM notification_prefs np WHERE np.user_id=u.id AND np.kind=? AND np.in_app=0 AND np.push=0) LIMIT 3000", array_merge($params, [$kind]));
      foreach ($people as $p) {
        $key = (int) $p['id'] . '|' . $kind . '|' . $title; $addr = strtolower((string) $p['email']);
        if (isset($done[$key]) || isset($GLOBALS['wdos_mailed'][$addr])) continue; $done[$key] = 1;
        $lang = (string) ($p['lang'] ?: 'en');
        [$tt, $bb] = ($lang !== 'en' && ($tr = note_in($lang, $title, $body))) ? $tr : [$title, $body];
        $link = app_url() . '/' . ltrim($url !== '' ? $url : '#/notifications', '/');
        if (mail_queue((string) $p['email'], (string) $p['first_name'], 'WDOS: ' . mb_substr($tt, 0, 150),
          mail_wrap($tt, '<p>' . htmlspecialchars('Dear ' . $p['first_name'] . ',') . '</p>' . ($bb !== '' ? '<p>' . nl2br(htmlspecialchars($bb)) . '</p>' : ''), 'Open in WDOS', $link))) $n++;
        unset($GLOBALS['wdos_mailed'][$addr]);    // one twin per notification, not one per request
      }
    }
    if ($n && function_exists('mail_drain')) mail_drain(min(12, $n));
  } catch (Throwable $e) { error_log('[wdos mail twins] ' . $e->getMessage()); }
}
