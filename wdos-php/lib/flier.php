<?php
declare(strict_types=1);
/* v109.56 routes: meeting fliers, their translations, the countdown on dashboards, replies from the email, and who will attend. */

/** The meetings coming up for her, with the flier in her language and her reply. Shown at the top of every dashboard. */
route('GET', '/meetings/countdown', function () {
  $u = require_user(); $out = []; $lang = flier_view_lang($u);
  $list = rows("SELECT m.*, h.first_name h_first, h.last_name h_last FROM meetings m JOIN users h ON h.id=m.host_id WHERE m.status='scheduled' AND m.ends_at>=? ORDER BY m.starts_at LIMIT 80", [now()]);
  foreach ($list as $m) {
    if (count($out) >= 3) break;
    if (!meeting_is_invited($u, $m)) continue;
    $out[] = meeting_shape($m) + ['flier' => flier_shape($m, $lang), 'mine' => (string) (val('SELECT answer FROM meeting_rsvps WHERE meeting_id=? AND user_id=?', [(int) $m['id'], (int) $u['id']]) ?? '')];
  }
  return ['items' => $out, 'now' => now()];
});

/** The host or HQ adds or changes the flier: a picture, and the words WDOS lays out and translates. */
route('POST', '/meetings/(\d+)/flier', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] !== 'scheduled') fail(409, 'The flier can be changed only before the meeting is held.', 'transition');
  $b = body(); $img = (string) ($b['image'] ?? '');
  // v109.59: a new picture (or none) means its words must be read again and its language pictures made again
  $newPic = $img !== '' || !empty($b['remove_image']);
  if ($img !== '') flier_store_image((int) $id, $img);
  elseif (!empty($b['remove_image'])) { flier_drop_image((string) $m['flier_image'], (string) $m['flier_mime']); q("UPDATE meetings SET flier_image='', flier_mime='' WHERE id=?", [(int) $id]); }
  if ($newPic) { try { q('UPDATE meetings SET flier_words=NULL WHERE id=?', [(int) $id]); q('UPDATE meeting_flier_texts SET words_json=NULL WHERE meeting_id=?', [(int) $id]); } catch (PDOException $e) { /* before migration 055 */ } if (function_exists('flier_drop_renders')) flier_drop_renders((int) $id); }
  $head = str_in('headline', 190); $text = str_in('text', 6000); $changed = false;
  if (array_key_exists('headline', $b) || array_key_exists('text', $b)) {
    $changed = $head !== (string) $m['flier_headline'] || $text !== (string) $m['flier_text'];
    if ($changed) { q('UPDATE meetings SET flier_headline=?, flier_text=? WHERE id=?', [$head, $text, (int) $id]); q("UPDATE meeting_flier_texts SET headline='', body='', source='ai' WHERE meeting_id=?", [(int) $id]); }
  }
  if ($img !== '' || !empty($b['remove_image']) || $changed) flier_drop_renders((int) $id);      // the email pictures are made again
  log_activity((int) $u['id'], null, 'meeting_flier', $m['title'], 'meeting', (int) $id);
  if ($changed && ($head !== '' || $text !== '')) after_response(function () use ($id) { @set_time_limit(300); flier_translate((int) $id); });
  $m = meeting_get((int) $id);
  return ['ok' => true, 'flier' => flier_shape($m, flier_view_lang($u)), 'translating' => $changed && ($head !== '' || $text !== '')];
});
/** The flier in every language, for the host to check and correct. */
route('GET', '/meetings/(\d+)/flier/langs', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  $ai = class_exists('Ai') && Ai::enabled() && (bool) Ai::lanes();
  $renders = []; foreach (array_merge(['en'], FLIER_LANGS) as $l) if ($ru = flier_render_url((int) $id, $l, false)) $renders[$l] = $ru;
  $wl = []; foreach (rows('SELECT lang, words_json, source FROM meeting_flier_texts WHERE meeting_id=?', [(int) $id]) as $r) if ((string) $r['words_json'] !== '') $wl[$r['lang']] = json_decode((string) $r['words_json'], true);
  return ['headline' => (string) $m['flier_headline'], 'text' => (string) $m['flier_text'], 'image' => flier_image_url($m), 'langs' => flier_langs((int) $id), 'ai_on' => $ai,
    'words' => flier_words($m), 'words_by_lang' => (object) $wl,
    'pictures' => (object) array_filter(array_combine(array_merge(['en'], FLIER_LANGS), array_map(fn($l) => flier_render_url((int) $id, $l, false), array_merge(['en'], FLIER_LANGS)))), 'renders' => $renders,
    'invites_waiting' => (bool) val('SELECT 1 FROM app_settings WHERE k=?', ['meet_inv_wait_' . (int) $id]),
    'leaders_by_lang' => array_column(rows("SELECT u.lang, COUNT(*) n FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND (" . meeting_invited_sql($m)[0] . ') GROUP BY u.lang', meeting_invited_sql($m)[1]), 'n', 'lang')];
});
route('POST', '/meetings/(\d+)/flier/langs/([a-z]{2})', function ($id, $lang) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if (!in_array($lang, FLIER_LANGS, true)) fail(422, 'Choose one of the WDOS languages.', 'lang');
  $head = str_in('headline', 250); $text = str_in('text', 6000);
  if ($head === '' && $text === '') { q("UPDATE meeting_flier_texts SET headline='', body='', source='ai' WHERE meeting_id=? AND lang=?", [(int) $id, $lang]); return ['ok' => true, 'langs' => flier_langs((int) $id)]; }
  q('INSERT INTO meeting_flier_texts (meeting_id,lang,headline,body,source,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE headline=VALUES(headline), body=VALUES(body), source=VALUES(source), updated_at=VALUES(updated_at)', [(int) $id, $lang, $head, $text, 'hq', now()]);
  flier_drop_renders((int) $id, $lang);
  return ['ok' => true, 'langs' => flier_langs((int) $id)];
});
route('POST', '/meetings/(\d+)/flier/translate', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m); @set_time_limit(180);
  $r = flier_translate((int) $id, !empty(body()['redo']));
  if (($r['reason'] ?? '') === 'ai_off') fail(409, 'Automatic translation is not set up on this server. Type each language yourself below, or ask the IT Lead to switch on an AI lane.', 'ai_off');
  if (($r['reason'] ?? '') === 'nothing_to_translate') fail(422, 'Write the flier\'s headline or message first.', 'empty');
  return ['ok' => true, 'done' => $r['done'], 'failed' => $r['failed'] ?? [], 'langs' => flier_langs((int) $id)];
});

