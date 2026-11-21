/* v109.44: on her birthday her dashboard opens with her card from WODDI HQ (her photo and her name on it) and the birthday
 * message WODDI HQ recorded for her, spoken with her name. She can save the card to her phone or share it. */
import { h, t, tf, icon, toast } from './ui.js';
import { api } from './api.js';

export function birthdayCard(opts = {}) {
  const box = h('div', { hidden: true });
  api('/me/birthday-card').then((r) => { if (!r || !r.today) { if (opts.page) box.replaceChildren(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'text-align:center;display:grid;gap:8px' }, icon('cake', 28), h('b', { text: t('Your birthday card appears here on your birthday') }), h('p', { class: 'muted', style: 'margin:0', text: t('WODDI HQ makes it with your photo and your name, and records a birthday message for you.') })))), box.hidden = !opts.page; return; }
    box.hidden = false; box.replaceChildren(view(r)); }).catch(() => {});
  return box;
}
function view(r) {
  const img = r.card ? h('img', { src: r.card, alt: tf('Happy birthday, {1}', r.name), class: 'bday__img', loading: 'eager' }) : null;
  const audio = r.voice ? h('audio', { controls: true, preload: 'auto', src: r.voice, class: 'bday__audio' }) : null;
  const play = r.voice ? h('button', { class: 'btn btn--lime', type: 'button', onclick: () => { audio.play().catch(() => {}); } }, icon('play', 18), t('Play my birthday message')) : null;
  const save = r.card ? h('a', { class: 'btn', href: r.card, download: 'WODDI-birthday-card.jpg' }, icon('down', 18), t('Save my card')) : null;
  const share = r.card && navigator.share ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => {
    try { const b = await (await fetch(r.card)).blob(); const f = new File([b], 'WODDI-birthday-card.jpg', { type: 'image/jpeg' });
      if (navigator.canShare && navigator.canShare({ files: [f] })) await navigator.share({ files: [f], title: t('My birthday card from WODDI HQ') }); else await navigator.share({ url: r.card, title: t('My birthday card from WODDI HQ') });
    } catch (e) { if (e && e.name !== 'AbortError') toast(t('Not shared'), e.message, 'bad'); } } }, icon('send', 18), t('Share')) : null;
  return h('section', { class: 'card bday' }, h('div', { class: 'bday__grid' },
    img ? h('div', { class: 'bday__card' }, img) : null,
    h('div', { class: 'bday__side' },
      h('span', { class: 'tag tag--wgmn', style: 'justify-self:start', text: t('From WODDI HQ') }),
      h('h2', { class: 'display', text: tf('Happy birthday, {1}!', r.name) }),
      h('p', { class: 'muted', style: 'margin:0', text: t('Everyone at WODDI HQ is celebrating you today. Thank you for all you give to your network.') }),
      audio ? h('div', { style: 'display:grid;gap:10px' }, play, audio) : h('p', { class: 'faint', style: 'margin:0', text: t('Your birthday message from WODDI HQ is on its way. It plays here when it arrives.') }),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, save, share))));
}
