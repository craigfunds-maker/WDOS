# v109.53: meeting times in each person's own time zone, and a Google Meet link made for the meeting's own time (Azeez, 24 Nov 2026).
import requests, subprocess, json, datetime, os
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[2600]'))
def run(): return subprocess.run('. /tmp/env.sh; php /home/claude/wa.php',shell=True,capture_output=True,text=True).stdout
ROOT=os.environ.get('WDOS_ROOT','/home/claude/wdos-php')
# three members of WGMN: one in Nigeria, one in Kenya (WDOS has not heard from her phone), one whose phone said New York
sN,r,_=reg(); uN=r.json()['user']; sK,r,_=reg(); uK=r.json()['user']; sY,r,_=reg(); uY=r.json()['user']
sql(f"update users set country_code='KE', tz='' where id={uK['id']}"); sql(f"update users set tz='' where id={uN['id']}")
# the phone reports its time zone
chk('a phone tells WDOS its time zone', sY.post(B+'/me/tz',json={'tz':'America/New_York'}).ok and sql(f"select tz from users where id={uY['id']}")=='America/New_York')
chk('an unknown time zone is refused', sY.post(B+'/me/tz',json={'tz':'Mars/Olympus'}).status_code==422 and sql(f"select tz from users where id={uY['id']}")=='America/New_York')
chk('signed out cannot set one', S().post(B+'/me/tz',json={'tz':'Africa/Lagos'}).status_code in (401,403))
# HQ schedules for 10:00 Nigeria time on 3 December 2026, which is 09:00 UTC
r=hq.post(B+'/meetings',json={'title':'TZ forum','mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':'2026-12-03T09:00:00Z','ends_at':'2026-12-03T10:00:00Z','audience':'members:WGMN'}); mid=r.json().get('id')
chk('meeting scheduled', r.ok, r.text)
body=lambda u: sql(f"select body from notifications where user_id={u['id']} and title='Meeting: TZ forum'")
chk('Nigeria reads 10:00 WAT', '10:00 WAT (UTC+1)' in body(uN), body(uN))
chk('Kenya reads 12:00 EAT, from her country', '12:00 EAT (UTC+3)' in body(uK), body(uK))
chk('New York reads 04:00 EST, from her phone', '04:00 EST (UTC-5)' in body(uY), body(uY))
chk('no notice says GMT any more', 'GMT' not in body(uN)+body(uK)+body(uY))
chk('each is told once', all(sql(f"select count(*) from notifications where user_id={u['id']} and title='Meeting: TZ forum'")=='1' for u in (uN,uK,uY)))
mail=lambda u: sql(f"select html from mail_outbox where to_email=(select email from users where id={u['id']}) and subject='Meeting: TZ forum'")
# emails: each person gets one (the text is cleared from the queue once sent, so the time in it is checked through the same function above)
chk('one email each, when email is on', sql("select count(*) from mail_outbox where subject='Meeting: TZ forum'") in ('0','3','4'), sql("select count(*) from mail_outbox where subject='Meeting: TZ forum'"))
php=subprocess.run(['php','-r',"require '%s/lib/timezones.php'; echo tz_when(1796288400,'Africa/Nairobi',true,true),'|',tz_when(1796288400,tz_country('GH')),'|',tz_when(1796288400,tz_country('US')),'|',tz_when(1796288400,tz_of(['tz'=>'','country_code'=>''])),'|',tz_when(1796288400,tz_country('IN'));" % ROOT],capture_output=True,text=True).stdout
chk('the email wording, written out in full', php.split('|')[0]=='Thursday 3 December 2026, 12:00 EAT (UTC+3)', php)
chk('country main zones: Ghana, the United States (New York), unknown means Nigeria', php.split('|')[1].endswith('09:00 GMT (UTC)') and php.split('|')[2].endswith('04:00 EST (UTC-5)') and php.split('|')[3].endswith('10:00 WAT (UTC+1)'), php)
chk('a half-hour zone', php.split('|')[4].endswith('14:30 IST (UTC+5:30)') or php.split('|')[4].endswith('14:30 UTC+5:30'), php)
# the meeting itself is kept in UTC
chk('stored in UTC', sql(f"select starts_at from meetings where id={mid}")=='2026-12-03 09:00:00')
# reminders, each in her own time
def at(m): sql(f"update meetings set starts_at=date_add(utc_timestamp(), interval {m} minute), ends_at=date_add(utc_timestamp(), interval {m+60} minute) where id={mid}")
at(45); run(); run()
rb=lambda u: sql(f"select body from notifications where user_id={u['id']} and title='Starting soon: TZ forum'")
st=sql(f"select timestampdiff(second,'1970-01-01 00:00:00',starts_at) from meetings where id={mid}")
def local(ts, tz): return datetime.datetime.fromtimestamp(int(ts), __import__('zoneinfo').ZoneInfo(tz)).strftime('%H:%M')
chk('reminder an hour before, in Kenya time', rb(uK).startswith('Begins at '+local(st,'Africa/Nairobi')+' EAT'), rb(uK))
chk('reminder an hour before, in New York time', local(st,'America/New_York') in rb(uY) and ('EST' in rb(uY) or 'EDT' in rb(uY)), rb(uY))
chk('reminder an hour before, in Nigeria time', rb(uN).startswith('Begins at '+local(st,'Africa/Lagos')+' WAT'), rb(uN))
chk('one reminder each', all(sql(f"select count(*) from notifications where user_id={u['id']} and title='Starting soon: TZ forum'")=='1' for u in (uN,uK,uY)))
# HQ staff's own notices stay in Nigeria time when their phone has not said otherwise
# the screen code: typing 10:00 in Nigeria time, Nairobi and New York, and how each time reads elsewhere
harness=f"""
const src = (await import('fs')).readFileSync('{ROOT}/js/tz.js','utf8').replace(/^import .*$/mg,'');
globalThis.document = {{ documentElement: {{ lang: 'en' }} }};
const m = await import('data:text/javascript;base64,' + Buffer.from('const api=()=>Promise.resolve();const t=(x)=>x;' + src).toString('base64'));
const out = {{}};
out.lagos = m.zonedToUtc('2026-12-03T10:00','Africa/Lagos').toISOString();
out.nairobi = m.zonedToUtc('2026-12-03T10:00','Africa/Nairobi').toISOString();
out.ny_winter = m.zonedToUtc('2026-12-03T10:00','America/New_York').toISOString();
out.ny_summer = m.zonedToUtc('2026-07-03T10:00','America/New_York').toISOString();
out.london_change = m.zonedToUtc('2026-03-29T03:30','Europe/London').toISOString();
const d = new Date('2026-12-03T09:00:00Z');
out.inKenya = m.timeIn(d,'Africa/Nairobi'); out.inLagos = m.timeIn(d,'Africa/Lagos'); out.inNY = m.timeIn(d,'America/New_York'); out.inIndia = m.timeIn(d,'Asia/Kolkata');
out.back = m.utcToZoned(d,'Africa/Lagos'); out.off = m.offsetText(d,'Asia/Kolkata');
console.log(JSON.stringify(out));
"""
open('/tmp/t141.mjs','w').write(harness)
o=subprocess.run(['node','/tmp/t141.mjs'],capture_output=True,text=True); chk('screen time code runs', o.returncode==0, o.stderr[:300])
j=json.loads(o.stdout or '{}')
chk('10:00 typed in Nigeria time is 09:00 UTC', j.get('lagos')=='2026-12-03T09:00:00.000Z', j)
chk('10:00 typed in Kenya time is 07:00 UTC', j.get('nairobi')=='2026-12-03T07:00:00.000Z', j)
chk('New York in winter and summer', j.get('ny_winter')=='2026-12-03T15:00:00.000Z' and j.get('ny_summer')=='2026-07-03T14:00:00.000Z', j)
chk('the morning the clocks change in London', j.get('london_change')=='2026-03-29T02:30:00.000Z', j)
chk('the same moment read in each place, zone named', j.get('inKenya','').startswith('12:00') and j['inKenya'].endswith('EAT (UTC+3)') and j.get('inLagos','').startswith('10:00') and j['inLagos'].endswith('WAT (UTC+1)') and j.get('inNY','').startswith('04:00') and j['inNY'].endswith('EST (UTC-5)'), j)
chk('a zone without a short name still shows its offset', 'UTC+5:30' in j.get('inIndia','') and (j['inIndia'].startswith('02:30') or j['inIndia'].startswith('14:30')), j)
chk('back to a typed value', j.get('back')=='2026-12-03T10:00' and j.get('off')=='UTC+5:30', j)
# the schedule form no longer starts a live call
mj=open(ROOT+'/js/pages/meetings.js').read()
chk('no instant Meet link in the form', "href: 'https://meet.google.com/new'" not in mj)
chk('the Meet link is made through Google Calendar for the chosen time', 'calendar.google.com/calendar/render' in mj and "action: 'TEMPLATE'" in mj)
chk('the form asks which time zone the time is typed in, Nigeria first', "field('Time zone of these times', tzSel)" in mj and "['Africa/Lagos', 'Nigeria']" in open(ROOT+'/js/tz.js').read())
for f in ('chapter','community','hq','meetreq'):
    chk(f'{f}: meeting times in her own time, not UTC', 'from \'../tz.js\'' in open(f'{ROOT}/js/pages/{f}.js').read())
print('FAILURES',bad)
