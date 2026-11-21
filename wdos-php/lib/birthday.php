<?php
declare(strict_types=1);
/* v109.44: a birthday card and a spoken greeting for every member (Azeez, 21 Nov 2026: "design a birthday greeting card with
 * their name, their picture ... not a generic writing alone ... show on their dashboard also be sent to their emails ...
 * personalize that the birthday greeting is coming from WODDI HQ, also a personalized voice (the clearest kokoro voice) greeting
 * them for birthday and mentioning their name. They should also get a push notification.")
 *
 *  - The card: a WODDI HQ design (assets/birthday/card-<lang>.jpg, one per language) with her profile photo in the circle and her
 *    name under it, put together here with GD, kept in birthday_media and sent as a picture in her email and on her phone.
 *  - The voice: Kokoro cannot run on shared hosting, so a GitHub Actions job (.github/workflows/birthday-voice.yml) speaks the
 *    greeting with Kokoro's af_heart voice (ff_siwis in French, pf_dora in Portuguese) a few days ahead and sends it back here.
 *    The job proves who it is with GitHub's own signed token (OIDC), so there is no password or key to keep anywhere.
 *  - Without a photo the circle shows her initials; without the voice yet, the card still goes out and the voice joins when it arrives. */

const BDAY_LANGS = ['en', 'fr', 'pt', 'sw', 'ar', 'ha', 'ig', 'yo'];
const BDAY_OIDC_ISS = 'https://token.actions.githubusercontent.com';
const BDAY_OIDC_AUD = 'woddicrm-birthday-voice';
const BDAY_REPO = 'craigfunds-maker/WDOS';

function bday_lang(array $u): string { $l = (string) ($u['lang'] ?? 'en'); return in_array($l, BDAY_LANGS, true) ? $l : 'en'; }

/** The words she hears. Kokoro speaks English, French and Portuguese clearly; the other languages hear the English greeting. */
function bday_voice_text(string $name, string $lang): array {
  $t = [
    'en' => ['af_heart', 'en-us', "Happy birthday, $name! This is WODDI HQ. Today, we celebrate you, and we thank God for your life. Thank you for all you give to your network, and to the women around you. May your new year be full of joy, good health, and every good thing. Happy birthday, $name. We are proud of you."],
    'fr' => ['ff_siwis', 'fr-fr', "Joyeux anniversaire, $name ! Ici le siège de WODDI. Aujourd'hui, nous vous célébrons, et nous rendons grâce à Dieu pour votre vie. Merci pour tout ce que vous donnez à votre réseau, et aux femmes autour de vous. Que votre nouvelle année soit pleine de joie, de santé, et de toutes les bonnes choses. Joyeux anniversaire, $name. Nous sommes fiers de vous."],
    'pt' => ['pf_dora', 'pt-br', "Feliz aniversário, $name! Aqui fala a sede da WODDI. Hoje, celebramos você, e damos graças a Deus pela sua vida. Obrigada por tudo o que dá à sua rede, e às mulheres à sua volta. Que o seu novo ano seja cheio de alegria, saúde, e todas as coisas boas. Feliz aniversário, $name. Temos orgulho de você."],
  ];
  return $t[$lang] ?? $t['en'];
}

/** Her row for this birthday, made the first time it is needed. */
function bday_row(int $uid, int $year): array {
  $r = row('SELECT user_id, year, token, lang, card_at, voice_at FROM birthday_media WHERE user_id=? AND year=?', [$uid, $year]);
  if ($r) return $r;
  $u = row('SELECT lang FROM users WHERE id=?', [$uid]) ?: [];
  q('INSERT IGNORE INTO birthday_media (user_id,year,token,lang,created_at) VALUES (?,?,?,?,?)', [$uid, $year, bin2hex(random_bytes(20)), bday_lang($u), now()]);
  return row('SELECT user_id, year, token, lang, card_at, voice_at FROM birthday_media WHERE user_id=? AND year=?', [$uid, $year]);
}

