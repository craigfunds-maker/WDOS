<?php
declare(strict_types=1);
/* Reporting and Accountability: shared functions (units, supervisors, periods, figures, reminders).
 * One report per leadership unit and period. The accountable leader files it, deputies and assistants
 * contribute, the supervisor (or HQ) reviews. Figures are counted from records, never typed in. */

const RP_PRINCIPALS = ['country_rep', 'state_coordinator', 'district_coordinator', 'cluster_coordinator', 'chapter_lead'];

function rp_config(): array {
  $def = ['auto' => 0, 'due_day' => 5, 'before' => [7, 3, 0], 'overdue' => [1, 3, 7], 'supervisor_day' => 3, 'escalation_day' => 7];   // auto = send reminders daily; off until HQ switches it on
  $saved = json_decode((string) (val("SELECT v FROM app_settings WHERE k='reporting_config'") ?? ''), true);
  return is_array($saved) ? array_merge($def, array_intersect_key($saved, $def)) : $def;
}
function rp_today(): DateTimeImmutable { return new DateTimeImmutable(cfg('WDOS_TEST_NOW', 'now'), new DateTimeZone('Africa/Lagos')); }
function rp_unit_key(array $s): string { return $s['network'] . '|' . $s['org_unit_id'] . '|' . $s['position_level'] . '|' . mb_strtolower(trim((string) $s['area_label'])); }
function rp_unit_label(array $s): string { return ($s['area_label'] !== '' ? $s['area_label'] . ', ' : '') . $s['unit_name'] . ' (' . $s['network'] . ')'; }

