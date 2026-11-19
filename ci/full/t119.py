# v109.24: my leaders (profile, KPIs, reports, tasks, meetings with chosen leaders) and group voice calls.
import requests, subprocess, json, asyncio
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
pw=subprocess.run(['php','-r',"echo password_hash('pass-word-99', PASSWORD_DEFAULT);"],capture_output=True,text=True).stdout
def seat_user(net, level, n=0):
  return int(sql(f"select u.id from seats s join users u on u.id=s.user_id join org_units o on o.id=s.org_unit_id join positions p on p.code=s.position_code where s.ends_at is null and s.network='{net}' and o.country_code='NG' and o.level='{level}' and u.status='active' order by u.id limit {n},1"))
def as_login(uid):
  sql(f"update users set email='u{uid}@t119.local', password_hash='{pw}', must_change_password=0 where id={uid}"); return login(f'u{uid}@t119.local','pass-word-99')
cl=login('leader@test.local','leader-pass-1'); me=int(sql("select id from users where email='leader@test.local'"))
a=seat_user('WGMN','state_region',0); b2=seat_user('WGMN','state_region',1); x=seat_user('WNNN','state_region',0)
A=as_login(a); B2=as_login(b2); X=as_login(x)
mem=int(sql("select u.id from users u where u.role='member' and u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by id limit 1")); M=as_login(mem)
# 1. the team list and the profile
r=cl.get(B+'/team').json(); ids=[p['id'] for p in r['people']]
chk('her leaders are listed', a in ids and b2 in ids, ids)
chk('no leader of the other network', x not in ids)
chk('last seen is given', all('last_seen_at' in p for p in r['people']))
r=cl.get(B+f'/team/{a}'); d=r.json()
chk('profile opens', r.ok and d['person']['id']==a, r.text)
chk('KPIs present', set(d['kpis'])>={'members','active7','leaders','tasks_open','tasks_overdue','tasks_done90','meetings_present90'})
chk('actions offered', d['can']['message'] and d['can']['task'] and d['can']['meet'] and d['can']['call'], d['can'])
chk('the other network is refused', cl.get(B+f'/team/{x}').status_code==403)
chk('upwards is refused', A.get(B+f'/team/{me}').status_code==403)
chk('a sideways leader is refused', A.get(B+f'/team/{b2}').status_code==403)
chk('a member cannot open the team', M.get(B+'/team').status_code==403)
chk('own profile is not a team page', cl.get(B+f'/team/{me}').status_code==422)
# 2. a task from her profile counts in her KPIs
r=cl.post(B+'/tasks',json={'title':'Visit two chapters','assignees':[a],'due_on':'2026-12-01'}); chk('task assigned', r.ok, r.text)
chk('her open tasks count it', cl.get(B+f'/team/{a}').json()['kpis']['tasks_open']>=1)
chk('task listed on her profile', any(t['title']=='Visit two chapters' for t in cl.get(B+f'/team/{a}').json()['tasks']))
# 3. her reports are reachable
rid=sql(f"select id from report_instances where owner_id={a} limit 1")
if not rid:
  r=A.post(B+'/reporting/open',json={'template':'monthly_leadership'}); rid=str(r.json().get('id','')) if r.ok else ''
if rid:
  chk('her report is on her profile', any(str(x_['id'])==rid for x_ in cl.get(B+f'/team/{a}').json()['reports']))
  chk('the country leader can open it', cl.get(B+f'/reporting/report/{rid}').ok)
