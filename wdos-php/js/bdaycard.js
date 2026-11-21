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
/* v109.44 (Azeez, 21 Nov 2026: "the balloons in the birthday card must animate, so is the characters"): on her dashboard the card
 * is alive. The picture underneath is her card without balloons, headline, name or ribbon; those are drawn here and move: the
 * balloons float and sway, confetti falls, and "Happy Birthday" (in her language), her name and her designation come in letter
 * by letter and keep a gentle wave. Saved and emailed, the card is the still picture. Calmer for anyone who asks for less motion. */
const BALLOONS = [[118, 420, '#7CB518', .95, 6.2, 0], [200, 520, '#F6C445', .68, 5.1, .8], [962, 410, '#FFFFFF', .9, 6.8, .4], [882, 520, '#7CB518', .66, 5.6, 1.3]];
function balloonSvg(x, y, c, sc, dur, delay) {
  const ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', '-95 0 190 390'); svg.setAttribute('class', 'bdlive__balloon'); svg.setAttribute('aria-hidden', 'true');
  svg.innerHTML = `<path d="M0 230 C -8 280, 14 320, -4 380" stroke="rgba(255,255,255,.75)" stroke-width="3" fill="none"/><ellipse cx="0" cy="110" rx="84" ry="106" fill="${c}"/><ellipse cx="-28" cy="70" rx="18" ry="30" fill="rgba(255,255,255,.35)" transform="rotate(-20 -28 70)"/><path d="M-10 214 L10 214 L0 230Z" fill="${c}"/>`;
  const w = 190 * sc; Object.assign(svg.style, { left: ((x - 95 * sc) / 1080 * 100) + '%', top: (y / 1350 * 100) + '%', width: (w / 1080 * 100) + '%', animationDuration: dur + 's', animationDelay: (-delay) + 's' });
  return svg;
}
const letters = (text, cls, start = 0) => {
  const el = h('span', { class: cls, 'aria-label': text });
  [...text].forEach((ch, i) => el.append(h('span', { class: 'bdl', 'aria-hidden': 'true', style: `--i:${i + start}`, text: ch === ' ' ? '\u00a0' : ch })));
  return el;
};
function liveCard(r) {
  const stage = h('div', { class: 'bdlive', role: 'img', 'aria-label': tf('Happy birthday, {1}', r.full_name || r.name) });
  stage.append(h('img', { class: 'bdlive__bg', src: r.live, alt: '' }));
  BALLOONS.forEach((b) => stage.append(balloonSvg(...b)));
  for (let i = 0; i < 22; i++) stage.append(h('i', { class: 'bdlive__conf', style: `left:${(i * 47) % 100}%;--d:${4 + (i % 5)}s;--w:${(i * 0.37) % 4}s;background:${['#7CB518', '#FFFFFF', '#F6C445', '#FF8CC6'][i % 4]}` }));
  const rtl = r.lang === 'ar';
  const head = letters(r.headline || 'Happy Birthday', 'bdlive__head'); if (rtl) head.dir = 'rtl';
  const name = letters(r.full_name || r.name || '', 'bdlive__name', (r.headline || '').length);
  stage.append(head, name);
  if (r.designation) stage.append(h('span', { class: 'bdlive__ribbon', text: r.designation }));
  // shrink the headline and name until each fits on one line, as on the printed card
  const fit = () => { if (!stage.isConnected || !stage.clientWidth) return requestAnimationFrame(fit);
    for (const el of [head, name]) { el.style.fontSize = ''; let f = parseFloat(getComputedStyle(el).fontSize); while (el.scrollWidth > el.clientWidth + 1 && f > 10) { f -= 1; el.style.fontSize = f + 'px'; } } };
  requestAnimationFrame(fit); window.addEventListener('resize', () => requestAnimationFrame(fit));
  return stage;
}
function view(r) {
  const img = r.live ? liveCard(r) : r.card ? h('img', { src: r.card, alt: tf('Happy birthday, {1}', r.name), class: 'bday__img', loading: 'eager' }) : null;
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
