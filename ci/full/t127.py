# v109.34: an envoy's candidate chooses from every leadership role, adds her photo, does the 3-day activation and is scored;
# HQ and the envoy see the same score; only a candidate who passed can be approved; approval tells her and the envoy. Synthetic data.
import requests, subprocess, json, time, base64, io
X=str(int(time.time()))[-6:]; B='http://localhost:8080/api'; H={'X-WDOS':'1'}; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:400])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers.update(H); return s
def login(e,p): s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
# v109.45: envoys must accept their appointment form before anything else works; these tests are about other things
_login_before_v10945=login
def login(*a,**k):
  sql("insert ignore into envoy_acceptances (user_id,accepted_at) select id, utc_timestamp() from users where role='envoy'")
  return _login_before_v10945(*a,**k)
sql("delete from login_attempts")
hq=login('hq@test.local','hq-password-1')
r=hq.post(B+'/hq/envoys',json={'first_name':'Testenv','last_name':'Envoy','email':f'te.{X}@example.org','phone':'','country_code':'NG'}); chk('envoy made', r.ok, r.text)
env=login(f'te.{X}@example.org', r.json()['temporary_password']); code=env.get(B+'/envoy/home').json()['code']
tables={'activation_days':[{'route':'ref3','day':1,'title':'Day 1','questions':'[]','acks':[]},{'route':'ref3','day':2,'title':'Day 2','questions':[{'key':'why','label':'Why?'}],'acks':['I read it']},{'route':'ref3','day':3,'title':'Day 3','questions':'[]','acks':[]}],
 'readiness_statements':[{'seq':1,'section':'A','section_title':'A','statement':'S one'},{'seq':2,'section':'B','section_title':'B','statement':'S two'}],'readiness_questions':[{'seq':1,'question':'Q one'},{'seq':2,'question':'Q two'}],'readiness_confirmations':[{'seq':1,'confirmation':'C one'}]}
r=requests.post(B+'/setup',headers=H,json={'key':'test-admin-key','action':'content_import','tables':tables}).json(); chk('content loaded', r['ok'])
pos=S().get(B+'/positions?all=1').json()['positions']; chk('every leadership role is listed for envoy candidates', len(pos)>=18 and any(p['code']=='state_secretary' for p in pos), len(pos))
ng=sql("select id from org_units where level='country' and country_code='NG'"); st=sql(f"select id from org_units where parent_id={ng} order by name limit 1")
def reg(name, posc, n):
  s=S(); r=s.post(B+'/auth/register',json={'network':'WGMN','first_name':name,'last_name':'Cand','dob':'1984-03-03','phone':f'+234803{X}{n}','country_id':int(ng),'state_id':int(st),'lga':'Test LGA','community':'Test','email':f'{name.lower()}.{X}@example.org','password':'member-pass-1','consent':True,'referral':{'code':code,'position':posc}})
  return s, r
