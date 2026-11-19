<?php
/* Small JSON endpoints used by assets/app.js. */
declare(strict_types=1);
$action = $seg[1] ?? '';
if ($action === 'vapid') json_out(['key' => cfg('vapid.public', '')]);
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
