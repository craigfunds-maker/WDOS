<?php
declare(strict_types=1);
/* v109.47: region rooms: group chat and forum for every region of the structure. Rules and membership are in rooms_fns.php. */

/** The files attached to messages, for showing (name, type, size); only files the sender uploaded are ever attached. */
function rm_files(array $msgs): array {
  $ids = []; foreach ($msgs as $m) foreach (array_filter(explode(',', (string) $m['files'])) as $f) $ids[] = (int) $f;
  if (!$ids) return [];
  $o = []; foreach (rows('SELECT id, name, mime, size FROM doc_files WHERE id IN (' . lm_in($ids) . ')') as $f) $o[(int) $f['id']] = ['id' => (int) $f['id'], 'name' => $f['name'], 'mime' => $f['mime'], 'size' => (int) $f['size']];
  return $o;
}
function rm_shape(array $m, array $files, array $u, bool $mod): array {
  return ['id' => (int) $m['id'], 'kind' => $m['kind'], 'title' => $m['title'], 'body' => $m['body'], 'created_at' => $m['created_at'], 'replies' => (int) $m['replies'], 'last_at' => $m['last_at'],
    'author' => ['id' => (int) $m['user_id'], 'first_name' => $m['first_name'], 'last_name' => $m['last_name'], 'has_photo' => (int) $m['has_photo'], 'last_seen_at' => $m['last_seen_at']],
    'files' => array_values(array_filter(array_map(fn($f) => $files[(int) $f] ?? null, array_filter(explode(',', (string) $m['files']))))),
    'can_remove' => $mod || (int) $m['user_id'] === (int) $u['id']];
}
/** Who may remove other people's messages: WODDI HQ, and the leaders at the top of the room's own region. */
function rm_moderator(array $u, string $net, array $n): bool {
  if (is_hq($u)) return true;
  $s = lm_seat_of((int) $u['id']); return $s && $s['network'] === $net && lm_same(lm_cut($s['place'], $s['depth']), $n);
}
/** Save a message (chat line, forum topic or reply) with its files. */
function rm_post(array $u, string $net, array $n, string $kind, string $title, string $body, $files, ?int $parent = null): int {
  $body = mb_substr(trim($body), 0, 6000); $title = mb_substr(trim($title), 0, 190);
  $fs = []; foreach (array_slice(is_array($files) ? $files : [], 0, ROOM_MAX_FILES) as $f) if ((int) $f && val('SELECT 1 FROM doc_files WHERE id=? AND uploaded_by=?', [(int) $f, (int) $u['id']])) $fs[] = (int) $f;
  if ($body === '' && !$fs) fail(422, 'Write a message, or attach a document, photo or video.', 'body');
  if ($kind === 'topic' && $title === '') fail(422, 'Give the topic a title.', 'title');
  throttle('room:' . (int) $u['id'], 120, 3600); throttle_hit('room:' . (int) $u['id']);
  $id = ins('INSERT INTO room_msgs (room,room_net,room_place,kind,parent_id,user_id,title,body,files,last_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
    [rm_key($net, $n), $net, json_encode(['cc' => $n['cc'], 'state' => $n['state'], 'lga' => $n['lga'], 'comm' => $n['comm']], JSON_UNESCAPED_UNICODE), $kind, $parent, (int) $u['id'], $title, $body, implode(',', $fs), now(), now()]);
  foreach ($fs as $f) try { q("INSERT INTO doc_links (file_id,owner_type,owner_id,added_by,created_at) VALUES (?,'room',?,?,?)", [$f, $id, (int) $u['id'], now()]); } catch (PDOException $e) { /* linked already */ }
  return $id;
}
function rm_room_out(array $u, string $net, array $n, bool $withMembers): array {
  $o = ['network' => $net, 'place' => $n, 'name' => lm_place_name($n), 'level' => lm_level_label(LM_LEVELS[lm_depth($n) - 1], $n['cc']), 'key' => rm_key($net, $n), 'can_moderate' => rm_moderator($u, $net, $n)];
  if ($withMembers) { $m = rm_members($net, $n); $o['members'] = $m; $o['online'] = count(array_filter($m, fn($x) => $x['last_seen_at'] && strtotime($x['last_seen_at'] . ' UTC') > time() - 300)); }
  return $o;
}

