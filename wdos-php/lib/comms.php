<?php
declare(strict_types=1);
/* Messages between a person and WODDI HQ, the e-mail queue, and the document library. */

const MSG_CATEGORIES = ['general' => 'General question', 'support' => 'I need help using WDOS', 'membership' => 'My membership or profile', 'leadership' => 'My leadership role', 'report' => 'Reports and tasks', 'concern' => 'A concern to raise with HQ'];

/* ------------------------------------------------------------- messages -- */
function thread_get(int $id, array $u): array {
  $t = row('SELECT t.*, p.first_name, p.last_name, p.has_photo, p.network, p.country_code, p.member_no FROM threads t JOIN users p ON p.id=t.user_id WHERE t.id=?', [$id]);
  if (!$t || (!is_hq($u) && (int) $t['user_id'] !== (int) $u['id'])) fail(404, 'That conversation was not found.', 'not_found');
  return $t;
}
function thread_shape(array $t, bool $hq): array {
  return ['id' => (int) $t['id'], 'subject' => $t['subject'], 'category' => $t['category'], 'category_label' => MSG_CATEGORIES[$t['category']] ?? 'General question', 'status' => $t['status'], 'unread' => (int) ($hq ? $t['hq_unread'] : $t['user_unread']),
    'snippet' => $t['last_snippet'], 'last_from_hq' => (int) $t['last_from_hq'] === 1, 'last_at' => $t['last_at'], 'created_at' => $t['created_at'],
    'person' => ['id' => (int) $t['user_id'], 'first_name' => $t['first_name'], 'last_name' => $t['last_name'], 'has_photo' => (int) $t['has_photo'], 'network' => $t['network'], 'country_code' => $t['country_code'], 'member_no' => $t['member_no']]];
}
/** Add one message to a conversation and tell the other side: in the app, by push, and by e-mail when mail is set up. */
function thread_post(array $t, array $sender, string $body): void {
  $fromHq = is_hq($sender); $snip = mb_substr(preg_replace('/\s+/', ' ', $body), 0, 200);
  q('INSERT INTO thread_messages (thread_id,sender_id,from_hq,body,created_at) VALUES (?,?,?,?,?)', [(int) $t['id'], (int) $sender['id'], $fromHq ? 1 : 0, $body, now()]);
  q('UPDATE threads SET last_at=?, last_snippet=?, last_from_hq=?, status=?, ' . ($fromHq ? 'user_unread=user_unread+1' : 'hq_unread=hq_unread+1') . ' WHERE id=?', [now(), $snip, $fromHq ? 1 : 0, $fromHq ? $t['status'] : 'open', (int) $t['id']]);
  $url = '#/messages/' . $t['id'];
  if ($fromHq) {
    notify((int) $t['user_id'], 'message', 'WODDI HQ: ' . mb_substr($t['subject'], 0, 120), $snip, $url);
    $p = row('SELECT email, first_name FROM users WHERE id=?', [(int) $t['user_id']]);
    if ($p) mail_queue($p['email'], $p['first_name'], 'WODDI HQ sent you a message: ' . mb_substr($t['subject'], 0, 120), mail_wrap('Dear ' . $p['first_name'] . ',', '<p>WODDI HQ wrote to you about <b>' . htmlspecialchars($t['subject']) . '</b>:</p><p style="white-space:pre-wrap;border-left:3px solid #D4006A;padding-left:12px">' . nl2br(htmlspecialchars(mb_substr($body, 0, 1500))) . '</p>', 'Reply in WDOS', app_url() . '/' . $url));
  } else notify_hq('message', trim($sender['first_name'] . ' ' . $sender['last_name']) . ': ' . mb_substr($t['subject'], 0, 110), $snip, $url);
  after_response(fn() => push_drain(20)); after_response(fn() => mail_drain(6));
}

