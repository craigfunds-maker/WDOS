import requests, json, sys, subprocess, datetime
B='http://localhost:8080/api'; H={'X-WDOS':'1'}
def S(email,pw):
    s=requests.Session(); s.headers.update(H); r=s.post(B+'/auth/login',json={'email':email,'password':pw}); assert r.ok, r.text; return s
def sql(q): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot','wdos','-N','-e',q],capture_output=True,text=True).stdout.strip()
ok=0
def chk(name, cond, extra=''):
    global ok
    print(('PASS ' if cond else 'FAIL ')+name, '' if cond else extra); ok+= 0 if cond else 1
# a state coordinator with a login
pw=subprocess.run(['php','-r','echo password_hash("coord-pass-1", PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout
cid=sql("select s.user_id from seats s where s.position_code='assistant_state_coordinator' and s.network='WGMN' and s.ends_at is null limit 1")
sql(f"update users set email='coord@test.local', password_hash='{pw}' where id={cid}")
hq=S('hq@test.local','hq-password-1'); lead=S('leader@test.local','leader-pass-1'); co=S('coord@test.local','coord-pass-1')
me=lead.get(B+'/me').json()['user']; chk('me has todo', 'todo' in me and me['seat']['acceptance']=='', me)
# ---- tasks
ppl=lead.get(B+'/tasks/people').json()['people']; chk('people in area', any(str(p['id'])==cid for p in ppl), len(ppl))
r=lead.post(B+'/tasks',json={'title':'Submit state membership list','details':'By LGA','priority':'high','due_on':'2026-11-20','requires_approval':True,'assignees':[int(cid)],'checklist':['Collect lists','Check duplicates','']}); chk('create task', r.ok, r.text); tid=r.json()['id']
r=co.post(B+'/tasks',json={'title':'Bad scope','assignees':[me['id']]}); chk('cannot assign upward', r.status_code==403, r.text)
d=co.get(B+f'/tasks/{tid}').json(); chk('assignee sees task', d['rights']=={'mine':True,'manage':False} and len(d['items'])==2, d)
chk('start', co.post(B+f'/tasks/{tid}/status',json={'to':'in_progress'}).ok)
r=co.post(B+f'/tasks/{tid}/status',json={'to':'completed'}); chk('approval gate', r.status_code==403, r.text)
chk('submit', co.post(B+f'/tasks/{tid}/status',json={'to':'submitted'}).ok)
r=lead.post(B+f'/tasks/{tid}/status',json={'to':'completed'}); chk('checklist blocks completion', r.status_code==422, r.text)
chk('send back', lead.post(B+f'/tasks/{tid}/status',json={'to':'in_progress','note':'Tick the checklist'}).ok)
for i in d['items']: co.post(B+f'/tasks/{tid}/item/{i["id"]}',json={'done':True})
co.post(B+f'/tasks/{tid}/status',json={'to':'submitted'}); r=lead.post(B+f'/tasks/{tid}/status',json={'to':'completed'}); chk('approve completion', r.ok, r.text)
chk('comment', co.post(B+f'/tasks/{tid}/comment',json={'body':'Thank you'}).ok)
l=lead.get(B+'/tasks?scope=assigned&status=').json(); chk('assigned list', l['total']==1 and l['items'][0]['status']=='completed', l)
l=lead.get(B+'/tasks?scope=area&status=').json(); chk('area list', l['total']>=1)
chk('task notifications', int(sql(f"select count(*) from notifications where kind='task'"))>=5)
# ---- meetings
st=(datetime.datetime.utcnow()+datetime.timedelta(minutes=30)).strftime('%Y-%m-%dT%H:%M:00Z'); en=(datetime.datetime.utcnow()+datetime.timedelta(minutes=90)).strftime('%Y-%m-%dT%H:%M:00Z')
m=lead.get(B+'/meetings').json(); chk('audiences', list(m['audiences'].keys())==['leaders:WGMN','people:WGMN','members:WGMN'], m['audiences'])
r=lead.post(B+'/meetings',json={'title':'Nigeria WGMN monthly','mode':'virtual','location':'https://meet.example.org/abc','starts_at':st,'ends_at':en,'audience':'leaders:WGMN','agenda':'1. Reports'}); chk('schedule', r.ok and r.json()['invited']>0, r.text); mid=r.json()['id']
chk('invitee sees meeting', any(x['id']==mid for x in co.get(B+'/meetings').json()['items']))
d=lead.get(B+f'/meetings/{mid}').json(); chk('host sees people', d['host'] and d['invited']>0 and len(d['people'])>0, d['invited'])
r=lead.post(B+f'/meetings/{mid}/attendance',json={'marks':{cid:'present', str(d['people'][0]['id']):'absent'}}); chk('attendance', r.ok, r.text)
r=co.post(B+f'/meetings/{mid}/close',json={'minutes':'x'*20}); chk('non-host cannot close', r.status_code==403)
r=lead.post(B+f'/meetings/{mid}/close',json={'minutes':'Agreed to file reports by the 5th.','actions':[{'action':'File October report','owner_id':int(cid),'deadline':'2026-11-15'},{'action':'Share template','deadline':''}]}); chk('close with actions', r.ok and r.json()['tasks']==2, r.text)
d=co.get(B+f'/meetings/{mid}').json(); chk('minutes and tasks visible', d['meeting']['status']=='completed' and len(d['tasks'])==2 and d['my_mark'] in('present','absent'), d)
m2=hq.post(B+'/meetings',json={'title':'All leaders','mode':'in_person','location':'Abuja','starts_at':st,'ends_at':en,'audience':'leaders:ALL'}); chk('hq schedule', m2.ok, m2.text)
subprocess.run('. /tmp/env.sh; curl -s "localhost:8080/api/cron?key=test-admin-key" >/tmp/cron.json',shell=True); chk('meeting reminder', 'meeting_' in open('/tmp/cron.json').read(), open('/tmp/cron.json').read()[:300])
# ---- structure
s=lead.get(B+'/structure').json(); chk('structure leader', any(r['kind']=='vacant' for r in s['rows']) and any(r['kind']=='filled' for r in s['rows']) and s['networks']==['WGMN'], len(s['rows']))
s=hq.get(B+'/structure').json(); chk('structure hq', len(s['countries'])==81 and sum(1 for r in s['rows'] if r['kind']=='vacant' and r['level']=='country')==161, sum(1 for r in s['rows'] if r['kind']=='vacant' and r['level']=='country'))
# ---- appointment + onboarding
nid=sql("select id from users where network='WGMN' and country_code='GH' and id not in (select user_id from seats) limit 1"); sql(f"update users set email='newlead@test.local', password_hash='{pw}' where id={nid}")
r=hq.post(B+f'/hq/members/{nid}/seat',json={'position':'country_rep','org_unit_id':int(sql("select id from org_units where level='country' and country_code='GH'")),'network':'WGMN','status':'appointed'}); chk('hq appoints', r.ok, r.text)
nl=S('newlead@test.local','coord-pass-1'); u=nl.get(B+'/me').json()['user']; chk('todo appointment', u['todo']['appointment'] and u['todo']['onboarding']==4 and u['seat']['acceptance']=='pending', u['todo'])
chk('accept', nl.post(B+'/appointment',json={'accept':True}).ok)
ob=nl.get(B+'/onboarding').json(); chk('onboarding steps', len(ob['steps'])==4 and ob['starter'], ob)
r=nl.post(B+'/onboarding/leadership_manual',json={'seconds':30,'reached_end':True}); chk('order enforced', r.status_code==409, r.text)
r=nl.post(B+'/onboarding/code_of_conduct',json={'seconds':30,'reached_end':False}); chk('must reach end', r.status_code==422)
r=nl.post(B+'/onboarding/code_of_conduct',json={'seconds':3,'reached_end':True}); chk('min time', r.status_code==422)
for c in ['code_of_conduct','leadership_manual','volunteer_agreement']: assert nl.post(B+'/onboarding/'+c,json={'seconds':30,'reached_end':True}).ok
r=nl.post(B+'/onboarding/conflict_of_interest',json={'note':''}); chk('declaration needed', r.status_code==422)
r=nl.post(B+'/onboarding/conflict_of_interest',json={'note':'none'}); chk('onboarding done', r.ok and r.json()['left']==0, r.text)
chk('hq reads record', hq.get(B+f'/onboarding?user={nid}').json()['read_only'])
chk('leader cannot read other country', lead.get(B+f'/onboarding?user={nid}').status_code==403)
# ---- referrals + activation
rf=lead.get(B+'/referrals').json(); chk('member link auto', len(rf['links'])==1 and rf['links'][0]['kind']=='member', rf['links'])
lagos=int(sql("select id from org_units where name='Lagos'")); r=lead.post(B+'/referrals/link',json={'position':'state_secretary','org_unit_id':lagos,'label':'Lagos secretary'}); chk('leader link', r.ok, r.text); code=r.json()['link']['code']
r=lead.post(B+'/referrals/link',json={'position':'country_rep'}); chk('cannot link own rank', r.status_code==403)
pub=requests.get(B+f'/ref/{code}').json(); chk('public link info', pub['link']['role']=='State Secretary' and pub['link']['owner']=='Amara Bello', pub)
chk('bad link', requests.get(B+'/ref/ZZZZZZZZ').status_code==404)
def reg(email, ref, net='WGMN', dob='1985-03-03'):
    s=requests.Session(); s.headers.update(H); ng=int(sql("select id from org_units where level='country' and country_code='NG'"))
    r=s.post(B+'/auth/register',json={'network':net,'first_name':'Cand','last_name':email.split('@')[0],'dob':dob,'phone':'+2348012345678','country_id':ng,'state_id':lagos,'lga':'Ikeja','community':'Alausa','email':email,'password':'candidate-1','consent':True,'referral':ref}); assert r.ok, r.text; return s, r.json()['user']
# v109.46: new activations follow the Leadership Activation Framework (tested in t137); this suite keeps testing the earlier journey
_reg_fw=reg
def reg(*a, **k):
  out=_reg_fw(*a, **k); sql("update activations set scheme='v1' where scheme='fw'"); return out
c1,u1=reg('c1@test.local',{'code':code}); chk('candidate has activation', u1['todo']['activation'], u1['todo'])
c2,u2=reg('c2@test.local',{'position':'district_coordinator','category':'individual','name':'Mrs Ade'}); a2=c2.get(B+'/activation').json()['activation']; chk('form referral', a2['role_title']=='LGA / District Coordinator' and a2['unit']=='Ikeja, Lagos' and a2['referrer']=='Mrs Ade', a2 and (a2['role_title'],a2['unit']))
c3,u3=reg('c3@test.local',{'code':rf['links'][0]['code']}); chk('member link no activation', not u3['todo']['activation'] and sql("select count(*) from referrals where user_id=%d"%u3['id'])=='1')
c4,u4=reg('c4@test.local',None); chk('plain signup', not u4['todo']['activation'])
a=c1.get(B+'/activation').json()['activation']; chk('activation view', a['route']=='ref3' and len(a['days'])==1 and not a['content']['complete'] and a['unit']=='Lagos', a['content'])
r=c1.post(B+'/activation/day/1',json={'reached_end':False,'complete':True}); chk('day needs reading', r.status_code==422, r.text)
r=c1.post(B+'/activation/day/1',json={'reached_end':True,'seconds':200,'complete':True}); chk('day 1 complete', r.ok, r.text)
r=c1.post(B+'/activation/assessment',json={}); chk('assessment blocked until content', r.status_code==409, r.text)
# load content
tables={'activation_days':[{'route':'ref3','day':2,'title':'Day 2 T','instruction':'Do it','content':[{'doc':'content/code-of-conduct.html'}],'questions':[{'key':'why','label':'Why do you want to serve?'}],'acks':['I have read the Code']},{'route':'ref3','day':3,'title':'Day 3 T','questions':'[]','acks':[]},{'route':'legacy14','day':9,'title':'old'}],
 'readiness_statements':[{'seq':1,'section':'A','section_title':'About WODDI','statement':'S one'},{'seq':2,'section':'B','section_title':'Role','statement':'S two'}],'readiness_questions':[{'seq':1,'question':'Q one'}],'readiness_confirmations':[{'seq':1,'confirmation':'C one'}],
 'onboarding_steps':[{'code':'welcome','seq':1,'title':'Welcome','doc_url':'content/leadership-manual.html','min_seconds':10,'required':True},{'code':'visit_dash','seq':2,'title':'See dashboard','href':'#/','mode':'visit'}],
 'hq_messages':[{'kind':'weekly','week_no':1,'network':'WGMN','headline':'You are part of something bigger than yourself','body':'Full text here'}],'mystery_table':[{'a':1}]}
r=requests.post(B+'/setup',headers=H,json={'key':'test-admin-key','action':'content_import','tables':tables}).json(); print(json.dumps([(x['table'],x['result']) for x in r['report']]))
chk('content import', r['ok'] and sql("select count(*) from activation_days where route='ref3'")=='3' and sql("select count(*) from readiness_items")=='4' and sql("select count(*) from onboarding_steps")=='2' and sql("select body from hq_messages where kind='weekly' and seq=1 and network='WGMN'")=='Full text here' and sql("select doc_url from onboarding_steps where code='welcome'")=='/assets/docs/leadership-manual.html')
a=c1.get(B+'/activation').json()['activation']; chk('content now complete', a['content']['complete'] and a['days'][1]['content'][0]['doc']=='/assets/docs/code-of-conduct.html', a['content'])
r=c1.post(B+'/activation/day/3',json={'complete':True}); chk('day order', r.status_code==409)
r=c1.post(B+'/activation/day/2',json={'reached_end':True,'answers':{'why':''},'acked':[0],'complete':True}); chk('answers needed', r.status_code==422)
c1.post(B+'/activation/day/2',json={'reached_end':True,'answers':{'why':'draft'},'acked':[],'complete':False}); a=c1.get(B+'/activation').json()['activation']; chk('autosave kept', a['days'][1]['answers'].get('why')=='draft' and a['days'][1]['status']=='in_progress', a['days'][1])
assert c1.post(B+'/activation/day/2',json={'reached_end':True,'answers':{'why':'To serve'},'acked':[0],'complete':True}).ok; assert c1.post(B+'/activation/day/3',json={'complete':True}).ok
r=c1.post(B+'/activation/assessment',json={'statements':{'1':'yes'},'short_answers':{'1':'x'},'confirmations':[1],'readiness':'ready','signature_name':'Cand c1'}); chk('all statements needed', r.status_code==422)
r=c1.post(B+'/activation/assessment',json={'statements':{'1':'yes','2':'clarify'},'short_answers':{'1':'x'},'confirmations':[1],'readiness':'ready','signature_name':'Cand c1'}); chk('assessment submitted', r.ok, r.text)
reg_=hq.get(B+'/hq/activations?route=ref3').json(); chk('register', reg_['counts']['registered']==2 and reg_['counts']['awaiting_review']==1, reg_['counts']); aid=[x for x in reg_['items'] if x['status']=='submitted'][0]['id']
v=hq.get(B+f'/hq/activations/{aid}').json()['activation']; chk('hq candidate view', v['existing']['statements']['2']=='clarify' and v['candidate']['email']=='c1@test.local')
chk('leader cannot open register', lead.get(B+'/hq/activations').status_code==403)
chk('return', hq.post(B+f'/hq/activations/{aid}',json={'action':'return','note':'Clarify statement 2'}).ok); a=c1.get(B+'/activation').json()['activation']; chk('candidate sees return', a['status']=='returned' and a['return_note']=='Clarify statement 2' and a['outcome']=='')
assert c1.post(B+'/activation/assessment',json={'statements':{'1':'yes','2':'yes'},'short_answers':{'1':'x'},'confirmations':[1],'readiness':'ready','signature_name':'Cand c1'}).ok
r=hq.post(B+f'/hq/activations/{aid}',json={'action':'appoint'}); chk('appoint needs selected', r.status_code==409)
chk('outcome', hq.post(B+f'/hq/activations/{aid}',json={'action':'outcome','outcome':'selected','note':'strong','disclosed':''}).ok)
r=hq.post(B+f'/hq/activations/{aid}',json={'action':'appoint'}); chk('appointed', r.ok and r.json()['title']=='State Secretary, Lagos', r.text)
u=c1.get(B+'/me').json()['user']; chk('new leader must accept', u['is_leader'] and u['todo']['appointment'] and u['seat']['title']=='State Secretary, Lagos', u['seat'])
ppl=lead.get(B+'/referrals').json()['people']; chk('referrer sees people', len(ppl)==2, ppl)
# open7
r=hq.post(B+'/hq/activations/open',json={'email':'c4@test.local','position':'chapter_lead','org_unit_id':lagos,'area_label':'Yaba Chapter','route':'open7'}); chk('open7', r.ok, r.text)
a=c4.get(B+'/activation').json()['activation']; chk('open7 view', a['route']=='open7' and a['content']['days_expected']==7 and a['unit']=='Yaba Chapter, Lagos', a['unit'])
# extension
sql("update activations set deadline='2026-01-01' where user_id=%d"%u2['id']); a=c2.get(B+'/activation').json()['activation']; chk('lapsed', a['status']=='incomplete')
chk('ask extension', c2.post(B+'/activation/extension',json={'reason':'I was travelling'}).ok); a2id=a['id']
chk('approve extension', hq.post(B+f'/hq/activations/{a2id}',json={'action':'extension','approve':True,'until':'2027-01-01'}).ok); chk('back in progress', c2.get(B+'/activation').json()['activation']['status']=='in_progress')
# prefs include new kinds
p=lead.get(B+'/notifications/prefs').json()['prefs']; chk('prefs kinds', {'task','meeting','activation'}<= {x['kind'] for x in p})
print('FAILURES', ok)
