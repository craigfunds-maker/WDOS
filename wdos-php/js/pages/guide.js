/* How to use WDOS: a guided tour played inside the app, in the person's language.
 * It greets her by her first name (the name is spoken by the phone's own voice where it has one), then walks screen by
 * screen through the real WDOS pages, showing exactly what to tap, with a caption and a narrated line for each screen.
 * The screens and the voice are small files (about 50 KB per screen) so it plays on low data. The same steps are written
 * out under "Read the steps instead", each one read aloud on request.
 * A leader or envoy sees it once after her first sign-in; it stays in the menu under How to use WDOS. */
import { h, t, tf, icon, card, i18n, listenBtn, avatar } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';

export const TOUR_LANGS = ['ar', 'en', 'fr', 'pt', 'sw'];
export const LEADER_STEPS = [
  ['Your dashboard', 'Your dashboard shows your area at a glance: your members, your chapters, and what needs you today.'],
  ['Moving around', 'Open the menu to move between pages. On a phone, the bar at the bottom holds the main pages, and More opens the full menu.'],
  ['Tasks', 'Open Tasks. Read what is asked and open any document attached. Do the work, upload the finished work, then press Submit for review.'],
  ['Your monthly report', 'Open My reports. Fill in the monthly report and upload the report itself as a PDF or Word file. Then press Submit report. WODDI HQ is told at once.'],
  ['Messages', 'Open Messages and press New message. Write to one person, or to a group by network, leadership level or region. You have 30 seconds to undo. Each person is told in the app, on the phone and by email.'],
  ['Video meetings', 'Open Video meetings to ask HQ for a meeting in your area. Fill in the form. As soon as HQ approves, the Join button is ready. Tell your people from the same page, or copy the meeting link and send it. Afterwards, send the minutes and the number who attended.'],
  ['Notifications and language', 'The bell shows what is new. Change your language at the top of any page, and press Listen to hear the page read aloud.'],
];
export const MEMBER_STEPS = [
  ['Your home', 'Your home page shows your seven-day journey, your chapter, your referrals and the WODDI Community.'],
  ['Seven-day journey', 'Open the seven-day journey and take one short step each day until you are an active member.'],
  ['Your chapter', 'Open My chapter to see the women near you and your chapter leaders.'],
  ['Invite three women', 'Open your referrals, copy your own link and send it on WhatsApp to three women you trust.'],
  ['Messages and help', 'Press Messages to write to WODDI HQ. The answer comes in the app and by email.'],
  ['Notifications and language', 'The bell shows what is new. Change your language at the top of any page, and press Listen to hear the page read aloud.'],
];
export const ENVOY_STEPS = [
  ['Your link', 'Your dashboard shows your own referral link. Copy it, or send it on WhatsApp, to women who should join WODDI.'],
  ['The people you referred', 'Everyone who joins through your link appears on your dashboard, with how far she is in her 3 days.'],
  ['Follow up', 'Press Message under a person\'s name to write to her. She is told in the app, on her phone and by email.'],
  ['Tasks', 'Open Tasks to see what HQ has given you. Do it, upload your work if needed, and submit it.'],
];

function stepsList() {
  const steps = app.user?.is_envoy ? ENVOY_STEPS : app.user?.is_leader ? LEADER_STEPS : MEMBER_STEPS;
  const all = () => steps.map(([a, b], i) => `${i + 1}. ${t(a)}. ${t(b)}`).join(' ');
  return h('div', { style: 'display:grid;gap:12px' }, h('div', {}, listenBtn(all, 'Listen to every step')),
    h('ol', { class: 'guidesteps' }, steps.map(([a, b]) => h('li', {}, h('b', { text: a }), h('p', { text: b }), listenBtn(() => t(a) + '. ' + t(b))))));
}

/* the phone's own voice says the first name; we never send the name anywhere */
function sayName(name, lang) {
  return new Promise((res) => {
    const ss = window.speechSynthesis; if (!ss || !name) { res(); return; }
    let done = false; const end = () => { if (!done) { done = true; res(); } };
    try {
      const u = new SpeechSynthesisUtterance(name); u.rate = 0.9;
      const v = ss.getVoices().find((x) => x.lang && x.lang.toLowerCase().startsWith(lang)) || ss.getVoices().find((x) => x.lang && x.lang.toLowerCase().startsWith('en'));
      if (v) { u.voice = v; u.lang = v.lang; } else u.lang = lang;
      u.onend = end; u.onerror = end; ss.cancel(); ss.speak(u); setTimeout(end, 2600);
    } catch { end(); }
  });
}

