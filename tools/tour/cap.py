# Capture the real WDOS screens for the guided tour, in one language: the screen, and where on it to tap.
# python3 cap.py leader|envoy LANG   -> wdos-php/assets/tour/<kind>/<lang>/<id>.webp + boxes.json
import asyncio, sys, json, os, io
from PIL import Image
from playwright.async_api import async_playwright
B='http://127.0.0.1:8080'; KIND=sys.argv[1]; L=sys.argv[2]
OUT=f'/home/claude/wdos-php/assets/tour/{KIND}/{L}'; os.makedirs(OUT,exist_ok=True)
SEED=json.load(open('/home/claude/tour/seed.json'))
W,H=390,700
# (id, page, clicks before, target selector, action)   action: tap | look | none
LEADER=[('dash','',[],None,'none'),('more','',[],'[data-tour=more]','tap'),('menu','',['[data-tour=more]'],'#side a[href="#/tasks"]','tap'),
 ('brief',f"tasks/{SEED['task']}",[],'#task-docs a[href^="/api/files/"]','tap'),('work',f"tasks/{SEED['task']}",[],'#task-docs button.btn--quiet','tap'),
 ('tsubmit',f"tasks/{SEED['task']}",[],'[data-tour=task-submit]','tap'),('rtab','reporting',[],'.tabbar a[href="#/reporting"]','tap'),
 ('rdoc','reporting/r/1',[],'#report-doc button.btn--quiet','tap'),('rsubmit','reporting/r/1',[],'[data-tour=report-submit]','tap'),
 ('dmnew','inbox',[],'[data-tour=dm-new]','tap'),('dmto','inbox/new',[],'[data-tour=dm-to]','tap'),('mrask','meetings/requests',[],'[data-tour=mr-ask]','tap'),
 ('mrjoin','meetings/requests/2',[],'[data-tour=mr-join]','tap'),('mrtell','meetings/requests/2',[],'[data-tour=mr-notify]','tap'),('mrcopy','meetings/requests/2',[],'[data-tour=mr-copy]','tap'),
 ('bell','',[],'[data-tour=bell]','tap'),('lang','',[],'.top select.langpick, header select.langpick, select.langpick','tap'),('listen','',[],'button.listen','tap'),('end','',[],None,'none')]
ENVOY=[('dash','',[],None,'none'),('copy','',[],'[data-tour=envoy-copy]','tap'),('wa','',[],'[data-tour=envoy-wa]','tap'),('people','',[],'.tablewrap','look'),
 ('msg','',[],'[data-tour=envoy-msg]','tap'),('tasks','',[],'.tabbar a[href="#/tasks"]','tap'),('bell','',[],'[data-tour=bell]','tap'),('lang','',[],'select.langpick','tap'),('end','',[],None,'none')]
SC=LEADER if KIND=='leader' else ENVOY
CRED=('leader@test.local','leader-pass-1') if KIND=='leader' else ('grace.envoy@wdos-mail.org','grace-envoy-1')
async def main():
    async with async_playwright() as p:
        b=await p.chromium.launch(); errs=[]
        ctx=await b.new_context(viewport={'width':W,'height':H},device_scale_factor=2,is_mobile=True,has_touch=True,service_workers='block')
        r=await ctx.request.post(B+'/api/auth/login',data={'email':CRED[0],'password':CRED[1]},headers={'X-WDOS':'1'}); assert r.ok, await r.text()
        await ctx.add_init_script(f"localStorage.setItem('wdos.lang','{L}'); localStorage.setItem('wdos.theme','light');")
        pg=await ctx.new_page(); pg.on('pageerror',lambda e: errs.append(str(e)))
        # the first-sign-in guide must not cover the screens
        await pg.route('**/api/me', lambda route: route.continue_())
        out=[]; last=None
        for sid,path,pre,sel,act in SC:
            if path!=last or pre:
                await pg.goto(B+'/#/'+path); await pg.wait_for_timeout(2300); last=path if not pre else None
                await pg.evaluate("document.querySelectorAll('.gmview.is-guide').forEach(e=>e.remove())")
            for c in pre: await pg.click(c); await pg.wait_for_timeout(800)
            box=None; label=''
            if sel:
                el=pg.locator(sel).first
                await el.wait_for(state='attached',timeout=8000)
                fixed=await el.evaluate("e=>{let n=e;while(n){const p=getComputedStyle(n).position;if(p==='fixed'||p==='sticky')return true;n=n.parentElement}return false}")
                inside=await el.evaluate("e=>!!e.closest('#side')")
                if inside: await el.evaluate("e=>e.scrollIntoView({block:'center'})"); await pg.wait_for_timeout(500)
                if not fixed:
                    await el.evaluate("e=>e.scrollIntoView({block:'center'})"); await pg.wait_for_timeout(600)
                rc=await el.evaluate("e=>{const r=e.getBoundingClientRect();return [r.x,r.y,r.width,r.height]}")
                label=(await el.evaluate("e=>(e.innerText||e.getAttribute('aria-label')||(e.options?e.options[e.selectedIndex].text:'')||'').trim().slice(0,80)"))
                x,y,w,h=rc; x=max(0,x); y=max(0,y); w=min(W-x,w); h=min(H-y,h)
                box=[round(x/W*100,2),round(y/H*100,2),round(w/W*100,2),round(h/H*100,2)]
            else:
                await pg.evaluate("window.scrollTo(0,0)"); await pg.wait_for_timeout(300)
            png=await pg.screenshot()
            im=Image.open(io.BytesIO(png)).convert('RGB').resize((540,round(540*H/W)),Image.LANCZOS)
            im.save(f'{OUT}/{sid}.webp','WEBP',quality=72,method=6)
            out.append({'id':sid,'action':act,'box':box,'label':label})
        d=await pg.evaluate("[document.documentElement.dir, document.documentElement.scrollWidth-innerWidth]")
        json.dump(out,open(f'{OUT}/boxes.json','w'),ensure_ascii=False,indent=0)
        print(KIND,L,'dir/overflow',d,'errors',errs[:3]); [print(' ',o['id'],o['box'],o['label'][:40]) for o in out]
        await b.close()
asyncio.run(main())
