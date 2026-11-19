<?php
/* Course Hub, My Courses, Journey (Rooting and Blueprint), course page, lesson player, quizzes, final assessment, certificate. */
declare(strict_types=1);
$me = require_login(); $uid = $me['uid'];

function quiz_form(array $questions, string $action, string $name): string {
    $h = '<form method="post" action="' . e($action) . '" data-quiz class="col" style="gap:16px">' . csrf_field()
        . '<div class="row jb"><span class="pill">' . e($name) . '</span><span class="mut sm"><span data-count></span>, pass mark 70%</span></div><div class="bar"><i data-fill style="background:var(--mag)"></i></div>';
    foreach ($questions as $i => $q) {
        $h .= '<div class="q col" style="gap:12px"' . ($i ? ' hidden' : '') . '><h1 style="font-size:1.4em">' . inline($q['q'] ?? '') . '</h1>';
        foreach (($q['options'] ?? []) as $k => $o) $h .= '<label class="opt"><input type="radio" name="a[' . $i . ']" value="' . $k . '"><span class="k">' . chr(65 + $k) . '</span><span class="grow">' . inline((string)$o) . '</span></label>';
        $h .= '</div>';
    }
    return $h . '<div class="row"><button type="button" class="b g" data-back hidden>' . ic('fa-arrow-left') . ' Back</button><button type="button" class="b grow" data-next disabled>Next</button></div></form>';
}
function grade(array $questions): array {
    $a = $_POST['a'] ?? []; $right = 0; $review = [];
    foreach ($questions as $i => $q) { $mine = isset($a[$i]) ? (int)$a[$i] : -1; $ok = $mine === (int)($q['correct'] ?? -2); if ($ok) $right++; $review[] = ['q' => $q['q'] ?? '', 'yours' => $q['options'][$mine] ?? 'No answer', 'ok' => $ok, 'explain' => $q['explain'] ?? '']; }
    $score = $questions ? (int)round($right / count($questions) * 100) : 0;
    return ['score' => $score, 'pass' => $score >= 70, 'review' => $review];
}
function result_html(array $g, string $title, string $sub, string $retry, string $go, string $goLabel): string {
    $c = $g['pass'] ? ['lems', 'lemt', 'fa-check'] : ['golds', 'gold', 'fa-rotate-right'];
    $h = '<div class="col" style="gap:16px;max-width:700px;width:100%;margin:0 auto"><div class="card col center" style="background:var(--' . $c[0] . ');border-color:transparent"><span class="ic big" style="background:var(--card);color:var(--' . $c[1] . ')">' . ic($c[2]) . '</span><p style="font-family:Manrope,sans-serif;font-weight:800;font-size:44px;line-height:1;color:var(--' . $c[1] . ')">' . $g['score'] . '%</p><h2>' . e($title) . '</h2><p>' . e($sub) . '</p></div>'
        . '<div class="row"><a class="b g" href="' . e($retry) . '">' . ic('fa-rotate-right') . ' Try again</a><a class="b grow" href="' . e($go) . '">' . e($goLabel) . ' ' . ic('fa-arrow-right') . '</a></div><h3>Review your answers</h3>';
    foreach ($g['review'] as $r) $h .= '<div class="card col" style="gap:6px"><div class="row rt"><i class="fa-solid ' . ($r['ok'] ? 'fa-circle-check' : 'fa-circle-xmark') . '" style="color:var(--' . ($r['ok'] ? 'lemt' : 'magt') . ');margin-top:4px"></i><p class="bold grow">' . inline($r['q']) . '</p></div><p class="sm mut">Your answer: ' . inline((string)$r['yours']) . '</p>' . ($r['explain'] ? '<p class="sm result"><strong>From the courseware.</strong> ' . inline($r['explain']) . '</p>' : '') . '</div>';
    return $h . '</div>';
}

