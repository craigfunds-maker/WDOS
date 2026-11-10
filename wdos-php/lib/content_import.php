<?php
declare(strict_types=1);
/* Programme content from the current WDOS: onboarding steps, activation days, the readiness assessment and the
 * full inspiration message texts. Every table that arrives is kept as it came (content_staging) and reported with
 * its columns, so anything this loader does not recognise can be mapped exactly, not guessed. */

function ci_pick(array $r, array $keys, $default = null) { foreach ($keys as $k) if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== '') return $r[$k]; return $default; }
function ci_list($v): array { if (is_string($v)) $v = json_decode($v, true); return is_array($v) ? array_values($v) : []; }
function ci_doc(string $url): string {
  if ($url === '') return '';
  $base = basename((string) parse_url($url, PHP_URL_PATH));
  return is_file(__DIR__ . '/../assets/docs/' . $base) ? '/assets/docs/' . $base : $url;
}
function ci_route(array $r, string $tbl): ?string {
  $v = strtolower((string) ci_pick($r, ['route', 'route_code', 'route_key', 'journey', 'programme', 'program', 'track', 'pathway'], $tbl));
  if (str_contains($v, 'ref') || preg_match('/(^|\D)3(\D|$)/', $v)) return 'ref3';
  if (str_contains($v, 'open') || preg_match('/(^|\D)7(\D|$)/', $v)) return 'open7';
  return null;
}

