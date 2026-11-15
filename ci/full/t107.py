# v108.0: the AI service. Run as: t107.py nokey  (no lane configured)  then  t107.py mock  (local stand-in provider)
import requests, subprocess, json, sys, os, datetime
B='http://localhost:8080/api'; MODE=sys.argv[1]
def S():
    s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
def sql(q, db='wdos'): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot',db,'-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0; cnt=0
def chk(n,c,e=''):
    global bad,cnt; cnt+=1
    if not c: bad+=1; print('FAIL',n,str(e)[:400])
sql('delete from ai_vectors'); sql('delete from login_attempts')
hq=S(); assert hq.post(B+'/auth/login',json={'email':'hq@test.local','password':'hq-password-1'}).ok
ld=S(); assert ld.post(B+'/auth/login',json={'email':'leader@test.local','password':'leader-pass-1'}).ok
anon=S()
period=(datetime.date.today().replace(day=1)-datetime.timedelta(days=1)).strftime('%Y-%m')
# invented report and issue for Nigeria, holding details that must never leave WDOS on a free lane
ou=sql("select id from org_units where country_code='NG' and level='country' limit 1"); owner=sql("select id from users where email='leader@test.local'")
sql("delete from report_instances where report_no='T107-1'"); sql("delete from issues where issue_no='T107-I'")
ans=json.dumps({'narrative':'We held two chapter meetings in Bwari and trained twelve women. Call Tola Adeyemi on +234 801 234 5678 or tola.private@example.org, member WGMN-NG-000123, see https://example.org/sheet for the list.','n':12}).replace("'","''")
sql(f"insert into report_instances (report_no,template,unit_key,network,org_unit_id,unit_label,owner_id,period_start,period_end,status,answers,submitted_at,on_time,created_at,updated_at) values ('T107-1','monthly','t107','WGMN',{ou},'Nigeria WGMN',{owner},'{period}-01','{period}-28','submitted','{ans}',now(),1,now(),now())")
sql(f"insert into issues (issue_no,raised_by,org_unit_id,network,title,category,severity,impact,status,created_at) values ('T107-I',{owner},{ou},'WGMN','Hall not available for meetings','logistics','red','Two meetings moved','open','{period}-10 10:00:00')")
sql("update users set first_name='Tola', last_name='Adeyemi' where email='sweep.member@example.org'"); sql(f"insert ignore into users (member_no,email,first_name,last_name,network,country_code,role,status,created_at) values ('WGMN-NG-T107','t107.name@example.org','Tola','Adeyemi','WGMN','NG','member','active',now())")
# access: every /hq/ai route is HQ only
for path in ['/hq/ai','/hq/ai/report-digest','/hq/ai/brief','/hq/ai/coverage-gaps']:
    chk('leader refused '+path, ld.get(B+path).status_code==403); chk('signed-out refused '+path, anon.get(B+path).status_code==401)
for path in ['/hq/ai/test','/hq/ai/report-digest','/hq/ai/brief','/hq/ai/library-index']:
    chk('leader refused POST '+path, ld.post(B+path,json={}).status_code==403)
st=hq.get(B+'/hq/ai').json(); body=json.dumps(st)
chk('status never carries a key value', 'Bearer' not in body and 'token' not in body.lower().replace('cf_api_token','').replace('hf_token',''), body[:300])
f=hq.get(B+f'/hq/ai/report-digest?period={period}&country=NG').json()
chk('digest facts counted from records', f['facts']['reports']['total']>=1 and f['facts']['reports']['submitted']>=1 and f['facts']['open_issues']>=1, f['facts']['reports'])
bf=hq.get(B+'/hq/ai/brief').json()
chk('brief facts are numbers or null, never invented', all(v is None or isinstance(v,(int,list)) for g in ('growth','activity','concerns') for v in bf['facts'][g].values()), bf['facts'])
chk('brief members_total equals the database', bf['facts']['growth']['members_total']==int(sql("select count(*) from users where role not in ('hq_admin','super_admin') and network is not null")))
g=hq.get(B+'/hq/ai/coverage-gaps').json()
chk('coverage gaps ranked with reasons', isinstance(g['areas'],list) and all(a['why'] for a in g['areas']) and g['areas']==sorted(g['areas'],key=lambda a:-a['score'])[:len(g['areas'])] or len(g['areas'])<2, g['areas'][:2])
chk('coverage member counts equal the database', sum(a['members'] for a in g['areas'])<=int(sql("select count(*) from users where network is not null")))
# library: two documents
for t,d,c in [('Finance policy','How chapter funds are kept and reported','Policies'),('Code of conduct','How leaders behave','Leadership')]:
    if not sql(f"select 1 from library_items where title='{t}'"): hq.post(B+'/library',json={'title':t,'description':d,'category':c,'audience':'all','url':'https://example.org/doc'})
w=ld.get(B+'/library/search?q=finance').json(); chk('library word search works', w['mode']=='words' and any(i['title']=='Finance policy' for i in w['items']), w)
chk('library search needs sign-in', anon.get(B+'/library/search?q=x').status_code==401)
if MODE=='nokey':
    chk('no lane listed without a key', st['lanes']==[], st['lanes'])
    r=hq.post(B+'/hq/ai/brief',json={'lang':'en'}).json(); chk('brief degrades: no_key, facts still returned', r['ok'] is False and r['reason']=='no_key' and r['facts']['growth']['members_total'] is not None, r)
    r=hq.post(B+'/hq/ai/report-digest',json={'country':'NG','period':period}).json(); chk('digest degrades: no_key, facts still returned', r['ok'] is False and r['reason']=='no_key' and r['facts']['reports']['total']>=1, r)
    r=hq.post(B+'/hq/ai/test',json={}).json(); chk('lane test says no key', r['ok'] is False and r['reason']=='no_key', r)
    chk('nothing logged as a call when no lane exists', sql("select count(*) from ai_calls")=='0')
    m=ld.get(B+'/library/search?q=money').json(); chk('meaning query falls back to words, no error', m['mode']=='words', m)
