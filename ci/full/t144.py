# v109.56: meeting fliers (uploaded or made by WDOS), translated into each leader's language, the countdown on dashboards
# and in emails, replies from the email, who will attend, and deleting (Azeez, 25 Nov 2026). Run with the stand-in AI (srv_ai.sh).
import requests, subprocess, json, datetime, os, time, base64, io, re
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[3700]'))
ROOT=os.environ.get('WDOS_ROOT','/home/claude/wdos-php')
U=datetime.UTC; tt=lambda **k:(datetime.datetime.now(U)+datetime.timedelta(**k)).strftime('%Y-%m-%dT%H:%M:%SZ')
from PIL import Image
def png(c=(212,0,106)):
    b=io.BytesIO(); Image.new('RGB',(600,750),c).save(b,'PNG'); return 'data:image/png;base64,'+base64.b64encode(b.getvalue()).decode()
def mailhtml(addr, subj):
    out=''
    for l in open('/tmp/aimock.log',encoding='utf-8',errors='replace'):
        if 'smtp' not in l or addr not in l: continue
        try: b=json.loads(l)['body']
        except Exception: continue
        if str(b.get('subject','')).startswith(subj): out=b.get('htmlContent','')
    return out or sql(f"select html from mail_outbox where to_email='{addr}' and subject like '{subj}%' order by id desc limit 1")
