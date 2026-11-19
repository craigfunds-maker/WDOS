<?php
/* Courses, lessons, progress, quizzes, certificates, translation packs and module videos. */
declare(strict_types=1);

const COURSE_LANGS = ['en' => 'English', 'fr' => 'Français', 'pt' => 'Português', 'ar' => 'العربية', 'sw' => 'Kiswahili', 'ha' => 'Hausa', 'yo' => 'Yorùbá', 'ig' => 'Igbo'];
const TRACKS = ['digital' => ['Digital', 'fa-laptop-code'], 'financial' => ['Financial', 'fa-wallet'], 'personal' => ['Personal', 'fa-user-graduate'], 'handson' => ['Hands-On', 'fa-hands-holding-circle'], 'sdg' => ['SDG', 'fa-seedling'], 'bpa' => ['Blueprint Academy', 'fa-compass-drafting'], 'elective' => ['Professional Electives', 'fa-briefcase']];
const UI_STR = [
    'in_lesson' => ['en' => 'In this lesson', 'fr' => 'Dans cette leçon', 'pt' => 'Nesta lição', 'ar' => 'في هذا الدرس', 'yo' => 'Nínú ẹ̀kọ́ yìí', 'ha' => 'A wannan darasi', 'ig' => "N'ime nkuzi a", 'sw' => 'Katika somo hili'],
    'takeaways' => ['en' => 'Key takeaways', 'fr' => 'À retenir', 'pt' => 'Pontos principais', 'ar' => 'أهم النقاط', 'yo' => 'Àwọn kókó pàtàkì', 'ha' => 'Muhimman abubuwa', 'ig' => 'Isi ihe ndị bụ isi', 'sw' => 'Mambo muhimu'],
    'task' => ['en' => 'Practice task', 'fr' => 'Exercice pratique', 'pt' => 'Tarefa prática', 'ar' => 'مهمة تطبيقية', 'yo' => 'Iṣẹ́ àṣefihàn', 'ha' => 'Aikin gwaji', 'ig' => 'Ọrụ omume', 'sw' => 'Kazi ya mazoezi'],
    'video' => ['en' => 'Module video', 'fr' => 'Vidéo du module', 'pt' => 'Vídeo do módulo', 'ar' => 'فيديو الوحدة', 'yo' => 'Fídíò ẹ̀ka ẹ̀kọ́', 'ha' => 'Bidiyon sashe', 'ig' => 'Vidiyo modul', 'sw' => 'Video ya moduli'],
];

function catalog(?string $network = null): array {
    static $c = null;
    if ($c === null) $c = json_decode((string)file_get_contents(APP_ROOT . '/content/catalog.json'), true) ?: [];
    return $network ? array_values(array_filter($c, fn($x) => $x['network'] === $network)) : $c;
}
function catalog_item(string $id): ?array { foreach (catalog() as $c) if ($c['id'] === $id) return $c; return null; }
function phases(): array { static $p = null; if ($p === null) $p = json_decode((string)file_get_contents(APP_ROOT . '/content/phases.json'), true) ?: []; return $p; }

function course(string $id): ?array {
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,120}$/', $id)) return null;
    $f = APP_ROOT . '/content/courses/' . $id . '.json';
    if (!is_file($f)) return null;
    $raw = (string)file_get_contents($f);
    /* Academic Board edits that were applied to the live course replace the original text. */
    try {
        foreach (rows("SELECT original_text, edited_text FROM course_content_overrides WHERE course_key = ? AND status IN ('applied','live') ORDER BY created_at", [$id]) as $o) {
            $a = substr(json_encode((string)$o['original_text'], JSON_UNESCAPED_UNICODE), 1, -1); $b = substr(json_encode((string)$o['edited_text'], JSON_UNESCAPED_UNICODE), 1, -1);
            if ($a !== '') $raw = str_replace($a, $b, $raw);
        }
    } catch (Throwable $e) {}
    $c = json_decode($raw, true);
    if (!$c) return null;
    foreach ($c['modules'] as $i => &$m) { $m['id'] = $m['id'] ?? ('m' . ($i + 1)); foreach ($m['lessons'] as $j => &$l) $l['id'] = $l['id'] ?? ($m['id'] . '-l' . ($j + 1)); }
    return $c;
}