/** The card as a JPEG: the design for her language, her photo in the circle, her name under it. Kept once made. */
function bday_card(int $uid, int $year, bool $fresh = false): ?string {
  if (!$fresh && ($b = val('SELECT card FROM birthday_media WHERE user_id=? AND year=? AND card IS NOT NULL', [$uid, $year]))) return (string) $b;
  if (!function_exists('imagecreatefromjpeg')) return null;
  $u = row('SELECT id, first_name, last_name, lang FROM users WHERE id=?', [$uid]); if (!$u) return null;
  $r = bday_row($uid, $year);
  $tpl = __DIR__ . '/../assets/birthday/card-' . $r['lang'] . '.jpg'; if (!is_file($tpl)) $tpl = __DIR__ . '/../assets/birthday/card-en.jpg';
  $im = imagecreatefromjpeg($tpl); if (!$im) return null;
  imagealphablending($im, true);
  $cx = 540; $cy = 600; $R = 186; $D = $R * 2;
  $p = row('SELECT bytes FROM user_photos WHERE user_id=?', [$uid]);
  $ph = $p ? @imagecreatefromstring((string) $p['bytes']) : false;
  if ($ph) {
    // her photo, cropped square from the centre, then cut to a circle with a soft edge
    $w = imagesx($ph); $h = imagesy($ph); $s = min($w, $h);
    $sq = imagecreatetruecolor($D, $D); imagealphablending($sq, false); imagesavealpha($sq, true);
    imagecopyresampled($sq, $ph, 0, 0, (int) (($w - $s) / 2), (int) (($h - $s) / 2), $D, $D, $s, $s);
    for ($y = 0; $y < $D; $y++) for ($x = 0; $x < $D; $x++) {
      $d = sqrt(($x - $R + .5) ** 2 + ($y - $R + .5) ** 2);
      if ($d > $R) { imagesetpixel($sq, $x, $y, imagecolorallocatealpha($sq, 0, 0, 0, 127)); continue; }
      if ($d > $R - 1.5) { $c = imagecolorat($sq, $x, $y); $a = (int) min(127, max(0, ($d - ($R - 1.5)) / 1.5 * 127));
        imagesetpixel($sq, $x, $y, imagecolorallocatealpha($sq, ($c >> 16) & 255, ($c >> 8) & 255, $c & 255, $a)); }
    }
    imagealphablending($im, true); imagecopy($im, $sq, $cx - $R, $cy - $R, 0, 0, $D, $D);
  } else {
    // no photo yet: her initials, large, in WODDI magenta on the pale circle
    $ini = mb_strtoupper(mb_substr(bday_plain($u['first_name']), 0, 1) . mb_substr((string) $u['last_name'], 0, 1));
    bday_text_centered($im, $ini, 140, $cy + 50, imagecolorallocate($im, 212, 0, 106), 380);
  }
  // her name, white, as large as fits on one line
  $name = trim((string) $u['first_name']) !== '' ? trim((string) $u['first_name'] . ' ' . (string) $u['last_name']) : 'Dear sister';
  bday_text_centered($im, $name, 58, 884, imagecolorallocate($im, 255, 255, 255), 940, imagecolorallocatealpha($im, 100, 0, 50, 90));
  // her designation under it on a lemon green ribbon (Azeez: "the card must carry their name, their designation, their profile picture")
  $des = bday_designation($uid);
  if ($des !== '') {
    $font = __DIR__ . '/../assets/fonts/ttf/DejaVuSans-Bold.ttf'; $fs = 24;
    for (; $fs > 14; $fs--) { $b = imagettfbbox($fs, 0, $font, $des); if (($b[2] - $b[0]) <= 860) break; }
    $b = imagettfbbox($fs, 0, $font, $des); $tw = $b[2] - $b[0]; $pw = $tw + 56; $x0 = (int) (540 - $pw / 2); $y0 = 906; $ph = 46; $r = 23;
    $lime = imagecolorallocate($im, 124, 181, 24);
    imagefilledrectangle($im, $x0 + $r, $y0, $x0 + $pw - $r, $y0 + $ph, $lime);
    imagefilledellipse($im, $x0 + $r, $y0 + $r, $ph, $ph, $lime); imagefilledellipse($im, $x0 + $pw - $r, $y0 + $r, $ph, $ph, $lime);
    imagettftext($im, $fs, 0, (int) (540 - $tw / 2 - $b[0]), $y0 + (int) (($ph + $fs) / 2) + 1, imagecolorallocate($im, 19, 33, 10), $font, $des);
  }
  ob_start(); imagejpeg($im, null, 88); $jpg = (string) ob_get_clean();
  q('UPDATE birthday_media SET card=?, card_at=? WHERE user_id=? AND year=?', [$jpg, now(), $uid, $year]);
  return $jpg;
}
/** What she is in WODDI, as her dashboard says it: her seat, her envoy title, or her network membership. */
function bday_designation(int $uid): string {
  $u = row('SELECT * FROM users WHERE id=?', [$uid]); if (!$u) return '';
  if (trim((string) ($u['designation'] ?? '')) !== '' && ($u['role'] ?? '') === 'envoy') return trim((string) $u['designation']);
  try { $seat = function_exists('current_seat') ? current_seat($uid) : null; if ($seat) return seat_title($seat); } catch (Throwable $e) { /* no seat */ }
  if (trim((string) ($u['designation'] ?? '')) !== '') return trim((string) $u['designation']);
  $net = strtoupper((string) ($u['network'] ?? '')); return $net ? "Member, $net" : 'Member of WODDI';
}
/** The name without a title such as Mrs., Madam or Dr., for initials. */
function bday_plain(string $first): string { return trim((string) preg_replace('/^(mrs?|ms|miss|madam|dr|prof|rev|hon|pastor|deaconess|lady|chief|princess|alhaja|engr|barr)\.?\s+/i', '', trim($first))); }
/** Centred text that shrinks until it fits. Fraunces for the Latin alphabet, DejaVu for letters Fraunces does not have. */
function bday_text_centered($im, string $text, int $size, int $baseline, int $color, int $maxW, ?int $shadow = null, bool $sans = false): void {
  $font = $sans ? __DIR__ . '/../assets/fonts/ttf/DejaVuSans-Bold.ttf' : (preg_match('/[^\x{0000}-\x{024F}]|[\x{0100}-\x{024F}]/u', $text) ? __DIR__ . '/../assets/fonts/ttf/DejaVuSans-Bold.ttf' : __DIR__ . '/../assets/fonts/ttf/Fraunces-Bold.ttf');
  for (; $size > 20; $size -= 2) { $b = imagettfbbox($size, 0, $font, $text); if (($b[2] - $b[0]) <= $maxW) break; }
  $b = imagettfbbox($size, 0, $font, $text); $x = (int) (540 - ($b[2] - $b[0]) / 2 - $b[0]);
  if ($shadow !== null) imagettftext($im, $size, 0, $x + 2, $baseline + 4, $shadow, $font, $text);
  imagettftext($im, $size, 0, $x, $baseline, $color, $font, $text);
}

