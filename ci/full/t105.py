# v103.0: email and phone are both compulsory at registration; the volunteering choice.
import requests, subprocess, json
B='http://localhost:8080/api'
def S():
    s=requests.Session(); s.headers.update({'X-WDOS':'1'}); return s
def sql(q): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot','wdos','-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0; cnt=0
def chk(n,c,e=''):
    global bad,cnt; cnt+=1
    if not c: bad+=1; print('FAIL',n,str(e)[:300])
hq=S(); assert hq.post(B+'/auth/login',json={'email':'hq@test.local','password':'hq-password-1'}).ok
ng=[c for c in hq.get(B+'/geo/countries').json()['countries'] if c['code']=='NG'][0]['id']
fct=[u for u in hq.get(B+f'/geo/children/{ng}').json()['units'] if 'Federal' in u['name']][0]
n=[900]
def reg(**kw):
    n[0]+=1; sql('delete from login_attempts'); s=S()
    b={'network':'WGMN','first_name':'Vera','last_name':f'V{n[0]}','dob':'1985-03-04','phone':f'+23481{30000000+n[0]}','country_id':ng,'state_id':fct['id'],'lga':'Bwari','community':'Ushafa','password':'246810','consent':True,'email':f'v{n[0]}@example.org','lang':'en'}
    b.update(kw); r=s.post(B+'/auth/register',json=b); return s,r,b
before=int(sql('select count(*) from users'))
# ---- both compulsory
for label,kw,code in [('email with no dot',{'email':'vera@nowhere'},'email'),('email with no at sign',{'email':'vera.example.org'},'email'),
                      ('no phone',{'phone':''},'phone'),('phone of 7 digits',{'phone':'+234 801'},'phone'),('phone of letters',{'phone':'call me later'},'phone'),('phone of 16 digits',{'phone':'+1234567890123456'},'phone'),
                      ('neither',{'email':'','phone':''},'phone')]:
    r=reg(**kw)[1]; j=r.json(); chk(f'registration refused: {label}', r.status_code==422 and (code is None or j.get('error')==code) and j.get('message'), r.text)
