<?php
declare(strict_types=1);
/* Reporting and Accountability: API routes. */

const RP_CATEGORIES = ['member_engagement', 'mobilisation', 'leadership_followup', 'recruitment_support', 'learning_support', 'meeting', 'programme', 'stakeholder', 'digital_support', 'communication', 'task_completed', 'issue', 'other'];

function rp_report(int $id): array { $r = row('SELECT * FROM report_instances WHERE id=?', [$id]); if (!$r) fail(404, 'That report was not found.', 'not_found'); return $r; }
function rp_node_for(array $r): ?array { foreach (rp_tree() as $n) if ($n['unit_key'] === $r['unit_key']) return $n; return null; }
/** What the signed-in person may do with a report. */
function rp_rights(array $u, array $r): array {
  $me = rp_me($u); $node = rp_node_for($r); $same = $me['unit_key'] === $r['unit_key'];
  $owner = (int) $r['owner_id'] === (int) $u['id'];
  $supervisor = $node && $node['sup_user'] !== null && (int) $node['sup_user'] === (int) $u['id'];
  $view = $owner || $same || is_hq($u) || ($node && rp_is_above($u, $node));
  $open = in_array($r['status'], ['draft', 'returned'], true);
  return ['view' => $view, 'edit' => $owner && $open, 'contribute' => $same && !$owner && $open,
    'review' => !$owner && in_array($r['status'], ['submitted', 'escalated'], true) && (is_hq($u) || $supervisor), 'new_version' => $owner && $r['status'] === 'approved'];
}
function rp_brief(array $r): array {
  return ['id' => (int) $r['id'], 'report_no' => $r['report_no'], 'template' => $r['template'], 'unit_label' => $r['unit_label'], 'network' => $r['network'], 'period_start' => $r['period_start'], 'period_end' => $r['period_end'],
    'due_on' => $r['due_on'], 'status' => $r['status'], 'version' => (int) $r['version'], 'submitted_at' => $r['submitted_at'], 'on_time' => $r['on_time'] === null ? null : (int) $r['on_time'] === 1, 'is_nil' => (int) $r['is_nil'] === 1, 'owner' => $r['owner_name'] ?? null];
}
const RP_LIST = "SELECT r.*, CONCAT(o.first_name,' ',o.last_name) owner_name FROM report_instances r JOIN users o ON o.id=r.owner_id";

