# What the browser sees on the front page (printed in the CI log, to diagnose the screen tests)
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
  b=p.chromium.launch(); pg=b.new_page(); logs=[]
  pg.on('console', lambda m: logs.append(f'console {m.type}: {m.text}'[:300])); pg.on('pageerror', lambda e: logs.append(f'pageerror: {e}'[:300]))
  pg.on('requestfailed', lambda r: logs.append(f'failed: {r.url} {r.failure}'[:300])); pg.on('response', lambda r: logs.append(f'{r.status} {r.url}'[:200]) if r.status>=400 else None)
  pg.goto('http://127.0.0.1:8080/'); pg.wait_for_timeout(8000)
  print('TITLE', pg.title()); print('BODY', pg.inner_text('body')[:400].replace('\n',' | '))
  for l in logs[:40]: print(l)
  b.close()
