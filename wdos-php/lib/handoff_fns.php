<?php
declare(strict_types=1);
/* Production handoff of 11 November 2026: shared functions.
 * Decision register D01 to D14, confidential cases, membership lifecycle, where members heard of WODDI,
 * the age transition notice, the annual calendar and the 80 percent workflow catalogue.
 * Loaded before every route. */

/* ---- D01 to D14: the decisions management has not yet approved ----------------------
 * code, question, the guardrail WDOS runs on until a decision is recorded, the work that waits, the linked rule (if one). */
const DECISIONS = [
  'D01' => ['African WNNN country title: Country Lead and Deputy Country Lead, or Country Representative and Deputy as in the diaspora', 'Both sets of titles are kept. Africa shows the title chosen under System rules; the diaspora always shows Country Representative.', 'Official titles and letters', 'wnnn_country_title'],
  'D02' => ['Does reaching 30 members activate a chapter, or make it eligible?', 'The chapter is marked eligible. It is not activated silently. HQ activates it.', 'Chapter status policy', 'chapter_auto_activate', 'eligible'],
  'D03' => ['Is 50 a universal hard maximum, or can a country set its own?', 'Hard maximum of 50. A country figure can be entered under System rules and every change is versioned.', 'Capacity rules', 'chapter_capacity', '50'],
  'D04' => ['Automatic chapter creation, split thresholds and catchment tie-break', 'A member is placed in an existing chapter with room. If there is none she is held as pending placement and stays a full member. Automatic creation is built behind a switch and should stay off until approved.', 'Autonomous creation', 'chapter_auto_create', 'off'],
  'D05' => ['Account recovery without OTP on shared or lost devices', 'Fingerprint or face (passkey) where the phone supports it, an email link where the member gave an email, and reset by HQ. A leader-assisted reset is built behind a switch and should stay off until approved.', 'Production sign-up and sign-in', 'recovery_by_leader', 'off'],
  'D06' => ['Governance of exceptional safeguarding and security cases', 'A separate confidential lane that only HQ case handlers see. A person named in a case never sees it. Nothing is sanctioned automatically.', 'Incident protocol', ''],
  'D07' => ['Is completing three referrals enforced?', 'A growth milestone. Nobody is excluded or held back for not reaching it.', 'Activation condition', 'journey_required_referrals', '0'],
  'D08' => ['WNNN member turning 30', 'The Person ID and every record stay. WDOS tells the member and lists her for HQ. Nothing changes network by itself and no WGMN role is ever given automatically.', 'Age transition engine', 'age_transition', 'hq'],
  'D09' => ['The ordinary seven-day screening criteria', 'Short, objective, non-invasive questions with equal text and audio routes and an open retry. No staff step.', 'Automatic activation rules', ''],
  'D10' => ['Approval of all five languages, audio and video', 'Arabic, English, French, Portuguese and Swahili are present as working versions. None is approved until a human reviewer signs each one.', 'Launch', ''],
  'D11' => ['Country geography and ward to cluster mappings', 'Official wards are stored apart from WODDI Community Clusters. A cluster is not called a ward.', 'Country onboarding', ''],
  'D12' => ['He-for-She appointment eligibility and rights', 'Deputy, assistant or administrative secretary only, by formal appointment, with rights limited to the appointed scope.', 'Role workflows', ''],
  'D13' => ['Consent, privacy, cross-border transfers and minors in programme contexts', 'Nobody under 18 can register. Members can download their own record and ask for closure. Legal review is still required before go-live.', 'Go-live', ''],
  'D14' => ['Service levels, recovery objectives, concurrency and operating budget', 'No figure is promised. Baselines are to be measured and signed.', 'Hosting acceptance', ''],
];
/** Each decision with what is recorded against it (the latest version) and the rule value WDOS is running on. */
function decisions_state(): array {
  $latest = [];
  try { foreach (rows('SELECT d.*, CONCAT(u.first_name,\' \',u.last_name) recorder FROM decisions d LEFT JOIN users u ON u.id=d.recorded_by ORDER BY d.id') as $r) $latest[$r['code']] = $r; } catch (PDOException $e) { /* before migration 017 */ }
  $out = [];
  foreach (DECISIONS as $code => $d) {
    [$q, $guard, $blocked, $rule] = $d; $want = $d[4] ?? null; $r = $latest[$code] ?? null;
    $out[] = ['code' => $code, 'question' => $q, 'guardrail' => $guard, 'blocked' => $blocked, 'rule' => $rule, 'current' => $rule !== '' ? policy($rule) : '', 'differs' => !$r && $want !== null && policy($rule) !== $want,
      'status' => $r ? 'Recorded' : 'Pending', 'decision' => $r['decision'] ?? '', 'approver' => $r['approver'] ?? '', 'approved_on' => $r['approved_on'] ?? '', 'version' => $r ? (int) $r['version'] : 0, 'recorded_by' => $r['recorder'] ?? ''];
  }
  return $out;
}

