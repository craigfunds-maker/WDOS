/* v109.34: the WODDI State Envoy's video guide (Azeez, 20 Nov 2026), in her sidebar under Video guide.
 * A narrated walk through her own dashboard: signing in, the dashboard, copying and sending the referral link, tracking the
 * candidates and their scores, messaging a candidate and WODDI HQ, and the profile photo. The same words are written below the
 * video, so reading is equal to watching (low data, no sound, or a shared phone). */
import { h, t, tf, card, i18n } from '../ui.js';

export const ENVOY_VIDEO_LANGS = ['ar', 'en', 'fr', 'pt', 'sw'];
export const ENVOY_GUIDE = [
  ['Sign in', 'Open woddicrm.org and press Sign in. Type the phone number or email HQ gave you, then your password, and press Sign in. Your envoy dashboard opens.'],
  ['Your dashboard', 'At the top you see your name and your state. Below are your numbers: the leaders you brought in, the people who joined through your link, and the visits to your link.'],
  ['Copy and send your link', 'Press Copy link and paste it in WhatsApp, a text or an email to the woman you recommend, or press Send on WhatsApp. When she opens it, she chooses the leadership role, adds her photo and creates her account.'],
  ['Track your candidates', 'Everyone who joins through your link appears in your list. The three dots show her three days of activation. When she finishes, WDOS gives her a score out of 100, and 60 is the pass mark. She becomes a volunteer leader only after she passes and HQ approves her. You are told when that happens.'],
  ['Message a candidate or HQ', 'Press Message beside a candidate to write to her. Press Message WODDI HQ to write to headquarters. You have 30 seconds to undo a message after you send it.'],
  ['Your profile photo', 'Open Settings and press Add photograph or Change photograph. Take a photo with your camera or choose one from your phone. It becomes your picture everywhere in WDOS.'],
];

export async function render(root) {
  const L = ENVOY_VIDEO_LANGS.includes(i18n.lang) ? i18n.lang : 'en';
  const src = `/assets/video/wdos-envoy-guide-${L}.mp4?v=34`;
  const v = h('video', { src, controls: true, playsinline: true, preload: 'metadata', poster: `/assets/video/wdos-envoy-guide-${L}.jpg?v=34`,
    style: 'width:100%;max-width:420px;border-radius:18px;background:#000;justify-self:center', 'aria-label': t('Video guide for WODDI State Envoys') });
  root.append(
    h('section', { class: 'hero' }, h('h1', { class: 'display', text: t('Your video guide') }), h('p', { text: t('Six short steps for your envoy dashboard. Watch it, or read the same steps below.') })),
    h('div', { class: 'row row--2-1' },
      card('Watch', L !== i18n.lang ? t('The video is in English. The steps below are in your language.') : null, h('div', { style: 'display:grid' }, v)),
      card('Read the steps', null, h('ol', { style: 'margin:0;padding-left:20px;display:grid;gap:12px' },
        ENVOY_GUIDE.map(([title, body], i) => h('li', {}, h('b', { text: tf('Step {1}: {2}', i + 1, t(title)) }), h('p', { class: 'muted', style: 'margin:4px 0 0', text: t(body) })))))));
}