function content_import(array $tables): array {
  $report = [];
  foreach ($tables as $tbl => $list) {
    $tbl = mb_substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) $tbl)), 0, 80); $list = array_values(array_filter(is_array($list) ? $list : [], 'is_array'));
    $cols = $list ? array_keys($list[0]) : []; $note = 'kept, not recognised'; $n = 0;
    q('DELETE FROM content_staging WHERE tbl=?', [$tbl]);
    foreach (array_chunk($list, 50) as $chunk) q('INSERT INTO content_staging (tbl,data,loaded_at) VALUES (?,?,?)', [$tbl, json_encode($chunk, JSON_UNESCAPED_UNICODE), now()]);
    $has = fn(string ...$k) => (bool) array_intersect($k, $cols);

    if (str_contains($tbl, 'onboarding') && $has('title') && $has('code', 'step_code', 'key', 'slug')) {
      q("DELETE FROM onboarding_steps WHERE origin='starter'");
      foreach ($list as $i => $r) {
        $code = mb_substr(preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ci_pick($r, ['code', 'step_code', 'key', 'slug']))), 0, 40); if ($code === '') continue;
        $doc = ci_doc((string) ci_pick($r, ['doc_url', 'document_url', 'doc', 'url', 'document'], '')); $href = (string) ci_pick($r, ['href', 'route', 'page', 'link'], '');
        $mode = strtolower((string) ci_pick($r, ['mode', 'kind', 'type', 'step_type'], '')); $mode = in_array($mode, ['read', 'visit', 'declare'], true) ? $mode : ($doc !== '' ? 'read' : ($href !== '' ? 'visit' : 'declare'));
        q('DELETE FROM onboarding_steps WHERE code=?', [$code]);
        q("INSERT INTO onboarding_steps (code,seq,title,detail,mode,doc_url,href,min_seconds,required,is_active,origin) VALUES (?,?,?,?,?,?,?,?,?,?,'current')",
          [$code, (int) ci_pick($r, ['seq', 'position', 'sort', 'sort_order', 'order_no', 'step_no', 'ord'], $i + 1), mb_substr((string) $r['title'], 0, 190), mb_substr((string) ci_pick($r, ['detail', 'description', 'body', 'hint'], ''), 0, 600), $mode, mb_substr($doc, 0, 255), mb_substr($href, 0, 120),
           (int) ci_pick($r, ['min_seconds', 'min_secs', 'seconds'], 20), ci_pick($r, ['required', 'is_required'], true) ? 1 : 0, ci_pick($r, ['is_active', 'active', 'enabled'], true) ? 1 : 0]);
        $n++;
      }
      $note = "onboarding steps loaded: $n";
    } elseif (str_contains($tbl, 'day') && $has('day', 'day_no', 'day_number') && $has('title')) {
      $skipped = 0;
      foreach ($list as $r) {
        $route = ci_route($r, $tbl); $day = (int) ci_pick($r, ['day', 'day_no', 'day_number'], 0);
        if (!$route || $day < 1 || $day > ACT_ROUTES[$route]['days']) { $skipped++; continue; }
        $content = array_map(function ($c) { if (is_string($c)) $c = ['doc' => $c]; if (isset($c['doc'])) $c['doc'] = ci_doc((string) $c['doc']); return $c; }, ci_list(ci_pick($r, ['content', 'documents', 'docs', 'reading'], [])));
        $qs = []; foreach (ci_list(ci_pick($r, ['questions', 'prompts'], [])) as $i => $x) { $qs[] = is_array($x) ? ['key' => (string) ($x['key'] ?? 'q' . ($i + 1)), 'label' => (string) ($x['label'] ?? $x['question'] ?? $x['text'] ?? '')] : ['key' => 'q' . ($i + 1), 'label' => (string) $x]; }
        $acks = array_map(fn($x) => is_array($x) ? (string) ($x['text'] ?? $x['label'] ?? '') : (string) $x, ci_list(ci_pick($r, ['acks', 'acknowledgements', 'acknowledgments', 'confirmations'], [])));
        q('DELETE FROM activation_days WHERE route=? AND day_no=?', [$route, $day]);
        q("INSERT INTO activation_days (route,day_no,title,instruction,content,notice,questions,acks,origin) VALUES (?,?,?,?,?,?,?,?,'current')", [$route, $day, mb_substr((string) $r['title'], 0, 190), mb_substr((string) ci_pick($r, ['instruction', 'instructions', 'intro', 'description'], ''), 0, 1000),
          json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), mb_substr((string) ci_pick($r, ['notice', 'note'], ''), 0, 1000), json_encode($qs, JSON_UNESCAPED_UNICODE), json_encode($acks, JSON_UNESCAPED_UNICODE)]);
        $n++;
      }
      $note = "activation days loaded: $n" . ($skipped ? ", $skipped not for the 3-Day or 7-Day route" : '');
    } elseif (str_contains($tbl, 'readiness') && $has('statement', 'question', 'confirmation', 'text', 'body')) {
      $kind = str_contains($tbl, 'statement') ? 'statement' : (str_contains($tbl, 'question') ? 'question' : (str_contains($tbl, 'confirm') ? 'confirmation' : ''));
      $cleared = [];
      foreach ($list as $i => $r) {
        $k = $kind ?: strtolower((string) ci_pick($r, ['kind', 'type', 'item_type'], isset($r['statement']) ? 'statement' : (isset($r['question']) ? 'question' : (isset($r['confirmation']) ? 'confirmation' : ''))));
        if (!in_array($k, ['statement', 'question', 'confirmation'], true)) continue;
        $text = (string) ci_pick($r, [$k, 'text', 'body', 'label'], ''); if ($text === '') continue;
        if (!isset($cleared[$k])) { q('DELETE FROM readiness_items WHERE kind=?', [$k]); $cleared[$k] = true; }
        q('INSERT INTO readiness_items (kind,seq,section,section_title,body) VALUES (?,?,?,?,?)', [$k, (int) ci_pick($r, ['seq', 'position', 'sort', 'sort_order', 'no', 'number'], $i + 1), mb_substr((string) ci_pick($r, ['section', 'section_key', 'section_code'], ''), 0, 4), mb_substr((string) ci_pick($r, ['section_title', 'section_name'], ''), 0, 190), mb_substr($text, 0, 1500)]);
        $n++;
      }
      $note = "readiness assessment items loaded: $n";
    } elseif ((str_contains($tbl, 'message') || str_contains($tbl, 'inspiration')) && $has('body', 'message', 'full_text', 'content')) {
      foreach ($list as $r) {
        $body = trim((string) ci_pick($r, ['body', 'message', 'full_text', 'content'], '')); if ($body === '') continue;
        $seq = (int) ci_pick($r, ['seq', 'week_no', 'week', 'month_no', 'number', 'no'], 0); $net = strtoupper((string) ci_pick($r, ['network', 'network_code'], ''));
        $kind = strtolower((string) ci_pick($r, ['kind', 'type', 'cadence'], str_contains($tbl, 'month') ? 'monthly' : 'weekly')); $kind = str_contains($kind, 'month') ? 'monthly' : 'weekly';
        $head = trim((string) ci_pick($r, ['headline', 'title', 'subject'], ''));
        $hit = 0;
        if ($head !== '') $hit = q('UPDATE hq_messages SET body=? WHERE headline=?', [$body, $head])->rowCount();
        if (!$hit && $seq > 0) $hit = in_array($net, ['WGMN', 'WNNN'], true) ? q('UPDATE hq_messages SET body=? WHERE kind=? AND seq=? AND network=?', [$body, $kind, $seq, $net])->rowCount() : 0;
        $n += $hit ? 1 : 0;
      }
      $note = "message texts matched to loaded messages: $n of " . count($list);
    }
    $report[] = ['table' => $tbl, 'rows' => count($list), 'columns' => $cols, 'result' => $note];
  }
  return $report;
}
