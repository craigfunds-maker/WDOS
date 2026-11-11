<?php
declare(strict_types=1);
/* HQ inspiration: the weekly message (Mondays), the monthly leadership message
 * (from the first Wednesday, for leaders) and the birthday greeting.
 * The calendar runs on Lagos time, where WODDI HQ sits. */

function hq_today(): DateTimeImmutable { return new DateTimeImmutable(cfg('WDOS_TEST_NOW', 'now'), new DateTimeZone('Africa/Lagos')); }   // WDOS_TEST_NOW is for testing a date; never set it on the live server
function setting(string $k, string $default = ''): string { return (string) (val('SELECT v FROM app_settings WHERE k=?', [$k]) ?? $default); }
/** Week number of the programme (1 = the week of the start Monday). 0 before it starts. */
function insp_week(?DateTimeImmutable $d = null): int {
  $d = $d ?? hq_today(); $start = new DateTimeImmutable(setting('weekly_start', '2026-09-14'), new DateTimeZone('Africa/Lagos'));
  $days = (int) floor(($d->setTime(0, 0)->getTimestamp() - $start->getTimestamp()) / 86400);
  return $days < 0 ? 0 : intdiv($days, 7) + 1;
}
function first_wednesday(DateTimeImmutable $d): DateTimeImmutable { return new DateTimeImmutable('first wednesday of ' . $d->format('F Y'), new DateTimeZone('Africa/Lagos')); }
/** Month number of the monthly series. A month is released on its first Wednesday; before that the previous one stays current. */
function insp_month(?DateTimeImmutable $d = null): int {
  $d = $d ?? hq_today(); [$sy, $sm] = array_map('intval', explode('-', setting('monthly_start', '2026-09')));
  $n = ((int) $d->format('Y') - $sy) * 12 + ((int) $d->format('n') - $sm) + 1;
  if ($d->setTime(0, 0) < first_wednesday($d)) $n--;
  return max(0, $n);
}
const INSP_TONES = ['celebration', 'gratitude', 'courage', 'calm', 'growth', 'purpose'];
/** Address of the photograph for a message, or '' when none is loaded yet. One query for the whole request. */
function insp_photo(string $key): string {
  static $have = null;
  if ($have === null) { $have = []; try { foreach (rows('SELECT image_key, updated_at FROM message_photos') as $r) $have[$r['image_key']] = strtotime($r['updated_at']); } catch (PDOException $e) { /* table arrives with the next migration run */ } }
  return isset($have[$key]) ? '/api/inspiration/photo?k=' . rawurlencode($key) . '&v=' . $have[$key] : '';
}
/** Fetch one photograph from Pexels, cropped wide for the card, and keep it. Returns '' or the reason it failed. */
function insp_photo_fetch(string $key, int $pexelsId, string $photographer = '', string $alt = ''): string {
  if ($pexelsId < 1) return 'no photograph number';
  $ch = curl_init("https://images.pexels.com/photos/$pexelsId/pexels-photo-$pexelsId.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=1280&h=640");
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WDOS/1.0; +https://thewoddi.org)']);
  $bin = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $info = $bin ? @getimagesizefromstring($bin) : false;
  if ($code !== 200 || !$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) return "Pexels answered $code";
  if (strlen($bin) > 1500000) return 'photograph too large';
  q('DELETE FROM message_photos WHERE image_key=?', [$key]);
  $st = db()->prepare('INSERT INTO message_photos (image_key,mime,bytes,pexels_id,photographer,page_url,alt,focus,updated_at) VALUES (?,?,?,?,?,?,?,?,?)');
  $st->bindValue(1, $key); $st->bindValue(2, $info['mime']); $st->bindValue(3, $bin, PDO::PARAM_LOB); $st->bindValue(4, $pexelsId, PDO::PARAM_INT);
  $st->bindValue(5, mb_substr($photographer, 0, 160)); $st->bindValue(6, "https://www.pexels.com/photo/$pexelsId/"); $st->bindValue(7, mb_substr($alt, 0, 300)); $st->bindValue(8, ''); $st->bindValue(9, now());
  $st->execute();
  return '';
}
/** The chosen photographs (lib/message_photos.json) load themselves: a few at a time, after a reply has been sent,
 *  until every message has its photograph. Nothing to press, and a fresh database fills itself the same way. */
