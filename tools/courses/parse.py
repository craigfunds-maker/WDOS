# Turn the uploaded course PDF text into structured JSON: 12 courses x 5 modules, quiz of 10, answer key (kept server side).
import re, json
T=open('/tmp/courses.txt').read()
T=re.sub(r'=== page \d+\nWODDI \| Volunteer Leaders - Full Personal Development Courses\n\d+\n','',T)
key={}
for m in re.finditer(r'Course (\d\d): [^\n]+\n((?:\d+: [A-D] \| ?)+\d+: [A-D])', T):
    key[int(m.group(1))]=[x.split(': ')[1] for x in m.group(2).split(' | ')]
courses=[]
parts=re.split(r'\nCOURSE (\d\d) \| ', '\n'+T.split('Facilitator answer key')[0])
i=1
while i < len(parts):
    no=int(parts[i]); body=parts[i+1]; i+=2
    if body.startswith('KNOWLEDGE AND APPLICATION QUIZ'):
        qtext=body.split('\n',2)[2]
        qs=re.split(r'\n(?=\d{1,2}\. )', '\n'+qtext)
        quiz=[]
        for q in qs:
            q=q.strip()
            if not re.match(r'\d{1,2}\. ',q): continue
            m=re.match(r'(\d{1,2})\. (.*?)\n\s*A\. (.*?)\nB\. (.*?)\nC\. (.*?)\nD\. (.*?)$', q, re.S)
            if not m: print('BADQ',no,q[:80]); continue
            clean=lambda s: re.sub(r'\s*\n\s*',' ',s).strip()
            quiz.append({'q':clean(m.group(2)),'o':[clean(m.group(k)) for k in range(3,7)]})
        courses[-1]['quiz']=quiz; continue
    title=body.split('\n',1)[0].strip()
    g=lambda a,b: re.sub(r'\s*\n\s*',' ',re.search(re.escape(a)+r'\n(.*?)\n'+re.escape(b),body,re.S).group(1)).strip()
    c={'no':no,'title':title,'purpose':g('Course purpose','The central leadership process'),'process':g('The central leadership process','Boundaries and safe practice'),'boundaries':g('Boundaries and safe practice','Learning outcomes'),'modules':[]}
    for mm in re.finditer(r'MODULE (\d+)\.(\d) \| ([^\n]+)\n(.*?)(?=\nMODULE \d+\.\d \||\nSelf-check before the quiz|\Z)', body, re.S):
        b=mm.group(4); cl=lambda s: re.sub(r'\s*\n\s*',' ',s).strip()
        def sec(a,z):
            r=re.search(re.escape(a)+r'\n(.*?)\n'+re.escape(z),b,re.S); return cl(r.group(1)) if r else ''
        ex=sec('Guided practical exercise','Complete the exercise in four parts')
        c['modules'].append({'no':int(mm.group(2)),'title':mm.group(3).strip(),'concept':sec('Understand the concept','Why this matters for a volunteer leader'),'scenario':sec('Worked WODDI leadership situation','Think it through'),'exercise':ex})
    sc=re.search(r'Self-check before the quiz\n(.*?)$',body,re.S); c['selfcheck']=re.sub(r'\s*\n\s*',' ',sc.group(1)).strip() if sc else ''
    courses.append(c)
for c in courses: c['key']=key[c['no']]
json.dump(courses,open('courses_en_full.json','w'),ensure_ascii=False,indent=1)
print(len(courses),[len(c['modules']) for c in courses],[len(c.get('quiz',[])) for c in courses])
print(json.dumps(courses[0]['modules'][0],ensure_ascii=False)[:700]); print(courses[0]['selfcheck'][:200])
