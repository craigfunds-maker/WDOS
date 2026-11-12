<?php
declare(strict_types=1);
/* The one AI service. Every AI feature calls Ai::chat / Ai::embed / Ai::transcribe and never a provider directly.
 * Lanes are tried in order: Cloudflare Workers AI, Hugging Face, Groq, Gemini. A lane exists only when its key is in the
 * server environment (Render). Keys are never stored in the database, never sent to the browser and never logged.
 * Every attempt is logged in ai_calls. When no lane answers the caller gets ok=false and shows its plain version.
 *
 * Data classes, because free tiers may use prompts to improve the provider's products:
 *   aggregate  counts and place names only. Any lane.
 *   text       words written by leaders. Free lane only after redaction, and only while rule ai_free_lane_text = redacted.
 *   sensitive  safeguarding or identifiable member data. Paid lanes only (AI_PAID_LANES). */
final class Ai {
  /** The lanes this server can use, in order. Names and models only: no key ever leaves this function's callers. */
  public static function lanes(): array {
    $paid = array_filter(array_map('trim', explode(',', strtolower((string) cfg('AI_PAID_LANES', '')))));
    $all = [
      'cloudflare' => ['on' => cfg('CF_ACCOUNT_ID') && cfg('CF_API_TOKEN'), 'base' => 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode((string) cfg('CF_ACCOUNT_ID', '')) . '/ai/v1', 'key' => 'CF_API_TOKEN',
        'chat' => cfg('CF_AI_CHAT_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'), 'embed' => cfg('CF_AI_EMBED_MODEL', '@cf/baai/bge-m3'), 'stt' => cfg('CF_AI_STT_MODEL', '@cf/openai/whisper-large-v3-turbo')],
      'huggingface' => ['on' => (bool) cfg('HF_TOKEN'), 'base' => 'https://router.huggingface.co/v1', 'key' => 'HF_TOKEN',
        'chat' => cfg('HF_CHAT_MODEL', 'meta-llama/Llama-3.3-70B-Instruct'), 'embed' => cfg('HF_EMBED_MODEL', 'BAAI/bge-m3'), 'stt' => ''],
      'groq' => ['on' => (bool) cfg('GROQ_API_KEY'), 'base' => 'https://api.groq.com/openai/v1', 'key' => 'GROQ_API_KEY',
        'chat' => cfg('GROQ_CHAT_MODEL', 'openai/gpt-oss-120b'), 'embed' => '', 'stt' => cfg('GROQ_STT_MODEL', 'whisper-large-v3-turbo')],
      'gemini' => ['on' => (bool) cfg('GEMINI_API_KEY'), 'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'key' => 'GEMINI_API_KEY',
        'chat' => cfg('GEMINI_CHAT_MODEL', 'gemini-flash-latest'), 'embed' => cfg('GEMINI_EMBED_MODEL', 'gemini-embedding-001'), 'stt' => ''],
    ];
    // a stand-in lane for automated tests on a developer machine only; it can never be set on a public host name
    $mock = (string) cfg('AI_MOCK_URL', '');
    if ($mock !== '' && preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?(/|$)#', $mock)) $all = ['mock' => ['on' => true, 'base' => rtrim($mock, '/'), 'key' => '', 'chat' => 'mock-chat', 'embed' => 'mock-embed', 'stt' => 'mock-stt']] + $all;
    $order = array_filter(array_map('trim', explode(',', strtolower((string) cfg('AI_LANES', 'mock,cloudflare,huggingface,groq,gemini')))));
    $out = [];
    foreach ($order as $name) if (isset($all[$name]) && $all[$name]['on']) $out[$name] = $all[$name] + ['paid' => in_array($name, $paid, true)];
    return $out;
  }
  public static function enabled(): bool { return (function_exists('policy') ? policy('ai_features') : 'on') !== 'off'; }
  public static function dailyCap(): int { return max(0, (int) cfg('AI_DAILY_CALLS', '300')); }
  public static function usedToday(): int { try { return (int) val('SELECT COUNT(*) FROM ai_calls WHERE cached=0 AND created_at>=?', [gmdate('Y-m-d 00:00:00')]); } catch (Throwable $e) { return 0; } }

  /** Removes what identifies a person: emails, phone numbers, links, membership numbers and any names given. */
  public static function redact(string $text, array $names = []): string {
    $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email]', $text);
    $text = preg_replace('#https?://\S+#iu', '[link]', $text);
    $text = preg_replace('/\b(WGMN|WNNN|HQ)-[A-Z0-9\-]{3,}\b/u', '[member number]', $text);
    $text = preg_replace('/(?<![\w])\+?\d[\d\s().\-]{6,}\d(?![\w])/u', '[phone]', $text);
    $names = array_values(array_unique(array_filter(array_map(fn($n) => trim((string) $n), $names), fn($n) => mb_strlen($n) >= 3)));
    usort($names, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    foreach (array_slice($names, 0, 4000) as $n) $text = preg_replace('/(?<![\p{L}])' . preg_quote($n, '/') . '(?![\p{L}])/iu', '[name]', $text);
    return (string) $text;
  }
  private static function log(string $feature, string $kind, string $lane, string $model, int $ms, bool $ok, string $error, int $in, int $out, ?int $uid, bool $cached = false): void {
    try { q('INSERT INTO ai_calls (feature,kind,lane,model,ms,ok,cached,error,in_chars,out_chars,user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [mb_substr($feature, 0, 40), $kind, $lane, mb_substr($model, 0, 120), $ms, $ok ? 1 : 0, $cached ? 1 : 0, mb_substr($error, 0, 190), $in, $out, $uid, now()]); } catch (Throwable $e) { /* logging never breaks a feature */ }
  }
  private static function post(string $url, string $keyName, array $payload, int $timeout): array {
    $h = ['Content-Type: application/json']; if ($keyName !== '') $h[] = 'Authorization: Bearer ' . cfg($keyName);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE), CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6]);
    $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) return [0, null, 'no answer: ' . $err];
    $j = json_decode((string) $raw, true);
    if ($code < 200 || $code >= 300) { $m = is_array($j) ? ($j['error']['message'] ?? ($j['errors'][0]['message'] ?? ($j['error'] ?? ''))) : ''; return [$code, null, 'HTTP ' . $code . (is_string($m) && $m !== '' ? ': ' . $m : '')]; }
    return [$code, is_array($j) ? $j : null, is_array($j) ? '' : 'answer was not JSON'];
  }
  /** Which lanes may carry this class of data, and whether the text must be redacted first. */
  private static function allowed(string $class): array {
    $rule = function_exists('policy') ? (policy('ai_free_lane_text') ?: 'redacted') : 'redacted'; $out = [];
    foreach (self::lanes() as $name => $l) {
      if ($class === 'sensitive' && !$l['paid']) continue;
      if ($class === 'text' && !$l['paid'] && $rule === 'off') continue;
      $out[$name] = $l + ['redact' => $class !== 'aggregate' && !$l['paid']];
    }
    return $out;
  }
  private static function refuse(string $class): string {
    if (!self::enabled()) return 'off';
    if (!self::lanes()) return 'no_key';
    if (!self::allowed($class)) return 'needs_paid_key';
    if (self::dailyCap() && self::usedToday() >= self::dailyCap()) return 'daily_cap';
    return '';
  }

  /** One question, one answer. $o: class (aggregate|text|sensitive), names (to redact), max_tokens, ttl (cache seconds), user_id. */
  public static function chat(string $feature, string $system, string $user, array $o = []): array {
    $class = $o['class'] ?? 'aggregate'; $uid = $o['user_id'] ?? null; $ttl = (int) ($o['ttl'] ?? 86400);
    if ($why = self::refuse($class)) return ['ok' => false, 'reason' => $why];
    $last = 'no_lane';
    foreach (self::allowed($class) as $name => $l) {
      $text = $l['redact'] ? self::redact($user, $o['names'] ?? []) : $user;
      $key = hash('sha256', $feature . '|' . $l['chat'] . '|' . $system . '|' . $text);
      if ($ttl > 0) { $hit = row('SELECT result, lane, model FROM ai_cache WHERE cache_key=? AND expires_at>?', [$key, now()]); if ($hit) { self::log($feature, 'chat', $hit['lane'], $hit['model'], 0, true, '', mb_strlen($text), mb_strlen($hit['result']), $uid, true); return ['ok' => true, 'text' => $hit['result'], 'lane' => $hit['lane'], 'model' => $hit['model'], 'cached' => true, 'redacted' => $l['redact']]; } }
      $t0 = microtime(true);
      [$code, $j, $err] = self::post($l['base'] . '/chat/completions', $l['key'], ['model' => $l['chat'], 'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $text]], 'max_tokens' => (int) ($o['max_tokens'] ?? 700), 'temperature' => 0.2], (int) cfg('AI_TIMEOUT', '25'));
      $ms = (int) round((microtime(true) - $t0) * 1000); $ans = is_array($j) ? trim((string) ($j['choices'][0]['message']['content'] ?? '')) : '';
      if ($ans === '') { $last = $err ?: 'empty answer'; self::log($feature, 'chat', $name, (string) $l['chat'], $ms, false, $last, mb_strlen($text), 0, $uid); continue; }
      self::log($feature, 'chat', $name, (string) $l['chat'], $ms, true, '', mb_strlen($text), mb_strlen($ans), $uid);
      if ($ttl > 0) { try { q('REPLACE INTO ai_cache (cache_key,feature,lane,model,result,created_at,expires_at) VALUES (?,?,?,?,?,?,?)', [$key, mb_substr($feature, 0, 40), $name, mb_substr((string) $l['chat'], 0, 120), $ans, now(), gmdate('Y-m-d H:i:s', time() + $ttl)]); } catch (Throwable $e) { /* cache is optional */ } }
      return ['ok' => true, 'text' => $ans, 'lane' => $name, 'model' => $l['chat'], 'cached' => false, 'redacted' => $l['redact']];
    }
    return ['ok' => false, 'reason' => 'all_failed', 'detail' => $last];
  }

  /** Turns texts into vectors for search by meaning. Returns ['ok', 'vectors' => [[float,...]], 'lane', 'model']. */
  public static function embed(string $feature, array $texts, array $o = []): array {
    $class = $o['class'] ?? 'aggregate'; $uid = $o['user_id'] ?? null;
    if ($why = self::refuse($class)) return ['ok' => false, 'reason' => $why];
    $texts = array_values(array_map(fn($t) => mb_substr((string) $t, 0, 2000), $texts)); $last = 'no_lane';
    foreach (self::allowed($class) as $name => $l) {
      if ($l['embed'] === '') continue;
      $in = $l['redact'] ? array_map(fn($t) => self::redact($t, $o['names'] ?? []), $texts) : $texts; $t0 = microtime(true);
      if ($name === 'huggingface') { [$code, $j, $err] = self::post('https://router.huggingface.co/hf-inference/models/' . $l['embed'] . '/pipeline/feature-extraction', $l['key'], ['inputs' => $in], 25); $vecs = is_array($j) && isset($j[0]) && is_array($j[0]) ? $j : null; }
      else { [$code, $j, $err] = self::post($l['base'] . '/embeddings', $l['key'], ['model' => $l['embed'], 'input' => $in], 25); $vecs = is_array($j) && isset($j['data'][0]['embedding']) ? array_map(fn($d) => $d['embedding'], $j['data']) : null; }
      $ms = (int) round((microtime(true) - $t0) * 1000); $n = array_sum(array_map('mb_strlen', $in));
      if (!$vecs || count($vecs) !== count($in)) { $last = $err ?: 'no vectors'; self::log($feature, 'embed', $name, (string) $l['embed'], $ms, false, $last, $n, 0, $uid); continue; }
      self::log($feature, 'embed', $name, (string) $l['embed'], $ms, true, '', $n, count($vecs), $uid);
      return ['ok' => true, 'vectors' => $vecs, 'lane' => $name, 'model' => (string) $l['embed']];
    }
    return ['ok' => false, 'reason' => 'all_failed', 'detail' => $last];
  }

  /** Speech to text. A voice is identifiable, so this is the sensitive class: paid lanes only. Not used by a screen yet. */
  public static function transcribe(string $feature, string $bytes, string $mime, string $lang = '', array $o = []): array {
    if ($why = self::refuse('sensitive')) return ['ok' => false, 'reason' => $why];
    $last = 'no_lane';
    foreach (self::allowed('sensitive') as $name => $l) {
      if ($l['stt'] === '') continue; $t0 = microtime(true);
      if ($name === 'cloudflare') { [$code, $j, $err] = self::post(str_replace('/ai/v1', '/ai/run/', $l['base']) . $l['stt'], $l['key'], ['audio' => base64_encode($bytes)] + ($lang ? ['language' => $lang] : []), 60); $txt = is_array($j) ? (string) ($j['result']['text'] ?? '') : ''; }
      else {
        $tmp = tempnam(sys_get_temp_dir(), 'wdos'); file_put_contents($tmp, $bytes); $ch = curl_init($l['base'] . '/audio/transcriptions');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $l['key'] !== '' ? ['Authorization: Bearer ' . cfg($l['key'])] : [], CURLOPT_POSTFIELDS => ['model' => $l['stt'], 'file' => new CURLFile($tmp, $mime, 'voice'), 'response_format' => 'json'] + ($lang ? ['language' => $lang] : []), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
        $raw = curl_exec($ch); $err = $raw === false ? curl_error($ch) : ''; curl_close($ch); @unlink($tmp); $j = json_decode((string) $raw, true); $txt = is_array($j) ? (string) ($j['text'] ?? '') : '';
      }
      $ms = (int) round((microtime(true) - $t0) * 1000);
      if (trim($txt) === '') { $last = $err ?: 'no words heard'; self::log($feature, 'stt', $name, (string) $l['stt'], $ms, false, $last, strlen($bytes), 0, $o['user_id'] ?? null); continue; }
      self::log($feature, 'stt', $name, (string) $l['stt'], $ms, true, '', strlen($bytes), mb_strlen($txt), $o['user_id'] ?? null);
      return ['ok' => true, 'text' => trim($txt), 'lane' => $name, 'model' => (string) $l['stt']];
    }
    return ['ok' => false, 'reason' => 'all_failed', 'detail' => $last];
  }
}

const AI_AFRICA = ['DZ','AO','BJ','BW','BF','BI','CV','CM','CF','TD','KM','CG','CD','CI','DJ','EG','GQ','ER','SZ','ET','GA','GM','GH','GN','GW','KE','LS','LR','LY','MG','MW','ML','MR','MU','MA','MZ','NA','NE','NG','RW','ST','SN','SC','SL','SO','ZA','SS','SD','TZ','TG','TN','UG','ZM','ZW'];
const AI_LANG_NAMES = ['en' => 'English', 'fr' => 'French', 'pt' => 'Portuguese', 'ar' => 'Arabic', 'sw' => 'Swahili', 'ha' => 'Hausa', 'yo' => 'Yoruba', 'ig' => 'Igbo'];
const AI_RULES = "You write for the headquarters of a women's network. Use ONLY the material between the markers. Never add a number, a name, a place or an event that is not in the material. If something was not captured, write \"not recorded\". Plain short sentences. No greeting, no sign-off, no markdown symbols, no em dashes.";
function ai_lang(string $l): string { return isset(AI_LANG_NAMES[$l]) ? $l : 'en'; }
function ai_n(string $sql, array $p = []): ?int { try { $v = val($sql, $p); return $v === null ? 0 : (int) $v; } catch (Throwable $e) { return null; } }   // null means "not recorded"
function ai_keep(string $feature, string $scope, string $period, string $lang, array $r, array $facts, ?int $by): void {
  q('DELETE FROM ai_outputs WHERE feature=? AND scope_key=? AND period=? AND lang=?', [$feature, $scope, $period, $lang]);
  q('INSERT INTO ai_outputs (feature,scope_key,period,lang,lane,model,body,facts,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)', [$feature, $scope, $period, $lang, $r['lane'], mb_substr((string) $r['model'], 0, 120), $r['text'], json_encode($facts, JSON_UNESCAPED_UNICODE), $by, now()]);
}
function ai_kept(string $feature, string $scope, string $period, string $lang): ?array {
  $r = row('SELECT o.body, o.lane, o.model, o.created_at, CONCAT(u.first_name,\' \',u.last_name) AS by_name FROM ai_outputs o LEFT JOIN users u ON u.id=o.created_by WHERE o.feature=? AND o.scope_key=? AND o.period=? AND o.lang=?', [$feature, $scope, $period, $lang]);
  return $r ?: null;
}

/* ---- 1. Report summariser: what the monthly leadership reports of one country say ---------------------------------- */
function ai_report_facts(string $cc, string $period): array {
  $from = $period . '-01'; $to = gmdate('Y-m-t', strtotime($from)); $prevFrom = gmdate('Y-m-01', strtotime($from . ' -1 month')); $prevTo = gmdate('Y-m-t', strtotime($prevFrom));
  $reps = rows("SELECT r.id, r.report_no, r.unit_label, r.network, r.status, r.is_nil, r.nil_reason, r.on_time, r.answers, r.submitted_at FROM report_instances r JOIN org_units ou ON ou.id=r.org_unit_id WHERE ou.country_code=? AND r.period_start>=? AND r.period_start<=? ORDER BY r.unit_label", [$cc, $from, $to]);
  $st = ['draft' => 0, 'submitted' => 0, 'returned' => 0, 'approved' => 0, 'other' => 0]; $nil = 0; $late = 0; $texts = [];
  foreach ($reps as $r) {
    $k = isset($st[$r['status']]) ? $r['status'] : 'other'; $st[$k]++; if ((int) $r['is_nil'] === 1) $nil++; if ($r['on_time'] !== null && (int) $r['on_time'] === 0) $late++;
    if ($r['status'] === 'draft') continue;
    $bits = []; if ((int) $r['is_nil'] === 1) $bits[] = 'No activity declared. Reason: ' . ($r['nil_reason'] ?: 'not recorded');
    $walk = function ($v) use (&$walk, &$bits) { if (is_string($v)) { $s = trim($v); if (mb_strlen($s) >= 25 && preg_match('/\s/u', $s)) $bits[] = mb_substr($s, 0, 500); } elseif (is_array($v)) foreach ($v as $x) $walk($x); };
    $walk(json_decode((string) $r['answers'], true));
    if ($bits) $texts[] = ['unit' => $r['unit_label'], 'network' => $r['network'], 'status' => $r['status'], 'text' => mb_substr(implode(' / ', $bits), 0, 1500)];
  }
  $issues = rows("SELECT i.title, i.category, i.severity, i.status, i.impact, i.decision_required, i.created_at FROM issues i JOIN org_units ou ON ou.id=i.org_unit_id WHERE ou.country_code=? AND ((i.created_at>=? AND i.created_at<=?) OR i.status NOT IN ('resolved','closed')) ORDER BY FIELD(i.severity,'critical','red','amber','green'), i.created_at DESC LIMIT 40", [$cc, $from . ' 00:00:00', $to . ' 23:59:59']);
  $cat = fn(string $a, string $b) => array_column(rows("SELECT i.category, COUNT(*) n FROM issues i JOIN org_units ou ON ou.id=i.org_unit_id WHERE ou.country_code=? AND i.created_at>=? AND i.created_at<=? GROUP BY i.category", [$cc, $a . ' 00:00:00', $b . ' 23:59:59']), 'n', 'category');
  $now = $cat($from, $to); $before = $cat($prevFrom, $prevTo); $rep = [];
  foreach ($now as $c => $n) if (isset($before[$c])) $rep[] = ['category' => $c, 'this_month' => (int) $n, 'last_month' => (int) $before[$c]];
  return ['country' => country_name($cc) ?: $cc, 'country_code' => $cc, 'period' => $period, 'reports' => ['total' => count($reps)] + $st + ['nil' => $nil, 'late' => $late], 'texts' => $texts,
    'issues' => array_map(fn($i) => ['title' => $i['title'], 'category' => $i['category'], 'severity' => $i['severity'], 'status' => $i['status'], 'impact' => $i['impact'], 'decision_required' => $i['decision_required']], $issues),
    'open_issues' => count(array_filter($issues, fn($i) => !in_array($i['status'], ['resolved', 'closed'], true))), 'repeating' => $rep];
}
function ai_report_digest(string $cc, string $period, string $lang, ?int $by): array {
  $f = ai_report_facts($cc, $period);
  if (!$f['reports']['total'] && !$f['issues']) return ['ok' => false, 'reason' => 'nothing_recorded', 'facts' => $f];
  $names = array_merge(array_column(rows('SELECT first_name FROM users WHERE country_code=?', [$cc]), 'first_name'), array_column(rows('SELECT last_name FROM users WHERE country_code=?', [$cc]), 'last_name'));
  $m = "COUNTRY: {$f['country']}\nMONTH: {$f['period']}\nREPORT COUNTS: " . json_encode($f['reports']) . "\nOPEN ISSUES: {$f['open_issues']}\nISSUE CATEGORIES SEEN THIS MONTH AND LAST MONTH: " . json_encode($f['repeating']) . "\n\nWHAT LEADERS WROTE:\n";
  foreach (array_slice($f['texts'], 0, 30) as $t) $m .= "- [{$t['unit']}, {$t['network']}, {$t['status']}] {$t['text']}\n";
  $m .= "\nISSUES RAISED:\n"; foreach (array_slice($f['issues'], 0, 25) as $i) $m .= "- [{$i['severity']}, {$i['category']}, {$i['status']}] {$i['title']}. Impact: " . ($i['impact'] ?: 'not recorded') . '. Decision needed: ' . ($i['decision_required'] ?: 'not recorded') . "\n";
  $sys = AI_RULES . ' Write in ' . AI_LANG_NAMES[$lang] . ". Write a digest with exactly three headed parts, each heading on its own line: DONE, STUCK, REPEATING ISSUES. Under each give two to five short lines starting with a hyphen. Name the unit a point comes from. Words like [name], [phone] or [email] stand for details removed for privacy: leave them out.";
  $r = Ai::chat('report_digest', $sys, "=== MATERIAL ===\n" . mb_substr($m, 0, 14000) . "\n=== END ===", ['class' => 'text', 'names' => $names, 'max_tokens' => 800, 'ttl' => 6 * 3600, 'user_id' => $by]);
  if (!$r['ok']) return $r + ['facts' => $f];
  ai_keep('report_digest', $cc, $period, $lang, $r, $f['reports'], $by);
  return ['ok' => true, 'facts' => $f, 'digest' => ai_kept('report_digest', $cc, $period, $lang), 'redacted' => $r['redacted']];
}

/* ---- 3. Weekly brief: one page from live counts. Counts only, so any lane may carry it. ---------------------------- */
function ai_brief_facts(): array {
  $d7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400); $d14 = gmdate('Y-m-d H:i:s', time() - 14 * 86400); $m = "role NOT IN ('hq_admin','super_admin') AND network IS NOT NULL";
  $top = []; try { $top = rows("SELECT country_code, COUNT(*) n FROM users WHERE $m AND created_at>=? AND country_code IS NOT NULL GROUP BY country_code ORDER BY n DESC LIMIT 5", [$d7]); } catch (Throwable $e) { $top = []; }
  return ['week_ending' => gmdate('Y-m-d'),
    'growth' => ['members_total' => ai_n("SELECT COUNT(*) FROM users WHERE $m"), 'joined_last_7_days' => ai_n("SELECT COUNT(*) FROM users WHERE $m AND created_at>=?", [$d7]), 'joined_previous_7_days' => ai_n("SELECT COUNT(*) FROM users WHERE $m AND created_at>=? AND created_at<?", [$d14, $d7]),
      'wgmn_joined_last_7_days' => ai_n("SELECT COUNT(*) FROM users WHERE $m AND network='WGMN' AND created_at>=?", [$d7]), 'wnnn_joined_last_7_days' => ai_n("SELECT COUNT(*) FROM users WHERE $m AND network='WNNN' AND created_at>=?", [$d7]),
      'countries_with_members' => ai_n("SELECT COUNT(DISTINCT country_code) FROM users WHERE $m"), 'top_countries_last_7_days' => array_map(fn($r) => ['country' => country_name($r['country_code']) ?: $r['country_code'], 'joined' => (int) $r['n']], $top)],
    'activity' => ['members_active_last_7_days' => ai_n("SELECT COUNT(*) FROM users WHERE $m AND last_seen_at>=?", [$d7]), 'chapters_total' => ai_n("SELECT COUNT(*) FROM chapters WHERE closed_at IS NULL"), 'chapters_opened_last_7_days' => ai_n('SELECT COUNT(*) FROM chapters WHERE created_at>=?', [$d7]),
      'chapters_at_capacity' => ai_n("SELECT COUNT(*) FROM chapters WHERE status='at_capacity'"), 'meetings_held_last_7_days' => ai_n("SELECT COUNT(*) FROM meetings WHERE status='closed' AND closed_at>=?", [$d7]),
      'reports_submitted_last_7_days' => ai_n('SELECT COUNT(*) FROM report_instances WHERE submitted_at>=?', [$d7]), 'reports_approved_last_7_days' => ai_n('SELECT COUNT(*) FROM report_instances WHERE approved_at>=?', [$d7]),
      'leaders_seated' => ai_n("SELECT COUNT(*) FROM seats WHERE ends_at IS NULL AND user_id IS NOT NULL"), 'leaders_appointed_last_7_days' => ai_n('SELECT COUNT(*) FROM seats WHERE starts_at>=?', [$d7])],
    'concerns' => ['reports_overdue' => ai_n("SELECT COUNT(*) FROM report_instances WHERE status IN ('draft','returned') AND due_on IS NOT NULL AND due_on<?", [gmdate('Y-m-d')]), 'issues_open' => ai_n("SELECT COUNT(*) FROM issues WHERE status NOT IN ('resolved','closed')"),
      'issues_open_red_or_critical' => ai_n("SELECT COUNT(*) FROM issues WHERE status NOT IN ('resolved','closed') AND severity IN ('red','critical')"), 'private_reports_open' => ai_n("SELECT COUNT(*) FROM cases WHERE status IN ('received','in_review')"),
      'members_waiting_for_a_chapter' => ai_n("SELECT COUNT(*) FROM users WHERE $m AND status='active' AND chapter_id IS NULL"), 'chapters_with_no_leader' => ai_n("SELECT COUNT(*) FROM chapters c WHERE c.closed_at IS NULL AND NOT EXISTS (SELECT 1 FROM seats s WHERE s.chapter_id=c.id AND s.ends_at IS NULL)"),
      'phone_notifications_failed_last_7_days' => ai_n("SELECT COUNT(*) FROM push_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at>=?", [$d7])]];
}
function ai_brief(string $lang, ?int $by): array {
  $f = ai_brief_facts(); $period = gmdate('o-\WW');
  $sys = AI_RULES . ' Write in ' . AI_LANG_NAMES[$lang] . '. Write a one-page weekly brief for the Founder with exactly four headed parts, each heading on its own line: GROWTH, ACTIVITY, CONCERNS, WINS. Two to four short lines under each, starting with a hyphen. A value of null means not recorded: say so and do not guess. Compare this week with the previous week only where both numbers are given. A win must be a number in the material that went up or a concern that is at zero.';
  $r = Ai::chat('weekly_brief', $sys, "=== MATERIAL ===\n" . json_encode($f, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n=== END ===", ['class' => 'aggregate', 'max_tokens' => 700, 'ttl' => 3600, 'user_id' => $by]);
  if (!$r['ok']) return $r + ['facts' => $f, 'period' => $period];
  ai_keep('weekly_brief', 'all', $period, $lang, $r, $f, $by);
  return ['ok' => true, 'facts' => $f, 'period' => $period, 'brief' => ai_kept('weekly_brief', 'all', $period, $lang)];
}

/* ---- 6. Coverage gap finder: arithmetic, not a model. Where there are members but thin structure. ------------------ */
function ai_coverage_gaps(): array {
  $m = "u.role NOT IN ('hq_admin','super_admin') AND u.network IS NOT NULL AND u.country_code IS NOT NULL AND u.status NOT IN ('withdrawn','closed')";
  $st = rows("SELECT u.country_code cc, COALESCE(NULLIF(u.state_region,''),'(not recorded)') area, COUNT(*) members, SUM(u.chapter_id IS NULL) unplaced, SUM(u.created_at>=?) joined_30 FROM users u WHERE $m GROUP BY u.country_code, area", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
  $ch = []; foreach (rows("SELECT country_code cc, COALESCE(NULLIF(state_region,''),'(not recorded)') area, COUNT(*) n FROM chapters WHERE closed_at IS NULL GROUP BY country_code, area") as $r) $ch[$r['cc'] . '|' . mb_strtolower($r['area'])] = (int) $r['n'];
  $ld = []; foreach (rows("SELECT ou.country_code cc, ou.name area, p.level lvl, COUNT(*) n FROM seats s JOIN positions p ON p.code=s.position_code JOIN org_units ou ON ou.id=s.org_unit_id WHERE s.ends_at IS NULL AND s.user_id IS NOT NULL GROUP BY ou.country_code, ou.name, p.level") as $r) { if ($r['lvl'] === 'country') $ld[$r['cc']] = ($ld[$r['cc']] ?? 0) + (int) $r['n']; else $ld[$r['cc'] . '|' . mb_strtolower($r['area'])] = ($ld[$r['cc'] . '|' . mb_strtolower($r['area'])] ?? 0) + (int) $r['n']; }
  $rowsOut = []; $byCountry = [];
  foreach ($st as $r) {
    $k = $r['cc'] . '|' . mb_strtolower($r['area']); $members = (int) $r['members']; $chapters = $ch[$k] ?? 0; $leaders = $ld[$k] ?? 0; $unplaced = (int) $r['unplaced'];
    $why = []; $score = 0;
    if ($leaders === 0) { $why[] = 'no_leader'; $score += 40 + min(40, $members); }
    if ($chapters === 0) { $why[] = 'no_chapter'; $score += 30; }
    if ($unplaced > 0) { $why[] = 'waiting'; $score += min(30, $unplaced * 2); }
    if ($members < 30) { $why[] = 'below_30'; $score += 10; }
    if ((int) $r['joined_30'] > 0) $score += min(15, (int) $r['joined_30']);        // women are already arriving there
    $byCountry[$r['cc']] = ($byCountry[$r['cc']] ?? 0) + $members;
    if ($why) $rowsOut[] = ['country_code' => $r['cc'], 'country' => country_name($r['cc']) ?: $r['cc'], 'area' => $r['area'], 'members' => $members, 'chapters' => $chapters, 'leaders' => $leaders, 'waiting' => $unplaced, 'joined_30' => (int) $r['joined_30'], 'why' => $why, 'score' => $score];
  }
  usort($rowsOut, fn($a, $b) => $b['score'] <=> $a['score'] ?: $b['members'] <=> $a['members']);
  $thin = []; foreach ($byCountry as $cc => $n) if ($n < 30 || empty($ld[$cc])) $thin[] = ['country_code' => $cc, 'country' => country_name($cc) ?: $cc, 'members' => $n, 'country_leaders' => $ld[$cc] ?? 0];
  usort($thin, fn($a, $b) => $b['members'] <=> $a['members']);
  $have = array_flip(array_keys($byCountry)); $none = [];
  foreach (rows("SELECT name, country_code FROM org_units WHERE level='country' ORDER BY name") as $r) if (in_array($r['country_code'], AI_AFRICA, true) && !isset($have[$r['country_code']])) $none[] = $r['name'];
  return ['as_of' => now(), 'areas' => array_slice($rowsOut, 0, 60), 'areas_total' => count($rowsOut), 'thin_countries' => array_slice($thin, 0, 30), 'african_countries_with_no_member' => $none];
}

/* ---- 5. Library search by meaning, with word search underneath ------------------------------------------------------ */
function ai_cos(array $a, array $b): float { $d = 0.0; $x = 0.0; $y = 0.0; $n = min(count($a), count($b)); for ($i = 0; $i < $n; $i++) { $d += $a[$i] * $b[$i]; $x += $a[$i] * $a[$i]; $y += $b[$i] * $b[$i]; } return $x > 0 && $y > 0 ? $d / sqrt($x * $y) : 0.0; }
function ai_library_text(array $it): string { return trim($it['title'] . '. ' . $it['category'] . '. ' . $it['description']); }
/** Keeps a vector for each library item. Titles and descriptions are HQ's own words about documents: no member data. */
function ai_library_index(int $max = 40): int {
  $todo = []; foreach (rows('SELECT l.id, l.title, l.category, l.description, v.text_hash FROM library_items l LEFT JOIN ai_vectors v ON v.item_type=\'library\' AND v.item_id=l.id WHERE l.archived=0') as $it) { $h = hash('sha256', ai_library_text($it)); if ($it['text_hash'] !== $h) $todo[] = $it + ['h' => $h]; if (count($todo) >= $max) break; }
  if (!$todo) return 0;
  $r = Ai::embed('library_search', array_map('ai_library_text', $todo)); if (!$r['ok']) return 0;
  foreach ($todo as $i => $it) q('REPLACE INTO ai_vectors (item_type,item_id,model,text_hash,vec,updated_at) VALUES (?,?,?,?,?,?)', ['library', (int) $it['id'], mb_substr($r['model'], 0, 120), $it['h'], json_encode(array_map(fn($x) => round((float) $x, 5), $r['vectors'][$i])), now()]);
  return count($todo);
}
function ai_library_search(array $items, string $query): array {
  $q = mb_strtolower(trim($query)); $words = array_values(array_filter(preg_split('/\s+/u', $q), fn($w) => mb_strlen($w) >= 2));
  $plain = function () use ($items, $words) { $out = []; foreach ($items as $it) { $t = mb_strtolower(ai_library_text($it)); $hit = 0; foreach ($words as $w) if (mb_strpos($t, $w) !== false) $hit++; if ($hit) $out[] = $it + ['match' => round($hit / max(1, count($words)), 2)]; } usort($out, fn($a, $b) => $b['match'] <=> $a['match']); return $out; };
  if ($q === '' || !$items) return ['mode' => 'words', 'items' => $q === '' ? $items : []];
  $vec = []; try { foreach (rows("SELECT item_id, model, vec FROM ai_vectors WHERE item_type='library'") as $v) $vec[(int) $v['item_id']] = $v; } catch (Throwable $e) { $vec = []; }
  if (!$vec || !Ai::enabled() || !Ai::lanes()) return ['mode' => 'words', 'items' => $plain()];
  $e = Ai::embed('library_search', [$query]); if (!$e['ok']) return ['mode' => 'words', 'items' => $plain()];
  $out = []; foreach ($items as $it) { $v = $vec[$it['id']] ?? null; if (!$v || $v['model'] !== $e['model']) continue; $s = ai_cos($e['vectors'][0], (array) json_decode($v['vec'], true)); if ($s >= 0.35) $out[] = $it + ['match' => round($s, 2)]; }
  if (!$out) return ['mode' => 'words', 'items' => $plain()];
  usort($out, fn($a, $b) => $b['match'] <=> $a['match']); return ['mode' => 'meaning', 'items' => array_slice($out, 0, 20)];
}

/** Daily: keep the library index fresh. Mondays: write the weekly brief in English so it is waiting for HQ. */
function ai_automations(array &$done): void {
  try {
    if (!Ai::enabled() || !Ai::lanes()) return;
    $r = cron_once('ai_library_index', fn() => ai_library_index(40) . ' indexed'); if ($r !== null) $done['ai_library_index'] = $r;
    if (gmdate('N') === '1') { $r = cron_once('ai_weekly_brief', function () { $b = ai_brief('en', null); return $b['ok'] ? 'written' : 'not written: ' . ($b['reason'] ?? ''); }); if ($r !== null) $done['ai_weekly_brief'] = $r; }
  } catch (Throwable $e) { error_log('WDOS ai automations: ' . $e->getMessage()); }
}
