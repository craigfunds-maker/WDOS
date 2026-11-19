<?php
declare(strict_types=1);
/* v109.24: group voice calls. Up to 8 people, sound only, straight between the phones (WebRTC), no Jitsi.
 * Every pair of people on the call has its own direct connection. Whoever joins sends one offer to each person already on the
 * call; each of them answers. WDOS passes those messages only between the two people concerned, and deletes them when the call ends.
 * Who may be rung follows the one-to-one rule (vc_people): leaders in the caller's own country and network; HQ rings any leader.
 * A person rung who does not join in 45 seconds gets a missed group call; she can still join while the call is live.
 * A leader can also bring in WODDI HQ ("hq" in the list): it rings everyone at HQ, and the first to answer takes the HQ place. */

const GC_MAX = 8;          // people on one call, the caller included
const GC_RING = 45;

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
  foreach (rows('SELECT p.user_id, p.status, p.joined_at, u.first_name, u.last_name, u.has_photo FROM voice_group_people p JOIN users u ON u.id=p.user_id WHERE p.group_id=? ORDER BY p.invited_at, p.user_id', [(int) $g['id']]) as $r) {
    $seat = current_seat((int) $r['user_id']);
    $people[] = ['id' => (int) $r['user_id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'has_photo' => (int) $r['has_photo'],
      'title' => $seat ? seat_title($seat) : 'WODDI HQ', 'status' => $r['status'], 'me' => (int) $r['user_id'] === (int) $u['id']];
  }
  $mine = gc_me($u, $g);
  return ['id' => (int) $g['id'], 'group' => true, 'title' => $g['title'], 'host_id' => (int) $g['host_id'], 'host' => gc_name((int) $g['host_id']), 'status' => $g['status'],
    'my_status' => $mine['status'] ?? (is_hq($u) && !empty($g['hq_ring']) ? 'ringing' : null), 'created_at' => $g['created_at'], 'people' => $people, 'max' => GC_MAX,
    'hq_ringing' => !empty($g['hq_ring']), 'hq_on' => gc_hq_on((int) $g['id'])];
}

