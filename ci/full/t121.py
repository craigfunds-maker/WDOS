# v109.25: logins for many leaders at once (PDF made in the browser).
import requests, subprocess, json
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s if r.ok else None
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
# give the Nigerian seat holders real-looking emails (test addresses ending .local are not sign-in addresses)
sql("update users u set u.email=concat('l',u.id,'@wdos-mail.org') where exists (select 1 from seats s join org_units o on o.id=s.org_unit_id where s.user_id=u.id and s.ends_at is null and o.country_code='NG') and u.email like '%.local' and u.email<>'leader@test.local'")
r=hq.get(B+'/hq/leader-logins/bulk?country=NG&levels=country,state_region'); d=r.json()
chk('list opens', r.ok, r.text)
chk('Nigeria only', all(p['country']=='NG' for p in d['people']) and len(d['people'])>=3, d['people'][:2])
chk('country and state levels only', {p['level'] for p in d['people']}<={'country','state_region'})
chk('positions given', all(p['position'] for p in d['people']))
chk('no HQ staff in the list', all(p['role'] not in ('hq_admin','super_admin') for p in d['people']))
w=hq.get(B+'/hq/leader-logins/bulk?country=NG&network=WNNN&levels=country,state_region').json()['people']
chk('network filter', w and all(p['network']=='WNNN' for p in w))
chk('a .local address cannot be given a login', any(not p['can_sign_in'] for p in d['people'] if p['signs_in_with'].endswith('.local')) or not any(p['signs_in_with'].endswith('.local') for p in d['people']))
chk('leaders cannot use it', ld.get(B+'/hq/leader-logins/bulk').status_code==403 and ld.post(B+'/hq/leader-logins/bulk',json={'ids':[1],'confirm':True}).status_code==403)
ids=[p['id'] for p in d['people'] if p['can_sign_in']][:4]
chk('confirmation needed', hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','levels':['country','state_region'],'ids':ids}).status_code==422)
out=sql("select u.id from users u where u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) and u.role='member' limit 1")
chk('someone outside the list is refused', hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','levels':['country','state_region'],'ids':ids+[int(out)],'confirm':True}).status_code==422)
chk('nobody\'s password changed by a refused batch', sql(f"select count(*) from users where id={out} and must_change_password=1")=='0')
# a leader signed in now is signed out by the batch
first=[p for p in d['people'] if p['id']==ids[0]][0]
sql(f"update users set password_hash=(select password_hash from (select password_hash from users where email='leader@test.local') x) where id={ids[0]}")
old=login(first['signs_in_with'],'leader-pass-1'); chk('she can sign in before', old is not None)
r=hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','levels':['country','state_region'],'ids':ids,'confirm':True}); x=r.json()
chk('logins created', r.ok and len(x['issued'])==len(ids), r.text)
chk('each has name, position, sign-in and password', all(i['name'] and i['position'] and i['sign_in_with'] and len(i['temporary_password'])>=10 for i in x['issued']))
chk('passwords differ', len({i['temporary_password'] for i in x['issued']})==len(ids))
chk('not cached', r.headers.get('Cache-Control','').startswith('no-store'))
chk('her old session ended', old.get(B+'/me').json().get('user') is None)
chk('her old password no longer works', login(first['signs_in_with'],'leader-pass-1') is None)
s=login(x['issued'][0]['sign_in_with'], x['issued'][0]['temporary_password']); chk('the new temporary password works', s is not None)
chk('she must choose her own', s.get(B+'/me').json()['user'].get('must_change_password') in (True,1), s.get(B+'/me').json()['user'])
chk('only hashes kept', sql(f"select count(*) from users where id in ({','.join(map(str,ids))}) and password_hash like '$2y$%'")==str(len(ids)))
chk('not stored readable anywhere', sql(f"select count(*) from activity_log where detail like '%{x['issued'][1]['temporary_password']}%'")=='0')
chk('each is told', int(sql(f"select count(*) from notifications where user_id in ({','.join(map(str,ids))}) and title='WODDI HQ gave you a new login'"))==len(ids))
chk('recorded for HQ', sql("select count(*) from activity_log where action='logins_issued'")=='1' and int(sql("select count(*) from activity_log where action='password_reset' and detail='Login issued in a batch'"))==len(ids))
chk('more than 300 refused', hq.post(B+'/hq/leader-logins/bulk',json={'country':'','levels':['country'],'ids':list(range(1,302)),'confirm':True}).status_code==422)
# an HQ login without the members area cannot use it
sql("update users set hq_features='reports' where email='hq@test.local' and role='hq_admin'")
if sql("select role from users where email='hq@test.local'")=='hq_admin':
  h2=login('hq@test.local','hq-password-1'); chk('HQ without the members area is refused', h2.get(B+'/hq/leader-logins/bulk?levels=country').status_code==403)
  sql("update users set hq_features=NULL where email='hq@test.local'")
# envoys
e=sql("select u.id from users u where u.role='member' and u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by id desc limit 1")
sql(f"update users set role='envoy', email='envoy{e}@wdos-mail.org', network='WGMN' where id={e}; insert into envoys (user_id,link_id,country_code,status,created_by,created_at) values ({e},0,'NG','active',1,utc_timestamp())")
if e:
  r=hq.get(B+'/hq/leader-logins/bulk?levels=envoy&country=NG&network=WGMN').json(); chk('envoys listed', any(p['id']==int(e) and p['level']=='envoy' and p['can_sign_in'] for p in r['people']), r)
  chk('an envoy gets a login', hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','network':'WGMN','levels':['envoy'],'ids':[int(e)],'confirm':True}).json()['issued'][0]['position']=='WODDI Envoy')
print('checks',ok+bad,'FAILURES',bad)