/** Every open principal seat, each with the user id of its reporting supervisor (null = WODDI HQ). */
function rp_tree(): array {
  static $tree = null;
  if ($tree !== null) return $tree;
  $in = "'" . implode("','", RP_PRINCIPALS) . "'";
  $all = rows("SELECT s.id seat_id, s.user_id, s.network, s.org_unit_id, s.area_label, s.position_code, p.level position_level, p.rank_no, ou.path unit_path, ou.name unit_name, ou.country_code,
                      u.first_name, u.last_name, u.has_photo, u.last_seen_at, CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END title
               FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units ou ON ou.id=s.org_unit_id JOIN users u ON u.id=s.user_id
               WHERE s.ends_at IS NULL AND s.position_code IN ($in) AND u.status='active' ORDER BY p.rank_no, ou.name");
  foreach ($all as $i => $b) {
    $best = null;
    foreach ($all as $a) {     // only country and state leaders supervise: below a state, seats are named places we cannot nest reliably
      if ($a['network'] !== $b['network'] || (int) $a['rank_no'] >= (int) $b['rank_no'] || !in_array($a['position_level'], ['country', 'state_region'], true)) continue;
      if (!str_starts_with($b['unit_path'], $a['unit_path']) || ($a['unit_path'] === $b['unit_path'] && $a['position_level'] === $b['position_level'])) continue;
      if (!$best || strlen($a['unit_path']) > strlen($best['unit_path'])) $best = $a;
    }
    $all[$i]['sup_user'] = $best ? (int) $best['user_id'] : null; $all[$i]['sup_name'] = $best ? trim($best['first_name'] . ' ' . $best['last_name']) : 'WODDI HQ';
    $all[$i]['unit_key'] = rp_unit_key($all[$i]);
  }
  return $tree = $all;
}
/** The signed-in person's place in reporting. */
function rp_me(array $u): array {
  $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  if (!$seat) return ['seat' => null, 'unit_key' => null, 'principal' => false, 'owner_id' => null, 'node' => null];
  $key = rp_unit_key($seat); $node = null;
  foreach (rp_tree() as $n) if ($n['unit_key'] === $key) { $node = $n; break; }
  return ['seat' => $seat, 'unit_key' => $key, 'principal' => in_array($seat['position_code'], RP_PRINCIPALS, true), 'owner_id' => $node ? (int) $node['user_id'] : null, 'node' => $node];
}
/** Units that report directly to this person. HQ ($userId null) gets every unit with no leader above it. */
function rp_direct_units(?int $userId): array { return array_values(array_filter(rp_tree(), fn($n) => $n['sup_user'] === $userId)); }
function rp_is_above(array $u, array $node): bool {     // anyone up the chain may read
  if (is_hq($u)) return true;
  $seat = current_seat((int) $u['id']);
  return $seat && $seat['network'] === $node['network'] && (int) $seat['rank_no'] < (int) $node['rank_no'] && str_starts_with($node['unit_path'], $seat['unit_path']);
}

/** Reporting period for a template. $when = 'YYYY-MM' for monthly, otherwise the latest closed period. */
function rp_period(string $cadence, ?string $when = null): array {
  $d = rp_today(); $cfg = rp_config();
  if ($cadence === 'monthly') {
    $start = ($when && preg_match('/^\d{4}-\d{2}$/', $when)) ? new DateTimeImmutable($when . '-01') : $d->modify('first day of last month');
    $end = $start->modify('last day of this month');
    $due = $start->modify('first day of next month')->modify('+' . (max(1, min(28, (int) $cfg['due_day'])) - 1) . ' days');
    return [$start->format('Y-m-d'), $end->format('Y-m-d'), $due->format('Y-m-d')];
  }
  if ($cadence === 'weekly') {
    $start = $d->modify('monday last week'); $end = $start->modify('+6 days');
    return [$start->format('Y-m-d'), $end->format('Y-m-d'), $end->modify('+2 days')->format('Y-m-d')];
  }
  return [$d->format('Y-m-d'), $d->format('Y-m-d'), null];
}
function rp_scope_sql(array $n): array {
  $w = 'ou.path LIKE ? AND u.network=?'; $p = [$n['unit_path'] . '%', $n['network']];
  if ($n['position_level'] === 'lga') { $w .= ' AND u.lga=?'; $p[] = $n['area_label']; }
  if (in_array($n['position_level'], ['community_cluster', 'chapter'], true)) { $w .= ' AND u.community=?'; $p[] = $n['area_label']; }
  return [$w, $p];
}
/** The system figures shown inside a report. Counted live for a draft, frozen at submission. */
function rp_snapshot(array $node, string $from, string $to, string $template): array {
  [$w, $p] = rp_scope_sql($node); $a = $from . ' 00:00:00'; $b = $to . ' 23:59:59';
  $m = row("SELECT COALESCE(SUM(u.created_at<?),0) opening, COALESCE(SUM(u.created_at>=? AND u.created_at<=?),0) newm, COALESCE(SUM(u.created_at<=?),0) closing,
                   COALESCE(SUM(u.created_at<=? AND u.last_seen_at>=?),0) active
            FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND $w", array_merge([$a, $a, $b, $b, $b, gmdate('Y-m-d H:i:s', strtotime($b) - 30 * 86400)], $p));
  $l = row("SELECT COUNT(*) filled, COALESCE(SUM(s.starts_at>=? AND s.starts_at<=?),0) appointments, COALESCE(SUM(u.last_seen_at IS NULL OR u.last_seen_at<?),0) inactive
            FROM seats s JOIN org_units ou ON ou.id=s.org_unit_id JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND s.network=? AND ou.path LIKE ?",
    [$a, $b, gmdate('Y-m-d H:i:s', strtotime($b) - 30 * 86400), $node['network'], $node['unit_path'] . '%']);
  $exits = (int) val('SELECT COUNT(*) FROM seats s JOIN org_units ou ON ou.id=s.org_unit_id WHERE s.network=? AND ou.path LIKE ? AND s.ends_at>=? AND s.ends_at<=?', [$node['network'], $node['unit_path'] . '%', $a, $b]);
  $act = row('SELECT COUNT(*) total, COALESCE(SUM(r.people_reached),0) people, COALESCE(SUM(r.follow_up_action<>\'\' AND r.follow_up_done=0),0) fu, COALESCE(SUM(r.escalate=1),0) esc
              FROM activity_records r JOIN org_units ou ON ou.id=r.org_unit_id WHERE r.network=? AND ou.path LIKE ? AND r.happened_at>=? AND r.happened_at<=?', [$node['network'], $node['unit_path'] . '%', $a, $b]);
  $iss = row("SELECT COALESCE(SUM(i.status NOT IN ('resolved','closed')),0) open, COALESCE(SUM(i.status NOT IN ('resolved','closed') AND i.severity IN ('red','critical')),0) red, COALESCE(SUM(i.resolved_at>=? AND i.resolved_at<=?),0) resolved
              FROM issues i JOIN org_units ou ON ou.id=i.org_unit_id WHERE i.network=? AND ou.path LIKE ?", [$a, $b, $node['network'], $node['unit_path'] . '%']);
  $tpl = rp_template($template); $units = array_values(array_filter(rp_direct_units((int) $node['user_id']), fn($n) => !$tpl || rp_applies($tpl, $n['position_level'])));
  $c = ['units' => count($units), 'expected' => count($units), 'submitted' => 0, 'on_time' => 0, 'approved' => 0, 'returned' => 0, 'overdue' => 0, 'not_submitted' => 0];
  $pastDue = $tpl ? (rp_period($tpl['cadence'], $tpl['cadence'] === 'monthly' ? substr($from, 0, 7) : null)[2] ?? null) : null; $late = $pastDue && rp_today()->format('Y-m-d') > $pastDue;
  foreach ($units as $un) {
    $r = row("SELECT status, on_time, due_on FROM report_instances WHERE unit_key=? AND template=? AND period_start=? AND status<>'superseded' ORDER BY version DESC LIMIT 1", [$un['unit_key'], $template, $from]);
    if ($r && in_array($r['status'], ['submitted', 'approved', 'escalated'], true)) { $c['submitted']++; if ((int) $r['on_time'] === 1) $c['on_time']++; if ($r['status'] === 'approved') $c['approved']++; }
    elseif ($r && $r['status'] === 'returned') { $c['returned']++; if ($late) $c['overdue']++; }
    else { $c['not_submitted']++; if ($late) $c['overdue']++; }
  }
  $i = fn($v) => (int) $v;
  return [
    'leadership' => ['filled' => $i($l['filled']), 'active' => $i($l['filled']) - $i($l['inactive']), 'inactive' => $i($l['inactive']), 'appointments' => $i($l['appointments']), 'exits' => $exits],
    'membership' => ['opening' => $i($m['opening']), 'new' => $i($m['newm']), 'closing' => $i($m['closing']), 'active' => $i($m['active'])],
    'compliance' => $c,
    'issues' => ['open' => $i($iss['open']), 'red_critical' => $i($iss['red']), 'resolved' => $i($iss['resolved'])],
    'tasks' => tasks_snapshot($node, $a, $b), 'meetings' => meetings_snapshot($node, $a, $b),
    'activities' => ['total' => $i($act['total']), 'people_reached' => $i($act['people']), 'follow_ups_open' => $i($act['fu']), 'escalations' => $i($act['esc'])],
  ];
}
/** Does this report form apply to a seat at this level? The forms name the levels that file them. */
function rp_applies(array $t, string $positionLevel): bool {
  $levels = $t['definition']['levels'] ?? []; if (!$levels) return true;
  return in_array($positionLevel === 'lga' ? 'district_lga' : $positionLevel, $levels, true);
}
function rp_template(string $code): ?array {
  $t = row('SELECT * FROM report_templates WHERE code=? AND is_active=1', [$code]);
  if (!$t) return null;
  $def = json_decode((string) $t['definition'], true);
  $t['definition'] = is_array($def) ? $def : ['sections' => []];
  return $t;
}

/* Reminders. Run once a day. Days are counted against the monthly report's due date. */
function rp_reminders_run(): array {
  $t = rp_template('monthly_leadership'); if (!$t) return ['notices' => 0, 'escalations' => 0, 'note' => 'no monthly template loaded'];
  $cfg = rp_config(); [$from, $to, $due] = rp_period('monthly');
  $days = (int) floor((strtotime(rp_today()->format('Y-m-d')) - strtotime($due)) / 86400);   // negative = days still to go
  $label = (new DateTimeImmutable($from))->format('F Y'); $n = 0; $e = 0;
  foreach (rp_tree() as $node) {
    if (!rp_applies($t, $node['position_level'])) continue;
    $r = row("SELECT status FROM report_instances WHERE unit_key=? AND template='monthly_leadership' AND period_start=? AND status<>'superseded' ORDER BY version DESC LIMIT 1", [$node['unit_key'], $from]);
    if ($r && in_array($r['status'], ['submitted', 'approved', 'escalated'], true)) continue;
    $returned = $r && $r['status'] === 'returned';
    if ($days <= 0 && in_array(-$days, array_map('intval', (array) $cfg['before']), true)) {
      notify((int) $node['user_id'], 'report', $days === 0 ? "Your $label report is due today" : "Your $label report is due in " . (-$days) . ((-$days) === 1 ? ' day' : ' days'), $returned ? 'It was returned for correction. Open it, correct it and resubmit.' : 'Open Reporting to complete and submit it.', '#/reporting'); $n++;
    }
    if ($days > 0 && in_array($days, array_map('intval', (array) $cfg['overdue']), true)) {
      notify((int) $node['user_id'], 'report', "Your $label report is $days " . ($days === 1 ? 'day' : 'days') . ' overdue', 'Submit it now, or record a nil declaration with the reason.', '#/reporting'); $n++;
    }
    if ($days === (int) $cfg['supervisor_day']) {
      $msg = [trim($node['first_name'] . ' ' . $node['last_name']) . "'s $label report is overdue", $node['title'] . ', ' . rp_unit_label($node) . ". It was due $days days ago.", '#/reporting/compliance'];
      if ($node['sup_user']) notify((int) $node['sup_user'], 'report', ...$msg); else notify_hq('report', ...$msg);
      $e++;
    }
    if ($days === (int) $cfg['escalation_day'] && $node['sup_user']) { notify_hq('report', 'Escalation: ' . $node['title'] . ', ' . rp_unit_label($node) . " has not reported for $label", "Due $days days ago. Supervisor: " . $node['sup_name'] . '.', '#/reporting/compliance'); $e++; }
  }
  return ['notices' => $n, 'escalations' => $e, 'days_from_due' => $days];
}
function rp_reminders_daily(array &$done): void {
  if ((int) rp_today()->format('G') < 7 || !(int) rp_config()['auto']) return;
  $r = cron_once('report_reminders', function () { $x = rp_reminders_run(); return ($x['notices'] ?? 0) . ' notices, ' . ($x['escalations'] ?? 0) . ' escalations'; });
  if ($r !== null) $done['report_reminders'] = $r;
}
