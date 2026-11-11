<?php
declare(strict_types=1);
/* Routes for the Founder's amendments: policy rules, chapters, chapter spaces, the member journey, wider audiences. */

/* ------------------------------------------------------------ policy rules -- */
route('GET', '/policy', function () {
  require_hq(); $rules = [];
  foreach (POLICY_DEFS as $k => $d) $rules[] = ['key' => $k, 'label' => $d[0], 'type' => $d[1], 'choices' => $d[1] === 'choice' ? $d[2] : null, 'min' => $d[1] === 'int' ? $d[2] : null, 'max' => $d[1] === 'int' ? $d[3] : null, 'value' => policy($k)];
  $own = []; foreach (rows("SELECT DISTINCT rule_key FROM policy_rules WHERE rule_key LIKE 'chapter_capacity@%'") as $r) { $cc = substr($r['rule_key'], -2); if ((int) policy($r['rule_key']) > 0) $own[] = ['country_code' => $cc, 'country' => country_name($cc), 'capacity' => (int) policy($r['rule_key'])]; }
  return ['rules' => $rules, 'capacity_by_country' => $own,
    'audit' => rows("SELECT a.action, a.detail, a.created_at, COALESCE(CONCAT(u.first_name,' ',u.last_name),'WDOS') actor FROM activity_log a LEFT JOIN users u ON u.id=a.actor_id WHERE a.action IN ('policy_changed','chapter_created','chapter_merged','chapter_split','chapter_transfer','chapter_activated','chapter_placement','member_activated','journey_exception_active','journey_exception_restricted','post_hidden','post_restored','pin_reset_by_leader','appointed','seat_closed','announcement','announcement_scheduled') ORDER BY a.id DESC LIMIT 40"),
    'history' => rows("SELECT p.rule_key, p.rule_value, p.effective_from, p.note, p.created_at, COALESCE(CONCAT(u.first_name,' ',u.last_name),'WDOS') changed_by FROM policy_rules p LEFT JOIN users u ON u.id=p.changed_by ORDER BY p.id DESC LIMIT 60"),
    'decisions' => array_map(fn($d) => ['key' => $d[0], 'title' => $d[1], 'text' => $d[2], 'current' => $d[0] !== '' ? policy($d[0]) : ''], POLICY_DECISIONS)];
});
route('POST', '/policy', function () {
  $me = require_hq(); $k = str_in('key', 60); $v = trim((string) (body()['value'] ?? '')); $d = POLICY_DEFS[$k] ?? null;
  if (preg_match('/^chapter_capacity@[A-Z]{2}$/', $k)) $d = ['Capacity for one country', 'int', 0, 500];      // 0 removes the country's own figure
  if (!$d) fail(422, 'That rule does not exist.', 'key');
  if ($d[1] === 'text') { $v = mb_substr($v, 0, 600); if (str_ends_with($k, '_url') && $v !== '' && !preg_match('#^(https://|/)#', $v)) fail(422, 'Give a link that starts with https://', 'value'); }
  elseif ($d[1] === 'int') { if (!ctype_digit($v) || (int) $v < $d[2] || (int) $v > $d[3]) fail(422, "Enter a whole number from {$d[2]} to {$d[3]}.", 'value'); }
  elseif (!isset($d[2][$v])) fail(422, 'Choose one of the listed options.', 'value');
  $from = str_in('effective_from', 10); $dt = DateTime::createFromFormat('Y-m-d', $from);
  if (!$dt || $dt->format('Y-m-d') !== $from) $from = gmdate('Y-m-d');
  if ($from < gmdate('Y-m-d')) fail(422, 'A rule cannot start in the past.', 'effective_from');
  if (str_starts_with($k, 'chapter_capacity') && (int) $v > 0 && (int) $v <= policy_int('chapter_min_active')) fail(422, 'Capacity must be above the minimum.', 'value');
  q('INSERT INTO policy_rules (rule_key,rule_value,effective_from,note,changed_by,created_at) VALUES (?,?,?,?,?,?)', [$k, $v, $from, str_in('note', 400), (int) $me['id'], now()]);
  log_activity((int) $me['id'], null, 'policy_changed', "$k = $v from $from");
  if ($k === 'wnnn_country_title' && $from <= gmdate('Y-m-d')) { q("UPDATE positions SET title_wnnn=? WHERE code='country_rep'", [$v]); q("UPDATE positions SET title_wnnn=? WHERE code='deputy_country_rep'", ['Deputy ' . $v]); }
  return ['ok' => true];
});

