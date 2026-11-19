"""v109.19: official polling-station lists as the Chapter level of the joining form, country by country.
Input: rows extracted from each electoral commission's published list (see tools/geo/extract/*.py), each with
region (state), district (LGA or district), ward (community cluster) and site (the polling station or centre).
Output, the same layout as Kenya (IEBC) and Nigeria (INEC):
  assets/geo/el/<CC>/index.json                     {state: [district, ...]}
  assets/geo/el/<CC>/<state-slug>/<district-slug>.json  {ward: [chapter, ...]}
and the country code is added to assets/geo/el/index.json. The states are the commission's own regions."""
import json, os, re, sys
EL = '/home/claude/wdos-php/assets/geo/el'; PU = '/home/claude/pu'
def slug(s): return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')   # same as geoSlug in js/geoeq.js
def tc(s):
    s = re.sub(r'\s+', ' ', str(s).strip()).title()
    s = re.sub(r"'S\b", "'s", s)
    small = {'Of','The','And','De','Da','Do','Dos','Das','E','La','Le','Du','Des','Et','En','Na','Wa','Ya','Al','El'}
    w = s.split(' ')
    return ' '.join(x.lower() if i and x in small else x for i, x in enumerate(w))
SOURCES = {   # country: (rows file, how a chapter is written, source note)
  'GH': ('gh_rows.json', lambda r: f"{r['code']} {tc(r['name'])}", 'Electoral Commission of Ghana, List of Polling Stations, 2024 General Elections'),
  'ZM': ('zm_rows.json', lambda r: f"{r['site']} ({r['pd']})", 'Electoral Commission of Zambia, Registered Voters per Polling Station, 2026'),
  'SN': ('sn_rows.json', lambda r: tc(r['site']), 'Ministère de l\'Intérieur (DGE), Implantation des bureaux de vote, 17 novembre 2024'),
  'MG': ('mg_rows.json', lambda r: f"{tc(r['site'])} ({r['fkt']})" if r.get('fkt') else tc(r['site']), 'CENI Madagascar, Liste et emplacement des bureaux de vote, présidentielle 2023'),
  'ZA': ('za_rows.json', lambda r: r['site'], 'Electoral Commission of South Africa (IEC), Voting Station Listing, 2026 Local Government Elections'),
  'AU': ('au_rows.json', lambda r: r['site'], 'Australian Electoral Commission, 2025 Federal Election polling places (election day)'),
  'BR': ('br_rows.json', lambda r: r['site'], 'Tribunal Superior Eleitoral, Eleitorado por local de votação 2026 (CC BY)'),
  'US': ('us_rows.json', lambda r: r['site'], 'Center for Public Integrity, us-polling-places (state and county election offices), 2018-2020'),
  'FR': ('fr_rows.json', lambda r: r['site'], 'Ministère de l\'Intérieur et INSEE, Bureaux de vote et adresses de leurs électeurs (REU), data.gouv.fr, Licence Ouverte 2.0'),
  'CI': ('ci_rows.json', lambda r: tc(r['site']), 'CEI Côte d\'Ivoire, Liste des lieux et bureaux de vote, présidentielle du 25 octobre 2025'),
}
def fields(cc, r):
    if cc == 'GH': return tc(r['region']), tc(r['district']), tc(r['const'])
    if cc == 'FR': return r['region'], r['district'], r['ward']      # already correctly written
    return tc(r['region']), tc(r['district']), tc(r['ward'])
def full_names(cc, tree):
    # some PDFs cut long district names short ("Ablekuma Central Munici"): complete them from the official district
    # list of the same region (geoBoundaries), when exactly one name there starts the same way
    W='/home/claude/wdos-php/assets/geo'; ws=json.load(open(f'{W}/world/{cc}.json')).get('s') or []
    k=lambda x: re.sub(r'[^a-z0-9]','',x.lower())
    code={k(n):c for n,c,*_ in ws}; fixed=0
    for st in list(tree):
        f=f'{W}/adm/{cc}/{code.get(k(st),"")}.json'
        if not os.path.exists(f): continue
        full=list(json.load(open(f)))
        for di in list(tree[st]):
            if any(k(x)==k(di) for x in full): continue
            c=[x for x in full if k(x).startswith(k(di))]
            if len(c)==1: tree[st].setdefault(c[0],{}).update(tree[st].pop(di)); fixed+=1
    return fixed
def build(cc):
    f, chap, note = SOURCES[cc]; rows = json.load(open(f'{PU}/{f}'))
    tree = {}
    for r in rows:
        st, di, wa = fields(cc, r)
        if not st or not di or not wa or st.lower() == 'diaspora': continue
        tree.setdefault(st, {}).setdefault(di, {}).setdefault(wa, set()).add(chap(r))
    if cc=='GH': print('  district names completed:', full_names(cc, tree))
    out = f'{EL}/{cc}'
    if os.path.isdir(out):
        for root, dirs, files in os.walk(out, topdown=False):
            for x in files: os.remove(os.path.join(root, x))
            for x in dirs: os.rmdir(os.path.join(root, x))
    os.makedirs(out, exist_ok=True)
    idx = {}; n = 0
    for st, ds in sorted(tree.items()):
        idx[st] = sorted(ds, key=str.casefold); os.makedirs(f'{out}/{slug(st)}', exist_ok=True)
        for di, ws in ds.items():
            data = {w: sorted(c, key=str.casefold) for w, c in sorted(ws.items(), key=lambda kv: kv[0].casefold())}
            n += sum(len(v) for v in data.values())
            json.dump(data, open(f'{out}/{slug(st)}/{slug(di)}.json', 'w'), ensure_ascii=False, separators=(',', ':'))
    json.dump(idx, open(f'{out}/index.json', 'w'), ensure_ascii=False, separators=(',', ':'))
    json.dump({'source': note, 'states': len(idx), 'districts': sum(len(v) for v in idx.values()), 'chapters': n}, open(f'{out}/source.json', 'w'), ensure_ascii=False)
    print(cc, len(idx), 'states', sum(len(v) for v in idx.values()), 'districts', n, 'chapters')
    return cc
if __name__ == '__main__':
    done = [build(cc) for cc in (sys.argv[1:] or SOURCES) if os.path.exists(f'{PU}/{SOURCES[cc][0]}')]
    idx = json.load(open(f'{EL}/index.json')); idx = sorted(set(idx) | set(done)); json.dump(idx, open(f'{EL}/index.json', 'w'))
    print('el countries', idx)