/* ---- my rooms -------------------------------------------------------------------------------------------------- */
route('GET', '/rooms', function () {
  $u = require_user(); $list = rm_mine($u);
  if (is_hq($u)) {          // HQ: the rooms where something was said lately; any other room opens from the leadership map
    foreach (rows("SELECT room_net, room_place, MAX(id) mx FROM room_msgs WHERE hidden_at IS NULL GROUP BY room_net, room_place ORDER BY mx DESC LIMIT 40") as $r) { $p = json_decode((string) $r['room_place'], true); if (is_array($p)) $list[] = [$r['room_net'], $p + ['chapter' => 0]]; }
  }
  if (!$list && !is_hq($u)) fail(403, 'Region chats open for volunteer leaders, State Envoys and WODDI HQ.', 'forbidden');
  $out = []; $seen = [];
  foreach ($list as [$net, $n]) {
    $k = rm_key($net, $n); if (isset($seen[$k])) continue; $seen[$k] = 1;
    $last = row("SELECT m.id, m.kind, m.title, m.body, m.created_at, u.first_name FROM room_msgs m JOIN users u ON u.id=m.user_id WHERE m.room=? AND m.hidden_at IS NULL ORDER BY m.id DESC LIMIT 1", [$k]);
    $read = (int) (val('SELECT last_id FROM room_reads WHERE room=? AND user_id=?', [$k, (int) $u['id']]) ?? 0);
    $out[] = rm_room_out($u, $net, $n, false) + ['members_count' => count(rm_members($net, $n)), 'unread' => (int) val('SELECT COUNT(*) FROM room_msgs WHERE room=? AND id>? AND user_id<>? AND hidden_at IS NULL', [$k, $read, (int) $u['id']]),
      'last' => $last ? ['kind' => $last['kind'], 'text' => mb_substr($last['title'] !== '' ? $last['title'] : $last['body'], 0, 120), 'by' => $last['first_name'], 'at' => $last['created_at']] : null];
  }
  return ['rooms' => $out, 'is_hq' => is_hq($u)];
});
/* ---- one room: who is in it, its chat, its forum ----------------------------------------------------------------- */
route('GET', '/rooms/room', function () {
  $u = require_user(); [$net, $n] = rm_in($_GET); if (!rm_can($u, $net, $n)) fail(403, 'This region chat is outside your area.', 'scope');
  return ['room' => rm_room_out($u, $net, $n, true)];
});
route('GET', '/rooms/chat', function () {
  $u = require_user(); [$net, $n] = rm_in($_GET); if (!rm_can($u, $net, $n)) fail(403, 'This region chat is outside your area.', 'scope');
  $k = rm_key($net, $n); $after = (int) ($_GET['after'] ?? 0); $mod = rm_moderator($u, $net, $n);
  $sql = "SELECT m.*, u.first_name, u.last_name, u.has_photo, u.last_seen_at FROM room_msgs m JOIN users u ON u.id=m.user_id WHERE m.room=? AND m.kind='chat' AND m.hidden_at IS NULL";
  $msgs = $after ? rows("$sql AND m.id>? ORDER BY m.id LIMIT 200", [$k, $after]) : array_reverse(rows("$sql ORDER BY m.id DESC LIMIT 80", [$k]));
  $f = rm_files($msgs);
  if ($msgs) q('INSERT INTO room_reads (room,user_id,last_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_id=GREATEST(last_id,VALUES(last_id))', [$k, (int) $u['id'], (int) end($msgs)['id']]);
  return ['messages' => array_map(fn($m) => rm_shape($m, $f, $u, $mod), $msgs)];
});
route('POST', '/rooms/chat', function () {
  $u = require_user(); $b = body(); [$net, $n] = rm_in($b); if (!rm_can($u, $net, $n)) fail(403, 'This region chat is outside your area.', 'scope');
  $id = rm_post($u, $net, $n, 'chat', '', (string) ($b['body'] ?? ''), $b['files'] ?? []);
  q('INSERT INTO room_reads (room,user_id,last_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_id=GREATEST(last_id,VALUES(last_id))', [rm_key($net, $n), (int) $u['id'], $id]);
  return ['ok' => true, 'id' => $id];
});
route('GET', '/rooms/forum', function () {
  $u = require_user(); [$net, $n] = rm_in($_GET); if (!rm_can($u, $net, $n)) fail(403, 'This region forum is outside your area.', 'scope');
  $mod = rm_moderator($u, $net, $n);
  $t = rows("SELECT m.*, u.first_name, u.last_name, u.has_photo, u.last_seen_at FROM room_msgs m JOIN users u ON u.id=m.user_id WHERE m.room=? AND m.kind='topic' AND m.hidden_at IS NULL ORDER BY m.last_at DESC, m.id DESC LIMIT 100", [rm_key($net, $n)]);
  $f = rm_files($t);
  return ['topics' => array_map(fn($m) => rm_shape($m, $f, $u, $mod), $t)];
});
route('POST', '/rooms/forum', function () {
  $u = require_user(); $b = body(); [$net, $n] = rm_in($b); if (!rm_can($u, $net, $n)) fail(403, 'This region forum is outside your area.', 'scope');
  $id = rm_post($u, $net, $n, 'topic', (string) ($b['title'] ?? ''), (string) ($b['body'] ?? ''), $b['files'] ?? []);
  // a new topic is news for the region: every leader in it is told (the writer is not)
  $q = []; foreach (['cc', 'state', 'lga', 'comm'] as $k2) if ($n[$k2] !== '') $q[] = $k2 . '=' . rawurlencode($n[$k2]);
  $url = '#/rooms/r?network=' . $net . '&' . implode('&', $q) . '&tab=forum&topic=' . $id;
  foreach (rm_members($net, $n) as $m) if ($m['id'] !== (int) $u['id']) notify($m['id'], 'room', 'New topic in ' . lm_place_name($n) . ': ' . mb_substr(trim((string) $b['title']), 0, 80), work_name($u) . ' started a topic in the ' . $net . ' forum for ' . lm_place_name($n) . '.', $url);
  after_response(fn() => push_drain(20));
  return ['ok' => true, 'id' => $id];
});
route('GET', '/rooms/topic/(\d+)', function ($id) {
  $u = require_user(); $t = row("SELECT m.*, u.first_name, u.last_name, u.has_photo, u.last_seen_at FROM room_msgs m JOIN users u ON u.id=m.user_id WHERE m.id=? AND m.kind='topic' AND m.hidden_at IS NULL", [(int) $id]);
  if (!$t) fail(404, 'That topic was not found.', 'not_found');
  $n = (json_decode((string) $t['room_place'], true) ?: []) + ['chapter' => 0]; if (!rm_can($u, (string) $t['room_net'], $n)) fail(403, 'This region forum is outside your area.', 'scope');
  $mod = rm_moderator($u, (string) $t['room_net'], $n);
  $r = rows("SELECT m.*, u.first_name, u.last_name, u.has_photo, u.last_seen_at FROM room_msgs m JOIN users u ON u.id=m.user_id WHERE m.parent_id=? AND m.kind='reply' AND m.hidden_at IS NULL ORDER BY m.id LIMIT 500", [(int) $id]);
  $f = rm_files(array_merge([$t], $r));
  return ['topic' => rm_shape($t, $f, $u, $mod), 'replies' => array_map(fn($m) => rm_shape($m, $f, $u, $mod), $r)];
});
route('POST', '/rooms/topic/(\d+)/reply', function ($id) {
  $u = require_user(); $t = row("SELECT * FROM room_msgs WHERE id=? AND kind='topic' AND hidden_at IS NULL", [(int) $id]); if (!$t) fail(404, 'That topic was not found.', 'not_found');
  $n = (json_decode((string) $t['room_place'], true) ?: []) + ['chapter' => 0]; $net = (string) $t['room_net'];
  if (!rm_can($u, $net, $n)) fail(403, 'This region forum is outside your area.', 'scope');
  $b = body(); $rid = rm_post($u, $net, $n, 'reply', '', (string) ($b['body'] ?? ''), $b['files'] ?? [], (int) $id);
  q('UPDATE room_msgs SET replies=replies+1, last_at=? WHERE id=?', [now(), (int) $id]);
  if ((int) $t['user_id'] !== (int) $u['id']) notify((int) $t['user_id'], 'room', work_name($u) . ' replied to your topic', mb_substr($t['title'], 0, 120), '#/rooms/r?network=' . $net . '&cc=' . rawurlencode($n['cc']) . ($n['state'] !== '' ? '&state=' . rawurlencode($n['state']) : '') . ($n['lga'] !== '' ? '&lga=' . rawurlencode($n['lga']) : '') . ($n['comm'] !== '' ? '&comm=' . rawurlencode($n['comm']) : '') . '&tab=forum&topic=' . (int) $id);
  after_response(fn() => push_drain(10));
  return ['ok' => true, 'id' => $rid];
});
/* The writer removes her own message; HQ and the region's own leaders remove anyone's. Kept in the database, hidden from the room. */
route('POST', '/rooms/msg/(\d+)/remove', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM room_msgs WHERE id=? AND hidden_at IS NULL', [(int) $id]); if (!$m) fail(404, 'That message was not found.', 'not_found');
  $n = (json_decode((string) $m['room_place'], true) ?: []) + ['chapter' => 0];
  if ((int) $m['user_id'] !== (int) $u['id'] && !(rm_can($u, (string) $m['room_net'], $n) && rm_moderator($u, (string) $m['room_net'], $n))) fail(403, 'You can remove your own messages. The region\'s leaders and WODDI HQ can remove others.', 'forbidden');
  q('UPDATE room_msgs SET hidden_at=?, hidden_by=? WHERE id=?', [now(), (int) $u['id'], (int) $id]);
  if ($m['kind'] === 'reply' && $m['parent_id']) q('UPDATE room_msgs SET replies=GREATEST(replies-1,0) WHERE id=?', [(int) $m['parent_id']]);
  log_activity((int) $u['id'], (int) $m['user_id'], 'room_message_removed', mb_substr($m['title'] !== '' ? $m['title'] : $m['body'], 0, 120));
  return ['ok' => true];
});
