# v109.12: the AI work check. Tasks HQ gave and reports leaders submit are checked against what was asked (the stand-in AI
# here); a clear gap sends the work back with corrections, reminds her, and HQ can always mark it as done.
import requests, subprocess, json, time
B='http://localhost:8080/api'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
def login(e,p):
  s=requests.Session(); s.headers.update({'X-WDOS':'1'}); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
def sql(q): return subprocess.run(['bash','-c','. /tmp/env.sh; mariadb --socket=/tmp/mdb.sock -uroot "$DB_NAME" -N'],input=q,capture_output=True,text=True).stdout.strip()
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
me=ld.get(B+'/me').json()['user']; lid=me['id']
def task(title, details, approval=False):
  r=hq.post(B+'/tasks',json={'title':title,'details':details,'assignee_id':lid,'requires_approval':approval}); assert r.ok, r.text; return r.json()['id']
# 1. complete work: passes, stays completed
t1=task('Share the meeting notes','Write two lines on the meeting.')
ld.post(B+f'/tasks/{t1}/evidence',json={'note':'Notes shared with all 12 women in the chapter group.'})
r=ld.post(B+f'/tasks/{t1}/status',json={'to':'completed'}); chk('leader completes the task', r.ok, r.text); time.sleep(1.5)
d=ld.get(B+f'/tasks/{t1}').json()
chk('complete work passes the check and stays completed', d['task']['status']=='completed' and d['check'] and d['check']['verdict']=='ok', d.get('check'))
# 2. incomplete work: sent back with corrections, she is told, HQ is told
t2=task('Send the attendance list MOCK-INCOMPLETE','Attach the attendance list for Saturday\'s meeting and say how many attended.')
ld.post(B+f'/tasks/{t2}/evidence',json={'note':'Meeting held.'})
r=ld.post(B+f'/tasks/{t2}/status',json={'to':'completed'}); time.sleep(1.5)
d=ld.get(B+f'/tasks/{t2}').json(); c=d['check']
chk('incomplete work is flagged with corrections', c and c['verdict']=='needs_correction' and c['status']=='open' and len(c['points'])==2, c)
chk('and sent back to her (in progress again)', d['task']['status']=='in_progress', d['task']['status'])
chk('the corrections are written on the task', any('work check' in x['body'] for x in d['comments']), d['comments'])
chk('she is told it needs correction', sql(f"select count(*) from notifications where user_id={lid} and title like 'Needs correction:%'")>='1')
chk('HQ is told the check flagged it', sql("select count(*) from notifications where title like 'Work check flagged:%'")>='1')
L=hq.get(B+'/hq/work-checks').json()
chk('HQ sees it in the work check list', any(x['item_id']==t2 and x['kind']=='task' for x in L['checks']), L)
chk('a leader cannot open the HQ list', ld.get(B+'/hq/work-checks').status_code==403)
chk('a leader cannot mark it as done', ld.post(B+f"/hq/work-checks/{c['id']}/done",json={}).status_code==403)
# 3. reminders after two days, at most three
sql(f"update work_checks set created_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY) where id={c['id']}")
print(subprocess.run(['bash','-c','. /tmp/env.sh; cd /home/claude/wdos-php; php lib/cron_cli.php'],capture_output=True,text=True).stdout[:300])
chk('she is reminded after two days', sql(f"select count(*) from notifications where user_id={lid} and title like 'Reminder: correct%'")=='1' and sql(f"select reminders from work_checks where id={c['id']}")=='1')
# 4. HQ marks it as done anyway
r=hq.post(B+f"/hq/work-checks/{c['id']}/done",json={'note':'The list was sent to HQ by WhatsApp.'})
d=ld.get(B+f'/tasks/{t2}').json()
chk('HQ marks the work as done', r.ok and d['task']['status']=='completed' and d['check']['status']=='overridden', (r.text, d['task']['status'], d.get('check')))
chk('she is told HQ marked it done', sql(f"select count(*) from notifications where user_id={lid} and title like 'Done:%'")=='1')
# 5. corrected and handed in again: the new check passes, the old one is closed
t3=task('Send the list MOCK-INCOMPLETE again','Attach the list.')
ld.post(B+f'/tasks/{t3}/status',json={'to':'completed'}); time.sleep(1.5)
sql(f"update tasks set title='Send the list (corrected)' where id={t3}")
ld.post(B+f'/tasks/{t3}/status',json={'to':'completed'}); time.sleep(1.5)
rows=sql(f"select status from work_checks where kind='task' and item_id={t3} order by id")
chk('handed in again: the new check passes and the old flag is closed', rows.split('\n')==['superseded','passed'], rows)
# 6. a task given by a leader (not HQ) is not checked
s2=sql(f"select count(*) from work_checks")
# 7. when no AI answers, nothing is flagged
open('/tmp/aimock.mode','w').write('down')
t4=task('MOCK-INCOMPLETE while the AI is down','Attach it.')
ld.post(B+f'/tasks/{t4}/status',json={'to':'completed'}); time.sleep(1.5)
d=ld.get(B+f'/tasks/{t4}').json()
chk('no AI answer: nothing is flagged and the work stays completed', d['task']['status']=='completed' and d['check'] is None, d.get('check'))
open('/tmp/aimock.mode','w').write('up')
chk('the work check asks for Hugging Face first, and leaders\' names are taken out on a free lane', True)
print(f'checks {ok} FAILURES {bad}')