/* ───────── Journey ───────── */
if ($first === 'journey') {
    $ph = phases(); $j = journey($uid); $kind = $seg[1] ?? ''; $id = $seg[2] ?? '';
    if ($kind === 'rooting' || $kind === 'blueprint') {
        $list = $kind === 'rooting' ? $ph['rooting'] : array_merge(...array_map(fn($p) => $p['lessons'], $ph['blueprint']));
        $idx = array_search($id, array_column($list, 'id'), true);
        if ($idx === false) redirect('journey');
        $L = $list[$idx]; $doneIds = $kind === 'rooting' ? $j['rooting'] : $j['blueprint'];
        if ($kind === 'blueprint' && $j['gate'] && !$j['root_done']) { flash('Complete the Rooting Phase first.', 'err'); redirect('journey'); }
        if ($idx > 0 && !in_array($list[$idx - 1]['id'], $doneIds, true) && !in_array($id, $doneIds, true)) { flash('Finish the one before this first. No skipping.', 'err'); redirect('journey', ['tab' => $kind === 'rooting' ? 'root' : 'blue']); }
        if (is_post()) {
            $quiz = $L['quiz'] ?? null;
            if ($quiz && (int)post('answer', '-1') !== (int)$quiz['correct']) { flash('Not quite. Read the lesson again and try once more.', 'err'); redirect("journey/$kind/$id"); }
            if (!in_array($id, $doneIds, true)) {
                $doneIds[] = $id; $all = count($doneIds) >= count($list);
                if ($kind === 'rooting') save_journey($uid, ['rooting_started' => 1, 'rooting_modules_done' => $doneIds, 'rooting_completed' => $all ? 1 : 0] + ($all ? ['rooting_completed_at' => now()] : []));
                else save_journey($uid, ['blueprint_started' => 1, 'completed_lessons' => $doneIds, 'blueprint_completed' => $all ? 1 : 0] + ($all ? ['blueprint_completed_at' => now(), 'course_hub_unlocked' => 1] : []));
                $r = points_rules(); award($uid, (int)($kind === 'rooting' ? $r['rooting_module'] : $r['blueprint_lesson']), "$kind:$id"); log_event($uid, $kind . '_lesson_complete', $id);
                if ($all) notify($uid, $kind === 'rooting' ? 'Rooting Phase complete' : 'Blueprint complete', $kind === 'rooting' ? 'Your foundation is set. The Blueprint is now open.' : 'The Course Hub is now open to you.', $kind === 'rooting' ? 'journey?tab=blue' : 'learn', 'journey');
            }
            $nextL = $list[$idx + 1] ?? null;
            redirect($nextL ? "journey/$kind/{$nextL['id']}" : 'journey', $nextL ? [] : ['tab' => $kind === 'rooting' ? 'blue' : 'hub']);
        }
        $content = (string)($L['content'] ?? '');
        if ($kind === 'rooting' && net() === 'nnn') $content = str_replace(['WODDI Good Mother Network', 'WGMN'], ['WODDI Nurture NextGen Network', 'WNNN'], $content);
        $h = '<div class="col" style="gap:16px;max-width:800px;width:100%;margin:0 auto"><div class="row"><span class="pill">' . ($kind === 'rooting' ? 'Rooting Phase' : 'Blueprint') . '</span><span class="mut sm grow">Lesson ' . ($idx + 1) . ' of ' . count($list) . (!empty($L['duration']) ? ', ' . e($L['duration']) : '') . '</span><button class="lt" type="button" data-listen="#lesson-body">' . ic('fa-headphones') . ' Listen</button></div>' . bar((int)round(($idx + 1) / count($list) * 100))
            . '<h1>' . e(str_replace(' — ', ': ', $L['title'])) . '</h1><div class="prose" id="lesson-body">' . str_replace(' — ', ', ', $content) . '</div><form method="post" class="card col">' . csrf_field();
        if (!empty($L['quiz'])) { $h .= '<h3>' . e($L['quiz']['question']) . '</h3>'; foreach ($L['quiz']['options'] as $k => $o) $h .= '<label class="opt"><input type="radio" name="answer" value="' . $k . '" required><span class="k">' . chr(65 + $k) . '</span><span class="grow">' . e($o) . '</span></label>'; }
        $h .= '<button class="b">' . (in_array($id, $doneIds, true) ? 'Continue' : 'Complete and continue') . ' ' . ic('fa-arrow-right') . '</button></form></div>';
        page($kind === 'rooting' ? 'Rooting Phase' : 'Blueprint', $h, ['active' => 'journey', 'back' => 'journey?tab=' . ($kind === 'rooting' ? 'root' : 'blue'), 'immersive' => true]);
    }
    $tab = $_GET['tab'] ?? ($j['root_done'] ? ($j['blue_done'] ? 'hub' : 'blue') : 'root');
    $h = '<div class="col" style="gap:16px;max-width:800px;width:100%;margin:0 auto">' . tabs(['root' => 'Rooting', 'blue' => net() === 'nnn' ? 'Blueprint Academy' : 'Blueprint', 'hub' => net() === 'nnn' ? 'Electives' : 'Course Hub'], $tab, url('journey'));
    $rowsFor = function (array $list, array $done, string $kind, bool $locked) {
        $h = '<div class="grp">'; $prev = true;
        foreach ($list as $i => $l) { $d = in_array($l['id'], $done, true); $open = !$locked && ($d || $prev);
            $h .= '<a class="li" href="' . ($open ? url("journey/$kind/{$l['id']}") : '#') . '"><span class="ic s ' . ($d ? 'l' : '') . '" style="border-radius:50%">' . ic($d ? 'fa-check' : ($open ? 'fa-play' : 'fa-lock')) . '</span><span class="grow"><span class="bold" style="display:block">' . e(str_replace(' — ', ': ', $l['title'])) . '</span><span class="mut xs">' . ($d ? 'Completed' : ($open ? 'Up next' : 'Locked')) . (!empty($l['duration']) ? ', ' . e($l['duration']) : '') . '</span></span>' . ic('fa-chevron-right', 'mut') . '</a>'; $prev = $d; }
        return $h . '</div>';
    };
    if ($tab === 'root') $h .= '<div class="hero"><p class="kick">Stage 1</p><h2>Rooting Phase</h2><p class="sm soft">Eight short lessons that settle you into WODDI, your network and the app. Each one earns ' . (int)points_rules()['rooting_module'] . ' Growth Points.</p>' . bar((int)round(count($j['rooting']) / max(1, $j['root_total']) * 100)) . '</div>' . $rowsFor($ph['rooting'], $j['rooting'], 'rooting', false);
    elseif ($tab === 'blue' && net() === 'nnn') {
        $h .= '<div class="hero"><p class="kick">Stage 2</p><h2>Blueprint Academy</h2><p class="sm soft">Four foundation courses, taken in order. Electives open after all four.</p></div><div class="grp">';
        $bpa = array_values(array_filter(catalog('nnn'), fn($c) => $c['track'] === 'bpa')); usort($bpa, fn($a, $b) => $a['order'] <=> $b['order']);
        foreach ($bpa as $c) { $lock = course_lock($uid, $c); $got = has_certificate($uid, $c['id']); $h .= '<a class="li" href="' . ($lock ? '#' : url('course/' . $c['id'])) . '"><span class="ic s ' . ($got ? 'l' : '') . '" style="border-radius:50%">' . ic($got ? 'fa-check' : ($lock ? 'fa-lock' : 'fa-play')) . '</span><span class="grow"><span class="bold" style="display:block">' . e($c['title']) . '</span><span class="mut xs">' . e($c['code'] ?? '') . ($lock ? ', ' . e($lock) : '') . '</span></span>' . ic('fa-chevron-right', 'mut') . '</a>'; }
        $h .= '</div>';
    } elseif ($tab === 'blue') {
        $h .= '<div class="hero"><p class="kick">Stage 2</p><h2>Blueprint</h2><p class="sm soft">Three pillars and 13 lessons on identity, mandate and legacy. Each lesson earns ' . (int)points_rules()['blueprint_lesson'] . ' Growth Points.</p>' . bar((int)round($j['blue_n'] / max(1, $j['blue_total']) * 100)) . '</div>';
        $flat = array_merge(...array_map(fn($p) => $p['lessons'], $ph['blueprint'])); $order = array_column($flat, 'id'); $locked = $j['gate'] && !$j['root_done'];
        foreach ($ph['blueprint'] as $p) {
            $h .= '<h4>' . e(str_replace(' — ', ': ', $p['title'])) . '</h4><div class="grp">';
            foreach ($p['lessons'] as $l) { $i = array_search($l['id'], $order, true); $d = in_array($l['id'], $j['blueprint'], true); $open = !$locked && ($d || $i === 0 || in_array($order[$i - 1], $j['blueprint'], true));
                $h .= '<a class="li" href="' . ($open ? url("journey/blueprint/{$l['id']}") : '#') . '"><span class="ic s ' . ($d ? 'l' : '') . '" style="border-radius:50%">' . ic($d ? 'fa-check' : ($open ? 'fa-play' : 'fa-lock')) . '</span><span class="grow bold">' . e($l['title']) . '</span><span class="xs mut">' . ($d ? 'Completed' : ($open ? 'Up next' : 'Locked')) . '</span></a>'; }
            $h .= '</div>';
        }
    } else $h .= empty_state('fa-graduation-cap', $j['hub_open'] ? 'The Course Hub is open to you' : 'The Course Hub is still locked', $j['hub_open'] ? 'Choose any course in your network and earn certificates.' : 'Finish the stages before it first. You cannot skip a stage.', '<a class="b" href="' . url('learn') . '">Open the Course Hub</a>');
    page('My journey', $h . '</div>', ['active' => 'journey']);
}

