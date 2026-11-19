import pdfplumber, json, re, collections, sys
A,Z=int(sys.argv[1]),int(sys.argv[2]); rows=[]
with pdfplumber.open('src/ZA/VotingStationListing_2026_AllVS.pdf') as p:
    for pg in p.pages[A:Z]:
        lines=collections.defaultdict(list)
        for w in pg.extract_words():
            if w['top']>80: lines[round(w['top'])].append(w)
        for top in sorted(lines):
            L=sorted(lines[top],key=lambda w:w['x0'])
            col=lambda a,b:' '.join(w['text'] for w in L if a<=w['x0']<b).strip()
            prov,muni,ward,rest=col(0,90),col(90,192),col(192,240),col(240,440)
            m=re.match(r'^(\d{8})(.*)$',rest)
            if prov and re.match(r'^\d{8}$',ward) and m:
                rows.append(dict(region=prov,district=re.sub(r'^[A-Z0-9]+ - ','',muni),ward=ward,site=m[2].strip(),vd=m[1]))
print(A,len(rows)); json.dump(rows,open(f'za_rows_{A}.json','w'))
