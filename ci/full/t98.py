import requests, subprocess, json, time
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
cs=hq.get(B+'/geo/countries').json()['countries']; ng=[c for c in cs if c['code']=='NG'][0]['id']; dia=[c for c in cs if c['code'] in ('US','GB','CA')]
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'Federal' in u['name'] or 'FCT' in u['name']][0]
n=[100]
def reg(net='WGMN', community='Asokoro', lga='AMAC', lang='sw', **kw):
    n[0]+=1; sql('delete from login_attempts'); s=S(); dob='1985-03-04' if net=='WGMN' else '2002-03-04'
    b={'network':net,'first_name':'Zuri','last_name':f'T{n[0]}','dob':dob,'phone':f'+23470{10000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':lga,'community':community,'password':'246810','consent':True,'lang':lang,'email':f'z{n[0]}@example.org'}
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r
# language of automatic messages
s1,r=reg(ward='Asokoro Ward A'); u1=r.json()['user']
t=sql(f"select title from notifications where user_id={u1['id']} and kind='welcome'"); chk('welcome in Swahili', t.startswith('Karibu WGMN'), t)
chk('ward kept apart from community', sql(f"select ward, community from users where id={u1['id']}")=='Asokoro Ward A\tAsokoro' and u1['person_id'].startswith('WDP-'))
s2,r=reg(lang='fr'); u2=r.json()['user']; s3,r=reg(lang='en'); u3=r.json()['user']
for s in (s1,):
    for d in (2,3,4,6): s.post(B+f'/journey/{d}',json={})
    s.post(B+'/journey/5',json={'answers':{'own_name':'yes','location':'yes','conduct':'yes','only_account':'yes','expect':'service'}}); s.post(B+'/journey/7')
