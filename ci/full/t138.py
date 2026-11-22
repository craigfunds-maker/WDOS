# v109.47: region rooms (group chat and forum with links, documents, photos, videos), who may open which room, online status and
# most active leaders on the leaderboard. Synthetic data only.
import requests, subprocess, time, base64
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
ng=int(sql("select id from org_units where level='country' and country_code='NG'")); ak=int(sql(f"select id from org_units where parent_id={ng} and name='Akwa Ibom'")); la=int(sql(f"select id from org_units where parent_id={ng} and name='Lagos'"))
n=[0]
def reg(first, lga='Uyo', comm='Etoi 1', st=None, net='WGMN'):
  n[0]+=1; sql("delete from login_attempts"); s=S(); e=f'{first.lower()}{n[0]}.{X}@example.org'
  r=s.post(B+'/auth/register',json={'network':net,'first_name':first,'last_name':'Rm'+X,'dob':'2001-03-04' if net=='WNNN' else '1985-03-04','phone':f'+234817{X}{n[0]:02d}','country_id':ng,'state_id':st or ak,'lga':lga,'community':comm,'password':'member-pass-1','consent':True,'email':e,'lang':'en'})
  assert r.ok, r.text[:300]; return s, r.json()['user']['id']
def seat(uid,pos,unit,net='WGMN',**kw):
  r=hq.post(B+f'/hq/members/{uid}/seat',json={'position':pos,'org_unit_id':unit,'network':net,**kw}); assert r.ok, r.text; sql(f"update seats set acceptance='accepted' where user_id={uid} and ends_at is null")
