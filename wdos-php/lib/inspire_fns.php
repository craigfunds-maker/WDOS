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
function insp_shape(array $m, array $u, string $label): array {
  return ['id' => (int) $m['id'], 'kind' => $m['kind'], 'seq' => (int) $m['seq'], 'network' => $m['network'], 'label' => $label, 'theme' => $m['theme'],
    'headline' => $m['headline'], 'key_thought' => $m['key_thought'], 'body' => (string) ($m['body'] ?? ''), 'image' => insp_photo($m['image']), 'first_name' => $u['first_name']];
}
function insp_weekly_for(?string $network, int $week): ?array {
  if (!$network || $week < 1) return null;
  return row("SELECT * FROM hq_messages WHERE kind='weekly' AND seq=? AND network=?", [$week, $network]);
}
/** The one card a person sees on the dashboard today, or null.
 *  The birthday card is for everyone. Weekly and monthly inspiration is written for volunteer leaders only. */
function insp_today(array $u): ?array {
  $d = hq_today();
  if ($u['dob'] && substr($u['dob'], 5) === $d->format('m-d')) {
    return ['id' => 0, 'kind' => 'birthday', 'seq' => 0, 'network' => (string) $u['network'], 'label' => 'Happy birthday', 'theme' => '', 'headline' => 'A day to celebrate you',
      'key_thought' => 'Everyone at WODDI HQ is celebrating you today.', 'body' => '', 'image' => insp_photo('messages/celebration.webp'), 'first_name' => $u['first_name']];
  }
  $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  if (!$seat) return null;
  $preview = setting('weekly_preview') === $u['id'] . ':' . $d->format('Y-m-d');           // one-leader test from the setup page
  if (((int) $d->format('N') === 1 || $preview) && ($m = insp_weekly_for($seat['network'], insp_week($d)))) return insp_shape($m, $u, 'Weekly inspiration, week ' . $m['seq']);
  // the monthly message stays on the dashboard for three days from the moment it was sent, then leaves
  $n = insp_month($d); $sent = $n ? setting('sent_monthly_' . $n) : '';
  if ($sent !== '' && time() - strtotime($sent . ' UTC') < 3 * 86400 && ($m = row("SELECT * FROM hq_messages WHERE kind='monthly' AND seq=?", [$n]))) return insp_shape($m, $u, 'Monthly leadership inspiration, ' . $d->format('F Y'));
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
