# v109.33: HQ adds a volunteer leader not yet in WDOS (account + seat), then issues her login. Synthetic data only.
import requests, subprocess
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s if r.ok else r
hq=login('hq@test.local','hq-password-1')
ng=[c for c in hq.get(B+'/geo/countries').json()['countries'] if c['code']=='NG'][0]
st=[u for u in hq.get(B+f"/geo/children/{ng['id']}").json()['units'] if u['name'].startswith('Akwa')]
chk('the state is in the structure', len(st)==1, st); sid=st[0]['id']
base={'first_name':'Testa','last_name':'Coordina','email':'testa.coord@example.org','phone':'+2348000000101','network':'WGMN','position':'state_coordinator','org_unit_id':sid}
r=hq.post(B+'/hq/leaders/add',json=base); j=r.json(); chk('state coordinator added', r.ok and j['title'].startswith('State / Regional Coordinator, Akwa'), r.text)
a=j.get('id')
chk('phone kept in local form', sql(f"select phone from users where id={a}")=='08000000101')
chk('no password made', sql(f"select coalesce(password_hash,'none') from users where id={a}")=='none')
chk('seat open, pending her acceptance', sql(f"select concat(position_code,'|',network,'|',acceptance) from seats where user_id={a} and ends_at is null")=='state_coordinator|WGMN|pending')
r=hq.post(B+'/hq/leaders/add',json={**base,'first_name':'Testb','last_name':'Assista','email':'testb.asst@example.org','phone':'08000000102','position':'assistant_state_coordinator'}); b=r.json().get('id')
chk('assistant added', r.ok and 'Assistant State' in r.json()['title'], r.text)
r=hq.post(B+'/hq/leaders/add',json={**base,'phone':''}); chk('same email refused', r.status_code==409, r.text)
r=hq.post(B+'/hq/leaders/add',json={**base,'email':'other@example.org','phone':'2348000000101'}); chk('same phone (other form) refused', r.status_code==409 and 'phone' in r.text, r.text)
r=hq.post(B+'/hq/leaders/add',json={**base,'email':'c3@example.org','phone':'','first_name':'Testc'}); chk('second holder is added, HQ told who else holds it', r.ok and r.json()['also_held_by']==['Testa Coordina'], r.text)
r=hq.post(B+'/hq/leaders/add',json={**base,'email':'c4@example.org','phone':'','org_unit_id':ng['id']}); chk('state seat needs a state', r.status_code==422)
r=hq.post(B+'/hq/leaders/add',json={**base,'email':'c5@example.org','phone':'','position':'country_rep'}); chk('country seat needs the country', r.status_code==422)
r=hq.post(B+'/hq/leaders/add',json={**base,'email':'c6@example.org','phone':'','position':'district_coordinator'}); chk('LGA seat needs its area', r.status_code==422)
L=login('leader@test.local','leader-pass-1'); r=L.post(B+'/hq/leaders/add',json={**base,'email':'c7@example.org','phone':''}); chk('a leader cannot add leaders', r.status_code in (401,403), r.status_code)
lst=hq.get(B+'/hq/leader-logins/bulk?country=NG&network=WGMN&levels=state_region').json()['people']
chk('both appear for logins', {a,b} <= {p['id'] for p in lst})
r=hq.post(B+'/hq/leader-logins/bulk',json={'country':'NG','network':'WGMN','levels':['state_region'],'ids':[a,b],'confirm':True}); iss=r.json()['issued']
chk('logins issued for the two only', r.ok and len(iss)==2 and all(x['position'] for x in iss), r.text)
pw={x['sign_in_with']:x['temporary_password'] for x in iss}
s=login('testa.coord@example.org',pw['testa.coord@example.org']); chk('she signs in with email', not isinstance(s,requests.Response), getattr(s,'text',''))
s2=login('08000000102',pw.get('testb.asst@example.org')); chk('assistant signs in with her phone too', not isinstance(s2,requests.Response), getattr(s2,'text',''))
me=s.get(B+'/me').json()['user']; chk('dashboard knows her seat', 'Akwa' in str(me), {k:me.get(k) for k in ('seat','title','designation')})
chk('HQ activity logged', sql(f"select count(*) from activity_log where action='leader_added' and subject_id={a}")=='1')
print(f'checks {ok+bad} FAILURES {bad}')
