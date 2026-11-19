# v109.19: official polling-station lists as chapters, beyond Nigeria and Kenya. The states are the electoral
# commission's regions; each district lists its wards; each ward lists its polling stations or centres as chapters.
import json, os
from playwright.sync_api import sync_playwright
B='http://localhost:8080'; EL='/home/claude/wdos-php/assets/geo/el'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
idx=json.load(open(f'{EL}/index.json'))
CASES=json.load(open('/home/claude/t116_cases.json'))
for cc in CASES: chk(f'{cc} is listed', cc in idx, idx); chk(f'{cc} names its source', os.path.exists(f'{EL}/{cc}/source.json'))
def opts(pg,sel): return pg.eval_on_selector(sel,'e=>e.isConnected?[...e.options].map(o=>o.textContent):[]')
with sync_playwright() as p:
  b=p.chromium.launch(); pg=b.new_page(viewport={'width':760,'height':1000}); errs=[]; pg.on('pageerror',lambda e: errs.append(str(e)))
  pg.goto(B+'/'); pg.wait_for_selector('.startcard'); pg.click('#start-continue'); pg.click('.net--wgmn'); pg.click('#net-continue'); pg.wait_for_selector('.stepnav')
  pg.fill('#f-first_name','Test'); pg.fill('#f-last_name','Person'); pg.fill('#f-dob','1985-02-03'); pg.fill('#f-contact','+260971234567')
  pg.click('.stepnav button[type=button]:not(.btn--ghost)'); pg.wait_for_timeout(700)
  def pick(sel,rx): v=pg.eval_on_selector(sel,f'e=>([...e.options].find(o=>new RegExp({json.dumps(rx)}).test(o.textContent))||{{}}).value'); assert v, (sel,rx); pg.select_option(sel,v); pg.wait_for_timeout(1300)
  for cc,(country,st,di,nst) in CASES.items():
    pick('#f-country',country); o=opts(pg,'#f-state'); chk(f'{cc}: commission regions as states', len(o)-2>=nst, o[:4])
    pick('#f-state',st); o=opts(pg,'select#f-lga'); chk(f'{cc}: districts', len(o)>3, o[:4])
    pick('select#f-lga',di); o=opts(pg,'#f-commsel'); chk(f'{cc}: wards', len(o)>=3 and o[0]=='Choose your community cluster (ward)', o[:4])
    pg.select_option('#f-commsel',index=1); pg.wait_for_timeout(500); o=opts(pg,'#f-boothsel')
    chk(f'{cc}: polling stations as chapters', o[0]=='Choose your chapter' and len(o)>=4 and o[-1]=='My chapter is not listed', o[:4])
  chk('no page errors', not errs, errs); b.close()
print('checks',ok+bad,'FAILURES',bad)
