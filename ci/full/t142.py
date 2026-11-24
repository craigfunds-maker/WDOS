# v109.54: on the HQ dashboard every action can be changed or deleted; staff logins can be changed and deleted (Azeez, 24 Nov 2026).
import requests, subprocess, json, datetime, os
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[3100]'))
ROOT=os.environ.get('WDOS_ROOT','/home/claude/wdos-php')
def login(e,p):
    sql('delete from login_attempts'); s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s,r
U=datetime.UTC; tt=lambda **k:(datetime.datetime.now(U)+datetime.timedelta(**k)).strftime('%Y-%m-%dT%H:%M:%SZ')
# ---------------- staff logins
r=hq.post(B+'/hq/staff',json={'first_name':'Bisi','last_name':'Staffer','email':'bisi.staff@example.org','designation':'Clerk'}); sid=r.json()['id']; spw=r.json()['temporary_password']
r=hq.post(B+'/hq/staff',json={'first_name':'Chuka','last_name':'Staffer','email':'chuka.staff@example.org'}); cid=r.json()['id']; cpw=r.json()['temporary_password']
st=lambda: {p['id']:p for p in hq.get(B+'/hq/staff').json()['items']}
chk('the staff list gives the details to edit', st()[sid]['email']=='bisi.staff@example.org' and st()[sid]['designation']=='Clerk', st()[sid])
r=hq.post(B+f'/hq/staff/{sid}/edit',json={'first_name':'Bisola','last_name':'Staffer','email':'bisola.staff@example.org','phone':'+2348011112222','designation':'Records clerk'})
chk('a super administrator changes a staff login', r.ok and st()[sid]['name']=='Bisola Staffer' and st()[sid]['email']=='bisola.staff@example.org' and st()[sid]['designation']=='Records clerk', r.text)
_,lr=login('bisola.staff@example.org',spw); chk('she signs in with the new email', lr.ok, lr.text)
_,lr=login('bisi.staff@example.org',spw); chk('the old email no longer signs in', lr.status_code in (401,422))
chk('an email another account uses is refused', hq.post(B+f'/hq/staff/{sid}/edit',json={'first_name':'Bisola','last_name':'Staffer','email':'chuka.staff@example.org'}).status_code==409)
chk('a bad email is refused', hq.post(B+f'/hq/staff/{sid}/edit',json={'first_name':'Bisola','last_name':'Staffer','email':'nope'}).status_code==422)
sql(f"update users set must_change_password=0 where id={cid}"); sc,_=login('chuka.staff@example.org',cpw)
chk('a staff member cannot change another staff login', sc.post(B+f'/hq/staff/{sid}/edit',json={'first_name':'X','last_name':'Yz','email':'x@example.org'}).status_code==403)
chk('a staff member can correct her own name', sc.post(B+f'/hq/staff/{cid}/edit',json={'first_name':'Chukwuka','last_name':'Staffer','email':'chuka.staff@example.org'}).ok and st()[cid]['name']=='Chukwuka Staffer')
chk('a staff member cannot delete logins', sc.post(B+f'/hq/staff/{sid}/delete',json={'confirm':True}).status_code==403)
chk('deleting needs confirmation', hq.post(B+f'/hq/staff/{sid}/delete',json={}).status_code==422 and sid in st())
hqid=int(sql("select id from users where email='hq@test.local'"))
chk('nobody deletes their own login', hq.post(B+f'/hq/staff/{hqid}/delete',json={'confirm':True}).status_code in (403,422))
r=hq.post(B+f'/hq/staff/{sid}/delete',json={'confirm':True}); chk('a staff login is deleted', r.ok and sid not in st(), r.text)
_,lr=login('bisola.staff@example.org',spw); chk('a deleted login cannot sign in', lr.status_code in (401,403,422), lr.text)
chk('the email can be used again', hq.post(B+'/hq/staff',json={'first_name':'Bisola','last_name':'Again','email':'bisola.staff@example.org'}).ok)
chk('the deletion is on record with who it was', 'bisola.staff@example.org' in sql(f"select detail from activity_log where action='hq_staff_deleted' and subject_id={sid}"))
chk('a staff login is not deleted from the member list', hq.post(B+f'/hq/members/{cid}/delete',json={'confirm':True}).status_code==422)
# ---------------- members, leaders, envoys
s1,r,b1=reg(); u1=r.json()['user']
r=hq.post(B+f'/hq/members/{u1["id"]}/contact',json={'first_name':'Ngozika','last_name':'Changed','email':'ngozika.changed@example.org','phone':'+2348099998888'})
m=hq.get(B+f'/hq/members/{u1["id"]}').json()['member']; chk('HQ changes a member\'s name, email and phone', r.ok and m['first_name']=='Ngozika' and m['email']=='ngozika.changed@example.org' and m['phone']=='+2348099998888', r.text)
chk('her email must be free', hq.post(B+f'/hq/members/{u1["id"]}/contact',json={'first_name':'Ngozika','last_name':'Changed','email':'chuka.staff@example.org','phone':''}).status_code==409)
lagos=sql("select id from org_units where level='state_region' and name='Lagos' limit 1")
r=hq.post(B+'/hq/leaders/add',json={'first_name':'Dele','last_name':'Leader','email':'dele.leader@example.org','network':'WGMN','position':'state_coordinator','org_unit_id':int(lagos)}); lid=r.json()['id']
hq.post(B+'/hq/leader-logins/bulk',json={'ids':[lid],'country':'NG','network':'WGMN','levels':['state_region'],'confirm':True})
chk('a member cannot delete anyone', s1.post(B+f'/hq/members/{lid}/delete',json={'confirm':True}).status_code in (401,403))
r=hq.post(B+f'/hq/members/{lid}/delete',json={'confirm':True}); chk('HQ deletes a leader', r.ok, r.text)
chk('her seat ends', sql(f"select count(*) from seats where user_id={lid} and ends_at is null")=='0')
chk('she drops out of the member list', all(x['id']!=lid for x in hq.get(B+'/hq/members',params={'q':'Dele'}).json()['items']))
chk('her email is free again', sql("select count(*) from users where email='dele.leader@example.org'")=='0')
chk('she is signed out everywhere', sql(f"select count(*) from sessions where user_id={lid}")=='0')
# ---------------- meetings
s2,r,b2=reg(); assert r.ok, r.text; u2=r.json()['user']; sql(f"update users set country_code='KE', tz='' where id={u2['id']}")
r=hq.post(B+'/meetings',json={'title':'Edit me','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':'2026-12-10T09:00:00Z','ends_at':'2026-12-10T10:00:00Z','audience':'members:WGMN','agenda':'One'}); mid=r.json()['id']
sql(f"insert into app_settings (k,v) values ('meet_day_{mid}','x')")
r=hq.post(B+f'/meetings/{mid}/edit',json={'title':'Edited forum','mode':'virtual','location':'https://meet.google.com/new-link-abc','starts_at':'2026-12-11T13:00:00Z','ends_at':'2026-12-11T14:00:00Z','agenda':'Two'})
mm=hq.get(B+f'/meetings/{mid}').json()['meeting']
chk('HQ changes a meeting', r.ok and mm['title']=='Edited forum' and mm['location']=='https://meet.google.com/new-link-abc' and mm['starts_at']=='2026-12-11 13:00:00' and mm['agenda']=='Two', r.text)
chk('those invited are told the new time, each in her own time', '16:00 EAT (UTC+3)' in sql(f"select body from notifications where user_id={u2['id']} and title='Meeting changed: Edited forum'"), sql(f"select body from notifications where user_id={u2['id']} and title like 'Meeting changed%'"))
chk('a new time sets the reminders going again', sql(f"select count(*) from app_settings where k='meet_day_{mid}'")=='0')
chk('a member who is not the host cannot change it', s1.post(B+f'/meetings/{mid}/edit',json={'title':'Mine','starts_at':tt(days=3),'ends_at':tt(days=3,hours=1),'location':'x'}).status_code==403)
chk('the end must be after the start', hq.post(B+f'/meetings/{mid}/edit',json={'title':'Edited forum','location':'https://x.y','starts_at':'2026-12-11T13:00:00Z','ends_at':'2026-12-11T12:00:00Z'}).status_code==422)
s1.post(B+f'/meetings/{mid}/rsvp',json={'answer':'yes'})
chk('deleting a meeting needs confirmation', hq.post(B+f'/meetings/{mid}/delete',json={}).status_code==422)
r=hq.post(B+f'/meetings/{mid}/delete',json={'confirm':True}); chk('HQ deletes a meeting', r.ok and hq.get(B+f'/meetings/{mid}').status_code==404, r.text)
chk('its replies go with it', sql(f"select count(*) from meeting_rsvps where meeting_id={mid}")=='0')
chk('those invited are told it will not hold', sql(f"select count(*) from notifications where user_id={u2['id']} and title='Cancelled: Edited forum'")=='1')
chk('it is gone from the list', all(x['id']!=mid for x in hq.get(B+'/meetings').json()['items']))
# ---------------- tasks
r=hq.post(B+'/tasks',json={'title':'Old task title','details':'d','priority':'normal','assignees':[u1['id']],'due_on':'2026-12-20'}); tid=(r.json().get('ids') or [r.json().get('id')])[0]
r=hq.post(B+f'/tasks/{tid}/edit',json={'title':'New task title','details':'Do it well','priority':'high','due_on':'2026-12-22'}); tk=hq.get(B+f'/tasks/{tid}').json()['task']
chk('whoever gave a task changes it', r.ok and tk['title']=='New task title' and tk['priority']=='high' and tk['due_on']=='2026-12-22', r.text)
chk('the person doing it is told', sql(f"select count(*) from notifications where user_id={u1['id']} and title='Task changed: New task title'")=='1')
chk('the person doing it cannot change it', s1.post(B+f'/tasks/{tid}/edit',json={'title':'Mine now'}).status_code==403)
chk('nor delete it', s1.post(B+f'/tasks/{tid}/delete',json={'confirm':True}).status_code==403)
r=hq.post(B+f'/tasks/{tid}/delete',json={'confirm':True}); chk('whoever gave a task deletes it', r.ok and hq.get(B+f'/tasks/{tid}').status_code==404, r.text)
chk('she is told she does not need to do it', sql(f"select count(*) from notifications where user_id={u1['id']} and title='Task removed: New task title'")=='1')
# ---------------- announcements
r=hq.post(B+'/hq/announcements',json={'title':'First wording','body':'Hello all','audience_network':'ALL','audience_role':'all'}); aid=r.json().get('id') or int(sql("select max(id) from announcements"))
sql(f"insert into announcement_texts (announcement_id,lang,title,body) values ({aid},'fr','Premier','Bonjour')")
r=hq.post(B+f'/hq/announcements/{aid}/edit',json={'title':'Second wording','body':'Hello again'}); a=[x for x in hq.get(B+'/hq/announcements').json()['items'] if int(x['id'])==aid][0]
chk('HQ changes an announcement', r.ok and a['title']=='Second wording' and a['body']=='Hello again', r.text)
chk('an out-of-date translation is removed', sql(f"select count(*) from announcement_texts where announcement_id={aid}")=='0')
chk('a member cannot change it', s1.post(B+f'/hq/announcements/{aid}/edit',json={'title':'Mine','body':'Mine'}).status_code in (401,403))
r=hq.post(B+f'/hq/announcements/{aid}/delete',json={'confirm':True}); chk('HQ deletes an announcement', r.ok and all(int(x['id'])!=aid for x in hq.get(B+'/hq/announcements').json()['items']), r.text)
# ---------------- calendar and library
r=hq.post(B+'/calendar',json={'title':'Old event','programme':'Other','starts_on':'2026-12-05','ends_on':'2026-12-05','network':'ALL'}); evid=r.json().get('id') or int(sql("select max(id) from calendar_events"))
r=hq.post(B+f'/calendar/{evid}/edit',json={'title':'New event','programme':'Other','starts_on':'2026-12-06','ends_on':'2026-12-07','network':'WGMN','note':'n'})
chk('HQ changes a calendar date', r.ok and sql(f"select concat(title,'|',starts_on,'|',ends_on,'|',network) from calendar_events where id={evid}")=='New event|2026-12-06|2026-12-07|WGMN', r.text)
chk('an end before the start is refused', hq.post(B+f'/calendar/{evid}/edit',json={'title':'New event','starts_on':'2026-12-06','ends_on':'2026-12-01'}).status_code==422)
r=hq.post(B+'/library',json={'title':'Old document','audience':'leaders','url':'https://example.org/a','category':'Guides'}); lbid=r.json().get('id') or int(sql("select max(id) from library_items"))
r=hq.post(B+f'/library/{lbid}/edit',json={'title':'New document','description':'About it','category':'Forms','audience':'all','url':'https://example.org/b'})
chk('HQ changes a document', r.ok and sql(f"select concat(title,'|',category,'|',audience,'|',url) from library_items where id={lbid}")=='New document|Forms|all|https://example.org/b', r.text)
chk('a member cannot change a document', s1.post(B+f'/library/{lbid}/edit',json={'title':'Mine'}).status_code in (401,403))
r=hq.post(B+f'/library/{lbid}/delete',json={'confirm':True}); chk('HQ deletes a document for good', r.ok and sql(f"select count(*) from library_items where id={lbid}")=='0', r.text)
# ---------------- the screens offer it
for f,words in [('staff',['/edit`','/delete`']),('members',['/contact`','/delete`']),('meetings',['/edit`','/delete`']),('tasks',['/edit`','/delete`']),('announcements',['/edit`','/delete`']),('calendar',['/edit`','/delete`']),('library',['/edit`','/delete`']),('envoys',['/delete`'])]:
    src=open(f'{ROOT}/js/pages/{f}.js').read(); chk(f'{f}: Edit and Delete on screen', all(w in src for w in words))
print('FAILURES',bad)