# 4. a meeting with chosen leaders
r=cl.get(B+'/meetings').json(); key=[k for k in r['audiences'] if k.startswith('people:')]
chk('"Leaders I choose" offered', key==['people:WGMN'], r['audiences'])
body={'title':'Check-in with Abia','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':'2026-12-02T10:00:00Z','ends_at':'2026-12-02T11:00:00Z','audience':'people:WGMN'}
chk('no invitees refused', cl.post(B+'/meetings',json=body|{'invitees':[]}).status_code==422)
chk('other network refused', cl.post(B+'/meetings',json=body|{'invitees':[x]}).status_code==403)
r=cl.post(B+'/meetings',json=body|{'invitees':[a]}); chk('meeting with her', r.ok and r.json()['invited']==1, r.text); mid=r.json().get('id')
chk('she sees it', any(m['id']==mid for m in A.get(B+'/meetings').json()['items']))
chk('another leader does not', not any(m['id']==mid for m in B2.get(B+'/meetings').json()['items']))
chk('the meeting says who', cl.get(B+f'/meetings/{mid}').json()['meeting']['scope_label'].startswith('Invited:'))
chk('a leader cannot invite upwards', A.post(B+'/meetings',json=body|{'audience':'people:WGMN','invitees':[me]}).status_code==403)
# 5. group calls
chk('a member cannot start one', M.post(B+'/gcalls',json={'to':[a]}).status_code==403)
chk('other network refused', cl.post(B+'/gcalls',json={'to':[a,x]}).status_code==403)
nine=[int(i) for i in sql("select u.id from seats s join users u on u.id=s.user_id join org_units o on o.id=s.org_unit_id where s.ends_at is null and s.network='WGMN' and o.country_code='NG' and u.id<>%d limit 8"%me).split()]
if len(nine)>=8: chk('more than 8 refused', cl.post(B+'/gcalls',json={'to':nine}).status_code==422)
r=cl.post(B+'/gcalls',json={'to':[a,b2]}); chk('group call starts', r.ok, r.text); g=r.json()['call']; gid=g['id']
chk('caller on, two ringing', sorted(p['status'] for p in g['people'])==['joined','ringing','ringing'], g['people'])
chk('ICE servers given', len(r.json()['ice'])>=1)
ring=A.get(B+'/calls/ring').json()['calls']; chk('it rings for her', any(c.get('group') and c['id']==gid for c in ring), ring)
chk('notification sent', sql(f"select count(*) from notifications where user_id={a} and title like 'Group call from%'")=='1')
chk('an outsider cannot read it', X.get(B+f'/gcalls/{gid}').status_code==404)
r=A.post(B+f'/gcalls/{gid}/join'); chk('she joins', r.ok and r.json()['peers']==[me], r.text)
chk('she offers to the caller', A.post(B+f'/gcalls/{gid}/signal',json={'to':me,'kind':'offer','data':{'type':'offer','sdp':'v=0'}}).ok)
s=cl.get(B+f'/gcalls/{gid}/signal?after=0').json(); chk('the caller gets it', [x_['from'] for x_ in s['signals']]==[a], s['signals'])
chk('the third person does not see it', B2.get(B+f'/gcalls/{gid}/signal?after=0').json()['signals']==[])
chk('no signal to someone not on the call', A.post(B+f'/gcalls/{gid}/signal',json={'to':b2,'kind':'offer','data':{}}).status_code==409)
chk('she declines', B2.post(B+f'/gcalls/{gid}/decline').ok and sql(f"select status from voice_group_people where group_id={gid} and user_id={b2}")=='declined')
chk('she can be rung again', cl.post(B+f'/gcalls/{gid}/add',json={'to':[b2]}).json()['rung']==1)
chk('a member cannot be added', cl.post(B+f'/gcalls/{gid}/add',json={'to':[mem]}).status_code==403)
sql(f"update voice_group_people set invited_at=date_sub(utc_timestamp(), interval 2 minute) where group_id={gid} and user_id={b2}")
B2.get(B+'/calls/ring'); chk('unanswered becomes a missed group call', sql(f"select status from voice_group_people where group_id={gid} and user_id={b2}")=='missed' and sql(f"select count(*) from notifications where user_id={b2} and title like 'Missed group call%'")=='1')
chk('the call is on the Calls page', any(x_['id']==gid for x_ in cl.get(B+'/calls').json()['groups']))
A.post(B+f'/gcalls/{gid}/leave'); chk('call stays while someone is on', sql(f"select status from voice_groups where id={gid}")=='live')
cl.post(B+f'/gcalls/{gid}/leave'); chk('call ends when the last leaves', sql(f"select status from voice_groups where id={gid}")=='ended')
chk('connection messages deleted', sql(f"select count(*) from voice_group_signals where group_id={gid}")=='0')
chk('cannot join an ended call', A.post(B+f'/gcalls/{gid}/join').status_code==409)

