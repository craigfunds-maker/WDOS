<?php
declare(strict_types=1);
/* v109.37 routes: the sign-in drive and envoy launch pack, and chapter placement by area. Functions: drive_fns.php. */

route('GET', '/hq/signin-drive', function () {
  require_hq(); $who = ($_GET['who'] ?? '') === 'envoys' ? 'envoys' : 'leaders';
  $cc = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($_GET['country'] ?? '')), 0, 2)); $net = (string) ($_GET['network'] ?? '');
  $people = drive_people($who, $cc, $net);
  $since = fn(string $role) => (int) val("SELECT COUNT(*) FROM signin_packs sp JOIN users u ON u.id=sp.user_id WHERE sp.sent_at IS NOT NULL AND u.last_seen_at>=sp.sent_at AND " . ($role === 'envoys' ? "u.role='envoy'" : "u.role='member'"));
  $countries = rows("SELECT DISTINCT u.country_code cc FROM users u WHERE u.status='active' AND u.last_seen_at IS NULL AND u.country_code IS NOT NULL AND (u.role='envoy' OR EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL))");
  return ['who' => $who, 'people' => $people, 'totals' => ['never' => count($people), 'packs_sent' => count(array_filter($people, fn($p) => $p['pack_at'])), 'emailed' => count(array_filter($people, fn($p) => $p['nudges'] > 0)),
      'signed_in_after_pack' => $since($who), 'no_phone' => count(array_filter($people, fn($p) => $p['wa'] === ''))],
    'countries' => array_map(fn($c) => ['code' => $c['cc'], 'name' => country_name($c['cc'])], $countries), 'email_on' => mail_configured()];
});
/* Fresh sign-in details for one person, as a WhatsApp message HQ sends from its own phone. A new temporary password is made here,
 * shown once, and only its hash is kept. Only for someone who has never signed in, unless HQ confirms. */
route('POST', '/hq/signin-drive/(\d+)/pack', function ($id) {
  $me = require_hq(); $id = (int) $id; $b = body();
  $u = row("SELECT * FROM users WHERE id=? AND status='active' AND role IN ('member','envoy')", [$id]); if (!$u) fail(404, 'That person was not found.', 'not_found');
  $who = $u['role'] === 'envoy' ? 'envoys' : 'leaders';
  if ($who === 'leaders' && !current_seat($id)) fail(422, 'She does not hold a leadership seat.', 'no_seat');
  if ($u['last_seen_at'] && empty($b['confirm'])) fail(409, 'She has signed in before. Her current password would stop working. Send anyway?', 'signed_in');
  throttle('pack:' . (int) $me['id'], 300, 3600); throttle_hit('pack:' . (int) $me['id']);
  $temp = temp_password();
  q('UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
  q('DELETE FROM sessions WHERE user_id=?', [$id]);
  q('INSERT INTO signin_packs (user_id,sent_at,sent_by,packs) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE sent_at=VALUES(sent_at), sent_by=VALUES(sent_by), packs=packs+1', [$id, now(), (int) $me['id']]);
  log_activity((int) $me['id'], $id, 'signin_pack', $who === 'envoys' ? 'Envoy launch pack sent on WhatsApp' : 'Sign-in details sent on WhatsApp');
  $text = drive_pack_text($u, $temp, $who); $wa = phone_intl((string) $u['phone'], $u['country_code']);
  header('Cache-Control: no-store');
  return ['ok' => true, 'text' => $text, 'wa_url' => 'https://wa.me/' . $wa . '?text=' . rawurlencode($text), 'has_number' => $wa !== ''];
});

route('GET', '/hq/placement', function () { require_hq(); return placement_groups(); });
/* Open a chapter for one area where enough women wait, and place them in it (and in more chapters if they are more than one holds). */
route('POST', '/hq/placement/open', function () {
  $me = require_hq(); $b = body();
  $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : fail(422, 'Choose the network.', 'network');
  $cc = strtoupper(str_in('country', 2)); $state = str_in('state', 120); $lga = str_in('lga', 120); $cm = str_in('community', 120);
  if (!preg_match('/^[A-Z]{2}$/', $cc) || ($lga === '' && $cm === '')) fail(422, 'A chapter needs a country and an LGA, city or community.', 'area');
  $waiting = (int) val("SELECT COUNT(*) FROM users WHERE status='active' AND chapter_id IS NULL AND network=? AND country_code=? AND COALESCE(state_region,'')=? AND COALESCE(lga,'')=? AND COALESCE(community,'')=? AND participant_class='member' AND role='member'", [$net, $cc, $state, $lga, $cm]);
  if (!$waiting) fail(409, 'Nobody waits for a chapter in this area any more.', 'none');
  $first = chapter_create($net, $cc, $state, $lga, $cm, (int) $me['id']);
  log_activity((int) $me['id'], null, 'chapter_created', (string) val('SELECT name FROM chapters WHERE id=?', [$first]) . ' (from the placement list)');
  $placed = 0; $made = 1;
  foreach (rows("SELECT * FROM users WHERE status='active' AND chapter_id IS NULL AND network=? AND country_code=? AND COALESCE(state_region,'')=? AND COALESCE(lga,'')=? AND COALESCE(community,'')=? AND participant_class='member' AND role='member' ORDER BY id LIMIT 600", [$net, $cc, $state, $lga, $cm]) as $u) {
    $cid = (int) val('SELECT id FROM chapters WHERE network=? AND country_code=? AND state_region=? AND lga=? AND community=? AND status IN ' . CHAPTER_OPEN . ' AND members<COALESCE(target_capacity, ?) ORDER BY seq LIMIT 1', [$net, $cc, $state, $lga, $cm, chapter_cap($cc)]);
    if (!$cid) { $cid = chapter_create($net, $cc, $state, $lga, $cm, (int) $me['id']); $made++; }
    q("INSERT INTO chapter_members (chapter_id,user_id,network,placed_by,reason,started_at) VALUES (?,?,?,?,'Placed by HQ when the chapter opened',?)", [$cid, (int) $u['id'], $net, 'hq:' . (int) $me['id'], now()]);
    q('UPDATE users SET chapter_id=? WHERE id=?', [$cid, (int) $u['id']]); chapter_refresh($cid); $placed++;
    notify((int) $u['id'], 'account', 'You have been placed in a chapter', 'Your chapter is ' . (string) val('SELECT name FROM chapters WHERE id=?', [$cid]) . '. Open it to meet your chapter.', '#/chapter');
  }
  after_response(fn() => push_drain(30));
  return ['ok' => true, 'placed' => $placed, 'chapters' => $made, 'name' => (string) val('SELECT name FROM chapters WHERE id=?', [$first])];
});