function insp_photos_tick(int $max = 5): void {
  try {
    $map = json_decode((string) @file_get_contents(__DIR__ . '/message_photos.json'), true); if (!is_array($map) || !$map) return;
    $have = array_flip(array_column(rows('SELECT image_key FROM message_photos'), 'image_key'));
    $todo = array_diff_key($map, $have); if (!$todo) return;
    $lock = val("SELECT v FROM app_settings WHERE k='msg_photos_lock'");
    if ($lock && strtotime($lock . ' UTC') > time() - 45) return;
    q("DELETE FROM app_settings WHERE k='msg_photos_lock'"); q("INSERT INTO app_settings (k,v) VALUES ('msg_photos_lock',?)", [now()]);
    $n = 0; $errs = [];
    foreach ($todo as $key => $id) { if ($n >= $max) break; $e = insp_photo_fetch((string) $key, (int) $id); if ($e !== '') $errs[] = "$key: $e"; $n++; }
    if ($errs) { error_log('[wdos photos] ' . implode('; ', array_slice($errs, 0, 3))); q("UPDATE app_settings SET v=? WHERE k='msg_photos_lock'", [gmdate('Y-m-d H:i:s', time() + 600)]); }   // Pexels refused: wait ten minutes before trying again
    else q("DELETE FROM app_settings WHERE k='msg_photos_lock'");
  } catch (Throwable $e) { /* tables arrive with the next migration run, or another request holds the lock */ }
}
/** The approved write-up for a message in the reader's language (English when that language is not written yet). */
function insp_text(int $messageId, string $lang): ?array {
  try { return row("SELECT * FROM hq_message_texts WHERE message_id=? AND lang IN (?, 'en') ORDER BY (lang=?) DESC LIMIT 1", [$messageId, $lang, $lang]); } catch (PDOException $e) { return null; }
}
/** $seat is the reader's seat; null (HQ's library) shows the text for both networks. */
function insp_shape(array $m, array $u, string $label, ?array $seat = null, string $lang = 'en'): array {
  $t = insp_text((int) $m['id'], $lang); $paras = []; $reflection = '';
  if ($t) {
    $fill = fn(string $x) => strtr($x, ['{{FirstName}}' => (string) ($u['first_name'] ?? '') !== '' ? (string) $u['first_name'] : 'Leader', '{{RoleTitle}}' => $seat ? ($seat['network'] === 'WNNN' ? $seat['title_wnnn'] : $seat['title_wgmn']) : 'a volunteer leader',
      '{{LocationName}}' => $seat ? ((string) $seat['area_label'] !== '' ? $seat['area_label'] : $seat['unit_name']) : 'your area']);
    if (trim((string) $t['body']) !== '') $paras[] = $fill(trim((string) $t['body']));
    $net = $seat['network'] ?? '';
    if ($net !== 'WNNN' && trim((string) $t['lens_wgmn']) !== '') $paras[] = trim((string) $t['lens_wgmn']);
    if ($net !== 'WGMN' && trim((string) $t['lens_wnnn']) !== '') $paras[] = trim((string) $t['lens_wnnn']);
    if ($t['closing'] !== '') $paras[] = $fill($t['closing']);
    $reflection = $t['reflection'];
  }
  return ['id' => (int) $m['id'], 'kind' => $m['kind'], 'seq' => (int) $m['seq'], 'network' => $m['network'], 'label' => $label, 'theme' => $m['theme'],
    'headline' => $t && $t['headline'] !== '' ? $t['headline'] : $m['headline'], 'key_thought' => $t && $t['key_thought'] !== '' ? $t['key_thought'] : $m['key_thought'],
    'body' => $paras ? implode("\n\n", $paras) : (string) ($m['body'] ?? ''), 'reflection' => $reflection, 'image' => insp_photo($m['image']), 'first_name' => $u['first_name'] ?? ''];
}
function insp_weekly_for(?string $network, int $week): ?array {
  if (!$network || $week < 1) return null;
  return row("SELECT * FROM hq_messages WHERE kind='weekly' AND seq=? AND network=?", [$week, $network]);
}
/** The one card a person sees on the dashboard today, or null.
 *  The birthday card is for everyone. Weekly and monthly inspiration is written for volunteer leaders only. */
