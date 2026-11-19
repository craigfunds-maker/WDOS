import requests, subprocess
B='http://localhost:8080/api'; H={'X-WDOS':'1'}
def S(email=None,pw=None):
    s=requests.Session(); s.headers.update(H)
    if email: r=s.post(B+'/auth/login',json={'email':email,'password':pw}); assert r.ok, r.text
    return s
def sql(q): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot','wdos','-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0
def chk(n,c,e=''):
    global bad
    print(('PASS ' if c else 'FAIL ')+n, '' if c else str(e)[:500]); bad+=0 if c else 1
hq=S('hq@test.local','hq-password-1'); hq.post(B+'/policy',json={'key':'journey_unlock','value':'open'})
cs=hq.get(B+'/geo/countries').json()['countries']; ng=[c for c in cs if c['code']=='NG'][0]['id']
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'Federal' in u['name']][0]
n=[500]
def reg(**kw):
    n[0]+=1; sql('delete from login_attempts'); s=S()
    b={'network':'WGMN','first_name':'Halima','last_name':f'P{n[0]}','dob':'1985-03-04','phone':f'+23481{10000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':'Bwari','community':'Dutse','password':'246810','consent':True,'email':f'p{n[0]}@example.org','lang':'en'}
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r
# contact: email alone is enough, phone alone is enough, neither is refused
chk('v103: an email and no phone is refused', reg(phone='')[1].status_code==422)
s1,r=reg(); u1=r.json().get('user',{}); chk('registers with an email and a phone', r.ok and u1.get('phone','')!='' and not u1.get('email_is_phone'), r.text)
s2,r=reg(email=''); u2=r.json().get('user',{}); chk('v106: registers with a phone and no email', r.ok and u2.get('email_is_phone'), r.text)
chk('neither phone nor email is refused', reg(email='',phone='')[1].status_code==422)
# journey: prototype names, summary, knowledge question
j=s1.get(B+'/journey').json(); chk('journey uses the prototype day names', [d['title'] for d in j['days']]==['Welcome and account','Mission and values','Three referrals','Community and chapter','Self-service screening','Institute introduction','Automatic activation'] and j['days'][2]['summary'].startswith('Share your personal link'), [d['title'] for d in j['days']])
q=[x for x in j['questions'] if x['key']=='expect'][0]; chk('screening has the prototype question with its two answers', q['text']=='What does WODDI expect of volunteers?' and list(q['options'].keys())==['service','dues'], q)
for d in (2,3,4,6): s1.post(B+f'/journey/{d}',json={})
ans={'own_name':'yes','location':'yes','conduct':'yes','only_account':'yes'}
chk('question must be answered', s1.post(B+'/journey/5',json={'answers':ans}).status_code==422)
s1.post(B+'/journey/5',json={'answers':{**ans,'expect':'dues'}}); r=s1.post(B+'/journey/7').json(); chk('wrong answer explained, retry', r['result']['status']=='retry' and 'compulsory local dues' in r['result']['reasons'][0]['text'], r['result'])
s1.post(B+'/journey/5',json={'answers':{**ans,'expect':'service'}}); chk('right answer activates', s1.post(B+'/journey/7').json()['result']['status']=='active')
# referrals page
code=s1.get(B+'/my/referrals').json()['referrals']['code']; s3,r=reg(referral={'code':code}); mr=s1.get(B+'/my/referrals').json(); chk('my 3 referrals lists who registered', mr['referrals']['registered']==1 and mr['invited'][0]['name']==f"Halima P{n[0]}"[:8]+'.' or mr['invited'][0]['status']=='On her seven days', mr)
# architecture
a=S('leader@test.local','leader-pass-1').get(B+'/leadership/architecture').json(); chk('architecture: six African tiers and four diaspora tiers with real titles', len(a['africa'])==6 and len(a['diaspora'])==4 and a['africa'][5][1]['WGMN']=='Chapter Coordinator / Assistant Chapter Coordinator' and a['africa'][1][1]['WNNN']=='Country Representative / Deputy Country Representative' and a['diaspora'][2][1]['WNNN']=='City Lead / Assistant City Lead' and len(a['extra'])==3, a['africa'])
# placement preview
p=hq.get(B+'/chapters/preview?network=WGMN&country=NG&state='+fct['name']+'&lga=Bwari&community=Dutse').json(); chk('placement check names the chapter', p['next']['name']=='Dutse WGMN 001' and 'Placed in Dutse WGMN 001' in p['outcome'], p)
p=hq.get(B+'/chapters/preview?network=WNNN&country=NG&state='+fct['name']+'&lga=Bwari&community=Dutse').json(); chk('placement check: other network gets its own chapter', p['next'] is None and 'opens the next chapter' in p['outcome'] and p['other_network']==1, p)
chk('member cannot use the placement check', s1.get(B+'/chapters/preview?network=WGMN&country=NG&lga=Bwari').status_code==403)
mc=s1.get(B+'/my/chapter').json(); chk('member sees chapters of her community without rosters', len(mc['siblings'])==1 and 'roster' not in mc['siblings'][0], mc.get('siblings'))
# tasks: reference, scope, evidence
cid=u1['chapter']['id']; r=hq.post(B+'/tasks',json={'title':'Submit chapter participation summary','assignee_id':u1['id'],'chapter_id':cid,'requires_approval':True}); tid=r.json()['id']
t=s1.get(B+f'/tasks/{tid}').json()['task']; chk('task has a reference, scope and owner', t['ref']==f'WDOS-TASK-{tid:05d}' and t['scope']=='Chapter: Dutse WGMN 001', t)
chk('evidence needs a note', s1.post(B+f'/tasks/{tid}/evidence',json={'note':''}).status_code==422)
chk('evidence link must be https', s1.post(B+f'/tasks/{tid}/evidence',json={'note':'Attendance sheet','url':'http://x.y'}).status_code==422)
chk('owner adds evidence', s1.post(B+f'/tasks/{tid}/evidence',json={'note':'Attendance sheet for November','url':'https://drive.google.com/file/abc'}).ok)
d=hq.get(B+f'/tasks/{tid}').json(); chk('evidence shows on the task and the assigner is told', d['task']['evidence']==1 and any(c['kind']=='evidence' and c['url'].startswith('https://') for c in d['comments']) and sql("select count(*) from notifications where title like 'Evidence added:%'")=='1', d['task'])
tl=hq.get(B+'/tasks?scope=area').json(); chk('task list counts chapter-scoped and escalations', tl['chapter_scoped']>=1 and 'escalations' in tl, tl.get('chapter_scoped'))
# reports intelligence
rp=hq.get(B+'/reports/auto').json(); dd=rp['distribution']; chk('distribution adds up', dd['total']==dd['active']+dd['onboarding']+dd['pending']+dd['inactive'] and 'report_status' in rp, dd)
# templates
tp=hq.get(B+'/hq/templates').json()['templates']; chk('templates listed in five languages', len(tp)==31 and set(tp[0]['langs'].keys())=={'en','ar','fr','pt','sw'} and tp[0]['langs']['sw']['title'].startswith('Heri'), [x['key'] for x in tp])
chk('HQ rewords the placement message in English', hq.post(B+'/hq/templates',json={'key':'chapter_placed','lang':'en','title':'Welcome to your chapter','body':'You are in {1}. Go and greet your sisters.'}).ok)
s4,r=reg(); u4=r.json()['user']; t=sql(f"select concat(title,'|',body) from notifications where user_id={u4['id']} and kind='account'"); chk('new wording is used with the chapter name filled in', t=='Welcome to your chapter|You are in Dutse WGMN 001. Go and greet your sisters.', t)
hq.post(B+'/hq/templates',json={'key':'chapter_placed','lang':'en','title':'','body':''}); chk('template change logged, and can go back', len(hq.get(B+'/hq/templates').json()['changes'])==2 and not hq.get(B+'/hq/templates').json()['templates'][7]['langs']['en']['own'])
chk('member cannot edit templates', s1.post(B+'/hq/templates',json={'key':'birthday','lang':'en','title':'x','body':'y'}).status_code==403)
# announcement to one member
r=hq.post(B+'/hq/announcements',json={'title':'A note for you','body':'Please call the office.','member':u2['id']}); chk('announcement to one member', r.ok and r.json()['recipients']==1, r.text)
chk('only she reads it', any(x['title']=='A note for you' for x in s2.get(B+'/announcements').json()['items']) and not any(x['title']=='A note for you' for x in s1.get(B+'/announcements').json()['items']))
# volunteer
r=s2.post(B+'/me/volunteer',json={}); chk('member becomes a volunteer with three development assignments', r.ok and s2.get(B+'/me').json()['user']['volunteer'] and s2.get(B+'/tasks').json()['total']==3, r.text)
chk('asking twice changes nothing', s2.post(B+'/me/volunteer',json={}).json().get('already') and s2.get(B+'/tasks').json()['total']==3)
# leader workspace + integrations + policy fixed
ld=S('leader@test.local','leader-pass-1').get(B+'/leader/dashboard').json(); chk('leader workspace figures', all(k in ld['workspace'] for k in ('chapters','open_tasks','reports_rate','scope')), ld.get('workspace'))
ig=hq.get(B+'/hq/integrations').json(); chk('integration health says what is and is not connected', [x[2] for x in ig['systems']]==['In WDOS','In WDOS','In WDOS','Not connected','Not connected','Not connected'] and len(ig['services'])==4, ig['systems'])
chk('member cannot open integration health', s1.get(B+'/hq/integrations').status_code==403)
pl=hq.get(B+'/policy').json(); chk('policies in force and nine decisions', len(pl['fixed'])==8 and pl['fixed'][0][1].startswith('No.') and len(pl['decisions'])==14, len(pl['decisions']))
hq.post(B+'/policy',json={'key':'journey_unlock','value':'daily'})
print('FAILURES', bad)