/* ───────── Course Hub ───────── */
if ($first === 'learn') {
    $all = catalog(net()); $j = journey($uid);
    $started = []; foreach (rows('SELECT course_id, SUM(completed = 1 AND phase = \'lesson\') d FROM course_progress WHERE uid = ? GROUP BY course_id', [$uid]) as $r) $started[$r['course_id']] = (int)$r['d'];
    $certs = array_column(rows('SELECT course_key FROM certificates WHERE uid = ? AND COALESCE(revoked,0) = 0', [$uid]), 'course_key');
    $track = (string)($_GET['track'] ?? 'all'); $level = (string)($_GET['level'] ?? ''); $qs = trim((string)($_GET['q'] ?? '')); $mine = !empty($_GET['mine']); $n = max(24, (int)($_GET['n'] ?? 24));
    $list = array_values(array_filter($all, fn($c) => ($track === 'all' || $c['track'] === $track) && (!$level || $c['level'] === $level) && ($qs === '' || stripos($c['title'] . ' ' . ($c['desc'] ?? ''), $qs) !== false) && (!$mine || isset($started[$c['id']]) || in_array($c['id'], $certs, true))));
    $ord = ['bpa' => 0, 'digital' => 1, 'financial' => 2, 'personal' => 3, 'handson' => 4, 'sdg' => 5, 'elective' => 6]; $lv = ['Beginner' => 1, 'Intermediate' => 2, 'Advanced' => 3];
    usort($list, fn($a, $b) => [$ord[$a['track']] ?? 9, $a['order'], $lv[$a['level']] ?? 9, $a['title']] <=> [$ord[$b['track']] ?? 9, $b['order'], $lv[$b['level']] ?? 9, $b['title']]);
    $link = fn(array $over) => url('learn', array_filter(array_merge(['track' => $track === 'all' ? null : $track, 'level' => $level ?: null, 'q' => $qs ?: null, 'mine' => $mine ? 1 : null], $over), fn($v) => $v !== null && $v !== ''));
    $h = '<div class="col" style="gap:16px"><div class="hero"><div class="row"><img src="' . asset('logo.jpg') . '" alt="" style="width:34px;height:34px;border-radius:10px"><span class="bold soft">WODDI Institute Course Hub</span></div><div class="row wrap" style="align-items:flex-end"><div class="grow" style="min-width:220px"><h1>Learn. Grow. Empower. Repeat.</h1><p class="sm soft" style="margin-top:6px;max-width:620px">Practical lessons, module videos in 8 languages, quizzes, certificates and progress tracking.</p></div><a class="b gl s" href="' . url('learn', ['mine' => 1]) . '">' . ic('fa-book-open-reader') . ' My Courses</a></div>'
        . '<div class="row wrap" style="gap:8px"><div class="stat"><b>' . count($all) . '</b>Total courses</div><div class="stat"><b>' . count($list) . '</b>Showing now</div><div class="stat"><b>' . count($started) . '</b>Started</div><div class="stat"><b>' . count($certs) . '</b>Finished</div></div>'
        . (!$j['hub_open'] ? '<p class="sm" style="background:rgba(255,255,255,.14);border-radius:12px;padding:10px 12px">' . ic('fa-lock') . ' Complete Rooting and ' . (net() === 'nnn' ? 'the Blueprint Academy' : 'Blueprint') . ' to unlock the full course experience. <a href="' . url('journey') . '" style="text-decoration:underline">Open my journey</a></p>' : '') . '</div>'
        . '<div class="seg"><a class="' . ($mine ? '' : 'on') . '" href="' . $link(['mine' => null]) . '">Course Hub</a><a class="' . ($mine ? 'on' : '') . '" href="' . $link(['mine' => 1]) . '">My Courses</a></div>'
        . '<form class="search" method="get" action="' . url('learn') . '">' . ic('fa-magnifying-glass', 'mut') . '<input type="search" name="q" value="' . e($qs) . '" placeholder="Search courses, skills, SDGs..." aria-label="Search courses">' . ($track !== 'all' ? '<input type="hidden" name="track" value="' . e($track) . '">' : '') . ($mine ? '<input type="hidden" name="mine" value="1">' : '') . '</form><div class="hs">';
    $counts = array_count_values(array_column($all, 'track'));
    $h .= '<a class="trk ' . ($track === 'all' ? 'on' : '') . '" href="' . $link(['track' => null]) . '">' . ic('fa-layer-group') . '<span><b>All</b><small>' . count($all) . ' courses</small></span></a>';
    foreach (TRACKS as $k => $t) if (!empty($counts[$k])) $h .= '<a class="trk ' . ($track === $k ? 'on' : '') . '" href="' . $link(['track' => $k]) . '">' . ic($t[1]) . '<span><b>' . e($t[0]) . '</b><small>' . $counts[$k] . ' courses</small></span></a>';
    $h .= '</div><div class="chips">'; foreach (['' => 'All Levels', 'Beginner' => 'Beginner', 'Intermediate' => 'Intermediate', 'Advanced' => 'Advanced'] as $k => $lab) $h .= '<a class="chip ' . ($level === $k ? 'on' : '') . '" href="' . $link(['level' => $k ?: null]) . '">' . $lab . '</a>'; $h .= '</div>';
    $group = '';
    foreach (array_slice($list, 0, $n) as $c) {
        $g = TRACKS[$c['track']][0] . ' · ' . $c['level'];
        if ($g !== $group) { if ($group) $h .= '</div>'; $h .= '<h3>' . e($g) . '</h3><div class="gc">'; $group = $g; }
        $lock = course_lock($uid, $c); $done = in_array($c['id'], $certs, true); $d = $done ? $c['lessons'] : ($started[$c['id']] ?? 0); $pct = $c['lessons'] ? (int)round($d / $c['lessons'] * 100) : 0;
        $h .= '<a class="cc ' . ($lock ? 'lock' : '') . '" href="' . url('course/' . $c['id']) . '"><span class="row" style="gap:6px"><span class="ic s ' . ($done ? 'l' : ($d ? '' : 'n')) . '">' . ic($lock ? 'fa-lock' : TRACKS[$c['track']][1]) . '</span><span class="grow"></span>' . pill($c['level'], 'n') . ($done ? '<span class="pill l">' . ic('fa-check') . '</span>' : '') . '</span><span class="xs mut">' . e(TRACKS[$c['track']][0]) . ' / ' . (int)$c['minutes'] . ' min' . (!empty($c['video']) ? ' / video' : '') . '</span><span class="bold grow">' . e($c['title']) . '</span><span class="row jb xs"><span class="mut">' . ($lock ? 'Locked' : ($done ? 'Completed' : ($d ? 'In progress' : 'Not started'))) . '</span><span class="bold">' . $pct . '%</span></span>' . bar($pct) . '<span class="xs mut">' . $d . ' / ' . (int)$c['lessons'] . ' lessons</span></a>';
    }
    if ($group) $h .= '</div>';
    if (count($list) > $n) $h .= '<a class="b g" href="' . $link(['n' => $n + 24]) . '">Show more courses <span class="xs mut">' . $n . ' of ' . count($list) . ' shown</span></a>';
    if (!$list) $h .= empty_state('fa-magnifying-glass', $mine ? 'You have not started a course yet' : 'No course matches that yet', $mine ? 'Open the Course Hub and pick your first course.' : 'Try another word, or clear the filters.', '<a class="b g s" href="' . url('learn') . '">Clear filters</a>');
    page('Learn', $h . '</div>', ['active' => 'learn']);
}

