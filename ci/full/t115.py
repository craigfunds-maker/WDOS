# v109.16: joining form location levels: country, state, LGA or district, community cluster (ward), chapter.
# Lists for every country from geoBoundaries; Nigeria (INEC) and Kenya (IEBC) keep their polling units as chapters.
import json, os
from playwright.sync_api import sync_playwright
B='http://localhost:8080'; G='/home/claude/wdos-php/assets/geo/adm'; ok=0; bad=0
def chk(n,c,x=''):
  global ok,bad
  if c: ok+=1
  else: bad+=1; print('FAIL',n,str(x)[:300])
idx=json.load(open(f'{G}/index.json'))
AF="DZ AO BJ BW BF BI CV CM CF TD KM CG CD CI DJ EG GQ ER SZ ET GA GM GH GN GW LS LR LY MG MW ML MR MU MA MZ NA NE RW ST SN SC SL SO ZA SS SD TZ TG TN UG EH ZM ZW".split()
chk('district lists for most African countries', sum(c in idx for c in AF)>=45, [c for c in AF if c not in idx])
chk('diaspora countries covered', all(c in idx for c in ['US','CA','GB','DE','AU','FR']), idx)
chk('Nigeria and Kenya keep their electoral lists', 'NG' not in idx and 'KE' not in idx)
for f in os.listdir(f'{G}/TZ'):
  d=json.load(open(f'{G}/TZ/{f}')); chk(f'TZ {f} is districts to wards', isinstance(d,dict) and all(isinstance(v,list) for v in d.values()))
def opts(pg,sel): return pg.eval_on_selector(sel,'e=>[...e.options].map(o=>o.textContent)')
with sync_playwright() as p:
  b=p.chromium.launch(); pg=b.new_page(viewport={'width':760,'height':1000}); errs=[]; pg.on('pageerror',lambda e: errs.append(str(e)))
  pg.goto(B+'/'); pg.wait_for_selector('.startcard'); pg.click('#start-continue'); pg.click('.net--wgmn'); pg.click('#net-continue'); pg.wait_for_selector('.stepnav')
  pg.fill('#f-first_name','Test'); pg.fill('#f-last_name','Person'); pg.fill('#f-dob','1985-02-03'); pg.fill('#f-contact','+2348012340000')
  pg.click('.stepnav button[type=button]:not(.btn--ghost)'); pg.wait_for_timeout(700)
  def pick(sel,rx): v=pg.eval_on_selector(sel,f'e=>[...e.options].find(o=>/{rx}/.test(o.textContent)).value'); pg.select_option(sel,v); pg.wait_for_timeout(1200)
  lab=lambda f: pg.inner_text(f'label[for="{f}"] > span')
  pick('#f-country','^Nigeria'); pick('#f-state','Federal'); pg.select_option('select#f-lga',label='Abuja Municipal (AMAC)'); pg.wait_for_timeout(1200)
  chk('label: Community cluster (ward)', lab('f-community')=='Community cluster (ward)', lab('f-community')); chk('label: Chapter', lab('f-ward')=='Chapter', lab('f-ward'))
  chk('ward list first line', opts(pg,'#f-commsel')[0]=='Choose your community cluster (ward)')
  pg.select_option('#f-commsel','Kabusa'); pg.wait_for_timeout(500); o=opts(pg,'#f-boothsel')
  chk('Nigeria: polling units are the chapters', o[0]=='Choose your chapter' and o[1].startswith('001 Kabusa') and o[-1]=='My chapter is not listed', o[:3])
  chk('old explanation removed', 'Your booth is your polling unit' not in pg.inner_text('body'))
  pick('#f-country','^Tanzania'); pick('#f-state','Dar es Salaam')
  chk('Tanzania districts', opts(pg,'select#f-lga')[1:4]==['Ilala','Kinondoni','Temeke'], opts(pg,'select#f-lga'))
  pg.select_option('select#f-lga','Ilala'); pg.wait_for_timeout(900); o=opts(pg,'#f-commsel'); chk('Tanzania wards listed', len(o)>10 and 'Buguruni' in o, o[:5])
  chk('no polling list: WDOS places her by default', pg.eval_on_selector('#f-boothsel','e=>e.value')=='__auto__')
  pick('#f-country','^Ghana'); pick('#f-state','Greater Accra'); o=opts(pg,'select#f-lga'); chk('Ghana districts', len(o)>20 and 'Accra Metropolis' in o, o[:4])
  pg.select_option('select#f-lga','Accra Metropolis'); pg.wait_for_timeout(700); chk('no ward list: she types her community', pg.is_visible('#f-community') and pg.is_hidden('#f-commsel'))
  pick('#f-country','United States'); pick('#f-state','Texas'); o=opts(pg,'select#f-lga'); chk('diaspora: cities and districts together', o[0]=='Choose your city or district' and len(o)>200, o[:3])
  chk('diaspora ward is optional', lab('f-community')=='Community cluster (ward, optional)', lab('f-community'))
  chk('no page errors', not errs, errs); b.close()
# live-site case: the country has WDOS structure units, so its states come from /geo/children, not the published list
with sync_playwright() as p:
  b=p.chromium.launch(); pg=b.new_page(viewport={'width':760,'height':1000})
  def countries(route):
    r=route.fetch(); d=r.json()
    for c in d['countries']:
      if c['code']=='GH': c['children']=2
    route.fulfill(response=r, body=json.dumps(d))
  pg.route('**/api/geo/countries', countries)
  pg.route('**/api/geo/children/*', lambda route: route.fulfill(status=200, content_type='application/json', body=json.dumps({'units':[{'id':9001,'name':'Central'},{'id':9003,'name':'Ashanti Region'}]})))
  pg.goto(B+'/'); pg.wait_for_selector('.startcard'); pg.click('#start-continue'); pg.click('.net--wgmn'); pg.click('#net-continue'); pg.wait_for_selector('.stepnav')
  pg.fill('#f-first_name','Test'); pg.fill('#f-last_name','Person'); pg.fill('#f-dob','1985-02-03'); pg.fill('#f-contact','+233201234567')
  pg.click('.stepnav button[type=button]:not(.btn--ghost)'); pg.wait_for_timeout(700)
  v=pg.eval_on_selector('#f-country','e=>[...e.options].find(o=>/^Ghana/.test(o.textContent)).value'); pg.select_option('#f-country',v); pg.wait_for_timeout(1300)
  for st,n in (('Central',20),('Ashanti Region',40)):
    pg.select_option('#f-state',label=st); pg.wait_for_timeout(1300)
    o=pg.eval_on_selector('select#f-lga','e=>e.isConnected?[...e.options].map(o=>o.textContent):[]')
    chk(f'structure-unit state {st} still lists its districts', len(o)-2>=n, o[:3])
  b.close()
print('checks',ok+bad,'FAILURES',bad)
