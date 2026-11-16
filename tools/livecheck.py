# Plays the live guide on a real account, step by step, and reports what it pointed at. python3 livecheck.py kind lang [width]
import asyncio, sys, json
from playwright.async_api import async_playwright
B='http://127.0.0.1:8080'; KIND=sys.argv[1]; L=sys.argv[2]; W=int(sys.argv[3]) if len(sys.argv)>3 else 390
CRED={'leader':('leader@test.local','leader-pass-1'),'envoy':('grace.envoy@wdos-mail.org','grace-envoy-1'),'member':('halima.tour@wdos-mail.org','member-pass-9')}[KIND]
async def main():
    async with async_playwright() as p:
        b=await p.chromium.launch(); errs=[]
        ctx=await b.new_context(viewport={'width':W,'height':760},is_mobile=W<600,has_touch=W<600,service_workers='block',timezone_id='Africa/Lagos')
        r=await ctx.request.post(B+'/api/auth/login',data={'email':CRED[0],'password':CRED[1]},headers={'X-WDOS':'1'}); assert r.ok
        await ctx.add_init_script(f"localStorage.setItem('wdos.lang','{L}')")
        pg=await ctx.new_page(); pg.on('pageerror',lambda e: errs.append(str(e)))
        await pg.goto(B+'/#/guide'); await pg.wait_for_timeout(2500)
        await pg.evaluate("document.querySelectorAll('.ltour').forEach(e=>e.remove())")
        await pg.evaluate("import('/js/pages/guide.js').then(m=>m.startTour({}))"); await pg.wait_for_timeout(1500)
        n=await pg.evaluate("parseInt(document.querySelector('.ltour .tour__count').textContent.match(/\\d+\\D+(\\d+)/)[1])")
        out=[]
        for k in range(n):
            if k: await pg.click('.ltour .tour__nav--next'); await pg.wait_for_timeout(4500)
            info=await pg.evaluate("""()=>{const r=document.querySelector('.ltour__ring');const s=r.style.display!=='none'&&r.style.display!=='';
              const note=document.querySelector('.ltour__body .tour__note');return {hash:location.hash,ring:s,note:!!note,text:document.querySelector('.ltour__body').innerText.slice(0,60)}}""")
            out.append(info)
            if k in (0,2,4,7,13,15): await pg.screenshot(path=f'/tmp/lt_{KIND}_{L}_{W}_{k}.png')
        print(KIND,L,W,'errors',errs[:3])
        for k,o in enumerate(out): print(f" {k:2d} ring={'Y' if o['ring'] else '-'} note={'Y' if o['note'] else '-'} {o['hash']:28s} {o['text']}")
        await b.close()
asyncio.run(main())
