<?php
declare(strict_types=1);
/* v109.5: Volunteer Leadership Personal Development Courses. The lesson text lives in assets/courses/<lang>/index.json
 * (the same in every language, in the order of the uploaded teaching edition, Nov 2026). The answer key stays here on the
 * server, so a learner's phone never holds it. Pass mark: 7 of 10, as the teaching edition proposes. A certificate
 * records completion only: it does not appoint anyone to a leadership role. */
const COURSE_BANK = 'tedition-2026-11';
const COURSE_PASS = 7;
const COURSE_KEY = [
  1 => 'CABDACDBCA',
  2 => 'BDACDBCABD',
  3 => 'ACDBCABDAC',
  4 => 'DBCABDACDB',
  5 => 'CABDACDBCA',
  6 => 'BDACDBCABD',
  7 => 'ACDBCABDAC',
  8 => 'DBCABDACDB',
  9 => 'CABDACDBCA',
  10 => 'BDACDBCABD',
  11 => 'ACDBCABDAC',
  12 => 'DBCABDACDB'
];
const COURSE_TITLES = [
  1 => 'Identity, Purpose and Values',
  2 => 'Confidence, Self-Worth and Resilience',
  3 => 'Discipline, Productivity and Time Management',
  4 => 'Emotional Intelligence and Self-Management',
  5 => 'Communication, Facilitation and Public Speaking',
  6 => 'Ethical Leadership and Service',
  7 => 'Financial Literacy and Resource Stewardship',
  8 => 'Digital Literacy, WDOS and Online Safety',
  9 => 'Conflict Resolution, Collaboration and Boundaries',
  10 => 'Wellbeing, Balance and Sustainable Volunteering',
  11 => 'Community Engagement and Impact',
  12 => 'Goal Setting, Mentorship and Leadership Readiness'
];
/* Pexels photographs (free to use under the Pexels licence) shown in each lesson; the server fetches and keeps them. */
const COURSE_PHOTOS = [
  1 => [33646629, 39598425, 27822437, 5945246, 33141463],
  2 => [8761552, 9968321, 6190671, 32103502, 5240843],
  3 => [6170644, 6170641, 7552367, 7593766, 12911263],
  4 => [36228845, 8342745, 32103502, 4557645, 14091541],
  5 => [8761560, 8123847, 9487622, 9034265, 18286979],
  6 => [39942530, 8061641, 9968321, 7849437, 7849436],
  7 => [20407552, 36866460, 15556066, 7173045, 10635140],
  8 => [38018031, 6190671, 33954347, 40419135, 7446597],
  9 => [1181360, 8483549, 1181435, 3894375, 3894383],
  10 => [29421662, 32630331, 36742030, 29716169, 14091541],
  11 => [20853361, 38166105, 28702872, 17902867, 11679893],
  12 => [3869639, 32103502, 5306434, 30690403, 7552367]
];
/* v109.8: each network has its own course pictures. WNNN shows young people (18 to 29) on its course cards, and each
 * network has its own hero picture on the course list. WGMN cards use the first picture of each course set. */
