<?php
declare(strict_types=1);
/* v109.13: voice calls (Azeez, 18 Nov 2026: "volunteer leaders to make voice call among the leaders in their regions and also call HQ").
 * Who may ring whom is decided here, on the server:
 *  - a volunteer leader rings leaders whose seat is in her own country and network (a leader above country level:
 *    the leaders under her part of the structure), and she can ring WODDI HQ;
 *  - an HQ admin can ring any serving leader (to return a call, for example).
 * Ringing reaches the other person in the open app (it asks every few seconds) and as a phone notification.
 * A call that nobody answers in 45 seconds becomes a missed call and the person is told.
 * v109.14 (Azeez: "voice call shouldn't use Jitsi"): the two browsers talk to each other directly (WebRTC, audio only).
 * WDOS passes only the connection messages (one offer, one answer) between the two people on the call, and deletes
 * them when the call ends. Public STUN servers help the phones find each other; when Cloudflare TURN keys are set
 * (CF_TURN_KEY_ID and CF_TURN_KEY_API_TOKEN in config.local.php) calls also connect behind strict mobile networks. */

const VC_RING_SECONDS = 45;

function vc_can_call(array $u): bool { return is_hq($u) || (bool) work_seat($u); }

/** Serving leaders this person may ring, as SQL over seats sc / users u / positions p / org_units o. */
function vc_reach_sql(array $u): array {
  $base = "sc.ends_at IS NULL AND sc.status NOT IN ('on_leave','suspended') AND u.status='active' AND u.id<>" . (int) $u['id'];
  if (is_hq($u)) return [$base, []];
  $seat = work_seat($u); if (!$seat) return ['1=0', []];
  if (!empty($seat['unit_country'])) return [$base . ' AND o.country_code=? AND sc.network=?', [$seat['unit_country'], $seat['network']]];
  return [$base . ' AND o.path LIKE ? AND sc.network=?', [$seat['unit_path'] . '%', $seat['network']]];
}
function vc_people(array $u, string $q = '', ?int $only = null): array {
  [$w, $p] = vc_reach_sql($u);
  if ($q !== '') { $w .= " AND CONCAT(u.first_name,' ',u.last_name) LIKE ?"; $p[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
  if ($only !== null) { $w .= ' AND u.id=?'; $p[] = $only; }
  $out = [];
  foreach (rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, sc.network, sc.area_label, p.title_wgmn, p.title_wnnn, o.name AS unit_name
                 FROM seats sc JOIN users u ON u.id=sc.user_id JOIN positions p ON p.code=sc.position_code JOIN org_units o ON o.id=sc.org_unit_id
                 WHERE $w ORDER BY p.rank_no, u.first_name, u.last_name LIMIT 400", $p) as $r) {
    $id = (int) $r['id']; if (isset($out[$id])) continue;
    $out[$id] = ['id' => $id, 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'has_photo' => (int) $r['has_photo'] === 1, 'network' => $r['network'],
      'title' => seat_title($r)];
  }
  return array_values($out);
}
function vc_name(int $id): string { $r = row('SELECT first_name, last_name FROM users WHERE id=?', [$id]); return $r ? trim($r['first_name'] . ' ' . $r['last_name']) : ''; }
function vc_role(array $u, array $c): string {
  $me = (int) $u['id'];
  if ((int) $c['caller_id'] === $me) return 'caller';
  if ((int) $c['to_hq'] === 1 && is_hq($u) && ($c['answered_by'] === null || (int) $c['answered_by'] === $me)) return 'callee';
  if ($c['callee_id'] !== null && (int) $c['callee_id'] === $me) return 'callee';
  return '';
}
function vc_out(array $u, array $c): array {
  $role = vc_role($u, $c); $other = $role === 'caller' ? ((int) $c['to_hq'] === 1 ? ((int) ($c['answered_by'] ?? 0) ?: null) : (int) $c['callee_id']) : (int) $c['caller_id'];
  $name = $role === 'caller' && (int) $c['to_hq'] === 1 && !$other ? 'WODDI HQ' : ($other ? vc_name($other) : '');
  $seat = $other ? current_seat($other) : null;
  return ['id' => (int) $c['id'], 'role' => $role, 'status' => $c['status'], 'to_hq' => (int) $c['to_hq'] === 1, 'with_id' => $other, 'with' => $name,
    'with_title' => $seat ? seat_title($seat) : ($other && ($p = row('SELECT role FROM users WHERE id=?', [$other])) && in_array($p['role'], ['hq_admin', 'super_admin'], true) ? 'WODDI HQ' : ''),
    'created_at' => $c['created_at'], 'answered_at' => $c['answered_at'], 'ended_at' => $c['ended_at']];
}
/** The network helpers given to both browsers: STUN always; Cloudflare TURN (relay) when its keys are set. */
function vc_ice(): array {
  $ice = [['urls' => ['stun:stun.cloudflare.com:3478', 'stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302']]];
  $kid = trim((string) cfg('CF_TURN_KEY_ID', '')); $tok = trim((string) cfg('CF_TURN_KEY_API_TOKEN', ''));
  if ($kid !== '' && $tok !== '' && preg_match('/^[A-Za-z0-9]+$/', $kid)) {
    $ch = curl_init('https://rtc.live.cloudflare.com/v1/turn/keys/' . $kid . '/credentials/generate-ice-servers');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_POSTFIELDS => json_encode(['ttl' => 7200]),
      CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tok, 'Content-Type: application/json']]);
    $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $d = $r ? json_decode((string) $r, true) : null;
    if ($code >= 200 && $code < 300 && !empty($d['iceServers'])) $ice = array_merge($ice, isset($d['iceServers'][0]) ? $d['iceServers'] : [$d['iceServers']]);
    else error_log('[wdos calls] TURN credentials not given: HTTP ' . $code);
  }
  return $ice;
}
/** Calls nobody answered become missed calls (the person rung is told once); calls left open for 3 hours are closed. */
function vc_expire(): void {
  $cut = gmdate('Y-m-d H:i:s', time() - VC_RING_SECONDS);
  foreach (rows("SELECT * FROM voice_calls WHERE status='ringing' AND created_at < ? LIMIT 50", [$cut]) as $c) {
    $st = q("UPDATE voice_calls SET status='missed', ended_at=? WHERE id=? AND status='ringing'", [now(), (int) $c['id']]);
    if ($st->rowCount() !== 1) continue;
    $from = vc_name((int) $c['caller_id']);
    if ((int) $c['to_hq'] === 1) notify_hq('call', 'Missed call from ' . $from, 'Open Calls in WDOS to ring back.', '#/calls');
    elseif ($c['callee_id']) notify((int) $c['callee_id'], 'call', 'Missed call from ' . $from, 'Open Calls in WDOS to ring back.', '#/calls');
  }
  q("UPDATE voice_calls SET status='ended', ended_at=? WHERE status='answered' AND answered_at < ?", [now(), gmdate('Y-m-d H:i:s', time() - 3 * 3600)]);
  try { q("DELETE s FROM voice_call_signals s JOIN voice_calls c ON c.id=s.call_id WHERE c.status NOT IN ('ringing','answered')"); } catch (PDOException $e) { /* before migration 036 */ }
}

