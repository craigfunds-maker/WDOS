<?php
declare(strict_types=1);
/* v109.3: HQ looks through everything kept from the old (Supabase) WDOS: counts per table checked against the old system,
 * search by person (email), table and words, and the old stored files. Read-only. Super administrators only, because the
 * old records include private case and safeguarding material. */
route('GET', '/hq/legacy', function () {
  $u = require_user(); if (($u['role'] ?? '') !== 'super_admin') fail(403, 'For the WDOS super administrator.', 'forbidden');
  try { val('SELECT 1 FROM legacy_records LIMIT 1'); } catch (PDOException $e) { return ['ready' => false, 'tables' => [], 'items' => [], 'files' => 0]; }
  $runs = rows('SELECT r.source_table, r.remote_rows, r.kept_rows, r.ran_at, r.note FROM legacy_runs r JOIN (SELECT source_table, MAX(id) id FROM legacy_runs GROUP BY source_table) x ON x.id=r.id ORDER BY r.source_table');
  $w = ['1=1']; $p = [];
  if (($t = trim((string) ($_GET['table'] ?? ''))) !== '') { $w[] = 'source_table=?'; $p[] = $t; }
  if (($e = strtolower(trim((string) ($_GET['email'] ?? '')))) !== '') { $w[] = 'person_email=?'; $p[] = $e; }
  if (($s = trim((string) ($_GET['q'] ?? ''))) !== '') { $w[] = 'payload LIKE ?'; $p[] = '%' . $s . '%'; }
  $page = max(1, (int) ($_GET['page'] ?? 1)); $where = implode(' AND ', $w);
  $total = (int) val("SELECT COUNT(*) FROM legacy_records WHERE $where", $p);
  $items = array_map(fn($r) => ['id' => (int) $r['id'], 'table' => $r['source_table'], 'source_id' => $r['source_id'], 'email' => $r['person_email'], 'created_at' => $r['created_at'], 'data' => json_decode($r['payload'], true)],
    rows("SELECT * FROM legacy_records WHERE $where ORDER BY created_at DESC, id DESC LIMIT 25 OFFSET " . (($page - 1) * 25), $p));
  return ['ready' => true, 'tables' => $runs, 'total' => $total, 'page' => $page, 'items' => $items,
    'files' => (int) val('SELECT COUNT(*) FROM legacy_files'), 'file_list' => rows('SELECT l.bucket, l.path, l.file_id, l.size FROM legacy_files l ORDER BY l.bucket, l.path LIMIT 600')];
});

/* one old stored file (a CV, a photo, a task file), opened from the private folder; super administrator only */
route('GET', '/hq/legacy/file', function () {
  $u = require_user(); if (($u['role'] ?? '') !== 'super_admin') fail(403, 'For the WDOS super administrator.', 'forbidden');
  $f = row('SELECT bucket, path FROM legacy_files WHERE bucket=? AND path=?', [(string) ($_GET['bucket'] ?? ''), (string) ($_GET['path'] ?? '')]);
  if (!$f || preg_match('#(^|/)\.\.(/|$)#', $f['bucket'] . '/' . $f['path'])) fail(404, 'That file was not found.', 'not_found');
  $dir = rtrim((string) (cfg('LEGACY_DIR') ?: dirname(__DIR__, 2) . '/wdos-legacy-files'), '/');
  $file = $dir . '/' . $f['bucket'] . '/' . $f['path'];
  if (!is_file($file)) fail(404, 'That file is not on this server.', 'not_found');
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
  header('Content-Type: ' . $mime); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
  header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename($file)) . '"'); header('Content-Length: ' . filesize($file));
  readfile($file); exit;
});
