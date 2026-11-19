<?php
declare(strict_types=1);
/* v109.24: group voice calls. Up to 8 people, sound only, straight between the phones (WebRTC), no Jitsi.
 * Every pair of people on the call has its own direct connection. Whoever joins sends one offer to each person already on the
 * call; each of them answers. WDOS passes those messages only between the two people concerned, and deletes them when the call ends.
 * Who may be rung follows the one-to-one rule (vc_people): leaders in the caller's own country and network; HQ rings any leader.
 * A person rung who does not join in 45 seconds gets a missed group call; she can still join while the call is live.
 * A leader can also bring in WODDI HQ ("hq" in the list): it rings everyone at HQ, and the first to answer takes the HQ place. */

const GC_MAX = 8;          // people on a phone-to-phone call, the caller included
const GC_RING = 45;
const GC_SPEAK = 7;        // on a relay call: the caller and up to 6 others speak at a time; everyone else listens
function gc_big_max(): int { return max(GC_MAX, min(500, (int) (cfg('BIG_CALL_MAX', '150') ?: 150))); }
/** Cloudflare Realtime SFU, for calls of more than 8 people. Set in config.local.php by HQ: CF_SFU_APP_ID and CF_SFU_APP_TOKEN. */
function sfu_on(): bool { return trim((string) cfg('CF_SFU_APP_ID', '')) !== '' && trim((string) cfg('CF_SFU_APP_TOKEN', '')) !== ''; }
function sfu_api(string $method, string $path, ?array $body = null): array {
  $app = trim((string) cfg('CF_SFU_APP_ID', '')); if (!preg_match('/^[A-Za-z0-9]+$/', $app)) fail(503, 'Large group calls are not set up yet. WODDI HQ can set them up.', 'sfu_off');
  $ch = curl_init(rtrim((string) cfg('CF_SFU_API', 'https://rtc.live.cloudflare.com/v1'), '/') . '/apps/' . $app . $path);
  curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim((string) cfg('CF_SFU_APP_TOKEN', '')), 'Content-Type: application/json']]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
  $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $d = $r ? json_decode((string) $r, true) : null;
  if ($code < 200 || $code >= 300 || !is_array($d) || !empty($d['errorCode'])) {
    error_log('[wdos calls] relay ' . $method . ' ' . preg_replace('#/sessions/[^/]+#', '/sessions/*', $path) . ': HTTP ' . $code . ' ' . (is_array($d) ? ($d['errorCode'] ?? '') . ' ' . ($d['errorDescription'] ?? '') : ''));
    fail(502, 'The call relay did not answer. Try again in a moment.', 'sfu');
  }
  return $d;
}
function gc_cap(array $g): int { return ($g['mode'] ?? 'mesh') === 'sfu' ? gc_big_max() : GC_MAX; }

function gc_name(int $id): string { return function_exists('vc_name') ? vc_name($id) : ''; }
function gc_get(int $id): array { $g = row('SELECT * FROM voice_groups WHERE id=?', [$id]); if (!$g) fail(404, 'That call was not found.', 'not_found'); return $g; }
function gc_me(array $u, array $g): ?array { return row('SELECT * FROM voice_group_people WHERE group_id=? AND user_id=?', [(int) $g['id'], (int) $u['id']]); }
function gc_mine(array $u, int $id): array {
  $g = gc_get($id); $me = gc_me($u, $g);
  // WODDI HQ was rung into the call: anyone at HQ may answer, until one of them has
  if (!$me && is_hq($u) && !empty($g['hq_ring'])) $me = ['group_id' => (int) $g['id'], 'user_id' => (int) $u['id'], 'status' => 'ringing', 'hq_seat' => true];
  if (!$me && is_hq($u) && gc_hq_on((int) $g['id'])) fail(409, 'Someone else at HQ has answered this call.', 'taken');
  if (!$me) fail(404, 'That call was not found.', 'not_found');
  return [$g, $me];
}
/** Someone from HQ is on the call or ringing for it. */
function gc_hq_on(int $gid): bool {
  return (bool) val("SELECT 1 FROM voice_group_people p JOIN users u ON u.id=p.user_id WHERE p.group_id=? AND p.status IN ('joined','ringing') AND u.role IN ('hq_admin','super_admin') LIMIT 1", [$gid]);
}

