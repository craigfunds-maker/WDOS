<?php
declare(strict_types=1);
/* v109.3: follow-ups. Each one goes out once (claim_send), in the app, by push and by email, in the person's language
 * where WDOS has the wording. They run from the 5-minute timer and on page visits.
 *  - leader video meetings: the day before, one hour before, and as the room opens at the start
 *  - tasks: two days before the due date (with email), due today and overdue now also by email,
 *    and the reviewer is reminded once when submitted work has waited two days
 *  - messages: one reminder when a message has stayed unread for a day
 *  - announcements: one reminder after two days to everyone who has not pressed "I have read this" */

/** In the app and by push (notify), and by email, in her language when the wording is known. */
function fu_tell(int $uid, string $kind, string $title, string $body, string $url): void {
  notify($uid, $kind, $title, $body, $url);
  $p = row("SELECT email, lang FROM users WHERE id=? AND status='active'", [$uid]); if (!$p) return;
  $lang = (string) ($p['lang'] ?: 'en');
  [$t, $b] = ($lang !== 'en' && ($tr = note_in($lang, $title, $body))) ? $tr : [$title, $body];
  person_mail($uid, 'WDOS: ' . mb_substr($t, 0, 150), $t, '<p>' . nl2br(htmlspecialchars($b)) . '</p>', $url);
}