/* ---- confidential cases (Q29, section 5.9, T27) -------------------------------------- */
const CASE_CATEGORIES = ['complaint' => 'A complaint about a person or a leader', 'safeguarding' => 'A safeguarding concern', 'security' => 'My account or someone pretending to be me', 'privacy' => 'My personal information', 'eligibility' => 'A disagreement about my membership', 'other' => 'Something else'];
const CASE_STATUS = ['received' => 'Received', 'in_review' => 'Being looked at', 'resolved' => 'Resolved', 'closed' => 'Closed'];
/** Who may handle a case: HQ staff, and never the person the case is about. */
function case_handlers_sql(?int $about): array { return $about ? ["u.role IN ('hq_admin','super_admin') AND u.id<>?", [$about]] : ["u.role IN ('hq_admin','super_admin')", []]; }
function case_may_handle(array $u, array $c): bool { return is_hq($u) && (int) ($c['about_user_id'] ?? 0) !== (int) $u['id'] && (int) $c['reporter_id'] !== (int) $u['id']; }
function case_open(int $reporter, string $category, ?int $about, string $aboutText, string $body): array {
  $id = ins('INSERT INTO cases (ref,reporter_id,category,about_user_id,about_text,body,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)', ['tmp' . token(8), $reporter, $category, $about, mb_substr($aboutText, 0, 190), $body, now(), now()]);
  $ref = sprintf('WDC-%s-%05d', gmdate('y'), $id % 100000); if (val('SELECT 1 FROM cases WHERE ref=?', [$ref])) $ref .= '-' . $id;
  q('UPDATE cases SET ref=? WHERE id=?', [$ref, $id]);
  // only the reference travels in the notice: no names, no words from the case
  [$w, $p] = case_handlers_sql($about);
  notify_many($w, $p, 'report', 'A confidential case was opened', 'Reference ' . $ref . '. Open the confidential cases list to read it.', '#/cases');
  notify($reporter, 'account', 'We received your private report', 'Your reference is ' . $ref . '. Only WODDI HQ case handlers can read it. You can follow it under Help.', '#/help');
  log_activity($reporter, null, 'case_opened', $ref);                          // the reference only
  return ['id' => $id, 'ref' => $ref];
}