/* ---------------------------------------------------------------- chapters -- */
/** Filters shared by the directory and the drilldown. Leaders are held to their own scope. */
function chapters_where(array $u): array {
  $w = ["c.status NOT IN ('merged','closed')"]; $p = [];
  if (!is_hq($u)) {
    $seat = current_seat((int) $u['id']); if (!$seat) fail(403, 'The chapter directory is for volunteer leaders and WODDI HQ.', 'forbidden');
    [$sw, $sp] = chapter_scope_sql($seat); $w[] = "($sw)"; $p = array_merge($p, $sp);
  }
  foreach (['network' => 'c.network', 'country' => 'c.country_code', 'state' => 'c.state_region', 'lga' => 'c.lga', 'community' => 'c.community', 'status' => 'c.status'] as $g => $col) {
    if (isset($_GET[$g]) && $_GET[$g] !== '') { $w[] = "$col=?"; $p[] = $_GET[$g] === '(none)' ? '' : (string) $_GET[$g]; }
  }
  if (($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w[] = '(c.name LIKE ? OR c.code LIKE ?)'; $p[] = "%$s%"; $p[] = "%$s%"; }
  return [implode(' AND ', $w), $p];
}
route('GET', '/chapters', function () {
  $u = require_user(); [$w, $p] = chapters_where($u);
  $page = max(1, (int) ($_GET['page'] ?? 1)); $size = 30; $total = (int) val("SELECT COUNT(*) FROM chapters c WHERE $w", $p);
  $items = array_map('chapter_shape', rows("SELECT c.* FROM chapters c WHERE $w ORDER BY c.country_code, c.state_region, c.lga, c.community, c.network, c.seq LIMIT $size OFFSET " . (($page - 1) * $size), $p));
  $lead = [];
  if ($items) foreach (rows("SELECT s.chapter_id, u.first_name, u.last_name FROM seats s JOIN users u ON u.id=s.user_id JOIN positions pp ON pp.code=s.position_code WHERE s.ends_at IS NULL AND s.chapter_id IN (" . implode(',', array_column($items, 'id')) . ") ORDER BY pp.rank_no DESC") as $r) $lead[(int) $r['chapter_id']] = trim($r['first_name'] . ' ' . $r['last_name']);
  foreach ($items as &$i) $i['leader'] = $lead[$i['id']] ?? ''; unset($i);
  $s = row("SELECT COUNT(*) total, COALESCE(SUM(c.status='developing'),0) developing, COALESCE(SUM(c.status='eligible'),0) eligible, COALESCE(SUM(c.status='active'),0) active, COALESCE(SUM(c.status='full'),0) `full`,
                   COALESCE(SUM(c.members),0) members, COALESCE(SUM(c.members>=? AND c.members<?),0) plan_next
            FROM chapters c WHERE $w", array_merge([policy_int('chapter_plan_next'), policy_int('chapter_capacity')], $p));
  $s['no_leader'] = (int) val("SELECT COUNT(*) FROM chapters c WHERE $w AND NOT EXISTS (SELECT 1 FROM seats s WHERE s.chapter_id=c.id AND s.ends_at IS NULL)", $p);
  $out = ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $size)), 'summary' => array_map('intval', $s), 'is_hq' => is_hq($u), 'as_of' => now(),
    'rules' => ['minimum' => policy_int('chapter_min_active'), 'plan_next' => policy_int('chapter_plan_next'), 'capacity' => policy_int('chapter_capacity'), 'auto_create' => policy('chapter_auto_create'), 'auto_activate' => policy('chapter_auto_activate')]];
  if (is_hq($u)) {
    $base = "status='active' AND chapter_id IS NULL AND network IS NOT NULL AND participant_class='member' AND role NOT IN ('hq_admin','super_admin')";
    $out['pending'] = ['placeable' => (int) val("SELECT COUNT(*) FROM users WHERE $base AND country_code IS NOT NULL AND (lga<>'' OR community<>'')"), 'no_location' => (int) val("SELECT COUNT(*) FROM users WHERE $base AND (country_code IS NULL OR (lga='' AND community=''))")];
  }
  return $out;
});
/* Drilldown: country, then state or region, then LGA or city, then community, then the chapters themselves. */
route('GET', '/chapters/tree', function () {
  $u = require_user(); [$w, $p] = chapters_where($u);
  $levels = ['country' => 'c.country_code', 'state' => 'c.state_region', 'lga' => 'c.lga', 'community' => 'c.community']; $next = null;
  foreach ($levels as $g => $col) if (!isset($_GET[$g]) || $_GET[$g] === '') { $next = $g; break; }
  $skipped = null;
  if ($next === 'state' && !val("SELECT 1 FROM chapters c WHERE $w AND c.state_region<>'' LIMIT 1", $p)) { $next = 'lga'; $skipped = 'state'; }      // no state layer in use here: go straight to the city
  if ($next === null) return ['level' => 'chapter', 'rows' => [], 'as_of' => now()];
  $col = $levels[$next]; $dia = is_diaspora((string) ($_GET['country'] ?? ''));
  $rowsOut = rows("SELECT $col k, COUNT(*) chapters, COALESCE(SUM(c.members),0) members, COALESCE(SUM(c.network='WGMN'),0) wgmn, COALESCE(SUM(c.network='WNNN'),0) wnnn,
                          COALESCE(SUM(c.status='full'),0) `full`, COALESCE(SUM(c.status='developing'),0) developing FROM chapters c WHERE $w GROUP BY $col ORDER BY members DESC, k LIMIT 300", $p);
  foreach ($rowsOut as &$r) { $r['label'] = $next === 'country' ? (country_name($r['k']) ?: 'Country not stated') : ($r['k'] === '' ? 'Not stated' : $r['k']); $r['key'] = $r['k'] === '' ? '(none)' : $r['k']; foreach (['chapters', 'members', 'wgmn', 'wnnn', 'full', 'developing'] as $n) $r[$n] = (int) $r[$n]; unset($r['k']); } unset($r);
  return ['level' => $next, 'rows' => $rowsOut, 'as_of' => now(), 'skipped' => $skipped, 'diaspora' => $dia];
});
route('POST', '/chapters', function () {
  $me = require_hq(); $b = body(); $net = in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true) ? $b['network'] : '';
  $cc = strtoupper(str_in('country', 2)); if ($net === '' || !preg_match('/^[A-Z]{2}$/', $cc)) fail(422, 'Choose the network and the country.', 'network');
  $lga = str_in('lga', 120); $community = str_in('community', 120);
  if ($lga === '' && $community === '') fail(422, 'Name the LGA, city or community this chapter serves.', 'lga');
  $id = chapter_create($net, $cc, str_in('state_region', 120), $lga, $community, (int) $me['id'], str_in('zone', 120));
  log_activity((int) $me['id'], null, 'chapter_created', (string) val('SELECT name FROM chapters WHERE id=?', [$id]));
  return ['ok' => true, 'id' => $id];
});
route('POST', '/chapters/backfill', function () {
  $me = require_hq(); $n = chapters_backfill(120); log_activity((int) $me['id'], null, 'chapter_placement', "$n placed");
  return ['ok' => true, 'placed' => $n, 'left' => (int) val("SELECT COUNT(*) FROM users WHERE status='active' AND chapter_id IS NULL AND network IS NOT NULL AND country_code IS NOT NULL AND participant_class='member' AND role NOT IN ('hq_admin','super_admin') AND (lga<>'' OR community<>'')")];
});
route('GET', '/my/chapter', function () {
  $u = require_user();
  if (!$u['chapter_id'] && !is_hq($u)) { chapter_place_user($u); $u = row('SELECT * FROM users WHERE id=?', [(int) $u['id']]); }
  $c = $u['chapter_id'] ? row('SELECT * FROM chapters WHERE id=?', [(int) $u['chapter_id']]) : null; $seat = current_seat((int) $u['id']);
  return ['chapter' => $c ? chapter_shape($c) : null, 'pending' => $c ? '' : chapter_pending_reason($u), 'leads' => $seat && !empty($seat['chapter_id']) ? (int) $seat['chapter_id'] : 0];
});
route('GET', '/chapters/(\d+)', function ($id) {
  $u = require_user(); $c = row('SELECT * FROM chapters WHERE id=?', [(int) $id]); if (!$c) fail(404, 'That chapter was not found.', 'not_found');
  $acc = chapter_access($u, $c); if ($acc === '') fail(403, 'You can open your own chapter only.', 'forbidden');
  $seat = current_seat((int) $u['id']); $full = $acc === 'hq' || ($acc === 'leader' && empty($seat['he_for_she']));
  // members see who is in their chapter by name and photograph only; contact details are for the chapter's leaders and HQ
  $cols = $full ? 'u.id, u.first_name, u.last_name, u.has_photo, u.member_no, u.phone, u.last_seen_at, m.started_at' : 'u.id, u.first_name, u.last_name, u.has_photo, m.started_at';
  $roster = rows("SELECT $cols FROM chapter_members m JOIN users u ON u.id=m.user_id WHERE m.chapter_id=? AND m.ended_at IS NULL ORDER BY u.first_name, u.last_name LIMIT 200", [(int) $id]);
  $leaders = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, s.he_for_she, CASE WHEN s.network='WNNN' THEN pp.title_wnnn ELSE pp.title_wgmn END title FROM seats s JOIN users u ON u.id=s.user_id JOIN positions pp ON pp.code=s.position_code WHERE s.ends_at IS NULL AND s.chapter_id=? ORDER BY pp.rank_no", [(int) $id]);
  $posts = rows("SELECT p.id, p.kind, p.body, p.created_at, p.reports, p.hidden_at, u.id user_id, u.first_name, u.last_name, u.has_photo FROM chapter_posts p JOIN users u ON u.id=p.user_id WHERE p.chapter_id=? " . ($full ? '' : 'AND p.hidden_at IS NULL ') . 'ORDER BY p.id DESC LIMIT 40', [(int) $id]);
  $out = ['chapter' => chapter_shape($c), 'access' => $acc, 'full' => $full, 'roster' => $roster, 'leaders' => $leaders, 'posts' => $posts, 'as_of' => now(),
    'meetings' => rows("SELECT m.id, m.title, m.starts_at, m.mode FROM meetings m WHERE m.starts_at>=? AND m.audience='members' AND m.host_id IN (SELECT s.user_id FROM seats s WHERE s.chapter_id=? AND s.ends_at IS NULL) ORDER BY m.starts_at LIMIT 5", [now(), (int) $id])];
  $out['announcements'] = rows('SELECT id, title, created_at FROM announcements WHERE audience_chapter=? AND sent_at IS NOT NULL ORDER BY id DESC LIMIT 5', [(int) $id]);
  $out['recovery_by_leader'] = policy('recovery_by_leader') === 'on' && $acc === 'leader' && $full;
  if ($full) {
    $out['tasks'] = rows("SELECT t.id, t.title, t.status, t.due_on, CONCAT(u.first_name,' ',u.last_name) owner FROM tasks t JOIN users u ON u.id=t.assignee_id WHERE t.chapter_id=? AND t.status IN ('open','in_progress','submitted') ORDER BY (t.due_on IS NULL), t.due_on LIMIT 8", [(int) $id]);
    $out['history'] = rows("SELECT u.first_name, u.last_name, m.started_at, m.ended_at, m.end_reason FROM chapter_members m JOIN users u ON u.id=m.user_id WHERE m.chapter_id=? AND m.ended_at IS NOT NULL ORDER BY m.ended_at DESC LIMIT 30", [(int) $id]);
    $out['stats'] = ['active_7d' => (int) val('SELECT COUNT(*) FROM users WHERE chapter_id=? AND last_seen_at>=?', [(int) $id, gmdate('Y-m-d H:i:s', time() - 7 * 86400)]),
      'journey_active' => (int) val("SELECT COUNT(*) FROM users u JOIN member_journeys j ON j.user_id=u.id WHERE u.chapter_id=? AND j.status='active'", [(int) $id]),
      'journey_open' => (int) val("SELECT COUNT(*) FROM users u JOIN member_journeys j ON j.user_id=u.id WHERE u.chapter_id=? AND j.status<>'active'", [(int) $id]),
      'referrals' => (int) val('SELECT COUNT(*) FROM referrals r JOIN users u ON u.id=r.referrer_id WHERE u.chapter_id=?', [(int) $id]),
      'tasks_open' => (int) val("SELECT COUNT(*) FROM tasks WHERE chapter_id=? AND status IN ('open','in_progress','submitted')", [(int) $id]),
      'attended' => (int) val("SELECT COUNT(*) FROM meeting_attendance a JOIN users u ON u.id=a.user_id WHERE u.chapter_id=? AND a.status='present' AND a.marked_at>=?", [(int) $id, gmdate('Y-m-d H:i:s', time() - 90 * 86400)]),
      'pending_nearby' => (int) val("SELECT COUNT(*) FROM users WHERE status='active' AND chapter_id IS NULL AND network=? AND country_code=? AND state_region=? AND lga=? AND community=?", [$c['network'], $c['country_code'], $c['state_region'], $c['lga'], $c['community']])];
  }
  return $out;
});
route('POST', '/chapters/(\d+)/activate', function ($id) {
  $me = require_hq(); $c = row('SELECT * FROM chapters WHERE id=?', [(int) $id]); if (!$c) fail(404, 'That chapter was not found.', 'not_found');
  if ((int) $c['members'] < policy_int('chapter_min_active')) fail(422, 'A chapter needs ' . policy_int('chapter_min_active') . ' members before it can be activated.', 'too_small');
  q('UPDATE chapters SET activated_at=? WHERE id=? AND activated_at IS NULL', [now(), (int) $id]); chapter_refresh((int) $id);
  log_activity((int) $me['id'], null, 'chapter_activated', $c['name']);
  return ['ok' => true];
});
route('POST', '/chapters/(\d+)/transfer', function ($id) {
  $me = require_hq(); $uid = (int) (body()['user_id'] ?? 0);
  chapter_move($uid, (int) $id, 'transfer', 'hq:' . (int) $me['id'], !empty(body()['allow_full']));
  log_activity((int) $me['id'], $uid, 'chapter_transfer', (string) val('SELECT name FROM chapters WHERE id=?', [(int) $id]));
  notify($uid, 'account', 'You have moved to a new chapter', 'WODDI HQ moved you to ' . val('SELECT name FROM chapters WHERE id=?', [(int) $id]) . '. Your membership history is kept.', '#/chapter');
  return ['ok' => true];
});
/* Merge: every current member moves to the other chapter, and this chapter is closed as merged. Membership history stays. */
route('POST', '/chapters/(\d+)/merge', function ($id) {
  $me = require_hq(); $from = row('SELECT * FROM chapters WHERE id=?', [(int) $id]); $to = row('SELECT * FROM chapters WHERE id=?', [(int) (body()['into'] ?? 0)]);
  if (!$from || !$to || (int) $from['id'] === (int) $to['id']) fail(422, 'Choose the chapter to merge into.', 'into');
  if ($from['network'] !== $to['network']) fail(422, 'Chapters of different networks cannot be merged.', 'network');
  if (in_array($to['status'], ['merged', 'closed'], true) || in_array($from['status'], ['merged', 'closed'], true)) fail(422, 'One of those chapters is no longer open.', 'closed');
  if ((int) $from['members'] + (int) $to['members'] > chapter_cap($to['country_code'])) fail(422, 'Together they would pass the capacity of ' . chapter_cap($to['country_code']) . '.', 'full');
  $n = now();
  q("INSERT INTO chapter_members (chapter_id,user_id,network,placed_by,started_at) SELECT ?, user_id, network, ?, ? FROM chapter_members WHERE chapter_id=? AND ended_at IS NULL", [(int) $to['id'], 'hq:' . (int) $me['id'], $n, (int) $from['id']]);
  q("UPDATE chapter_members SET ended_at=?, end_reason='merged' WHERE chapter_id=? AND ended_at IS NULL", [$n, (int) $from['id']]);
  q('UPDATE users SET chapter_id=? WHERE chapter_id=?', [(int) $to['id'], (int) $from['id']]);
  q('UPDATE seats SET chapter_id=? WHERE chapter_id=? AND ends_at IS NULL', [(int) $to['id'], (int) $from['id']]);
  q("UPDATE chapters SET status='merged', members=0, merged_into=?, closed_at=? WHERE id=?", [(int) $to['id'], $n, (int) $from['id']]);
  chapter_refresh((int) $to['id']); log_activity((int) $me['id'], null, 'chapter_merged', $from['name'] . ' into ' . $to['name']);
  return ['ok' => true];
});
/* Split: the chosen members move to a new chapter of the same catchment. */
route('POST', '/chapters/(\d+)/split', function ($id) {
  $me = require_hq(); $c = row('SELECT * FROM chapters WHERE id=?', [(int) $id]); if (!$c || in_array($c['status'], ['merged', 'closed'], true)) fail(404, 'That chapter was not found.', 'not_found');
  $ids = array_values(array_unique(array_filter(array_map('intval', (array) (body()['user_ids'] ?? [])))));
  if (!$ids) fail(422, 'Choose the members who move to the new chapter.', 'user_ids');
  $new = chapter_create($c['network'], $c['country_code'], $c['state_region'], $c['lga'], $c['community'], (int) $me['id'], $c['zone']); $moved = 0;
  foreach ($ids as $uid) if ((int) val('SELECT chapter_id FROM users WHERE id=?', [$uid]) === (int) $c['id']) { chapter_move($uid, $new, 'split', 'hq:' . (int) $me['id']); $moved++; }
  log_activity((int) $me['id'], null, 'chapter_split', $c['name'] . ": $moved moved");
  return ['ok' => true, 'id' => $new, 'moved' => $moved];
});

