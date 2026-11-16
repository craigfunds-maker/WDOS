# The words of the live guide: assets/tour/<kind>/<lang>/tour.json. The guide runs on each person's own pages,
# so there are no screenshots any more; this file holds only the lines, and whether each step is a tap or a look.
import json, sys, os, glob
sys.path.insert(0,'/home/claude/tour'); from texts import GREET, LEADER, ENVOY, MEMBER
R='/home/claude/wdos-php/assets/tour/'
LOOK={'dash','end','people','mrminutes'}
for kind,lines in (('leader',LEADER),('envoy',ENVOY),('member',MEMBER)):
    for L in ('en','fr','pt','sw','ar'):
        d=f'{R}{kind}/{L}'; os.makedirs(d,exist_ok=True)
        for f in glob.glob(d+'/*.webp')+glob.glob(d+'/boxes.json'): os.remove(f)    # old screenshots of a sample account
        sc=[{'id':i,'action':'none' if i in ('dash','end') else 'look' if i in LOOK else 'tap','text':t[L]} for i,t in lines]
        g=GREET['glad'][L]; cut=g.rfind('. ',0,len(g)-2)
        out={'hello':GREET['hello'][L],'glad':g[:cut+1],'intro':g[cut+2:],'scenes':sc}
        json.dump(out,open(f'{d}/tour.json','w'),ensure_ascii=False,separators=(',',':'))
        print(kind,L,len(sc),out['intro'][:60])