/* ---- membership lifecycle (Q04, Q43) -------------------------------------------------- */
/** The member's place in the lifecycle, in the handoff's words. */
function lifecycle_of(array $u): array {
  if ($u['status'] === 'withdrawn') return ['withdrawn', 'Withdrawn at your own request'];
  if ($u['status'] === 'suspended') return ['restricted', 'Restricted. WODDI HQ will explain why.'];
  if ($u['status'] === 'pending_profile') return ['registered', 'Registered. Finish your profile to continue.'];
  if (!empty($u['paused_at'])) return ['paused', 'Paused by you. You can come back at any time.'];
  $j = null; try { $j = row('SELECT status FROM member_journeys WHERE user_id=?', [(int) $u['id']]); } catch (PDOException $e) { /* table not there yet */ }
  if (!$j) return ['active', 'Active'];
  return match ($j['status']) {
    'active' => ['active', 'Active'],
    'restricted', 'review' => ['restricted', 'Held for a check by WODDI HQ. Your membership number stays yours.'],
    'retry' => ['onboarding', 'On your seven days, with more time given automatically'],
    default => ['onboarding', 'On your seven days'],
  };
}
function member_pause(array $u, bool $pause): void {
  q('UPDATE users SET paused_at=? WHERE id=?', [$pause ? now() : null, (int) $u['id']]);
  log_activity((int) $u['id'], (int) $u['id'], $pause ? 'membership_paused' : 'membership_resumed');
}
/** A member closes her own membership. Her history is kept; her chapter place and any appointment end; she is signed out everywhere. */
function member_withdraw(array $u, string $reason): void {
  $id = (int) $u['id'];
  if (is_hq($u)) fail(422, 'An HQ account cannot be closed here. Ask a super administrator.', 'hq');
  $seat = current_seat($id);
  q("UPDATE seats SET ends_at=?, end_reason='resigned' WHERE user_id=? AND ends_at IS NULL", [now(), $id]);
  chapter_leave($id, 'withdrawn');
  q("UPDATE users SET status='withdrawn', withdrawn_at=? WHERE id=?", [now(), $id]);
  q('DELETE FROM sessions WHERE user_id=?', [$id]);
  log_activity($id, $id, 'membership_withdrawn', mb_substr($reason, 0, 200));
  notify_hq('registration', 'A member closed her membership', trim($u['first_name'] . ' ' . $u['last_name']) . ' (' . $u['member_no'] . ')' . ($seat ? '. She held the seat ' . seat_title($seat) . ', which is now vacant.' : '.'), '#/members/' . $id);
}
/** Everything WDOS holds about one member, for her own download (right of access). No other person's details. */
function member_export(array $u): array {
  $id = (int) $u['id']; $safe = function (string $sql, array $p) { try { return rows($sql, $p); } catch (PDOException $e) { return []; } };
  return [
    'prepared_at' => gmdate('c'), 'person_id' => sprintf('WDP-%07d', $id),
    'profile' => array_intersect_key($u, array_flip(['member_no', 'email', 'first_name', 'last_name', 'phone', 'dob', 'network', 'country_code', 'state_region', 'lga', 'community', 'ward', 'lang', 'status', 'source', 'created_at', 'last_seen_at', 'paused_at', 'volunteer_since'])),
    'chapter_history' => $safe('SELECT c.name chapter, m.started_at, m.ended_at, m.reason, m.end_reason FROM chapter_members m JOIN chapters c ON c.id=m.chapter_id WHERE m.user_id=? ORDER BY m.id', [$id]),
    'appointments' => $safe('SELECT position_code, network, area_label, status, starts_at, ends_at, end_reason FROM seats WHERE user_id=? ORDER BY id', [$id]),
    'seven_day_journey' => $safe('SELECT day_no, answers, done_at FROM member_journey_steps WHERE user_id=? ORDER BY day_no', [$id]),
    'referrals_made' => count($safe('SELECT 1 FROM referrals WHERE referrer_id=?', [$id])),
    'tasks' => $safe('SELECT title, status, due_on, created_at, completed_at FROM tasks WHERE assignee_id=? ORDER BY id', [$id]),
    'meeting_replies' => $safe('SELECT meeting_id, answer, answered_at FROM meeting_rsvps WHERE user_id=?', [$id]),
    'private_reports' => $safe('SELECT ref, category, status, created_at FROM cases WHERE reporter_id=? ORDER BY id', [$id]),
    'notification_choices' => $safe('SELECT kind, in_app, push FROM notification_prefs WHERE user_id=?', [$id]),
    'record_of_changes' => $safe('SELECT action, created_at FROM activity_log WHERE subject_id=? ORDER BY id DESC LIMIT 200', [$id]),
  ];
}

/* ---- the volunteering choice (Q05, Q10): member, volunteer, or interested in a leadership position. It records interest only: WODDI HQ appoints leaders. */
function volunteer_interest_save(int $userId, string $choice): string {
  $choice = trim($choice); $u = row('SELECT * FROM users WHERE id=?', [$userId]); if (!$u) return '';
  if ($choice !== '' && $choice !== 'volunteer' && !val("SELECT 1 FROM positions WHERE code=? AND (level IN ('chapter','community','lga','city') OR code='campus_ambassador')", [$choice])) $choice = 'volunteer';   // only the roles offered on the form
  if ($choice === '' && !empty($u['volunteer_since'])) $choice = 'volunteer';                 // she is a volunteer already: an empty choice only clears a leadership interest
  if ($choice === (string) ($u['volunteer_interest'] ?? '') && ($choice === '' || !empty($u['volunteer_since']))) return $choice;
  q('UPDATE users SET volunteer_interest=? WHERE id=?', [$choice, $userId]);
  if ($choice !== '') { volunteer_join($u); log_activity($userId, $userId, 'volunteer_interest', $choice); }
  return $choice;
}
function volunteer_interest_label(array $u): string {
  $c = (string) ($u['volunteer_interest'] ?? ''); if ($c === '' || $c === 'volunteer') return ($c !== '' || !empty($u['volunteer_since'])) ? 'Volunteer in the chapter' : '';
  $p = row('SELECT title_wgmn, title_wnnn FROM positions WHERE code=?', [$c]); return $p ? 'Interested in: ' . ($u['network'] === 'WNNN' ? $p['title_wnnn'] : $p['title_wgmn']) : '';
}

