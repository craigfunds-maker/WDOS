<?php
declare(strict_types=1);
/* HQ staff logins, and how each leader signs in.
 * WDOS never stores a readable password, so nobody can look one up. What HQ can do is see the sign-in name and give a new temporary password, shown once. */

function temp_password(int $n = 10): string { $a = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; $t = ''; for ($i = 0; $i < $n; $i++) $t .= $a[random_int(0, strlen($a) - 1)]; return $t; }
function signin_shape(array $r): array {
  $phoneLogin = str_ends_with((string) $r['email'], '@phone.wdos.invalid');
  return ['id' => (int) $r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'signs_in_with' => $phoneLogin ? (string) $r['phone'] : (string) $r['email'], 'kind' => $phoneLogin ? 'Phone number' : 'Email',
    'password' => $r['password_hash'] ? ((int) $r['must_change_password'] === 1 ? 'Temporary password, not yet changed' : 'Set by the person. Cannot be read.') : (($r['signup_method'] ?? '') === 'google' ? 'None. Signs in with Google.' : 'No password set yet. Give a temporary one.'),
    'fingerprint' => (int) ($r['prints'] ?? 0) > 0, 'last_seen_at' => $r['last_seen_at'], 'status' => $r['status'], 'role' => $r['role'], 'created_at' => $r['created_at']];
}

