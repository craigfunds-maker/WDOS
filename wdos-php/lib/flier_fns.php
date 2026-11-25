<?php
declare(strict_types=1);
/* v109.56: meeting fliers (Azeez, 25 Nov 2026). HQ or the host uploads a flier picture, or WDOS makes one from the meeting's
 * details and the words she writes. The flier's words are translated into each language a leader can choose in WDOS, so
 * every leader reads it in her own language on her dashboard and in her emails. A countdown goes with it, and the email
 * carries one-tap replies (yes, maybe, no) so HQ knows how many will attend.
 * Translations come from the AI lanes when one is set up. They are working translations; HQ can correct any of them. */

const FLIER_LANGS = ['fr', 'pt', 'sw', 'ar', 'ha', 'yo', 'ig'];
const FLIER_LANG_NAMES = ['en' => 'English', 'fr' => 'French', 'pt' => 'Portuguese', 'sw' => 'Swahili', 'ar' => 'Arabic', 'ha' => 'Hausa', 'yo' => 'Yoruba', 'ig' => 'Igbo'];

/** The fixed words around a flier in an email or on the reply page, in each language. Working translations. */
const FLIER_WORDS = [
  'en' => ['days' => '{1} days to go', 'day' => '1 day to go', 'hours' => '{1} hours to go', 'soon' => 'Starting soon', 'now' => 'Happening now', 'ask' => 'Will you attend?', 'yes' => 'Yes, I will attend', 'maybe' => 'Maybe', 'no' => 'No, I cannot attend', 'thanks' => 'Thank you. Your reply is saved.', 'when' => 'When', 'closed' => 'This meeting is no longer open for replies.', 'bad' => 'This link is not valid. Open WDOS to reply.', 'change' => 'You can change your reply here at any time.'],
  'fr' => ['days' => 'J-{1}', 'day' => 'J-1, c\'est demain', 'hours' => 'Dans {1} heures', 'soon' => 'Commence bientôt', 'now' => 'En cours', 'ask' => 'Serez-vous présente ?', 'yes' => 'Oui, je serai présente', 'maybe' => 'Peut-être', 'no' => 'Non, je ne pourrai pas', 'thanks' => 'Merci. Votre réponse est enregistrée.', 'when' => 'Quand', 'closed' => 'Cette réunion n\'accepte plus de réponses.', 'bad' => 'Ce lien n\'est pas valide. Ouvrez WDOS pour répondre.', 'change' => 'Vous pouvez changer votre réponse ici à tout moment.'],
  'pt' => ['days' => 'Faltam {1} dias', 'day' => 'Falta 1 dia', 'hours' => 'Faltam {1} horas', 'soon' => 'Começa em breve', 'now' => 'A decorrer agora', 'ask' => 'Vai participar?', 'yes' => 'Sim, vou participar', 'maybe' => 'Talvez', 'no' => 'Não posso participar', 'thanks' => 'Obrigada. A sua resposta foi guardada.', 'when' => 'Quando', 'closed' => 'Esta reunião já não aceita respostas.', 'bad' => 'Esta ligação não é válida. Abra o WDOS para responder.', 'change' => 'Pode mudar a sua resposta aqui a qualquer momento.'],
  'sw' => ['days' => 'Zimebaki siku {1}', 'day' => 'Imebaki siku 1', 'hours' => 'Zimebaki saa {1}', 'soon' => 'Inaanza hivi karibuni', 'now' => 'Inaendelea sasa', 'ask' => 'Utahudhuria?', 'yes' => 'Ndiyo, nitahudhuria', 'maybe' => 'Huenda', 'no' => 'Hapana, sitaweza', 'thanks' => 'Asante. Jibu lako limehifadhiwa.', 'when' => 'Lini', 'closed' => 'Mkutano huu haupokei majibu tena.', 'bad' => 'Kiungo hiki si sahihi. Fungua WDOS ili ujibu.', 'change' => 'Unaweza kubadilisha jibu lako hapa wakati wowote.'],
  'ar' => ['days' => 'باقي {1} أيام', 'day' => 'باقي يوم واحد', 'hours' => 'باقي {1} ساعات', 'soon' => 'يبدأ قريبًا', 'now' => 'يجري الآن', 'ask' => 'هل ستحضرين؟', 'yes' => 'نعم، سأحضر', 'maybe' => 'ربما', 'no' => 'لا، لا أستطيع', 'thanks' => 'شكرًا. تم حفظ ردك.', 'when' => 'الموعد', 'closed' => 'هذا الاجتماع لم يعد يقبل الردود.', 'bad' => 'هذا الرابط غير صالح. افتحي WDOS للرد.', 'change' => 'يمكنك تغيير ردك هنا في أي وقت.'],
  'ha' => ['days' => 'Saura kwana {1}', 'day' => 'Saura kwana 1', 'hours' => 'Saura awa {1}', 'soon' => 'Zai fara nan ba da jimawa ba', 'now' => 'Ana yi yanzu', 'ask' => 'Za ki halarta?', 'yes' => 'Eh, zan halarta', 'maybe' => 'Wataƙila', 'no' => 'A\'a, ba zan iya ba', 'thanks' => 'Na gode. An adana amsarki.', 'when' => 'Yaushe', 'closed' => 'Wannan taron ba ya karɓar amsa kuma.', 'bad' => 'Wannan mahaɗin ba daidai ba ne. Buɗe WDOS ki amsa.', 'change' => 'Za ki iya canza amsarki a nan a kowane lokaci.'],
  'yo' => ['days' => 'Ọjọ́ {1} ló kù', 'day' => 'Ọjọ́ kan ló kù', 'hours' => 'Wákàtí {1} ló kù', 'soon' => 'Yóò bẹ̀rẹ̀ láìpẹ́', 'now' => 'Ó ń lọ lọ́wọ́', 'ask' => 'Ṣé o máa wá?', 'yes' => 'Bẹ́ẹ̀ ni, màá wá', 'maybe' => 'Bóyá', 'no' => 'Rárá, mi ò ní lè wá', 'thanks' => 'Ẹ ṣé. A ti fi èsì rẹ pamọ́.', 'when' => 'Ìgbà wo', 'closed' => 'Ìpàdé yìí kò gba èsì mọ́.', 'bad' => 'Ìjápọ̀ yìí kò tọ̀nà. Ṣí WDOS láti fèsì.', 'change' => 'O lè yí èsì rẹ padà níbí nígbàkígbà.'],
  'ig' => ['days' => 'Ụbọchị {1} fọdụrụ', 'day' => 'Otu ụbọchị fọdụrụ', 'hours' => 'Awa {1} fọdụrụ', 'soon' => 'Ọ ga-amalite n\'oge na-adịghị anya', 'now' => 'Ọ na-aga ugbu a', 'ask' => 'Ị ga-abịa?', 'yes' => 'Ee, a ga m abịa', 'maybe' => 'Ma eleghị anya', 'no' => 'Mba, agaghị m enwe ike', 'thanks' => 'Daalụ. Echekwala azịza gị.', 'when' => 'Mgbe', 'closed' => 'Nzukọ a anaghịzi anabata azịza.', 'bad' => 'Njikọ a ezighi ezi. Mepee WDOS ka ị zaa.', 'change' => 'Ị nwere ike ịgbanwe azịza gị ebe a mgbe ọ bụla.'],
];
function flier_lang(string $l): string { return isset(FLIER_WORDS[$l]) ? $l : 'en'; }
/** The language a screen is shown in: the one the app is set to now (?lang=), otherwise the one saved for her. */
function flier_view_lang(array $u): string { $l = (string) ($_GET['lang'] ?? ''); return isset(FLIER_WORDS[$l]) ? $l : flier_lang((string) ($u['lang'] ?? 'en')); }
function flier_word(string $lang, string $k, $a = null): string { $w = FLIER_WORDS[flier_lang($lang)][$k] ?? FLIER_WORDS['en'][$k]; return $a === null ? $w : str_replace('{1}', (string) $a, $w); }
/** "5 days to go", "3 hours to go", in her language. */
function flier_countdown(int $ts, string $lang): string {
  $in = $ts - time();
  if ($in <= 0) return flier_word($lang, 'now');
  if ($in < 3600) return flier_word($lang, 'soon');
  if ($in < 86400) return flier_word($lang, 'hours', (int) floor($in / 3600));
  $d = (int) ceil($in / 86400); return $d === 1 ? flier_word($lang, 'day') : flier_word($lang, 'days', $d);
}

