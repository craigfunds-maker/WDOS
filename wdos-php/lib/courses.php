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
  $photos = []; foreach (rows("SELECT image_key, mime, updated_at FROM message_photos WHERE image_key LIKE 'course/%'") as $p) $photos[$p['image_key']] = media_url($p['image_key'], $p['mime'], strtotime($p['updated_at']));
  $place = row('SELECT course_no, part, seconds, updated_at FROM course_place WHERE user_id=?', [$id]);
  return ['courses' => $out, 'videos' => function_exists('course_videos_out') ? course_videos_out() : [], 'place' => $place ? ['course' => (int) $place['course_no'], 'part' => $place['part'], 'seconds' => (int) $place['seconds'], 'at' => $place['updated_at']] : null, 'programme' => $certs[0] ?? null, 'pass' => COURSE_PASS, 'photos' => $photos, 'name' => trim($u['first_name'] . ' ' . $u['last_name'])];
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
  q('DELETE FROM course_drafts WHERE user_id=? AND course_no=?', [(int) $u['id'], $c]);
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

/* v109.8: her answers, unsent quiz choices and the place she stopped are kept with her account, so any device continues */
route('GET', '/courses/(\d+)/notes/(\d+)', function ($c, $m) {
  $u = require_user(); return ['body' => (string) (val('SELECT body FROM course_notes WHERE user_id=? AND course_no=? AND module_no=?', [(int) $u['id'], (int) $c, (int) $m]) ?? '')];
});
route('POST', '/courses/(\d+)/notes/(\d+)', function ($c, $m) {
  $u = require_user(); $c = (int) $c; $m = (int) $m;
  if (!isset(COURSE_TITLES[$c]) || $m < 1 || $m > 5) fail(404, 'That lesson was not found.', 'not_found');
  $b = mb_substr((string) (body()['body'] ?? ''), 0, 4000);
  q('INSERT INTO course_notes (user_id,course_no,module_no,body,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body), updated_at=VALUES(updated_at)', [(int) $u['id'], $c, $m, $b, now()]);
  return ['ok' => true];
});
route('GET', '/courses/(\d+)/draft', function ($c) {
  $u = require_user(); return ['answers' => (string) (val('SELECT answers FROM course_drafts WHERE user_id=? AND course_no=?', [(int) $u['id'], (int) $c]) ?? '')];
});
route('POST', '/courses/(\d+)/draft', function ($c) {
  $u = require_user(); $c = (int) $c; if (!isset(COURSE_TITLES[$c])) fail(404, 'That course was not found.', 'not_found');
  $a = (string) (body()['answers'] ?? ''); if (!preg_match('/^[ABCD-]{0,10}$/', $a)) fail(422, 'Those answers could not be kept.', 'answers');
  q('INSERT INTO course_drafts (user_id,course_no,answers,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE answers=VALUES(answers), updated_at=VALUES(updated_at)', [(int) $u['id'], $c, $a, now()]);
  return ['ok' => true];
});
route('POST', '/courses/place', function () {
  $u = require_user(); $b = body(); $c = (int) ($b['course'] ?? 0); $p = (string) ($b['part'] ?? '');
  if (!isset(COURSE_TITLES[$c]) || !preg_match('/^([1-5]|quiz|)$/', $p)) fail(422, 'That place is not part of the courses.', 'place');
  q('INSERT INTO course_place (user_id,course_no,part,seconds,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE course_no=VALUES(course_no), part=VALUES(part), seconds=VALUES(seconds), updated_at=VALUES(updated_at)', [(int) $u['id'], $c, $p, max(0, min(36000, (int) ($b['seconds'] ?? 0))), now()]);
  return ['ok' => true];
});
