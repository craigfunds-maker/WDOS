<?php
declare(strict_types=1);
/* v109.46: the WODDI 3-Day and 7-Day Leadership Activation Framework, as WDOS runs it (Azeez, 22 Nov 2026).
 *
 * Decided with Azeez on 22 Nov 2026:
 *   - Activation stays BEFORE appointment, as screening. HQ appoints only a candidate who has passed and whose evidence HQ has verified.
 *   - A woman who came by any referral (a State Envoy's link, a Country Representative's or Deputy's link, any leader's link, or a person
 *     she names) does the 3-Day route: Days 1 to 3. Only a woman who applied with no referral does the 7-Day route: Days 1 to 7.
 *   - Leaders already seated do no activation.
 *
 * Days 1 to 3 (100 points): appointment acceptance, consent and profile 20; WDOS access 20; orientation and role understanding 30;
 * leadership communication 20; immediate priorities 10. Pass: 80 or more AND the three mandatory gates (consent, WDOS access, a confirmed
 * reporting relationship). A gate missing means Pending Corrections even at 80 or more.
 * Days 4 to 7 (100 points): coverage and vacancy mapping 20; quality referrals and mobilisation 20; communication and collaboration 20;
 * action plan 20; WDOS reporting and compliance 20. Pass: 80 or more, verified evidence, no critical compliance breach, Day 7 report in.
 * Referrals are scored for quality, verification and documented effort, never for appointments completed within seven days.
 *
 * Each task keeps: owner (the candidate), due date, required evidence, status, submission time, reviewer, score, remarks, resubmissions.
 * Statuses: Not Started, In Progress, Submitted, Pending Corrections, Verified, Requiring Support; the activation ends Activated.
 * WDOS suggests a score from the evidence (quiz marks, completeness); HQ's score replaces it when HQ reviews the task. */