function gc_shape(array $u, array $g): array {
  $people = [];
  $big = ($g['mode'] ?? 'mesh') === 'sfu';
  foreach (rows('SELECT p.*, u.first_name, u.last_name, u.has_photo FROM voice_group_people p JOIN users u ON u.id=p.user_id WHERE p.group_id=? ORDER BY p.invited_at, p.user_id', [(int) $g['id']]) as $r) {
    $seat = current_seat((int) $r['user_id']);
    $people[] = ['id' => (int) $r['user_id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'has_photo' => (int) $r['has_photo'],
      'title' => $seat ? seat_title($seat) : 'WODDI HQ', 'status' => $r['status'], 'me' => (int) $r['user_id'] === (int) $u['id']]
      + ($big ? ['speaking' => (int) ($r['speaking'] ?? 0) === 1, 'live' => (int) ($r['speaking'] ?? 0) === 1 && (int) ($r['sfu_published'] ?? 0) === 1, 'hand' => !empty($r['hand_at']) ? $r['hand_at'] : null] : []);
  }
  $mine = gc_me($u, $g);
  return ['id' => (int) $g['id'], 'group' => true, 'title' => $g['title'], 'host_id' => (int) $g['host_id'], 'host' => gc_name((int) $g['host_id']), 'status' => $g['status'],
    'my_status' => $mine['status'] ?? (is_hq($u) && !empty($g['hq_ring']) ? 'ringing' : null), 'created_at' => $g['created_at'], 'people' => $people, 'max' => gc_cap($g), 'mode' => $g['mode'] ?? 'mesh', 'speak_max' => GC_SPEAK,
    'hq_ringing' => !empty($g['hq_ring']), 'hq_on' => gc_hq_on((int) $g['id'])];
}

/** Ringing that nobody took becomes a missed call; a call with nobody left on it ends; nothing stays open more than 3 hours. */
function gc_expire(): void {
  try {
    foreach (rows("SELECT p.group_id, p.user_id, g.host_id FROM voice_group_people p JOIN voice_groups g ON g.id=p.group_id WHERE p.status='ringing' AND p.invited_at < ? LIMIT 100", [gmdate('Y-m-d H:i:s', time() - GC_RING)]) as $r) {
      if (q("UPDATE voice_group_people SET status='missed' WHERE group_id=? AND user_id=? AND status='ringing'", [(int) $r['group_id'], (int) $r['user_id']])->rowCount() === 1)
        notify((int) $r['user_id'], 'call', 'Missed group call from ' . gc_name((int) $r['host_id']), 'Open Calls in WDOS to join if the call is still on, or to ring back.', '#/calls/g' . (int) $r['group_id']);
    }
    foreach (rows('SELECT id, host_id, hq_by FROM voice_groups WHERE hq_ring IS NOT NULL AND hq_ring < ? LIMIT 50', [gmdate('Y-m-d H:i:s', time() - GC_RING)]) as $r)
      if (q('UPDATE voice_groups SET hq_ring=NULL WHERE id=? AND hq_ring IS NOT NULL', [(int) $r['id']])->rowCount() === 1)
        notify_hq('call', 'Missed group call from ' . gc_name((int) ($r['hq_by'] ?: $r['host_id'])), 'Nobody at HQ answered in time. Open Calls in WDOS to join if the call is still on.', '#/calls/g' . (int) $r['id']);
    $cut = gmdate('Y-m-d H:i:s', time() - 3 * 3600);
    foreach (rows("SELECT g.id FROM voice_groups g WHERE g.status='live' AND (g.created_at < ? OR (g.created_at < ? AND NOT EXISTS (SELECT 1 FROM voice_group_people p WHERE p.group_id=g.id AND p.status='joined')))", [$cut, gmdate('Y-m-d H:i:s', time() - 20)]) as $g) gc_end((int) $g['id']);
  } catch (PDOException $e) { /* before migration 039 */ }
}
function gc_end(int $id): void {
  q("UPDATE voice_groups SET status='ended', ended_at=?, hq_ring=NULL WHERE id=? AND status='live'", [now(), $id]);
  q("UPDATE voice_group_people SET status='left', left_at=COALESCE(left_at, ?) WHERE group_id=? AND status='joined'", [now(), $id]);
  q("UPDATE voice_group_people SET status='missed' WHERE group_id=? AND status='ringing'", [$id]);
  q('DELETE FROM voice_group_signals WHERE group_id=?', [$id]);
}
/** Ring people into a call. Each must be someone the inviter may ring; "hq" rings WODDI HQ. */
function gc_check_reach(array $u, array $ids): void {
  if (!$ids) return;
  [$w, $p] = vc_reach_sql($u);
  $ok = array_map('intval', array_column(rows("SELECT DISTINCT u.id FROM seats sc JOIN users u ON u.id=sc.user_id JOIN org_units o ON o.id=sc.org_unit_id WHERE $w AND u.id IN (" . implode(',', array_map('intval', $ids)) . ')', $p), 'id'));
  if (array_diff(array_map('intval', $ids), $ok)) fail(403, 'You can ring leaders in your own country and network, and WODDI HQ.', 'to');
}
function gc_invite(array $u, int $gid, array $ids, bool $hq = false): int {
  gc_check_reach($u, array_filter($ids, fn($x) => (int) $x !== (int) $u['id']));
  $n = 0; $from = trim($u['first_name'] . ' ' . $u['last_name']);
  $g = gc_get($gid);
  $have = (int) val("SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status IN ('joined','ringing')", [$gid]) + (!empty($g['hq_ring']) ? 1 : 0);
  foreach ($ids as $to) {
    $to = (int) $to; if ($to === (int) $u['id']) continue;
    $cur = row('SELECT status FROM voice_group_people WHERE group_id=? AND user_id=?', [$gid, $to]);
    if ($cur && in_array($cur['status'], ['joined', 'ringing'], true)) continue;
    if ($have + 1 > gc_cap($g)) fail(422, 'This call takes ' . gc_cap($g) . ' people at most.', 'full');
    if ($cur) q("UPDATE voice_group_people SET status='ringing', invited_by=?, invited_at=?, left_at=NULL WHERE group_id=? AND user_id=?", [(int) $u['id'], now(), $gid, $to]);
    else q("INSERT INTO voice_group_people (group_id,user_id,status,invited_by,invited_at) VALUES (?,?,'ringing',?,?)", [$gid, $to, (int) $u['id'], now()]);
    notify($to, 'call', 'Group call from ' . $from, 'Tap to join the call.', '#/calls/g' . $gid);
    $have++; $n++;
  }
  if ($hq && !is_hq($u) && empty($g['hq_ring']) && !gc_hq_on($gid)) {
    if ($have + 1 > gc_cap($g)) fail(422, 'This call takes ' . gc_cap($g) . ' people at most.', 'full');
    q('UPDATE voice_groups SET hq_ring=?, hq_by=? WHERE id=?', [now(), (int) $u['id'], $gid]);
    $seat = work_seat($u);
    notify_hq('call', 'Group call from ' . $from, ($seat ? seat_title($seat) . '. ' : '') . 'Tap to join the call.', '#/calls/g' . $gid);
    $have++; $n++;
  }
  return $n;
}
/** The people asked for: leader ids, and whether WODDI HQ is wanted ("hq"). */
function gc_to(): array {
  $raw = is_array(body()['to'] ?? null) ? body()['to'] : [];
  $hq = in_array('hq', $raw, true);
  $ids = array_values(array_unique(array_filter(array_map('intval', array_filter($raw, fn($x) => $x !== 'hq')), fn($x) => $x > 0)));
  return [$ids, $hq];
}

route('POST', '/gcalls', function () {
  $u = require_user(); if (!vc_can_call($u)) fail(403, 'Voice calls are for volunteer leaders and WODDI HQ.', 'forbidden');
  throttle('vcall:' . (int) $u['id'], 40, 3600); throttle_hit('vcall:' . (int) $u['id']);
  [$ids, $hq] = gc_to(); if (is_hq($u)) $hq = false;
  if (!empty(body()['all'])) {       // everyone under her seat (HQ: every serving leader), up to the limit
    if (is_hq($u)) $all = array_map('intval', array_column(rows("SELECT DISTINCT s.user_id FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND u.status='active' AND s.status NOT IN ('on_leave','suspended') LIMIT 2000"), 'user_id'));
    else { if (!work_seat($u)) fail(403, 'Your team opens when WODDI HQ records your leadership seat.', 'no_seat'); [$w, $wp] = work_people_sql($u);
      $all = array_map('intval', array_column(rows("SELECT DISTINCT u.id FROM users u JOIN seats sc ON sc.user_id=u.id AND sc.ends_at IS NULL AND sc.status NOT IN ('on_leave','suspended') WHERE u.status='active' AND $w LIMIT 2000", $wp), 'id')); }
    $ids = array_values(array_unique(array_merge($ids, $all)));
  }
  $ids = array_values(array_filter($ids, fn($x) => $x !== (int) $u['id']));
  if (!$ids && !$hq) fail(422, empty(body()['all']) ? 'Choose who to call.' : 'There are no leaders under your seat to call yet.', 'to');
  $n = count($ids) + ($hq ? 1 : 0) + 1; $mode = $n > GC_MAX ? 'sfu' : 'mesh';
  if ($mode === 'sfu' && !sfu_on()) fail(422, 'A call with more than ' . GC_MAX . ' people needs the call relay, which WODDI HQ has not set up yet. Call ' . (GC_MAX - 1) . ' people at most for now.', 'sfu_off');
  if ($n > gc_big_max()) fail(422, 'A group call takes ' . gc_big_max() . ' people at most, you included.', 'full');
  gc_check_reach($u, $ids);
  // one call at a time: anything this person was on is left
  foreach (rows("SELECT group_id FROM voice_group_people WHERE user_id=? AND status='joined'", [(int) $u['id']]) as $r) gc_leave($u, (int) $r['group_id']);
  q("UPDATE voice_calls SET status='cancelled', ended_at=? WHERE caller_id=? AND status='ringing'", [now(), (int) $u['id']]);
  $gid = ins('INSERT INTO voice_groups (host_id,title,status,created_at,mode) VALUES (?,?,?,?,?)', [(int) $u['id'], mb_substr(str_in('title', 120), 0, 120), 'live', now(), $mode]);
  q("INSERT INTO voice_group_people (group_id,user_id,status,invited_by,invited_at,joined_at,speaking) VALUES (?,?,'joined',?,?,?,?)", [$gid, (int) $u['id'], (int) $u['id'], now(), now(), $mode === 'sfu' ? 1 : 0]);
  gc_invite($u, $gid, $ids, $hq);
  log_activity((int) $u['id'], count($ids) === 1 ? $ids[0] : null, 'group_call', 'Group call: ' . implode(' and ', array_filter([!$ids ? '' : (count($ids) === 1 ? vc_name($ids[0]) : count($ids) . ' leaders'), $hq ? 'WODDI HQ' : ''])), 'gcall', $gid);
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'call' => gc_shape($u, gc_get($gid)), 'ice' => vc_ice(), 'peers' => [], 'ring_seconds' => GC_RING];
});
route('GET', '/gcalls/(\d+)', function ($id) { $u = require_user(); gc_expire(); [$g] = gc_mine($u, (int) $id); return ['call' => gc_shape($u, $g)]; });