/** The flier picture's address, or ''. */
function flier_image_url(array $m, bool $absolute = false): string {
  if ((string) ($m['flier_image'] ?? '') === '') return '';
  preg_match('/-(\d+)$/', (string) $m['flier_image'], $x);
  $u = media_url((string) $m['flier_image'], (string) $m['flier_mime'], (int) ($x[1] ?? 0));
  return $absolute ? app_origin() . $u : $u;
}
/** The flier's words in one language: her own when translated, otherwise the words as HQ wrote them. */
function flier_text_for(array $m, string $lang): array {
  $head = (string) ($m['flier_headline'] ?? ''); $body = (string) ($m['flier_text'] ?? ''); $used = 'en';
  if ($lang !== 'en' && ($head !== '' || $body !== '')) {
    try { $t = row('SELECT headline, body FROM meeting_flier_texts WHERE meeting_id=? AND lang=?', [(int) $m['id'], $lang]); } catch (PDOException $e) { $t = null; }
    if ($t) { $head = $t['headline'] !== '' ? $t['headline'] : $head; $body = (string) $t['body'] !== '' ? (string) $t['body'] : $body; $used = $lang; }
  }
  return ['headline' => $head, 'body' => $body, 'lang' => $used];
}
function flier_has(array $m): bool { return (string) ($m['flier_image'] ?? '') !== '' || trim((string) ($m['flier_headline'] ?? '')) !== '' || trim((string) ($m['flier_text'] ?? '')) !== ''; }
/** What a screen needs to draw the flier, in this person's language. */
function flier_shape(array $m, string $lang): ?array {
  if (!flier_has($m)) return null;
  $img = flier_image_url($m);
  // v109.59: an uploaded picture with its words painted in her language, when it has been made
  if ($img !== '' && function_exists('flier_render_url')) { $l = flier_lang($lang); $tr = flier_render_url((int) $m['id'], $l, false); if ($tr !== '') $img = $tr; }
  return ['image' => $img, 'original' => flier_image_url($m)] + flier_text_for($m, flier_lang($lang));
}