/** Who will attend: the replies, by answer, and who has not replied. */
route('GET', '/meetings/(\d+)/replies', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  [$w, $p] = meeting_invited_sql($m);
  $people = rows("SELECT u.id, u.first_name, u.last_name, u.has_photo, u.state_region, u.country_code, u.designation, mr.answer, mr.answered_at, mj.joined_at FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id
                  LEFT JOIN meeting_rsvps mr ON mr.meeting_id=? AND mr.user_id=u.id LEFT JOIN meeting_joins mj ON mj.meeting_id=? AND mj.user_id=u.id WHERE u.status='active' AND u.id<>? AND ($w) ORDER BY (mj.joined_at IS NULL), (mr.answer IS NULL), mr.answer='no', mr.answer='maybe', u.first_name, u.last_name LIMIT 2000",
    array_merge([(int) $id, (int) $id, (int) $m['host_id']], $p));
  $invited = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND u.id<>? AND ($w)", array_merge([(int) $m['host_id']], $p));
  $c = ['yes' => 0, 'maybe' => 0, 'no' => 0]; foreach (rows('SELECT answer, COUNT(*) n FROM meeting_rsvps WHERE meeting_id=? GROUP BY answer', [(int) $id]) as $r) $c[$r['answer']] = (int) $r['n'];
  return ['invited' => $invited, 'counts' => $c, 'no_reply' => max(0, $invited - array_sum($c)), 'joined' => (int) val('SELECT COUNT(*) FROM meeting_joins WHERE meeting_id=?', [(int) $id]), 'people' => $people, 'shown' => count($people)];
});
/** Ask the people who have not replied, in the app and by email, with the reply buttons. */
route('POST', '/meetings/(\d+)/nudge', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($m['status'] !== 'scheduled' || strtotime($m['starts_at'] . ' UTC') <= time()) fail(409, 'This meeting is no longer open for replies.', 'transition');
  throttle('meetnudge:' . (int) $id, 3, 86400); throttle_hit('meetnudge:' . (int) $id);
  [$w, $p] = meeting_invited_sql($m); $nr = "NOT EXISTS (SELECT 1 FROM meeting_rsvps mr WHERE mr.meeting_id=" . (int) $id . " AND mr.user_id=u.id)";
  $ts = strtotime($m['starts_at'] . ' UTC');
  $n = notify_many_timed("u.id<>? AND $nr AND " . meeting_join_sql($m, $w), array_merge([(int) $m['host_id']], $p), 'meeting', 'Will you attend? ' . mb_substr($m['title'], 0, 110), fn($tz) => 'On ' . tz_when($ts, $tz, true, true) . '. Tap to say yes, maybe or no.', '#/meetings/' . (int) $id);
  $mailed = 0;
  if (mail_configured()) foreach (rows("SELECT u.id, u.lang, u.email, u.first_name, u.tz, u.country_code FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND u.email<>'' AND u.email NOT LIKE '%@phone.wdos.invalid' AND u.id<>? AND $nr AND ($w) LIMIT 2000", array_merge([(int) $m['host_id']], $p)) as $r) {
    $tz = tz_of($r);
    if (mail_queue($r['email'], $r['first_name'], 'Will you attend? ' . mb_substr($m['title'], 0, 150), mail_wrap('Dear ' . $r['first_name'] . ',', '<p><b>' . htmlspecialchars($m['title']) . '</b><br>' . htmlspecialchars(tz_when($ts, $tz, true, true)) . '</p>' . flier_mail_block($m, $r), 'Open in WDOS', app_url() . '/#/meetings/' . (int) $id))) $mailed++;
  }
  log_activity((int) $u['id'], null, 'meeting_nudge', $m['title'] . " · $n asked", 'meeting', (int) $id);
  after_response(fn() => push_drain(80)); after_response(fn() => mail_drain(15));
  return ['ok' => true, 'asked' => $n, 'emails' => $mailed];
});

