<?php
declare(strict_types=1);
/* v109 routes: documents, direct messages, WODDI Envoys, HQ staff access, the leader guide. */

/* ------------------------------------------------------------- documents -- */
/* v109.5: one piece of a large file. The server in front of WDOS refuses a single request over about 1.5 MB, so the
 * browser sends files in pieces of up to 600 KB and then names the upload ('upload:<id>') where it used to send the data. */
route('POST', '/uploads/part', function () {
  $u = require_user(); $b = body();
  $id = (string) ($b['upload'] ?? ''); $seq = (int) ($b['seq'] ?? -1); $total = (int) ($b['total'] ?? 0);
  if (!preg_match('/^[a-f0-9]{32}$/', $id) || $total < 1 || $total > 40 || $seq < 0 || $seq >= $total) fail(422, 'That file could not be read. Choose it again.', 'file');
  $bin = base64_decode((string) ($b['data'] ?? ''), true);
  if ($bin === false || $bin === '' || strlen($bin) > 700000) fail(422, 'That file could not be read. Choose it again.', 'file');
  if ($seq === 0) { throttle('upload:' . (int) $u['id'], 80, 3600); throttle_hit('upload:' . (int) $u['id']); q('DELETE FROM upload_parts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]); }
  $owner = val('SELECT user_id FROM upload_parts WHERE upload_id=? LIMIT 1', [$id]);
  if ($owner !== null && (int) $owner !== (int) $u['id']) fail(403, 'That upload belongs to someone else.', 'forbidden');
  $st = db()->prepare('REPLACE INTO upload_parts (upload_id,user_id,seq,total,bytes,created_at) VALUES (?,?,?,?,?,?)');
  $st->bindValue(1, $id); $st->bindValue(2, (int) $u['id'], PDO::PARAM_INT); $st->bindValue(3, $seq, PDO::PARAM_INT); $st->bindValue(4, $total, PDO::PARAM_INT); $st->bindValue(5, $bin, PDO::PARAM_LOB); $st->bindValue(6, now()); $st->execute();
  return ['ok' => true, 'have' => (int) val('SELECT COUNT(*) FROM upload_parts WHERE upload_id=?', [$id])];
});
route('POST', '/files', function () {
  $u = require_user(); $b = body(); $data = (string) ($b['data'] ?? '');
  if (!str_starts_with($data, 'upload:')) { throttle('upload:' . (int) $u['id'], 80, 3600); throttle_hit('upload:' . (int) $u['id']); }
  $bin = upload_bytes($u, $data);
  return ['ok' => true, 'file' => doc_store($u, mb_substr(basename((string) ($b['name'] ?? 'document')), 0, 190), $bin, ($b['purpose'] ?? '') === 'report', ($b['purpose'] ?? '') === 'recording', ($b['purpose'] ?? '') === 'room')];
});
route('GET', '/files/(\d+)', function ($id) {
  $u = require_user(); if (!doc_can($u, (int) $id)) fail(404, 'That file was not found, or it is outside your area.', 'not_found');
  doc_send((int) $id, !empty($_GET['dl']));
});

/* Reports: the leader attaches the report itself as a PDF or Word document. */
route('GET', '/reporting/report/(\d+)/documents', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); $rt = rp_rights($u, $r); if (!$rt['view'] && !$rt['review']) fail(403, 'This report is outside your area.', 'forbidden');
  return ['documents' => doc_list('report', (int) $id), 'can_edit' => $rt['edit'] || $rt['contribute'], 'required' => policy('report_document_required') !== 'no' && (int) $r['is_nil'] !== 1];
});
route('POST', '/reporting/report/(\d+)/documents', function ($id) {
  $u = require_user(); $r = rp_report((int) $id); $rt = rp_rights($u, $r);
  if (!$rt['edit'] && !$rt['contribute']) fail(403, 'Documents can be added only while the report is open.', 'locked');
  $fid = (int) (body()['file_id'] ?? 0); $f = row('SELECT * FROM doc_files WHERE id=? AND uploaded_by=?', [$fid, (int) $u['id']]);
  if (!$f) fail(422, 'Upload the document first.', 'file');
  if (!isset(DOC_TYPES_REPORT[$f['mime']])) fail(422, 'A report document must be a PDF or a Word (.docx) file.', 'type');
  doc_link($fid, 'report', (int) $id, 'report', (int) $u['id'], (int) $r['version']);
  log_activity((int) $u['id'], (int) $r['owner_id'], 'report_document_added', $r['report_no'] . ' · ' . $f['name'], 'report', (int) $id);
  return ['ok' => true, 'documents' => doc_list('report', (int) $id)];
});
route('POST', '/reporting/report/(\d+)/documents/(\d+)/delete', function ($id, $fid) {
  $u = require_user(); $r = rp_report((int) $id); $rt = rp_rights($u, $r);
  if (!$rt['edit'] && !$rt['contribute']) fail(403, 'Documents can be removed only while the report is open.', 'locked');
  $l = row("SELECT * FROM doc_links WHERE file_id=? AND owner_type='report' AND owner_id=? AND removed_at IS NULL", [(int) $fid, (int) $id]);
  if (!$l || ((int) $l['added_by'] !== (int) $u['id'] && !$rt['edit'])) fail(404, 'That document was not found.', 'not_found');
  q("UPDATE doc_links SET removed_at=? WHERE file_id=? AND owner_type='report' AND owner_id=?", [now(), (int) $fid, (int) $id]);
  return ['ok' => true, 'documents' => doc_list('report', (int) $id)];
});
route('POST', '/reporting/report/(\d+)/documents/(\d+)/restore', function ($id, $fid) {
  $u = require_user(); $r = rp_report((int) $id); $rt = rp_rights($u, $r);
  if (!$rt['edit'] && !$rt['contribute']) fail(403, 'This report is no longer open.', 'locked');
  q("UPDATE doc_links SET removed_at=NULL WHERE file_id=? AND owner_type='report' AND owner_id=? AND added_by=?", [(int) $fid, (int) $id, (int) $u['id']]);
  return ['ok' => true, 'documents' => doc_list('report', (int) $id)];
});

/* Tasks: HQ attaches the brief or template; the person doing the task attaches the finished work. */
route('GET', '/tasks/(\d+)/files', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $rt = task_rights($u, $t);
  return ['files' => doc_list('task', (int) $id), 'can_add_brief' => $rt['manage'], 'can_add_work' => $rt['mine'] && in_array($t['status'], ['open', 'in_progress'], true)];
});
route('POST', '/tasks/(\d+)/files', function ($id) {
  $u = require_user(); $t = task_get((int) $id); $rt = task_rights($u, $t); $b = body();
  $role = ($b['role'] ?? '') === 'brief' ? 'brief' : 'work';
  if ($role === 'brief' && !$rt['manage']) fail(403, 'Only the person who gave the task adds its brief.', 'forbidden');
  if ($role === 'work' && !$rt['mine']) fail(403, 'Only the person doing the task adds the finished work.', 'forbidden');
  if ($role === 'work' && !in_array($t['status'], ['open', 'in_progress'], true)) fail(409, 'This task is no longer open for documents.', 'locked');
  $fid = (int) ($b['file_id'] ?? 0); if (!row('SELECT 1 FROM doc_files WHERE id=? AND uploaded_by=?', [$fid, (int) $u['id']])) fail(422, 'Upload the document first.', 'file');
  doc_link($fid, 'task', (int) $id, $role, (int) $u['id']);
  return ['ok' => true, 'files' => doc_list('task', (int) $id)];
});
route('POST', '/tasks/(\d+)/files/(\d+)/delete', function ($id, $fid) {
  $u = require_user(); $t = task_get((int) $id); $rt = task_rights($u, $t);
  $l = row("SELECT * FROM doc_links WHERE file_id=? AND owner_type='task' AND owner_id=? AND removed_at IS NULL", [(int) $fid, (int) $id]);
  if (!$l || ((int) $l['added_by'] !== (int) $u['id'] && !$rt['manage'])) fail(404, 'That document was not found.', 'not_found');
  q("UPDATE doc_links SET removed_at=? WHERE file_id=? AND owner_type='task' AND owner_id=?", [now(), (int) $fid, (int) $id]);
  return ['ok' => true, 'files' => doc_list('task', (int) $id)];
});