route('GET', '/reporting/home', function () {
  $u = require_user(); $me = rp_me($u); $hq = is_hq($u);
  $templates = rows('SELECT code, title, cadence FROM report_templates WHERE is_active=1 ORDER BY cadence DESC, title');
  if ($me['seat']) $templates = array_values(array_filter($templates, fn($t) => rp_applies(rp_template($t['code']), $me['seat']['position_level'])));
  $out = ['is_hq' => $hq, 'templates' => $templates, 'unit' => null, 'monthly' => null, 'counts' => null, 'supervisor' => null];
  if ($me['seat']) {
    $out['unit'] = ['label' => rp_unit_label($me['seat']), 'role' => seat_title($me['seat']), 'principal' => $me['principal'], 'supervisor' => $me['node']['sup_name'] ?? 'WODDI HQ',
      'accountable' => $me['node'] ? trim($me['node']['first_name'] . ' ' . $me['node']['last_name']) : null];
    if (($mt = rp_template('monthly_leadership')) && rp_applies($mt, $me['seat']['position_level'])) {
      [$from, $to, $due] = rp_period('monthly');
      $r = row("SELECT * FROM report_instances WHERE unit_key=? AND template='monthly_leadership' AND period_start=? AND status<>'superseded' ORDER BY version DESC LIMIT 1", [$me['unit_key'], $from]);
      $out['monthly'] = ['period_start' => $from, 'due_on' => $due, 'status' => $r['status'] ?? 'not_started', 'id' => $r ? (int) $r['id'] : null, 'overdue' => !$r || in_array($r['status'], ['draft', 'returned'], true) ? rp_today()->format('Y-m-d') > $due : false];
    }
  }
  $uid = (int) $u['id']; $monthStart = rp_today()->format('Y-m-01');
  $mine = $me['unit_key'] ? "r.unit_key=" . db()->quote($me['unit_key']) : '1=0';
  $direct = rp_direct_units($hq ? null : $uid); $keys = array_map(fn($n) => db()->quote($n['unit_key']), $direct); $in = $keys ? implode(',', $keys) : "''";
  $out['counts'] = [
    'due' => (int) val("SELECT COUNT(*) FROM report_instances r WHERE $mine AND r.status IN ('draft','returned')"),
    'returned' => (int) val("SELECT COUNT(*) FROM report_instances r WHERE $mine AND r.status='returned'"),
    'approved_month' => (int) val("SELECT COUNT(*) FROM report_instances r WHERE " . ($hq ? '1=1' : $mine) . " AND r.status='approved' AND r.approved_at>=?", [$monthStart . ' 00:00:00']),
    'awaiting_review' => (int) val("SELECT COUNT(*) FROM report_instances r WHERE r.unit_key IN ($in) AND r.status IN ('submitted','escalated')"),
    'open_issues' => (int) val("SELECT COUNT(*) FROM issues i LEFT JOIN org_units ou ON ou.id=i.org_unit_id WHERE i.status NOT IN ('resolved','closed') AND " . ($hq ? '1=1' : ($me['seat'] ? 'i.network=' . db()->quote($me['seat']['network']) . ' AND ou.path LIKE ' . db()->quote($me['seat']['unit_path'] . '%') : 'i.raised_by=' . $uid)), []),
  ];
  if ($mt = rp_template('monthly_leadership')) $direct = array_values(array_filter($direct, fn($n) => rp_applies($mt, $n['position_level'])));
  if ($direct && $mt) {
    [$from, , $due] = rp_period('monthly'); $late = rp_today()->format('Y-m-d') > $due; $s = ['units' => count($direct), 'approved' => 0, 'submitted' => 0, 'returned' => 0, 'overdue' => 0, 'not_started' => 0];
    foreach ($direct as $n) {
      $st = val("SELECT status FROM report_instances WHERE unit_key=? AND template='monthly_leadership' AND period_start=? AND status<>'superseded' ORDER BY version DESC LIMIT 1", [$n['unit_key'], $from]);
      if ($st === 'approved') $s['approved']++; elseif (in_array($st, ['submitted', 'escalated'], true)) $s['submitted']++; elseif ($st === 'returned') $s['returned']++; elseif ($late) $s['overdue']++; else $s['not_started']++;
    }
    $out['supervisor'] = $s + ['period_start' => $from, 'due_on' => $due];
  }
  return $out;
});

