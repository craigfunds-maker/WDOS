<?php
declare(strict_types=1);
/* v109.22: birthdays and inspiration reach every member by every channel, and HQ can see what went out.
 * Azeez, 19 Nov 2026: "capture their birthday greetings, daily inspiration messages, weekly messages, all the automated
 * inspiration messages; it is not showing on the existing leaders dashboard ... capture their birthday at the point of
 * entry ... send a push notification as well as their emails; also show it in the admin dashboard."
 *  - Birthday: on the day, in the app, by phone notification and by email; the dashboard card says happy birthday.
 *    Members who joined without a date of birth (older imports) are asked for it on their dashboard.
 *  - Daily: a "thought for today" for every member from 07:00 Lagos time, in the app and by phone notification, drawn from
 *    the Founder-approved weekly and monthly texts of her network (or a daily message HQ writes for that date).
 *    Not by email: one email a day to every member would use up the email plan in days.
 *  - Weekly (Mondays) and monthly (first Wednesday): to volunteer leaders, now also by email; the week's message stays on
 *    the dashboard all week, not only on the Monday.
 *  - Every send is written to auto_sends with how many people it reached by each channel. */

function as_log(string $kind, string $label, int $inApp, int $push, int $email): void {
  try { q('INSERT INTO auto_sends (kind,label,in_app,push,email,created_at) VALUES (?,?,?,?,?,?)', [$kind, mb_substr($label, 0, 190), $inApp, $push, $email, now()]); }
  catch (PDOException $e) { /* before migration 037 */ }
}
/** Phones that will get the notification: active members in the group who have notifications on and have not switched this kind off. */
function as_push_count(string $where, array $params, string $kind): int {
  try { return (int) val("SELECT COUNT(DISTINCT ps.user_id) FROM push_subscriptions ps JOIN users u ON u.id=ps.user_id
                          WHERE u.status='active' AND ($where) AND NOT EXISTS (SELECT 1 FROM notification_prefs np WHERE np.user_id=u.id AND np.kind=? AND np.push=0)", array_merge($params, [$kind])); }
  catch (PDOException $e) { try { return (int) val("SELECT COUNT(DISTINCT ps.user_id) FROM push_subscriptions ps JOIN users u ON u.id=ps.user_id WHERE u.status='active' AND ($where)", $params); } catch (PDOException $e2) { return 0; } }
}

/* ---- the daily thought ---------------------------------------------------- */
/** Today's thought for a network: HQ's own daily message for this date if there is one, otherwise the next one in
 *  turn from the approved weekly and monthly texts of that network. Same for everyone in the network on the same day. */
function insp_daily_for(?string $network, ?DateTimeImmutable $d = null): ?array {
  $d = $d ?: hq_today(); $net = in_array($network, ['WGMN', 'WNNN'], true) ? $network : 'WGMN';
  $doy = (int) $d->format('z') + 1;
  $own = row("SELECT * FROM hq_messages WHERE kind='daily' AND seq=? AND network IN (?, 'ALL') ORDER BY (network=?) DESC LIMIT 1", [$doy, $net, $net]);
  if ($own) return $own;
  $pool = rows("SELECT * FROM hq_messages WHERE key_thought<>'' AND ((kind='weekly' AND network=?) OR kind='monthly') ORDER BY kind DESC, seq, id", [$net]);
  if (!$pool) return null;
  $days = (int) floor($d->setTime(0, 0)->getTimestamp() / 86400);
  return $pool[$days % count($pool)];
}

/** Everything for her dashboard today: the birthday, weekly or monthly card first, and the thought for today with it. */
function insp_cards(array $u, string $lang): array {
  $first = insp_today($u, $lang); $out = $first ? [$first] : [];
  if ($first && ($first['kind'] ?? '') !== 'daily' && ($m = insp_daily_for(current_seat((int) $u['id'])['network'] ?? ($u['network'] ?? null)))) {
    $c = insp_shape($m, $u, 'Thought for today', current_seat((int) $u['id']), $lang); $c['kind'] = 'daily';
    if ((int) $c['id'] !== (int) ($first['id'] ?? -1)) $out[] = $c;
  }
  return $out;
}

/* ---- sends ------------------------------------------------------------------ */
function run_celebrations(array &$done): void {
  $d = hq_today();
  if ((int) $d->format('G') < 7) return;
  $date = $d->format('Y-m-d');
  // the daily thought, to every active member of each network
  if (claim_send('sent_daily_' . $date)) {
    $inApp = 0; $push = 0;
    foreach (['WGMN', 'WNNN'] as $net) {
      $m = insp_daily_for($net, $d); if (!$m) continue;
      $where = 'u.network=?';
      if ((int) $d->format('N') === 1) $where .= ' AND NOT EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)';   // leaders get the weekly message on Mondays
      $title = 'Thought for today: ' . $m['headline'];
      $push += as_push_count($where, [$net], 'inspiration');
      $inApp += notify_many($where, [$net], 'inspiration', $title, (string) $m['key_thought'], '#/');
    }
    as_log('daily', 'Thought for today, ' . $d->format('j M Y'), $inApp, $push, 0);
    $done['daily_inspiration'] = "$inApp members";
  }
}
/** Birthday emails, sent with the in-app and phone greetings (see run_automations in notify.php). */
function birthday_mail(array $u): int {
  if (!mail_configured() || !str_contains((string) $u['email'], '@')) return 0;
  return mail_many("u.id=? AND u.status='active'", [(int) $u['id']], 'Happy birthday, ' . $u['first_name'], 'Everyone at WODDI HQ is celebrating you today. Thank you for all you give to your network.', '#/');
}

/* ---- member: her date of birth, if WDOS does not have it yet --------------- */
route('POST', '/me/birthday', function () {
  $u = require_user();
  if (!empty($u['dob'])) fail(409, 'Your date of birth is already saved. To correct it, write to WODDI HQ from Help.', 'dob_set');
  $dob = str_in('dob', 10); $dt = DateTime::createFromFormat('!Y-m-d', $dob);
  if (!$dt || $dt->format('Y-m-d') !== $dob) fail(422, 'Choose your date of birth.', 'dob');
  $age = age_on($dob);
  if ($age < 18 || $age > 110) fail(422, 'Check the year of your date of birth.', 'dob');
  if (($u['network'] ?? '') === 'WNNN' && $age >= 30 && !is_hq($u)) { /* allowed: the move to WGMN at 30 is handled by HQ (D08) */ }
  q('UPDATE users SET dob=? WHERE id=? AND dob IS NULL', [$dob, (int) $u['id']]);
  return ['ok' => true, 'user' => public_user(row('SELECT * FROM users WHERE id=?', [(int) $u['id']]))];
});

/* ---- HQ: birthdays and what went out -------------------------------------- */
route('GET', '/hq/celebrations', function () {
  require_hq(); $d = hq_today(); $md = $d->format('m-d');
  $who = fn(array $r) => ['id' => (int) $r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'network' => $r['network'], 'country' => $r['country_code'],
    'leader' => (bool) $r['leader'], 'email' => str_contains((string) $r['email'], '@') && !preg_match('/\.(invalid|test|local|example)$/', (string) $r['email']), 'phone_alerts' => (bool) $r['push']];
  $sel = "SELECT u.id, u.first_name, u.last_name, u.network, u.country_code, u.email, u.dob,
                 EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL) leader,
                 EXISTS (SELECT 1 FROM push_subscriptions p WHERE p.user_id=u.id) push FROM users u WHERE u.status='active'";
  $today = array_map($who, rows("$sel AND u.dob IS NOT NULL AND DATE_FORMAT(u.dob,'%m-%d')=? ORDER BY u.first_name LIMIT 200", [$md]));
  $next = [];
  for ($i = 1; $i <= 7; $i++) { $day = $d->modify("+$i day");
    foreach (rows("$sel AND u.dob IS NOT NULL AND DATE_FORMAT(u.dob,'%m-%d')=? ORDER BY u.first_name LIMIT 50", [$day->format('m-d')]) as $r) $next[] = $who($r) + ['on' => $day->format('Y-m-d')]; }
  $missing = rows("SELECT u.network, COUNT(*) n, SUM(EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)) leaders FROM users u
                   WHERE u.status='active' AND u.dob IS NULL AND u.role NOT IN ('hq_admin','super_admin') GROUP BY u.network");
  $sends = []; try { $sends = rows('SELECT kind, label, in_app, push, email, created_at FROM auto_sends ORDER BY id DESC LIMIT 30'); } catch (PDOException $e) { }
  $card = insp_daily_for('WGMN', $d); $cardN = insp_daily_for('WNNN', $d);
  return ['today' => $today, 'next' => $next,
    'missing' => array_map(fn($r) => ['network' => $r['network'] ?: 'none', 'members' => (int) $r['n'], 'leaders' => (int) $r['leaders']], $missing),
    'sends' => array_map(fn($r) => ['kind' => $r['kind'], 'label' => $r['label'], 'in_app' => (int) $r['in_app'], 'push' => (int) $r['push'], 'email' => (int) $r['email'], 'at' => $r['created_at']], $sends),
    'daily' => ['WGMN' => $card ? $card['headline'] : null, 'WNNN' => $cardN ? $cardN['headline'] : null], 'mail' => mail_configured()];
});
route('GET', '/hq/celebrations/missing', function () {
  require_hq();
  return ['members' => array_map(fn($r) => ['id' => (int) $r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'network' => $r['network'], 'country' => $r['country_code'], 'member_no' => $r['member_no'], 'leader' => (bool) $r['leader']],
    rows("SELECT u.id, u.first_name, u.last_name, u.network, u.country_code, u.member_no, EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL) leader
          FROM users u WHERE u.status='active' AND u.dob IS NULL AND u.role NOT IN ('hq_admin','super_admin') ORDER BY leader DESC, u.first_name LIMIT 500"))];
});
