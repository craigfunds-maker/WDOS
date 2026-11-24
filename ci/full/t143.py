# v109.55: a meeting for several groups at once, with chosen people added on top (Azeez, 24 Nov 2026: "in Who is invited?
# i should be able to add/tick multiple people").
import requests, subprocess, json, datetime, os
exec(open('/home/claude/t101.py').read().split("# ---- D01-D14")[0].replace('n=[700]','n=[3400]'))
U=datetime.UTC; tt=lambda **k:(datetime.datetime.now(U)+datetime.timedelta(**k)).strftime('%Y-%m-%dT%H:%M:%SZ')
lagos=int(sql("select id from org_units where level='state_region' and name='Lagos' limit 1"))
def leader(fn,net,pos='state_coordinator'):
    r=hq.post(B+'/hq/leaders/add',json={'first_name':fn,'last_name':'Lead','email':fn.lower()+'.lead@example.org','network':net,'position':pos,'org_unit_id':lagos}); return r.json()['id']
wg=leader('Wuraola','WGMN'); wn=leader('Nnenna','WNNN')
s1,r,b1=reg(); mem=r.json()['user']['id']
hqid=int(sql("select id from users where email='hq@test.local'"))
r=hq.post(B+'/hq/staff',json={'first_name':'Halima','last_name':'Staff','email':'halima.staff@example.org'}); staff=r.json()['id']
told=lambda mid,uid: int(sql(f"select count(*) from notifications where user_id={uid} and url='#/meetings/{mid}'"))
base={'mode':'virtual','location':'https://meet.google.com/abc-defg-hij','starts_at':tt(days=3),'ends_at':tt(days=3,hours=1)}
r=hq.post(B+'/meetings',json={**base,'title':'Mixed forum','audiences':['leaders:WGMN','hq_team:ALL','people:ALL'],'invitees':[mem]}); mid=r.json().get('id')
chk('HQ ticks two groups and adds a person', r.ok and sql(f"select audience from meetings where id={mid}")=='mix', r.text)
chk('the WGMN leader is told', told(mid,wg)==1); chk('the HQ staff member is told', told(mid,staff)==1)
chk('the chosen member is told', told(mid,mem)==1); chk('the WNNN leader is not told', told(mid,wn)==0)
chk('the label names every group', sql(f"select scope_label from meetings where id={mid}")=='WGMN leadership + WODDI HQ team + '+sql(f"select concat(first_name,' ',last_name) from users where id={mem}"))
chk('the member can open it', s1.get(B+f'/meetings/{mid}').ok)
d=hq.get(B+f'/meetings/{mid}').json(); ids={p['id'] for p in d['people']}
chk('the host sees everyone invited', {wg,staff,mem} <= ids and wn not in ids, d['invited'])
r=hq.post(B+'/meetings',json={**base,'title':'Both leaderships','audiences':['leaders:WGMN','leaders:WNNN']}); m2=r.json()['id']
chk('two leaderships at once reach both', told(m2,wg)==1 and told(m2,wn)==1 and told(m2,mem)==0, r.text)
r=hq.post(B+'/meetings',json={**base,'title':'One group','audiences':['leaders:WNNN']}); m3=r.json()['id']
chk('one group alone stays a plain meeting', sql(f"select concat(audience,':',network) from meetings where id={m3}")=='leaders:WNNN' and told(m3,wn)==1 and told(m3,wg)==0)
r=hq.post(B+'/meetings',json={**base,'title':'Old form','audience':'leaders:WGMN'}); chk('the old single choice still works', r.ok and told(r.json()['id'],wg)==1, r.text)
r=hq.post(B+'/meetings',json={**base,'title':'People only','audiences':['people:ALL'],'invitees':[wn]}); chk('people only, as before', r.ok and sql(f"select audience from meetings where id={r.json()['id']}")=='people' and told(r.json()['id'],wn)==1)
chk('nothing ticked is refused', hq.post(B+'/meetings',json={**base,'title':'Nobody','audiences':[]}).status_code==422)
chk('a group she may not call is refused', hq.post(B+'/meetings',json={**base,'title':'Bad','audiences':['leaders:WGMN','bogus:X']}).status_code==422)
gh=int(sql("select id from org_units where level='country' and name='Ghana' limit 1"))
r=hq.post(B+'/meetings',json={**base,'title':'Ghana only','audiences':['leaders:WGMN','hq_team:ALL'],'country_id':gh}); m5=r.json()['id']
chk('a country limit keeps the Lagos leader out but HQ in', told(m5,wg)==0 and told(m5,staff)==1, r.text)
r=hq.post(B+f'/meetings/{mid}/edit',json={'title':'Mixed forum moved','mode':'virtual','location':base['location'],'starts_at':tt(days=4),'ends_at':tt(days=4,hours=1)})
chk('moving a mixed meeting tells every group and the chosen person', r.ok and told(mid,wg)==2 and told(mid,staff)==2 and told(mid,mem)==2 and told(mid,wn)==0, r.text)
r=hq.post(B+f'/meetings/{mid}/delete',json={'confirm':True}); chk('deleting it clears its groups', r.ok and sql(f"select count(*) from meeting_groups where meeting_id={mid}")=='0')
print('FAILURES',bad)
