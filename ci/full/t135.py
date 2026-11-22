# v109.45: the WODDI Envoy Appointment Acceptance & Commitment Form (prefilled, Parts B and C ticked, signed) holds every other
# envoy page until accepted; HQ sees who accepted and acknowledges (Part F); reminders by email and phone; envoys make voice
# calls to HQ and to the people they referred, who can ring them back. Synthetic data only.
import requests, subprocess, time, os, datetime, base64, io
from PIL import Image, ImageDraw
X=str(int(time.time()))[-6:]; B='http://localhost:8080/api'; H={'X-WDOS':'1'}; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(x)[:400])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers.update(H); return s
def login(e,p): s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
def cron(now): return subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=dict(os.environ, ADMIN_KEY='x', WDOS_TEST_NOW=now, BREVO_API_KEY='ci-not-real', MAIL_FROM='wdos-ci@wdos-mail.org', BREVO_API_URL='http://127.0.0.1:9/v3/smtp/email'),capture_output=True,text=True)
sql("delete from login_attempts")
hq=login('hq@test.local','hq-password-1')
def envoy(tag):
  r=hq.post(B+'/hq/envoys',json={'first_name':'Env'+tag,'last_name':'Test','email':f'{tag}.{X}@example.org','phone':'08031234567','country_code':'NG'}); assert r.ok, r.text
  s=login(f'{tag}.{X}@example.org', r.json()['temporary_password']); uid=int(sql(f"select id from users where email='{tag}.{X}@example.org'"))
  sql(f"update users set email='{tag}.{X}@wdos-mail.org' where id={uid}"); return s, uid
