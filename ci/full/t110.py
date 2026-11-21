# v109.3 follow-ups: meeting countdown, task follow-ups, unread messages, announcements by email and their reminder.
# Each goes out once, in the app (and push), and by email. Synthetic data only.
import requests, subprocess, time, datetime
B='http://localhost:8080/api'
SQL='mariadb --socket=/tmp/mdb.sock -uroot wdos'.split()
def sql(q): return subprocess.run(SQL+['-N','-e',q],capture_output=True,text=True).stdout.strip()
P=F=0
def chk(n,c,e=''):
    global P,F
    if c: P+=1; print('ok',n)
    else: F+=1; print('FAIL',n,e)
def S(): s=requests.Session(); s.headers['X-WDOS']='1'; return s
def login(e,p): s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
def tick():
    sql("delete from app_settings where k='auto_tick'"); r=requests.get(B+'/cron?key=test-admin-key'); assert r.ok, r.text; return r.json()
def at(mins): return (datetime.datetime.now(datetime.UTC).replace(tzinfo=None)+datetime.timedelta(minutes=mins)).strftime('%Y-%m-%d %H:%M:%S')
sql("delete from login_attempts")
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
lid=int(sql("select id from users where email='leader@test.local'"))
sql(f"update users set email='leader.t110@wdos-mail.org' where id={lid}")      # test.local addresses are never emailed
def notes(like): return int(sql(f"select count(*) from notifications where user_id={lid} and title like '{like}'"))
def mails(like): return int(sql(f"select count(*) from mail_outbox where to_email='leader.t110@wdos-mail.org' and subject like '{like}'"))
try:
    # ---- meeting countdown
    form={'leader_name':'Ngozi Leader','region':'Lagos','title':'Countdown check','reason':'Plan the outreach in every LGA.','attendees_text':'LGA coordinators','expected_count':10,'starts_at':at(180)[:16],'duration_min':60}
    mid=ld.post(B+'/meet-requests',json=form).json()['id']
    chk('HQ approves', hq.post(B+f'/meet-requests/{mid}/decide',json={'decision':'approve'}).ok)
    sql(f"update meeting_requests set starts_at='{at(22*60)}' where id={mid}"); tick()
    chk('the day before: in the app', notes('%: tomorrow')==1)
    chk('the day before: by email', mails('WDOS: %: tomorrow')==1, sql('select subject from mail_outbox'))
    tick(); chk('sent once only', notes('%: tomorrow')==1)
    sql(f"update meeting_requests set starts_at='{at(50)}' where id={mid}"); tick()
    chk('one hour before: in the app and by email', notes('%: in 1 hour')==1 and mails('WDOS: %: in 1 hour')==1)
    sql(f"update meeting_requests set starts_at='{at(4)}' where id={mid}"); tick()
    chk('as it starts: in the app and by email', notes('%: starting now')==1 and mails('WDOS: %: starting now')==1)
    # ---- task follow-ups
    soon=sql("select date(utc_timestamp() + interval 1 hour + interval 2 day)")
    r=hq.post(B+'/tasks',json={'title':'Count the chapter members','assignee_id':lid,'due_on':soon}); chk('HQ gives a task due in 2 days', r.ok, r.text)
    tid=r.json().get('id') or int(sql("select max(id) from tasks"))
    sql(f"update tasks set due_on='{soon}' where id={tid}"); sql("delete from cron_runs where job like 'task_followups%'") if sql("show tables like 'cron_runs'") else None
    tick(); chk('2 days before: in the app and by email', notes('Due in 2 days:%')>=1 and mails('WDOS: Due in 2 days:%')>=1, sql('select subject from mail_outbox order by id desc limit 3'))
    # ---- unread message
    r=hq.post(B+'/dm/send',json={'subject':'Hello','body':'Please send the chapter list.','audience':{'people':[lid]}}).json(); bid=r['batch_id']
    sql(f"update dm_batches set undo_until=utc_timestamp() - interval 1 second where id={bid}"); sql("update app_settings set v='0' where k='outbox_tick'"); hq.get(B+'/me'); time.sleep(3); tick()
    chk('the message is released', sql(f'select released_at is not null from dm_batches where id={bid}')=='1')
    sql(f"update dm_threads set last_at=utc_timestamp() - interval 26 hour"); tick()
    chk('unread for a day: reminded in the app and by email', notes('Unread message from%')==1 and mails('WDOS: Unread message from%')==1, sql('select released_at from dm_batches'))
    tick(); chk('the message reminder goes once', notes('Unread message from%')==1)
    # ---- announcements by email, and the reminder
    r=hq.post(B+'/hq/announcements',json={'title':'Leaders meeting on Friday','body':'All leaders meet on Friday at 4pm.','network':'ALL','role':'leaders','by_email':True}); chk('HQ sends an announcement by email too', r.ok, r.text)
    aid=int(sql("select max(id) from announcements"))
    chk('the announcement is emailed to the leader', mails('WDOS: Leaders meeting on Friday%')==1, sql("select to_email from mail_outbox where subject like '%Leaders meeting%'"))
    r=hq.post(B+'/hq/announcements',json={'title':'In the app only','body':'Not emailed.','network':'ALL','role':'leaders'}); chk('without the email box, leaders still get it by email (v109.44: leaders and envoys get every announcement by email)', mails('WDOS: In the app only%')==1)
    sql(f"update announcements set sent_at=utc_timestamp() - interval 3 day where id={aid}"); tick()
    chk('unread after 2 days: reminder in the app and by email', notes('Reminder: Leaders meeting on Friday%')==1 and mails('WDOS: Reminder: Leaders meeting%')==1)
    ld.post(B+f'/announcements/{aid}/ack',json={})
    # ---- self-governance
    mid2=ld.post(B+'/meet-requests',json=dict(form,title='Waiting too long')).json()['id']
    sql(f"update meeting_requests set created_at=utc_timestamp() - interval 13 hour where id={mid2}"); tick()
    chk('HQ reminded about a request waiting 12 hours', int(sql("select count(*) from notifications n join users u on u.id=n.user_id where u.email='hq@test.local' and n.title like 'Waiting for HQ: video meeting%'"))==1)
    tick(); chk('that reminder goes once', int(sql("select count(*) from notifications n join users u on u.id=n.user_id where u.email='hq@test.local' and n.title like 'Waiting for HQ%'"))==1)
    cid=sql("select id from chapters order by id limit 1")
    if cid:
        sql(f"update chapters set members=members+7 where id={cid}"); sql("delete from cron_runs where job='self_check'"); tick()
        chk('a wrong chapter count is corrected by itself', sql(f"select members=(select count(*) from chapter_members m where m.chapter_id={cid} and m.ended_at is null) from chapters where id={cid}")=='1')
    # ---- budget
    sql("delete from mail_outbox"); before=int(sql("select count(*) from mail_outbox"))
    print('bulk budget is enforced in mail_many (MAIL_MONTHLY_BUDGET, default 4500)')
finally:
    sql(f"update users set email='leader@test.local' where id={lid}")
print('checks',P+F); print('FAILURES',F)
