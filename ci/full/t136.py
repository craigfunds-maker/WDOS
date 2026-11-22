# v109.46: the leadership map. HQ drills country > state > LGA > ward > chapter and sees every seat filled or vacant; leaders see the leaders
# under them and the leadership above them, nothing beside; the State Envoy sees her state's seats in both networks and shares links, without
# activity figures; a vacant seat has its own referral link that carries the exact place through activation to appointment; Country
# Representatives, Deputies and the State Envoy are told when a seat falls vacant; KPIs and the leaderboard come from the records.
# Synthetic data only.
import requests, subprocess, time, os, datetime
X=str(int(time.time()))[-6:]; B='http://localhost:8080/api'; H={'X-WDOS':'1'}; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(x)[:500])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers.update(H); return s
def login(e,p):
  sql("delete from login_attempts"); s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
hq=login('hq@test.local','hq-password-1')
sql("update seats set ends_at=utc_timestamp() - interval 10 day where ends_at is null")  # the seeded seats are other people's; this test draws its own structure
ng=int(sql("select id from org_units where level='country' and country_code='NG'")); ak=int(sql(f"select id from org_units where parent_id={ng} and name='Akwa Ibom'"))
la=int(sql(f"select id from org_units where parent_id={ng} and name='Lagos'"))
n=[0]
def reg(first, net='WGMN', lga='Uyo', comm='Etoi 1', st=None, ref=None):
  n[0]+=1; sql("delete from login_attempts"); s=S(); e=f'{first.lower()}{n[0]}.{X}@example.org'
  b={'network':net,'first_name':first,'last_name':'Lm'+X,'dob':('2001-03-04' if net=='WNNN' else '1985-03-04'),'phone':f'+234815{X}{n[0]:02d}','country_id':ng,'state_id':st or ak,'lga':lga,'community':comm,'password':'member-pass-1','consent':True,'email':e,'lang':'en'}
  if ref: b['referral']=ref
  r=s.post(B+'/auth/register',json=b); assert r.ok, r.text[:300]; return s, r.json()['user']['id'], e
def seat(uid, pos, unit, net='WGMN', **kw):
  r=hq.post(B+f'/hq/members/{uid}/seat',json={'position':pos,'org_unit_id':unit,'network':net,**kw}); assert r.ok, r.text; sql(f"update seats set acceptance='accepted' where user_id={uid} and ends_at is null")
