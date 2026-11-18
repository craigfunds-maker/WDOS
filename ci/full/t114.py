# v109.13: voice calls. A leader rings leaders in her own country and network, or WODDI HQ; members cannot ring;
# the first HQ admin to answer takes a call to HQ; unanswered calls become missed calls; nobody outside the call gets the room.
import requests, subprocess, json, time
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
pw=subprocess.run(['php','-r',"echo password_hash('pass-word-99', PASSWORD_DEFAULT);"],capture_output=True,text=True).stdout
def leader_where(cond):
  return sql(f"select u.id from users u join seats s on s.user_id=u.id and s.ends_at is null join org_units o on o.id=s.org_unit_id where u.email<>'leader@test.local' and u.status='active' and {cond} order by u.id limit 1")
same=leader_where("s.network='WGMN' and o.country_code='NG' and s.status not in ('on_leave','suspended')")
othernet=leader_where("s.network='WNNN' and o.country_code='NG'")
othercountry=leader_where("o.country_code<>'NG' and o.country_code is not null")
if not othercountry:   # move one more Nigerian WGMN leader's seat to Ghana for this test
  othercountry=sql("select u.id from users u join seats s on s.user_id=u.id and s.ends_at is null join org_units o on o.id=s.org_unit_id where u.email not in ('leader@test.local') and s.network='WGMN' and o.country_code='NG' order by u.id desc limit 1")
  gh=sql("select id from org_units where level='country' and country_code='GH' limit 1")
  if othercountry and gh: sql(f"update seats set org_unit_id={gh} where user_id={othercountry} and ends_at is null")
member=sql("select u.id from users u where u.role='member' and u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by id limit 1")
for i,e in [(same,'same@test.local'),(othernet,'othernet@test.local'),(othercountry,'abroad@test.local'),(member,'plain@test.local')]:
  if i: sql(f"update users set email='{e}', password_hash='{pw}', must_change_password=0 where id={i}")
