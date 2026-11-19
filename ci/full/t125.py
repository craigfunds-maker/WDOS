# v109.33: one personal development course a month for volunteer leaders; the profile photo prompt's upload path. Synthetic data.
import requests, subprocess, os, base64, io
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); return s if r.ok else r
def cron(now):
  env=dict(os.environ, ADMIN_KEY='t125', WDOS_TEST_NOW=now)
  return subprocess.run(['bash','-c','. /tmp/env.sh; php /home/claude/wdos-php/lib/cron_cli.php'],env=env,capture_output=True,text=True).stdout
L=login('leader@test.local','leader-pass-1'); lid=int(L.get(B+'/me').json()['user']['id'])
sql(f"delete from course_starts where user_id={lid}; delete from course_progress where user_id={lid}; delete from course_attempts where user_id={lid}")
c=L.get(B+'/courses').json()['courses']
chk('first visit: only course 1 open', [x['no'] for x in c if x['open']]==[1], [(x['no'],x['open']) for x in c])
chk('each later course has its date', all(x['opens_at'] for x in c[1:]))
st=sql(f"select started_at from course_starts where user_id={lid}"); chk('her start is kept', bool(st), st)
chk('course 2 a month after', c[1]['opens_at'][:7]!=c[0]['opens_at'][:7] if c[0]['opens_at'] else True)
r=L.post(B+'/courses/2/modules/1',json={}); chk('a closed course cannot be started (server)', r.status_code==423 and 'opens on' in r.text, r.text)
r=L.post(B+'/courses/3/quiz',json={'answers':['A']*10}); chk('closed quiz refused', r.status_code==423, r.status_code)
chk('course 1 can be started', L.post(B+'/courses/1/modules/1',json={}).ok)
# 31 January start: course 2 on 28 Feb, never March
sql(f"update course_starts set started_at='2027-01-31 10:00:00' where user_id={lid}")
c=L.get(B+'/courses').json()['courses']; chk('month end is clamped', c[1]['opens_at'].startswith('2027-02-28') and c[2]['opens_at'].startswith('2027-03-31') and c[12-1]['opens_at'].startswith('2027-12-31'), [x['opens_at'] for x in c[:3]])
chk('already started course stays open even if its month is later', c[0]['open'] is True)
# three months in: courses 1 to 4 open
sql(f"update course_starts set started_at=date_sub(utc_timestamp(), interval 3 month) where user_id={lid}")
c=L.get(B+'/courses').json()['courses']; chk('three months in: four courses', [x['no'] for x in c if x['open']]==[1,2,3,4], [x['no'] for x in c if x['open']])
# work begun before this release keeps a later course open
sql(f"insert ignore into course_progress (user_id,course_no,module_no,completed_at) values ({lid},9,1,utc_timestamp())")
c=L.get(B+'/courses').json()['courses']; chk('a course she had begun stays open', c[8]['open'] is True and c[7]['open'] is False)
# HQ sees all
hq=login('hq@test.local','hq-password-1'); c=hq.get(B+'/courses').json()['courses']; chk('HQ sees every course', all(x['open'] for x in c))
hid=int(hq.get(B+'/me').json()['user']['id']); chk('no schedule row for HQ', sql(f"select count(*) from course_starts where user_id={hid}")=='0')
# the monthly notice
sql(f"delete from app_settings where k like 'course_unlocks_%'; delete from notifications where user_id={lid} and title like 'New course open%'")
sql(f"update course_starts set started_at='2026-10-20 09:00:00', told_upto=1 where user_id={lid}")
cron('2026-11-21 07:30'); chk('not before 08:00', sql(f"select count(*) from notifications where user_id={lid} and title like 'New course open%'")=='0')
cron('2026-11-21 08:30'); n=sql(f"select concat(title,' | ',url) from notifications where user_id={lid} and title like 'New course open%'")
chk('she is told course 2 opened', 'Confidence, Self-Worth and Resilience' in n and '#/courses/2' in n, n)
sql("delete from app_settings where k like 'course_unlocks_%'")
cron('2026-11-22 08:30'); chk('told once', sql(f"select count(*) from notifications where user_id={lid} and title like 'New course open%'")=='1')
sql("delete from app_settings where k like 'course_unlocks_%'")
cron('2026-12-21 08:30'); chk('next month: course 3', sql(f"select count(*) from notifications where user_id={lid} and title like 'New course open: Discipline%'")=='1')
chk('told_upto moves', sql(f"select told_upto from course_starts where user_id={lid}")=='3')
# notification in French: written in her language when it is sent
sql(f"update users set lang='fr' where id={lid}"); sql("delete from app_settings where k like 'course_unlocks_%'")
cron('2027-01-21 08:30'); n=sql(f"select concat(title,' | ',body) from notifications where user_id={lid} order by id desc limit 1")
chk('notification in her language', 'Nouveau cours ouvert' in n and 'Le cours 4 sur 12' in n, n)
sql(f"update users set lang='en' where id={lid}")
# the photo prompt: what it needs from /me, and the upload it uses
sql(f"update users set has_photo=0 where id={lid}")
me=L.get(B+'/me').json()['user']; chk('/me says no photo yet', me['has_photo'] is False)
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')
r=L.post(B+'/me/photo',json={'data':'data:image/png;base64,'+base64.b64encode(png).decode()}); chk('photo saves', r.ok, r.text)
chk('it is her profile picture now', L.get(B+'/me').json()['user']['has_photo'] is True)
r=L.post(B+'/me/photo',json={'data':'data:image/png;base64,'+base64.b64encode(b'not an image').decode()}); chk('a non-image is refused', r.status_code==422)
print(f'checks {ok+bad} FAILURES {bad}')
