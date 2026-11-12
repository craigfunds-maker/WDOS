<?php
declare(strict_types=1);
/* AI routes. HQ only, apart from library search which every signed-in person uses and which falls back to word search.
 * Nothing here publishes, appoints, sanctions or sends: each result is a draft for a person to read. */

function ai_status(): array {
  $lanes = []; foreach (Ai::lanes() as $n => $l) $lanes[] = ['lane' => $n, 'chat' => $l['chat'], 'embed' => $l['embed'], 'speech' => $l['stt'], 'paid' => $l['paid']];
  return ['enabled' => Ai::enabled(), 'lanes' => $lanes, 'daily_cap' => Ai::dailyCap(), 'used_today' => Ai::usedToday(), 'free_lane_text' => policy('ai_free_lane_text') ?: 'redacted',
    'known_lanes' => [['lane' => 'cloudflare', 'needs' => ['CF_ACCOUNT_ID', 'CF_API_TOKEN']], ['lane' => 'huggingface', 'needs' => ['HF_TOKEN']], ['lane' => 'groq', 'needs' => ['GROQ_API_KEY']], ['lane' => 'gemini', 'needs' => ['GEMINI_API_KEY']]]];
}
route('GET', '/hq/ai', function () {
  require_hq(); $d30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
  return ai_status() + ['as_of' => now(),
    'usage' => rows('SELECT feature, lane, kind, COUNT(*) calls, SUM(ok) succeeded, SUM(cached) from_cache, ROUND(AVG(CASE WHEN cached=0 THEN ms END)) avg_ms, MAX(created_at) last_at FROM ai_calls WHERE created_at>=? GROUP BY feature, lane, kind ORDER BY feature, lane', [$d30]),
    'recent_failures' => rows('SELECT feature, lane, error, created_at FROM ai_calls WHERE ok=0 AND created_at>=? ORDER BY id DESC LIMIT 12', [$d30])];
});
route('POST', '/hq/ai/test', function () {
  $me = require_hq(); throttle('aitest:' . (int) $me['id'], 6, 3600); throttle_hit('aitest:' . (int) $me['id']);
  $r = Ai::chat('lane_test', 'Reply with one word.', 'Reply with the single word: ready', ['class' => 'aggregate', 'max_tokens' => 8, 'ttl' => 0, 'user_id' => (int) $me['id']]);
  return ['ok' => $r['ok'], 'lane' => $r['lane'] ?? '', 'reason' => $r['reason'] ?? '', 'detail' => $r['detail'] ?? ''];
});

route('GET', '/hq/ai/report-digest', function () {
  require_hq(); $period = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['period'] ?? '')) ? $_GET['period'] : gmdate('Y-m', strtotime('first day of last month'));
  $cc = strtoupper(substr((string) ($_GET['country'] ?? ''), 0, 2)); $lang = ai_lang((string) ($_GET['lang'] ?? 'en'));
  $countries = array_map(fn($r) => ['code' => $r['code'], 'name' => country_name($r['code']) ?: $r['code'], 'reports' => (int) $r['reports']], rows('SELECT o2.country_code code, COUNT(r.id) reports FROM report_instances r JOIN org_units o2 ON o2.id=r.org_unit_id WHERE o2.country_code IS NOT NULL GROUP BY o2.country_code ORDER BY reports DESC'));
  if ($cc === '' && $countries) $cc = $countries[0]['code'];
  return ['period' => $period, 'country' => $cc, 'countries' => $countries, 'facts' => $cc !== '' ? ai_report_facts($cc, $period) : null, 'digest' => $cc !== '' ? ai_kept('report_digest', $cc, $period, $lang) : null, 'ai' => ai_status()];
});
route('POST', '/hq/ai/report-digest', function () {
  $me = require_hq(); $b = body(); $period = (string) ($b['period'] ?? ''); $cc = strtoupper(substr((string) ($b['country'] ?? ''), 0, 2));
  if (!preg_match('/^\d{4}-\d{2}$/', $period) || !country_name($cc)) fail(422, 'Choose a country and a month.', 'input');
  $r = ai_report_digest($cc, $period, ai_lang((string) ($b['lang'] ?? 'en')), (int) $me['id']);
  if ($r['ok']) log_activity((int) $me['id'], null, 'ai_report_digest', $cc . ' ' . $period);
  return $r;
});

route('GET', '/hq/ai/brief', function () {
  require_hq(); $lang = ai_lang((string) ($_GET['lang'] ?? 'en')); $period = gmdate('o-\WW');
  return ['period' => $period, 'facts' => ai_brief_facts(), 'brief' => ai_kept('weekly_brief', 'all', $period, $lang) ?: ($lang !== 'en' ? null : ai_kept('weekly_brief', 'all', $period, 'en')), 'ai' => ai_status()];
});
route('POST', '/hq/ai/brief', function () {
  $me = require_hq(); $r = ai_brief(ai_lang((string) (body()['lang'] ?? 'en')), (int) $me['id']);
  if ($r['ok']) log_activity((int) $me['id'], null, 'ai_weekly_brief', $r['period']);
  return $r;
});

route('GET', '/hq/ai/coverage-gaps', function () { require_hq(); return ai_coverage_gaps(); });

route('GET', '/library/search', function () {
  $u = require_user(); $hq = is_hq($u); $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 160);
  $list = rows('SELECT id, title, description, category, audience, kind, url, file_name, mime, origin, archived, created_at, LENGTH(bytes) size FROM library_items WHERE archived=0 ORDER BY category, title');
  $items = []; foreach ($list as $it) if (library_can($u, $it)) { $it['id'] = (int) $it['id']; $it['archived'] = false; $it['size'] = (int) $it['size']; $items[] = $it; }
  if ($query !== '') { throttle('libsearch:' . (int) $u['id'], 40, 3600); throttle_hit('libsearch:' . (int) $u['id']); }
  return ai_library_search($items, $query) + ['is_hq' => $hq, 'q' => $query];
});
route('POST', '/hq/ai/library-index', function () { require_hq(); return ['indexed' => ai_library_index(60), 'ai' => ai_status()]; });