/* ---- a reply from the email, without signing in ------------------------------------------------------------------
 * The link in the email opens a small page; nothing is saved by opening it (mail scanners open links). Pressing a button
 * on the page saves the reply. The link is signed for that person and that meeting only. */
function flier_reply_who(int $mid): array {
  $uid = (int) ($_GET['u'] ?? body()['u'] ?? 0); $sig = (string) ($_GET['s'] ?? body()['s'] ?? '');
  $m = row('SELECT * FROM meetings WHERE id=?', [$mid]); $who = $uid ? row("SELECT id, first_name, lang, tz, country_code, status FROM users WHERE id=?", [$uid]) : null;
  $ok = $m && $who && $who['status'] === 'active' && hash_equals(flier_sig($mid, $uid), $sig) && ((int) $m['host_id'] === $uid || meeting_is_invited($who + ['role' => ''], $m));
  return [$ok, $m, $who];
}
route('GET', '/meetings/(\d+)/reply', function ($id) {
  [$ok, $m, $who] = flier_reply_who((int) $id); $lang = flier_lang((string) ($who['lang'] ?? 'en'));
  $ans = in_array($_GET['a'] ?? '', ['yes', 'maybe', 'no'], true) ? $_GET['a'] : '';
  header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex'); header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'");
  $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
  $head = '<!doctype html><html lang="' . $lang . '"' . ($lang === 'ar' ? ' dir="rtl"' : '') . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>WDOS</title>'
    . '<style>body{font-family:Arial,Helvetica,sans-serif;background:#fbf6f8;color:#231019;margin:0;padding:24px}main{max-width:520px;margin:0 auto;background:#fff;border-radius:16px;border-top:6px solid #D4006A;border-bottom:4px solid #7CB518;padding:22px;box-shadow:0 6px 24px rgba(35,16,25,.08)}'
    . 'h1{font-size:21px;margin:8px 0}p{font-size:16px;line-height:1.5}.b{display:block;width:100%;margin:10px 0;padding:15px;border-radius:12px;border:2px solid #D4006A;background:#fff;color:#231019;font-size:17px;font-weight:bold;cursor:pointer}.b.on,.b:hover{background:#D4006A;color:#fff}#msg{font-weight:bold;color:#3b6b00;min-height:24px}img{max-width:100%;border-radius:10px}.c{display:inline-block;background:#7CB518;color:#12210A;font-weight:bold;padding:6px 14px;border-radius:999px}</style></head><body><main>'
    . '<img src="/assets/logos/woddi-email.png" alt="WODDI" width="150" style="display:block;margin:0 auto 6px;width:150px">';
  if (!$ok) { echo $head . '<p>' . $e(flier_word($lang, 'bad')) . '</p><p><a href="/">WDOS</a></p></main></body></html>'; exit; }
  $ts = strtotime($m['starts_at'] . ' UTC'); $f = flier_text_for($m, $lang); $mine = (string) (val('SELECT answer FROM meeting_rsvps WHERE meeting_id=? AND user_id=?', [(int) $id, (int) $who['id']]) ?? '');
  $open = $m['status'] === 'scheduled' && $ts > time(); $img = flier_image_url($m);
  echo $head . ($img !== '' ? '<img src="' . $e($img) . '" alt="">' : '') . '<h1>' . $e($f['headline'] !== '' ? $f['headline'] : $m['title']) . '</h1>'
    . '<p><b>' . $e(flier_word($lang, 'when')) . ':</b> ' . $e(tz_when($ts, tz_of($who), true, true)) . '</p>' . ($open ? '<p><span class="c">' . $e(flier_countdown($ts, $lang)) . '</span></p>' : '')
    . ($open ? '<p><b>' . $e($who['first_name']) . ', ' . $e(flier_word($lang, 'ask')) . '</b></p>'
      . implode('', array_map(fn($a) => '<button class="b' . (($mine ?: $ans) === $a ? ' on' : '') . '" data-a="' . $a . '">' . $e(flier_word($lang, $a)) . '</button>', ['yes', 'maybe', 'no']))
      . '<p id="msg" role="status"></p><p style="font-size:13px;color:#7a6a72">' . $e(flier_word($lang, 'change')) . '</p>'
      . '<script>document.querySelectorAll(".b").forEach(function(b){b.onclick=function(){fetch(location.pathname,{method:"POST",headers:{"Content-Type":"application/json","X-WDOS":"1"},body:JSON.stringify({u:' . (int) $who['id'] . ',s:' . json_encode((string) $_GET['s']) . ',answer:b.dataset.a})}).then(function(r){return r.json()}).then(function(j){if(j.ok){document.querySelectorAll(".b").forEach(function(x){x.classList.toggle("on",x===b)});document.getElementById("msg").textContent=' . json_encode(flier_word($lang, 'thanks')) . '}else{document.getElementById("msg").textContent=j.message||"Error"}}).catch(function(){document.getElementById("msg").textContent="Offline"})}})</script>'
      : '<p>' . $e(flier_word($lang, 'closed')) . '</p>')
    . '</main></body></html>';
  exit;
});
route('POST', '/meetings/(\d+)/reply', function ($id) {
  [$ok, $m, $who] = flier_reply_who((int) $id);
  if (!$ok) fail(403, flier_word((string) ($who['lang'] ?? 'en'), 'bad'), 'bad_link');
  throttle('meetreply:' . (int) $who['id'], 30, 3600); throttle_hit('meetreply:' . (int) $who['id']);
  $a = (string) (body()['answer'] ?? ''); if (!in_array($a, ['yes', 'no', 'maybe'], true)) fail(422, 'Choose yes, no or maybe.', 'answer');
  if ($m['status'] !== 'scheduled' || strtotime($m['starts_at'] . ' UTC') <= time()) fail(409, flier_word((string) $who['lang'], 'closed'), 'transition');
  q('DELETE FROM meeting_rsvps WHERE meeting_id=? AND user_id=?', [(int) $id, (int) $who['id']]);
  q('INSERT INTO meeting_rsvps (meeting_id,user_id,answer,answered_at) VALUES (?,?,?,?)', [(int) $id, (int) $who['id'], $a, now()]);
  return ['ok' => true, 'answer' => $a];
});