const FW_PASS = 80;
const FW_STATUS = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'submitted' => 'Submitted', 'pending_corrections' => 'Pending Corrections', 'verified' => 'Verified', 'requiring_support' => 'Requiring Support', 'activated' => 'Activated'];
/* key => [day, stage, criterion, points, title, what she does, evidence she gives, gate?] */
const FW_TASKS = [
  'consent' => [1, 'A', 'Appointment acceptance, consent and profile', 20, 'Day 1 · Acceptance and onboarding', 'Accept your nomination, give your consent, check your profile, and acknowledge your activation kit and role description.', 'Signed consent, a complete profile, acknowledgement of the kit', 'consent'],
  'access' => [2, 'A', 'WDOS access activated and tested', 20, 'Day 2 · WDOS access', 'Sign in to WDOS and open your activation. WDOS records your verified sign-in by itself.', 'Verified sign-in (recorded by WDOS)', 'access'],
  'orientation' => [2, 'A', 'Orientation and role understanding', 30, 'Day 2 · Orientation', 'Read the orientation on WODDI, your role, your reporting line and the communication flow, then answer the 10 orientation questions.', 'Orientation acknowledged and quiz result', ''],
  'contact' => [3, 'A', 'Leadership communication established', 20, 'Day 3 · Leadership connection', 'Contact your reporting officer and, where there is one, your assistant or counterpart. Tell us how and what you agreed. Your reporting officer confirms it in WDOS.', 'Confirmed contact with your reporting officer', 'reporting'],
  'priorities' => [3, 'A', 'Immediate priorities submitted', 10, 'Day 3 · Immediate priorities', 'Write your three immediate priorities and any vacancies you already know of in your area.', 'Initial checklist and priorities', ''],
  'coverage' => [4, 'B', 'Accurate leadership coverage and vacancy mapping', 20, 'Day 4 · Coverage mapping', 'Review the leaders and vacant seats in the area you would serve (WDOS shows them below). Say what is right, what is missing and what needs correcting.', 'Leadership coverage and vacancy report', ''],
  'referrals' => [5, 'B', 'Quality referrals and mobilisation activities', 20, 'Day 5 · Referrals and mobilisation', 'Begin quality referrals for the vacant seats and identify prospective members. Record each person and how far screening has gone. Quality counts, not numbers.', 'Referral register with screening status, and engagement evidence', ''],
  'engagement' => [6, 'B', 'Communication and collaboration', 20, 'Day 6 · Engagement', 'Connect with the leaders and collaborators in your area. Record each contact: who, how, when and the outcome.', 'Communication and engagement log', ''],
  'plan' => [6, 'B', 'Practical role-appropriate action plan', 20, 'Day 6 · Action plan', 'Agree your priorities with the leaders you contacted and write a short, realistic action plan: what, by when, with whom.', 'Short action plan', ''],
  'report' => [7, 'B', 'WDOS reporting and compliance assessment', 20, 'Day 7 · Report and assessment', 'Submit your first report on Days 1 to 7 with any supporting document, and answer the 6 compliance questions.', 'WDOS report, supporting documents and assessment result', 'report'],
];
const FW_ORIENTATION = [
  ['WODDI and its networks', 'WODDI exists so that No Woman Is Left Out. The WODDI Good Mother Network (WGMN) is for women aged 30 and above. The WODDI Nurture Next-Gen Network (WNNN) is for young women aged 18 to 29. Both run under one WDOS with their own titles: WGMN uses Coordinator, WNNN uses Lead.'],
  ['The structure', 'Level 0 is WODDI HQ. Then Country Representative and Deputy (WNNN: Country Lead and Deputy), State or Regional, LGA or District, Ward or Community Cluster, and Chapter. Deputies and assistants support their principal within the permissions HQ approves. State Envoys support both state teams with engagement and referrals; they are not supervisors.'],
  ['Who reports to whom', 'Reports go up: Chapter to Community Cluster to LGA or District to State or Region to Country to HQ. Directives come down the same line. Leaders at the same level may coordinate, without bypassing the approved line or the controls on appointments and spending. A confidential complaint or an urgent safeguarding concern can go straight to HQ.'],
  ['WDOS', 'WDOS is where you work: your dashboard, assigned tasks, evidence uploads, referrals, meetings and reports. Every task and report you submit is recorded with its date, so your work is visible and credited.'],
  ['Volunteer compliance', 'As a WODDI volunteer you must not fundraise without authorisation, charge dues or levies, open local bank accounts, sign contracts or make external commitments in WODDI\'s name. HQ alone approves appointments and spending.'],
];
/* [question, options, the right option] */
const FW_QUIZ_A = [
  ['What is WODDI\'s slogan?', ['No Woman Is Left Out', 'Women First, Always', 'Together We Rise'], 0],
  ['Which network is for women aged 30 and above?', ['WNNN', 'WGMN', 'Both'], 1],
  ['WNNN leaders below country level are called…', ['Coordinators', 'Leads', 'Representatives'], 1],
  ['In what order do reports go up?', ['Chapter, Community Cluster, LGA or District, State or Region, Country, HQ', 'Chapter straight to HQ', 'State, Chapter, Country, HQ'], 0],
  ['A State Envoy is…', ['the supervisor of the State Coordinator', 'a complementary role that supports both state teams, not a supervisor', 'a member of HQ staff'], 1],
  ['Deputies and assistants…', ['support their principal within approved permissions', 'approve appointments', 'replace the principal whenever they wish'], 0],
  ['A confidential complaint or an urgent safeguarding concern…', ['must go through every level first', 'can go straight to WODDI HQ', 'should be posted in the chapter group'], 1],
  ['Working with a leader at the same level is…', ['not allowed', 'allowed for approved coordination, without bypassing the approved line', 'a way to skip your supervisor'], 1],
  ['Where do you submit tasks, evidence, referrals and reports?', ['In WDOS', 'On WhatsApp only', 'By phone call to HQ'], 0],
  ['Who makes a leadership appointment final?', ['The person who referred you', 'Your chapter', 'WODDI HQ, after screening and approval'], 2],
];
const FW_QUIZ_B = [
  ['May a volunteer leader open a local bank account in WODDI\'s name?', ['Yes, for her chapter', 'No', 'Yes, if members agree'], 1],
  ['A member offers to pay a levy to join your chapter. You…', ['collect it and record it', 'refuse: WODDI has no local dues or levies, and tell HQ', 'ask her to pay the chapter lead'], 1],
  ['May you raise funds for a WODDI activity on your own?', ['Yes, if it is for a good cause', 'Only with written HQ authorisation', 'Yes, up to a small amount'], 1],
  ['A business offers you a contract for WODDI. You…', ['sign it to save time', 'refer it to WODDI HQ, the only one who can commit WODDI', 'sign it with your assistant'], 1],
  ['A leader at your level suggests skipping your State Coordinator to get a quick decision. You…', ['go ahead', 'coordinate with her, but keep to the approved reporting line', 'report her to the police'], 1],
  ['Evidence of an assigned task goes…', ['on the WDOS task, uploaded with the task', 'to your personal email', 'nowhere; telling HQ is enough'], 0],
];

