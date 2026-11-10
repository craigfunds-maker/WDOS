<?php
declare(strict_types=1);
route('GET', '/inspiration/today', fn() => ['card' => insp_today(require_user())]);

route('GET', '/inspiration/library', function () {
  $u = require_user(); $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  if (!$seat) fail(403, 'Inspiration messages are written for volunteer leaders. This page opens when WODDI HQ records a leadership seat.', 'no_seat');
  $week = insp_week(); $month = insp_month(); $net = $seat['network'];
  $weekly = rows("SELECT * FROM hq_messages WHERE kind='weekly' AND network=? AND seq<=? ORDER BY seq DESC", [$net, $week]);
  $monthly = rows("SELECT * FROM hq_messages WHERE kind='monthly' AND seq<=? ORDER BY seq DESC", [$month]);
  $ms = new DateTimeImmutable(setting('monthly_start', '2026-09') . '-01');
  return [
    'network' => $net, 'week' => $week, 'month' => $month, 'is_monday' => (int) hq_today()->format('N') === 1,
    'weekly' => array_map(fn($m) => insp_shape($m, $u, 'Week ' . $m['seq']) + ['released' => true, 'current' => (int) $m['seq'] === $week], $weekly),
    'monthly' => array_map(fn($m) => insp_shape($m, $u, $ms->modify('+' . ((int) $m['seq'] - 1) . ' months')->format('F Y')) + ['released' => true, 'current' => (int) $m['seq'] === $month], $monthly),
  ];
});