route('POST', '/gcalls/(\d+)/join', function ($id) {
  $u = require_user(); gc_expire(); [$g, $me] = gc_mine($u, (int) $id);
  if ($g['status'] !== 'live') fail(409, 'This call has ended.', 'gone');
  if (!empty($me['hq_seat'])) {      // the first person at HQ to answer takes the HQ place
    if (q('UPDATE voice_groups SET hq_ring=NULL WHERE id=? AND hq_ring IS NOT NULL', [(int) $id])->rowCount() !== 1) fail(409, 'Someone else at HQ has answered this call.', 'taken');
    q("INSERT INTO voice_group_people (group_id,user_id,status,invited_by,invited_at) VALUES (?,?,'ringing',?,?)", [(int) $id, (int) $u['id'], (int) ($g['hq_by'] ?: $g['host_id']), now()]);
    $me['status'] = 'ringing'; $g = gc_get((int) $id);
  }
  if ($me['status'] !== 'joined') {
    if ((int) val("SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status='joined'", [(int) $id]) >= gc_cap($g)) fail(422, 'This call is full.', 'full');
    log_activity((int) $u['id'], (int) $g['host_id'], 'group_call_join', 'Joined a group call by ' . gc_name((int) $g['host_id']), 'gcall', (int) $id);
    q("UPDATE voice_group_people SET status='joined', joined_at=?, left_at=NULL, sfu_session=NULL, sfu_published=0, hand_at=NULL, speaking=? WHERE group_id=? AND user_id=?", [now(), (int) $g['host_id'] === (int) $u['id'] && ($g['mode'] ?? '') === 'sfu' ? 1 : 0, (int) $id, (int) $u['id']]);
    q('DELETE FROM voice_group_signals WHERE group_id=? AND (from_id=? OR to_id=?)', [(int) $id, (int) $u['id'], (int) $u['id']]);    // a fresh start after a rejoin
  }
  $peers = array_map('intval', array_column(rows("SELECT user_id FROM voice_group_people WHERE group_id=? AND status='joined' AND user_id<>?", [(int) $id, (int) $u['id']]), 'user_id'));
  return ['ok' => true, 'call' => gc_shape($u, $g), 'ice' => vc_ice(), 'peers' => $peers];
});
route('POST', '/gcalls/(\d+)/decline', function ($id) {
  $u = require_user(); [, $me] = gc_mine($u, (int) $id); if (!empty($me['hq_seat'])) return ['ok' => true];      // one person at HQ saying no leaves it ringing for the others
  q("UPDATE voice_group_people SET status='declined' WHERE group_id=? AND user_id=? AND status='ringing'", [(int) $id, (int) $u['id']]);
  return ['ok' => true];
});
function gc_leave(array $u, int $id): void {
  q("UPDATE voice_group_people SET status='left', left_at=?, speaking=0, hand_at=NULL, sfu_published=0 WHERE group_id=? AND user_id=? AND status='joined'", [now(), $id, (int) $u['id']]);
  q('DELETE FROM voice_group_signals WHERE group_id=? AND (from_id=? OR to_id=?)', [$id, (int) $u['id'], (int) $u['id']]);
  if (!(int) val("SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status='joined'", [$id])) gc_end($id);
}
route('POST', '/gcalls/(\d+)/leave', function ($id) { $u = require_user(); gc_mine($u, (int) $id); gc_leave($u, (int) $id); return ['ok' => true]; });
route('POST', '/gcalls/(\d+)/add', function ($id) {
  $u = require_user(); [$g, $me] = gc_mine($u, (int) $id);
  if ($g['status'] !== 'live' || $me['status'] !== 'joined') fail(409, 'Join the call first.', 'not_joined');
  [$ids, $hq] = gc_to(); $n = gc_invite($u, (int) $id, $ids, $hq);
  if ($n) log_activity((int) $u['id'], count($ids) === 1 ? $ids[0] : null, 'group_call_add', 'Brought into a group call: ' . implode(', ', array_merge(array_map('vc_name', $ids), $hq ? ['WODDI HQ'] : [])), 'gcall', (int) $id);
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'rung' => $n, 'call' => gc_shape($u, gc_get((int) $id))];
});