route('POST', '/reporting/open', function () {
  $u = require_user(); $me = rp_me($u);
  if (!$me['seat']) fail(403, 'Formal reports belong to a leadership unit. Once HQ seats you, your unit\'s reports appear here.', 'no_unit');
  $t = rp_template(str_in('template', 40)); if (!$t) fail(404, 'That report type is not loaded.', 'no_template');
  if (!rp_applies($t, $me['seat']['position_level'])) fail(403, 'This report form is not filed at your level.', 'level');
  if (!$me['owner_id']) fail(409, 'This unit has no accountable leader seated yet, so no report can be opened for it.', 'no_leader');
  [$from, $to, $due] = rp_period($t['cadence'], str_in('period', 7) ?: null);
  if ($from > rp_today()->format('Y-m-d')) fail(422, 'That period has not started yet.', 'future');
  $r = $t['cadence'] === 'adhoc' ? null : row("SELECT id FROM report_instances WHERE unit_key=? AND template=? AND period_start=? AND status<>'superseded' ORDER BY version DESC LIMIT 1", [$me['unit_key'], $t['code'], $from]);
  if ($r) return ['id' => (int) $r['id']];
  if (!$me['principal']) fail(403, 'The accountable leader of your unit opens the report. You can add your contribution once it is open.', 'not_principal');
  $id = ins('INSERT INTO report_instances (template,unit_key,network,org_unit_id,unit_label,owner_id,period_start,period_end,due_on,status,version,answers,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$t['code'], $me['unit_key'], $me['seat']['network'], (int) $me['seat']['org_unit_id'], rp_unit_label($me['seat']), (int) $u['id'], $from, $to, $due, 'draft', 1, '{}', now(), now()]);
  q('UPDATE report_instances SET report_no=? WHERE id=?', [sprintf('RPT-%s-%05d', str_replace('-', '', substr($from, 0, 7)), $id), $id]);
  return ['id' => $id];
});

route('GET', '/reporting/report/(\d+)', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); $rights = rp_rights($u, $r);
  if (!$rights['view']) fail(403, 'This report belongs to another unit.', 'forbidden');
  $t = rp_template($r['template']); $node = rp_node_for($r);
  $frozen = json_decode((string) $r['snapshot'], true);
  $snap = is_array($frozen) ? $frozen : ($node ? rp_snapshot($node, $r['period_start'], $r['period_end'], $r['template']) : []);
  $ownerSeat = current_seat((int) $r['owner_id']);
  $snap['identity'] = ['unit' => $r['unit_label'], 'leader' => (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [(int) $r['owner_id']]), 'role' => $ownerSeat ? seat_title($ownerSeat) : '', 'supervisor' => $node['sup_name'] ?? 'WODDI HQ',
    'period_start' => $r['period_start'], 'period_end' => $r['period_end'], 'due_on' => $r['due_on'], 'report_no' => $r['report_no'], 'version' => (int) $r['version']];
  return ['report' => rp_brief($r + ['owner_name' => (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [(int) $r['owner_id']])]) + ['nil_reason' => $r['nil_reason'], 'answers' => json_decode((string) $r['answers'], true) ?: new stdClass(), 'supervisor' => $node['sup_name'] ?? 'WODDI HQ', 'frozen' => is_array($frozen)],
    'template' => $t ? ['code' => $t['code'], 'title' => $t['title'], 'cadence' => $t['cadence'], 'requires_evidence' => (int) $t['requires_evidence'] === 1, 'sections' => $t['definition']['sections'] ?? []] : null,
    'snapshot' => $snap, 'rights' => $rights,
    'evidence' => rows("SELECT e.id, e.url, e.note, e.created_at, CONCAT(u.first_name,' ',u.last_name) by_name FROM report_evidence e JOIN users u ON u.id=e.by_user WHERE e.report_id=? ORDER BY e.id", [(int) $id]),
    'contributions' => rows("SELECT c.id, c.note, c.created_at, CONCAT(u.first_name,' ',u.last_name) by_name FROM report_contributions c JOIN users u ON u.id=c.user_id WHERE c.report_id=? ORDER BY c.id", [(int) $id]),
    'reviews' => rows("SELECT v.action, v.note, v.version, v.created_at, CONCAT(u.first_name,' ',u.last_name) by_name FROM report_reviews v JOIN users u ON u.id=v.by_user WHERE v.report_id=? ORDER BY v.id", [(int) $id])];
});

route('POST', '/reporting/report/(\d+)/save', function ($id) {
  $u = require_user(); $r = rp_report((int) $id);
  if (!rp_rights($u, $r)['edit']) fail(403, 'This report can no longer be edited.', 'locked');
  $answers = body()['answers'] ?? []; if (!is_array($answers)) $answers = [];
  $json = json_encode($answers, JSON_UNESCAPED_UNICODE); if (strlen($json) > 400000) fail(413, 'This report is too large to save. Shorten the longest answers.', 'too_large');
  q('UPDATE report_instances SET answers=?, is_nil=?, nil_reason=?, updated_at=? WHERE id=?', [$json, empty(body()['is_nil']) ? 0 : 1, str_in('nil_reason', 400), now(), (int) $id]);
  return ['ok' => true, 'saved_at' => now()];
});

