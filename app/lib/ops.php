<?php
/* Shared by the admin area and the scheduled job: audiences, campaigns, reminders. */
declare(strict_types=1);

const AUDIENCES = ['all' => 'All learners', 'gmn' => 'WGMN learners', 'nnn' => 'WNNN learners', 'inactive14' => 'Not seen for 14 days or more', 'not_started' => 'Registered but never started', 'graduates' => 'Certificate holders', 'staff' => 'Staff and admins', 'me' => 'Only me (test)'];

function audience_uids(string $aud, ?string $country = null): array {
    $w = "COALESCE(status,'active') NOT IN ('banned','deleted','suspended')"; $p = [];
    if ($aud === 'me') return [uid()];
    if ($aud === 'gmn') $w .= " AND COALESCE(pathway,'gmn') <> 'nnn'";
    elseif ($aud === 'nnn') $w .= " AND pathway = 'nnn'";
    elseif ($aud === 'inactive14') { $w .= ' AND (last_seen IS NULL OR last_seen < ?)'; $p[] = gmdate('Y-m-d H:i:s', time() - 14 * 86400); }
    elseif ($aud === 'not_started') $w .= ' AND uid NOT IN (SELECT uid FROM learning_events WHERE uid IS NOT NULL) AND uid NOT IN (SELECT uid FROM user_progress WHERE uid IS NOT NULL AND rooting_started = 1)';
    elseif ($aud === 'graduates') $w .= ' AND uid IN (SELECT uid FROM certificates WHERE COALESCE(revoked,0) = 0)';
    elseif ($aud === 'staff') $w .= " AND role IN ('admin','super_admin','moderator','instructor','tutor')";
    if ($country) { $w .= ' AND country = ?'; $p[] = $country; }
    return array_column(rows("SELECT uid FROM users WHERE $w", $p), 'uid');
}