/* ---- where members heard of WODDI (Q31) ------------------------------------------------ */
const SOURCES = ['friend' => 'A friend or relative', 'leader' => 'A WODDI leader', 'event' => 'A WODDI event', 'religious' => 'My place of worship', 'school' => 'My school or campus', 'social' => 'Social media', 'radio_tv' => 'Radio or television', 'website' => 'The WODDI website', 'other' => 'Another way'];
function source_save(int $userId): void {
  $b = body(); $s = (string) ($b['source'] ?? ''); $c = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($b['campaign'] ?? ''));
  if (!empty($b['referral']['code']) && $s === '') $s = 'referral';
  if ($s !== 'referral' && !isset(SOURCES[$s])) $s = '';
  if ($s !== '' || $c !== '') q('UPDATE users SET source=?, campaign=? WHERE id=?', [$s, mb_substr((string) $c, 0, 40), $userId]);
}

/* ---- safe retry of a registration whose reply was lost (T04, T29) ---------------------- */
/** The account already made by this same request, if the phone is sending it again. The PIN must match: the key alone opens nothing. */
function idem_existing(string $key, string $password): ?array {
  if (strlen($key) < 16) return null;
  try { $uid = (int) val('SELECT user_id FROM idem_keys WHERE key_hash=? AND created_at>?', [hash('sha256', $key), gmdate('Y-m-d H:i:s', time() - 86400)]); } catch (PDOException $e) { return null; }
  if (!$uid) return null;
  $u = row('SELECT * FROM users WHERE id=?', [$uid]);
  return $u && $u['password_hash'] && password_verify($password, $u['password_hash']) ? $u : null;
}
function idem_keep(string $key, int $userId): void { if (strlen($key) >= 16) { try { q('INSERT INTO idem_keys (key_hash,user_id,created_at) VALUES (?,?,?)', [hash('sha256', $key), $userId, now()]); } catch (PDOException $e) { /* ignore */ } } }

/* ---- the 80 percent instrument (Q42, section 15) ---------------------------------------- */
/* The routine workflows, as the developer declares them. No weights are set here: the business owner and QA agree them.
 * A row counts toward the score only when a named person has verified it. Workflows WDOS does not do stay in the list. */