/** Ringing that nobody took becomes a missed call; a call with nobody left on it ends; nothing stays open more than 3 hours. */
function gc_expire(): void {
  try {
    foreach (rows("SELECT p.group_id, p.user_id, g.host_id FROM voice_group_people p JOIN voice_groups g ON g.id=p.group_id WHERE p.status='ringing' AND p.invited_at < ? LIMIT 100", [gmdate('Y-m-d H:i:s', time() - GC_RING)]) as $r) {
      if (q("UPDATE voice_group_people SET status='missed' WHERE group_id=? AND user_id=? AND status='ringing'", [(int) $r['group_id'], (int) $r['user_id']])->rowCount() === 1)
        notify((int) $r['user_id'], 'meeting', 'Missed group call from ' . gc_name((int) $r['host_id']), 'Open Calls in WDOS to join if the call is still on, or to ring back.', '#/calls/g' . (int) $r['group_id']);
    }
    foreach (rows('SELECT id, host_id, hq_by FROM voice_groups WHERE hq_ring IS NOT NULL AND hq_ring < ? LIMIT 50', [gmdate('Y-m-d H:i:s', time() - GC_RING)]) as $r)
      if (q('UPDATE voice_groups SET hq_ring=NULL WHERE id=? AND hq_ring IS NOT NULL', [(int) $r['id']])->rowCount() === 1)
        notify_hq('meeting', 'Missed group call from ' . gc_name((int) ($r['hq_by'] ?: $r['host_id'])), 'Nobody at HQ answered in time. Open Calls in WDOS to join if the call is still on.', '#/calls/g' . (int) $r['id']);
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
function gc_invite(array $u, int $gid, array $ids, bool $hq = false): int {
  $n = 0; $from = trim($u['first_name'] . ' ' . $u['last_name']);
  $g = gc_get($gid);
  $have = (int) val("SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status IN ('joined','ringing')", [$gid]) + (!empty($g['hq_ring']) ? 1 : 0);
  foreach ($ids as $to) {
    $to = (int) $to; if ($to === (int) $u['id']) continue;
    if (!vc_people($u, '', $to)) fail(403, 'You can bring in leaders in your own country and network, and WODDI HQ.', 'to');
    $cur = row('SELECT status FROM voice_group_people WHERE group_id=? AND user_id=?', [$gid, $to]);
    if ($cur && in_array($cur['status'], ['joined', 'ringing'], true)) continue;
    if ($have + 1 > GC_MAX) fail(422, 'A group call takes ' . GC_MAX . ' people at most.', 'full');
    if ($cur) q("UPDATE voice_group_people SET status='ringing', invited_by=?, invited_at=?, left_at=NULL WHERE group_id=? AND user_id=?", [(int) $u['id'], now(), $gid, $to]);
    else q("INSERT INTO voice_group_people (group_id,user_id,status,invited_by,invited_at) VALUES (?,?,'ringing',?,?)", [$gid, $to, (int) $u['id'], now()]);
    notify($to, 'meeting', 'Group call from ' . $from, 'Tap to join the call.', '#/calls/g' . $gid);
    $have++; $n++;
  }
  if ($hq && !is_hq($u) && empty($g['hq_ring']) && !gc_hq_on($gid)) {
    if ($have + 1 > GC_MAX) fail(422, 'A group call takes ' . GC_MAX . ' people at most.', 'full');
    q('UPDATE voice_groups SET hq_ring=?, hq_by=? WHERE id=?', [now(), (int) $u['id'], $gid]);
    $seat = work_seat($u);
    notify_hq('meeting', 'Group call from ' . $from, ($seat ? seat_title($seat) . '. ' : '') . 'Tap to join the call.', '#/calls/g' . $gid);
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
  [$ids, $hq] = gc_to(); $ids = array_values(array_filter($ids, fn($x) => $x !== (int) $u['id'])); if (is_hq($u)) $hq = false;
  if (!$ids && !$hq) fail(422, 'Choose who to call.', 'to');
  if (count($ids) + ($hq ? 1 : 0) > GC_MAX - 1) fail(422, 'A group call takes ' . GC_MAX . ' people at most, you included.', 'full');
  foreach ($ids as $to) if (!vc_people($u, '', $to)) fail(403, 'You can ring leaders in your own country and network.', 'to');
  // one call at a time: anything this person was on is left
  foreach (rows("SELECT group_id FROM voice_group_people WHERE user_id=? AND status='joined'", [(int) $u['id']]) as $r) gc_leave($u, (int) $r['group_id']);
  q("UPDATE voice_calls SET status='cancelled', ended_at=? WHERE caller_id=? AND status='ringing'", [now(), (int) $u['id']]);
  $gid = ins('INSERT INTO voice_groups (host_id,title,status,created_at) VALUES (?,?,?,?)', [(int) $u['id'], mb_substr(str_in('title', 120), 0, 120), 'live', now()]);
  q("INSERT INTO voice_group_people (group_id,user_id,status,invited_by,invited_at,joined_at) VALUES (?,?,'joined',?,?,?)", [$gid, (int) $u['id'], (int) $u['id'], now(), now()]);
  gc_invite($u, $gid, $ids, $hq);
  log_activity((int) $u['id'], count($ids) === 1 ? $ids[0] : null, 'group_call', 'Group call: ' . implode(' and ', array_filter([!$ids ? '' : (count($ids) === 1 ? vc_name($ids[0]) : count($ids) . ' leaders'), $hq ? 'WODDI HQ' : ''])));
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
    if ((int) val("SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status='joined'", [(int) $id]) >= GC_MAX) fail(422, 'This call is full.', 'full');
    log_activity((int) $u['id'], (int) $g['host_id'], 'group_call_join', 'Joined a group call by ' . gc_name((int) $g['host_id']));
    q("UPDATE voice_group_people SET status='joined', joined_at=?, left_at=NULL WHERE group_id=? AND user_id=?", [now(), (int) $id, (int) $u['id']]);
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
  q("UPDATE voice_group_people SET status='left', left_at=? WHERE group_id=? AND user_id=? AND status='joined'", [now(), $id, (int) $u['id']]);
  q('DELETE FROM voice_group_signals WHERE group_id=? AND (from_id=? OR to_id=?)', [$id, (int) $u['id'], (int) $u['id']]);
  if (!(int) val("SELECT COUNT(*) FROM voice_group_people WHERE group_id=? AND status='joined'", [$id])) gc_end($id);
}
route('POST', '/gcalls/(\d+)/leave', function ($id) { $u = require_user(); gc_mine($u, (int) $id); gc_leave($u, (int) $id); return ['ok' => true]; });
route('POST', '/gcalls/(\d+)/add', function ($id) {
  $u = require_user(); [$g, $me] = gc_mine($u, (int) $id);
  if ($g['status'] !== 'live' || $me['status'] !== 'joined') fail(409, 'Join the call first.', 'not_joined');
  [$ids, $hq] = gc_to(); $n = gc_invite($u, (int) $id, $ids, $hq);
  if ($n) log_activity((int) $u['id'], count($ids) === 1 ? $ids[0] : null, 'group_call_add', 'Brought into a group call: ' . implode(', ', array_merge(array_map('vc_name', $ids), $hq ? ['WODDI HQ'] : [])));
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'rung' => $n, 'call' => gc_shape($u, gc_get((int) $id))];
});

/** Connection messages between two people on the call: offer, answer. Only the person they are for can read them. */
route('POST', '/gcalls/(\d+)/signal', function ($id) {
  $u = require_user(); [$g, $me] = gc_mine($u, (int) $id);
  if ($g['status'] !== 'live' || $me['status'] !== 'joined') fail(409, 'You are not on this call.', 'gone');
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