/* ---- the flier design (v109.57) --------------------------------------------------------------------------------
 * HQ can give every generated flier a designed background (made in Canva, for example). WDOS writes the meeting's words,
 * the date in each reader's own time and a QR code on top. With no design uploaded, WDOS draws its own magenta background. */
function flier_template(): array {
  $r = row("SELECT mime, updated_at FROM message_photos WHERE image_key='flier/template'");
  return $r ? ['url' => media_url('flier/template', $r['mime'], strtotime($r['updated_at'] . ' UTC')), 'updated_at' => $r['updated_at']] : ['url' => '', 'updated_at' => null];
}
route('GET', '/meetings/flier-template', function () { require_user(); return flier_template(); });
route('POST', '/hq/flier-template', function () {
  $me = require_hq();
  if (!empty(body()['remove'])) { $o = row("SELECT mime FROM message_photos WHERE image_key='flier/template'"); if ($o) flier_drop_image('flier/template', (string) $o['mime']); log_activity((int) $me['id'], null, 'flier_template', 'removed'); return ['ok' => true] + flier_template(); }
  [$mime, $bin] = flier_check_image((string) (body()['image'] ?? ''));
  $info = getimagesizefromstring($bin);
  if (($info[0] ?? 0) < 600 || ($info[1] ?? 0) < 700) fail(422, 'Choose a design at least 600 by 700 pixels. The best size is 1080 by 1350 (portrait).', 'flier_image');
  $o = row("SELECT mime FROM message_photos WHERE image_key='flier/template'"); if ($o) @unlink(media_file('flier/template', (string) $o['mime']));
  $st = db()->prepare('REPLACE INTO message_photos (image_key,mime,bytes,updated_at) VALUES (?,?,?,?)');
  $st->bindValue(1, 'flier/template'); $st->bindValue(2, $mime); $st->bindValue(3, $bin, PDO::PARAM_LOB); $st->bindValue(4, now()); $st->execute();
  media_write('flier/template', $mime, $bin);
  log_activity((int) $me['id'], null, 'flier_template', 'new design ' . $info[0] . 'x' . $info[1]);
  return ['ok' => true] + flier_template();
});

