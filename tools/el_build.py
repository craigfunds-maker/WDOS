"""Electoral geography for the joining form, Nigeria-style: state -> LGA-equivalent -> ward-equivalent (community cluster)
-> polling station (Booth). Writes assets/geo/el/<CC>/index.json ({state: [lga, ...]}) and
assets/geo/el/<CC>/<state-slug>/<lga-slug>.json ({ward: [station, ...]})."""
import json, os, re, struct, sys, unicodedata
W = '/home/claude/wdos-php/assets/geo'
def slug(s): return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')   # same as geoSlug in js/geoeq.js
def tc(s):
    s = re.sub(r'\s+', ' ', s.strip()).title()
    return re.sub(r"'S\b", "'s", s)
def norm(s): return re.sub(r'[^a-z]', '', unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower())
def dbf(path):
    f = open(path, 'rb'); h = f.read(32); n, hl, rl = struct.unpack('<IHH', h[4:12]); fields = []
    while True:
        d = f.read(32)
        if d[0] == 0x0d: break
        fields.append((d[:11].split(b'\0')[0].decode(), d[16]))
    f.seek(hl)
    for _ in range(n):
        r = f.read(rl); o = 1; row = {}
        for nm, l in fields: row[nm] = r[o:o + l].decode('latin1').strip(); o += l
        yield row
def write(cc, tree):
    states = json.load(open(f'{W}/states.json'))[cc]; idx = {}; byn = {norm(s): s for s in states}
    for st, lgas in sorted(tree.items()):
        name = byn.get(norm(st))
        if not name:
            cand = [s for k, s in byn.items() if norm(st)[:5] == k[:5]]
            name = cand[0] if len(cand) == 1 else None
        if not name: print('no state match', cc, st); continue
        idx[name] = sorted(lgas)
        for lga, wards in lgas.items():
            p = f'{W}/el/{cc}/{slug(name)}'; os.makedirs(p, exist_ok=True)
            json.dump({w: sorted(set(b)) for w, b in sorted(wards.items())}, open(f'{p}/{slug(lga)}.json', 'w'), ensure_ascii=False, separators=(',', ':'))
    json.dump(idx, open(f'{W}/el/{cc}/index.json', 'w'), ensure_ascii=False, separators=(',', ':'))
    print(cc, len(idx), 'states', sum(len(v) for v in idx.values()), 'lga-level', sum(len(w) for l in tree.values() for w in l.values()), 'wards',
          sum(len(b) for l in tree.values() for w in l.values() for b in w.values()), 'stations')
if 'KE' in sys.argv:
    t = {}
    for r in dbf('/tmp/geo_dl/kenya-election-data/output/pollingstations.dbf'):
        if not (r['county'] and r['constituen'] and r['ward'] and r['name']): continue
        t.setdefault(tc(r['county']), {}).setdefault(tc(r['constituen']), {}).setdefault(tc(r['ward']), []).append(tc(r['name']))
    write('KE', t)
