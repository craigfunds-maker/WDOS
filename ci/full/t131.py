# v109.39: every email carries the WODDI logo at the top, and every phone notification shows the WODDI logo as its picture
import requests, subprocess, json, time
B='http://localhost:8080'; H={'X-WDOS':'1'}; ok=bad=0
def chk(n,c,info=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(info)[:400])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def sent(needle): return [l for l in open('/tmp/aimock.log',encoding='utf-8',errors='replace') if needle in l and '/smtp' in l]
sql("delete from login_attempts; update users set email='hq@wdos-mail.org' where email='hq@test.local'")
r=requests.post(B+'/api/auth/forgot',json={'email':'hq@wdos-mail.org'},headers=H); time.sleep(2)
m=sent('Reset your WDOS password')
chk('password reset email sent', r.ok and m, r.text)
chk('reset email has the WODDI logo at the top and a button', m and m[-1].count('woddi-email.png')==1 and 'Choose a new password' in m[-1] and m[-1].index('woddi-email.png')<m[-1].index('Reset your WDOS password</h2>'), m[-1][:600] if m else '')
sql("update users set email='hq@test.local' where email='hq@wdos-mail.org'")
hq=requests.Session(); hq.headers.update(H); hq.post(B+'/api/auth/login',json={'email':'hq@test.local','password':'hq-password-1'})
# an email built with mail_wrap: logo once, not twice (send_mail must not add a second)
mid=sql("select id from users where role='member' and status='active' order by id limit 1"); sql(f"update users set email='dmtest@wdos-mail.org' where id={mid}")
r=hq.post(B+'/api/dm/send',json={'audience':{'people':[int(mid)]},'subject':'Logo check','body':'Hello, this is a logo check.'})
sql("update dm_batches set undo_until=utc_timestamp()-interval 1 minute; delete from app_settings where k='outbox_tick'"); hq.get(B+'/api/me'); time.sleep(3)
t=sent('dmtest@wdos-mail.org')
chk('a normal WDOS email (a message from HQ) has the logo exactly once', t and t[-1].count('woddi-email.png')==1, (r.status_code, r.text[:200], t[-1][:300] if t else ''))
# an email queued without mail_wrap still gets the logo
sql("insert into mail_outbox (to_email,to_name,subject,html,created_at) values ('raw@wdos-mail.org','Raw','Plain email test','<p>plain body</p>',utc_timestamp())")
sql("delete from app_settings where k='outbox_tick'"); hq.get(B+'/api/me'); time.sleep(2)
for _ in range(3):
  if sent('Plain email test'): break
  hq.post(B+'/api/hq/system/email-retry',json={}); time.sleep(2)
p=sent('Plain email test'); chk('an email built any other way still goes out with the logo', p and p[-1].count('woddi-email.png')==1 and 'plain body' in p[-1], p[-1][:300] if p else sql("select status,last_error from mail_outbox where to_email='raw@wdos-mail.org'"))
# images are served
for path in ['/assets/logos/woddi-email.png','/assets/icons/woddi-notify-192.png']:
  r=requests.get(B+path); chk(f'{path} is served as an image', r.ok and r.headers.get('content-type','').startswith('image/png') and len(r.content)>5000, r.status_code)
sw=requests.get(B+'/sw.js').text
chk('phone notifications use the WODDI logo as their picture', "icon: '/assets/icons/woddi-notify-192.png'" in sw and "'/assets/icons/woddi-notify-192.png'" in sw.split('SHELL')[1][:900])
print(f'checks {ok+bad} FAILURES {bad}')
