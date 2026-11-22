import requests, subprocess, json, datetime
B='http://localhost:8080/api'; H={'X-WDOS':'1'}
def S(email=None,pw=None):
    s=requests.Session(); s.headers.update(H)
    if email: r=s.post(B+'/auth/login',json={'email':email,'password':pw}); assert r.ok, r.text
    return s
def sql(q): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot','wdos','-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0
def chk(n,c,e=''):
    global bad
    print(('PASS ' if c else 'FAIL ')+n, '' if c else str(e)[:600]); bad+=0 if c else 1
hq=S('hq@test.local','hq-password-1'); hq.post(B+'/policy',json={'key':'journey_unlock','value':'open'})
cs=hq.get(B+'/geo/countries').json()['countries']; ng=[c for c in cs if c['code']=='NG'][0]['id']
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'Federal' in u['name']][0]
n=[700]
def reg(**kw):
    n[0]+=1; sql('delete from login_attempts'); s=S()
    b={'network':'WGMN','first_name':'Ngozi','last_name':f'H{n[0]}','dob':'1985-03-04','phone':f'+23481{20000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':'Bwari','community':'Kubwa','password':'246810','consent':True,'email':f'h{n[0]}@example.org','lang':'en'}
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r,b

# ---- D01-D14
d=hq.get(B+'/policy').json()['decisions']; chk('D: register holds D01 to D14, all pending', [x['code'] for x in d]==[f'D{i:02d}' for i in range(1,15)] and all(x['status']=='Pending' for x in d), d)
chk('D: D04 shows it runs differently from the guardrail while automatic creation is on', [x for x in d if x['code']=='D04'][0]['differs'] is True)
s1,r,b1=reg(); u1=r.json()['user']; chk('registration works', r.ok, r.text)
chk('D: a member cannot read or record decisions', s1.get(B+'/decisions').status_code==403 and s1.post(B+'/decisions/D02',json={'decision':'Activate at 30','approver':'Someone','approved_on':'2026-11-10'}).status_code==403)
chk('D: a decision needs an approver and a date', hq.post(B+'/decisions/D02',json={'decision':'Activate at 30','approver':'','approved_on':'2026-11-10'}).status_code==422 and hq.post(B+'/decisions/D02',json={'decision':'Activate at 30','approver':'Test Approver','approved_on':''}).status_code==422)
r=hq.post(B+'/decisions/D02',json={'decision':'Mark eligible; HQ activates','approver':'Test Approver, test role','approved_on':'2026-11-10'}); r2=hq.post(B+'/decisions/D02',json={'decision':'Activate automatically at 30','approver':'Test Approver, test role','approved_on':'2026-11-11'})
x=[y for y in hq.get(B+'/decisions').json()['items'] if y['code']=='D02'][0]; chk('D: decisions are versioned, latest shown, history kept', r.json()['version']==1 and r2.json()['version']==2 and x['version']==2 and x['status']=='Recorded' and sql("select count(*) from decisions where code='D02'")=='2', x)
chk('D: unknown code refused', hq.post(B+'/decisions/D77',json={'decision':'xxxxx','approver':'abc','approved_on':'2026-11-10'}).status_code==404)

# ---- D04 guardrail: pending placement never blocks a registration
hq.post(B+'/policy',json={'key':'chapter_auto_create','value':'off'})
sp,r,_=reg(community='Ushafa'); up=r.json().get('user',{}); chk('T: with automatic creation off, a woman with no chapter near her still registers and is pending', r.ok and up.get('chapter') is None and up.get('member_no','').startswith('WGMN-NG-'), r.text)
mc=sp.get(B+'/my/chapter').json(); chk('T: pending placement is explained to her', 'pending' in json.dumps(mc).lower() or 'placed' in json.dumps(mc).lower(), mc)
hq.post(B+'/policy',json={'key':'chapter_auto_create','value':'on'})