ld=S('leader@test.local','leader-pass-1'); lid=ld.get(B+'/me').json()['user']['id']
sql(f"update users set lang='fr', email='amara.leader@example.org' where id={lid}")
base={'mode':'virtual','location':'https://meet.google.com/abc-defg-hij','audiences':['leaders:WGMN']}
# 1. a bad picture stops everything before the meeting is made
n0=sql("select count(*) from meetings")
r=hq.post(B+'/meetings',json={**base,'title':'Bad flier','starts_at':tt(days=5),'ends_at':tt(days=5,hours=1),'flier_image':'data:image/png;base64,AAAA'})
chk('a bad flier picture is refused and nothing is made', r.status_code==422 and sql("select count(*) from meetings")==n0, r.text)
# 2. a meeting with a flier picture and words
r=hq.post(B+'/meetings',json={**base,'title':'Leaders forum','starts_at':tt(days=5),'ends_at':tt(days=5,hours=1),'flier_image':png(),'flier_headline':'Leaders Forum 2026','flier_text':'Come and plan the new year with us.'})
mid=r.json().get('id'); chk('HQ schedules a meeting with a flier', r.ok and r.json()['emails']>0, r.text)
key=sql(f"select flier_image from meetings where id={mid}")
chk('the picture is kept', key.startswith(f'flier/m{mid}-') and sql(f"select count(*) from message_photos where image_key='{key}'")=='1')
chk('the picture is a plain file for emails', any(f.startswith('flier-m'+str(mid)) for f in os.listdir(ROOT+'/assets/media')))
time.sleep(4)
chk('the words are translated into seven languages', sql(f"select count(*) from meeting_flier_texts where meeting_id={mid}")=='7', sql(f"select lang from meeting_flier_texts where meeting_id={mid}"))
chk('in French for the French reader', sql(f"select headline from meeting_flier_texts where meeting_id={mid} and lang='fr'")=='[French] Leaders Forum 2026')
mail=mailhtml('amara.leader@example.org','Meeting: Leaders forum')
chk('her email carries the flier in French', '[French] Leaders Forum 2026' in mail and 'flier-m' in mail, mail[:300])
chk('her email counts down in French', 'J-5' in mail or 'J-4' in mail, re.findall(r'J-\d|days to go',mail))
chk('her email has the three reply buttons', mail.count(f'/api/meetings/{mid}/reply?u={lid}&amp;a=')==3)
# 3. the dashboard countdown
d=ld.get(B+'/meetings/countdown?lang=fr').json(); it=[x for x in d['items'] if x['id']==mid]
chk('her dashboard shows the meeting with the flier in French', it and it[0]['flier']['headline']=='[French] Leaders Forum 2026' and it[0]['flier']['image'], d)
d=ld.get(B+'/meetings/countdown?lang=sw').json(); it=[x for x in d['items'] if x['id']==mid]
chk('switching the app to Swahili shows Swahili', it and it[0]['flier']['headline'].startswith('[Swahili]'))
d=ld.get(B+'/meetings/countdown?lang=en').json(); it=[x for x in d['items'] if x['id']==mid]
chk('in English she reads the words as written', it and it[0]['flier']['headline']=='Leaders Forum 2026')
# 4. replies from the email
url=re.search(r'/api/meetings/\d+/reply\?u=\d+&amp;a=yes&amp;s=([0-9a-f]+)',mail); sig=url.group(1)
g=requests.get(B+f'/meetings/{mid}/reply?u={lid}&a=yes&s={sig}')
chk('the email link opens a page in her language and saves nothing', g.ok and 'Serez-vous présente' in g.text and sql(f"select count(*) from meeting_rsvps where meeting_id={mid}")=='0', g.text[:200])
badr=requests.post(B+f'/meetings/{mid}/reply',json={'u':lid,'s':'0'*32,'answer':'yes'},headers={'X-WDOS':'1'})
chk('a wrong signature is refused', badr.status_code==403)
other=int(sql(f"select id from users where id<>{lid} and role='member' limit 1"))
chk('her signature does not work for someone else', requests.post(B+f'/meetings/{mid}/reply',json={'u':other,'s':sig,'answer':'yes'},headers={'X-WDOS':'1'}).status_code==403)
ok=requests.post(B+f'/meetings/{mid}/reply',json={'u':lid,'s':sig,'answer':'yes'},headers={'X-WDOS':'1'})
chk('pressing Yes on the page saves her reply', ok.ok and sql(f"select answer from meeting_rsvps where meeting_id={mid} and user_id={lid}")=='yes', ok.text)
# 5. who will attend
rp=hq.get(B+f'/meetings/{mid}/replies').json()
chk('HQ sees who will attend', rp['counts']['yes']==1 and any(p['id']==lid and p['answer']=='yes' for p in rp['people']) and rp['no_reply']==rp['invited']-1, {k:rp[k] for k in ('counts','invited','no_reply')})
chk('the list shows how many will attend', [m for m in hq.get(B+'/meetings').json()['items'] if m['id']==mid][0]['yes']==1)
r=hq.post(B+f'/meetings/{mid}/nudge',json={}); chk('HQ asks those who have not replied', r.ok and r.json()['asked']==rp['no_reply'], r.text)
chk('she, who replied, is not asked again', sql(f"select count(*) from notifications where user_id={lid} and title like 'Will you attend?%'")=='0')
chk('a leader who is not the host cannot see the replies', ld.get(B+f'/meetings/{mid}/replies').status_code==403)
chk('a leader who is not the host cannot change the flier', ld.post(B+f'/meetings/{mid}/flier',json={'headline':'X'}).status_code==403)
# 6. the host corrects a language, changes the words, removes the picture
r=hq.post(B+f'/meetings/{mid}/flier/langs/ha',json={'headline':'Taron Shugabanni','text':'Ku zo.'}); chk('HQ corrects the Hausa by hand', r.ok and [l for l in r.json()['langs'] if l['lang']=='ha'][0]['source']=='hq')
r=hq.post(B+f'/meetings/{mid}/flier/translate',json={'redo':True}); chk('translating again keeps what HQ wrote', r.ok and sql(f"select headline from meeting_flier_texts where meeting_id={mid} and lang='ha'")=='Taron Shugabanni', r.text)
r=hq.post(B+f'/meetings/{mid}/flier',json={'headline':'Leaders Forum moved online','text':'New details inside.'}); time.sleep(4)
chk('new words are translated afresh', r.ok and sql(f"select headline from meeting_flier_texts where meeting_id={mid} and lang='fr'")=='[French] Leaders Forum moved online' and sql(f"select count(*) from meeting_flier_texts where meeting_id={mid}")=='7')
r=hq.post(B+f'/meetings/{mid}/flier',json={'remove_image':True}); chk('the picture can be removed', r.ok and sql(f"select flier_image from meetings where id={mid}")=='' and sql(f"select count(*) from message_photos where image_key='{key}'")=='0')
d=hq.get(B+f'/meetings/{mid}').json(); chk('without a picture WDOS makes the flier from the words', d['flier'] and d['flier']['image']=='' and d['flier']['headline']=='Leaders Forum moved online')
# 7. a meeting with no flier has none, and emails as before
r=hq.post(B+'/meetings',json={**base,'title':'Plain meeting','starts_at':tt(days=2),'ends_at':tt(days=2,hours=1)}); pm=r.json()['id']
chk('a meeting without a flier works as before', r.ok and hq.get(B+f'/meetings/{pm}').json()['flier'] is None)
pmail=mailhtml('amara.leader@example.org','Meeting: Plain meeting')
chk('its email still has the countdown and reply buttons', 'J-2' in pmail or 'J-1' in pmail, re.findall(r'J-\d',pmail))
# 8. the countdown reminders: a week and three days before
r=hq.post(B+'/meetings',json={**base,'title':'Week away','starts_at':tt(days=6,hours=12),'ends_at':tt(days=6,hours=13),'flier_headline':'Week away forum','flier_text':'Plan ahead.'}); wk=r.json()['id']; time.sleep(4)
r=hq.post(B+'/meetings',json={**base,'title':'Three days away','starts_at':tt(days=2,hours=12),'ends_at':tt(days=2,hours=13)}); td=r.json()['id']
out=subprocess.run(['bash','-c','. /tmp/env.sh; BREVO_API_KEY=ci-not-real MAIL_FROM=wdos-ci@wdos-mail.org BREVO_API_URL=http://127.0.0.1:8099/v3/smtp/email php /home/claude/wa.php'],capture_output=True,text=True).stdout
chk('a week before, everyone is reminded', f'meeting_{wk}' in out and int(sql(f"select count(*) from notifications where user_id={lid} and title='7 days to go: Week away'"))==1, out[:300])
chk('three days before, everyone is reminded', f'meeting_{td}' in out and int(sql(f"select count(*) from notifications where user_id={lid} and title='3 days to go: Three days away'"))==1)
wm=mailhtml('amara.leader@example.org','7 days to go: Week away')
chk('the week reminder email has the flier in her language', '[French] Week away forum' in wm, wm[:200])
out2=subprocess.run(['bash','-c','. /tmp/env.sh; BREVO_API_KEY=ci-not-real MAIL_FROM=wdos-ci@wdos-mail.org BREVO_API_URL=http://127.0.0.1:8099/v3/smtp/email php /home/claude/wa.php'],capture_output=True,text=True).stdout
chk('each reminder goes out once', f'meeting_{wk}' not in out2 and f'meeting_{td}' not in out2)
# 9. deleting
key2=sql(f"select flier_image from meetings where id={wk}")
r=hq.post(B+f'/meetings/{mid}/delete',json={'confirm':True}); chk('HQ deletes the meeting', r.ok and sql(f"select count(*) from meetings where id={mid}")=='0')
chk('its translations and replies go too', sql(f"select count(*) from meeting_flier_texts where meeting_id={mid}")=='0' and sql(f"select count(*) from meeting_rsvps where meeting_id={mid}")=='0')
chk('an old reply link then says the link is not valid', 'pas valide' in requests.get(B+f'/meetings/{mid}/reply?u={lid}&a=yes&s={sig}').text)
r=hq.post(B+'/meetings',json={**base,'title':'With picture','starts_at':tt(days=9),'ends_at':tt(days=9,hours=1),'flier_image':png((124,181,24))}); pk=r.json()['id']; k3=sql(f"select flier_image from meetings where id={pk}")
hq.post(B+f'/meetings/{pk}/delete',json={'confirm':True}); chk('deleting removes the flier picture', sql(f"select count(*) from message_photos where image_key='{k3}'")=='0')
# 10. with automatic translation switched off
hq.post(B+'/policy',json={'key':'ai_features','value':'off','note':'test'})
r=hq.post(B+f'/meetings/{wk}/flier/translate',json={'redo':True}); chk('with translation off, HQ is told to type the languages', r.status_code==409 and 'Type each language' in r.text, r.text)
chk('and the languages page says so', hq.get(B+f'/meetings/{wk}/flier/langs').json()['ai_on'] is False)
hq.post(B+'/policy',json={'key':'ai_features','value':'on','note':'test'})
src=open(ROOT+'/js/flier.js').read(); chk('dashboards show the countdown', all("countdownCard()" in open(f'{ROOT}/js/pages/{p}.js').read() for p in ('home','leader','hq')))
# 11. HQ's flier design (v109.57)
chk('no design at first', hq.get(B+'/meetings/flier-template').json()['url']=='')
chk('a leader cannot set the design', ld.post(B+'/hq/flier-template',json={'image':png()}).status_code in (401,403))
chk('a tiny picture is refused as a design', hq.post(B+'/hq/flier-template',json={'image':(lambda: (lambda b: 'data:image/png;base64,'+base64.b64encode(b).decode())((lambda: (lambda io_: (Image.new('RGB',(300,300),(1,2,3)).save(io_,'PNG'), io_.getvalue())[1])(io.BytesIO()))()))()}).status_code==422)
r=hq.post(B+'/hq/flier-template',json={'image':png()}); chk('HQ uploads the design', r.ok and r.json()['url'].startswith('/assets/media/flier-template'), r.text)
chk('every leader can load it', ld.get(B+'/meetings/flier-template').json()['url']==r.json()['url'])
r=hq.post(B+'/hq/flier-template',json={'remove':True}); chk('HQ goes back to the WDOS background', r.ok and r.json()['url']=='' and sql("select count(*) from message_photos where image_key='flier/template'")=='0')
chk('the flier draws a QR code', 'drawQr(' in open(ROOT+'/js/flier.js').read())
print('FAILURES',bad)
