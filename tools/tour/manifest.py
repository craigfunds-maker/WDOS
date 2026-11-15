# Join the captured boxes with the words: assets/tour/<kind>/<lang>/tour.json
import json, sys
sys.path.insert(0,'/home/claude/tour'); from texts import GREET, LEADER, ENVOY
R='/home/claude/wdos-php/assets/tour/'
INTRO={'en':'This short guide shows you, step by step, exactly what to tap in WDOS.'}
for kind,lines in (('leader',LEADER),('envoy',ENVOY)):
    for L in ('en','fr','pt','sw','ar'):
        bx={b['id']:b for b in json.load(open(f'{R}{kind}/{L}/boxes.json'))}
        sc=[{'id':i,'box':bx[i]['box'],'action':bx[i]['action'],'text':t[L]} for i,t in lines]
        g=GREET['glad'][L]; cut=g.rfind('. ',0,len(g)-2)
        out={'hello':GREET['hello'][L],'glad':g[:cut+1],'intro':g[cut+2:],'scenes':sc}
        json.dump(out,open(f'{R}{kind}/{L}/tour.json','w'),ensure_ascii=False,separators=(',',':'))
        print(kind,L,len(sc),out['intro'][:50])
