import requests, subprocess, json, datetime
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[1500]'))
def run(): return subprocess.run('. /tmp/env.sh; php /home/claude/wa.php',shell=True,capture_output=True,text=True).stdout
U=datetime.UTC; t=lambda **k:(datetime.datetime.now(U)+datetime.timedelta(**k)).strftime('%Y-%m-%dT%H:%M:%SZ')
s1,r,b1=reg(); u1=r.json()['user']; s2,r,b2=reg(network='WNNN',dob='2001-02-03'); u2=r.json()['user']
gh=[c for c in cs if c['code']=='GH'][0]['id']
aud=hq.get(B+'/meetings').json()['audiences']; chk('HQ can invite leaders, all members of either network, or the HQ team', set(aud)=={'people:ALL','leaders:ALL','leaders:WGMN','leaders:WNNN','members:ALL','members:WGMN','members:WNNN','hq_team:ALL'}, aud)
chk('a member cannot schedule a meeting', s1.post(B+'/meetings',json={'title':'My own','location':'https://meet.google.com/abc-defg-hij','starts_at':t(days=2),'ends_at':t(days=2,hours=1),'audience':'members:WGMN'}).status_code==403)
r=hq.post(B+'/meetings',json={'title':'WGMN members forum','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':t(days=2),'ends_at':t(days=2,hours=1),'audience':'members:WGMN','agenda':'1. Welcome\n2. Chapters'}); mid=r.json().get('id'); chk('HQ schedules for all WGMN members with a Meet link and agenda', r.ok and r.json()['invited']>=1, r.text)
chk('the WGMN member is invited and told in the app', s1.get(B+f'/meetings/{mid}').ok and sql(f"select count(*) from notifications where user_id={u1['id']} and kind='meeting' and title like 'Meeting: WGMN members forum%'")=='1')
chk('the WNNN member is not invited and cannot open it', s2.get(B+f'/meetings/{mid}').status_code==403 and sql(f"select count(*) from notifications where user_id={u2['id']} and kind='meeting'")=='0')
r=hq.post(B+'/meetings',json={'title':'Ghana only','location':'https://meet.google.com/xyz-abcd-efg','starts_at':t(days=3),'ends_at':t(days=3,hours=1),'audience':'members:ALL','country_id':gh}); gid=r.json()['id']
chk('a meeting kept to one country leaves out members elsewhere', r.ok and s1.get(B+f'/meetings/{gid}').status_code==403 and 'Ghana' in hq.get(B+f'/meetings/{gid}').json()['meeting']['scope_label'], hq.get(B+f'/meetings/{gid}').json()['meeting']['scope_label'])
r=hq.post(B+'/meetings',json={'title':'Everyone','location':'https://meet.google.com/all-abcd-efg','starts_at':t(days=4),'ends_at':t(days=4,hours=1),'audience':'members:ALL'}); aid=r.json()['id']; chk('both networks: both members invited', s1.get(B+f'/meetings/{aid}').ok and s2.get(B+f'/meetings/{aid}').ok)
# replies, link opens
s1.post(B+f'/meetings/{mid}/rsvp',json={'answer':'yes'}); chk('pressing Join records that she opened the link, once', s1.post(B+f'/meetings/{mid}/join',json={}).ok and s1.post(B+f'/meetings/{mid}/join',json={}).ok and sql(f"select count(*) from meeting_joins where meeting_id={mid}")=='1')
chk('someone not invited cannot record a join', s2.post(B+f'/meetings/{mid}/join',json={}).status_code==403)
d=hq.get(B+f'/meetings/{mid}').json(); me=[p for p in d['people'] if p['id']==u1['id']][0]; chk('HQ sees her reply and that she opened the link', d['opened_link']==1 and me['rsvp']=='yes' and me['joined'], me)
sm=hq.get(B+'/hq/meetings/summary').json(); x=[m for m in sm['items'] if m['id']==mid][0]; chk('HQ dashboard summary: invited, replies and link opens', x['upcoming'] and x['rsvp']['yes']==1 and x['opened_link']==1 and x['invited']>=1 and s1.get(B+'/hq/meetings/summary').status_code==403, x)
# reminders
n0=sql(f"select count(*) from notifications where user_id={u1['id']} and kind='meeting'"); run(); chk('no reminder two days ahead', sql(f"select count(*) from notifications where user_id={u1['id']} and kind='meeting'")==n0)
def at(**k): sql(f"update meetings set starts_at=date_add(utc_timestamp(), interval {k['m']} minute), ends_at=date_add(utc_timestamp(), interval {k['m']+60} minute) where id={mid}")
at(m=23*60+40); run(); run(); chk('the day before: one reminder, once', sql(f"select count(*) from notifications where user_id={u1['id']} and title like 'Tomorrow: WGMN members forum%'")=='1')
at(m=45); run(); run(); chk('one hour before: one reminder, once', sql(f"select count(*) from notifications where user_id={u1['id']} and title like 'Starting soon: WGMN members forum%'")=='1')
at(m=3); run(); run(); chk('as it starts: one reminder, once, and it reaches her phone queue', sql(f"select count(*) from notifications where user_id={u1['id']} and title like 'Starting now: WGMN members forum%'")=='1')
chk('reminders never reached the member who was not invited', sql(f"select count(*) from notifications where user_id={u2['id']} and title like '%WGMN members forum%'")=='0')
# attendance and minutes
chk('HQ marks attendance', hq.post(B+f'/meetings/{mid}/attendance',json={'marks':{str(u1['id']):'present'}}).ok and hq.get(B+f'/meetings/{mid}').json()['present']==1)
r=hq.post(B+f'/meetings/{mid}/close',json={'minutes':'We agreed to open two chapters in Bwari.','actions':[{'action':'Send the chapter list','deadline':'','owner_id':0}]}); chk('closing records minutes and creates the action', r.ok and r.json()['tasks']==1, r.text)
chk('everyone invited is told the minutes are ready', sql(f"select count(*) from notifications where user_id={u1['id']} and title like 'Minutes are ready: WGMN members forum%'")=='1' and 'two chapters' in s1.get(B+f'/meetings/{mid}').json()['meeting']['minutes'])
x=[m for m in hq.get(B+'/hq/meetings/summary').json()['items'] if m['id']==mid][0]; chk('summary shows it as held with attendance', not x['upcoming'] and x['present']==1, x)
print('FAILURES',bad)
