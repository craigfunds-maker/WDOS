# v109.44: birthday card (her photo, name and designation on a WODDI HQ design), its email, phone notification and dashboard place;
# the voice endpoint only for GitHub's signed job; the photo and birthday prompts stay until done; email twins for tasks and
# announcements to leaders and envoys (not members); the tone unlocks on a phone's first touch. Synthetic data only.
import requests, subprocess, json, time, base64, io, os, datetime
from PIL import Image, ImageDraw
X=str(int(time.time()))[-6:]; B='http://localhost:8080/api'; H={'X-WDOS':'1'}; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(x)[:400])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers.update(H); return s
def login(e,p): s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text[:200]); return s
def sent(needle): return [l for l in open('/tmp/aimock.log',encoding='utf-8',errors='replace') if needle in l]
def cron(now): return subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=dict(os.environ, ADMIN_KEY='x', WDOS_TEST_NOW=now, BREVO_API_KEY='ci-not-real', MAIL_FROM='wdos-ci@wdos-mail.org', BREVO_API_URL='http://127.0.0.1:9/v3/smtp/email'),capture_output=True,text=True)
sql("delete from login_attempts")
hq=login('hq@test.local','hq-password-1')
lag=datetime.datetime.now(datetime.UTC).replace(tzinfo=None)+datetime.timedelta(hours=1); TODAY=lag.strftime('%Y-%m-%d'); NOW=TODAY+' 10:30'
# an envoy whose birthday is today, with a photo
r=hq.post(B+'/hq/envoys',json={'first_name':'Bday','last_name':'Envoy','email':f'bd.{X}@example.org','phone':'','country_code':'NG'}); chk('envoy made', r.ok, r.text)
EM=f'bd.{X}@wdos-mail.org'
env=login(f'bd.{X}@example.org', r.json()['temporary_password']); eid=int(sql(f"select id from users where email='bd.{X}@example.org'"))
sql(f"update users set email='{EM}' where id={eid}")
me=env.get(B+'/me').json()['user']; chk('no birthday yet: she is asked for it on her dashboard', not me.get('dob'))
envjs=requests.get('http://localhost:8080/js/pages/envoy.js').text
chk('envoy dashboard carries the birthday question, the photo card and the birthday card', "u.dob ? '' : birthdayAsk()" in envjs and 'photoNudge(u)' in envjs and 'birthdayCard()' in envjs)
pj=requests.get('http://localhost:8080/js/photoask.js').text
chk('the photo prompt comes back every visit until a photo is saved', 'let askedThisVisit = false' in pj and 'sessionStorage' not in pj.split('export function needsPhoto')[1].split('}')[0])
im=Image.new('RGB',(600,600),(240,200,170)); d=ImageDraw.Draw(im); d.ellipse((150,120,450,480),fill=(120,70,40)); d.ellipse((230,250,270,290),fill='white'); d.ellipse((330,250,370,290),fill='white'); d.arc((230,330,370,420),0,180,fill='white',width=8)
buf=io.BytesIO(); im.save(buf,'JPEG'); data='data:image/jpeg;base64,'+base64.b64encode(buf.getvalue()).decode()
r=env.post(B+'/me/photo',json={'data':data}); chk('photo saved', r.ok, r.text)
sql(f"update users set dob='{int(TODAY[:4])-38}{TODAY[4:]}', designation='WODDI State Envoy, Bauchi' where id={eid}")
r=env.get(B+'/me/birthday-card').json(); chk('on her birthday the dashboard has her card', r.get('today') and r.get('card','').endswith('/card.jpg'), r)
c=requests.get(r['card']); chk('the card is a picture, reachable from her email without signing in', c.ok and c.headers.get('content-type')=='image/jpeg', c.status_code)
open('/home/claude/shots/bday-card.jpg','wb').write(c.content)
ci=Image.open(io.BytesIO(c.content)); chk('card is 1080 by 1350', ci.size==(1080,1350), ci.size)
px=ci.convert('RGB').getpixel((540,615)); chk('her photo is in the circle, not the empty pink', abs(px[0]-px[1])>30 and px!=(242,215,228), px)
chk('a wrong link shows nothing', requests.get(B+'/birthday/'+'0'*40+'/card.jpg').status_code==404)
# the greeting on the day
sql("delete from cron_runs where job='birthdays'; delete from mail_outbox")
out=cron(NOW)
n=sql(f"select concat(title,'|',url) from notifications where user_id={eid} and kind='birthday' order by id desc limit 1")
chk('in-app and phone greeting opens her card', n.startswith('Happy birthday, Bday|#/birthday'), n+out.stderr[-300:])
m=sql(f"select subject from mail_outbox where to_email='{EM}' and subject like 'Happy birthday%'")
chk('her birthday email is queued', m=='Happy birthday, Bday', sql("select to_email,subject from mail_outbox"))
html=sql(f"select html from mail_outbox where to_email='{EM}' and subject like 'Happy birthday%'")
chk('the email carries her card picture and the WODDI logo', '/card.jpg' in html and 'woddi-email.png' in html, html[:300])
# the voice: only GitHub's signed job may ask or send
chk('voice list refused without the GitHub token', S().get(B+'/voice/birthdays').status_code==401)
chk('voice list refused with a made-up token', S().get(B+'/voice/birthdays',headers={'X-GitHub-OIDC':'aaa.bbb.ccc'}).status_code==401)
chk('voice upload refused without the GitHub token', S().post(B+f'/voice/birthdays/{eid}/{TODAY[:4]}',data=b'ID3'+b'x'*3000).status_code==401)
# with the voice in place, her dashboard plays it
sql(f"update birthday_media set voice=0x494433{'00'*2500}, voice_at=utc_timestamp() where user_id={eid}")
r=env.get(B+'/me/birthday-card').json(); chk('when the voice has arrived, the dashboard has it', (r.get('voice') or '').endswith('/voice.mp3'), r)
v=requests.get(r['voice']); chk('the voice is served as MP3', v.ok and v.headers.get('content-type')=='audio/mpeg')
# email twins: a leader gets tasks and announcements by email as well; an ordinary member does not
lid=sql("select s.user_id from seats s where s.ends_at is null order by s.id limit 1"); LEM=f'lead.{X}@wdos-mail.org'; sql(f"update users set email='{LEM}', status='active' where id={lid}")
mid=sql("select u.id from users u where u.role='member' and u.status='active' and not exists (select 1 from seats s where s.user_id=u.id and s.ends_at is null) order by u.id limit 1"); MEM=f'mem.{X}@wdos-mail.org'; sql(f"update users set email='{MEM}' where id={mid}")
r=hq.post(B+'/tasks',json={'title':f'Twin task {X}','assignees':[int(lid),int(mid)],'due_on':(lag+datetime.timedelta(days=9)).strftime('%Y-%m-%d')}); chk('HQ gives a task', r.ok, r.text)
time.sleep(2)
chk('the leader also gets the new task by email', any(LEM in l and f'Twin task {X}' in l for l in sent(LEM)) or sql(f"select count(*) from mail_outbox where to_email='{LEM}' and subject like '%Twin task {X}%'")!='0', sql(f"select subject from mail_outbox where to_email='{LEM}'"))
chk('an ordinary member is not emailed for it', sql(f"select count(*) from mail_outbox where to_email='{MEM}' and subject like '%Twin task {X}%'")=='0' and not any(MEM in l and 'Twin task' in l for l in sent(MEM)))
r=hq.post(B+'/hq/announcements',json={'title':f'Twin news {X}','body':'Please read this.','network':'ALL','role':'all'}); chk('HQ sends an announcement', r.ok, r.text)
time.sleep(2)
chk('leader gets the announcement by email', sql(f"select count(*) from mail_outbox where to_email='{LEM}' and subject like '%Twin news {X}%'")!='0' or any(f'Twin news {X}' in l for l in sent(LEM)))
chk('the envoy gets it by email too', sql(f"select count(*) from mail_outbox where to_email='{EM}' and subject like '%Twin news {X}%'")!='0' or any(f'Twin news {X}' in l for l in sent(EM)))
chk('and only once each', sql(f"select count(*) from mail_outbox where to_email='{LEM}' and subject like '%Twin news {X}%'") in ('0','1'))
ui=requests.get('http://localhost:8080/js/ui.js').text; sw=requests.get('http://localhost:8080/sw.js').text
chk('the tone is unlocked by the first touch on a phone', "window.addEventListener(e, unlockSound" in ui and 'decodeAudioData' in ui)
chk('phone notifications vibrate and are not silent', 'vibrate: [200, 100, 200, 100, 300]' in sw and 'silent: false' in sw and "image: d.image" in sw)
print(f'checks {ok+bad} FAILURES {bad}')