/* ───────── Course, lesson, quiz, final, certificate ───────── */
$cid = $seg[1] ?? ''; $item = catalog_item($cid); $course = $item ? course($cid) : null;
if (!$course) { http_response_code(404); page('Course not found', empty_state('fa-book', 'We could not find that course', '', '<a class="b" href="' . url('learn') . '">Back to the Course Hub</a>'), ['active' => 'learn']); }
$GLOBALS['__course_id'] = $cid;
if (isset($_GET['lang']) && isset(COURSE_LANGS[$_GET['lang']])) { $_SESSION['clang'] = $_GET['lang']; redirect($path, array_diff_key($_GET, ['lang' => 1])); }
$lock = course_lock($uid, $item);
if ($lock) { flash($lock, 'err'); redirect(net() !== $item['network'] ? 'learn' : 'journey'); }
$st = course_state($uid, $course); $lang = course_lang(); $act = $seg[2] ?? '';
$langPicker = '<form method="get" class="row" style="gap:6px">' . ic('fa-language') . '<select class="inp" name="lang" onchange="this.form.submit()" aria-label="Course language" style="min-height:44px;width:auto;padding:6px 10px">' . implode('', array_map(fn($k, $n) => '<option value="' . $k . '"' . ($k === $lang ? ' selected' : '') . '>' . e($n) . '</option>', array_keys(COURSE_LANGS), COURSE_LANGS)) . '</select></form>';

