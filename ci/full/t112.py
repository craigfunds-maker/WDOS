# v109.6: Volunteer Leadership Personal Development Courses. Lessons in order, quiz gated, answers kept on the server,
# certificates issued once, public QR check shows name/course/date only, programme certificate after all twelve.
import requests, json, re, os
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
W='/home/claude/wdos-php/'
src=open(W+'lib/courses_fns.php').read()
KEY={int(a):b for a,b in re.findall(r"(\d+)\s*=>\s*'([ABCD]{10})'",src)}
chk('answer key for all twelve courses', sorted(KEY)==list(range(1,13)), KEY)
# content in all five languages, same shape, and the even-question right answer lines up with the key in every language
EN=json.load(open(W+'assets/courses/en/index.json'))
ENW={n:json.load(open(W+f'assets/courses/en/{n}.json')) for n in ('wgmn','wnnn')}
chk('the two network editions differ in examples and practice', all(a['scenario']!=b['scenario'] and a['exercise']!=b['exercise'] for c1,c2 in zip(ENW['wgmn']['courses'],ENW['wnnn']['courses']) for a,b in zip(c1['modules'],c2['modules'])))
chk('WNNN edition speaks to ages 18 to 29', '18' in ENW['wnnn']['t']['why'] and 'Nurture NextGen' in ENW['wnnn']['t']['why'] and 'Good Mother' in ENW['wgmn']['t']['why'])
chk('answer key not shipped to the phone', not any('key' in m for c in EN['courses'] for m in c['modules']))
for L,NET in [(l,n) for l in ('ar','en','fr','pt','sw') for n in ('wgmn','wnnn')]:
  D=json.load(open(W+f'assets/courses/{L}/{NET}.json'))
  chk(f'{L} {NET}: twelve courses', len(D['courses'])==12)
  shape=all(len(c['modules'])==5 and len(c['quiz'])==10 and all(len(q['o'])==4 and len(set(q['o']))==4 for q in c['quiz']) for c in D['courses'])
  chk(f'{L} {NET}: five lessons and ten four-option questions each', shape)
  if L!='en':
    good=True
    for c,e in zip(D['courses'],EN['courses']):
      k=KEY[c['no']]
      for i in range(1,10,2):
        ai='ABCD'.index(k[i])
        if not e['quiz'][i]['o'][ai].startswith('Use the practice of'): good=False
        if c['quiz'][i]['o'][ai] in e['quiz'][i]['o']: good=False   # really translated
      if c['title']==e['title']: good=False
    chk(f'{L} {NET}: translated, and the right answers sit where the key says', good)
    chk(f'{L} {NET}: lesson topic phrase for the templates', all(m.get('topic') for c in D['courses'] for m in c['modules']))
  if NET=='wnnn':
    import sys as _s; _s.path.insert(0,'/home/claude/wdos/tools/courses') if os.path.exists('/home/claude/wdos/tools/courses') else _s.path.insert(0,os.environ.get('GITHUB_WORKSPACE','')+'/tools/courses')
    from voice_plan import plan
    need=plan(L,json.load(open(W+f'assets/courses/{L}/wgmn.json')),D)
    vd=W+f'assets/courses/voice/{L}/'; cues=json.load(open(vd+'cues.json')) if os.path.exists(vd+'cues.json') else {}
    miss=[k for k in need if k not in cues or not os.path.exists(vd+k+'.mp3')]
    chk(f'{L}: the whole lesson is narrated, both networks ({len(need)-len(miss)}/{len(need)} clips)', not miss, miss[:4])
