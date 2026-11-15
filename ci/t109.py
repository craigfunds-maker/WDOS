# v109 part 2: envoy self sign-up (open, review, closed), HQ approval, Google start for envoys, the call inside WDOS (join data, JaaS token),
# meeting notifications in the member's language.
import requests, subprocess, json, time, os
X=str(int(time.time()))[-6:]
B='http://localhost:8080/api'
def S():
    s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
SQL=os.environ.get('WDOS_SQL','mariadb --socket=/tmp/mdb.sock -uroot wdos').split()
def sql(q): return subprocess.run(SQL+['-N','-e',q],capture_output=True,text=True).stdout.strip()
def php(code): return subprocess.run(['php','-r',code],capture_output=True,text=True,cwd=os.environ.get('WDOS_ROOT','/home/claude/wdos-php'))
bad=0; cnt=0
def chk(n,c,e=''):
    global bad,cnt; cnt+=1
    if not c: bad+=1; print('FAIL',n,str(e)[:500])
def login(e,p):
    s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
sql("delete from login_attempts"); sql("delete from rate_limits") if sql("show tables like 'rate_limits'") else None
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
cc=sql("select country_code from org_units where level='country' and country_code='NG' limit 1") or sql("select country_code from org_units where level='country' limit 1")
form=lambda **k: {'first_name':'Halima','last_name':'Sule','email':f'halima.{X}@wdos-mail.org','phone':'+2348031112222','country_code':cc,'state_region':'Kaduna','city':'Zaria',
  'organisation':'Zaria market women','reach_plan':'My market association and two church fellowships in Zaria.','heard_from':'A WGMN coordinator','password':'envoy-pass-1','consent':True,**k}
