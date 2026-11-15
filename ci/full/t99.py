import requests, subprocess, json
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
hq=S('hq@test.local','hq-password-1')
cs=hq.get(B+'/geo/countries').json()['countries']; ng=[c for c in cs if c['code']=='NG'][0]['id']
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'Federal' in u['name']][0]
n=[300]
def reg(net='WGMN', community='Maitama', lga='Abuja Municipal Area Council', **kw):
    n[0]+=1; sql('delete from login_attempts'); s=S(); dob='1985-03-04' if net=='WGMN' else '2002-03-04'
    b={'network':net,'first_name':'Mait','last_name':f'M{n[0]}','dob':dob,'phone':f'+23490{10000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':lga,'community':community,'password':'246810','consent':True,'email':f'm{n[0]}@example.org'}
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r
s1,r=reg(); u1=r.json()['user']; cid=u1['chapter']['id']
c=hq.get(B+f'/chapters/{cid}').json()['chapter']; chk('readable chapter code per the specification', c['code']=='WGMN-NG-FCT-AMAC-MAIT-001' and c['name']=='Maitama WGMN 001', c['code'])
chk('saved under Nigeria, FCT, AMAC, Maitama', (c['country'],c['state_region'],c['lga'],c['community'])==('Nigeria',fct['name'],'Abuja Municipal Area Council','Maitama'), c)
chk('member told her chapter', sql(f"select count(*) from notifications where user_id={u1['id']} and title='You have been placed in a chapter'")=='1')
chk('cluster recorded as master data', sql("select count(*) from clusters where name='Maitama' and country_code='NG'")=='1' and 'Maitama' in requests.get(B+'/geo/clusters?country=NG&lga=Abuja Municipal Area Council',headers=H).json()['clusters'])
sw,r=reg(net='WNNN'); uw=r.json()['user']; chk('WNNN chapter separate and coded', uw['chapter']['code']=='WNNN-NG-FCT-AMAC-MAIT-001')
# leader told of a new member
lid=u1['id']; hq.post(B+f'/hq/members/{lid}/seat',json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':cid,'network':'WGMN'})
s2,r=reg(); u2=r.json()['user']; chk('chapter leader told of a new member', sql(f"select count(*) from notifications where user_id={lid} and title='A new member joined your chapter'")=='1')
# own target capacity + threshold alert + at capacity
r=hq.post(B+f'/chapters/{cid}/edit',json={'target_capacity':'3','name':'Maitama WGMN 001'}); chk('capacity below minimum refused', r.status_code==422, r.text)
hq.post(B+'/policy',json={'key':'chapter_min_active','value':'5'}); hq.post(B+'/policy',json={'key':'chapter_plan_next','value':'5'})
r=hq.post(B+f'/chapters/{cid}/edit',json={'target_capacity':'6','catchment':'Maitama district, north of the river'}); chk('chapter own target capacity and catchment', r.ok and hq.get(B+f'/chapters/{cid}').json()['chapter']['capacity']==6, r.text)
for i in range(4): reg()
st=hq.get(B+f'/chapters/{cid}').json()['chapter']; chk('6 of 6 is at capacity', st['members']==6 and st['status']=='full', st)
chk('HQ alerted at thresholds', sql("select count(*) from notifications where title in ('Chapter ready for activation','Chapter at capacity','Plan the next chapter')")>='2', sql("select title,count(*) from notifications where kind='reminder' group by title"))
s7,r=reg(); u7=r.json()['user']; chk('next member goes to 002, registration not refused', r.ok and u7['chapter']['code']=='WGMN-NG-FCT-AMAC-MAIT-002', r.text)
# transfer into full: needs reason; exception flag; history keeps reason and approver
chk('full chapter refuses a plain transfer', hq.post(B+f'/chapters/{cid}/transfer',json={'user_id':u7['id']}).status_code==422)
chk('override needs a written reason', hq.post(B+f'/chapters/{cid}/transfer',json={'user_id':u7['id'],'allow_full':True}).status_code==422)
r=hq.post(B+f'/chapters/{cid}/transfer',json={'user_id':u7['id'],'allow_full':True,'reason':'Sister of a member, same compound'}); chk('override with reason', r.ok, r.text)
st=hq.get(B+f'/chapters/{cid}').json()['chapter']; chk('visible capacity exception flag', st['capacity_exception'] and st['members']==7, st)
hst=hq.get(B+f"/members/{u7['id']}/chapters").json()['items']; chk('history keeps old and new chapter, dates, reason, approver', len(hst)==2 and hst[0]['reason']=='Sister of a member, same compound' and 'Approved by' in hst[0]['approval_ref'] and hst[1]['end_reason']=='transfer' and hst[1]['ended_at'], hst)
chk('member sees own history only', s7.get(B+f"/members/{u7['id']}/chapters").ok and s7.get(B+f"/members/{u1['id']}/chapters").status_code==403)
chk('exception logged', sql("select count(*) from activity_log where action='capacity_exception'")=='1')
# inactive member frees the seat, reinstated is placed again
hq.post(B+f"/hq/members/{u2['id']}",json={'status':'suspended'}); chk('inactive member does not hold a seat', hq.get(B+f'/chapters/{cid}').json()['chapter']['members']==6 and sql(f"select end_reason from chapter_members where user_id={u2['id']} and ended_at is not null")=='inactive')
hq.post(B+f"/hq/members/{u2['id']}",json={'status':'active'}); chk('reinstated member is placed again', sql(f"select chapter_id is not null from users where id={u2['id']}")=='1')
# reconcile
d=hq.get(B+'/chapters?network=WGMN&community=Maitama').json(); rc=d['reconcile']; chk('totals reconcile for a filter', rc['balanced'] and rc['total']==rc['assigned']+rc['pending']+rc['inactive']+rc['other'] and rc['total']==7, rc)
chk('distribution, sign-ups, transfers, needs present', len(d['distribution'])==6 and d['transfers_30d']>=1 and rc['signups_30d']==7 and 'needs' in d and 'suspended' in d['summary'], d.get('transfers_30d'))
# overlap warning on manual create, duplicate name warning, orphan guard
b={'network':'WGMN','country':'NG','state_region':fct['name'],'lga':'Abuja Municipal Area Council','community':'Maitama'}
r=hq.post(B+'/chapters',json=b); chk('warns before overlapping chapter', r.status_code==409 and 'still has room' in r.json()['message'], r.text)
r=hq.post(B+'/chapters',json={**b,'confirm':True}); c3=r.json()['id']; chk('opens after confirmation', r.ok and hq.get(B+f'/chapters/{c3}').json()['chapter']['code']=='WGMN-NG-FCT-AMAC-MAIT-003')
chk('duplicate name warning', hq.post(B+f'/chapters/{c3}/edit',json={'name':'Maitama WGMN 001'}).status_code==409)
chk('no orphan chapter', hq.post(B+'/chapters',json={**b,'country':'ZZ'}).status_code==422)
# suspend / reopen / close
chk('suspend needs a reason', hq.post(B+f'/chapters/{c3}/status',json={'to':'suspended'}).status_code==422)
chk('suspend', hq.post(B+f'/chapters/{c3}/status',json={'to':'suspended','note':'No venue this quarter'}).ok and hq.get(B+f'/chapters/{c3}').json()['chapter']['status']=='suspended')
chk('suspended chapter takes no transfer', hq.post(B+f'/chapters/{c3}/transfer',json={'user_id':u7['id']}).status_code==422)
chk('reopen', hq.post(B+f'/chapters/{c3}/status',json={'to':'open'}).ok and hq.get(B+f'/chapters/{c3}').json()['chapter']['status']=='developing')
c2=sql("select id from chapters where code='WGMN-NG-FCT-AMAC-MAIT-002'"); m2=sql(f"select user_id from chapter_members where chapter_id={c2} and ended_at is null limit 1")
r=hq.post(B+f'/chapters/{c2}/status',json={'to':'closed','note':'Merged into the neighbourhood chapter'}); chk('close keeps history and re-places members', r.ok and sql(f"select status from chapters where id={c2}")=='closed' and sql(f"select count(*) from chapter_members where chapter_id={c2}")>='1' and sql(f"select chapter_id is not null and chapter_id<>{c2} from users where id={m2}")=='1', r.text)
# pending queue and manual place
sp,r=reg(lga='',community=''); up=r.json()['user']
q=hq.get(B+'/chapters/pending?kind=no_location').json(); chk('pending queue shows who and why', any(x['id']==up['id'] and 'LGA' in x['reason'] and not x['placeable'] for x in q['items']), q['items'][:2])
chk('place now explains when it cannot', hq.post(B+f"/chapters/place/{up['id']}",json={}).status_code==422)
chk('member cannot open the queue', s1.get(B+'/chapters/pending').status_code==403)
# export logged and permissioned
L=S('m301@example.org','246810'); r=L.get(B+f'/chapters/{cid}/export'); chk('chapter leader exports own roster', r.ok and 'Membership no.' in r.text and r.text.count('\n')>=7, r.text[:200])
chk('export logged', sql("select count(*) from activity_log where action='roster_exported'")=='1')
chk('member cannot export', s7.get(B+f'/chapters/{cid}/export').status_code==403)
chk('leader cannot export another chapter', L.get(B+f'/chapters/{c3}/export').status_code==403)
# cluster leader sees all chapters of the cluster, chapter leader only own
hq.post(B+f"/hq/members/{u7['id']}/seat",json={'position':'cluster_coordinator','org_unit_id':fct['id'],'area_label':'Maitama','network':'WGMN'})
chk('cluster coordinator sees every WGMN chapter in the cluster', s7.get(B+'/chapters').json()['total']==2 and s7.get(B+f'/chapters/{c3}').ok, s7.get(B+'/chapters').json()['total'])
chk('cluster coordinator cannot see the WNNN chapter', s7.get(B+f"/chapters/{uw['chapter']['id']}").status_code==403)
chk('chapter leader sees own only', L.get(B+'/chapters').json()['total']==1)
# clusters master data
r=hq.post(B+'/clusters',json={'country':'NG','state_region':fct['name'],'lga':'Bwari','name':'Kubwa','official_ward':'Kubwa Ward','catchment':'Kubwa and Byazhin'}); chk('HQ adds a Community Cluster with its official ward', r.ok, r.text)
chk('duplicate cluster refused', hq.post(B+'/clusters',json={'country':'NG','lga':'Bwari','name':'Kubwa'}).status_code==409)
cl=hq.get(B+'/clusters?country=NG').json()['items']; mk=[x for x in cl if x['name']=='Maitama'][0]; chk('cluster directory with chapter counts', mk['chapters']>=2 and mk['members']>=6, mk)
chk('cluster edit', hq.post(B+'/clusters',json={'id':mk['id'],'official_ward':'Maitama Ward (to verify)','catchment':'Maitama'}).ok)
# zones
r=hq.post(B+'/zones',json={'name':'Maitama North','chapter_ids':[cid,c3],'supervisor_id':u2['id']}); z=r.json().get('id'); chk('zone groups chapters under a supervisor', r.ok and hq.get(B+f'/chapters/{cid}').json()['zone']['name']=='Maitama North', r.text)
chk('zone must be one network', hq.post(B+'/zones',json={'name':'Mixed','chapter_ids':[cid,uw['chapter']['id']]}).status_code==422)
s2=S('m303@example.org','246810')
chk('supervisor sees zone chapters without a leadership seat', s2.get(B+'/chapters').json()['total']==2 and s2.get(B+f'/chapters/{c3}').json()['access']=='leader' and s2.get(B+'/me').json()['user']['zone_supervisor'])
chk('end zone', hq.post(B+f'/zones/{z}',json={'end':True}).ok and s2.get(B+'/chapters').status_code==403)
p=hq.get(B+'/policy').json(); chk('decisions register now has eight items', len(p['decisions'])==14, len(p['decisions']))
hq.post(B+'/policy',json={'key':'chapter_min_active','value':'30'}); hq.post(B+'/policy',json={'key':'chapter_plan_next','value':'40'})
print('FAILURES', bad)
