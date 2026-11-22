<?php
declare(strict_types=1);
/* v109.47: region rooms (Azeez, 22 Nov 2026: "group chat for each region ... community forum for leadership region as well must work, they
 * should be able to share link, document, photo, video").
 * One room for every region of the structure (country, state or region, LGA or district, ward or community cluster) in each network.
 * Its members are the volunteer leaders serving in that region. A leader opens the rooms on her own line (her country, her state, down to her
 * own place) and the rooms of the regions under her. The State Envoy opens her country and state rooms in both networks. HQ opens every room.
 * Each room has a group chat and a forum (topics with replies). Attachments are WDOS files, readable only by people who may open the room.
 * Shared functions here (doc_can reads them for files); the routes are in rooms.php. */

const ROOM_MAX_FILES = 6;

/** The room key: network and the place, written so spelling differences meet in one room. */
function rm_key(string $net, array $n): string {
  return $net . ':' . strtoupper($n['cc']) . ':' . lm_k($n['state']) . ':' . lm_k($n['lga']) . ':' . lm_k($n['comm']);
}
/** The place and network from the request (query or body). */
function rm_in(array $src): array {
  foreach (['cc', 'state', 'lga', 'comm'] as $k) $_GET[$k] = (string) ($src[$k] ?? '');
  $_GET['chapter'] = '';
  $n = lm_node_in(); $net = strtoupper((string) ($src['network'] ?? ''));
  if (!in_array($net, ['WGMN', 'WNNN'], true)) fail(422, 'Choose the network.', 'network');
  if (lm_depth($n) < 1) fail(422, 'Choose the region.', 'region');
  return [$net, $n];
}
/** May this person open the room of $n in network $net? Returns a reason-free bool; never fails. */
function rm_can(array $u, string $net, array $n): bool {
  if (is_hq($u)) return true;
  $d = lm_depth($n); if ($d < 1 || $d > 4) return false;
  if (($u['role'] ?? '') === 'envoy') {
    $e = row('SELECT country_code, state_region FROM envoys WHERE user_id=?', [(int) $u['id']]); if (!$e) return false;
    $mine = ['cc' => (string) $e['country_code'], 'state' => (string) $e['state_region'], 'lga' => '', 'comm' => '', 'chapter' => 0];
    return $d <= 2 && $mine['cc'] !== '' && lm_inside($mine, $n) && ($d === 1 || $mine['state'] !== '');
  }
  $s = lm_seat_of((int) $u['id']); if (!$s || $s['network'] !== $net) return false;
  $root = lm_cut($s['place'], min(4, $s['depth']));
  return lm_inside($n, $root) || lm_inside($root, $n);           // her region and the regions under it, and every region above her
}
/** The leaders who belong to a room: everyone serving inside the region, in that network; State Envoys in their country and state rooms. */
function rm_members(string $net, array $n): array {
  $out = []; $seen = [];
  foreach (lm_seats() as $s) if ($s['network'] === $net && lm_inside($s['place'], $n) && !isset($seen[$s['user_id']])) {
    $seen[$s['user_id']] = 1;
    $out[] = ['id' => $s['user_id'], 'first_name' => $s['person']['first_name'], 'last_name' => $s['person']['last_name'], 'has_photo' => $s['person']['has_photo'], 'last_seen_at' => $s['person']['last_seen_at'],
      'title' => lm_title($s['position_code'], $s['network'], $s['place']['cc']), 'place' => lm_place_name(lm_cut($s['place'], $s['depth'])), 'rank' => $s['rank']];
  }
  if (lm_depth($n) <= 2 && $n['cc'] !== '') {
    $w = 'e.country_code=?'; $p = [$n['cc']]; if (lm_depth($n) === 2) { $w .= ' AND LOWER(e.state_region)=LOWER(?)'; $p[] = $n['state']; }
    foreach (rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.last_seen_at, e.state_region FROM envoys e JOIN users u ON u.id=e.user_id WHERE $w AND u.status='active' AND e.status<>'ended'", $p) as $e)
      if (!isset($seen[(int) $e['id']])) { $seen[(int) $e['id']] = 1; $out[] = ['id' => (int) $e['id'], 'first_name' => $e['first_name'], 'last_name' => $e['last_name'], 'has_photo' => (int) $e['has_photo'], 'last_seen_at' => $e['last_seen_at'], 'title' => 'State Envoy', 'place' => $e['state_region'], 'rank' => 50]; }
  }
  usort($out, fn($a, $b) => [$a['rank'], $a['last_name']] <=> [$b['rank'], $b['last_name']]);
  return $out;
}
/** The rooms on a person's own line, from her country down to her own region (each with its network). */
function rm_mine(array $u): array {
  $out = [];
  if (($u['role'] ?? '') === 'envoy') {
    $e = row('SELECT country_code, state_region FROM envoys WHERE user_id=?', [(int) $u['id']]); if (!$e || !$e['country_code']) return [];
    $n = ['cc' => (string) $e['country_code'], 'state' => (string) $e['state_region'], 'lga' => '', 'comm' => '', 'chapter' => 0];
    foreach (['WGMN', 'WNNN'] as $net) for ($d = 1; $d <= ($n['state'] !== '' ? 2 : 1); $d++) $out[] = [$net, lm_cut($n, $d)];
    return $out;
  }
  $s = lm_seat_of((int) $u['id']); if (!$s) return [];
  for ($d = 1; $d <= min(4, $s['depth']); $d++) $out[] = [$s['network'], lm_cut($s['place'], $d)];
  return $out;
}
/** For WDOS file access: may this person read a file attached in a room? */
function rm_file_ok(array $u, int $msgId): bool {
  try { $m = row('SELECT room_net, room_place FROM room_msgs WHERE id=?', [$msgId]); } catch (PDOException $e) { return false; }
  if (!$m) return false; $n = json_decode((string) $m['room_place'], true); if (!is_array($n)) return false;
  return rm_can($u, (string) $m['room_net'], $n + ['chapter' => 0]);
}
