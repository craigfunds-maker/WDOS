<?php
declare(strict_types=1);
/* The Founder's implementation amendments of 11 November 2026: shared functions.
 * Policy rules (versioned), chapters and automatic placement, and the automated seven-day member journey.
 * Loaded before every route because joining (api/index.php) places the member and opens the journey. */

/* ---- policy rules ---------------------------------------------------------- */
const POLICY_DEFS = [
  'chapter_min_active' => ['Chapter: minimum active members', 'int', 5, 200],
  'chapter_plan_next' => ['Chapter: next-chapter planning alert from', 'int', 5, 500],
  'chapter_capacity' => ['Chapter: capacity (automatic placement stops here)', 'int', 10, 500],
  'chapter_auto_activate' => ['Chapter: on reaching the minimum', 'choice', ['eligible' => 'Mark it eligible for activation', 'active' => 'Make it active automatically']],
  'chapter_auto_create' => ['Chapter: when no chapter has room', 'choice', ['on' => 'Open the next chapter automatically', 'off' => 'Hold the member as pending placement']],
  'journey_days' => ['Member journey: length in days', 'int', 1, 30],
  'journey_unlock' => ['Member journey: how steps open', 'choice', ['daily' => 'One new step each day', 'open' => 'All steps open from the first day']],
  'journey_extension_days' => ['Member journey: automatic extension in days', 'int', 0, 60],
  'journey_required_referrals' => ['Member journey: registered referrals needed', 'int', 0, 10],
  'wnnn_country_title' => ['WNNN country leader title in Africa', 'choice', ['Country Lead' => 'Country Lead (African structure document)', 'Country Representative' => 'Country Representative (diaspora document)']],
];
const POLICY_FALLBACK = ['chapter_min_active' => '30', 'chapter_plan_next' => '40', 'chapter_capacity' => '50', 'chapter_auto_activate' => 'eligible', 'chapter_auto_create' => 'on',
  'journey_days' => '7', 'journey_unlock' => 'daily', 'journey_extension_days' => '7', 'journey_required_referrals' => '0', 'wnnn_country_title' => 'Country Lead'];
/* The six items the amendments document lists as needing a recorded management choice. */
const POLICY_DECISIONS = [
  ['wnnn_country_title', 'African WNNN country title', 'Country Lead (African structure document) or Country Representative (diaspora document). Both sources are kept; WDOS shows the one chosen here.'],
  ['chapter_auto_activate', 'What reaching 30 members does', 'Either the chapter becomes active by itself, or it is marked eligible and HQ activates it.'],
  ['chapter_capacity', 'Whether 50 is the universal maximum', 'One capacity applies everywhere today. Capacity by geography is not built yet.'],
  ['chapter_auto_create', 'Automatic chapter creation and catchment', 'Placement follows network, country, state, LGA or city, and community. Automatic splitting of a full chapter is not built: the next chapter is opened instead.'],
  ['', 'Exceptional security and safeguarding cases', 'Possible duplicate accounts are held in an exceptions list for HQ. They are never removed or sanctioned automatically.'],
  ['', 'Account recovery without OTP', 'A member with an email address resets by email link. A member who signed up with a phone number only is reset by HQ today. A tested self-service route still needs a decision.'],
];

function policy(string $key): string {
  static $cache = null;
  if ($cache === null) {
    $cache = [];
    try { foreach (rows('SELECT rule_key, rule_value FROM policy_rules WHERE effective_from<=? ORDER BY effective_from, id', [gmdate('Y-m-d')]) as $r) $cache[$r['rule_key']] = $r['rule_value']; }
    catch (PDOException $e) { $cache = []; }
  }
  return (string) ($cache[$key] ?? POLICY_FALLBACK[$key] ?? '');
}
function policy_int(string $key): int { return (int) policy($key); }

/* ---- geography ------------------------------------------------------------- */
const AFRICA_CC = ['AO','BF','BI','BJ','BW','CD','CF','CG','CI','CM','CV','DJ','DZ','EG','EH','ER','ET','GA','GH','GM','GN','GQ','GW','KE','KM','LR','LS','LY','MA','MG','ML','MR','MU','MW','MZ','NA','NE','NG','RW','SC','SD','SL','SN','SO','SS','ST','SZ','TD','TG','TN','TZ','UG','ZA','ZM','ZW'];
function is_diaspora(?string $cc): bool { return $cc !== null && $cc !== '' && !in_array($cc, AFRICA_CC, true); }