t=sql(f"select title from notifications where user_id={u1['id']} and kind='activation'"); chk('activation message in Swahili', 'mwanachama hai' in t, t)
# announcement with translations, each member in own language
cid=u1['chapter']['id']
r=hq.post(B+'/hq/announcements',json={'title':'Chapter day','body':'We meet on Saturday.','chapter':cid,'texts':{'sw':{'title':'Siku ya tawi','body':'Tunakutana Jumamosi.'},'fr':{'title':'Journée du chapitre','body':'Nous nous réunissons samedi.'}}}); aid=r.json().get('id'); chk('announcement with translations', r.ok and r.json()['recipients']==3, r.text)
g=lambda uid: sql(f"select title from notifications where user_id={uid} and kind='announcement' order by id desc limit 1")
chk('each in her language', g(u1['id'])=='Siku ya tawi' and g(u2['id'])=='Journée du chapitre' and g(u3['id'])=='Chapter day', [g(u1['id']),g(u2['id']),g(u3['id'])])
a=s1.get(B+'/announcements').json()['items'][0]; chk('reads in own language', a['title']=='Siku ya tawi' and not a['acked'], a)
chk('acknowledge', s1.post(B+f'/announcements/{aid}/ack',json={}).ok and s1.get(B+'/announcements').json()['items'][0]['acked'])
h=[x for x in hq.get(B+'/hq/announcements').json()['items'] if x['id']==aid][0]; chk('HQ sees acknowledged count and languages', int(h['acks'])==1 and h['langs']=='fr,sw', h)
# scheduled
r=hq.post(B+'/hq/announcements',json={'title':'Later note','body':'This goes later.','chapter':cid,'scheduled_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime(time.time()+3600))}); sid=r.json()['id']; chk('schedule for later', r.ok and r.json().get('scheduled_at') and r.json()['recipients']==0, r.text)
chk('not visible before its time', not any(x['id']==sid for x in s1.get(B+'/announcements').json()['items']))
sql(f"update announcements set scheduled_at=date_sub(utc_timestamp(), interval 1 minute) where id={sid}"); sql("update app_settings set v='0' where k='auto_tick'"); hq.get(B+'/notifications/pulse'); time.sleep(3)
chk('sent automatically when due', sql(f"select sent_at is not null, recipients from announcements where id={sid}")=='1\t3', sql(f"select sent_at, recipients from announcements where id={sid}"))
chk('past schedule refused', hq.post(B+'/hq/announcements',json={'title':'Old note','body':'xxxx','scheduled_at':'2020-01-01T00:00:00Z'}).status_code==422)
# community
c=s1.get(B+'/community').json(); keys=[l['key'] for l in c['levels']]; chk('community levels to cluster', [l['level'] for l in c['levels']]==['Network','Country','State or region','LGA or district','Community or cluster'] and c['chapter'], c['levels'])
r=s1.post(B+'/community/space',json={'key':keys[1],'body':'Hello Nigeria WGMN'}); pid=r.json()['id']; chk('post in country space', r.ok)
sw,rw=reg(net='WNNN'); chk('other network cannot read WGMN space', sw.get(B+'/community/space?key='+keys[1]).status_code==403 and sw.post(B+'/community/space',json={'key':keys[1],'body':'hi there'}).status_code==403)
chk('same network reads', any(p['id']==pid for p in s2.get(B+'/community/space?key='+keys[1]).json()['posts']))
hq.post(B+'/policy',json={'key':'community_blocked_words','value':'loan, betting'})
r=s2.post(B+'/community/space',json={'key':keys[1],'body':'Quick LOAN offer here'}); chk('word filter holds post', r.ok and r.json()['held'] and not any(p['id']==r.json()['id'] for p in s3.get(B+'/community/space?key='+keys[1]).json()['posts']), r.text)
r2=s2.post(B+f'/chapters/{cid}/posts',json={'body':'betting tips'}); chk('word filter in chapter space', r2.ok and r2.json().get('held'), r2.text)
mq=hq.get(B+'/hq/moderation').json(); chk('restricted queue for HQ', len(mq['spaces'])==1 and len(mq['chapters'])==1 and int(mq['spaces'][0]['held'])==1, mq)
chk('HQ releases held post', hq.post(B+f"/community/posts/{mq['spaces'][0]['id']}/hide",json={'restore':True}).ok and len(hq.get(B+'/hq/moderation').json()['spaces'])==0)
chk('member cannot open queue', s1.get(B+'/hq/moderation').status_code==403)
r=hq.post(B+'/community/circles',json={'name':'Mothers in business','kind':'circle','network':'WGMN','about':'Trade and savings'}); cir=r.json()['id']; chk('HQ opens a topic circle', r.ok)
r=hq.post(B+'/community/circles',json={'name':'Mentorship: first-time leaders','kind':'mentorship'}); chk('mentorship space', r.ok)
chk('member cannot open a circle', s1.post(B+'/community/circles',json={'name':'My own'}).status_code==403)
chk('must join before posting', s1.post(B+'/community/space',json={'key':f'sp:{cir}','body':'hello'}).status_code==403)
chk('join and post', s1.post(B+f'/community/circles/{cir}/join',json={}).ok and s1.post(B+'/community/space',json={'key':f'sp:{cir}','body':'hello circle'}).ok)
chk('WNNN cannot join WGMN circle', sw.post(B+f'/community/circles/{cir}/join',json={}).status_code==403)
chk('circles listed with joined flag', [x for x in s1.get(B+'/community').json()['circles'] if x['id']==cir][0]['joined'])
# tasks with chapter scope and delegation
lid=u2['id']; r=hq.post(B+f'/hq/members/{lid}/seat',json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':cid,'network':'WGMN'}); L=S(f"z{102}@example.org",'246810')
r=hq.post(B+'/tasks',json={'title':'Collect chapter attendance','assignee_id':lid,'chapter_id':cid}); tid=r.json()['id']; chk('HQ sets a chapter task', r.ok and sql(f"select chapter_id from tasks where id={tid}")==str(cid), r.text)
r=L.post(B+'/tasks',json={'title':'Call ten members','assignee_id':u1['id'],'parent_id':tid}); chk('leader delegates, parent link and chapter kept', r.ok and sql(f"select parent_id, chapter_id, assignee_id from tasks where id={r.json()['id']}")==f"{tid}\t{cid}\t{u1['id']}", r.text)
tl=L.get(B+f'/tasks?chapter={cid}').json(); chk('chapter task filter', tl['total']==2 and all(t['chapter_id']==cid for t in tl['items']), tl.get('total'))
chk('member cannot use chapter filter', s1.get(B+f'/tasks?chapter={cid}').status_code==403)
chk('delegating someone else task refused', s3.post(B+'/tasks',json={'title':'xxxx','parent_id':tid}).status_code==403)
cd=L.get(B+f'/chapters/{cid}').json(); chk('chapter dashboard has tasks, attendance, announcements', cd['stats']['tasks_open']==2 and len(cd['tasks'])==2 and 'attended' in cd['stats'] and len(cd['announcements'])==2, cd.get('stats'))
# automatic report
rp=hq.get(B+'/reports/auto').json(); chk('auto report by country', rp['level']=='country' and rp['rows'][0]['label']=='Nigeria' and 'activated' in rp['rows'][0], rp['rows'][:1])
rp=hq.get(B+f"/reports/auto?country=NG&state={fct['name']}&lga=AMAC&community=Asokoro&network=WGMN").json(); row=[x for x in rp['rows'] if x['key']==str(cid)]
chk('auto report reaches chapter', rp['level']=='chapter' and row and row[0]['members']==3 and row[0]['activated']==1 and row[0]['tasks_open']==2 and row[0]['leaders']==1, rp['rows'])
rl=L.get(B+'/reports/auto').json(); chk('leader report limited to own chapter', sum(x['members'] for x in rl['rows'])==3, rl['rows'])
chk('member cannot open report', s1.get(B+'/reports/auto').status_code==403)
# capacity by country
chk('country capacity', hq.post(B+'/policy',json={'key':'chapter_capacity@NG','value':'35'}).ok and hq.get(B+f'/chapters/{cid}').json()['chapter']['capacity']==35 and hq.get(B+'/policy').json()['capacity_by_country'][0]['capacity']==35)
hq.post(B+'/policy',json={'key':'chapter_capacity@NG','value':'0'}); chk('country capacity removed', hq.get(B+f'/chapters/{cid}').json()['chapter']['capacity']==50)
# recovery
chk('leader recovery off by default', L.post(B+f"/leader/members/{u1['id']}/reset-pin",json={}).status_code==403)
hq.post(B+'/policy',json={'key':'recovery_by_leader','value':'on'})
r=L.post(B+f"/leader/members/{u1['id']}/reset-pin",json={}); pin=r.json().get('temporary_pin'); chk('chapter leader gives temporary PIN', r.ok and len(pin)==6, r.text)
x=S(); rr=x.post(B+'/auth/login',json={'email':'z101@example.org','password':pin}); chk('member signs in with it and must change', rr.ok and rr.json()['user']['must_change_password'], rr.text)
chk('logged and HQ told', sql("select count(*) from activity_log where action='pin_reset_by_leader'")=='1' and sql("select count(*) from notifications where title='A chapter leader reset a member PIN'")>='1')
o=sql("select id from users where chapter_id is not null and chapter_id<>%d limit 1" % cid); chk('not for other chapters', L.post(B+f'/leader/members/{o}/reset-pin',json={}).status_code==403)
hq.post(B+'/policy',json={'key':'recovery_by_leader','value':'off'})
# diaspora: no state needed, city chapter, tree skips state
if dia:
    d=dia[0]; sd,r=reg(country_id=d['id'],state_id=0,state_region='',lga='Houston',community=''); ud=r.json().get('user',{}); chk('diaspora registers with city and no state', r.ok and ud.get('chapter') and ud['chapter']['name']=='Houston WGMN 001' and ud['diaspora'], r.text)
    t=hq.get(B+f"/chapters/tree?country={d['code']}").json(); chk('diaspora tree skips unused state tier', t['level']=='lga' and t['skipped']=='state' and t['diaspora'] and t['rows'][0]['label']=='Houston', t)
    ck=[l['level'] for l in sd.get(B+'/community').json()['levels']]; chk('diaspora community uses City', ck==['Network','Country','City'], ck)
else: print('SKIP diaspora: no non-African country in the unit list', [c['code'] for c in cs][:8])
sx,r=reg(state_id=0,state_region=''); chk('Africa still needs a state', r.status_code==422)
# campus ambassador
r=hq.post(B+f"/hq/members/{u3['id']}/seat",json={'position':'campus_ambassador','org_unit_id':fct['id'],'area_label':'University of Abuja','network':'WGMN'}); chk('campus ambassador appointed', r.ok, r.text)
chk('campus ambassador has no roster', s3.get(B+'/leader/members').status_code==403 and s3.get(B+'/referrals/links').status_code in (200,404))
# integration and welcome media
chk('integration closed without key', requests.get(B+'/integration/person?email=z101@example.org').status_code==404)
chk('welcome media endpoint', requests.get(B+'/public/welcome',headers=H).json()=={'video_url':'','audio_url':'','transcript':''})
chk('audit list', len(hq.get(B+'/policy').json()['audit'])>5)
hq.post(B+'/policy',json={'key':'journey_unlock','value':'daily'}); hq.post(B+'/policy',json={'key':'community_blocked_words','value':''})
print('FAILURES', bad)