/* ------------------------------------------------------- direct messages -- */
function dm_person(array $r): array { return ['id' => (int) $r['id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'has_photo' => (int) $r['has_photo'], 'title' => dm_title_of((int) $r['id'])]; }
route('GET', '/dm', function () {
  $u = require_user(); $me = (int) $u['id']; $hidden = ($_GET['view'] ?? '') === 'deleted';
  $vis = dm_visible_sql($me);
  $list = rows("SELECT t.*, p.id pid, p.first_name, p.last_name, p.has_photo,
      (SELECT COUNT(*) FROM dm_messages m WHERE m.thread_id=t.id AND m.sender_id<>? AND m.deleted_at IS NULL AND m.id > CASE WHEN t.lo_id=? THEN t.lo_read ELSE t.hi_read END AND $vis) unread,
      (SELECT m.body FROM dm_messages m WHERE m.thread_id=t.id AND m.deleted_at IS NULL AND $vis ORDER BY m.id DESC LIMIT 1) last_body,
      (SELECT m.subject FROM dm_messages m WHERE m.thread_id=t.id AND m.deleted_at IS NULL AND $vis ORDER BY m.id DESC LIMIT 1) last_subject,
      (SELECT MAX(m.created_at) FROM dm_messages m WHERE m.thread_id=t.id AND $vis) last_visible
    FROM dm_threads t JOIN users p ON p.id = CASE WHEN t.lo_id=? THEN t.hi_id ELSE t.lo_id END
    WHERE (t.lo_id=? OR t.hi_id=?) AND (CASE WHEN t.lo_id=? THEN t.lo_hidden ELSE t.hi_hidden END)=? ORDER BY t.last_at DESC LIMIT 300", [$me, $me, $me, $me, $me, $me, $hidden ? 1 : 0]);
  $items = [];
  foreach ($list as $t) { if ($t['last_visible'] === null) continue; $items[] = ['id' => (int) $t['id'], 'person' => dm_person(['id' => $t['pid'], 'first_name' => $t['first_name'], 'last_name' => $t['last_name'], 'has_photo' => $t['has_photo']]), 'unread' => (int) $t['unread'], 'subject' => (string) $t['last_subject'], 'snippet' => mb_substr(preg_replace('/\s+/u', ' ', (string) $t['last_body']), 0, 140), 'last_at' => $t['last_visible'], 'mine_last' => (int) $t['last_sender'] === $me]; }
  return ['items' => $items, 'unread' => dm_unread_total($me), 'drafts' => (int) val('SELECT COUNT(*) FROM dm_drafts WHERE user_id=?', [$me]), 'can_group' => is_hq($u) || (bool) work_seat($u) || ($u['role'] ?? '') === 'envoy', 'view' => $hidden ? 'deleted' : 'inbox'];
});
route('GET', '/dm/(\d+)', function ($id) {
  $u = require_user(); $me = (int) $u['id']; $t = dm_thread_for($u, (int) $id);
  $msgs = rows('SELECT m.id, m.sender_id, m.subject, m.body, m.created_at, m.deleted_at, m.batch_id, b.undo_until, b.recipients FROM dm_messages m LEFT JOIN dm_batches b ON b.id=m.batch_id WHERE m.thread_id=? AND ' . dm_visible_sql($me) . ' AND (b.undone_at IS NULL OR b.id IS NULL) ORDER BY m.id LIMIT 500', [(int) $id]);
  $max = 0; foreach ($msgs as &$m) { $m['id'] = (int) $m['id']; $m['mine'] = (int) $m['sender_id'] === $me; if ($m['deleted_at']) $m['body'] = ''; $m['can_undo'] = $m['mine'] && $m['undo_until'] && $m['undo_until'] > now(); $m['to_many'] = (int) ($m['recipients'] ?? 1) > 1; unset($m['undo_until'], $m['recipients']); if (!$m['mine']) $max = max($max, $m['id']); }
  if ($max) q('UPDATE dm_threads SET ' . $t['side'] . '_read=GREATEST(' . $t['side'] . '_read, ?) WHERE id=?', [$max, (int) $id]);
  q("UPDATE notifications SET read_at=? WHERE user_id=? AND kind='message' AND read_at IS NULL AND url IN ('#/inbox', ?)", [now(), $me, '#/inbox/' . (int) $id]);
  $other = row('SELECT id, first_name, last_name, has_photo, status FROM users WHERE id=?', [$t['other_id']]);
  return ['thread' => ['id' => (int) $t['id'], 'person' => dm_person($other), 'active' => $other['status'] === 'active'], 'messages' => $msgs];
});
route('POST', '/dm/audience', function () {
  $u = require_user(); [$w, $p] = dm_audience($u, body());
  $n = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w", $p);
  $sample = rows("SELECT u.id, u.first_name, u.last_name FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w ORDER BY u.first_name LIMIT 8", $p);
  $ids = !empty(body()['with_ids']) ? array_map('intval', array_column(rows("SELECT u.id FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w ORDER BY u.id LIMIT 1000", $p), 'id')) : null;
  return ['count' => $n, 'sample' => array_map(fn($r) => trim($r['first_name'] . ' ' . $r['last_name']), $sample)] + ($ids !== null ? ['ids' => $ids] : []);
});
route('GET', '/dm/people', function () {
  $u = require_user(); [$w, $p] = dm_allowed_sql($u); $s = trim((string) ($_GET['q'] ?? ''));
  if (ctype_digit((string) ($_GET['id'] ?? ''))) return ['people' => array_map('dm_person', rows("SELECT u.id, u.first_name, u.last_name, u.has_photo FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w) AND u.id=?", array_merge($p, [(int) $_GET['id']])))];
  if (mb_strlen($s) < 2) return ['people' => []];
  $list = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w) AND (CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.member_no LIKE ? OR u.email LIKE ?) ORDER BY u.first_name LIMIT 20", array_merge($p, ["%$s%", "%$s%", "%$s%"]));
  return ['people' => array_map('dm_person', $list)];
});
route('GET', '/dm/options', function () {
  $u = require_user(); $hq = is_hq($u); $seat = work_seat($u); $envoy = ($u['role'] ?? '') === 'envoy';
  $who = $hq ? ['leaders', 'members', 'envoys', 'staff', 'everyone'] : ($envoy ? ['staff', 'members'] : ($seat ? (empty($seat['he_for_she']) ? ['leaders', 'members', 'everyone'] : ['leaders']) : []));
  $pos = $hq ? rows('SELECT code, title_wgmn, title_wnnn, level FROM positions ORDER BY rank_no') : ($seat ? rows('SELECT code, title_wgmn, title_wnnn, level FROM positions WHERE rank_no<>? ORDER BY rank_no', [(int) $seat['rank_no']]) : []);
  $countries = $hq ? rows("SELECT DISTINCT u.country_code code FROM users u WHERE u.country_code IS NOT NULL AND u.status='active'") : [];
  $countries = array_map(fn($c) => ['code' => $c['code'], 'name' => country_name($c['code']) ?: $c['code']], $countries); usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));
  return ['who' => $who, 'network' => $hq ? null : ($seat['network'] ?? null), 'positions' => $pos, 'countries' => $countries, 'undo_seconds' => DM_UNDO_SECONDS];
});
route('POST', '/dm/send', function () {
  $u = require_user(); $b = body(); $subject = str_in('subject', 190); $text = str_in('body', 6000);
  if (mb_strlen($text) < 1) fail(422, 'Write your message first.', 'body');
  throttle('dm:' . (int) $u['id'], 120, 3600); throttle_hit('dm:' . (int) $u['id']);
  $aud = is_array($b['audience'] ?? null) ? $b['audience'] : [];
  if (empty($aud['people']) && !is_hq($u) && !work_seat($u) && ($u['role'] ?? '') !== 'envoy') fail(403, 'Choose the person you are writing to.', 'audience');
  $r = dm_send($u, $aud, $subject, $text, str_in('label', 600));
  if (($did = (int) ($b['draft_id'] ?? 0)) > 0) q('DELETE FROM dm_drafts WHERE id=? AND user_id=?', [$did, (int) $u['id']]);
  after_response(fn() => dm_release_due());
  return ['ok' => true] + $r;
});
route('POST', '/dm/(\d+)/reply', function ($id) {
  $u = require_user(); $t = dm_thread_for($u, (int) $id); $text = str_in('body', 6000);
  if (mb_strlen($text) < 1) fail(422, 'Write your message first.', 'body');
  if (!val("SELECT 1 FROM users WHERE id=? AND status='active'", [$t['other_id']])) fail(409, 'This person no longer has an active WDOS account.', 'inactive');
  throttle('dm:' . (int) $u['id'], 120, 3600); throttle_hit('dm:' . (int) $u['id']);
  // a reply stays inside the conversation even when the sender could not have started it (for example a member answering her leader)
  $me = (int) $u['id']; $n = now();
  $bid = ins('INSERT INTO dm_batches (sender_id,subject,audience,recipients,undo_until,created_at) VALUES (?,?,?,?,?,?)', [$me, '', 'reply', 1, gmdate('Y-m-d H:i:s', time() + DM_UNDO_SECONDS), $n]);
  ins('INSERT INTO dm_messages (thread_id,sender_id,batch_id,subject,body,created_at) VALUES (?,?,?,?,?,?)', [(int) $id, $me, $bid, '', $text, $n]);
  q('UPDATE dm_threads SET last_at=?, last_sender=?, last_snippet=?, lo_hidden=0, hi_hidden=0 WHERE id=?', [$n, $me, mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 200), (int) $id]);
  after_response(fn() => dm_release_due());
  return ['ok' => true, 'batch_id' => $bid, 'undo_seconds' => DM_UNDO_SECONDS];
});
/** Undo a send within its first seconds: nobody was told yet, and the words come back to the writer. */
route('POST', '/dm/undo/(\d+)', function ($bid) {
  $u = require_user(); $b = row('SELECT * FROM dm_batches WHERE id=? AND sender_id=?', [(int) $bid, (int) $u['id']]);
  if (!$b) fail(404, 'That message was not found.', 'not_found');
  if ($b['undone_at']) return ['ok' => true, 'already' => true];
  if ($b['released_at'] || $b['undo_until'] <= now()) fail(409, 'Too late to undo: the message has been delivered. You can still delete it.', 'too_late');
  if (q('UPDATE dm_batches SET undone_at=? WHERE id=? AND released_at IS NULL', [now(), (int) $bid])->rowCount() === 0) fail(409, 'Too late to undo: the message has been delivered. You can still delete it.', 'too_late');
  $first = row('SELECT subject, body FROM dm_messages WHERE batch_id=? LIMIT 1', [(int) $bid]);
  $tids = array_map('intval', array_column(rows('SELECT DISTINCT thread_id FROM dm_messages WHERE batch_id=?', [(int) $bid]), 'thread_id'));
  q('DELETE FROM dm_messages WHERE batch_id=?', [(int) $bid]);
  foreach (array_chunk($tids, 500) as $chunk) {
    $in = implode(',', $chunk);
    q("UPDATE dm_threads t SET t.last_at=COALESCE((SELECT MAX(m.created_at) FROM dm_messages m WHERE m.thread_id=t.id), t.created_at), t.last_snippet=COALESCE((SELECT LEFT(m.body,200) FROM dm_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1),'') WHERE t.id IN ($in)");
    q("DELETE FROM dm_threads WHERE id IN ($in) AND NOT EXISTS (SELECT 1 FROM dm_messages m WHERE m.thread_id=dm_threads.id)");
  }
  log_activity((int) $u['id'], null, 'message_undone', mb_substr(($b['subject'] ?: 'message') . ' · ' . $b['recipients'], 0, 240));
  return ['ok' => true, 'subject' => $first['subject'] ?? '', 'body' => $first['body'] ?? ''];
});
route('POST', '/dm/message/(\d+)/delete', function ($mid) {
  $u = require_user(); $m = row('SELECT * FROM dm_messages WHERE id=? AND sender_id=?', [(int) $mid, (int) $u['id']]);
  if (!$m) fail(404, 'You can delete only your own messages.', 'not_found');
  $all = !empty(body()['everyone']) && $m['batch_id'];
  q('UPDATE dm_messages SET deleted_at=?, body=\'\' WHERE ' . ($all ? 'batch_id=? AND sender_id=?' : 'id=? AND sender_id=?'), [now(), $all ? (int) $m['batch_id'] : (int) $mid, (int) $u['id']]);
  return ['ok' => true];
});
route('POST', '/dm/hide', function () {
  $u = require_user(); $me = (int) $u['id']; $ids = array_slice(array_values(array_filter(array_map('intval', (array) (body()['ids'] ?? [])))), 0, 500); $h = empty(body()['hidden']) ? 0 : 1;
  if (!$ids) fail(422, 'Choose at least one conversation.', 'ids');
  $in = implode(',', $ids);
  q("UPDATE dm_threads SET lo_hidden=? WHERE lo_id=? AND id IN ($in)", [$h, $me]); q("UPDATE dm_threads SET hi_hidden=? WHERE hi_id=? AND id IN ($in)", [$h, $me]);
  return ['ok' => true, 'count' => count($ids)];
});
route('POST', '/dm/read', function () {
  $u = require_user(); $me = (int) $u['id']; $ids = array_slice(array_values(array_filter(array_map('intval', (array) (body()['ids'] ?? [])))), 0, 500); if (!$ids) return ['ok' => true];
  $in = implode(',', $ids);
  q("UPDATE dm_threads t SET t.lo_read=COALESCE((SELECT MAX(m.id) FROM dm_messages m WHERE m.thread_id=t.id),0) WHERE t.lo_id=? AND t.id IN ($in)", [$me]);
  q("UPDATE dm_threads t SET t.hi_read=COALESCE((SELECT MAX(m.id) FROM dm_messages m WHERE m.thread_id=t.id),0) WHERE t.hi_id=? AND t.id IN ($in)", [$me]);
  return ['ok' => true];
});
route('GET', '/dm/drafts', function () { $u = require_user(); return ['drafts' => rows('SELECT id, subject, body, audience, updated_at FROM dm_drafts WHERE user_id=? ORDER BY updated_at DESC LIMIT 50', [(int) $u['id']])]; });
route('POST', '/dm/drafts', function () {
  $u = require_user(); $b = body(); $id = (int) ($b['id'] ?? 0); $aud = mb_substr(json_encode($b['audience'] ?? new stdClass(), JSON_UNESCAPED_UNICODE), 0, 2000);
  if (trim(str_in('subject', 190) . str_in('body', 6000)) === '') fail(422, 'There is nothing to save yet.', 'body');
  if ($id && val('SELECT 1 FROM dm_drafts WHERE id=? AND user_id=?', [$id, (int) $u['id']])) q('UPDATE dm_drafts SET subject=?, body=?, audience=?, updated_at=? WHERE id=?', [str_in('subject', 190), str_in('body', 6000), $aud, now(), $id]);
  else { if ((int) val('SELECT COUNT(*) FROM dm_drafts WHERE user_id=?', [(int) $u['id']]) >= 50) fail(422, 'You have 50 drafts saved. Delete some first.', 'drafts'); $id = ins('INSERT INTO dm_drafts (user_id,subject,body,audience,updated_at) VALUES (?,?,?,?,?)', [(int) $u['id'], str_in('subject', 190), str_in('body', 6000), $aud, now()]); }
  return ['ok' => true, 'id' => $id];
});
route('POST', '/dm/drafts/(\d+)/delete', function ($id) { $u = require_user(); q('DELETE FROM dm_drafts WHERE id=? AND user_id=?', [(int) $id, (int) $u['id']]); return ['ok' => true]; });

