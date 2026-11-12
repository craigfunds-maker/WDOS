<?php
/* Leader terms and their end, the operations figures, and backup.
   Three-day finalisation checklist: member and leader lifecycle (role expiry, replacement, access revocation),
   dashboard requirements (automation share, delivery failures, support incidents), backup and restoration. */

/** Set or clear the last day of an appointment. A date in the past is refused: ending a seat is its own, deliberate step. */
function seat_term_set(int $seatId, string $date, int $byId): ?string {
  $s = row('SELECT * FROM seats WHERE id=? AND ends_at IS NULL', [$seatId]); if (!$s) fail(404, 'That appointment is not open.', 'not_found');
  $date = trim($date);
  if ($date === '') { q('UPDATE seats SET term_ends_on=NULL WHERE id=?', [$seatId]); log_activity($byId, (int) $s['user_id'], 'seat_term', 'no end date'); return null; }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) fail(422, 'Give the end date as a real calendar date.', 'term_ends_on');
  if ($date < gmdate('Y-m-d')) fail(422, 'The end date cannot be in the past. To end an appointment today, use End this appointment.', 'term_ends_on');
  q('UPDATE seats SET term_ends_on=? WHERE id=?', [$date, $seatId]); log_activity($byId, (int) $s['user_id'], 'seat_term', 'ends ' . $date);
  return $date;
}

/** End one appointment because its term is over. Her access as a leader stops at once (current_seat reads open seats only); her membership is untouched. */
function seat_term_end(array $s, int $byId): void {
  q("UPDATE seats SET ends_at=?, end_reason='term_expired' WHERE id=? AND ends_at IS NULL", [now(), (int) $s['id']]);
  $title = position_title($s['position_code'], $s['network']) . ($s['area_label'] !== '' ? ', ' . $s['area_label'] : '');
  log_activity($byId ?: null, (int) $s['user_id'], 'seat_term_ended', $title . ($byId ? '' : ' (automatic)'));
  notify((int) $s['user_id'], 'appointment', 'Your term as ' . $title . ' has ended', 'Thank you for your service. Your membership continues as before. WODDI HQ will be in touch about what comes next.', '#/');
}

/** Once a day: tell HQ about terms ending in 30 days and terms ending today; end them by itself only when the rule says so. */
function ops_automations(array &$done): void {
  try {
    $r = cron_once('seat_terms', function () {
      $n = 0; $today = gmdate('Y-m-d'); $soon = gmdate('Y-m-d', time() + 30 * 86400);
      $base = "SELECT s.*, u.first_name, u.last_name FROM seats s JOIN users u ON u.id=s.user_id WHERE s.ends_at IS NULL AND s.term_ends_on";
      foreach (rows("$base=?", [$soon]) as $s) { notify_hq('appointment', 'A leader term ends in 30 days', trim($s['first_name'] . ' ' . $s['last_name']) . ', ' . position_title($s['position_code'], $s['network']) . ($s['area_label'] !== '' ? ', ' . $s['area_label'] : '') . '. Extend the term or plan the replacement.', '#/members/' . (int) $s['user_id']); $n++; }
      $auto = policy('seat_term_expiry') === 'auto';
      foreach (rows("$base<=?", [$today]) as $s) {
        $who = trim($s['first_name'] . ' ' . $s['last_name']) . ', ' . position_title($s['position_code'], $s['network']) . ($s['area_label'] !== '' ? ', ' . $s['area_label'] : '');
        if ($auto) { seat_term_end($s, 0); notify_hq('appointment', 'A leader term has ended and the seat is vacant', $who . '. Appoint the replacement from the member\'s page or from Leadership structure.', '#/structure'); $n++; }
        elseif ($s['term_ends_on'] === $today) { notify_hq('appointment', 'A leader term ends today', $who . '. The seat stays open until you extend the term or end the appointment.', '#/members/' . (int) $s['user_id']); $n++; }
      }
      return "$n told";
    });
    if ($r !== null) $done['seat_terms'] = $r;
  } catch (PDOException $e) { /* before migration 021 */ }
}

/** Tables a backup leaves out: sign-in sessions and throttling counters are worthless after a restore and should not travel. */
const BACKUP_SKIP = ['sessions', 'login_attempts', 'auth_challenges'];
