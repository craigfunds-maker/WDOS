<?php
/* Web Push for the PWA, written against RFC 8291 (message encryption) and RFC 8292 (VAPID).
   No outside library: only PHP's openssl and curl extensions, which cPanel hosts provide. */
declare(strict_types=1);

function b64u(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

/* Creates a new VAPID key pair: ['public' => base64url(65 bytes), 'private' => base64url(32 bytes)]. */
function vapid_generate(): array {
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($k)['ec'];
    return ['public' => b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)), 'private' => b64u(str_pad($d['d'], 32, "\0", STR_PAD_LEFT))];
}
function ec_private_pem(string $d32, string $pub65): string {
    $der = "\x30\x77\x02\x01\x01\x04\x20" . $d32 . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00" . $pub65;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}
function ec_public_pem(string $pub65): string {
    $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $pub65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
/* DER ECDSA signature -> raw 64 byte r||s, as JWT ES256 needs. */
function der_to_raw(string $der): string {
    $o = 2; if (ord($der[1]) & 0x80) $o += ord($der[1]) & 0x7f;
    $rl = ord($der[$o + 1]); $r = substr($der, $o + 2, $rl); $o += 2 + $rl;
    $sl = ord($der[$o + 1]); $s = substr($der, $o + 2, $sl);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}
function vapid_jwt(string $endpoint, string $pubB64, string $privB64, string $subject): string {
    $p = parse_url($endpoint);
    $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $in = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $subject]));
    openssl_sign($in, $sig, ec_private_pem(b64u_dec($privB64), b64u_dec($pubB64)), OPENSSL_ALGO_SHA256);
    return $in . '.' . b64u(der_to_raw($sig));
}
/* Encrypts a payload for one subscription (aes128gcm). Returns the request body. */
function push_encrypt(string $payload, string $p256dhB64, string $authB64): string {
    $ua = b64u_dec($p256dhB64); $auth = b64u_dec($authB64);
    $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($local)['ec'];
    $as = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
    $shared = openssl_pkey_derive(openssl_pkey_get_public(ec_public_pem($ua)), $local, 32);
    if ($shared === false) throw new RuntimeException('Could not derive the push key.');
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $ua . $as, $auth);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $as . $ct . $tag;
}
/* Sends one push. Returns the HTTP status (201 is success; 404 and 410 mean the subscription is gone). */
function push_send(array $sub, array $message): int {
    $pub = (string)cfg('vapid.public'); $priv = (string)cfg('vapid.private');
    if (!$pub || !$priv || empty($sub['endpoint']) || empty($sub['p256dh']) || empty($sub['auth'])) return 0;
    $body = push_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
    $jwt = vapid_jwt($sub['endpoint'], $pub, $priv, (string)cfg('vapid.subject', 'mailto:woddi.org@gmail.com'));
    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: normal', 'Authorization: vapid t=' . $jwt . ', k=' . $pub]]);
    curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $code;
}
function push_to_user(string $uid, string $title, string $body = '', string $link = ''): int {
    if (!cfg('vapid.public')) return 0;
    $sent = 0;
    foreach (rows('SELECT * FROM push_subscriptions WHERE uid = ?', [$uid]) as $s) {
        $code = push_send($s, ['title' => $title, 'body' => $body, 'url' => $link ?: url(''), 'icon' => asset('icon-192.png')]);
        if ($code === 404 || $code === 410) q('DELETE FROM push_subscriptions WHERE id = ?', [$s['id']]);
        elseif ($code >= 200 && $code < 300) $sent++;
    }
    return $sent;
}
