<?php
declare(strict_types=1);
/* v109: documents for reports and tasks, direct messages between people in WDOS, WODDI Envoys, and what each HQ staff login may open.
 * Loaded before every route: the guard on HQ features runs before any HQ route answers. */

/* ---- HQ features: a super administrator decides what each HQ staff login opens. NULL on the user means every feature. ---- */
const HQ_FEATURES = [
  'members' => 'Members and leader dashboards',
  'structure' => 'Leadership structure and seats',
  'chapters' => 'Chapters',
  'referrals' => 'Referral, activation and WODDI Envoys',
  'reports' => 'Reports and leader reports',
  'tasks' => 'Tasks, meetings and calendar',
  'communications' => 'Messages, announcements and inspiration',
  'library' => 'Document library management',
  'cases' => 'Confidential cases',
  'administration' => 'System rules, registers, AI, voices and integrations',
];
/** Which API paths belong to which feature. Paths a person uses for herself (her profile, her notifications) are never gated. */
const HQ_FEATURE_PATHS = [
  'members' => '#^/(hq/members|hq/leader-logins|hq/leaders/add|hq/signin-drive|hq/volunteers|hq/age-transition|leader/(dashboard|members))#',
  'structure' => '#^/(hq/seats|hq/terms|structure|leadership|leadmap)#',
  'chapters' => '#^/(chapters|zones|clusters|hq/journeys|hq/placement)#',
  'referrals' => '#^/(referrals|hq/activations|hq/envoy)#',
  'reports' => '#^/(reporting|reports)#',
  'tasks' => '#^/(tasks|meetings|meet-requests|calendar|hq/meetings)#',
  'communications' => '#^/(hq/announcements|hq/inspiration|hq/templates|hq/delivery|hq/moderation|messages)#',
  'cases' => '#^/(hq/cases|cases)#',
  'administration' => '#^/(hq/handoff|hq/health|hq/workflows|hq/ai|hq/guide-audio|hq/integrations|hq/operations|hq/sources|hq/content-import|decisions|policy)#',
];
const STAFF_ROSTER = [
  ['name' => 'Her Excellency Dr. Nneoma Nkechi Rochas Okorocha, PhD', 'first' => 'Nneoma', 'last' => 'Okorocha', 'designation' => 'Founder/CEO', 'email' => 'nkechi.o@thewoddi.org', 'features' => null],
  ['name' => 'Regina Okoro', 'first' => 'Regina', 'last' => 'Okoro', 'designation' => 'Head of Field and Country Operations', 'email' => 'regina@thewoddi.org', 'features' => ['members', 'structure', 'chapters', 'referrals', 'reports', 'tasks', 'communications']],
  ['name' => 'Amaranjo Laurel', 'first' => 'Laurel', 'last' => 'Amaranjo', 'designation' => 'Administration Lead', 'email' => 'laurel@thewoddi.org', 'features' => ['members', 'structure', 'chapters', 'tasks', 'communications', 'library']],
  ['name' => 'Mfon Nta', 'first' => 'Mfon', 'last' => 'Nta', 'designation' => 'Network Administrative Secretary', 'email' => 'mfon@thewoddi.org', 'features' => ['members', 'chapters', 'reports', 'tasks', 'communications', 'library']],
  ['name' => 'Azeez Razak', 'first' => 'Azeez', 'last' => 'Razak', 'designation' => 'IT Lead / WDOS Technical Administrator', 'email' => 'aziz@thewoddi.org', 'features' => null],
  ['name' => 'Okolo Ekene Peter', 'first' => 'Ekene', 'last' => 'Okolo', 'designation' => 'Quality Assurance, Accreditation and Monitoring Office', 'email' => 'ekene@thewoddi.org', 'features' => ['members', 'structure', 'referrals', 'reports', 'tasks']],
];
function hq_features_of(array $u): ?array {
  if (($u['role'] ?? '') === 'super_admin' || !isset($u['hq_features']) || $u['hq_features'] === null || $u['hq_features'] === '') return null;
  return array_values(array_intersect(array_keys(HQ_FEATURES), explode(',', (string) $u['hq_features'])));
}
function hq_can(array $u, string $feature): bool { if (!is_hq($u)) return false; $f = hq_features_of($u); return $f === null || in_array($feature, $f, true); }
/** Runs before the HQ routes. An HQ login without a feature gets 403 from the server, not just a hidden button. */
function hq_feature_guard(string $method, string $path): void {
  if (!preg_match('#^/(hq|reporting|reports|tasks|meetings|meet-requests|calendar|messages|dm|chapters|zones|clusters|referrals|structure|leadership|leader|cases|decisions|policy)#', $path)) return;
  if ($method === 'GET' && $path === '/policy') return;                 // the rules are read by everyone
  try { $u = current_user(); } catch (Throwable $e) { return; }
  if (!$u || ($u['role'] ?? '') !== 'hq_admin') return;
  $f = hq_features_of($u); if ($f === null) return;
  foreach (HQ_FEATURE_PATHS as $feature => $rx) if (preg_match($rx, $path) && !in_array($feature, $f, true)) fail(403, 'Your HQ login does not include this area. Ask a super administrator to add it under Staff and logins.', 'feature');
}

/* ---- the HQ inbox: one official address receives an email when work reaches HQ ---- */
function hq_inbox(): string { $e = trim((string) (function_exists('policy') ? policy('hq_notify_email') : '')); return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : 'woddingo@gmail.com'; }
function hq_mail(string $subject, string $heading, string $html, string $url): void {
  mail_queue(hq_inbox(), 'WODDI HQ', $subject, mail_wrap($heading, $html, 'Open in WDOS', app_url() . '/' . ltrim($url, '/')));
  after_response(fn() => mail_drain(6));
}
function person_mail(int $userId, string $subject, string $heading, string $html, string $url): void {
  $p = row('SELECT email, first_name FROM users WHERE id=? AND status=\'active\'', [$userId]); if (!$p) return;
  mail_queue((string) $p['email'], (string) $p['first_name'], $subject, mail_wrap($heading, $html, 'Open in WDOS', app_url() . '/' . ltrim($url, '/')));
  after_response(fn() => mail_drain(6));
}

