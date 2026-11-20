/* v109.37: after sign-in, if this device does not get WDOS notifications yet, WDOS asks once (Azeez, 20 Nov 2026). Calls, tasks,
 * approvals and messages then reach her phone even when WDOS is closed. "Not now" waits three days before asking again on this device. */
import { h, t, icon, toast } from './ui.js';
import { pushState, pushEnable, isIos, isInstalled } from './api.js';
import { app } from './app.js';
import { sendTest } from './pushnudge.js';

const KEY = 'wdos.pushask';
export async function needsPushAsk() {
  try { if (sessionStorage.getItem(KEY) || Number(localStorage.getItem(KEY) || 0) > Date.now()) return false; } catch { /* private mode */ }
  let st; try { st = await pushState(); } catch { return false; }
  return st === 'off' || (st === 'unsupported' && isIos() && !isInstalled());
}
export async function askPush(after = () => {}) {
  try { sessionStorage.setItem(KEY, '1'); } catch { /* */ }
  const ios = isIos() && !isInstalled();
  const close = (later) => { if (later) { try { localStorage.setItem(KEY, String(Date.now() + 3 * 86400000)); } catch { /* */ } } wrap.remove(); after(); };
  const err = h('p', { class: 'err', role: 'alert', style: 'margin:0' });
  const on = h('button', { class: 'btn btn--lime', type: 'button', onclick: async () => { on.disabled = true; err.textContent = '';
    try { await pushEnable(app.config.vapid); toast(t('Notifications are on'), t('A test notification is on its way.'), 'ok'); sendTest().catch(() => {}); close(false); }
    catch (e) { err.textContent = e.message; on.disabled = false; } } }, icon('bell', 18), t('Turn on notifications'));
  const wrap = h('div', { class: 'hwpanel photoask', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('Get WDOS on your phone') },
    h('div', { class: 'hwpanel__box card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px;justify-items:center;text-align:center' },
      h('div', { class: 'nrow__ico', style: 'width:64px;height:64px' }, icon('bell', 30)),
      h('h3', { style: 'margin:0', text: t('Get WDOS on your phone') }),
      h('p', { class: 'muted', style: 'margin:0', text: ios ? t('On an iPhone, tap the Share button, choose Add to Home Screen, then open WDOS from the new icon and switch notifications on there.') : t('Calls, tasks, messages and approvals reach you at once, even when WDOS is closed. You can switch them off any time in Settings.') }),
      h('div', { class: 'photoask__actions' }, ios ? null : on, h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => close(true) }, t('Not now'))), err)));
  document.body.append(wrap);
}
