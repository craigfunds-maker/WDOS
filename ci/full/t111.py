# v109.3: old WDOS records page. Super admin only; counts, search by person and words.
import requests, subprocess, sys
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,x)
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
# a stand-in for the old Supabase (synthetic rows only), then the command-line copy, run twice to prove it is safe to repeat
subprocess.Popen(['php','-S','127.0.0.1:8799','/home/claude/sbmock.php'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL); import time; time.sleep(1)
cmd='. /tmp/env.sh; cd /home/claude/wdos-php; SUPABASE_URL=http://127.0.0.1:8799 SUPABASE_SERVICE_KEY=stand-in php lib/legacy_cli.php'
o1=subprocess.run(['bash','-c',cmd],capture_output=True,text=True).stdout; o2=subprocess.run(['bash','-c',cmd],capture_output=True,text=True).stdout
chk('copy finishes', 'LEGACY COPY DONE' in o1, o1)
chk('secret stores are never copied', 'skipped (secret store): email_secrets' in o1)
chk('running again copies nothing twice', 'bucket cvs: 0 new files kept' in o2 and 'rows kept in total: 2' in o2, o2)
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
d=hq.get(B+'/hq/legacy').json()
chk('page opens for the super admin', d.get('ready') is True, d)
chk('each table shows old and kept counts', any(t['source_table']=='tasks' and int(t['remote_rows'])==2 and int(t['kept_rows'])==2 and t['note']=='complete' for t in d['tables']), d.get('tables'))
chk('stored files counted', d['files']>=1)
chk('search by person', hq.get(B+'/hq/legacy',params={'email':'amina.test@wdos-mail.org','table':'tasks'}).json()['total']==1)
chk('search by words', hq.get(B+'/hq/legacy',params={'q':'Synthetic task'}).json()['total']==1)
f=d['file_list'][0]
r=hq.get(B+'/hq/legacy/file',params={'bucket':f['bucket'],'path':f['path']})
chk('HQ opens an old stored file', r.ok and r.content.startswith(b'%PDF'), r.status_code)
chk('a leader cannot open an old stored file', ld.get(B+'/hq/legacy/file',params={'bucket':f['bucket'],'path':f['path']}).status_code==403)
chk('no path tricks', hq.get(B+'/hq/legacy/file',params={'bucket':'cvs','path':'../../etc/passwd'}).status_code==404)
chk('a leader cannot open old records', ld.get(B+'/hq/legacy').status_code==403)
chk('signed-out cannot open old records', requests.get(B+'/hq/legacy',headers={'X-WDOS':'1'}).status_code in (401,403))
chk('command-line copy refuses the web', requests.get('http://localhost:8080/lib/legacy_cli.php').status_code in (403,404))

# Google sign-up, either network: the details must still be filled in before anything else opens
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
h=subprocess.run(['php','-r','echo password_hash("google-pass-1", PASSWORD_DEFAULT);'],capture_output=True,text=True).stdout
for net,em in (('WGMN','g.wgmn@wdos-mail.org'),('WNNN','g.wnnn@wdos-mail.org')):
  sql(f"INSERT INTO users (email,password_hash,first_name,last_name,network,role,status,signup_method,created_at) VALUES ('{em}','{h}','Gift','Test','{net}','member','pending_profile','google',NOW())")
  g=login(em,'google-pass-1')
  chk(f'{net} Google sign-up: tasks closed until the profile is done', g.get(B+'/tasks').json().get('error')=='profile_incomplete')
  chk(f'{net} Google sign-up: messages closed too', g.get(B+'/messages').status_code==403)
  r=g.post(B+'/auth/complete-profile',json={'network':net,'first_name':'Gift'})
  chk(f'{net} Google sign-up: half a form is refused', r.status_code==422, r.text)
  chk(f'{net} Google sign-up: still waiting for the details', sql(f"select status from users where email='{em}'")=='pending_profile')

# v109.4: the guide runs on her own pages: words and voice for every step, and no screenshots of anyone's account
import json as _j, os as _o, glob as _g
R='/home/claude/wdos-php/assets/tour/'
for kind in ('leader','envoy','member'):
  for L in ('ar','en','fr','pt','sw'):
    m=_j.load(open(f'{R}{kind}/{L}/tour.json'))
    chk(f'guide {kind} {L}: every step has words and a voice', m['scenes'] and all(sc['text'] and _o.path.getsize(f'{R}voice/{L}/{kind}-{sc["id"]}.mp3')>2000 for sc in m['scenes']))
chk('guide: welcome and greeting voiced in every language', all(_o.path.exists(f'{R}voice/{L}/{k}.mp3') for L in ('ar','en','fr','pt','sw') for k in ('hello','glad')))
chk('guide: no screenshots of a sample account are shipped', not _g.glob(R+'*/*/*.webp'))
js=open('/home/claude/wdos-php/js/pages/guide.js').read()
chk('guide: the browser voice is no longer used', 'speechSynthesis' not in js)
chk('guide: every scene of every kind has a plan on her own pages', all(f"id: '{sc['id']}'" in js for kind in ('leader','envoy','member') for sc in _j.load(open(f'{R}{kind}/en/tour.json'))['scenes']))
print(f'checks {ok+bad}'); print(f'FAILURES {bad}'); sys.exit(1 if bad else 0)