const WORKFLOW_SEED = [
  ['W01', 'Membership', 'Member registration without OTP', 'Language, network, details, place, PIN', 'A woman opens the join page or an invitation link', 'Person ID, membership number, account', 'yes', 'yes', 'T03,T04'],
  ['W02', 'Membership', 'Network assignment by age', 'Date of birth checked against the age rule', 'Registration', 'WGMN or WNNN membership', 'yes', 'yes', 'T09,T10'],
  ['W03', 'Membership', 'Seven-day journey and automatic activation', 'Seven daily steps, self-screening, automatic outcome', 'Registration', 'Active member, or more time given', 'yes', 'yes', 'T06,T07'],
  ['W04', 'Membership', 'Referral link and counting', 'Share link, opened, registered, completed counted apart', 'Member shares her link', 'Referral record', 'yes', 'yes', 'T08'],
  ['W05', 'Membership', 'Pause, return and closure by the member', 'Member chooses in Settings', 'Member request', 'Lifecycle status and record', 'yes', 'yes', ''],
  ['W06', 'Membership', 'WNNN member turning 30', 'Notice to the member, list for HQ, HQ moves her', 'Thirtieth birthday within 30 days', 'Transition recorded, Person ID kept', 'part', 'no', 'T31'],
  ['W07', 'Chapters', 'Automatic chapter placement', 'Chapter with room found and seat taken in one transaction', 'Registration or change of place', 'Chapter assignment, or pending placement', 'yes', 'yes', 'T11,T12,T15'],
  ['W08', 'Chapters', 'Chapter status by membership', 'Recount on every change', 'A member joins or leaves', 'Developing, eligible, active, at capacity', 'yes', 'yes', 'T13'],
  ['W09', 'Chapters', 'Transfer, merge and split', 'HQ action with reason and approval reference', 'HQ decision', 'History kept, counts corrected', 'yes', 'yes', 'T14'],
  ['W10', 'Leadership', 'Appointment to a position', 'HQ records position, area, status, He-for-She', 'Approved appointment', 'Seat with scoped dashboard', 'yes', 'yes', 'T16,T18'],
  ['W11', 'Leadership', 'Leave, suspension, resignation and replacement', 'HQ changes the state of the seat', 'Change in a leader\'s availability', 'Access removed or restored, history kept', 'yes', 'yes', 'T21'],
  ['W12', 'Leadership', 'Vacancy register', 'Positions with no holder listed by area', 'Continuous', 'Vacancy list', 'yes', 'no', ''],
  ['W13', 'Work', 'Task assignment, delegation and approval', 'Assign, start, submit, approve or send back', 'A leader or HQ assigns', 'Closed task with evidence', 'yes', 'yes', 'T23'],
  ['W14', 'Work', 'Task reminders and escalation', 'Automatic on the due date and after', 'Due date', 'Reminder and escalation notice', 'yes', 'yes', ''],
  ['W15', 'Reporting', 'Periodic leadership report', 'Open, write, submit, review', 'Reporting period opens', 'Reviewed report', 'yes', 'yes', ''],
  ['W16', 'Reporting', 'Automatic roll-up report to chapter', 'Counted from records, no typing', 'Continuous', 'Figures by level with time taken', 'yes', 'no', 'T22'],
  ['W17', 'Meetings', 'Scheduling, invitation and reply', 'Schedule, notify, attend or decline', 'A leader or HQ schedules', 'Meeting with replies', 'yes', 'yes', 'T34'],
  ['W18', 'Meetings', 'Attendance, minutes and action items', 'Mark attendance, write minutes, actions become tasks', 'Meeting ends', 'Minutes and tasks', 'yes', 'yes', 'T34'],
  ['W19', 'Meetings', 'Annual calendar and reminders', 'HQ enters the date, WDOS reminds', 'Seven days and one day before', 'Reminder to the network in scope', 'yes', 'no', 'T34'],
  ['W20', 'Communication', 'Announcement to any level down to chapter and one member', 'Write, choose audience, send or schedule', 'HQ or a leader', 'Notice in each member\'s language where written', 'yes', 'yes', 'T25'],
  ['W21', 'Communication', 'Birthday, anniversary and inspiration messages', 'Automatic, no Send button', 'The date', 'Message in the member\'s language', 'yes', 'no', 'T24'],
  ['W22', 'Communication', 'Messages between a member and HQ', 'Thread with replies and status', 'Member or HQ writes', 'Answered thread', 'yes', 'no', ''],
  ['W23', 'Communication', 'SMS, WhatsApp and voice delivery', 'Not built. In-app and phone notifications only; email when switched on', '', '', 'no', 'no', ''],
  ['W24', 'Community', 'Chapter space, circles and moderation', 'Post, report, hide, blocked words held', 'Member posts', 'Moderated space', 'yes', 'yes', ''],
  ['W25', 'Learning', 'Institute enrolment and progress sync', 'Not connected. The Institute keeps its own accounts until the key is set and the Institute calls WDOS', '', '', 'no', 'no', 'T26'],
  ['W26', 'Support', 'Private complaint or safeguarding report', 'Member files, HQ case handler works it', 'Member report', 'Case with reference and outcome', 'yes', 'yes', 'T27'],
  ['W27', 'Support', 'Account recovery without OTP', 'Fingerprint, email link, or HQ reset', 'Member forgets her PIN', 'New PIN', 'part', 'yes', 'T35'],
  ['W28', 'Governance', 'Policy rules and decision register', 'Versioned rules with effective dates; decisions recorded with approver', 'Management decision', 'Rule in force, audit entry', 'yes', 'no', 'T13'],
  ['W29', 'Governance', 'Backups, restore and monitoring', 'Not built in WDOS. Depends on the database and hosting services', '', '', 'no', 'no', 'T30'],
  ['W30', 'Programmes', 'Programme beneficiary records (WHI, WESAP, YES, SNARP and others)', 'Not built. Only calendar dates are held', '', '', 'no', 'no', ''],
];
function workflow_seed_once(): void {
  try {
    if ((int) val('SELECT COUNT(*) FROM workflow_catalogue') > 0) return;
    foreach (WORKFLOW_SEED as $w) q('INSERT INTO workflow_catalogue (workflow_id,area,workflow_name,steps,trigger_text,outcome,digitally_executable,exception_coverage,test_id,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)', array_merge($w, [now()]));
  } catch (PDOException $e) { /* before migration 017 */ }
}
/** The score, exactly as section 15 defines it. Null until weights exist. Only verified, fully digital workflows are credited. */
function workflow_score(array $items): array {
  $eligible = 0.0; $credited = 0.0; $declared = 0.0; $unweighted = 0;
  foreach ($items as $w) {
    if ($w['eligible_weight'] === null) { $unweighted++; continue; }
    $wt = (float) $w['eligible_weight']; $eligible += $wt;
    $digital = $w['digitally_executable'] === 'yes' && $w['exception_coverage'] === 'yes';
    if ($digital) $declared += $wt;
    if ($digital && $w['verified_by'] !== '' && $w['verification_date']) $credited += $wt;
  }
  return ['eligible_weight' => $eligible, 'verified_weight' => $credited, 'declared_weight' => $declared, 'unweighted' => $unweighted,
    'score' => $eligible > 0 ? round(100 * $credited / $eligible, 1) : null, 'declared_score' => $eligible > 0 ? round(100 * $declared / $eligible, 1) : null];
}

