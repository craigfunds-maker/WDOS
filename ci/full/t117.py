# v109.21: wards (community clusters) for countries without an official ward list, from GeoNames places inside
# each district; OpenStreetMap polling stations as chapters where mapped; no "In X, this is your ..." note.
import json
from playwright.sync_api import sync_playwright
B='http://localhost:8080'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
idx=json.load(open('/home/claude/wdos-php/assets/geo/adm/index.json'))
chk('wards in at least 160 countries', sum(1 for v in idx.values() if v)>=160, len(idx))
for cc in ['LR','SO','CD','EG','GH','UG','GB']: chk(f'{cc} has wards', idx.get(cc,0)>=1, idx.get(cc))
def opts(pg,sel): return pg.eval_on_selector(sel,'e=>e.isConnected?[...e.options].map(o=>o.textContent):[]')
with sync_playwright() as p:
  b=p.chromium.launch(); pg=b.new_page(viewport={'width':760,'height':1000}); errs=[]; pg.on('pageerror',lambda e: errs.append(str(e)))
  pg.goto(B+'/'); pg.wait_for_selector('.startcard'); pg.click('#start-continue'); pg.click('.net--wgmn'); pg.click('#net-continue'); pg.wait_for_selector('.stepnav')
  pg.fill('#f-first_name','Test'); pg.fill('#f-last_name','Person'); pg.fill('#f-dob','1985-02-03'); pg.fill('#f-contact','+231771234567')
  pg.click('.stepnav button[type=button]:not(.btn--ghost)'); pg.wait_for_timeout(700)
  def pick(sel,rx): v=pg.eval_on_selector(sel,f'e=>([...e.options].find(o=>new RegExp({json.dumps(rx)}).test(o.textContent))||{{}}).value'); assert v,(sel,rx); pg.select_option(sel,v); pg.wait_for_timeout(1300)
  pick('#f-country','^Liberia'); pick('#f-state','^Lofa'); pick('select#f-lga','^Foya$')
  o=opts(pg,'#f-commsel'); chk('Liberia, Lofa, Foya: towns and villages as wards', len(o)>50 and o[0]=='Choose your community cluster (ward)', o[:4])
  chk('no "In X, this is your ..." note', 'this is your' not in pg.inner_text('body').lower(), pg.inner_text('label[for="f-community"]'))
  chk('chapter defaults to placing her near home', pg.eval_on_selector('#f-boothsel','e=>e.value')=='__auto__')
  chk('no page errors', not errs, errs); b.close()
print('checks',ok+bad,'FAILURES',bad)