# ---- T04/T29 safe retry, drafts
key='k'*24; n[0]+=1; body={'network':'WGMN','first_name':'Retry','last_name':'Case','dob':'1980-01-01','phone':'+2348130009991','country_id':ng,'state_id':fct['id'],'lga':'Bwari','community':'Kubwa','password':'135790','consent':True,'email':'retry.case@example.org','lang':'en','idem':key}
sql('delete from login_attempts'); a=S().post(B+'/auth/register',json=body); b=S().post(B+'/auth/register',json=body)
chk('T04/T29: the same registration sent twice returns the same account, once', a.ok and b.ok and a.json()['user']['id']==b.json()['user']['id'] and b.json().get('resumed') is True and sql("select count(*) from users where phone='+2348130009991'")=='1', b.text)
c=S().post(B+'/auth/register',json={**body,'password':'975310'}); chk('T29: the retry key alone opens nothing: a different PIN is refused', c.status_code==409, c.text)
chk('T29: one journey and one chapter place for her', sql(f"select count(*) from member_journeys where user_id={a.json()['user']['id']}")=='1' and sql(f"select count(*) from chapter_members where user_id={a.json()['user']['id']} and ended_at is null")=='1')
p=S(); r=p.post(B+'/public/draft',json={'data':{'first_name':'Amara','contact':'+2348000000001','password':'secret1','step':2}}); tok=r.json()['token']
rd=p.post(B+'/public/draft/read',json={'token':tok}).json()['data']; chk('draft: kept on the server behind a token, and the PIN is never kept', rd['first_name']=='Amara' and 'password' not in rd and 'secret1' not in sql("select data from reg_drafts"), rd)
chk('draft: a wrong token returns nothing', p.post(B+'/public/draft/read',json={'token':'x'*43}).json()['data'] is None)
r=p.post(B+'/public/draft',json={'token':tok,'data':{'first_name':'Amara','last_name':'Obi'}}); chk('draft: updated under the same token', r.json()['token']==tok and sql('select count(*) from reg_drafts')=='1')
sd,r,_=reg(draft=tok,source='event',campaign='wiws-2027!'); ud=r.json()['user']; chk('draft: removed once the membership is made', sql('select count(*) from reg_drafts')=='0')

# ---- Q31 source
chk('Q31: source and campaign saved, campaign cleaned', sql(f"select concat(source,'|',campaign) from users where id={ud['id']}")=='event|wiws-2027', sql(f"select concat(source,'|',campaign) from users where id={ud['id']}"))
code=s1.get(B+'/my/referrals').json()['referrals']['code']; sr,r,_=reg(referral={'code':code}); chk('Q31: an invitation link is recorded as the source', sql(f"select source from users where id={r.json()['user']['id']}")=='referral')
src=hq.get(B+'/hq/sources').json(); chk('Q31: HQ source report counts by source', any(x['source']=='event' and x['registered']==1 for x in src['rows']) and any(x['source']=='not_stated' for x in src['rows']), src['rows'][:4])
chk('Q31: the source report is HQ only', s1.get(B+'/hq/sources').status_code==403)