# ---- open
a=S()
chk('join info open', a.get(B+'/envoy/join-info').json()['mode']=='open')
r=a.post(B+'/envoy/join',json=form(consent=False)); chk('consent required', r.status_code==422 and r.json().get('error')=='consent', r.text)
r=a.post(B+'/envoy/join',json=form(reach_plan='x')); chk('where she will share is required', r.status_code==422, r.text)
r=a.post(B+'/envoy/join',json=form(country_code='ZZ')); chk('unknown country refused', r.status_code==422, r.text)
r=a.post(B+'/envoy/join',json=form(password='short')); chk('short password refused', r.status_code==422, r.text)
r=a.post(B+'/envoy/join',json=form()); chk('envoy signed up', r.ok and r.json()['live'] is True and r.json()['user']['is_envoy'], r.text)
eid=int(sql(f"select id from users where email='halima.{X}@wdos-mail.org'"))
h=a.get(B+'/envoy/home'); chk('she is signed in and sees her dashboard', h.ok and h.json()['status']=='active' and '/#/r/' in h.json()['link'], h.text)
chk('her link is on', sql(f"select l.active from envoys e join referral_links l on l.id=e.link_id where e.user_id={eid}")=='1')
chk('details kept', sql(f"select concat(source,'|',state_region,'|',city) from envoys where user_id={eid}")=='self|Kaduna|Zaria')
chk('HQ notified', int(sql(f"select count(*) from notifications where title like 'New WODDI Envoy: Halima Sule%' and created_at>=utc_timestamp()-interval 5 minute"))>=1)
chk('HQ inbox email queued', int(sql("select count(*) from mail_outbox where subject like 'WDOS: new WODDI Envoy Halima%'"))>=1)
r=S().post(B+'/envoy/join',json=form(phone='+2348039990000')); chk('same email refused', r.status_code==409, r.text)
r=a.post(B+'/envoy/join',json=form(email=f'other.{X}@wdos-mail.org')); chk('signed-in person cannot sign up again', r.status_code==409, r.text)
chk('envoy cannot open the member list', a.get(B+'/hq/members').status_code in (401,403))
chk('envoy cannot open leader dashboard data', a.get(B+'/leader/dashboard').status_code in (401,403))
code=a.get(B+'/envoy/home').json()['code']
ng=sql("select id from org_units where level='country' and country_code='NG'"); kd=sql(f"select id from org_units where parent_id={ng} limit 1")
inv=S(); r=inv.post(B+'/auth/register',json={'network':'WGMN','first_name':'Asabe','last_name':'Invitee','dob':'1985-02-02','phone':'+2348039'+X,'country_id':int(ng),'state_id':int(kd),'lga':'Zaria','community':'Samaru','email':f'asabe.{X}@wdos-mail.org','password':'member-pass-1','consent':True,'referral':{'code':code,'position':'chapter_lead'}})
act=inv.get(B+'/activation').json().get('activation') if r.ok else None
chk("an envoy's invitee starts the 3-day activation", r.ok and act and act.get('route')=='ref3', r.text[:200])
ppl=a.get(B+'/envoy/home').json()['people']; chk('the envoy sees her and her 3 days', any(p['first_name']=='Asabe' and p['activation'] and p['activation']['days_total']==3 for p in ppl), ppl[:1])
d=hq.get(B+f'/hq/envoys/{eid}').json()['envoy']; chk('HQ sees her sign-up details', d['source']=='self' and d['reach_plan'].startswith('My market') and d['city']=='Zaria', d)
L=[e for e in hq.get(B+'/hq/envoys').json()['envoys'] if e['id']==eid]; chk('HQ list shows her', L and L[0]['source']=='self' and L[0]['status']=='active', L)
# ---- no approval step and no closing: every sign-up is live, HQ can still pause
chk('sign-up rule retired', all(r['key']!='envoy_signup' for r in hq.get(B+'/policy').json()['rules']))
chk('join info always open', S().get(B+'/envoy/join-info').json()['mode']=='open')
r=hq.post(B+f'/hq/envoys/{eid}',json={'active':False}); chk('HQ can pause an envoy', r.ok and sql(f"select concat(e.status,'|',l.active) from envoys e join referral_links l on l.id=e.link_id where e.user_id={eid}")=='suspended|0', r.text)
r=hq.post(B+f'/hq/envoys/{eid}',json={'active':True}); chk('HQ can switch her back on', r.ok and sql(f"select l.active from envoys e join referral_links l on l.id=e.link_id where e.user_id={eid}")=='1', r.text)
chk('leader cannot pause envoys', ld.post(B+f'/hq/envoys/{eid}',json={'active':False}).status_code in (401,403))
# ---- Google start for envoys (keys not set here: sent back with a clear reason, never a crash)
r=S().get(B+'/auth/google/start?as=envoy',allow_redirects=False); chk('google start answers', r.status_code in (302,303) and 'google_off' in r.headers.get('Location',''), (r.status_code, r.headers.get('Location')))
chk('google keys reported off', S().get(B+'/envoy/join-info').json()['google'] is False)
# ---- the call inside WDOS
mid=sql("select id from meeting_requests where status='approved' and starts_at<=utc_timestamp() and request_no='MR-202611-0099'")
if not mid:
    sql("insert into meeting_requests (request_no,requester_id,network,country_code,region,leader_name,title,reason,attendees_text,attendee_ids,graphics,other_needs,expected_count,starts_at,duration_min,tz,status,room,hq_note,created_at,updated_at) select 'MR-202611-0099',id,'WGMN','NG','Lagos','Ngunan Addingi','Test call now','Plan the outreach','Coordinators','','','',5,utc_timestamp(),60,'Africa/Lagos','approved','WODDI-TEST-ROOM-123','',utc_timestamp(),utc_timestamp() from users where email='leader@test.local'")
    mid=sql("select id from meeting_requests where request_no='MR-202611-0099'")