export async function tourPlayer({ onClose, first } = {}) {
  const kind = app.user?.is_envoy ? 'envoy' : app.user?.is_leader ? 'leader' : 'member';
  const lang = TOUR_LANGS.includes(i18n.lang) ? i18n.lang : 'en';
  const base = `/assets/tour/${kind}/${lang}/`; const voice = `/assets/tour/voice/${lang}/`;
  let man = { scenes: [] };
  try { man = await (await fetch(base + 'tour.json')).json(); } catch { /* offline */ }
  const scenes = man.scenes || [];
  const name = app.user?.first_name || '';
  const total = scenes.length + 1; let i = 0; let playing = false; let timer = null; let audio = null; let token = 0;
  const bar = h('div', { class: 'tour__bar' }, h('span'));
  const count = h('small', { class: 'tour__count', 'aria-live': 'polite' });
  const stage = h('div', { class: 'tour__stage' });
  const cap = h('p', { class: 'tour__cap', dir: 'auto', 'aria-live': 'polite' });
  const playBtn = h('button', { class: 'btn btn--lime tour__play', type: 'button', onclick: () => (playing ? pause() : play()) });
  const prev = h('button', { class: 'btn btn--ghost tour__nav', type: 'button', 'aria-label': t('Previous'), onclick: () => go(i - 1, true) }, icon('back', 20), h('span', { text: t('Previous') }));
  const next = h('button', { class: 'btn btn--ghost tour__nav tour__nav--next', type: 'button', 'aria-label': t('Next'), onclick: () => go(i + 1, true) }, h('span', { text: t('Next') }), icon('fwd', 20));
  const note = lang !== i18n.lang ? h('p', { class: 'tour__note', text: t('This guide is not yet narrated in your language, so it plays in English. Read the steps instead to see them in your language.') }) : null;
  const preload = (k) => { const s = scenes[k]; if (s) { const im = new Image(); im.src = base + s.id + '.webp'; } };
  const stopSound = () => { token++; clearTimeout(timer); try { audio?.pause(); } catch { /* none */ } audio = null; try { window.speechSynthesis?.cancel(); } catch { /* none */ } };
  const clip = (src, my) => new Promise((res) => {
    if (my !== token) { res(false); return; }
    const a = new Audio(src); audio = a; let ok = true;
    a.onended = () => res(true); a.onerror = () => { ok = false; res(false); };
    a.play().catch(() => { ok = false; res(false); });
    setTimeout(() => { if (ok && a.paused && a.currentTime === 0) res(false); }, 9000);
  });
  const paintBtn = () => { playBtn.replaceChildren(icon(playing ? 'pause' : 'play', 18), h('span', { text: t(playing ? 'Pause' : (i === 0 && first ? 'Start the guide' : 'Play')) })); playBtn.setAttribute('aria-pressed', String(playing)); };
  function draw() {
    bar.firstChild.style.width = `${Math.round(((i + 1) / total) * 100)}%`;
    count.textContent = tf('Step {1} of {2}', i + 1, total);
    prev.disabled = i === 0; next.disabled = i >= total - 1;
    if (i === 0) {
      stage.replaceChildren(h('div', { class: 'tour__hello' },
        h('img', { src: '/assets/logos/woddi-disc.png', alt: '', class: 'tour__logo', onerror: (e) => e.target.remove() }),
        app.user ? avatar(app.user, 88) : null,
        h('h2', { class: 'display', dir: 'auto' }, h('span', { text: String(man.hello || 'Hello,').replace(/[,،]\s*$/, '') + ' ' }), h('bdi', { text: name + '!' })),
        h('p', { dir: 'auto', text: man.glad || '' })));
      cap.textContent = man.intro || '';
    } else {
      const s = scenes[i - 1];
      const shot = h('div', { class: 'tour__phone' }, h('img', { src: base + s.id + '.webp', alt: s.text, draggable: 'false' }));
      if (s.box) {
        const [x, y, w, hh] = s.box;
        shot.append(h('div', { class: 'tour__ring' + (s.action === 'tap' ? ' is-tap' : ''), style: `left:${x}%;top:${y}%;width:${w}%;height:${hh}%` }));
        if (s.action === 'tap') shot.append(h('div', { class: 'tour__finger', 'aria-hidden': 'true', style: `left:${x + w / 2}%;top:${y + hh / 2}%` }, h('span', { class: 'tour__dot' }), h('span', { class: 'tour__hand', text: '👆' })));
      }
      stage.replaceChildren(shot); cap.textContent = s.text; preload(i);
    }
    paintBtn();
  }
  async function speak() {
    stopSound(); const my = token; if (!playing) return;
    let ok;
    if (i === 0) { await clip(voice + 'hello.mp3', my); if (my !== token) return; await sayName(name, lang); if (my !== token) return; ok = await clip(voice + 'glad.mp3', my); }
    else ok = await clip(voice + kind + '-' + scenes[i - 1].id + '.mp3', my);
    if (my !== token || !playing) return;
    const words = (cap.textContent || '').split(/\s+/).length;
    timer = setTimeout(() => { if (my !== token || !playing) return; if (i < total - 1) { i++; draw(); speak(); } else { playing = false; paintBtn(); } }, ok ? 900 : Math.max(4000, words * 420));
  }
  function go(k, user) { if (k < 0 || k >= total) return; i = k; draw(); if (playing) speak(); else if (user) stopSound(); }
  function play() { if (i >= total - 1 && !playing) i = 0; playing = true; draw(); speak(); }
  function pause() { playing = false; stopSound(); paintBtn(); }
  const steps = h('details', { class: 'tour__steps' }, h('summary', { text: t('Read the steps instead') }), stepsList());
  const node = h('div', { class: 'tour', dir: document.documentElement.dir || 'ltr' }, h('div', { class: 'tour__top' }, bar, count), note, stage, cap,
    h('div', { class: 'tour__controls' }, prev, playBtn, next), steps);
  node.stop = () => { playing = false; stopSound(); };
  node.keys = (e) => { if (e.key === 'ArrowRight') go(document.documentElement.dir === 'rtl' ? i - 1 : i + 1, true); else if (e.key === 'ArrowLeft') go(document.documentElement.dir === 'rtl' ? i + 1 : i - 1, true); else if (e.key === ' ' && e.target === document.body) { e.preventDefault(); playing ? pause() : play(); } };
  if (!scenes.length) { stage.replaceChildren(h('p', { class: 'faint', text: t('The guided tour could not load. Check your connection, or read the steps below.') })); steps.open = true; }
  draw(); preload(0);
  return node;
}

