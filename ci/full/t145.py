# v109.58: meetings come from WODDI; emails wait for the flier drawn in each language; Join goes through WDOS and is recorded;
# urgent phone notices; late and closing reminders; slides; minutes with attendance; translations add nothing (Azeez, 25 Nov 2026).
import requests, subprocess, json, datetime, os, time, base64, io, re
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[3900]'))
ROOT=os.environ.get('WDOS_ROOT','/home/claude/wdos-php')
U=datetime.UTC; tt=lambda **k:(datetime.datetime.now(U)+datetime.timedelta(**k)).strftime('%Y-%m-%dT%H:%M:%SZ')
from PIL import Image
def jpg(c=(212,0,106),w=1080,h=1350):
    b=io.BytesIO(); Image.new('RGB',(w,h),c).save(b,'JPEG'); return 'data:image/jpeg;base64,'+base64.b64encode(b.getvalue()).decode()
def mailhtml(addr, subj):
    out=''
    for l in open('/tmp/aimock.log',encoding='utf-8',errors='replace'):
        if 'smtp' not in l or addr not in l: continue
        try: b=json.loads(l)['body']
        except Exception: continue
        if str(b.get('subject','')).startswith(subj): out=b.get('htmlContent','')
    return out or sql(f"select html from mail_outbox where to_email='{addr}' and subject like '{subj}%' order by id desc limit 1")
def mails(addr,subj): return int(sql(f"select count(*) from mail_outbox where to_email='{addr}' and subject like '{subj}%'") or 0)
def wa(): return subprocess.run(['bash','-c','. /tmp/env.sh; BREVO_API_KEY=ci-not-real MAIL_FROM=wdos-ci@wdos-mail.org BREVO_API_URL=http://127.0.0.1:8099/v3/smtp/email php /home/claude/wa.php'],capture_output=True,text=True).stdout
ld=S('leader@test.local','leader-pass-1'); lid=ld.get(B+'/me').json()['user']['id']
sql(f"update users set lang='fr', email='amara.leader@example.org' where id={lid}")
base={'mode':'virtual','location':'https://meet.google.com/abc-defg-hij','audiences':['leaders:WGMN']}
# 1. a flier with a headline only: the translation adds nothing
r=hq.post(B+'/meetings',json={**base,'title':'Forum one','starts_at':tt(days=4),'ends_at':tt(days=4,hours=1),'flier_image':jpg((40,10,30),600,750),'flier_headline':'Forum one'})
mid=r.json()['id']; chk('with a flier the emails wait for the flier pictures', r.ok and r.json()['emails_held'] is True, r.text)
time.sleep(4)
chk('the headline is translated', sql(f"select headline from meeting_flier_texts where meeting_id={mid} and lang='fr'")=='[French] Forum one')
chk('no message was written, so the translation has none', sql(f"select count(*) from meeting_flier_texts where meeting_id={mid} and body<>''")=='0')
chk('no invitation email yet', mails('amara.leader@example.org','Meeting: Forum one')==0 and mailhtml('amara.leader@example.org','Meeting: Forum one')=='')
# 2. the host's browser sends the pictures, then the invitations
for l in ('en','fr'):
    r=hq.post(B+f'/meetings/{mid}/flier/render/{l}',json={'image':jpg((212,0,106) if l=='en' else (124,181,24))}); chk(f'the {l} flier picture is kept', r.ok and r.json()['url'], r.text)