/* ---- v109.58 ------------------------------------------------------------------------------------------------------ */
/** The host's browser sends the flier picture it drew for one language (words translated, date, QR code), for the emails. */
route('POST', '/meetings/(\d+)/flier/render/([a-z]{2})', function ($id, $lang) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if ($lang !== 'en' && !in_array($lang, FLIER_LANGS, true)) fail(422, 'Choose one of the WDOS languages.', 'lang');
  [$mime, $bin] = flier_check_image((string) (body()['image'] ?? '')); $key = "flier/r{$id}-{$lang}";
  $o = row('SELECT mime FROM message_photos WHERE image_key=?', [$key]); if ($o) @unlink(media_file($key, (string) $o['mime']));
  $st = db()->prepare('REPLACE INTO message_photos (image_key,mime,bytes,updated_at) VALUES (?,?,?,?)');
  $st->bindValue(1, $key); $st->bindValue(2, $mime); $st->bindValue(3, $bin, PDO::PARAM_LOB); $st->bindValue(4, now()); $st->execute();
  media_write($key, $mime, $bin);
  return ['ok' => true, 'url' => flier_render_url((int) $id, $lang, false)];
});
/** The pictures are ready: send the invitation emails now (once). */
route('POST', '/meetings/(\d+)/send-invites', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  $n = meeting_invite_mail((int) $id); after_response(fn() => mail_drain(15));
  return ['ok' => true, 'emails' => $n];
});
/** Join through WDOS from an email: record that she joined, then open the call. */
route('GET', '/meetings/(\d+)/go', function ($id) {
  [$ok, $m, $who] = flier_reply_who((int) $id);
  $to = app_origin() . '/#/meetings/' . (int) $id;
  if ($ok && $m['status'] === 'scheduled') {
    try { q('INSERT INTO meeting_joins (meeting_id,user_id,joined_at) VALUES (?,?,?)', [(int) $id, (int) $who['id'], now()]); } catch (PDOException $e) { /* already recorded */ }
    if (preg_match('#https://\S+#i', (string) $m['location'], $lm)) $to = rtrim($lm[0], '.,)');
  }
  header('Cache-Control: no-store'); header('Location: ' . $to, true, 302); exit;
});
/** Slides and documents for the meeting. Everyone invited can open them; they go out with the minutes. */
route('POST', '/meetings/(\d+)/files', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  $fid = (int) (body()['file_id'] ?? 0); if (!val('SELECT 1 FROM doc_files WHERE id=? AND uploaded_by=?', [$fid, (int) $u['id']])) fail(404, 'That file was not found.', 'not_found');
  doc_link($fid, 'smeeting', (int) $id, 'document', (int) $u['id']);
  $told = 0;
  if ($m['status'] === 'completed') {      // added after the meeting: everyone invited is told
    $name = (string) val('SELECT name FROM doc_files WHERE id=?', [$fid]);
    $told = meeting_broadcast($m, 'New document from the meeting: ' . mb_substr($m['title'], 0, 90), 'A document from the meeting was added: ' . $name . '. Open WDOS to download it.', false);
    after_response(fn() => push_drain(80)); after_response(fn() => mail_drain(15));
  }
  return ['ok' => true, 'files' => doc_list('smeeting', (int) $id), 'told' => $told];
});
route('POST', '/meetings/(\d+)/files/(\d+)/remove', function ($id, $fid) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  q("UPDATE doc_links SET removed_at=? WHERE file_id=? AND owner_type='smeeting' AND owner_id=?", [now(), (int) $fid, (int) $id]);
  return ['ok' => true, 'files' => doc_list('smeeting', (int) $id)];
});

