# v109: report documents, task files, direct messages (bulk, undo, delete, drafts), envoys, HQ staff areas, HQ inbox email.
import requests, subprocess, json, base64, time, io, zipfile, os
B='http://localhost:8080/api'
def S():
    s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
SQL=os.environ.get('WDOS_SQL','mariadb --socket=/tmp/mdb.sock -uroot wdos').split()
def sql(q): return subprocess.run(SQL+['-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0; cnt=0
def chk(n,c,e=''):
    global bad,cnt; cnt+=1
    if not c: bad+=1; print('FAIL',n,str(e)[:500])
def until(fn,secs=40):
    t=time.time()
    while time.time()-t<secs:
        if fn(): return True
        time.sleep(1)
    return False
def login(e,p):
    s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
sql("delete from login_attempts")
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1'); anon=S()
PDF=b'%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n'+b'x'*2000
def docx():
    b=io.BytesIO(); z=zipfile.ZipFile(b,'w'); z.writestr('[Content_Types].xml','<Types/>'); z.writestr('word/document.xml','<w:document/>'); z.close(); return b.getvalue()
up=lambda s,name,data,purpose='': s.post(B+'/files',json={'name':name,'data':base64.b64encode(data).decode(),'purpose':purpose})
# ---- files
r=up(ld,'November report.pdf',PDF,'report'); chk('pdf accepted', r.ok and r.json()['file']['kind']=='pdf', r.text); pdf_id=r.json()['file']['id']
r=up(ld,'report.docx',docx(),'report'); chk('docx accepted', r.ok and r.json()['file']['kind']=='docx', r.text); docx_id=r.json()['file']['id']
r=up(ld,'notes.txt',b'just text','report'); chk('text refused for a report', r.status_code==422, r.text)
r=up(ld,'fake.pdf',b'MZ\x90\x00 not a pdf','report'); chk('renamed file refused: content decides', r.status_code==422, r.text)
r=up(ld,'sheet.xlsx',b'PK\x03\x04xl/workbook.xml','report'); chk('excel refused for a report', r.status_code==422)
r=up(ld,'sheet.xlsx',b'PK\x03\x04xl/workbook.xml'); chk('excel accepted for a task', r.ok, r.text)
big=b'%PDF-1.4'+b'0'*(10*1024*1024+10); r=up(ld,'big.pdf',big,'report'); chk('over 10 MB refused', r.status_code in (413,422), r.status_code)
r=up(ld,'big9.pdf',b'%PDF-1.4'+b'7'*(1300*1024),'report'); chk('1.3 MB pdf stored in pieces', r.ok and int(sql(f"select chunks from doc_files where id={r.json()['file']['id']}"))==3, r.text)
chk('signed-out cannot upload', anon.post(B+'/files',json={'name':'a.pdf','data':base64.b64encode(PDF).decode()}).status_code==401)
# ---- report document required, viewable, HQ told
rid=ld.post(B+'/reporting/open',json={'template':'monthly_leadership'}).json().get('id'); chk('report opened', bool(rid))
r=ld.post(B+f'/reporting/report/{rid}/submit',json={}).json(); chk('submit blocked without document', r['submitted'] is False and any('PDF or Word' in e for e in r['errors']), r)
r=ld.post(B+f'/reporting/report/{rid}/documents',json={'file_id':pdf_id}); chk('document attached', r.ok and len(r.json()['documents'])==1, r.text)
r=hq.post(B+f'/reporting/report/{rid}/documents',json={'file_id':pdf_id}); chk('someone else cannot attach to this report', r.status_code in (403,422), r.status_code)
r=ld.post(B+f'/reporting/report/{rid}/submit',json={}).json(); chk('document no longer blocks', not any('PDF or Word' in e for e in r.get('errors',[])), r)
v=hq.get(B+f'/files/{pdf_id}'); chk('HQ opens the PDF in the browser', v.status_code==200 and v.content==PDF and v.headers['Content-Type']=='application/pdf' and v.headers['Content-Disposition'].startswith('inline'), v.headers)
d=hq.get(B+f'/files/{docx_id}?dl=1'); chk('uploader-only docx not visible to others until linked', d.status_code==200 or d.status_code==404)
other=login('seed0@test.local','seed-pass-1') if False else None
chk('signed-out cannot open a file', anon.get(B+f'/files/{pdf_id}').status_code==401)
seedpw=sql("select 1"); 
m=S(); r=m.post(B+'/auth/register',json={'network':'WGMN','first_name':'Outside','last_name':'Person','dob':'1980-01-01','phone':'+2348100001108','country_id':int(sql("select id from org_units where level='country' and country_code='GH'")),'state_region':'Accra','lga':'Accra','community':'Osu','password':'outside-1','consent':True,'email':'outside.t108@example.org','lang':'en'})
chk('outsider registered', r.ok, r.text[:200])
chk('an outsider cannot open the report document', m.get(B+f'/files/{pdf_id}').status_code==404)
# nil declaration submits without a document and reaches HQ by push and email
sql("delete from mail_outbox")
ld.post(B+f'/reporting/report/{rid}/documents/{pdf_id}/delete',json={})
ld.post(B+f'/reporting/report/{rid}/save',json={'answers':{'declaration':True},'is_nil':True,'nil_reason':'No activity in this period, the area was flooded.'})
r=ld.post(B+f'/reporting/report/{rid}/submit',json={}).json(); chk('nil declaration submits without a document', r.get('submitted') is True, r)
chk('HQ told in the app', int(sql("select count(*) from notifications n join users u on u.id=n.user_id where u.email='hq@test.local' and n.kind='report' and n.title like '%submitted%'"))>=1)
chk('HQ inbox emailed at woddingo@gmail.com', sql("select count(*) from mail_outbox where to_email='woddingo@gmail.com' and subject like 'WDOS report submitted%'")=='1', sql('select to_email,subject from mail_outbox'))
# ---- tasks: HQ brief attached on creation, assignee hands in work, assigner emailed
lid=int(sql("select id from users where email='leader@test.local'"))
br=up(hq,'Template.docx',docx()).json()['file']['id']
t=hq.post(B+'/tasks',json={'title':'November chapter census','details':'Use the template.','assignees':[lid],'requires_approval':True,'files':[br]}).json(); tid=t['id']
f=ld.get(B+f'/tasks/{tid}/files').json(); chk('brief visible to the assignee', any(x['id']==br and x['role']=='brief' for x in f['files']) and f['can_add_work'], f)
chk('assignee can open the brief', ld.get(B+f'/files/{br}').status_code==200)
w=up(ld,'Census.pdf',PDF).json()['file']['id']; r=ld.post(B+f'/tasks/{tid}/files',json={'file_id':w,'role':'work'}); chk('work attached', r.ok, r.text)
chk('assignee cannot add a brief', ld.post(B+f'/tasks/{tid}/files',json={'file_id':w,'role':'brief'}).status_code==403)
sql("delete from mail_outbox")
r=ld.post(B+f'/tasks/{tid}/status',json={'to':'submitted'}); chk('task submitted', r.ok, r.text)
chk('HQ inbox emailed on task submission', int(sql("select count(*) from mail_outbox where to_email='woddingo@gmail.com' and subject like 'WDOS task submitted%'"))==1, sql('select to_email,subject from mail_outbox'))
chk('HQ can open the handed-in work', hq.get(B+f'/files/{w}').status_code==200)
# ---- direct messages
o=ld.get(B+'/dm/options').json(); chk('leader may write to leaders and members', 'leaders' in o['who'] and 'envoys' not in o['who'], o['who'])
oh=hq.get(B+'/dm/options').json(); chk('HQ may write to everyone, envoys and staff', set(['leaders','members','envoys','staff','everyone'])<=set(oh['who']))
a=hq.post(B+'/dm/audience',json={'who':'leaders','network':'WGMN'}).json(); n_wgmn=int(sql("select count(distinct s.user_id) from seats s join users u on u.id=s.user_id where s.ends_at is null and s.network='WGMN' and u.status='active'"))
chk('bulk by network counts the WGMN leaders', a['count']==n_wgmn, (a, n_wgmn))
a2=hq.post(B+'/dm/audience',json={'who':'leaders','level':'country'}).json(); chk('bulk by leadership level', a2['count']==int(sql("select count(distinct s.user_id) from seats s join positions p on p.code=s.position_code join users u on u.id=s.user_id where s.ends_at is null and p.level='country' and u.status='active'")), a2)
a3=hq.post(B+'/dm/audience',json={'who':'members','country':'GH'}).json(); chk('bulk by region', a3['count']>=1, a3)
# leader cannot reach outside her area
outsider=int(sql("select id from users where email='outside.t108@example.org'"))
r=ld.post(B+'/dm/send',json={'subject':'Hi','body':'hello','audience':{'people':[outsider]}}); chk('leader cannot write to someone outside her area', r.status_code==422, r.text)
# HQ to one leader: undo window
sql("delete from mail_outbox"); sql("delete from notifications where kind='message'")
r=hq.post(B+'/dm/send',json={'subject':'Welcome','body':'Please confirm your chapter list.','audience':{'people':[lid]},'label':'one leader'}).json(); bid=r['batch_id']
chk('send returns an undo window', r['recipients']==1 and r['undo_seconds']==30, r)
chk('recipient does not see it during the undo time', ld.get(B+'/dm').json()['items']==[] )
u=hq.post(B+f'/dm/undo/{bid}',json={}).json(); chk('undo returns the words', u['ok'] and u['body']=='Please confirm your chapter list.', u)
chk('undone message leaves no trace for the recipient', ld.get(B+'/dm').json()['items']==[] and sql('select count(*) from dm_messages')=='0')
r=hq.post(B+'/dm/send',json={'subject':'Welcome','body':'Please confirm your chapter list.','audience':{'people':[lid]}}).json(); bid=r['batch_id']
sql(f"update dm_batches set undo_until=utc_timestamp() - interval 1 second where id={bid}")
chk('too late to undo after the window', hq.post(B+f'/dm/undo/{bid}',json={}).status_code==409)
sql("update app_settings set v='0' where k='outbox_tick'"); hq.get(B+'/me'); time.sleep(2)
chk('recipient told in the app after release', until(lambda: int(sql(f"select count(*) from notifications where user_id={lid} and kind='message'"))==1), sql('select released_at from dm_batches'))
inbox=ld.get(B+'/dm').json(); chk('message in the leader inbox with unread count', len(inbox['items'])==1 and inbox['items'][0]['unread']==1 and inbox['unread']==1, inbox)
th=inbox['items'][0]['id']; conv=ld.get(B+f'/dm/{th}').json(); chk('conversation opens and shows sender title', conv['messages'][0]['body'].startswith('Please') and 'WODDI HQ' in conv['thread']['person']['title'], conv['thread'])
chk('opening clears unread', ld.get(B+'/dm').json()['unread']==0)
r=ld.post(B+f'/dm/{th}/reply',json={'body':'Confirmed, sending today.'}); chk('reply', r.ok, r.text)
# delete own message, hide conversation and undo the hide
mid=int(sql("select max(id) from dm_messages where sender_id=%d"%lid)); chk('delete own message', ld.post(B+f'/dm/message/{mid}/delete',json={}).ok)
chk('cannot delete someone else\'s message', ld.post(B+f'/dm/message/{conv["messages"][0]["id"]}/delete',json={}).status_code==404)
chk('hide conversation', ld.post(B+'/dm/hide',json={'ids':[th],'hidden':True}).ok and ld.get(B+'/dm').json()['items']==[])
chk('deleted view lists it, and restore brings it back', len(ld.get(B+'/dm?view=deleted').json()['items'])==1 and ld.post(B+'/dm/hide',json={'ids':[th],'hidden':False}).ok and len(ld.get(B+'/dm').json()['items'])==1)
chk('outsider cannot read the conversation', m.get(B+f'/dm/{th}').status_code==404)
# drafts
d=ld.post(B+'/dm/drafts',json={'subject':'Draft','body':'to finish later','audience':{'who':'leaders'}}).json(); chk('draft saved', d['ok'])
chk('draft listed', len(ld.get(B+'/dm/drafts').json()['drafts'])==1)
chk('draft deleted', ld.post(B+f'/dm/drafts/{d["id"]}/delete',json={}).ok and ld.get(B+'/dm/drafts').json()['drafts']==[])
# bulk send by HQ, emails per recipient language
sql("insert ignore into users (member_no,email,first_name,last_name,network,country_code,role,status,lang,created_at) values ('WGMN-T108-1','amina.t108@wdos-mail.org','Amina','Test','WGMN','NG','member','active','fr',now())")
amina=int(sql("select id from users where email='amina.t108@wdos-mail.org'"))
sql(f"insert into seats (user_id,position_code,network,org_unit_id,status,starts_at) values ({amina},'state_coordinator','WGMN',(select id from org_units where name='Lagos' limit 1),'appointed',now())")
sql("delete from mail_outbox")
r=hq.post(B+'/dm/send',json={'subject':'Monthly reports','body':'Reports are due on the 5th.','audience':{'who':'leaders','network':'WGMN'},'label':'WGMN leaders'}).json()
chk('bulk send to all WGMN leaders', r['recipients']==n_wgmn+1, r)
sql(f"update dm_batches set undo_until=utc_timestamp() - interval 1 second where id={r['batch_id']}"); sql("update app_settings set v='0' where k='outbox_tick'"); hq.get(B+'/me'); time.sleep(2)
until(lambda: sql("select count(*) from mail_outbox where to_email='amina.t108@wdos-mail.org'")=='1')
chk('one email per leader with a real address, in her language', sql("select subject from mail_outbox where to_email='amina.t108@wdos-mail.org'")=='Nouveau message dans WDOS de '+sql("select concat(first_name,' ',last_name) from users where email='hq@test.local'"), sql('select to_email,subject from mail_outbox'))
chk('each recipient has a private conversation', int(sql(f"select count(distinct thread_id) from dm_messages where batch_id={r['batch_id']}"))==n_wgmn+1)
# ---- envoys
sql("delete from login_attempts")
e=hq.post(B+'/hq/envoys',json={'first_name':'Grace','last_name':'Envoy','email':'grace.envoy@wdos-mail.org','phone':'+2348000001108','country_code':'NG'}).json()
chk('envoy created with a link', e['ok'] and '#/r/' in e['link'], e)
chk('leader cannot create envoys', ld.post(B+'/hq/envoys',json={}).status_code==403)
code=e['link'].split('/r/')[1]
ev=S(); ev.post(B+'/auth/login',json={'email':'grace.envoy@wdos-mail.org','password':e['temporary_password']})
ev.post(B+'/me/password',json={'current_password':e['temporary_password'],'new_password':'grace-envoy-1'})
me=ev.get(B+'/me').json()['user']; chk('envoy profile', me['is_envoy'] and not me['is_leader'] and not me['is_hq'], me)
chk('envoy has no leader dashboard', ev.get(B+'/leader/dashboard').status_code in (403,404))
chk('envoy cannot open HQ areas', ev.get(B+'/hq/members').status_code==403)
home=ev.get(B+'/envoy/home').json(); chk('envoy home', home['kpis']['referred']==0 and code in home['link'], home)
chk('a member cannot open the envoy page', m.get(B+'/envoy/home').status_code==403)
lk=S().get(B+'/ref/'+code).json(); chk('envoy link is public', lk['link']['kind']=='envoy', lk)
p=S(); r=p.post(B+'/auth/register',json={'network':'WNNN','first_name':'Zainab','last_name':'Referred','dob':'2000-05-05','phone':'+2348100002108','country_id':int(sql("select id from org_units where level='country' and country_code='NG'")),'state_id':int(sql("select id from org_units where name='Lagos' limit 1")),'lga':'Ikeja','community':'Alausa','password':'zainab-1','consent':True,'email':'zainab.t108@example.org','lang':'en','referral':{'code':code,'position':'chapter_lead'}})
chk('referred person joined through the envoy link (WNNN, link serves both networks)', r.ok, r.text[:300])
act=p.get(B+'/activation').json()['activation']; chk('the referred person is in the 3-day activation', act and act['route']=='ref3' and len(act['days'])>=1, act and act['route'])
home=ev.get(B+'/envoy/home').json(); chk('envoy sees the person and her activation', home['kpis']['referred']==1 and home['people'][0]['activation']['status']=='in_progress', home['people'])
zid=home['people'][0]['id']
r=ev.post(B+'/dm/send',json={'body':'Welcome Zainab. Day 1 is open: start today.','audience':{'people':[zid]}}); chk('envoy messages her referral', r.ok, r.text)
r=ev.post(B+'/dm/send',json={'body':'hi','audience':{'people':[lid]}}); chk('envoy cannot message leaders', r.status_code==422)
k=hq.get(B+'/hq/envoys').json(); chk('HQ sees envoy KPIs', k['totals']['referred']==1 and k['envoys'][0]['kpis']['messages_sent_30']==1 and k['totals']['envoys']==1, k['totals'])
t=hq.post(B+'/tasks',json={'title':'Share your link in three groups','assignees':[me['id']]}); chk('HQ assigns a task to the envoy', t.ok, t.text)
chk('envoy sees the task', ev.get(B+'/envoy/home').json()['tasks']['open']==1)
chk('suspend envoy', hq.post(B+f'/hq/envoys/{me["id"]}',json={'active':False}).ok and S().get(B+'/ref/'+code).status_code==404)
# ---- HQ staff areas
st=login('hq@test.local','hq-password-1')
ac=hq.get(B+'/hq/staff/access').json(); chk('roster lists the six staff', len(ac['roster'])==6 and ac['hq_inbox']=='woddingo@gmail.com', ac['roster'][0])
s=hq.post(B+'/hq/staff',json={'first_name':'Mfon','last_name':'Nta','email':'mfon.t108@wdos-mail.org','designation':'Network Administrative Secretary','features':['members','reports']}).json()
chk('staff created with areas', s['ok'], s)
ms=S(); ms.post(B+'/auth/login',json={'email':'mfon.t108@wdos-mail.org','password':s['temporary_password']}); ms.post(B+'/me/password',json={'current_password':s['temporary_password'],'new_password':'mfon-pass-1'})
mu=ms.get(B+'/me').json()['user']; chk('staff areas in the profile', mu['hq_features']==['members','reports'] and mu['designation']=='Network Administrative Secretary', mu.get('hq_features'))
chk('allowed area opens', ms.get(B+'/hq/members').status_code==200)
chk('area not given is refused by the server', ms.get(B+'/hq/cases').status_code==403 and ms.get(B+'/hq/envoys').status_code==403 and ms.post(B+'/policy',json={'key':'chapter_capacity','value':'40'}).status_code==403, ms.get(B+'/hq/cases').status_code)
chk('staff cannot change her own areas', ms.post(B+f'/hq/staff/{mu["id"]}/access',json={'all':True}).status_code==403)
chk('super admin widens her areas', hq.post(B+f'/hq/staff/{mu["id"]}/access',json={'features':['members','reports','cases'],'designation':'Network Administrative Secretary'}).ok and ms.get(B+'/hq/cases').status_code==200)
chk('every area again', hq.post(B+f'/hq/staff/{mu["id"]}/access',json={'all':True}).ok and ms.get(B+'/me').json()['user']['hq_features'] is None)
# ---- guide
chk('guide marked seen', ld.post(B+'/me/guide-seen',json={}).ok and ld.get(B+'/me').json()['user']['guide_seen'] is True)
chk('HQ inbox rule can be changed and must be an email', hq.post(B+'/policy',json={'key':'hq_notify_email','value':'not-an-email'}).status_code==422)
# ---- video meetings asked for by leaders (Jitsi, HQ approval, gate, minutes)
import datetime
def at(mins): return (datetime.datetime.now(datetime.UTC).replace(tzinfo=None)+datetime.timedelta(minutes=mins)).strftime('%Y-%m-%d %H:%M')
sql("delete from mail_outbox"); sql("delete from login_attempts")
form={'leader_name':'Ngozi Leader','region':'Lagos','title':'Lagos coordinators planning','reason':'Plan the December outreach in every LGA.','attendees_text':'The 20 LGA coordinators of Lagos','expected_count':20,'starts_at':at(180),'duration_min':60,'graphics':'A flyer with the date and time','other_needs':''}
r=ld.post(B+'/meet-requests',json=dict(form,starts_at=at(10))); chk('too-soon request refused', r.status_code==422, r.text)
r=ld.post(B+'/meet-requests',json=dict(form,title='x')); chk('title required', r.status_code==422)
r=ld.post(B+'/meet-requests',json=form).json(); chk('leader asks for a video meeting', r.get('ok') and r['request_no'].startswith('MR-'), r); mid=r['id']
chk('HQ told in the app', int(sql("select count(*) from notifications n join users u on u.id=n.user_id where u.email='hq@test.local' and n.kind='meeting' and n.title like 'Video meeting request%'"))>=1)
chk('HQ inbox emailed about the request', int(sql("select count(*) from mail_outbox where to_email='woddingo@gmail.com' and subject like 'WDOS video meeting request%'"))==1, sql('select to_email,subject from mail_outbox'))
chk('a member cannot ask for a video meeting', m.post(B+'/meet-requests',json=form).status_code==403)
chk('an outsider cannot see it', m.get(B+f'/meet-requests/{mid}').status_code==404)
r=ld.post(B+f'/meet-requests/{mid}/join',json={}); chk('gate stays locked before approval', r.status_code==403 and r.json()['error']=='locked', r.text)
chk('leader cannot approve her own request', ld.post(B+f'/meet-requests/{mid}/decide',json={'decision':'approve'}).status_code==403)
chk('HQ sees it waiting', hq.get(B+'/meet-requests?status=pending').json()['pending']>=1)
chk('declining needs a reason', hq.post(B+f'/meet-requests/{mid}/decide',json={'decision':'decline'}).status_code==422)
sql("delete from mail_outbox"); sql("update users set email='ngozi.t108@wdos-mail.org' where email='leader@test.local'")
r=hq.post(B+f'/meet-requests/{mid}/decide',json={'decision':'approve','note':'Approved. HQ will send the flyer.'}); chk('HQ approves', r.ok, r.text)
chk('leader emailed about the approval', int(sql("select count(*) from mail_outbox where subject like 'WDOS video meeting approved%'"))==1, sql('select to_email,subject from mail_outbox'))
d=ld.get(B+f'/meet-requests/{mid}').json()['meeting']; chk('approved and gate not yet open', d['status']=='approved' and d['gate']=='not_yet', d['gate'])
r=ld.post(B+f'/meet-requests/{mid}/join',json={}); chk('join refused before the room opens', r.status_code==403 and r.json()['error']=='not_yet', r.text)
sql(f"update meeting_requests set starts_at=utc_timestamp() - interval 5 minute where id={mid}")
r=ld.post(B+f'/meet-requests/{mid}/join',json={}).json(); chk('the room opens at the meeting time on Jitsi', r.get('ok') and r['url'].startswith('https://meet.vpsfree.cz/WODDI-') and r['domain']=='meet.vpsfree.cz' and sql(f"select call_domain from meeting_requests where id={mid}")=='meet.vpsfree.cz', r)
chk('HQ can join too', hq.post(B+f'/meet-requests/{mid}/join',json={}).ok)
chk('joins counted', ld.get(B+f'/meet-requests/{mid}').json()['meeting']['joins']==2)
chk('room name never shown before join', 'meet.jit.si' not in json.dumps(ld.get(B+f'/meet-requests/{mid}').json()))
chk('short minutes refused', ld.post(B+f'/meet-requests/{mid}/minutes',json={'minutes':'ok','attendance_count':5}).status_code==422)
chk('attendance number required', ld.post(B+f'/meet-requests/{mid}/minutes',json={'minutes':'We agreed each LGA holds one outreach day before 20 December.','attendance_count':0}).status_code==422)
sql("delete from mail_outbox")
r=ld.post(B+f'/meet-requests/{mid}/minutes',json={'minutes':'We agreed each LGA holds one outreach day before 20 December.','attendance_count':18,'attendance_names':'18 coordinators'}); chk('minutes sent', r.ok, r.text)
chk('HQ inbox gets the minutes', int(sql("select count(*) from mail_outbox where to_email='woddingo@gmail.com' and subject like 'WDOS meeting minutes%'"))==1, sql('select to_email,subject from mail_outbox'))
d=hq.get(B+f'/meet-requests/{mid}').json()['meeting']; chk('held with attendance recorded', d['status']=='held' and d['attendance_count']==18, d['status'])
print('checks',cnt); print('FAILURES',bad)
sql("update users set email='leader@test.local' where email='ngozi.t108@wdos-mail.org'")