/* ----------------------------------------------------------- chapter space -- */
route('POST', '/chapters/(\d+)/posts', function ($id) {
  $u = require_user(); $c = row('SELECT * FROM chapters WHERE id=?', [(int) $id]); if (!$c) fail(404, 'That chapter was not found.', 'not_found');
  $acc = chapter_access($u, $c); if ($acc === '') fail(403, 'You can write in your own chapter only.', 'forbidden');
  $text = trim(str_in('body', 2000)); if (mb_strlen($text) < 2) fail(422, 'Write your message.', 'empty');
  throttle('cpost:' . (int) $u['id'], 20, 3600); throttle_hit('cpost:' . (int) $u['id']);
  $kind = $acc !== 'member' && !empty(body()['notice']) ? 'notice' : 'post';
  $held = $acc === 'member' && community_held($text);
  $pid = ins('INSERT INTO chapter_posts (chapter_id,user_id,kind,body,held,hidden_at,created_at) VALUES (?,?,?,?,?,?,?)', [(int) $id, (int) $u['id'], $kind, $text, $held ? 1 : 0, $held ? now() : null, now()]);
  if ($held) return ['ok' => true, 'id' => $pid, 'held' => true];
  if ($kind === 'notice') { notify_many('u.chapter_id=? AND u.id<>?', [(int) $id, (int) $u['id']], 'announcement', 'Chapter notice: ' . $c['name'], mb_substr($text, 0, 240), '#/chapter/' . (int) $id); after_response(fn() => push_drain(40)); }
  return ['ok' => true, 'id' => $pid];
});
route('POST', '/chapters/posts/(\d+)/report', function ($pid) {
  $u = require_user(); $p = row('SELECT p.*, c.id cid FROM chapter_posts p JOIN chapters c ON c.id=p.chapter_id WHERE p.id=?', [(int) $pid]); if (!$p) fail(404, 'That post was not found.', 'not_found');
  if (chapter_access($u, row('SELECT * FROM chapters WHERE id=?', [(int) $p['cid']])) === '') fail(403, 'You can report posts in your own chapter only.', 'forbidden');
  try { q('INSERT INTO chapter_post_reports (post_id,user_id,reason,created_at) VALUES (?,?,?,?)', [(int) $pid, (int) $u['id'], str_in('reason', 200), now()]); } catch (PDOException $e) { return ['ok' => true]; }
  $n = (int) val('SELECT COUNT(*) FROM chapter_post_reports WHERE post_id=?', [(int) $pid]);
  // three separate reports take a post out of view until a chapter leader or HQ has looked at it
  q('UPDATE chapter_posts SET reports=?, hidden_at=IF(?>=3 AND hidden_at IS NULL, ?, hidden_at) WHERE id=?', [$n, $n, now(), (int) $pid]);
  if ($n === 1) notify_hq('reminder', 'A chapter post was reported', mb_substr($p['body'], 0, 160), '#/chapter/' . (int) $p['cid']);
  return ['ok' => true];
});
route('POST', '/chapters/posts/(\d+)/hide', function ($pid) {
  $u = require_user(); $p = row('SELECT * FROM chapter_posts WHERE id=?', [(int) $pid]); if (!$p) fail(404, 'That post was not found.', 'not_found');
  $acc = chapter_access($u, row('SELECT * FROM chapters WHERE id=?', [(int) $p['chapter_id']])); if (!in_array($acc, ['hq', 'leader'], true)) fail(403, 'Only the chapter leaders and WODDI HQ can do this.', 'forbidden');
  $hide = empty(body()['restore']);
  q('UPDATE chapter_posts SET hidden_at=?, hidden_by=? WHERE id=?', [$hide ? now() : null, $hide ? (int) $u['id'] : null, (int) $pid]);
  log_activity((int) $u['id'], (int) $p['user_id'], $hide ? 'post_hidden' : 'post_restored', mb_substr($p['body'], 0, 120));
  return ['ok' => true];
});

