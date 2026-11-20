<?php
declare(strict_types=1);
/* v109.37 (Azeez, 20 Nov 2026: "do 1 2 3 4 5 6 in a go"):
 *  1. Sign-in drive: volunteer leaders who have never signed in, each with a WhatsApp button that sends fresh sign-in details,
 *     and a weekly email with a link to choose a password, until she signs in.
 *  2. Envoy launch pack: the same for WODDI State Envoys, with her referral link and the envoy video guide.
 *  4. Chapter placement: who waits for a chapter, by area, with the reason, and "Open a chapter here" where enough women wait.
 *  5. Missing details: members with no country or no LGA, city or community are asked once a fortnight (twice at most).
 *  6. Weekly country report: every Monday each Country Representative or Country Lead gets her country's week by email and in the app. */

/** International dialling codes, for WhatsApp links (wa.me needs the number with its country code and no plus). */
const DIAL = ['NG' => '234', 'GH' => '233', 'KE' => '254', 'UG' => '256', 'TZ' => '255', 'RW' => '250', 'BI' => '257', 'ZA' => '27', 'ZM' => '260', 'ZW' => '263', 'MW' => '265',
  'MZ' => '258', 'BW' => '267', 'NA' => '264', 'LS' => '266', 'SZ' => '268', 'MG' => '261', 'MU' => '230', 'ET' => '251', 'SS' => '211', 'SD' => '249', 'SO' => '252', 'DJ' => '253',
  'ER' => '291', 'EG' => '20', 'LY' => '218', 'TN' => '216', 'DZ' => '213', 'MA' => '212', 'MR' => '222', 'SN' => '221', 'GM' => '220', 'GN' => '224', 'GW' => '245', 'SL' => '232',
  'LR' => '231', 'CI' => '225', 'BF' => '226', 'ML' => '223', 'NE' => '227', 'TD' => '235', 'CM' => '237', 'CF' => '236', 'GA' => '241', 'CG' => '242', 'CD' => '243', 'GQ' => '240',
  'AO' => '244', 'BJ' => '229', 'TG' => '228', 'CV' => '238', 'ST' => '239', 'KM' => '269', 'SC' => '248', 'GB' => '44', 'US' => '1', 'CA' => '1', 'IE' => '353', 'DE' => '49',
  'FR' => '33', 'IT' => '39', 'ES' => '34', 'NL' => '31', 'BE' => '32', 'AE' => '971', 'SA' => '966', 'QA' => '974', 'AU' => '61', 'PT' => '351', 'CH' => '41', 'SE' => '46', 'NO' => '47'];
function phone_intl(string $phone, ?string $cc): string {
  $p = preg_replace('/[^\d+]/', '', $phone);
  if ($p === '') return '';
  if (str_starts_with($p, '+')) return substr($p, 1);
  if (str_starts_with($p, '00')) return substr($p, 2);
  $code = DIAL[strtoupper((string) $cc)] ?? '';
  if ($code !== '' && str_starts_with($p, $code) && strlen($p) > 10) return $p;
  if ($code !== '' && str_starts_with($p, '0')) return $code . substr($p, 1);
  return $code !== '' ? $code . $p : '';
}
function real_email(?string $e): bool { return (bool) filter_var((string) $e, FILTER_VALIDATE_EMAIL) && !preg_match('/\.(invalid|test|local|example)$/i', (string) $e); }