png=base64.b64encode(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')).decode()
a, r = reg('Goodcand','state_secretary','1'); chk('candidate for a non-framework role registers', r.ok, r.text[:300])
chk('her photo saves straight after', a.post(B+'/me/photo',json={'data':'data:image/png;base64,'+png}).ok and a.get(B+'/me').json()['user']['has_photo'] is True)
act=a.get(B+'/activation').json()['activation']; chk('3-day activation started for the role she chose', act and act['route']=='ref3' and 'State Secretary' in act['role_title'], act and act.get('role_title'))
chk('not a leader yet', not a.get(B+'/me').json()['user'].get('is_leader'))
p=[x for x in env.get(B+'/envoy/home').json()['people'] if x['first_name']=='Goodcand'][0]
chk('envoy sees a running score', p['activation']['score']['final'] is False and p['activation']['score']['score']==0, p['activation'])
for d,body in [(1,{'reached_end':True,'complete':True}),(2,{'reached_end':True,'answers':{'why':'To serve women in my state'},'acked':[0],'complete':True}),(3,{'reached_end':True,'complete':True})]:
  r=a.post(B+f'/activation/day/{d}',json=body); chk(f'day {d} done', r.ok, r.text)
p=[x for x in env.get(B+'/envoy/home').json()['people'] if x['first_name']=='Goodcand'][0]; chk('score so far: the three days', p['activation']['score']['score']==30, p['activation']['score'])
long='I will hold monthly meetings in each LGA, follow up every chapter lead by phone and send my report to HQ on time each month.'
r=a.post(B+'/activation/assessment',json={'statements':{'1':'yes','2':'yes'},'short_answers':{'1':long,'2':long},'confirmations':[1],'readiness':'ready','signature_name':'Goodcand Cand'}); chk('assessment submitted', r.ok, r.text)
p=[x for x in env.get(B+'/envoy/home').json()['people'] if x['first_name']=='Goodcand'][0]; sc=p['activation']['score']
chk('final score 100 and passed', sc['final'] and sc['score']==100 and sc['passed'], sc)
reg_=hq.get(B+'/hq/activations?route=ref3').json(); it=[x for x in reg_['items'] if x['first_name']=='Goodcand'][0]
chk('HQ register shows the same score', it['score']['score']==100 and it['score']['passed'], it['score'])
v=hq.get(B+f"/hq/activations/{it['id']}").json()['activation']; chk('HQ sees the breakdown', v['score']['parts']=={'days':30,'on_time':10,'understanding':25,'answers':25,'readiness':10}, v['score'])
# a weak candidate does not pass
b, r = reg('Weakcand','chapter_lead','2'); assert r.ok, r.text
for d,body in [(1,{'reached_end':True,'complete':True}),(2,{'reached_end':True,'answers':{'why':'ok'},'acked':[0],'complete':True}),(3,{'reached_end':True,'complete':True})]: b.post(B+f'/activation/day/{d}',json=body)
r=b.post(B+'/activation/assessment',json={'statements':{'1':'clarify','2':'clarify'},'short_answers':{'1':'yes','2':'ok'},'confirmations':[1],'readiness':'not_now','signature_name':'Weakcand Cand'}); assert r.ok, r.text
wit=[x for x in hq.get(B+'/hq/activations?route=ref3').json()['items'] if x['first_name']=='Weakcand'][0]
chk('weak candidate is below the pass mark', wit['score']['final'] and not wit['score']['passed'] and wit['score']['score']<60, wit['score'])
r=hq.post(B+f"/hq/activations/{wit['id']}",json={'action':'approve'}); chk('HQ cannot approve a candidate who did not pass', r.status_code==409 and 'pass mark' in r.text, r.text)
hq.post(B+f"/hq/activations/{wit['id']}",json={'action':'outcome','outcome':'selected','note':'','disclosed':''})
r=hq.post(B+f"/hq/activations/{wit['id']}",json={'action':'appoint'}); chk('nor appoint her by the old route', r.status_code==409, r.text)
chk('she is still not a leader', not b.get(B+'/me').json()['user'].get('is_leader'))
# a candidate still in progress cannot be approved
c, r = reg('Midcand','chapter_lead','3'); mit=[x for x in hq.get(B+'/hq/activations?route=ref3').json()['items'] if x['first_name']=='Midcand'][0]
r=hq.post(B+f"/hq/activations/{mit['id']}",json={'action':'approve'}); chk('not before she finishes', r.status_code==409, r.text)
# approval
sql("delete from mail_outbox")
r=hq.post(B+f"/hq/activations/{it['id']}",json={'action':'approve'}); chk('HQ approves the one who passed', r.ok and r.json()['title'].startswith('State Secretary'), r.text)
me=a.get(B+'/me').json()['user']; chk('she is now a volunteer leader (to accept her agreement)', me['is_leader'] and me['todo']['appointment'], me.get('seat'))
gid=sql(f"select id from users where email='goodcand.{X}@example.org'")
chk('she gets a push/in-app notice', int(sql(f"select count(*) from notifications where user_id={gid} and kind='appointment'"))>=1)
chk('she gets an email', int(sql(f"select count(*) from mail_outbox where to_email='goodcand.{X}@example.org' and subject='You are now a WODDI volunteer leader'"))==1, sql("select to_email,subject from mail_outbox"))
eid=sql(f"select id from users where email='te.{X}@example.org'")
chk('the envoy is told in the app', int(sql(f"select count(*) from notifications where user_id={eid} and title like 'Goodcand Cand is now a volunteer leader%'"))==1)
chk('and by email', int(sql(f"select count(*) from mail_outbox where to_email='te.{X}@example.org'"))==1)
p=[x for x in env.get(B+'/envoy/home').json()['people'] if x['first_name']=='Goodcand'][0]; chk('envoy sees her approved', p['approved_as'].startswith('State Secretary'), p.get('approved_as'))
r=hq.post(B+f"/hq/activations/{it['id']}",json={'action':'approve'}); chk('not approved twice', r.status_code==409)
# messages: the envoy can write to WODDI HQ as a group
o=env.get(B+'/dm/options').json(); chk('envoy can choose WODDI HQ', 'staff' in o['who'], o['who'])
r=env.post(B+'/dm/send',json={'audience':{'who':'staff'},'subject':'Hello HQ','body':'A question from an envoy.'}); chk('envoy message to HQ sent', r.ok and r.json()['recipients']>=1, r.text)
r=env.post(B+'/dm/send',json={'audience':{'who':'leaders'},'subject':'x','body':'y'}); chk('envoy reaches only leaders she brought in', r.ok and r.json()['recipients']==1, r.text)
print(f'checks {ok+bad} FAILURES {bad}')
