/* WDOS front end entry: session, router, shell (collapsible sidebar, top bar, notification bell). */
import { h, clear, icon, avatar, toast, ago, theme, empty } from './ui.js';
import { api } from './api.js';

export const app = { user: null, config: { google: false, push: false, vapid: '', mail: false, build: '' }, unread: 0, latest: 0 };
const root = document.getElementById('app');
const store = { get: (k, d) => { try { return localStorage.getItem(k) ?? d; } catch { return d; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* ignore */ } } };

const KIND_ICON = { welcome: 'award', registration: 'user', announcement: 'megaphone', appointment: 'award', account: 'shield', birthday: 'cake', reminder: 'clock', digest: 'chart', inspiration: 'spark' };
export const kindIcon = (k) => KIND_ICON[k] || 'bell';

/* ---------------------------------------------------------------- routes -- */
const pages = {
  welcome: () => import('./pages/welcome.js'), login: () => import('./pages/login.js'),
  hq: () => import('./pages/hq.js'), members: () => import('./pages/members.js'), announcements: () => import('./pages/announcements.js'),
  leader: () => import('./pages/leader.js'), home: () => import('./pages/home.js'),
  notifications: () => import('./pages/notifications.js'), settings: () => import('./pages/settings.js'), inspiration: () => import('./pages/inspiration.js'),
};
function resolve(path) {
  const seg = path.split('/').filter(Boolean); const u = app.user;
  const pub = (name, params = {}) => ({ name, params, shell: false });
  if (!u) {
    if (seg[0] === 'login') return pub('login');
    if (seg[0] === 'reset') return pub('login', { reset: seg[1] });
    return pub('welcome', { network: (seg[0] === 'join' ? seg[1] : '') || '' });
  }
  if (u.status === 'pending_profile') return pub('welcome', { complete: true, network: u.network || '' });
  const sh = (name, title, params = {}) => ({ name, title, params, shell: true });
  switch (seg[0]) {
    case 'members': return u.is_hq ? sh('members', 'Members', { id: seg[1] }) : u.is_leader ? sh('members', 'My members', { scope: 'leader' }) : sh('home', 'Home');
    case 'announcements': return sh('announcements', 'Announcements', { id: seg[1] });
    case 'inspiration': if (u.is_leader && !u.is_hq) return sh('inspiration', 'Inspiration'); location.hash = '#/'; return u.is_hq ? sh('hq', 'HQ dashboard') : sh('home', 'Home');
    case 'notifications': return sh('notifications', 'Notifications');
    case 'settings': return sh('settings', 'Settings');
    case 'login': case 'join': location.hash = '#/'; // signed in already
    // falls through
    default: return u.is_hq ? sh('hq', 'HQ dashboard') : u.is_leader ? sh('leader', 'Leadership dashboard') : sh('home', 'Home');
  }
}
let shellEls = null, navToken = 0;
async function render() {
  const token = ++navToken;
  const path = (location.hash || '#/').slice(1).split('?')[0];
  const r = resolve(path);
  let outlet;
  if (r.shell) { if (!shellEls) buildShell(); shellEls.title.textContent = r.title; markNav(path); outlet = shellEls.page; shellEls.shell.classList.remove('nav-open'); }
  else { shellEls = null; clear(root); outlet = root; }
  document.title = (r.title ? r.title + ' · ' : '') + 'WDOS';
  try {
    const mod = await pages[r.name]();
    if (token !== navToken) return;                    // a newer navigation won
    clear(outlet); window.scrollTo(0, 0);
    await mod.render(outlet, r.params);
  } catch (e) {
    if (token !== navToken) return;
    console.error(e); clear(outlet);
    outlet.append(h('div', { class: 'card' }, empty('x', 'This page did not load', e.message || 'Check your connection and try again.', h('button', { class: 'btn btn--quiet', onclick: render }, 'Try again'))));
  }
}
export const go = (hash) => { if (location.hash === hash) render(); else location.hash = hash; };
export async function refreshUser() { app.user = (await api('/me')).user; return app.user; }
export async function signOut() { stopPulse(); await api('/auth/logout', {}).catch(() => {}); app.user = null; shellEls = null; go('#/login'); }
export function signedIn(user) { app.user = user; shellEls = null; startPulse(); go('#/'); }

