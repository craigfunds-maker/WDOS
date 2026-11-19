import pdfplumber, json, re, collections
rows=[]
with pdfplumber.open('src/GH/Polling_stations.pdf') as p:
    for pg in p.pages:
        ws=pg.extract_words(keep_blank_chars=False)
        hdr={w['text']:w['x0'] for w in ws if w['text'] in ('Constituency','District','Region') and w['top']<130}
        if len(hdr)<3: continue
        xc,xd,xr=hdr['Constituency']-3,hdr['District']-3,hdr['Region']-3
        lines=collections.defaultdict(list)
        for w in ws:
            if w['top']<125 or w['top']>pg.height-60: continue
            lines[round(w['top'])].append(w)
        cur=None
        for top in sorted(lines):
            L=sorted(lines[top],key=lambda w:w['x0'])
            code=[w for w in L if re.match(r'^[A-Z]\d{6}[A-Z]?$',w['text']) and w['x0']<185]
            col=lambda a,b:' '.join(w['text'] for w in L if a<=w['x0']<b)
            parts=dict(name=col(185,xc),const=col(xc,xd),district=col(xd,xr),region=col(xr,9999))
            if code:
                cur=dict(code=code[0]['text'],**parts); rows.append(cur)
            elif cur:   # a wrapped line continues the row above
                for k,v in parts.items():
                    if v: cur[k]=(cur[k]+' '+v).strip()
print(len(rows)); print(rows[:2]); print(collections.Counter(r['region'] for r in rows).most_common(20))
json.dump(rows,open('gh_rows.json','w'))
