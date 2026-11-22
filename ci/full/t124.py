# v109.31: an envoy's volunteer leaders and the alerts she gets; first password page; settings for phone sign-in. Synthetic data.
import requests, subprocess, json, os
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s if r.ok else r
def cron(now):
  env=dict(os.environ, ADMIN_KEY='t124', WDOS_TEST_NOW=now)
  return subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=env,capture_output=True,text=True).stdout
# v109.45: envoys must accept their appointment form before anything else works; this test is about other things
_login_before_v10945=login
def login(*a,**k):
  sql("insert ignore into envoy_acceptances (user_id,accepted_at) select id, utc_timestamp() from users where role='envoy'")
  return _login_before_v10945(*a,**k)
hq=login('hq@test.local','hq-password-1')
d=hq.post(B+'/hq/envoys/import',json={'rows':[{'state':'Lagos','name':'Test Envoy Person','phone':'08000000099'}]}).json(); env=d['added'][0]['id']
x=hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','levels':['envoy'],'ids':[env],'confirm':True}).json(); tmp=x['issued'][0]['temporary_password']
E=login('08000000099',tmp)
me=E.get(B+'/me').json()['user']; chk('temporary password: must choose her own', me['must_change_password'] is True and me['email_is_phone'] is True)
# three leaders she brought in
L=[int(i) for i in sql("select u.id from seats s join users u on u.id=s.user_id join org_units o on o.id=s.org_unit_id where s.ends_at is null and o.country_code='NG' and u.status='active' and u.role='member' and u.email<>'leader@test.local' order by u.id limit 3").split()]
for i in L: sql(f"insert into referrals (user_id,link_id,referrer_id,category,created_at) values ({i},(select link_id from envoys where user_id={env}),{env},'envoy',utc_timestamp()) on duplicate key update referrer_id={env}")
a,b,c=L
sql(f"update users set last_seen_at=utc_timestamp() where id in ({a},{c})")
sql(f"update users set last_seen_at=date_sub(utc_timestamp(), interval 12 day) where id={b}")
sql(f"insert into tasks (title,priority,due_on,status,assigned_by,assignee_id,created_at,updated_at) values ('Visit the chapters','normal','2026-11-01','open',1,{a},utc_timestamp(),utc_timestamp())")
sql(f"update seats set ends_at=utc_timestamp(), end_reason='resigned' where user_id={c} and ends_at is null")
h=E.get(B+'/envoy/home').json(); ls={l['id']:l for l in h['leaders']}
chk('her volunteer leaders are listed', set(L)<=set(ls), list(ls))
chk('overdue task flagged', any(f['kind']=='task_overdue' for f in ls[a]['flags']), ls[a]['flags'])
chk('not signing in flagged', any(f['kind']=='no_signin' and '12 days' in f['text'] for f in ls[b]['flags']), ls[b]['flags'])
chk('stopped serving flagged', any(f['kind']=='stopped' and 'Resigned' in f['text'] for f in ls[c]['flags']) and not ls[c]['serving'], ls[c])
chk('numbers on the dashboard', h['leader_kpis']['brought_in']==3 and h['leader_kpis']['serving']==2 and h['leader_kpis']['attention']==3 and h['leader_kpis']['overdue']==1, h['leader_kpis'])
chk('those who need her come first', all(l['flags'] for l in h['leaders'][:3]))
chk('a member cannot open it', isinstance(login('leader@test.local','leader-pass-1'),requests.Session) and login('leader@test.local','leader-pass-1').get(B+'/envoy/home').status_code==403)
chk('HQ sees them on her page', len(hq.get(B+f'/hq/envoys/{env}').json()['leaders'])==3)
# the daily alert
sql("delete from app_settings where k like 'envoy_alerts_%'; delete from envoy_alerts")
cron('2026-11-23 07:30'); chk('not before 08:00', sql(f"select count(*) from notifications where user_id={env} and kind='activation'")=='0')
cron('2026-11-23 08:30'); n=sql(f"select concat(title,' || ',body) from notifications where user_id={env} and kind='activation' order by id desc limit 1")
chk('she is told', '3 leaders you brought in need you' in n, n)
chk('what each needs', 'overdue' in n and 'not signed in for 12 days' in n and 'resigned' in n, n)
cron('2026-11-23 09:30'); chk('once a day', sql(f"select count(*) from notifications where user_id={env} and kind='activation'")=='1')
sql("delete from app_settings where k like 'envoy_alerts_%'")
cron('2026-11-24 08:30'); chk('nothing new, nothing sent', sql(f"select count(*) from notifications where user_id={env} and kind='activation'")=='1')
sql(f"insert into tasks (title,priority,due_on,status,assigned_by,assignee_id,created_at,updated_at) values ('Second task','normal','2026-11-02','open',1,{a},utc_timestamp(),utc_timestamp())")
sql("delete from app_settings where k like 'envoy_alerts_%'")
cron('2026-11-24 09:00'); n=sql(f"select body from notifications where user_id={env} and kind='activation' order by id desc limit 1")
chk('a new overdue task is told', sql(f"select count(*) from notifications where user_id={env} and kind='activation'")=='2' and 'overdue' in n and 'resigned' not in n, n)
# settings for a phone sign-in
r=E.post(B+'/me',json={'first_name':'Test','last_name':'Envoy','phone':'08111111111','lga':'X','community':'Y'})
chk('her sign-in number cannot be changed by her', r.ok and sql(f"select phone from users where id={env}")=='08000000099')
chk('member fields are not saved for an envoy', sql(f"select concat(lga,'|',community) from users where id={env}")=='|')
# choosing her own password
r=E.post(B+'/me/password',json={'current_password':tmp,'new_password':'my-own-pass1'}); chk('she chooses her own', r.ok, r.text)
chk('done', E.get(B+'/me').json()['user']['must_change_password'] is False and isinstance(login('08000000099','my-own-pass1'),requests.Session))
print('checks',ok+bad,'FAILURES',bad)