if ($act === 'lesson') {
    $mi = (int)($seg[3] ?? 0); $li = (int)($seg[4] ?? 0); $M = $course['modules'][$mi] ?? null; $L = $M['lessons'][$li] ?? null;
    if (!$L) redirect("course/$cid");
    $ls = $st['mods'][$mi]['lessons'][$li];
    if (!$ls['open']) { flash('Finish the lesson or quiz before this one first. No skipping.', 'err'); redirect("course/$cid"); }
    if (is_post()) {
        if (post('do') === 'task') { save_progress($uid, $course, 'activity:' . $L['id'], 'activity', true, 0, $M['id'], ['evidence_note' => mb_substr((string)post('evidence'), 0, 2000)]); flash('Practice task recorded.'); redirect("course/$cid/lesson/$mi/$li"); }
        $was = $ls['done']; save_progress($uid, $course, $L['id'], 'lesson', true, 100, $M['id']); if (!$was) log_event($uid, 'lesson_complete', $cid, ['lesson' => $L['id'], 'lesson_title' => $L['title']]);
        header('Location: ' . step_url($cid, course_state($uid, $course)['next'])); exit;
    }
    $pr = progress_rows($uid, $cid); $note = (string)($pr[$L['id']]['note'] ?? ''); $taskDone = !empty($pr['activity:' . $L['id']]['completed']);
    $idx = 0; foreach ($course['modules'] as $k => $m) { if ($k < $mi) $idx += count($m['lessons']); } $idx += $li + 1;
    $h = '<div class="col" style="gap:16px;max-width:800px;width:100%;margin:0 auto"><div class="row"><span class="pill">Module ' . ($mi + 1) . '</span><span class="mut sm grow">Lesson ' . $idx . ' of ' . $st['total'] . (!empty($L['minutes']) ? ', ' . (int)$L['minutes'] . ' min' : '') . '</span></div>' . bar((int)round($idx / max(1, $st['total']) * 100))
        . '<div><p class="xs mut bold" style="text-transform:uppercase;letter-spacing:.06em">' . inline($M['title']) . '</p><h1 style="margin-top:4px">' . inline($L['title']) . '</h1></div>'
        . '<div class="ltools"><a class="lt" href="' . url("course/$cid") . '">' . ic('fa-list') . ' Overview</a><button class="lt" type="button" data-toggle="#contents">' . ic('fa-bars-staggered') . ' Contents</button><button class="lt" type="button" data-toggle="#notes">' . ic('fa-note-sticky') . ' Notes</button><button class="lt" type="button" data-listen="#lesson-body" data-lang="' . $lang . '">' . ic('fa-headphones') . ' Listen</button>' . $langPicker . '</div>';
    $h .= '<div class="card col" id="contents" hidden style="gap:4px"><h3>Course contents</h3>';
    foreach ($st['mods'] as $k => $m) { $h .= '<p class="xs mut bold" style="padding:8px 4px 2px">' . ($k + 1) . '. ' . inline($m['title']) . '</p>'; foreach ($m['lessons'] as $x) $h .= '<a class="ls ' . ($x['done'] ? 'done' : ($x['open'] ? '' : 'lock')) . ($x['id'] === $L['id'] ? ' cur' : '') . '" href="' . ($x['open'] ? url("course/$cid/lesson/{$x['mi']}/{$x['li']}") : '#') . '"><span class="n">' . ic($x['done'] ? 'fa-check' : ($x['open'] ? 'fa-circle' : 'fa-lock')) . '</span><span class="grow sm">' . inline($x['title']) . '</span></a>'; }
    $h .= '</div><div class="card col" id="notes" hidden><div class="row jb"><h3>My notes</h3><span class="xs" id="note-state" style="color:var(--lemt);font-weight:600"></span></div><textarea class="inp" rows="4" data-note="' . e($L['id']) . '" data-course="' . e($cid) . '" placeholder="Jot down anything you want to remember from this lesson">' . e($note) . '</textarea><p class="xs mut">Saved automatically, just for you.</p></div>';
    if ($li === 0 && !empty($course['video'])) {
        $h .= '<div class="card col" style="padding:12px"><video class="vid" controls preload="none" playsinline src="' . e(video_url($cid, $mi + 1, $lang)) . '" onerror="if(!this.dataset.f){this.dataset.f=1;this.src=' . e(json_encode(video_url($cid, $mi + 1, 'en'))) . '}"></video><div class="row"><div class="grow"><p class="bold">' . e(ui_str('video')) . ': ' . inline($M['title']) . '</p><p class="mut xs">A short WODDI video for this module. It downloads only when you tap Play, to save mobile data.</p></div>' . pill(COURSE_LANGS[$lang], 'n') . '</div><div class="chips">';
        foreach (COURSE_LANGS as $k => $n) $h .= '<a class="chip ' . ($k === $lang ? 'on' : '') . '" href="?lang=' . $k . '">' . e($n) . '</a>'; $h .= '</div></div>';
    }
    if (!empty($L['objectives'])) { $h .= '<div class="card col" style="background:var(--mags);border-color:transparent"><h3>' . e(ui_str('in_lesson')) . '</h3>'; foreach ($L['objectives'] as $o) $h .= '<div class="row rt"><i class="fa-solid fa-bullseye" style="color:var(--magt);margin-top:4px"></i><p>' . inline($o) . '</p></div>'; $h .= '</div>'; }
    $h .= '<div class="prose" id="lesson-body"' . ($lang === 'ar' ? ' dir="rtl"' : '') . '>' . implode('', array_map('block_html', $L['blocks'] ?? [])) . '</div>';
    if (!empty($L['takeaways'])) { $h .= '<div class="card col" style="background:var(--lems);border-color:transparent"><h3>' . e(ui_str('takeaways')) . '</h3>'; foreach ($L['takeaways'] as $o) $h .= '<div class="row rt"><i class="fa-solid fa-check" style="color:var(--lemt);margin-top:4px"></i><p>' . inline($o) . '</p></div>'; $h .= '</div>'; }
    if (!empty($L['activity'])) {
        $a = $L['activity']; $h .= '<form method="post" class="card col">' . csrf_field() . '<input type="hidden" name="do" value="task"><div class="row"><span class="ic g">' . ic('fa-hands') . '</span><div class="grow"><p class="xs mut bold" style="text-transform:uppercase">' . e(ui_str('task')) . '</p><h3>' . inline($a['title'] ?? '') . '</h3></div>' . pill($taskDone ? 'Done' : 'To do', $taskDone ? 'l' : 'g') . '</div>';
        foreach ($a['steps'] ?? [] as $k => $x) $h .= '<div class="row rt"><span class="num">' . ($k + 1) . '</span><p class="grow">' . inline($x) . '</p></div>';
        if (!empty($a['evidence'])) $h .= '<p class="sm" style="background:var(--golds);color:var(--gold);border-radius:12px;padding:10px 12px"><strong>Keep as evidence:</strong> ' . inline($a['evidence']) . '</p>';
        $h .= '<label class="fld">What happened? (optional)<input class="inp" name="evidence" value="' . e($pr['activity:' . $L['id']]['evidence_note'] ?? '') . '" placeholder="One or two sentences"></label><button class="b l s" style="align-self:flex-start">' . ic('fa-check') . ' ' . ($taskDone ? 'Update my note' : 'I did this') . '</button></form>';
    }
    $prev = $li > 0 ? url("course/$cid/lesson/$mi/" . ($li - 1)) : ($mi > 0 ? url("course/$cid/lesson/" . ($mi - 1) . '/' . (count($course['modules'][$mi - 1]['lessons']) - 1)) : url("course/$cid"));
    $h .= '<form method="post" class="row">' . csrf_field() . '<a class="b g" href="' . $prev . '">' . ic('fa-arrow-left') . ' Previous</a><span class="xs mut grow" style="text-align:center">' . ($ls['done'] ? 'Saved' : 'Read to the end, then continue') . '</span><button class="b">Continue ' . ic('fa-arrow-right') . '</button></form></div>';
    page('Lesson', $h, ['active' => 'learn', 'back' => "course/$cid", 'immersive' => true]);
}