/* ---------------------------------------------------------- WODDI Envoys -- */
function envoy_invite_mail(int $userId, string $kind): bool {
  $p = row('SELECT id, email, first_name FROM users WHERE id=?', [$userId]); if (!$p || !mail_configured()) return false;
  $tok = token(32); q('INSERT INTO password_resets (token_hash,user_id,expires_at) VALUES (?,?,?)', [hash('sha256', $tok), (int) $p['id'], gmdate('Y-m-d H:i:s', time() + 72 * 3600)]);
  $link = app_origin() . '/#/reset/' . $tok; $what = $kind === 'envoy' ? 'a WODDI Envoy' : 'a member of WODDI HQ staff';
  return mail_queue((string) $p['email'], (string) $p['first_name'], 'Your WODDI Digital Operating System login', mail_wrap('Dear ' . htmlspecialchars((string) $p['first_name']) . ',',
    '<p>WODDI HQ has opened a WDOS login for you as ' . $what . '.</p><p>Use the button within 72 hours to choose your own password. You then sign in with this email address.</p>', 'Choose my password', $link));
}
route('GET', '/envoy/home', function () {
  $u = require_user(); $e = envoy_of($u); if (!$e) fail(403, 'This page is for WODDI Envoys.', 'forbidden');
  $me = (int) $u['id'];
  $leaders = envoy_leaders($me);
  return ['link' => app_origin() . '/#/r/' . $e['code'], 'code' => $e['code'], 'status' => $e['status'], 'kpis' => envoy_kpis($me, (int) $e['link_id']), 'people' => envoy_people($me),
    'leaders' => $leaders, 'designation' => (string) ($u['designation'] ?? ''),
    'leader_kpis' => ['brought_in' => count($leaders), 'serving' => count(array_filter($leaders, fn($l) => $l['serving'])), 'attention' => count(array_filter($leaders, fn($l) => (bool) $l['flags'])),
      'overdue' => array_sum(array_column($leaders, 'tasks_overdue'))],
    'tasks' => ['open' => (int) val("SELECT COUNT(*) FROM tasks WHERE assignee_id=? AND status IN ('open','in_progress')", [$me]), 'submitted' => (int) val("SELECT COUNT(*) FROM tasks WHERE assignee_id=? AND status='submitted'", [$me]), 'overdue' => (int) val("SELECT COUNT(*) FROM tasks WHERE assignee_id=? AND status IN ('open','in_progress') AND due_on IS NOT NULL AND due_on<?", [$me, work_today()])],
    'unread' => dm_unread_total($me), 'leadmap' => function_exists('lm_envoy_summary') ? lm_envoy_summary($u) : null];      // v109.46: the leadership seats in her state
});
route('GET', '/hq/envoys', function () {
  require_hq(); $list = [];
  foreach (rows('SELECT e.*, u.first_name, u.last_name, u.email, u.phone, u.has_photo, u.last_seen_at, u.status ustatus, l.code FROM envoys e JOIN users u ON u.id=e.user_id JOIN referral_links l ON l.id=e.link_id ORDER BY u.first_name, u.last_name') as $e)
    $list[] = ['id' => (int) $e['user_id'], 'first_name' => $e['first_name'], 'last_name' => $e['last_name'], 'email' => str_ends_with((string) $e['email'], '.invalid') ? '' : $e['email'], 'phone' => $e['phone'], 'has_photo' => (int) $e['has_photo'], 'country' => country_name($e['country_code']), 'status' => $e['status'],
      'last_seen_at' => $e['last_seen_at'], 'source' => $e['source'] ?? 'hq', 'link' => app_origin() . '/#/r/' . $e['code'], 'kpis' => envoy_kpis((int) $e['user_id'], (int) $e['link_id'])];
  $tot = []; foreach ($list as $e) foreach ($e['kpis'] as $k => $v) $tot[$k] = ($tot[$k] ?? 0) + $v;
  return ['envoys' => $list, 'totals' => $tot + ['envoys' => count($list), 'active' => count(array_filter($list, fn($e) => $e['status'] === 'active'))]];
});
route('GET', '/hq/envoys/(\d+)', function ($id) {
  require_hq(); $e = row('SELECT e.*, u.first_name, u.last_name, u.phone, l.code FROM envoys e JOIN users u ON u.id=e.user_id JOIN referral_links l ON l.id=e.link_id WHERE e.user_id=?', [(int) $id]);
  if (!$e) fail(404, 'That envoy was not found.', 'not_found');
  return ['envoy' => ['id' => (int) $e['user_id'], 'first_name' => $e['first_name'], 'last_name' => $e['last_name'], 'status' => $e['status'], 'link' => app_origin() . '/#/r/' . $e['code'], 'source' => $e['source'] ?? 'hq',
      'phone' => $e['phone'], 'country' => country_name($e['country_code']), 'state_region' => $e['state_region'] ?? null, 'city' => $e['city'] ?? null, 'organisation' => $e['organisation'] ?? null, 'reach_plan' => $e['reach_plan'] ?? null, 'heard_from' => $e['heard_from'] ?? null, 'created_at' => $e['created_at']], 'kpis' => envoy_kpis((int) $id, (int) $e['link_id']), 'people' => envoy_people((int) $id), 'leaders' => envoy_leaders((int) $id)];
});
route('POST', '/hq/envoys', function () {
  $me = require_hq(); $b = body(); $first = str_in('first_name', 80); $last = str_in('last_name', 80); $email = strtolower(str_in('email', 190)); $phone = preg_replace('/[^\d+]/', '', str_in('phone', 32));
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter the first name and surname.', 'name');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address. The envoy signs in with it.', 'email');
  if (val('SELECT 1 FROM users WHERE email=?', [$email])) fail(409, 'An account with this email already exists.', 'email_taken');
  $cc = preg_match('/^[A-Z]{2}$/', strtoupper(str_in('country_code', 2))) ? strtoupper(str_in('country_code', 2)) : null;
  $temp = temp_password();
  $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,phone,role,status,signup_method,must_change_password,country_code,designation,created_at) VALUES (?,?,?,?,?,'envoy','active','staff',1,?,'WODDI Envoy',?)", [$email, password_hash($temp, PASSWORD_DEFAULT), $first, $last, $phone, $cc, now()]);
  $lid = ins("INSERT INTO referral_links (code,owner_id,kind,network,label,campaign,created_at) VALUES (?,?,'envoy','',?,'envoy',?)", [ref_new_code(), $id, 'WODDI Envoy: ' . $first . ' ' . $last, now()]);
  q('INSERT INTO envoys (user_id,link_id,country_code,status,created_by,created_at) VALUES (?,?,?,?,?,?)', [$id, $lid, $cc, 'active', (int) $me['id'], now()]);
  $mailed = !empty($b['send_invite']) && envoy_invite_mail($id, 'envoy'); if ($mailed) after_response(fn() => mail_drain(4));
  log_activity((int) $me['id'], $id, 'envoy_created', $email);
  return ['ok' => true, 'id' => $id, 'email' => $email, 'temporary_password' => $temp, 'invited' => $mailed, 'link' => app_origin() . '/#/r/' . val('SELECT code FROM referral_links WHERE id=?', [$lid])];
});
route('POST', '/hq/envoys/(\d+)', function ($id) {
  $me = require_hq(); $e = row('SELECT * FROM envoys WHERE user_id=?', [(int) $id]); if (!$e) fail(404, 'That envoy was not found.', 'not_found');
  $on = !empty(body()['active']);
  q('UPDATE envoys SET status=? WHERE user_id=?', [$on ? 'active' : 'suspended', (int) $id]); q('UPDATE referral_links SET active=? WHERE id=?', [$on ? 1 : 0, (int) $e['link_id']]);
  q('UPDATE users SET status=? WHERE id=?', [$on ? 'active' : 'suspended', (int) $id]); if (!$on) q('DELETE FROM sessions WHERE user_id=?', [(int) $id]);
  if ($on && $e['status'] === 'pending') { notify((int) $id, 'account', 'Your WODDI Envoy link is on', 'WODDI HQ approved you. Open your dashboard and share your link.', '#/'); person_mail((int) $id, 'WDOS: you are now a WODDI Envoy', 'Your envoy link is on', '<p>WODDI HQ approved your envoy sign-up. Open WDOS to copy your referral link and share it.</p>', '#/'); }
  log_activity((int) $me['id'], (int) $id, $on ? 'envoy_restored' : 'envoy_suspended');
  return ['ok' => true];
});

