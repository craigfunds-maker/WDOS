<?php
/* Copy EVERYTHING from the old (Supabase) WDOS into this WDOS, so nothing is lost. Command line only.
 *   php lib/legacy_cli.php            every table, then the stored files (CVs, task files, documents)
 *   php lib/legacy_cli.php tables     tables only
 *   php lib/legacy_cli.php files      stored files only
 * Needs two settings in config.local.php, copied from Supabase > Project Settings > API:
 *   'SUPABASE_URL' => 'https://<project>.supabase.co', 'SUPABASE_SERVICE_KEY' => '<service_role key>'
 * Safe to run again: rows and files already kept are skipped (unique keys). It only reads from Supabase.
 * Each table is counted on both sides and the counts are written to legacy_runs and printed. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/core.php';
function legacy_dir(): string { return rtrim((string) (cfg('LEGACY_DIR') ?: dirname(__DIR__, 2) . '/wdos-legacy-files'), '/'); }
@set_time_limit(0); ini_set('memory_limit', '1024M');
// the two settings may also sit in their own file beside the website folder (never inside it): wdos-supabase.php
$sbFile = dirname(__DIR__, 2) . '/wdos-supabase.php'; $sbCfg = is_file($sbFile) ? (array) require $sbFile : [];
$base = rtrim((string) ($sbCfg['SUPABASE_URL'] ?? '' ?: cfg('SUPABASE_URL')), '/'); $key = (string) ($sbCfg['SUPABASE_SERVICE_KEY'] ?? '' ?: cfg('SUPABASE_SERVICE_KEY'));
if ($base === '' || $key === '') { fwrite(STDERR, "Add SUPABASE_URL and SUPABASE_SERVICE_KEY to wdos-supabase.php (beside public_html) or config.local.php first.\n"); exit(2); }
$what = $argv[1] ?? 'all';

function sb(string $method, string $path, ?string $body = null, array $extra = [], bool $raw = false) {
  global $base, $key;
  for ($try = 0; $try < 4; $try++) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_HEADER => true,
      CURLOPT_HTTPHEADER => array_merge(['apikey: ' . $key, 'Authorization: Bearer ' . $key, 'Content-Type: application/json'], $extra)]);
    if ($method === 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);              // a HEAD reply has no body: do not wait for one
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    if ($res === false || $code >= 500 || $code === 429) { sleep(2 + 3 * $try); continue; }
    $head = substr($res, 0, $hs); $data = substr($res, $hs);
    return [$code, $raw ? $data : json_decode($data, true), $head];
  }
  return [0, null, ''];
}
function pick_time(array $r): ?string {
  foreach (['created_at', 'inserted_at', 'uploaded_at', 'sent_at', 'updated_at'] as $k) if (!empty($r[$k]) && ($t = strtotime((string) $r[$k]))) return gmdate('Y-m-d H:i:s', $t);
  return null;
}

/* who the record belongs to: every uuid column that matches an old account gives its email, so HQ can search by person */
$emails = [];
function person_of(array $r): ?string {
  global $emails;
  foreach (['profile_id', 'user_id', 'owner_id', 'requester_id', 'reporter_id', 'assigned_to', 'sender_id', 'recipient_id', 'author_id', 'member_id', 'candidate_id', 'id'] as $k)
    if (!empty($r[$k]) && is_string($r[$k]) && isset($emails[$r[$k]])) return $emails[$r[$k]];
  if (!empty($r['email']) && is_string($r['email'])) return strtolower($r['email']);
  return null;
}

