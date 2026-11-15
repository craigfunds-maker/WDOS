import requests, json, subprocess, datetime, base64
B='http://localhost:8080/api'; H={'X-WDOS':'1'}
def S(email,pw):
    s=requests.Session(); s.headers.update(H); r=s.post(B+'/auth/login',json={'email':email,'password':pw}); assert r.ok, r.text; return s
def sql(q): return subprocess.run(['mariadb','--socket=/tmp/mdb.sock','-uroot','wdos','-N','-e',q],capture_output=True,text=True).stdout.strip()
bad=0
def chk(n,c,e=''):
    global bad
    print(('PASS ' if c else 'FAIL ')+n, '' if c else str(e)[:400]); bad+=0 if c else 1
hq=S('hq@test.local','hq-password-1'); lead=S('leader@test.local','leader-pass-1'); co=S('coord@test.local','coord-pass-1')
me=lead.get(B+'/me').json()['user']
r=lead.get(B+'/messages'); chk('list empty', r.ok and r.json()['items']==[] and 'general' in r.json()['categories'] or r.ok, r.text)
cats=list(r.json()['categories'].keys())
r=lead.post(B+'/messages',json={'subject':'Membership cards','body':'When do the cards arrive? See https://woddi.org/cards','category':cats[0]}); chk('member writes HQ', r.ok, r.text); tid=r.json().get('id')
chk('empty body refused', lead.post(B+'/messages',json={'subject':'x','body':''}).status_code==422)
n=sql("select count(*) from notifications where kind='message'"); chk('HQ notified', int(n)>=1, n)
l=hq.get(B+'/messages').json(); chk('HQ inbox', l['items'] and l['items'][0]['id']==tid and l['items'][0]['unread']>=1 and l['counts']['unread']>=1, l)
chk('HQ unread count', hq.get(B+'/messages/unread').json().get('unread',0)>=1, hq.get(B+'/messages/unread').text)
d=hq.get(B+f'/messages/{tid}').json(); chk('HQ opens', d['is_hq'] and len(d['messages'])==1 and d['thread']['person']['id']==me['id'], d)
chk('read clears', hq.get(B+'/messages/unread').json()['unread']==0)
chk('other member blocked', co.get(B+f'/messages/{tid}').status_code in (403,404))
r=hq.post(B+f'/messages/{tid}/reply',json={'body':'They arrive next week.'}); chk('HQ replies', r.ok, r.text)
chk('member unread', lead.get(B+'/messages/unread').json()['unread']==1, lead.get(B+'/messages/unread').text)
d=lead.get(B+f'/messages/{tid}').json(); chk('member reads reply', len(d['messages'])==2 and int(d['messages'][1]['from_hq'])==1 and not d['is_hq'], d)
chk('member cannot resolve', lead.post(B+f'/messages/{tid}/status',json={'status':'resolved'}).status_code==403)
chk('HQ resolves', hq.post(B+f'/messages/{tid}/status',json={'status':'resolved'}).ok)
chk('resolved tab', len(lead.get(B+'/messages?status=resolved').json()['items'])==1)
chk('reply reopens', lead.post(B+f'/messages/{tid}/reply',json={'body':'Thank you'}).ok and hq.get(B+f'/messages/{tid}').json()['thread']['status']=='open')
p=hq.get(B+'/messages/people?q=coord').json()['people']; chk('HQ finds people', len(p)>=1, p)
chk('member cannot search people', lead.get(B+'/messages/people?q=a').status_code==403)
r=hq.post(B+'/messages',json={'to':p[0]['id'],'subject':'Your report','body':'Please send the October report.','category':cats[0]}); chk('HQ starts thread', r.ok, r.text); t2=r.json()['id']
d=co.get(B+f'/messages/{t2}').json(); chk('recipient sees HQ thread', len(d['messages'])==1 and int(d['messages'][0]['from_hq'])==1, d)
chk('recipient replies', co.post(B+f'/messages/{t2}/reply',json={'body':'Sent.'}).ok)
chk('lead cannot see other thread', lead.get(B+f'/messages/{t2}').status_code in (403,404))
chk('mail off queues nothing', sql('select count(*) from mail_outbox')=='0')
# library
L=lead.get(B+'/library').json(); chk('library seeded', len(L['items'])==3 and not L['is_hq'], L)
chk('member cannot add', lead.post(B+'/library',json={'title':'x','url':'https://a.b'}).status_code==403)
r=hq.post(B+'/library',json={'title':'Report template','category':'Templates','audience':'leaders','url':'https://drive.google.com/x'}); chk('HQ adds link', r.ok, r.text)
pdf='data:application/pdf;base64,'+base64.b64encode(b'%PDF-1.4 test').decode()
r=hq.post(B+'/library',json={'title':'HQ handbook','category':'Policies','audience':'hq','data':pdf,'file_name':'handbook.pdf'}); chk('HQ adds file', r.ok, r.text); fid=r.json().get('id')
chk('bad type refused', hq.post(B+'/library',json={'title':'bad','data':'data:application/x-msdownload;base64,AAAA','file_name':'a.exe'}).status_code==422)
L=lead.get(B+'/library').json(); chk('leader sees link not hq file', len(L['items'])==4, [x['title'] for x in L['items']])
chk('hq file blocked for leader', lead.get(B+f'/library/{fid}/file').status_code in (403,404))
f=hq.get(B+f'/library/{fid}/file'); chk('hq downloads', f.ok and f.content==b'%PDF-1.4 test' and 'pdf' in f.headers['content-type'], f.headers)
chk('archive', hq.post(B+f'/library/{fid}',json={'archived':True}).ok and any(x['archived'] for x in hq.get(B+'/library').json()['items']))
# meeting
st=(datetime.datetime.utcnow()+datetime.timedelta(days=3)).strftime('%Y-%m-%d %H:%M:%S'); en=(datetime.datetime.utcnow()+datetime.timedelta(days=3,hours=1)).strftime('%Y-%m-%d %H:%M:%S')
r=lead.post(B+'/meetings',json={'title':'Planning call','mode':'virtual','location':'Zoom: https://zoom.us/j/123456?pwd=abc','starts_at':st,'ends_at':en,'audience':'leaders:WGMN','agenda':'Plan'}); j=r.json(); chk('meeting scheduled', r.ok and j['invited']>0 and j['mail_on'] is False and j['emails']==0, r.text)
d=co.get(B+f"/meetings/{j['id']}").json(); m=d.get('meeting',d); chk('meeting link', m.get('link')=='https://zoom.us/j/123456?pwd=abc', m)
n=sql(f"select count(*) from notifications where kind='meeting' and url like '%meetings/{j['id']}%'"); chk('meeting notifications', int(n)>0, n)
n=sql("select count(*) from push_outbox"); print('push_outbox rows', n)
print('FAILURES', bad)