/* -------------------------------------------------- HQ staff: who opens what -- */
route('GET', '/hq/staff/access', function () {
  $me = require_hq();
  $roster = array_map(function ($r) { $u = row('SELECT id, role, status FROM users WHERE email=?', [$r['email']]); return $r + ['user_id' => $u ? (int) $u['id'] : null, 'account_role' => $u['role'] ?? null, 'account_status' => $u['status'] ?? null]; }, STAFF_ROSTER);
  $staff = array_map(fn($s) => ['id' => (int) $s['id'], 'designation' => $s['designation'], 'features' => hq_features_of($s), 'role' => $s['role']], rows("SELECT id, role, designation, hq_features FROM users WHERE role IN ('hq_admin','super_admin')"));
  return ['features' => HQ_FEATURES, 'roster' => $roster, 'staff' => $staff, 'can_manage' => $me['role'] === 'super_admin', 'hq_inbox' => hq_inbox()];
});
route('POST', '/hq/staff/(\d+)/access', function ($id) {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator decides what a staff login opens.', 'forbidden');
  $s = row("SELECT * FROM users WHERE id=? AND role IN ('hq_admin','super_admin')", [(int) $id]); if (!$s) fail(404, 'That staff login was not found.', 'not_found');
  $b = body(); $all = !empty($b['all']);
  $f = $all ? null : implode(',', array_values(array_intersect(array_keys(HQ_FEATURES), (array) ($b['features'] ?? []))));
  if (!$all && $f === '') fail(422, 'Tick at least one area, or choose every area.', 'features');
  q('UPDATE users SET hq_features=?, designation=? WHERE id=?', [$s['role'] === 'super_admin' ? null : $f, str_in('designation', 160), (int) $id]);
  log_activity((int) $me['id'], (int) $id, 'hq_access_changed', $all ? 'every area' : (string) $f);
  return ['ok' => true];
});
route('POST', '/hq/staff/(\d+)/invite', function ($id) {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator sends staff invitations.', 'forbidden');
  if (!val("SELECT 1 FROM users WHERE id=? AND role IN ('hq_admin','envoy') AND status='active'", [(int) $id])) fail(404, 'That login was not found.', 'not_found');
  if (!mail_configured()) fail(409, 'Email is not switched on yet, so no invitation can be sent.', 'mail_off');
  envoy_invite_mail((int) $id, (string) val('SELECT role FROM users WHERE id=?', [(int) $id]) === 'envoy' ? 'envoy' : 'staff'); after_response(fn() => mail_drain(4));
  log_activity((int) $me['id'], (int) $id, 'login_invitation_sent');
  return ['ok' => true];
});