owner=sql(f"select u.email from meeting_requests m join users u on u.id=m.requester_id where m.id={mid}")
s=ld if owner=='leader@test.local' else hq
r=s.post(B+f'/meet-requests/{mid}/join',json={}); j=r.json() if r.ok else {}
chk('join gives what the page needs to embed', r.ok and j.get('room')=='WODDI-TEST-ROOM-123' and j.get('domain') and j.get('name') and j.get('engine') in ('public','self','jaas','community'), r.text)
chk('default server is the tested community one, no limit', j.get('domain')=='meet.vpsfree.cz' and j.get('engine')=='community' and not j.get('limit_minutes'), j)
r=hq.post(B+'/policy',json={'key':'video_call_server','value':'meet.guifi.net','note':'test'}); chk('HQ can switch the call server', r.ok, r.text)
j2=s.post(B+f'/meet-requests/{mid}/join',json={}).json(); chk('the new rule applies to a meeting with no server fixed yet', j2.get('domain')=='meet.guifi.net', j2)
r=hq.post(B+'/policy',json={'key':'video_call_server','value':'evil.example.com','note':'test'}); chk('unknown server refused', r.status_code==422, r.status_code)
hq.post(B+'/policy',json={'key':'video_call_server','value':'meet.vpsfree.cz','note':'test'})
out=S().post(B+f'/meet-requests/{mid}/join',json={}); chk('signed-out cannot join', out.status_code==401)
# JaaS token: signed with the key, verifies with its public half, carries room, name and moderator flag
p=php(r'''
$k=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]); openssl_pkey_export($k,$pem); $pub=openssl_pkey_get_details($k)['key'];
putenv('JAAS_APP_ID=vpaas-magic-cookie-test'); putenv('JAAS_API_KEY_ID=vpaas-magic-cookie-test/abc123'); putenv('JAAS_PRIVATE_KEY='.str_replace("\n",'\n',$pem));
function cfg($k,$d=null){ $v=getenv($k); return $v===false?$d:$v; }
require 'lib/v109_fns.php';
$j=jaas_jwt(['id'=>7,'first_name'=>'Ngozi','last_name'=>'Leader','email'=>'n@x.org'],'WODDI-ROOM',true); [$h,$b,$s]=explode('.',$j);
$d=fn($x)=>base64_decode(strtr($x,'-_','+/'));
echo json_encode(['ok'=>openssl_verify("$h.$b",$d($s),$pub,OPENSSL_ALGO_SHA256),'head'=>json_decode($d($h),true),'body'=>json_decode($d($b),true),'engine'=>call_engine()]);
''')
try: jj=json.loads(p.stdout[p.stdout.index('{'):])
except Exception: jj={}
chk('JaaS token signature verifies', jj.get('ok')==1, p.stdout[-400:]+p.stderr[-400:])
chk('JaaS token header', jj.get('head',{}).get('kid')=='vpaas-magic-cookie-test/abc123' and jj['head'].get('alg')=='RS256', jj.get('head'))
bd=jj.get('body',{}); chk('JaaS token claims', bd.get('sub')=='vpaas-magic-cookie-test' and bd.get('room')=='WODDI-ROOM' and bd.get('aud')=='jitsi' and bd['context']['user']['moderator']=='true' and bd['context']['user']['name']=='Ngozi Leader' and bd['exp']>bd['nbf'], bd)
chk('engine switches to JaaS when keys are set', jj.get('engine')=='jaas', jj.get('engine'))
# ---- meeting notification in the member's own language
p=php(r'''function rows(){return [];} require 'lib/notify_i18n.php'; echo json_encode([note_in('fr','Video meeting approved: Lagos','15 Nov 2026, 07:25 GMT. The Join button is ready now.'), note_in('ar','Minutes: Lagos','18 attended.'), note_in('sw','You are invited: Plan','15 Nov 2026, 07:25 GMT')], JSON_UNESCAPED_UNICODE);''')
nn=json.loads(p.stdout); chk('approved notice in French', nn[0][0]=='Réunion vidéo approuvée : Lagos' and 'Rejoindre' in nn[0][1], nn[0])
chk('minutes notice in Arabic', nn[1][0].startswith('محضر') and '18' in nn[1][1], nn[1]); chk('invite in Swahili', nn[2][0]=='Umealikwa: Plan', nn[2])
chk('template list and its descriptions line up', php(r'''function rows(){return [];} require 'lib/notify_i18n.php'; echo count(NOTE_TEMPLATES)===count(NOTE_META)?'same':'differ';''').stdout=='same')
print('checks',cnt,'FAILURES',bad)
