# Synthetic data for the guided tour screenshots. Runs after t108 and t109 on a local test database. No real people.
import requests, base64, subprocess, json
B='http://127.0.0.1:8080/api'
SQL='mariadb --socket=/tmp/mdb.sock -uroot wdos'.split()
def sql(q): return subprocess.run(SQL+['-N','-e',q],capture_output=True,text=True).stdout.strip()
def S(): s=requests.Session(); s.headers['X-WDOS']='1'; return s
def login(e,p): s=S(); r=s.post(B+'/auth/login',json={'email':e,'password':p}); assert r.ok,(e,r.text); return s
sql("delete from login_attempts")
hq=login('hq@test.local','hq-password-1'); ld=login('leader@test.local','leader-pass-1')
lid=int(sql("select id from users where email='leader@test.local'"))
PDF=b'%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n'+b'x'*2000
up=lambda s,name,purpose='': s.post(B+'/files',json={'name':name,'data':base64.b64encode(PDF).decode(),'purpose':purpose}).json()['file']['id']
br=up(hq,'December outreach plan.pdf')
t=hq.post(B+'/tasks',json={'title':'Hold one outreach day in every LGA','details':'Read the plan attached. Hold the outreach day, then upload the attendance sheet and photos.','assignees':[lid],'requires_approval':True,'files':[br]}).json()
print('task',t.get('id'))
tpl=ld.get(B+'/reporting').json(); print('reporting keys', list(tpl)[:10])
gid=sql("select id from users where email='grace.envoy@wdos-mail.org'")
if gid: print('envoy on', hq.post(B+f'/hq/envoys/{gid}',json={'active':True}).status_code)
json.dump({'task':t.get('id'),'envoy':gid},open('/home/claude/tour/seed.json','w'))
