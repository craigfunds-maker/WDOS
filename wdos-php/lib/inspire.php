<?php
declare(strict_types=1);
require_once __DIR__ . '/inspire_fns.php';

route('GET', '/inspiration/today', fn() => ['card' => insp_today(require_user())]);

route('GET', '/inspiration/library', function () {
  $u = require_user(); $hq = is_hq($u); $week = insp_week(); $month = insp_month(); $leader = (bool) current_seat((int) $u['id']);
  $net = $hq && in_array($_GET['network'] ?? '', ['WGMN', 'WNNN'], true) ? $_GET['network'] : ($hq ? 'WGMN' : (string) $u['network']);
  $weekly = rows("SELECT * FROM hq_messages WHERE kind='weekly' AND network=?" . ($hq ? '' : ' AND seq<=' . $week) . ' ORDER BY seq DESC', [$net]);
  $monthly = ($hq || $leader) ? rows("SELECT * FROM hq_messages WHERE kind='monthly'" . ($hq ? ' AND seq<=' . max($month + 3, 6) : ' AND seq<=' . $month) . ' ORDER BY seq DESC') : [];
  $ms = new DateTimeImmutable(setting('monthly_start', '2026-09') . '-01');
  return [
    'network' => $net, 'is_hq' => $hq, 'week' => $week, 'month' => $month, 'is_monday' => (int) hq_today()->format('N') === 1,
    'weeks_loaded' => (int) val("SELECT COALESCE(MAX(seq),0) FROM hq_messages WHERE kind='weekly'"),
    'weekly' => array_map(fn($m) => insp_shape($m, $u, 'Week ' . $m['seq']) + ['released' => (int) $m['seq'] <= $week, 'current' => (int) $m['seq'] === $week], $weekly),
    'monthly' => array_map(fn($m) => insp_shape($m, $u, $ms->modify('+' . ((int) $m['seq'] - 1) . ' months')->format('F Y')) + ['released' => (int) $m['seq'] <= $month, 'current' => (int) $m['seq'] === $month], $monthly),
  ];
});