ld=login('leader@test.local','leader-pass-1'); hq=login('hq@test.local','hq-password-1')
d=ld.get(B+'/courses').json()
chk('course list', len(d.get('courses',[]))==12 and d['pass']==7, d)
chk('a signed-out visitor cannot open the courses', requests.get(B+'/courses',headers={'X-WDOS':'1'}).status_code in (401,403))
chk('quiz closed before the lessons', ld.post(B+'/courses/1/quiz',json={'answers':list(KEY[1])}).status_code in (403,409,422))
chk('lessons open in order', ld.post(B+'/courses/1/modules/3',json={}).status_code in (403,409,422))
for m in range(1,6): r=ld.post(B+'/courses/1/modules/%d'%m,json={}); chk(f'lesson {m} finished', r.ok, r.text)
chk('a lesson outside the course is refused', ld.post(B+'/courses/1/modules/6',json={}).status_code in (403,404,409,422))
chk('half the answers refused', ld.post(B+'/courses/1/quiz',json={'answers':['A']*9}).status_code==422)
chk('odd answers refused', ld.post(B+'/courses/1/quiz',json={'answers':['E']*10}).status_code==422)
wrong=['A' if x!='A' else 'B' for x in KEY[1]]
r=ld.post(B+'/courses/1/quiz',json={'answers':wrong}).json()
chk('all wrong: not passed, every question listed to review, no certificate', r.get('passed') is False and r['score']==0 and len(r['wrong'])==10 and not r.get('certificate'), r)
six=list(KEY[1]); 
for i in range(4): six[i]='A' if six[i]!='A' else 'B'
r=ld.post(B+'/courses/1/quiz',json={'answers':six}).json()
chk('six of ten is below the pass mark', r.get('passed') is False and r['score']==6, r)
seven=list(KEY[1]); 
for i in range(3): seven[i]='A' if seven[i]!='A' else 'B'
r=ld.post(B+'/courses/1/quiz',json={'answers':seven}).json()
chk('seven of ten passes and earns a certificate', r.get('passed') is True and r['score']==7 and r.get('certificate'), r)
code=(r.get('certificate') or {}).get('code','')
chk('certificate number format', re.fullmatch(r'WD-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}',code), code)
r2=ld.post(B+'/courses/1/quiz',json={'answers':list(KEY[1])}).json()
chk('passing again keeps the same certificate', (r2.get('certificate') or {}).get('code')==code, r2)
p=requests.get(B+f'/public/certificates/{code}',headers={'X-WDOS':'1'}).json()
chk('public check finds it', p.get('found') is True and p['certificate']['valid'] is True and p['certificate']['course_no']==1, p)
chk('public check shows no score, email or phone', 'score' not in p['certificate'] and not any(k in json.dumps(p) for k in ('@','password','phone')), p)
chk('unknown number not found', requests.get(B+'/public/certificates/WD-AAAA-BBBB-CCCC',headers={'X-WDOS':'1'}).json().get('found') is False)
chk('owner sees her certificate', ld.get(B+f'/certificates/{code}').ok)
chk('HQ can see it', hq.get(B+f'/certificates/{code}').ok)
other=None
for e,pw in (('member@test.local','member-pass-1'),('deputy@test.local','deputy-pass-1'),('envoy@test.local','envoy-pass-1')):
  try: other=login(e,pw); break
  except AssertionError: pass
if other: chk('another person cannot open it privately', other.get(B+f'/certificates/{code}').status_code==404)
chk('a leader cannot open the HQ course figures', ld.get(B+'/hq/courses').status_code==403)
# all twelve: the programme certificate (v109.33: one course opens a month, so her start is set a year back first)
import subprocess
subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input="update course_starts set started_at=date_sub(utc_timestamp(), interval 12 month) where user_id=(select id from users where email='leader@test.local')",capture_output=True,text=True)
for c in range(2,13):
  for m in range(1,6): ld.post(B+f'/courses/{c}/modules/{m}',json={})
  r=ld.post(B+f'/courses/{c}/quiz',json={'answers':list(KEY[c])}).json()
chk('programme certificate after all twelve', bool(r.get('programme')), r)
d=ld.get(B+'/courses').json()
chk('list shows twelve passed', sum(1 for c in d['courses'] if c['passed'])==12)
h=hq.get(B+'/hq/courses').json()
chk('HQ figures count certificates', h['certificates']>=13, h)
# v109.8: nothing lives only on one phone: lesson answers, unsent quiz choices and the place she stopped follow her account
s2=login('leader@test.local','leader-pass-1')   # a second device: a new session
r=ld.post(B+'/courses/3/notes/1',json={'body':'My synthetic practice answer'})
chk('lesson answer saved to the account', r.ok, r.text)
chk('the answer opens on another device', s2.get(B+'/courses/3/notes/1').json().get('body')=='My synthetic practice answer')
chk('a lesson outside the course is refused for notes', ld.post(B+'/courses/3/notes/9',json={'body':'x'}).status_code==404)
chk('quiz choices kept before sending', ld.post(B+'/courses/3/draft',json={'answers':'AB--------'}).ok and s2.get(B+'/courses/3/draft').json().get('answers')=='AB--------')
chk('odd quiz choices refused', ld.post(B+'/courses/3/draft',json={'answers':"x' OR 1"}).status_code==422)
chk('the place she stopped', ld.post(B+'/courses/place',json={'course':4,'part':'2','seconds':95}).ok and s2.get(B+'/courses').json().get('place',{}).get('part')=='2' and s2.get(B+'/courses').json()['place']['seconds']==95)
chk('a made-up place is refused', ld.post(B+'/courses/place',json={'course':4,'part':'../x'}).status_code==422)
other2=requests.Session(); other2.headers.update({'X-WDOS':'1'})
chk('signed-out cannot read answers', other2.get(B+'/courses/3/notes/1').status_code==401)
for m in range(1,6): ld.post(B+f'/courses/3/modules/{m}',json={})
ld.post(B+'/courses/3/quiz',json={'answers':list(KEY[3])})
chk('sending the quiz clears the kept choices', ld.get(B+'/courses/3/draft').json().get('answers')=='')
print(f'checks {ok} FAILURES {bad}')