function insp_today(array $u, string $lang = 'en'): ?array {
  $d = hq_today();
  if ($u['dob'] && substr($u['dob'], 5) === $d->format('m-d')) {
    return ['id' => 0, 'kind' => 'birthday', 'seq' => 0, 'network' => (string) $u['network'], 'label' => 'Happy birthday', 'theme' => '', 'headline' => 'A day to celebrate you',
      'key_thought' => 'Everyone at WODDI HQ is celebrating you today.', 'body' => '', 'image' => insp_photo('messages/celebration.webp'), 'first_name' => $u['first_name']];
  }
  $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  if (!$seat) return null;
  $preview = setting('weekly_preview') === $u['id'] . ':' . $d->format('Y-m-d');           // one-leader test from the setup page
  if (((int) $d->format('N') === 1 || $preview) && ($m = insp_weekly_for($seat['network'], insp_week($d)))) return insp_shape($m, $u, 'Weekly inspiration, week ' . $m['seq'], $seat, $lang);
  // the monthly message stays on the dashboard for three days from the moment it was sent, then leaves
  $n = insp_month($d); $sent = $n ? setting('sent_monthly_' . $n) : '';
  if ($sent !== '' && time() - strtotime($sent . ' UTC') < 3 * 86400 && ($m = row("SELECT * FROM hq_messages WHERE kind='monthly' AND seq=?", [$n]))) return insp_shape($m, $u, 'Monthly leadership inspiration, ' . $d->format('F Y'), $seat, $lang);
  return null;
}
function set_setting(string $k, string $v): void { q('DELETE FROM app_settings WHERE k=?', [$k]); q('INSERT INTO app_settings (k,v) VALUES (?,?)', [$k, $v]); }
/** Claim a send so two requests can never both do it. True for the one that got it. */
function claim_send(string $key): bool {
  try { q('INSERT INTO app_settings (k,v) VALUES (?,?)', [$key, now()]); return true; } catch (PDOException $e) { return false; }
}

/* Scheduled sends, to volunteer leaders only, in the app and by push. Each message goes out exactly once:
 * the weekly one on its Monday from 07:00 Lagos time, the monthly one from 07:00 on the first Wednesday
 * (and as a catch-up if nothing was running that day). */
function run_inspiration_automations(array &$done): void {
  $d = hq_today();
  if ((int) $d->format('G') < 7) return;
  $leaders = 'EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL AND s.network=?)';
  $week = insp_week($d);
  if ((int) $d->format('N') === 1 && $week >= 1 && insp_weekly_for('WGMN', $week) && claim_send('sent_weekly_' . $week)) {
    $n = 0;
    foreach (['WGMN', 'WNNN'] as $net) if ($m = insp_weekly_for($net, $week)) $n += notify_many($leaders, [$net], 'inspiration', $m['headline'], $m['key_thought'], '#/');
    $done['weekly_inspiration'] = "week $week to $n leaders";
  }
  $month = insp_month($d);
  if ($month >= 1 && ($m = row("SELECT * FROM hq_messages WHERE kind='monthly' AND seq=?", [$month])) && claim_send('sent_monthly_' . $month)) {
    $n = notify_many('EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)', [], 'inspiration', 'This month: ' . $m['headline'], 'Monthly leadership inspiration for ' . $d->format('F') . '. Theme: ' . $m['theme'] . '.', '#/');
    $done['monthly_inspiration'] = "month $month to $n leaders";
  }
}
/** Runs the automations from ordinary traffic, at most every five minutes, so nothing depends on an outside timer. */
function automations_tick(): void {
  $last = (int) (val("SELECT v FROM app_settings WHERE k='auto_tick'") ?? 0);
  if (time() - $last < 300) return;
  if (q("UPDATE app_settings SET v=? WHERE k='auto_tick' AND v=?", [(string) time(), (string) $last])->rowCount() === 0) {
    if ($last !== 0 || !claim_send('auto_tick')) return;                      // someone else took this tick
    set_setting('auto_tick', (string) time());
  }
  $done = run_automations(); run_inspiration_automations($done); if (function_exists('rp_reminders_daily')) rp_reminders_daily($done); if (function_exists('work_automations')) work_automations($done);
  push_drain(60);
}
