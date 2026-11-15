# v104.0: leader terms and replacement, newsletters and talk shows, operations figures, backup and restore.
import requests, subprocess, json, gzip, io, datetime, os
B='http://localhost:8080/api'
def S():
    s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
def sql(q, db='wdos'): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot',db,'-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0; cnt=0
def chk(n,c,e=''):
    global bad,cnt; cnt+=1
    if not c: bad+=1; print('FAIL',n,str(e)[:300])
hq=S(); assert hq.post(B+'/auth/login',json={'email':'hq@test.local','password':'hq-password-1'}).ok
ng=[c for c in hq.get(B+'/geo/countries').json()['countries'] if c['code']=='NG'][0]['id']
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'Federal' in u['name']][0]
n=[1100]
def reg(**kw):
    n[0]+=1; sql('delete from login_attempts'); s=S()
    b={'network':'WGMN','first_name':'Tola','last_name':f'K{n[0]}','dob':'1985-03-04','phone':f'+23481{40000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':'Bwari','community':'Ushafa','password':'246810','consent':True,'email':f'k{n[0]}@example.org','lang':'en'}
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r,b
day=lambda d: (datetime.datetime.now(datetime.UTC)+datetime.timedelta(days=d)).strftime('%Y-%m-%d')
# ---- leader terms
s1,r,b1=reg(); u1=r.json()['user']; s2,r,b2=reg(); u2=r.json()['user']; s3,r,b3=reg(); u3=r.json()['user']
seat=lambda u,**kw: hq.post(B+f"/hq/members/{u['id']}/seat",json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':u['chapter']['id'],'network':'WGMN',**kw})
r=seat(u1,term_ends_on=day(30)); me=s1.get(B+'/me').json()['user']; chk('appointing with an end date records the term', r.ok and me['seat']['term_ends_on']==day(30) and me['is_leader'], r.text)
sid1=me['seat']['id']
chk('an end date in the past is refused', hq.post(B+f'/hq/seats/{sid1}/term',json={'term_ends_on':day(-1)}).status_code==422)
chk('a date that does not exist is refused', hq.post(B+f'/hq/seats/{sid1}/term',json={'term_ends_on':'2027-02-30'}).status_code==422)
chk('members and leaders cannot set terms', s2.post(B+f'/hq/seats/{sid1}/term',json={'term_ends_on':day(90)}).status_code==403 and s1.post(B+f'/hq/seats/{sid1}/term',json={'term_ends_on':day(900)}).status_code==403 and S().post(B+f'/hq/seats/{sid1}/term',json={}).status_code==401)
t=hq.get(B+'/hq/terms').json(); chk('the term list shows her with the days left', t['with_end_date']==1 and t['due'][0]['user_id']==u1['id'] and t['due'][0]['days_left']==30 and t['rule']=='notify', t)
chk('the term list is for HQ only', s1.get(B+'/hq/terms').status_code==403)
# the daily job: default rule only tells HQ
def run_job(): return subprocess.run('. /tmp/env.sh; php /home/claude/terms.php',shell=True,capture_output=True,text=True).stdout
hqid=sql("select id from users where email='hq@test.local'")
before=int(sql(f"select count(*) from notifications where user_id={hqid}")); run_job()
chk('30 days before: HQ is told, the leader is not', sql(f"select count(*) from notifications where user_id={hqid} and title='A leader term ends in 30 days'")=='1' and sql(f"select count(*) from notifications where user_id={u1['id']} and title like '%term%'")=='0', sql(f"select title from notifications where user_id={hqid} order by id desc limit 3"))
hq.get(B+'/notifications/pulse'); hq.get(B+'/notifications/pulse'); chk('the job is claimed once a day: the live five-minute pulse does not tell HQ again', sql(f"select count(*) from notifications where user_id={hqid} and title='A leader term ends in 30 days'")=='1')
seat(u2); sid2=s2.get(B+'/me').json()['user']['seat']['id']; sql(f"update seats set term_ends_on='{day(0)}' where id={sid2}"); run_job()
chk('default rule on the last day: HQ is told and the seat stays', sql(f"select count(*) from notifications where user_id={hqid} and title='A leader term ends today'")=='1' and s2.get(B+'/me').json()['user']['is_leader'] and sql(f"select count(*) from seats where id={sid2} and ends_at is null")=='1')
chk('rule can be changed to automatic, and is recorded', hq.post(B+'/policy',json={'key':'seat_term_expiry','value':'auto','note':'test'}).ok and hq.get(B+'/hq/terms').json()['rule']=='auto')
chk('a rule value that is not offered is refused', hq.post(B+'/policy',json={'key':'seat_term_expiry','value':'delete_everyone','note':'x'}).status_code==422)
sql(f"update seats set term_ends_on='{day(-1)}' where id={sid2}"); run_job(); me2=s2.get(B+'/me').json()['user']
chk('automatic rule: the seat ends, with the reason kept', sql(f"select end_reason from seats where id={sid2}")=='term_expired' and sql(f"select count(*) from seats where id={sid2} and ends_at is not null")=='1')
chk('her leader access stops at once and her membership stays', not me2['is_leader'] and me2['seat'] is None and me2['status']=='active' and me2.get('chapter') and s2.get(B+'/leader/dashboard').status_code in (403,404), me2.get('status'))
chk('she is thanked once, and HQ is told the seat is vacant', sql(f"select count(*) from notifications where user_id={u2['id']} and title like 'Your term as%has ended'")=='1' and sql(f"select count(*) from notifications where user_id={hqid} and title='A leader term has ended and the seat is vacant'")=='1')
chk('a seat with no end date is never touched by the job', seat(u3).ok and (run_job() or True) and s3.get(B+'/me').json()['user']['is_leader'])
t=hq.get(B+'/hq/terms').json(); chk('the ended term is listed for replacement', any(x['user_id']==u2['id'] for x in t['ended']) and not any(x['user_id']==u2['id'] for x in t['due']), t['ended'])
r=seat(u2,term_ends_on=day(365)); chk('replacement: the same seat can be filled again, with a new term', r.ok and s2.get(B+'/me').json()['user']['seat']['term_ends_on']==day(365))
r=hq.post(B+f'/hq/seats/{sid1}/end-term',json={}); chk('HQ can end an appointment by hand; a second try finds nothing open', r.ok and not s1.get(B+'/me').json()['user']['is_leader'] and hq.post(B+f'/hq/seats/{sid1}/end-term',json={}).status_code==404)
chk('clearing an end date works', hq.post(B+f"/hq/seats/{s3.get(B+'/me').json()['user']['seat']['id']}/term",json={'term_ends_on':''}).json()['term_ends_on'] is None)
chk('every term change is in the activity log', int(sql("select count(*) from activity_log where action in ('seat_term','seat_term_ended')"))>=5)
# ---- newsletters and talk shows
r=hq.post(B+'/hq/announcements',json={'title':'WODDI Newsletter, issue 1','body':'News from both networks.','network':'ALL','role':'all','kind':'newsletter'}); chk('HQ sends a newsletter issue', r.ok and r.json().get('recipients',0)>=3, r.text)
hq.post(B+'/hq/announcements',json={'title':'A plain notice','body':'For everyone.','network':'ALL','role':'all'})
it=s1.get(B+'/announcements').json()['items']; kinds={x['title']:x['kind'] for x in it}
chk('members see which item is a newsletter', kinds.get('WODDI Newsletter, issue 1')=='newsletter' and kinds.get('A plain notice')=='announcement', kinds)
chk('a made-up kind is stored as an ordinary announcement', hq.post(B+'/hq/announcements',json={'title':'Odd kind','body':'x y z','network':'ALL','role':'all','kind':'<script>'}).ok and sql("select kind from announcements where title='Odd kind'")=='announcement')
s3lead=s3; r=s3lead.post(B+'/leader/announcements',json={'title':'Chapter note','body':'From your leader.','kind':'newsletter'}); chk('a leader cannot issue a newsletter: it stays a chapter announcement', (not r.ok) or sql("select kind from announcements where title='Chapter note'")=='announcement', r.text)
iso=lambda h: (datetime.datetime.now(datetime.UTC)+datetime.timedelta(hours=h)).strftime('%Y-%m-%dT%H:%M:%SZ')
r=hq.post(B+'/meetings',json={'title':'WODDI Talk Show: Raising daughters','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':iso(48),'ends_at':iso(49),'audience':'members:ALL','kind':'talk_show'}); chk('HQ schedules a talk show for all members', r.ok, r.text)
r2=hq.post(B+'/meetings',json={'title':'Leaders check-in','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':iso(50),'ends_at':iso(51),'audience':'leaders:ALL'})
ms=s1.get(B+'/meetings').json(); allm=[m for k in ms if isinstance(ms[k],list) for m in ms[k] if isinstance(m,dict) and 'title' in m]
mk={m['title']:m.get('kind') for m in allm}; chk('a member sees the talk show, marked as one', mk.get('WODDI Talk Show: Raising daughters')=='talk_show', mk)
chk('an ordinary meeting stays a meeting', sql("select kind from meetings where title='Leaders check-in'")=='meeting')
# ---- operations figures
o=hq.get(B+'/hq/operations'); j=o.json(); chk('v106: with the starting estimate, the dashboard shows 0 percent verified and the declared share, below the target', j['automation']['weighted']==30 and j['automation']['verified_share']==0 and 60<j['automation']['declared_share']<80, j['automation'])
sql("update workflow_catalogue set eligible_weight=null, manual_minutes_baseline=null, frequency=''")
o=hq.get(B+'/hq/operations'); j=o.json(); chk('operations figures load for HQ', o.ok and j['automation']['workflows']==30 and j['automation']['verified_share'] is None and j['automation']['target']==80 and j['delivery']['email_on'] is False and j['uptime']['measured'] is False, o.text[:300])
chk('automation share is not claimed before weights and verification exist', j['automation']['weighted']==0 and j['automation']['declared_share'] is None)
w=hq.get(B+'/hq/workflows').json()['items'][0]; hq.post(B+'/hq/workflows',json={**w,'eligible_weight':10,'manual_minutes_baseline':30,'verified_by':'Test Person','verification_date':day(0)})
j=hq.get(B+'/hq/operations').json(); chk('once a workflow is weighted and verified the share is calculated', j['automation']['weighted']==1 and j['automation']['verified_share']==100.0, j['automation'])
s1.post(B+'/cases',json={'category':'other','about':'','body':'I need help with my account please.'}); j=hq.get(B+'/hq/operations').json(); chk('an open private report shows as a support incident', j['support']['open_cases']==1 and j['support']['oldest_open_days']==0, j['support'])
chk('operations figures are for HQ only', s1.get(B+'/hq/operations').status_code==403 and S().get(B+'/hq/operations').status_code==401)
chk('the figures carry no names, emails or phones', not any(x in json.dumps(j) for x in ('@example.org','+23481','Tola')))
# ---- backup and restore
chk('a backup needs a signed-in super administrator', S().post(B+'/hq/backup',json={'password':'hq-password-1'}).status_code==401 and s1.post(B+'/hq/backup',json={'password':'246810'}).status_code==403)
chk('a backup needs her own password again', hq.post(B+'/hq/backup',json={'password':'wrong'}).status_code==403 and hq.post(B+'/hq/backup',json={}).status_code==403)
sql("update users set role='hq_admin' where email='hq@test.local'"); chk('an ordinary HQ login cannot take a backup', hq.post(B+'/hq/backup',json={'password':'hq-password-1'}).status_code==403); sql("update users set role='super_admin' where email='hq@test.local'")
sql("delete from login_attempts"); r=hq.post(B+'/hq/backup',json={'password':'hq-password-1'}); ok=r.ok and r.headers.get('Content-Type','').startswith('application/gzip')
chk('the backup downloads as one compressed file', ok and 'wdos-backup-' in r.headers.get('Content-Disposition','') and r.headers.get('Cache-Control')=='no-store', r.text[:200] if not ok else '')
lines=gzip.decompress(r.content).decode().splitlines() if ok else ['{}']; meta=json.loads(lines[0]); rowsn={}
for l in lines[1:]:
    t_=json.loads(l)['t']; rowsn[t_]=rowsn.get(t_,0)+1
chk('the file says what it holds, and holds exactly that', meta.get('format')=='wdos-backup-1' and all(rowsn.get(t_,0)==c for t_,c in meta['tables'].items()) and meta['tables']['users']==int(sql('select count(*) from users')), {t_:(c,rowsn.get(t_,0)) for t_,c in meta.get('tables',{}).items() if rowsn.get(t_,0)!=c})
chk('sign-in sessions and throttling counters are left out', not any(t_ in meta['tables'] for t_ in ('sessions','login_attempts','auth_challenges')))
chk('the backup is logged and shown on the dashboard', sql("select count(*) from activity_log where action='backup_taken'")=='1' and hq.get(B+'/hq/operations').json()['backup']['last']!='')
chk('nothing is left on the server', not any('backup' in f for f in os.listdir('/home/claude/wdos-php')) and not any(f.startswith('wdos-backup') for f in os.listdir('/tmp')))
open('/tmp/t106-backup.jsonl.gz','wb').write(r.content)
print('checks',cnt); print('FAILURES',bad)