route('GET', '/calls', function () {
  $u = require_user(); vc_expire(); $me = (int) $u['id']; require_once __DIR__ . '/gcalls.php';
  $w = '(caller_id=? OR callee_id=?' . (is_hq($u) ? ' OR to_hq=1' : '') . ')';
  $recent = array_map(fn($c) => vc_out($u, $c), rows("SELECT * FROM voice_calls WHERE $w ORDER BY id DESC LIMIT 40", [$me, $me]));
  return ['can_call' => vc_can_call($u), 'is_hq' => is_hq($u), 'people' => vc_can_call($u) ? vc_people($u, str_in_get('q')) : [], 'recent' => array_values(array_filter($recent, fn($r) => $r['role'] !== '')),
    'groups' => function_exists('gc_recent') ? gc_recent($u) : [], 'max_group' => defined('GC_MAX') ? (sfu_on() ? gc_big_max() : GC_MAX) : 0, 'relay' => function_exists('sfu_on') && sfu_on()];
});
function str_in_get(string $k): string { return mb_substr(trim((string) ($_GET[$k] ?? '')), 0, 60); }

route('POST', '/calls', function () {
  $u = require_user(); if (!vc_can_call($u)) fail(403, 'Voice calls are for volunteer leaders and WODDI HQ.', 'forbidden');
  throttle('vcall:' . (int) $u['id'], 40, 3600); throttle_hit('vcall:' . (int) $u['id']);
  $to = body()['to'] ?? null; $hq = $to === 'hq'; $callee = $hq ? null : (int) $to;
  if ($hq && is_hq($u)) fail(422, 'You are WODDI HQ. Choose a leader to ring.', 'to');
  if (!$hq) { if ($callee < 1 || !vc_people($u, '', $callee)) fail(403, 'You can ring leaders in your own country and network, and WODDI HQ.', 'to'); }
  // one call at a time: anything this person was still ringing is cancelled
  q("UPDATE voice_calls SET status='cancelled', ended_at=? WHERE caller_id=? AND status='ringing'", [now(), (int) $u['id']]);
  $room = 'WODDI-call-' . bin2hex(random_bytes(10));
  $id = ins('INSERT INTO voice_calls (caller_id,callee_id,to_hq,room,status,created_at) VALUES (?,?,?,?,?,?)', [(int) $u['id'], $callee, $hq ? 1 : 0, $room, 'ringing', now()]);
  $from = trim($u['first_name'] . ' ' . $u['last_name']); $seat = work_seat($u); $who = $seat ? seat_title($seat) : 'WODDI HQ';
  if ($hq) notify_hq('call', 'Voice call from ' . $from, $who, '#/calls/' . $id);
  else notify($callee, 'call', 'Voice call from ' . $from, $who, '#/calls/' . $id);
  log_activity((int) $u['id'], $callee, 'voice_call', $hq ? 'Rang WODDI HQ' : 'Rang ' . vc_name((int) $callee), 'call', (int) $id);
  $c = row('SELECT * FROM voice_calls WHERE id=?', [$id]);
  return ['ok' => true, 'call' => vc_out($u, $c), 'ice' => vc_ice(), 'ring_seconds' => VC_RING_SECONDS];
});

