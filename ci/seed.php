<?php
// LOCAL TEST DATA ONLY. Not part of the delivery. Fills the test database so charts can be checked.
require (getenv('WDOS_ROOT') ?: __DIR__ . '/../wdos-php') . '/lib/core.php';
mt_srand(7);
$first = ['Ngozi','Amina','Chiamaka','Fatima','Blessing','Grace','Halima','Ifeoma','Kemi','Zainab','Esther','Mary','Aisha','Funke','Adaeze','Ruth','Hadiza','Tolu','Uche','Yetunde','Akosua','Wanjiru','Nomsa','Mariam','Thandi'];
$last = ['Okafor','Bello','Adeyemi','Eze','Musa','Okonkwo','Ibrahim','Olawale','Nwosu','Abubakar','Mensah','Kamau','Dlamini','Diallo','Banda','Chukwu','Yusuf','Ogunleye'];
$countries = [['NG',48,60],['GH',31,10],['KE',35,8],['ZA',55,6],['CM',17,5],['UG',61,4],['RW',49,3],['SN',51,2],['TZ',58,2]];
$states = rows("SELECT id,name FROM org_units WHERE parent_id=48");
$lgas = ['Ikeja','Surulere','Owerri North','Kaduna South','Gwagwalada','Enugu East','Ibadan North','Uyo'];
$n = 0;
for ($i = 0; $i < 420; $i++) {
  $r = mt_rand(1,100); $acc = 0; foreach ($countries as $c) { $acc += $c[2]; if ($r <= $acc) break; }
  $daysAgo = (int) floor(pow(mt_rand(0,1000)/1000, 1.6) * 110);
  $created = gmdate('Y-m-d H:i:s', time() - $daysAgo*86400 - mt_rand(0,80000));
  $net = mt_rand(1,100) <= 62 ? 'WGMN' : 'WNNN';
  $age = $net === 'WGMN' ? mt_rand(30,58) : mt_rand(18,29);
  $dob = gmdate('Y-m-d', time() - $age*31557600 - mt_rand(0,300)*86400);
  $unit = $c[1]; $state = 'Central';
  if ($c[0]==='NG') { $s = $states[mt_rand(0,count($states)-1)]; if (mt_rand(1,100)<=45) $s = $states[[24,14,16,18][mt_rand(0,3)]]; $unit = (int)$s['id']; $state = $s['name']; }
  $fn = $first[mt_rand(0,count($first)-1)]; $ln = $last[mt_rand(0,count($last)-1)];
  $id = ins('INSERT INTO users (email,password_hash,first_name,last_name,phone,dob,network,country_code,org_unit_id,state_region,lga,role,status,signup_method,last_seen_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    ["seed$i@test.local", null, $fn, $ln, '+23480'.mt_rand(10000000,99999999), $dob, $net, $c[0], $unit, $state, $lgas[mt_rand(0,7)], 'member', 'active', mt_rand(1,4)===1?'google':'email', mt_rand(1,3)>1?gmdate('Y-m-d H:i:s', time()-mt_rand(0,12)*86400):null, $created]);
  q('UPDATE users SET member_no=? WHERE id=?', [sprintf('%s-%s-%06d',$net,$c[0],$id), $id]);
  if ($daysAgo < 3) log_activity($id, $id, 'registered', "$net · $state, {$c[0]}");
  $n++;
}
// a few seats so the leadership views have real rows
$pw = password_hash('leader-pass-1', PASSWORD_DEFAULT);
$lead = row("SELECT id FROM users WHERE network='WGMN' AND country_code='NG' ORDER BY id LIMIT 1");
q("UPDATE users SET email='leader@test.local', password_hash=?, first_name='Amara', last_name='Bello' WHERE id=?", [$pw, (int)$lead['id']]);
q('INSERT INTO seats (user_id,position_code,network,org_unit_id,status,starts_at) VALUES (?,?,?,?,?,?)', [(int)$lead['id'],'country_rep','WGMN',48,'appointed',gmdate('Y-m-d H:i:s', time()-60*86400)]);
$k=0; foreach (rows("SELECT id, org_unit_id, network FROM users WHERE country_code='NG' AND org_unit_id>=100 AND id<>? ORDER BY id LIMIT 60", [(int)$lead['id']]) as $u) {
  if ($k++ % 4) continue; $pos = ['state_coordinator','assistant_state_coordinator'][$k % 2];
  q('INSERT INTO seats (user_id,position_code,network,org_unit_id,status,starts_at) VALUES (?,?,?,?,?,?)', [(int)$u['id'],$pos,$u['network'],(int)$u['org_unit_id'],'appointed',now()]);
}
echo "seeded $n\n";