const COURSE_HERO = ['WGMN' => 20853361, 'WNNN' => 7849436];
const COURSE_THUMBS_WNNN = [1 => 32711379, 2 => 33664024, 3 => 5311453, 4 => 5050355, 5 => 29328857, 6 => 39942530, 7 => 9430881, 8 => 1181667, 9 => 34211750, 10 => 7113357, 11 => 8061641, 12 => 8093007];
function course_photo_list(): array {
  $l = [];
  foreach (COURSE_HERO as $n => $id) $l['course/hero-' . strtolower($n)] = [$id, 'WODDI ' . $n . ' volunteer leaders'];
  foreach (COURSE_THUMBS_WNNN as $c => $id) $l[sprintf('course/wnnn-c%02d', $c)] = [$id, COURSE_TITLES[$c]];
  foreach (COURSE_PHOTOS as $c => $ids) foreach ($ids as $i => $id) $l[sprintf('course/c%02d-%d', $c, $i + 1)] = [$id, COURSE_TITLES[$c]];
  return $l;
}
function course_photos_seed(int $max = 8): int {
  require_once __DIR__ . '/inspire_fns.php'; $n = 0;
  foreach (course_photo_list() as $key => [$id, $alt]) {
    if (val('SELECT 1 FROM message_photos WHERE image_key=?', [$key])) continue;
    if ($n++ >= $max) return $n;
    $e = insp_photo_fetch($key, (int) $id, '', $alt);
    if ($e !== '') error_log('[wdos course photos] ' . $key . ': ' . $e);
  }
  return $n;
}
function cert_code(): string {
  $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  do { $s = ''; for ($i = 0; $i < 12; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; $code = 'WD-' . substr($s, 0, 4) . '-' . substr($s, 4, 4) . '-' . substr($s, 8, 4); }
  while (val('SELECT 1 FROM certificates WHERE code=?', [$code]));
  return $code;
}
function cert_issue(array $u, int $courseNo, int $score): array {
  $have = row('SELECT * FROM certificates WHERE user_id=? AND course_no=? AND revoked_at IS NULL', [(int) $u['id'], $courseNo]);
  if ($have) return $have;
  q('DELETE FROM certificates WHERE user_id=? AND course_no=?', [(int) $u['id'], $courseNo]);
  $code = cert_code(); $name = trim($u['first_name'] . ' ' . $u['last_name']);
  ins('INSERT INTO certificates (code,user_id,course_no,holder_name,network,score,issued_at) VALUES (?,?,?,?,?,?,?)', [$code, (int) $u['id'], $courseNo, mb_substr($name, 0, 190), (string) ($u['network'] ?? ''), $score, now()]);
  return row('SELECT * FROM certificates WHERE code=?', [$code]);
}
function cert_out(array $c): array {
  return ['code' => $c['code'], 'course_no' => (int) $c['course_no'], 'course' => $c['course_no'] ? COURSE_TITLES[(int) $c['course_no']] : 'Volunteer Leadership Personal Development Programme',
    'name' => $c['holder_name'], 'network' => $c['network'], 'score' => (int) $c['score'], 'issued_at' => $c['issued_at'], 'valid' => $c['revoked_at'] === null];
}

/* v109.10: short Pexels video clips for the lesson video, three per course and a set of young people for WNNN.
 * Chosen by hand on pexels.com (African women and young people; no AI-made clips). The 5-minute timer copies them,
 * two at a time, from Pexels to assets/media/video as plain files the web server sends directly. */
const COURSE_VIDEOS = [
  1 => ['4316824-sd_960_540_30fps.mp4', '5658292-sd_960_540_25fps.mp4', '8496259-sd_960_540_25fps.mp4'],
  2 => ['8136037-sd_960_540_25fps.mp4', '8873216-sd_960_540_25fps.mp4', '8873236-sd_960_540_25fps.mp4'],
  3 => ['6607913-sd_960_506_24fps.mp4', '6817048-sd_960_540_30fps.mp4', '5974570-sd_360_640_24fps.mp4'],
  4 => ['8135956-sd_960_540_25fps.mp4', '8844676-sd_960_506_24fps.mp4', '5044335-sd_960_540_30fps.mp4'],
  5 => ['8124286-sd_360_640_30fps.mp4', '9034406-sd_960_540_24fps.mp4', '6563886-sd_960_540_25fps.mp4'],
  6 => ['7475339-sd_960_540_25fps.mp4', '6565765-sd_960_506_25fps.mp4', '6565777-sd_506_960_25fps.mp4'],
  7 => ['8869620-sd_960_506_25fps.mp4', '8869915-sd_960_506_25fps.mp4', '20450812-sd_960_540_30fps.mp4'],
  8 => ['5351496-sd_960_540_24fps.mp4', '5664426-sd_960_540_25fps.mp4', '7967293-sd_360_640_25fps.mp4'],
  9 => ['7491474-sd_360_640_30fps.mp4', '7491483-sd_960_540_30fps.mp4', '6860982-sd_360_640_25fps.mp4'],
  10 => ['5664057-sd_960_540_25fps.mp4', '6646594-sd_960_540_25fps.mp4', '8136124-sd_360_640_25fps.mp4'],
  11 => ['19749256-sd_960_540_24fps.mp4', '8717541-sd_360_640_25fps.mp4', '6646716-sd_960_540_24fps.mp4'],
  12 => ['8716869-sd_960_540_25fps.mp4', '9034462-sd_960_540_24fps.mp4', '8485236-sd_360_640_25fps.mp4'],
  'wnnn' => ['7683400-sd_360_640_30fps.mp4', '8198507-sd_960_540_25fps.mp4', '8199379-sd_960_540_25fps.mp4', '6496231-sd_960_540_30fps.mp4', '3044657-sd_960_540_24fps.mp4', '9198421-sd_960_540_25fps.mp4', '8060865-sd_960_540_25fps.mp4'],
];
function course_video_dir(): string { return __DIR__ . '/../assets/media/video'; }
function course_videos_tick(int $max = 2): int {
  $dir = course_video_dir(); if (!is_dir($dir)) @mkdir($dir, 0755, true); $n = 0;
  foreach (COURSE_VIDEOS as $files) foreach ($files as $f) {
    if (is_file("$dir/$f") || $n >= $max) continue;
    if (!preg_match('/^(\d+)-[a-z0-9_]+\.mp4$/', $f, $m)) continue;
    $n++;
    $ch = curl_init("https://videos.pexels.com/video-files/{$m[1]}/$f");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WDOS/1.0; +https://thewoddi.org)']);
    $bin = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !is_string($bin) || strlen($bin) < 20000 || strlen($bin) > 20000000 || substr($bin, 4, 4) !== 'ftyp') { error_log("[wdos video] $f: Pexels answered $code"); continue; }
    $tmp = "$dir/.$f.part"; if (@file_put_contents($tmp, $bin) !== false) @rename($tmp, "$dir/$f");
  }
  return $n;
}
function course_videos_out(): array {
  $dir = course_video_dir(); $out = [];
  foreach (COURSE_VIDEOS as $k => $files) foreach ($files as $f) if (is_file("$dir/$f")) $out[is_int($k) ? 'c' . $k : $k][] = '/assets/media/video/' . $f;
  return $out;
}
