# v109.43: turning on notifications right after an update waits for WDOS to be ready instead of failing with
# "Subscription failed - no active Service Worker"
import asyncio
from playwright.async_api import async_playwright
B='http://127.0.0.1:8080'; ok=bad=0
def chk(n,c,info=''):
  global ok,bad
  if c: ok+=1; print('ok  ',n)
  else: bad+=1; print('FAIL',n,str(info)[:300])
async def main():
  async with async_playwright() as p:
    br=await p.chromium.launch(executable_path='/usr/bin/google-chrome',args=['--no-sandbox'])
    ctx=await br.new_context(); pg=await ctx.new_page()
    await pg.goto(B+'/#/login'); await pg.wait_for_timeout(1500)
    # the moment after an update: no worker at all, then ask for the registration at once
    r=await pg.evaluate("""async()=>{ for (const g of await navigator.serviceWorker.getRegistrations()) await g.unregister();
      const before=(await navigator.serviceWorker.getRegistration()); const m=await import('/js/api.js?x='+Date.now());
      const t=Date.now(); const reg=await m.activeReg(30000); return {before:!!(before&&before.active), state:reg.active&&reg.active.state, ms:Date.now()-t}; }""")
    chk('with no active worker, WDOS waits until one is active', r['before']==False and r['state']=='activated', r)
    # while a new version is installing, it waits for that one
    r=await pg.evaluate("""async()=>{ const reg=await navigator.serviceWorker.getRegistration(); await reg.update(); const m=await import('/js/api.js?y='+Date.now()); const g=await m.activeReg(30000); return g.active&&g.active.state; }""")
    chk('during an update it still hands back an active worker', r=='activated', r)
    src=await (await pg.request.get(B+'/js/api.js')).text()
    chk('turning on notifications uses the waiting and retry path', 'const sub = await subscribeWithRetry(vapid);' in src and 'serviceWorker.ready' not in src.split('export async function pushEnable')[1].split('export')[0])
    chk('if it still cannot, she gets a plain message, not the browser error', 'WDOS is finishing an update on this device.' in src)
asyncio.run(main()); print(f'checks {ok+bad} FAILURES {bad}')