chk('refused registrations create no account, no journey and no chapter place', int(sql('select count(*) from users'))==before and sql("select count(*) from users where last_name like 'V9%'")=='0')
chk('the refusal names the field in plain words', 'phone number' in reg(phone='')[1].json()['message'].lower())
# v106: no email at all: she joins and signs in with her phone number alone
sp,r,bp=reg(email='',phone='+234 809 111 2222'); up=r.json().get('user',{}); chk('v106: a woman with no email joins with her phone number alone', r.ok and up.get('email_is_phone') and up.get('chapter') and up.get('journey',{}).get('done')==1, r.text)
chk('v106: the internal sign-in name is never shown as her email', '@phone.wdos.invalid' not in json.dumps(hq.get(B+f"/hq/members/{up['id']}").json().get('member',{}).get('signs_in_with','')) )
lg=S(); sql('delete from login_attempts'); chk('v106: she signs in with the phone number, typed with or without spaces', lg.post(B+'/auth/login',json={'email':'+2348091112222','password':'246810'}).ok and S().post(B+'/auth/login',json={'email':'+234 809 111 2222','password':'246810'}).ok)
chk('v106: a wrong PIN with her phone number is refused', S().post(B+'/auth/login',json={'email':'+2348091112222','password':'000999'}).status_code==401)
r=reg(email='',phone='+2348091112222')[1]; chk('v106: a second woman with the same phone and no email is told plainly what to do, and nothing is created', r.status_code==409 and r.json().get('error')=='phone_taken' and 'email' in r.json()['message'], r.text)
s9,r,b9=reg(phone='+2348091112222'); chk('v106: with her own email she can still join on that shared phone', r.ok and not r.json()['user'].get('email_is_phone'), r.text)
sql('delete from login_attempts'); chk('v106: the phone number still opens only the phone-only account, not the email one', S().post(B+'/auth/login',json={'email':'+2348091112222','password':'246810'}).json().get('user',{}).get('id')==up.get('id'))
s1,r,b1=reg(phone='0801 234 5678 '); u1=r.json().get('user',{}); chk('registers with both: a local-format phone with spaces is accepted', r.ok and u1.get('email')==b1['email'] and u1.get('phone') and not u1.get('email_is_phone'), r.text)
chk('she is placed in a chapter and day 1 of the journey is done', bool(u1.get('chapter')) and u1.get('journey',{}).get('done')==1, u1)
r=reg(email=b1['email'].upper())[1]; chk('the same email again is refused, whatever the capitals', r.status_code in (409,422) and r.json().get('error') in ('email_taken','exists','email'), r.text)
s2,r,b2=reg(phone=b1['phone']); u2=r.json().get('user',{}); chk('shared phones: a second woman can register with the same phone number and her own email', r.ok and u2.get('id')!=u1.get('id'), r.text)
lg=S(); sql('delete from login_attempts'); chk('sign-in works with the email', lg.post(B+'/auth/login',json={'email':b1['email'],'password':'246810'}).ok)
lg=S(); r=lg.post(B+'/auth/login',json={'email':b1['phone'],'password':'246810'}); chk('a shared phone number does not open an email account', not r.ok and r.json().get('error')=='bad_credentials', r.text)
r=S().post(B+'/auth/register',json={**b1,'email':'someone.new@example.org','password':'246810','dob':'1985-13-45'}); chk('an impossible date of birth is still refused', r.status_code==422, r.text)
# ---- the volunteering choice at registration
roles=S().get(B+'/public/volunteer-roles'); R=roles.json().get('roles',[]); codes=[x['code'] for x in R]
chk('the role list is public, and offers chapter and LGA roles with both network titles', roles.ok and 'chapter_lead' in codes and all(x['title_wgmn'] and x['title_wnnn'] for x in R), roles.text[:200])
chk('the registration form offers the Volunteer Leadership Framework roles (country to chapter), and no continental or global seat', {x['level'] for x in R}=={'country','state_region','lga','community_cluster','chapter'} and not any(x['level'] in ('region','continent','global') for x in R), [x['level'] for x in R])
s3,r,b3=reg(volunteer_interest=''); u3=r.json()['user']; chk('joining as a member: no volunteer mark, no assignments', not u3.get('volunteer') and u3.get('volunteer_interest')=='' and s3.get(B+'/tasks').json()['total']==0, u3.get('volunteer_interest'))
s4,r,b4=reg(volunteer_interest='volunteer'); u4=r.json()['user']
me4=s4.get(B+'/me').json()['user']; chk('choosing to volunteer at registration marks her a volunteer', me4.get('volunteer') and me4.get('volunteer_interest')=='volunteer' and me4.get('volunteer_interest_label')=='Volunteer in the chapter', me4.get('volunteer_interest_label'))
chk('and gives her the three development assignments, tied to her chapter', s4.get(B+'/tasks').json()['total']==3 and sql(f"select count(*) from tasks where assignee_id={u4['id']} and chapter_id={u4['chapter']['id']}")=='3', s4.get(B+'/tasks').text[:200])
chk('and thanks her once', sql(f"select count(*) from notifications where user_id={u4['id']} and title='Thank you for volunteering'")=='1')
s5,r,b5=reg(volunteer_interest='assistant_cluster_coordinator',network='WNNN',dob='2001-05-05'); u5=r.json()['user']; me5=s5.get(B+'/me').json()['user']
chk('choosing a leadership role records the interest with the title of her own network', me5.get('volunteer_interest')=='assistant_cluster_coordinator' and me5.get('volunteer_interest_label')=='Interested in: Assistant Ward / Community / Cluster Lead' and me5.get('volunteer'), me5.get('volunteer_interest_label'))
chk('interest in a role gives no seat and no leader access', not me5.get('is_leader') and me5.get('seat') is None and sql(f"select count(*) from seats where user_id={u5['id']}")=='0' and s5.get(B+'/leader/dashboard').status_code in (403,404), s5.get(B+'/leader/dashboard').status_code)
s6,r,b6=reg(volunteer_interest='country_coordinator'); me6=s6.get(B+'/me').json()['user']; chk('a role that is not offered cannot be slipped in: it is kept as plain volunteering', me6.get('volunteer_interest')=='volunteer', me6.get('volunteer_interest'))
s7,r,b7=reg(volunteer_interest="x' OR 1=1 --"); me7=s7.get(B+'/me').json()['user']; chk('a made-up role code is kept as plain volunteering, and nothing breaks', r.ok and me7.get('volunteer_interest')=='volunteer')
# ---- later, from the home screen
chk('the choice needs a signed-in member', S().post(B+'/me/volunteer-role',json={'choice':'volunteer'}).status_code==401)
r=s3.post(B+'/me/volunteer-role',json={'choice':'chapter_lead'}); me3=s3.get(B+'/me').json()['user']; chk('a member can choose later from the home screen', r.ok and r.json()['choice']=='chapter_lead' and me3.get('volunteer_interest_label')=='Interested in: Chapter Coordinator' and s3.get(B+'/tasks').json()['total']==3, r.text)
r=s3.post(B+'/me/volunteer-role',json={'choice':'chapter_lead'}); chk('saving the same choice twice adds no second set of assignments', r.ok and s3.get(B+'/tasks').json()['total']==3 and sql(f"select count(*) from notifications where user_id={u3['id']} and title='Thank you for volunteering'")=='1')
r=s3.post(B+'/me/volunteer-role',json={'choice':''}); me3=s3.get(B+'/me').json()['user']; chk('clearing the choice keeps her a volunteer and only drops the leadership interest', r.ok and r.json()['choice']=='volunteer' and me3.get('volunteer') and me3.get('volunteer_interest_label')=='Volunteer in the chapter', r.text)
r=s1.post(B+'/me/volunteer',json={}); me1=s1.get(B+'/me').json()['user']; chk('the older Institute button still works and shows as volunteering', r.ok and me1.get('volunteer') and me1.get('volunteer_interest_label')=='Volunteer in the chapter', me1.get('volunteer_interest_label'))
chk('the Institute button after a registration choice says already, with no second set', s4.post(B+'/me/volunteer',json={}).json().get('already') and s4.get(B+'/tasks').json()['total']==3)
# ---- HQ sees it; nobody else does
chk('the volunteer list is for HQ only', s4.get(B+'/hq/volunteers').status_code==403 and S().get(B+'/hq/volunteers').status_code==401)
v=hq.get(B+'/hq/volunteers').json(); it={x['id']:x for x in v['items']}
chk('HQ list holds every volunteer with her choice', v['total']==6 and it[u5['id']]['interest']=='Assistant Ward / Community / Cluster Lead' and it[u5['id']]['leadership'] and it[u4['id']]['interest']=='Volunteer in the chapter' and not it[u4['id']]['leadership'] and u2['id'] not in it and it[u1['id']]['interest']=='Volunteer in the chapter', {k:x['interest'] for k,x in it.items()})
chk('the list carries no email, phone or password', not any(k in json.dumps(v) for k in ('@example.org','+23481','password')), '')
m=hq.get(B+f"/hq/members/{u5['id']}").json()['member']; chk('HQ member page shows the choice', m.get('volunteer_interest_label')=='Interested in: Assistant Ward / Community / Cluster Lead', m.get('volunteer_interest_label'))
chk('her chapter leaders were not sent anyone’s contact details', sql("select count(*) from notifications where title='A member of your chapter is now a volunteer' and (body like '%@%' or body like '%+234%')")=='0')
# ---- drafts keep the new fields, and never a password
d=S().post(B+'/public/draft',json={'data':{'network':'WGMN','email':'draft@example.org','contact':'+2348011112222','vol':'lead','vol_role':'chapter_lead','password':'246810'}})
tok=d.json().get('token'); g=S().post(B+'/public/draft/read',json={'token':tok}); dd=g.json().get('data') or {}
chk('a saved draft keeps email, phone and the volunteering choice, and never the password', d.ok and dd.get('email')=='draft@example.org' and dd.get('contact')=='+2348011112222' and dd.get('vol')=='lead' and dd.get('vol_role')=='chapter_lead' and 'password' not in dd and sql("select count(*) from reg_drafts where data like '%246810%'")=='0', g.text[:200])
print('checks',cnt); print('FAILURES',bad)