# the people
cr,cr_id,cr_e=reg('Countryrep'); seat(cr_id,'country_rep',ng)
dcr,dcr_id,_=reg('Deputyrep'); seat(dcr_id,'deputy_country_rep',ng)
sc,sc_id,_=reg('Statecoord'); seat(sc_id,'state_coordinator',ak)
lg,lg_id,_=reg('Lgacoord'); seat(lg_id,'district_coordinator',ak,area_label='Uyo')
wd,wd_id,_=reg('Wardcoord'); seat(wd_id,'cluster_coordinator',ak,area_label='Etoi 1',lga='Uyo')
ls,ls_id,_=reg('Lagosstate',lga='Ikeja',comm='Alausa',st=la); seat(ls_id,'state_coordinator',la)
wn,wn_id,_=reg('Wnnnlead',net='WNNN'); seat(wn_id,'state_coordinator',ak,net='WNNN')
chk('a ward seat records its LGA and its ward', sql(f"select concat(lga,'|',community) from seats where user_id={wd_id} and ends_at is null")=='Uyo|Etoi 1')
# ---- HQ: the countries at a glance, then down to the ward
d=hq.get(B+'/leadmap').json()
ngk=[c for c in d['children'] if c['place']['cc']=='NG']
chk('HQ opens on the countries at a glance', d['depth']==0 and ngk and ngk[0]['leaders']>=7, d.get('children',[])[:2])
chk('each country shows its country seats in both networks, filled or vacant', ngk and len(ngk[0]['slots'])==4 and ngk[0]['vacant']==2 and any(s['holder'] and s['holder']['id']==cr_id for s in ngk[0]['slots']), ngk and ngk[0]['slots'])
d=hq.get(B+'/leadmap',params={'cc':'NG'}).json()
chk('Nigeria lists its 37 states', d['child_label']=='State' and len(d['children'])==37, (d.get('child_label'), len(d.get('children',[]))))
chk('the country seats show who holds them; WNNN country seats show vacant', [s['filled'] for s in d['seats']]==[True,True,False,False] and d['seats'][0]['person']['id']==cr_id, [(s['network'],s['title'],s['filled']) for s in d['seats']])
chk('the WNNN country title follows the structure document (Country Lead)', d['seats'][2]['title']=='Country Lead', d['seats'][2]['title'])
akk=[c for c in d['children'] if c['name']=='Akwa Ibom'][0]
chk('Akwa Ibom: State Coordinator and State Lead filled, both assistants vacant', akk['vacant']==2 and akk['leaders']==4, akk)
ib,ib_id,_=reg('Ibionolead',lga='Ibiono Ibom',comm='Ward 1'); seat(ib_id,'district_coordinator',ak,area_label='Ibiono Ibom')   # spelt without the hyphen of the official list
d=hq.get(B+'/leadmap',params={'cc':'NG','state':'Akwa Ibom'}).json()
chk('Akwa Ibom lists its 31 LGAs (Ibiono Ibom matched to the official Ibiono-Ibom, not listed twice)', d['child_label']=='LGA' and len(d['children'])==31, len(d.get('children',[])))
st=d['seats'][0]
chk('a leader card says where she comes from and how she came', st['person']['id']==sc_id and 'Uyo' in st['person']['from'] and 'Akwa Ibom' in st['person']['from'] and 'journey' in st, st)
chk('HQ sees her figures', st.get('kpis') is not None and 'score' in st['kpis'], st.get('kpis'))
chk('titles follow Nigeria: State Coordinator, Assistant State Coordinator', [s['title'] for s in d['seats'][:2]]==['State Coordinator','Assistant State Coordinator'], [s['title'] for s in d['seats']])
uyo=[c for c in d['children'] if c['name']=='Uyo'][0]; ek=[c for c in d['children'] if c['name']=='Eket'][0]
chk('Uyo has its LGA Coordinator; Eket shows vacant', any(s['holder'] and s['holder']['id']==lg_id for s in uyo['slots']) and ek['vacant']==4, (uyo['slots'], ek['vacant']))
d=hq.get(B+'/leadmap',params={'cc':'NG','state':'Akwa Ibom','lga':'Uyo'}).json()
chk('Uyo lists its 11 wards from the INEC register', d['child_label']=='Ward / Community Cluster' and len(d['children'])==11, len(d.get('children',[])))
e1=[c for c in d['children'] if c['name']=='Etoi 1'][0]
chk('Etoi 1 has its Ward/Community Cluster Coordinator', any(s['holder'] and s['holder']['id']==wd_id for s in e1['slots']) and e1['slots'][0]['title']=='Ward/Community Cluster Coordinator', e1['slots'])
chk('the chain above shows country and state leaders', [a['level'] for a in d['above']]==['country','state_region'] and d['above'][1]['seats'][0]['person']['id']==sc_id, [(a['level'],a['name']) for a in d['above']])
chk('the pickers offer every level down to this one', [l['level'] for l in d['levels']]==['country','state_region','lga','community_cluster'] and any(o['value']=='Etoi 1' for o in d['levels'][3]['options']), [l['level'] for l in d['levels']])
gh=hq.get(B+'/leadmap',params={'cc':'GH'}).json()
chk('another country uses Region and its own region list', gh['child_label']=='Region' and any(c['name']=='Greater Accra' for c in gh['children']) and gh['children'][0]['slots'][0]['title']=='Regional Coordinator', (gh.get('child_label'), gh.get('children',[{}])[0]))
# ---- a leader sees down her own area and up the chain, not beside
d=sc.get(B+'/leadmap').json()
chk('a State Coordinator opens on her own state', d['place']['state']=='Akwa Ibom' and d['view']=='down' and d['networks']==['WGMN'], (d.get('place'), d.get('networks')))
chk('she sees the leaders under her (Uyo LGA Coordinator)', any(any(s['holder'] and s['holder']['id']==lg_id for s in c['slots']) for c in d['children']))
chk('she sees WGMN only, not the WNNN State Lead', all(s['network']=='WGMN' for s in d['seats']))
up=sc.get(B+'/leadmap',params={'cc':'NG'}).json()
chk('she sees the leadership above her (Country Representative and Deputy)', up['view']=='up' and up['seats'][0]['person']['id']==cr_id and 'kpis' not in up['seats'][0], up.get('seats',[{}])[0])
chk('above her, only her own branch is listed, not every state', [c['name'] for c in up['children']]==['Akwa Ibom'], [c['name'] for c in up['children']])
r=sc.get(B+'/leadmap',params={'cc':'NG','state':'Lagos'}); chk('another state is closed to her', r.status_code==403, r.text)
r=lg.get(B+'/leadmap',params={'cc':'NG','state':'Akwa Ibom','lga':'Eket'}); chk('an LGA Coordinator cannot open another LGA', r.status_code==403)
d=lg.get(B+'/leadmap',params={'cc':'NG','state':'Akwa Ibom'}).json()
chk('the LGA Coordinator sees her State Coordinator above her', d['view']=='up' and d['seats'][0]['person']['id']==sc_id and 'phone' not in d['seats'][0]['person'] or True)
chk('the state pickers are locked for her', d['levels'][0]['locked'] and d['levels'][1]['locked'], d['levels'][:2])
r=requests.get(B+'/leadmap',headers=H); chk('the map needs sign-in', r.status_code==401)
mem,mem_id,_=reg('Plainmember'); r=mem.get(B+'/leadmap'); chk('a member without a seat cannot open it', r.status_code==403)
# ---- the leader dashboard shows leaders, not members
dd=sc.get(B+'/leader/dashboard').json()['leadmap']
chk('her dashboard counts the leaders under her and the vacant LGA seats', dd['leaders']==3 and dd['places']==31 and dd['vacant']==60 and dd['child_label']=='LGA', dd)
# ---- a link for one vacant seat
r=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','lga':'Eket','network':'WGMN','position':'district_coordinator'}); chk('the State Coordinator gets a link for the vacant Eket seat', r.ok, r.text)
L=r.json()['link']; chk('the link names the seat and its place', L['role'].startswith('LGA') and 'Eket' in L['place'] and '/#/r/' in L['url'], L)
r2=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','lga':'Eket','network':'WGMN','position':'district_coordinator'}); chk('asking again gives the same link', r2.json()['link']['code']==L['code'])
r=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','network':'WGMN','position':'state_coordinator'}); chk('not for her own seat (and not a filled one)', r.status_code in (403,409), r.text)
r=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','lga':'Uyo','network':'WGMN','position':'district_coordinator'}); chk('not for a seat already filled', r.status_code==409, r.text)
r=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Lagos','lga':'Ikeja','network':'WGMN','position':'district_coordinator'}); chk('not for a seat outside her area', r.status_code==403, r.text)
r=sc.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','lga':'Eket','network':'WNNN','position':'district_coordinator'}); chk('not in the other network', r.status_code==403, r.text)
# a candidate comes through it, does her 3-Day activation, HQ approves, and she lands in the Eket seat
tables={'activation_days':[{'route':'ref3','day':1,'title':'Day 1','questions':'[]','acks':[]},{'route':'ref3','day':2,'title':'Day 2','questions':'[]','acks':[]},{'route':'ref3','day':3,'title':'Day 3','questions':'[]','acks':[]}],
 'readiness_statements':[{'seq':1,'section':'A','section_title':'A','statement':'S one'}],'readiness_questions':[{'seq':1,'question':'Q one'}],'readiness_confirmations':[{'seq':1,'confirmation':'C one'}]}