/* ---- annual calendar (Q35) ------------------------------------------------------------- */
const PROGRAMMES = ['WIWS' => 'WODDI Inspiring Women Summit', 'Institute' => 'WODDI Institute cohort', 'WHI' => 'WHI', 'WESAP' => 'WESAP', 'YES' => 'YES', 'SNARP' => 'SNARP', 'Thirst' => 'WODDI Thirst', 'TTT' => 'TTT', 'Harmony' => 'Harmony Hub', 'Leadership' => 'Leadership meeting', 'Chapter' => 'Chapter activity', 'Other' => 'Other'];
function calendar_where(array $u): array {
  if (is_hq($u)) return ['1=1', []];
  return ["(network='ALL' OR network=?) AND (country_code='' OR country_code=?)", [(string) $u['network'], (string) $u['country_code']]];
}

/** Move a WNNN member who has reached 30 to WGMN. The Person ID, membership number, history and referrals stay. No WGMN role is given.
 *  $endSeat: HQ ends her WNNN seat when moving her by hand; the automatic move leaves the seat for HQ to review and hand over. */
function age_move(array $u, ?int $by, bool $endSeat): void {
  $id = (int) $u['id'];
  if ($endSeat) q("UPDATE seats SET ends_at=?, end_reason='age_transition' WHERE user_id=? AND ends_at IS NULL", [now(), $id]);
  q("UPDATE users SET network='WGMN' WHERE id=?", [$id]);
  chapter_replace_after_change($id);
  log_activity($by, $id, 'age_transition', 'WNNN to WGMN at 30' . ($by ? '' : ' (automatic)'));
  notify($id, 'account', 'Welcome to WGMN, ' . $u['first_name'], 'You have moved from WNNN to the WODDI Good Mother Network. Your Person ID, your records and your history came with you.', '#/');
}

