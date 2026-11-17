<?php
declare(strict_types=1);
/* v109.5 routes: Volunteer Leadership Personal Development Courses and verifiable certificates. */

route('GET', '/courses', function () {
  $u = require_user(); $id = (int) $u['id'];
  $done = []; foreach (rows('SELECT course_no, module_no FROM course_progress WHERE user_id=?', [$id]) as $r) $done[(int) $r['course_no']][] = (int) $r['module_no'];
  $best = []; foreach (rows('SELECT course_no, MAX(score) s, COUNT(*) n, MAX(passed) p FROM course_attempts WHERE user_id=? GROUP BY course_no', [$id]) as $r) $best[(int) $r['course_no']] = $r;
  $certs = []; foreach (rows('SELECT * FROM certificates WHERE user_id=? AND revoked_at IS NULL', [$id]) as $c) $certs[(int) $c['course_no']] = cert_out($c);
  $out = [];
  foreach (COURSE_TITLES as $no => $t) $out[] = ['no' => $no, 'modules_done' => $done[$no] ?? [], 'best' => isset($best[$no]) ? (int) $best[$no]['s'] : null,
    'attempts' => isset($best[$no]) ? (int) $best[$no]['n'] : 0, 'passed' => !empty($best[$no]['p']), 'certificate' => $certs[$no] ?? null];
  $photos = []; foreach (rows("SELECT image_key, updated_at FROM message_photos WHERE image_key LIKE 'course/%'") as $p) $photos[$p['image_key']] = '/api/inspiration/photo?k=' . rawurlencode($p['image_key']) . '&v=' . strtotime($p['updated_at']);
  return ['courses' => $out, 'programme' => $certs[0] ?? null, 'pass' => COURSE_PASS, 'photos' => $photos, 'name' => trim($u['first_name'] . ' ' . $u['last_name'])];
});

route('POST', '/courses/(\d+)/modules/(\d+)', function ($c, $m) {
  $u = require_user(); $c = (int) $c; $m = (int) $m;
  if (!isset(COURSE_TITLES[$c]) || $m < 1 || $m > 5) fail(404, 'That lesson was not found.', 'not_found');
  if ($m > 1 && !val('SELECT 1 FROM course_progress WHERE user_id=? AND course_no=? AND module_no=?', [(int) $u['id'], $c, $m - 1])) fail(409, 'Finish the lesson before this one first.', 'order');
  q('INSERT IGNORE INTO course_progress (user_id,course_no,module_no,completed_at) VALUES (?,?,?,?)', [(int) $u['id'], $c, $m, now()]);
  return ['ok' => true];
});

route('POST', '/courses/(\d+)/quiz', function ($c) {
  $u = require_user(); $c = (int) $c; if (!isset(COURSE_KEY[$c])) fail(404, 'That course was not found.', 'not_found');
  throttle('quiz:' . (int) $u['id'], 30, 3600); throttle_hit('quiz:' . (int) $u['id']);
  if ((int) val('SELECT COUNT(*) FROM course_progress WHERE user_id=? AND course_no=?', [(int) $u['id'], $c]) < 5) fail(409, 'Finish all five lessons before the quiz.', 'lessons');
  $a = body()['answers'] ?? null;
  if (!is_array($a) || count($a) !== 10) fail(422, 'Answer all ten questions.', 'answers');
  $a = array_map(fn($x) => strtoupper(substr((string) $x, 0, 1)), $a);
  foreach ($a as $x) if (!in_array($x, ['A', 'B', 'C', 'D'], true)) fail(422, 'Answer all ten questions.', 'answers');
  $key = str_split(COURSE_KEY[$c]); $score = 0; $wrong = [];
  foreach ($key as $i => $k) { if ($a[$i] === $k) $score++; else $wrong[] = $i; }
  $passed = $score >= COURSE_PASS;
  ins('INSERT INTO course_attempts (user_id,course_no,score,passed,answers,bank_version,created_at) VALUES (?,?,?,?,?,?,?)', [(int) $u['id'], $c, $score, $passed ? 1 : 0, implode('', $a), COURSE_BANK, now()]);
  $cert = null; $programme = null;
  if ($passed) {
    $cert = cert_out(cert_issue($u, $c, $score));
    notify((int) $u['id'], 'reminder', 'Certificate earned: ' . COURSE_TITLES[$c], 'You passed with ' . $score . ' of 10. Your certificate is ready to view, download and share.', '#/certificate/' . $cert['code']);
    $passedAll = (int) val('SELECT COUNT(DISTINCT course_no) FROM course_attempts WHERE user_id=? AND passed=1', [(int) $u['id']]);
    if ($passedAll >= count(COURSE_TITLES)) $programme = cert_out(cert_issue($u, 0, (int) round((float) val('SELECT AVG(m) FROM (SELECT MAX(score) m FROM course_attempts WHERE user_id=? GROUP BY course_no) x', [(int) $u['id']]) * 10)));
    log_activity((int) $u['id'], (int) $u['id'], 'course_passed', COURSE_TITLES[$c] . " ($score/10)");
  }
  // which questions to look at again; the right answers stay on the server, so a retake is a real second try
  return ['score' => $score, 'passed' => $passed, 'pass' => COURSE_PASS, 'wrong' => $wrong, 'certificate' => $cert, 'programme' => $programme];
});

route('GET', '/certificates/([A-Z0-9-]{8,24})', function ($code) {
  $u = require_user(); $c = row('SELECT * FROM certificates WHERE code=?', [$code]);
  if (!$c || ((int) $c['user_id'] !== (int) $u['id'] && !is_hq($u))) fail(404, 'That certificate was not found.', 'not_found');
  return ['certificate' => cert_out($c)];
});

/* public: anyone who scans the QR code sees whether the certificate is genuine (name, course, date), and nothing else */
route('GET', '/public/certificates/([A-Z0-9-]{8,24})', function ($code) {
  throttle('certv:' . client_ip(), 120, 3600); throttle_hit('certv:' . client_ip());
  $c = row('SELECT * FROM certificates WHERE code=?', [$code]);
  if (!$c) return ['found' => false];
  $o = cert_out($c); unset($o['score']);
  return ['found' => true, 'certificate' => $o + ['revoked_reason' => $c['revoked_at'] ? 'Withdrawn by WODDI Headquarters' : null]];
});

route('GET', '/hq/courses', function () {
  require_hq();
  return ['learners' => (int) val('SELECT COUNT(DISTINCT user_id) FROM course_progress'),
    'per_course' => rows('SELECT course_no, COUNT(DISTINCT user_id) learners, SUM(passed) passes, COUNT(*) attempts FROM course_attempts GROUP BY course_no ORDER BY course_no'),
    'certificates' => (int) val('SELECT COUNT(*) FROM certificates WHERE revoked_at IS NULL')];
});