/** Asked every few seconds by the open app: calls ringing for me now. */
route('GET', '/calls/ring', function () {
  $u = require_user(); if (!vc_can_call($u)) return ['calls' => []];
  vc_expire(); $me = (int) $u['id'];
  $w = '(callee_id=?' . (is_hq($u) ? ' OR to_hq=1' : '') . ')';
  $list = rows("SELECT * FROM voice_calls WHERE status='ringing' AND $w AND caller_id<>? AND created_at >= ? ORDER BY id DESC LIMIT 5", [$me, $me, gmdate('Y-m-d H:i:s', time() - VC_RING_SECONDS)]);
  $calls = array_map(fn($c) => vc_out($u, $c), $list);
  require_once __DIR__ . '/gcalls.php';
  if (function_exists('gc_ringing')) $calls = array_merge($calls, gc_ringing($u));      // v109.24: group calls
  return ['calls' => $calls];
});

function vc_mine(array $u, int $id): array {
  $c = row('SELECT * FROM voice_calls WHERE id=?', [$id]);
  if (!$c || vc_role($u, $c) === '') fail(404, 'That call was not found.', 'not_found');
  return $c;
}
route('GET', '/calls/(\d+)', function ($id) { $u = require_user(); vc_expire(); return ['call' => vc_out($u, vc_mine($u, (int) $id))]; });