/** The people of the drive: never signed in, active, and either seated volunteer leaders or WODDI Envoys. */
function drive_people(string $who, string $cc = '', string $net = ''): array {
  $w = "u.status='active' AND u.last_seen_at IS NULL"; $p = [];
  if ($who === 'envoys') $w .= " AND u.role='envoy'";
  else $w .= " AND u.role='member' AND EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)";
  if (preg_match('/^[A-Z]{2}$/', $cc)) { $w .= ' AND u.country_code=?'; $p[] = $cc; }
  if (in_array($net, ['WGMN', 'WNNN'], true)) { $w .= ' AND (u.network=? OR EXISTS (SELECT 1 FROM seats s2 WHERE s2.user_id=u.id AND s2.ends_at IS NULL AND s2.network=?))'; $p[] = $net; $p[] = $net; }
  $out = [];
  foreach (rows("SELECT u.*, sp.sent_at pack_at, sp.packs, sp.nudges, sp.nudged_at FROM users u LEFT JOIN signin_packs sp ON sp.user_id=u.id WHERE $w ORDER BY u.country_code, u.state_region, u.first_name LIMIT 1500", $p) as $u) {
    $seat = $who === 'envoys' ? null : current_seat((int) $u['id']);
    $phoneLogin = str_ends_with((string) $u['email'], '@phone.wdos.invalid');
    $out[] = ['id' => (int) $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'has_photo' => (int) $u['has_photo'], 'network' => $seat ? $seat['network'] : (string) $u['network'],
      'position' => $seat ? seat_title($seat) : ($u['designation'] !== '' ? $u['designation'] : 'WODDI Envoy'), 'country' => $u['country_code'], 'state' => $u['state_region'],
      'phone' => (string) $u['phone'], 'wa' => phone_intl((string) $u['phone'], $u['country_code']), 'sign_in_with' => $phoneLogin ? (string) $u['phone'] : (string) $u['email'],
      'email' => real_email($u['email']), 'pack_at' => $u['pack_at'], 'packs' => (int) ($u['packs'] ?? 0), 'nudges' => (int) ($u['nudges'] ?? 0), 'nudged_at' => $u['nudged_at'], 'created_at' => $u['created_at']];
  }
  return $out;
}
/** The WhatsApp message with fresh sign-in details. Plain words, short lines, for a phone screen. */
function drive_pack_text(array $u, string $temp, string $who): string {
  $site = app_origin(); $seat = $who === 'envoys' ? null : current_seat((int) $u['id']);
  $phoneLogin = str_ends_with((string) $u['email'], '@phone.wdos.invalid');
  $lines = ['Hello ' . $u['first_name'] . ',', '',
    $seat ? 'You are ' . seat_title($seat) . ' in WODDI ' . $seat['network'] . '. Your WDOS account is ready.' : 'You are a ' . ($u['designation'] !== '' ? $u['designation'] : 'WODDI Envoy') . '. Your WDOS account is ready.', '',
    'Open: ' . $site . '/#/login', 'Sign in with: ' . ($phoneLogin ? $u['phone'] : $u['email']), 'Temporary password: ' . $temp, 'You can change it in Settings after you sign in.', ''];
  if ($who === 'envoys') {
    $code = val("SELECT l.code FROM envoys e JOIN referral_links l ON l.id=e.link_id WHERE e.user_id=?", [(int) $u['id']]);
    if ($code) $lines[] = 'Your referral link to share: ' . $site . '/#/r/' . $code;
    $lines[] = 'Watch how to use your dashboard (2 minutes): ' . $site . '/assets/video/wdos-envoy-guide-en.mp4';
  } else $lines[] = 'After you sign in, open the menu and choose How to use WDOS for a short guided tour.';
  $lines[] = ''; $lines[] = 'No Woman Is Left Out.'; $lines[] = 'WODDI HQ';
  return implode("\n", $lines);
}