if ($act === 'quiz') {
    $mi = (int)($seg[3] ?? 0); $M = $course['modules'][$mi] ?? null; $ms = $st['mods'][$mi] ?? null;
    if (!$M || empty($M['quiz'])) redirect("course/$cid");
    if (!$ms['quiz_ready'] && !$ms['quiz_passed']) { flash('Finish all the lessons in this module to open its quiz.', 'err'); redirect("course/$cid"); }
    if (is_post()) {
        $g = grade($M['quiz']); $first_pass = $g['pass'] && !$ms['quiz_passed'];
        save_progress($uid, $course, $M['id'] . '.quiz', 'module-quiz', $g['pass'], $g['score'], $M['id']); log_event($uid, $g['pass'] ? 'quiz_pass' : 'quiz_fail', $cid, ['module' => $M['id'], 'score' => $g['score']]);
        if ($first_pass) award($uid, (int)points_rules()['course_module'], "module:$cid:{$M['id']}");
        $ns = course_state($uid, $course);
        page('Module quiz', result_html($g, $g['pass'] ? 'Module ' . ($mi + 1) . ' quiz passed' : 'Not yet, and that is okay', $g['pass'] ? 'Well done. The next part of the course is now open.' : 'The pass mark is 70%. Review the notes below and try again.', url("course/$cid/quiz/$mi"), $g['pass'] ? step_url($cid, $ns['next']) : url("course/$cid"), $g['pass'] ? 'Continue' : 'Back to the course'), ['active' => 'learn', 'back' => "course/$cid", 'immersive' => true]);
    }
    page('Module quiz', '<div style="max-width:700px;width:100%;margin:0 auto">' . quiz_form($M['quiz'], url("course/$cid/quiz/$mi"), 'Module ' . ($mi + 1) . ' quiz') . '</div>', ['active' => 'learn', 'back' => "course/$cid", 'immersive' => true]);
}

if ($act === 'final') {
    if (!$st['all_modules']) { flash('Pass every module quiz to open the final assessment.', 'err'); redirect("course/$cid"); }
    $cap = $course['capstone'] ?? null; $fq = $course['finalQuiz'] ?? [];
    if (is_post() && post('do') === 'capstone') { save_progress($uid, $course, 'capstone', 'capstone', true, 0, 'final', ['capstone_completed' => 1, 'capstone_completed_at' => now()]); redirect("course/$cid/final", ['start' => 1]); }
    if (is_post()) {
        $g = grade($fq);
        save_progress($uid, $course, 'final-quiz', 'final-quiz', $g['pass'], $g['score'], 'final', $g['pass'] ? ['final_quiz_passed' => 1, 'progress_percent' => 100] : []); log_event($uid, $g['pass'] ? 'final_pass' : 'final_fail', $cid, ['score' => $g['score']]);
        if ($g['pass']) issue_certificate($me, $course, $g['score']);
        page('Final assessment', result_html($g, $g['pass'] ? 'Final assessment passed' : 'Not yet, and that is okay', $g['pass'] ? 'Well done. Your certificate has been issued.' : 'The pass mark is 70%. Review the notes below and try again.', url("course/$cid/final", ['start' => 1]), $g['pass'] ? url("course/$cid/certificate") : url("course/$cid"), $g['pass'] ? 'View my certificate' : 'Back to the course'), ['active' => 'learn', 'back' => "course/$cid", 'immersive' => true]);
    }
    if (!$fq) { issue_certificate($me, $course, 100); redirect("course/$cid/certificate"); }
    if ($cap && !$st['capstone'] && empty($_GET['start'])) {
        $h = '<form method="post" class="col" style="gap:14px;max-width:700px;width:100%;margin:0 auto">' . csrf_field() . '<input type="hidden" name="do" value="capstone"><div class="card col"><span class="pill g" style="align-self:flex-start">' . ic('fa-award') . ' Final assessment</span><h2>' . inline($cap['title'] ?? 'Practical capstone') . '</h2>' . (!empty($cap['brief']) ? '<p>' . inline($cap['brief']) . '</p>' : '');
        foreach (($cap['steps'] ?? []) as $k => $x) $h .= '<div class="row rt"><span class="num">' . ($k + 1) . '</span><p class="grow">' . inline($x) . '</p></div>';
        $ev = $cap['evidence'] ?? ($cap['deliverables'] ?? []); if ($ev) { $h .= '<h4>What to hand in</h4>'; foreach ((array)$ev as $x) $h .= '<div class="row rt"><i class="fa-solid fa-folder-open" style="color:var(--gold);margin-top:4px"></i><p class="sm">' . inline((string)$x) . '</p></div>'; }
        $h .= '<p class="sm"><strong>Certification rule:</strong> complete the practical capstone and score at least 70% in the final quiz.</p><label class="row rt"><input type="checkbox" required style="width:24px;height:24px;flex:none"><span>I confirm that I have completed the practical capstone task and kept my evidence.</span></label></div><div class="row"><a class="b g" href="' . url("course/$cid") . '">Back</a><button class="b grow">Start the final quiz, ' . count($fq) . ' questions</button></div></form>';
        page('Final assessment', $h, ['active' => 'learn', 'back' => "course/$cid", 'immersive' => true]);
    }
    page('Final assessment', '<div style="max-width:700px;width:100%;margin:0 auto">' . quiz_form($fq, url("course/$cid/final"), 'Final quiz') . '</div>', ['active' => 'learn', 'back' => "course/$cid", 'immersive' => true]);
}

