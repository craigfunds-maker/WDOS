<?php
declare(strict_types=1);
/* Today's card for the signed-in leader. There is no menu entry or library page: a message appears on the
 * dashboard at its time and arrives as a notification. */
route('GET', '/inspiration/today', fn() => ['card' => insp_today(require_user(), preg_replace('/[^a-z]/', '', strtolower((string) ($_GET['lang'] ?? 'en'))) ?: 'en')]);

/* ---- photographs and texts: HQ library --------------------------------------- */
function insp_keys(): array {
  $k = array_column(rows('SELECT image FROM hq_messages'), 'image');
  foreach (INSP_TONES as $t) $k[] = "messages/$t.webp";
  return $k;
}
/** Everything HQ needs to review: each message with its date, its write-up and its photograph. */
route('GET', '/hq/inspiration', function () {
  require_hq(); $tz = new DateTimeZone('Africa/Lagos');
  $photos = []; foreach (rows('SELECT image_key, pexels_id, photographer, page_url, alt, updated_at FROM message_photos') as $p) $photos[$p['image_key']] = $p;
  $wk = new DateTimeImmutable(setting('weekly_start', '2026-09-14'), $tz); [$sy, $sm] = array_map('intval', explode('-', setting('monthly_start', '2026-09')));
  $out = []; $langs = []; try { foreach (rows('SELECT message_id, lang FROM hq_message_texts ORDER BY lang') as $x) $langs[(int) $x['message_id']][] = $x['lang']; } catch (PDOException $e) { }
  foreach (rows("SELECT * FROM hq_messages ORDER BY kind DESC, seq, network") as $m) {
    $when = $m['kind'] === 'weekly' ? $wk->modify('+' . (((int) $m['seq'] - 1) * 7) . ' days') : first_wednesday((new DateTimeImmutable(sprintf('%04d-%02d-01', $sy, $sm), $tz))->modify('+' . ((int) $m['seq'] - 1) . ' months'));
    $p = $photos[$m['image']] ?? null;
    $c = insp_shape($m, ['first_name' => ''], '', null, 'en');
    $out[] = ['id' => (int) $m['id'], 'kind' => $m['kind'], 'seq' => (int) $m['seq'], 'network' => $m['network'], 'theme' => $m['theme'], 'headline' => $c['headline'], 'key_thought' => $c['key_thought'], 'body' => $c['body'], 'reflection' => $c['reflection'],
      'langs' => $langs[(int) $m['id']] ?? [], 'date' => $when->format('Y-m-d'), 'key' => $m['image'], 'image' => insp_photo($m['image']), 'credit' => $p ? ['photographer' => $p['photographer'], 'page_url' => $p['page_url'], 'alt' => $p['alt']] : null];
  }
  $tones = array_map(fn($t) => ['tone' => $t, 'key' => "messages/$t.webp", 'image' => insp_photo("messages/$t.webp"), 'credit' => isset($photos["messages/$t.webp"]) ? ['photographer' => $photos["messages/$t.webp"]['photographer'], 'page_url' => $photos["messages/$t.webp"]['page_url']] : null], INSP_TONES);
  return ['messages' => $out, 'tones' => $tones, 'today' => hq_today()->format('Y-m-d'), 'with_photo' => count($photos), 'with_text' => count(array_filter($out, fn($m) => trim($m['body']) !== ''))];
});
/** HQ names a Pexels photograph for a message; the server fetches it from Pexels, cropped wide for the card, and keeps it. */
route('POST', '/hq/inspiration/photos', function () {
  $me = require_hq(); @set_time_limit(120); $keys = array_flip(insp_keys()); $res = [];
  foreach (array_slice(is_array(body()['items'] ?? null) ? body()['items'] : [], 0, 10) as $it) {
    $key = (string) ($it['key'] ?? ''); $src = (string) ($it['src'] ?? ''); $r = ['key' => $key, 'ok' => false];
    $u = parse_url($src);
    if (!isset($keys[$key])) $r['error'] = 'unknown message';
    elseif (($u['scheme'] ?? '') !== 'https' || ($u['host'] ?? '') !== 'images.pexels.com' || !preg_match('#^/photos/\d+/[A-Za-z0-9._-]+\.(jpe?g|png)$#i', (string) ($u['path'] ?? ''))) $r['error'] = 'not a Pexels photograph address';
    else {
      preg_match('#^/photos/(\d+)/#', (string) $u['path'], $pm); $e = insp_photo_fetch($key, (int) $pm[1], (string) ($it['photographer'] ?? ''), (string) ($it['alt'] ?? ''));
      $r = $e === '' ? ['key' => $key, 'ok' => true] : ['key' => $key, 'ok' => false, 'error' => $e];
    }
    $res[] = $r;
  }
  log_activity((int) $me['id'], null, 'inspiration_photos', count(array_filter($res, fn($x) => $x['ok'])) . ' loaded');
  return ['ok' => true, 'results' => $res, 'total' => (int) val('SELECT COUNT(*) FROM message_photos')];
});
/** The write-up that goes with a message. Matched by series, number and network. Only HQ; an empty text is ignored. */
route('POST', '/hq/inspiration/texts', function () {
  $me = require_hq(); $n = 0; $miss = [];
  foreach (array_slice(is_array(body()['items'] ?? null) ? body()['items'] : [], 0, 200) as $it) {
    $body = trim((string) ($it['body'] ?? '')); if ($body === '') continue;
    $kind = ($it['kind'] ?? '') === 'monthly' ? 'monthly' : 'weekly'; $seq = (int) ($it['seq'] ?? 0); $net = strtoupper((string) ($it['network'] ?? 'ALL'));
    $hit = q('UPDATE hq_messages SET body=?' . (trim((string) ($it['key_thought'] ?? '')) !== '' ? ', key_thought=' . db()->quote(mb_substr(trim((string) $it['key_thought']), 0, 400)) : '') . ' WHERE kind=? AND seq=? AND network=?', [$body, $kind, $seq, $kind === 'monthly' ? 'ALL' : $net])->rowCount();
    if ($hit || val('SELECT 1 FROM hq_messages WHERE kind=? AND seq=? AND network=?', [$kind, $seq, $kind === 'monthly' ? 'ALL' : $net])) $n++; else $miss[] = "$kind $seq $net";
  }
  log_activity((int) $me['id'], null, 'inspiration_texts', "$n loaded");
  return ['ok' => true, 'loaded' => $n, 'not_found' => $miss];
});
/** Programme content from the current WDOS (same loader as setup section 8), for an HQ administrator who is signed in. */
route('POST', '/hq/content-import', function () {
  $me = require_hq(); require_once __DIR__ . '/content_import.php'; @set_time_limit(120);
  $rep = content_import(is_array(body()['tables'] ?? null) ? body()['tables'] : []);
  log_activity((int) $me['id'], null, 'content_import', count($rep) . ' tables');
  return ['ok' => true, 'report' => $rep];
});
