# v109.16: district and ward lists for every country, from geoBoundaries gbOpen (each government's published
# administrative areas). Each district is placed in its state, and each ward in its district, by where it lies on the map.
# States are matched to the state list WDOS already uses (ISO 3166-2 code first, then the name), so nothing already
# saved by members changes. Nigeria (INEC) and Kenya (IEBC) keep their electoral lists, which go down to polling units.
# Output: wdos-php/assets/geo/adm/<CC>/<state code>.json  {"District": ["Ward", ...]}  and adm/index.json
import json, os, re, sys, unicodedata, difflib
from shapely.geometry import shape
from shapely.strtree import STRtree
RAW='/home/claude/gb/raw'; W='/home/claude/wdos-php/assets/geo/world'; OUT='/home/claude/wdos-php/assets/geo/adm'
SKIP={'NG','KE'}
pairs=[l.split() for l in open('/home/claude/gb/list.txt')]
def norm(s):
    s=unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower()
    s=re.sub(r"\b(province|region|state|governorate|district|county|department|departement|prefecture|municipality|oblast|voivodeship|autonomous|region of|of|the|wilaya|muhafazah|division)\b",' ',s)
    return re.sub(r'[^a-z0-9]+','',s)
def jsnorm(x):      # the same as normName() in welcome.js
    x=unicodedata.normalize('NFKD',x); x=''.join(ch for ch in x if not unicodedata.combining(ch)).lower()
    x=re.sub(r'\b(state|region|province|governorate|district|county)\b','',x); return re.sub(r'[^a-z0-9]','',x)
def load(p):
    if not os.path.exists(p): return []
    out=[]
    for f in json.load(open(p))['features']:
        pr=f['properties']; g=f.get('geometry')
        if not g: continue
        try: geo=shape(g)
        except Exception: continue
        if geo.is_empty: continue
        name=(pr.get('shapeName') or '').strip()
        if not name or name.lower() in ('unknown','none','null'): continue
        out.append({'name':re.sub(r'\s+',' ',name),'iso':(pr.get('shapeISO') or '').strip(),'geo':geo,'pt':geo.representative_point()})
    return out
def parent(items, parents):
    if not parents: return
    tree=STRtree([p['geo'] for p in parents])
    for it in items:
        hit=[i for i in tree.query(it['pt']) if parents[i]['geo'].contains(it['pt'])]
        it['up']=int(hit[0]) if hit else int(tree.nearest(it['pt']))
idx={}; report=[]
os.makedirs(OUT,exist_ok=True)
for cc,i3 in pairs:
    if cc in SKIP or not os.path.exists(f'{W}/{cc}.json'): continue
    states=json.load(open(f'{W}/{cc}.json')).get('s') or []
    lv=[load(f'{RAW}/{i3}-ADM{k}.geojson') for k in (1,2,3,4)]
    bycode={f'{cc}-{s[1]}'.upper():s for s in states}; byname={norm(s[0]):s for s in states}
    def match(items):
        m={}
        for k,p in enumerate(items):
            s=bycode.get(p['iso'].upper()) or byname.get(norm(p['name']))
            if not s:
                best=difflib.get_close_matches(norm(p['name']), list(byname), n=1, cutoff=0.8)
                s=byname[best[0]] if best else None
            if s: m[k]=s
        return m
    # which level of this country's areas are WDOS's states? the one that matches the most of them
    best=(0,None,{})
    for k in range(3):
        if not lv[k] or not lv[k+1]: continue      # a level is only useful if there is a level below it
        m=match(lv[k]); n=len({s[1] for s in m.values()})
        if n>best[0]: best=(n,k,m)
    n,k,m=best
    if k is None or k+1>3 or not lv[k+1] or n < 0.5*len(states): report.append(f'{cc}: skipped (states matched {n}/{len(states)})'); continue
    top,dist,ward=lv[k],lv[k+1],(lv[k+2] if k+2<=3 else [])
    if ward and len(dist) < 1.2*len(top):      # the next level only repeats the states (Algeria): go one level down
        dist,ward=ward,(lv[k+3] if k+3<=3 else [])
    parent(dist,top); parent(ward,dist)
    tree={}
    for j,d in enumerate(dist):
        s=m.get(d.get('up'))
        if not s: continue
        kids={w['name'] for w in ward if w.get('up')==j and w['name']!=d['name']}
        tree.setdefault(s[1],{}).setdefault(d['name'],set()).update(kids)
    a1=top
    if not tree: report.append(f'{cc}: no state matched'); continue
    os.makedirs(f'{OUT}/{cc}',exist_ok=True)
    nd=nw=0
    for code,ds in tree.items():
        data={k:sorted(v,key=str.casefold) for k,v in sorted(ds.items(), key=lambda kv: kv[0].casefold())}
        nd+=len(data); nw+=sum(len(v) for v in data.values())
        json.dump(data,open(f'{OUT}/{cc}/{code}.json','w'),ensure_ascii=False,separators=(',',':'))
    idx[cc]=1 if nw else 0     # 1: wards too, 0: districts only
    report.append(f'{cc}: states {len(tree)}/{len(states)} (WDOS states = ADM{k+1}) districts {nd} wards {nw}')
    # by name: every official level-1 area (regions in Malawi, Uganda, Madagascar...) with its level-2 and level-3 areas
    if lv[0] and lv[1]:
        parent(lv[1],lv[0]); parent(lv[2],lv[1]) if lv[2] else None
        for i0,a in enumerate(lv[0]):
            ds={}
            for j,d in enumerate(lv[1]):
                if d.get('up')!=i0: continue
                ds[d['name']]=sorted({w['name'] for w in (lv[2] or []) if w.get('up')==j and w['name']!=d['name']}, key=str.casefold)
            if ds: json.dump(dict(sorted(ds.items(), key=lambda kv: kv[0].casefold())),open(f'{OUT}/{cc}/n-{jsnorm(a["name"])}.json','w'),ensure_ascii=False,separators=(',',':'))
json.dump(idx,open(f'{OUT}/index.json','w'),separators=(',',':'))
print('\n'.join(report)); print('countries', len(idx), 'with wards', sum(idx.values()))
