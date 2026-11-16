/* How to use WDOS: a guided tour played on the person's OWN dashboard and pages, in her language.
 * WDOS opens each of her real pages in turn, rings the exact button to tap with a moving finger, and reads one short
 * line aloud (natural voice files, small for low data). It never presses anything for her: it only points.
 * Where she has nothing yet (no task, no report), it says what will appear there instead of pointing.
 * Her name is shown on screen; nothing about her is sent anywhere. A leader, member or envoy sees it once after her
 * first sign-in, and can play it again any time from the menu under How to use WDOS. */
import { h, t, tf, icon, card, i18n, listenBtn, avatar } from '../ui.js';
import { api } from '../api.js';
import { app, go as goTo } from '../app.js';

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

/* each scene: r = page to open, open = follow the first matching link on that page (her own first task, report or
 * meeting), pre = press first (only harmless buttons that open a menu or a form), sel = what to ring, alt = what to ring
 * where sel is not shown (a computer has a side menu instead of the bottom bar) */
const SIDE = (h_) => `#side a[href="${h_}"]`;
const PLAN = {
  leader: [
    { id: 'dash', r: '#/' }, { id: 'more', r: '#/', sel: '[data-tour=more]', alt: SIDE('#/tasks') },
    { id: 'menu', r: '#/', pre: '[data-tour=more]', sel: SIDE('#/tasks') },
    { id: 'brief', r: '#/tasks', open: 'a.list__link[href^="#/tasks/"]', sel: '#task-docs a[href^="/api/files/"]' },
    { id: 'work', sel: '#task-docs button.btn--quiet' }, { id: 'tsubmit', sel: '[data-tour=task-submit]' },
    { id: 'rtab', r: '#/reporting', sel: '.tabbar a[href="#/reporting"]', alt: SIDE('#/reporting') },
    { id: 'rdoc', r: '#/reporting', open: 'a[href^="#/reporting/r/"], tr[tabindex]', sel: '#report-doc button.btn--quiet' },
    { id: 'rsubmit', sel: '[data-tour=report-submit]' },
    { id: 'dmnew', r: '#/inbox', sel: '[data-tour=dm-new]' }, { id: 'dmto', r: '#/inbox/new', sel: '[data-tour=dm-to]' },
    { id: 'mrask', r: '#/meetings/requests', sel: '[data-tour=mr-ask]' },
    { id: 'mrflier', r: '#/meetings/requests', pre: '[data-tour=mr-ask]', sel: '[data-tour=mr-flier]' },
    { id: 'mrjoin', r: '#/meetings/requests', open: 'a.list__link[href^="#/meetings/requests/"]', sel: '[data-tour=mr-join]' },
    { id: 'mrtell', sel: '[data-tour=mr-notify]' }, { id: 'mrcopy', sel: '[data-tour=mr-copy]' }, { id: 'mrminutes', sel: '[data-tour=mr-minutes]' },
    { id: 'bell', r: '#/', sel: '[data-tour=bell]' }, { id: 'lang', sel: 'select.langpick' }, { id: 'listen', sel: 'button.listen' }, { id: 'end', r: '#/' }],
  envoy: [
    { id: 'dash', r: '#/' }, { id: 'copy', r: '#/', sel: '[data-tour=envoy-copy]' }, { id: 'wa', sel: '[data-tour=envoy-wa]' }, { id: 'people', sel: '.tablewrap' },
    { id: 'msg', sel: '[data-tour=envoy-msg]' }, { id: 'tasks', sel: '.tabbar a[href="#/tasks"]', alt: SIDE('#/tasks') },
    { id: 'bell', sel: '[data-tour=bell]' }, { id: 'lang', sel: 'select.langpick' }, { id: 'end', r: '#/' }],
  member: [
    { id: 'dash', r: '#/' }, { id: 'journey', r: '#/', sel: 'a.kpi--link[href="#/journey"]', alt: SIDE('#/journey') },
    { id: 'chapter', sel: 'a.kpi--link[href="#/chapter"]', alt: SIDE('#/chapter') }, { id: 'invite', sel: 'a.kpi--link[href="#/invite"]', alt: SIDE('#/invite') },
    { id: 'community', sel: 'a.nudge[href="#/community"]', alt: SIDE('#/community') }, { id: 'messages', sel: '.tabbar a[href="#/messages"]', alt: SIDE('#/messages') },
    { id: 'bell', sel: '[data-tour=bell]' }, { id: 'lang', sel: 'select.langpick' }, { id: 'end', r: '#/' }],
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const shown = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
const find = (sel) => (sel ? [...document.querySelectorAll(sel)].find((e) => shown(e) && !e.closest('.ltour')) : null);
async function waitFor(sel, ms) { const end = Date.now() + ms; while (Date.now() < end) { const e = find(sel); if (e) return e; await sleep(150); } return null; }
const sameRoute = (r) => (location.hash || '#/') === r || (r === '#/' && ['', '#', '#/'].includes(location.hash));

function stepsList() {
  const steps = app.user?.is_envoy ? ENVOY_STEPS : app.user?.is_leader ? LEADER_STEPS : MEMBER_STEPS;
  const all = () => steps.map(([a, b], i) => `${i + 1}. ${t(a)}. ${t(b)}`).join(' ');
  return h('div', { style: 'display:grid;gap:12px' }, h('div', {}, listenBtn(all, 'Listen to every step')),
    h('ol', { class: 'guidesteps' }, steps.map(([a, b]) => h('li', {}, h('b', { text: a }), h('p', { text: b }), listenBtn(() => t(a) + '. ' + t(b))))));
}

let running = null;
export async function startTour({ first } = {}) {
  if (running) return;
  const kind = app.user?.is_envoy ? 'envoy' : app.user?.is_leader ? 'leader' : 'member';
  const lang = TOUR_LANGS.includes(i18n.lang) ? i18n.lang : 'en';
  const voice = `/assets/tour/voice/${lang}/`;
  let man = { scenes: [] };
  try { man = await (await fetch(`/assets/tour/${kind}/${lang}/tour.json`, { cache: 'no-cache' })).json(); } catch { /* offline: the words below still show */ }
  const words = Object.fromEntries((man.scenes || []).map((s) => [s.id, s]));
  const scenes = PLAN[kind].filter((s) => words[s.id]);
  const name = app.user?.first_name || '';
  const total = scenes.length + 1; let i = 0; let playing = false; let timer = null; let audio = null; let token = 0; let raf = 0; let target = null;
  const dir = document.documentElement.dir || 'ltr';
  const shade = h('div', { class: 'ltour__shade' });
  const ring = h('div', { class: 'ltour__ring' });
  const finger = h('div', { class: 'ltour__finger', 'aria-hidden': 'true' }, h('span', { class: 'tour__dot' }), h('span', { class: 'tour__hand', text: '👆' }));
  const bar = h('div', { class: 'tour__bar' }, h('span'));
  const count = h('small', { class: 'tour__count', 'aria-live': 'polite' });
  const body = h('div', { class: 'ltour__body', dir: 'auto', 'aria-live': 'polite' });
  const playBtn = h('button', { class: 'btn btn--lime tour__play', type: 'button', onclick: () => (playing ? pause() : play()) });
  const prev = h('button', { class: 'btn btn--ghost tour__nav', type: 'button', 'aria-label': t('Previous'), onclick: () => step(i - 1, true) }, icon('back', 20), h('span', { text: t('Previous') }));
  const next = h('button', { class: 'btn btn--ghost tour__nav tour__nav--next', type: 'button', 'aria-label': t('Next'), onclick: () => step(i + 1, true) }, h('span', { text: t('Next') }), icon('fwd', 20));
  const closeBtn = h('button', { class: 'iconbtn ltour__x', type: 'button', 'aria-label': t('Close the guide'), onclick: () => close() }, icon('x'));
  const readLink = h('a', { href: '#/guide', class: 'ltour__read', text: t('Read the steps instead'), onclick: () => close(true) });
  const note = lang !== i18n.lang ? h('p', { class: 'tour__note', text: t('This guide is not yet narrated in your language, so it plays in English. Read the steps instead to see them in your language.') }) : null;
  const panel = h('div', { class: 'ltour__panel', role: 'dialog', 'aria-modal': 'false', 'aria-label': t('How to use WDOS'), dir },
    h('div', { class: 'ltour__top' }, h('div', { class: 'grow' }, bar, count), closeBtn), note, body, h('div', { class: 'tour__controls' }, prev, playBtn, next), readLink);
  const root = h('div', { class: 'ltour', dir }, shade, ring, finger, panel);
  running = { stop: () => close() };

  const stopSound = () => { token++; clearTimeout(timer); try { audio?.pause(); } catch { /* none */ } audio = null; };
  const clip = (src, my) => new Promise((res) => {
    if (my !== token) { res(false); return; }
    const a = new Audio(src); audio = a; let ok = true;
    a.onended = () => res(true); a.onerror = () => { ok = false; res(false); };
    a.play().catch(() => { ok = false; res(false); });
    setTimeout(() => { if (ok && a.paused && a.currentTime === 0) res(false); }, 9000);
  });
  const paintBtn = () => { playBtn.replaceChildren(icon(playing ? 'pause' : 'play', 18), h('span', { text: t(playing ? 'Pause' : (i === 0 ? 'Start the guide' : 'Play')) })); playBtn.setAttribute('aria-pressed', String(playing)); };
  // keep the ring on her real button while the page moves (scrolling, the menu sliding in, a slow phone)
  function track() {
    cancelAnimationFrame(raf);
    const loop = () => {
      if (target && target.isConnected && shown(target)) {
        const r = target.getBoundingClientRect(); const pad = 6;
        Object.assign(ring.style, { display: 'block', left: `${r.left - pad}px`, top: `${r.top - pad}px`, width: `${r.width + pad * 2}px`, height: `${r.height + pad * 2}px` });
        Object.assign(finger.style, { display: 'block', left: `${r.left + r.width / 2}px`, top: `${r.top + r.height / 2}px` });
        shade.style.display = 'none';
        const low = r.top + r.height / 2 > innerHeight * 0.5; panel.classList.toggle('is-top', low);
      } else { ring.style.display = 'none'; finger.style.display = 'none'; shade.style.display = i === 0 ? 'block' : 'none'; panel.classList.remove('is-top'); }
      raf = requestAnimationFrame(loop);
    };
    loop();
  }
  async function show(k) {
    const my = token; target = null;
    bar.firstChild.style.width = `${Math.round(((k + 1) / total) * 100)}%`;
    count.textContent = tf('Step {1} of {2}', k + 1, total);
    prev.disabled = k === 0; next.disabled = k >= total - 1; paintBtn();
    if (k === 0) {
      if (!sameRoute('#/')) { goTo('#/'); await sleep(500); }
      body.replaceChildren(h('div', { class: 'tour__hello' },
        h('img', { src: '/assets/logos/woddi-disc.png', alt: '', class: 'tour__logo', onerror: (e) => e.target.remove() }),
        app.user ? avatar(app.user, 72) : null,
        h('h2', { class: 'display', dir: 'auto' }, h('span', { text: String(man.hello || 'Hello,').replace(/[,،]\s*$/, '') + ' ' }), h('bdi', { text: name + '!' })),
        h('p', { dir: 'auto', text: [man.glad, man.intro].filter(Boolean).join(' ') })));
      return;
    }
    const s = scenes[k - 1]; const w = words[s.id];
    body.replaceChildren(h('p', { class: 'ltour__text', dir: 'auto', text: w.text }));
    if (s.r && !sameRoute(s.r)) { goTo(s.r); await sleep(450); }
    if (my !== token && !playing) return;
    if (s.open) {
      const link = await waitFor(s.open, 2500);
      if (link) { if (link.tagName === 'A') goTo(link.getAttribute('href')); else link.click(); await sleep(500); }
    }
    if (s.pre) { const b = find(s.pre); if (b) { b.click(); await sleep(450); } }
    const el = (await waitFor(s.sel, s.sel ? 3000 : 0)) || find(s.alt);
    if (k !== i) return;                                         // she moved on while the page was opening
    if (el) {
      let fixed = false; for (let n = el; n; n = n.parentElement) { const p = getComputedStyle(n).position; if (p === 'fixed' || p === 'sticky') { fixed = true; break; } }
      if (!fixed) el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      target = el; finger.classList.toggle('is-look', w.action !== 'tap');
    } else if (s.sel) body.append(h('p', { class: 'tour__note', text: t('This is not on your page right now, so there is nothing to point at. You will see it here when you need it.') }));
    else if (s.id === 'dash' || s.id === 'end') window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  async function speak() {
    stopSound(); const my = token; if (!playing) return;
    let ok;
    if (i === 0) { ok = await clip(voice + 'hello.mp3', my); if (my !== token) return; ok = await clip(voice + 'glad.mp3', my); }
    else {
      const nx = scenes[i]; if (nx) { const pre = new Audio(); pre.preload = 'auto'; pre.src = voice + kind + '-' + nx.id + '.mp3'; }   // the next line is ready, no pause to fetch it
      ok = await clip(voice + kind + '-' + scenes[i - 1].id + '.mp3', my);
    }
    if (my !== token || !playing) return;
    const n = (body.textContent || '').split(/\s+/).length;
    timer = setTimeout(() => { if (my !== token || !playing) return; if (i < total - 1) step(i + 1); else { playing = false; paintBtn(); } }, ok ? 500 : Math.max(4000, n * 400));
  }
  async function step(k, user) { if (k < 0 || k >= total) return; i = k; stopSound(); const shownAt = show(k); if (playing) { await shownAt; if (i === k) speak(); } else if (user) await shownAt; }
  function play() { if (i >= total - 1 && !playing) i = 0; playing = true; step(i); }
  function pause() { playing = false; stopSound(); paintBtn(); }
  function close(toSteps) {
    playing = false; stopSound(); cancelAnimationFrame(raf); root.remove(); running = null;
    document.removeEventListener('keydown', keys); document.body.classList.remove('in-ltour');
    document.querySelector('.shell')?.classList.remove('nav-open');
    if (first) api('/me/guide-seen', {}).then(() => { if (app.user) app.user.guide_seen = true; }).catch(() => {});
    if (!toSteps) goTo('#/');
  }
  const keys = (e) => {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') step(dir === 'rtl' ? i - 1 : i + 1, true);
    else if (e.key === 'ArrowLeft') step(dir === 'rtl' ? i + 1 : i - 1, true);
  };
  document.addEventListener('keydown', keys); document.body.append(root); document.body.classList.add('in-ltour');
  if (!scenes.length) body.replaceChildren(h('p', { text: t('The guided tour could not load. Check your connection, or read the steps instead.') }));
  track(); await show(0); playBtn.focus();
}

/* first sign-in: the tour opens on her own dashboard; it is marked as seen when she closes it */
export function openGuide(first) { return startTour({ first }); }

export async function render(root) {
  root.append(card(app.user?.is_envoy ? 'How to use WDOS as a WODDI Envoy' : app.user?.is_leader ? 'How to use WDOS as a volunteer leader' : 'How to use WDOS as a member',
    'Press Start. WDOS opens your own pages one by one, points at exactly what to tap, and reads each step aloud in your language.',
    h('div', { style: 'display:grid;gap:16px' },
      h('div', {}, h('button', { class: 'btn btn--lime', type: 'button', onclick: () => startTour({}) }, icon('play', 18), h('span', { text: t('Start the guided tour') }))),
      h('h3', { style: 'margin:0', text: t('The steps, written out') }), stepsList())));
}