if ($what === 'all' || $what === 'tables') {
  // the old accounts: id -> email (auth.users through the admin API)
  for ($page = 1; $page < 200; $page++) {
    [$c, $j] = sb('GET', '/auth/v1/admin/users?page=' . $page . '&per_page=500'); $list = $j['users'] ?? [];
    foreach ($list as $u) if (!empty($u['email'])) $emails[$u['id']] = strtolower($u['email']);
    if (count($list) < 500) break;
  }
  echo 'old accounts known: ' . count($emails) . "\n";
  // auth users themselves are kept too (no password hashes: those already came across with the member import)
  foreach ($emails as $id => $em) q('INSERT IGNORE INTO legacy_records (source_table,source_id,person_email,created_at,payload,imported_at) VALUES (?,?,?,?,?,?)', ['auth.users', $id, $em, null, json_encode(['id' => $id, 'email' => $em]), now()]);
  [$c, $spec] = sb('GET', '/rest/v1/');
  $tables = array_values(array_filter(array_map(fn($p) => ltrim((string) $p, '/'), array_keys($spec['paths'] ?? [])), fn($t) => $t !== '' && !str_starts_with($t, 'rpc/')));
  $skip = ['email_secrets', 'push_secrets', 'org_secrets', 'password_resets'];               // secrets of the old system are never copied
  $grand = 0;
  foreach ($tables as $t) {
    if (in_array($t, $skip, true)) { echo "skipped (secret store): $t\n"; continue; }
    [$c, , $head] = sb('HEAD', '/rest/v1/' . rawurlencode($t) . '?select=*', null, ['Prefer: count=exact', 'Range: 0-0']);
    $remote = preg_match('#content-range:\s*[^/]*/(\d+)#i', $head, $m) ? (int) $m[1] : -1;
    $kept = 0; $off = 0; $page = 1000;
    while (true) {
      [$c, $rows] = sb('GET', '/rest/v1/' . rawurlencode($t) . '?select=*&limit=' . $page . '&offset=' . $off);
      if ($c >= 400 || !is_array($rows)) { echo "  $t: read failed ($c)\n"; break; }
      foreach ($rows as $i => $r) {
        $sid = isset($r['id']) ? (string) $r['id'] : (isset($r['profile_id']) ? 'p:' . $r['profile_id'] . ':' . ($r['path'] ?? $off + $i) : 'row:' . ($off + $i) . ':' . substr(sha1(json_encode($r)), 0, 12));
        $kept += q('INSERT IGNORE INTO legacy_records (source_table,source_id,person_email,created_at,payload,imported_at) VALUES (?,?,?,?,?,?)',
          [$t, mb_substr($sid, 0, 80), person_of($r), pick_time($r), json_encode($r, JSON_UNESCAPED_UNICODE), now()])->rowCount();
      }
      if (count($rows) < $page) break; $off += $page;
    }
    $have = (int) val('SELECT COUNT(*) FROM legacy_records WHERE source_table=?', [$t]);
    q('INSERT INTO legacy_runs (source_table,remote_rows,kept_rows,ran_at,note) VALUES (?,?,?,?,?)', [$t, max(0, $remote), $have, now(), $remote >= 0 && $have >= $remote ? 'complete' : 'CHECK']);
    printf("%-40s old %6d  kept %6d  %s\n", $t, $remote, $have, $remote >= 0 && $have >= $remote ? 'OK' : 'CHECK');
    $grand += $have;
  }
  echo "rows kept in total: $grand\n";
}

if ($what === 'all' || $what === 'files') {
  [$c, $buckets] = sb('GET', '/storage/v1/bucket');
  if (!is_dir(legacy_dir()) && !@mkdir(legacy_dir(), 0750, true)) { fwrite(STDERR, 'Cannot create ' . legacy_dir() . "\n"); exit(3); }
  $walk = function (string $bucket, string $prefix) use (&$walk) {
    $n = 0;
    for ($off = 0; ; $off += 100) {
      [$c, $items] = sb('POST', '/storage/v1/object/list/' . rawurlencode($bucket), json_encode(['prefix' => $prefix, 'limit' => 100, 'offset' => $off, 'sortBy' => ['column' => 'name', 'order' => 'asc']]));
      if (!is_array($items) || !$items) break;
      foreach ($items as $it) {
        $path = ($prefix !== '' ? rtrim($prefix, '/') . '/' : '') . $it['name'];
        if (empty($it['id'])) { $n += $walk($bucket, $path); continue; }                    // a folder
        if (val('SELECT id FROM legacy_files WHERE bucket=? AND path=?', [$bucket, $path])) continue;
        [$code, $bin] = sb('GET', '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $path))), null, [], true);
        if ($code !== 200 || !is_string($bin)) { echo "  could not read $bucket/$path ($code)\n"; continue; }
        // kept as plain files in a private folder outside the website (394 MB is too much for database rows)
        if (preg_match('#(^|/)\.\.(/|$)#', $bucket . '/' . $path)) { echo "  unsafe path skipped: $bucket/$path\n"; continue; }
        $dest = legacy_dir() . '/' . $bucket . '/' . $path; @mkdir(dirname($dest), 0750, true);
        if (file_put_contents($dest, $bin) !== strlen($bin)) { echo "  could not save $bucket/$path\n"; continue; }
        $fid = null;
        q('INSERT INTO legacy_files (bucket,path,file_id,size,note,imported_at) VALUES (?,?,?,?,?,?)', [$bucket, mb_substr($path, 0, 400), $fid, strlen($bin), '', now()]);
        $n++;
      }
      if (count($items) < 100) break;
    }
    return $n;
  };
  foreach ((array) $buckets as $b) { $got = $walk((string) $b['id'], ''); echo "bucket {$b['id']}: $got new files kept\n"; }
  echo 'files kept in total: ' . (int) val('SELECT COUNT(*) FROM legacy_files') . ' in ' . legacy_dir() . "\n";
}
echo "LEGACY COPY DONE\n";
