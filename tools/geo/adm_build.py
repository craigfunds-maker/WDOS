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
# v109.21: wards and chapters where the official boundaries stop
#  - GeoNames (CC BY 4.0) populated places: towns, villages and neighbourhoods inside a district become its wards
#    when there is no official ward list for it
#  - OpenStreetMap (ODbL) polling stations become chapters under the nearest of those wards
import gzip, glob
from shapely.geometry import Point
GN='/home/claude/pu/gn/places.tsv.gz'; OSM=glob.glob('/home/claude/pu/src/OSM/polling_stations*.tsv')
PL={}      # cc -> [(name, Point, population)]
if os.path.exists(GN):
    with gzip.open(GN,'rt',encoding='utf-8') as f:
        for ln in f:
            a=ln.rstrip('\n').split('\t')
            if len(a)<9 or not a[3].startswith('PPL'): continue
            try: PL.setdefault(a[4],[]).append((a[0],Point(float(a[2]),float(a[1])),int(a[8] or 0),a[5],a[6]))
            except ValueError: pass
PS=[]; seen=set()
for fn in OSM:
    for ln in open(fn,encoding='utf-8',errors='ignore'):
        a=ln.rstrip('\n').split('\t')
        if len(a)<3 or not a[2].strip(): continue
        try: k=(round(float(a[0]),4),round(float(a[1]),4))
        except ValueError: continue
        if k in seen: continue
        seen.add(k); PS.append((a[2].strip()[:120],Point(float(a[1]),float(a[0]))))
PS_TREE=STRtree([p for _,p in PS]) if PS else None
print('GeoNames countries', len(PL), 'OSM polling stations', len(PS))
CAP=300
def places_by_district(cc, dist):
    # GeoNames places of this country, grouped by the district polygon that contains them, biggest first
    pts=PL.get(cc) or []
    if not pts or not dist: return {}
    tree=STRtree([d['geo'] for d in dist]); out={}
    for name,pt,pop,*_ in pts:
        for i in tree.query(pt):
            if dist[i]['geo'].contains(pt): out.setdefault(int(i),[]).append((pop,name,pt)); break
    for i in out: out[i].sort(key=lambda x:(-x[0],x[1]))
    return out
def stations_by_district(dist):
    if not PS_TREE or not dist: return {}
    out={}
    for j,d in enumerate(dist):
        for i in PS_TREE.query(d['geo'],predicate='contains'): out.setdefault(j,[]).append(PS[int(i)])
    return out
def slug(x): return re.sub(r'[^a-z0-9]+','-',x.lower()).strip('-')
idx={}; report=[]; chap_total={}
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
    gnp=places_by_district(cc,dist); pss=stations_by_district(dist)
    tree={}; chaps={}
    for j,d in enumerate(dist):
        s=m.get(d.get('up'))
        if not s: continue
        off=[w for w in ward if w.get('up')==j and w['name']!=d['name']]
        if off: wpts=[(w['name'],w['pt']) for w in off]
        else: wpts=[(n,pt) for _,n,pt in (gnp.get(j) or [])[:CAP] if n!=d['name']]
        names={n for n,_ in wpts}
        tree.setdefault(s[1],{}).setdefault(d['name'],set()).update(names)
        if wpts and pss.get(j):
            wt=STRtree([pt for _,pt in wpts])
            for sn,spt in pss[j]:
                w=wpts[int(wt.nearest(spt))][0]
                chaps.setdefault(s[1],{}).setdefault(d['name'],{}).setdefault(w,set()).add(sn)
    a1=top
    if not tree: report.append(f'{cc}: no state matched'); continue
    os.makedirs(f'{OUT}/{cc}',exist_ok=True)
    nd=nw=0
    for code,ds in tree.items():
        data={k:sorted(v,key=str.casefold) for k,v in sorted(ds.items(), key=lambda kv: kv[0].casefold())}
        nd+=len(data); nw+=sum(len(v) for v in data.values())
        json.dump(data,open(f'{OUT}/{cc}/{code}.json','w'),ensure_ascii=False,separators=(',',':'))
    nc=0
    for code,ds in chaps.items():
        os.makedirs(f'{OUT}/{cc}/c-{code}',exist_ok=True)
        for dn,ws in ds.items():
            data={w:sorted(v,key=str.casefold) for w,v in sorted(ws.items(), key=lambda kv: kv[0].casefold())}; nc+=sum(len(v) for v in data.values())
            json.dump(data,open(f'{OUT}/{cc}/c-{code}/{slug(dn)}.json','w'),ensure_ascii=False,separators=(',',':'))
    if nc: chap_total[cc]=nc
    idx[cc]=(2 if nc else 1) if nw else 0     # 2: wards and chapters, 1: wards, 0: districts only
    report.append(f'{cc}: states {len(tree)}/{len(states)} (WDOS states = ADM{k+1}) districts {nd} wards {nw} chapters {nc}')
    # by name: every official level-1 area (regions in Malawi, Uganda, Madagascar...) with its level-2 and level-3 areas
    if lv[0] and lv[1]:
        parent(lv[1],lv[0]); parent(lv[2],lv[1]) if lv[2] else None
        gn1=places_by_district(cc,lv[1]) if not lv[2] else {}
        for i0,a in enumerate(lv[0]):
            ds={}
            for j,d in enumerate(lv[1]):
                if d.get('up')!=i0: continue
                ws={w['name'] for w in (lv[2] or []) if w.get('up')==j and w['name']!=d['name']} or {n for _,n,_ in (gn1.get(j) or [])[:CAP] if n!=d['name']}
                ds[d['name']]=sorted(ws, key=str.casefold)
            if ds: json.dump(dict(sorted(ds.items(), key=lambda kv: kv[0].casefold())),open(f'{OUT}/{cc}/n-{jsnorm(a["name"])}.json','w'),ensure_ascii=False,separators=(',',':'))