e1,u1=envoy('ea1'); e2,u2=envoy('ea2')
me=e1.get(B+'/me').json()['user']; chk('a new envoy is held at her appointment form', me.get('envoy_form_needed') is True and me.get('can_call') is False, me)
r=e1.get(B+'/envoy/home'); chk('the server refuses her dashboard until she accepts', r.status_code==403 and r.json().get('error')=='envoy_acceptance', r.text)
chk('and voice calls', e1.get(B+'/calls').status_code==403 and e1.post(B+'/calls',json={'to':'hq'}).status_code==403)
chk('she can still read her notices and her own details', e1.get(B+'/notifications').ok and e1.get(B+'/me').ok)
d=e1.get(B+'/envoy/acceptance').json(); p=d['prefill']
chk('the form comes filled from her WDOS record', p['full_name']=='Envea1 Test' and p['phone']=='08031234567' and p['country']=='Nigeria' and bool(p['effective_date']), p)
chk('what WDOS does not have is left for her to fill', p['profession']=='' , p)
chk('Part B has 3 statements and Part C has 8, as on the paper form', len(d['part_b'])==3 and len(d['part_c'])==8)
im=Image.new('RGB',(900,260),'white'); dr=ImageDraw.Draw(im); dr.line([(60,180),(200,90),(330,170),(480,80),(640,160)],fill='black',width=6)
buf=io.BytesIO(); im.save(buf,'PNG'); SIG='data:image/png;base64,'+base64.b64encode(buf.getvalue()).decode()
full={'profession':'Teacher','organisation':'Community Girls School','network':'WGMN','focus':'Girls education in Bauchi','first_30_days':'Hold two outreach meetings with women groups','part_b':[0,1,2],'part_c':list(range(8)),'declaration':True,'signature':SIG,'signed_name':'Envea1 Test'}
def miss(k,v): b=dict(full); b[k]=v; return e1.post(B+'/envoy/acceptance',json=b)
r=miss('part_b',[0,1]); chk('every Part B statement must be ticked', r.status_code==422 and r.json()['error']=='part_b', r.text)
r=miss('part_c',[0,1,2,3,4,5,6]); chk('every Part C commitment must be ticked', r.status_code==422 and r.json()['error']=='part_c', r.text)
r=miss('profession',''); chk('anything WDOS did not know must be filled', r.status_code==422 and r.json()['error']=='profession', r.text)
r=miss('signature',''); chk('she must sign', r.status_code==422 and r.json()['error']=='signature', r.text)
r=miss('declaration',False); chk('she must tick the declaration', r.status_code==422 and r.json()['error']=='declaration', r.text)
sql("delete from mail_outbox")
r=e1.post(B+'/envoy/acceptance',json=full); chk('she accepts', r.ok, r.text)
me=e1.get(B+'/me').json()['user']; chk('her dashboard opens', me.get('envoy_form_needed') is False and e1.get(B+'/envoy/home').ok and me.get('can_call') is True, me)
chk('a second acceptance is refused', e1.post(B+'/envoy/acceptance',json=full).status_code==409)
time.sleep(1)
def mails(addr,subj): return int(sql(f"select count(*) from mail_outbox where to_email='{addr}' and subject like '{subj}%'") or 0) or sum(1 for l in open('/tmp/aimock.log',encoding='utf-8',errors='replace') if addr in l and subj.split('%')[0] in l)
chk('she gets a confirmation email with her form', mails(f'ea1.{X}@wdos-mail.org','Your WODDI Envoy appointment: accepted')>=1)
chk('HQ is told in the app', sql(f"select count(*) from notifications n join users u on u.id=n.user_id where u.role in ('hq_admin','super_admin') and n.title like 'Envea1 Test accepted%'")!='0')
L=hq.get(B+'/hq/envoy-acceptances').json(); rows={x['id']:x for x in L['envoys']}
chk('HQ sees who has accepted and who has not', rows[u1]['accepted_at'] and not rows[u2]['accepted_at'] and L['totals']['accepted']>=1 and L['totals']['pending']>=1, L['totals'])
f=hq.get(B+f'/hq/envoy-acceptances/{u1}').json()['record']
chk('HQ opens her filled form, ticks and signature included', f['profession']=='Teacher' and f['part_b']==[0,1,2] and len(f['part_c'])==8 and f['signature'].startswith('data:image/png'), {k:f[k] for k in ('profession','part_b')})
r=hq.post(B+f'/hq/envoy-acceptances/{u1}/acknowledge',json={'name':'Test Officer','title':'Administration Lead','ref':''}); chk('HQ acknowledges (Part F) and WDOS makes the reference', r.ok and r.json()['ref'].startswith('WODDI-ENV-'), r.text)
chk('she is told of the acknowledgement', sql(f"select count(*) from notifications where user_id={u1} and title='WODDI HQ has acknowledged your appointment'")=='1')
chk('the acknowledgement shows on her form', e1.get(B+'/envoy/acceptance').json()['record']['ack']['name']=='Test Officer')
# follow-up reminders for the envoy who has not accepted
lag=datetime.datetime.now(datetime.UTC).replace(tzinfo=None)+datetime.timedelta(hours=1); NOW=lag.strftime('%Y-%m-%d')+' 09:30'
sql("delete from app_settings where k like 'envoy_accept_%'"); out=cron(NOW)
chk('the daily run reminds her by email and phone', sql(f"select count(*) from envoy_accept_nudges where user_id={u2}")=='1' and sql(f"select count(*) from notifications where user_id={u2} and title='Please accept your WODDI Envoy appointment'")=='1', out.stdout[-200:]+out.stderr[-300:])
chk('the accepted envoy is not reminded', sql(f"select count(*) from envoy_accept_nudges where user_id={u1}")=='0')
sql("delete from app_settings where k like 'envoy_accept_%'"); cron(NOW)
chk('not again the same day', sql(f"select count(*) from envoy_accept_nudges where user_id={u2}")=='1')
sql(f"update envoy_accept_nudges set sent_at=utc_timestamp()-interval 3 day where user_id={u2}"); sql("delete from app_settings where k like 'envoy_accept_%'"); cron(NOW)
chk('the next reminder comes two days later', sql(f"select count(*) from envoy_accept_nudges where user_id={u2}")=='2')
r=hq.post(B+'/hq/envoy-acceptances/remind',json={}); chk('HQ can remind everyone not yet accepted', r.ok and r.json()['reminded']>=1, r.text)
# voice calls
mid=int(sql("select u.id from users u where u.role='member' and u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by u.id limit 1"))
other=int(sql(f"select u.id from users u where u.role='member' and u.status='active' and u.id<>{mid} and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by u.id limit 1"))
sql(f"delete from referrals where user_id={mid}"); lk=sql(f"select link_id from envoys where user_id={u1}")
sql(f"insert into referrals (user_id, referrer_id, link_id, created_at) values ({mid},{u1},{lk},utc_timestamp())")
c=e1.get(B+'/calls').json(); chk('the envoy sees the people she referred to ring', c['can_call'] and any(p['id']==mid for p in c['people']) and not any(p['id']==other for p in c['people']), [p['id'] for p in c['people']])
r=e1.post(B+'/calls',json={'to':mid}); chk('she rings someone she referred', r.ok, r.text)
chk('her call rings on that person', sql(f"select count(*) from voice_calls where caller_id={u1} and callee_id={mid} and status='ringing'")=='1')
H2=f"""update users set password_hash='{subprocess.run(['php','-r','echo password_hash("Mem-pass-1", PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout}', must_change_password=0, status='active' where id={mid}"""; sql(H2)
mem=login(sql(f"select email from users where id={mid}"),'Mem-pass-1')
rg=mem.get(B+'/calls/ring').json()['calls']; chk('the person she referred sees the call ringing, though she is not a leader', any(c.get('with','').startswith('Envea1') for c in rg), rg)

r=e1.post(B+'/calls',json={'to':other}); chk('she cannot ring someone she did not refer', r.status_code==403, r.text)
r=e1.post(B+'/calls',json={'to':'hq'}); chk('she rings WODDI HQ', r.ok, r.text)
chk('HQ is rung with her designation', sql(f"select count(*) from notifications n join users u on u.id=n.user_id where u.role in ('hq_admin','super_admin') and n.kind='call' and n.title='Voice call from Envea1 Test'")!='0')
mc=mem.get(B+'/calls').json(); chk('she can ring her envoy back, and only her', mc['can_call'] and [p['id'] for p in mc['people']]==[u1], mc.get('people'))
chk('but not HQ', mem.post(B+'/calls',json={'to':'hq'}).status_code==403)
chk('HQ can ring envoys', any(p['id']==u1 for p in hq.get(B+'/calls?q=Envea1').json()['people']))
chk('the envoy still waiting cannot make calls', e2.post(B+'/calls',json={'to':'hq'}).status_code==403)
print(f'checks {ok+bad} FAILURES {bad}')