/* ---------------------------------------------------- the leader's guide -- */
route('POST', '/me/guide-seen', function () { $u = require_user(); q('UPDATE users SET guide_seen_at=? WHERE id=?', [now(), (int) $u['id']]); return ['ok' => true]; });

/* -------------------------------------------- video meetings asked for by leaders -- */
route('GET', '/meet-requests', function () {
  $u = require_user(); $me = (int) $u['id']; $st = (string) ($_GET['status'] ?? '');
  $w = is_hq($u) ? '1=1' : '(requester_id=? OR FIND_IN_SET(?, attendee_ids))'; $p = is_hq($u) ? [] : [$me, (string) $me];
  if (in_array($st, ['pending', 'approved', 'declined', 'cancelled', 'held'], true)) { $w .= ' AND status=?'; $p[] = $st; }
  $list = rows("SELECT * FROM meeting_requests WHERE $w ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, starts_at DESC LIMIT 200", $p);
  $seat = work_seat($u);
  return ['items' => array_map(fn($m) => mr_out($u, $m), $list), 'pending' => is_hq($u) ? (int) val("SELECT COUNT(*) FROM meeting_requests WHERE status='pending'") : null,
    'can_request' => (bool) $seat || is_hq($u), 'jitsi' => jitsi_domain(),
    'me' => ['name' => trim($u['first_name'] . ' ' . $u['last_name']), 'title' => $seat ? seat_title($seat) : '', 'network' => $seat['network'] ?? ($u['network'] ?? ''), 'country_code' => $u['country_code'] ?? null, 'region' => trim(implode(', ', array_filter([$u['state_region'] ?? '', $u['lga'] ?? ''])))]];
});
route('GET', '/meet-requests/(\d+)', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]);
  if (!$m || mr_role($u, $m) === '') fail(404, 'That meeting was not found.', 'not_found');
  return ['meeting' => mr_out($u, $m)];
});
route('POST', '/meet-requests', function () {
  $u = require_user(); $seat = work_seat($u);
  if (!$seat && !is_hq($u)) fail(403, 'Video meetings are requested by volunteer leaders.', 'forbidden');
  $b = body(); throttle('mreq:' . (int) $u['id'], 20, 86400); throttle_hit('mreq:' . (int) $u['id']);
  $title = str_in('title', 190); $reason = str_in('reason', 3000); $who = str_in('attendees_text', 3000);
  if (mb_strlen($title) < 4) fail(422, 'Give the meeting a title.', 'title');
  if (mb_strlen($reason) < 10) fail(422, 'Say briefly why the meeting is needed.', 'reason');
  if (mb_strlen($who) < 3) fail(422, 'Say who will attend.', 'attendees_text');
  $start = strtotime(str_in('starts_at', 25) . ' UTC'); if (!$start) fail(422, 'Choose the date and time of the meeting.', 'starts_at');
  if ($start < time() + 3600) fail(422, 'Ask at least one hour before the meeting, so HQ has time to approve it.', 'starts_at');
  if ($start > time() + 365 * 86400) fail(422, 'Choose a date within the next year.', 'starts_at');
  $dur = (int) ($b['duration_min'] ?? 60); if ($dur < 15 || $dur > 480) fail(422, 'A meeting lasts between 15 minutes and 8 hours.', 'duration_min');
  // invited WDOS people must be inside the requester's reach
  $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($b['people'] ?? []))))), 0, 300);
  if ($ids) { [$w, $p] = dm_allowed_sql($u); $ok = array_map('intval', array_column(rows("SELECT u.id FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w) AND u.id IN (" . implode(',', $ids) . ')', $p), 'id')); $ids = array_values(array_intersect($ids, $ok)); }
  $cc = preg_match('/^[A-Z]{2}$/', strtoupper(str_in('country_code', 2))) ? strtoupper(str_in('country_code', 2)) : ($u['country_code'] ?? null);
  if ((int) ($b['flier_file'] ?? 0)) { $ff = row('SELECT mime FROM doc_files WHERE id=? AND uploaded_by=?', [(int) $b['flier_file'], (int) $u['id']]); if (!$ff) fail(422, 'Upload the flier again.', 'flier_file'); if (!isset(FLIER_TYPES[$ff['mime']])) fail(422, 'The flier must be a picture (JPG or PNG) or a PDF.', 'flier_file'); }
  $n = now();
  $id = ins('INSERT INTO meeting_requests (requester_id,network,country_code,region,leader_name,title,reason,attendees_text,attendee_ids,expected_count,graphics,other_needs,starts_at,duration_min,tz,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [(int) $u['id'], $seat['network'] ?? '', $cc, str_in('region', 190), str_in('leader_name', 190) ?: trim($u['first_name'] . ' ' . $u['last_name']), $title, $reason, $who, implode(',', $ids), max(0, min(100000, (int) ($b['expected_count'] ?? 0))), str_in('graphics', 2000), str_in('other_needs', 2000), gmdate('Y-m-d H:i:s', $start), $dur, str_in('tz', 60), 'pending', $n, $n]);
  $no = 'MR-' . gmdate('Ym') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT); q('UPDATE meeting_requests SET request_no=? WHERE id=?', [$no, $id]);
  if ((int) ($b['flier_file'] ?? 0)) mr_set_flier($u, $id, (int) $b['flier_file']);
  $who_name = trim($u['first_name'] . ' ' . $u['last_name']); $when = gmdate('j M Y, H:i', $start) . ' GMT';
  notify_hq('meeting', mb_substr($title, 0, 120) . ': meeting request', $who_name . ' · ' . $when, '#/meetings/requests/' . $id);
  hq_mail('WDOS video meeting request ' . $no . ' from ' . $who_name, 'A leader asks for a video meeting', '<p><b>' . htmlspecialchars($who_name) . '</b>' . ($seat ? ' (' . htmlspecialchars(seat_title($seat)) . ')' : '') . ' asks for a video meeting.</p><p><b>' . htmlspecialchars($title) . '</b><br>' . htmlspecialchars($when) . ', ' . $dur . ' minutes</p><p>' . nl2br(htmlspecialchars($reason)) . '</p><p><b>Attending:</b> ' . htmlspecialchars($who) . '</p>' . (str_in('graphics', 2000) !== '' ? '<p><b>Graphics needed:</b> ' . htmlspecialchars(str_in('graphics', 2000)) . '</p>' : ''), '#/meetings/requests/' . $id);
  log_activity((int) $u['id'], null, 'meeting_requested', $no . ' · ' . $title, 'meetreq', (int) $id);
  after_response(fn() => push_drain(20));
  return ['ok' => true, 'id' => $id, 'request_no' => $no];
});
route('POST', '/meet-requests/(\d+)/decide', function ($id) {
  $me = require_hq(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]); if (!$m) fail(404, 'That meeting was not found.', 'not_found');
  $d = (string) (body()['decision'] ?? ''); $note = str_in('note', 2000);
  if (!in_array($d, ['approve', 'decline'], true)) fail(422, 'Approve or decline.', 'decision');
  if ($d === 'decline' && mb_strlen($note) < 3) fail(422, 'Say why, so the leader knows what to change.', 'note');
  if (!in_array($m['status'], ['pending', 'approved'], true)) fail(409, 'This request is already ' . $m['status'] . '.', 'state');
  $room = $m['room'] ?: 'WODDI-' . preg_replace('/[^A-Za-z0-9]/', '', $m['request_no']) . '-' . strtoupper(substr(b64u(random_bytes(9)), 0, 10));
  q('UPDATE meeting_requests SET status=?, hq_note=?, decided_by=?, decided_at=?, room=?, updated_at=? WHERE id=?', [$d === 'approve' ? 'approved' : 'declined', $note, (int) $me['id'], now(), $d === 'approve' ? $room : $m['room'], now(), (int) $id]);
  if ($d === 'approve' && empty($m['call_domain'])) q('UPDATE meeting_requests SET call_domain=? WHERE id=?', [jitsi_domain(), (int) $id]);   // everyone in this meeting uses the same server, even if HQ changes the rule later
  $when = gmdate('j M Y, H:i', strtotime($m['starts_at'] . ' UTC')) . ' GMT'; $url = '#/meetings/requests/' . $id;
  if ($d === 'approve') {
    notify((int) $m['requester_id'], 'meeting', mb_substr($m['title'], 0, 120) . ': approved', $when . '. The Join button is ready now.', $url);
    person_mail((int) $m['requester_id'], 'WDOS video meeting approved: ' . mb_substr($m['title'], 0, 120), 'Your video meeting is approved', '<p>WODDI HQ approved <b>' . htmlspecialchars($m['title']) . '</b> for ' . htmlspecialchars($when) . '.</p><p>Open the meeting in WDOS. The Join button is ready now, and you can tell your people from the meeting page.</p>' . ($note !== '' ? '<p>' . nl2br(htmlspecialchars($note)) . '</p>' : ''), $url);
    if ($ids = mr_ids($m)) { notify_many('u.id IN (' . implode(',', $ids) . ')', [], 'meeting', 'You are invited: ' . mb_substr($m['title'], 0, 120), $when, $url); foreach ($ids as $i) person_mail($i, 'WDOS video meeting: ' . mb_substr($m['title'], 0, 120), 'You are invited to a video meeting', '<p><b>' . htmlspecialchars($m['title']) . '</b>, ' . htmlspecialchars($when) . '.</p><p>Open it in WDOS. The Join button is ready now.</p>', $url); }
  } else {
    notify((int) $m['requester_id'], 'meeting', mb_substr($m['title'], 0, 110) . ': not approved', $note, $url);
    person_mail((int) $m['requester_id'], 'WDOS video meeting not approved: ' . mb_substr($m['title'], 0, 110), 'Your video meeting request', '<p>WODDI HQ did not approve <b>' . htmlspecialchars($m['title']) . '</b>.</p><p>' . nl2br(htmlspecialchars($note)) . '</p>', $url);
  }
  log_activity((int) $me['id'], (int) $m['requester_id'], 'meeting_request_' . $d, $m['request_no']);
  after_response(fn() => push_drain(30));
  return ['ok' => true];
});
route('POST', '/meet-requests/(\d+)/cancel', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]);
  if (!$m || mr_role($u, $m) !== 'requester') fail(404, 'That meeting was not found.', 'not_found');
  if (!in_array($m['status'], ['pending', 'approved'], true)) fail(409, 'This request can no longer be cancelled.', 'state');
  q("UPDATE meeting_requests SET status='cancelled', updated_at=? WHERE id=?", [now(), (int) $id]);
  notify_hq('meeting', mb_substr($m['title'], 0, 120) . ': cancelled', $m['request_no'], '#/meetings/requests/' . $id);
  if ($m['status'] === 'approved' && ($ids = mr_ids($m))) notify_many('u.id IN (' . implode(',', $ids) . ')', [], 'meeting', 'Cancelled: ' . mb_substr($m['title'], 0, 120), $m['request_no'], '#/meetings/requests/' . $id);
  return ['ok' => true];
});
/** The gate: the room address is given only to the requester, the people invited and HQ, only after approval, and only while the room is open. */
route('POST', '/meet-requests/(\d+)/join', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]);
  if (!$m || mr_role($u, $m) === '') fail(404, 'That meeting was not found.', 'not_found');
  if ($m['status'] !== 'approved') fail(403, $m['status'] === 'pending' ? 'This meeting is waiting for HQ approval. The room opens after approval.' : 'This meeting is not approved, so there is no room.', 'locked');
  [$open, $close] = mr_window($m);
  if (time() < $open) fail(403, 'The room opens once HQ approves.', 'not_yet', ['opens_at' => gmdate('Y-m-d H:i:s', $open)]);
  if (time() > $close) fail(403, 'This meeting has ended.', 'closed');
  q('INSERT INTO meeting_request_joins (request_id,user_id,first_at,last_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE last_at=VALUES(last_at)', [(int) $id, (int) $u['id'], now(), now()]);
  $name = trim($u['first_name'] . ' ' . $u['last_name']); $dom = ($m['call_domain'] ?? '') ?: jitsi_domain(); $kind = call_engine($dom); $mod = in_array(mr_role($u, $m), ['requester', 'hq'], true);
  $tab = 'https://' . $dom . '/' . rawurlencode($m['room']) . '#userInfo.displayName=' . rawurlencode('"' . $name . '"');
  $out = ['ok' => true, 'engine' => $kind, 'room' => $m['room'], 'domain' => $dom, 'name' => $name, 'email' => (string) ($u['email'] ?? ''), 'moderator' => $mod, 'url' => $tab,
    'closes_at' => gmdate('Y-m-d H:i:s', $close), 'limit_minutes' => $kind === 'public' ? 5 : null];
  if ($kind === 'jaas') {
    $jwt = jaas_jwt($u, $m['room'], $mod);
    if ($jwt) { $app = (string) cfg('JAAS_APP_ID'); $out = array_merge($out, ['domain' => '8x8.vc', 'app_id' => $app, 'jwt' => $jwt, 'url' => 'https://8x8.vc/' . $app . '/' . rawurlencode($m['room']) . '?jwt=' . $jwt]); }
    else { $out['engine'] = 'public'; $out['limit_minutes'] = 5; $out['engine_problem'] = 'The JaaS private key could not be read. Check JAAS_PRIVATE_KEY.'; }
  }
  return $out;
});
/** v109.2: the requester or HQ tells more WDOS people about an approved meeting. They are added to the invited list
 *  (so the gate lets them in) and get an in-app note, a phone notification and an email with the meeting link. */
