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
  $wk = function_exists('insp_week') ? insp_week($d) : 0;
  $pool = rows("SELECT * FROM hq_messages WHERE key_thought<>'' AND ((kind='weekly' AND network=? AND seq<>?) OR kind='monthly') ORDER BY kind DESC, seq, id", [$net, $wk]);
  if (!$pool) return null;
  $days = (int) floor($d->setTime(0, 0)->getTimestamp() / 86400);
  return $pool[$days % count($pool)];
}

/** v109.23: one card at a time (two cards showed the same message, 19 Nov). The birthday, the week's message on its Monday,
 *  the monthly message for its three days, and on every other day the thought for today. */
function insp_cards(array $u, string $lang): array {
  $c = insp_today($u, $lang); return $c ? [$c] : [];
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
    // by email: one email a week with the week's seven thoughts (Monday), or one a day if the plan allows it
    $mail = 0; $daily = (bool) cfg('INSPIRATION_EMAIL_DAILY');
    if ($daily || (int) $d->format('N') === 1) foreach (['WGMN', 'WNNN'] as $net) {
      $t = insp_digest($net, $d, $daily ? 1 : 7); if (!$t) continue;
      [[$sj, $bd], $other] = insp_mail_texts($t);
      $w = 'u.network=?';
      // leaders who got this week's message by email have the week's thoughts in that email
      if (!$daily && setting('sent_weekly_' . insp_week($d)) !== '') $w .= ' AND NOT EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)';
      $mail += mail_many("u.status='active' AND " . $w, [$net], $sj, $bd, '#/', $other);
    }
    as_log('daily', 'Thought for today, ' . $d->format('j M Y'), $inApp, $push, $mail);
    $done['daily_inspiration'] = "$inApp members";
  }
  run_dob_asks($done);
}
/* ---- the thoughts by email ----------------------------------------------- */
/** The seven thoughts of the week starting $monday, in one email: [lang => [subject, body]]. The plan allows about 5,000
 *  emails a month, so a daily email to every member (about 20,000) cannot go; one email a week (about 2,900) can.
 *  INSPIRATION_EMAIL_DAILY=1 in config.local.php switches to one email a day, for a bigger email plan. */
