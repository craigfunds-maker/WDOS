# v109.27: group calls with many people through the call relay (Cloudflare Realtime SFU), with a stand-in relay.
import requests, subprocess, json, time, os
B0='http://localhost:8080/api'; B='http://localhost:8081/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
subprocess.run(['bash','-c','pkill -f "php -S 127.0.0.1:8081"']); time.sleep(.3)
srv=subprocess.Popen(['bash','-c','cd /home/claude/wdos-php; . /tmp/env.sh; export CF_SFU_APP_ID=testapp CF_SFU_APP_TOKEN=test-sfu-token CF_SFU_API=http://127.0.0.1:8099/v1; exec php -S 127.0.0.1:8081 /home/claude/router.php >/tmp/php81.log 2>&1'], start_new_session=True); time.sleep(1.5)
pw=subprocess.run(['php','-r',"echo password_hash('pass-word-99', PASSWORD_DEFAULT);"],capture_output=True,text=True).stdout
def login(base,e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(base+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
def as_login(base,uid):
  sql(f"update users set email='u{uid}@t122.local', password_hash='{pw}', must_change_password=0 where id={uid}"); return login(base,f'u{uid}@t122.local','pass-word-99')
me=int(sql("select id from users where email='leader@test.local'"))
ng=sql("select id from org_units where level='country' and country_code='NG'"); st=sql(f"select id from org_units where parent_id={ng} and level='state_region' order by id limit 1")
# give her a big team: 12 more WGMN state seats in Nigeria
extra=[int(i) for i in sql("select u.id from users u where u.role='member' and u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by id limit 12").split()]
for i in extra: sql(f"insert into seats (user_id,position_code,network,org_unit_id,area_label,status,starts_at,acceptance) values ({i},'state_coordinator','WGMN',{st},'','appointed',utc_timestamp(),'accepted')")
L0=login(B0,'leader@test.local','leader-pass-1'); L=login(B,'leader@test.local','leader-pass-1')
team=L.get(B+'/team').json(); n=len(team['people'])
chk('a big team', n>=12, n)
# 1. without the relay: refused with a plain reason
d0=L0.get(B0+'/team').json(); chk('no relay: limit 8', d0['max_group']==8 and d0.get('relay') is False, d0.get('max_group'))
r=L0.post(B0+'/gcalls',json={'to':[],'all':True}); chk('no relay: calling all is refused, saying why', r.status_code==422 and 'relay' in r.text, r.text)
# 2. with the relay
chk('relay: higher limit', team['max_group']>=150 and team['relay'] is True, team.get('max_group'))
r=L.post(B+'/gcalls',json={'to':[],'all':True}); chk('relay: she calls all her leaders', r.ok, r.text); c=r.json()['call']; gid=c['id']
chk('it goes through the relay', c['mode']=='sfu')
chk('everyone under her is rung', len([p for p in c['people'] if p['status']=='ringing'])==n, len(c['people']))
chk('she starts with the floor', [p for p in c['people'] if p['me']][0]['speaking'] is True)
chk('each is told', int(sql(f"select count(*) from notifications where title like 'Group call from%' and user_id in ({','.join(str(p['id']) for p in team['people'])})"))==n)
chk('the relay key is never sent out', 'test-sfu-token' not in r.text)
people=[p['id'] for p in team['people']]
S={pid:as_login(B,pid) for pid in people[:9]}
a=people[0]; A=S[a]
r=A.post(B+f'/gcalls/{gid}/join'); chk('a leader joins listening', r.ok and [p for p in r.json()['call']['people'] if p['me']][0]['speaking'] is False, r.text)
# 3. relay connection
chk('caller connects to the relay', L.post(B+f'/gcalls/{gid}/relay/session',json={}).ok and sql(f"select sfu_session is not null from voice_group_people where group_id={gid} and user_id={me}")=='1')
chk('publish before connecting is refused', A.post(B+f'/gcalls/{gid}/relay/publish',json={'sdp':'v=0','mid':'0'}).status_code in (403,409))
A.post(B+f'/gcalls/{gid}/relay/session',json={})
chk('a listener cannot send her voice', A.post(B+f'/gcalls/{gid}/relay/publish',json={'sdp':'v=0 offer','mid':'0'}).status_code==403)
r=L.post(B+f'/gcalls/{gid}/relay/publish',json={'sdp':'v=0 offer','mid':'0'}); chk('the caller sends her voice', r.ok and r.json()['answer']['type']=='answer', r.text)
chk('she is live', [p for p in A.get(B+f'/gcalls/{gid}').json()['call']['people'] if p['id']==me][0]['live'] is True)
r=A.post(B+f'/gcalls/{gid}/relay/pull',json={'ids':[me]}); x=r.json(); chk('a listener hears the caller', r.ok and x['tracks']==[{'user_id':me,'mid':'1'}] and x['offer']['type']=='offer', r.text)
chk('she answers the relay', A.post(B+f'/gcalls/{gid}/relay/answer',json={'sdp':'v=0 answer'}).ok)
chk('nobody silent can be pulled', A.post(B+f'/gcalls/{gid}/relay/pull',json={'ids':[people[1]]}).json()['tracks']==[])
chk('she stops hearing someone', A.post(B+f'/gcalls/{gid}/relay/close',json={'mids':['1']}).ok)
log=[json.loads(l) for l in open('/tmp/aimock.log') if '/v1/apps/testapp/' in l]
chk('relay calls carry the right app', len(log)>=5 and any(l['path'].endswith('/renegotiate') and l['method']=='PUT' for l in log), len(log))
# 4. the floor: up to 7 speak, the rest raise a hand
for pid in people[1:9]: S[pid].post(B+f'/gcalls/{gid}/join')
spoke=[]
for pid in people[:9]:
  r=S[pid].post(B+f'/gcalls/{gid}/speak'); spoke.append(r.json()['speaking'])
chk('six more can speak', spoke[:6]==[True]*6, spoke)
chk('then hands go up', spoke[6:]==[False,False,False], spoke)
c=L.get(B+f'/gcalls/{gid}').json()['call']; chk('hands are shown', len([p for p in c['people'] if p['hand']])==3)
h7=people[6]
chk('the caller cannot give the floor when full', L.post(B+f'/gcalls/{gid}/floor',json={'user_id':h7,'on':True}).status_code==422)
chk('a leader cannot hand out the floor', A.post(B+f'/gcalls/{gid}/floor',json={'user_id':h7,'on':True}).status_code==403)
chk('the caller mutes someone', L.post(B+f'/gcalls/{gid}/floor',json={'user_id':people[1],'on':False}).ok)
chk('and gives the floor to a raised hand', L.post(B+f'/gcalls/{gid}/floor',json={'user_id':h7,'on':True}).ok and sql(f"select speaking from voice_group_people where group_id={gid} and user_id={h7}")=='1')
chk('a speaker steps back', A.post(B+f'/gcalls/{gid}/quiet').ok and sql(f"select speaking from voice_group_people where group_id={gid} and user_id={a}")=='0')
chk('direct signals are not used on a relay call', A.post(B+f'/gcalls/{gid}/signal',json={'to':me,'kind':'offer','data':{}}).status_code==409)
x=int(sql("select u.id from seats s join users u on u.id=s.user_id where s.ends_at is null and s.network='WNNN' and u.status='active' limit 1")); X=as_login(B,x)
chk('an outsider cannot reach the relay for this call', X.post(B+f'/gcalls/{gid}/relay/session',json={}).status_code==404)
S[people[2]].post(B+f'/gcalls/{gid}/leave'); chk('leaving clears her place', sql(f"select concat(status,speaking) from voice_group_people where group_id={gid} and user_id={people[2]}")=='left0')
# 5. small calls still go phone to phone
for pid in people[:9]: S[pid].post(B+f'/gcalls/{gid}/leave')
L.post(B+f'/gcalls/{gid}/leave')
r=L.post(B+'/gcalls',json={'to':people[:3]}); chk('a small call stays phone to phone', r.ok and r.json()['call']['mode']=='mesh', r.text)
L.post(B+f"/gcalls/{r.json()['call']['id']}/leave")
chk('the relay errors are not shown raw', 'errorCode' not in L.post(B+'/gcalls/999999/relay/session',json={}).text)
srv.terminate()
print('checks',ok+bad,'FAILURES',bad)
