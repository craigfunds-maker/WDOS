# v109.37: sign-in drive and envoy launch pack, weekly sign-in email, missing-detail asks, Monday country report,
# chapter placement by area, country and state in Settings. Synthetic data only.
import requests, subprocess, os, json, re, urllib.parse
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:400])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
def login(e,p): s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s if r.ok else r
# v109.45: envoys must accept their appointment form before anything else works; these tests are about other things
_login_before_v10945=login
def login(*a,**k):
  sql("insert ignore into envoy_acceptances (user_id,accepted_at) select id, utc_timestamp() from users where role='envoy'")
  return _login_before_v10945(*a,**k)
def cron(now): return subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=dict(os.environ, ADMIN_KEY='x', WDOS_TEST_NOW=now, BREVO_API_KEY='ci-not-real', MAIL_FROM='wdos-ci@wdos-mail.org', BREVO_API_URL='http://127.0.0.1:9/v3/smtp/email'),capture_output=True,text=True).stdout
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
# a seated leader who has never signed in, with a Nigerian phone and a real-looking email
ng=sql("select id from org_units where level='country' and country_code='NG'"); kd=sql(f"select id from org_units where parent_id={ng} and name='Kaduna'")
r=hq.post(B+'/hq/leaders/add',json={'first_name':'Drive','last_name':'Leader','email':'drive.leader@example.org','phone':'08031234567','network':'WGMN','position':'state_secretary','org_unit_id':int(kd)}); lid=r.json()['id']
sql(f"update users set email='drive.leader@wdos-mail.org' where id={lid}")
d=hq.get(B+'/hq/signin-drive?who=leaders').json(); me=[p for p in d['people'] if p['id']==lid]
chk('she is on the drive list', len(me)==1 and me[0]['wa']=='2348031234567' and me[0]['email'], me)
chk('totals', d['totals']['never']>=1 and 'signed_in_after_pack' in d['totals'], d['totals'])
chk('a leader cannot open the drive', ld.get(B+'/hq/signin-drive').status_code in (401,403))
r=hq.post(B+f'/hq/signin-drive/{lid}/pack',json={}); j=r.json()
chk('pack made', r.ok and j['has_number'] and j['wa_url'].startswith('https://wa.me/2348031234567?text='), r.text[:300])
temp=re.search(r'Temporary password: (\S+)',j.get('text','')); chk('message carries the link, her sign-in and a temporary password', temp and '/#/login' in j['text'] and 'State Secretary' in j['text'], j.get('text'))
s=login('drive.leader@wdos-mail.org', temp.group(1) if temp else 'x'); chk('the temporary password signs her in', not isinstance(s,requests.Response))
chk('pack recorded', sql(f"select packs from signin_packs where user_id={lid}")=='1')
r=hq.post(B+f'/hq/signin-drive/{lid}/pack',json={}); chk('after she signed in, a new pack needs confirming', r.status_code==409, r.text)
d=hq.get(B+'/hq/signin-drive?who=leaders').json(); chk('she leaves the list once signed in', not any(p['id']==lid for p in d['people'])); chk('and counts as signed in after the pack', d['totals']['signed_in_after_pack']>=1, d['totals'])
# envoy launch pack
r=hq.post(B+'/hq/envoys',json={'first_name':'Pack','last_name':'Envoy','email':'pack.envoy@wdos-mail.org','phone':'+256772000111','country_code':'UG'}); eid=r.json()['id']
e=hq.get(B+'/hq/signin-drive?who=envoys').json(); chk('envoy on the envoy tab', any(p['id']==eid and p['wa']=='256772000111' for p in e['people']), [p for p in e['people'] if p['id']==eid])
j=hq.post(B+f'/hq/signin-drive/{eid}/pack',json={}).json(); chk('envoy pack has her referral link and the video guide', '/#/r/' in j['text'] and 'wdos-envoy-guide-en.mp4' in j['text'], j['text'])
# weekly sign-in email on Tuesday 09:00 Lagos, only to never-signed-in leaders/envoys with a real email
r=hq.post(B+'/hq/leaders/add',json={'first_name':'Mail','last_name':'Waiting','email':'mail.waiting@wdos-mail.org','phone':'','network':'WNNN','position':'state_secretary','org_unit_id':int(kd)}); mid=r.json()['id']
sql("delete from mail_outbox; delete from app_settings where k like 'signin_nudge_%'")
cron('2026-11-24 08:30'); chk('not before 09:00', sql("select count(*) from mail_outbox where subject='Your WDOS account is waiting for you'")=='0')
cron('2026-11-23 10:30'); chk('not on a Monday', sql("select count(*) from mail_outbox where subject='Your WDOS account is waiting for you' and to_email='mail.waiting@wdos-mail.org'")=='0')
cron('2026-11-24 09:30'); chk('Tuesday: she is emailed', sql("select count(*) from mail_outbox where subject='Your WDOS account is waiting for you' and to_email='mail.waiting@wdos-mail.org'")=='1')
chk('the one who signed in is not', sql("select count(*) from mail_outbox where to_email='drive.leader@wdos-mail.org' and subject='Your WDOS account is waiting for you'")=='0')
html=sql("select html from mail_outbox where to_email='mail.waiting@wdos-mail.org' order by id desc limit 1"); tok=re.search(r'#/reset/([A-Za-z0-9_-]+)',html)
chk('the email links to choose a password', bool(tok))
r=S().post(B+'/auth/reset',json={'token':tok.group(1) if tok else '','password':'chosen-pass-9'}); chk('the link lets her choose a password', r.ok, r.text)
chk('and she signs in with it', not isinstance(login('mail.waiting@wdos-mail.org','chosen-pass-9'),requests.Response))
sql("delete from app_settings where k like 'signin_nudge_%'"); cron('2026-11-24 10:30'); chk('once a day only', sql("select nudges from signin_packs where user_id=%d"%mid)=='1')
# missing details: no country, or no LGA/community and no chapter
m=sql("select id from users where email='seed5@test.local'"); sql(f"update users set country_code=NULL, chapter_id=NULL where id={m}; delete from chapter_members where user_id={m}; delete from detail_asks; delete from app_settings where k like 'detail_ask_%'")
cron('2026-11-21 09:30'); chk('not before 10:00', sql(f"select count(*) from detail_asks where user_id={m}")=='0')
cron('2026-11-21 10:30'); chk('she is asked', sql(f"select asks from detail_asks where user_id={m}")=='1' and sql(f"select count(*) from notifications where user_id={m} and title='Please complete your WODDI details'")=='1')
sql("delete from app_settings where k like 'detail_ask_%'"); cron('2026-11-22 10:30'); chk('not again the next day', sql(f"select asks from detail_asks where user_id={m}")=='1')
# Settings: she adds her country and state; afterwards she cannot change them
sql(f"update users set password_hash=(select password_hash from (select password_hash from users where email='leader@test.local') x), state_region='' where id={m}")
sm=login('seed5@test.local','leader-pass-1'); lg=sql(f"select id from org_units where parent_id={ng} and name='Lagos'")
r=sm.post(B+'/me',json={'first_name':'','last_name':'','phone':'','lga':'Ikeja','community':'Allen','country_id':int(ng),'state_id':int(lg)}); u=r.json().get('user',{})
chk('country and state saved when missing', r.ok and u.get('country_code')=='NG' and u.get('state_region')=='Lagos', u)
gh=sql("select id from org_units where level='country' and country_code='GH'")
r=sm.post(B+'/me',json={'lga':'Ikeja','community':'Allen','country_id':int(gh or 0)}); chk('not changed once set', r.json()['user']['country_code']=='NG')
# Monday country report to the Country Representative
cr=sql("select s.user_id from seats s join org_units o on o.id=s.org_unit_id where s.ends_at is null and s.position_code='country_rep' and o.country_code='NG' limit 1")
if not cr:
  r=hq.post(B+'/hq/leaders/add',json={'first_name':'Country','last_name':'Rep','email':'country.rep@wdos-mail.org','phone':'','network':'WGMN','position':'country_rep','org_unit_id':int(ng)}); cr=str(r.json()['id'])