function bday_card_url(array $r): string { return app_origin() . '/api/birthday/' . $r['token'] . '/card.jpg'; }
function bday_voice_url(array $r): string { return app_origin() . '/api/birthday/' . $r['token'] . '/voice.mp3'; }

/** Is today her birthday (WODDI HQ's day, Lagos)? Returns the year of this birthday, or 0. */
function bday_today(array $u): int {
  $d = hq_today(); return (!empty($u['dob']) && substr((string) $u['dob'], 5) === $d->format('m-d')) ? (int) $d->format('Y') : 0;
}

/* ---- her card and voice, without signing in (the token in her email) ------ */
if (isset($METHOD, $PATH) && $METHOD === 'GET' && preg_match('#^/birthday/([a-f0-9]{40})/(card\.jpg|voice\.mp3)$#', $PATH, $m)) {
  $r = row('SELECT user_id, year FROM birthday_media WHERE token=?', [$m[1]]);
  if (!$r) { http_response_code(404); exit; }
  if ($m[2] === 'card.jpg') { $b = bday_card((int) $r['user_id'], (int) $r['year']); $mime = 'image/jpeg'; }
  else { $b = val('SELECT voice FROM birthday_media WHERE token=? AND voice IS NOT NULL', [$m[1]]); $mime = 'audio/mpeg'; }
  if (!$b) { http_response_code(404); exit; }
  header('Content-Type: ' . $mime); header('Cache-Control: private, max-age=86400'); header('Content-Length: ' . strlen((string) $b)); echo $b; exit;
}