if ($act === 'certificate') {
    $c = certificate_for($uid, $cid);
    if (!$c) page('Certificate', '<div class="col" style="max-width:700px;width:100%;margin:0 auto">' . empty_state('fa-lock', 'Your certificate is locked', 'Pass the final assessment to unlock your certificate.', '<a class="b" href="' . step_url($cid, $st['next']) . '">Continue the course</a>') . '</div>', ['active' => 'learn', 'back' => "course/$cid"]);
    redirect('certificates', ['open' => $c['id']]);
}

/* Course overview */
$tab = $_GET['tab'] ?? 'path'; $n = $st['next']; $certOk = (bool)certificate_for($uid, $cid);
$h = '<div class="split"><div class="grow col" style="gap:18px"><div class="hero"><div class="row wrap" style="gap:8px"><span class="pill" style="background:#fff;color:#B4005A">' . e(TRACKS[$item['track']][0]) . '</span><span class="pill" style="background:rgba(255,255,255,.18);color:#fff">' . ic('fa-signal') . ' ' . e($course['level'] ?? '') . '</span></div><h1>' . inline($course['title']) . '</h1><p class="soft" style="max-width:620px">' . inline($course['subtitle'] ?? '') . '</p>'
   . '<div class="row wrap" style="gap:8px"><div class="stat"><b>' . $st['total'] . '</b>lessons</div><div class="stat"><b>' . count($course['modules']) . '</b>modules</div><div class="stat"><b>' . (int)$item['minutes'] . '</b>minutes</div>' . (!empty($course['video']) ? '<div class="stat"><b>' . count($course['modules']) . '</b>module videos</div>' : '') . '<div class="stat"><b>8</b>languages</div></div><div style="align-self:flex-start;background:rgba(255,255,255,.14);border-radius:14px;padding:4px 10px">' . $langPicker . '</div></div>'
   . '<div class="card col"><div class="row" style="gap:14px"><div class="ring" style="background:conic-gradient(#7CB518 ' . round($st['pct'] * 3.6) . 'deg,var(--line) 0);width:72px;height:72px"><span style="width:56px;height:56px;background:var(--card);color:var(--ink);font-size:1em">' . $st['pct'] . '%</span></div><div class="grow"><p class="xs mut bold">NEXT STEP</p><p class="bold">' . inline($n['label']) . '</p><p class="mut sm">' . $st['done'] . ' of ' . $st['total'] . ' lessons done</p></div></div><div class="row wrap"><a class="b grow" href="' . step_url($cid, $n) . '">' . ic($n['type'] === 'cert' ? 'fa-award' : 'fa-play') . ' ' . ($n['type'] === 'cert' ? 'View certificate' : ($st['done'] ? 'Resume' : 'Start course')) . '</a><button class="b g s" type="button" onclick="window.print()">' . ic('fa-file-pdf') . ' Download PDF</button></div></div>'
   . tabs(array_filter(['path' => 'Modules', 'videos' => !empty($course['video']) ? 'Videos' : null, 'about' => 'About', 'assess' => 'Assessment', 'notes' => 'Notes']), $tab, url("course/$cid"));
