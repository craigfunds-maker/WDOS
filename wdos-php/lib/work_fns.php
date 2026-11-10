<?php
declare(strict_types=1);
/* Tasks and meetings: shared functions. Loaded before every route, because reports count tasks and
 * meetings and approved report priorities become tasks. */

const TASK_PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
const TASK_OPEN = "('open','in_progress','submitted')";

function work_seat(array $u): ?array {
  static $c = [];
  $id = (int) $u['id'];
  if (!array_key_exists($id, $c)) $c[$id] = is_hq($u) ? null : current_seat($id);
  return $c[$id];
}
function work_name(array $u): string { return trim($u['first_name'] . ' ' . $u['last_name']); }
function work_today(): string { return (new DateTimeImmutable(cfg('WDOS_TEST_NOW', 'now'), new DateTimeZone('Africa/Lagos')))->format('Y-m-d'); }
function work_date(string $s): ?string {
  $s = trim($s); if ($s === '') return null;
  $d = DateTime::createFromFormat('Y-m-d', substr($s, 0, 10));
  return ($d && $d->format('Y-m-d') === substr($s, 0, 10)) ? $d->format('Y-m-d') : null;
}

/** The people this person may assign a task to or call to a meeting, as a WHERE on users u. Herself is added by the caller. */
function work_people_sql(array $u): array {
  if (is_hq($u)) return ["(u.role IN ('hq_admin','super_admin') OR EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL))", []];
  $seat = work_seat($u);
  if (!$seat) return ['1=0', []];
  return ['EXISTS (SELECT 1 FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id
                   WHERE s.user_id=u.id AND s.ends_at IS NULL AND s.network=? AND o.path LIKE ? AND p.rank_no>?)',
    [$seat['network'], $seat['unit_path'] . '%', (int) $seat['rank_no']]];
}
function work_may_assign(array $by, int $assignee): bool {
  if ($assignee === (int) $by['id']) return true;
  [$w, $p] = work_people_sql($by);
  return (bool) val("SELECT 1 FROM users u WHERE u.id=? AND u.status='active' AND $w", array_merge([$assignee], $p));
}

/** Create one task. The unit and network come from the assignee's seat, so reports can count it. */
function task_create(int $byId, int $assigneeId, string $title, string $details = '', string $priority = 'normal', ?string $due = null, bool $approval = false, string $source = '', bool $tell = true): int {
  $seat = current_seat($assigneeId);
  $home = $seat ? null : row('SELECT org_unit_id, network FROM users WHERE id=?', [$assigneeId]);
  $id = ins('INSERT INTO tasks (title,details,priority,due_on,requires_approval,status,assigned_by,assignee_id,org_unit_id,network,source,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [mb_substr($title, 0, 190), $details, isset(TASK_PRIORITIES[$priority]) ? $priority : 'normal', $due, $approval ? 1 : 0, 'open', $byId, $assigneeId,
     $seat ? (int) $seat['org_unit_id'] : ($home['org_unit_id'] ?? null), $seat ? $seat['network'] : ($home['network'] ?? null), mb_substr($source, 0, 40), now(), now()]);
  if ($tell && $assigneeId !== $byId) {
    $by = row('SELECT first_name, last_name FROM users WHERE id=?', [$byId]);
    notify($assigneeId, 'task', 'New task: ' . mb_substr($title, 0, 120), 'Assigned by ' . ($by ? work_name($by) : 'WODDI HQ') . ($due ? '. Due ' . date('j M Y', strtotime($due)) : '') . '.', '#/tasks/' . $id);
  }
  return $id;
}
/** The assigner, HQ, or a leader over the task's unit. Never the assignee on that ground alone. */
function task_can_manage(array $u, array $t): bool {
  if ((int) $t['assigned_by'] === (int) $u['id'] || is_hq($u)) return true;
  $seat = work_seat($u);
  if (!$seat || (int) $t['assignee_id'] === (int) $u['id'] || !$t['org_unit_id'] || $seat['network'] !== $t['network']) return false;
  $path = (string) val('SELECT path FROM org_units WHERE id=?', [(int) $t['org_unit_id']]);
  $their = current_seat((int) $t['assignee_id']);
  return str_starts_with($path, $seat['unit_path']) && (!$their || (int) $their['rank_no'] > (int) $seat['rank_no']);
}

/** Rows of an approved report that name a piece of work become tasks for the leader who filed it. Runs once per report. */
function tasks_from_report(array $r, int $reviewerId): int {
  $a = json_decode((string) $r['answers'], true); if (!is_array($a)) return 0;
  $src = 'report:' . $r['id'];
  if (val('SELECT 1 FROM tasks WHERE source=? LIMIT 1', [$src])) return 0;
  $n = 0;
  foreach ([['priorities', 'priority'], ['actions', 'action'], ['followups', 'action']] as [$key, $col]) {
    foreach (is_array($a[$key] ?? null) ? $a[$key] : [] as $row) {
      $title = trim((string) ($row[$col] ?? '')); if (mb_strlen($title) < 3) continue;
      $bits = [];
      foreach (['deliverable' => 'Deliverable', 'decision' => 'Decision', 'owner' => 'Owner named in the report', 'support' => 'Support needed'] as $k => $label) if (trim((string) ($row[$k] ?? '')) !== '') $bits[] = $label . ': ' . trim((string) $row[$k]);
      $bits[] = 'From report ' . $r['report_no'] . ', ' . $r['unit_label'] . '.';
      task_create($reviewerId, (int) $r['owner_id'], $title, implode("\n", $bits), 'normal', work_date((string) ($row['deadline'] ?? '')), false, $src, false);
      $n++;
    }
  }
  if ($n) notify((int) $r['owner_id'], 'task', $n . ($n === 1 ? ' task' : ' tasks') . ' created from ' . $r['report_no'], 'The priorities and actions in your approved report are now in your task list.', '#/tasks');
  return $n;
}