/* ---- her dashboard: is there a birthday card today? ----------------------- */
route('GET', '/me/birthday-card', function () {
  $u = require_user(); $year = bday_today($u);
  if (!$year) return ['today' => false];
  $r = bday_row((int) $u['id'], $year);
  if (!$r['card_at']) bday_card((int) $u['id'], $year);
  $r = bday_row((int) $u['id'], $year);
  return ['today' => true, 'name' => $u['first_name'], 'card' => $r['card_at'] ? bday_card_url($r) : null, 'voice' => $r['voice_at'] ? bday_voice_url($r) : null];
});
/* she changed her photo on her birthday: make the card again with the new one */
function bday_photo_changed(int $uid): void {
  try { $u = row('SELECT id, dob FROM users WHERE id=?', [$uid]); if ($u && ($y = bday_today($u))) { bday_row($uid, $y); bday_card($uid, $y, true); } } catch (Throwable $e) { /* never blocks the upload */ }
}

/* ---- the greeting itself, called once on the day by run_automations -------- */
function bday_greet(array $u): array {
  $uid = (int) $u['id']; $year = (int) hq_today()->format('Y');
  $r = bday_row($uid, $year); bday_card($uid, $year); $r = bday_row($uid, $year);
  $card = $r['card_at'] ? bday_card_url($r) : null;
  notify($uid, 'birthday', 'Happy birthday, ' . $u['first_name'], 'Everyone at WODDI HQ is celebrating you today. Thank you for all you give to your network.', '#/birthday', $card ? ['image' => $card] : []);
  $mail = 0;
  if (str_contains((string) $u['email'], '@') && !preg_match('/\.(invalid|test|local|example)$/i', (string) $u['email'])) {
    $lang = bday_lang($u);
    [$subj, $intro, $btn] = bday_mail_words($lang, (string) $u['first_name']);
    $html = mail_wrap($subj, '<p style="font-size:16px">' . htmlspecialchars($intro) . '</p>'
      . ($card ? '<p style="text-align:center;margin:18px 0"><a href="' . htmlspecialchars(app_origin() . '/#/birthday') . '"><img src="' . htmlspecialchars($card) . '" width="480" alt="' . htmlspecialchars($subj) . '" style="width:100%;max-width:480px;height:auto;border:0;border-radius:14px"></a></p>' : ''),
      $btn, app_origin() . '/#/birthday');
    mail_queue((string) $u['email'], (string) $u['first_name'], $subj, $html); $mail = 1;
  }
  return ['card' => (bool) $card, 'mail' => $mail];
}
function bday_mail_words(string $lang, string $name): array {
  $w = [
    'en' => ["Happy birthday, $name", 'Everyone at WODDI HQ is celebrating you today. Here is your birthday card from all of us. Open WDOS to hear the birthday message we recorded for you.', 'Open my card and hear my message'],
    'fr' => ["Joyeux anniversaire, $name", "Tout le siège de WODDI vous célèbre aujourd'hui. Voici votre carte d'anniversaire de notre part à tous. Ouvrez WDOS pour écouter le message d'anniversaire que nous avons enregistré pour vous.", 'Ouvrir ma carte et écouter mon message'],
    'pt' => ["Feliz aniversário, $name", 'Toda a sede da WODDI celebra você hoje. Aqui está o seu cartão de aniversário de todos nós. Abra o WDOS para ouvir a mensagem de aniversário que gravámos para você.', 'Abrir o meu cartão e ouvir a mensagem'],
    'sw' => ["Heri ya siku ya kuzaliwa, $name", 'Makao Makuu yote ya WODDI yanakusherehekea leo. Hii hapa kadi yako ya siku ya kuzaliwa kutoka kwetu sote. Fungua WDOS usikilize ujumbe tuliokurekodia.', 'Fungua kadi yangu na usikilize ujumbe'],
    'ar' => ["عيد ميلاد سعيد يا $name", 'يحتفل بكِ اليوم كل من في مقر WODDI. هذه بطاقة عيد ميلادك منا جميعاً. افتحي WDOS لتستمعي إلى رسالة عيد الميلاد التي سجلناها لكِ.', 'افتحي بطاقتي واستمعي إلى رسالتي'],
    'ha' => ["Barka da zagayowar ranar haihuwa, $name", 'Dukkan hedkwatar WODDI suna murnar ki a yau. Ga katin ranar haihuwarki daga gare mu duka. Buɗe WDOS don sauraron saƙon da muka naɗa miki.', 'Buɗe katina in saurari saƙona'],
    'ig' => ["Ụbọchị ọmụmụ ọma, $name", 'Isi ụlọ ọrụ WODDI niile na-eme gị ememe taa. Lee kaadị ụbọchị ọmụmụ gị site n’aka anyị niile. Mepee WDOS ka ị nụ ozi anyị dekọọrọ gị.', 'Mepee kaadị m ma nụ ozi m'],
    'yo' => ["Ẹ ku ọjọ ìbí, $name", 'Gbogbo olú-ilé-iṣẹ́ WODDI ń ṣe ayẹyẹ rẹ lónìí. Káàdì ọjọ́ ìbí rẹ láti ọ̀dọ̀ gbogbo wa nìyí. Ṣí WDOS kí o gbọ́ ọ̀rọ̀ tí a gbà sílẹ̀ fún ọ.', 'Ṣí káàdì mi kí n gbọ́ ọ̀rọ̀ mi'],
  ];
  return $w[$lang] ?? $w['en'];
}

