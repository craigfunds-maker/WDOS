/* A member's home: her membership, what is left to set up, and the latest from HQ. */
import { h, icon, avatar, flag, netTag, card, sk, empty, ago, greeting, fmtDate, NETS, inspCard } from '../ui.js';
import { pushNudge } from '../pushnudge.js';
import { goodMotherCard } from '../goodmother.js';
import { api, pushState } from '../api.js';
import { app } from '../app.js';

export async function render(root) {
  const u = app.user; const net = NETS[u.network] || NETS.WGMN;
  root.append(h('section', { class: 'hero' }, avatar(u, 76), h('div', { class: 'grow' }, h('h1', { class: 'display', text: `${greeting()}, ${u.first_name}` }), h('p', { text: net.name }),
    h('div', { class: 'hero__meta' }, flag(u.country_code), netTag(u.network), u.member_no ? h('span', { class: 'tag num', text: u.member_no }) : null, h('span', { class: 'tag', text: [u.state_region, u.country].filter(Boolean).join(', ') }), h('span', { class: 'tag', text: 'Member since ' + fmtDate(u.created_at, { month: 'short', year: 'numeric' }) }))),
    h('img', { class: 'hero__disc', src: net.disc, alt: '' })));
  if (u.todo && u.todo.activation) root.append(h('section', { class: 'card nudge' }, h('div', { class: 'card__body', style: 'display:flex;gap:16px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'grow' }, h('b', { text: 'My Activation' }), h('p', { class: 'muted', style: 'margin:4px 0 0', text: 'You are being considered for a volunteer leadership position. Continue your activation from where you stopped.' })), h('a', { class: 'btn', href: '#/activation', text: 'Open my activation' }))));
  root.append(pushNudge());
  if (u.network === 'WGMN' && u.country_code) root.append(goodMotherCard(u.country_code, u.country));
  const today = h('div', { hidden: true }); root.append(today);
  api('/inspiration/today').then((r) => { if (r.card) { today.hidden = false; today.append(inspCard(r.card)); } }).catch(() => {});
  const steps = h('div', { class: 'steps' }, sk.rows(3)); const news = h('div', {}, sk.rows(3));
  root.append(h('div', { class: 'row row--2' }, card('Finish setting up', 'Three things that help your leaders reach you', steps), card('From WODDI HQ', 'Announcements for your network', news, h('a', { class: 'btn btn--ghost btn--s', href: '#/announcements', text: 'All announcements' }))));
  const [push, creds, ann] = await Promise.all([pushState().catch(() => 'off'), api('/webauthn/credentials').catch(() => ({ credentials: [] })), api('/announcements').catch(() => ({ items: [] }))]);
  const step = (done, title, text) => h('a', { class: 'step' + (done ? ' is-done' : ''), href: '#/settings' }, h('span', { class: 'step__dot' }, icon('check', 15)), h('div', {}, h('b', { text: title }), h('div', { class: 'faint', style: 'font-size:13.5px', text })));
  steps.replaceChildren(
    step(u.has_photo, 'Add your photograph', u.has_photo ? 'Done. Your leaders can recognise you.' : 'So your leaders and HQ can recognise you.'),
    step(push === 'on', 'Switch on notifications', push === 'on' ? 'Done on this device.' : push === 'unsupported' ? 'Not available in this browser. On iPhone, add WDOS to the Home Screen.' : 'Get announcements on this phone even when WDOS is closed.'),
    step(creds.credentials.length > 0, 'Set up fingerprint sign-in', creds.credentials.length ? 'Done. Sign in without typing a password.' : 'Sign in with your fingerprint or face next time.'));
  news.replaceChildren(ann.items.length ? h('div', { class: 'list reveal' }, ann.items.slice(0, 4).map((a) => h('a', { class: 'list__link', href: '#/announcements/' + a.id }, h('div', { class: 'nrow__ico' }, icon('megaphone', 18)), h('div', { class: 'grow' }, h('b', { text: a.title }), h('small', { text: ago(a.created_at) })))))
    : empty('megaphone', 'No announcements yet', 'When WODDI HQ writes to your network, you will read it here and get a notification.'));
}