route('POST', '/calls/(\d+)/answer', function ($id) {
  $u = require_user(); vc_expire(); $c = vc_mine($u, (int) $id);
  if (vc_role($u, $c) !== 'callee') fail(403, 'Only the person being rung can answer.', 'forbidden');
  // the first to answer takes the call (for calls to HQ, the other HQ admins stop ringing)
  $st = q("UPDATE voice_calls SET status='answered', answered_at=?, answered_by=? WHERE id=? AND status='ringing'", [now(), (int) $u['id'], (int) $id]);
  if ($st->rowCount() !== 1) {
    $now = row('SELECT status, answered_by FROM voice_calls WHERE id=?', [(int) $id]);
    fail(409, $now['status'] === 'answered' ? 'Someone else at HQ has answered this call.' : 'This call has ended.', 'gone');
  }
  return ['ok' => true, 'call' => vc_out($u, row('SELECT * FROM voice_calls WHERE id=?', [(int) $id])), 'ice' => vc_ice()];
});
route('POST', '/calls/(\d+)/decline', function ($id) {
  $u = require_user(); $c = vc_mine($u, (int) $id);
  if (vc_role($u, $c) !== 'callee') fail(403, 'Only the person being rung can decline.', 'forbidden');
  q("UPDATE voice_calls SET status='declined', ended_at=? WHERE id=? AND status='ringing'", [now(), (int) $id]);
  return ['ok' => true];
});
route('POST', '/calls/(\d+)/end', function ($id) {
  $u = require_user(); $c = vc_mine($u, (int) $id);
  if ($c['status'] === 'ringing' && vc_role($u, $c) === 'caller') q("UPDATE voice_calls SET status='cancelled', ended_at=? WHERE id=? AND status='ringing'", [now(), (int) $id]);
  elseif ($c['status'] === 'answered') q("UPDATE voice_calls SET status='ended', ended_at=? WHERE id=? AND status='answered'", [now(), (int) $id]);
  return ['ok' => true];
});

/** The connection messages: the caller posts her offer, the person who answered posts the answer.
 *  Each side reads only what the other side posted. Nobody else on WDOS can read or post. */
route('POST', '/calls/(\d+)/signal', function ($id) {
  $u = require_user(); $c = vc_mine($u, (int) $id);
  if (!in_array($c['status'], ['ringing', 'answered'], true)) fail(409, 'This call has ended.', 'gone');
  $kind = (string) (body()['kind'] ?? ''); $data = body()['data'] ?? null; $role = vc_role($u, $c);
  if (!($role === 'caller' && $kind === 'offer') && !($role === 'callee' && $kind === 'answer') && $kind !== 'ice') fail(422, 'That message is not part of a call.', 'kind');
  if ($role === 'callee' && $c['status'] !== 'answered') fail(409, 'Answer the call first.', 'not_answered');
  $json = json_encode($data, JSON_UNESCAPED_SLASHES);
  if ($json === false || strlen($json) > 60000) fail(422, 'That message is too large.', 'data');
  if ((int) val('SELECT COUNT(*) FROM voice_call_signals WHERE call_id=? AND from_id=?', [(int) $id, (int) $u['id']]) >= 60) fail(429, 'Too many messages for one call.', 'throttled');
  $sid = ins('INSERT INTO voice_call_signals (call_id,from_id,kind,payload,created_at) VALUES (?,?,?,?,?)', [(int) $id, (int) $u['id'], $kind, $json, now()]);
  return ['ok' => true, 'id' => $sid];
});
route('GET', '/calls/(\d+)/signal', function ($id) {
  $u = require_user(); $c = vc_mine($u, (int) $id); $after = max(0, (int) ($_GET['after'] ?? 0));
  $role = vc_role($u, $c);
  $other = $role === 'caller' ? (int) ($c['answered_by'] ?? $c['callee_id'] ?? 0) : (int) $c['caller_id'];
  $list = $other ? rows('SELECT id, kind, payload FROM voice_call_signals WHERE call_id=? AND from_id=? AND id>? ORDER BY id LIMIT 50', [(int) $id, $other, $after]) : [];
  return ['status' => $c['status'], 'signals' => array_map(fn($r) => ['id' => (int) $r['id'], 'kind' => $r['kind'], 'data' => json_decode($r['payload'], true)], $list)];
});

/** HQ check (v109.15): does Cloudflare give WDOS a relay (TURN) for calls? Reports only counts, never the keys or credentials. */
route('GET', '/hq/calls/relay', function () {
  $u = require_user(); if (!is_hq($u)) fail(403, 'HQ only.', 'forbidden');
  $set = trim((string) cfg('CF_TURN_KEY_ID', '')) !== '' && trim((string) cfg('CF_TURN_KEY_API_TOKEN', '')) !== '';
  $ice = vc_ice(); $turn = 0; $stun = 0;
  foreach ($ice as $s) foreach ((array) ($s['urls'] ?? []) as $url) { if (str_starts_with((string) $url, 'turn')) $turn++; elseif (str_starts_with((string) $url, 'stun')) $stun++; }
  return ['keys_set' => $set, 'relay_ready' => $turn > 0, 'turn_addresses' => $turn, 'stun_addresses' => $stun];
});
