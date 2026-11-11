<?php
declare(strict_types=1);
/* Profile photographs. They live in the database (user_photos), so they survive every update and the later move
 * to shared hosting. The leader headshots that ship with the current WDOS are bundled in assets/leaders and are
 * given to the matching leaders by name, the same way the current WDOS matches them. A photograph a person
 * uploaded is never replaced. */

function photo_store(int $userId, string $mime, string $bin): void {
  q('DELETE FROM user_photos WHERE user_id=?', [$userId]);
  $st = db()->prepare('INSERT INTO user_photos (user_id,mime,bytes,updated_at) VALUES (?,?,?,?)');
  $st->bindValue(1, $userId, PDO::PARAM_INT); $st->bindValue(2, $mime); $st->bindValue(3, $bin, PDO::PARAM_LOB); $st->bindValue(4, now());
  $st->execute();
  q('UPDATE users SET has_photo=1 WHERE id=?', [$userId]);
}
/** A photograph sent from the browser as a data URL: checked, then stored. */
function photo_from_upload(int $userId): void {
  $bin = base64_decode((string) preg_replace('#^data:image/\w+;base64,#', '', (string) (body()['data'] ?? '')), true);
  if (!$bin || strlen($bin) > 400000) fail(422, 'That photograph is too large. Choose another.', 'photo_size');
  $info = @getimagesizefromstring($bin);
  if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) fail(422, 'Choose a JPG or PNG photograph.', 'photo_type');
  photo_store($userId, $info['mime'], $bin);
}
function photo_norm(string $s): string {
  $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : $s;
  return strtolower($t === false ? $s : $t);
}
/** Give the bundled headshots to seated leaders who have no photograph yet. Safe to run again at any time. */
function photos_seed(): array {
  $map = json_decode((string) @file_get_contents(__DIR__ . '/leader_photos.json'), true);
  if (!is_array($map)) return ['given' => 0, 'names' => []];
  $given = [];
  $leaders = rows('SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u JOIN seats s ON s.user_id=u.id AND s.ends_at IS NULL WHERE u.has_photo=0');
  foreach ($leaders as $u) {
    $full = photo_norm($u['first_name'] . ' ' . $u['last_name']); $key = preg_replace('/[^a-z]/', '', $full);
    $words = array_values(array_filter(preg_split('/\s+/', trim(preg_replace('/[^a-z\s]/', ' ', $full))), fn($w) => strlen($w) > 1));
    $file = null;
    foreach ($map as $e) if ($e['name'] !== '' && $e['name'] === $key) { $file = $e['file']; break; }
    if (!$file && count($words) >= 2) foreach ($map as $e) if (count(array_intersect($words, $e['tokens'])) >= 2) { $file = $e['file']; break; }
    if (!$file) continue;
    $bin = @file_get_contents(__DIR__ . '/../assets/leaders/' . basename($file)); if (!$bin) continue;
    photo_store((int) $u['id'], 'image/jpeg', $bin); $given[] = $u['first_name'] . ' ' . $u['last_name'];
  }
  return ['given' => count($given), 'names' => $given];
}
/** Runs by itself once per version of the headshot set, the first time the app is opened after an update. */
function photos_seed_once(): void {
  try {
    if (val("SELECT 1 FROM app_settings WHERE k='leader_photos_v1'")) return;
    q("INSERT INTO app_settings (k,v) VALUES ('leader_photos_v1',?)", [now()]);
    $r = photos_seed(); q("UPDATE app_settings SET v=? WHERE k='leader_photos_v1'", [now() . ' given ' . $r['given']]);
    if ($r['given']) error_log('[wdos photos] headshots given to ' . $r['given'] . ' leaders');
  } catch (Throwable $e) { /* another request holds it, or the tables are not ready yet */ }
}