route('GET', '/messages', function () {
  $u = require_user(); $hq = is_hq($u); $w = $hq ? '1=1' : 't.user_id=?'; $p = $hq ? [] : [(int) $u['id']];
  $st = (string) ($_GET['status'] ?? '');
  if (in_array($st, ['open', 'resolved'], true)) { $w .= ' AND t.status=?'; $p[] = $st; }
  if ($hq && $st === 'unread') $w .= ' AND t.hq_unread>0';
  if ($hq && ($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w .= " AND (t.subject LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ?)"; $p[] = "%$s%"; $p[] = "%$s%"; }
  $list = rows("SELECT t.*, p.first_name, p.last_name, p.has_photo, p.network, p.country_code, p.member_no FROM threads t JOIN users p ON p.id=t.user_id WHERE $w ORDER BY " . ($hq ? '(t.hq_unread>0) DESC' : '(t.user_unread>0) DESC') . ', t.last_at DESC LIMIT 150', $p);
  $c = $hq ? row("SELECT COALESCE(SUM(hq_unread>0),0) unread, COALESCE(SUM(status='open'),0) open, COUNT(*) total FROM threads") : row('SELECT COALESCE(SUM(user_unread>0),0) unread, COALESCE(SUM(status=\'open\'),0) open, COUNT(*) total FROM threads WHERE user_id=?', [(int) $u['id']]);
  return ['items' => array_map(fn($t) => thread_shape($t, $hq), $list), 'counts' => array_map('intval', $c), 'is_hq' => $hq, 'categories' => MSG_CATEGORIES];
});
route('GET', '/messages/unread', function () {
  $u = require_user();
  return ['unread' => (int) (is_hq($u) ? val('SELECT COUNT(*) FROM threads WHERE hq_unread>0') : val('SELECT COUNT(*) FROM threads WHERE user_id=? AND user_unread>0', [(int) $u['id']]))];
});
route('POST', '/messages', function () {
  $u = require_user(); $hq = is_hq($u); $subject = str_in('subject', 190); $body = str_in('body', 6000); $to = $hq ? (int) (body()['to'] ?? 0) : (int) $u['id'];
  if (mb_strlen($subject) < 3) fail(422, 'Give your message a subject.', 'subject');
  if (mb_strlen($body) < 2) fail(422, 'Write your message first.', 'body');
  if ($hq && !val("SELECT 1 FROM users WHERE id=? AND status='active' AND role='member'", [$to])) fail(422, 'Choose the person you are writing to.', 'to');
  throttle('msg:' . $u['id'], 30, 3600); throttle_hit('msg:' . $u['id']);
  $cat = isset(MSG_CATEGORIES[str_in('category', 20)]) ? str_in('category', 20) : 'general';
  $id = ins('INSERT INTO threads (user_id,subject,category,status,started_by_hq,created_at,last_at) VALUES (?,?,?,?,?,?,?)', [$to, $subject, $cat, 'open', $hq ? 1 : 0, now(), now()]);
  thread_post(thread_get($id, $u), $u, $body);
  return ['ok' => true, 'id' => $id];
});
route('GET', '/messages/(\d+)', function ($id) {
  $u = require_user(); $t = thread_get((int) $id, $u); $hq = is_hq($u);
  q('UPDATE threads SET ' . ($hq ? 'hq_unread' : 'user_unread') . '=0 WHERE id=?', [(int) $id]);
  q("UPDATE notifications SET read_at=? WHERE user_id=? AND kind='message' AND url=? AND read_at IS NULL", [now(), (int) $u['id'], '#/messages/' . (int) $id]);
  return ['thread' => thread_shape($t, $hq), 'is_hq' => $hq,
    'messages' => rows('SELECT m.id, m.body, m.from_hq, m.created_at, m.sender_id, s.first_name, s.last_name, s.has_photo FROM thread_messages m JOIN users s ON s.id=m.sender_id WHERE m.thread_id=? ORDER BY m.id', [(int) $id])];
});
route('POST', '/messages/(\d+)/reply', function ($id) {
  $u = require_user(); $t = thread_get((int) $id, $u); $body = str_in('body', 6000);
  if (mb_strlen($body) < 1) fail(422, 'Write your message first.', 'body');
  throttle('msg:' . $u['id'], 60, 3600); throttle_hit('msg:' . $u['id']);
  thread_post($t, $u, $body);
});
route('POST', '/messages/(\d+)/status', function ($id) {
  $u = require_hq(); $t = thread_get((int) $id, $u); $st = str_in('status', 12) === 'resolved' ? 'resolved' : 'open';
  q('UPDATE threads SET status=? WHERE id=?', [$st, (int) $id]);
  if ($st === 'resolved') notify((int) $t['user_id'], 'message', 'Resolved: ' . mb_substr($t['subject'], 0, 120), 'WODDI HQ marked this conversation as resolved. Reply at any time to open it again.', '#/messages/' . $id);
  return ['ok' => true, 'status' => $st];
});
/** HQ looks someone up to write to. */
route('GET', '/messages/people', function () {
  require_hq(); $s = trim((string) ($_GET['q'] ?? '')); if (mb_strlen($s) < 2) return ['people' => []];
  return ['people' => rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.network, u.country_code, u.member_no, u.state_region FROM users u WHERE u.status='active' AND u.role='member' AND (CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR u.member_no LIKE ?) ORDER BY u.first_name LIMIT 12", ["%$s%", "%$s%", "%$s%"])];
});

/* ------------------------------------------------------- document library -- */
function library_can(array $u, array $it): bool { return is_hq($u) || $it['audience'] === 'all' || ($it['audience'] === 'leaders' && (bool) work_seat($u)); }
route('GET', '/library', function () {
  $u = require_user(); $hq = is_hq($u);
  $list = rows('SELECT id, title, description, category, audience, kind, url, file_name, mime, origin, archived, created_at, LENGTH(bytes) size FROM library_items' . ($hq ? '' : ' WHERE archived=0') . ' ORDER BY archived, category, title');
  $out = []; foreach ($list as $it) if (library_can($u, $it)) { $it['id'] = (int) $it['id']; $it['archived'] = (int) $it['archived'] === 1; $it['size'] = (int) $it['size']; $out[] = $it; }
  return ['items' => $out, 'is_hq' => $hq];
});
route('POST', '/library', function () {
  $me = require_hq(); $b = body(); $title = str_in('title', 190); if (mb_strlen($title) < 3) fail(422, 'Give the document a title.', 'title');
  $aud = in_array($b['audience'] ?? '', ['all', 'leaders', 'hq'], true) ? $b['audience'] : 'leaders'; $url = str_in('url', 600); $bin = null; $mime = ''; $name = '';
  if (!empty($b['data'])) {
    $bin = base64_decode((string) preg_replace('#^data:[^;]+;base64,#', '', (string) $b['data']), true); $name = mb_substr(basename(str_in('file_name', 190)), 0, 190);
    if (!$bin || strlen($bin) > 6000000) fail(422, 'That file is too large. The limit is 6 MB.', 'size');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bin) ?: 'application/octet-stream';
    $ok = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/zip', 'text/plain'];
    if (!in_array($mime, $ok, true)) fail(422, 'Upload a PDF, Word, Excel, PowerPoint, text or picture file.', 'type');
  } elseif (!preg_match('#^https://#i', $url)) fail(422, 'Paste a link that starts with https://, or choose a file.', 'url');
  $st = db()->prepare('INSERT INTO library_items (title,description,category,audience,kind,url,file_name,mime,bytes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
  foreach ([$title, str_in('description', 600), str_in('category', 60) ?: 'General', $aud, $bin ? 'file' : 'link', $bin ? '' : $url, $name, $mime] as $i => $v) $st->bindValue($i + 1, $v);
  $st->bindValue(9, $bin, $bin ? PDO::PARAM_LOB : PDO::PARAM_NULL); $st->bindValue(10, (int) $me['id'], PDO::PARAM_INT); $st->bindValue(11, now()); $st->execute(); $newId = (int) db()->lastInsertId();
  log_activity((int) $me['id'], null, 'library_added', $title);
  return ['ok' => true, 'id' => $newId];
});
route('POST', '/library/(\d+)', function ($id) { require_hq(); q('UPDATE library_items SET archived=? WHERE id=?', [empty(body()['archived']) ? 0 : 1, (int) $id]); return ['ok' => true]; });
