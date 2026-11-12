<?php
declare(strict_types=1);
/* Fingerprint / face / device-PIN sign-in (WebAuthn passkeys) in plain PHP.
 * The browser hands us the public key at enrolment (attestation "none"), and
 * every sign-in is a signature we verify with openssl. */

function wa_challenge(string $purpose, ?int $userId, string $data = ''): array {
  $id = bin2hex(random_bytes(16)); $ch = token(32);
  q('INSERT INTO auth_challenges (id,purpose,user_id,challenge,data,expires_at) VALUES (?,?,?,?,?,?)',
    [$id, $purpose, $userId, $ch, $data, gmdate('Y-m-d H:i:s', time() + 300)]);
  return [$id, $ch];
}
function wa_take(string $id, string $purpose): array {
  $c = row('SELECT * FROM auth_challenges WHERE id=? AND purpose=? AND expires_at>?', [$id, $purpose, now()]);
  if (!$c) fail(400, 'That request expired. Please try again.', 'challenge_expired');
  q('DELETE FROM auth_challenges WHERE id=?', [$id]);
  return $c;
}
/** Checks shared by enrolment and sign-in. Returns the raw authenticator data. */
function wa_check(string $type, string $challenge, string $clientDataB64, string $authDataB64): array {
  $clientRaw = b64u_dec($clientDataB64);
  $cd = json_decode($clientRaw, true);
  if (!is_array($cd) || ($cd['type'] ?? '') !== $type) fail(400, 'Unexpected fingerprint response.', 'wa_type');
  if (!hash_equals($challenge, (string) ($cd['challenge'] ?? ''))) fail(400, 'Fingerprint challenge did not match.', 'wa_challenge');
  if (rtrim((string) ($cd['origin'] ?? ''), '/') !== app_origin()) fail(400, 'Fingerprint sign-in was started on a different address.', 'wa_origin');
  $ad = b64u_dec($authDataB64);
  if (strlen($ad) < 37 || !hash_equals(hash('sha256', app_host(), true), substr($ad, 0, 32))) fail(400, 'Fingerprint response is for a different site.', 'wa_rp');
  $flags = ord($ad[32]);
  if (!($flags & 0x01) || !($flags & 0x04)) fail(400, 'Your device did not confirm it was you.', 'wa_uv');
  $count = unpack('N', substr($ad, 33, 4))[1];
  return [$ad, $clientRaw, $count];
}
function wa_register_options(array $u): array {
  [$cid, $ch] = wa_challenge('wa_register', (int) $u['id']);
  $existing = array_map(fn($r) => ['type' => 'public-key', 'id' => $r['credential_id']], rows('SELECT credential_id FROM webauthn_credentials WHERE user_id=?', [(int) $u['id']]));
  return ['challenge_id' => $cid, 'publicKey' => [
    'challenge' => $ch,
    'rp' => ['id' => app_host(), 'name' => 'WDOS'],
    'user' => ['id' => b64u('wdos-' . $u['id']), 'name' => $u['email'], 'displayName' => trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['email']],
    'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
    'authenticatorSelection' => ['authenticatorAttachment' => 'platform', 'residentKey' => 'preferred', 'userVerification' => 'required'],
    'attestation' => 'none', 'timeout' => 60000, 'excludeCredentials' => $existing,
  ]];
}
function wa_register_verify(array $u, array $in): array {
  $c = wa_take((string) ($in['challenge_id'] ?? ''), 'wa_register');
  if ((int) $c['user_id'] !== (int) $u['id']) fail(400, 'That request belongs to another account.', 'wa_user');
  wa_check('webauthn.create', $c['challenge'], (string) ($in['clientDataJSON'] ?? ''), (string) ($in['authenticatorData'] ?? ''));
  $credId = (string) ($in['id'] ?? ''); $alg = (int) ($in['alg'] ?? -7);
  $spki = b64u_dec((string) ($in['publicKey'] ?? ''));
  if ($credId === '' || strlen($credId) > 250 || strlen($spki) < 60 || !in_array($alg, [-7, -257], true)) fail(400, 'This device did not return a usable key.', 'wa_key');
  $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
  if (!openssl_pkey_get_public($pem)) fail(400, 'This device returned a key WDOS cannot read.', 'wa_key');
  $label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 80) ?: 'This device';
  try {
    q('INSERT INTO webauthn_credentials (user_id,credential_id,public_key,alg,sign_count,label,created_at) VALUES (?,?,?,?,?,?,?)', [(int) $u['id'], $credId, $pem, $alg, 0, $label, now()]);
  } catch (PDOException $e) { fail(409, 'This device is already set up for fingerprint sign-in.', 'wa_exists'); }
  return ['ok' => true];
}
function wa_login_options(string $emailHint): array {
  [$cid, $ch] = wa_challenge('wa_login', null);
  $allow = [];
  if ($emailHint !== '') {
    $allow = array_map(fn($r) => ['type' => 'public-key', 'id' => $r['credential_id']],
      rows('SELECT c.credential_id FROM webauthn_credentials c JOIN users u ON u.id=c.user_id WHERE u.email=?', [strtolower($emailHint)]));
  }
  return ['challenge_id' => $cid, 'publicKey' => ['challenge' => $ch, 'rpId' => app_host(), 'userVerification' => 'required', 'timeout' => 60000, 'allowCredentials' => $allow]];
}
/** Returns the user row on success. */
function wa_login_verify(array $in): array {
  $c = wa_take((string) ($in['challenge_id'] ?? ''), 'wa_login');
  $cred = row('SELECT * FROM webauthn_credentials WHERE credential_id=?', [(string) ($in['id'] ?? '')]);
  if (!$cred) fail(401, 'This fingerprint is not set up for any WDOS account. Sign in with your password, then switch it on in Settings.', 'wa_unknown');
  [$ad, $clientRaw, $count] = wa_check('webauthn.get', $c['challenge'], (string) ($in['clientDataJSON'] ?? ''), (string) ($in['authenticatorData'] ?? ''));
  $sig = b64u_dec((string) ($in['signature'] ?? ''));
  if (openssl_verify($ad . hash('sha256', $clientRaw, true), $sig, $cred['public_key'], OPENSSL_ALGO_SHA256) !== 1) fail(401, 'Fingerprint check failed.', 'wa_signature');
  if ($count > 0 && $count <= (int) $cred['sign_count']) fail(401, 'This fingerprint key looks copied. Sign in with your password.', 'wa_counter');
  q('UPDATE webauthn_credentials SET sign_count=?, last_used_at=? WHERE id=?', [$count, now(), (int) $cred['id']]);
  $u = row('SELECT * FROM users WHERE id=?', [(int) $cred['user_id']]);
  if (!$u || in_array($u['status'], ['suspended', 'withdrawn'], true)) fail(403, 'This account is suspended. Contact WODDI HQ.', 'suspended');
  return $u;
}
