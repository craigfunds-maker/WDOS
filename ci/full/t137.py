# v109.46: the 3-Day and 7-Day Leadership Activation Framework, run before appointment (Azeez, 22 Nov 2026).
# A referred woman (Envoy, Country Representative or any leader's link, or a person she names) does Days 1 to 3; only a woman with no
# referral does Days 1 to 7; a seated leader does none. Tasks keep evidence, scores, reviewer and remarks; the three gates; pass 80;
# Pending Corrections and resubmission; Requiring Support; compliance breach; HQ activates only when both stages pass and all evidence
# is verified; the scores show on the HQ dashboard, the HQ register, the envoy's and the referring leader's dashboards. Synthetic data only.
import requests, subprocess, time
X=str(int(time.time()))[-6:]; B='http://localhost:8080/api'; H={'X-WDOS':'1'}; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(x)[:500])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers.update(H); return s
def login(e,p):
  sql("delete from login_attempts"); s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
sql("update seats set ends_at=utc_timestamp() - interval 10 day where ends_at is null")
hq=login('hq@test.local','hq-password-1')
ng=int(sql("select id from org_units where level='country' and country_code='NG'")); ak=int(sql(f"select id from org_units where parent_id={ng} and name='Akwa Ibom'"))
n=[0]
def reg(first, ref=None, lga='Uyo', comm='Etoi 1'):
  n[0]+=1; sql("delete from login_attempts"); s=S(); e=f'{first.lower()}{n[0]}.{X}@example.org'
  b={'network':'WGMN','first_name':first,'last_name':'Fw'+X,'dob':'1985-03-04','phone':f'+234816{X}{n[0]:02d}','country_id':ng,'state_id':ak,'lga':lga,'community':comm,'password':'member-pass-1','consent':True,'email':e,'lang':'en'}
  if ref: b['referral']=ref
  r=s.post(B+'/auth/register',json=b); assert r.ok, r.text[:300]; return s, r.json()['user']['id'], e
def seat(uid, pos, unit, **kw):
  r=hq.post(B+f'/hq/members/{uid}/seat',json={'position':pos,'org_unit_id':unit,'network':'WGMN',**kw}); assert r.ok, r.text; sql(f"update seats set acceptance='accepted' where user_id={uid} and ends_at is null")