/* ── Translation packs (the same files the live site uses), cached on disk ── */
function course_lang(): string { $l = $_SESSION['clang'] ?? (me()['language'] ?? 'en'); return isset(COURSE_LANGS[$l]) ? $l : 'en'; }
function tpack(string $lang, string $courseId): array {
    static $mem = [];
    $k = $lang . '/' . $courseId;
    if (isset($mem[$k])) return $mem[$k];
    if ($lang === 'en') return $mem[$k] = [];
    $dir = APP_ROOT . '/storage/i18n/' . $lang; $f = $dir . '/' . $courseId . '.json';
    if (!is_file($f) || filemtime($f) < time() - 7 * 86400) {
        @mkdir($dir, 0755, true);
        $ch = curl_init(cfg('i18n_root', 'https://raw.githubusercontent.com/Buja-OS/woddi-course-video/i18n/') . $lang . '/' . rawurlencode($courseId) . '.json');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 9, CURLOPT_FOLLOWLOCATION => true]);
        $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code === 200 && $body) @file_put_contents($f, $body); elseif (!is_file($f)) @file_put_contents($f, '{}');
    }
    $j = json_decode((string)@file_get_contents($f), true) ?: [];
    $map = (isset($j['t']) && is_array($j['t'])) ? $j['t'] : $j;
    $norm = [];
    foreach ($map as $en => $tr) if (is_string($tr)) $norm[pack_key((string)$en)] = $tr;
    return $mem[$k] = $norm;
}
function pack_key(string $s): string { return trim((string)preg_replace('/\s+/', ' ', str_replace('**', '', $s))); }
function T(?string $s): string {
    $s = (string)$s; $cid = $GLOBALS['__course_id'] ?? ''; $lang = course_lang();
    if ($lang === 'en' || $cid === '' || $s === '') return $s;
    return tpack($lang, $cid)[pack_key($s)] ?? $s;
}
function ui_str(string $k): string { $l = course_lang(); return UI_STR[$k][$l] ?? UI_STR[$k]['en'] ?? $k; }
function inline(?string $s): string { return (string)preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', e(preg_replace('/\s*—\s*/u', ', ', T($s)))); }

function video_url(string $courseId, int $moduleNo, string $lang): string {
    return 'https://github.com/Buja-OS/woddi-course-video/releases/download/v2-' . rawurlencode($courseId) . '/' . $lang . '__m' . $moduleNo . '.mp4';
}

/* ── Lesson rendering ── */
function block_html(array $b): string {
    switch ($b['t'] ?? '') {
        case 'p': return '<p>' . inline($b['text'] ?? '') . '</p>';
        case 'h': return '<h2>' . inline($b['text'] ?? '') . '</h2>';
        case 'illustration':
            $src = ($b['set'] ?? '') === 'undraw' ? 'cc3img/u/' . rawurlencode((string)($b['name'] ?? '')) . '.svg' : (!empty($b['file']) ? 'cc3img/f/' . rawurlencode((string)$b['file']) . '.webp' : '');
            if (!$src || !is_file(APP_ROOT . '/assets/' . rawurldecode($src))) return '';
            return '<figure class="fig"><img src="' . asset($src) . '" alt="' . e(T($b['alt'] ?? '')) . '" loading="lazy">' . (!empty($b['caption']) ? '<figcaption>' . inline($b['caption']) . '</figcaption>' : '') . '</figure>';
        case 'table':
            $h = '<div class="ltb"><table><thead><tr>'; foreach ($b['head'] ?? [] as $c) $h .= '<th>' . inline($c) . '</th>';
            $h .= '</tr></thead><tbody>'; foreach ($b['rows'] ?? [] as $r) { $h .= '<tr>'; foreach ($r as $c) $h .= '<td>' . inline((string)$c) . '</td>'; $h .= '</tr>'; }
            return $h . '</tbody></table></div>';
        case 'diagram':
            $k = $b['kind'] ?? 'flow'; $h = '<div class="dia"><p class="bold">' . inline($b['title'] ?? '') . '</p>';
            if ($k === 'compare') {
                $h .= '<div class="g2">';
                foreach ([['left', 0, 'mags', 'magt'], ['right', 1, 'lems', 'lemt']] as $s) { $h .= '<div class="cmp" style="background:var(--' . $s[2] . ')"><p class="bold" style="color:var(--' . $s[3] . ')">' . inline($b['items'][$s[1]] ?? '') . '</p>'; foreach ($b[$s[0]] ?? [] as $x) $h .= '<p class="sm">' . inline($x) . '</p>'; $h .= '</div>'; }
                $h .= '</div>';
            } elseif ($k === 'checklist') { foreach ($b['items'] ?? [] as $x) $h .= '<div class="row"><i class="fa-solid fa-square-check" style="color:var(--lemt)"></i><span>' . inline($x) . '</span></div>'; }
            elseif ($k === 'pyramid') { $n = max(1, count($b['items'] ?? [])); $h .= '<div class="col" style="align-items:center;gap:6px">'; foreach ($b['items'] ?? [] as $i => $x) $h .= '<span class="pyr" style="width:' . round(46 + 54 * ($i + 1) / $n) . '%">' . inline($x) . '</span>'; $h .= '</div>'; }
            else { $h .= '<div class="row wrap" style="gap:8px">'; foreach ($b['items'] ?? [] as $i => $x) $h .= '<span class="fstep"><span class="num">' . ($i + 1) . '</span>' . inline($x) . '</span>'; $h .= '</div>'; }
            return $h . (!empty($b['caption']) ? '<p class="mut sm">' . inline($b['caption']) . '</p>' : '') . '</div>';
        case 'example':
            $h = '<div class="card col"><span class="pill g" style="align-self:flex-start"><i class="fa-solid fa-pen-ruler"></i> Worked example</span><h3>' . inline($b['title'] ?? '') . '</h3><p class="mut">' . inline($b['context'] ?? '') . '</p>';
            foreach ($b['steps'] ?? [] as $i => $x) $h .= '<div class="row top"><span class="num">' . ($i + 1) . '</span><p class="grow">' . inline($x) . '</p></div>';
            return $h . (!empty($b['result']) ? '<p class="result"><strong>Result.</strong> ' . inline($b['result']) . '</p>' : '') . '</div>';
        case 'callout':
            $m = ['tip' => ['fa-lightbulb', 'lems', 'lemt', 'Tip'], 'warning' => ['fa-triangle-exclamation', 'golds', 'gold', 'Watch out'], 'safety' => ['fa-helmet-safety', 'golds', 'gold', 'Safety first'], 'note' => ['fa-circle-info', 'mags', 'magt', 'Note']][$b['kind'] ?? 'note'] ?? ['fa-circle-info', 'mags', 'magt', 'Note'];
            return '<div class="callout" style="background:var(--' . $m[1] . ')"><i class="fa-solid ' . $m[0] . '" style="color:var(--' . $m[2] . ')"></i><div><p class="bold" style="color:var(--' . $m[2] . ')">' . inline($b['title'] ?? $m[3]) . '</p><p>' . inline($b['text'] ?? '') . '</p></div></div>';
        case 'list': $h = '<ul class="blist">'; foreach ($b['items'] ?? [] as $x) $h .= '<li>' . inline($x) . '</li>'; return $h . '</ul>';
        case 'steps': $h = '<div class="col" style="gap:8px">'; foreach ($b['items'] ?? [] as $i => $x) $h .= '<div class="row top"><span class="num">' . ($i + 1) . '</span><p class="grow">' . inline($x) . '</p></div>'; return $h . '</div>';
    }
    return '';
}

/* ── Progress ── */
function progress_rows(string $uid, string $cid): array {
    $out = [];
    foreach (rows('SELECT * FROM course_progress WHERE uid = ? AND course_id = ?', [$uid, $cid]) as $r) $out[(string)$r['lesson_id']] = $r;
    return $out;
}
function save_progress(string $uid, array $course, string $lessonId, string $phase, bool $completed, int $score = 0, ?string $moduleId = null, array $extra = []): void {
    $data = array_merge(['video_time' => $score, 'phase' => $phase, 'completed' => $completed ? 1 : 0, 'language' => course_lang(), 'module_id' => $moduleId, 'last_seen_at' => now(), 'updated_at' => now(), 'course_title' => $course['title']], $extra);
    $ex = row('SELECT id, completed FROM course_progress WHERE uid = ? AND course_id = ? AND lesson_id = ? LIMIT 1', [$uid, $course['id'], $lessonId]);
    if ($ex) { if ($ex['completed'] && !$completed) unset($data['completed']); upd('course_progress', $data, 'id = ?', [$ex['id']]); }
    else ins('course_progress', array_merge($data, ['uid' => $uid, 'course_id' => $course['id'], 'lesson_id' => $lessonId]));
}
/* Works out where a learner stands in a course: what is done, what is unlocked and the next step. */
function course_state(string $uid, array $course): array {
    $p = progress_rows($uid, $course['id']);
    $done = fn(string $id) => !empty($p[$id]['completed']);
    $total = 0; $doneN = 0; $mods = []; $next = null; $prevOk = true; $allQ = true;
    foreach ($course['modules'] as $mi => $m) {
        $ld = 0; $lessons = []; $prevLesson = true;
        foreach ($m['lessons'] as $li => $l) {
            $d = $done($l['id']); $open = $prevOk && $prevLesson;
            $lessons[] = ['id' => $l['id'], 'title' => $l['title'], 'minutes' => $l['minutes'] ?? 0, 'done' => $d, 'open' => $open || $d, 'mi' => $mi, 'li' => $li];
            if ($d) $ld++; elseif ($open && !$next) $next = ['type' => 'lesson', 'mi' => $mi, 'li' => $li, 'label' => 'Lesson ' . ($mi + 1) . '.' . ($li + 1) . ': ' . $l['title']];
            $prevLesson = $d; $total++;
        }
        $doneN += $ld;
        $hasQuiz = !empty($m['quiz']);
        $qp = !$hasQuiz || ($done($m['id'] . '.quiz') || $done('quiz-' . $m['id']));
        $allLessons = $ld === count($m['lessons']);
        if ($prevOk && $allLessons && !$qp && !$next) $next = ['type' => 'quiz', 'mi' => $mi, 'label' => 'Module ' . ($mi + 1) . ' quiz'];
        $mods[] = ['id' => $m['id'], 'title' => $m['title'], 'overview' => $m['overview'] ?? '', 'lessons' => $lessons, 'done' => $ld, 'open' => $prevOk, 'quiz_ready' => $prevOk && $allLessons && $hasQuiz, 'quiz_passed' => $hasQuiz && $qp, 'has_quiz' => $hasQuiz];
        if (!($allLessons && $qp)) { $allQ = false; }
        $prevOk = $prevOk && $allLessons && $qp;
    }
    $final = $done('final-quiz') || (bool)array_filter($p, fn($r) => !empty($r['final_quiz_passed']));
    $hasFinal = !empty($course['finalQuiz']);
    if (!$hasFinal && $allQ && $total > 0) $final = $final || true;
    if (!$next) $next = $final ? ['type' => 'cert', 'label' => 'Your certificate is ready'] : ['type' => 'final', 'label' => 'Final assessment'];
    return ['mods' => $mods, 'total' => $total, 'done' => $doneN, 'pct' => $total ? (int)round($doneN / $total * 100) : 0, 'all_modules' => $allQ, 'final' => $final, 'has_final' => $hasFinal, 'next' => $next,
        'capstone' => (bool)array_filter($p, fn($r) => !empty($r['capstone_completed'])), 'notes' => array_filter(array_map(fn($r) => $r['note'] ?? '', $p))];
}
function step_url(string $cid, array $next): string {
    return match ($next['type']) { 'lesson' => url("course/$cid/lesson/{$next['mi']}/{$next['li']}"), 'quiz' => url("course/$cid/quiz/{$next['mi']}"), 'final' => url("course/$cid/final"), default => url("course/$cid/certificate") };
}

/* ── Journey gate: Rooting, then Blueprint, then the Course Hub ── */
function journey(string $uid): array {
    $r = row('SELECT * FROM user_progress WHERE uid = ? ORDER BY updated_at DESC LIMIT 1', [$uid]) ?: [];
    $ph = phases(); $rootIds = array_column($ph['rooting'], 'id');
    $rd = array_values(array_intersect($rootIds, jdec($r['rooting_modules_done'] ?? '[]')));
    if (!empty($r['rooting_completed'])) $rd = $rootIds;
    $bpIds = []; foreach ($ph['blueprint'] as $p) foreach ($p['lessons'] as $l) $bpIds[] = $l['id'];
    $bd = array_values(array_intersect($bpIds, jdec($r['completed_lessons'] ?? '[]')));
    if (!empty($r['blueprint_completed'])) $bd = $bpIds;
    $rootDone = count($rd) === count($rootIds);
    if (net() === 'nnn') {
        $bpa = array_values(array_filter(catalog('nnn'), fn($c) => $c['track'] === 'bpa')); usort($bpa, fn($a, $b) => $a['order'] <=> $b['order']);
        $got = 0; foreach ($bpa as $c) if (has_certificate($uid, $c['id'])) $got++;
        $blueDone = $got === count($bpa); $blueTotal = count($bpa); $blueN = $got;
    } else { $blueDone = count($bd) === count($bpIds); $blueTotal = count($bpIds); $blueN = count($bd); }
    $gate = (string)setting('onboarding_gate', 'on') !== 'off';
    return ['row' => $r, 'rooting' => $rd, 'root_total' => count($rootIds), 'root_done' => $rootDone, 'blueprint' => $bd, 'blue_total' => $blueTotal, 'blue_n' => $blueN, 'blue_done' => $blueDone,
        'hub_open' => !$gate || ($rootDone && $blueDone), 'gate' => $gate];
}
function save_journey(string $uid, array $data): void {
    $data['updated_at'] = now(); $data['last_activity_date'] = gmdate('Y-m-d');
    $r = row('SELECT id FROM user_progress WHERE uid = ? LIMIT 1', [$uid]);
    if ($r) upd('user_progress', $data, 'id = ?', [$r['id']]); else ins('user_progress', array_merge($data, ['uid' => $uid]));
}
/* Can this learner open this course? Returns '' when yes, otherwise the reason. */
function course_lock(string $uid, array $item): string {
    if ($item['network'] !== net()) return 'This course belongs to the other network.';
    if (is_staff()) return '';
    $j = journey($uid);
    if ($j['gate'] && !$j['root_done']) return 'Complete the Rooting Phase first.';
    if (net() === 'nnn') {
        if ($item['track'] === 'elective') return ($j['gate'] && !$j['blue_done']) ? 'Electives open after all four Blueprint Academy courses.' : '';
        $bpa = array_values(array_filter(catalog('nnn'), fn($c) => $c['track'] === 'bpa')); usort($bpa, fn($a, $b) => $a['order'] <=> $b['order']);
        foreach ($bpa as $c) { if ($c['id'] === $item['id']) return ''; if (!has_certificate($uid, $c['id'])) return 'Finish "' . $c['title'] . '" first.'; }
        return '';
    }
    return ($j['gate'] && !$j['blue_done']) ? 'Complete the Blueprint first.' : '';
}

/* ── Certificates ── */
function has_certificate(string $uid, string $cid): bool { return (bool)val('SELECT 1 FROM certificates WHERE uid = ? AND course_key = ? AND COALESCE(revoked,0) = 0 LIMIT 1', [$uid, $cid]); }
function certificate_for(string $uid, string $cid): ?array { return row('SELECT * FROM certificates WHERE uid = ? AND course_key = ? AND COALESCE(revoked,0) = 0 ORDER BY issued_at DESC LIMIT 1', [$uid, $cid]); }
/* The cohort a date falls in, as the live site names it: "September–November 2026". Reads academic_cycles when it has rows. */
function cohort_for(?string $date = null): string {
    $t = $date ? strtotime($date) : time(); $y = (int)gmdate('Y', $t); $m = (int)gmdate('n', $t);
    try { $r = row('SELECT name FROM academic_cycles WHERE year = ? AND start_month <= ? AND end_month >= ? LIMIT 1', [$y, $m, $m]); if ($r && $r['name']) return (string)$r['name']; } catch (Throwable $e) {}
    if ($m === 1) return 'September–November ' . ($y - 1);
    return ($m <= 5 ? 'February–April ' : ($m <= 8 ? 'June–August ' : 'September–November ')) . $y;
}
/* Hours printed on the certificate: the live site's rule by level. */
function course_hours(?string $courseKey): string {
    $it = $courseKey ? catalog_item($courseKey) : null; if (!$it) return '60';
    return stripos((string)$it['level'], 'advanced') !== false ? '40' : (stripos((string)$it['level'], 'intermediate') !== false ? '30' : '20');
}
function issue_certificate(array $user, array $course, int $score): array {
    $ex = certificate_for($user['uid'], $course['id']);
    if ($ex) return $ex;
    do { $code = 'WDI-' . gmdate('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)); } while (val('SELECT 1 FROM certificates WHERE credential_code = ?', [$code]));
    $id = ins('certificates', ['uid' => $user['uid'], 'course_key' => $course['id'], 'title' => $course['certificateTitle'] ?? $course['title'], 'course_name' => $course['title'], 'issued_at' => now(), 'score' => $score,
        'language' => course_lang(), 'student_name' => display_name($user), 'pathway' => $user['pathway'] ?? 'gmn', 'revoked' => 0, 'source' => 'app', 'credential_code' => $code, 'cohort' => cohort_for()]);
    try { ins('certificate_audit', ['certificate_id' => $id, 'uid' => $user['uid'], 'action' => 'issued', 'credential_code' => $code, 'by_name' => 'system', 'new_value' => $course['title'], 'at' => now()]); } catch (Throwable $e) {}
    award($user['uid'], (int)points_rules()['certificate'], 'certificate:' . $course['id']);
    /* Marketplace rule from the live site: listings wait for the seller's first certificate, then go live by themselves. */
    try { $w = q("UPDATE mp_listings SET status = 'active', published_at = ? WHERE user_id = ? AND status = 'pending_certificate'", [now(), $user['uid']])->rowCount(); if ($w) notify($user['uid'], 'Your marketplace listing is live', 'You earned a certificate, so your waiting listing is now visible to buyers.', 'market?tab=shop', 'market', false); } catch (Throwable $e) {}
    notify($user['uid'], 'Your certificate is ready', $course['title'] . '. Download or share it.', 'certificates', 'certificate');
    return row('SELECT * FROM certificates WHERE id = ?', [$id]);
}

/* Recorded narration for a lesson, if a file exists: assets/audio/journey/<lang>/<id>.mp3 or assets/audio/courses/<course>/<lang>/<lesson>.mp3. */
function listen_audio(string $kind, string $id, string $lang = 'en', string $course = ''): string {
    $id = preg_replace('/[^A-Za-z0-9._-]/', '', $id); $course = preg_replace('/[^a-z0-9-]/', '', $course); $lang = preg_replace('/[^a-z]/', '', $lang);
    $rel = $kind === 'journey' ? "audio/journey/$lang/$id.mp3" : "audio/courses/$course/$lang/$id.mp3";
    return is_file(APP_ROOT . '/assets/' . $rel) ? ' data-audio="' . e(asset($rel)) . '"' : '';
}