/** Store an uploaded flier picture (a data: address from the phone, already made smaller there). */
function flier_check_image(string $data): array {
  if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#s', $data, $x)) fail(422, 'Choose a JPG, PNG or WebP picture for the flier.', 'flier_image');
  $bin = base64_decode($x[2], true);
  if ($bin === false || strlen($bin) < 200) fail(422, 'That picture could not be read. Choose it again.', 'flier_image');
  if (strlen($bin) > 1200000) fail(422, 'That picture is too large. Choose one under 1 MB.', 'flier_image');
  $info = @getimagesizefromstring($bin);
  if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) fail(422, 'That file is not a picture WDOS can show.', 'flier_image');
  return [$info['mime'], $bin];
}
function flier_store_image(int $mid, string $data): array {
  [$mime, $bin] = flier_check_image($data); $key = 'flier/m' . $mid . '-' . time();
  $old = row('SELECT flier_image, flier_mime FROM meetings WHERE id=?', [$mid]);
  $st = db()->prepare('REPLACE INTO message_photos (image_key,mime,bytes,updated_at) VALUES (?,?,?,?)');
  $st->bindValue(1, $key); $st->bindValue(2, $mime); $st->bindValue(3, $bin, PDO::PARAM_LOB); $st->bindValue(4, now()); $st->execute();
  media_write($key, $mime, $bin);
  q('UPDATE meetings SET flier_image=?, flier_mime=? WHERE id=?', [$key, $mime, $mid]);
  if ($old && $old['flier_image'] !== '' && $old['flier_image'] !== $key) flier_drop_image((string) $old['flier_image'], (string) $old['flier_mime']);
  return ['key' => $key, 'mime' => $mime];
}
function flier_drop_image(string $key, string $mime): void {
  if ($key === '') return;
  q('DELETE FROM message_photos WHERE image_key=?', [$key]); @unlink(media_file($key, $mime));
}