/* ---- documents: stored in 512 KB pieces so one file never meets a database row limit ---- */
const DOC_CHUNK = 524288;
const DOC_MAX = 10485760;               // 10 MB
const REC_MAX = 15728640;               // 15 MB: a meeting recording or voice note (about an hour of voice at low data)
const DOC_TYPES_MEDIA = ['audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/ogg' => 'ogg', 'audio/webm' => 'webm', 'audio/wav' => 'wav', 'video/mp4' => 'mp4', 'video/webm' => 'webm'];
const DOC_TYPES_REPORT = ['application/pdf' => 'pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
const DOC_TYPES_ANY = DOC_TYPES_REPORT + ['application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx', 'text/csv' => 'csv', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
/** What the bytes really are. A renamed file does not pass: the content decides. */
function doc_sniff(string $bin, string $name): string {
  if (strncmp($bin, '%PDF-', 5) === 0) return 'application/pdf';
  if (strncmp($bin, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
  if (strncmp($bin, "\x89PNG", 4) === 0) return 'image/png';
  if (strncmp($bin, "\xD0\xCF\x11\xE0", 4) === 0) return 'application/msword';
  if (strncmp($bin, "PK\x03\x04", 4) === 0) {
    if (str_contains($bin, 'word/')) return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    if (str_contains($bin, 'xl/')) return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    if (str_contains($bin, 'ppt/')) return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    return 'application/zip';
  }
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (strncmp($bin, 'ID3', 3) === 0 || (strlen($bin) > 2 && ord($bin[0]) === 0xFF && (ord($bin[1]) & 0xE0) === 0xE0 && $ext === 'mp3')) return 'audio/mpeg';
  if (strncmp($bin, 'OggS', 4) === 0) return 'audio/ogg';
  if (strncmp($bin, 'RIFF', 4) === 0 && substr($bin, 8, 4) === 'WAVE') return 'audio/wav';
  if (strncmp($bin, "\x1A\x45\xDF\xA3", 4) === 0) return in_array($ext, ['weba', 'opus'], true) || str_contains($name, 'voice') || $ext === '' ? 'audio/webm' : ($ext === 'webm' && !str_contains(substr($bin, 0, 4096), 'V_VP') ? 'audio/webm' : 'video/webm');
  if (substr($bin, 4, 4) === 'ftyp') return in_array($ext, ['m4a', 'aac'], true) || str_contains(substr($bin, 8, 4), 'M4A') ? 'audio/mp4' : 'video/mp4';
  if (preg_match('/\.csv$/i', $name) && mb_check_encoding(substr($bin, 0, 4000), 'UTF-8')) return 'text/csv';
  return 'application/octet-stream';
}
/** The bytes of a file the browser sent: either inline (a data: URL or plain base64) or, for a large file, 'upload:<id>'
 * naming pieces already sent to /uploads/part by this same person. The pieces are joined in order and then deleted. */
function upload_bytes(array $u, string $data, int $max = 16000000): string {
  if (preg_match('/^upload:([a-f0-9]{32})$/', $data, $m)) {
    $parts = rows('SELECT seq, total, user_id, bytes FROM upload_parts WHERE upload_id=? ORDER BY seq', [$m[1]]);
    if (!$parts || (int) $parts[0]['user_id'] !== (int) $u['id'] || count($parts) !== (int) $parts[0]['total']) fail(422, 'The file did not arrive completely. Choose it again.', 'file');
    $bin = ''; foreach ($parts as $i => $p) { if ((int) $p['seq'] !== $i) fail(422, 'The file did not arrive completely. Choose it again.', 'file'); $bin .= $p['bytes']; if (strlen($bin) > $max) fail(422, 'That file is too large.', 'size'); }
    q('DELETE FROM upload_parts WHERE upload_id=?', [$m[1]]);
    return $bin;
  }
  $bin = base64_decode((string) preg_replace('#^data:[^;]*;base64,#', '', $data), true);
  if ($bin === false) fail(422, 'That file could not be read. Choose it again.', 'file');
  return $bin;
}
function doc_store(array $u, string $name, string $bin, bool $reportOnly, bool $media = false): array {
  if ($bin === '') fail(422, 'Choose a file first.', 'file');
  if ($media) {
    if (strlen($bin) > REC_MAX) fail(422, 'That recording is too large. The limit is 15 MB. Record a shorter voice note, or send the minutes as a document.', 'size');
    $mime = doc_sniff($bin, $name); $types = DOC_TYPES_MEDIA;
    if (!isset($types[$mime])) fail(422, 'Upload a recording as an audio file (MP3, M4A, OGG, WEBM or WAV) or an MP4 video.', 'type');
  } else {
  if (strlen($bin) > DOC_MAX) fail(422, 'That file is too large. The limit is 10 MB. Save the report as a PDF, or make the pictures in it smaller.', 'size');
  $mime = doc_sniff($bin, $name); $types = $reportOnly ? DOC_TYPES_REPORT : DOC_TYPES_ANY;
  if (!isset($types[$mime])) fail(422, $reportOnly ? 'A report document must be a PDF or a Word (.docx) file.' : 'Upload a PDF, Word, Excel, PowerPoint, CSV or picture file.', 'type');
  }
  $ext = $types[$mime]; $base = trim(preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '', pathinfo($name, PATHINFO_FILENAME))) ?: 'document';
  $clean = mb_substr($base, 0, 150) . '.' . $ext; $n = (int) ceil(strlen($bin) / DOC_CHUNK);
  $id = ins('INSERT INTO doc_files (name,mime,size,sha256,chunks,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?)', [$clean, $mime, strlen($bin), hash('sha256', $bin), $n, (int) $u['id'], now()]);
  for ($i = 0; $i < $n; $i++) { $st = db()->prepare('INSERT INTO doc_chunks (file_id,seq,bytes) VALUES (?,?,?)'); $st->bindValue(1, $id, PDO::PARAM_INT); $st->bindValue(2, $i, PDO::PARAM_INT); $st->bindValue(3, substr($bin, $i * DOC_CHUNK, DOC_CHUNK), PDO::PARAM_LOB); $st->execute(); }
  return ['id' => $id, 'name' => $clean, 'mime' => $mime, 'size' => strlen($bin), 'kind' => $ext];
}
function doc_link(int $fileId, string $type, int $ownerId, string $role, int $by, int $version = 1): void {
  q('DELETE FROM doc_links WHERE file_id=? AND owner_type=? AND owner_id=?', [$fileId, $type, $ownerId]);
  q('INSERT INTO doc_links (file_id,owner_type,owner_id,role,version,added_by,created_at) VALUES (?,?,?,?,?,?,?)', [$fileId, $type, $ownerId, $role, $version, $by, now()]);
}
function doc_list(string $type, int $ownerId): array {
  return array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'mime' => $r['mime'], 'size' => (int) $r['size'], 'role' => $r['role'], 'version' => (int) $r['version'], 'added_by' => trim($r['first_name'] . ' ' . $r['last_name']), 'added_by_id' => (int) $r['added_by'], 'created_at' => $r['created_at'], 'url' => '/api/files/' . $r['id']],
    rows('SELECT f.id, f.name, f.mime, f.size, l.role, l.version, l.added_by, l.created_at, u.first_name, u.last_name FROM doc_links l JOIN doc_files f ON f.id=l.file_id JOIN users u ON u.id=l.added_by WHERE l.owner_type=? AND l.owner_id=? AND l.removed_at IS NULL ORDER BY l.created_at', [$type, $ownerId]));
}
/** May this person open the file? Through any report or task it belongs to, or because she uploaded it. */
function doc_can(array $u, int $fileId): bool {
  if (is_hq($u)) return true;
  $f = row('SELECT uploaded_by FROM doc_files WHERE id=?', [$fileId]); if (!$f) return false;
  if ((int) $f['uploaded_by'] === (int) $u['id']) return true;
  foreach (rows('SELECT owner_type, owner_id FROM doc_links WHERE file_id=? AND removed_at IS NULL', [$fileId]) as $l) {
    if ($l['owner_type'] === 'report' && ($r = row('SELECT * FROM report_instances WHERE id=?', [(int) $l['owner_id']]))) { $rt = rp_rights($u, $r); if ($rt['view'] || $rt['review']) return true; }
    if ($l['owner_type'] === 'meeting' && ($mm = row('SELECT * FROM meeting_requests WHERE id=?', [(int) $l['owner_id']])) && (mr_role($u, $mm) !== '' || in_array((int) $u['id'], mr_attended($mm), true))) return true;
    if ($l['owner_type'] === 'task' && ($t = row(TASK_SELECT . ' WHERE t.id=?', [(int) $l['owner_id']]))) { if ((int) $t['assignee_id'] === (int) $u['id'] || task_can_manage($u, $t)) return true; }
  }
  return false;
}
function doc_send(int $fileId, bool $download): never {
  $f = row('SELECT * FROM doc_files WHERE id=?', [$fileId]); if (!$f) fail(404, 'That file was not found.', 'not_found');
  while (ob_get_level()) ob_end_clean();
  $media = isset(DOC_TYPES_MEDIA[$f['mime']]);
  $inline = !$download && ($media || in_array($f['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true));
  $size = (int) $f['size']; $from = 0; $to = $size - 1;
  /* byte ranges, so a recording plays and seeks on iPhone and Android */
  if ($media && preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $rg)) {
    if ($rg[1] === '' && $rg[2] !== '') { $from = max(0, $size - (int) $rg[2]); } else { $from = (int) $rg[1]; if ($rg[2] !== '') $to = min($to, (int) $rg[2]); }
    if ($from > $to || $from >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
    http_response_code(206); header('Content-Range: bytes ' . $from . '-' . $to . '/' . $size);
  }
  header('Content-Type: ' . $f['mime']); header('Content-Length: ' . ($to - $from + 1)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=300');
  if ($media) header('Accept-Ranges: bytes');
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"" . preg_replace('/[^A-Za-z0-9._ ()\-]/', '_', $f['name']) . "\"; filename*=UTF-8''" . rawurlencode($f['name']));
  if ($size === 0) exit;
  for ($i = intdiv($from, DOC_CHUNK); $i <= intdiv($to, DOC_CHUNK); $i++) {
    $b = (string) val('SELECT bytes FROM doc_chunks WHERE file_id=? AND seq=?', [$fileId, $i]); $s0 = $i * DOC_CHUNK;
    $a = max(0, $from - $s0); $e = min(strlen($b) - 1, $to - $s0); if ($e >= $a) echo substr($b, $a, $e - $a + 1); flush();
  }
  exit;
}

/* ---- direct messages ---------------------------------------------------- */
const DM_UNDO_SECONDS = 30;
/** Who this person may start a conversation with, as a WHERE on users u (with org_units ou LEFT JOINed on u.org_unit_id). */
function dm_allowed_sql(array $u): array {
  $me = (int) $u['id']; $hq = "u.role IN ('hq_admin','super_admin')";
  if (is_hq($u)) return ["u.id<>$me", []];
  if (($u['role'] ?? '') === 'envoy') return ["u.id<>$me AND ($hq OR u.id IN (SELECT r.user_id FROM referrals r WHERE r.referrer_id=?))", [$me]];
  $seat = work_seat($u);
  if (!$seat) return ["u.id<>$me AND ($hq OR EXISTS (SELECT 1 FROM seats sc WHERE sc.user_id=u.id AND sc.ends_at IS NULL AND sc.chapter_id IS NOT NULL AND sc.chapter_id=?))", [(int) ($u['chapter_id'] ?? 0)]];
  [$down, $dp] = work_people_sql($u);
  $up = 'EXISTS (SELECT 1 FROM seats s2 JOIN positions p2 ON p2.code=s2.position_code JOIN org_units o2 ON o2.id=s2.org_unit_id WHERE s2.user_id=u.id AND s2.ends_at IS NULL AND s2.network=? AND ? LIKE CONCAT(o2.path,\'%\') AND p2.rank_no<?)';
  $up_p = [$seat['network'], $seat['unit_path'], (int) $seat['rank_no']];
  $w = "u.id<>$me AND ($hq OR $down OR $up"; $p = array_merge($dp, $up_p);
  if (empty($seat['he_for_she']) && $seat['position_code'] !== 'campus_ambassador') { [$mw, $mp] = leader_scope($seat); $w .= " OR (u.role='member' AND $mw)"; $p = array_merge($p, $mp); }
  return [$w . ')', $p];
}
/** The audience of one send: the people picked one by one, or a group described by filters, always inside what the sender may reach. */
function dm_audience(array $u, array $a): array {
  [$w, $p] = dm_allowed_sql($u); $w = "u.status='active' AND ($w)";
  $ids = array_values(array_unique(array_filter(array_map('intval', is_array($a['people'] ?? null) ? $a['people'] : []))));
  if ($ids) return [$w . ' AND u.id IN (' . implode(',', array_slice($ids, 0, 500)) . ')', $p];
  $who = (string) ($a['who'] ?? 'leaders');
  if ($who === 'leaders') $w .= ' AND EXISTS (SELECT 1 FROM seats sa WHERE sa.user_id=u.id AND sa.ends_at IS NULL)';
  elseif ($who === 'members') $w .= " AND u.role='member' AND NOT EXISTS (SELECT 1 FROM seats sa WHERE sa.user_id=u.id AND sa.ends_at IS NULL)";
  elseif ($who === 'envoys') $w .= " AND u.role='envoy'";
  elseif ($who === 'staff') $w .= " AND u.role IN ('hq_admin','super_admin')";
  elseif ($who !== 'everyone') fail(422, 'Choose who the message is for.', 'who');
  if (in_array($a['network'] ?? '', ['WGMN', 'WNNN'], true)) { $w .= ' AND (u.network=? OR EXISTS (SELECT 1 FROM seats sn WHERE sn.user_id=u.id AND sn.ends_at IS NULL AND sn.network=?))'; $p[] = $a['network']; $p[] = $a['network']; }
  $lvl = (string) ($a['level'] ?? '');
  if (in_array($lvl, ['continental', 'country', 'state', 'lga', 'community_cluster', 'chapter'], true)) { $w .= ' AND EXISTS (SELECT 1 FROM seats sl JOIN positions pl ON pl.code=sl.position_code WHERE sl.user_id=u.id AND sl.ends_at IS NULL AND pl.level=?)'; $p[] = $lvl; }
  if (($pos = (string) ($a['position'] ?? '')) !== '') { $w .= ' AND EXISTS (SELECT 1 FROM seats sp WHERE sp.user_id=u.id AND sp.ends_at IS NULL AND sp.position_code=?)'; $p[] = $pos; }
  if (preg_match('/^[A-Z]{2}$/', (string) ($a['country'] ?? ''))) { $w .= ' AND u.country_code=?'; $p[] = $a['country']; }
  if (($st = trim((string) ($a['state'] ?? ''))) !== '') { $w .= ' AND u.state_region=?'; $p[] = mb_substr($st, 0, 120); }
  return [$w, $p];
}
function dm_title_of(int $userId): string {
  $u = row('SELECT id, role, designation FROM users WHERE id=?', [$userId]); if (!$u) return '';
  if (in_array($u['role'], ['hq_admin', 'super_admin'], true)) return $u['designation'] !== '' ? $u['designation'] . ', WODDI HQ' : 'WODDI HQ';
  if ($u['role'] === 'envoy') return 'WODDI Envoy';
  $s = current_seat($userId); return $s ? seat_title($s) : 'Member';
}
const DM_MAIL = [
  'en' => ['New message in WDOS from {name}', 'You have a new message', '{name} ({title}) sent you a message in the WODDI Digital Operating System.', 'Subject'],
  'fr' => ['Nouveau message dans WDOS de {name}', 'Vous avez un nouveau message', "{name} ({title}) vous a envoyé un message dans le système numérique de WODDI.", 'Objet'],
  'pt' => ['Nova mensagem no WDOS de {name}', 'Tem uma nova mensagem', '{name} ({title}) enviou-lhe uma mensagem no sistema digital da WODDI.', 'Assunto'],
  'ar' => ['رسالة جديدة في WDOS من {name}', 'لديك رسالة جديدة', 'أرسلت إليك {name} ({title}) رسالة في نظام WODDI الرقمي.', 'الموضوع'],
  'sw' => ['Ujumbe mpya kwenye WDOS kutoka kwa {name}', 'Una ujumbe mpya', '{name} ({title}) amekutumia ujumbe kwenye mfumo wa kidijitali wa WODDI.', 'Mada'],
];
/** One send to one person or to many. Each recipient gets a private conversation with the sender. Nothing reaches anyone until the undo time has passed. */
function dm_send(array $u, array $aud, string $subject, string $body, string $label): array {
  [$w, $p] = dm_audience($u, $aud);
  $count = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w", $p);
  if ($count === 0) fail(422, 'Nobody matches this choice. Change the filters or pick people one by one.', 'audience');
  $cap = is_hq($u) ? 5000 : 1000; if ($count > $cap) fail(422, "That is $count people. Send to $cap people at most at a time: narrow the choice by network, level or region.", 'audience');
  $me = (int) $u['id']; $n = now(); $snip = mb_substr(preg_replace('/\s+/u', ' ', ($subject !== '' ? $subject . ': ' : '') . $body), 0, 200);
  $bid = ins('INSERT INTO dm_batches (sender_id,subject,audience,recipients,undo_until,created_at) VALUES (?,?,?,?,?,?)', [$me, $subject, mb_substr($label, 0, 600), $count, gmdate('Y-m-d H:i:s', time() + DM_UNDO_SECONDS), $n]);
  q("INSERT IGNORE INTO dm_threads (lo_id,hi_id,created_at,last_at) SELECT LEAST(?,u.id), GREATEST(?,u.id), ?, ? FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w", array_merge([$me, $me, $n, $n], $p));
  q("INSERT INTO dm_messages (thread_id,sender_id,batch_id,subject,body,created_at) SELECT t.id, ?, ?, ?, ?, ? FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id JOIN dm_threads t ON t.lo_id=LEAST(?,u.id) AND t.hi_id=GREATEST(?,u.id) WHERE $w", array_merge([$me, $bid, $subject, $body, $n, $me, $me], $p));
  q('UPDATE dm_threads t JOIN dm_messages m ON m.thread_id=t.id SET t.last_at=?, t.last_sender=?, t.last_snippet=?, t.lo_hidden=0, t.hi_hidden=0 WHERE m.batch_id=?', [$n, $me, $snip, $bid]);
  log_activity($me, $count === 1 ? (int) val("SELECT u.id FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE $w LIMIT 1", $p) : null, 'message_sent', mb_substr(($subject ?: 'message') . " · $count " . ($count === 1 ? 'person' : 'people') . ($label !== '' ? " · $label" : ''), 0, 240), 'message', (int) $bid);
  return ['batch_id' => $bid, 'recipients' => $count, 'undo_until' => gmdate('Y-m-d H:i:s', time() + DM_UNDO_SECONDS), 'undo_seconds' => DM_UNDO_SECONDS];
}
/** After the undo time: tell each recipient in the app, on the phone, and by email, in her own language. */
function dm_release_due(): int {
  $done = 0;
  try {
    foreach (rows('SELECT * FROM dm_batches WHERE released_at IS NULL AND undone_at IS NULL AND undo_until<=? ORDER BY id LIMIT 5', [now()]) as $b) {
      if (q('UPDATE dm_batches SET released_at=? WHERE id=? AND released_at IS NULL', [now(), (int) $b['id']])->rowCount() === 0) continue;
      $s = row('SELECT id, first_name, last_name FROM users WHERE id=?', [(int) $b['sender_id']]); if (!$s) continue;
      $name = trim($s['first_name'] . ' ' . $s['last_name']); $title = dm_title_of((int) $s['id']);
      $where = "u.id IN (SELECT CASE WHEN t.lo_id=? THEN t.hi_id ELSE t.lo_id END FROM dm_messages m JOIN dm_threads t ON t.id=m.thread_id WHERE m.batch_id=?)"; $wp = [(int) $s['id'], (int) $b['id']];
      $first = row('SELECT body FROM dm_messages WHERE batch_id=? LIMIT 1', [(int) $b['id']]); $text = mb_substr(($b['subject'] !== '' ? $b['subject'] . ': ' : '') . preg_replace('/\s+/u', ' ', (string) ($first['body'] ?? '')), 0, 200);
      $one = (int) $b['recipients'] === 1 ? (int) val("SELECT u.id FROM users u WHERE $where LIMIT 1", $wp) : 0;
      $url = $one ? '#/inbox/' . (int) val('SELECT thread_id FROM dm_messages WHERE batch_id=? LIMIT 1', [(int) $b['id']]) : '#/inbox';
      notify_many($where, $wp, 'message', $name . ' · ' . $title, $text, $url, []);
      // email in each recipient's language, set-based so a thousand recipients cost a handful of statements
      if (mail_configured()) foreach (DM_MAIL as $l => [$subj, $head, $line, $subjLabel]) {
        $sv = str_replace('{name}', $name, $subj); $lv = str_replace(['{name}', '{title}'], [htmlspecialchars($name), htmlspecialchars($title)], $line);
        $html = mail_wrap($head, '<p>' . $lv . '</p>' . ($b['subject'] !== '' ? '<p><b>' . $subjLabel . ':</b> ' . htmlspecialchars((string) $b['subject']) . '</p>' : '') . '<p style="color:#5a4a52">' . nl2br(htmlspecialchars(mb_substr((string) ($first['body'] ?? ''), 0, 6000))) . '</p>', 'Open WDOS', app_url() . '/' . $url);
        $lw = $l === 'en' ? "(u.lang IS NULL OR u.lang NOT IN ('fr','pt','ar','sw'))" : 'u.lang=?';
        q("INSERT INTO mail_outbox (to_email,to_name,subject,html,created_at,batch_id) SELECT u.email, u.first_name, ?, ?, ?, ? FROM users u WHERE u.status='active' AND u.email LIKE '%@%' AND u.email NOT LIKE '%.invalid' AND u.email NOT LIKE '%.test' AND u.email NOT LIKE '%.local' AND u.email NOT LIKE '%example.%' AND $lw AND $where",
          array_merge([mb_substr($sv, 0, 190), $html, now(), (int) $b['id']], $l === 'en' ? [] : [$l], $wp));
      }
      $done++;
    }
    if ($done) { push_drain(60); mail_drain(12); }
  } catch (Throwable $e) { error_log('[wdos dm release] ' . $e->getMessage()); }
  return $done;
}
function dm_thread_for(array $u, int $id): array {
  $t = row('SELECT * FROM dm_threads WHERE id=?', [$id]); $me = (int) $u['id'];
  if (!$t || ((int) $t['lo_id'] !== $me && (int) $t['hi_id'] !== $me)) fail(404, 'That conversation was not found.', 'not_found');
  $t['other_id'] = (int) $t['lo_id'] === $me ? (int) $t['hi_id'] : (int) $t['lo_id']; $t['side'] = (int) $t['lo_id'] === $me ? 'lo' : 'hi';
  return $t;
}
/** Messages a recipient can see: sent ones always to the sender; to the recipient only after the undo time, and never an undone send. */
function dm_visible_sql(int $me): string { return "(m.sender_id=$me OR m.batch_id IS NULL OR EXISTS (SELECT 1 FROM dm_batches b WHERE b.id=m.batch_id AND b.undone_at IS NULL AND b.undo_until<=UTC_TIMESTAMP()))"; }
function dm_unread_total(int $me): int {
  try { return (int) val('SELECT COUNT(*) FROM dm_messages m JOIN dm_threads t ON t.id=m.thread_id WHERE (t.lo_id=? OR t.hi_id=?) AND m.sender_id<>? AND m.deleted_at IS NULL AND m.id > CASE WHEN t.lo_id=? THEN t.lo_read ELSE t.hi_read END AND ' . dm_visible_sql($me), [$me, $me, $me, $me]); } catch (Throwable $e) { return 0; }
}

/* ---- WODDI Envoys: refer people through one unique link; the people referred take the 3-day referral activation ---- */
function envoy_of(array $u): ?array { return ($u['role'] ?? '') === 'envoy' ? row('SELECT e.*, l.code FROM envoys e JOIN referral_links l ON l.id=e.link_id WHERE e.user_id=?', [(int) $u['id']]) : null; }
function envoy_people(int $envoyId): array {
  $out = [];
  foreach (rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.network, u.country_code, u.state_region, u.last_seen_at, r.position_code, r.created_at referred_at,
      a.id act_id, a.status act_status, a.deadline, a.started_at, a.submitted_at, a.outcome, a.route act_route
    FROM referrals r JOIN users u ON u.id=r.user_id LEFT JOIN activations a ON a.id=(SELECT MAX(a2.id) FROM activations a2 WHERE a2.user_id=u.id) WHERE r.referrer_id=? ORDER BY r.created_at DESC LIMIT 500", [$envoyId]) as $p) {
    $days = $p['act_id'] ? (int) val('SELECT COUNT(*) FROM activation_progress WHERE activation_id=? AND completed_at IS NOT NULL', [(int) $p['act_id']]) : 0;
    $started = $p['act_id'] ? (int) val('SELECT COUNT(*) FROM activation_progress WHERE activation_id=?', [(int) $p['act_id']]) : 0;
    $out[] = ['id' => (int) $p['id'], 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'has_photo' => (int) $p['has_photo'], 'network' => $p['network'], 'country' => country_name($p['country_code']), 'state_region' => $p['state_region'],
      'role' => $p['position_code'] ? position_title((string) $p['position_code'], (string) $p['network']) : '', 'referred_at' => $p['referred_at'], 'last_seen_at' => $p['last_seen_at'],
      'activation' => $p['act_id'] ? ['status' => $p['act_status'], 'deadline' => $p['deadline'], 'days_done' => $days, 'days_opened' => $started, 'days_total' => $p['act_route'] === 'open7' ? 7 : 3, 'assessment_submitted' => $p['submitted_at'] !== null, 'decided' => $p['outcome'] !== '',
        // v109.34: the same score HQ sees, and whether HQ has approved her yet
        'score' => activation_score(['id' => (int) $p['act_id'], 'route' => $p['act_route'], 'deadline' => $p['deadline']])] : null,
      'approved_as' => ($seat = current_seat((int) $p['id'])) ? seat_title($seat) : ''];
  }
  return $out;
}
/** v109.31: the volunteer leaders an envoy brought in (people who joined through her link and hold, or held, a seat),
 *  with what needs her attention: overdue tasks, not signing in, stopping serving. Azeez, 19 Nov 2026. */
function envoy_leaders(int $envoyId): array {
  $out = []; $today = function_exists('work_today') ? work_today() : gmdate('Y-m-d'); $wk = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
  foreach (rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.network, u.state_region, u.last_seen_at, u.phone, r.created_at referred_at FROM referrals r JOIN users u ON u.id=r.user_id
                 WHERE r.referrer_id=? AND EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id) ORDER BY u.first_name LIMIT 500", [$envoyId]) as $p) {
    $id = (int) $p['id']; $seat = current_seat($id);
    $last = $seat ? null : row('SELECT s.*, p.title_wgmn, p.title_wnnn, o.name unit_name FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id WHERE s.user_id=? ORDER BY s.id DESC LIMIT 1', [$id]);
    $t = row("SELECT COALESCE(SUM(status IN ('open','in_progress')),0) open, COALESCE(SUM(status IN ('open','in_progress') AND due_on IS NOT NULL AND due_on<?),0) overdue, COALESCE(SUM(status='completed'),0) done FROM tasks WHERE assignee_id=?", [$today, $id]);
    $flags = [];
    if ((int) $t['overdue'] > 0) $flags[] = ['kind' => 'task_overdue', 'n' => (int) $t['overdue'], 'text' => (int) $t['overdue'] === 1 ? '1 task overdue' : $t['overdue'] . ' tasks overdue'];
    $since = $seat ? $seat['starts_at'] : null;
    if ($seat && !$p['last_seen_at'] && $since && $since < gmdate('Y-m-d H:i:s', time() - 3 * 86400)) $flags[] = ['kind' => 'never_signed', 'text' => 'Has not signed in since her appointment'];
    elseif ($seat && $p['last_seen_at'] && $p['last_seen_at'] < $wk) { $nd = (int) floor((time() - strtotime($p['last_seen_at'] . ' UTC')) / 86400); $flags[] = ['kind' => 'no_signin', 'n' => $nd, 'text' => 'Not signed in for ' . $nd . ' days']; }
    if ($seat && in_array($seat['status'], ['on_leave', 'suspended'], true)) $flags[] = ['kind' => 'paused', 'text' => $seat['status'] === 'on_leave' ? 'On leave' : 'Suspended by HQ'];
    if (!$seat && $last) $flags[] = ['kind' => 'stopped', 'text' => ['resigned' => 'Resigned from her seat', 'removed' => 'Her appointment was ended', 'replaced' => 'Replaced in her seat'][$last['end_reason']] ?? 'No longer holds a seat', 'seat' => (int) $last['id']];
    $out[] = ['id' => $id, 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'has_photo' => (int) $p['has_photo'], 'network' => $p['network'], 'state_region' => $p['state_region'],
      'title' => $seat ? seat_title($seat) : ($last ? seat_title($last) : ''), 'serving' => (bool) $seat, 'referred_at' => $p['referred_at'], 'last_seen_at' => $p['last_seen_at'],
      'tasks_open' => (int) $t['open'], 'tasks_overdue' => (int) $t['overdue'], 'tasks_done' => (int) $t['done'], 'flags' => $flags];
  }
  usort($out, fn($a, $b) => (count($b['flags']) <=> count($a['flags'])) ?: strcmp($a['first_name'], $b['first_name']));
  return $out;
}
/** Once a day from 08:00 Lagos: each envoy is told, in the app and on her phone (and by email if she has one), about leaders she
 *  brought in who need her: a task overdue (once per task), not signing in (once a week), stopping serving (once per seat). */
function run_envoy_alerts(array &$done): void {
  $d = function_exists('hq_today') ? hq_today() : new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
  if ((int) $d->format('G') < 8 || !claim_send('envoy_alerts_' . $d->format('Y-m-d'))) return;
  $week = $d->format('o-W'); $told = 0;
  try {
    foreach (rows("SELECT e.user_id FROM envoys e JOIN users u ON u.id=e.user_id WHERE e.status='active' AND u.status='active'") as $e) {
      $eid = (int) $e['user_id']; $lines = [];
      foreach (envoy_leaders($eid) as $l) {
        $why = [];
        foreach ($l['flags'] as $f) {
          if ($f['kind'] === 'task_overdue') {
            foreach (rows("SELECT id FROM tasks WHERE assignee_id=? AND status IN ('open','in_progress') AND due_on IS NOT NULL AND due_on<?", [$l['id'], function_exists('work_today') ? work_today() : $d->format('Y-m-d')]) as $tk)
              if (q('INSERT IGNORE INTO envoy_alerts (envoy_id,user_id,kind,ref,sent_at) VALUES (?,?,?,?,?)', [$eid, $l['id'], 'task_overdue', (string) $tk['id'], now()])->rowCount()) { $why['t'] = $f['text']; }
          } else {
            $ref = in_array($f['kind'], ['no_signin', 'never_signed'], true) ? $week : (string) ($f['seat'] ?? $f['kind']);
            if (q('INSERT IGNORE INTO envoy_alerts (envoy_id,user_id,kind,ref,sent_at) VALUES (?,?,?,?,?)', [$eid, $l['id'], $f['kind'], $ref, now()])->rowCount()) $why[$f['kind']] = $f['text'];
          }
        }
        if ($why) $lines[] = trim($l['first_name'] . ' ' . $l['last_name']) . ': ' . strtolower(implode('; ', $why));
      }
      if (!$lines) continue;
      $title = count($lines) === 1 ? 'A leader you brought in needs you' : count($lines) . ' leaders you brought in need you';
      $body = implode("\n", array_slice($lines, 0, 5)) . (count($lines) > 5 ? "\n…and " . (count($lines) - 5) . ' more on your dashboard.' : '') . "\nOpen your dashboard to send her a message.";
      notify($eid, 'activation', $title, mb_substr($body, 0, 590), '#/');
      if (function_exists('mail_many')) mail_many("u.id=? AND u.status='active'", [$eid], $title, $body, '#/');
      $told++;
    }
  } catch (PDOException $ex) { return; }      // before migration 042
  $done['envoy_alerts'] = "$told envoys";
}

function envoy_kpis(int $envoyId, int $linkId): array {
  $ppl = envoy_people($envoyId); $d30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
  return ['visits_30' => (int) val('SELECT COUNT(*) FROM referral_visits WHERE link_id=? AND created_at>=?', [$linkId, $d30]), 'visits_total' => (int) val('SELECT COUNT(*) FROM referral_visits WHERE link_id=?', [$linkId]),
    'referred' => count($ppl), 'referred_30' => count(array_filter($ppl, fn($p) => $p['referred_at'] >= $d30)),
    'activation_started' => count(array_filter($ppl, fn($p) => $p['activation'] && $p['activation']['days_opened'] > 0)),
    'three_days_done' => count(array_filter($ppl, fn($p) => $p['activation'] && $p['activation']['days_done'] >= 3)),
    'assessment_submitted' => count(array_filter($ppl, fn($p) => $p['activation'] && $p['activation']['assessment_submitted'])),
    'active_7' => count(array_filter($ppl, fn($p) => $p['last_seen_at'] && $p['last_seen_at'] >= gmdate('Y-m-d H:i:s', time() - 7 * 86400))),
    'messages_sent_30' => (int) val('SELECT COUNT(*) FROM dm_messages WHERE sender_id=? AND created_at>=? AND deleted_at IS NULL', [$envoyId, $d30])];
}

/** After any reply: release messages whose undo time has passed and send waiting email and phone notifications. At most every 15 seconds. */
function outbox_tick(): void {
  try {
    $last = (int) (val("SELECT v FROM app_settings WHERE k='outbox_tick'") ?? 0);
    if (time() - $last < 15) return;
    if (q("UPDATE app_settings SET v=? WHERE k='outbox_tick' AND v=?", [(string) time(), (string) $last])->rowCount() === 0) {
      if (val("SELECT 1 FROM app_settings WHERE k='outbox_tick'")) return;                 // another request took this tick
      try { q("INSERT INTO app_settings (k,v) VALUES ('outbox_tick',?)", [(string) time()]); } catch (Throwable $e) { return; }
    }
    dm_release_due(); mail_drain(10); push_drain(30);
  } catch (Throwable $e) { /* before migration 024, or the database is busy: the next request tries again */ }
}

/* ---- video meetings a leader asks for: HQ approves, then the room opens at the meeting time ---- */
/* The call runs inside WDOS. Three engines, best first:
 *  1. Jitsi as a Service (8x8.vc) when JAAS_APP_ID, JAAS_API_KEY_ID and JAAS_PRIVATE_KEY are set: embedded, no time limit,
 *     each person gets a signed pass with her name. 8x8's free plan covers 25 people a month; above that 8x8 charges.
 *  2. A self-hosted Jitsi server named in JITSI_DOMAIN: embedded, no time limit, no cost but the server.
 *  3. Otherwise the public meet.jit.si server, still embedded, but meet.jit.si ends embedded calls after 5 minutes. */
function jaas_configured(): bool { return (string) cfg('JAAS_APP_ID') !== '' && (string) cfg('JAAS_API_KEY_ID') !== '' && (string) cfg('JAAS_PRIVATE_KEY') !== ''; }
function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function jaas_jwt(array $u, string $room, bool $moderator): ?string {
  $pem = str_replace('\\n', "\n", (string) cfg('JAAS_PRIVATE_KEY')); $key = openssl_pkey_get_private($pem);
  if (!$key) return null;
  $app = (string) cfg('JAAS_APP_ID'); $t = time();
  $head = ['alg' => 'RS256', 'kid' => (string) cfg('JAAS_API_KEY_ID'), 'typ' => 'JWT'];
  $body = ['aud' => 'jitsi', 'iss' => 'chat', 'sub' => $app, 'room' => $room, 'iat' => $t, 'nbf' => $t - 10, 'exp' => $t + 4 * 3600,
    'context' => ['user' => ['id' => 'wdos-' . (int) $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'email' => (string) ($u['email'] ?? ''), 'moderator' => $moderator ? 'true' : 'false'],
      'features' => ['livestreaming' => 'false', 'recording' => 'false', 'transcription' => 'false', 'outbound-call' => 'false']]];
  $msg = b64url(json_encode($head)) . '.' . b64url(json_encode($body, JSON_UNESCAPED_UNICODE));
  if (!openssl_sign($msg, $sig, $key, OPENSSL_ALGO_SHA256)) return null;
  return $msg . '.' . b64url($sig);
}
const CALL_SERVERS = ['meet.vpsfree.cz', 'meet.guifi.net', 'meet.jit.si'];
/** Which Jitsi server a new meeting uses: WODDI's own server when JITSI_DOMAIN is set, otherwise the community server chosen in System rules. */
function jitsi_domain(): string {
  $own = strtolower(trim((string) cfg('JITSI_DOMAIN', ''))); if ($own !== '' && preg_match('/^[a-z0-9.\-]+$/', $own)) return $own;
  $d = (string) policy('video_call_server'); return in_array($d, CALL_SERVERS, true) ? $d : 'meet.vpsfree.cz';
}
/** jaas: 8x8 keys set; public: meet.jit.si (5-minute embed limit, sign-in to start); community or own: no limit. */
function call_engine(?string $domain = null): string { if (jaas_configured()) return 'jaas'; $d = $domain ?: jitsi_domain(); return $d === 'meet.jit.si' ? 'public' : (in_array($d, CALL_SERVERS, true) ? 'community' : 'self'); }
/** The room opens 30 minutes before the start and closes three hours after the planned end. */
/** v109.2: the room opens the moment HQ approves (Azeez), and closes 3 hours after the planned end */
function mr_window(array $m): array {
  $s = strtotime($m['starts_at'] . ' UTC');
  $open = !empty($m['decided_at']) ? min($s - 1800, strtotime($m['decided_at'] . ' UTC')) : $s - 1800;
  return [$open, $s + (int) $m['duration_min'] * 60 + 3 * 3600];
}
function mr_ids(array $m): array { return array_values(array_filter(array_map('intval', explode(',', (string) $m['attendee_ids'])))); }
/** requester, invited WDOS people, or HQ */
function mr_attended(array $m): array { return array_values(array_filter(array_map('intval', explode(',', (string) ($m['attended_ids'] ?? ''))))); }
/** name and seat title of each person, for minutes and the attendance list */
function people_titled(array $ids): array {
  if (!$ids) return [];
  $out = [];
  foreach (rows('SELECT id, first_name, last_name, has_photo FROM users WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $p) {
    $seat = current_seat((int) $p['id']);
    $out[] = ['id' => (int) $p['id'], 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'has_photo' => (int) $p['has_photo'], 'title' => $seat ? seat_title($seat) : ''];
  }
  usort($out, fn($a, $b) => strcmp($a['first_name'] . $a['last_name'], $b['first_name'] . $b['last_name']));
  return $out;
}
function mr_role(array $u, array $m): string {
  if ((int) $m['requester_id'] === (int) $u['id']) return 'requester';
  if (in_array((int) $u['id'], mr_ids($m), true)) return 'invited';
  return is_hq($u) ? 'hq' : '';
}
function mr_out(array $u, array $m): array {
  $role = mr_role($u, $m); [$open, $close] = mr_window($m); $t = time();
  $req = row('SELECT id, first_name, last_name, has_photo FROM users WHERE id=?', [(int) $m['requester_id']]);
  $people = ($ids = mr_ids($m)) ? rows('SELECT id, first_name, last_name, has_photo FROM users WHERE id IN (' . implode(',', $ids) . ')') : [];
  $gate = $m['status'] === 'approved' ? ($t < $open ? 'not_yet' : ($t > $close ? 'closed' : 'open')) : ($m['status'] === 'held' ? 'closed' : 'locked');
  return ['id' => (int) $m['id'], 'request_no' => $m['request_no'], 'title' => $m['title'], 'reason' => $m['reason'], 'leader_name' => $m['leader_name'], 'network' => $m['network'], 'country' => country_name($m['country_code']) ?: '', 'country_code' => $m['country_code'], 'region' => $m['region'],
    'attendees_text' => $m['attendees_text'], 'people' => array_map(fn($p) => ['id' => (int) $p['id'], 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'has_photo' => (int) $p['has_photo']], $people), 'expected_count' => (int) $m['expected_count'],
    'graphics' => $m['graphics'], 'other_needs' => $m['other_needs'], 'starts_at' => $m['starts_at'], 'duration_min' => (int) $m['duration_min'], 'tz' => $m['tz'], 'status' => $m['status'], 'hq_note' => $m['hq_note'], 'decided_at' => $m['decided_at'],
    'requester' => $req ? ['id' => (int) $req['id'], 'first_name' => $req['first_name'], 'last_name' => $req['last_name'], 'has_photo' => (int) $req['has_photo']] : null, 'my_role' => $role,
    'gate' => $gate, 'opens_at' => gmdate('Y-m-d H:i:s', $open), 'joins' => (int) val('SELECT COUNT(*) FROM meeting_request_joins WHERE request_id=?', [(int) $m['id']]),
    'attended' => people_titled(mr_attended($m)), 'minute_files' => array_values(array_filter(doc_list('meeting', (int) $m['id']), fn($f) => $f['role'] !== 'flier')),
    'flier' => mr_flier((int) $m['id']),
    'joined' => (in_array($role, ['requester', 'hq'], true) && !$m['minutes']) ? people_titled(array_map('intval', array_column(rows('SELECT user_id FROM meeting_request_joins WHERE request_id=?', [(int) $m['id']]), 'user_id'))) : [],
    'minutes' => $m['minutes'], 'attendance_count' => $m['attendance_count'] === null ? null : (int) $m['attendance_count'], 'attendance_names' => $m['attendance_names'], 'minutes_at' => $m['minutes_at'],
    'can_decide' => is_hq($u) && in_array($m['status'], ['pending', 'approved'], true), 'can_cancel' => $role === 'requester' && in_array($m['status'], ['pending', 'approved'], true) && $t < strtotime($m['starts_at'] . ' UTC'),
    'can_flier' => in_array($role, ['requester', 'hq'], true) && !in_array($m['status'], ['declined', 'cancelled'], true) && $t <= $close,
    'can_notify' => in_array($role, ['requester', 'hq'], true) && $m['status'] === 'approved' && $t <= $close,
    'guest_link' => (in_array($role, ['requester', 'hq'], true) && $m['status'] === 'approved' && $t <= $close && $m['room'] && call_engine(($m['call_domain'] ?? '') ?: jitsi_domain()) !== 'jaas') ? 'https://' . (($m['call_domain'] ?? '') ?: jitsi_domain()) . '/' . rawurlencode($m['room']) : null,
    'can_minutes' => ($role === 'requester' || is_hq($u)) && $m['status'] === 'approved' && $t >= strtotime($m['starts_at'] . ' UTC'), 'created_at' => $m['created_at']];
}

/** v109.2: the meeting flier, a picture (JPG/PNG) or a PDF, added by the leader with her request or by HQ later */
const FLIER_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
function mr_flier(int $id): ?array {
  foreach (doc_list('meeting', $id) as $f) if ($f['role'] === 'flier') return $f;
  return null;
}
function mr_set_flier(array $u, int $meetingId, int $fileId): array {
  $f = row('SELECT * FROM doc_files WHERE id=? AND uploaded_by=?', [$fileId, (int) $u['id']]);
  if (!$f) fail(422, 'Upload the flier again.', 'flier_file');
  if (!isset(FLIER_TYPES[$f['mime']])) fail(422, 'The flier must be a picture (JPG or PNG) or a PDF.', 'flier_file');
  q("UPDATE doc_links SET removed_at=? WHERE owner_type='meeting' AND owner_id=? AND role='flier' AND removed_at IS NULL", [now(), $meetingId]);
  doc_link((int) $f['id'], 'meeting', $meetingId, 'flier', (int) $u['id']);
  return $f;
}