/* ---- the timer: the weekly sign-in email, the missing-detail asks, the Monday country report ---- */
function run_drive_jobs(array &$done): void {
  $d = function_exists('hq_today') ? hq_today() : new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
  try { val('SELECT 1 FROM signin_packs LIMIT 1'); } catch (PDOException $e) { return; }        // before migration 045
  if ((int) $d->format('G') >= 9 && $d->format('N') === '2' && claim_send('signin_nudge_' . $d->format('Y-m-d'))) $done['signin_nudge'] = run_signin_nudges($d);
  if ((int) $d->format('G') >= 10 && claim_send('detail_ask_' . $d->format('Y-m-d'))) $done['detail_ask'] = run_detail_asks($d);
  if ((int) $d->format('G') >= 8 && $d->format('N') === '1' && claim_send('country_week_' . $d->format('Y-m-d'))) $done['country_week'] = run_country_reports($d);
  if ((int) $d->format('G') >= 10 && claim_send('referral_news_' . $d->format('Y-m-d'))) $done['referral_news'] = run_referral_news($d);   // v109.38
}
/** Tuesdays from 09:00: a leader or envoy who has never signed in and has a real email gets a link to choose her password (valid 7 days). At most 4 weeks. */
function run_signin_nudges(DateTimeImmutable $d): string {
  if (!mail_configured()) return 'email off';
  $n = 0;
  foreach (rows("SELECT u.* FROM users u LEFT JOIN signin_packs sp ON sp.user_id=u.id WHERE u.status='active' AND u.last_seen_at IS NULL AND COALESCE(sp.nudges,0)<4 AND (sp.nudged_at IS NULL OR sp.nudged_at<?)
      AND (u.role='envoy' OR (u.role='member' AND EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL))) ORDER BY u.id LIMIT 400", [gmdate('Y-m-d H:i:s', time() - 6 * 86400)]) as $u) {
    if (!real_email($u['email'])) continue;
    $tok = token(32);
    q('INSERT INTO password_resets (token_hash,user_id,expires_at) VALUES (?,?,?)', [hash('sha256', $tok), (int) $u['id'], gmdate('Y-m-d H:i:s', time() + 7 * 86400)]);
    $seat = $u['role'] === 'envoy' ? null : current_seat((int) $u['id']);
    $who = $seat ? seat_title($seat) . ' in WODDI ' . $seat['network'] : ($u['designation'] !== '' ? $u['designation'] : 'a WODDI Envoy');
    $body = '<p>Dear ' . htmlspecialchars($u['first_name']) . ',</p><p>Your WDOS account is ready and waiting for you, as <b>' . htmlspecialchars($who) . '</b>. You have not signed in yet.</p>'
      . '<p>Press the button to choose your own password. The link works for 7 days. Then sign in at ' . htmlspecialchars(app_origin()) . ' with this email address.</p><p>No Woman Is Left Out.</p>';
    mail_queue($u['email'], trim($u['first_name'] . ' ' . $u['last_name']), 'Your WDOS account is waiting for you', mail_wrap('Sign in to WDOS', $body, 'Choose my password', app_origin() . '/#/reset/' . $tok));
    q('INSERT INTO signin_packs (user_id,nudges,nudged_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE nudges=nudges+1, nudged_at=VALUES(nudged_at)', [(int) $u['id'], now()]);
    $n++;
  }
  after_response(fn() => mail_drain(20));
  return "$n emails";
}
/** Daily from 10:00: members whose country, or LGA, city or community, is missing are asked to add it (in the app, on the phone, by email).
 *  Each member at most twice, two weeks apart; 200 a day. Without these WDOS cannot count her in her country or place her in a chapter. */