/** Translate the flier's words into each language. Words HQ corrected by hand are kept. Returns what happened. */
function flier_translate(int $mid, bool $redo = false): array {
  $m = row('SELECT id, flier_headline, flier_text FROM meetings WHERE id=?', [$mid]);
  if (!$m) return ['done' => 0, 'reason' => 'not_found'];
  $head = trim((string) $m['flier_headline']); $body = trim((string) $m['flier_text']);
  if ($head === '' && $body === '') return ['done' => 0, 'reason' => 'nothing_to_translate'];
  if (!class_exists('Ai') || !Ai::enabled() || !Ai::lanes()) return ['done' => 0, 'reason' => 'ai_off'];
  $done = 0; $failed = []; $why = '';
  foreach (FLIER_LANGS as $l) {
    $have = row('SELECT source, headline, body FROM meeting_flier_texts WHERE meeting_id=? AND lang=?', [$mid, $l]);
    if ($have && ($have['headline'] !== '' || (string) $have['body'] !== '') && ($have['source'] === 'hq' || !$redo)) continue;   // v109.59: a row holding only the picture's words still needs the headline
    $r = Ai::chat('meeting_flier', 'You translate a WODDI meeting flier into ' . FLIER_LANG_NAMES[$l] . '. WODDI is a women\'s organisation; write warmly and simply for women with little schooling. '
      . 'Keep names of people and places, links, email addresses, phone numbers, dates, times and the words WODDI, WGMN, WNNN and WDOS exactly as they are. '
      . 'Translate only the words given, faithfully: add nothing, explain nothing, invent no details. A field that is empty stays empty. '
      . 'Answer with JSON only, in this shape: {"headline": "...", "body": "..."}', json_encode(['headline' => $head, 'body' => $body], JSON_UNESCAPED_UNICODE),
      ['class' => 'aggregate', 'max_tokens' => 900]);
    $j = ($r['ok'] ?? false) && preg_match('/\{.*\}/s', (string) $r['text'], $x) ? json_decode($x[0], true) : null;
    if (!is_array($j) || (trim((string) ($j['headline'] ?? '')) === '' && trim((string) ($j['body'] ?? '')) === '')) { $failed[] = $l; $why = (string) ($r['reason'] ?? 'unreadable answer'); continue; }
    q('INSERT INTO meeting_flier_texts (meeting_id,lang,headline,body,source,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE headline=VALUES(headline), body=VALUES(body), source=VALUES(source), updated_at=VALUES(updated_at)',
      // v109.58: what was empty stays empty, so a translation can never carry words HQ did not write (it once invented a whole message)
      [$mid, $l, $head === '' ? '' : mb_substr(trim((string) ($j['headline'] ?? '')), 0, 250), $body === '' ? '' : mb_substr(trim((string) ($j['body'] ?? '')), 0, 6000), 'ai', now()]);
    $done++;
  }
  return ['done' => $done, 'failed' => $failed, 'reason' => $failed ? $why : ''];
}
/** Which languages have the flier's words, and how they were made. */
function flier_langs(int $mid): array {
  $have = []; foreach (rows('SELECT lang, headline, body, source, updated_at FROM meeting_flier_texts WHERE meeting_id=?', [$mid]) as $r) $have[$r['lang']] = $r;
  return array_map(fn($l) => ['lang' => $l, 'name' => FLIER_LANG_NAMES[$l], 'ready' => isset($have[$l]) && ($have[$l]['headline'] !== '' || (string) $have[$l]['body'] !== ''), 'source' => $have[$l]['source'] ?? '', 'headline' => $have[$l]['headline'] ?? '', 'body' => (string) ($have[$l]['body'] ?? '')], FLIER_LANGS);
}

/* ---- one-tap replies from the email -------------------------------------------- */
function flier_secret(): string {
  $s = (string) (val("SELECT v FROM app_settings WHERE k='meeting_reply_secret'") ?? '');
  if ($s === '') { $s = bin2hex(random_bytes(32)); try { q("INSERT INTO app_settings (k,v) VALUES ('meeting_reply_secret',?)", [$s]); } catch (PDOException $e) { $s = (string) val("SELECT v FROM app_settings WHERE k='meeting_reply_secret'"); } }
  return $s;
}
function flier_sig(int $mid, int $uid): string { return substr(hash_hmac('sha256', "meeting-reply|$mid|$uid", flier_secret()), 0, 32); }
function flier_reply_url(int $mid, int $uid, string $answer): string { return app_origin() . '/api/meetings/' . $mid . '/reply?u=' . $uid . '&a=' . $answer . '&s=' . flier_sig($mid, $uid); }

