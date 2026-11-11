<?php
declare(strict_types=1);
/* Web Push in plain PHP (openssl + curl only, nothing to install):
 * VAPID (RFC 8292) + aes128gcm payload encryption (RFC 8291). Works the same
 * on Render and on shared hosting. */

const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

function vapid_generate(): array {
  $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  $d = openssl_pkey_get_details($k)['ec'];
  $pad = fn(string $b) => str_pad($b, 32, "\0", STR_PAD_LEFT);
  return ['public' => b64u("\x04" . $pad($d['x']) . $pad($d['y'])), 'private' => b64u($pad($d['d']))];
}
function push_configured(): bool { return (bool) cfg('VAPID_PUBLIC') && (bool) cfg('VAPID_PRIVATE'); }

function p256_private_pem(string $d, string $pub65): string {
  $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $pub65;
  return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}
function p256_public_pem(string $pub65): string {
  return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(P256_SPKI_PREFIX) . $pub65), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
/** DER ECDSA signature -> raw r||s (64 bytes) as JWT ES256 needs. */
function der_to_raw(string $der): string {
  $o = 2; if (ord($der[1]) & 0x80) $o += ord($der[1]) & 0x7f;
  $o++; $rl = ord($der[$o++]); $r = substr($der, $o, $rl); $o += $rl;
  $o++; $sl = ord($der[$o++]); $s = substr($der, $o, $sl);
  $fix = fn(string $x) => str_pad(ltrim($x, "\0"), 32, "\0", STR_PAD_LEFT);
  return $fix($r) . $fix($s);
}
function vapid_header(string $endpoint): string {
  $pub = b64u_dec((string) cfg('VAPID_PUBLIC')); $prv = b64u_dec((string) cfg('VAPID_PRIVATE'));
  $p = parse_url($endpoint);
  $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
  $head = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
  $claims = b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => cfg('VAPID_SUBJECT', 'mailto:it@thewoddi.org')]));
  $sig = '';
  if (!openssl_sign("$head.$claims", $sig, p256_private_pem($prv, $pub), OPENSSL_ALGO_SHA256)) throw new RuntimeException('VAPID signing failed');
  return 'vapid t=' . "$head.$claims." . b64u(der_to_raw($sig)) . ', k=' . cfg('VAPID_PUBLIC');
}
/** Encrypt a payload for one subscription. Returns the request body. */
function push_encrypt(string $payload, string $p256dh, string $auth): string {
  $ua = b64u_dec($p256dh); $authSecret = b64u_dec($auth);
  if (strlen($ua) !== 65 || strlen($authSecret) < 16) throw new RuntimeException('Bad subscription keys');
  $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  $d = openssl_pkey_get_details($eph)['ec'];
  $pad = fn(string $b) => str_pad($b, 32, "\0", STR_PAD_LEFT);
  $as = "\x04" . $pad($d['x']) . $pad($d['y']);
  $secret = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($ua)), $eph, 32);
  if ($secret === false) throw new RuntimeException('ECDH failed');
  $prkKey = hash_hmac('sha256', $secret, $authSecret, true);
  $ikm = hash_hmac('sha256', "WebPush: info\0" . $ua . $as . "\x01", $prkKey, true);
  $salt = random_bytes(16);
  $prk = hash_hmac('sha256', $ikm, $salt, true);
  $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
  $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
  $tag = '';
  $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
  return $salt . pack('N', 4096) . chr(65) . $as . $ct . $tag;
}
/** Send one push. Returns [httpStatus, errorText]. */
function push_send(array $sub, string $payload): array {
  $bodyBin = push_encrypt($payload, $sub['p256dh'], $sub['auth']);
  $ch = curl_init($sub['endpoint']);
  curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $bodyBin, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: normal', 'Authorization: ' . vapid_header($sub['endpoint'])],
  ]);
  $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  return [$code, $code >= 200 && $code < 300 ? '' : mb_substr($err ?: (string) $res, 0, 180)];
}
/** Work through the outbox. Called after a reply, by the cron URL, and by HQ's browser after a broadcast. */
function push_drain(int $limit = 40): array {
  $sent = 0; $failed = 0; $gone = 0;
  if (!push_configured()) return ['sent' => 0, 'failed' => 0, 'gone' => 0, 'left' => 0, 'configured' => false];
  $jobs = rows("SELECT o.id, o.payload, o.attempts, s.id AS sid, s.endpoint, s.p256dh, s.auth
                FROM push_outbox o LEFT JOIN push_subscriptions s ON s.id=o.subscription_id
                WHERE o.status='queued' ORDER BY o.id LIMIT " . max(1, min(200, $limit)));
  foreach ($jobs as $j) {
    if (!$j['sid']) { q("UPDATE push_outbox SET status='gone' WHERE id=?", [(int) $j['id']]); $gone++; continue; }
    // claim the row first so two drains never send the same push twice
    if (q("UPDATE push_outbox SET status='sending', attempts=attempts+1 WHERE id=? AND status='queued'", [(int) $j['id']])->rowCount() === 0) continue;
    try { [$code, $err] = push_send($j, $j['payload']); } catch (Throwable $e) { $code = 0; $err = $e->getMessage(); }
    if ($code >= 200 && $code < 300) {
      q("UPDATE push_outbox SET status='sent', sent_at=? WHERE id=?", [now(), (int) $j['id']]);
      q('UPDATE push_subscriptions SET last_ok_at=?, fail_count=0 WHERE id=?', [now(), (int) $j['sid']]);
      $sent++;
    } elseif ($code === 404 || $code === 410) {   // the browser dropped this subscription
      q('DELETE FROM push_subscriptions WHERE id=?', [(int) $j['sid']]);
      q("UPDATE push_outbox SET status='gone', last_error=? WHERE id=?", ["HTTP $code", (int) $j['id']]);
      $gone++;
    } else {
      $retry = ((int) $j['attempts'] + 1) < 3;
      q('UPDATE push_outbox SET status=?, last_error=? WHERE id=?', [$retry ? 'queued' : 'failed', mb_substr("HTTP $code $err", 0, 180), (int) $j['id']]);
      q('UPDATE push_subscriptions SET fail_count=fail_count+1 WHERE id=?', [(int) $j['sid']]);
      error_log('[wdos push] not accepted by ' . parse_url($j['endpoint'], PHP_URL_HOST) . ": HTTP $code $err");
      $failed++;
    }
  }
  return ['sent' => $sent, 'failed' => $failed, 'gone' => $gone, 'left' => (int) val("SELECT COUNT(*) FROM push_outbox WHERE status='queued'"), 'configured' => true];
}

/** Which kind of device a push address belongs to, in words a member understands. */
function push_device(string $endpoint, string $ua = ''): string {
  $h = (string) parse_url($endpoint, PHP_URL_HOST);
  $os = str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') ? 'iPhone or iPad' : (str_contains($ua, 'Android') ? 'Android phone' : (str_contains($ua, 'Windows') ? 'Windows computer' : (str_contains($ua, 'Mac OS') ? 'Mac' : 'This device')));
  $via = str_contains($h, 'apple.com') ? 'Apple' : (str_contains($h, 'googleapis.com') || str_contains($h, 'google.com') ? 'Google' : (str_contains($h, 'mozilla') ? 'Firefox' : (str_contains($h, 'windows.com') || str_contains($h, 'microsoft') ? 'Microsoft' : $h)));
  return "$os (through $via)";
}
/** Send one test straight to every device of this person and say exactly what each push service answered. */
function push_test_user(int $userId, string $title, string $body): array {
  $out = [];
  foreach (rows('SELECT * FROM push_subscriptions WHERE user_id=? ORDER BY id', [$userId]) as $s) {
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => '#/notifications', 'kind' => 'reminder', 'id' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try { [$code, $err] = push_send($s, $payload); } catch (Throwable $e) { $code = 0; $err = $e->getMessage(); }
    $ok = $code >= 200 && $code < 300;
    if ($ok) q('UPDATE push_subscriptions SET last_ok_at=?, fail_count=0 WHERE id=?', [now(), (int) $s['id']]);
    elseif ($code === 404 || $code === 410) q('DELETE FROM push_subscriptions WHERE id=?', [(int) $s['id']]);
    if (!$ok) error_log('[wdos push] test not accepted by ' . parse_url($s['endpoint'], PHP_URL_HOST) . ": HTTP $code $err");
    $out[] = ['device' => push_device($s['endpoint'], (string) $s['user_agent']), 'ok' => $ok, 'code' => $code, 'gone' => $code === 404 || $code === 410, 'error' => $ok ? '' : mb_substr($err, 0, 160), 'since' => $s['created_at']];
  }
  return $out;
}
