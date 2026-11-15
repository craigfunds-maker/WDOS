/* How to use WDOS: the narrated walkthrough video in the person's language, and the same steps written out, each one read aloud on request.
 * A leader or envoy sees it once after her first sign-in; it stays in the menu under How to use WDOS. */
import { h, t, icon, card, i18n, listenBtn } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';

/* languages with a narrated leader video, built from the real WDOS screens in that language */
export const LEADER_VIDEO = ['ar', 'en', 'fr', 'pt', 'sw'];
export const LEADER_STEPS = [
  ['Your dashboard', 'Your dashboard shows your area at a glance: your members, your chapters, and what needs you today.'],
  ['Moving around', 'Open the menu to move between pages. On a phone, the bar at the bottom holds the main pages, and More opens the full menu.'],
  ['Tasks', 'Open Tasks. Read what is asked and open any document attached. Do the work, upload the finished work, then press Submit for review.'],
  ['Your monthly report', 'Open My reports. Fill in the monthly report and upload the report itself as a PDF or Word file. Then press Submit report. WODDI HQ is told at once.'],
  ['Messages', 'Open Messages and press New message. Write to one person, or to a group by network, leadership level or region. You have 30 seconds to undo. Each person is told in the app, on the phone and by email.'],
  ['Video meetings', 'Open Video meetings to ask HQ for a meeting in your area. Fill in the form. When HQ approves, the Join button unlocks 30 minutes before the start. Afterwards, send the minutes and the number who attended.'],
  ['Notifications and language', 'The bell shows what is new. Change your language at the top of any page, and press Listen to hear the page read aloud.'],
];
export const ENVOY_STEPS = [
  ['Your link', 'Your dashboard shows your own referral link. Copy it and share it with women who should join WODDI.'],
  ['The people you referred', 'Everyone who joins through your link appears on your dashboard, with how far she is in her 3 days.'],
  ['Follow up', 'Press Message beside a person to write to her. She is told in the app, on her phone and by email.'],
  ['Tasks', 'Open Tasks to see what HQ has given you. Do it, upload your work if needed, and submit it.'],
];

function guideBody(onDone) {
  const envoy = app.user?.is_envoy; const steps = envoy ? ENVOY_STEPS : LEADER_STEPS; const lang = i18n.lang;
  const vlang = LEADER_VIDEO.includes(lang) ? lang : 'en';
  const kind = envoy ? 'envoy' : 'leader';
  const video = h('video', { class: 'guidevid', controls: true, preload: 'metadata', playsinline: true, poster: `/assets/video/wdos-${kind}-guide-${vlang}.jpg`, src: `/assets/video/wdos-${kind}-guide-${vlang}.mp4`, 'aria-label': t('How to use WDOS, video guide') });
  const note = vlang !== lang ? h('p', { class: 'faint', style: 'margin:0', text: t('This video is not yet narrated in your language, so it plays in English. The steps below are in your language, and Listen reads them aloud.') }) : null;
  const all = () => steps.map(([a, b], i) => `${i + 1}. ${t(a)}. ${t(b)}`).join(' ');
  return h('div', { style: 'display:grid;gap:14px' }, video, note,
    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, listenBtn(all, 'Listen to every step'), onDone ? h('button', { class: 'btn', type: 'button', onclick: onDone }, t('I am ready')) : null),
    h('ol', { class: 'guidesteps' }, steps.map(([a, b]) => h('li', {}, h('b', { text: a }), h('p', { text: b }), listenBtn(() => t(a) + '. ' + t(b))))));
}

export function openGuide(first) {
  if (document.querySelector('.gmview.is-guide')) return;
  const close = () => { wrap.remove(); document.removeEventListener('keydown', esc); api('/me/guide-seen', {}).then(() => { if (app.user) app.user.guide_seen = true; }).catch(() => {}); };
  const esc = (e) => { if (e.key === 'Escape') close(); };
  const wrap = h('div', { class: 'gmview is-guide', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('How to use WDOS') },
    h('div', { class: 'card guidebox' }, h('div', { class: 'card__head' }, h('div', { class: 'grow' }, h('h2', { text: first ? t('Welcome to your WDOS dashboard') : t('How to use WDOS') }), h('p', { text: app.user?.is_t('Watch the short video, or read the steps. You can open this again any time from the menu, under How to use WDOS.') })),
      h('button', { class: 'iconbtn', type: 'button', 'aria-label': t('Close'), onclick: close }, icon('x'))), h('div', { class: 'card__body' }, guideBody(close))));
  document.addEventListener('keydown', esc); document.body.append(wrap);
}

export async function render(root) {
  root.append(card(app.user?.is_envoy ? 'How to use WDOS as a WODDI Envoy' : 'How to use WDOS as a volunteer leader', 'The video is narrated in your language where a voice exists. Every step is also written out, and Listen reads it aloud.', guideBody(null)));
}