/** The flier, the countdown and the reply buttons, for one person's email, in her language. */
function flier_mail_block(array $m, array $r): string {
  $lang = flier_lang((string) ($r['lang'] ?? 'en')); $rtl = $lang === 'ar' ? ' dir="rtl"' : '';
  $ts = strtotime($m['starts_at'] . ' UTC'); $out = '<div' . $rtl . '>';
  if ($m['status'] === 'scheduled' && $ts > time()) $out .= '<p style="margin:16px 0 8px"><span style="display:inline-block;background:#7CB518;color:#12210A;font-weight:bold;padding:6px 14px;border-radius:999px">' . htmlspecialchars(flier_countdown($ts, $lang)) . '</span></p>';
  $rendered = flier_has($m) ? (flier_render_url((int) $m['id'], $lang) ?: flier_render_url((int) $m['id'], 'en')) : '';
  if ($rendered !== '') {      // v109.58: the flier picture made for her language, with the words, the date and the QR code on it
    $f = flier_text_for($m, $lang);
    $out .= '<div style="margin:12px 0"><img src="' . htmlspecialchars($rendered) . '" alt="' . htmlspecialchars($f['headline'] ?: $m['title']) . '" style="display:block;width:100%;max-width:560px;height:auto;border:0;border-radius:14px"></div>';
  } elseif (flier_has($m)) {
    $f = flier_text_for($m, $lang); $img = flier_image_url($m, true);
    $out .= '<div style="border:2px solid #D4006A;border-radius:14px;overflow:hidden;margin:12px 0">'
      . ($img !== '' ? '<img src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($f['headline'] ?: $m['title']) . '" style="display:block;width:100%;max-width:560px;height:auto;border:0">' : '')
      . '<div style="background:#D4006A;color:#ffffff;padding:16px 18px"><div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.9">No Woman Is Left Out</div>'
      . '<div style="font-size:21px;font-weight:bold;margin-top:6px">' . htmlspecialchars($f['headline'] !== '' ? $f['headline'] : $m['title']) . '</div></div>'
      . ($f['body'] !== '' ? '<div style="padding:14px 18px;white-space:pre-wrap;font-size:15px;line-height:1.5">' . nl2br(htmlspecialchars(mb_substr($f['body'], 0, 2500))) . '</div>' : '')
      . '<div style="background:#7CB518;height:6px"></div></div>';
  }
  if ($m['status'] === 'scheduled' && $ts > time() && !empty($r['id'])) {
    $b = fn($a, $bg, $fg) => '<a href="' . htmlspecialchars(flier_reply_url((int) $m['id'], (int) $r['id'], $a)) . '" style="display:inline-block;margin:4px 6px 4px 0;background:' . $bg . ';color:' . $fg . ';text-decoration:none;padding:10px 16px;border-radius:10px;font-weight:bold">' . htmlspecialchars(flier_word($lang, $a)) . '</a>';
    $out .= '<p style="margin:16px 0 6px;font-weight:bold">' . htmlspecialchars(flier_word($lang, 'ask')) . '</p><p style="margin:0">' . $b('yes', '#D4006A', '#ffffff') . $b('maybe', '#f1e6ec', '#231019') . $b('no', '#f1e6ec', '#231019') . '</p>';
  }
  return $out . '</div>';
}

/* ---- v109.58: joining through WDOS, the invitation email, the per-language flier pictures for emails ---------------- */
/** A link that opens the meeting through WDOS: it records that she joined, then takes her to the call. */
function flier_join_url(int $mid, int $uid): string { return app_origin() . '/api/meetings/' . $mid . '/go?u=' . $uid . '&s=' . flier_sig($mid, $uid); }
/** The flier picture made for emails in one language (drawn by the host's browser with the translated words), or ''. */
function flier_render_url(int $mid, string $lang, bool $absolute = true): string {
  try { $r = row('SELECT mime, updated_at FROM message_photos WHERE image_key=?', ["flier/r$mid-$lang"]); } catch (PDOException $e) { $r = null; }
  if (!$r) return '';
  $u = media_url("flier/r$mid-$lang", $r['mime'], strtotime($r['updated_at'] . ' UTC'));
  return $absolute ? app_origin() . $u : $u;
}
function flier_drop_renders(int $mid, ?string $lang = null): void {
  foreach (rows('SELECT image_key, mime FROM message_photos WHERE image_key LIKE ?', ["flier/r$mid-" . ($lang ?? '%')]) as $r) flier_drop_image($r['image_key'], $r['mime']);
}
/** The picture a phone notification shows for this meeting: the English flier picture, or the uploaded one. */
function flier_push_image(array $m): string { return flier_render_url((int) $m['id'], 'en') ?: (flier_image_url($m, true)); }

/** The invitation email, once per meeting. With a flier it waits for the flier pictures (or 4 minutes at most). */
function meeting_invite_mail(int $mid): int {
  if (!mail_configured() || !claim_send('meet_inv_' . $mid)) return 0;
  q("DELETE FROM app_settings WHERE k=?", ['meet_inv_wait_' . $mid]);
  $m = row('SELECT * FROM meetings WHERE id=?', [$mid]); if (!$m || $m['status'] !== 'scheduled') return 0;
  $host = row('SELECT * FROM users WHERE id=?', [(int) $m['host_id']]); $caller = $host ? meeting_caller($host) : 'WODDI';
  $s = strtotime($m['starts_at'] . ' UTC'); $e = strtotime($m['ends_at'] . ' UTC'); $loc = (string) $m['location'];
  $link = preg_match('#https://\S+#i', $loc, $lm) ? rtrim($lm[0], '.,)') : '';
  $rest = $link ? trim(str_replace($lm[0], '', $loc)) : '';
  [$w, $p] = meeting_invited_sql($m); $mailed = 0;
  foreach (rows("SELECT u.id, u.lang, u.email, u.first_name, u.tz, u.country_code FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND u.email<>'' AND u.email NOT LIKE '%@phone.wdos.invalid' AND (u.id=? OR ($w)) LIMIT 3000", array_merge([(int) $m['host_id']], $p)) as $r) {
    $tz = tz_of($r); $when = tz_when($s, $tz, true, true) . ' to ' . tz_when($e, $tz, false); $go = $link ? flier_join_url($mid, (int) $r['id']) : '';
    $html = '<p><b>' . htmlspecialchars($caller) . '</b> has called a meeting.</p><table cellpadding="6" style="border-collapse:collapse;font-size:15px">'
      . '<tr><td style="color:#7a6a72">Meeting</td><td><b>' . htmlspecialchars($m['title']) . '</b></td></tr><tr><td style="color:#7a6a72">When</td><td>' . htmlspecialchars($when) . '</td></tr>'
      . '<tr><td style="color:#7a6a72">Format</td><td>' . (['virtual' => 'Online', 'in_person' => 'In person', 'hybrid' => 'In person and online'][$m['mode']] ?? 'Online') . '</td></tr><tr><td style="color:#7a6a72">' . ($link ? 'Link' : 'Where') . '</td><td>'
      . ($link ? '<a href="' . htmlspecialchars($go) . '">' . htmlspecialchars($link) . '</a>' . ($rest !== '' ? '<br>' . htmlspecialchars($rest) : '') : htmlspecialchars($loc)) . '</td></tr></table>'
      . (trim((string) $m['agenda']) !== '' ? '<p style="margin-top:14px"><b>Agenda</b></p><p style="white-space:pre-wrap">' . nl2br(htmlspecialchars(mb_substr((string) $m['agenda'], 0, 2000))) . '</p>' : '');
    if (mail_queue($r['email'], $r['first_name'], 'Meeting: ' . mb_substr($m['title'], 0, 150), mail_wrap('Dear ' . $r['first_name'] . ',', $html . flier_mail_block($m, $r), $link ? 'Join the meeting' : 'Open in WDOS', $link ? $go : app_url() . '/#/meetings/' . $mid))) $mailed++;
  }
  return $mailed;
}
/** What the minutes email says about attendance. */
function meeting_attendance_html(array $m): string {
  $id = (int) $m['id']; [$w, $p] = meeting_invited_sql($m);
  $inv = (int) val("SELECT COUNT(*) FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND u.id<>? AND ($w)", array_merge([(int) $m['host_id']], $p));
  $yes = (int) val("SELECT COUNT(*) FROM meeting_rsvps WHERE meeting_id=? AND answer='yes'", [$id]);
  $joined = (int) val('SELECT COUNT(*) FROM meeting_joins WHERE meeting_id=?', [$id]);
  $present = (int) val("SELECT COUNT(*) FROM meeting_attendance WHERE meeting_id=? AND status='present'", [$id]);
  $cell = fn($n, $l) => '<td style="padding:10px 14px;background:#fbeef4;border-radius:10px;text-align:center"><div style="font-size:22px;font-weight:bold;color:#D4006A">' . $n . '</div><div style="font-size:12px;color:#7a6a72">' . $l . '</div></td>';
  return '<p style="margin-top:14px"><b>Attendance</b></p><table cellspacing="6"><tr>' . $cell($inv, 'invited') . $cell($yes, 'said they would attend') . $cell($joined, 'joined through WDOS') . ($present ? $cell($present, 'marked present') : '') . '</tr></table>';
}
function meeting_files_html(int $mid): string {
  $fs = function_exists('doc_list') ? doc_list('smeeting', $mid) : []; if (!$fs) return '';
  return '<p style="margin-top:14px"><b>Slides and documents</b></p><ul>' . implode('', array_map(fn($f) => '<li>' . htmlspecialchars($f['name']) . '</li>', $fs)) . '</ul><p style="font-size:13px;color:#7a6a72">Open WDOS to download them.</p>';
}

/* ---- v109.59: the words inside an uploaded flier picture ---------------------------------------------------------------
 * The host's browser reads the words on the picture (where each line sits, and the text) and sends them here as "groups"
 * (a paragraph of one or more lines). Each group is translated as a whole; the browser then covers the original words and
 * paints the translation in the same places. HQ can correct any language by hand; a hand-written language is kept. */
function flier_words(array $m): ?array { $w = json_decode((string) ($m['flier_words'] ?? ''), true); return is_array($w) && !empty($w['groups']) ? $w : null; }
function flier_words_translate(int $mid, bool $redo = false): array {
  $m = row('SELECT id, flier_words FROM meetings WHERE id=?', [$mid]); $w = $m ? flier_words($m) : null;
  if (!$w) return ['done' => 0, 'reason' => 'no_words'];
  if (!class_exists('Ai') || !Ai::enabled() || !Ai::lanes()) return ['done' => 0, 'reason' => 'ai_off'];
  $src = array_map(fn($g) => (string) $g['text'], $w['groups']); $done = 0; $failed = []; $why = '';
  foreach (FLIER_LANGS as $l) {
    $have = row('SELECT source, words_json FROM meeting_flier_texts WHERE meeting_id=? AND lang=?', [$mid, $l]);
    if ($have && (string) $have['words_json'] !== '' && ($have['source'] === 'hq' || !$redo)) continue;
    $r = Ai::chat('meeting_flier_words', 'You translate the words printed on a WODDI meeting flier into ' . FLIER_LANG_NAMES[$l] . '. WODDI is a women\'s organisation; write simply and warmly. '
      . 'You get a JSON array of short texts. Translate each one faithfully and keep it about as short as the original, because it must fit in the same place on the picture. '
      . 'Keep names of people and places, links, email addresses, phone numbers, dates, times, numbers, hashtags and the words WODDI, WGMN, WNNN and WDOS exactly as they are. Add nothing. '
      . 'Answer with a JSON array only, with exactly ' . count($src) . ' strings in the same order.', json_encode($src, JSON_UNESCAPED_UNICODE), ['class' => 'aggregate', 'max_tokens' => 1600]);
    $j = ($r['ok'] ?? false) && preg_match('/\[.*\]/s', (string) $r['text'], $x) ? json_decode($x[0], true) : null;
    if (!is_array($j) || count($j) !== count($src)) { $failed[] = $l; $why = (string) ($r['reason'] ?? 'unreadable answer'); continue; }
    $j = array_map(fn($s) => mb_substr(trim((string) $s), 0, 600), array_values($j));
    q("INSERT INTO meeting_flier_texts (meeting_id,lang,headline,body,source,updated_at,words_json) VALUES (?,?,'','','ai',?,?) ON DUPLICATE KEY UPDATE words_json=VALUES(words_json), updated_at=VALUES(updated_at)", [$mid, $l, now(), json_encode($j, JSON_UNESCAPED_UNICODE)]);
    $done++;
  }
  return ['done' => $done, 'failed' => $failed, 'reason' => $failed ? $why : ''];
}
