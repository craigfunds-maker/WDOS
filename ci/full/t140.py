# v109.51: logins for a whole network in one click. Every seated leader of one network, every country, every level; only people with no
# login get a password; a password a leader uses is never replaced; unused logins are replaced only on request; the PDF is arranged by country.
import requests, subprocess, json, time, os
X=str(int(time.time()))[-6:]
B='http://localhost:8080/api'
def S():
    s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
SQL=os.environ.get('WDOS_SQL','mariadb --socket=/tmp/mdb.sock -uroot wdos').split()
def sql(q): return subprocess.run(SQL+['-N','-e',q],capture_output=True,text=True).stdout.strip()
ROOT=os.environ.get('WDOS_ROOT','/home/claude/wdos-php')
bad=0; cnt=0
def chk(n,c,e=''):
    global bad,cnt; cnt+=1
    if not c: bad+=1; print('FAIL',n,str(e)[:500])
def login(e,p):
    s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s,r
sql("delete from login_attempts")
hq,_=login('hq@test.local','hq-password-1'); ld,_=login('leader@test.local','leader-pass-1')
GH=sql("select id from org_units where level='country' and country_code='GH'"); KE=sql("select id from org_units where level='country' and country_code='KE'")
ST=sql("select o.id from org_units o join org_units c on c.id=o.parent_id where o.level='state_region' and c.country_code='NG' order by o.name limit 1")
def add(tag,net,pos,unit,area='',email=None):
    r=hq.post(B+'/hq/leaders/add',json={'first_name':'Test'+tag,'last_name':'Leader'+X,'email':email or f'{tag.lower()}.{X}@wdos-mail.org','network':net,'position':pos,'org_unit_id':int(unit),'area_label':area})
    assert r.ok,(tag,r.text); return r.json()['id']