# ---- chapter leader for case routing and seat states
sl,r,_=reg(first_name='Lead'); ul=r.json()['user']; cid=u1['chapter']['id']
r=hq.post(B+f"/hq/members/{ul['id']}/seat",json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':cid,'network':'WGMN'}); chk('chapter leader appointed', r.ok, r.text)
chk('leader dashboard opens', sl.get(B+'/leader/dashboard').status_code==200)

today=datetime.date.today()
# ---- Q34 RSVP
start=(datetime.datetime.now(datetime.UTC)+datetime.timedelta(days=3)).strftime('%Y-%m-%d %H:%M:%S'); end=(datetime.datetime.now(datetime.UTC)+datetime.timedelta(days=3,hours=1)).strftime('%Y-%m-%d %H:%M:%S')
r=sl.post(B+'/meetings',json={'title':'Chapter forum','mode':'virtual','location':'https://meet.example.org/x','starts_at':start,'ends_at':end,'audience':'members:WGMN'}); mid=r.json().get('id'); chk('meeting scheduled', r.ok and mid, r.text)
chk('Q34: a member replies', s1.post(B+f'/meetings/{mid}/rsvp',json={'answer':'yes'}).ok and sd.post(B+f'/meetings/{mid}/rsvp',json={'answer':'no'}).ok and s1.post(B+f'/meetings/{mid}/rsvp',json={'answer':'maybe'}).ok)
rv=s1.get(B+f'/meetings/{mid}/rsvp').json(); chk('Q34: one reply each, latest kept, totals right', rv['mine']=='maybe' and rv['counts']=={'yes':0,'no':1,'maybe':1}, rv)
chk('Q34: bad reply refused', s1.post(B+f'/meetings/{mid}/rsvp',json={'answer':'x'}).status_code==422)
# ---- T27 confidential cases
r=s1.post(B+'/cases',json={'category':'complaint','about_my_leader':True,'body':'She asked members to pay dues into her own account.'}); ref=r.json().get('ref',''); chk('T27: a member files a private report and gets a reference', r.ok and ref.startswith('WDC-'), r.text)
chk('T27: the chapter leader named cannot see cases at all', sl.get(B+'/hq/cases').status_code==403)
chk('T27: nothing about the case reached the leader', sql(f"select count(*) from notifications where user_id={ul['id']} and (title like '%case%' or body like '%{ref}%' or body like '%dues%')")=='0')
hn=sql(f"select concat(title,' ',body) from notifications where body like '%{ref}%' and user_id<>{u1['id']} limit 1"); chk('T27: the HQ notice carries the reference only, no names and no words from the report', ref in hn and 'dues' not in hn and 'Lead' not in hn and 'Ngozi' not in hn, hn)
hc=hq.get(B+'/hq/cases').json(); c0=[c for c in hc['items'] if c['ref']==ref][0]; chk('T27: HQ handler sees it, matched to the leader', c0['about_matched'] and c0['category_key']=='complaint')
chk('T27: closing needs an outcome', hq.post(B+f"/hq/cases/{c0['id']}",json={'status':'resolved'}).status_code==422)
chk('T27: handler updates; member sees status and outcome', hq.post(B+f"/hq/cases/{c0['id']}",json={'status':'resolved','outcome':'Reviewed by HQ. The matter was addressed.','note':'internal'}).ok and s1.get(B+'/my/cases').json()['items'][0]['status']=='resolved' and 'internal' not in s1.get(B+'/my/cases').text)
chk('T27: nobody was sanctioned automatically', sql(f"select status from users where id={ul['id']}")=='active' and sql(f"select count(*) from seats where user_id={ul['id']} and ends_at is null")=='1')
r=s1.post(B+'/cases',json={'category':'complaint','about':'Azeez Olayinka','body':'A complaint about an HQ staff member for the test.'}); ref2=r.json()['ref']; hc=hq.get(B+'/hq/cases').json()
cid2=sql(f"select id from cases where ref='{ref2}'"); chk('T27: a case about an HQ handler is hidden from that handler and cannot be opened by ID', all(c['ref']!=ref2 for c in hc['items']) and hq.post(B+f'/hq/cases/{cid2}',json={'status':'closed','outcome':'closed by myself'}).status_code==404)
chk('T27: that handler got no notice of it', sql(f"select count(*) from notifications where body like '%{ref2}%' and user_id=(select id from users where email='hq@test.local')")=='0')
chk('T27: one member cannot read another member\'s reports', sd.get(B+'/my/cases').json()['items']==[])
chk('T27: category required', s1.post(B+'/cases',json={'category':'nope','body':'something long enough'}).status_code==422)

# ---- T21 seat states
chk('T21: suspending needs a reason', hq.post(B+f"/hq/members/{ul['id']}/seat-state",json={'state':'suspended'}).status_code==422)
r=hq.post(B+f"/hq/members/{ul['id']}/seat-state",json={'state':'suspended','note':'Pending review'}); chk('T21: a suspended leader loses the leadership view at once on her open session', r.ok and sl.get(B+'/leader/dashboard').status_code==403 and sl.get(B+'/leader/members').status_code==403 and sl.get(B+'/me').json()['user']['is_leader'] is False, sl.get(B+'/leader/dashboard').text[:200])
chk('T21: her membership is untouched', sl.get(B+'/me').json()['user']['status']=='active' and sl.get(B+'/my/chapter').ok)
hq.post(B+f"/hq/members/{ul['id']}/seat-state",json={'state':'on_leave','note':'Maternity leave'}); chk('Q20: on leave also closes the leadership view; the appointment stays on record', sl.get(B+'/leader/dashboard').status_code==403 and sql(f"select status from seats where user_id={ul['id']} and ends_at is null")=='on_leave')
hq.post(B+f"/hq/members/{ul['id']}/seat-state",json={'state':'appointed'}); chk('Q20: back to appointed restores it', sl.get(B+'/leader/dashboard').status_code==200)
hq.post(B+f"/hq/members/{ul['id']}/seat-state",json={'state':'resigned','note':'Own request'}); chk('Q20: resignation ends the seat with its reason and keeps the history', sl.get(B+'/leader/dashboard').status_code==403 and sql(f"select end_reason from seats where user_id={ul['id']} order by id desc limit 1")=='resigned')
chk('Q20: seat states are HQ only', s1.post(B+f"/hq/members/{ul['id']}/seat-state",json={'state':'appointed'}).status_code==403)

# ---- lifecycle
lf=s1.get(B+'/me/lifecycle').json(); chk('Q04: lifecycle state shown to the member', lf['state'] in ('onboarding','active') and lf['paused'] is False, lf)
s1.post(B+'/me/pause',json={'pause':True}); before=sql(f"select count(*) from notifications where user_id={u1['id']}")
hq.post(B+'/tasks',json={'title':'Bring the attendance sheet','assignee_id':u1['id']}); after=sql(f"select count(*) from notifications where user_id={u1['id']}")
chk('Q04/Q33: a paused member gets no routine notices', before==after and s1.get(B+'/me').json()['user']['paused'] is True, (before,after))
s1.post(B+'/me/pause',json={'pause':False}); chk('Q04: she comes back by herself', s1.get(B+'/me/lifecycle').json()['paused'] is False and len(s1.get(B+'/me/lifecycle').json()['history'])>=3)
ex=s1.get(B+'/me/export'); chk('Q43: she can download her own record, with no password hash and no other person', ex.ok and ex.json()['profile']['first_name']=='Ngozi' and 'password_hash' not in ex.text and 'Lead' not in ex.text and len(ex.json()['private_reports'])==2, ex.text[:300])
chk('Q04: closing needs her PIN', s1.post(B+'/me/withdraw',json={'password':'000000'}).status_code==403)
sw,r,bw=reg(); uw=r.json()['user']; r=sw.post(B+'/me/withdraw',json={'password':'246810','reason':'Moving abroad','erase':True})
chk('Q04: closure signs her out everywhere and ends her chapter place, history kept', r.ok and sw.get(B+'/my/chapter').status_code==401 and sql(f"select count(*) from sessions where user_id={uw['id']}")=='0' and sql(f"select concat(status,'|',ifnull(chapter_id,'none')) from users where id={uw['id']}")=='withdrawn|none' and sql(f"select count(*) from chapter_members where user_id={uw['id']} and ended_at is not null")=='1')
sql('delete from login_attempts'); r=S().post(B+'/auth/login',json={'email':bw['email'],'password':'246810'}); chk('Q04: a closed membership cannot sign in, and is told why', r.status_code==403 and r.json()['error']=='withdrawn', r.text)
chk('Q43: her erasure request became a privacy case for HQ, nothing deleted automatically', sql(f"select category from cases where reporter_id={uw['id']}")=='privacy' and sql(f"select first_name from users where id={uw['id']}")=='Ngozi')
chk('Q04: HQ can restore her', hq.post(B+f"/hq/members/{uw['id']}",json={'status':'active'}).ok and S().post(B+'/auth/login',json={'email':bw['email'],'password':'246810'}).ok)

# ---- T31 age transition
today=datetime.date.today(); dob29=(today.replace(year=today.year-29)-datetime.timedelta(days=200)).isoformat()
sy,r,_=reg(network='WNNN',dob=dob29,first_name='Young'); uy=r.json()['user']; chk('WNNN member registered', r.ok and uy['network']=='WNNN', r.text)
code=sy.get(B+'/my/referrals').json()['referrals']['code']; reg(network='WNNN',dob=dob29,referral={'code':code})
chk('T31: nobody under 30 can be moved', hq.post(B+f"/hq/age-transition/{uy['id']}",json={}).status_code==422)
sql(f"update users set dob='{today.replace(year=today.year-30).isoformat()}' where id={uy['id']}")
ag=hq.get(B+'/hq/cases').json()['age_transition']; chk('T31: HQ list shows her reaching 30; she was not moved automatically', any(a['id']==uy['id'] for a in ag) and sql(f"select network from users where id={uy['id']}")=='WNNN', ag)
oldch=sql(f"select chapter_id from users where id={uy['id']}"); r=hq.post(B+f"/hq/age-transition/{uy['id']}",json={}); me=sy.get(B+'/me').json()['user']
chk('T31: moved to WGMN with the same Person ID, membership number, referrals and history', r.ok and me['network']=='WGMN' and me['id']==uy['id'] and me['person_id']==uy['person_id'] and sql(f"select count(*) from referrals where referrer_id={uy['id']}")=='1' and sql(f"select count(*) from member_journeys where user_id={uy['id']}")=='1', me)
chk('T31: her WNNN chapter place closed and a WGMN one opened, never a WNNN roster', sql(f"select count(*) from chapter_members where user_id={uy['id']} and chapter_id={oldch} and ended_at is not null")=='1' and sql(f"select c.network from chapters c join users u on u.chapter_id=c.id where u.id={uy['id']}")=='WGMN')
chk('T31: no WGMN role was given', sql(f"select count(*) from seats where user_id={uy['id']} and ends_at is null")=='0')

# ---- Q35 calendar
yr=today.year+1
chk('Q35: a member cannot add calendar dates', s1.post(B+'/calendar',json={'title':'X event','starts_on':f'{yr}-03-08'}).status_code==403)
r=hq.post(B+'/calendar',json={'title':'Test summit date','programme':'WIWS','starts_on':f'{yr}-03-08','ends_on':f'{yr}-03-09'}); r2=hq.post(B+'/calendar',json={'title':'WNNN only test date','programme':'Institute','starts_on':f'{yr}-05-01','network':'WNNN'})
cal=s1.get(B+f'/calendar?year={yr}').json(); chk('Q35: a WGMN member sees shared dates and not WNNN-only dates', r.ok and r2.ok and [e['title'] for e in cal['items']]==['Test summit date'] and cal['can_edit'] is False, cal['items'])
chk('Q35: end before start refused', hq.post(B+'/calendar',json={'title':'Bad dates','starts_on':f'{yr}-03-08','ends_on':f'{yr}-03-01'}).status_code==422)

# ---- Q42 / T32 the 80 percent instrument
w0=hq.get(B+'/hq/workflows').json(); chk('T32 (v106): a starting estimate is entered and labelled as one; nothing is verified, so verified coverage is 0 and the target is not claimed', len(w0['items'])==30 and w0['score']['score']==0 and w0['score']['unweighted']==0 and 60<w0['score']['declared_score']<80 and w0['estimates_on']!='' and all(i['verified_by']=='' for i in w0['items']), w0['score'])
sql("update workflow_catalogue set eligible_weight=null, manual_minutes_baseline=null, frequency=''")      # HQ can clear the estimate; it is not put back
w=hq.get(B+'/hq/workflows').json(); chk('T32: with no weights the score cannot be stated, and a cleared estimate stays cleared', len(w['items'])==30 and w['score']['score'] is None and all(i['eligible_weight'] is None and i['verified_by']=='' for i in w['items']), w['score'])
chk('T32: HQ only', s1.get(B+'/hq/workflows').status_code==403)
for wid,wt in (('W01',10),('W03',10),('W23',10),('W29',10)):
    it=[i for i in w['items'] if i['workflow_id']==wid][0]; hq.post(B+'/hq/workflows',json={**it,'eligible_weight':wt})
sc=hq.get(B+'/hq/workflows').json()['score']; chk('T32: undigitised workflows stay in the denominator; unverified work earns nothing', sc['eligible_weight']==40 and sc['score']==0 and sc['declared_score']==50.0, sc)
it=[i for i in w['items'] if i['workflow_id']=='W01'][0]; chk('T32: verification needs a name and a date together', hq.post(B+'/hq/workflows',json={**it,'eligible_weight':10,'verified_by':'QA Tester'}).status_code==422)
hq.post(B+'/hq/workflows',json={**it,'eligible_weight':10,'verified_by':'QA Tester','verification_date':'2026-11-11'}); sc=hq.get(B+'/hq/workflows').json()['score']; chk('T32: score is verified weight over eligible weight', sc['score']==25.0, sc)

# ---- health and reconciliation
hh=hq.get(B+'/hq/health').json(); chk('T22: live integrity checks all clear', all(v==0 for v in hh['reconcile'].values()) and hh['build']=='v109.46', hh['reconcile'])
chk('health is HQ only and shows no secrets', s1.get(B+'/hq/health').status_code==403 and 'KEY' not in json.dumps(hh).upper().replace('INSTITUTE_KEY',''))
# ---- D08 approved: automatic move, thirty days after the notice
def run(): return subprocess.run('. /tmp/env.sh; php /home/claude/age.php',shell=True,capture_output=True,text=True).stdout
sa,r,_=reg(network='WNNN',dob=dob29,first_name='Auto'); ua=r.json()['user']; sb,r,_=reg(network='WNNN',dob=dob29,first_name='Seat'); ub=r.json()['user']
hq.post(B+f"/hq/members/{ub['id']}/seat",json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':ub['chapter']['id'],'network':'WNNN'})
d30=today.replace(year=today.year-30).isoformat(); sql(f"update users set dob='{d30}' where id in ({ua['id']},{ub['id']})")
o=run(); chk('D08: default rule moves nobody; she is told once', sql(f"select network from users where id={ua['id']}")=='WNNN' and sql(f"select age_notice_at is not null from users where id={ua['id']}")=='1', o)
hq.post(B+'/policy',json={'key':'age_transition','value':'auto'}); o=run(); chk('D08: with the automatic rule, nobody moves before 30 days have passed since she was told', sql(f"select network from users where id={ua['id']}")=='WNNN', o)
sql(f"update users set age_notice_at=date_sub(utc_timestamp(), interval 31 day) where id in ({ua['id']},{ub['id']})"); o=run(); me=sa.get(B+'/me').json()['user']
chk('D08: then she moves by herself, same Person ID, into a WGMN chapter', me['network']=='WGMN' and me['person_id']==ua['person_id'] and sql(f"select c.network from chapters c join users u on u.chapter_id=c.id where u.id={ua['id']}")=='WGMN', o)
chk('D08: a WNNN seat is not ended automatically and HQ is asked to review it', sql(f"select network from users where id={ub['id']}")=='WGMN' and sql(f"select count(*) from seats where user_id={ub['id']} and ends_at is null and network='WNNN'")=='1' and sql("select count(*) from notifications where title='A WNNN leader has moved to WGMN at 30'")!='0', o)
chk('D08: no WGMN role was given automatically', sql(f"select count(*) from seats where user_id={ua['id']}")=='0')
hr=hq.get(B+'/hq/handoff').json(); chk('register: 48 questions, 12 amendments, 36 tests, 14 decisions, HQ only', len(hr['questions'])==48 and len(hr['amendments'])==12 and len(hr['tests'])==36 and len(hr['decisions'])==14 and s1.get(B+'/hq/handoff').status_code==403 and requests.get('http://localhost:8080/lib/handoff_register.json').status_code in (403,404), requests.get('http://localhost:8080/lib/handoff_register.json').status_code)
sql('delete from login_attempts'); r=S().post(B+'/auth/register',json={'network':'WNNN','first_name':'Too','last_name':'Young','dob':f'{today.year-16}-01-01','phone':'+2348139990001','country_id':ng,'state_id':fct['id'],'lga':'Bwari','community':'Kubwa','password':'246810','consent':True,'email':'too.young@example.org'}); chk('D13: nobody under 18 can register', r.status_code==422, r.text)
print('FAILURES',bad)