/* ---- daily automatic work added by the handoff ------------------------------------------ */
function handoff_automations(array &$done): void {
  try {
    // Membership anniversary, on the day, to the member herself (Q12).
    $r = cron_once('anniversaries', function () {
      $n = 0; $today = new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
      foreach (rows("SELECT id, first_name, created_at FROM users WHERE status='active' AND paused_at IS NULL AND DATE_FORMAT(created_at,'%m-%d')=? AND YEAR(created_at)<?", [$today->format('m-d'), (int) $today->format('Y')]) as $u) {
        $y = (int) $today->format('Y') - (int) substr($u['created_at'], 0, 4);
        notify((int) $u['id'], 'birthday', 'Thank you for ' . $y . ($y === 1 ? ' year' : ' years') . ' with WODDI, ' . $u['first_name'], 'Today is the anniversary of the day you joined. Everyone at WODDI HQ thanks you.', '#/'); $n++;
      }
      return "$n thanked";
    });
    if ($r !== null) $done['anniversaries'] = $r;
    // A WNNN member whose thirtieth birthday is within 30 days is told once. Nothing changes by itself (D08).
    $r = cron_once('age_transition', function () {
      $n = 0;
      foreach (rows("SELECT id, first_name FROM users WHERE status='active' AND network='WNNN' AND participant_class='member' AND dob IS NOT NULL AND age_notice_at IS NULL AND DATE_ADD(dob, INTERVAL 30 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) LIMIT 200") as $u) {
        notify((int) $u['id'], 'account', 'You are reaching 30, ' . $u['first_name'], 'WNNN is for women aged 18 to 29 and WGMN is for women aged 30 and above. Your Person ID, your records and your history stay with you. WODDI HQ will guide you on the next step. Nothing changes until then.', '#/help');
        q('UPDATE users SET age_notice_at=? WHERE id=?', [now(), (int) $u['id']]); $n++;
      }
      // Approved D08: thirty days after she was told, and once she is 30, she moves by herself. A WNNN seat is never ended automatically: HQ reviews it.
      $moved = 0; $seats = 0;
      if (policy('age_transition') === 'auto') foreach (rows("SELECT * FROM users WHERE status='active' AND network='WNNN' AND participant_class='member' AND dob IS NOT NULL AND DATE_ADD(dob, INTERVAL 30 YEAR) <= CURDATE() AND age_notice_at IS NOT NULL AND age_notice_at <= ? LIMIT 100", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]) as $u) {
        age_move($u, null, false); $moved++;
        if (val('SELECT 1 FROM seats WHERE user_id=? AND ends_at IS NULL', [(int) $u['id']])) { $seats++; notify_hq('appointment', 'A WNNN leader has moved to WGMN at 30', trim($u['first_name'] . ' ' . $u['last_name']) . ' still holds her WNNN seat. Review it and arrange the handover.', '#/members/' . (int) $u['id']); }
      }
      if ($moved) return "$n told, $moved moved, $seats seats to review";
      if ($n) notify_hq('registration', "$n WNNN " . ($n === 1 ? 'member is' : 'members are') . ' reaching 30', 'They have been told. Open the age transition list to see them. No one is moved automatically.', '#/cases');
      return "$n told";
    });
    if ($r !== null) $done['age_transition'] = $r;
    // Calendar reminders: seven days before and one day before, to everyone the date is for.
    $r = cron_once('calendar_reminders', function () {
      $n = 0;
      foreach ([7 => 'w', 1 => 'd'] as $days => $mark) {
        foreach (rows("SELECT * FROM calendar_events WHERE starts_on=DATE_ADD(CURDATE(), INTERVAL $days DAY) AND reminded NOT LIKE ?", ['%' . $mark . '%']) as $e) {
          $w = "u.role='member'"; $p = [];
          if ($e['network'] !== 'ALL') { $w .= ' AND u.network=?'; $p[] = $e['network']; }
          if ($e['country_code'] !== '') { $w .= ' AND u.country_code=?'; $p[] = $e['country_code']; }
          $n += notify_many("($w) AND u.paused_at IS NULL", $p, 'reminder', ($days === 7 ? 'In one week: ' : 'Tomorrow: ') . $e['title'], 'On ' . gmdate('j F Y', strtotime($e['starts_on'])) . '. Open the calendar for the details.', '#/calendar');
          q('UPDATE calendar_events SET reminded=CONCAT(reminded, ?) WHERE id=?', [$mark, (int) $e['id']]);
        }
      }
      q('DELETE FROM reg_drafts WHERE expires_at<?', [now()]);
      q('DELETE FROM idem_keys WHERE created_at<?', [gmdate('Y-m-d H:i:s', time() - 2 * 86400)]);
      return "$n reminders";
    });
    if ($r !== null) $done['calendar'] = $r;
  } catch (Throwable $e) { error_log('[wdos handoff] ' . $e->getMessage()); }
}
/** Lifecycle and pause state for the app shell. Safe before the migration has run. */
function handoff_brief(array $u): array {
  try { [$k, $t] = lifecycle_of($u); return ['lifecycle' => $k, 'lifecycle_text' => $t, 'paused' => !empty($u['paused_at']), 'source' => $u['source'] ?? '', 'volunteer_interest' => $u['volunteer_interest'] ?? '', 'volunteer_interest_label' => volunteer_interest_label($u)]; }
  catch (Throwable $e) { return ['lifecycle' => 'active', 'lifecycle_text' => 'Active', 'paused' => false]; }
}