sql(f"insert into users (email,password_hash,first_name,last_name,role,status,network,created_at) select 'hq2@test.local','{pw}','Second','Admin','hq_admin','active','WGMN',UTC_TIMESTAMP() from dual where not exists (select 1 from users where email='hq2@test.local')")
hq=login('hq@test.local','hq-password-1'); hq2=login('hq2@test.local','pass-word-99'); ld=login('leader@test.local','leader-pass-1')
sm=login('same@test.local','pass-word-99'); pl=login('plain@test.local','pass-word-99')
chk('fixtures: a second WGMN Nigeria leader exists', bool(same), same)
# who can be rung
d=ld.get(B+'/calls').json(); ids={p['id'] for p in d['people']}
chk('leader can call', d['can_call'] is True)
chk('same-country, same-network leader is listed', int(same) in ids, ids)
if othernet: chk('a leader of the other network is not listed', int(othernet) not in ids)
if othercountry: chk('a leader in another country is not listed', int(othercountry) not in ids)
chk('a plain member is not listed', member=='' or int(member) not in ids)
chk('a member cannot ring anyone', pl.post(B+'/calls',json={'to':'hq'}).status_code==403)
if othernet: chk('the server refuses a call outside her reach', ld.post(B+'/calls',json={'to':int(othernet)}).status_code==403)
# leader to leader
r=ld.post(B+'/calls',json={'to':int(same)}); chk('leader rings a leader', r.ok and r.json()['call']['status']=='ringing', r.text)
c=r.json()['call']; room=r.json()['join']['room']
chk('the caller gets a random room', room.startswith('WODDI-call-') and len(room)>20)
chk('the other leader is told in the app', sql(f"select count(*) from notifications where user_id={same} and title like 'Voice call from %'")=='1')
rg=sm.get(B+'/calls/ring').json()['calls']; chk('it rings in her open app', len(rg)==1 and rg[0]['id']==c['id'] and rg[0]['with']=='Amara Bello', rg)
chk('nobody else sees the call', hq.get(B+f"/calls/{c['id']}").status_code==404 and pl.get(B+f"/calls/{c['id']}").status_code==404)
chk('the caller cannot answer her own call', ld.post(B+f"/calls/{c['id']}/answer",json={}).status_code==403)
a=sm.post(B+f"/calls/{c['id']}/answer",json={}); chk('she answers and gets the same room', a.ok and a.json()['join']['room']==room, a.text)
chk('the caller sees it answered', ld.get(B+f"/calls/{c['id']}").json()['call']['status']=='answered')
chk('answering twice is refused', sm.post(B+f"/calls/{c['id']}/answer",json={}).status_code==409)
sm.post(B+f"/calls/{c['id']}/end",json={}); chk('hanging up ends it', ld.get(B+f"/calls/{c['id']}").json()['call']['status']=='ended')
# decline
c2=ld.post(B+'/calls',json={'to':int(same)}).json()['call']; sm.post(B+f"/calls/{c2['id']}/decline",json={})
chk('declined call shows as declined', ld.get(B+f"/calls/{c2['id']}").json()['call']['status']=='declined')
# to HQ: every HQ admin hears it, the first to answer takes it
c3=ld.post(B+'/calls',json={'to':'hq'}).json()['call']
chk('both HQ admins hear the call', len(hq.get(B+'/calls/ring').json()['calls'])==1 and len(hq2.get(B+'/calls/ring').json()['calls'])==1)
chk('the call shows the caller to HQ', hq.get(B+'/calls/ring').json()['calls'][0]['with']=='Amara Bello')
r1=hq2.post(B+f"/calls/{c3['id']}/answer",json={}); r2=hq.post(B+f"/calls/{c3['id']}/answer",json={})
chk('the first HQ admin takes it', r1.ok, r1.text); chk('the second is told someone else answered', r2.status_code in (404,409), r2.text)
chk('it stops ringing for the other admin', len(hq.get(B+'/calls/ring').json()['calls'])==0)
chk('the leader sees who at HQ answered', ld.get(B+f"/calls/{c3['id']}").json()['call']['with']=='Second Admin')
chk('HQ cannot call HQ', hq.post(B+'/calls',json={'to':'hq'}).status_code==422)
r=hq.post(B+'/calls',json={'to':4}); chk('HQ can ring a leader back', r.ok, r.text)
hq.post(B+f"/calls/{r.json()['call']['id']}/end",json={})
chk('a cancelled call is not ringing any more', len(ld.get(B+'/calls/ring').json()['calls'])==0)
# missed
c4=ld.post(B+'/calls',json={'to':int(same)}).json()['call']
sql(f"update voice_calls set created_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE) where id={c4['id']}")
chk('unanswered becomes missed', ld.get(B+f"/calls/{c4['id']}").json()['call']['status']=='missed')
chk('she is told about the missed call', sql(f"select count(*) from notifications where user_id={same} and title='Missed call from Amara Bello'")=='1')
ld.get(B+f"/calls/{c4['id']}"); chk('the missed call note is sent once', sql(f"select count(*) from notifications where user_id={same} and title='Missed call from Amara Bello'")=='1')
rec=sm.get(B+'/calls').json()['recent']; chk('recent calls list for the leader', len(rec)>=3 and any(x['status']=='missed' for x in rec), rec)
# in her own language
p=subprocess.run(['php','-r',"function rows(){return [];} require '/home/claude/wdos-php/lib/notify_i18n.php'; echo json_encode([note_in('fr','Voice call from Amara Bello','Country Representative, Nigeria'), note_in('ar','Missed call from Amara Bello','Open Calls in WDOS to ring back.')], JSON_UNESCAPED_UNICODE);"],capture_output=True,text=True)
nn=json.loads(p.stdout); chk('call note in French', nn[0][0]=='Appel vocal de Amara Bello', nn); chk('missed call in Arabic', nn[1][0].startswith('مكالمة فائتة'), nn)
print('checks',ok+bad,'FAILURES',bad)