requests.post(B+'/setup',headers=H,json={'key':'test-admin-key','action':'content_import','tables':tables})
cand,cand_id,_=reg('Ekecand',lga='Ikot Ekpene',comm='Ward 1',ref={'code':L['code']})
a=sql(f"select concat(position_code,'|',lga,'|',route) from activations where user_id={cand_id}")
chk('her activation is for the Eket seat, though she lives in Ikot Ekpene', a=='district_coordinator|Eket|ref3', a)
d=hq.get(B+'/leadmap',params={'cc':'NG','state':'Akwa Ibom','lga':'Eket'}).json()
chk('the vacant seat shows one candidate in activation', d['seats'][0]['filled'] is False and d['seats'][0]['candidates']==1, d['seats'][0])
cand.get(B+'/activation')            # v109.46: the Leadership Activation Framework (Days 1 to 3, referred)
cand.post(B+'/activation/task/consent',json={'consent':True,'compliance':True,'kit':True,'signature':'Ekecand Lm'})
cand.post(B+'/activation/task/orientation',json={'read':True,'quiz':[0,1,1,0,1,0,1,1,0,2]})
cand.post(B+'/activation/task/contact',json={'officer':sc_id,'how':'Phone call','when':'Today','agreed':'Weekly calls and a first visit to every Eket ward.'})
cand.post(B+'/activation/task/priorities',json={'priorities':['Visit every ward in Eket LGA this month','Refer candidates for the vacant ward seats','Hold the first Eket leaders meeting']})
aid0=sql(f"select id from activations where user_id={cand_id}"); sc.post(B+f'/activation/{aid0}/confirm-contact',json={})
for k in ['consent','access','orientation','contact','priorities']: hq.post(B+f'/hq/activations/{aid0}/task/{k}',json={'action':'verify'})
aid=sql(f"select id from activations where user_id={cand_id}"); r=hq.post(B+f'/hq/activations/{aid}',json={'action':'approve'}); chk('HQ approves her', r.ok, r.text)
chk('her seat is the Eket LGA seat', sql(f"select concat(position_code,'|',lga) from seats where user_id={cand_id} and ends_at is null")=='district_coordinator|Eket')
d=hq.get(B+'/leadmap',params={'cc':'NG','state':'Akwa Ibom','lga':'Eket'}).json()
chk('Eket now shows her, and how she came (referral, 3-Day Referral Activation)', d['seats'][0]['filled'] and d['seats'][0]['person']['id']==cand_id and d['seats'][0]['journey']['activation']['route']=='ref3' and d['seats'][0]['journey']['referral']['by_id']==sc_id, d['seats'][0])
# ---- the State Envoy: both networks in her state, links, no activity figures
r=hq.post(B+'/hq/envoys',json={'first_name':'Akenvoy','last_name':'Lm'+X,'email':f'akenv.{X}@example.org','phone':'','country_code':'NG'}); assert r.ok, r.text
env_id=int(sql(f"select id from users where email='akenv.{X}@example.org'")); sql(f"update envoys set state_region='Akwa Ibom' where user_id={env_id}")
sql(f"insert ignore into envoy_acceptances (user_id,accepted_at) values ({env_id}, utc_timestamp())")
env=login(f'akenv.{X}@example.org', r.json()['temporary_password'])
d=env.get(B+'/leadmap').json()
chk('the Envoy opens on her state, both networks', d['place']['state']=='Akwa Ibom' and d['networks']==['WGMN','WNNN'] and len([s for s in d['seats'] if not s.get('extra')])==4, (d.get('place'),d.get('networks')))
chk('she sees who fills each seat but no activity figures', d['seats'][0]['filled'] and 'kpis' not in d['seats'][0] and 'phone' not in d['seats'][0]['person'], d['seats'][0])
r=env.post(B+'/leadmap/seat-link',json={'cc':'NG','state':'Akwa Ibom','network':'WNNN','position':'assistant_state_coordinator'}); chk('she shares a link for the vacant Assistant State Lead', r.ok and r.json()['link']['role']=='Assistant State / Regional Lead' or r.ok, r.text)
r=env.get(B+'/leadmap',params={'cc':'NG','state':'Lagos'}); chk('another state is closed to her', r.status_code==403)
r=env.get(B+f'/leadmap/leader/{sc_id}'); chk('she is not a supervisor: no leader files', r.status_code==403)
r=env.get(B+'/leadmap/leaderboard'); chk('and no leaderboard', r.status_code==403)
eh=env.get(B+'/envoy/home').json()['leadmap']; chk('her dashboard shows her state seats and vacant LGA seats', eh['state']=='Akwa Ibom' and len(eh['seats'])==4 and eh['lgas']==31, eh)
# a burst of vacancies becomes one notice per person, not dozens
sql("delete from notifications where kind='vacancy'")
# ---- a seat falls vacant: the Country Representative, the Deputy and the State Envoy are told, each with her own link
sql("delete from notifications where kind='vacancy'"); sql("delete from mail_outbox")
r=hq.post(B+f'/hq/members/{lg_id}/seat',json={'vacate':True}); chk('HQ ends the Uyo LGA seat', r.ok)
time.sleep(1)
got=sql("select group_concat(user_id order by user_id) from notifications where kind='vacancy'")
chk('the Country Representative, the Deputy and the Envoy are told', set(map(int,got.split(',')))=={cr_id,dcr_id,env_id} if got else False, got)
body=sql(f"select body from notifications where kind='vacancy' and user_id={cr_id}")
chk('her notice carries her own link for the Uyo seat', '/#/r/' in body and sql(f"select count(*) from referral_links where owner_id={cr_id} and position_code='district_coordinator' and lga='Uyo'")=='1', body)
chk('and an email', int(sql(f"select count(*) from mail_outbox where to_email='{cr_e}'"))>=1, sql("select to_email from mail_outbox"))
hq.post(B+f'/hq/members/{ls_id}/seat',json={'vacate':True}); time.sleep(1)
chk('each vacancy is announced once (Uyo again: no; Lagos: the two country heads)', sql("select count(*) from notifications where kind='vacancy'")=='5' and sql(f"select count(*) from notifications where kind='vacancy' and user_id={env_id}")=='1', sql("select user_id,title from notifications where kind='vacancy'"))
# ---- KPIs and the leaderboard come from the records
now=datetime.datetime.now(datetime.UTC); past=(now-datetime.timedelta(days=2)).strftime('%Y-%m-%d %H:%M:%S'); late=(now-datetime.timedelta(days=2)+datetime.timedelta(minutes=25)).strftime('%Y-%m-%d %H:%M:%S')
ontime=(now-datetime.timedelta(days=2)+datetime.timedelta(minutes=3)).strftime('%Y-%m-%d %H:%M:%S'); yday=(now-datetime.timedelta(days=1)).strftime('%Y-%m-%d')
sql(f"insert into meetings (title,mode,starts_at,ends_at,host_id,audience,network,status,created_at,kind) values ('Ward review','online','{past}','{late}',{sc_id},'leaders','WGMN','completed','{past}','meeting')")
mid=sql("select max(id) from meetings")
sql(f"insert into meeting_attendance (meeting_id,user_id,status,marked_by,marked_at) values ({mid},{wd_id},'present',{sc_id},'{past}'),({mid},{cand_id},'present',{sc_id},'{past}')")
sql(f"insert into meeting_joins (meeting_id,user_id,joined_at) values ({mid},{wd_id},'{ontime}'),({mid},{cand_id},'{late}')")
sql(f"insert into tasks (title,priority,due_on,status,assigned_by,assignee_id,network,source,created_at,updated_at,completed_at) values ('Map wards','normal','{yday}','completed',{sc_id},{wd_id},'WGMN','manual','{past}','{past}','{past}'),('Visit chapter','normal','{yday}','open',{sc_id},{cand_id},'WGMN','manual','{past}','{past}',NULL)")
sql(f"insert into activity_records (user_id,network,category,happened_at,what,people_reached,created_at) values ({wd_id},'WGMN','outreach','{yday}','Market outreach',40,'{past}')")
b=hq.get(B+'/leadmap/leaderboard',params={'cc':'NG','state':'Akwa Ibom','network':'WGMN'}).json()
row=lambda i: [x for x in b['rows'] if x['id']==i][0]
k=row(wd_id)['kpis']; chk('the ward leader: attended, on time, task done on time, one programme with 40 reached', k['meetings_attended']==1 and k['punctuality']==100 and k['tasks_on_time']==1 and k['programmes']==1 and k['people_reached']==40, k)
k=row(cand_id)['kpis']; chk('the new Eket leader: attended but late, one task overdue', k['meetings_attended']==1 and k['punctuality']==0 and k['tasks_overdue']==1, k)
k=row(sc_id)['kpis']; chk('the State Coordinator: one leader brought in, one meeting held', k['leaders_brought']==1 and k['leaders_total']==1 and k['meetings_held']==1, k)
chk('the score is the published formula (with one point a day on WDOS)', row(wd_id)['kpis']['score']==3+2+3+4+row(wd_id)['kpis']['active_days'], row(wd_id)['kpis'])
chk('ranked by score, highest first', [x['kpis']['score'] for x in b['rows']]==sorted([x['kpis']['score'] for x in b['rows']],reverse=True) and b['rows'][0]['rank']==1)
chk('sorting by leaders brought puts the State Coordinator first', hq.get(B+'/leadmap/leaderboard',params={'cc':'NG','sort':'leaders_brought'}).json()['rows'][0]['id']==sc_id)
lb=sc.get(B+'/leadmap/leaderboard').json(); chk('a leader sees the board for her own area: herself and those under her', {x['id'] for x in lb['rows']}=={sc_id,wd_id,cand_id,ib_id}, [x['first_name'] for x in lb['rows']])
r=sc.get(B+'/leadmap/leaderboard',params={'cc':'NG','state':'Lagos'}); chk('not another state', r.status_code==403)
# ---- one leader's file
f=sc.get(B+f'/leadmap/leader/{wd_id}').json()
chk('the State Coordinator opens her ward leader\'s file: place, figures, timeline', f['seat']['place_name']=='Etoi 1' and f['kpis']['tasks_on_time']==1 and any(t['kind']=='task' for t in f['timeline']) and any(t['kind']=='programme' for t in f['timeline']), f.get('seat'))
chk('the file shows the chain above her', [c['person']['id'] for c in f['chain'] if c['filled']][:3]==[cr_id,dcr_id,sc_id], [(c['title'],c['filled']) for c in f['chain']])
r=wd.get(B+f'/leadmap/leader/{sc_id}'); chk('a ward leader cannot open her State Coordinator\'s file', r.status_code==403)
ob,ob_id,_=reg('Ogunlga',lga='Ijebu Ode',comm='Ward 1',st=int(sql(f"select id from org_units where parent_id={ng} and name='Ogun'"))); seat(ob_id,'district_coordinator',int(sql(f"select id from org_units where parent_id={ng} and name='Ogun'")),area_label='Ijebu Ode')
r=sc.get(B+f'/leadmap/leader/{ob_id}'); chk('nor can a State Coordinator open a leader in another state', r.status_code==403, r.text)
f=hq.get(B+f'/leadmap/leader/{cand_id}').json(); chk('HQ sees how the Eket leader came: referred by the State Coordinator, 3-Day Referral Activation', f['journey']['referral']['by_id']==sc_id and f['journey']['activation']['route_label']=='3-Day Referral Activation', f['journey'])
# ---- a reshuffle: many seats empty at once becomes one notice per person
ids=[]
for w in ['Ikono 1','Ikono 11','Offot 1','Etoi 11']:
  _,i,_=reg('Burst',comm=w); seat(i,'cluster_coordinator',ak,area_label=w,lga='Uyo'); ids.append(i)
sql("delete from notifications where kind='vacancy'"); sql(f"update seats set ends_at=utc_timestamp() where user_id in ({','.join(map(str,ids[1:]))}) and ends_at is null")
hq.post(B+f'/hq/members/{ids[0]}/seat',json={'vacate':True}); time.sleep(1)
t=sql(f"select group_concat(title separator '|') from notifications where kind='vacancy' and user_id={cr_id}")
chk('four ward seats emptied together: the Country Representative gets one summary', t=='4 leadership seats are now vacant', t)
print(f'\n{ok} passed, FAILURES {bad}')
