import requests, subprocess, json
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[1200]'))
s1,r,b1=reg(); u1=r.json()['user']
# staff
chk('member cannot list or create staff', s1.get(B+'/hq/staff').status_code==403 and s1.post(B+'/hq/staff',json={'first_name':'Eve','last_name':'Intruder','email':'eve@example.org'}).status_code==403)
chk('staff login needs a valid email and names', hq.post(B+'/hq/staff',json={'first_name':'A','last_name':'B','email':'x'}).status_code==422 and hq.post(B+'/hq/staff',json={'first_name':'Amara','last_name':'Staff','email':'not-an-email'}).status_code==422)
chk('an email already in use is refused', hq.post(B+'/hq/staff',json={'first_name':'Amara','last_name':'Staff','email':b1['email']}).status_code==409)
r=hq.post(B+'/hq/staff',json={'first_name':'Amara','last_name':'Staff','email':'Amara.Staff@Example.org'}); j=r.json(); temp=j.get('temporary_password',''); sid=j.get('id')
chk('super administrator creates a staff login; temporary password shown once', r.ok and len(temp)==10 and j['email']=='amara.staff@example.org', r.text)
chk('the password is stored only as a hash', temp not in sql(f"select password_hash from users where id={sid}") and sql(f"select concat(role,'|',must_change_password) from users where id={sid}")=='hq_admin|1')
sql('delete from login_attempts'); st=S('amara.staff@example.org',temp)
chk('new staff signs in but HQ data stays closed until she changes the temporary password', st.get(B+'/me').json()['user']['is_hq'] and st.get(B+'/hq/dashboard').status_code==403 and st.get(B+'/hq/members').json().get('error')=='password_change', st.get(B+'/hq/members').text[:200])
r=st.post(B+'/me/password',json={'current_password':temp,'new_password':'Stronger-Pass-77'}); chk('she chooses her own password and the HQ dashboard opens', r.ok and st.get(B+'/hq/dashboard').status_code==200 and st.get(B+'/hq/members').status_code==200, r.text)
chk('ordinary HQ staff cannot create staff, switch staff off, or reset another staff password', st.post(B+'/hq/staff',json={'first_name':'Bola','last_name':'Other','email':'bola@example.org'}).status_code==403 and st.post(B+f'/hq/staff/{sid}',json={'active':False}).status_code==403)
r2=hq.post(B+'/hq/staff',json={'first_name':'Bola','last_name':'Other','email':'bola@example.org'}); bid=r2.json()['id']
chk('ordinary HQ staff cannot reset a colleague', st.post(B+f'/hq/members/{bid}/reset-password',json={}).status_code==403)
hqid=sql("select id from users where email='hq@test.local'"); chk('nobody can switch off a super administrator or herself here', hq.post(B+f'/hq/staff/{hqid}',json={'active':False}).status_code==422 and st.post(B+f'/hq/members/{hqid}/reset-password',json={}).status_code==403)
r=hq.post(B+f'/hq/staff/{sid}',json={'active':False}); chk('switching a staff login off signs her out at once', r.ok and st.get(B+'/hq/dashboard').status_code==401, st.get(B+'/hq/dashboard').status_code)
sql('delete from login_attempts'); chk('and she cannot sign back in', S().post(B+'/auth/login',json={'email':'amara.staff@example.org','password':'Stronger-Pass-77'}).status_code==403)
hq.post(B+f'/hq/staff/{sid}',json={'active':True}); sql('delete from login_attempts'); chk('switched back on, she signs in again', S().post(B+'/auth/login',json={'email':'amara.staff@example.org','password':'Stronger-Pass-77'}).ok)
ls=hq.get(B+'/hq/staff').json(); chk('staff list shows sign-in names and never a password or hash', ls['can_manage'] and len(ls['items'])==3 and 'password_hash' not in json.dumps(ls) and '$2y$' not in json.dumps(ls) and 'Stronger' not in json.dumps(ls), ls)
# leaders
sl,r,bl=reg(first_name='Lead'); ul=r.json()['user']; hq.post(B+f"/hq/members/{ul['id']}/seat",json={'position':'chapter_lead','org_unit_id':fct['id'],'chapter_id':u1['chapter']['id'],'network':'WGMN'})
sp,r,bp=reg(first_name='Phone'); up=r.json()['user']; sql(f"update users set email='p{bp['phone'][1:]}@phone.wdos.invalid' where id={up['id']}");   # an account from before v103
hq.post(B+f"/hq/members/{up['id']}/seat",json={'position':'assistant_chapter_lead','org_unit_id':fct['id'],'chapter_id':u1['chapter']['id'],'network':'WGMN'})
ll=hq.get(B+'/hq/leader-logins'); it={x['id']:x for x in ll.json()['items']}
chk('leader sign-in list: email for one, phone number for the other, never the internal phone address', it[ul['id']]['signs_in_with']==bl['email'] and it[up['id']]['signs_in_with']==bp['phone'] and it[up['id']]['kind']=='Phone number' and 'wdos.invalid' not in ll.text, it.get(up['id']))
chk('no password or hash is in the leader list', 'password_hash' not in ll.text and '$2y$' not in ll.text and '246810' not in ll.text and it[ul['id']]['password']=='Set by the person. Cannot be read.')
chk('leader sign-in list is HQ only', sl.get(B+'/hq/leader-logins').status_code==403 and s1.get(B+'/hq/leader-logins').status_code==403)
r=hq.post(B+f"/hq/members/{ul['id']}/reset-password",json={}); t2=r.json()['temporary_password']; sql('delete from login_attempts')
chk('HQ gives a locked-out leader a temporary password; her old one stops working', S().post(B+'/auth/login',json={'email':bl['email'],'password':'246810'}).status_code==401 and S().post(B+'/auth/login',json={'email':bl['email'],'password':t2}).ok)
chk('the list then shows it as temporary', {x['id']:x for x in hq.get(B+'/hq/leader-logins').json()['items']}[ul['id']]['password']=='Temporary password, not yet changed')
print('FAILURES',bad)
