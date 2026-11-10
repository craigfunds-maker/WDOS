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
function insp_shape(array $m, array $u, string $label): array {
  return ['id' => (int) $m['id'], 'kind' => $m['kind'], 'seq' => (int) $m['seq'], 'network' => $m['network'], 'label' => $label, 'theme' => $m['theme'],
    'headline' => $m['headline'], 'key_thought' => $m['key_thought'], 'body' => (string) ($m['body'] ?? ''), 'image' => '/assets/inspiration/' . $m['image'], 'first_name' => $u['first_name']];
}
function insp_weekly_for(?string $network, int $week): ?array {
  if (!$network || $week < 1) return null;
  return row("SELECT * FROM hq_messages WHERE kind='weekly' AND seq=? AND network=?", [$week, $network]);
}
/** The one card a person sees on her dashboard today, or null. Birthday first, then Monday's message, then the month's. */
function insp_today(array $u): ?array {
  $d = hq_today();
  if ($u['dob'] && substr($u['dob'], 5) === $d->format('m-d')) {
    return ['id' => 0, 'kind' => 'birthday', 'seq' => 0, 'network' => (string) $u['network'], 'label' => 'Happy birthday', 'theme' => '', 'headline' => 'A day to celebrate you',
      'key_thought' => 'Everyone at WODDI HQ is celebrating you today.', 'body' => '', 'image' => '/assets/inspiration/messages/celebration.webp', 'first_name' => $u['first_name']];
  }
  if ((int) $d->format('N') === 1 && ($m = insp_weekly_for($u['network'], insp_week($d)))) return insp_shape($m, $u, 'Weekly inspiration, week ' . $m['seq']);
  if (current_seat((int) $u['id']) && ($n = insp_month($d)) && ($m = row("SELECT * FROM hq_messages WHERE kind='monthly' AND seq=?", [$n]))) {
    return insp_shape($m, $u, 'Monthly leadership inspiration, ' . $d->format('F Y'));
  }
  return null;
}

/* Scheduled sends. Called from run_automations(); each runs at most once a day. */
function run_inspiration_automations(array &$done): void {
  $d = hq_today();
  if ((int) $d->format('G') < 7) return;                       // released at 07:00 Lagos time
  if ((int) $d->format('N') === 1) {
    $r = cron_once('weekly_inspiration', function () use ($d) {
      $week = insp_week($d); $n = 0;
      foreach (['WGMN', 'WNNN'] as $net) {
        if ($m = insp_weekly_for($net, $week)) $n += notify_many('u.network=?', [$net], 'inspiration', $m['headline'], $m['key_thought'], '#/inspiration');
      }
      return $n ? "week $week to $n" : "no message loaded for week $week";
    });
    if ($r !== null) $done['weekly_inspiration'] = $r;
  }
  if ($d->format('Y-m-d') === first_wednesday($d)->format('Y-m-d')) {
    $r = cron_once('monthly_inspiration', function () use ($d) {
      $m = row("SELECT * FROM hq_messages WHERE kind='monthly' AND seq=?", [insp_month($d)]);
      if (!$m) return 'no message loaded for this month';
      $n = notify_many('EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)', [], 'inspiration', 'This month: ' . $m['headline'], 'Monthly leadership inspiration for ' . $d->format('F') . '. Theme: ' . $m['theme'] . '.', '#/inspiration');
      return "month {$m['seq']} to $n leaders";
    });
    if ($r !== null) $done['monthly_inspiration'] = $r;
  }
}