sc,sc_id,_=reg('Statecoord'); seat(sc_id,'state_coordinator',ak)
other,other_id,_=reg('Otherleader'); seat(other_id,'district_coordinator',ak,area_label='Eket')
# an Envoy's link
r=hq.post(B+'/hq/envoys',json={'first_name':'Fwenvoy','last_name':'T'+X,'email':f'fwenv.{X}@example.org','phone':'','country_code':'NG'}); env_id=int(sql(f"select id from users where email='fwenv.{X}@example.org'"))
sql(f"insert ignore into envoy_acceptances (user_id,accepted_at) values ({env_id}, utc_timestamp())"); env=login(f'fwenv.{X}@example.org', r.json()['temporary_password'])
code=env.get(B+'/envoy/home').json()['code']
# ---- who does which route
c1,c1_id,_=reg('Envoyref',ref={'code':code,'position':'district_coordinator'})
a=sql(f"select concat(route,'|',scheme) from activations where user_id={c1_id}"); chk('referred by an Envoy: 3-Day, under the framework', a=='ref3|fw', a)
crl=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','lga':'Oron','network':'WGMN','position':'district_coordinator'}).json()['link']
c2,c2_id,_=reg('Leaderref',ref={'code':crl['code']})
chk('referred through a leader\'s seat link: 3-Day', sql(f"select route from activations where user_id={c2_id}")=='ref3')
c3,c3_id,_=reg('Namedref',ref={'position':'cluster_coordinator','category':'individual','name':'Mrs Ada Okon'})
chk('referred by a person she names: 3-Day', sql(f"select route from activations where user_id={c3_id}")=='ref3')
c4,c4_id,c4_e=reg('Selfapply',ref={'position':'cluster_coordinator'})
chk('applied with no referral: 7-Day', sql(f"select route from activations where user_id={c4_id}")=='open7', sql(f"select route from activations where user_id={c4_id}"))
c5,c5_id,c5_e=reg('Hqopened')
r=hq.post(B+'/hq/activations/open',json={'email':c5_e,'position':'cluster_coordinator','org_unit_id':ak,'area_label':'Etoi 1','route':'ref3'})
chk('HQ opening one for a woman with no referral gives 7 days, whatever is asked', r.ok and sql(f"select route from activations where user_id={c5_id}")=='open7', r.text)
r=hq.post(B+'/hq/activations/open',json={'email':sql(f"select email from users where id={sc_id}"),'position':'cluster_coordinator','org_unit_id':ak,'area_label':'Etoi 1'})
chk('a seated leader does no activation', r.status_code==409 and sql(f"select count(*) from activations where user_id={sc_id}")=='0', r.text)
# ---- the 3-Day route, step by step
v=c1.get(B+'/activation').json()['activation']
chk('her activation shows the framework: 5 tasks on Days 1 to 3', v['scheme']=='fw' and [t['key'] for t in v['fw']['tasks']]==['consent','access','orientation','contact','priorities'], v.get('fw',{}).get('tasks'))
chk('WDOS records her verified sign-in by itself (gate)', v['fw']['gates']['access'] and [t for t in v['fw']['tasks'] if t['key']=='access'][0]['score']==20, v['fw']['gates'])
chk('the quiz is sent without its answers', all(set(q)=={'q','options'} for q in v['quiz_a']) and len(v['quiz_a'])==10)
chk('her reporting officer is offered: the State Coordinator above the Uyo seat, then HQ', [o['id'] for o in v['officers']]==[sc_id,0], v['officers'])
chk('due dates run Day 1 to Day 3 from her start', [t['due_on'] for t in v['fw']['tasks']][0] < [t['due_on'] for t in v['fw']['tasks']][-1])
r=c1.post(B+'/activation/task/orientation',json={'read':True,'quiz':[0]*10}); chk('Day 2 waits for Day 1', r.status_code==409 and r.json()['error']=='order', r.text)
r=c1.post(B+'/activation/task/consent',json={'consent':True,'compliance':True,'signature':'Envoyref Fw'}); chk('Day 1 needs every tick', r.status_code==422 and r.json()['error']=='kit', r.text)
r=c1.post(B+'/activation/task/consent',json={'consent':True,'compliance':True,'kit':True,'signature':'Envoyref Fw'}); chk('Day 1 sent', r.ok and r.json()['auto_score']==15, r.text)
right=[0,1,1,0,1,0,1,1,0,2]
r=c1.post(B+'/activation/task/orientation',json={'read':True,'quiz':right[:9]}); chk('every orientation question must be answered', r.status_code==422)
r=c1.post(B+'/activation/task/orientation',json={'read':True,'quiz':right[:8]+[1,2]}); chk('9 of 10 right gives 27 of 30', r.ok and r.json()['auto_score']==27, r.text)
r=c1.post(B+'/activation/task/orientation',json={'read':True,'quiz':right}); chk('a sent task cannot be sent again until HQ returns it', r.status_code==409)
r=c1.post(B+'/activation/task/contact',json={'officer':other_id,'how':'Phone','when':'22 Nov','agreed':'We agreed weekly calls'}); chk('only an officer above the seat can be named', r.status_code==422, r.text)
r=c1.post(B+'/activation/task/contact',json={'officer':sc_id,'how':'Phone call','when':'22 Nov, 10am','agreed':'We agreed a weekly call and my first ward visits.','counterpart':'WNNN State Lead'}); chk('Day 3 contact sent', r.ok and r.json()['auto_score']==10, r.text)
chk('her reporting officer is told', sql(f"select count(*) from notifications where user_id={sc_id} and title like 'Envoyref%contacted you'")=='1')
r=c1.post(B+'/activation/task/priorities',json={'priorities':['Visit every ward in Uyo LGA this month','Fill the vacant ward seats with referrals','Hold the first LGA leaders meeting'],'vacancies':'Ward seats in Ikono 1 and Offot 1'}); chk('priorities sent', r.ok and r.json()['auto_score']==10, r.text)
st=r.json()['state']; chk('score 15+20+27+10+10 = 82, but the reporting gate is not met, so not passed', st['score']==82 and st['passed'] is False and st['gates']['reporting'] is False, st)
aid=int(sql(f"select id from activations where user_id={c1_id}"))
chk('every task in: status Submitted for HQ\'s Day 3 review', sql(f"select status from activations where id={aid}")=='submitted' and sql(f"select count(*) from notifications n join users u on u.id=n.user_id where u.role in ('hq_admin','super_admin') and n.title like 'Day 3 review: Envoyref%'")!='0')
r=hq.post(B+f'/hq/activations/{aid}',json={'action':'approve'}); chk('HQ cannot activate while a mandatory gate is missing', r.status_code==409 and 'reporting officer' in r.text, r.text)
r=other.post(B+f'/activation/{aid}/confirm-contact',json={}); chk('another leader cannot confirm the contact', r.status_code==403)
cf=sc.get(B+'/leader/dashboard').json()['confirm']; chk('the officer sees the request on her dashboard', [x['activation_id'] for x in cf]==[aid] and cf[0]['evidence']['how']=='Phone call', cf)
r=sc.post(B+f'/activation/{aid}/confirm-contact',json={}); chk('the State Coordinator confirms', r.ok)
s=hq.get(B+f'/hq/activations/{aid}').json()['activation']; f=s['fw']
chk('now 92, all gates met, passed', f['score']==92 and f['passed'] and all(f['gates'].values()), f['score'])
r=hq.post(B+f'/hq/activations/{aid}',json={'action':'approve'}); chk('still, HQ activates only after verifying the evidence', r.status_code==409 and r.json()['error']=='not_verified', r.text)
r=hq.post(B+f'/hq/activations/{aid}/task/priorities',json={'action':'verify','score':11}); chk('a score above the task\'s points is refused', r.status_code==422)
r=hq.post(B+f'/hq/activations/{aid}/task/orientation',json={'action':'correct','remarks':'Read the communication flow again and retake the questions.'}); chk('HQ returns one task: Pending Corrections', r.ok and r.json()['state']['decision']=='pending_corrections', r.text)
chk('she is told, with the remarks', sql(f"select count(*) from notifications where user_id={c1_id} and title like 'Pending corrections:%'")=='1')
r=c1.post(B+'/activation/task/orientation',json={'read':True,'quiz':right}); chk('she resends it: 10 of 10, 30 points', r.ok and r.json()['auto_score']==30, r.text)
chk('one resubmission is recorded', sql(f"select resubmissions from activation_tasks where activation_id={aid} and task_key='orientation'")=='1')
for k in ['consent','access','orientation','contact','priorities']:
  r=hq.post(B+f'/hq/activations/{aid}/task/{k}',json={'action':'verify','remarks':'Checked'}); assert r.ok, r.text
