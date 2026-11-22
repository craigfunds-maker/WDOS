/* v109.48: WDOS on the home screen, like an app from a store.
 * Android and desktop Chrome: the browser's own install prompt, kept until she taps Install.
 * iPhone and iPad: Safari has no prompt, so she is shown the two taps (Share, then Add to Home Screen).
 * Nothing is shown once WDOS already runs from the home screen. */
import { h, icon, t, toast } from './ui.js';

let deferred = null;
const listeners = new Set();
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferred = e; listeners.forEach((f) => f()); });
window.addEventListener('appinstalled', () => { deferred = null; try { localStorage.setItem('wdos.installed', '1'); } catch { /* private mode */ } listeners.forEach((f) => f()); });

export const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
export const isIOS = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
export const canInstall = () => !standalone() && (!!deferred || isIOS());
export const onInstallChange = (f) => { listeners.add(f); return () => listeners.delete(f); };

export async function installApp() {
  if (deferred) {
    const ev = deferred; deferred = null; ev.prompt();
    try { const c = await ev.userChoice; if (c && c.outcome === 'accepted') toast(t('WDOS is on your home screen'), t('Open it from there next time, like any app.')); } catch { /* closed */ }
    listeners.forEach((f) => f()); return;
  }
  if (isIOS()) iosSheet();
}
function iosSheet() {
  const close = () => wrap.remove();
  const wrap = h('div', { class: 'hwpanel', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('Add WDOS to your home screen'), onclick: (e) => { if (e.target === wrap) close(); } },
    h('div', { class: 'card hwpanel__box' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
      h('h2', { style: 'margin:0;font-size:19px', text: t('Add WDOS to your home screen') }),
      h('p', { class: 'muted', style: 'margin:0', text: t('It opens full screen like an app, and you sign in less often.') }),
      h('ol', { class: 'iosteps' },
        h('li', {}, h('span', { class: 'iosteps__ico' }, icon('share', 20)), h('span', { text: t('Tap the Share button at the bottom of Safari.') })),
        h('li', {}, h('span', { class: 'iosteps__ico' }, icon('plus', 20)), h('span', { text: t('Scroll down and tap Add to Home Screen.') })),
        h('li', {}, h('span', { class: 'iosteps__ico' }, icon('check', 20)), h('span', { text: t('Tap Add. The WDOS icon appears on your home screen.') }))),
      h('button', { class: 'btn btn--brand', type: 'button', onclick: close, text: t('Got it') }))));
  document.body.append(wrap);
}
/* A slim card at the top of her home page on a phone, until she installs or says not now (asked again after 30 days). */
export function installNudge() {
  const KEY = 'wdos.install.later';
  let later = 0; try { later = +localStorage.getItem(KEY) || 0; } catch { /* private mode */ }
  const box = h('div', { class: 'installnudge' });
  const paint = () => {
    const show = canInstall() && Date.now() - later > 30 * 864e5 && window.matchMedia('(max-width: 900px)').matches;
    box.replaceChildren(); box.hidden = !show; if (!show) return;
    box.append(h('img', { src: '/assets/icons/icon-192.png', alt: '' }),
      h('div', { class: 'grow' }, h('b', { text: t('Get the WDOS app') }), h('small', { text: t('Free. Opens full screen and works on a weak network.') })),
      h('button', { class: 'btn btn--brand btn--s', type: 'button', onclick: installApp, text: t('Install') }),
      h('button', { class: 'iconbtn', type: 'button', 'aria-label': t('Not now'), onclick: () => { later = Date.now(); try { localStorage.setItem(KEY, String(later)); } catch { /* */ } paint(); } }, icon('x', 18)));
  };
  paint(); onInstallChange(paint); return box;
}