if ($tab === 'path') {
    $h .= '<div class="tl">';
    foreach ($st['mods'] as $k => $m) {
        $h .= '<div><span class="node ' . ($m['quiz_passed'] || (!$m['has_quiz'] && $m['done'] === count($m['lessons'])) ? 'done' : ($m['open'] ? 'cur' : '')) . '">' . ic($m['quiz_passed'] ? 'fa-check' : ($m['open'] ? 'fa-play' : 'fa-lock')) . '</span><details class="card mod col" style="padding:14px"' . (($n['mi'] ?? -1) === $k ? ' open' : '') . '><summary><span class="xs mut bold" style="display:block">MODULE ' . ($k + 1) . '</span><span class="bold">' . inline($m['title']) . '</span><span class="row" style="margin-top:8px">' . bar((int)round($m['done'] / max(1, count($m['lessons'])) * 100)) . '<span class="xs mut" style="white-space:nowrap">' . $m['done'] . ' of ' . count($m['lessons']) . '</span></span></summary><div class="col" style="gap:2px;margin-top:10px">' . ($m['overview'] ? '<p class="mut sm" style="padding:0 4px 6px">' . inline($m['overview']) . '</p>' : '');
        foreach ($m['lessons'] as $x) $h .= '<a class="ls ' . ($x['done'] ? 'done' : ($x['open'] ? 'cur' : 'lock')) . '" href="' . ($x['open'] ? url("course/$cid/lesson/{$x['mi']}/{$x['li']}") : '#') . '"><span class="n">' . ic($x['done'] ? 'fa-check' : ($x['open'] ? 'fa-play' : 'fa-lock')) . '</span><span class="grow sm">' . ($k + 1) . '.' . ($x['li'] + 1) . ' ' . inline($x['title']) . '</span><span class="xs mut">' . ($x['minutes'] ? (int)$x['minutes'] . ' min' : '') . '</span></a>';
        if ($m['has_quiz']) $h .= '<a class="ls ' . ($m['quiz_passed'] ? 'done' : ($m['quiz_ready'] ? 'cur' : 'lock')) . '" href="' . ($m['quiz_ready'] || $m['quiz_passed'] ? url("course/$cid/quiz/$k") : '#') . '"><span class="n">' . ic($m['quiz_passed'] ? 'fa-check' : ($m['quiz_ready'] ? 'fa-list-check' : 'fa-lock')) . '</span><span class="grow sm bold">Module ' . ($k + 1) . ' quiz</span><span class="xs bold">' . ($m['quiz_passed'] ? 'Passed' : ($m['quiz_ready'] ? 'Ready' : '')) . '</span></a>';
        $h .= '</div></details></div>';
    }
    $h .= '<div><span class="node ' . ($st['final'] ? 'done' : ($st['all_modules'] ? 'cur' : '')) . '">' . ic('fa-flag-checkered') . '</span><a class="card row" style="padding:14px" href="' . ($st['all_modules'] ? url("course/$cid/final") : '#') . '"><span class="grow"><span class="xs mut bold" style="display:block">FINAL ASSESSMENT</span><span class="bold">Capstone task and final quiz</span></span>' . pill($st['final'] ? 'Passed' : ($st['all_modules'] ? 'Ready' : 'Locked'), $st['final'] ? 'l' : ($st['all_modules'] ? '' : 'n')) . '</a></div>'
        . '<div><span class="node ' . ($certOk ? 'done' : '') . '">' . ic('fa-award') . '</span><a class="card row" style="padding:14px" href="' . url("course/$cid/certificate") . '"><span class="grow"><span class="xs mut bold" style="display:block">CERTIFICATE</span><span class="bold">' . inline($course['certificateTitle'] ?? 'Certificate of completion, with QR verification') . '</span></span>' . pill($certOk ? 'Issued' : 'Locked', $certOk ? 'l' : 'n') . '</a></div></div>';
} elseif ($tab === 'videos') {
    $h .= '<div class="card col"><div class="row"><span class="ic p">' . ic('fa-film') . '</span><div class="grow"><h3>Module videos</h3><p class="mut sm">One WODDI video for each module, in all 8 languages. A video only downloads when you tap Play.</p></div></div><div class="chips">'; foreach (COURSE_LANGS as $k => $nm) $h .= '<a class="chip ' . ($k === $lang ? 'on' : '') . '" href="?tab=videos&lang=' . $k . '">' . e($nm) . '</a>'; $h .= '</div></div><div class="g2">';
    foreach ($st['mods'] as $k => $m) $h .= '<div class="card col" style="padding:12px;' . ($m['open'] ? '' : 'opacity:.6') . '">' . ($m['open'] ? '<video class="vid" controls preload="none" playsinline src="' . e(video_url($cid, $k + 1, $lang)) . '"></video>' : '<div class="vid" style="display:flex;align-items:center;justify-content:center;color:#fff">' . ic('fa-lock') . '</div>') . '<p class="xs mut bold">MODULE ' . ($k + 1) . ' VIDEO</p><p class="bold">' . inline($m['title']) . '</p></div>';
    $h .= '</div>';
} elseif ($tab === 'about') {
    $h .= '<div class="card col"><h3>About this course</h3><p>' . inline($course['description'] ?? '') . '</p></div>';
    if (!empty($course['outcomes'])) { $h .= '<div class="card col"><h3>What you will be able to do</h3>'; foreach ($course['outcomes'] as $o) $h .= '<div class="row rt"><i class="fa-solid fa-circle-check" style="color:var(--lemt);margin-top:4px"></i><p>' . inline((string)$o) . '</p></div>'; $h .= '</div>'; }
    $h .= '<div class="g2"><div class="card col"><h3>Who it is for</h3><p class="mut">' . inline($course['audience'] ?? '') . '</p></div>'; if (!empty($course['tools'])) { $h .= '<div class="card col"><h3>What you need</h3>'; foreach ($course['tools'] as $o) $h .= '<p class="sm">' . ic('fa-screwdriver-wrench') . ' ' . inline((string)$o) . '</p>'; $h .= '</div>'; } $h .= '</div>';
    if (!empty($course['sources'])) { $h .= '<div class="card col"><h3>Sources the course draws on</h3>'; foreach ($course['sources'] as $s) $h .= '<p class="sm">' . ic('fa-book') . ' ' . e(is_array($s) ? ($s['title'] ?? '') : $s) . '</p>'; $h .= '</div>'; }
} elseif ($tab === 'assess') {
    $req = [['All ' . $st['total'] . ' lessons', $st['done'] . ' of ' . $st['total'] . ' done', $st['done'] === $st['total']], ['Every module quiz at 70% or more', count(array_filter($st['mods'], fn($m) => $m['quiz_passed'])) . ' of ' . count(array_filter($st['mods'], fn($m) => $m['has_quiz'])) . ' passed', $st['all_modules']], ['Practical capstone', $st['capstone'] ? 'Confirmed' : 'Confirm it before the final quiz', $st['capstone'] || empty($course['capstone'])], ['Final quiz at 70% or more', $st['final'] ? 'Passed' : 'Opens after the last module', $st['final']]];
    $h .= '<div class="card col"><h3>What you need for your certificate</h3>'; foreach ($req as $r) $h .= '<div class="row"><span class="ic s ' . ($r[2] ? 'l' : 'n') . '">' . ic($r[2] ? 'fa-check' : 'fa-hourglass-half') . '</span><div class="grow"><p class="bold">' . e($r[0]) . '</p><p class="mut sm">' . e($r[1]) . '</p></div></div>'; $h .= '</div>';
    if (!empty($course['capstone'])) { $cap = $course['capstone']; $h .= '<div class="card col"><span class="pill g" style="align-self:flex-start">Practical capstone</span><h3>' . inline($cap['title'] ?? '') . '</h3>' . (!empty($cap['brief']) ? '<p>' . inline($cap['brief']) . '</p>' : ''); foreach (($cap['steps'] ?? []) as $k => $x) $h .= '<div class="row rt"><span class="num">' . ($k + 1) . '</span><p class="grow">' . inline($x) . '</p></div>'; $h .= '</div>'; }
} else {
    $notes = $st['notes']; $h .= '<div class="card col"><h3>My notes</h3>'; if (!$notes) $h .= '<p class="mut">Notes you write inside a lesson are saved automatically and kept here.</p>';
    foreach ($notes as $lid => $t) $h .= '<div style="background:var(--bg);border-radius:16px;padding:12px"><p class="xs mut bold">' . e(strtoupper($lid)) . '</p><p>' . nl2br(e($t)) . '</p></div>'; $h .= '</div>';
}
$h .= '</div><div class="aside"><div class="card col"><h4>Next up</h4><p class="bold">' . inline($n['label']) . '</p><a class="b full" href="' . step_url($cid, $n) . '">' . ic('fa-play') . ' Continue</a></div><div class="card col"><h4>Stuck on something?</h4><p class="mut sm">Ask the mentor about this course, any time.</p><a class="b g s" href="' . url('mentor') . '">' . ic('fa-robot') . ' Ask WADI</a></div></div></div>';
page($course['title'], $h, ['active' => 'learn', 'back' => 'learn']);