/** Connection messages between two people on the call: offer, answer. Only the person they are for can read them. */
route('POST', '/gcalls/(\d+)/signal', function ($id) {
  $u = require_user(); [$g, $me] = gc_mine($u, (int) $id);
  if ($g['status'] !== 'live' || $me['status'] !== 'joined') fail(409, 'You are not on this call.', 'gone');
  if (($g['mode'] ?? 'mesh') !== 'mesh') fail(409, 'This call goes through the relay.', 'mode');
  $to = (int) (body()['to'] ?? 0); $kind = (string) (body()['kind'] ?? '');
  if (!in_array($kind, ['offer', 'answer'], true)) fail(422, 'That message is not part of a call.', 'kind');
  if (!val("SELECT 1 FROM voice_group_people WHERE group_id=? AND user_id=? AND status='joined'", [(int) $id, $to])) fail(409, 'That person is not on the call.', 'gone');
  $json = json_encode(body()['data'] ?? null, JSON_UNESCAPED_SLASHES);
  if ($json === false || strlen($json) > 60000) fail(422, 'That message is too large.', 'data');
  if ((int) val('SELECT COUNT(*) FROM voice_group_signals WHERE group_id=? AND from_id=? AND to_id=?', [(int) $id, (int) $u['id'], $to]) >= 10) fail(429, 'Too many messages for one call.', 'throttled');
  return ['ok' => true, 'id' => ins('INSERT INTO voice_group_signals (group_id,from_id,to_id,kind,payload,created_at) VALUES (?,?,?,?,?,?)', [(int) $id, (int) $u['id'], $to, $kind, $json, now()])];
});
route('GET', '/gcalls/(\d+)/signal', function ($id) {
  $u = require_user(); gc_expire(); [$g] = gc_mine($u, (int) $id); $after = max(0, (int) ($_GET['after'] ?? 0));
  $list = rows('SELECT id, from_id, kind, payload FROM voice_group_signals WHERE group_id=? AND to_id=? AND id>? ORDER BY id LIMIT 50', [(int) $id, (int) $u['id'], $after]);
  $g = gc_get((int) $id);
  return ['call' => gc_shape($u, $g), 'signals' => array_map(fn($r) => ['id' => (int) $r['id'], 'from' => (int) $r['from_id'], 'kind' => $r['kind'], 'data' => json_decode($r['payload'], true)], $list)];
});