/* ---- the Kokoro voice, spoken by the GitHub Actions job -------------------- */
/** GitHub's signed token for the birthday-voice workflow of this repository, on its main branch. No key is stored anywhere. */
function bday_oidc_ok(): bool {
  // the job sends GitHub's token in its own header: shared hosting often drops the Authorization header before PHP sees it
  $hdr = isset($_SERVER['HTTP_X_GITHUB_OIDC']) ? 'Bearer ' . trim((string) $_SERVER['HTTP_X_GITHUB_OIDC']) : ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
  if (!preg_match('/^Bearer\s+([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $hdr, $m)) return false;
  $dec = fn($s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
  $h = json_decode((string) $dec($m[1]), true); $c = json_decode((string) $dec($m[2]), true);
  if (!is_array($h) || !is_array($c) || ($h['alg'] ?? '') !== 'RS256') return false;
  $now = time();
  if (($c['iss'] ?? '') !== BDAY_OIDC_ISS || ($c['aud'] ?? '') !== BDAY_OIDC_AUD || ($c['repository'] ?? '') !== BDAY_REPO) return false;
  if (($c['exp'] ?? 0) < $now || ($c['nbf'] ?? $now) > $now + 60) return false;
  if (!str_starts_with((string) ($c['job_workflow_ref'] ?? ''), BDAY_REPO . '/.github/workflows/birthday-voice.yml@refs/heads/main')) return false;
  $pem = bday_jwk_pem((string) ($h['kid'] ?? '')); if (!$pem) return false;
  return openssl_verify($m[1] . '.' . $m[2], (string) $dec($m[3]), $pem, OPENSSL_ALGO_SHA256) === 1;
}
/** GitHub's public signing key with this id, fetched from GitHub and kept for six hours. */
function bday_jwk_pem(string $kid): ?string {
  $cache = setting('gh_oidc_jwks'); $at = (int) setting('gh_oidc_jwks_at');
  $keys = json_decode($cache, true);
  $find = function ($keys) use ($kid) { foreach (($keys['keys'] ?? []) as $k) if (($k['kid'] ?? '') === $kid && ($k['kty'] ?? '') === 'RSA') return $k; return null; };
  $k = is_array($keys) && time() - $at < 21600 ? $find($keys) : null;
  if (!$k) {
    $ch = curl_init(BDAY_OIDC_ISS . '/.well-known/jwks'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = (string) curl_exec($ch); curl_close($ch);
    $keys = json_decode($body, true); if (!is_array($keys)) return null;
    q('INSERT INTO app_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', ['gh_oidc_jwks', $body]);
    q('INSERT INTO app_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', ['gh_oidc_jwks_at', (string) time()]);
    $k = $find($keys); if (!$k) return null;
  }
  return bday_rsa_pem((string) $k['n'], (string) $k['e']);
}
function bday_rsa_pem(string $n, string $e): string {
  $b = fn($s) => base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
  $len = function (int $l): string { if ($l < 128) return chr($l); $o = ''; while ($l > 0) { $o = chr($l & 255) . $o; $l >>= 8; } return chr(0x80 | strlen($o)) . $o; };
  $int = function (string $x) use ($len): string { if (ord($x[0]) > 127) $x = "\0" . $x; return "\x02" . $len(strlen($x)) . $x; };
  $seq = fn(string $x) => "\x30" . $len(strlen($x)) . $x;
  $rsa = $seq($int($b($n)) . $int($b($e)));
  $spki = $seq($seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00") . "\x03" . $len(strlen($rsa) + 1) . "\0" . $rsa);
  return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
/** Birthdays from today to three days ahead that still need their voice. */
function bday_voice_due(): array {
  $d = hq_today(); $out = [];
  for ($i = 0; $i <= 3; $i++) {
    $day = $d->modify("+$i days"); $year = (int) $day->format('Y');
    foreach (rows("SELECT u.id, u.first_name, u.lang FROM users u LEFT JOIN birthday_media b ON b.user_id=u.id AND b.year=?
                   WHERE u.status='active' AND u.dob IS NOT NULL AND DATE_FORMAT(u.dob,'%m-%d')=? AND (b.voice_at IS NULL) LIMIT 300", [$year, $day->format('m-d')]) as $u) {
      $lang = bday_lang($u); [$voice, $code, $text] = bday_voice_text(trim((string) $u['first_name']) ?: 'dear sister', $lang);
      $out[] = ['id' => (int) $u['id'], 'year' => $year, 'voice' => $voice, 'lang' => $code, 'text' => $text];
    }
  }
  return $out;
}
route('GET', '/voice/birthdays', function () {
  if (!bday_oidc_ok()) fail(401, 'Only the WODDI birthday voice job may ask for this.', 'oidc');
  return ['items' => bday_voice_due()];
});
route('POST', '/voice/birthdays/(\d+)/(\d{4})', function ($uid, $year) {
  if (!bday_oidc_ok()) fail(401, 'Only the WODDI birthday voice job may send this.', 'oidc');
  $uid = (int) $uid; $year = (int) $year;
  $due = array_filter(bday_voice_due(), fn($x) => $x['id'] === $uid && $x['year'] === $year);
  if (!$due) fail(409, 'That birthday is not waiting for a voice.', 'not_due');
  $bin = (string) file_get_contents('php://input');
  if (strlen($bin) < 2000 || strlen($bin) > 1500000 || !(str_starts_with($bin, 'ID3') || (ord($bin[0]) === 0xFF && (ord($bin[1]) & 0xE0) === 0xE0))) fail(422, 'Send the greeting as an MP3 under 1.5 MB.', 'mp3');
  bday_row($uid, $year);
  q('UPDATE birthday_media SET voice=?, voice_at=? WHERE user_id=? AND year=?', [$bin, now(), $uid, $year]);
  return ['ok' => true];
});