/* ---------------------------------------------------------- member journey -- */
route('GET', '/journey', function () {
  $u = require_user();
  if (!$u['chapter_id'] && !is_hq($u)) { chapter_place_user($u); $u = row('SELECT * FROM users WHERE id=?', [(int) $u['id']]); }
  return mj_state($u);
});
route('POST', '/journey/start', function () {            // a member who joined before the journey existed may choose to take it
  $u = require_user(); if (is_hq($u)) fail(422, 'HQ staff do not take the member journey.', 'hq');
  mj_start((int) $u['id']); return mj_state($u);
});
route('POST', '/journey/(\d+)', function ($day) {
  $u = require_user(); $day = (int) $day; $j = row('SELECT * FROM member_journeys WHERE user_id=?', [(int) $u['id']]);
  if (!$j || !isset(MJ_DAYS[$day])) fail(404, 'That step was not found.', 'not_found');
  if ($day > mj_open_days($j)) fail(422, 'This step opens on its own day. Come back then.', 'locked');
  if ($day === 7) { $res = mj_decide($u); return mj_state($u) + ['result' => $res]; }
  $answers = null;
  if ($day === 5) {
    $a = (array) (body()['answers'] ?? []); $clean = [];
    foreach (MJ_QUESTIONS as $k => $qq) { if (!in_array($a[$k] ?? '', ['yes', 'no'], true)) fail(422, 'Please answer every question.', 'answers'); $clean[$k] = $a[$k]; }
    $answers = json_encode($clean);
  }
  q('DELETE FROM member_journey_steps WHERE user_id=? AND day_no=?', [(int) $u['id'], $day]);
  q('INSERT INTO member_journey_steps (user_id,day_no,answers,done_at) VALUES (?,?,?,?)', [(int) $u['id'], $day, $answers, now()]);
  if ($j['status'] === 'retry') q("UPDATE member_journeys SET status='in_progress' WHERE user_id=?", [(int) $u['id']]);
  return mj_state($u);
});
/* HQ watches the automation; it does not operate it. The only list that needs a person is the exceptions list. */
route('GET', '/hq/journeys', function () {
  require_hq();
  $s = row("SELECT COUNT(*) total, COALESCE(SUM(status='in_progress'),0) in_progress, COALESCE(SUM(status='active'),0) active, COALESCE(SUM(status='retry'),0) retry, COALESCE(SUM(status='review'),0) review, COALESCE(SUM(extensions>0),0) extended FROM member_journeys");
  return ['summary' => array_map('intval', $s), 'as_of' => now(),
    'exceptions' => rows("SELECT u.id, u.first_name, u.last_name, u.member_no, u.network, u.country_code, j.decided_at FROM member_journeys j JOIN users u ON u.id=j.user_id WHERE j.status='review' ORDER BY j.decided_at DESC LIMIT 100"),
    'recent' => rows("SELECT u.id, u.first_name, u.last_name, u.network, j.status, j.decided_at FROM member_journeys j JOIN users u ON u.id=j.user_id WHERE j.decided_at IS NOT NULL ORDER BY j.decided_at DESC LIMIT 15")];
});
/* The protected exceptional channel: HQ clears a possible duplicate, or leaves it restricted. Logged either way. */
route('POST', '/hq/journeys/(\d+)', function ($uid) {
  $me = require_hq(); $j = row("SELECT * FROM member_journeys WHERE user_id=? AND status IN ('review','restricted')", [(int) $uid]); if (!$j) fail(404, 'That case is not in the exceptions list.', 'not_found');
  $to = (body()['decision'] ?? '') === 'clear' ? 'active' : 'restricted';
  q('UPDATE member_journeys SET status=?, decided_at=?, reasons=? WHERE user_id=?', [$to, now(), $to === 'active' ? '[]' : json_encode([['day' => 0, 'text' => 'WODDI HQ is reviewing your account. You can keep using WDOS. Send a message to HQ if you need help.']]), (int) $uid]);
  log_activity((int) $me['id'], (int) $uid, 'journey_exception_' . $to, str_in('note', 300));
  if ($to === 'active') notify((int) $uid, 'activation', 'You are now an active WODDI member', 'WODDI HQ has checked your account and everything is in order.', '#/journey');
  return ['ok' => true];
});

