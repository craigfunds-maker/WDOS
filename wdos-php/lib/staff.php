<?php
declare(strict_types=1);
/* HQ staff logins, and how each leader signs in.
 * WDOS never stores a readable password, so nobody can look one up. What HQ can do is see the sign-in name and give a new temporary password, shown once. */

function temp_password(): string { $a = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; $t = ''; for ($i = 0; $i < 10; $i++) $t .= $a[random_int(0, strlen($a) - 1)]; return $t; }
function signin_shape(array $r): array {
  $phoneLogin = str_ends_with((string) $r['email'], '@phone.wdos.invalid');
  return ['id' => (int) $r['id'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']), 'signs_in_with' => $phoneLogin ? (string) $r['phone'] : (string) $r['email'], 'kind' => $phoneLogin ? 'Phone number' : 'Email',
    'password' => $r['password_hash'] ? ((int) $r['must_change_password'] === 1 ? 'Temporary password, not yet changed' : 'Set by the person. Cannot be read.') : (($r['signup_method'] ?? '') === 'google' ? 'None. Signs in with Google.' : 'No password set yet. Give a temporary one.'),
    'fingerprint' => (int) ($r['prints'] ?? 0) > 0, 'last_seen_at' => $r['last_seen_at'], 'status' => $r['status'], 'role' => $r['role'], 'created_at' => $r['created_at']];
}

route('GET', '/hq/staff', function () {
  $me = require_hq();
  $list = rows("SELECT u.*, (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.user_id=u.id) prints FROM users u WHERE u.role IN ('hq_admin','super_admin') ORDER BY u.role DESC, u.first_name, u.last_name");
  return ['items' => array_map('signin_shape', $list), 'can_manage' => $me['role'] === 'super_admin', 'me' => (int) $me['id']];
});
/* A new HQ staff login. Only a super administrator can make one. The temporary password is shown once and must be changed at first sign-in. */
route('POST', '/hq/staff', function () {
  $me = require_hq(); if ($me['role'] !== 'super_admin') fail(403, 'Only a super administrator can create HQ staff logins.', 'forbidden');
  $first = str_in('first_name', 80); $last = str_in('last_name', 80); $email = strtolower(str_in('email', 190));
  if (mb_strlen($first) < 2 || mb_strlen($last) < 2) fail(422, 'Enter the first name and surname.', 'name');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Enter a valid email address. The staff member signs in with it.', 'email');
  if (val('SELECT 1 FROM users WHERE email=?', [$email])) fail(409, 'An account with this email already exists. Open that person under Members and give HQ access there.', 'email_taken');
  $temp = temp_password();
  $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,phone,role,status,signup_method,must_change_password,created_at) VALUES (?,?,?,?,?,'hq_admin','active','staff',1,?)",
    [$email, password_hash($temp, PASSWORD_DEFAULT), $first, $last, preg_replace('/[^\d+]/', '', str_in('phone', 32)), now()]);
  log_activity((int) $me['id'], $id, 'hq_staff_created', $email);
  return ['ok' => true, 'id' => $id, 'email' => $email, 'temporary_password' => $temp];
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