sql("update users set email='country.rep@wdos-mail.org' where id=%s and email not like '%%@%%.invalid'"%cr)
sql(f"delete from mail_outbox; delete from app_settings where k like 'country_week_%'; delete from notifications where user_id={cr} and title like 'Your week%'")
cron('2026-11-24 08:30'); chk('not on a Tuesday', sql(f"select count(*) from notifications where user_id={cr} and title like 'Your week in Nigeria%'")=='0')
cron('2026-11-23 08:30'); n=sql(f"select concat(title,' | ',body) from notifications where user_id={cr} and title like 'Your week in Nigeria%' order by id desc limit 1")
chk('Monday: the Country Representative gets her week', 'leaders signed in this week' in n and 'tasks overdue' in n, n)
em=sql(f"select count(*) from mail_outbox where to_email=(select email from users where id={cr}) and subject like 'WDOS: Your week in Nigeria%'"); chk('and by email', em=='1' or '@' not in sql(f"select email from users where id={cr}"), em)
# placement by area
for i in range(12): sql(f"insert into users (email,first_name,last_name,network,country_code,state_region,lga,community,role,status,signup_method,participant_class,created_at) values ('wait{i}@example.org','Wait','{i}','WNNN','NG','Kaduna','Zaria','Testcluster','member','active','email','member',utc_timestamp())")
pg=hq.get(B+'/hq/placement').json(); g=[x for x in pg['groups'] if x['community']=='Testcluster']
chk('the waiting area is listed with its reason', g and g[0]['n']==12 and g[0]['reason']=='no_chapter', g)
sql("insert into policy_rules (rule_key,rule_value,effective_from,created_at) values ('chapter_min_active','10',utc_date(),utc_timestamp())") if sql("show tables like 'policy_rules'") else None
pg=hq.get(B+'/hq/placement').json(); g=[x for x in pg['groups'] if x['community']=='Testcluster']
r=hq.post(B+'/hq/placement/open',json={'network':'WNNN','country':'NG','state':'Kaduna','lga':'Zaria','community':'Testcluster'}); chk('HQ opens a chapter and places them', r.ok and r.json()['placed']==12, r.text)
chk('nobody waits there now', not any(x['community']=='Testcluster' for x in hq.get(B+'/hq/placement').json()['groups']))
chk('members told', int(sql("select count(*) from notifications n join users u on u.id=n.user_id where u.email like 'wait%@example.org' and n.title='You have been placed in a chapter'"))==12)
chk('a leader cannot open placement', ld.get(B+'/hq/placement').status_code in (401,403))
sy={i['key']:i for i in hq.get(B+'/hq/system').json()['items']}; chk('system health links to the new pages', sy['no_chapter']['href']=='#/hq/placement' and sy['never_signed']['href']=='#/hq/drive')
# a database that also lists old migrations no longer in the folder still runs the new ones
sql("insert ignore into migrations (name,ran_at) values ('000_old_a.sql',utc_timestamp()),('000_old_b.sql',utc_timestamp()),('000_old_c.sql',utc_timestamp()); delete from migrations where name='045_drive.sql'; drop table signin_packs")
hq.get(B+'/config'); chk('a missing migration runs even when the list is longer than the folder', sql("show tables like 'signin_packs'")=='signin_packs' and sql("select count(*) from migrations where name='045_drive.sql'")=='1')
print(f'checks {ok+bad} FAILURES {bad}')
