import pdfplumber, json, re, collections
rows=set(); B=[65,138,215,300,440,570,10000]
with pdfplumber.open('src/MG/LISTE-ET-EMPLACEMENT-DES-BV-2.pdf') as p:
    for pg in p.pages:
        lines=collections.defaultdict(list)
        for w in pg.extract_words(): lines[round(w['top'])].append(w)
        for top in sorted(lines):
            L=sorted(lines[top],key=lambda w:w['x0'])
            if not L or not re.match(r'^\d+$',L[0]['text']) or not any(re.match(r'^\d{12}',w['text']) for w in L): continue
            col=lambda i:' '.join(w['text'] for w in L if B[i]<=w['x0']<B[i+1]).strip()
            reg,dis,com,fkt,cen=[col(i) for i in range(5)]
            if reg and dis and com and cen: rows.add((reg.title(),dis.title(),com.title(),fkt.title(),cen))
rows=[dict(region=a,district=b,ward=c,fkt=d,site=e) for a,b,c,d,e in sorted(rows)]
print(len(rows)); print(rows[:2]); print(collections.Counter(r['region'] for r in rows))
json.dump(rows,open('mg_rows.json','w'))
