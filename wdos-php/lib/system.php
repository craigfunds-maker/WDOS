<?php
declare(strict_types=1);
/* v109.35: System health for HQ (Azeez, 20 Nov 2026: "it shows failed emails and when I tapped on it, it doesn't take me anywhere").
 * The daily self-check notification opens this page. Each item says what is wrong and opens the place to fix it. The email part
 * shows Brevo's own reason for the failures, can test the connection without sending anything, and can send the failed ones again. */

const BREVO_IP_PAGE = 'https://app.brevo.com/security/authorised_ips';
function sys_email(): array {
  $d7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400); $n = fn(string $sql, array $p = []) => (int) (val($sql, $p) ?? 0);
  $out = ['on' => mail_configured(), 'sent_7d' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status='sent' AND created_at>?", [$d7]),
    'failed_7d' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status='failed' AND created_at>?", [$d7]), 'waiting' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status IN ('queued','sending')"),
    'last_sent' => val("SELECT MAX(sent_at) FROM mail_outbox WHERE status='sent'"), 'reasons' => [], 'subjects' => [], 'ip_block' => null];
  $out['subjects'] = rows("SELECT subject, COUNT(*) n, MAX(created_at) last_at FROM mail_outbox WHERE status='failed' AND created_at>? GROUP BY subject ORDER BY n DESC LIMIT 10", [$d7]);
  try { $out['reasons'] = rows("SELECT last_error reason, COUNT(*) n FROM mail_outbox WHERE status IN ('failed','queued') AND last_error<>'' AND created_at>? GROUP BY last_error ORDER BY n DESC LIMIT 4", [$d7]); }
  catch (PDOException $e) { /* before migration 044 */ }
  $test = val("SELECT v FROM app_settings WHERE k='mail_test'"); $out['test'] = $test ? json_decode((string) $test, true) : null;
  foreach (array_merge(array_column($out['reasons'], 'reason'), [$out['test']['message'] ?? '']) as $r)
    if (preg_match('/unrecognised IP address ([0-9a-f.:]+)/i', (string) $r, $m)) { $out['ip_block'] = ['ip' => rtrim($m[1], '.:'), 'fix_url' => BREVO_IP_PAGE]; break; }
  return $out;
}
/** Asks Brevo who we are: checks the key, the server address and the sender. Sends no email. */
function sys_email_test(): array {
  if (!mail_configured()) return ['ok' => false, 'message' => 'Email is switched off: BREVO_API_KEY and MAIL_FROM are not set in config.local.php.', 'at' => now()];
  $get = function (string $path) { $ch = curl_init('https://api.brevo.com/v3' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_HTTPHEADER => ['api-key: ' . cfg('BREVO_API_KEY'), 'Accept: application/json']]);
    $b = (string) curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return [$c, json_decode($b, true) ?: []]; };
  [$c, $b] = $get('/account');
  if ($c === 0) $res = ['ok' => false, 'message' => 'No answer from Brevo. The server could not reach api.brevo.com.'];
  elseif ($c < 200 || $c >= 300) $res = ['ok' => false, 'message' => 'Brevo refused: HTTP ' . $c . ' ' . mb_substr((string) ($b['message'] ?? ''), 0, 260)];
  else {
    [$c2, $s] = $get('/senders'); $from = strtolower((string) cfg('MAIL_FROM')); $active = false;
    foreach (($s['senders'] ?? []) as $x) if (strtolower((string) $x['email']) === $from) $active = !empty($x['active']);
    $credits = null; foreach (($b['plan'] ?? []) as $p) if (($p['creditsType'] ?? '') === 'sendLimit') $credits = (int) $p['credits'];
    $res = $active ? ['ok' => true, 'message' => 'Brevo accepts this server. The sender address is active' . ($credits !== null ? " and $credits emails are left on the plan." : '.')]
                   : ['ok' => false, 'message' => 'Brevo accepts this server, but the sender address in MAIL_FROM is not an active sender in Brevo. Add and verify it under Senders in Brevo.'];
  }
  $res['at'] = now();
  q("INSERT INTO app_settings (k,v) VALUES ('mail_test',?) ON DUPLICATE KEY UPDATE v=VALUES(v)", [json_encode($res)]);
  return $res;
}