function followup_automations(array &$done): void {
  $now = time();
  /* 1. leader video meetings asked for through WDOS and approved by HQ */
  try {
    foreach (rows("SELECT * FROM meeting_requests WHERE status='approved' AND starts_at BETWEEN ? AND ?", [gmdate('Y-m-d H:i:s', $now - 900), gmdate('Y-m-d H:i:s', $now + 25 * 3600)]) as $m) {
      $in = strtotime($m['starts_at'] . ' UTC') - $now; $when = gmdate('j M Y, H:i', strtotime($m['starts_at'] . ' UTC')) . ' GMT';
      // the meeting's own name leads the reminder (v109.13): "Kaduna chapter leaders: in 1 hour"
      if ($in <= 600) { $key = 'mr_now_'; $sfx = ': starting now'; $b = 'Open WDOS and tap Join the video meeting.'; }
      elseif ($in <= 3900 && $in > 600) { $key = 'mr_hour_'; $sfx = ': in 1 hour'; $b = $when; }
      elseif ($in > 20 * 3600) { $key = 'mr_day_'; $sfx = ': tomorrow'; $b = $when; }
      else continue;
      if (!claim_send($key . $m['id'])) continue;
      $to = array_values(array_unique(array_merge(mr_ids($m), [(int) $m['requester_id']])));
      foreach ($to as $uid) fu_tell($uid, 'meeting', mb_substr($m['title'], 0, 120) . $sfx, $b, '#/meetings/requests/' . (int) $m['id']);
      $done['mr_' . $m['id']] = count($to) . ' reminded';
    }
  } catch (PDOException $e) { error_log('[wdos followup meetings] ' . $e->getMessage()); }

  try { if (function_exists('vc_expire')) vc_expire(); } catch (PDOException $e) { /* before the calls table exists */ }

  /* 2. tasks, once a day from 07:00 Lagos time */
  try {
    if (cfg('FOLLOWUP_ANY_HOUR') === '1' || (int) (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->format('G') >= 7) {      // the hour gate can be lifted for tests only
      $today = work_today(); $soon = date('Y-m-d', strtotime($today) + 2 * 86400); $y = date('Y-m-d', strtotime($today) - 86400);
      $r = cron_once('task_followups', function () use ($today, $soon, $y) {
        $n = 0;
        foreach (rows("SELECT id, title, assignee_id, due_on FROM tasks WHERE status IN ('open','in_progress') AND due_on IN (?,?,?)", [$soon, $today, $y]) as $t) {
          $url = '#/tasks/' . $t['id'];
          if ($t['due_on'] === $soon) fu_tell((int) $t['assignee_id'], 'task', 'Due in 2 days: ' . mb_substr($t['title'], 0, 120), 'Finish it and submit it before the due date.', $url);
          else {     // the in-app note for today and overdue is sent by task_due; this adds the email
            $title = ($t['due_on'] === $today ? 'Due today: ' : 'Overdue: ') . mb_substr($t['title'], 0, 120);
            $body = $t['due_on'] === $today ? 'This task is due today.' : 'This task was due yesterday. Complete it or tell the assigner what is holding it.';
            $p = row("SELECT lang FROM users WHERE id=?", [(int) $t['assignee_id']]); $lang = (string) ($p['lang'] ?? 'en');
            [$tt, $bb] = ($lang !== 'en' && $lang !== '' && ($tr = note_in($lang, $title, $body))) ? $tr : [$title, $body];
            person_mail((int) $t['assignee_id'], 'WDOS: ' . mb_substr($tt, 0, 150), $tt, '<p>' . htmlspecialchars($bb) . '</p>', $url);
          }
          $n++;
        }
        // submitted work that has waited two days for review: remind the person who gave the task, once
        foreach (rows("SELECT t.id, t.title, t.assigned_by, u.first_name, u.last_name FROM tasks t JOIN users u ON u.id=t.assignee_id WHERE t.status='submitted' AND t.updated_at < ?", [gmdate('Y-m-d H:i:s', time() - 2 * 86400)]) as $t) {
          if (!claim_send('task_review_' . $t['id'])) continue;
          fu_tell((int) $t['assigned_by'], 'task', 'Waiting for your review: ' . mb_substr($t['title'], 0, 120), trim($t['first_name'] . ' ' . $t['last_name']) . ' submitted this task two days ago.', '#/tasks/' . $t['id']); $n++;
        }
        return "$n followed up";
      });
      if ($r !== null) $done['task_followups'] = $r;
    }
  } catch (PDOException $e) { error_log('[wdos followup tasks] ' . $e->getMessage()); }

  /* 3. messages unread for a day: one reminder per thread and message */
  try {
    foreach (rows("SELECT t.*, m.id mid, m.sender_id FROM dm_threads t JOIN dm_messages m ON m.id=(SELECT MAX(x.id) FROM dm_messages x WHERE x.thread_id=t.id AND x.deleted_at IS NULL)
                   WHERE t.last_at BETWEEN ? AND ? LIMIT 400", [gmdate('Y-m-d H:i:s', $now - 3 * 86400), gmdate('Y-m-d H:i:s', $now - 86400)]) as $th) {
      $to = (int) $th['sender_id'] === (int) $th['lo_id'] ? (int) $th['hi_id'] : (int) $th['lo_id'];
      $read = $to === (int) $th['lo_id'] ? (int) $th['lo_read'] : (int) $th['hi_read'];
      if ($read >= (int) $th['mid']) continue;
      if ((int) val('SELECT COUNT(*) FROM dm_messages m JOIN dm_batches b ON b.id=m.batch_id WHERE m.id=? AND (b.undone_at IS NOT NULL OR b.released_at IS NULL)', [(int) $th['mid']]) > 0) continue;      // undone, or still in its 30 seconds
      if (!claim_send('dm_fu_' . $th['id'] . '_' . $th['mid'])) continue;
      $s = row('SELECT first_name, last_name FROM users WHERE id=?', [(int) $th['sender_id']]); if (!$s) continue;
      fu_tell($to, 'message', 'Unread message from ' . trim($s['first_name'] . ' ' . $s['last_name']), 'Open Messages in WDOS to read it and reply.', '#/inbox/' . (int) $th['id']);
      $done['dm_fu'] = ($done['dm_fu'] ?? 0) + 1;
    }
  } catch (PDOException $e) { error_log('[wdos followup messages] ' . $e->getMessage()); }

  /* 4. announcements not yet read two days after they went out */
  try {
    foreach (rows("SELECT * FROM announcements WHERE kind='announcement' AND sent_at BETWEEN ? AND ?", [gmdate('Y-m-d H:i:s', $now - 7 * 86400), gmdate('Y-m-d H:i:s', $now - 2 * 86400)]) as $a) {
      if (!claim_send('ann_fu_' . $a['id'])) continue;
      [$w, $p] = audience_where($a);
      $where = "($w) AND u.id<>? AND NOT EXISTS (SELECT 1 FROM announcement_acks k WHERE k.announcement_id=? AND k.user_id=u.id)"; $wp = array_merge($p, [(int) $a['author_id'], (int) $a['id']]);
      $title = 'Reminder: ' . mb_substr($a['title'], 0, 140); $body = 'Please read this announcement and press I have read this.'; $url = '#/announcements/' . (int) $a['id'];
      $n = notify_many($where, $wp, 'announcement', $title, $body, $url);
      if (!empty($a['by_email'])) mail_many($where, $wp, $title, $body, $url);
      $done['ann_fu_' . $a['id']] = $n . ' reminded';
    }
  } catch (PDOException $e) { error_log('[wdos followup announcements] ' . $e->getMessage()); }
}

/** v109.3 self-governance: WDOS watches itself. Runs from the same timer.
 *  - a video meeting request HQ has not answered in 12 hours: HQ is reminded once (app, phone, email)
 *  - once a day: chapter member counts are recounted and corrected by themselves; anything WDOS cannot correct on its own
 *    (a member in two chapters, a member placed in the other network, a chapter over capacity, email or push failures,
 *    a leader seat held by a suspended account) is written to HQ in one short report, in the app and by email. */
function self_governance(array &$done): void {
  try {
    foreach (rows("SELECT id, request_no, title, leader_name FROM meeting_requests WHERE status='pending' AND created_at < ?", [gmdate('Y-m-d H:i:s', time() - 12 * 3600)]) as $m) {
      if (!claim_send('mr_wait_' . $m['id'])) continue;
      $t = 'Waiting for HQ: video meeting ' . $m['request_no']; $b = mb_substr($m['title'], 0, 120) . ' (' . $m['leader_name'] . ') has waited 12 hours for a decision.';
      notify_hq('meeting', $t, $b, '#/meetings/requests/' . (int) $m['id']);
      hq_mail('WDOS: ' . $t, 'A meeting request is waiting', '<p>' . htmlspecialchars($b) . '</p>', '#/meetings/requests/' . (int) $m['id']);
      $done['mr_wait'] = ($done['mr_wait'] ?? 0) + 1;
    }
  } catch (PDOException $e) { error_log('[wdos self meetings] ' . $e->getMessage()); }
  try {
    $r = cron_once('self_check', function () {
      $fixed = 0;
      foreach (rows('SELECT c.id FROM chapters c WHERE c.members<>(SELECT COUNT(*) FROM chapter_members m WHERE m.chapter_id=c.id AND m.ended_at IS NULL)') as $c) { chapter_refresh((int) $c['id']); $fixed++; }
      $n = fn(string $sql, array $p = []) => (int) (val($sql, $p) ?? 0);
      $issues = array_filter([
        'Members in two chapters at once' => $n('SELECT COUNT(*) FROM (SELECT user_id FROM chapter_members WHERE ended_at IS NULL GROUP BY user_id HAVING COUNT(*)>1) x'),
        'Members placed in a chapter of the other network' => $n('SELECT COUNT(*) FROM chapter_members m JOIN chapters c ON c.id=m.chapter_id JOIN users u ON u.id=m.user_id WHERE m.ended_at IS NULL AND u.network<>c.network'),
        'Chapters over capacity without an exception' => $n('SELECT COUNT(*) FROM chapters c WHERE c.capacity_exception=0 AND c.members>COALESCE(c.target_capacity, ?)', [policy_int('chapter_capacity')]),
        'Leader seats held by a suspended account' => $n("SELECT COUNT(*) FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND u.status IN ('suspended','withdrawn')"),
        'Emails that failed in the last day' => $n("SELECT COUNT(*) FROM mail_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at > ?", [gmdate('Y-m-d H:i:s', time() - 86400)]),
        'Phone notifications that failed in the last day' => $n("SELECT COUNT(*) FROM push_outbox WHERE status NOT IN ('queued','sending','sent') AND created_at > ?", [gmdate('Y-m-d H:i:s', time() - 86400)]),
      ]);
      if ($issues) {
        $lines = []; foreach ($issues as $k => $v) $lines[] = "$k: $v";
        $b = implode('. ', $lines) . '.' . ($fixed ? " $fixed chapter counts were corrected automatically." : '');
        notify_hq('reminder', 'WDOS daily self-check: ' . count($issues) . ' item(s) need HQ', mb_substr($b, 0, 240), isset($issues['Emails that failed in the last day']) ? '#/hq/system/email' : '#/hq/system');
        hq_mail('WDOS daily self-check', 'Items that need HQ', '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $lines)) . '</li></ul>' . ($fixed ? '<p>' . $fixed . ' chapter member counts were corrected automatically.</p>' : ''), '#/hq/system');
      }
      return ($fixed ? "$fixed counts fixed; " : '') . count($issues) . ' issues';
    });
    if ($r !== null) $done['self_check'] = $r;
  } catch (PDOException $e) { error_log('[wdos self check] ' . $e->getMessage()); }
}

/** Email many people at once, set-based, each in her language where a version exists ($texts: lang => [title, body]).
 *  Bulk email is held to the monthly budget (policy-free setting MAIL_MONTHLY_BUDGET, default 4500) so the Brevo plan is never
 *  exhausted; anything over the budget is skipped and the in-app and push notes still reach everyone. Returns how many were queued. */
function mail_many(string $where, array $wp, string $title, string $body, string $url, array $texts = []): int {
  if (!mail_configured()) return 0;
  $budget = (int) (cfg('MAIL_MONTHLY_BUDGET') ?: 4500);
  $used = (int) val('SELECT COUNT(*) FROM mail_outbox WHERE created_at >= ?', [gmdate('Y-m-01 00:00:00')]);
  $left = max(0, $budget - $used); if ($left === 0) { error_log('[wdos] monthly email budget reached; bulk email skipped'); return 0; }
  $n = 0;
  foreach (array_merge(['en'], NOTE_LANGS) as $l) {
    if ($left - $n <= 0) break;
    if (isset($texts[$l])) [$tt, $bb] = $texts[$l]; elseif ($l !== 'en' && ($tr = note_in($l, $title, $body))) [$tt, $bb] = $tr; else [$tt, $bb] = [$title, $body];
    $html = mail_wrap($tt, '<p>' . nl2br(htmlspecialchars((string) $bb)) . '</p>', 'Open in WDOS', app_url() . '/' . ltrim($url, '/'));
    $lw = $l === 'en' ? "(COALESCE(u.lang,'')='' OR u.lang='en' OR u.lang NOT IN ('" . implode("','", NOTE_LANGS) . "'))" : 'u.lang=?';
    $st = q("INSERT INTO mail_outbox (to_email,to_name,subject,html,created_at) SELECT u.email, u.first_name, ?, ?, ? FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id
             WHERE ($where) AND $lw AND u.email LIKE '%@%' AND u.email NOT REGEXP '[.](invalid|test|local|example)$' LIMIT " . (int) ($left - $n),
      array_merge(['WDOS: ' . mb_substr((string) $tt, 0, 150), $html, now()], $wp, $l === 'en' ? [] : [$l]));
    $n += $st->rowCount();
  }
  if ($n) after_response(fn() => mail_drain(20));
  return $n;
}


