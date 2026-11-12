<?php
declare(strict_types=1);
/* The spoken joining guide, recorded by a person. Step 0 is the opening line used in the video; steps 1 to 7 are the screens.
 * A recording made here is what Listen plays for that language, ahead of any synthetic voice. */
const GUIDE_LANGS = ['ar', 'en', 'fr', 'ha', 'ig', 'pt', 'sw', 'yo'];

route('GET', '/public/guide-audio', function () {
  $out = [];
  try { foreach (rows('SELECT lang, step, UNIX_TIMESTAMP(updated_at) v FROM guide_audio ORDER BY lang, step') as $r) $out[$r['lang']][(int) $r['step']] = (int) $r['v']; } catch (PDOException $e) { /* before migration 019 */ }
  return ['recorded' => (object) $out];
});
route('GET', '/public/guide-audio/([a-z]{2,3})/(\d)', function ($lang, $step) {
  $r = row('SELECT mime, data FROM guide_audio WHERE lang=? AND step=?', [$lang, (int) $step]);
  if (!$r) fail(404, 'No recording for this step yet.', 'not_found');
  header('Content-Type: ' . $r['mime']); header('Content-Length: ' . strlen($r['data'])); header('Cache-Control: public, max-age=31536000, immutable'); header('Accept-Ranges: none');
  echo $r['data']; exit;
});
route('GET', '/hq/guide-audio', function () {
  require_hq();
  return ['langs' => GUIDE_LANGS, 'items' => rows("SELECT g.lang, g.step, g.bytes, g.updated_at, UNIX_TIMESTAMP(g.updated_at) v, CONCAT(u.first_name,' ',u.last_name) recorded_by FROM guide_audio g LEFT JOIN users u ON u.id=g.recorded_by ORDER BY g.lang, g.step")];
});
route('POST', '/hq/guide-audio', function () {
  $me = require_hq(); $lang = str_in('lang', 5); $step = (int) (body()['step'] ?? -1);
  if (!in_array($lang, GUIDE_LANGS, true) || $step < 0 || $step > 7) fail(422, 'Choose the language and the step.', 'step');
  $mime = strtolower(explode(';', (string) (body()['mime'] ?? ''))[0]);
  if (!in_array($mime, ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/x-m4a', 'audio/m4a', 'video/webm', 'video/mp4'], true)) fail(422, 'That is not a sound recording WDOS can play.', 'mime');
  $bin = base64_decode((string) (body()['data'] ?? ''), true);
  if ($bin === false || strlen($bin) < 2000) fail(422, 'The recording is empty or too short.', 'data');
  if (strlen($bin) > 3 * 1024 * 1024) fail(422, 'The recording is larger than 3 MB. Record a shorter take.', 'size');
  if (str_starts_with($mime, 'video/')) $mime = 'audio/' . substr($mime, 6);             // phones label a voice note as video/webm or video/mp4
  q('DELETE FROM guide_audio WHERE lang=? AND step=?', [$lang, $step]);
  $st = db()->prepare('INSERT INTO guide_audio (lang,step,mime,data,bytes,recorded_by,updated_at) VALUES (?,?,?,?,?,?,?)');
  $st->bindValue(1, $lang); $st->bindValue(2, $step, PDO::PARAM_INT); $st->bindValue(3, $mime); $st->bindValue(4, $bin, PDO::PARAM_LOB); $st->bindValue(5, strlen($bin), PDO::PARAM_INT); $st->bindValue(6, (int) $me['id'], PDO::PARAM_INT); $st->bindValue(7, now()); $st->execute();
  log_activity((int) $me['id'], null, 'guide_voice_recorded', "$lang step $step");
  return ['ok' => true];
});
route('POST', '/hq/guide-audio/delete', function () {
  $me = require_hq(); $lang = str_in('lang', 5); $step = (int) (body()['step'] ?? -1);
  q('DELETE FROM guide_audio WHERE lang=? AND step=?', [$lang, $step]); log_activity((int) $me['id'], null, 'guide_voice_removed', "$lang step $step");
  return ['ok' => true];
});