route('GET', '/hq/staff', function () {
  $me = require_hq();
  $list = rows("SELECT u.*, (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.user_id=u.id) prints FROM users u WHERE u.role IN ('hq_admin','super_admin') AND u.status<>'deleted' ORDER BY u.role DESC, u.first_name, u.last_name");
  return ['items' => array_map(fn($r) => signin_shape($r) + ['first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'email' => $r['email'], 'phone' => (string) $r['phone'], 'designation' => (string) ($r['designation'] ?? '')], $list), 'can_manage' => $me['role'] === 'super_admin', 'me' => (int) $me['id']];
});
/* A new HQ staff login. Only a super administrator can make one. The temporary password is shown once and must be changed at first sign-in. */
route('POST', '/hq/staff', function () {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator can create HQ staff logins.', 'forbidden');
  $first = str_in('first_name', 80); $last = str_in('last_name', 80); $email = strtolower(str_in('email', 190));
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter the first name and surname.', 'name');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address. The staff member signs in with it.', 'email');
  if (val('SELECT 1 FROM users WHERE email=?', [$email])) fail(409, 'An account with this email already exists. Open that person under Members and give HQ access there.', 'email_taken');
  $temp = temp_password();
  $b = body(); $feat = !empty($b['all']) || !isset($b['features']) ? null : implode(',', array_values(array_intersect(array_keys(HQ_FEATURES), (array) $b['features'])));
  if ($feat === '') fail(422, 'Tick at least one area, or choose every area.', 'features');
  $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,phone,role,status,signup_method,must_change_password,designation,hq_features,created_at) VALUES (?,?,?,?,?,'hq_admin','active','staff',1,?,?,?)",
    [$email, password_hash($temp, PASSWORD_DEFAULT), $first, $last, preg_replace('/[^\d+]/', '', str_in('phone', 32)), str_in('designation', 160), $feat, now()]);
  $mailed = !empty($b['send_invite']) && envoy_invite_mail($id, 'staff'); if ($mailed) after_response(fn() => mail_drain(4));
  log_activity((int) $me['id'], $id, 'hq_staff_created', $email);
  return ['ok' => true, 'id' => $id, 'email' => $email, 'temporary_password' => $temp, 'invited' => $mailed];
});
/* Switch a staff login off or back on. Off signs the person out everywhere at once. */
route('POST', '/hq/staff/(\d+)', function ($id) {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator can change HQ staff logins.', 'forbidden');
  $u = row("SELECT * FROM users WHERE id=? AND role IN ('hq_admin','super_admin')", [(int) $id]); if (!$u) fail(404, 'That staff login was not found.', 'not_found');
  if ((int) $id === (int) $me['id']) fail(422, 'You cannot change your own login here.', 'self');
  if ($u['role'] === 'super_admin') fail(403, 'A super administrator login cannot be switched off here.', 'forbidden');
  $on = !empty(body()['active']);
  q('UPDATE users SET status=? WHERE id=?', [$on ? 'active' : 'suspended', (int) $id]); if (!$on) q('DELETE FROM sessions WHERE user_id=?', [(int) $id]);
  log_activity((int) $me['id'], (int) $id, $on ? 'hq_staff_restored' : 'hq_staff_switched_off');
  return ['ok' => true];
});
/* How each leader signs in. No password is ever returned: there is none to return. */
route('GET', '/hq/leader-logins', function () {
  require_hq();
  $list = rows("SELECT u.*, s.network seat_network, s.area_label, s.status seat_status, CASE WHEN s.network='WNNN' THEN p.title_wnnn ELSE p.title_wgmn END title, o.name unit_name,
                       (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.user_id=u.id) prints
                FROM seats s JOIN users u ON u.id=s.user_id JOIN positions p ON p.code=s.position_code JOIN org_units o ON o.id=s.org_unit_id
                WHERE s.ends_at IS NULL ORDER BY p.rank_no, o.name, u.first_name LIMIT 2000");
  return ['as_of' => now(), 'items' => array_map(fn($r) => signin_shape($r) + ['title' => $r['title'], 'network' => $r['seat_network'], 'area' => $r['area_label'] !== '' ? $r['area_label'] : $r['unit_name'], 'seat_status' => $r['seat_status'], 'member_no' => $r['member_no']], $list)];
});

/* ---------------------------------------------------------------- v109.54: change or delete a login --
 * Azeez, 24 Nov 2026: "every action, I should be able to modify, edit, delete ... also the staff logins".
 * Deleting a person keeps the record of what she did (activity log, meetings she held, reports) but closes the account for good:
 * she is signed out everywhere, her seat and links end, her email and phone are freed so they can be used again, and she drops out
 * of every list. Nothing she wrote is lost. HQ staff logins are deleted only by a super administrator; nobody deletes their own. */
function person_delete(array $me, int $id, string $why): array {
  $u = row('SELECT * FROM users WHERE id=?', [$id]); if (!$u || $u['status'] === 'deleted') fail(404, 'That person was not found.', 'not_found');
  if ($id === (int) $me['id']) fail(422, 'You cannot delete your own login.', 'self');
  if ($u['role'] === 'super_admin') fail(403, 'A super administrator login cannot be deleted.', 'forbidden');
  if (empty(body()['confirm'])) fail(422, 'Confirm that you want to delete this login.', 'confirm');
  $was = trim($u['first_name'] . ' ' . $u['last_name']) . ' <' . $u['email'] . '>' . ($u['phone'] ? ' ' . $u['phone'] : '');
  q('UPDATE seats SET ends_at=? WHERE user_id=? AND ends_at IS NULL', [now(), $id]);
  try { q("UPDATE envoys SET status='removed' WHERE user_id=?", [$id]); } catch (PDOException $e) { /* before envoys */ }
  q('UPDATE referral_links SET active=0 WHERE owner_id=?', [$id]);
  q('DELETE FROM sessions WHERE user_id=?', [$id]);
  foreach (['webauthn_credentials', 'push_subscriptions'] as $t) { try { q("DELETE FROM $t WHERE user_id=?", [$id]); } catch (PDOException $e) { /* table not there */ } }
  q("UPDATE users SET status='deleted', email=?, phone='', password_hash=NULL, must_change_password=0 WHERE id=?", ["deleted-$id@deleted.wdos.invalid", $id]);
  log_activity((int) $me['id'], $id, $why, $was);
  if (function_exists('lm_vacancy_scan')) after_response(function () { lm_vacancy_scan(); });
  return ['ok' => true, 'deleted' => trim($u['first_name'] . ' ' . $u['last_name'])];
}
/** Name, email, phone and designation of a staff login. */
route('POST', '/hq/staff/(\d+)/edit', function ($id) {
  $me = require_hq(); $id = (int) $id;
  if ($me['role'] !== 'super_admin' && $id !== (int) $me['id']) fail(403, 'Only a super administrator can change another staff login.', 'forbidden');
  $u = row("SELECT * FROM users WHERE id=? AND role IN ('hq_admin','super_admin') AND status<>'deleted'", [$id]); if (!$u) fail(404, 'That staff login was not found.', 'not_found');
  $first = str_in('first_name', 80); $last = str_in('last_name', 80); $email = strtolower(str_in('email', 190));
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter the first name and surname.', 'name');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address. The staff member signs in with it.', 'email');
  if (val('SELECT 1 FROM users WHERE email=? AND id<>?', [$email, $id])) fail(409, 'Another account already uses this email.', 'email_taken');
  q('UPDATE users SET first_name=?, last_name=?, email=?, phone=?, designation=? WHERE id=?', [$first, $last, $email, preg_replace('/[^\d+]/', '', str_in('phone', 32)), str_in('designation', 160), $id]);
  if ($email !== $u['email'] && $id !== (int) $me['id']) q('DELETE FROM sessions WHERE user_id=?', [$id]);   // a new sign-in name: she signs in again with it
  log_activity((int) $me['id'], $id, 'hq_staff_changed', $u['email'] !== $email ? $u['email'] . ' to ' . $email : $email);
  return ['ok' => true];
});
route('POST', '/hq/staff/(\d+)/delete', function ($id) {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator can delete HQ staff logins.', 'forbidden');
  if (!val("SELECT 1 FROM users WHERE id=? AND role IN ('hq_admin','super_admin')", [(int) $id])) fail(404, 'That staff login was not found.', 'not_found');
  return person_delete($me, (int) $id, 'hq_staff_deleted');
});
/** A member, leader or envoy. HQ staff logins are deleted from HQ staff. */
route('POST', '/hq/members/(\d+)/delete', function ($id) {
  $me = require_hq();
  if (val("SELECT 1 FROM users WHERE id=? AND role IN ('hq_admin','super_admin')", [(int) $id])) fail(422, 'This is an HQ staff login. Delete it under HQ staff logins.', 'staff');
  return person_delete($me, (int) $id, 'member_deleted');
});
/** v109.54: a member's, leader's or envoy's name, email and phone. A phone-only login keeps signing in with her phone. */
route('POST', '/hq/members/(\d+)/contact', function ($id) {
  $me = require_hq(); $id = (int) $id; $u = row("SELECT * FROM users WHERE id=? AND status<>'deleted'", [$id]); if (!$u) fail(404, 'That person was not found.', 'not_found');
  if ($u['role'] === 'super_admin' && $me['role'] !== 'super_admin') fail(403, 'Only a super administrator can change this account.', 'forbidden');
  $first = str_in('first_name', 80); $last = str_in('last_name', 80); if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter the first name and surname.', 'name');
  $phoneLogin = str_ends_with((string) $u['email'], '@phone.wdos.invalid');
  $email = strtolower(str_in('email', 190)); $phone = preg_replace('/[^\d+]/', '', str_in('phone', 32));
  if ($email === '' && $phoneLogin) $email = $u['email'];                   // she has no email: she keeps signing in with her phone
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address, or leave it empty if she signs in with her phone.', 'email');
  if (val('SELECT 1 FROM users WHERE email=? AND id<>?', [$email, $id])) fail(409, 'Another account already uses this email.', 'email_taken');
  if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 8) fail(422, 'Check the phone number.', 'phone');
  if ($phoneLogin && $phone === '') fail(422, 'She signs in with her phone number, so it cannot be empty.', 'phone');
  q('UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?', [$first, $last, $email, $phone, $id]);
  log_activity((int) $me['id'], $id, 'contact_corrected', trim("$first $last") . ($email !== $u['email'] ? ' · email changed' : '') . ($phone !== (string) $u['phone'] ? ' · phone changed' : ''));
  return ['ok' => true];
});