/** Group calls ringing for me now (added to /calls/ring). */
function gc_ringing(array $u): array {
  try {
    gc_expire();
    $out = [];
    foreach (rows("SELECT g.* FROM voice_group_people p JOIN voice_groups g ON g.id=p.group_id WHERE p.user_id=? AND p.status='ringing' AND g.status='live' AND p.invited_at >= ? ORDER BY g.id DESC LIMIT 3", [(int) $u['id'], gmdate('Y-m-d H:i:s', time() - GC_RING)]) as $g) {
      $s = gc_shape($u, $g); $on = count(array_filter($s['people'], fn($p) => $p['status'] === 'joined'));
      $out[] = ['id' => $s['id'], 'group' => true, 'with' => $s['host'], 'with_title' => tf_count($on), 'on' => $on, 'people' => $s['people']];
    }
    if (is_hq($u)) foreach (rows("SELECT g.* FROM voice_groups g WHERE g.status='live' AND g.hq_ring >= ? AND NOT EXISTS (SELECT 1 FROM voice_group_people p WHERE p.group_id=g.id AND p.user_id=?) ORDER BY g.id DESC LIMIT 3", [gmdate('Y-m-d H:i:s', time() - GC_RING), (int) $u['id']]) as $g) {
      $s = gc_shape($u, $g); $on = count(array_filter($s['people'], fn($p) => $p['status'] === 'joined'));
      $out[] = ['id' => $s['id'], 'group' => true, 'with' => gc_name((int) ($g['hq_by'] ?: $g['host_id'])), 'with_title' => tf_count($on) . ', for WODDI HQ', 'on' => $on, 'people' => $s['people'], 'for_hq' => true];
    }
    return $out;
  } catch (PDOException $e) { return []; }
}
function tf_count(int $n): string { return $n === 1 ? 'Group call, 1 person on' : "Group call, $n people on"; }
/** My recent group calls, for the Calls page. */
function gc_recent(array $u): array {
  try {
    return array_map(fn($g) => gc_shape($u, $g), rows('SELECT g.* FROM voice_groups g JOIN voice_group_people p ON p.group_id=g.id AND p.user_id=? ORDER BY g.id DESC LIMIT 10', [(int) $u['id']]));
  } catch (PDOException $e) { return []; }
}