A1={}; A2={}
if os.path.exists('/home/claude/pu/gn/admin1CodesASCII.txt'):
    for ln in open('/home/claude/pu/gn/admin1CodesASCII.txt',encoding='utf-8'):
        a=ln.rstrip('\n').split('\t'); c,_,k1=a[0].partition('.'); A1.setdefault(c,{})[k1]=(a[1],a[2])
    for ln in open('/home/claude/pu/gn/admin2Codes.txt',encoding='utf-8'):
        a=ln.rstrip('\n').split('\t'); c,k1,k2=(a[0].split('.')+['',''])[:3]; A2.setdefault(c,{})[(k1,k2)]=a[1]
EL=set(json.load(open('/home/claude/wdos-php/assets/geo/el/index.json'))) if os.path.exists('/home/claude/wdos-php/assets/geo/el/index.json') else set()
for cc in sorted(A1):
    if cc in idx or cc in SKIP or cc in EL or not os.path.exists(f'{W}/{cc}.json'): continue
    states=json.load(open(f'{W}/{cc}.json')).get('s') or []
    if not states: continue
    byname={norm(st[0]):st for st in states}; m1={}
    for k1,(n1,asc) in A1[cc].items():
        st=byname.get(norm(n1)) or byname.get(norm(asc))
        if not st:
            b=difflib.get_close_matches(norm(asc), list(byname), n=1, cutoff=0.8); st=byname[b[0]] if b else None
        if st: m1[k1]=st
    if len(set(x[1] for x in m1.values())) < 0.5*len(states):
        # WDOS's states may be GeoNames' second level (Ireland's counties, Morocco's provinces): then the towns and
        # villages of each become its districts
        m2={}
        for (k1,k2),n2 in (A2.get(cc) or {}).items():
            st=byname.get(norm(n2))
            if not st:
                b=difflib.get_close_matches(norm(n2), list(byname), n=1, cutoff=0.85); st=byname[b[0]] if b else None
            if st: m2[(k1,k2)]=st
        if len(set(x[1] for x in m2.values())) < 0.5*len(states): report.append(f'{cc}: GeoNames skipped (states matched {len(m1)}/{len(states)})'); continue
        tree={}
        for name,pt,pop,k1,k2 in sorted(PL.get(cc) or [], key=lambda x:(-x[2],x[0])):
            st=m2.get((k1,k2))
            if not st: continue
            ds=tree.setdefault(st[1],{})
            if len(ds)<CAP and name!=st[0]: ds.setdefault(name,[])
        if tree:
            os.makedirs(f'{OUT}/{cc}',exist_ok=True)
            for code,ds in tree.items(): json.dump(dict(sorted(ds.items(), key=lambda kv: kv[0].casefold())),open(f'{OUT}/{cc}/{code}.json','w'),ensure_ascii=False,separators=(',',':'))
            idx[cc]=0; report.append(f'{cc}: GeoNames level 2 = states, towns as districts: {len(tree)}/{len(states)} states')
        continue
    tree={}
    pts=sorted(PL.get(cc) or [], key=lambda x:(-x[2],x[0]))
    for (k1,k2),n2 in (A2.get(cc) or {}).items():
        if k1 in m1: tree.setdefault(m1[k1][1],{}).setdefault(n2,[])
    for name,pt,pop,k1,k2 in pts:
        if k1 not in m1: continue
        n2=(A2.get(cc) or {}).get((k1,k2))
        if not n2: continue
        lst=tree.setdefault(m1[k1][1],{}).setdefault(n2,[])
        if len(lst)<CAP and name!=n2 and name not in lst: lst.append(name)
    if not tree: continue
    os.makedirs(f'{OUT}/{cc}',exist_ok=True); nd=nw=0
    for code,ds in tree.items():
        data={k:sorted(v,key=str.casefold) for k,v in sorted(ds.items(), key=lambda kv: kv[0].casefold())}; nd+=len(data); nw+=sum(len(v) for v in data.values())
        json.dump(data,open(f'{OUT}/{cc}/{code}.json','w'),ensure_ascii=False,separators=(',',':'))
    idx[cc]=1 if nw else 0
    report.append(f'{cc}: GeoNames states {len(tree)}/{len(states)} districts {nd} wards {nw}')
json.dump(idx,open(f'{OUT}/index.json','w'),separators=(',',':'))
json.dump(chap_total,open(f'{OUT}/chapters.json','w'),separators=(',',':'))
print('\n'.join(report)); print('countries', len(idx), 'with wards', sum(1 for v in idx.values() if v), 'with chapters', len(chap_total), sum(chap_total.values()))
