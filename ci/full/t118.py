# v109.22: birthdays and inspiration by every channel, recorded for HQ.
import requests, subprocess, json, os, datetime
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
def cron(now, url='http://127.0.0.1:8099/v3/smtp/email'):
  env=dict(os.environ, ADMIN_KEY='t118-key', WDOS_TEST_NOW=now, BREVO_API_KEY='ci-not-real', MAIL_FROM='wdos-ci@wdos-mail.org', BREVO_API_URL=url)
  r=subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=env,capture_output=True,text=True); return r.stdout
pw=subprocess.run(['php','-r',"echo password_hash('pass-word-99', PASSWORD_DEFAULT);"],capture_output=True,text=True).stdout
mem=sql("select u.id from users u where u.role='member' and u.status='active' and u.network='WGMN' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by id limit 1")
sql(f"update users set email='nodob@test.local', password_hash='{pw}', must_change_password=0, dob=NULL where id={mem}")
m=login('nodob@test.local','pass-word-99'); ld=login('leader@test.local','leader-pass-1'); hq=login('hq@test.local','hq-password-1')
# 1. a member with no date of birth is asked and can add it once
chk('member without a date of birth', m.get(B+'/me').json()['user']['dob'] in (None,''))
chk('a wrong date is refused', m.post(B+'/me/birthday',json={'dob':'2015-02-30'}).status_code==422)
chk('under 18 is refused', m.post(B+'/me/birthday',json={'dob':(datetime.date.today()-datetime.timedelta(days=365*10)).isoformat()}).status_code==422)
r=m.post(B+'/me/birthday',json={'dob':'1980-06-15'}); chk('she adds it', r.ok and r.json()['user']['dob']=='1980-06-15', r.text)
chk('it cannot be changed again by her', m.post(B+'/me/birthday',json={'dob':'1981-06-15'}).status_code==409)
# 2. dashboard cards: a thought for today for a member, and for a leader with the week's message
c=m.get(B+'/inspiration/today?lang=en').json(); chk('member sees a thought for today', any(x['kind']=='daily' and x['label']=='Thought for today' for x in c['cards']), c)
chk('one card at a time (member)', len(c['cards'])==1, c)
c=ld.get(B+'/inspiration/today?lang=en').json(); wd=(datetime.datetime.now(datetime.UTC)+datetime.timedelta(hours=1)).isoweekday()
chk('one card at a time (leader)', len(c['cards'])==1, c)
chk('leader: the week\'s message on Monday, the thought for today on other days', c['cards'][0]['kind']==('weekly' if wd==1 else 'daily'), c)
# 3. scheduled sends: Monday 08:00 Lagos, with a birthday today
today=datetime.datetime.now(datetime.UTC).replace(tzinfo=None)+datetime.timedelta(hours=1)
sql(f"update users set dob='1985-{today:%m-%d}', email='bday.t118@wdos-mail.org' where email='leader@test.local'")
sql("update users u set u.email=concat('ldr',u.id,'@wdos-mail.org') where exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) and u.email like '%.local' and u.email<>'bday.t118@wdos-mail.org'")
sql("update users u set u.email=concat('mem',u.id,'@wdos-mail.org') where u.status='active' and u.email like '%.local' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null)")
sql("delete from app_settings where k like 'sent_%' or k like 'dob_ask_%'; delete from cron_runs; delete from mail_outbox; delete from auto_sends; delete from dob_asks")
out=cron('2026-11-23 08:00', 'http://127.0.0.1:9/none')   # keep the email bodies in the outbox to read them; chk('cron ran', '"ok":true' in out, out[:300])
sends={r.split('\t')[0]:r.split('\t') for r in sql("select kind,in_app,push,email,label from auto_sends").split('\n') if r}
chk('birthday recorded', 'birthday' in sends and int(sends['birthday'][1])>=1, sends)
chk('birthday by email', int(sends.get('birthday',[0,0,0,0])[3])>=1, sends)
chk('weekly to leaders, with email', 'weekly' in sends and int(sends['weekly'][1])>=1 and int(sends['weekly'][3])>=1, sends)
chk('daily thought to members', 'daily' in sends and int(sends['daily'][1])>=1, sends)
chk('no daily for leaders on the weekly Monday', sql("select count(*) from notifications n join seats s on s.user_id=n.user_id and s.ends_at is null where n.title like 'Thought for today%'")=='0')
chk('member got the thought for today', sql(f"select count(*) from notifications where user_id={mem} and title like 'Thought for today:%'")=='1')
chk('emails queued for the leaders', int(sql("select count(*) from mail_outbox where subject like 'WDOS: Weekly inspiration:%'") or 0)>=1)
chk('birthday email to her', sql("select count(*) from mail_outbox where to_email='bday.t118@wdos-mail.org' and subject like 'WDOS: Happy birthday%'")=='1')
chk('the leaders\' weekly email carries the week\'s thoughts', int(sql("select count(*) from mail_outbox where subject like 'WDOS: Weekly inspiration:%' and html like '%Your thoughts for this week%'") or 0)>=1)
chk('members get the week\'s thoughts by email', int(sql("select count(*) from mail_outbox where subject='WDOS: Your thoughts for this week'") or 0)>=1)
chk('leaders do not get a second email', sql("select count(*) from mail_outbox o join users u on u.email=o.to_email join seats s on s.user_id=u.id and s.ends_at is null where o.subject='WDOS: Your thoughts for this week'")=='0')
chk('daily send records its emails', int(sends.get('daily',[0,0,0,0])[3])>=1, sends)
chk('the email has seven days', sql("select html from mail_outbox where subject='WDOS: Your thoughts for this week' limit 1").count('Monday')>=1 and sql("select html from mail_outbox where subject='WDOS: Your thoughts for this week' limit 1").count('Sunday')>=1)
out=cron('2026-11-23 08:30'); chk('nothing goes twice', sql("select count(*) from auto_sends")==str(len(sends)), sql("select kind from auto_sends"))
# 4. members with no date of birth are asked by email, at most twice, 14 days apart
sql("update users set dob=NULL where id in (select id from (select u.id from users u where u.status='active' and u.role='member' and u.email like 'mem%@wdos-mail.org' order by id limit 3) t)")
nod=int(sql("select count(*) from users where status='active' and dob is null and role not in ('hq_admin','super_admin')"))
cron('2026-11-23 09:30')
chk('asked by email', int(sql("select count(*) from mail_outbox where subject='WDOS: When is your birthday?'") or 0)>=1)
chk('asked in the app', int(sql("select count(*) from notifications where title='When is your birthday?'") or 0)==nod, (nod, sql("select count(*) from notifications where title='When is your birthday?'")))
chk('recorded for HQ', sql("select count(*) from auto_sends where kind='dob_ask'")=='1')
chk('nobody with a date of birth is asked', sql("select count(*) from dob_asks a join users u on u.id=a.user_id where u.dob is not null")=='0')
cron('2026-11-24 09:30'); chk('not asked again the next day', sql("select count(*) from auto_sends where kind='dob_ask'")=='1')
cron('2026-12-08 09:30'); chk('asked a second time after 14 days', sql("select max(asks) from dob_asks")=='2')
cron('2026-12-23 09:30'); chk('never more than twice', sql("select max(asks) from dob_asks")=='2' and sql("select count(*) from auto_sends where kind='dob_ask'")=='2')
# 5. HQ panel
r=hq.get(B+'/hq/celebrations').json()
chk('HQ sees today\'s birthday', any(p['name'] for p in r['today']), r['today'])
chk('HQ sees the sends', {s['kind'] for s in r['sends']}>={'birthday','weekly','daily'}, r['sends'])
chk('HQ sees today\'s thoughts', r['daily']['WGMN'] and r['daily']['WNNN'], r['daily'])
chk('members without a date of birth are counted', isinstance(r['missing'],list))
chk('HQ list of missing birthdays', hq.get(B+'/hq/celebrations/missing').ok)
chk('the panel is HQ only', ld.get(B+'/hq/celebrations').status_code==403)
print('checks',ok+bad,'FAILURES',bad)
