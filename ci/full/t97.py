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
cs=hq.get(B+'/geo/countries').json()['countries']; ng=[c for c in cs if c['code']=='NG'][0]['id']; us=[c for c in cs if c['code']=='US']
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'FCT' in u['name'] or 'Abuja' in u['name'] or 'Federal' in u['name']][0]
n=[0]
def reg(net='WGMN', community='Maitama', lga='AMAC', email=True, **kw):
    n[0]+=1; sql('delete from login_attempts'); s=S(); dob='1985-03-04' if net=='WGMN' else '2002-03-04'
    b={'network':net,'first_name':'Ada','last_name':f'Test{n[0]}','dob':dob,'phone':f'+23480{10000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':lga,'community':community,'password':'246810','consent':True,'lang':'sw'}
    if email: b['email']=f'a{n[0]}@example.org'
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r
# policy
p=hq.get(B+'/policy').json(); chk('policy lists rules', len(p['rules'])==22 and len(p['decisions'])==14 and [r for r in p['rules'] if r['key']=='chapter_capacity'][0]['value']=='50', p)
chk('policy rejects bad', hq.post(B+'/policy',json={'key':'chapter_capacity','value':'10'}).status_code==422)
chk('policy open unlock', hq.post(B+'/policy',json={'key':'journey_unlock','value':'open','note':'test'}).ok)
# registration without email or OTP, PIN
chk('v103: registration without a phone number is refused, and names the field', (lambda x: x.status_code==422 and x.json()['error']=='phone')(reg(phone='')[1]))
chk('v103: a phone number that is too short is refused', reg(phone='12345')[1].status_code==422)
chk('nothing was created by the refused attempts', sql("select count(*) from users where last_name like 'Test%'")=='0'); n[0]=0
s1,r=reg(email=False); u1=r.json().get('user',{}); chk('v106: a woman with no email registers with her phone number alone, 6-digit PIN, no OTP', r.ok and u1.get('email_is_phone') and u1.get('phone')=='+2348010000001', r.text)
chk('first member is placed in the first chapter and day 1 is done', r.ok and u1.get('email_is_phone') and u1.get('chapter') and u1['chapter']['name']=='Maitama WGMN 001' and u1['journey']['done']==1, r.text)
chk('weak PIN refused', reg(password='111111')[1].status_code==422)
lg=S(); r=lg.post(B+'/auth/login',json={'email':'+2348010000001','password':'246810'}); chk('she signs in with her phone number', r.ok, r.text)
chk('lang saved', sql(f"select lang from users where id={u1['id']}")=='sw')
# WNNN same community -> separate chapter
s2,r=reg(net='WNNN'); u2=r.json()['user']; chk('WNNN never in WGMN chapter', u2['chapter']['name']=='Maitama WNNN 001' and u2['chapter']['id']!=u1['chapter']['id'], u2.get('chapter'))
# fill chapter to 50: 49 more
for i in range(49): reg()
chk('chapter 001 has 50 and is full', sql(f"select members,status from chapters where id={u1['chapter']['id']}")=='50\tfull', sql(f"select members,status from chapters where id={u1['chapter']['id']}"))
s51,r=reg(); u51=r.json()['user']; chk('51st registers and goes to 002', r.ok and u51['chapter']['name']=='Maitama WGMN 002', r.text)
# auto create off -> pending not refused
hq.post(B+'/policy',json={'key':'chapter_auto_create','value':'off'})
sp,r=reg(community='Wuse'); up=r.json().get('user',{}); chk('no room: registered with pending placement', r.ok and up.get('chapter') is None, r.text)
mc=sp.get(B+'/my/chapter').json(); chk('pending reason shown', mc['chapter'] is None and 'full' in mc['pending'], mc)
hq.post(B+'/policy',json={'key':'chapter_auto_create','value':'on'})
mc=sp.get(B+'/my/chapter').json(); chk('placed once a chapter can open', mc['chapter'] and mc['chapter']['name']=='Wuse WGMN 001', mc)
sn,r=reg(lga='',community=''); chk('no location: pending with guidance', r.ok and r.json()['user']['chapter'] is None and 'LGA' in sn.get(B+'/my/chapter').json()['pending'], r.text)
# 1000 members -> 20 chapters by backfill
sql("insert into users (email,first_name,last_name,phone,dob,network,country_code,org_unit_id,state_region,lga,community,role,status,created_at) select concat('bulk',seq,'@example.org'),'Bulk',concat('M',seq),'', '1980-01-01','WGMN','NG',%d,'%s','Bwari','Kubwa','member','active',now() from seq_1_to_1000" % (fct['id'], fct['name'].replace("'","''")))
tot=0
for i in range(12):
    r=hq.post(B+'/chapters/backfill').json(); tot+=r['placed']
    if r['left']==0: break
