<?php
/* Small JSON endpoints used by assets/app.js. */
declare(strict_types=1);
$action = $seg[1] ?? '';
if ($action === 'vapid') json_out(['key' => cfg('vapid.public', '')]);

/* ── Public: saved translations and on-demand translation for the landing page language bar ── */
const GTX = ['zh' => 'zh-CN', 'nso' => 'nso', 'bem' => 'bem'];
function i18n_lang(string $l): string { return preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $l) ? strtolower($l) : ''; }
if ($action === 'i18n') {
    $lang = i18n_lang((string)($_GET['lang'] ?? '')); header('Cache-Control: public, max-age=300');
    json_out(['rows' => $lang ? rows('SELECT src, text FROM site_i18n WHERE lang = ? LIMIT 3000', [$lang]) : []]);
}
if ($action === 'translate') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST; $lang = i18n_lang((string)($in['target'] ?? ($in['lang'] ?? '')));
    $texts = array_values(array_map(fn($t) => mb_substr((string)$t, 0, 8000), (array)($in['texts'] ?? (isset($in['text']) ? [$in['text']] : []))));
    if (!$lang || !$texts) json_out(['error' => 'text or texts is required'], 400);
    if (count($texts) > 60 || array_sum(array_map('strlen', $texts)) > 20000) json_out(['error' => 'Batch too large'], 413);
    if ($lang === 'en') json_out(['translations' => $texts, 'translation' => $texts[0], 'target' => $lang]);
    /* gentle limit per visitor: 40 batches per 10 minutes */
    $now = time(); $_SESSION['tr'] = array_values(array_filter($_SESSION['tr'] ?? [], fn($t) => $t > $now - 600)); if (count($_SESSION['tr']) >= 40) json_out(['error' => 'Slow down'], 429); $_SESSION['tr'][] = $now;
    $hash = array_map('sha1', $texts); $have = [];
    foreach (rows('SELECT src_hash, text FROM site_i18n WHERE lang = ? AND src_hash IN (' . implode(',', array_fill(0, count($hash), '?')) . ')', array_merge([$lang], $hash)) as $r) $have[$r['src_hash']] = $r['text'];
    $todo = []; foreach ($texts as $i => $t) if (!isset($have[$hash[$i]]) && !in_array($t, $todo, true)) $todo[] = $t;
    $engine = 'cache';
    if ($todo) {
        $out = null;
        if ($key = cfg('gemini_key')) {
            $prompt = "Translate each string in this JSON array from English into the language with code \"$lang\". Keep **bold markers**, numbers, names (WODDI, WGMN, WNNN) and punctuation. Return only a JSON array of the same length, in the same order.\n" . json_encode($todo, JSON_UNESCAPED_UNICODE);
            foreach ([cfg('gemini_model', 'gemini-flash-latest'), 'gemini-2.5-flash'] as $model) {
                $r = http_json("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode($key), ['contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json']], [], 20);
                $arr = json_decode((string)($r['body']['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
                if (is_array($arr) && count($arr) === count($todo)) { $out = array_map('strval', $arr); $engine = "gemini:$model"; break; }
            }
        }
        if (!$out) { /* same free fallback the old site used */
            $enc = fn($t) => preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', htmlspecialchars($t, ENT_NOQUOTES));
            $ch = curl_init('https://translate.googleapis.com/translate_a/t?client=gtx&sl=en&tl=' . rawurlencode(GTX[$lang] ?? $lang) . '&format=html');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'], CURLOPT_POSTFIELDS => implode('&', array_map(fn($t) => 'q=' . rawurlencode($enc($t)), $todo))]);
            $j = json_decode((string)curl_exec($ch), true); curl_close($ch); if (is_string($j)) $j = [$j];
            if (is_array($j) && count($j) === count($todo)) { $out = array_map(fn($x) => trim(preg_replace('/\s+/', ' ', html_entity_decode(preg_replace(['/\s*<b>\s*/', '/\s*<\/b>\s*/'], [' **', '** '], (string)(is_array($x) ? $x[0] : $x)), ENT_QUOTES | ENT_HTML5))), $j); $engine = 'google-free'; }
        }
        if ($out) foreach ($todo as $i => $t) { $v = trim((string)$out[$i]); if ($v === '') continue; $have[sha1($t)] = $v; try { q('REPLACE INTO site_i18n (lang, src_hash, src, text, scope, engine, created_at) VALUES (?,?,?,?,?,?,?)', [$lang, sha1($t), $t, $v, mb_substr((string)($in['scope'] ?? ''), 0, 40), $engine, now()]); } catch (Throwable $e) {} }
    }
    $tr = array_map(fn($t, $h) => $have[$h] ?? $t, $texts, $hash);
    json_out(['translations' => $tr, 'translation' => $tr[0], 'target' => $lang, 'engine' => $engine]);
}

if (!is_post()) json_out(['ok' => false, 'error' => 'POST only'], 405);
if (!hash_equals(csrf(), (string)($_POST['_csrf'] ?? ''))) json_out(['ok' => false, 'error' => 'Session expired. Reload the page.'], 419);
$me = me(); if (!$me) json_out(['ok' => false, 'error' => 'Please sign in.'], 401);
$uid = $me['uid'];

if ($action === 'push-subscribe') {
    $ep = (string)post('endpoint'); $p = (string)post('p256dh'); $a = (string)post('auth');
    if (!preg_match('#^https://#', $ep) || $p === '' || $a === '') json_out(['ok' => false, 'error' => 'Bad subscription'], 422);
    q('DELETE FROM push_subscriptions WHERE endpoint = ?', [$ep]);
    ins('push_subscriptions', ['uid' => $uid, 'endpoint' => $ep, 'p256dh' => $p, 'auth' => $a]);
    json_out(['ok' => true]);
}
if ($action === 'push-test') { require_once APP_ROOT . '/lib/push.php'; $r = push_to_user($uid, 'WODDI Institute', 'Notifications are working on this device.', ''); json_out(['ok' => true, 'sent' => $r]); }
if ($action === 'note') {
    $cid = (string)post('course'); $lesson = (string)post('lesson'); $co = course($cid);
    if (!$co || $lesson === '') json_out(['ok' => false], 422);
    $note = mb_substr((string)post('note'), 0, 6000);
    if (val('SELECT 1 FROM course_progress WHERE uid = ? AND course_id = ? AND lesson_id = ?', [$uid, $cid, $lesson])) q('UPDATE course_progress SET note = ?, updated_at = ? WHERE uid = ? AND course_id = ? AND lesson_id = ?', [$note, now(), $uid, $cid, $lesson]);
    else ins('course_progress', ['uid' => $uid, 'course_id' => $cid, 'course_title' => $co['title'], 'lesson_id' => $lesson, 'phase' => 'lesson', 'completed' => 0, 'note' => $note, 'updated_at' => now()]);
    json_out(['ok' => true]);
}
if ($action === 'like') {
    $id = (string)post('post'); $country = post('kind') === 'country';
    [$likes, $posts] = $country ? ['community_post_likes', 'community_posts'] : ['forum_likes', 'forum_posts'];
    $p = row("SELECT * FROM `$posts` WHERE id = ?", [$id]); if (!$p) json_out(['ok' => false], 404);
    $has = val("SELECT 1 FROM `$likes` WHERE post_id = ? AND uid = ?", [$id, $uid]);
    if ($has) q("DELETE FROM `$likes` WHERE post_id = ? AND uid = ?", [$id, $uid]);
    else { ins($likes, ['post_id' => $id, 'uid' => $uid, 'user_id' => $uid]); $owner = (string)($p['uid'] ?: ($p['user_id'] ?? '')); if ($owner && $owner !== $uid && !$country) notify($owner, first_name($me) . ' liked your post', '', 'community?post=' . $id, 'like', false); }
    $n = (int)val("SELECT COUNT(*) FROM `$likes` WHERE post_id = ?", [$id]);
    q("UPDATE `$posts` SET likes_count = ? WHERE id = ?", [$n, $id]); if (!$country) q('UPDATE forum_posts SET like_count = ? WHERE id = ?', [$n, $id]);
    json_out(['ok' => true, 'liked' => !$has, 'count' => $n]);
}
if ($action === 'read') { q('UPDATE notifications SET read_at = ?, `read` = 1, unread = 0 WHERE id = ? AND (uid = ? OR user_id = ?)', [now(), post('id'), $uid, $uid]); json_out(['ok' => true, 'unread' => unread_count($uid)]); }
if ($action === 'unread') json_out(['ok' => true, 'unread' => unread_count($uid)]);
json_out(['ok' => false, 'error' => 'Unknown action'], 404);