route('GET', '/hq/system', function () {
  require_hq(); $n = fn(string $sql, array $p = []) => (int) (val($sql, $p) ?? 0); $d1 = gmdate('Y-m-d H:i:s', time() - 86400);
  $email = sys_email();
  $items = [
    ['key' => 'email', 'label' => 'Emails that failed in the last day', 'n' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status='failed' AND created_at>?", [$d1]), 'href' => '#/hq/system/email'],
    ['key' => 'push', 'label' => 'Phone notifications that failed in the last day', 'n' => $n("SELECT COUNT(*) FROM push_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at>?", [$d1]), 'href' => '#/hq/system/email'],
    ['key' => 'two_chapters', 'label' => 'Members in two chapters at once', 'n' => $n('SELECT COUNT(*) FROM (SELECT user_id FROM chapter_members WHERE ended_at IS NULL GROUP BY user_id HAVING COUNT(*)>1) x'), 'href' => '#/chapters'],
    ['key' => 'cross_net', 'label' => 'Members placed in a chapter of the other network', 'n' => $n('SELECT COUNT(*) FROM chapter_members m JOIN chapters c ON c.id=m.chapter_id JOIN users u ON u.id=m.user_id WHERE m.ended_at IS NULL AND u.network<>c.network'), 'href' => '#/chapters'],
    ['key' => 'over_cap', 'label' => 'Chapters over capacity without an exception', 'n' => $n('SELECT COUNT(*) FROM chapters c WHERE c.capacity_exception=0 AND c.members>COALESCE(c.target_capacity, ?)', [policy_int('chapter_capacity')]), 'href' => '#/chapters'],
    ['key' => 'dup_phone', 'label' => 'Phone numbers used by two accounts (sign-in with that number is ambiguous)', 'n' => $n("SELECT COUNT(*) FROM (SELECT phone FROM users WHERE status='active' AND phone<>'' GROUP BY phone HAVING COUNT(*)>1) x"), 'href' => '#/members?dup=phone'],
    ['key' => 'no_country', 'label' => 'Members with no country', 'n' => $n("SELECT COUNT(*) FROM users WHERE status='active' AND role='member' AND (country_code IS NULL OR country_code='')"), 'href' => '#/members?country=none', 'soft' => true],
    ['key' => 'no_chapter', 'label' => 'Members not placed in a chapter yet', 'n' => $n("SELECT COUNT(*) FROM users u WHERE u.status='active' AND u.role='member' AND u.network IN ('WGMN','WNNN') AND NOT EXISTS (SELECT 1 FROM chapter_members m WHERE m.user_id=u.id AND m.ended_at IS NULL)"), 'href' => '#/members?chapter=none', 'soft' => true],
    ['key' => 'never_signed', 'label' => 'Volunteer leaders who have never signed in', 'n' => $n("SELECT COUNT(DISTINCT s.user_id) FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND u.status='active' AND u.last_seen_at IS NULL"), 'href' => '#/members?kind=leaders&seen=never', 'soft' => true],
    ['key' => 'suspended_seat', 'label' => 'Leader seats held by a suspended account', 'n' => $n("SELECT COUNT(*) FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND u.status IN ('suspended','withdrawn')"), 'href' => '#/structure'],
  ];
  $push = ['sent_7d' => $n("SELECT COUNT(*) FROM push_outbox WHERE status='sent' AND created_at>?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]),
    'failed_7d' => $n("SELECT COUNT(*) FROM push_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at>?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]), 'devices' => $n('SELECT COUNT(*) FROM push_subscriptions')];
  return ['items' => $items, 'email' => $email, 'push' => $push, 'build' => WDOS_BUILD, 'as_of' => now()];
});
route('POST', '/hq/system/email-test', function () { require_hq(); throttle('mailtest', 30, 3600); throttle_hit('mailtest'); return sys_email_test(); });
route('POST', '/hq/system/email-retry', function () {
  $me = require_hq(); throttle('mailretry', 6, 3600); throttle_hit('mailretry');
  $t = sys_email_test();
  if (!$t['ok'] && empty(body()['anyway'])) fail(409, 'Brevo is still refusing: ' . $t['message'] . ' Fix that first, then send them again.', 'brevo');
  $d7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
  try { $n = q("UPDATE mail_outbox SET status='queued', attempts=0, last_error='' WHERE status='failed' AND created_at>? AND html<>''", [$d7])->rowCount(); }
  catch (PDOException $e) { $n = q("UPDATE mail_outbox SET status='queued', attempts=0 WHERE status='failed' AND created_at>? AND html<>''", [$d7])->rowCount(); }
  log_activity((int) $me['id'], null, 'mail_retry', "$n emails queued again");
  after_response(fn() => mail_drain(20));
  return ['ok' => true, 'queued' => $n];
});

/* v109.35: volunteer leaders at a glance on the HQ dashboard: total, by network, level, country and state, every figure a link
 * to the Members list filtered to those leaders (Azeez, 20 Nov 2026). Counts people, not seats: one person with one open seat. */
route('GET', '/hq/leaders-glance', function () {
  require_hq();
  $base = "FROM seats s JOIN users u ON u.id=s.user_id JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id WHERE s.ends_at IS NULL AND u.status='active'";
  $byNet = []; foreach (rows("SELECT s.network, COUNT(DISTINCT s.user_id) n $base GROUP BY s.network") as $r) $byNet[$r['network']] = (int) $r['n'];
  $levels = ['country' => 'Country', 'state_region' => 'State or region', 'lga' => 'LGA or district', 'community_cluster' => 'Ward or cluster', 'chapter' => 'Chapter'];
  $lv = []; foreach (rows("SELECT p.level, s.network, COUNT(DISTINCT s.user_id) n $base GROUP BY p.level, s.network") as $r) $lv[$r['level']][$r['network']] = (int) $r['n'];
  $byLevel = []; foreach ($levels as $k => $l) $byLevel[] = ['level' => $k, 'label' => $l, 'WGMN' => $lv[$k]['WGMN'] ?? 0, 'WNNN' => $lv[$k]['WNNN'] ?? 0];
  $byCountry = rows("SELECT o.country_code code, (SELECT c.name FROM org_units c WHERE c.level='country' AND c.country_code=o.country_code LIMIT 1) name,
      CAST(COUNT(DISTINCT CASE WHEN s.network='WGMN' THEN s.user_id END) AS SIGNED) WGMN, CAST(COUNT(DISTINCT CASE WHEN s.network='WNNN' THEN s.user_id END) AS SIGNED) WNNN, COUNT(DISTINCT s.user_id) total
      $base GROUP BY o.country_code ORDER BY total DESC LIMIT 12");
  $byState = rows("SELECT o.country_code code, o.name state, CAST(COUNT(DISTINCT CASE WHEN s.network='WGMN' THEN s.user_id END) AS SIGNED) WGMN, CAST(COUNT(DISTINCT CASE WHEN s.network='WNNN' THEN s.user_id END) AS SIGNED) WNNN, COUNT(DISTINCT s.user_id) total
      $base AND o.level='state_region' GROUP BY o.country_code, o.name ORDER BY total DESC LIMIT 12");
  $n = fn(string $sql, array $p = []) => (int) (val($sql, $p) ?? 0);
  $extra = ['never_signed_in' => $n("SELECT COUNT(DISTINCT s.user_id) $base AND u.last_seen_at IS NULL"),
    'active_7d' => $n("SELECT COUNT(DISTINCT s.user_id) $base AND u.last_seen_at>=?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]),
    'agreement_pending' => $n("SELECT COUNT(DISTINCT s.user_id) $base AND s.acceptance='pending'"),
    'envoys' => $n("SELECT COUNT(*) FROM users WHERE role='envoy' AND status='active'"),
    'in_activation' => $n("SELECT COUNT(*) FROM activations WHERE status IN ('in_progress','returned')"), 'awaiting_review' => $n("SELECT COUNT(*) FROM activations WHERE status='submitted'")];
  return ['total' => $n("SELECT COUNT(DISTINCT s.user_id) $base"), 'by_network' => ['WGMN' => $byNet['WGMN'] ?? 0, 'WNNN' => $byNet['WNNN'] ?? 0], 'by_level' => $byLevel, 'by_country' => $byCountry, 'by_state' => $byState] + $extra;
});
