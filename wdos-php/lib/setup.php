<?php
declare(strict_types=1);
/* /api/setup : a browser-only setup page. Protected by ADMIN_KEY.
 * It creates the tables, makes the first HQ administrator and generates the
 * push keys, so nobody needs a terminal. */

function setup_key_ok(string $k): bool { $a = (string) cfg('ADMIN_KEY', ''); return $a !== '' && hash_equals($a, $k); }
require_once __DIR__ . '/migrate.php';

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
  if ($act === 'import') {
    require __DIR__ . '/import.php';
    @set_time_limit(120);
    $list = is_array($b['rows'] ?? null) ? array_slice($b['rows'], 0, 80) : [];
    out(['ok' => true, 'commit' => !empty($b['commit'])] + import_rows($list, !empty($b['commit'])));
  }
  if ($act === 'insp_test') {
    require_once __DIR__ . '/inspire_fns.php'; require_once __DIR__ . '/notify.php';
    $u = row('SELECT * FROM users WHERE email=?', [strtolower(trim((string) ($b['email'] ?? '')))]);
    if (!$u) fail(404, 'No account has that email.', 'not_found');
    $seat = current_seat((int) $u['id']);
    if (!$seat) fail(422, 'That account holds no leadership seat. Appoint it from Members first: inspiration goes to volunteer leaders only.', 'no_seat');
    $m = insp_weekly_for($seat['network'], insp_week());
    if (!$m) fail(422, 'No weekly message is loaded for week ' . insp_week() . '.', 'no_message');
    set_setting('weekly_preview', $u['id'] . ':' . hq_today()->format('Y-m-d'));
    notify((int) $u['id'], 'inspiration', $m['headline'], $m['key_thought'], '#/');
    out(['ok' => true, 'week' => (int) $m['seq'], 'headline' => $m['headline'], 'devices' => (int) val('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=?', [(int) $u['id']])] + push_drain(10));
  }
  if ($act === 'templates_import') {
    $n = 0; $skipped = []; $loaded = [];
    foreach (is_array($b['rows'] ?? null) ? $b['rows'] : [] as $raw) {
      $j = is_array($raw) ? $raw : json_decode((string) $raw, true); if (!is_array($j)) continue;
      $code = (string) ($j['code'] ?? $j['key'] ?? $j['slug'] ?? $j['template'] ?? $j['id'] ?? '');
      $def = $j['definition'] ?? $j['schema'] ?? $j['body'] ?? $j['config'] ?? $j['template_json'] ?? null;
      if (is_string($def)) $def = json_decode($def, true);
      if (!is_array($def) && isset($j['sections'])) $def = ['sections' => is_string($j['sections']) ? json_decode($j['sections'], true) : $j['sections']];
      if (isset($def[0]) && is_array($def)) $def = ['sections' => $def];
      if (is_array($def) && !isset($def['levels']) && is_array($j['levels'] ?? null)) $def['levels'] = $j['levels'];
      if ($code === '' || !preg_match('/^[a-z0-9_]{2,40}$/', $code) || !is_array($def) || !is_array($def['sections'] ?? null) || !$def['sections']) { $skipped[] = $code !== '' ? $code : '(row with no code)'; continue; }
      $title = (string) ($j['title'] ?? $j['name'] ?? $j['label'] ?? $def['title'] ?? ucwords(str_replace('_', ' ', $code)));
      $cad = strtolower((string) ($j['cadence'] ?? $j['frequency'] ?? $def['cadence'] ?? ''));
      $cad = str_contains($cad, 'month') || str_starts_with($code, 'monthly') ? 'monthly' : (str_contains($cad, 'week') || str_starts_with($code, 'weekly') ? 'weekly' : 'adhoc');
      $ev = !empty($j['requires_evidence']) || !empty($def['requires_evidence']) || !empty($j['evidence_required']) ? 1 : 0;
      q('DELETE FROM report_templates WHERE code=?', [$code]);
      q('INSERT INTO report_templates (code,title,cadence,requires_evidence,definition,is_active,loaded_at) VALUES (?,?,?,?,?,?,?)', [$code, mb_substr($title, 0, 160), $cad, $ev, json_encode($def, JSON_UNESCAPED_UNICODE), (($j['is_active'] ?? $j['active'] ?? true) === false) ? 0 : 1, now()]);
      $n++; $loaded[] = $code . ' (' . count($def['sections']) . ' sections, ' . $cad . ')';
    }
    out(['ok' => true, 'loaded' => $loaded, 'skipped' => $skipped]);
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
<section><h2>5. Bring members across from the current WDOS</h2>
<p>This copies every member and leader, with the password each one already uses, from the current WDOS into this one. Passwords travel as scrambled hashes inside the file and are never shown. Keep the file private and delete it afterwards.</p>
<ol><li>Open the current WDOS project in Supabase, then <b>SQL Editor</b>. Press <b>Copy the export query</b> below and paste it in.</li>
<li>Beside the Run button, change the row limit from 100 to <b>No limit</b>. Press <b>Run</b>.</li>
<li>Above the results press <b>Export</b>, then <b>Download CSV</b>.</li>
<li>Choose that file here and press <b>Check the file</b>. Nothing is saved at this step.</li>
<li>Read the summary. If it looks right, press <b>Import now</b>.</li></ol>
<button id="copysql" style="background:#3a2233">Copy the export query</button> <span id="copied" class="ok"></span>
<textarea id="sql" readonly rows="5" style="width:100%;box-sizing:border-box;margin:10px 0;background:#0d060b;color:#c9b5c1;border:1px solid #3a2233;border-radius:8px;font:12px ui-monospace,Consolas,monospace;padding:8px">with recursive up as (
  select o.id as start_id, o.id, o.parent_id, o.level::text as lvl, o.name, o.country_iso::text as iso, 0 as d
  from org_units o
  union all
  select up.start_id, o.id, o.parent_id, o.level::text, o.name, o.country_iso::text, up.d + 1
  from up join org_units o on o.id = up.parent_id where up.d &lt; 8
), chain as (
  select start_id,
    max(name) filter (where lvl = 'country') as country_name,
    max(iso) as country_iso,
    max(name) filter (where lvl = 'state_region') as state_name,
    max(name) filter (where lvl = 'district_lga') as lga_name,
    max(name) filter (where lvl = 'community_cluster') as cluster_name,
    max(name) filter (where lvl = 'chapter') as chapter_name
  from up group by start_id
), seat as (
  select distinct on (ra.profile_id) ra.profile_id, ra.role::text as role, ra.org_unit_id, ra.starts_at,
    to_jsonb(ra)-&gt;&gt;'appointment_status' as appt
  from role_assignments ra
  where ra.ends_at is null and ra.role::text in ('country_rep','deputy_country_rep','state_coordinator','assistant_state_coordinator','state_secretary','district_coordinator','assistant_district_coordinator','cluster_coordinator','assistant_cluster_coordinator','chapter_lead','assistant_chapter_lead')
  order by ra.profile_id, array_position(array['country_rep','deputy_country_rep','state_coordinator','assistant_state_coordinator','state_secretary','district_coordinator','assistant_district_coordinator','cluster_coordinator','assistant_cluster_coordinator','chapter_lead','assistant_chapter_lead'], ra.role::text)
), hq as (
  select ra.profile_id, string_agg(distinct ra.role::text, '|') as roles
  from role_assignments ra
  where ra.ends_at is null and ra.role::text not in ('country_rep','deputy_country_rep','state_coordinator','assistant_state_coordinator','state_secretary','district_coordinator','assistant_district_coordinator','cluster_coordinator','assistant_cluster_coordinator','chapter_lead','assistant_chapter_lead','member')
  group by ra.profile_id
)
select
  u.email,
  u.encrypted_password as password_hash,
  (select coalesce(to_jsonb(i)-&gt;&gt;'provider_id', i.identity_data-&gt;&gt;'sub') from auth.identities i
    where i.user_id = u.id and i.provider = 'google' limit 1) as google_sub,
  p.membership_no, p.first_name, p.last_name, p.phone,
  to_jsonb(p)-&gt;&gt;'birth_date' as birth_date,
  p.network::text as network, p.status::text as status,
  p.country as profile_country, p.state_region, p.lga,
  to_jsonb(p)-&gt;&gt;'community' as community,
  pc.country_iso as unit_country_iso, pc.country_name as unit_country, pc.state_name as unit_state, pc.lga_name as unit_lga,
  to_jsonb(p)-&gt;&gt;'hq_function' as hq_function, hq.roles as hq_roles,
  s.role as seat_role, s.appt as seat_status, s.starts_at as seat_since,
  sc.country_iso as seat_country_iso, sc.country_name as seat_country, sc.state_name as seat_state,
  sc.lga_name as seat_lga, sc.cluster_name as seat_cluster, sc.chapter_name as seat_chapter,
  u.created_at, u.last_sign_in_at,
  (u.banned_until is not null and u.banned_until &gt; now()) as banned,
  count(*) over () as total_rows
from auth.users u
join profiles p on p.id = u.id
left join chain pc on pc.start_id = p.org_unit_id
left join seat s on s.profile_id = p.id
left join chain sc on sc.start_id = s.org_unit_id
left join hq on hq.profile_id = p.id
where u.email is not null
order by u.created_at;</textarea>
<input id="csv" type="file" accept=".csv,text/csv">
<button id="dry">Check the file</button> <button id="go" disabled style="background:#7cb518;color:#17240a">Import now</button>
<div id="ires"></div></section>
<section><h2>6. Test the weekly inspiration on one leader</h2>
<p>Sends this week's message to ONE leader account now, in the app and as a push to that leader's devices, and shows the card on that leader's dashboard for the rest of today. Nobody else receives anything.</p>
<input id="tmail" type="email" placeholder="Email of a leader account you can sign in to"><button id="tsend">Send the test</button><p id="tres"></p></section>
<section><h2>7. Load the report templates</h2>
<p>The report forms (monthly leadership report, weekly pulse and the rest) are loaded from the current WDOS exactly as approved. Run this one line in the current WDOS Supabase <b>SQL Editor</b>, then <b>Export</b> &gt; <b>Download CSV</b>, and choose the file here. It is safe to repeat.</p>
<pre id="tsql" style="user-select:all">select to_jsonb(t)::text as row from report_templates t;</pre>
<input id="tcsv" type="file" accept=".csv,text/csv"><button id="tload">Load templates</button><p id="tlres"></p></section>
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

$('copysql').onclick=async()=>{try{await navigator.clipboard.writeText($('sql').value);}catch(e){$('sql').select();document.execCommand('copy');}$('copied').textContent='Copied';};
function parseCSV(t){t=t.replace(/^﻿/,'');const rows=[];let row=[],f='',q=false;for(let i=0;i<t.length;i++){const c=t[i];if(q){if(c==='"'){if(t[i+1]==='"'){f+='"';i++;}else q=false;}else f+=c;}else if(c==='"')q=true;else if(c===','){row.push(f);f='';}else if(c==='\n'){row.push(f);rows.push(row);row=[];f='';}else if(c!=='\r')f+=c;}if(f!==''||row.length){row.push(f);rows.push(row);}return rows;}
const esc=s=>String(s).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
async function runImport(commit){
  const file=$('csv').files[0],box=$('ires');if(!file){box.innerHTML='<p class="no">Choose the CSV file first.</p>';return;}
  const grid=parseCSV(await file.text());const head=(grid.shift()||[]).map(h=>h.trim());
  if(!head.includes('email')||!head.includes('password_hash')){box.innerHTML='<p class="no">This is not the export file: the email and password_hash columns are missing. Run the export query again and download the CSV.</p>';return;}
  const rows=grid.filter(r=>r.length>1).map(r=>Object.fromEntries(head.map((h,i)=>[h,r[i]===undefined||(/^null$/i.test(r[i])&&h!=='first_name'&&h!=='last_name')?'':r[i]])));
  const expected=Number(rows[0]&&rows[0].total_rows)||0;
  $('dry').disabled=$('go').disabled=true;const T={};const lists={seat_notes:[],hq_holders:[],new_units:[],no_country_list:[]};
  for(let i=0;i<rows.length;i+=50){
    box.innerHTML='<p>'+(commit?'Importing':'Checking')+' '+Math.min(i+50,rows.length)+' of '+rows.length+'…</p>';
    const r=await call('import',{commit,rows:rows.slice(i,i+50)});
    if(!r.ok){box.innerHTML='<p class="no">Stopped at row '+(i+1)+': '+esc(r.message||'server error')+'</p><p>Rows before this one '+(commit?'were saved; it is safe to run again, existing emails are skipped.':'were only checked.')+'</p>';$('dry').disabled=false;return;}
    for(const k in r){if(typeof r[k]==='number')T[k]=(T[k]||0)+r[k];else if(Array.isArray(r[k])&&lists[k])lists[k].push(...r[k]);}
  }
  lists.new_units=[...new Set(lists.new_units)];
  const li=(n,t)=>'<li>'+n+' '+t+'</li>';const ul=(t,a)=>a.length?'<p><b>'+t+' ('+a.length+')</b></p><ul>'+a.slice(0,80).map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>':'';
  box.innerHTML='<h2 style="margin-top:16px">'+(commit?'Imported':'Check result. Nothing has been saved yet.')+'</h2>'
   +(expected&&expected!==rows.length?'<p class="no">The file has '+rows.length+' rows but the current WDOS has '+expected+' accounts. The row limit was not set to No limit. Run the export again.</p>':'')
   +'<ul>'+li(T.rows||0,'rows read')+li(T.created||0,commit?'accounts created':'accounts would be created')+li(T.already||0,'already here, left untouched')+li(T.invalid||0,'skipped, no valid email')+li(T.demo||0,'demo accounts skipped (addresses ending in .invalid)')
   +li(T.with_password||0,'keep their current password')+li(T.google_only||0,'sign in with Google only')+li(T.no_login||0,'have no password or Google link, HQ resets from Members')
   +li(T.wgmn||0,'WGMN')+li(T.wnnn||0,'WNNN')+li(T.no_network||0,'with no network on the profile')+li(T.suspended||0,'suspended or exited, brought across as suspended')
   +li(T.seats||0,'leadership seats')+li(T.no_country||0,'with no recognisable country')+'</ul>'
   +ul('Leadership seats that need a decision',lists.seat_notes)+ul('New state or region units added from the official structure',lists.new_units)
   +ul('Held an HQ role or function in the current WDOS. Imported as ordinary accounts; give HQ access from Members',lists.hq_holders)+ul('No recognisable country',lists.no_country_list);
  const full=['WDOS import '+(commit?'result':'check')+' '+new Date().toISOString(),'',...[...box.querySelectorAll('ul:first-of-type li')].map(x=>x.textContent),'','SEATS THAT NEED A DECISION ('+lists.seat_notes.length+')',...lists.seat_notes,'','NEW STATE OR REGION UNITS ('+lists.new_units.length+')',...lists.new_units,'','HELD AN HQ ROLE ('+lists.hq_holders.length+')',...lists.hq_holders,'','NO RECOGNISABLE COUNTRY ('+lists.no_country_list.length+', first 25 per batch)',...lists.no_country_list].join('\r\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([full],{type:'text/plain'}));a.download='wdos-import-'+(commit?'result':'check')+'.txt';a.textContent='Download this report as a text file';a.style.cssText='display:inline-block;margin:10px 0;color:#9bd63a;font-weight:600';box.prepend(a);
  $('dry').disabled=false;$('go').disabled=commit||!(T.created>0)||(expected&&expected!==rows.length);
}
$('dry').onclick=()=>runImport(false);$('go').onclick=()=>{if(confirm('Import these accounts into this WDOS now?'))runImport(true);};
$('tload').onclick=async()=>{const f=$('tcsv').files[0];if(!f){$('tlres').textContent='Choose the CSV file first.';return;}const g=parseCSV(await f.text());const head=(g.shift()||[]).map(x=>x.trim());const i=Math.max(0,head.indexOf('row'));const r=await call('templates_import',{rows:g.filter(x=>x[i]).map(x=>x[i])});$('tlres').className=r.ok&&r.loaded.length?'ok':'no';$('tlres').textContent=r.ok?('Loaded '+r.loaded.length+': '+(r.loaded.join('; ')||'none')+(r.skipped.length?'. Skipped (no usable definition): '+r.skipped.join(', '):'')):r.message;};
$('tsend').onclick=async()=>{$('tres').textContent='Sending…';const r=await call('insp_test',{email:$('tmail').value});$('tres').className=r.ok?'ok':'no';$('tres').textContent=r.ok?('Sent week '+r.week+': "'+r.headline+'". Devices with notifications on: '+r.devices+', pushes delivered: '+r.sent+'. Sign in as that leader to see the card.'):r.message;};
</script></main></body></html>