route('POST', '/meet-requests/(\d+)/notify', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]);
  $role = $m ? mr_role($u, $m) : '';
  if (!$m || !in_array($role, ['requester', 'hq'], true)) fail(404, 'That meeting was not found.', 'not_found');
  if ($m['status'] !== 'approved') fail(409, 'People can be told once HQ has approved the meeting.', 'state');
  [, $close] = mr_window($m); if (time() > $close) fail(409, 'This meeting has ended.', 'closed');
  throttle('mrnotify:' . (int) $u['id'], 30, 86400); throttle_hit('mrnotify:' . (int) $u['id']);
  $b = body(); $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($b['people'] ?? []))))), 0, 500);
  $everyone = !empty($b['everyone_invited']);
  if ($ids) {
    if (is_hq($u)) $ok = array_map('intval', array_column(rows("SELECT id FROM users WHERE status='active' AND id IN (" . implode(',', $ids) . ')'), 'id'));
    else { [$w, $p] = dm_allowed_sql($u); $ok = array_map('intval', array_column(rows("SELECT u.id FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($w) AND u.id IN (" . implode(',', $ids) . ')', $p), 'id')); }
    $ids = array_values(array_diff(array_intersect($ids, $ok), [(int) $u['id']]));
  }
  if (!$ids && !$everyone) fail(422, 'Choose at least one person to tell.', 'people');
  $all = array_values(array_unique(array_merge(mr_ids($m), $ids)));
  if (count($all) > 1000) fail(422, 'A meeting can have up to 1000 invited people.', 'people');
  q('UPDATE meeting_requests SET attendee_ids=?, updated_at=? WHERE id=?', [implode(',', $all), now(), (int) $id]);
  $to = $everyone ? array_values(array_diff(array_unique(array_merge($all, [(int) $m['requester_id']])), [(int) $u['id']])) : $ids;
  if (!$to) fail(422, 'Nobody else is invited yet. Choose people to tell.', 'people');
  $when = gmdate('j M Y, H:i', strtotime($m['starts_at'] . ' UTC')) . ' GMT'; $url = '#/meetings/requests/' . $id;
  $from = trim($u['first_name'] . ' ' . $u['last_name']); $msg = str_in('message', 600);
  notify_many('u.id IN (' . implode(',', $to) . ')', [], 'meeting', 'You are invited: ' . mb_substr($m['title'], 0, 120), $when . ' · ' . $from . ($msg !== '' ? ': ' . mb_substr($msg, 0, 160) : ''), $url);
  foreach ($to as $i) person_mail($i, 'WDOS video meeting: ' . mb_substr($m['title'], 0, 120), 'You are invited to a video meeting',
    '<p>' . htmlspecialchars($from) . ' invites you to <b>' . htmlspecialchars($m['title']) . '</b>, ' . htmlspecialchars($when) . '.</p>' . ($msg !== '' ? '<p>' . nl2br(htmlspecialchars($msg)) . '</p>' : '') . '<p>Open the meeting in WDOS and tap <b>Join video meeting</b>. It opens inside WDOS.</p>' . (mr_flier((int) $id) ? '<p>The meeting flier is on the meeting page in WDOS.</p>' : ''), $url);
  log_activity((int) $u['id'], null, 'meeting_notified', $m['request_no'] . ' · ' . count($to), 'meetreq', (int) $m['id']);
  after_response(fn() => push_drain(40));
  return ['ok' => true, 'told' => count($to), 'invited' => count($all)];
});
/** v109.2: add or change the meeting flier. The leader who asked (until the meeting ends) or HQ. When HQ adds it, the leader is told. */
route('POST', '/meet-requests/(\d+)/flier', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]);
  $role = $m ? mr_role($u, $m) : '';
  if (!$m || !in_array($role, ['requester', 'hq'], true)) fail(404, 'That meeting was not found.', 'not_found');
  if (in_array($m['status'], ['declined', 'cancelled'], true)) fail(409, 'This meeting is not going ahead.', 'state');
  $f = mr_set_flier($u, (int) $id, (int) (body()['file_id'] ?? 0));
  if ($role === 'hq' && (int) $m['requester_id'] !== (int) $u['id']) {
    $url = '#/meetings/requests/' . $id;
    notify_many('u.id=?', [(int) $m['requester_id']], 'meeting', 'Meeting flier ready: ' . mb_substr($m['title'], 0, 120), 'Open the meeting to see and share it.', $url);
    person_mail((int) $m['requester_id'], 'WDOS meeting flier: ' . mb_substr($m['title'], 0, 120), 'Your meeting flier is ready', '<p>WODDI HQ added the flier for <b>' . htmlspecialchars($m['title']) . '</b>.</p><p>Open the meeting in WDOS to see it, download it and share it with your people.</p>', $url);
  }
  log_activity((int) $u['id'], null, 'meeting_flier', $m['request_no'] . ' · ' . $f['name'], 'meetreq', (int) $m['id']);
  return ['ok' => true, 'flier' => mr_flier((int) $id)];
});
/** After the meeting: the minutes, the leaders who attended, the minutes document and the recording go to every volunteer
 *  leader invited or present, the requester, and HQ (HQ dashboard panel, in-app note, phone, and an email to each HQ admin
 *  and to the HQ inbox). */