sc,sc_id=reg('Rstate'); seat(sc_id,'state_coordinator',ak)
lg,lg_id=reg('Rlga'); seat(lg_id,'district_coordinator',ak,area_label='Uyo')
ek,ek_id=reg('Reket',lga='Eket'); seat(ek_id,'district_coordinator',ak,area_label='Eket')
ls,ls_id=reg('Rlagos',lga='Ikeja',comm='Alausa',st=la); seat(ls_id,'state_coordinator',la)
wn,wn_id=reg('Rwnnn',net='WNNN'); seat(wn_id,'state_coordinator',ak,net='WNNN')
mem,mem_id=reg('Rmember')
AK={'network':'WGMN','cc':'NG','state':'Akwa Ibom'}; UYO={**AK,'lga':'Uyo'}; EKET={**AK,'lga':'Eket'}; NGR={'network':'WGMN','cc':'NG'}
mine=lg.get(B+'/rooms').json()['rooms']
chk('an LGA Coordinator\'s rooms: Nigeria, Akwa Ibom, Uyo (her own line)', [r['name'] for r in mine]==['Nigeria','Akwa Ibom','Uyo'], [r['name'] for r in mine])
r=lg.get(B+'/rooms/room',params=AK).json()['room']
chk('the Akwa Ibom WGMN room has the leaders serving in Akwa Ibom, WGMN only', {m['id'] for m in r['members']}=={sc_id,lg_id,ek_id}, [m['first_name'] for m in r['members']])
chk('it counts who is online now', r['online']>=2, r['online'])
chk('she cannot open the Eket room beside hers', lg.get(B+'/rooms/room',params=EKET).status_code==403)
chk('nor another state', lg.get(B+'/rooms/room',params={**NGR,'state':'Lagos'}).status_code==403)
chk('nor the WNNN room', lg.get(B+'/rooms/room',params={**AK,'network':'WNNN'}).status_code==403)
chk('the State Coordinator opens the rooms under her (Eket)', sc.get(B+'/rooms/room',params=EKET).ok)
chk('a member with no seat has no region chat', mem.get(B+'/rooms').status_code==403 and mem.get(B+'/rooms/room',params=AK).status_code==403)
chk('HQ opens any room', hq.get(B+'/rooms/room',params={**NGR,'state':'Lagos'}).ok)
# chat with a link and a photo
png=base64.b64encode(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')).decode()
f=lg.post(B+'/files',json={'name':'ward-visit.png','data':'data:image/png;base64,'+png}).json()['file']
r=lg.post(B+'/rooms/chat',json={**AK,'body':'Ward visit photos, and the plan: https://example.org/plan','files':[f['id']]}); chk('she sends a chat message with a link and a photo', r.ok, r.text)
mid=r.json()['id']
c=sc.get(B+'/rooms/chat',params=AK).json()['messages']
chk('the State Coordinator reads it, with the photo attached', c[-1]['body'].startswith('Ward visit') and c[-1]['files'][0]['mime']=='image/png' and c[-1]['author']['id']==lg_id, c[-1])
chk('and can open the photo', sc.get(B+f"/files/{f['id']}").ok)
chk('a leader outside the room cannot open the photo', ls.get(B+f"/files/{f['id']}").status_code==404)
other=ls.post(B+'/files',json={'name':'x.png','data':'data:image/png;base64,'+png}).json()['file']
r=lg.post(B+'/rooms/chat',json={**AK,'body':'trying','files':[other['id']]}); chk('only her own uploads can be attached', r.ok and not [m for m in sc.get(B+'/rooms/chat',params=AK).json()['messages'] if m['id']==r.json()['id']][0]['files'])
chk('polling for new messages returns only newer ones', len(sc.get(B+'/rooms/chat',params={**AK,'after':mid}).json()['messages'])==1)
chk('an empty message is refused', lg.post(B+'/rooms/chat',json={**AK,'body':'  '}).status_code==422)
lst=sc.get(B+'/rooms').json()['rooms']; ak_room=[x for x in lst if x['name']=='Akwa Ibom'][0]
chk('her room list shows the last message', ak_room['last'] and ak_room['last']['by']=='Rlga', ak_room)
# forum
r=sc.post(B+'/rooms/forum',json={**AK,'title':'December outreach plan','body':'Share your LGA plans here.'}); chk('the State Coordinator starts a forum topic', r.ok, r.text); tid=r.json()['id']
chk('every leader in the region is told, not the writer', sql(f"select count(*) from notifications where kind='room' and user_id={lg_id}")=='1' and sql(f"select count(*) from notifications where kind='room' and user_id={sc_id}")=='0')
r=lg.post(B+f'/rooms/topic/{tid}/reply',json={'body':'Uyo plan attached','files':[f['id']]}); chk('the LGA Coordinator replies with a document', r.ok, r.text)
t=sc.get(B+f'/rooms/topic/{tid}').json(); chk('the topic shows the reply and its file', len(t['replies'])==1 and t['replies'][0]['files'] and t['topic']['replies']==1, t)
chk('a leader of another state cannot read the topic', ls.get(B+f'/rooms/topic/{tid}').status_code==403)
fr=lg.get(B+'/rooms/forum',params=AK).json()['topics']; chk('the forum lists the topic with its reply count', fr[0]['title']=='December outreach plan' and fr[0]['replies']==1)
# removing
r=ek.post(B+f'/rooms/msg/{mid}/remove'); chk('another member cannot remove someone else\'s message', r.status_code==403)
r=sc.post(B+f'/rooms/msg/{mid}/remove'); chk('the State Coordinator (leader of the region) can', r.ok)
chk('removed messages leave the room', all(m['id']!=mid for m in sc.get(B+'/rooms/chat',params=AK).json()['messages']))
# envoy rooms
r=hq.post(B+'/hq/envoys',json={'first_name':'Renvoy','last_name':'T'+X,'email':f'renv.{X}@example.org','phone':'','country_code':'NG'}); eid=int(sql(f"select id from users where email='renv.{X}@example.org'"))
sql(f"update envoys set state_region='Akwa Ibom' where user_id={eid}"); sql(f"insert ignore into envoy_acceptances (user_id,accepted_at) values ({eid}, utc_timestamp())")
env=login(f'renv.{X}@example.org', r.json()['temporary_password'])
er=env.get(B+'/rooms').json()['rooms']; chk('the State Envoy has the Nigeria and Akwa Ibom rooms of both networks', sorted((x['network'],x['name']) for x in er)==[('WGMN','Akwa Ibom'),('WGMN','Nigeria'),('WNNN','Akwa Ibom'),('WNNN','Nigeria')], [(x['network'],x['name']) for x in er])
chk('but not the LGA rooms', env.get(B+'/rooms/room',params=UYO).status_code==403)
chk('she is a member of the Akwa Ibom room', any(m['id']==eid for m in sc.get(B+'/rooms/room',params=AK).json()['room']['members']))
# most active
sql(f"insert ignore into user_days (user_id,day) values ({ek_id}, utc_date()), ({ek_id}, utc_date()-interval 1 day), ({ek_id}, utc_date()-interval 2 day), ({ek_id}, utc_date()-interval 3 day)")
b=hq.get(B+'/leadmap/leaderboard',params={'cc':'NG','state':'Akwa Ibom','network':'WGMN','sort':'active_days'}).json()
chk('the leaderboard counts active days (one row a day on WDOS) and sorts by them', b['rows'][0]['id']==ek_id and b['rows'][0]['kpis']['active_days']==4, [(x['first_name'],x['kpis']['active_days']) for x in b['rows']])
chk('active days count in the score (1 point a day)', b['rows'][0]['kpis']['score']>=4 and b['score_rules']['active_days']==1)
chk('signing in records today', sql(f"select count(*) from user_days where user_id={lg_id} and day=utc_date()")=='1')
print(f'\n{ok} passed, FAILURES {bad}')