/* ----------------------------------------------------------------- shell -- */
function navItems() {
  const u = app.user;
  if (u.is_hq) return [['Overview'], ['#/', 'grid', 'Dashboard'], ['#/members', 'users', 'Members'], ['Communication'], ['#/announcements', 'megaphone', 'Announcements'], ['#/notifications', 'bell', 'Notifications'], ['Account'], ['#/settings', 'sliders', 'Settings']];
  if (u.is_leader) return [['My leadership'], ['#/', 'grid', 'Dashboard'], ['#/members', 'users', 'My members'], ['Communication'], ['#/announcements', 'megaphone', 'Announcements'], ['#/inspiration', 'spark', 'Inspiration'], ['#/notifications', 'bell', 'Notifications'], ['Account'], ['#/settings', 'sliders', 'Settings']];
  return [['My network'], ['#/', 'grid', 'Home'], ['#/announcements', 'megaphone', 'Announcements'], ['#/notifications', 'bell', 'Notifications'], ['Account'], ['#/settings', 'sliders', 'Settings']];
}
function markNav(path) {
  const first = '#/' + (path.split('/').filter(Boolean)[0] || '');
  shellEls.nav.querySelectorAll('a').forEach((a) => { if (a.getAttribute('href') === first) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
}
function buildShell() {
  clear(root); const u = app.user;
  const disc = u.network === 'WNNN' ? '/assets/logos/wnnn-disc.png' : '/assets/logos/wgmn-disc.png';
  const nav = h('nav', { class: 'nav', 'aria-label': 'Main' }, navItems().map((it) => it.length === 1 ? h('div', { class: 'nav__group', text: it[0] })
    : h('a', { href: it[0], 'data-tip': it[2] }, icon(it[1]), h('span', { text: it[2] }), it[0] === '#/notifications' ? h('i', { class: 'nav__count', hidden: true, 'data-unread': '' }) : null)));
  const shell = h('div', { class: 'shell' + (store.get('wdos.side', '') === 'min' ? ' is-min' : '') });
  const toggle = h('button', { class: 'side__toggle', type: 'button', 'aria-label': 'Collapse or expand the menu', onclick: () => { const min = shell.classList.toggle('is-min'); store.set('wdos.side', min ? 'min' : 'full'); toggle.setAttribute('aria-expanded', String(!min)); window.dispatchEvent(new Event('resize')); } }, icon('collapse'), h('span', { text: 'Collapse menu' }));
  toggle.setAttribute('aria-expanded', String(!shell.classList.contains('is-min')));
  const side = h('aside', { class: 'side', id: 'side' },
    h('div', { class: 'side__brand' }, h('img', { class: 'side__mark', src: u.is_hq ? '/assets/icons/icon-192.png' : disc, alt: '' }), h('div', { class: 'side__word' }, h('b', { text: 'WDOS' }), h('span', { text: u.is_hq ? 'WODDI Headquarters' : (u.network || '') + ' network' }))),
    nav,
    h('div', { class: 'side__foot' }, h('a', { class: 'side__me', href: '#/settings', style: 'color:inherit;text-decoration:none' }, avatar(u, 38), h('div', {}, h('b', { text: `${u.first_name} ${u.last_name}` }), h('small', { text: u.seat ? u.seat.title : u.is_hq ? 'HQ administrator' : u.member_no || '' }))), toggle));
  const title = h('h1', {});
  const badge = h('i', { class: 'badge', hidden: true, 'data-unread': '' });
  const bellWrap = h('div', { style: 'position:relative' });
  const bell = h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Notifications', 'aria-haspopup': 'true', onclick: (e) => { e.stopPropagation(); togglePop(bellWrap, bellPanel); } }, icon('bell'), badge);
  bellWrap.append(bell);
  const meWrap = h('div', { style: 'position:relative' });
  meWrap.append(h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Your account', onclick: (e) => { e.stopPropagation(); togglePop(meWrap, mePanel); } }, avatar(u, 34)));
  const themeBtn = h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Switch between dark and light', onclick: () => { theme.toggle(); themeBtn.replaceChildren(icon(theme.get() === 'light' ? 'moon' : 'sun')); } }, icon(theme.get() === 'light' ? 'moon' : 'sun'));
  const page = h('div', { class: 'page', id: 'page' });
  const main = h('div', { class: 'main' },
    h('header', { class: 'top' }, h('button', { class: 'iconbtn top__menu', type: 'button', 'aria-label': 'Open the menu', 'aria-controls': 'side', onclick: () => shell.classList.add('nav-open') }, icon('menu')), title, h('div', { class: 'grow' }), themeBtn, bellWrap, meWrap),
    h('main', {}, page));
  shell.append(side, h('div', { class: 'scrim', onclick: () => shell.classList.remove('nav-open') }), main);
  root.append(shell);
  shellEls = { shell, nav, title, page };
  paintUnread();
}
let openPop = null;
function closePop() { openPop?.remove(); openPop = null; }
function togglePop(anchor, build) { const was = openPop && openPop.parentNode === anchor; closePop(); if (was) return; openPop = build(); anchor.append(openPop); }
document.addEventListener('click', (e) => { if (openPop && !openPop.contains(e.target)) closePop(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closePop(); shellEls?.shell.classList.remove('nav-open'); } });

function mePanel() {
  const u = app.user;
  return h('div', { class: 'pop menu', style: 'width:240px' }, h('div', { style: 'padding:10px 12px 12px;border-bottom:1px solid var(--line-soft);margin-bottom:6px' }, h('b', { text: `${u.first_name} ${u.last_name}` }), h('div', { class: 'faint', style: 'font-size:13px;overflow-wrap:anywhere', text: u.email })),
    h('a', { href: '#/settings', onclick: closePop }, icon('sliders', 18), 'Settings'), h('button', { type: 'button', onclick: () => { closePop(); signOut(); } }, icon('logout', 18), 'Sign out'));
}
export function notifRow(n, onOpen) {
  return h('a', { class: 'nrow' + (n.read_at ? '' : ' is-new'), href: n.url || '#/notifications', onclick: () => { if (!n.read_at) api('/notifications/read', { ids: [n.id] }).then((r) => setUnread(r.unread)).catch(() => {}); onOpen && onOpen(); } },
    h('div', { class: 'nrow__ico' }, icon(kindIcon(n.kind), 18)), h('div', {}, h('b', { text: n.title }), n.body ? h('p', { text: n.body }) : null, h('time', { text: ago(n.created_at) })));
}
function bellPanel() {
  const list = h('div', { class: 'pop__list' }, h('div', { style: 'padding:8px 16px' }, Array.from({ length: 3 }, () => h('div', { class: 'sk sk--line', style: 'height:44px;margin:10px 0' }))));
  const pop = h('div', { class: 'pop', role: 'dialog', 'aria-label': 'Notifications' }, h('div', { class: 'pop__head' }, h('b', { text: 'Notifications' }),
    h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { const r = await api('/notifications/read', {}); setUnread(r.unread); list.querySelectorAll('.is-new').forEach((x) => x.classList.remove('is-new')); } }, 'Mark all read')), list,
    h('div', { style: 'padding:10px 16px;text-align:center' }, h('a', { href: '#/notifications', onclick: closePop, text: 'See all notifications' })));
  api('/notifications').then((r) => { clear(list); setUnread(r.unread);
    if (!r.items.length) list.append(empty('bell', 'Nothing yet', 'Announcements, appointments and reminders will appear here.'));
    else r.items.slice(0, 8).forEach((n) => list.append(notifRow(n, closePop)));
  }).catch((e) => { clear(list); list.append(empty('x', 'Could not load', e.message)); });
  return pop;
}

/* ------------------------------------------------- in-app notifications -- */
function paintUnread() { document.querySelectorAll('[data-unread]').forEach((b) => { b.hidden = app.unread < 1; b.textContent = app.unread > 99 ? '99+' : String(app.unread); }); if (navigator.setAppBadge) (app.unread ? navigator.setAppBadge(app.unread) : navigator.clearAppBadge()).catch(() => {}); }
export function setUnread(n) { app.unread = Number(n) || 0; paintUnread(); }
let pulseTimer = null;
async function pulse(announce = true) {
  if (!app.user || document.hidden) return;
  try {
    const r = await api('/notifications/pulse?after=' + app.latest);
    setUnread(r.unread);
    if (announce && app.latest) r.fresh.slice().reverse().forEach((n) => toast(n.title, n.body, 'info', () => go(n.url || '#/notifications')));
    if (r.fresh.length) window.dispatchEvent(new CustomEvent('wdos:notified', { detail: r.fresh }));
    app.latest = Math.max(app.latest, r.latest);
  } catch { /* offline or signed out: the next tick tries again */ }
}
function startPulse() { stopPulse(); app.latest = 0; pulse(false); pulseTimer = setInterval(pulse, 25000); }
function stopPulse() { clearInterval(pulseTimer); pulseTimer = null; app.unread = 0; }
document.addEventListener('visibilitychange', () => { if (!document.hidden) pulse(); });
navigator.serviceWorker?.addEventListener('message', (e) => {
  if (e.data?.type === 'wdos:push') pulse();                       // a push landed while the app is open: refresh the bell now
  if (e.data?.type === 'wdos:new-build') { try { if (sessionStorage.getItem('wdos.build') !== e.data.build) { sessionStorage.setItem('wdos.build', e.data.build); location.reload(); } } catch { /* ignore */ } }
});
window.addEventListener('wdos:signed-out', () => { if (app.user) { app.user = null; shellEls = null; stopPulse(); toast('You have been signed out', 'Sign in again to continue.', 'bad'); go('#/login'); } });

/* ------------------------------------------------------------------ boot -- */
window.addEventListener('hashchange', render);
(async function boot() {
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  try {
    const [cfg, me] = await Promise.all([api('/config'), api('/me')]);
    app.config = cfg; app.user = me.user;
  } catch (e) {
    clear(root).append(h('div', { class: 'public' }, h('div', { class: 'signin' }, h('div', { class: 'card' }, empty('x', 'WDOS cannot start', e.message, h('button', { class: 'btn', onclick: () => location.reload() }, 'Reload'))))));
    return;
  }
  if (app.user) startPulse();
  render();
})();