r=hq.get(B+f'/hq/activations/{aid}').json()['activation']['fw']; chk('verified: 15+20+30+20+10 = 95, each task names its reviewer', r['score']==95 and r['decision']=='verified' and all(t['reviewer'] for t in r['tasks']), (r['score'],r['decision']))
r=hq.post(B+f'/hq/activations/{aid}',json={'action':'approve'}); chk('HQ activates her: appointed to the seat', r.ok and sql(f"select position_code from seats where user_id={c1_id} and ends_at is null")=='district_coordinator', r.text)
chk('the activation now reads Activated', hq.get(B+f'/hq/activations/{aid}').json()['activation']['fw']['decision']=='activated')
# ---- the 7-Day route
v=c4.get(B+'/activation').json()['activation']; aid4=int(sql(f"select id from activations where user_id={c4_id}"))
chk('7-Day: 10 tasks, Days 1 to 7', len(v['fw']['tasks'])==10 and v['fw']['days_total']==7 and v['fw']['tasks'][-1]['day']==7)
chk('Day 4 shows her the leaders and vacant seats of the place she would serve', v['coverage']['place']=='Etoi 1' and len(v['coverage']['seats'])==2, v['coverage'])
r=c4.post(B+'/activation/task/coverage',json={'summary':'x'*60}); chk('Day 4 waits for Days 1 to 3', r.status_code==409)
c4.post(B+'/activation/task/consent',json={'consent':True,'compliance':True,'kit':True,'signature':'Self Apply'})
c4.post(B+'/activation/task/orientation',json={'read':True,'quiz':right})
r=c4.post(B+'/activation/task/contact',json={'officer':0,'how':'WDOS message','when':'22 Nov','agreed':'HQ will be my reporting line until a ward leader is seated.'}); chk('with no leader above, she names WODDI HQ', r.ok, r.text)
c4.post(B+'/activation/task/priorities',json={'priorities':['Map every polling unit in Etoi 1 ward','Meet the women groups in Etoi 1','Start the first chapter in Etoi 1 ward']})
chk('HQ is told when Days 1 to 3 are in (Day 3 review)', sql("select count(*) from notifications n join users u on u.id=n.user_id where u.role in ('hq_admin','super_admin') and n.title like 'Day 3 review: Selfapply%'")!='0')
c4.post(B+'/activation/task/coverage',json={'summary':'Etoi 1 has no Ward Coordinator and no Assistant. Uyo LGA has its LGA Coordinator. I visited three polling units and found two active women groups ready for a chapter.','gaps':'Ward Coordinator and Assistant are both vacant in Etoi 1.','confirmed_list':True})
r=c4.post(B+'/activation/task/referrals',json={'people':[{'name':'Prospect One','for':'Assistant Ward Coordinator','status':'interested'},{'name':'Prospect Two','for':'member','status':'contacted'},{'name':'Prospect Three','for':'member','status':'identified'}],'engagement':'Spoke with the women group leaders at the market and church; two asked for the link.'})
chk('referrals scored for documented quality: two screened prospects and engagement evidence, not appointments', r.ok and r.json()['auto_score']==12, r.text)
c4.post(B+'/activation/task/engagement',json={'log':[{'who':'Uyo LGA Coordinator','how':'Call','when':'Day 6','outcome':'Agreed to co-host a ward meeting'},{'who':'Church women leader','how':'Visit','when':'Day 6','outcome':'Will bring 15 women'},{'who':'Market women leader','how':'Visit','when':'Day 6','outcome':'Will share the link'}]})
c4.post(B+'/activation/task/plan',json={'actions':[{'what':'Hold the first Etoi 1 ward meeting with women groups','by':'5 Dec','with':'LGA Coordinator'},{'what':'Refer two candidates for the ward seats','by':'10 Dec','with':'HQ'},{'what':'Open the first chapter in Etoi 1 ward','by':'20 Dec','with':'Chapter members'}]})
r=c4.post(B+'/activation/task/report',json={'report':'r'*100,'quiz':[1,1,1,1,1]}); chk('the 6 compliance questions must all be answered', r.status_code==422)
r=c4.post(B+'/activation/task/report',json={'report':'Days 1 to 7: I accepted, oriented, contacted HQ, mapped Etoi 1, referred three people, met three leaders and planned the first ward meeting. '*3,'quiz':[1,1,1,1,1,0]})
st=r.json()['state']; chk('Day 7 in: two stage scores, Days 4 to 7 out of 100', r.ok and st['stage_b'] is not None and st['days_done']==7, st)
s=hq.get(B+f'/hq/activations/{aid4}').json()['activation']['fw']
chk('7-Day: not passed until HQ confirms the contact (she named HQ)', s['passed'] is False and s['gates']['reporting'] is False and s['gates']['report'] is True, s['gates'])
for t in s['tasks']: hq.post(B+f'/hq/activations/{aid4}/task/{t["key"]}',json={'action':'verify'})
s=hq.get(B+f'/hq/activations/{aid4}').json()['activation']['fw']
chk('verifying the contact confirms the gate; both stages scored', s['gates']['reporting'] and s['stage_a']>=80 and s['stage_b']>=80, (s['stage_a'],s['stage_b']))
hq.post(B+f'/hq/activations/{aid4}',json={'action':'breach','note':'Collected a levy from members'})
r=hq.post(B+f'/hq/activations/{aid4}',json={'action':'approve'}); chk('a recorded compliance breach stops activation', r.status_code==409 and 'breach' in r.text, r.text)
hq.post(B+f'/hq/activations/{aid4}',json={'action':'breach','note':''})
r=hq.post(B+f'/hq/activations/{aid4}',json={'action':'approve'}); chk('cleared, she is activated', r.ok, r.text)
# ---- Requiring Support
v=c2.get(B+'/activation').json(); aid2=int(sql(f"select id from activations where user_id={c2_id}"))
r=hq.post(B+f'/hq/activations/{aid2}',json={'action':'support','note':'She needs help signing in on her phone. HQ will call her.'}); chk('HQ marks Requiring Support', r.ok and hq.get(B+f'/hq/activations/{aid2}').json()['activation']['fw']['decision']=='requiring_support')
r=c2.post(B+'/activation/task/consent',json={'consent':True,'compliance':True,'kit':True,'signature':'L R'}); chk('her tasks wait while support is arranged', r.status_code==409 and r.json()['error']=='support', r.text)
hq.post(B+f'/hq/activations/{aid2}',json={'action':'resume'}); r=c2.post(B+'/activation/task/consent',json={'consent':True,'compliance':True,'kit':True,'signature':'L R'}); chk('HQ reopens it and she carries on', r.ok, r.text)
# ---- where the scores show
dsh=hq.get(B+'/hq/dashboard').json()['activations']
chk('HQ dashboard: 3-Day and 7-Day activations by status, with average score and pass rate', dsh['ref3']['activated']==1 and dsh['open7']['activated']==1 and dsh['ref3']['total']>=3 and dsh['ref3']['average'] is not None, dsh)
reg_=hq.get(B+'/hq/activations?route=ref3').json(); it=[x for x in reg_['items'] if x['user_id']==c2_id][0]
chk('the register shows each one\'s framework status and score', it['scheme']=='fw' and it['score']['framework'] and 'Day' in it['stage'], it)
p=[x for x in env.get(B+'/envoy/home').json()['people'] if x['id']==c1_id][0]
chk('the Envoy sees the same score and that HQ approved her', p['activation']['score']['score']==95 and p['approved_as'].startswith('LGA'), p['activation']['score'])
cand=sc.get(B+'/leader/dashboard').json()['candidates']; chk('the State Coordinator sees the candidate she referred, with day and score', any(x['id']==c2_id and x['days_total']==3 for x in cand), cand)
r=c4.post(B+'/activation/day/1',json={'complete':True}); chk('the earlier day-by-day route is closed to framework activations', r.status_code in (404,409))
print(f'\n{ok} passed, FAILURES {bad}')