/* ---- chapters -------------------------------------------------------------- */
const CHAPTER_STATUS = ['developing' => 'Developing', 'eligible' => 'Eligible for activation', 'active' => 'Active', 'full' => 'At capacity', 'merged' => 'Merged', 'closed' => 'Closed'];
const CHAPTER_OPEN = "('developing','eligible','active')";

/** Recount a chapter and set its state from the rules in force. */
function chapter_refresh(int $id): void {
  $c = row('SELECT * FROM chapters WHERE id=?', [$id]); if (!$c || in_array($c['status'], ['merged', 'closed'], true)) return;
  $n = (int) val('SELECT COUNT(*) FROM chapter_members WHERE chapter_id=? AND ended_at IS NULL', [$id]);
  $min = policy_int('chapter_min_active'); $cap = policy_int('chapter_capacity'); $activated = $c['activated_at'];
  if ($n >= $min && !$activated && policy('chapter_auto_activate') === 'active') $activated = now();
  $status = $n >= $cap ? 'full' : ($n < $min ? 'developing' : ($activated ? 'active' : 'eligible'));
  if ($n >= $cap && !$activated) $activated = now();                       // a full chapter is by definition a working one
  q('UPDATE chapters SET members=?, status=?, activated_at=? WHERE id=?', [$n, $status, $activated, $id]);
}
function chapter_create(string $net, string $cc, string $state, string $lga, string $community, ?int $by, string $zone = ''): int {
  $seq = 1 + (int) val('SELECT COALESCE(MAX(seq),0) FROM chapters WHERE network=? AND country_code=? AND state_region=? AND lga=? AND community=?', [$net, $cc, $state, $lga, $community]);
  $place = $community !== '' ? $community : ($lga !== '' ? $lga : $state);
  $id = ins('INSERT INTO chapters (network,country_code,state_region,lga,community,zone,seq,name,status,members,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,0,?,?)',
    [$net, $cc, $state, $lga, $community, mb_substr($zone, 0, 120), $seq, mb_substr(sprintf('%s %s %03d', $place, $net, $seq), 0, 190), 'developing', $by, now()]);
  q('UPDATE chapters SET code=? WHERE id=?', [sprintf('%s-%s-%05d', $net, $cc, $id), $id]);
  return $id;
}
/** The first chapter of this catchment with room, lowest number first; a new one if the rules allow; otherwise 0. */
function chapter_with_room(string $net, string $cc, string $state, string $lga, string $community, bool $lock = false): int {
  $id = (int) val('SELECT id FROM chapters WHERE network=? AND country_code=? AND state_region=? AND lga=? AND community=? AND status IN ' . CHAPTER_OPEN . ' AND members<? ORDER BY seq LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
    [$net, $cc, $state, $lga, $community, policy_int('chapter_capacity')]);
  if ($id) return $id;
  return policy('chapter_auto_create') === 'on' ? chapter_create($net, $cc, $state, $lga, $community, null) : 0;
}
/** Why someone has no chapter yet, in words she can act on. */
function chapter_pending_reason(array $u): string {
  if (!$u['network'] || !$u['country_code']) return 'Finish your profile so WDOS can place you in a chapter.';
  if ($u['lga'] === '' && $u['community'] === '') return is_diaspora($u['country_code']) ? 'Add your city in Settings so WDOS can place you in a chapter near you.' : 'Add your LGA or district and your community in Settings so WDOS can place you in a chapter near you.';
  return 'Every chapter near you is full. You will be placed as soon as the next chapter opens. Your membership is not affected.';
}
/** Place one member automatically. Never throws: a member is never refused because of chapter capacity. */
function chapter_place_user(array $u, string $by = 'auto'): int {
  try {
    if (!$u['network'] || !$u['country_code'] || in_array($u['role'], ['hq_admin', 'super_admin'], true) || ($u['participant_class'] ?? 'member') !== 'member') return 0;
    if (!empty($u['chapter_id'])) return (int) $u['chapter_id'];
    if ($u['lga'] === '' && $u['community'] === '') return 0;
    $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
      $cid = chapter_with_room($u['network'], $u['country_code'], $u['state_region'], $u['lga'], $u['community'], true);
      if ($cid) {
        q('INSERT INTO chapter_members (chapter_id,user_id,network,placed_by,started_at) VALUES (?,?,?,?,?)', [$cid, (int) $u['id'], $u['network'], $by, now()]);
        q('UPDATE users SET chapter_id=? WHERE id=?', [$cid, (int) $u['id']]);
        chapter_refresh($cid);
      }
      if ($own) $pdo->commit();
      return $cid;
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  } catch (Throwable $e) { error_log('[wdos chapter] ' . $e->getMessage()); return 0; }
}
/** End the current chapter membership (history is kept). */
function chapter_leave(int $userId, string $reason): void {
  $cur = (int) val('SELECT chapter_id FROM users WHERE id=?', [$userId]); if (!$cur) return;
  q('UPDATE chapter_members SET ended_at=?, end_reason=? WHERE user_id=? AND ended_at IS NULL', [now(), mb_substr($reason, 0, 40), $userId]);
  q('UPDATE users SET chapter_id=NULL WHERE id=?', [$userId]);
  chapter_refresh($cur);
}
/** Move a member to a named chapter of her own network. HQ may go past capacity; automatic moves may not. */
function chapter_move(int $userId, int $toId, string $reason, string $by, bool $allowFull = false): void {
  $u = row('SELECT * FROM users WHERE id=?', [$userId]); $to = row('SELECT * FROM chapters WHERE id=?', [$toId]);
  if (!$u || !$to) fail(404, 'That member or chapter was not found.', 'not_found');
  if ($to['network'] !== $u['network']) fail(422, 'A ' . $u['network'] . ' member cannot be placed in a ' . $to['network'] . ' chapter.', 'network');
  if (in_array($to['status'], ['merged', 'closed'], true)) fail(422, 'That chapter is no longer open.', 'closed');
  if ((int) $u['chapter_id'] === $toId) return;
  if (!$allowFull && (int) $to['members'] >= policy_int('chapter_capacity')) fail(422, 'That chapter is at capacity.', 'full');
  chapter_leave($userId, $reason);
  q('INSERT INTO chapter_members (chapter_id,user_id,network,placed_by,started_at) VALUES (?,?,?,?,?)', [$toId, $userId, $u['network'], $by, now()]);
  q('UPDATE users SET chapter_id=? WHERE id=?', [$toId, $userId]);
  chapter_refresh($toId);
}
/** After a change of network or location: leave a chapter that no longer fits, then place again. */
function chapter_replace_after_change(int $userId): void {
  try {
    $u = row('SELECT * FROM users WHERE id=?', [$userId]); if (!$u) return;
    if ($u['chapter_id']) {
      $c = row('SELECT * FROM chapters WHERE id=?', [(int) $u['chapter_id']]);
      $fits = $c && $c['network'] === $u['network'] && $c['country_code'] === $u['country_code'] && mb_strtolower($c['state_region']) === mb_strtolower($u['state_region']) && mb_strtolower($c['lga']) === mb_strtolower($u['lga']) && mb_strtolower($c['community']) === mb_strtolower($u['community']);
      if ($fits) return;
      chapter_leave($userId, 'moved'); $u['chapter_id'] = null;
    }
    chapter_place_user($u);
  } catch (Throwable $e) { error_log('[wdos chapter] ' . $e->getMessage()); }
}
/** Place waiting members in bulk, a catchment at a time. Returns how many were placed. */
function chapters_backfill(int $maxChapters = 40): int {
  $placed = 0; $cap = policy_int('chapter_capacity');
  $groups = rows("SELECT network, country_code, state_region, lga, community, COUNT(*) n FROM users
                  WHERE status='active' AND chapter_id IS NULL AND network IS NOT NULL AND country_code IS NOT NULL AND participant_class='member' AND role NOT IN ('hq_admin','super_admin') AND (lga<>'' OR community<>'')
                  GROUP BY network, country_code, state_region, lga, community ORDER BY n DESC LIMIT 30");
  foreach ($groups as $g) {
    $left = (int) $g['n'];
    while ($left > 0 && $maxChapters > 0) {
      $cid = chapter_with_room($g['network'], $g['country_code'], $g['state_region'], $g['lga'], $g['community']); if (!$cid) break;
      $free = $cap - (int) val('SELECT members FROM chapters WHERE id=?', [$cid]); if ($free < 1) { chapter_refresh($cid); break; }
      $ids = array_map('intval', array_column(rows("SELECT id FROM users WHERE status='active' AND chapter_id IS NULL AND network=? AND country_code=? AND state_region=? AND lga=? AND community=? AND participant_class='member' AND role NOT IN ('hq_admin','super_admin') ORDER BY id LIMIT " . (int) $free,
        [$g['network'], $g['country_code'], $g['state_region'], $g['lga'], $g['community']]), 'id'));
      if (!$ids) break;
      $in = implode(',', $ids);
      q("INSERT INTO chapter_members (chapter_id,user_id,network,placed_by,started_at) SELECT ?, id, network, 'auto', ? FROM users WHERE id IN ($in) AND chapter_id IS NULL", [$cid, now()]);
      q("UPDATE users SET chapter_id=? WHERE id IN ($in) AND chapter_id IS NULL", [$cid]);
      chapter_refresh($cid); $placed += count($ids); $left -= count($ids); $maxChapters--;
    }
    if ($maxChapters < 1) break;
  }
  return $placed;
}
function chapter_shape(array $c): array {
  $cap = policy_int('chapter_capacity'); $n = (int) $c['members'];
  return ['id' => (int) $c['id'], 'code' => $c['code'], 'name' => $c['name'], 'network' => $c['network'], 'country_code' => $c['country_code'], 'country' => country_name($c['country_code']),
    'state_region' => $c['state_region'], 'lga' => $c['lga'], 'community' => $c['community'], 'zone' => $c['zone'], 'seq' => (int) $c['seq'], 'status' => $c['status'], 'status_label' => CHAPTER_STATUS[$c['status']] ?? $c['status'],
    'members' => $n, 'capacity' => $cap, 'minimum' => policy_int('chapter_min_active'), 'plan_next' => $n >= policy_int('chapter_plan_next') && $n < $cap, 'diaspora' => is_diaspora($c['country_code']), 'created_at' => $c['created_at'], 'activated_at' => $c['activated_at']];
}
/** What this person may see of a chapter: 'hq', 'leader' (roster and tools), 'member' (her own chapter), or '' (nothing). */
function chapter_access(array $u, array $c): string {
  if (is_hq($u)) return 'hq';
  $seat = current_seat((int) $u['id']);
  if ($seat && $seat['network'] === $c['network']) {
    if ((int) ($seat['chapter_id'] ?? 0) === (int) $c['id']) return 'leader';
    $lvl = $seat['position_level']; $same = fn($a, $b) => $a !== '' && mb_strtolower((string) $a) === mb_strtolower((string) $b);
    if ($seat['unit_country'] === $c['country_code'] && empty($seat['chapter_id'])) {
      if ($lvl === 'country') return 'leader';
      if ($lvl === 'state_region' && $same($seat['unit_name'], $c['state_region'])) return 'leader';
      if ($lvl === 'lga' && $same($seat['area_label'], $c['lga'])) return 'leader';
      if ($lvl === 'community_cluster' && $same($seat['area_label'], $c['community'])) return 'leader';
      if ($lvl === 'chapter' && ($same($seat['area_label'], $c['name']) || $same($seat['area_label'], $c['code']))) return 'leader';
    }
  }
  return (int) ($u['chapter_id'] ?? 0) === (int) $c['id'] ? 'member' : '';
}
/** SQL that limits chapters to a leader's own scope. */
function chapter_scope_sql(array $seat): array {
  if (!empty($seat['chapter_id'])) return ['c.id=?', [(int) $seat['chapter_id']]];
  $w = 'c.network=? AND c.country_code=?'; $p = [$seat['network'], (string) $seat['unit_country']];
  switch ($seat['position_level']) {
    case 'country': break;
    case 'state_region': $w .= ' AND c.state_region=?'; $p[] = $seat['unit_name']; break;
    case 'lga': $w .= ' AND c.lga=?'; $p[] = $seat['area_label']; break;
    case 'community_cluster': $w .= ' AND c.community=?'; $p[] = $seat['area_label']; break;
    default: $w .= ' AND (c.name=? OR c.code=?)'; $p[] = $seat['area_label']; $p[] = $seat['area_label'];
  }
  return [$w, $p];
}

/* ---- the seven-day member journey ------------------------------------------- */
/* Day titles and what each day does follow the table in section 3 of the amendments document.
 * The sentences a member reads are working text: they need the Founder's approval and reviewed translations. */
const MJ_DAYS = [
  1 => ['Welcome to WODDI', 'auto', 'Your account is created and your membership number is ready. Your seven days start today. Each day there is one short step. You can read it or press Listen.'],
  2 => ['Know WODDI', 'read', 'WODDI brings women together to grow, lead and serve, so that no woman is left out. Your network is your home in WODDI. Today, read or listen to this short introduction, then press Done.'],
  3 => ['Invite three women', 'referral', 'WODDI grows when each woman brings three more. Share your own link with three women you know. You can see here how many opened your link, how many registered, and how many completed their seven days.'],
  4 => ['Meet your chapter', 'chapter', 'A chapter is your local WODDI family of 30 to 50 women in your own network. WDOS places you in a chapter near you by itself. Today, see your chapter and its space.'],
  5 => ['A few questions', 'screening', 'Answer these short questions yourself. There is no phone call and nobody needs to approve you. If an answer needs correcting, WDOS tells you what to do and you can answer again.'],
  6 => ['Learn and serve', 'read', 'The WODDI Institute offers learning for every member, and volunteers help their chapter and community. Today, read or listen to how you can learn and serve, and finish any step you missed.'],
  7 => ['Your result', 'final', 'WDOS checks your seven days by itself. If everything is complete you become an active member straight away. If something is missing, WDOS shows you exactly what, and you can finish it and check again.'],
];
const MJ_QUESTIONS = [
  'own_name' => ['Is the name on your profile your own name?', 'yes', 'Open Settings, correct your name, then answer again.'],
  'location' => ['Is the place on your profile where you live now?', 'yes', 'Open Settings, correct your LGA, city or community, then answer again.'],
  'conduct' => ['Do you agree to follow the WODDI Code of Conduct?', 'yes', 'You can read the Code of Conduct in the Document library. Membership needs your agreement to it.'],
  'only_account' => ['Is this your only WDOS account?', 'yes', 'Please sign in to your first account instead of this one. If you cannot, send a message to WODDI HQ.'],
];

function mj_start(int $userId): void {
  try {
    if (val('SELECT 1 FROM member_journeys WHERE user_id=?', [$userId])) return;
    $deadline = gmdate('Y-m-d', time() + policy_int('journey_days') * 86400);
    q("INSERT INTO member_journeys (user_id,status,started_at,deadline) VALUES (?,'in_progress',?,?)", [$userId, now(), $deadline]);
    q('INSERT INTO member_journey_steps (user_id,day_no,done_at) VALUES (?,1,?)', [$userId, now()]);
  } catch (Throwable $e) { error_log('[wdos journey] ' . $e->getMessage()); }
}
/** A member's own invitation link (made the first time it is needed) and what came of it. */
function mj_referrals(array $u): array {
  $l = row("SELECT * FROM referral_links WHERE owner_id=? AND kind='member' AND position_code='' AND network=? ORDER BY id LIMIT 1", [(int) $u['id'], (string) $u['network']]);
  if (!$l && $u['network']) {
    $id = ins("INSERT INTO referral_links (code,owner_id,kind,network,org_unit_id,area_label,label,created_at) VALUES (?,?,'member',?,?,?,?,?)", [ref_new_code(), (int) $u['id'], $u['network'], $u['org_unit_id'] ? (int) $u['org_unit_id'] : null, '', 'My invitation link', now()]);
    $l = row('SELECT * FROM referral_links WHERE id=?', [$id]);
  }
  if (!$l) return ['code' => '', 'opened' => 0, 'registered' => 0, 'verified' => 0, 'target' => 3];
  return ['code' => $l['code'], 'target' => 3,
    'opened' => (int) val('SELECT COUNT(*) FROM referral_visits v JOIN referral_links l ON l.id=v.link_id WHERE l.owner_id=?', [(int) $u['id']]),
    'registered' => (int) val('SELECT COUNT(*) FROM referrals WHERE referrer_id=?', [(int) $u['id']]),
    'verified' => (int) val("SELECT COUNT(*) FROM referrals r JOIN member_journeys j ON j.user_id=r.user_id WHERE r.referrer_id=? AND j.status='active'", [(int) $u['id']])];
}
function mj_open_days(array $j): int {
  $total = count(MJ_DAYS);
  if (policy('journey_unlock') === 'open') return $total;
  return max(1, min($total, intdiv(time() - strtotime($j['started_at'] . ' UTC'), 86400) + 1));
}
/** Other accounts that look like the same person: same phone and date of birth, or same full name, date of birth and country. */
function mj_duplicates(array $u): int {
  $n = 0;
  if ($u['phone'] !== '' && $u['dob']) $n += (int) val("SELECT COUNT(*) FROM users WHERE id<>? AND phone=? AND dob=? AND status<>'pending_profile'", [(int) $u['id'], $u['phone'], $u['dob']]);
  if ($u['dob']) $n += (int) val("SELECT COUNT(*) FROM users WHERE id<>? AND dob=? AND first_name=? AND last_name=? AND country_code=? AND status<>'pending_profile'", [(int) $u['id'], $u['dob'], $u['first_name'], $u['last_name'], (string) $u['country_code']]);
  return $n;
}
/** The automatic outcome. No person approves it. Nothing here removes or sanctions anyone. */
function mj_decide(array $u): array {
  $j = row('SELECT * FROM member_journeys WHERE user_id=?', [(int) $u['id']]); if (!$j) return ['status' => 'none', 'reasons' => []];
  if ($j['status'] === 'active') return ['status' => 'active', 'reasons' => []];
  $done = []; foreach (rows('SELECT day_no, answers FROM member_journey_steps WHERE user_id=?', [(int) $u['id']]) as $s) $done[(int) $s['day_no']] = (string) $s['answers'];
  $reasons = [];
  foreach (MJ_DAYS as $n => $d) if ($n < 7 && !isset($done[$n])) $reasons[] = ['day' => $n, 'text' => "Day $n is not finished yet: " . $d[0] . '.'];
  if (isset($done[5])) { $a = json_decode((string) $done[5], true) ?: []; foreach (MJ_QUESTIONS as $k => $qq) if (($a[$k] ?? '') !== $qq[1]) $reasons[] = ['day' => 5, 'text' => $qq[2]]; }
  $need = policy_int('journey_required_referrals');
  if ($need > 0 && ($have = mj_referrals($u)['registered']) < $need) $reasons[] = ['day' => 3, 'text' => "You need $need registered referrals. You have $have so far. Keep sharing your link."];
  $dup = !$reasons && mj_duplicates($u) > 0;
  $status = $reasons ? 'retry' : ($dup ? 'review' : 'active');
  if ($dup) $reasons[] = ['day' => 0, 'text' => 'WDOS found another account with the same details as yours. Nothing has been removed. WODDI HQ will look at this and you can keep using WDOS meanwhile.'];
  q('UPDATE member_journeys SET status=?, reasons=?, attempts=attempts+1, decided_at=? WHERE user_id=?', [$status, json_encode($reasons, JSON_UNESCAPED_UNICODE), now(), (int) $u['id']]);
  if ($status === 'active') {
    q('INSERT IGNORE INTO member_journey_steps (user_id,day_no,done_at) VALUES (?,7,?)', [(int) $u['id'], now()]);
    notify((int) $u['id'], 'activation', 'You are now an active WODDI member', 'You finished your seven days, ' . $u['first_name'] . '. Welcome. Your chapter and your network are open to you.', '#/journey');
    log_activity((int) $u['id'], (int) $u['id'], 'member_activated', 'Automatic seven-day outcome');
    $ref = (int) val('SELECT referrer_id FROM referrals WHERE user_id=?', [(int) $u['id']]);
    if ($ref) notify($ref, 'activation', $u['first_name'] . ' completed the seven days', 'A woman you invited is now an active member. This counts as a verified referral.', '#/journey');
  }
  return ['status' => $status, 'reasons' => $reasons];
}
function mj_state(array $u): array {
  $j = row('SELECT * FROM member_journeys WHERE user_id=?', [(int) $u['id']]);
  if (!$j) return ['journey' => null];
  $done = []; foreach (rows('SELECT day_no, answers, done_at FROM member_journey_steps WHERE user_id=?', [(int) $u['id']]) as $s) $done[(int) $s['day_no']] = $s;
  $open = mj_open_days($j); $days = [];
  foreach (MJ_DAYS as $n => $d) $days[] = ['day' => $n, 'title' => $d[0], 'kind' => $d[1], 'text' => $d[2], 'done' => isset($done[$n]), 'done_at' => $done[$n]['done_at'] ?? null, 'open' => $n <= $open,
    'opens_on' => gmdate('Y-m-d', strtotime($j['started_at'] . ' UTC') + ($n - 1) * 86400), 'answers' => $n === 5 && isset($done[5]) ? (json_decode((string) $done[5]['answers'], true) ?: null) : null];
  $ch = $u['chapter_id'] ? row('SELECT * FROM chapters WHERE id=?', [(int) $u['chapter_id']]) : null;
  return ['journey' => ['status' => $j['status'], 'started_at' => $j['started_at'], 'deadline' => $j['deadline'], 'extensions' => (int) $j['extensions'], 'attempts' => (int) $j['attempts'], 'reasons' => json_decode((string) $j['reasons'], true) ?: [],
      'done' => count($done), 'total' => count(MJ_DAYS), 'open_days' => $open],
    'days' => $days, 'questions' => array_map(fn($k, $v) => ['key' => $k, 'text' => $v[0]], array_keys(MJ_QUESTIONS), MJ_QUESTIONS),
    'referrals' => mj_referrals($u), 'chapter' => $ch ? chapter_shape($ch) : null, 'chapter_pending' => $ch ? '' : chapter_pending_reason($u)];
}
/** Daily: one reminder for unfinished open steps, the automatic final check, and the automatic extension. Nothing waits for a person. */
function mj_daily(): string {
  $today = gmdate('Y-m-d'); $rem = 0; $dec = 0; $ext = 0;
  foreach (rows("SELECT j.*, u.first_name FROM member_journeys j JOIN users u ON u.id=j.user_id WHERE j.status IN ('in_progress','retry') AND u.status='active' ORDER BY j.started_at LIMIT 2000") as $j) {
    $uid = (int) $j['user_id']; $done = (int) val('SELECT COUNT(*) FROM member_journey_steps WHERE user_id=? AND day_no<7', [$uid]); $open = mj_open_days($j);
    if ($done >= 6 && $open >= 7 && $j['status'] === 'in_progress') { mj_decide(row('SELECT * FROM users WHERE id=?', [$uid])); $dec++; continue; }
    if ($j['deadline'] < $today) {
      $more = policy_int('journey_extension_days');
      if ((int) $j['extensions'] < 1 && $more > 0) {
        q('UPDATE member_journeys SET deadline=?, extensions=extensions+1 WHERE user_id=?', [gmdate('Y-m-d', time() + $more * 86400), $uid]);
        notify($uid, 'reminder', 'You have more time for your seven days', $j['first_name'] . ", WDOS has given you $more more days by itself. Open your journey and continue where you stopped.", '#/journey'); $ext++;
      }
      continue;                                                                // after the extension she can still finish at any time; nothing is closed
    }
    if ($done < min(6, $open) && $j['reminded_on'] !== $today) {
      q('UPDATE member_journeys SET reminded_on=? WHERE user_id=?', [$today, $uid]);
      notify($uid, 'reminder', 'Your next WODDI step is ready', $j['first_name'] . ', one short step is waiting in your seven-day journey. You can read it or listen to it.', '#/journey'); $rem++;
    }
  }
  return "$rem reminded, $dec decided, $ext extended";
}
/** Runs with the five-minute tick: the daily journey work, and bulk placement of members still waiting for a chapter. */
function amend_automations(array &$done): void {
  try {
    $r = cron_once('member_journey', 'mj_daily'); if ($r !== null) $done['member_journey'] = $r;
    $n = chapters_backfill(25); if ($n) $done['chapter_placement'] = "$n placed";
  } catch (Throwable $e) { error_log('[wdos amend] ' . $e->getMessage()); }
}
/** Short facts about a person's chapter and journey for the app shell. Safe before the migration has run. */
function amend_brief(array $u): array {
  try {
    $c = !empty($u['chapter_id']) ? row('SELECT id, name, code, status, members FROM chapters WHERE id=?', [(int) $u['chapter_id']]) : null;
    $j = row('SELECT status FROM member_journeys WHERE user_id=?', [(int) $u['id']]);
    return ['chapter' => $c ? ['id' => (int) $c['id'], 'name' => $c['name'], 'code' => $c['code'], 'status' => $c['status'], 'members' => (int) $c['members']] : null,
      'journey' => $j ? ['status' => $j['status'], 'done' => (int) val('SELECT COUNT(*) FROM member_journey_steps WHERE user_id=?', [(int) $u['id']]), 'total' => count(MJ_DAYS)] : null,
      'lang' => $u['lang'] ?? 'en', 'diaspora' => is_diaspora($u['country_code'])];
  } catch (PDOException $e) { return ['chapter' => null, 'journey' => null, 'lang' => 'en', 'diaspora' => false]; }
}

/* ---- announcements through every level ------------------------------------- */
/** Build the audience from the request. Leaders are confined to their own scope; chapter leaders to their own chapter. */
function audience_from_request(array $me): array {
  $b = body(); $hq = is_hq($me); $seat = $hq ? null : current_seat((int) $me['id']);
  if (!$hq && !$seat) fail(403, 'Announcements are sent by volunteer leaders and WODDI HQ.', 'forbidden');
  $a = ['network' => 'ALL', 'role' => 'all', 'country' => null, 'state' => '', 'lga' => '', 'community' => '', 'chapter' => null, 'lang' => ''];
  if (in_array($b['network'] ?? '', ['WGMN', 'WNNN'], true)) $a['network'] = $b['network'];
  if (($b['role'] ?? '') === 'leaders') $a['role'] = 'leaders';
  if (preg_match('/^[A-Z]{2}$/', (string) ($b['country'] ?? ''))) $a['country'] = $b['country'];
  $a['state'] = str_in('state', 120); $a['lga'] = str_in('lga', 120); $a['community'] = str_in('community', 120);
  if ((int) ($b['chapter'] ?? 0)) $a['chapter'] = (int) $b['chapter'];
  if (preg_match('/^[a-z]{2,3}$/', (string) ($b['lang'] ?? ''))) $a['lang'] = $b['lang'];
  if ($seat) {                                              // a leader's audience can only narrow inside her own scope
    $a['network'] = $seat['network']; $a['country'] = $seat['unit_country']; $lvl = $seat['position_level'];
    if (!empty($seat['chapter_id'])) $a['chapter'] = (int) $seat['chapter_id'];
    elseif ($lvl === 'state_region') $a['state'] = $seat['unit_name'];
    elseif ($lvl === 'lga') $a['lga'] = $seat['area_label'];
    elseif ($lvl === 'community_cluster') $a['community'] = $seat['area_label'];
    elseif ($lvl === 'chapter') fail(422, 'Your seat is not linked to a chapter yet. Ask WODDI HQ to link it.', 'no_chapter');
    if ($a['chapter'] && empty($seat['chapter_id'])) { $c = row('SELECT * FROM chapters WHERE id=?', [$a['chapter']]); if (!$c || chapter_access($me, $c) !== 'leader') fail(403, 'That chapter is outside your area.', 'forbidden'); }
  }
  $w = ["u.status='active'"]; $p = [];
  if ($a['network'] !== 'ALL') { $w[] = 'u.network=?'; $p[] = $a['network']; }
  if ($a['role'] === 'leaders') $w[] = 'EXISTS (SELECT 1 FROM seats s WHERE s.user_id=u.id AND s.ends_at IS NULL)';
  if ($a['country']) { $w[] = 'u.country_code=?'; $p[] = $a['country']; }
  if ($a['state'] !== '') { $w[] = 'u.state_region=?'; $p[] = $a['state']; }
  if ($a['lga'] !== '') { $w[] = 'u.lga=?'; $p[] = $a['lga']; }
  if ($a['community'] !== '') { $w[] = 'u.community=?'; $p[] = $a['community']; }
  if ($a['chapter']) { $w[] = 'u.chapter_id=?'; $p[] = $a['chapter']; }
  if ($a['lang'] !== '') { $w[] = 'u.lang=?'; $p[] = $a['lang']; }
  return [$a, implode(' AND ', $w), $p];
}
function announcement_send(array $me): array {
  $title = str_in('title', 160); $text = str_in('body', 4000);
  if (mb_strlen($title) < 3 || mb_strlen($text) < 3) fail(422, 'Write a title and a message.', 'empty');
  [$a, $w, $p] = audience_from_request($me);
  $tone = in_array(body()['tone'] ?? '', ['celebration', 'gratitude', 'courage', 'calm', 'growth', 'purpose'], true) ? body()['tone'] : '';
  $id = ins('INSERT INTO announcements (author_id,title,body,audience_network,audience_role,audience_country,audience_state,audience_lga,audience_community,audience_chapter,audience_lang,tone,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    [(int) $me['id'], $title, $text, $a['network'], $a['role'], $a['country'], $a['state'], $a['lga'], $a['community'], $a['chapter'], $a['lang'], $tone, now()]);
  $n = notify_many($w, $p, 'announcement', $title, mb_substr($text, 0, 240), '#/announcements/' . $id);
  q('UPDATE announcements SET recipients=? WHERE id=?', [$n, $id]);
  log_activity((int) $me['id'], null, 'announcement', $title);
  after_response(fn() => push_drain(60));
  // "sent" means WDOS queued it for each person. It does not mean it was read.
  return ['ok' => true, 'id' => $id, 'recipients' => $n, 'push_queued' => (int) val("SELECT COUNT(*) FROM push_outbox WHERE status='queued'")];
}
