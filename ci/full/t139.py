# v109.49: an envoy whose phone makes drawing hard types her full name as her signature instead. Synthetic data only.
# envoy page until accepted; HQ sees who accepted and acknowledges (Part F); reminders by email and phone; envoys make voice
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
e,u=envoy('ts1')
base={'profession':'Nurse','organisation':'Primary Health Centre','network':'WGMN','focus':'Maternal health outreach','first_30_days':'Meet ward women leaders','part_b':[0,1,2],'part_c':list(range(8)),'declaration':True,'signed_name':'Envts1 Test'}
r=e.post(B+'/envoy/acceptance',json=dict(base,signature=''))
chk('no drawing and no typed name is still refused', r.status_code==422 and r.json()['error']=='signature' and 'type your full name' in r.json()['message'], r.text)
r=e.post(B+'/envoy/acceptance',json=dict(base,signature='',signature_typed=True,signature_text='  '))
chk('an empty typed name is refused', r.status_code==422 and r.json()['error']=='signature', r.text)
r=e.post(B+'/envoy/acceptance',json=dict(base,signature='',signature_typed=True,signature_text='12'))
chk('digits are not a name', r.status_code==422, r.text)
r=e.post(B+'/envoy/acceptance',json=dict(base,signature='',signature_typed=True,signature_text='Envts1 <b>Test</b>'))
chk('a typed full name is accepted as her signature', r.ok, r.text)
rec=e.get(B+'/envoy/acceptance').json()['record']
chk('it is kept as a typed signature, markup removed', rec['signature']=='typed:Envts1 Test', rec['signature'])
chk('her dashboard opens after accepting', e.get(B+'/envoy/home').ok)
f=hq.get(B+f'/hq/envoy-acceptances/{u}').json()['record']
chk('HQ sees the typed signature on her form', f['signature']=='typed:Envts1 Test', f['signature'])
# v109.50: an envoy has no seat above her
PWH=subprocess.run(['php','-r','echo password_hash("Envts-pw-1", PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout
sql(f"update users set password_hash='{PWH}' where id={u}; update envoys set state_region='Akwa Ibom' where user_id={u}"); e2=login(f'ts1.{X}@wdos-mail.org','Envts-pw-1')
m=e2.get(B+'/leadmap?cc=NG&state=Akwa%20Ibom').json()
chk('an envoy is shown no leadership above her', m.get('above')==[] and m.get('role')=='envoy', {k:m.get(k) for k in ('above','role')})
print(ok,'passed, FAILURES',bad)
