# v109.25: HQ sees everything leaders do, live, and every item opens.
import requests, subprocess, json, datetime, time
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:400])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
pw=subprocess.run(['php','-r',"echo password_hash('pass-word-99', PASSWORD_DEFAULT);"],capture_output=True,text=True).stdout
def as_login(uid):
  sql(f"update users set email='u{uid}@t120.local', password_hash='{pw}', must_change_password=0 where id={uid}"); return login(f'u{uid}@t120.local','pass-word-99')
hq=login('hq@test.local','hq-password-1'); cl=login('leader@test.local','leader-pass-1'); me=int(sql("select id from users where email='leader@test.local'"))
a=int(sql("select u.id from seats s join users u on u.id=s.user_id join org_units o on o.id=s.org_unit_id where s.ends_at is null and s.network='WGMN' and o.country_code='NG' and o.level='state_region' and u.status='active' order by u.id limit 1"))
A=as_login(a)
start=sql("select date_format(date_sub(utc_timestamp(), interval 1 minute),'%Y-%m-%d %H:%i:%s')")   # a minute back: her sign-in a moment ago is inside the window
# what leaders do
tid=cl.post(B+'/tasks',json={'title':'Count chapter members','assignees':[a]}).json().get('ids',[None])[0] or int(sql("select max(id) from tasks"))
A.post(B+f'/tasks/{tid}/comment',json={'body':'On it this week'}); A.post(B+f'/tasks/{tid}/status',json={'to':'in_progress'})
r=cl.post(B+'/dm/send',json={'subject':'Saturday visit','body':'Please confirm the venue for Saturday.','audience':{'people':[a]}}); chk('message sent', r.ok, r.text)
r=A.post(B+'/messages',json={'subject':'Need more forms','body':'We ran out of registration forms.'}); chk('help desk message', r.ok, r.text)
r=A.post(B+'/reporting/activity',json={'category':'other','what':'Visited two chapters in Ikeja','result':'Both met; 40 women','people_reached':40}); chk('field record', r.ok, r.text)
r=cl.post(B+'/calls',json={'to':a}); cid=r.json()['call']['id']; chk('call', r.ok, r.text)
r=cl.post(B+'/gcalls',json={'to':[a,'hq']}); gid=r.json()['call']['id']
body={'title':'Lagos check-in','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':'2026-12-02T10:00:00Z','ends_at':'2026-12-02T11:00:00Z','audience':'people:WGMN','invitees':[a]}
mid=cl.post(B+'/meetings',json=body).json()['id']; A.post(B+f'/meetings/{mid}/rsvp',json={'answer':'yes'})
time.sleep(1.1)
# 1. the feed
r=hq.get(B+'/hq/watch?days=7'); d=r.json(); chk('HQ watch opens', r.ok, r.text)
mine=[i for i in d['items'] if i['at']>=start]
kinds={i['kind'] for i in mine}
chk('tasks, calls, meetings, messages, field, sign-ins are all there', {'tasks','calls','meetings','messages','field','access'}<=kinds, kinds)
whats={i['what'] for i in mine}
for w in ['Gave a task','Commented on a task','Started a task','Sent a message','Wrote to WODDI HQ','Recorded field activity','Rang a leader','Started a group call','Scheduled a meeting','Will attend a meeting','Signed in']:
  chk('feed has: '+w, w in whats, sorted(whats))
chk('each item names the leader and her seat', all(i['leader'] and i['leader']['name'] for i in mine))
chk('who it was with', any(i['what']=='Gave a task' and i['with'] and i['with']['id']==a for i in mine))
# 2. every item opens
links={i['ref']['type']:i['link'] for i in mine}
chk('task opens the task', links.get('task')==f'#/tasks/{tid}', links)
chk('meeting opens the meeting', links.get('meeting')==f'#/meetings/{mid}', links)
chk('help desk opens the thread', (links.get('thread') or '').startswith('#/messages/'), links)
chk('HQ can open the task', hq.get(B+f'/tasks/{tid}').ok)
chk('HQ can open the meeting', hq.get(B+f'/meetings/{mid}').ok)
ref=lambda t: next(i['ref'] for i in mine if i['ref']['type']==t)
r=hq.get(B+f"/hq/watch/item/call/{cid}").json(); chk('call detail', r['caller']['id']==me and r['callee']['id']==a and r['status'], r)
r=hq.get(B+f"/hq/watch/item/gcall/{gid}").json(); chk('group call detail: who was on it', len(r['people'])>=2, r)
dm=ref('dm'); r=hq.get(B+f"/hq/watch/item/dm/{dm['id']}").json()
chk('message detail: who, to whom, subject', r['from']['id']==me and r['to']['id']==a and r['subject']=='Saturday visit', r)
chk('words private by default', r['can_read'] is False and r['body'] is None)
chk('a leader cannot switch reading on', cl.post(B+'/hq/watch/read-messages',json={'on':True}).status_code==403)
chk('HQ switches reading on', hq.post(B+'/hq/watch/read-messages',json={'on':True}).json()['on'] is True)
r=hq.get(B+f"/hq/watch/item/dm/{dm['id']}").json(); chk('HQ reads the words', r['body']=='Please confirm the venue for Saturday.' and r['thread'], r)
chk('each reading is recorded', sql("select count(*) from activity_log where action='hq_read_message'")=='1')
chk('the change is recorded', int(sql("select count(*) from activity_log where action='policy_changed' and detail like '%messages between leaders%'"))>=1)
hq.post(B+'/hq/watch/read-messages',json={'on':False})
rec=ref('record'); r=hq.get(B+f"/hq/watch/item/record/{rec['id']}").json(); chk('field record detail', r['record']['people_reached']==40 or r['record']['people_reached']=='40', r)
si=ref('signin'); r=hq.get(B+f"/hq/watch/item/signin/{si['id']}").json(); chk('sign-in detail with device', r['sessions'] and 'device' in r['sessions'][0], r)
# 3. filters, one leader, live
r=hq.get(B+f'/hq/watch?days=7&leader={a}').json(); chk('one leader only', r['items'] and {i['leader_id'] for i in r['items']}=={a}, {i['leader_id'] for i in r['items']})
chk('her header', r['leader']['id']==a and r['leader']['title'], r.get('leader'))
r=hq.get(B+'/hq/watch?days=7&kinds=calls').json(); chk('kind filter', r['items'] and {i['kind'] for i in r['items']}=={'calls'})
chk('network filter', all((i['leader'] or {}).get('network')=='WNNN' for i in hq.get(B+'/hq/watch?days=7&network=WNNN').json()['items']))
chk('country filter', all((i['leader'] or {}).get('country')=='KE' for i in hq.get(B+'/hq/watch?days=7&country=KE').json()['items']))
now=d['now']; time.sleep(1.1)
A.post(B+f'/tasks/{tid}/comment',json={'body':'Half done'})
r=hq.get(B+'/hq/watch?days=7&after='+now).json(); chk('live: only what is new', [i['what'] for i in r['items']]==['Commented on a task'], [i['what'] for i in r['items']])
# 4. HQ only
chk('leaders cannot see it', cl.get(B+'/hq/watch').status_code==403 and cl.get(B+f'/hq/watch/item/call/{cid}').status_code==403)
for G in (gid,):
  for S in (cl,A,hq): S.post(B+f'/gcalls/{G}/leave')
print('checks',ok+bad,'FAILURES',bad)