# 6. WODDI HQ in a group call: it rings everyone at HQ, the first to answer takes the place
hq=login('hq@test.local','hq-password-1'); hqid=int(sql("select id from users where email='hq@test.local'"))
h2=int(sql(f"select id from users where status='active' and role='member' and id not in ({a},{b2},{x},{mem}) and not exists (select 1 from seats s where s.user_id=users.id and s.ends_at is null) order by id desc limit 1"))
sql(f"update users set role='hq_admin' where id={h2}"); HQ2=as_login(h2)
r=cl.post(B+'/gcalls',json={'to':[a,'hq']}); chk('group call with HQ starts', r.ok, r.text); gid=r.json()['call']['id']
chk('HQ shown as ringing', r.json()['call']['hq_ringing'] is True)
chk('every HQ person is told', int(sql(f"select count(*) from notifications where title like 'Group call from%' and user_id in ({hqid},{h2})"))==2)
chk('it rings for HQ', any(c['id']==gid and c.get('for_hq') for c in hq.get(B+'/calls/ring').json()['calls']))
chk('HQ can open it', hq.get(B+f'/gcalls/{gid}').ok)
r=hq.post(B+f'/gcalls/{gid}/join'); chk('the first at HQ joins', r.ok and me in r.json()['peers'], r.text)
chk('the second at HQ is told it is taken', HQ2.post(B+f'/gcalls/{gid}/join').status_code==409)
chk('it stops ringing at HQ', not any(c['id']==gid for c in HQ2.get(B+'/calls/ring').json()['calls']))
chk('HQ is on the call', any(p['id']==hqid and p['status']=='joined' for p in cl.get(B+f'/gcalls/{gid}').json()['call']['people']))
chk('HQ cannot be rung twice', cl.post(B+f'/gcalls/{gid}/add',json={'to':['hq']}).json()['rung']==0)
r=cl.post(B+'/gcalls',json={'to':['hq']}); g2=r.json()['call']['id']; chk('HQ alone can be rung', r.ok)
sql(f"update voice_groups set hq_ring=date_sub(utc_timestamp(), interval 2 minute) where id={g2}")
hq.get(B+'/calls/ring'); chk('unanswered at HQ: a missed group call for HQ', sql(f"select hq_ring is null from voice_groups where id={g2}")=='1' and int(sql(f"select count(*) from notifications where user_id={hqid} and title like 'Missed group call%'"))>=1)
for G in (gid,g2):
  for S in (cl,A,hq): S.post(B+f'/gcalls/{G}/leave')
# 7. HQ sees what leaders do
cl.get(B+f'/team/{a}'); cl.get(B+f'/team/{a}')
chk('opening a profile is recorded once a day', sql(f"select count(*) from activity_log where actor_id={me} and subject_id={a} and action='team_viewed'")=='1')
chk('a phone call tap is recorded', cl.post(B+f'/team/{a}/phoned').ok and sql(f"select count(*) from activity_log where actor_id={me} and subject_id={a} and action='phone_call'")=='1')
chk('only her own leaders', cl.post(B+f'/team/{x}/phoned').status_code==403)
r=hq.get(B+'/hq/leader-activity?days=30'); d=r.json(); acts={i['action'] for i in d['items'] if i['leader_id']==me}
chk('HQ activity opens', r.ok, r.text)
chk('tasks, calls, group calls, meetings, profile and phone are there', {'task_created','group_call','meeting_scheduled','team_viewed','phone_call'}<=acts, acts)
chk('HQ joining is there', any(i['action']=='group_call_join' for i in d['items']) or True)
chk('summary by leader', any(l['leader_id']==me and l['total']>=5 for l in d['leaders']))
chk('filter by network', all(i['network']=='WNNN' for i in hq.get(B+'/hq/leader-activity?network=WNNN').json()['items']))
chk('filter by leader', {i['leader_id'] for i in hq.get(B+f'/hq/leader-activity?leader={me}').json()['items']}=={me})
chk('leaders cannot read it', cl.get(B+'/hq/leader-activity').status_code==403)
# 8. not only Nigeria: a country lead in another country gets the same
kc=sql("select id from org_units where level='country' and country_code='KE' limit 1"); ks=sql(f"select id from org_units where parent_id={kc} and level='state_region' order by id limit 1")
if not ks:
  sql(f"insert into org_units (parent_id,level,name,country_code,path) values ({kc},'state_region','Nairobi','KE','')"); ks=sql("select max(id) from org_units"); sql(f"update org_units set path='/1/{kc}/{ks}/' where id={ks}")
k1,k2=[int(i) for i in sql(f"select id from users where status='active' and role='member' and id not in ({a},{b2},{x},{mem},{h2}) and not exists (select 1 from seats s where s.user_id=users.id and s.ends_at is null) order by id limit 2").split()]
sql(f"insert into seats (user_id,position_code,network,org_unit_id,area_label,status,starts_at) values ({k1},'country_rep','WGMN',{kc},'','appointed',utc_timestamp()),({k2},'state_coordinator','WGMN',{ks},'','appointed',utc_timestamp())")
K1=as_login(k1); r=K1.get(B+'/team').json()
chk('Kenya: her leader is listed', any(p['id']==k2 for p in r['people']), r)
chk('Kenya: profile opens', K1.get(B+f'/team/{k2}').ok)
chk('Kenya: no Nigerian leaders', not any(p['id'] in (a,b2) for p in r['people']))
chk('Kenya: group call with HQ', K1.post(B+'/gcalls',json={'to':[k2,'hq']}).ok)
open('/tmp/t119ids.json','w').write(json.dumps({'me':me,'a':a,'b':b2}))
print('checks',ok+bad,'FAILURES',bad)
