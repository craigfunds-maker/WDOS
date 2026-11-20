# v109.35: system health page and email delivery; volunteer leaders at a glance with clickable filters. Synthetic data.
import requests, subprocess
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p): s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok, r.text; return s
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
chk('migration 044 added the reason column', sql("show columns from mail_outbox like 'last_error'")!='')
sql("insert into mail_outbox (to_email,to_name,subject,html,status,attempts,created_at,last_error) values ('a@example.org','A','WDOS: When is your birthday?','<p>x</p>','failed',3,utc_timestamp(),'HTTP 401: We have detected you are using an unrecognised IP address 66.235.168.24. If you performed this action make sure to add the new IP address')")
sql("insert into mail_outbox (to_email,to_name,subject,html,status,attempts,created_at,last_error) values ('b@example.org','B','WDOS: When is your birthday?','<p>x</p>','failed',3,utc_timestamp(),'HTTP 401: We have detected you are using an unrecognised IP address 66.235.168.24. If you performed this action make sure to add the new IP address')")
d=hq.get(B+'/hq/system').json()
it={i['key']:i for i in d['items']}
chk('self-check counts the failed emails', it['email']['n']>=2 and it['email']['href']=='#/hq/system/email', it['email'])
chk('every item has a place to open', all(i['href'].startswith('#/') for i in d['items']))
e=d['email']; chk('the reason is shown', any('unrecognised IP' in r['reason'] for r in e['reasons']), e['reasons'])
chk('the server address block is recognised, with the fix link', e['ip_block'] and e['ip_block']['ip']=='66.235.168.24' and 'authorised_ips' in e['ip_block']['fix_url'], e['ip_block'])
chk('what failed, grouped', any(s['subject']=='WDOS: When is your birthday?' and int(s['n'])>=2 for s in e['subjects']), e['subjects'])
r=hq.post(B+'/hq/system/email-retry',json={}); chk('no resend while Brevo still refuses', r.status_code==409, r.text)
chk('failed ones untouched', sql("select count(*) from mail_outbox where status='failed' and to_email in ('a@example.org','b@example.org')")=='2')
chk('a leader cannot open system health', ld.get(B+'/hq/system').status_code in (401,403))
chk('a leader cannot resend', ld.post(B+'/hq/system/email-retry',json={}).status_code in (401,403))
g=hq.get(B+'/hq/leaders-glance').json()
tot=int(sql("select count(distinct s.user_id) from seats s join users u on u.id=s.user_id where s.ends_at is null and u.status='active'"))
chk('total volunteer leaders', g['total']==tot, (g['total'],tot))
chk('network split adds up to at least the total', g['by_network']['WGMN']+g['by_network']['WNNN']>=g['total'])
chk('by level, country and state', len(g['by_level'])==5 and g['by_country'] and 'state' in (g['by_state'][0] if g['by_state'] else {'state':1}))
m=hq.get(B+'/hq/members?kind=leaders').json(); chk('the total opens the same people', m['total']==g['total'], (m['total'],g['total']))
w=hq.get(B+'/hq/members?kind=leaders&seat_network=WGMN').json(); chk('WGMN tile opens WGMN leaders', w['total']==g['by_network']['WGMN'], (w['total'],g['by_network']))
c=g['by_country'][0]; x=hq.get(B+'/hq/members?kind=leaders&seat_country='+c['code']).json(); chk('a country bar opens its leaders', x['total']==int(c['total']), (x['total'],c))
if g['by_state']:
  st=g['by_state'][0]; x=hq.get(B+'/hq/members?'+requests.compat.urlencode({'kind':'leaders','seat_country':st['code'],'seat_state':st['state']})).json(); chk('a state bar opens its leaders', x['total']==int(st['total']), (x['total'],st))
lv=[l for l in g['by_level'] if l['WGMN']+l['WNNN']>0][0]; x=hq.get(B+'/hq/members?kind=leaders&level='+lv['level']).json(); chk('a level bar opens its leaders', x['total']>0)
x=hq.get(B+'/hq/members?kind=leaders&seen=never').json(); chk('never signed in opens them', x['total']==g['never_signed_in'], (x['total'],g['never_signed_in']))
dsh=hq.get(B+'/hq/dashboard').json(); chk('dashboard levels carry their key', all('level' in l for l in dsh['seats_by_level'])); chk('leader split on the tile', 'leaders_wgmn' in dsh['kpis'])
chk('leader cannot open the glance', ld.get(B+'/hq/leaders-glance').status_code in (401,403))
# v109.36: data checks open their lists
sy={i['key']:i for i in hq.get(B+'/hq/system').json()['items']}
chk('data checks listed', {'dup_phone','no_country','no_chapter','never_signed'} <= set(sy), list(sy))
for k,q in [('no_country','country=none'),('no_chapter','chapter=none'),('dup_phone','dup=phone')]:
  r=hq.get(B+'/hq/members?'+q).json(); chk(f'{k} opens the same people', r['total']==sy[k]['n'], (k, r['total'], sy[k]['n']))
print(f'checks {ok+bad} FAILURES {bad}')