/** v109.59: the words the host's browser read on the uploaded flier picture, with their places. They are translated next. */
route('POST', '/meetings/(\d+)/flier/words', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  $w = body()['words'] ?? null; $groups = [];
  foreach ((array) ($w['groups'] ?? []) as $g) {
    $t = mb_substr(trim((string) ($g['text'] ?? '')), 0, 400); if ($t === '' || !preg_match('/\p{L}{2}/u', $t)) continue;
    $lines = []; foreach ((array) ($g['lines'] ?? []) as $b) if (is_array($b) && count($b) >= 4) $lines[] = array_map(fn($v) => round((float) $v, 1), array_slice(array_values($b), 0, 9));
    if ($lines) $groups[] = ['text' => $t, 'lines' => array_slice($lines, 0, 12)] + (isset($g['style']) && is_array($g['style']) ? ['style' => array_slice($g['style'], 0, 6)] : []);
    if (count($groups) >= 60) break;
  }
  $data = ['w' => (int) ($w['w'] ?? 0), 'h' => (int) ($w['h'] ?? 0), 'groups' => $groups];
  q('UPDATE meetings SET flier_words=? WHERE id=?', [$groups ? json_encode($data, JSON_UNESCAPED_UNICODE) : null, (int) $id]);
  q("UPDATE meeting_flier_texts SET words_json=NULL WHERE meeting_id=? AND source<>'hq'", [(int) $id]);
  if ($groups) after_response(function () use ($id) { @set_time_limit(300); flier_words_translate((int) $id); });
  return ['ok' => true, 'groups' => count($groups), 'translating' => (bool) $groups];
});
/** HQ corrects the words of one language on the picture. */
route('POST', '/meetings/(\d+)/flier/words/([a-z]{2})', function ($id, $lang) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m);
  if (!in_array($lang, FLIER_LANGS, true)) fail(422, 'Choose one of the WDOS languages.', 'lang');
  $w = flier_words($m); if (!$w) fail(409, 'WDOS has not read the words on this flier yet.', 'no_words');
  $t = array_values((array) (body()['texts'] ?? [])); if (count($t) !== count($w['groups'])) fail(422, 'Give one text for each part of the flier.', 'texts');
  q("INSERT INTO meeting_flier_texts (meeting_id,lang,headline,body,source,updated_at,words_json) VALUES (?,?,'','','hq',?,?) ON DUPLICATE KEY UPDATE words_json=VALUES(words_json), source='hq', updated_at=VALUES(updated_at)",
    [(int) $id, $lang, now(), json_encode(array_map(fn($s) => mb_substr(trim((string) $s), 0, 600), $t), JSON_UNESCAPED_UNICODE)]);
  return ['ok' => true];
});
route('POST', '/meetings/(\d+)/flier/words-translate', function ($id) {
  $u = require_user(); $m = meeting_get((int) $id); meeting_host($u, $m); @set_time_limit(180);
  $r = flier_words_translate((int) $id, !empty(body()['redo']));
  if (($r['reason'] ?? '') === 'ai_off') fail(409, 'Automatic translation is not set up on this server. Type each language yourself, or ask the IT Lead to switch on an AI lane.', 'ai_off');
  return ['ok' => true] + $r;
});
