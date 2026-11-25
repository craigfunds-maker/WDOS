# v109.59: the words inside an uploaded flier picture are read (in the browser), translated per language here, corrected by
# HQ when needed, and the painted pictures are what each reader sees and gets by email (Azeez, 25 Nov 2026).
import requests, subprocess, json, datetime, os, time, base64, io, re
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[4100]'))
ROOT=os.environ.get('WDOS_ROOT','/home/claude/wdos-php')
U=datetime.UTC; tt=lambda **k:(datetime.datetime.now(U)+datetime.timedelta(**k)).strftime('%Y-%m-%dT%H:%M:%SZ')
from PIL import Image
def jpg(c=(212,0,106),w=900,h=1125):
    b=io.BytesIO(); Image.new('RGB',(w,h),c).save(b,'JPEG'); return 'data:image/jpeg;base64,'+base64.b64encode(b.getvalue()).decode()
ld=S('leader@test.local','leader-pass-1'); lid=ld.get(B+'/me').json()['user']['id']
sql(f"update users set lang='fr', email='amara.leader@example.org' where id={lid}")
base={'mode':'virtual','location':'https://meet.google.com/abc-defg-hij','audiences':['leaders:WGMN']}
r=hq.post(B+'/meetings',json={**base,'title':'Leaders forum','starts_at':tt(days=4),'ends_at':tt(days=4,hours=1),'flier_image':jpg(),'flier_headline':'Leaders forum'}); mid=r.json()['id']; time.sleep(3)
words={'w':900,'h':1125,'groups':[{'text':'LEADERS FORUM','lines':[[150,82,750,131,13]]},{'text':'Bring your monthly report','lines':[[245,741,658,772,25]]},{'text':'12345','lines':[[1,1,9,9,5]]}]}
r=hq.post(B+f'/meetings/{mid}/flier/words',json={'words':words}); chk('the host sends the words read on the picture', r.ok and r.json()['groups']==2, r.text)
chk('a leader cannot', ld.post(B+f'/meetings/{mid}/flier/words',json={'words':words}).status_code==403)
time.sleep(4); d=hq.get(B+f'/meetings/{mid}/flier/langs').json()
chk('the words are kept with their places', d['words']['groups'][0]['lines'][0][:4]==[150,82,750,131] and len(d['words']['groups'])==2)
chk('each language gets the words translated, in order', d['words_by_lang'].get('fr')==['[FR] LEADERS FORUM','[FR] Bring your monthly report'] and len(d['words_by_lang'])==7, d['words_by_lang'])
chk('the headline is still translated too', [l for l in d['langs'] if l['lang']=='fr'][0]['headline'].startswith('[French]'))
r=hq.post(B+f'/meetings/{mid}/flier/words/ha',json={'texts':['TARON SHUGABANNI','Ku kawo rahotonku']}); chk('HQ corrects the Hausa words', r.ok, r.text)
chk('the wrong number of texts is refused', hq.post(B+f'/meetings/{mid}/flier/words/ha',json={'texts':['one']}).status_code==422)
r=hq.post(B+f'/meetings/{mid}/flier/words-translate',json={'redo':True}); chk('translating again keeps what HQ wrote', r.ok and json.loads(sql(f"select words_json from meeting_flier_texts where meeting_id={mid} and lang='ha'"))[0]=='TARON SHUGABANNI', r.text)
hq.post(B+f'/meetings/{mid}/flier/langs/ha',json={'headline':'Taro','text':''})
chk('correcting the Hausa headline keeps the Hausa picture words', json.loads(sql(f"select words_json from meeting_flier_texts where meeting_id={mid} and lang='ha'"))[0]=='TARON SHUGABANNI')
for l in ('en','fr'): hq.post(B+f'/meetings/{mid}/flier/render/{l}',json={'image':jpg((124,181,24) if l=='fr' else (212,0,106))})
f=ld.get(B+f'/meetings/countdown?lang=fr').json()['items'][0]['flier']
chk('the French reader sees the French picture', f'flier-r{mid}-fr' in f['image'] and f['original'] and f['original']!=f['image'], f)
f=ld.get(B+f'/meetings/{mid}?lang=sw').json()['flier']; chk('a language without its picture shows the original', f['image']==f['original'])
r=hq.post(B+f'/meetings/{mid}/send-invites',json={}); time.sleep(1)
html=''
for l in open('/tmp/aimock.log',encoding='utf-8',errors='replace'):
    if 'smtp' in l and 'amara.leader@example.org' in l and 'Leaders forum' in l: html=json.loads(l)['body'].get('htmlContent','')
html=html or sql(f"select html from mail_outbox where to_email='amara.leader@example.org' and subject like 'Meeting: Leaders forum%' limit 1")
chk('her email carries the French picture', f'flier-r{mid}-fr' in html, re.findall(r'flier-[^"?]+',html))
r=hq.post(B+f'/meetings/{mid}/flier',json={'image':jpg((10,10,10))}); chk('a new picture clears the old words and pictures', r.ok and sql(f"select flier_words from meetings where id={mid}") in ('','NULL') and sql(f"select count(*) from meeting_flier_texts where meeting_id={mid} and words_json is not null")=='0' and sql(f"select count(*) from message_photos where image_key like 'flier/r{mid}-%'")=='0')
src=open(ROOT+'/js/flier.js').read()
chk('the word reader runs in the browser from WDOS itself', "'/assets/vendor/ocr/tesseract.min.js'" in src and os.path.exists(ROOT+'/assets/vendor/ocr/eng.traineddata.gz'))
chk('the reader uses the page layout mode and a resolution hint', "tessedit_pageseg_mode: '3'" in src and "user_defined_dpi: '300'" in src)
print('FAILURES',bad)
