/* One place that tells a person, on their dashboard, how to get notifications on this device, and switches them on in one tap. */
import { h, icon, toast } from './ui.js';
import { api, pushState, pushEnable, isIos, isInstalled } from './api.js';
import { app } from './app.js';

/** What the push services answered for each device, in plain words. */
export function testReport(r) {
  if (!r.devices.length) return ['No device is registered', 'Switch notifications off and on again on this device, then send the test again.', 'bad'];
  const bad = r.devices.filter((d) => !d.ok);
  if (!bad.length) return ['Test delivered to ' + (r.devices.length === 1 ? r.devices[0].device : r.devices.length + ' devices'), 'The push service accepted it. If nothing appears within a minute, notifications for this browser are switched off in the settings of the phone or computer itself.', 'ok'];
  return ['Test not delivered', bad.map((d) => `${d.device}: ${d.gone ? 'this device was no longer registered, so switch notifications off and on again' : 'the push service answered ' + (d.code || 'nothing') + (d.error ? ' (' + d.error + ')' : '')}`).join('. ') + '.', 'bad'];
}
export async function sendTest() { const r = await api('/push/test', {}); const [t, b, k] = testReport(r); toast(t, b, k); return r; }

export function pushNudge() {
  const box = h('div', { hidden: true });
  const card = (title, text, action) => h('section', { class: 'card nudge' }, h('div', { class: 'card__body nudge__row' }, h('div', { class: 'nrow__ico' }, icon('bell', 18)), h('div', { class: 'grow' }, h('b', { text: title }), h('p', { class: 'muted', style: 'margin:4px 0 0', text })), action || null));
  (async () => {
    let st; try { st = await pushState(); } catch { return; }
    if (st === 'on') return;
    if (st === 'unsupported' && isIos() && !isInstalled()) box.replaceChildren(card('Add WDOS to your Home Screen', 'On an iPhone, notifications only reach an installed app. Tap the Share button in your browser, choose Add to Home Screen, then open WDOS from the new icon and switch notifications on.'));
    else if (st === 'blocked') box.replaceChildren(card('Notifications are blocked on this device', 'Allow notifications for WDOS in your browser or phone settings, then come back to this page.'));
    else if (st === 'off') { const b = h('button', { class: 'btn', type: 'button', onclick: async () => { b.disabled = true; try { await pushEnable(app.config.vapid); box.hidden = true; await sendTest(); } catch (e) { toast('Notifications not switched on', e.message, 'bad'); b.disabled = false; } } }, 'Turn on');
      box.replaceChildren(card('Turn on notifications', 'Get announcements, tasks and meeting reminders on this device, even when WDOS is closed.', b)); }
    else return;
    box.hidden = false;
  })();
  return box;
}