route('POST', '/meet-requests/(\d+)/minutes', function ($id) {
  $u = require_user(); $m = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $id]);
  if (!$m || (mr_role($u, $m) !== 'requester' && !is_hq($u))) fail(404, 'That meeting was not found.', 'not_found');
  if ($m['status'] !== 'approved' && $m['status'] !== 'held') fail(409, 'Minutes are recorded for an approved meeting.', 'state');
  if (time() < strtotime($m['starts_at'] . ' UTC')) fail(409, 'Minutes are recorded once the meeting has started.', 'not_yet');
  $b = body(); $min = str_in('minutes', 12000); $cnt = (int) ($b['attendance_count'] ?? -1); $names = str_in('attendance_names', 6000);
  if (mb_strlen($min) < 20) fail(422, 'Write the minutes: what was discussed and decided.', 'minutes');
  /* who attended: only people WDOS saw open the room, or people invited */
  $allowed = array_values(array_unique(array_merge(mr_ids($m), [(int) $m['requester_id']], array_map('intval', array_column(rows('SELECT user_id FROM meeting_request_joins WHERE request_id=?', [(int) $id]), 'user_id')))));
  $att = array_values(array_intersect(array_unique(array_map('intval', (array) ($b['attended'] ?? []))), $allowed));
  if ($cnt < count($att)) $cnt = count($att);
  if ($cnt < 1 || $cnt > 100000) fail(422, 'Enter how many people attended.', 'attendance_count');
  $files = [];
  foreach (['minutes_file' => 'minutes', 'recording_file' => 'recording'] as $k => $role) {
    $fid = (int) ($b[$k] ?? 0); if (!$fid) continue;
    $f = row('SELECT * FROM doc_files WHERE id=? AND uploaded_by=?', [$fid, (int) $u['id']]); if (!$f) fail(422, 'Upload the file again.', $k);
    if ($role === 'minutes' && !isset(DOC_TYPES_ANY[$f['mime']])) fail(422, 'The minutes document must be a PDF, Word or similar document.', $k);
    if ($role === 'recording' && !isset(DOC_TYPES_MEDIA[$f['mime']])) fail(422, 'The recording must be an audio or video file.', $k);
    $files[$role] = $f;
  }
  q("UPDATE meeting_requests SET status='held', minutes=?, attendance_count=?, attendance_names=?, attended_ids=?, minutes_by=?, minutes_at=?, updated_at=? WHERE id=?", [$min, $cnt, $names, implode(',', $att), (int) $u['id'], now(), now(), (int) $id]);
  foreach ($files as $role => $f) doc_link((int) $f['id'], 'meeting', (int) $id, $role, (int) $u['id']);
  $url = '#/meetings/requests/' . $id; $title = 'Minutes: ' . mb_substr($m['title'], 0, 120);
  $line = $cnt . ' attended.';   /* kept short so it is shown in each person's language (notify_i18n meet_minutes) */
  $plist = people_titled($att);
  $html = '<p><b>' . htmlspecialchars($m['title']) . '</b> (' . htmlspecialchars($m['request_no']) . '), ' . htmlspecialchars(gmdate('j M Y, H:i', strtotime($m['starts_at'] . ' UTC'))) . ' GMT</p>'
    . '<p><b>Attendance:</b> ' . $cnt . '</p>'
    . ($plist ? '<p><b>Leaders who attended</b></p><ul>' . implode('', array_map(fn($p) => '<li>' . htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) . ($p['title'] !== '' ? ', ' . htmlspecialchars($p['title']) : '') . '</li>', $plist)) . '</ul>' : '')
    . ($names !== '' ? '<p><b>Others present</b><br>' . nl2br(htmlspecialchars($names)) . '</p>' : '')
    . '<p><b>Minutes</b></p><p>' . nl2br(htmlspecialchars($min)) . '</p>'
    . ($files ? '<p><b>Attached in WDOS:</b> ' . implode(', ', array_map(fn($r, $f) => htmlspecialchars(($r === 'recording' ? 'Recording: ' : 'Minutes document: ') . $f['name']), array_keys($files), $files)) . '. Open the meeting in WDOS to play or download them.</p>' : '');
  $to = array_values(array_diff(array_unique(array_merge(mr_ids($m), [(int) $m['requester_id']], $att)), [(int) $u['id']]));
  $hqIds = array_map('intval', array_column(rows("SELECT id FROM users WHERE role IN ('hq_admin','super_admin') AND status='active'"), 'id'));
  $to = array_values(array_diff($to, $hqIds));
  if ($to) { notify_many('u.id IN (' . implode(',', $to) . ')', [], 'meeting', $title, $line, $url); foreach ($to as $i) person_mail($i, 'WDOS meeting minutes: ' . mb_substr($m['title'], 0, 110), 'Minutes of the meeting', $html, $url); }
  notify_hq('meeting', $title, $line, $url);
  foreach (array_diff($hqIds, [(int) $u['id']]) as $i) person_mail($i, 'WDOS meeting minutes ' . $m['request_no'] . ': ' . mb_substr($m['title'], 0, 100), 'Minutes of a leader video meeting', $html, $url);
  hq_mail('WDOS meeting minutes ' . $m['request_no'] . ': ' . mb_substr($m['title'], 0, 100), 'Minutes of a leader video meeting', $html, $url);
  log_activity((int) $u['id'], null, 'meeting_minutes', $m['request_no'] . ' · ' . $cnt . ' attended', 'meetreq', (int) $m['id']);
  after_response(fn() => push_drain(40));
  return ['ok' => true, 'told' => count($to), 'hq' => count($hqIds)];
});
/** HQ dashboard: the latest minutes of leader video meetings */
route('GET', '/hq/meeting-minutes', function () {
  $u = require_user(); if (!is_hq($u)) fail(403, 'For WODDI HQ.', 'forbidden');
  return ['items' => array_map(fn($m) => ['id' => (int) $m['id'], 'request_no' => $m['request_no'], 'title' => $m['title'], 'leader_name' => $m['leader_name'], 'country' => country_name($m['country_code']) ?: '', 'network' => $m['network'],
    'starts_at' => $m['starts_at'], 'minutes_at' => $m['minutes_at'], 'attendance_count' => (int) $m['attendance_count'], 'leaders' => count(mr_attended($m)), 'files' => (int) val("SELECT COUNT(*) FROM doc_links WHERE owner_type='meeting' AND owner_id=? AND role<>'flier' AND removed_at IS NULL", [(int) $m['id']]), 'summary' => mb_substr((string) $m['minutes'], 0, 220)],
    rows("SELECT * FROM meeting_requests WHERE status='held' AND minutes IS NOT NULL ORDER BY minutes_at DESC LIMIT 12"))];
});
/* -------------------------------------------------- WODDI Envoys sign themselves up -- */
route('GET', '/envoy/join-info', fn() => ['mode' => 'open', 'google' => (bool) cfg('GOOGLE_CLIENT_ID') && (bool) cfg('GOOGLE_CLIENT_SECRET')]);
route('POST', '/envoy/join', function () {
  $me = current_user(); $google = $me && $me['role'] === 'envoy' && $me['status'] === 'pending_profile';
  if ($me && !$google) fail(409, 'You are already signed in. Sign out first to make a WODDI Envoy account.', 'signed_in');
  throttle('envoy-join:' . client_ip(), 6, 3600);
  $b = body(); $first = str_in('first_name', 80); $last = str_in('last_name', 80); $phone = preg_replace('/[^\d+]/', '', str_in('phone', 32));
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter your first name and surname.', 'name');
  if (strlen(ltrim($phone, '+')) < 8) fail(422, 'Enter your phone number with the country code, for example +2348012345678.', 'phone');
  $cc = strtoupper(str_in('country_code', 2)); if (!preg_match('/^[A-Z]{2}$/', $cc) || !val("SELECT 1 FROM org_units WHERE level='country' AND country_code=?", [$cc])) fail(422, 'Choose your country.', 'country');
  $state = str_in('state_region', 120); $city = str_in('city', 120); $org = str_in('organisation', 160); $plan = str_in('reach_plan', 1000); $heard = str_in('heard_from', 160);
  if (mb_strlen($state) < 2) fail(422, 'Enter your state or region.', 'state_region');
  if (mb_strlen($plan) < 10) fail(422, 'Tell us in a sentence where you will share your link, for example your church women\'s group or your market association.', 'reach_plan');
  if (empty($b['consent'])) fail(422, 'Tick the box to agree to the WODDI Envoy terms and privacy notice.', 'consent');
  if ($google) { $id = (int) $me['id']; q("UPDATE users SET first_name=?, last_name=?, phone=?, country_code=?, state_region=?, status='active', designation='WODDI Envoy' WHERE id=?", [$first, $last, $phone, $cc, $state, $id]); }
  else {
    $email = strtolower(str_in('email', 190)); $pw = (string) ($b['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address. You sign in with it.', 'email');
    if (strlen($pw) < 8) fail(422, 'Choose a password of at least 8 characters.', 'password');
    if (val('SELECT 1 FROM users WHERE email=?', [$email])) fail(409, 'An account with this email already exists. Sign in instead.', 'email_taken');
    $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,phone,role,status,signup_method,country_code,state_region,designation,created_at) VALUES (?,?,?,?,?,'envoy','active','envoy',?,?,'WODDI Envoy',?)", [$email, password_hash($pw, PASSWORD_DEFAULT), $first, $last, $phone, $cc, $state, now()]);
  }
  if (val('SELECT 1 FROM envoys WHERE user_id=?', [$id])) fail(409, 'This envoy account is already set up.', 'already');
  throttle_hit('envoy-join:' . client_ip());
  $live = true;                         // every envoy who signs up gets her link at once; HQ can pause her later
  $lid = ins("INSERT INTO referral_links (code,owner_id,kind,network,label,campaign,active,created_at) VALUES (?,?,'envoy','',?,'envoy',?,?)", [ref_new_code(), $id, 'WODDI Envoy: ' . $first . ' ' . $last, $live ? 1 : 0, now()]);
  q('INSERT INTO envoys (user_id,link_id,country_code,status,created_by,created_at,state_region,city,organisation,reach_plan,heard_from,source) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$id, $lid, $cc, $live ? 'active' : 'pending', $id, now(), $state, $city ?: null, $org ?: null, $plan, $heard ?: null, 'self']);
  log_activity($id, $id, 'envoy_signed_up', $google ? 'google' : 'email');
  $who = $first . ' ' . $last . ' · ' . country_name($cc);
  notify_hq('account', $live ? 'New WODDI Envoy: ' . $first . ' ' . $last : 'WODDI Envoy waiting for approval: ' . $first . ' ' . $last, $who, '#/envoys/' . $id);
  hq_mail('WDOS: new WODDI Envoy ' . $first . ' ' . $last, $live ? 'A new WODDI Envoy signed up' : 'A WODDI Envoy is waiting for your approval',
    '<p><b>' . htmlspecialchars($first . ' ' . $last) . '</b>, ' . htmlspecialchars($state . ', ' . country_name($cc)) . '.</p><p><b>Where she will share her link:</b> ' . nl2br(htmlspecialchars($plan)) . '</p>' . ($live ? '<p>Her referral link is already on. You can pause her from WODDI Envoys in WDOS.</p>' : '<p>Open WODDI Envoys in WDOS to approve her.</p>'), '#/envoys/' . $id);
  if (!$google) session_start_for($id, 'password');
  return ['ok' => true, 'live' => $live, 'user' => public_user(row('SELECT * FROM users WHERE id=?', [$id]))];
});