function fw_keys(string $route): array { return array_values(array_filter(array_keys(FW_TASKS), fn($k) => $route === 'open7' || FW_TASKS[$k][1] === 'A')); }
function fw_due(array $a, int $day): string {
  return (new DateTimeImmutable(substr((string) $a['started_at'], 0, 10) . ' 12:00:00', new DateTimeZone('Africa/Lagos')))->modify('+' . ($day - 1) . ' days')->format('Y-m-d');
}
function fw_rows(int $aid): array { $o = []; foreach (rows('SELECT * FROM activation_tasks WHERE activation_id=?', [$aid]) as $r) $o[$r['task_key']] = $r; return $o; }
function fw_quiz_public(array $quiz): array { return array_map(fn($q) => ['q' => $q[0], 'options' => $q[1]], $quiz); }
function fw_quiz_mark(array $quiz, $answers): int { $n = 0; foreach ($quiz as $i => $q) if (is_array($answers) && isset($answers[$i]) && (int) $answers[$i] === $q[2]) $n++; return $n; }

/** The place of the seat she is screened for, as a leadership-map place. */
function fw_place(array $a): array {
  $u = row('SELECT ou.level, ou.name, ou.country_code FROM org_units ou WHERE ou.id=?', [(int) $a['org_unit_id']]) ?: ['level' => '', 'name' => '', 'country_code' => ''];
  $lvl = (string) val('SELECT level FROM positions WHERE code=?', [$a['position_code']]); $d = array_search($lvl, LM_LEVELS, true); $d = $d === false ? 1 : $d + 1;
  $state = $u['level'] === 'state_region' ? $u['name'] : ($lvl === 'state_region' ? $a['area_label'] : '');
  $n = ['cc' => (string) $u['country_code'], 'state' => $state, 'lga' => (string) ($a['lga'] ?? ''), 'comm' => (string) ($a['community'] ?? ''), 'chapter' => (int) ($a['chapter_id'] ?? 0)];
  if ($d >= 3 && $n['lga'] === '') $n['lga'] = $lvl === 'lga' ? $a['area_label'] : '';
  if ($d >= 4 && $n['comm'] === '') $n['comm'] = $lvl === 'community_cluster' ? $a['area_label'] : '';
  return lm_cut($n, $d);
}
/** The leaders she may name as her reporting officer: the serving leaders above the seat, nearest first. WODDI HQ when there are none. */
function fw_officers(array $a): array {
  $n = fw_place($a); $out = []; $d = lm_depth($n);
  for ($i = $d - 1; $i >= 1; $i--) foreach (lm_seats() as $s) if ($s['network'] === $a['network'] && $s['depth'] === $i && lm_same($s['place'], lm_cut($n, $i)) && !in_array($s['status'], ['on_leave', 'suspended'], true))
    $out[] = ['id' => $s['user_id'], 'name' => trim($s['person']['first_name'] . ' ' . $s['person']['last_name']), 'title' => lm_title($s['position_code'], $s['network'], $n['cc']), 'place' => lm_place_name(lm_cut($n, $i))];
  $out[] = ['id' => 0, 'name' => 'WODDI HQ', 'title' => 'Headquarters', 'place' => ''];
  return $out;
}

