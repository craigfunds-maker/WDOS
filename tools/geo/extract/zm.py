import pdfplumber, json, re
rows=[]; prov=dist=ward=None
with pdfplumber.open('src/ZM/rptPDListing20260508.pdf') as p:
    for pg in p.pages:
        for ln in (pg.extract_text() or '').split('\n'):
            m=re.match(r'^Province:\s*\d+\s+(.+?)\s+\d+$',ln) or re.match(r'^Province:\s*\d+\s+(.+)$',ln)
            if m: prov=m[1].strip().title(); continue
            m=re.match(r'^District\s+\d+\s+(.+)$',ln)
            if m: dist=m[1].strip().title(); continue
            m=re.match(r'^Ward\s*:\s*\d+\s*,\s*(.+)$',ln)
            if m: ward=m[1].strip().title(); continue
            m=re.match(r'^(\d{12})\s+(.+?)\s+-\s+\d+\s+(.+?)-\d+\s+[\d,]+\s+[\d,]+\s+[\d.]+\s+[\d.]+\s+[\d,]+$',ln)
            if m and prov and dist and ward:
                rows.append({'region':prov,'district':dist,'ward':ward,'pd':m[2].strip().title(),'site':m[3].strip()})
print(len(rows)); print(rows[:2]); print(sorted({r['region'] for r in rows}))
json.dump(rows,open('zm_rows.json','w'))