/* ---- calls through the relay (v109.27): speaking, hands and the relay connection -------------------------------- */
function gc_big(array $u, int $id): array {
  [$g, $me] = gc_mine($u, $id);
  if (($g['mode'] ?? 'mesh') !== 'sfu') fail(409, 'This call goes phone to phone.', 'mode');
  if ($g['status'] !== 'live' || ($me['status'] ?? '') !== 'joined') fail(409, 'Join the call first.', 'not_joined');
  return [$g, $me];
}
/** I want to speak: straight away if a place is free, otherwise my hand goes up. */
route('POST', '/gcalls/(\d+)/speak', function ($id) {
  $u = require_user(); [$g, $me] = gc_big($u, (int) $id); $id = (int) $id;
  if ((int) $me['speaking'] === 1) return ['ok' => true, 'speaking' => true, 'call' => gc_shape($u, $g)];
  $n = (int) val('SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status=\'joined\' AND speaking=1', [$id]);
  if ($n < GC_SPEAK) { q('UPDATE voice_group_people SET speaking=1, hand_at=NULL WHERE group_id=? AND user_id=?', [$id, (int) $u['id']]); $on = true; }
  else { q('UPDATE voice_group_people SET hand_at=COALESCE(hand_at, ?) WHERE group_id=? AND user_id=?', [now(), $id, (int) $u['id']]); $on = false; }
  return ['ok' => true, 'speaking' => $on, 'call' => gc_shape($u, gc_get($id))];
});
route('POST', '/gcalls/(\d+)/quiet', function ($id) {
  $u = require_user(); gc_big($u, (int) $id);
  q('UPDATE voice_group_people SET speaking=0, hand_at=NULL WHERE group_id=? AND user_id=?', [(int) $id, (int) $u['id']]);
  return ['ok' => true, 'call' => gc_shape($u, gc_get((int) $id))];
});
/** The caller gives someone the floor, or takes it back. */
route('POST', '/gcalls/(\d+)/floor', function ($id) {
  $u = require_user(); [$g] = gc_big($u, (int) $id); $id = (int) $id;
  if ((int) $g['host_id'] !== (int) $u['id']) fail(403, 'Only the person who started the call can do this.', 'forbidden');
  $who = (int) (body()['user_id'] ?? 0); $on = !empty(body()['on']);
  if ($who === (int) $u['id']) fail(422, 'This is you.', 'self');
  if (!val("SELECT 1 FROM voice_group_people WHERE group_id=? AND user_id=? AND status='joined'", [$id, $who])) fail(409, 'She is not on the call.', 'gone');
  if ($on && (int) val('SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status=\'joined\' AND speaking=1 AND user_id<>?', [$id, $who]) >= GC_SPEAK) fail(422, GC_SPEAK . ' people are speaking already. Mute someone first.', 'full');
  q('UPDATE voice_group_people SET speaking=?, hand_at=NULL WHERE group_id=? AND user_id=?', [$on ? 1 : 0, $id, $who]);
  return ['ok' => true, 'call' => gc_shape($u, gc_get($id))];
});
/** My connection to the relay: one per person per call. The relay's key never leaves this server. */
route('POST', '/gcalls/(\d+)/relay/session', function ($id) {
  $u = require_user(); [, $me] = gc_big($u, (int) $id);
  $d = sfu_api('POST', '/sessions/new');
  if (empty($d['sessionId'])) fail(502, 'The call relay did not answer. Try again in a moment.', 'sfu');
  q('UPDATE voice_group_people SET sfu_session=?, sfu_published=0 WHERE group_id=? AND user_id=?', [(string) $d['sessionId'], (int) $id, (int) $u['id']]);
  return ['ok' => true];
});
function gc_my_session(array $u, int $id): string {
  $s = (string) val('SELECT sfu_session FROM voice_group_people WHERE group_id=? AND user_id=?', [$id, (int) $u['id']]);
  if ($s === '') fail(409, 'Connect to the relay first.', 'no_session');
  return $s;
}
/** Send my voice: only while I have the floor. */
route('POST', '/gcalls/(\d+)/relay/publish', function ($id) {
  $u = require_user(); [, $me] = gc_big($u, (int) $id); $id = (int) $id;
  if ((int) $me['speaking'] !== 1) fail(403, 'Wait until you are given the floor.', 'not_speaking');
  $sdp = (string) (body()['sdp'] ?? ''); $mid = (string) (body()['mid'] ?? '');
  if ($sdp === '' || strlen($sdp) > 60000 || $mid === '' || strlen($mid) > 8) fail(422, 'That is not part of a call.', 'sdp');
  $d = sfu_api('POST', '/sessions/' . rawurlencode(gc_my_session($u, $id)) . '/tracks/new', ['sessionDescription' => ['type' => 'offer', 'sdp' => $sdp], 'tracks' => [['location' => 'local', 'mid' => $mid, 'trackName' => 'mic']]]);
  if (!empty($d['tracks'][0]['errorCode'])) fail(502, 'The call relay did not take your voice. Try again.', 'sfu');
  q('UPDATE voice_group_people SET sfu_published=1 WHERE group_id=? AND user_id=?', [$id, (int) $u['id']]);
  return ['ok' => true, 'answer' => $d['sessionDescription'] ?? null];
});
/** Hear the people who have the floor. Returns the relay's offer and which person each line is. */
route('POST', '/gcalls/(\d+)/relay/pull', function ($id) {
  $u = require_user(); gc_big($u, (int) $id); $id = (int) $id;
  $want = array_values(array_unique(array_filter(array_map('intval', is_array(body()['ids'] ?? null) ? body()['ids'] : []), fn($x) => $x > 0 && $x !== (int) $u['id'])));
  if (!$want || count($want) > GC_SPEAK) fail(422, 'Choose who to hear.', 'ids');
  $live = rows("SELECT user_id, sfu_session FROM voice_group_people WHERE group_id=? AND status='joined' AND speaking=1 AND sfu_published=1 AND sfu_session IS NOT NULL AND user_id IN (" . implode(',', $want) . ')', [$id]);
  if (!$live) return ['ok' => true, 'tracks' => [], 'offer' => null];
  $d = sfu_api('POST', '/sessions/' . rawurlencode(gc_my_session($u, $id)) . '/tracks/new', ['tracks' => array_map(fn($r) => ['location' => 'remote', 'sessionId' => $r['sfu_session'], 'trackName' => 'mic'], $live)]);
  $tracks = [];
  foreach ($live as $i => $r) { $t = $d['tracks'][$i] ?? null; if ($t && empty($t['errorCode']) && isset($t['mid'])) $tracks[] = ['user_id' => (int) $r['user_id'], 'mid' => (string) $t['mid']]; }
  return ['ok' => true, 'tracks' => $tracks, 'offer' => !empty($d['requiresImmediateRenegotiation']) ? ($d['sessionDescription'] ?? null) : null];
});
route('POST', '/gcalls/(\d+)/relay/answer', function ($id) {
  $u = require_user(); gc_big($u, (int) $id); $sdp = (string) (body()['sdp'] ?? '');
  if ($sdp === '' || strlen($sdp) > 60000) fail(422, 'That is not part of a call.', 'sdp');
  sfu_api('PUT', '/sessions/' . rawurlencode(gc_my_session($u, (int) $id)) . '/renegotiate', ['sessionDescription' => ['type' => 'answer', 'sdp' => $sdp]]);
  return ['ok' => true];
});
/** Stop hearing someone (she lost the floor or left). */
route('POST', '/gcalls/(\d+)/relay/close', function ($id) {
  $u = require_user(); gc_big($u, (int) $id);
  $mids = array_values(array_filter(array_map('strval', is_array(body()['mids'] ?? null) ? body()['mids'] : []), fn($m) => $m !== '' && strlen($m) <= 8));
  if (!$mids) return ['ok' => true];
  sfu_api('PUT', '/sessions/' . rawurlencode(gc_my_session($u, (int) $id)) . '/tracks/close', ['tracks' => array_map(fn($m) => ['mid' => $m], array_slice($mids, 0, 20)), 'force' => true]);
  return ['ok' => true];
});
