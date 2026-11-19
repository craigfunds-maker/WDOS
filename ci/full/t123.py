# v109.29: state envoys from a register, signing in with a local phone number; sign-in details in bulk. Synthetic data only.
import requests, subprocess, json
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s if r.ok else r
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
taken=sql("select phone from users where phone<>'' and phone is not null and role='member' limit 1")
rows=[{'state':'Abia','name':'Prof. Testa Example-Nweke','phone':'08000000001'},
      {'state':'Adamawa','name':'Hajia Sample Person','phone':'+234 800 000 0002'},
      {'state':'Adamawa','name':'Second Adamawa','phone':'+2348000000003'},
      {'state':'FCT','name':'Mina Test','phone':'0800 000 0004'},
      {'state':'Imo','name':'Azuka','phone':'08000000005'},
      {'state':'Lagos','name':'Already Here','phone':taken},
      {'state':'Kogi','name':'No Phone','phone':''}]
chk('a leader cannot import', ld.post(B+'/hq/envoys/import',json={'rows':rows}).status_code==403)
r=hq.post(B+'/hq/envoys/import',json={'rows':rows,'country':'NG','network':'WGMN'}); d=r.json()
chk('import runs', r.ok, r.text)
chk('five added', len(d['added'])==5, d)
chk('the number already on WDOS is not added twice', any(s['name']=='Already Here' and 'already on WDOS' in s['why'] for s in d['skipped']), d['skipped'])
chk('no phone, not added', any(s['name']=='No Phone' for s in d['skipped']))
chk('numbers kept without +234', sorted(a['phone'] for a in d['added'])==['08000000001','08000000002','08000000003','08000000004','08000000005'], [a['phone'] for a in d['added']])
chk('two envoys in one state', len([a for a in d['added'] if a['state']=='Adamawa'])==2)
chk('FCT found', [a for a in d['added'] if a['name']=='Mina Test'][0]['state']=='Federal Capital Territory')
u=json.loads(sql("select json_object('f',first_name,'l',last_name,'r',role,'d',designation,'st',state_region,'cc',country_code,'n',network,'pw',password_hash is null,'mc',must_change_password,'e',email) from users where phone='08000000001'"))
chk('title kept with the name', u['f']=='Prof. Testa' and u['l']=='Example-Nweke', u)
chk('envoy, Nigeria, WGMN, state', u['r']=='envoy' and u['cc']=='NG' and u['n']=='WGMN' and u['st']=='Abia' and u['d']=='WODDI State Envoy, Abia', u)
chk('no password yet', u['pw']==1 and u['mc']==1)
chk('signs in with her phone', u['e']=='p08000000001@phone.wdos.invalid')
chk('one-word name', sql("select concat(first_name,'|',last_name) from users where phone='08000000005'")=='Azuka|')
chk('each has a referral link', sql("select count(*) from envoys e join referral_links l on l.id=e.link_id where e.user_id in (select id from users where phone like '0800000000%')")=='5')
r=login('08000000001','anything'); chk('before her details: a plain message', r.status_code==401 and 'not ready yet' in r.text, r.text)
chk('importing again adds nobody', len(hq.post(B+'/hq/envoys/import',json={'rows':rows[:2]}).json()['added'])==0)
# bulk sign-in details for envoys
lst=hq.get(B+'/hq/leader-logins/bulk?country=NG&network=WGMN&levels=envoy').json()['people']
mine=[p for p in lst if p['signs_in_with'].startswith('0800000000')]
chk('the envoys are listed for sign-in details', len(mine)==5, [p['signs_in_with'] for p in lst])
chk('signs in with the phone, shown', all(p['kind']=='Phone number' and p['can_sign_in'] for p in mine))
chk('position says state envoy', all(p['position'].startswith('WODDI State Envoy, ') for p in mine))
r=hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','network':'WGMN','levels':['envoy'],'ids':[p['id'] for p in mine],'confirm':True}); x=r.json()
chk('details made', r.ok and len(x['issued'])==5, r.text)
pw={i['sign_in_with']:i['temporary_password'] for i in x['issued']}
chk('PDF rows carry the local number', set(pw)=={'08000000001','08000000002','08000000003','08000000004','08000000005'})
s=login('08000000002',pw['08000000002']); chk('she signs in with 0800...', not isinstance(s,requests.Response))
s=login('+2348000000002',pw['08000000002']); chk('and with +234 800...', not isinstance(s,requests.Response))
s=login('2348000000003',pw['08000000003']); chk('and with 234800...', not isinstance(s,requests.Response))
s=login('0800 000 0004',pw['08000000004']); chk('and with spaces', not isinstance(s,requests.Response))
s5=login('08000000005',pw['08000000005']+' \n'); chk('a copied password with a space or line end works', not isinstance(s5,requests.Response))
chk('a wrong password still fails', isinstance(login('08000000005',pw['08000000005'][:-1]+'x'),requests.Response))
me=s.get(B+'/me').json()['user']; chk('she lands as an envoy who must choose a password', me['is_envoy'] and me.get('must_change_password') in (True,1), me)
chk('envoys page lists them', len([e for e in hq.get(B+'/hq/envoys').json().get('items',hq.get(B+'/hq/envoys').json().get('envoys',[])) if 'Testa' in json.dumps(e) or 'Mina' in json.dumps(e)])>=2)
print('checks',ok+bad,'FAILURES',bad)