/** The score WDOS suggests from the evidence alone, out of the task's points. */
function fw_auto(string $key, array $ev, array $a): int {
  $len = fn($s) => mb_strlen(trim((string) $s)); $pts = FW_TASKS[$key][3];
  switch ($key) {
    case 'consent': { $u = row('SELECT * FROM users WHERE id=?', [(int) $a['user_id']]); $prof = $u && $u['phone'] !== '' && $u['dob'] && $u['lga'] !== '' && $u['community'] !== '' && (int) $u['has_photo'] === 1;
      return (!empty($ev['consent']) && !empty($ev['compliance']) ? 10 : 0) + ($prof ? 5 : 0) + (!empty($ev['kit']) ? 5 : 0); }
    case 'access': return !empty($ev['signed_in_at']) ? 20 : 0;
    case 'orientation': return !empty($ev['read']) ? (int) round(30 * (int) ($ev['quiz_right'] ?? 0) / count(FW_QUIZ_A)) : 0;
    case 'contact': return ($len($ev['how'] ?? '') && $len($ev['when'] ?? '') && $len($ev['agreed'] ?? '') >= 20 ? 10 : 0);      // the other 10 when the officer confirms
    case 'priorities': { $p = array_filter((array) ($ev['priorities'] ?? []), fn($x) => mb_strlen(trim((string) $x)) >= 20); return (int) round(10 * min(3, count($p)) / 3); }
    case 'coverage': return min(20, ($len($ev['summary'] ?? '') >= 150 ? 10 : (int) round(10 * $len($ev['summary'] ?? '') / 150)) + ($len($ev['gaps'] ?? '') >= 40 ? 5 : 0) + (!empty($ev['confirmed_list']) ? 5 : 0));
    case 'referrals': { $q = array_filter((array) ($ev['people'] ?? []), fn($p) => trim((string) ($p['name'] ?? '')) !== '' && in_array($p['status'] ?? '', ['contacted', 'interested', 'registered', 'screening'], true));
      return min(20, 4 * min(4, count($q)) + ($len($ev['engagement'] ?? '') >= 80 ? 4 : 0)); }
    case 'engagement': { $l = array_filter((array) ($ev['log'] ?? []), fn($x) => trim((string) ($x['who'] ?? '')) !== '' && mb_strlen(trim((string) ($x['outcome'] ?? ''))) >= 10); return (int) round($pts * min(3, count($l)) / 3); }
    case 'plan': { $p = array_filter((array) ($ev['actions'] ?? []), fn($x) => mb_strlen(trim((string) ($x['what'] ?? ''))) >= 20 && trim((string) ($x['by'] ?? '')) !== ''); return (int) round($pts * min(3, count($p)) / 3); }
    case 'report': return min(10, (int) round(10 * $len($ev['report'] ?? '') / 300)) + (int) round(10 * (int) ($ev['quiz_right'] ?? 0) / count(FW_QUIZ_B));
  }
  return 0;
}
/** Everything about a framework activation: each task, the stage scores, the gates, and what is still needed. */
function fw_state(array $a): array {
  $rows = fw_rows((int) $a['id']); $keys = fw_keys($a['route']); $tasks = []; $stage = ['A' => 0, 'B' => 0]; $verifiedAll = true; $submittedAll = true; $corr = 0; $doneDays = [];
  foreach ($keys as $k) {
    [$day, $st, $crit, $pts, $title, $does, $evid, $gate] = FW_TASKS[$k]; $r = $rows[$k] ?? null;
    $status = $r ? $r['status'] : 'not_started'; $ev = $r ? jn_json($r['evidence']) : [];
    $score = $r ? ($r['score'] !== null ? (int) $r['score'] : (int) $r['auto_score']) : 0;
    if ($k === 'contact' && $r && $r['confirmed_at'] && $r['score'] === null) $score = min($pts, (int) $r['auto_score'] + 10);
    $stage[$st] += $score;
    if ($status !== 'verified') $verifiedAll = false;
    if (!in_array($status, ['submitted', 'verified'], true)) $submittedAll = false;
    if ($status === 'pending_corrections') $corr++;
    $tasks[] = ['key' => $k, 'day' => $day, 'stage' => $st, 'criterion' => $crit, 'points' => $pts, 'title' => $title, 'does' => $does, 'evidence_needed' => $evid, 'gate' => $gate,
      'status' => $status, 'status_label' => FW_STATUS[$status], 'due_on' => fw_due($a, $day), 'evidence' => $ev, 'files' => $r && $r['files'] !== '' ? array_map('intval', explode(',', $r['files'])) : [],
      'score' => $score, 'reviewed' => $r && $r['score'] !== null, 'auto_score' => $r ? (int) $r['auto_score'] : null, 'submitted_at' => $r['submitted_at'] ?? null, 'reviewed_at' => $r['reviewed_at'] ?? null,
      'reviewer' => $r && $r['reviewer_id'] ? (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [(int) $r['reviewer_id']]) : '', 'remarks' => $r['remarks'] ?? '',
      'resubmissions' => $r ? (int) $r['resubmissions'] : 0, 'officer_id' => $r && $r['officer_id'] !== null ? (int) $r['officer_id'] : null, 'confirmed_at' => $r['confirmed_at'] ?? null,
      'confirmed_by' => $r && $r['confirmed_by'] ? (string) val("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?", [(int) $r['confirmed_by']]) : ''];
    if (in_array($status, ['submitted', 'verified'], true)) $doneDays[$day] = ($doneDays[$day] ?? 0) + 1;
  }
  $t = array_column($tasks, null, 'key');
  $gates = ['consent' => !empty($t['consent']['evidence']['consent']) && in_array($t['consent']['status'], ['submitted', 'verified'], true),
    'access' => !empty($t['access']['evidence']['signed_in_at']), 'reporting' => !empty($t['contact']['confirmed_at'])];
  $passA = $stage['A'] >= FW_PASS && !in_array(false, $gates, true);
  $seven = $a['route'] === 'open7';
  if ($seven) $gates['report'] = in_array($t['report']['status'] ?? '', ['submitted', 'verified'], true);
  $breach = trim((string) ($a['compliance_breach'] ?? '')) !== '';
  $passB = !$seven || ($stage['B'] >= FW_PASS && $gates['report'] && !$breach);
  $score = $seven ? (int) round(($stage['A'] + $stage['B']) / 2) : $stage['A'];
  $daysTotal = $seven ? 7 : 3; $daysDone = 0; for ($i = 1; $i <= $daysTotal; $i++) { $need = count(array_filter($tasks, fn($x) => $x['day'] === $i)); if ($need && ($doneDays[$i] ?? 0) >= $need) $daysDone++; else break; }
  $final = $submittedAll; $passed = $final && $passA && $passB;
  $missing = []; foreach ($gates as $g => $ok) if (!$ok) $missing[] = ['consent' => 'signed consent', 'access' => 'verified WDOS sign-in', 'reporting' => 'reporting officer\'s confirmation', 'report' => 'Day 7 report'][$g];
  $decision = $a['status'] === 'completed' ? ($a['outcome'] === 'selected' ? 'activated' : 'decided') : ($a['status'] === 'support' ? 'requiring_support' : ($corr || $a['status'] === 'returned' ? 'pending_corrections' : ($submittedAll ? ($verifiedAll ? 'verified' : 'submitted') : (array_filter($tasks, fn($x) => $x['status'] !== 'not_started') ? 'in_progress' : 'not_started'))));
  if ($submittedAll && !$passed && $decision === 'verified') $decision = 'pending_corrections';
  return ['tasks' => $tasks, 'stage_a' => $stage['A'], 'stage_b' => $seven ? $stage['B'] : null, 'score' => $score, 'pass_mark' => FW_PASS, 'gates' => $gates, 'missing_gates' => $missing, 'pass_a' => $passA, 'pass_b' => $seven ? $passB : null,
    'breach' => $breach ? $a['compliance_breach'] : '', 'final' => $final, 'passed' => $passed, 'verified_all' => $verifiedAll, 'days_done' => $daysDone, 'days_total' => $daysTotal, 'corrections' => $corr,
    'decision' => $decision, 'decision_label' => FW_STATUS[$decision] ?? 'Decided'];
}
/** The score in the shape the rest of WDOS already reads (the envoy's dashboard, the HQ register, the leaderboard). */
function fw_score(array $a): array {
  $s = fw_state($a); $parts = []; foreach ($s['tasks'] as $t) $parts[$t['key']] = $t['score'];
  return ['score' => $s['score'], 'pass_mark' => FW_PASS, 'final' => $s['final'], 'passed' => $s['passed'], 'days_done' => $s['days_done'], 'days_total' => $s['days_total'], 'parts' => $parts,
    'stage_a' => $s['stage_a'], 'stage_b' => $s['stage_b'], 'gates' => $s['gates'], 'decision' => $s['decision'], 'decision_label' => $s['decision_label'], 'framework' => true];
}
/** WDOS records her verified sign-in the first time she opens her activation after it starts. */
function fw_touch_access(array $a): void {
  if (($a['scheme'] ?? '') !== 'fw' || !in_array($a['status'], ['in_progress', 'returned'], true)) return;
  if (val("SELECT 1 FROM activation_tasks WHERE activation_id=? AND task_key='access'", [(int) $a['id']])) return;
  $ev = ['signed_in_at' => now(), 'how' => 'Signed in to WDOS and opened the activation'];
  try { q("INSERT INTO activation_tasks (activation_id,task_key,status,evidence,auto_score,submitted_at) VALUES (?,'access','submitted',?,20,?)", [(int) $a['id'], json_encode($ev), now()]); } catch (PDOException $e) { /* recorded by a parallel request */ }
}
/** Read and check what she submits for one task. Returns [evidence, files, officer]. Refuses with 422 naming what is missing. */
function fw_take(string $key, array $b, array $a, int $uid): array {
  $s = fn($k, $n = 2000) => mb_substr(trim((string) ($b[$k] ?? '')), 0, $n); $ev = []; $officer = null;
  $list = function ($k, array $fields, int $max) use ($b) { $o = []; foreach (array_slice(is_array($b[$k] ?? null) ? $b[$k] : [], 0, $max) as $x) { $r = []; foreach ($fields as $f => $n) $r[$f] = mb_substr(trim((string) ($x[$f] ?? '')), 0, $n); if (implode('', $r) !== '') $o[] = $r; } return $o; };
  switch ($key) {
    case 'consent':
      foreach (['consent', 'compliance', 'kit'] as $k) if (empty($b[$k])) fail(422, ['consent' => 'Tick that you accept the nomination and give your consent.', 'compliance' => 'Tick that you will keep to the volunteer rules.', 'kit' => 'Tick that you received your activation kit and role description.'][$k], $k);
      if ($s('signature', 160) === '') fail(422, 'Type your full name as your signature.', 'signature');
      $ev = ['consent' => true, 'compliance' => true, 'kit' => true, 'signature' => $s('signature', 160), 'signed_at' => now()]; break;
    case 'orientation':
      if (empty($b['read'])) fail(422, 'Tick that you read the orientation.', 'read');
      $ans = is_array($b['quiz'] ?? null) ? array_map('intval', array_slice($b['quiz'], 0, count(FW_QUIZ_A))) : [];
      if (count($ans) < count(FW_QUIZ_A)) fail(422, 'Answer all 10 orientation questions.', 'quiz');
      $ev = ['read' => true, 'quiz' => $ans, 'quiz_right' => fw_quiz_mark(FW_QUIZ_A, $ans), 'quiz_of' => count(FW_QUIZ_A)]; break;
    case 'contact':
      $off = (int) ($b['officer'] ?? -1); $ok = array_column(fw_officers($a), null, 'id'); if (!isset($ok[$off])) fail(422, 'Choose your reporting officer from the list.', 'officer');
      foreach (['how' => 'Say how you contacted her', 'when' => 'Say when you contacted her', 'agreed' => 'Write what you agreed (a sentence or two)'] as $k => $m) if ($s($k) === '') fail(422, $m . '.', $k);
      $ev = ['officer' => $ok[$off]['name'] . ($off ? ', ' . $ok[$off]['title'] : ''), 'how' => $s('how', 60), 'when' => $s('when', 40), 'agreed' => $s('agreed', 1500), 'counterpart' => $s('counterpart', 300)];
      $officer = $off; break;
    case 'priorities':
      $p = array_values(array_filter(array_map(fn($x) => mb_substr(trim((string) $x), 0, 600), array_slice(is_array($b['priorities'] ?? null) ? $b['priorities'] : [], 0, 3)), fn($x) => $x !== ''));
      if (count($p) < 3) fail(422, 'Write your three immediate priorities.', 'priorities');
      $ev = ['priorities' => $p, 'vacancies' => $s('vacancies', 1500)]; break;
    case 'coverage':
      if (mb_strlen($s('summary')) < 40) fail(422, 'Write your coverage report: which seats are filled, which are vacant, and what you found.', 'summary');
      $ev = ['summary' => $s('summary', 3000), 'gaps' => $s('gaps', 1500), 'corrections' => $s('corrections', 1500), 'confirmed_list' => !empty($b['confirmed_list'])]; break;
    case 'referrals':
      $people = $list('people', ['name' => 120, 'for' => 120, 'status' => 16, 'note' => 300], 30);
      foreach ($people as &$p) if (!in_array($p['status'], ['identified', 'contacted', 'interested', 'screening', 'registered', 'declined'], true)) $p['status'] = 'identified'; unset($p);
      if (!$people) fail(422, 'Record at least one person you approached, and how far screening has gone.', 'people');
      $ev = ['people' => $people, 'engagement' => $s('engagement', 2000), 'via_link' => (int) val('SELECT COUNT(*) FROM referrals WHERE referrer_id=? AND created_at>=?', [$uid, $a['started_at']])]; break;
    case 'engagement':
      $log = $list('log', ['who' => 120, 'how' => 60, 'when' => 40, 'outcome' => 600], 30);
      if (!$log) fail(422, 'Record at least one contact: who, how, when and the outcome.', 'log');
      $ev = ['log' => $log]; break;
    case 'plan':
      $acts = $list('actions', ['what' => 400, 'by' => 40, 'with' => 160], 10);
      if (!$acts) fail(422, 'Write at least one action: what, by when and with whom.', 'actions');
      $ev = ['actions' => $acts]; break;
    case 'report':
      if (mb_strlen($s('report')) < 80) fail(422, 'Write your Day 7 report: what you did on Days 1 to 7 and what you found.', 'report');
      $ans = is_array($b['quiz'] ?? null) ? array_map('intval', array_slice($b['quiz'], 0, count(FW_QUIZ_B))) : [];
      if (count($ans) < count(FW_QUIZ_B)) fail(422, 'Answer all 6 compliance questions.', 'quiz');
      $ev = ['report' => $s('report', 6000), 'quiz' => $ans, 'quiz_right' => fw_quiz_mark(FW_QUIZ_B, $ans), 'quiz_of' => count(FW_QUIZ_B)]; break;
    default: fail(422, 'That task is recorded by WDOS itself.', 'task');
  }
  $files = []; foreach (array_slice(is_array($b['files'] ?? null) ? $b['files'] : [], 0, 6) as $f) if ((int) $f && val('SELECT 1 FROM doc_files WHERE id=? AND uploaded_by=?', [(int) $f, $uid])) $files[] = (int) $f;
  return [$ev, $files, $officer];
}
/** For HQ and for a leader's dashboard: the candidates someone referred, and where each one stands. */
function fw_people_brief(int $referrerId): array {
  $out = [];
  foreach (rows("SELECT a.*, u.first_name, u.last_name, u.has_photo FROM activations a JOIN users u ON u.id=a.user_id JOIN referrals r ON r.user_id=a.user_id AND r.referrer_id=?
      WHERE a.id=(SELECT MAX(a2.id) FROM activations a2 WHERE a2.user_id=a.user_id) ORDER BY a.id DESC LIMIT 30", [$referrerId]) as $a) {
    $sc = activation_score($a);
    $out[] = ['id' => (int) $a['user_id'], 'first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'has_photo' => (int) $a['has_photo'], 'route' => ACT_ROUTES[$a['route']]['label'] ?? $a['route'],
      'role' => position_title($a['position_code'], $a['network']), 'status' => $sc['decision_label'] ?? $a['status'], 'score' => $sc['score'], 'passed' => $sc['passed'], 'days_done' => $sc['days_done'], 'days_total' => $sc['days_total'] ?? 3, 'deadline' => $a['deadline']];
  }
  return $out;
}
/** For a leader's dashboard: candidates who named her as their reporting officer and wait for her to confirm. */
function fw_confirm_requests(int $uid): array {
  try { $r = rows("SELECT t.activation_id, t.evidence, t.submitted_at, a.position_code, a.network, u.first_name, u.last_name, u.has_photo, u.id user_id FROM activation_tasks t JOIN activations a ON a.id=t.activation_id JOIN users u ON u.id=a.user_id
      WHERE t.task_key='contact' AND t.officer_id=? AND t.confirmed_at IS NULL AND t.status IN ('submitted','verified') AND a.status IN ('in_progress','submitted','returned') ORDER BY t.submitted_at", [$uid]); } catch (PDOException $e) { return []; }
  return array_map(fn($x) => ['activation_id' => (int) $x['activation_id'], 'candidate' => ['id' => (int) $x['user_id'], 'first_name' => $x['first_name'], 'last_name' => $x['last_name'], 'has_photo' => (int) $x['has_photo']],
    'role' => position_title($x['position_code'], $x['network']), 'evidence' => jn_json($x['evidence']), 'submitted_at' => $x['submitted_at']], $r);
}
/** For the HQ dashboard: every framework activation in the period, by route and by status, with the average score and the pass rate. */
function fw_summary(int $days): array {
  $out = []; $from = gmdate('Y-m-d H:i:s', time() - $days * 86400);
  try { $list = rows("SELECT * FROM activations WHERE scheme='fw' AND (started_at>=? OR status IN ('in_progress','submitted','returned','support')) ORDER BY id DESC LIMIT 2000", [$from]); } catch (PDOException $e) { return []; }
  foreach (['ref3', 'open7'] as $r) $out[$r] = ['label' => ACT_ROUTES[$r]['label'], 'total' => 0, 'not_started' => 0, 'in_progress' => 0, 'submitted' => 0, 'verified' => 0, 'pending_corrections' => 0, 'requiring_support' => 0, 'activated' => 0, 'decided' => 0, 'incomplete' => 0, 'passed' => 0, 'scored' => 0, 'score_sum' => 0];
  foreach ($list as $a) {
    $s = fw_state($a); $o = &$out[$a['route']]; if (!isset($o)) continue; $o['total']++;
    $k = $a['status'] === 'incomplete' ? 'incomplete' : $s['decision']; $o[$k] = ($o[$k] ?? 0) + 1;
    if ($s['final']) { $o['scored']++; $o['score_sum'] += $s['score']; if ($s['passed']) $o['passed']++; }
    unset($o);
  }
  foreach ($out as &$o) { $o['average'] = $o['scored'] ? (int) round($o['score_sum'] / $o['scored']) : null; $o['pass_rate'] = $o['scored'] ? (int) round(100 * $o['passed'] / $o['scored']) : null; unset($o['score_sum']); }
  return $out;
}