chk('a leader cannot send flier pictures', ld.post(B+f'/meetings/{mid}/flier/render/en',json={'image':jpg()}).status_code==403)
r=hq.post(B+f'/meetings/{mid}/send-invites',json={}); chk('then the invitations go', r.ok and r.json()['emails']>0, r.text); time.sleep(2)
m1=mailhtml('amara.leader@example.org','Meeting: Forum one')
chk('the email says WODDI has called the meeting', '<b>WODDI</b> has called a meeting.' in m1, m1[:300])
chk('her email carries the French flier picture', f'flier-r{mid}-fr' in m1, re.findall(r'flier-[^"?]+',m1))
go=re.search(r'(/api/meetings/%d/go\?u=%d&amp;s=[0-9a-f]+)'%(mid,lid),m1)
chk('the Join button goes through WDOS', go is not None and 'Join the meeting' in m1)
chk('sending again sends nothing', hq.post(B+f'/meetings/{mid}/send-invites',json={}).json()['emails']==0)
g=requests.get(B.replace('/api','')+go.group(1).replace('&amp;','&'),allow_redirects=False)
chk('joining from the email is recorded and opens the call', g.status_code==302 and g.headers['Location']=='https://meet.google.com/abc-defg-hij' and sql(f"select count(*) from meeting_joins where meeting_id={mid} and user_id={lid}")=='1', (g.status_code,g.headers.get('Location')))
g=requests.get(B+f'/meetings/{mid}/go?u={lid}&s={"0"*32}',allow_redirects=False)
chk('a wrong link records nothing and opens WDOS', g.status_code==302 and '/#/meetings/' in g.headers['Location'])
# 3. if the host closes her browser, the server sends after 4 minutes
r=hq.post(B+'/meetings',json={**base,'title':'Forum two','starts_at':tt(days=5),'ends_at':tt(days=5,hours=1),'flier_headline':'Forum two','flier_text':'Bring your report.'}); m2=r.json()['id']; time.sleep(4)
sql(f"update app_settings set v='2020-01-01 00:00:00' where k='meet_inv_wait_{m2}'"); o=wa()
chk('the server sends held invitations after 4 minutes', f'meeting_invites_{m2}' in o and mailhtml('amara.leader@example.org','Meeting: Forum two')!='', o[:300])
# 4. phone notices for meetings are urgent
sql(f"insert into push_subscriptions (user_id,endpoint_hash,endpoint,p256dh,auth,created_at) values ({lid},'{'a'*64}','https://push.example.org/x','k','a',now())")
r=hq.post(B+'/meetings',json={**base,'title':'Forum three','starts_at':tt(days=6),'ends_at':tt(days=6,hours=1)}); m3=r.json()['id']
pl=sql(f"select payload from push_outbox po join push_subscriptions ps on ps.id=po.subscription_id where ps.user_id={lid} and payload like '%Forum three%' limit 1")
chk('a meeting push is urgent: it stays on screen and buzzes', '"urgent":true' in pl and '"kind":"meeting"' in pl, pl[:200])
chk('the phone shows it until she acts', 'requireInteraction: urgent' in open(ROOT+'/sw.js').read())
# 5. started: those who have not joined are called again, once
r=hq.post(B+'/meetings',json={**base,'title':'Live now','starts_at':tt(minutes=10),'ends_at':tt(minutes=70)}); m4=r.json()['id']
sql(f"update meetings set starts_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 6 MINUTE) where id={m4}")
other=int(sql(f"select s.user_id from seats s where s.network='WGMN' and s.ends_at is null and s.user_id<>{lid} limit 1"))
sql(f"insert into meeting_joins (meeting_id,user_id,joined_at) values ({m4},{lid},UTC_TIMESTAMP())")
o=wa(); chk('once it has started, those who have not joined are called', f'meeting_late_{m4}' in o and sql(f"select count(*) from notifications where user_id={other} and title='Happening now: Live now'")=='1', o[:200])
chk('she, who joined, is not called', sql(f"select count(*) from notifications where user_id={lid} and title='Happening now: Live now'")=='0')
wa(); chk('the call goes once', sql(f"select count(*) from notifications where user_id={other} and title='Happening now: Live now'")=='1')
# 6. slides, the close reminder, minutes with attendance and the slides
pdf='data:application/pdf;base64,'+base64.b64encode(b'%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n').decode()
f=hq.post(B+'/files',json={'name':'forum-slides.pdf','data':pdf}).json()['file']
r=hq.post(B+f'/meetings/{m4}/files',json={'file_id':f['id']}); chk('HQ adds the slides to the meeting', r.ok and r.json()['files'][0]['name']=='forum-slides.pdf', r.text)
chk('an invited leader can open the slides', ld.get(B+f"/files/{f['id']}").status_code==200)
wn=int(sql("select s.user_id from seats s where s.network='WNNN' and s.ends_at is null limit 1")); sql(f"update users set email='wnnn.lead@example.org', password_hash=(select password_hash from (select password_hash from users where email='amara.leader@example.org') x), must_change_password=0 where id={wn}")
ws=S('wnnn.lead@example.org','leader-pass-1'); chk('someone not invited cannot', ws.get(B+f"/files/{f['id']}").status_code in (403,404))
chk('the meeting page lists the slides', hq.get(B+f'/meetings/{m4}').json()['files'][0]['id']==f['id'])
sql(f"update meetings set starts_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 HOUR), ends_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR) where id={m4}")
hqid=int(sql("select id from users where email='hq@test.local'")); o=wa()
chk('an hour after it ends, the host is asked for the minutes', sql(f"select count(*) from notifications where user_id={hqid} and title='Write the minutes: Live now'")=='1', o[:200])
r=hq.post(B+f'/meetings/{m4}/close',json={'minutes':'We agreed the plan for the new year.'}); chk('HQ closes it with minutes', r.ok, r.text); time.sleep(2)
mm=mailhtml('amara.leader@example.org','Minutes are ready: Live now')
chk('the minutes email carries the minutes, attendance and the slides', 'We agreed the plan' in mm and 'Attendance' in mm and 'joined through WDOS' in mm and 'forum-slides.pdf' in mm, mm[:400])
rp=hq.get(B+f'/meetings/{m4}/replies').json(); chk('who joined is counted', rp['joined']==1 and any(p['id']==lid and p['joined_at'] for p in rp['people']))
src=open(ROOT+'/js/flier.js').read()
chk('the flier draws the network logo on the map of Africa', 'drawAfrica(' in src and 'wgmn-disc.png' in src and 'wnnn-disc.png' in src)
chk('an uploaded picture is placed inside the translated flier', 'photo' in src and 'renderEmailFliers' in src)
print('FAILURES',bad)
