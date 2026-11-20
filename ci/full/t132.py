# v109.40: ambiences. Six moods for the workspace, light and dark, kept on the device, WODDI the default; text stays readable (WCAG AA)
import requests, re
B='http://localhost:8080'; ok=bad=0
def chk(n,c,info=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(info)[:300])
def L(h):
  c=[int(h[i:i+2],16)/255 for i in (1,3,5)]; c=[x/12.92 if x<=.03928 else ((x+.055)/1.055)**2.4 for x in c]; return .2126*c[0]+.7152*c[1]+.0722*c[2]
def cr(a,b): a,b=L(a),L(b); return (max(a,b)+.05)/(min(a,b)+.05)
css=requests.get(B+'/assets/css/app.css').text; ui=requests.get(B+'/js/ui.js').text; ix=requests.get(B+'/').text
low=[]
for k in ['sunrise','garden','lavender','ocean','ankara']:
  for light in (False,True):
    sel=f':root[data-ambience="{k}"]'+('[data-theme="light"]' if light else '')+'{'
    chk(f'{k} {"light" if light else "dark"} colours exist', sel in css)
    blk=css.split(sel,1)[1].split('}',1)[0]; v=dict(re.findall(r'--([a-z0-9-]+):(#[0-9A-Fa-f]{6})',blk))
    for bg in ('bg','surface','raised'):
      for fg,need in (('ink',4.5),('ink-2',4.5),('accent',4.5),('ink-3',3.5)):
        if cr(v[fg],v[bg])<need: low.append((k,light,fg,bg,round(cr(v[fg],v[bg]),2)))
    chk(f'{k} {"light" if light else "dark"} has its soft pattern', (sel[:-1]+':not([data-motif="off"]){--motif:url(') in css)
chk('all ambience text meets WCAG AA contrast', not low, low)
chk('the WODDI default is unchanged: no ambience set means the original colours', ':root{\n  --brand:#D4006A' in css and '--bg:#130912' in css and '--bg:#FAF4F7' in css)
chk('the choice is applied before the page draws, so there is no flash', "localStorage.getItem('wdos.ambience')" in ix and 'd.dataset.ambience=a' in ix)
chk('six ambiences offered in Settings', len(re.findall(r"\{ k: '(woddi|sunrise|garden|lavender|ocean|ankara)'", ui))==6)
st=requests.get(B+'/js/pages/settings.js').text
chk('Settings has the Ambience card and the pattern switch', "card('Ambience'" in st and 's-motif' in st)
fr=requests.get(B+'/assets/i18n/fr.json').json()
chk('ambience words are translated', fr.get('Ambience')=='Ambiance' and fr.get('Rich earth and gold'))
# v109.41: the official colours in full strength
chk('banners are solid WODDI magenta with a lemon green line', '.hero{background:#D4006A;' in css and '#9E004F' not in css.split('v109.41')[1] and '#B8005C' not in css.split('v109.41')[1] and '.hero::after{content:"";position:absolute;left:0;right:0;bottom:0;height:6px;background:#7CB518}' in css)
chk('the page she is on is solid magenta in the menu', '.nav a[aria-current="page"]{background:#D4006A;color:#fff' in css)
chk('network tags in full colour', '.tag--wgmn{background:#D4006A;color:#fff' in css and '.tag--wnnn{background:#7CB518;color:#13210A' in css)
chk('white on magenta and dark green on lime pass AA', cr('#FFFFFF','#D4006A')>=4.5 and cr('#13210A','#7CB518')>=4.5 and cr('#D4006A','#FFFFFF')>=4.5 and cr('#FF3D9E','#130912')>=4.5)
print(f'checks {ok+bad} FAILURES {bad}')