k=sql("select count(*), sum(members), min(members), max(members) from chapters where community='Kubwa' and network='WGMN'")
chk('1000 members in 20 chapters of 50', k=='20\t1000\t50\t50', k)
chk('totals reconcile', sql("select count(*) from users where community='Kubwa' and chapter_id is not null")=='1000' and sql("select count(*) from chapter_members m join chapters c on c.id=m.chapter_id where c.community='Kubwa' and m.ended_at is null")=='1000')
# directory + tree
d=hq.get(B+'/chapters?community=Kubwa').json(); chk('directory', d['total']==20 and d['summary']['full']==20 and 'pending' in d, d['summary'])
t=hq.get(B+'/chapters/tree').json(); chk('tree country', t['level']=='country' and t['rows'][0]['label']=='Nigeria', t)
t=hq.get(B+'/chapters/tree?country=NG&state='+fct['name']).json(); chk('tree lga level', t['level']=='lga' and any(r['label']=='Bwari' and r['members']==1000 for r in t['rows']), t)
# journey
j=s1.get(B+'/journey').json(); chk('journey state', j['journey']['done']==1 and len(j['days'])==7 and j['referrals']['code'] and j['chapter']['name']=='Maitama WGMN 001', j.get('journey'))
r=s1.post(B+'/journey/7').json(); chk('early final check lists what is missing', r['result']['status']=='retry' and len(r['result']['reasons'])==5, r.get('result'))
for d_ in (2,3,4,6): s1.post(B+f'/journey/{d_}',json={})
chk('screening needs all answers', s1.post(B+'/journey/5',json={'answers':{'own_name':'yes'}}).status_code==422)
s1.post(B+'/journey/5',json={'answers':{'own_name':'yes','location':'no','conduct':'yes','only_account':'yes','expect':'service'}})
r=s1.post(B+'/journey/7').json(); chk('wrong answer -> actionable reason, retry', r['result']['status']=='retry' and 'Settings' in r['result']['reasons'][0]['text'], r['result'])
s1.post(B+'/journey/5',json={'answers':{'own_name':'yes','location':'yes','conduct':'yes','only_account':'yes','expect':'service'}})
r=s1.post(B+'/journey/7').json(); chk('automatic activation, no staff', r['result']['status']=='active' and r['journey']['status']=='active', r['result'])
chk('no HQ approval involved', sql(f"select count(*) from activity_log where subject_id={u1['id']} and action='member_activated' and actor_id={u1['id']}")=='1')
# duplicate -> review, not removed
sd,r=reg(first_name='Ada',last_name='Test1',phone='+2348010000001'); ud=r.json().get('user')
chk('duplicate phone+dob may still register with email', r.ok, r.text)
for d_ in (2,3,4,6): sd.post(B+f'/journey/{d_}',json={})
sd.post(B+'/journey/5',json={'answers':{'own_name':'yes','location':'yes','conduct':'yes','only_account':'yes','expect':'service'}})
r=sd.post(B+'/journey/7').json(); chk('duplicate goes to exceptions, not excluded', r['result']['status']=='review' and sql(f"select status from users where id={ud['id']}")=='active', r['result'])
hj=hq.get(B+'/hq/journeys').json(); chk('HQ exceptions list', hj['summary']['review']==1 and hj['exceptions'][0]['id']==ud['id'], hj['summary'])
chk('HQ clears exception', hq.post(B+f"/hq/journeys/{ud['id']}",json={'decision':'clear','note':'sister shares phone'}).ok and sd.get(B+'/journey').json()['journey']['status']=='active')
chk('member cannot open hq journeys', s1.get(B+'/hq/journeys').status_code==403)
# referral verified
me1=s1.get(B+'/journey').json()['referrals']
sr,r=reg(referral={'code':me1['code']}); ur=r.json()['user']; chk('referred member registers', r.ok and sql(f"select referrer_id from referrals where user_id={ur['id']}")==str(u1['id']), r.text)
for d_ in (2,3,4,6): sr.post(B+f'/journey/{d_}',json={})
sr.post(B+'/journey/5',json={'answers':{'own_name':'yes','location':'yes','conduct':'yes','only_account':'yes','expect':'service'}}); sr.post(B+'/journey/7')
rf=s1.get(B+'/journey').json()['referrals']; chk('referrals tracked separately', rf['registered']==1 and rf['verified']==1, rf)
# chapter space + access
cid=u1['chapter']['id']
c=s1.get(B+f'/chapters/{cid}').json(); chk('member sees own chapter, names only', c['access']=='member' and 'phone' not in c['roster'][0] and len(c['roster'])==50, c.get('access'))
chk('WNNN member cannot open WGMN chapter', s2.get(B+f'/chapters/{cid}').status_code==403)
chk('other chapter closed to member', s51.get(B+f'/chapters/{cid}').status_code==403)
r=s1.post(B+f'/chapters/{cid}/posts',json={'body':'Hello sisters'}); pid=r.json()['id']; chk('member posts', r.ok)
chk('outsider cannot post', s2.post(B+f'/chapters/{cid}/posts',json={'body':'x y'}).status_code==403)
ms=[S() for _ in range(3)]
ids=sql(f"select email from users where chapter_id={cid} and email like 'a%@example.org' limit 4").split('\n')[1:4]
for e in ids:
    x=S(e,'246810'); x.post(B+f'/chapters/posts/{pid}/report',json={'reason':'test'})