/** Returns [errors, warnings]. Rules follow the reporting specification: required answers, declaration, evidence, nil reason, five priorities, red risks owned. */
function rp_validate(array $r, array $t): array {
  $a = json_decode((string) $r['answers'], true) ?: []; $err = []; $warn = [];
  $node = rp_node_for($r); $snap = $node ? rp_snapshot($node, $r['period_start'], $r['period_end'], $r['template']) : [];
  // "requiredWhen": "compliance.overdue>0" makes an answer compulsory only when the counted figure says so
  $when = function (string $rule) use ($snap): bool { return preg_match('/^(\w+)\.(\w+)\s*>\s*(\d+)$/', trim($rule), $m) ? ((int) ($snap[$m[1]][$m[2]] ?? 0) > (int) $m[3]) : false; };
  if ((int) $r['is_nil'] === 1) { if (trim($r['nil_reason']) === '') $err[] = 'Give the reason for the nil declaration.'; }
  else foreach ($t['definition']['sections'] ?? [] as $sec) {
    if (($sec['conditional'] ?? '') === 'finance' && empty($a['has_finance'])) continue;
    foreach ($sec['fields'] ?? [] as $f) {
      $v = $a[$f['key']] ?? null; $empty = $v === null || $v === '' || $v === false || (is_array($v) && !$v);
      if (!empty($f['required']) && $empty) $err[] = 'Required: ' . ($sec['key'] ?? '') . '. ' . ($f['label'] ?? $f['key']);
      if ($empty && !empty($f['requiredWhen']) && $when((string) $f['requiredWhen'])) $err[] = 'Required because the figures in section ' . ($sec['key'] ?? '') . ' show items overdue: ' . ($f['label'] ?? $f['key']);
      if (($f['type'] ?? '') === 'rows' && is_array($v) && !empty($f['max']) && count($v) > (int) $f['max']) $err[] = ($sec['key'] ?? '') . '. ' . ($sec['title'] ?? '') . ': at most ' . (int) $f['max'] . ' rows.';
      if (($f['type'] ?? '') === 'rows' && is_array($v)) {
        if (empty($f['max']) && stripos($f['key'], 'priorit') !== false && count($v) > 5) $err[] = 'Maximum five priorities.';
        foreach ($v as $row) {
          $sev = strtolower((string) ($row['severity'] ?? $row['rating'] ?? ''));
          if (in_array($sev, ['red', 'critical'], true) && (trim((string) ($row['action'] ?? $row['action_taken'] ?? '')) === '' || trim((string) ($row['owner'] ?? '')) === '')) { $err[] = 'Every red or critical issue needs the action already taken and an owner.'; break; }
        }
      }
    }
  }
  if (rp_has_field($t, 'declaration') && empty($a['declaration']) && (int) $r['is_nil'] === 1) $err[] = 'Tick the leader declaration.';
  if ((int) $t['requires_evidence'] === 1 && !(int) val('SELECT COUNT(*) FROM report_evidence WHERE report_id=?', [(int) $r['id']])) $err[] = 'This report type requires at least one piece of evidence.';
  if (!(int) val('SELECT COUNT(*) FROM activity_records WHERE user_id=? AND happened_at>=? AND happened_at<=?', [(int) $r['owner_id'], $r['period_start'] . ' 00:00:00', $r['period_end'] . ' 23:59:59']) && (int) $r['is_nil'] === 0) $warn[] = 'No operational activity records exist for this period; achievements claimed here are not backed by records.';
  if (preg_match('/\b(safeguard|abuse|harass|assault|rape|violence|complaint against)/i', json_encode($a))) $warn[] = 'Wording suggests a safeguarding or complaint matter. Those go through the confidential channel to HQ, never a routine report.';
  return [array_values(array_unique($err)), $warn];
}
function rp_has_field(array $t, string $key): bool { foreach ($t['definition']['sections'] ?? [] as $s) foreach ($s['fields'] ?? [] as $f) if (($f['key'] ?? '') === $key) return true; return false; }

route('POST', '/reporting/report/(\d+)/submit', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); $t = rp_template($r['template']);
  if (!rp_rights($u, $r)['edit'] || !$t) fail(403, 'This report can no longer be submitted.', 'locked');
  [$err, $warn] = rp_validate($r, $t);
  if ($err) return ['ok' => true, 'submitted' => false, 'errors' => $err, 'warnings' => $warn];
  $node = rp_node_for($r); $snap = $node ? rp_snapshot($node, $r['period_start'], $r['period_end'], $r['template']) : [];
  $onTime = $r['due_on'] ? (int) (rp_today()->format('Y-m-d') <= $r['due_on']) : 1; $again = $r['status'] === 'returned';
  q("UPDATE report_instances SET status='submitted', snapshot=?, submitted_at=?, on_time=COALESCE(on_time, ?), updated_at=? WHERE id=?", [json_encode($snap), now(), $onTime, now(), (int) $id]);
  q('INSERT INTO report_reviews (report_id,version,action,note,by_user,created_at) VALUES (?,?,?,?,?,?)', [(int) $id, (int) $r['version'], $again ? 'resubmit' : 'submit', '', (int) $u['id'], now()]);
  $title = trim($u['first_name'] . ' ' . $u['last_name']) . ($again ? ' resubmitted ' : ' submitted ') . $r['report_no']; $bodyText = $r['unit_label'] . '. Open it to review.';
  if ($node && $node['sup_user']) notify((int) $node['sup_user'], 'report', $title, $bodyText, '#/reporting/r/' . $id); else notify_hq('report', $title, $bodyText, '#/reporting/r/' . $id);
  log_activity((int) $u['id'], (int) $u['id'], 'report_submitted', $r['report_no'] . ' · ' . $r['unit_label']);
  after_response(fn() => push_drain(20));
  return ['ok' => true, 'submitted' => true, 'report_no' => $r['report_no'], 'warnings' => $warn];
});