function insp_digest(string $net, DateTimeImmutable $monday, int $days = 7): array {
  $subj = ['en' => 'Your thoughts for this week', 'fr' => 'Vos pensées de la semaine', 'pt' => 'Os seus pensamentos desta semana', 'sw' => 'Mawazo yako ya wiki hii', 'ar' => 'أفكارك لهذا الأسبوع'];
  $intro = ['en' => 'A thought for each day this week, from WODDI HQ. Read one each morning.', 'fr' => 'Une pensée pour chaque jour de la semaine, de la part de WODDI HQ. Lisez-en une chaque matin.',
            'pt' => 'Um pensamento para cada dia desta semana, da WODDI HQ. Leia um cada manhã.', 'sw' => 'Wazo kwa kila siku ya wiki hii, kutoka WODDI HQ. Soma moja kila asubuhi.', 'ar' => 'فكرة لكل يوم من هذا الأسبوع، من WODDI HQ. اقرئي واحدة كل صباح.'];
  $fmt = ['en' => 'en_GB', 'fr' => 'fr_FR', 'pt' => 'pt_PT', 'sw' => 'sw_KE', 'ar' => 'ar'];
  $out = [];
  foreach ($subj as $l => $sj) {
    $lines = [];
    for ($i = 0; $i < $days; $i++) {
      $d = $monday->modify("+$i day"); $m = insp_daily_for($net, $d); if (!$m) continue;
      $t = insp_text((int) $m['id'], $l);
      $day = class_exists('IntlDateFormatter') ? (new IntlDateFormatter($fmt[$l], IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Africa/Lagos', null, 'EEEE d MMMM'))->format($d) : $d->format('l j F');
      $lines[] = mb_strtoupper(mb_substr($day, 0, 1)) . mb_substr($day, 1) . "\n" . ($t && $t['headline'] !== '' ? $t['headline'] : $m['headline']) . "\n" . ($t && $t['key_thought'] !== '' ? $t['key_thought'] : $m['key_thought']);
    }
    $today = ['en' => 'Thought for today', 'fr' => 'Pensée du jour', 'pt' => 'Pensamento do dia', 'sw' => 'Wazo la leo', 'ar' => 'فكرة اليوم'][$l];
    if ($lines) $out[$l] = $days === 1 ? [$today . ': ' . explode("\n", $lines[0])[1], implode("\n", array_slice(explode("\n", $lines[0]), 1))] : [$sj, $intro[$l] . "\n\n" . implode("\n\n", $lines)];
  }
  return $out;
}
function insp_mail_texts(array $t): array { $en = $t['en'] ?? ['', '']; unset($t['en']); return [$en, $t]; }

/** Members with no date of birth: asked by email, in the app and by phone, at most twice, 14 days apart, up to 200 a day. */
function run_dob_asks(array &$done): void {
  $d = hq_today(); if ((int) $d->format('G') < 9 || !claim_send('dob_ask_' . $d->format('Y-m-d'))) return;
  try {
    $ids = array_map('intval', array_column(rows("SELECT u.id FROM users u LEFT JOIN dob_asks a ON a.user_id=u.id
      WHERE u.status='active' AND u.dob IS NULL AND u.role NOT IN ('hq_admin','super_admin')
        AND (a.user_id IS NULL OR (a.asks < 2 AND a.last_at < ?)) ORDER BY u.id LIMIT 200", [gmdate('Y-m-d H:i:s', $d->getTimestamp() - 14 * 86400)]), 'id'));
  } catch (PDOException $e) { return; }      // before migration 038
  if (!$ids) return;
  $where = 'u.id IN (' . implode(',', $ids) . ')';
  $title = 'When is your birthday?';
  $body = 'Add your date of birth in WDOS so WODDI can celebrate you on your day, in the app, on your phone and by email. Open WDOS, and the card on your dashboard asks for it. It takes a few seconds.';
  $tx = ['fr' => ['Quand est votre anniversaire ?', "Ajoutez votre date de naissance dans WDOS pour que WODDI vous fête le jour venu, dans l'application, sur votre téléphone et par e-mail. Ouvrez WDOS : la carte de votre tableau de bord vous la demande. Cela prend quelques secondes."],
         'pt' => ['Quando é o seu aniversário?', 'Adicione a sua data de nascimento no WDOS para que a WODDI a celebre no seu dia, na aplicação, no telemóvel e por e-mail. Abra o WDOS: o cartão do seu painel pede-a. Leva poucos segundos.'],
         'sw' => ['Siku yako ya kuzaliwa ni lini?', 'Ongeza tarehe yako ya kuzaliwa kwenye WDOS ili WODDI ikusherehekee siku yako, kwenye programu, kwenye simu na kwa barua pepe. Fungua WDOS, kadi kwenye dashibodi yako itaiomba. Inachukua sekunde chache.'],
         'ar' => ['متى عيد ميلادك؟', 'أضيفي تاريخ ميلادك في WDOS حتى تحتفل بك WODDI في يومك، في التطبيق وعلى هاتفك وبالبريد الإلكتروني. افتحي WDOS وستطلبه منك البطاقة في لوحتك. يستغرق ذلك ثوانٍ.']];
  $push = as_push_count($where, [], 'birthday');
  $inApp = notify_many($where, [], 'birthday', $title, $body, '#/', $tx);
  $mail = mail_many("u.status='active' AND " . $where, [], $title, $body, '#/', $tx);
  foreach ($ids as $id) q('INSERT INTO dob_asks (user_id,asks,last_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE asks=asks+1, last_at=VALUES(last_at)', [$id, gmdate('Y-m-d H:i:s', $d->getTimestamp())]);
  as_log('dob_ask', 'Asked for a date of birth', $inApp, $push, $mail);
  $done['dob_asks'] = count($ids) . ' members';
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