else:
    open('/tmp/aimock.log','w').close(); open('/tmp/aimock.mode','w').write('up'); sql("delete from ai_cache"); sql("delete from ai_calls"); sql("delete from ai_outputs"); sql("delete from login_attempts")
    chk('mock lane listed, free', [l['lane'] for l in st['lanes']]==['mock'] and st['lanes'][0]['paid'] is False, st['lanes'])
    r=hq.post(B+'/hq/ai/test',json={}).json(); chk('lane test answers', r['ok'] and r['lane']=='mock', r)
    r=hq.post(B+'/hq/ai/report-digest',json={'country':'NG','period':period,'lang':'fr'}).json()
    chk('digest written and kept', r['ok'] and 'DONE' in r['digest']['body'] and r['redacted'] is True, r)
    sent=[json.loads(l) for l in open('/tmp/aimock.log')][-1]['body']['messages']; txt=sent[1]['content']
    chk('phone removed before sending', '801 234' not in txt and '[phone]' in txt, txt[-600:])
    chk('email removed before sending', 'tola.private' not in txt and '[email]' in txt)
    chk('link and member number removed', 'example.org/sheet' not in txt and 'WGMN-NG-000123' not in txt)
    chk('member names removed', 'Tola' not in txt and 'Adeyemi' not in txt and '[name]' in txt, txt[-600:])
    chk('asked to write in French and never invent', 'French' in sent[0]['content'] and 'ONLY the material' in sent[0]['content'])
    chk('kept digest is returned on the next read', hq.get(B+f'/hq/ai/report-digest?period={period}&country=NG&lang=fr').json()['digest'] is not None)
    n0=len(open('/tmp/aimock.log').readlines()); r2=hq.post(B+'/hq/ai/report-digest',json={'country':'NG','period':period,'lang':'fr'}).json()
    chk('second identical request comes from the cache, no provider call', r2['ok'] and len(open('/tmp/aimock.log').readlines())==n0)
    r=hq.post(B+'/hq/ai/brief',json={'lang':'en'}).json(); chk('brief written', r['ok'] and 'GROWTH' in r['brief']['body'], r)
    btxt=[json.loads(l) for l in open('/tmp/aimock.log')][-1]['body']['messages'][1]['content']; chk('brief sends counts only: no email, phone or member name', '@' not in btxt and 'Tola' not in btxt and '+234' not in btxt, btxt[:300])
    chk('every call logged with lane and time', int(sql("select count(*) from ai_calls where lane='mock' and ok=1"))>=3 and int(sql("select count(*) from ai_calls where cached=1"))>=1)
    u=hq.get(B+'/hq/ai').json(); chk('usage shown to HQ', any(x['feature']=='report_digest' for x in u['usage']) and u['used_today']>=3, u['usage'])
    # rule: text never on a free lane
    hq.post(B+'/policy',json={'key':'ai_free_lane_text','value':'off','note':'t107'}); sql("delete from ai_cache")
    r=hq.post(B+'/hq/ai/report-digest',json={'country':'NG','period':period}).json(); chk('rule off: digest needs a paid key, facts still returned', r['ok'] is False and r['reason']=='needs_paid_key' and r['facts']['reports']['total']>=1, r)
    r=hq.post(B+'/hq/ai/brief',json={'lang':'pt'}).json(); chk('rule off: counts-only brief still allowed', r['ok'], r)
    hq.post(B+'/policy',json={'key':'ai_free_lane_text','value':'redacted','note':'t107'})
    # provider down: graceful
    open('/tmp/aimock.mode','w').write('down'); sql("delete from ai_cache")
    r=hq.post(B+'/hq/ai/brief',json={'lang':'sw'}); chk('provider down: 200 with reason all_failed and facts', r.status_code==200 and r.json()['ok'] is False and r.json()['reason']=='all_failed' and 'facts' in r.json(), r.text[:200])
    chk('failure logged', int(sql("select count(*) from ai_calls where ok=0 and error like 'HTTP 503%'"))>=1)
    m=ld.get(B+'/library/search?q=money'); chk('provider down: library search still answers by words', m.status_code==200 and m.json()['mode']=='words', m.text[:200])
    open('/tmp/aimock.mode','w').write('up')
    # library by meaning
    r=hq.post(B+'/hq/ai/library-index',json={}).json(); chk('library indexed', r['indexed']>=2, r)
    m=ld.get(B+'/library/search?q=money').json(); chk('search by meaning finds the finance document from the word money', m['mode']=='meaning' and m['items'] and m['items'][0]['title']=='Finance policy', m)
    hqonly=sql("select count(*) from library_items where audience='hq'")
    chk('meaning search respects audience', all(i['audience']!='hq' for i in m['items']))
    # switch off
    hq.post(B+'/policy',json={'key':'ai_features','value':'off','note':'t107'})
    r=hq.post(B+'/hq/ai/brief',json={'lang':'en'}).json(); chk('ai_features off: reason off', r['ok'] is False and r['reason']=='off', r)
    hq.post(B+'/policy',json={'key':'ai_features','value':'on','note':'t107'})
    chk('private reports never reach a lane', not any('cases' in l or 'safeguard' in l.lower() for l in open('/tmp/aimock.log')))
print('checks',cnt); print('FAILURES',bad)
