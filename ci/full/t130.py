# v109.38: the person who referred a candidate hears by email and in the app when she has not signed in, stopped her activation,
# passed, or was not appointed; each news goes once, in the referrer's language. A follow-up message reaches the candidate by email in full.
# (setup copied from t127) v109.34: an envoy's candidate chooses from every leadership role, adds her photo, does the 3-day activation and is scored;
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
import os, datetime
def cron(now): return subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=dict(os.environ, ADMIN_KEY='x', WDOS_TEST_NOW=now, BREVO_API_KEY='ci-not-real', MAIL_FROM='wdos-ci@wdos-mail.org', BREVO_API_URL='http://127.0.0.1:9/v3/smtp/email'),capture_output=True,text=True)
lag=datetime.datetime.now(datetime.UTC).replace(tzinfo=None)+datetime.timedelta(hours=1); NOW=lag.strftime('%Y-%m-%d')+' 10:30'
eid=sql(f"select id from users where email='te.{X}@example.org'"); EM=f'te.{X}@example.org'
def news(cid,kind): return int(sql(f"select count(*) from envoy_alerts where envoy_id={eid} and user_id={cid} and kind='ref_{kind}'"))
def mails(subj): return int(sql(f"select count(*) from mail_outbox where to_email='{EM}' and subject like '{subj}%'"))
def uid(n): return sql(f"select id from users where email='{n.lower()}.{X}@example.org'")
sql("delete from mail_outbox")
DAYS=[(1,{'reached_end':True,'complete':True}),(2,{'reached_end':True,'answers':{'why':'To serve women in my state'},'acked':[0],'complete':True}),(3,{'reached_end':True,'complete':True})]
long='I will hold monthly meetings in each LGA, follow up every chapter lead by phone and send my report to HQ on time each month.'
# passed
a, r = reg('Passcand','state_secretary','1'); assert r.ok, r.text
for d,b in DAYS: a.post(B+f'/activation/day/{d}',json=b)
r=a.post(B+'/activation/assessment',json={'statements':{'1':'yes','2':'yes'},'short_answers':{'1':long,'2':long},'confirmations':[1],'readiness':'ready','signature_name':'Passcand Cand'}); chk('passed assessment submitted', r.ok, r.text)
pa=uid('Passcand')
chk('envoy told in the app that she passed, with her score', int(sql(f"select count(*) from notifications where user_id={eid} and title='Passcand Cand passed her activation' and body like 'She scored 100 of 100%' and url like '#/inbox/to/{pa}?f=passed%'"))==1)
chk('and by email', mails('Passcand Cand passed her activation')==1, sql("select to_email,subject from mail_outbox"))
import time as _t; _t.sleep(1)
def sent(needle):
  out=[]
  for ln in open('/tmp/aimock.log',encoding='utf-8',errors='replace'):
    if needle in ln: out.append(json.loads(ln) if ln.strip().startswith('{') else ln)
  return [json.dumps(x,ensure_ascii=False) if not isinstance(x,str) else x for x in out]
html=' '.join(sent('Passcand Cand passed her activation'))
chk('the email has a follow-up button to message her', f'#/inbox/to/{pa}?f=passed' in html and 'Send her a message' in html, html[-600:])
# a weak candidate: no "passed" news; HQ does not appoint her, envoy told in French
b, r = reg('Weakcand','chapter_lead','2'); assert r.ok
for d,x in DAYS: b.post(B+f'/activation/day/{d}',json=x)
b.post(B+'/activation/assessment',json={'statements':{'1':'clarify','2':'clarify'},'short_answers':{'1':'yes','2':'ok'},'confirmations':[1],'readiness':'not_now','signature_name':'Weakcand Cand'})
wk=uid('Weakcand'); chk('no "passed" news for a candidate below the pass mark', news(wk,'passed')==0)
sql(f"update users set lang='fr' where id={eid}")
wid=[x for x in hq.get(B+'/hq/activations?route=ref3').json()['items'] if x['first_name']=='Weakcand'][0]['id']
r=hq.post(B+f"/hq/activations/{wid}",json={'action':'outcome','outcome':'not_selected','note':'','disclosed':''}); chk('HQ records not selected', r.ok, r.text)
chk('envoy told she was not appointed, in French', int(sql(f"select count(*) from mail_outbox where to_email='{EM}' and subject='Weakcand Cand n''a pas été nommée cette fois'"))==1, sql(f"select subject from mail_outbox where to_email='{EM}'"))
_t.sleep(1); fr=' '.join(sent('Weakcand Cand n')); chk('the French email names the outcome and greets her in French', 'Not selected' in fr and ('Chère' in fr or 'Ch\\u00e8re' in fr), fr[-400:])
hq.post(B+f"/hq/activations/{wid}",json={'action':'outcome','outcome':'not_selected','note':'again','disclosed':''})
chk('recording the same outcome again does not repeat the news', news(wk,'outcome')==1)
sql(f"update users set lang='en' where id={eid}")
# not signing in, and an activation that ran out
c, r = reg('Quietcand','chapter_lead','3'); qc=uid('Quietcand')
sql(f"update referrals set created_at=utc_timestamp()-interval 4 day where user_id={qc}; update users set last_seen_at=utc_timestamp()-interval 4 day where id={qc}")
d_, r = reg('Stallcand','chapter_lead','4'); sc_=uid('Stallcand')
sql(f"update activations set deadline=curdate()-interval 2 day where user_id={sc_}")
o_, r = reg('Oldcand','chapter_lead','5'); oc=uid('Oldcand')
sql(f"update activations set deadline=curdate()-interval 30 day where user_id={oc}")
sql("delete from app_settings where k like 'referral_news_%'"); out=cron(NOW)
chk('daily run tells the envoy who has not signed in', news(qc,'nosign')==1 and mails('Quietcand Cand has not signed in for 3 days')==1, out.stdout[-300:]+out.stderr[-300:])
chk('and whose activation ran out', news(sc_,'stalled')==1 and mails('Stallcand Cand did not finish her activation')==1)
chk('old history is not sent', news(oc,'stalled')==0)
chk('a candidate who is signing in is not reported', news(pa,'nosign')==0)
sql("delete from app_settings where k like 'referral_news_%'"); cron(NOW)
chk('the next run the same week does not repeat', news(qc,'nosign')==1 and news(sc_,'stalled')==1 and mails('Quietcand')==1)
# follow-up message: the candidate gets the whole message by email
msg='Hello Passcand,\nWell done on your activation. '+('I am here to support you all the way. '*12)+'END-OF-MESSAGE'
sql(f"update users set email='passcand.{X}@wdos-mail.org' where id={pa}")
r=env.post(B+'/dm/send',json={'audience':{'people':[int(pa)]},'subject':'Congratulations','body':msg}); chk('envoy sends a follow-up to her candidate', r.ok and r.json()['recipients']==1, r.text)
sql("update dm_batches set undo_until=utc_timestamp()-interval 1 minute; delete from app_settings where k='outbox_tick'"); env.get(B+'/me'); _t.sleep(2)
h=' '.join(sent('END-OF-MESSAGE'))
chk('candidate gets the follow-up by email, the whole message with its line breaks', 'END-OF-MESSAGE' in h and '<br' in h and f'passcand.{X}@wdos-mail.org' in h, h[:300])
chk('and in the app', int(sql(f"select count(*) from notifications where user_id={pa} and kind='message'"))>=1)
print(f'checks {ok+bad} FAILURES {bad}')