route('POST', '/reporting/report/(\d+)/review', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); $act = str_in('action', 12); $note = str_in('note', 1000);
  if (!rp_rights($u, $r)['review']) fail(403, 'You are not the reviewer of this report.', 'forbidden');
  if (!in_array($act, ['approve', 'return', 'escalate'], true)) fail(422, 'Choose approve, return or escalate.', 'action');
  if ($act === 'return' && mb_strlen($note) < 3) fail(422, 'Give the reason for returning the report.', 'note');
  if ($act === 'escalate' && is_hq($u)) fail(422, 'HQ is the last level. Approve it or return it.', 'action');
  $status = ['approve' => 'approved', 'return' => 'returned', 'escalate' => 'escalated'][$act];
  q('UPDATE report_instances SET status=?, approved_at=?, updated_at=? WHERE id=?', [$status, $act === 'approve' ? now() : null, now(), (int) $id]);
  q('INSERT INTO report_reviews (report_id,version,action,note,by_user,created_at) VALUES (?,?,?,?,?,?)', [(int) $id, (int) $r['version'], $act, $note, (int) $u['id'], now()]);
  $who = trim($u['first_name'] . ' ' . $u['last_name']);
  notify((int) $r['owner_id'], 'report', $r['report_no'] . ' was ' . ['approve' => 'approved', 'return' => 'returned for correction', 'escalate' => 'escalated to HQ'][$act], $act === 'return' ? "$who wrote: $note" : "Reviewed by $who.", '#/reporting/r/' . $id);
  if ($act === 'escalate') notify_hq('report', $r['report_no'] . ' escalated by ' . $who, $r['unit_label'] . ($note !== '' ? ". $note" : ''), '#/reporting/r/' . $id);
  after_response(fn() => push_drain(20));
  return ['ok' => true, 'status' => $status];
});

route('POST', '/reporting/report/(\d+)/evidence', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); $rights = rp_rights($u, $r);
  if (!$rights['edit'] && !$rights['contribute']) fail(403, 'Evidence can only be added while the report is open.', 'locked');
  $url = str_in('url', 600); if (!preg_match('#^https?://\S+$#', $url)) fail(422, 'Enter a full link starting with http.', 'url');
  q('INSERT INTO report_evidence (report_id,url,note,by_user,created_at) VALUES (?,?,?,?,?)', [(int) $id, $url, str_in('note', 300), (int) $u['id'], now()]);
});
route('POST', '/reporting/report/(\d+)/evidence/(\d+)/delete', function ($id, $eid) {
  $u = require_user(); $r = rp_report((int) $id); if (!rp_rights($u, $r)['edit']) fail(403, 'Evidence can only be removed while the report is open.', 'locked');
  q('DELETE FROM report_evidence WHERE id=? AND report_id=?', [(int) $eid, (int) $id]);
});
route('POST', '/reporting/report/(\d+)/contribution', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); if (!rp_rights($u, $r)['contribute']) fail(403, 'Contributions are open to deputies and assistants of this unit while the report is in draft.', 'forbidden');
  $note = str_in('note', 2000); if (mb_strlen($note) < 3) fail(422, 'Write your contribution.', 'note');
  q('INSERT INTO report_contributions (report_id,user_id,note,created_at) VALUES (?,?,?,?)', [(int) $id, (int) $u['id'], $note, now()]);
  notify((int) $r['owner_id'], 'report', trim($u['first_name'] . ' ' . $u['last_name']) . ' added to ' . $r['report_no'], mb_substr($note, 0, 200), '#/reporting/r/' . $id);
});
route('POST', '/reporting/report/(\d+)/new-version', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); if (!rp_rights($u, $r)['new_version']) fail(403, 'Only the accountable leader can correct an approved report.', 'forbidden');
  q("UPDATE report_instances SET status='superseded', report_no=CONCAT(report_no,'-v',version), updated_at=? WHERE id=?", [now(), (int) $id]);
  $new = ins('INSERT INTO report_instances (report_no,template,unit_key,network,org_unit_id,unit_label,owner_id,period_start,period_end,due_on,status,version,answers,is_nil,nil_reason,on_time,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [$r['report_no'], $r['template'], $r['unit_key'], $r['network'], (int) $r['org_unit_id'], $r['unit_label'], (int) $r['owner_id'], $r['period_start'], $r['period_end'], $r['due_on'], 'draft', (int) $r['version'] + 1, $r['answers'], (int) $r['is_nil'], $r['nil_reason'], $r['on_time'], now(), now()]);
  q('INSERT INTO report_evidence (report_id,url,note,by_user,created_at) SELECT ?, url, note, by_user, created_at FROM report_evidence WHERE report_id=?', [$new, (int) $id]);
  q('INSERT INTO report_reviews (report_id,version,action,note,by_user,created_at) VALUES (?,?,?,?,?,?)', [$new, (int) $r['version'] + 1, 'version', '', (int) $u['id'], now()]);
  return ['id' => $new];
});