function mail_html(string $title, string $body, string $link = '', string $cta = 'Open WODDI Institute'): string {
    $b = nl2br(preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', e($body)));
    $href = e($link ? (preg_match('#^https?://#', $link) ? $link : url($link)) : url(''));
    return '<div style="background:#FBF7F4;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#2A0F2B"><div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #EFE4EA"><div style="background:#2A0F2B;color:#fff;padding:18px 24px;font-weight:bold;font-size:18px">WODDI Institute</div><div style="padding:24px"><h1 style="font-size:20px;margin:0 0 12px">' . e($title) . '</h1><p style="font-size:15px;line-height:1.6;margin:0 0 20px">' . $b . '</p><a href="' . $href . '" style="display:inline-block;background:#D4006A;color:#fff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold">' . e($cta) . '</a></div><div style="padding:14px 24px;background:#FBF7F4;color:#7A6875;font-size:12px">You are receiving this because you have a WODDI Institute account. You can turn reminders off in Settings.</div></div></div>';
}

/* Creates the campaign, writes every in-app notification at once, and queues email and push for the sender. */
function campaign_queue(string $subject, string $body, array $channels, array $uids, string $link = '', string $audience = '', bool $test = false): string {
    $mid = (string)ins('ops_messages', ['subject' => $subject, 'body' => $body, 'channels' => $channels, 'audience' => $audience, 'recipients' => count($uids), 'email_sent' => 0, 'inapp_sent' => 0, 'push_sent' => 0, 'failed' => 0, 'is_test' => $test ? 1 : 0, 'created_by' => uid()]);
    $inapp = 0;
    foreach (array_chunk($uids, 200) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        foreach (rows("SELECT uid, email, name, display_name, phone, role, country FROM users WHERE uid IN ($in)", $chunk) as $u) {
            if (in_array('inapp', $channels, true)) { notify($u['uid'], $subject, $body, $link, 'announcement', false); $inapp++; }
            ins('ops_message_recipients', ['message_id' => $mid, 'leader_key' => $link, 'name' => display_name($u), 'email' => $u['email'], 'phone' => $u['phone'], 'role' => $u['role'], 'country' => $u['country'], 'uid' => $u['uid'],
                'email_status' => in_array('email', $channels, true) && filter_var($u['email'], FILTER_VALIDATE_EMAIL) ? 'queued' : 'skipped', 'push_status' => in_array('push', $channels, true) ? 'queued' : 'skipped', 'inapp_status' => in_array('inapp', $channels, true) ? 'sent' : 'skipped']);
        }
    }
    q('UPDATE ops_messages SET inapp_sent = ? WHERE id = ?', [$inapp, $mid]);
    return $mid;
}

/* Sends queued email and push until the time budget runs out. Safe to call again and again. */
function campaign_run(int $seconds = 20): array {
    require_once APP_ROOT . '/lib/push.php';
    $end = microtime(true) + $seconds; $done = ['email' => 0, 'push' => 0, 'failed' => 0];
    while (microtime(true) < $end) {
        $batch = rows("SELECT r.*, m.subject, m.body FROM ops_message_recipients r JOIN ops_messages m ON m.id = r.message_id WHERE r.email_status = 'queued' OR r.push_status = 'queued' ORDER BY r.created_at LIMIT 25");
        if (!$batch) break;
        foreach ($batch as $r) {
            $set = [];
            if ($r['push_status'] === 'queued') { $n = 0; try { $n = push_to_user((string)$r['uid'], $r['subject'], mb_strimwidth((string)$r['body'], 0, 140, '...'), $r['leader_key'] ? url($r['leader_key']) : url('messages')); } catch (Throwable $e) {} $set['push_status'] = $n ? 'sent' : (val('SELECT 1 FROM push_subscriptions WHERE uid = ? LIMIT 1', [(string)$r['uid']]) ? 'not_delivered' : 'no_device'); if ($n) { $done['push']++; q('UPDATE ops_messages SET push_sent = push_sent + 1 WHERE id = ?', [$r['message_id']]); } }
            if ($r['email_status'] === 'queued') { $ok = false; try { $ok = send_mail((string)$r['email'], $r['subject'], mail_html($r['subject'], (string)$r['body'], (string)$r['leader_key'])); } catch (Throwable $e) { $set['error'] = mb_substr($e->getMessage(), 0, 200); } $set['email_status'] = $ok ? 'sent' : 'failed'; q('UPDATE ops_messages SET ' . ($ok ? 'email_sent = email_sent + 1' : 'failed = failed + 1') . ' WHERE id = ?', [$r['message_id']]); $done[$ok ? 'email' : 'failed']++; }
            if ($set) upd('ops_message_recipients', $set, 'id = ?', [$r['id']]);
            if (microtime(true) >= $end) break;
        }
    }
    $done['left'] = (int)val("SELECT COUNT(*) FROM ops_message_recipients WHERE email_status = 'queued' OR push_status = 'queued'");
    return $done;
}

const REMINDER_TIERS = [[30, 'tier30', 'We kept your place', 'It has been a month, {name}. Your courses and your progress are still here. Ten minutes today is enough to begin again.'], [14, 'tier14', 'Your next lesson is waiting', '{name}, two weeks is a long time away from your goal. Open one lesson today and pick up from where you stopped.'], [7, 'tier7', 'One small step this week', 'Hello {name}. You have not learned for a week. Your next lesson is short, and it earns Growth Points.'], [3, 'tier3', 'Keep your streak going', 'Hello {name}. Come back for one short lesson today and keep your learning going.']];

/* Gentle inactivity reminders, at most one per learner every 3 days, and a learner only gets each tier once per absence. */
function reminders_run(int $limit = 60): array {
    require_once APP_ROOT . '/lib/push.php';
    $run = uuid4(); $sent = 0; $now = time();
    $list = rows("SELECT uid, email, name, display_name, last_seen, last_reminder_tier, last_reminder_sent_at FROM users WHERE COALESCE(reminders_opt_out,0) = 0 AND COALESCE(status,'active') NOT IN ('banned','deleted','suspended') AND COALESCE(role,'student') NOT IN ('admin','super_admin') AND last_seen IS NOT NULL AND last_seen < ? AND (last_reminder_sent_at IS NULL OR last_reminder_sent_at < ?) ORDER BY last_seen DESC LIMIT " . (int)$limit, [gmdate('Y-m-d H:i:s', $now - 3 * 86400), gmdate('Y-m-d H:i:s', $now - 3 * 86400)]);
    foreach ($list as $u) {
        $days = (int)floor(($now - strtotime((string)$u['last_seen'])) / 86400); $tier = null;
        foreach (REMINDER_TIERS as $t) if ($days >= $t[0]) { $tier = $t; break; }
        if (!$tier || ((int)$u['last_reminder_tier'] === $tier[0] && $u['last_reminder_sent_at'] && strtotime((string)$u['last_reminder_sent_at']) > strtotime((string)$u['last_seen']))) continue;
        $body = str_replace('{name}', first_name($u), $tier[3]);
        notify($u['uid'], $tier[2], $body, 'learn', 'reminder', false);
        $push = 0; try { $push = push_to_user($u['uid'], $tier[2], $body, url('learn')); } catch (Throwable $e) {}
        $mail = filter_var($u['email'], FILTER_VALIDATE_EMAIL) ? send_mail($u['email'], $tier[2], mail_html($tier[2], $body, 'learn', 'Continue learning')) : false;
        ins('reminder_log', ['uid' => $u['uid'], 'kind' => $tier[1], 'email_status' => $mail ? 'sent' : 'skipped', 'push_status' => $push ? 'sent' : 'no_device', 'inapp_status' => 'sent', 'detail' => "$days days inactive", 'run_id' => $run]);
        upd('users', ['last_reminder_sent_at' => now(), 'last_reminder_tier' => $tier[0]], 'uid = ?', [$u['uid']]); $sent++;
    }
    return ['checked' => count($list), 'sent' => $sent];
}

/* A reminder to everyone who said they will attend an event that starts within the next 24 hours. */
function event_reminders_run(): int {
    $n = 0;
    foreach (rows("SELECT * FROM al_events WHERE starts_at BETWEEN ? AND ? AND COALESCE(status,'published') NOT IN ('cancelled','draft')", [now(), gmdate('Y-m-d H:i:s', time() + 86400)]) as $ev)
        foreach (rows('SELECT uid FROM al_rsvps WHERE event_id = ?', [$ev['id']]) as $r) {
            if (val('SELECT 1 FROM reminder_log WHERE uid = ? AND kind = ?', [$r['uid'], 'event:' . $ev['id']])) continue;
            notify($r['uid'], 'Starting soon: ' . $ev['title'], gmdate('l, g:i A', strtotime((string)$ev['starts_at'])) . ' GMT', 'community?tab=events', 'event');
            ins('reminder_log', ['uid' => $r['uid'], 'kind' => 'event:' . $ev['id'], 'inapp_status' => 'sent', 'push_status' => 'sent']); $n++;
        }
    return $n;
}

function audit(string $action, ?string $targetUid = null, ?string $targetId = null, array $meta = [], ?string $table = null): void {
    try { ins('admin_audit_logs', ['admin_uid' => uid(), 'action' => $action, 'target_uid' => $targetUid, 'target_id' => $targetId, 'meta' => $meta, 'target_table' => $table]); } catch (Throwable $e) {}
}
function csv_out(string $name, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '-' . gmdate('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    if ($rows) { fputcsv($o, array_keys($rows[0])); foreach ($rows as $r) fputcsv($o, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r)); }
    exit;
}