/* ---- figures for reports -------------------------------------------------- */
function tasks_snapshot(array $node, string $a, string $b): array {
  $r = row("SELECT COALESCE(SUM(t.created_at>=? AND t.created_at<=?),0) assigned, COALESCE(SUM(t.status='completed' AND t.completed_at>=? AND t.completed_at<=?),0) completed,
                   COALESCE(SUM(t.status IN " . TASK_OPEN . "),0) open, COALESCE(SUM(t.status IN " . TASK_OPEN . " AND t.due_on IS NOT NULL AND t.due_on<?),0) overdue, COALESCE(SUM(t.status='submitted'),0) awaiting
            FROM tasks t JOIN org_units ou ON ou.id=t.org_unit_id WHERE t.network=? AND ou.path LIKE ?", [$a, $b, $a, $b, work_today(), $node['network'], $node['unit_path'] . '%']);
  return array_map('intval', $r);
}
function meetings_snapshot(array $node, string $a, string $b): array {
  $r = row("SELECT COALESCE(SUM(m.status='completed'),0) held, COALESCE(SUM(m.status='scheduled'),0) scheduled, COALESCE(SUM(m.status='cancelled'),0) cancelled
            FROM meetings m WHERE m.starts_at>=? AND m.starts_at<=? AND m.network IN (?, 'ALL') AND m.unit_path LIKE ?", [$a, $b, $node['network'], $node['unit_path'] . '%']);
  $p = (int) val("SELECT COUNT(*) FROM meeting_attendance ma JOIN meetings m ON m.id=ma.meeting_id WHERE ma.status='present' AND m.starts_at>=? AND m.starts_at<=? AND m.network IN (?, 'ALL') AND m.unit_path LIKE ?", [$a, $b, $node['network'], $node['unit_path'] . '%']);
  return array_map('intval', $r) + ['present' => $p];
}

/* ---- who a meeting is for -------------------------------------------------- */
/** WHERE on users u (joined to org_units ou on the home unit) for everyone invited to this meeting. */
function meeting_invited_sql(array $m): array {
  if ($m['audience'] === 'hq_team') return ["u.role IN ('hq_admin','super_admin')", []];
  if ($m['audience'] === 'members') {
    $w = 'u.network=? AND ou.path LIKE ?'; $p = [$m['network'], $m['unit_path'] . '%'];
    if ($m['scope_level'] === 'lga') { $w .= ' AND u.lga=?'; $p[] = $m['scope_area']; }
    if (in_array($m['scope_level'], ['community_cluster', 'chapter'], true)) { $w .= ' AND u.community=?'; $p[] = $m['scope_area']; }
    return [$w, $p];
  }
  $w = 'EXISTS (SELECT 1 FROM seats s JOIN org_units o ON o.id=s.org_unit_id WHERE s.user_id=u.id AND s.ends_at IS NULL AND o.path LIKE ?' . ($m['network'] === 'ALL' ? '' : ' AND s.network=?') . ')';
  return [$w, $m['network'] === 'ALL' ? [$m['unit_path'] . '%'] : [$m['unit_path'] . '%', $m['network']]];
}
function meeting_is_invited(array $u, array $m): bool {
  if ((int) $m['host_id'] === (int) $u['id']) return true;
  [$w, $p] = meeting_invited_sql($m);
  return (bool) val("SELECT 1 FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.id=? AND ($w)", array_merge([(int) $u['id']], $p));
}

/* ---- reminders -------------------------------------------------------------- */
function work_automations(array &$done): void {
  try {
    // once a day from 07:00 Lagos time: tasks due today, and tasks that became overdue yesterday
    if ((int) (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->format('G') >= 7) {
      $r = cron_once('task_due', function () {
        $today = work_today(); $y = date('Y-m-d', strtotime($today) - 86400); $n = 0;
        foreach (rows("SELECT id, title, assignee_id, due_on FROM tasks WHERE status IN ('open','in_progress') AND due_on IN (?, ?)", [$today, $y]) as $t) {
          notify((int) $t['assignee_id'], 'task', ($t['due_on'] === $today ? 'Due today: ' : 'Overdue: ') . mb_substr($t['title'], 0, 120), $t['due_on'] === $today ? 'This task is due today.' : 'This task was due yesterday. Complete it or tell the assigner what is holding it.', '#/tasks/' . $t['id']); $n++;
        }
        return "$n reminded";
      });
      if ($r !== null) $done['task_due'] = $r;
    }
    // meetings that start within the next hour: one reminder each
    foreach (rows("SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE m.status='scheduled' AND m.starts_at>? AND m.starts_at<=?", [now(), gmdate('Y-m-d H:i:s', time() + 3600)]) as $m) {
      if (!claim_send('meet_rem_' . $m['id'])) continue;
      [$w, $p] = meeting_invited_sql($m);
      $join = $m['audience'] === 'members' ? "u.id IN (SELECT u.id FROM (SELECT u.id FROM users u JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w) u)" : $w;
      $n = notify_many("(u.id=? OR $join)", array_merge([(int) $m['host_id']], $p), 'meeting', 'Starting soon: ' . mb_substr($m['title'], 0, 120), 'Begins at ' . gmdate('H:i', strtotime($m['starts_at'])) . ' GMT. Open WDOS for the ' . ($m['mode'] === 'in_person' ? 'address.' : 'link.'), '#/meetings/' . $m['id']);
      $done['meeting_' . $m['id']] = "$n reminded";
    }
  } catch (PDOException $e) { error_log('[wdos work] ' . $e->getMessage()); }
}
