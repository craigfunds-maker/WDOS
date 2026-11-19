import asyncio, sys
from playwright.async_api import async_playwright
B='http://localhost:8080'; L=sys.argv[1]; O=f'/home/claude/vid2/{L}-s'
async def main():
    async with async_playwright() as p:
        br = await p.chromium.launch(executable_path='/usr/bin/google-chrome', headless=True, args=['--no-sandbox','--autoplay-policy=no-user-gesture-required']); errs=[]
        ctx = await br.new_context(viewport={'width':390,'height':700}, device_scale_factor=2, is_mobile=True, has_touch=True); pg = await ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e))); pg.on('request', lambda r: reqs.append(r.url))
        vis=lambda: pg.evaluate("({lang:!!document.querySelector('.startcard')?.offsetParent, net:!!document.querySelector('.stage')?.offsetParent, form:!!document.querySelector('.jform, form')?.offsetParent, y:scrollY, dash:/Open my dashboard/.test(document.body.innerText), w:document.documentElement.scrollWidth})")
        shot=lambda n: pg.screenshot(path=f'{O}{n}.png')
        await pg.goto(B+'/'); await pg.wait_for_selector('.startcard'); await pg.wait_for_timeout(600)
        if L!='en': await pg.select_option('#lang-start', L); await pg.wait_for_timeout(1500); await pg.wait_for_selector('.startcard')
        print('1 language screen:', await vis()); await shot(1)
        # Listen on screen 1 plays the recorded clip in this language
        await pg.click('.startcard .listen'); await pg.wait_for_timeout(1200)
        print('  listen pressed:', await pg.get_attribute('.startcard .listen','aria-pressed'), '| audio requested:', [r for r in reqs if 'audio/guide' in r][-1:] )
        await pg.click('#start-continue'); await pg.wait_for_timeout(500); print('2 network screen:', await vis(), '| listen stopped:', await pg.get_attribute('.startcard .listen','aria-pressed'))
        await pg.click('#net-continue'); await pg.wait_for_timeout(300); print('  continue with no network chosen ->', (await pg.inner_text('.stage .err'))[:60], '| still on network:', (await vis())['net'])
        await pg.click('.net--wgmn'); await pg.wait_for_timeout(500); print('  after touching WGMN, form shown?', (await vis())['form']); await pg.evaluate("window.scrollTo(0,0)"); await pg.locator('.nets').scroll_into_view_if_needed(); await pg.evaluate("window.scrollBy(0,-90)"); await pg.wait_for_timeout(400); await shot(2)
        await pg.click('#net-continue'); await pg.wait_for_selector('.stepnav'); await pg.wait_for_timeout(600); print('3 details screen:', await vis(), '| step label:', await pg.inner_text('.stephead .eyebrow'))
        await pg.fill('#f-first_name','Amina'); await pg.fill('#f-last_name','Bello'); await pg.fill('#f-dob','1988-05-06'); await pg.click('.stepnav button[type=button]:not(.btn--ghost)'); await pg.wait_for_timeout(300); print('  Continue with no email or phone ->', (await pg.inner_text('.stepnav ~ .err, .jform .err'))[:70] if await pg.query_selector('.jform .err') else '', '| still on details:', await pg.is_visible('#f-email'))
        await pg.fill('#f-email', f'amina.{L}@example.org'); await pg.click('.stepnav button[type=button]:not(.btn--ghost)'); await pg.wait_for_timeout(300); print('  Continue with email but no phone -> still on details:', await pg.is_visible('#f-contact'), '| focus on phone:', await pg.evaluate("document.activeElement.id"))
        await pg.fill('#f-contact','+2348012345678'); await pg.wait_for_timeout(300); print('  message gone once she types:', not await pg.query_selector('.jform .err:not(:empty)')); await shot(3)
        await pg.click('.stepnav .btn--ghost'); await pg.wait_for_timeout(400); print('  Back from details ->', await vis()); await pg.click('#net-continue'); await pg.wait_for_timeout(500); print('  forward again keeps what she typed:', await pg.input_value('#f-first_name'))
        await pg.click('.stepnav button[type=button]:not(.btn--ghost)'); await pg.wait_for_timeout(500)
        v=await pg.eval_on_selector('#f-country','e=>[...e.options].find(o=>/Nig[eé]ria|نيجيريا/.test(o.textContent)).value'); await pg.select_option('#f-country',v); await pg.wait_for_timeout(900)
        st=await pg.eval_on_selector('#f-state','e=>[...e.options].find(o=>/Federal/.test(o.textContent)).value'); await pg.select_option('#f-state',st); await pg.wait_for_timeout(1200); await pg.select_option('select#f-lga', label='Abuja Municipal (AMAC)'); await pg.wait_for_timeout(1200); await pg.select_option('#f-commsel','Garki'); await pg.wait_for_timeout(600); await pg.select_option('#f-boothsel', index=1); await pg.wait_for_timeout(300); await pg.evaluate("window.scrollTo(0,0)"); await pg.wait_for_timeout(300); await shot(4)
        await pg.evaluate("(()=>{const e=document.querySelector('label[for=f-country]'); window.scrollTo(0, e.getBoundingClientRect().top+scrollY-20)})()"); await pg.wait_for_timeout(400); await pg.screenshot(path=f'{O}4b.png')
        await pg.click('.stepnav button[type=button]:not(.btn--ghost)'); await pg.wait_for_timeout(600); await pg.check('#f-consent'); print('5 volunteering choice shown:', await pg.is_visible('#f-vol'), '| role list hidden at first:', not await pg.is_visible('#f-volrole'))
        await pg.check('#f-vol input[value=lead]'); await pg.wait_for_timeout(300); await pg.fill('#f-password','864213'); await pg.click('.stepnav button[type=submit]'); await pg.wait_for_timeout(400); print('  leadership chosen with no role -> refused, focus:', await pg.evaluate("document.activeElement.id"), '| roles offered:', await pg.eval_on_selector('#f-volrole','e=>e.options.length-1'))
        await pg.select_option('#f-volrole', index=1); await pg.fill('#f-password','864213'); await pg.locator('#f-vol').scroll_into_view_if_needed(); await pg.evaluate("window.scrollBy(0,-80)"); await pg.locator('#f-password').scroll_into_view_if_needed(); await pg.evaluate("window.scrollBy(0,120)"); await pg.wait_for_timeout(300); await shot(5)
        await pg.click('.stepnav button[type=submit]'); await pg.wait_for_timeout(4000); await pg.evaluate("document.querySelectorAll('.gmview.is-guide, .modal, .vmodal').forEach(e=>e.remove()); location.hash='#/journey'"); await pg.wait_for_selector('.jdays', timeout=15000); await pg.wait_for_timeout(2500); await pg.evaluate("document.querySelectorAll('.ltour').forEach(e=>e.remove())"); await pg.wait_for_timeout(400); await pg.evaluate("window.scrollTo(0,0)"); await shot(6)
        me=await pg.evaluate("fetch('/api/me',{headers:{'X-WDOS':'1'}}).then(r=>r.json()).then(j=>[j.user.email,j.user.phone,j.user.volunteer,j.user.volunteer_interest_label])"); print('  account:', me)
        await pg.evaluate("location.hash='#/home'"); await pg.wait_for_timeout(1500); print('  home card:', await pg.is_visible('#vol-choice'), '| value', await pg.evaluate("document.getElementById('vol-choice').value"), await pg.input_value('#vol-role'), '| sideways overflow', await pg.evaluate("document.documentElement.scrollWidth-innerWidth")); await pg.locator('#vol-choice').scroll_into_view_if_needed(); await pg.screenshot(path=f'{O}8.png')
        await pg.evaluate("location.hash='#/help'"); await pg.wait_for_timeout(1800); await shot(7)
        await pg.evaluate("import('/js/app.js').then(m=>m.signOut())"); await pg.wait_for_timeout(1500); await pg.goto(B+'/'); await pg.wait_for_selector('#video-guide'); await pg.click('#video-guide'); await pg.wait_for_timeout(800)
        print('video file for this language:', await pg.evaluate("document.querySelector('.vmodal video').getAttribute('src')"), '| errors', errs)
        await br.close()
reqs=[]
asyncio.run(main())