chk('3 reports hide the post', sql(f"select hidden_at is not null from chapter_posts where id={pid}")=='1')
chk('member cannot unhide', s1.post(B+f'/chapters/posts/{pid}/hide',json={'restore':True}).status_code==403)
# chapter leader: appoint with chapter link
lid=sql(f"select id from users where chapter_id={cid} and id<>{u1['id']} limit 1"); le=sql(f"select email from users where id={lid}")
r=hq.post(B+f'/hq/members/{lid}/seat',json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':cid,'network':'WGMN'}); chk('appoint chapter coordinator', r.ok and 'Chapter Coordinator' in r.json()['title'], r.text)
L=S(le,'246810'); mm=L.get(B+'/leader/members').json(); chk('chapter leader roster = own chapter only', mm['total']==50, mm.get('total'))
c=L.get(B+f'/chapters/{cid}').json(); chk('chapter leader sees contact details and stats', c['access']=='leader' and c['full'] and 'phone' in c['roster'][0] and 'stats' in c, c.get('access'))
other=sql("select id from chapters where name='Maitama WGMN 002'")
chk('chapter leader cannot see unrelated chapter', L.get(B+f'/chapters/{other}').status_code==403)
dl=L.get(B+'/chapters').json(); chk('leader directory limited', dl['total']==1, dl['total'])
r=L.post(B+'/leader/announcements',json={'title':'Chapter meeting','body':'Saturday at 10','network':'WNNN','country':'GH'}); chk('chapter leader announces to own chapter only', r.ok and r.json()['recipients']==50, r.text)
chk('restore hidden post by leader', L.post(B+f'/chapters/posts/{pid}/hide',json={'restore':True}).ok)
a=s51.get(B+'/announcements').json()['items']; chk('other chapter does not get it', not any(x['title']=='Chapter meeting' for x in a))
a=s1.get(B+'/announcements').json()['items']; chk('own chapter gets it', any(x['title']=='Chapter meeting' for x in a))
r=hq.post(B+'/hq/announcements',json={'title':'Swahili note','body':'Habari zenu','lang':'sw','network':'WGMN'}); chk('HQ filter by language', r.ok and r.json()['recipients']>=50, r.text)
# he-for-she
hid=sql(f"select id from users where chapter_id={cid} and id not in ({u1['id']},{lid}) limit 1"); he=sql(f"select email from users where id={hid}")
chk('he-for-she cannot be principal', hq.post(B+f'/hq/members/{hid}/seat',json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':cid,'he_for_she':True}).status_code==422)
r=hq.post(B+f'/hq/members/{hid}/seat',json={'position':'chapter_secretary','org_unit_id':fct['id'],'chapter_id':cid,'he_for_she':True,'network':'WGMN'}); chk('he-for-she as administrative secretary', r.ok, r.text)
Hs=S(he,'246810'); chk('he-for-she has no personal roster', Hs.get(B+'/leader/members').status_code==403)
me=Hs.get(B+'/me').json()['user']; chk('he-for-she flagged, not a chapter member', me['he_for_she'] and me['chapter'] is None, me)
chk('he-for-she can use tasks and meetings', Hs.get(B+'/tasks').ok and Hs.get(B+'/meetings').ok)
c=Hs.get(B+f'/chapters/{cid}').json(); chk('he-for-she sees names only', c['access']=='leader' and not c['full'] and 'phone' not in c['roster'][0], c.get('full'))
# transfer / merge / split keep history
r=hq.post(B+f'/chapters/{other}/transfer',json={'user_id':u1['id']}); chk('transfer', r.ok and sql(f"select chapter_id from users where id={u1['id']}")==other, r.text)
chk('history kept', sql(f"select count(*) from chapter_members where user_id={u1['id']}")=='2' and sql(f"select end_reason from chapter_members where user_id={u1['id']} and ended_at is not null")=='transfer')
chk('cross-network transfer refused', hq.post(B+f"/chapters/{u2['chapter']['id']}/transfer",json={'user_id':u1['id']}).status_code==422)
w=sql("select id from chapters where name='Wuse WGMN 001'")
r=hq.post(B+f'/chapters/{w}/merge',json={'into':other}); chk('merge', r.ok and sql(f"select status,merged_into from chapters where id={w}")==f'merged\t{other}', r.text)
k1=sql("select id from chapters where community='Kubwa' order by seq limit 1"); ids=[int(x) for x in sql(f"select id from users where chapter_id={k1} limit 10").split('\n')]
r=hq.post(B+f'/chapters/{k1}/split',json={'user_ids':ids}); chk('split', r.ok and r.json()['moved']==10 and sql(f"select members from chapters where id={k1}")=='40' and sql(f"select name,members from chapters where id={r.json()['id']}")=='Kubwa WGMN 021\t10', r.text)
chk('plan-next alert at 40', [c for c in hq.get(B+'/chapters?community=Kubwa&status=active').json()['items'] if c['id']==int(k1)][0]['plan_next'])
# location change re-places
r=s51.post(B+'/me',json={'lga':'Bwari','community':'Kubwa'}); chk('moving community re-places automatically', r.ok and r.json()['user']['chapter']['name'].startswith('Kubwa WGMN'), r.text)
# positions/titles
chk('titles per source', sql("select title_wgmn,title_wnnn from positions where code='chapter_lead'")=='Chapter Coordinator\tChapter Lead' and sql("select title_wgmn,title_wnnn from positions where code='city_coordinator'")=='City Coordinator\tCity Lead')
hq.post(B+'/policy',json={'key':'wnnn_country_title','value':'Country Representative'}); chk('WNNN title decision applies', sql("select title_wnnn from positions where code='deputy_country_rep'")=='Deputy Country Representative')
hq.post(B+'/policy',json={'key':'wnnn_country_title','value':'Country Lead'})
chk('delivery states', 'push' in hq.get(B+'/hq/delivery').json())
chk('policy history', len(hq.get(B+'/policy').json()['history'])>=15)
hq.post(B+'/policy',json={'key':'journey_unlock','value':'daily'})
sx,r=reg(); chk('daily unlock locks day 3 on day 1', sx.post(B+'/journey/3',json={}).status_code==422 and sx.get(B+'/journey').json()['journey']['open_days']==1)
print('FAILURES', bad)