route('GET', '/reporting/list', function () {
  $u = require_user(); $me = rp_me($u); $tab = $_GET['tab'] ?? 'mine'; $hq = is_hq($u);
  $keys = array_map(fn($n) => db()->quote($n['unit_key']), rp_direct_units($hq ? null : (int) $u['id'])); $in = $keys ? implode(',', $keys) : "''";
  $mine = $me['unit_key'] ? 'r.unit_key=' . db()->quote($me['unit_key']) : '1=0';
  $where = match ($tab) {
    'review' => "r.unit_key IN ($in) AND r.status IN ('submitted','escalated','returned')" . ($hq ? " OR r.status='escalated'" : ''),
    'archive' => ($hq ? '1=1' : "($mine OR r.unit_key IN ($in))") . " AND r.status IN ('approved','superseded')",
    default => "$mine AND r.status NOT IN ('approved','superseded')",
  };
  return ['items' => array_map('rp_brief', rows(RP_LIST . " WHERE $where ORDER BY r.updated_at DESC LIMIT 60"))];
});

route('GET', '/reporting/compliance', function () {
  $u = require_user(); $hq = is_hq($u); $me = rp_me($u); $tree = rp_tree();
  $of = (int) ($_GET['of'] ?? 0); $parent = null;
  if ($of) { foreach ($tree as $n) if ((int) $n['user_id'] === $of) $parent = $n; if (!$parent || !rp_is_above($u, $parent) && (int) $u['id'] !== $of) fail(403, 'That unit is outside your scope.', 'forbidden'); }
  elseif (!$hq) { if (!$me['node'] || !$me['principal']) return ['rows' => [], 'period_start' => null, 'due_on' => null, 'parent' => null, 'loaded' => (bool) rp_template('monthly_leadership')]; $parent = $me['node']; }
  [$from, , $due] = rp_period('monthly'); $late = rp_today()->format('Y-m-d') > $due; $out = [];
  $mt = rp_template('monthly_leadership');
  foreach (rp_direct_units($parent ? (int) $parent['user_id'] : null) as $n) {
    if ($mt && !rp_applies($mt, $n['position_level'])) continue;
    $r = row("SELECT id, status, submitted_at, on_time, report_no FROM report_instances WHERE unit_key=? AND template='monthly_leadership' AND period_start=? AND status<>'superseded' ORDER BY version DESC LIMIT 1", [$n['unit_key'], $from]);
    $st = $r['status'] ?? 'not_started';
    $out[] = ['user_id' => (int) $n['user_id'], 'first_name' => $n['first_name'], 'last_name' => $n['last_name'], 'has_photo' => (int) $n['has_photo'], 'title' => $n['title'], 'unit' => rp_unit_label($n), 'country_code' => $n['country_code'],
      'status' => $st, 'report_id' => $r ? (int) $r['id'] : null, 'report_no' => $r['report_no'] ?? null, 'submitted_at' => $r['submitted_at'] ?? null, 'late' => $r ? ((int) ($r['on_time'] ?? 1) === 0) : false,
      'overdue' => in_array($st, ['not_started', 'draft', 'returned'], true) && $late, 'children' => count(rp_direct_units((int) $n['user_id'])), 'last_seen_at' => $n['last_seen_at']];
  }
  return ['rows' => $out, 'period_start' => $from, 'due_on' => $due, 'loaded' => (bool) rp_template('monthly_leadership'),
    'parent' => $parent ? ['user_id' => (int) $parent['user_id'], 'name' => trim($parent['first_name'] . ' ' . $parent['last_name']), 'unit' => rp_unit_label($parent), 'sup_user' => $parent['sup_user']] : null];
});