export async function openGuide(first) {
  if (document.querySelector('.gmview.is-guide')) return;
  let player = null;
  const close = () => { player?.stop(); wrap.remove(); document.removeEventListener('keydown', esc); document.body.classList.remove('in-tour'); api('/me/guide-seen', {}).then(() => { if (app.user) app.user.guide_seen = true; }).catch(() => {}); };
  const esc = (e) => { if (e.key === 'Escape') close(); else player?.keys(e); };
  const body = h('div', { class: 'card__body' });
  const wrap = h('div', { class: 'gmview is-guide', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('How to use WDOS') },
    h('div', { class: 'card guidebox' }, h('div', { class: 'card__head' }, h('div', { class: 'grow' }, h('h2', { text: first ? t('Welcome to your WDOS dashboard') : t('How to use WDOS') }), h('p', { text: t('Press Play and follow the screens. You can open this again any time from the menu, under How to use WDOS.') })),
      h('button', { class: 'iconbtn', type: 'button', 'aria-label': t('Close'), onclick: close }, icon('x'))), body));
  document.addEventListener('keydown', esc); document.body.append(wrap); document.body.classList.add('in-tour');
  player = await tourPlayer({ first });
  body.append(player, h('div', { style: 'margin-top:12px' }, h('button', { class: 'btn btn--quiet', type: 'button', onclick: close }, t(first ? 'I am ready' : 'Close'))));
}

export async function render(root) {
  const host = h('div');
  root.append(card(app.user?.is_envoy ? 'How to use WDOS as a WODDI Envoy' : app.user?.is_leader ? 'How to use WDOS as a volunteer leader' : 'How to use WDOS as a member', 'Press Play. Each screen shows exactly what to tap, with the words read aloud in your language.', host));
  const p = await tourPlayer({});
  host.append(p);
  const off = () => { p.stop(); window.removeEventListener('hashchange', off); document.removeEventListener('keydown', p.keys); };
  window.addEventListener('hashchange', off); document.addEventListener('keydown', p.keys);
}