A=add('Ama','WGMN','country_rep',GH); Bk=add('Bola','WGMN','state_coordinator',ST); C=add('Chi','WGMN','district_coordinator',ST,'Ikeja')
D=add('Dupe','WGMN','cluster_coordinator',ST,'Ward 4'); E=add('Esi','WNNN','state_coordinator',ST); F=add('Fola','WGMN','chapter_lead',ST,'Chapter 9',email=f'fola.{X}@school.test')
K=add('Kui','WGMN','deputy_country_rep',KE)
hashed=lambda p: subprocess.run(['php','-r',f'echo password_hash("{p}", PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout
sql(f"update users set password_hash='{hashed('her-own-pass-1')}', must_change_password=0, last_seen_at=utc_timestamp() where id={C}")
sql(f"update users set password_hash='{hashed('old-temp-pass-1')}', must_change_password=1, last_seen_at=NULL where id={D}")
# ---- the list
r=hq.get(B+'/hq/leader-logins/network',params={'network':'WGMN'}); chk('list loads', r.ok, r.text); d=r.json()
by={p['id']:p for p in d['people']}
chk('every level and country of the network is listed', all(i in by for i in (A,Bk,C,D,F,K)), list(by)[:10])
chk('the other network is left out', E not in by)
chk('WNNN seats of the seed are left out', not any(p['position'].startswith('Assistant State / Regional Lead') for p in d['people']))
chk('no password yet: needs a login', by[A]['cat']=='new' and by[Bk]['cat']=='new', by[A])
chk('her own password: already signed in', by[C]['cat']=='active', by[C])
chk('temporary password never used: pending', by[D]['cat']=='pending', by[D])
chk('no usable email or phone: cannot', by[F]['cat']=='cannot', by[F])
chk('designation is title and area', by[C]['position']=='LGA / District Coordinator, Ikeja' and by[A]['position'].startswith('Country Representative'), by[C]['position'])
chk('country names given', by[A]['country_name']=='Ghana' and by[K]['country_name']=='Kenya' and by[Bk]['country_name']=='Nigeria', by[A])
cl={c['cc']:c for c in d['countries']}; chk('country list for ticking, with counts', all(k in cl for k in ('GH','KE','NG')) and cl['GH']['count']>=1 and cl['GH']['name']=='Ghana', d['countries'])
chk('every leadership level offered', set(d['levels'])=={'country','state_region','lga','community_cluster','chapter','envoy'}, d['levels'])
chk('each person carries her seat network', by[A]['network']=='WGMN', by[A])
names=[p['country_name'] for p in d['people']]; chk('sorted by country', names==sorted(names), names[:12])
ng=[p for p in d['people'] if p['country_name']=='Nigeria']; chk('within a country, highest level first', [p['rank'] for p in ng]==sorted(p['rank'] for p in ng))
chk('no password or hash in the list', 'password_hash' not in r.text and '$2y$' not in r.text)
chk('network must be chosen', hq.get(B+'/hq/leader-logins/network').status_code==422)
chk('a leader cannot list it', ld.get(B+'/hq/leader-logins/network',params={'network':'WGMN'}).status_code in (401,403))
chk('signed out cannot list it', S().get(B+'/hq/leader-logins/network',params={'network':'WGMN'}).status_code in (401,403))
ab=hq.get(B+'/hq/leader-logins/network',params={'network':'ALL'}).json(); ids={p['id']:p for p in ab['people']}
chk('both networks: WGMN and WNNN together', A in ids and E in ids and ids[E]['network']=='WNNN', len(ab['people']))
chk('both networks: no one listed twice', len(ids)==len(ab['people']))
chk('unknown network refused', hq.get(B+'/hq/leader-logins/network',params={'network':'XYZ'}).status_code==422)
# ---- issuing
P=lambda **k: hq.post(B+'/hq/leader-logins/network',json={'network':'WGMN',**k})
r=P(ids=[A,Bk]); chk('logins issued', r.ok and len(r.json()['issued'])==2, r.text)
pw={x['id']:x['temporary_password'] for x in r.json().get('issued',[])}
chk('must change at first sign-in', sql(f"select must_change_password from users where id={A}")=='1')
s,lr=login(f'ama.{X}@wdos-mail.org',pw.get(A,'x')); chk('the temporary password signs her in', lr.ok, lr.text)
chk('she was told', int(sql(f"select count(*) from notifications where user_id={A} and title='WODDI HQ gave you a WDOS login'"))==1)
chk('logged', int(sql(f"select count(*) from activity_log where action='logins_issued' and detail like '%WGMN · all countries%'"))>=1, sql("select detail from activity_log where action='logins_issued'"))
r=P(ids=[K],countries=['KE']); chk('a chosen country is logged', r.ok and int(sql("select count(*) from activity_log where action='logins_issued' and detail like '%WGMN · KE%'"))==1, r.text)
r=P(ids=[C]); chk('a password she uses is never replaced', r.status_code==422 and r.json().get('error')=='in_use', r.text)
_,lr=login(f'chi.{X}@wdos-mail.org','her-own-pass-1'); chk('her own password still works', lr.ok, lr.text)
r=P(ids=[A]); chk('after first sign-in she counts as signed in', r.status_code==422 and r.json().get('error')=='in_use', r.text)
r=P(ids=[D]); chk('an unused login is kept by default', r.status_code==422 and r.json().get('error')=='pending', r.text)
r=P(ids=[D],reissue_unused=True); chk('replacing needs confirmation', r.status_code==422 and r.json().get('error')=='confirm', r.text)
r=P(ids=[D],reissue_unused=True,confirm=True); chk('replaced when asked and confirmed', r.ok and len(r.json()['issued'])==1, r.text)
_,lr=login(f'dupe.{X}@wdos-mail.org','old-temp-pass-1'); chk('the old unused password stops working', lr.status_code in (401,422), lr.text)
r=P(ids=[F]); chk('no email or phone: refused', r.status_code==422 and r.json().get('error')=='no_contact', r.text)
r=P(ids=[E]); chk('a leader of the other network: refused', r.status_code==422, r.text)
r=hq.post(B+'/hq/leader-logins/network',json={'network':'WNNN','ids':[E]}); chk('she is issued from her own network', r.ok and len(r.json()['issued'])==1, r.text)
hqid=sql("select id from users where email='hq@test.local'")
r=P(ids=[int(hqid)]); chk('HQ staff are never touched', r.status_code==422, r.text)
r=P(ids=list(range(1,102))); chk('at most 100 in one batch', r.status_code==422, r.text)
r=P(ids=[]); chk('empty batch refused', r.status_code==422)
G=add('Gifty','WGMN','deputy_country_rep',GH)
r=ld.post(B+'/hq/leader-logins/network',json={'network':'WGMN','ids':[G]}); chk('a leader cannot issue', r.status_code in (401,403))
chk('her login untouched by the refused call', sql(f"select password_hash is null from users where id={G}")=='1')
r=hq.post(B+'/hq/leader-logins/network',json={'network':'ALL','ids':[G]}); chk('both networks: issued', r.ok and len(r.json()['issued'])==1, r.text)
# ---- the PDF, built by the page's own code from the list
d=hq.get(B+'/hq/leader-logins/network',params={'network':'WGMN'}).json()
people=[{'country_name':p['country_name'],'level':p['level'],'name':p['name'],'position':p['position'],'sign_in_with':p['sign_in_with'],'password':'Zx9TestPw2' if p['id']==K else '','status':p['cat']} for p in d['people']]
open('/tmp/t140.json','w').write(json.dumps(people))
js=f"""import {{ networkLoginsPdf }} from '{ROOT}/js/pdfmini.js'; import {{ readFileSync, writeFileSync }} from 'fs';
const b = networkLoginsPdf({{ network: 'WGMN', title: 'WDOS logins: WGMN volunteer leaders', made: 'test', host: 'woddicrm.org', note: 'Confidential test.', people: JSON.parse(readFileSync('/tmp/t140.json','utf8')) }});
writeFileSync('/tmp/t140.pdf', Buffer.from(await b.arrayBuffer()));"""
open('/tmp/t140.mjs','w').write(js); o=subprocess.run(['node','/tmp/t140.mjs'],capture_output=True,text=True); chk('PDF built', o.returncode==0, o.stderr)
txt=subprocess.run(['pdftotext','-layout','/tmp/t140.pdf','-'],capture_output=True,text=True).stdout
chk('cover and country table', 'WDOS logins: WGMN volunteer leaders' in txt and 'Leaders by country' in txt and 'All countries' in txt, txt[:300])
chk('each country has its own section', all(c in txt for c in ('Ghana','Kenya','Nigeria')))
chk('levels grouped', 'Country leadership' in txt and 'State or region' in txt and 'Chapter' in txt)
chk('new password printed', 'Zx9TestPw2' in txt)
chk('people who already sign in get no password, a note instead', 'Already signed in. Uses her' in txt)
chk('missing contact noted', 'Add an email or phone number' in txt)
chk('pages numbered with the total', 'Page 1 of' in txt and 'CONFIDENTIAL' in txt)
print('checks',cnt,'FAILURES',bad)