/* ---- operational records: record once, close to the time it happens ---- */
route('POST', '/reporting/activity', function () {
  $u = require_user(); if (is_hq($u) && !$u['network']) fail(403, 'Operational records are kept by members and leaders in the field.', 'forbidden');
  $seat = current_seat((int) $u['id']); $cat = in_array(str_in('category', 40), RP_CATEGORIES, true) ? str_in('category', 40) : 'other';
  $what = str_in('what', 1500); $result = str_in('result', 1000);
  if (mb_strlen($what) < 5 || mb_strlen($result) < 3) fail(422, 'Say what happened and the result.', 'empty');
  $when = strtotime(str_in('happened_at', 20)) ?: time(); if ($when > time() + 86400) fail(422, 'The date cannot be in the future.', 'date');
  $fuDue = preg_match('/^\d{4}-\d{2}-\d{2}$/', str_in('follow_up_due', 10)) ? str_in('follow_up_due', 10) : null;
  $id = ins('INSERT INTO activity_records (user_id,org_unit_id,network,category,happened_at,what,people_reached,people_category,result,follow_up_action,follow_up_due,evidence_ref,escalate,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [(int) $u['id'], $seat ? (int) $seat['org_unit_id'] : ($u['org_unit_id'] ? (int) $u['org_unit_id'] : null), $seat['network'] ?? $u['network'], $cat, gmdate('Y-m-d H:i:s', $when), $what, max(0, min(1000000, (int) (body()['people_reached'] ?? 0))), str_in('people_category', 160), $result, str_in('follow_up_action', 400), $fuDue, str_in('evidence_ref', 400), empty(body()['escalate']) ? 0 : 1, now()]);
  return ['ok' => true, 'id' => $id];
});
route('GET', '/reporting/activities', function () {
  $u = require_user(); $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  $w = is_hq($u) ? '1=1' : ($seat ? 'r.network=' . db()->quote($seat['network']) . ' AND ou.path LIKE ' . db()->quote($seat['unit_path'] . '%') : 'r.user_id=' . (int) $u['id']);
  return ['items' => rows("SELECT r.id, r.category, r.happened_at, r.what, r.people_reached, r.people_category, r.result, r.follow_up_action, r.follow_up_due, r.follow_up_done, r.evidence_ref, r.escalate, r.user_id, CONCAT(u.first_name,' ',u.last_name) by_name
                           FROM activity_records r JOIN users u ON u.id=r.user_id LEFT JOIN org_units ou ON ou.id=r.org_unit_id WHERE $w ORDER BY r.happened_at DESC LIMIT 40")];
});
route('POST', '/reporting/activity/(\d+)/done', function ($id) { $u = require_user(); q('UPDATE activity_records SET follow_up_done=1 WHERE id=? AND user_id=?', [(int) $id, (int) $u['id']]); });

/* ---- risks and escalations (non-confidential operational issues) ---- */
route('POST', '/reporting/issue', function () {
  $u = require_user(); $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  $title = str_in('title', 190); if (mb_strlen($title) < 4) fail(422, 'Give the issue a short title.', 'title');
  $sev = in_array(str_in('severity', 12), ['green', 'amber', 'red', 'critical'], true) ? str_in('severity', 12) : 'amber';
  $route = in_array(str_in('escalated_to', 16), ['supervisor', 'country', 'hofco', 'hq', 'management'], true) ? str_in('escalated_to', 16) : 'supervisor';
  if (in_array($sev, ['red', 'critical'], true) && mb_strlen(str_in('action_taken', 1000)) < 3) fail(422, 'A red or critical issue needs the action already taken.', 'action');
  $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', str_in('due_on', 10)) ? str_in('due_on', 10) : null;
  $id = ins('INSERT INTO issues (raised_by,org_unit_id,network,title,category,severity,impact,action_taken,decision_required,escalated_to,due_on,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
    [(int) $u['id'], $seat ? (int) $seat['org_unit_id'] : ($u['org_unit_id'] ? (int) $u['org_unit_id'] : null), $seat['network'] ?? $u['network'], $title, in_array(str_in('category', 40), RP_CATEGORIES, true) ? str_in('category', 40) : 'other', $sev, str_in('impact', 1000), str_in('action_taken', 1000), str_in('decision_required', 1000), $route, $due, now()]);
  $no = sprintf('ISS-%05d', $id); q('UPDATE issues SET issue_no=? WHERE id=?', [$no, $id]);
  $who = trim($u['first_name'] . ' ' . $u['last_name']); $me = rp_me($u); $text = ucfirst($sev) . ' issue raised by ' . $who . ($seat ? ', ' . rp_unit_label($seat) : '') . '.';
  $toHq = $route !== 'supervisor' || in_array($sev, ['red', 'critical'], true) || !$me['node'] || !$me['node']['sup_user'];
  if ($me['node'] && $me['node']['sup_user'] && (int) $me['node']['sup_user'] !== (int) $u['id']) notify((int) $me['node']['sup_user'], 'report', "$no: $title", $text, '#/reporting/issues');
  if ($toHq) notify_hq('report', "$no: $title", $text, '#/reporting/issues');
  after_response(fn() => push_drain(20));
  return ['ok' => true, 'issue_no' => $no];
});
route('GET', '/reporting/issues', function () {
  $u = require_user(); $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  $w = is_hq($u) ? '1=1' : ($seat ? '(i.raised_by=' . (int) $u['id'] . ' OR (i.network=' . db()->quote($seat['network']) . ' AND ou.path LIKE ' . db()->quote($seat['unit_path'] . '%') . '))' : 'i.raised_by=' . (int) $u['id']);
  return ['items' => rows("SELECT i.*, CONCAT(u.first_name,' ',u.last_name) by_name, ou.name unit FROM issues i JOIN users u ON u.id=i.raised_by LEFT JOIN org_units ou ON ou.id=i.org_unit_id WHERE $w
                           ORDER BY (i.status IN ('resolved','closed')), FIELD(i.severity,'critical','red','amber','green'), i.id DESC LIMIT 60"), 'can_manage' => is_hq($u) || (bool) $seat];
});
route('POST', '/reporting/issue/(\d+)', function ($id) {
  $u = require_user(); $i = row('SELECT i.*, ou.path FROM issues i LEFT JOIN org_units ou ON ou.id=i.org_unit_id WHERE i.id=?', [(int) $id]); if (!$i) fail(404, 'That issue was not found.', 'not_found');
  $seat = is_hq($u) ? null : current_seat((int) $u['id']);
  $ok = is_hq($u) || (int) $i['raised_by'] === (int) $u['id'] || ($seat && $seat['network'] === $i['network'] && $i['path'] && str_starts_with($i['path'], $seat['unit_path']));
  if (!$ok) fail(403, 'That issue is outside your scope.', 'forbidden');
  $st = str_in('status', 20); if (!in_array($st, ['open', 'in_progress', 'awaiting_decision', 'resolved', 'closed'], true)) fail(422, 'Unknown status.', 'status');
  q('UPDATE issues SET status=?, resolved_at=? WHERE id=?', [$st, in_array($st, ['resolved', 'closed'], true) ? now() : null, (int) $id]);
  if ((int) $i['raised_by'] !== (int) $u['id']) notify((int) $i['raised_by'], 'report', $i['issue_no'] . ' is now ' . str_replace('_', ' ', $st), $i['title'], '#/reporting/issues');
});

/* ---- HQ: settings and manual reminder run ---- */
route('GET', '/reporting/config', function () { require_hq(); return ['config' => rp_config()]; });
route('POST', '/reporting/config', function () {
  require_hq(); $b = body(); $list = fn($v) => array_values(array_unique(array_filter(array_map('intval', is_array($v) ? $v : explode(',', (string) $v)), fn($x) => $x >= 0 && $x <= 60)));
  $cfg = ['auto' => empty($b['auto']) ? 0 : 1, 'due_day' => max(1, min(28, (int) ($b['due_day'] ?? 5))), 'before' => $list($b['before'] ?? [7, 3, 0]), 'overdue' => $list($b['overdue'] ?? [1, 3, 7]), 'supervisor_day' => max(1, min(60, (int) ($b['supervisor_day'] ?? 3))), 'escalation_day' => max(1, min(60, (int) ($b['escalation_day'] ?? 7)))];
  set_setting('reporting_config', json_encode($cfg)); return ['ok' => true, 'config' => $cfg];
});
route('POST', '/reporting/run-reminders', function () { require_hq(); $r = rp_reminders_run(); return ['ok' => true] + $r + push_drain(60); });
