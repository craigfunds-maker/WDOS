import pdfplumber, json, re
rows=set()
with pdfplumber.open('src/SN/Carte_electorale_2024.pdf') as p:
    for pg in p.pages:
        t=pg.extract_text() or ''; m=re.search(r'Région\s*:\s*(.+?)\s+Département\s*:\s*(.+?)\s+Commune\s*:\s*(.+)',t)
        if not m: continue
        reg,dep,com=[x.strip().title() for x in m.groups()]
        for ln in t.split('\n'):
            mm=re.match(r'^(.+?)\s+(\d{2,3})\s+(\d{1,4})(\s|$)',ln)
            if mm and not ln.startswith(('Lieu de vote','REPUBLIQUE','MINISTERE','Région')): rows.add((reg,dep,com,mm[1].strip()))
rows=[dict(region=a,district=b,ward=c,site=d) for a,b,c,d in sorted(rows)]
print(len(rows)); print(rows[:2]); print(sorted({r['region'] for r in rows}))
json.dump(rows,open('sn_rows.json','w'))
