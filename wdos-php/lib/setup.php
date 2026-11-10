<?php
declare(strict_types=1);
/* /api/setup : a browser-only setup page. Protected by ADMIN_KEY.
 * It creates the tables, makes the first HQ administrator and generates the
 * push keys, so nobody needs a terminal. */

function setup_key_ok(string $k): bool { $a = (string) cfg('ADMIN_KEY', ''); return $a !== '' && hash_equals($a, $k); }
function run_migrations(): array {
  db()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) NOT NULL PRIMARY KEY, ran_at DATETIME NOT NULL) DEFAULT CHARSET=utf8mb4');
  $done = array_column(rows('SELECT name FROM migrations'), 'name'); $ran = [];
  $files = glob(__DIR__ . '/../migrations/*.sql'); sort($files);
  foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $done, true)) continue;
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($f));
    foreach (array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql))) as $stmt) db()->exec($stmt);   // one statement at a time (TiDB rule)
    q('INSERT INTO migrations (name,ran_at) VALUES (?,?)', [$name, now()]);
    $ran[] = $name;
  }
  return $ran;
}

if ($METHOD === 'POST') {
  $b = body();
  if (!setup_key_ok((string) ($b['key'] ?? ''))) fail(403, cfg('ADMIN_KEY') ? 'That admin key is not correct.' : 'ADMIN_KEY is not set on the server yet. Add it in the hosting environment settings first.', 'key');
  $act = (string) ($b['action'] ?? '');
  if ($act === 'status') {
    $tables = false; $admins = 0; $members = 0; $dbErr = '';
    try { $tables = (bool) val("SHOW TABLES LIKE 'users'"); if ($tables) { $admins = (int) val("SELECT COUNT(*) FROM users WHERE role IN ('hq_admin','super_admin')"); $members = (int) val('SELECT COUNT(*) FROM users'); } }
    catch (Throwable $e) { $dbErr = $e->getMessage(); }
    out(['ok' => true, 'build' => WDOS_BUILD, 'db' => $dbErr === '', 'db_error' => $dbErr, 'tables' => $tables, 'admins' => $admins, 'members' => $members,
      'push' => push_configured(), 'google' => (bool) cfg('GOOGLE_CLIENT_ID') && (bool) cfg('GOOGLE_CLIENT_SECRET'), 'mail' => mail_configured(),
      'app_url' => app_origin(), 'google_redirect' => app_origin() . '/api/auth/google/callback', 'cron_url' => app_origin() . '/api/cron?key=YOUR_ADMIN_KEY']);
  }
  if ($act === 'migrate') out(['ok' => true, 'ran' => run_migrations()]);
  if ($act === 'vapid') out(['ok' => true] + vapid_generate());
  if ($act === 'admin') {
    $email = strtolower(trim((string) ($b['email'] ?? ''))); $pw = (string) ($b['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) fail(422, 'Enter a valid email and a password of at least 10 characters.', 'input');
    $u = row('SELECT id FROM users WHERE email=?', [$email]);
    if ($u) { q("UPDATE users SET role='super_admin', status='active', password_hash=? WHERE id=?", [password_hash($pw, PASSWORD_DEFAULT), (int) $u['id']]); $id = (int) $u['id']; }
    else {
      $id = ins("INSERT INTO users (email,password_hash,first_name,last_name,role,status,signup_method,created_at) VALUES (?,?,?,?,'super_admin','active','setup',?)",
        [$email, password_hash($pw, PASSWORD_DEFAULT), mb_substr(trim((string) ($b['first_name'] ?? 'WODDI')), 0, 80), mb_substr(trim((string) ($b['last_name'] ?? 'HQ')), 0, 80), now()]);
      q('UPDATE users SET member_no=? WHERE id=?', [sprintf('HQ-%04d', $id), $id]);
    }
    log_activity($id, $id, 'hq_access_given', 'Created from the setup page');
    out(['ok' => true, 'id' => $id]);
  }
  fail(400, 'Unknown setup action.', 'action');
}
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WDOS setup</title>
<style>
body{font:16px/1.55 system-ui,Segoe UI,Roboto,sans-serif;background:#140a12;color:#f3e9ef;margin:0;padding:28px 16px}
main{max-width:720px;margin:0 auto}h1{font-size:26px;margin:0 0 4px}h2{font-size:17px;margin:0 0 6px}p{color:#c9b5c1;margin:6px 0 12px}
section{background:#1d1019;border:1px solid #3a2233;border-radius:14px;padding:18px 20px;margin:14px 0}
input{font:inherit;width:100%;box-sizing:border-box;padding:11px 12px;border-radius:9px;border:1px solid #4a2c41;background:#140a12;color:#fff;margin:4px 0 10px}
button{font:inherit;font-weight:600;padding:11px 18px;border-radius:9px;border:0;background:#d4006a;color:#fff;cursor:pointer}button:disabled{opacity:.5}
code,pre{background:#0d060b;border:1px solid #3a2233;border-radius:8px;padding:2px 6px;word-break:break-all;white-space:pre-wrap;font-size:13.5px}pre{padding:10px 12px}
.ok{color:#9bd63a}.no{color:#ff8fb5}li{margin:4px 0}.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
</style></head><body><main>
<h1>WDOS setup</h1><p>Build <?= WDOS_BUILD ?>. Work down this page once after the first deployment. Everything happens in this browser tab.</p>
<section><h2>1. Admin key</h2><p>Paste the <code>ADMIN_KEY</code> value you set in the hosting environment.</p>
<input id="key" type="password" placeholder="Admin key" autocomplete="off"><button id="check">Check the server</button><ul id="status"></ul></section>
<section><h2>2. Create the database tables</h2><p>Safe to press again after every update: it only runs what is new.</p><button id="migrate">Create or update tables</button><p id="mres"></p></section>
<section><h2>3. First HQ administrator</h2><p>This account opens the HQ dashboard. If the email already exists it is promoted and given this password.</p>
<div class="row"><input id="fn" placeholder="First name"><input id="ln" placeholder="Surname"></div><input id="em" type="email" placeholder="Email"><input id="pw" type="password" placeholder="Password (10 characters or more)">
<button id="admin">Create HQ administrator</button><p id="ares"></p></section>
<section><h2>4. Push notification keys</h2><p>Press once, then copy both values into the hosting environment as <code>VAPID_PUBLIC</code> and <code>VAPID_PRIVATE</code>. Never generate new ones after members have switched notifications on, or their devices stop receiving.</p>
<button id="vapid">Generate push keys</button><pre id="vres" hidden></pre></section>
<script>
const $=id=>document.getElementById(id);
async function call(action,extra={}){const r=await fetch('/api/setup',{method:'POST',headers:{'Content-Type':'application/json','X-WDOS':'1'},body:JSON.stringify({key:$('key').value,action,...extra})});return r.json();}
const line=(ok,t)=>`<li class="${ok?'ok':'no'}">${ok?'Ready':'Not yet'}: ${t}</li>`;
$('check').onclick=async()=>{const s=await call('status');if(!s.ok){$('status').innerHTML=`<li class="no">${s.message}</li>`;return;}
$('status').innerHTML=line(s.db,'database connection'+(s.db_error?' ('+s.db_error+')':''))+line(s.tables,'tables created')+line(s.admins>0,'HQ administrator ('+s.admins+')')+line(s.push,'push keys')+line(s.google,'Google sign-in keys')+line(s.mail,'email (Brevo)')
+`<li>Members in the database: ${s.members}</li><li>Google redirect address to register: <code>${s.google_redirect}</code></li><li>Address for the 5-minute timer: <code>${s.cron_url}</code></li>`;};
$('migrate').onclick=async()=>{$('mres').textContent='Working…';const r=await call('migrate');$('mres').textContent=r.ok?(r.ran.length?'Ran: '+r.ran.join(', '):'Already up to date.'):r.message;};
$('admin').onclick=async()=>{const r=await call('admin',{first_name:$('fn').value,last_name:$('ln').value,email:$('em').value,password:$('pw').value});$('ares').textContent=r.ok?'Done. Sign in on the main page with this email and password.':r.message;};
$('vapid').onclick=async()=>{const r=await call('vapid');$('vres').hidden=false;$('vres').textContent=r.ok?'VAPID_PUBLIC='+r.public+'\nVAPID_PRIVATE='+r.private:r.message;};
</script></main></body></html>