/* --------------------------------------------------------------- language -- */
route('POST', '/me/lang', function () {
  $u = require_user(); $l = str_in('lang', 5);
  if (preg_match('/^[a-z]{2,3}$/', $l)) q('UPDATE users SET lang=? WHERE id=?', [$l, (int) $u['id']]);
  return ['ok' => true];
});

/* --------------------------------------- announcements through every level -- */
route('POST', '/leader/announcements', fn() => announcement_send(require_user()));
route('GET', '/leader/announcements', function () {
  $u = require_user(); if (!current_seat((int) $u['id']) && !is_hq($u)) fail(403, 'For volunteer leaders.', 'forbidden');
  return ['items' => rows('SELECT id, title, body, recipients, audience_chapter, created_at FROM announcements WHERE author_id=? ORDER BY id DESC LIMIT 20', [(int) $u['id']])];
});
/* Where a message went: queued, sent, failed. Delivery beyond that is only known when the phone's push service reports it. */
route('GET', '/hq/delivery', function () {
  require_hq(); $out = ['as_of' => now(), 'push' => [], 'mail' => []];
  foreach (rows('SELECT status, COUNT(*) n FROM push_outbox GROUP BY status') as $r) $out['push'][$r['status']] = (int) $r['n'];
  try { foreach (rows('SELECT status, COUNT(*) n FROM mail_outbox GROUP BY status') as $r) $out['mail'][$r['status']] = (int) $r['n']; } catch (PDOException $e) { /* mail table not there yet */ }
  $out['read'] = (int) val('SELECT COUNT(*) FROM notifications WHERE read_at IS NOT NULL AND created_at>=?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
  $out['created'] = (int) val('SELECT COUNT(*) FROM notifications WHERE created_at>=?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
  return $out;
});

/* ------------------------------------------------- announcements: read, acknowledge -- */
route('POST', '/announcements/(\d+)/ack', function ($id) {
  $u = require_user(); if (!val('SELECT 1 FROM announcements WHERE id=? AND sent_at IS NOT NULL', [(int) $id])) fail(404, 'That announcement was not found.', 'not_found');
  q('INSERT IGNORE INTO announcement_acks (announcement_id,user_id,acked_at) VALUES (?,?,?)', [(int) $id, (int) $u['id'], now()]);
  return ['ok' => true];
});

/* ------------------------------------------------------------------- community -- */
route('GET', '/community', function () {
  $u = require_user(); $levels = [];
  foreach (community_keys($u) as [$k, $label, $lvl]) $levels[] = ['key' => $k, 'label' => $label, 'level' => $lvl, 'posts' => (int) val('SELECT COUNT(*) FROM space_posts WHERE space_key=? AND hidden_at IS NULL', [$k])];
  $w = is_hq($u) ? '1=1' : "(s.network='ALL' OR s.network=?)";
  $circles = rows("SELECT s.id, s.kind, s.name, s.about, s.network, (SELECT COUNT(*) FROM space_members m WHERE m.space_id=s.id) members,
                          EXISTS (SELECT 1 FROM space_members m WHERE m.space_id=s.id AND m.user_id=?) joined FROM spaces s WHERE s.closed_at IS NULL AND $w ORDER BY s.kind, s.name LIMIT 100",
    is_hq($u) ? [(int) $u['id']] : [(int) $u['id'], (string) $u['network']]);
  foreach ($circles as &$c) { $c['id'] = (int) $c['id']; $c['members'] = (int) $c['members']; $c['joined'] = (int) $c['joined'] === 1; } unset($c);
  $ch = !empty($u['chapter_id']) ? row('SELECT * FROM chapters WHERE id=?', [(int) $u['chapter_id']]) : null;
  return ['levels' => $levels, 'circles' => $circles, 'chapter' => $ch ? chapter_shape($ch) : null, 'chapter_pending' => $ch || is_hq($u) ? '' : chapter_pending_reason($u), 'is_hq' => is_hq($u)];
});
route('GET', '/community/space', function () {
  $u = require_user(); $key = mb_substr((string) ($_GET['key'] ?? ''), 0, 190); $acc = space_access($u, $key);
  if ($acc === '') fail(403, 'That space is not one of yours.', 'forbidden');
  $mod = $acc !== 'member'; $sp = preg_match('/^sp:(\d+)$/', $key, $m) ? row('SELECT id, kind, name, about FROM spaces WHERE id=?', [(int) $m[1]]) : null;
  return ['key' => $key, 'access' => $acc, 'space' => $sp,
    'posts' => rows("SELECT p.id, p.body, p.created_at, p.reports, p.hidden_at, p.held, u.id user_id, u.first_name, u.last_name, u.has_photo FROM space_posts p JOIN users u ON u.id=p.user_id WHERE p.space_key=? " . ($mod ? '' : 'AND (p.hidden_at IS NULL OR p.user_id=' . (int) $u['id'] . ') ') . 'ORDER BY p.id DESC LIMIT 50', [$key])];
});
route('POST', '/community/space', function () {
  $u = require_user(); $key = mb_substr((string) (body()['key'] ?? ''), 0, 190); $acc = space_access($u, $key);
  if ($acc === '') fail(403, 'You can write in your own spaces only.', 'forbidden');
  $text = trim(str_in('body', 2000)); if (mb_strlen($text) < 2) fail(422, 'Write your message.', 'empty');
  throttle('spost:' . (int) $u['id'], 20, 3600); throttle_hit('spost:' . (int) $u['id']);
  $held = $acc === 'member' && community_held($text);
  $id = ins('INSERT INTO space_posts (space_key,user_id,body,held,hidden_at,created_at) VALUES (?,?,?,?,?,?)', [$key, (int) $u['id'], $text, $held ? 1 : 0, $held ? now() : null, now()]);
  return ['ok' => true, 'id' => $id, 'held' => $held];
});
route('POST', '/community/posts/(\d+)/report', function ($pid) {
  $u = require_user(); $p = row('SELECT * FROM space_posts WHERE id=?', [(int) $pid]); if (!$p || space_access($u, $p['space_key']) === '') fail(404, 'That post was not found.', 'not_found');
  try { q('INSERT INTO space_post_reports (post_id,user_id,created_at) VALUES (?,?,?)', [(int) $pid, (int) $u['id'], now()]); } catch (PDOException $e) { return ['ok' => true]; }
  $n = (int) val('SELECT COUNT(*) FROM space_post_reports WHERE post_id=?', [(int) $pid]);
  q('UPDATE space_posts SET reports=?, hidden_at=IF(?>=3 AND hidden_at IS NULL, ?, hidden_at) WHERE id=?', [$n, $n, now(), (int) $pid]);
  if ($n === 1) notify_hq('reminder', 'A community post was reported', mb_substr($p['body'], 0, 160), '#/community/moderation');
  return ['ok' => true];
});
route('POST', '/community/posts/(\d+)/hide', function ($pid) {
  $u = require_user(); $p = row('SELECT * FROM space_posts WHERE id=?', [(int) $pid]); if (!$p) fail(404, 'That post was not found.', 'not_found');
  if (!in_array(space_access($u, $p['space_key']), ['hq', 'leader'], true)) fail(403, 'Only the leaders of this space and WODDI HQ can do this.', 'forbidden');
  $hide = empty(body()['restore']);
  q('UPDATE space_posts SET hidden_at=?, hidden_by=?, held=0 WHERE id=?', [$hide ? now() : null, $hide ? (int) $u['id'] : null, (int) $pid]);
  log_activity((int) $u['id'], (int) $p['user_id'], $hide ? 'post_hidden' : 'post_restored', mb_substr($p['body'], 0, 120));
  return ['ok' => true];
});
route('POST', '/community/circles', function () {
  $me = require_hq(); $name = str_in('name', 160); if (mb_strlen($name) < 3) fail(422, 'Give the space a name.', 'name');
  $kind = (body()['kind'] ?? '') === 'mentorship' ? 'mentorship' : 'circle'; $net = in_array(body()['network'] ?? '', ['WGMN', 'WNNN'], true) ? body()['network'] : 'ALL';
  $id = ins('INSERT INTO spaces (kind,name,about,network,created_by,created_at) VALUES (?,?,?,?,?,?)', [$kind, $name, str_in('about', 600), $net, (int) $me['id'], now()]);
  log_activity((int) $me['id'], null, 'space_created', $name);
  return ['ok' => true, 'id' => $id];
});
route('POST', '/community/circles/(\d+)/(join|leave|close)', function ($id, $act) {
  $u = require_user(); $s = row('SELECT * FROM spaces WHERE id=? AND closed_at IS NULL', [(int) $id]); if (!$s) fail(404, 'That space was not found.', 'not_found');
  if ($act === 'close') { require_hq(); q('UPDATE spaces SET closed_at=? WHERE id=?', [now(), (int) $id]); return ['ok' => true]; }
  if ($act === 'join') { if (!is_hq($u) && $s['network'] !== 'ALL' && $s['network'] !== $u['network']) fail(403, 'This space is for the other network.', 'network'); q('INSERT IGNORE INTO space_members (space_id,user_id,joined_at) VALUES (?,?,?)', [(int) $id, (int) $u['id'], now()]); }
  else q('DELETE FROM space_members WHERE space_id=? AND user_id=?', [(int) $id, (int) $u['id']]);
  return ['ok' => true];
});
/* The restricted-content queue: posts held by the word filter, hidden after reports, or reported at least once. */
route('GET', '/hq/moderation', function () {
  require_hq();
  return ['spaces' => rows("SELECT p.id, p.space_key place, p.body, p.reports, p.held, p.hidden_at, p.created_at, u.id user_id, u.first_name, u.last_name FROM space_posts p JOIN users u ON u.id=p.user_id WHERE p.hidden_at IS NOT NULL OR p.reports>0 ORDER BY p.id DESC LIMIT 60"),
    'chapters' => rows("SELECT p.id, c.name place, p.chapter_id, p.body, p.reports, p.held, p.hidden_at, p.created_at, u.id user_id, u.first_name, u.last_name FROM chapter_posts p JOIN users u ON u.id=p.user_id JOIN chapters c ON c.id=p.chapter_id WHERE p.hidden_at IS NOT NULL OR p.reports>0 ORDER BY p.id DESC LIMIT 60"), 'as_of' => now()];
});

/* --------------------------------------------------------- automatic report -- */
/* Consolidated by WDOS from its own records, down to chapter. Nothing here is typed in by a person. */
route('GET', '/reports/auto', function () {
  $u = require_user(); $w = ["u.status='active'", 'u.network IS NOT NULL', "u.participant_class='member'"]; $p = [];
  if (!is_hq($u)) { $seat = current_seat((int) $u['id']); if (!$seat || !empty($seat['he_for_she'])) fail(403, 'This report is for volunteer leaders and WODDI HQ.', 'forbidden'); [$sw, $sp] = user_scope_sql($seat); $w[] = "($sw)"; $p = $sp; }
  $levels = ['country' => 'u.country_code', 'state' => 'u.state_region', 'lga' => 'u.lga', 'community' => 'u.community', 'chapter' => 'u.chapter_id']; $next = 'chapter';
  foreach (['network' => 'u.network'] + $levels as $g => $col) if (isset($_GET[$g]) && $_GET[$g] !== '') { $w[] = "$col=?"; $p[] = $_GET[$g] === '(none)' ? '' : (string) $_GET[$g]; }
  foreach (['country', 'state', 'lga', 'community'] as $g) if (!isset($_GET[$g]) || $_GET[$g] === '') { $next = $g; break; }
  $ws = implode(' AND ', $w);
  if ($next === 'state' && !val("SELECT 1 FROM users u WHERE $ws AND u.state_region<>'' LIMIT 1", $p)) $next = 'lga';
  $col = $levels[$next]; $k = $next === 'chapter' ? "COALESCE($col,0)" : $col; $out = [];
  $add = function (string $sql, array $par, array $fields) use (&$out) { foreach (rows($sql, $par) as $r) { $key = (string) $r['k']; foreach ($fields as $f) $out[$key][$f] = (int) $r[$f]; } };
  $add("SELECT $k k, COUNT(*) members, COALESCE(SUM(u.chapter_id IS NULL),0) pending, COALESCE(SUM(u.last_seen_at>=?),0) active_7d FROM users u WHERE $ws GROUP BY $k", array_merge([gmdate('Y-m-d H:i:s', time() - 7 * 86400)], $p), ['members', 'pending', 'active_7d']);
  $add("SELECT $k k, COALESCE(SUM(j.status='active'),0) activated, COALESCE(SUM(j.status<>'active'),0) on_journey FROM users u JOIN member_journeys j ON j.user_id=u.id WHERE $ws GROUP BY $k", $p, ['activated', 'on_journey']);
  $add("SELECT $k k, COUNT(*) referrals FROM referrals r JOIN users u ON u.id=r.referrer_id WHERE $ws GROUP BY $k", $p, ['referrals']);
  $add("SELECT $k k, COUNT(*) leaders FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND " . str_replace("u.participant_class='member'", '1=1', $ws) . " GROUP BY $k", $p, ['leaders']);
  $add("SELECT $k k, COUNT(*) attended FROM meeting_attendance a JOIN users u ON u.id=a.user_id WHERE a.status='present' AND a.marked_at>=? AND $ws GROUP BY $k", array_merge([gmdate('Y-m-d H:i:s', time() - 90 * 86400)], $p), ['attended']);
  $add("SELECT $k k, COUNT(*) tasks_open FROM tasks t JOIN users u ON u.id=t.assignee_id WHERE t.status IN ('open','in_progress','submitted') AND $ws GROUP BY $k", $p, ['tasks_open']);
  $names = []; if ($next === 'chapter' && $out) foreach (rows('SELECT id, name, status, members FROM chapters WHERE id IN (' . implode(',', array_map('intval', array_keys($out))) . ')') as $c) $names[(string) $c['id']] = $c;
  $rowsOut = [];
  foreach ($out as $key => $r) {
    if (!isset($r['members'])) continue;
    $key = (string) $key; $label = $next === 'country' ? (country_name($key) ?: 'Country not stated') : ($next === 'chapter' ? ($key === '0' ? 'Waiting for a chapter' : ($names[$key]['name'] ?? 'Chapter ' . $key)) : ($key === '' ? 'Not stated' : $key));
    $rowsOut[] = ['key' => $next === 'chapter' ? (string) $key : ($key === '' ? '(none)' : (string) $key), 'label' => $label, 'chapter_status' => $next === 'chapter' && isset($names[$key]) ? CHAPTER_STATUS[$names[$key]['status']] ?? '' : '']
      + array_map(fn($f) => (int) ($r[$f] ?? 0), ['members' => 'members', 'pending' => 'pending', 'active_7d' => 'active_7d', 'activated' => 'activated', 'on_journey' => 'on_journey', 'referrals' => 'referrals', 'leaders' => 'leaders', 'attended' => 'attended', 'tasks_open' => 'tasks_open']);
  }
  usort($rowsOut, fn($a, $b) => $b['members'] <=> $a['members']);
  return ['level' => $next, 'rows' => array_slice($rowsOut, 0, 400), 'as_of' => now(), 'source' => 'Counted by WDOS from membership, journey, referral, seat, attendance and task records.', 'course_progress' => 'Not connected: course progress is held by the WODDI Institute.'];
});

/* ---------------------------------------- recovery in person, by a chapter leader -- */
route('POST', '/leader/members/(\d+)/reset-pin', function ($id) {
  $me = require_user(); if (policy('recovery_by_leader') !== 'on') fail(403, 'This way of recovering an account is switched off.', 'off');
  $m = row('SELECT * FROM users WHERE id=?', [(int) $id]); $c = $m && $m['chapter_id'] ? row('SELECT * FROM chapters WHERE id=?', [(int) $m['chapter_id']]) : null; $seat = current_seat((int) $me['id']);
  if (!$c || !$seat || !empty($seat['he_for_she']) || (int) ($seat['chapter_id'] ?? 0) !== (int) $c['id'] || is_hq($m) || current_seat((int) $m['id'])) fail(403, 'You can do this only for a member of your own chapter, in person.', 'forbidden');
  throttle('pinreset:' . (int) $me['id'], 5, 86400); throttle_hit('pinreset:' . (int) $me['id']);
  $temp = (string) random_int(100000, 999999);
  q('UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?', [password_hash($temp, PASSWORD_DEFAULT), (int) $id]); q('DELETE FROM sessions WHERE user_id=?', [(int) $id]);
  log_activity((int) $me['id'], (int) $id, 'pin_reset_by_leader', $c['name']);
  notify((int) $id, 'account', 'Your PIN was reset by your chapter leader', 'Sign in with the temporary PIN you were given and choose your own. If you did not ask for this, tell WODDI HQ.', '#/settings');
  notify_hq('reminder', 'A chapter leader reset a member PIN', $me['first_name'] . ' ' . $me['last_name'] . ' reset the PIN of ' . $m['first_name'] . ' ' . $m['last_name'] . ' in ' . $c['name'] . '.', '#/members/' . (int) $id);
  return ['ok' => true, 'temporary_pin' => $temp];
});

/* --------------------------------- one Person ID for the Institute and the Community -- */
/* The WODDI Institute (or any approved WODDI system) asks WDOS who a person is, with a shared key set as INTEGRATION_KEY.
 * WDOS stays the owner of membership; the other system keeps its own records against the same Person ID. */
if ($METHOD === 'GET' && $PATH === '/integration/person') {
  $key = (string) cfg('INTEGRATION_KEY'); $given = (string) ($_SERVER['HTTP_X_WDOS_KEY'] ?? '');
  if ($key === '' || !hash_equals($key, $given)) fail(404, 'Not found.', 'not_found');
  $u = null;
  if (!empty($_GET['email'])) $u = row('SELECT * FROM users WHERE email=?', [strtolower((string) $_GET['email'])]);
  elseif (!empty($_GET['member_no'])) $u = row('SELECT * FROM users WHERE member_no=?', [(string) $_GET['member_no']]);
  elseif (!empty($_GET['person_id'])) $u = row('SELECT * FROM users WHERE id=?', [(int) preg_replace('/\D/', '', (string) $_GET['person_id'])]);
  if (!$u) fail(404, 'No person with those details.', 'no_person');
  $c = $u['chapter_id'] ? row('SELECT code, name FROM chapters WHERE id=?', [(int) $u['chapter_id']]) : null;
  out(['person_id' => sprintf('WDP-%07d', (int) $u['id']), 'member_no' => $u['member_no'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'network' => $u['network'], 'country_code' => $u['country_code'],
    'status' => $u['status'], 'language' => $u['lang'], 'chapter' => $c, 'journey' => (string) val('SELECT status FROM member_journeys WHERE user_id=?', [(int) $u['id']])]);
}
/* What the welcome screen may offer besides text: WODDI's own short video or audio, with the words written out. */
route('GET', '/public/welcome', fn() => ['video_url' => policy('welcome_video_url'), 'audio_url' => policy('welcome_audio_url'), 'transcript' => policy('welcome_transcript')]);