function run_detail_asks(DateTimeImmutable $d): string {
  $ids = array_map('intval', array_column(rows("SELECT u.id FROM users u LEFT JOIN detail_asks a ON a.user_id=u.id
      WHERE u.status='active' AND u.role='member' AND u.network IN ('WGMN','WNNN')
        AND ((u.country_code IS NULL OR u.country_code='') OR (u.chapter_id IS NULL AND COALESCE(u.lga,'')='' AND COALESCE(u.community,'')=''))
        AND (a.user_id IS NULL OR (a.asks<2 AND a.last_at<?)) ORDER BY u.id LIMIT 200", [gmdate('Y-m-d H:i:s', time() - 14 * 86400)]), 'id'));
  if (!$ids) return '0 members';
  $where = 'u.id IN (' . implode(',', $ids) . ')';
  $title = 'Please complete your WODDI details';
  $body = 'Your country, state and LGA or city are missing in WDOS. Add them in Settings so WODDI can count you in your country and place you in a chapter of women near you. It takes a minute.';
  $tx = ['fr' => ['Merci de compléter vos informations WODDI', "Votre pays, votre État et votre LGA ou ville manquent dans WDOS. Ajoutez-les dans Paramètres pour que WODDI vous compte dans votre pays et vous place dans un chapitre de femmes près de chez vous. Cela prend une minute."],
    'pt' => ['Complete os seus dados WODDI', 'O seu país, estado e LGA ou cidade faltam no WDOS. Adicione-os nas Definições para que a WODDI a conte no seu país e a coloque num capítulo de mulheres perto de si. Leva um minuto.'],
    'sw' => ['Tafadhali kamilisha taarifa zako za WODDI', 'Nchi yako, jimbo lako na LGA au jiji lako havipo kwenye WDOS. Viongeze kwenye Mipangilio ili WODDI ikuhesabu katika nchi yako na kukuweka kwenye tawi la wanawake walio karibu nawe. Inachukua dakika moja.'],
    'ar' => ['يُرجى إكمال بياناتك في WODDI', 'بلدك وولايتك ومنطقتك أو مدينتك غير موجودة في WDOS. أضيفيها في الإعدادات حتى تحتسبك WODDI في بلدك وتضعك في فرع من النساء القريبات منك. يستغرق ذلك دقيقة.']];
  $inApp = notify_many($where, [], 'account', $title, $body, '#/settings', $tx);
  $mail = function_exists('mail_many') ? mail_many("u.status='active' AND " . $where, [], $title, $body, '#/settings', $tx) : 0;
  foreach ($ids as $id) q('INSERT INTO detail_asks (user_id,asks,last_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE asks=asks+1, last_at=VALUES(last_at)', [$id, now()]);
  if (function_exists('as_log')) as_log('detail_ask', 'Asked for missing details', $inApp, 0, $mail);
  after_response(function () { mail_drain(20); push_drain(30); });
  return count($ids) . ' members';
}
/** One country's week, for its Country Representative or Country Lead, in one network. Every figure has a link into WDOS. */
function country_week(string $cc, string $net): array {
  $wk = gmdate('Y-m-d H:i:s', time() - 7 * 86400); $today = function_exists('work_today') ? work_today() : gmdate('Y-m-d');
  $seatW = "s.ends_at IS NULL AND s.network=? AND EXISTS (SELECT 1 FROM org_units o WHERE o.id=s.org_unit_id AND o.country_code=?)";
  $n = fn(string $sql, array $p) => (int) (val($sql, $p) ?? 0);
  return [
    'leaders' => $n("SELECT COUNT(DISTINCT s.user_id) FROM seats s WHERE $seatW", [$net, $cc]),
    'signed_in_week' => $n("SELECT COUNT(DISTINCT s.user_id) FROM seats s JOIN users u ON u.id=s.user_id WHERE $seatW AND u.last_seen_at>=?", [$net, $cc, $wk]),
    'never' => $n("SELECT COUNT(DISTINCT s.user_id) FROM seats s JOIN users u ON u.id=s.user_id WHERE $seatW AND u.last_seen_at IS NULL", [$net, $cc]),
    'overdue' => $n("SELECT COUNT(*) FROM tasks t JOIN seats s ON s.user_id=t.assignee_id WHERE $seatW AND t.status IN ('open','in_progress') AND t.due_on IS NOT NULL AND t.due_on<?", [$net, $cc, $today]),
    'new_members' => $n("SELECT COUNT(*) FROM users WHERE country_code=? AND network=? AND status<>'pending_profile' AND created_at>=?", [$cc, $net, $wk]),
    'members' => $n("SELECT COUNT(*) FROM users WHERE country_code=? AND network=? AND status='active'", [$cc, $net]),
    'candidates' => $n("SELECT COUNT(*) FROM activations a JOIN org_units o ON o.id=a.org_unit_id WHERE o.country_code=? AND a.network=? AND a.status IN ('in_progress','returned','submitted')", [$cc, $net]),
  ];
}
function run_country_reports(DateTimeImmutable $d): string {
  $sent = 0;
  foreach (rows("SELECT s.user_id, s.network, o.country_code cc, u.first_name, u.last_name, u.email FROM seats s JOIN org_units o ON o.id=s.org_unit_id JOIN users u ON u.id=s.user_id
      WHERE s.ends_at IS NULL AND s.position_code IN ('country_rep','deputy_country_rep') AND u.status='active' AND o.country_code IS NOT NULL") as $r) {
    $w = country_week($r['cc'], $r['network']); $country = country_name($r['cc']); $site = app_origin();
    $title = 'Your week in ' . $country . ' (' . $r['network'] . ')';
    $body = $w['signed_in_week'] . ' of ' . $w['leaders'] . ' leaders signed in this week, ' . $w['never'] . ' never yet. ' . $w['overdue'] . ' tasks overdue. ' . $w['new_members'] . ' new members. ' . $w['candidates'] . ' candidates in activation.';
    notify((int) $r['user_id'], 'reminder', $title, $body, '#/team');
    if (real_email($r['email'])) {
      $row = fn(string $label, $v, string $href) => '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . htmlspecialchars($label) . '</td><td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right"><a href="' . $site . $href . '" style="color:#D4006A;font-weight:bold;text-decoration:none">' . htmlspecialchars((string) $v) . '</a></td></tr>';
      $html = '<p>Dear ' . htmlspecialchars($r['first_name']) . ',</p><p>Here is ' . htmlspecialchars($country) . ' in ' . $r['network'] . ' this past week. Each number opens the people behind it in WDOS.</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:15px">' . $row('Volunteer leaders', $w['leaders'], '/#/team') . $row('Signed in this week', $w['signed_in_week'], '/#/team')
        . $row('Never signed in yet', $w['never'], '/#/team') . $row('Tasks overdue', $w['overdue'], '/#/tasks') . $row('New members this week', $w['new_members'], '/#/members')
        . $row('Members in all', $w['members'], '/#/members') . $row('Candidates in activation', $w['candidates'], '/#/team') . '</table><p>No Woman Is Left Out.</p>';
      mail_queue($r['email'], trim($r['first_name'] . ' ' . $r['last_name']), 'WDOS: ' . $title, mail_wrap($title, $html, 'Open my team', $site . '/#/team'));
    }
    $sent++;
  }
  after_response(function () { mail_drain(20); push_drain(30); });
  return "$sent reports";
}

/* ---- 4: who waits for a chapter, by area ---- */
function placement_groups(): array {
  $min = function_exists('policy_int') ? max(1, policy_int('chapter_min_active') ?: 10) : 10;
  $out = [];
  foreach (rows("SELECT u.network, u.country_code cc, COALESCE(u.state_region,'') state, COALESCE(u.lga,'') lga, COALESCE(u.community,'') community, COUNT(*) n
      FROM users u WHERE u.status='active' AND u.chapter_id IS NULL AND u.network IN ('WGMN','WNNN') AND u.participant_class='member' AND u.role='member'
      GROUP BY u.network, u.country_code, u.state_region, u.lga, u.community ORDER BY n DESC LIMIT 200") as $g) {
    $noPlace = !$g['cc'] || ($g['lga'] === '' && $g['community'] === '');
    $room = $noPlace ? 0 : (int) val('SELECT COUNT(*) FROM chapters WHERE network=? AND country_code=? AND state_region=? AND lga=? AND community=? AND status IN ' . CHAPTER_OPEN . ' AND members<COALESCE(target_capacity, ?)',
      [$g['network'], $g['cc'], $g['state'], $g['lga'], $g['community'], chapter_cap($g['cc'])]);
    $reason = $noPlace ? 'no_location' : ($room ? 'placeable' : 'no_chapter');
    $out[] = ['network' => $g['network'], 'country' => $g['cc'], 'country_name' => $g['cc'] ? country_name($g['cc']) : '', 'state' => $g['state'], 'lga' => $g['lga'], 'community' => $g['community'], 'n' => (int) $g['n'],
      'reason' => $reason, 'can_open' => $reason === 'no_chapter' && (int) $g['n'] >= $min];
  }
  return ['groups' => $out, 'min' => $min, 'auto_create' => policy('chapter_auto_create') === 'on'];
}
