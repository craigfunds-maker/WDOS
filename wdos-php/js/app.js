/* WDOS front end entry: session, router, shell (collapsible sidebar, top bar, notification bell). */
import { h, clear, icon, avatar, toast, ago, theme, empty, i18n, t, langPicker, chime } from './ui.js';
import { pushSync } from './api.js';
import { api } from './api.js';

export const app = { user: null, config: { google: false, push: false, vapid: '', mail: false, build: '' }, unread: 0, latest: 0 };
const root = document.getElementById('app');
const store = { get: (k, d) => { try { return localStorage.getItem(k) ?? d; } catch { return d; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* ignore */ } } };

const KIND_ICON = { message: 'send', task: 'tasks', meeting: 'calendar', activation: 'route', welcome: 'award', registration: 'user', announcement: 'megaphone', appointment: 'award', account: 'shield', birthday: 'cake', reminder: 'clock', digest: 'chart', inspiration: 'spark', report: 'chart' };
export const kindIcon = (k) => KIND_ICON[k] || 'bell';

/* ---------------------------------------------------------------- routes -- */
const pages = {
  welcome: () => import('./pages/welcome.js'), login: () => import('./pages/login.js'),
  hq: () => import('./pages/hq.js'), members: () => import('./pages/members.js'), announcements: () => import('./pages/announcements.js'),
  leader: () => import('./pages/leader.js'), home: () => import('./pages/home.js'),
  reporting: () => import('./pages/reporting.js'), tasks: () => import('./pages/tasks.js'), meetings: () => import('./pages/meetings.js'), structure: () => import('./pages/structure.js'),
  onboarding: () => import('./pages/onboarding.js'), referrals: () => import('./pages/referrals.js'), activation: () => import('./pages/activation.js'), inspiration: () => import('./pages/inspiration.js'), messages: () => import('./pages/messages.js'), journey: () => import('./pages/journey.js'), community: () => import('./pages/community.js'), leadership: () => import('./pages/leadership.js'), reports: () => import('./pages/reports.js'), communications: () => import('./pages/communications.js'), institute: () => import('./pages/institute.js'), integrations: () => import('./pages/integrations.js'), invite: () => import('./pages/invite.js'), chapter: () => import('./pages/chapter.js'), chapters: () => import('./pages/chapters.js'), rules: () => import('./pages/rules.js'), library: () => import('./pages/library.js'), help: () => import('./pages/help.js'), voices: () => import('./pages/voices.js'), staff: () => import('./pages/staff.js'), cases: () => import('./pages/cases.js'), calendar: () => import('./pages/calendar.js'), coverage: () => import('./pages/coverage.js'), handoff: () => import('./pages/handoff.js'),
  notifications: () => import('./pages/notifications.js'), settings: () => import('./pages/settings.js'),
};
function resolve(path) {
  const seg = path.split('/').filter(Boolean); const u = app.user;
  const pub = (name, params = {}) => ({ name, params, shell: false });
  if (!u) {
    if (seg[0] === 'login') return pub('login');
    if (seg[0] === 'reset') return pub('login', { reset: seg[1] });
    if (seg[0] === 'r' && seg[1]) return pub('welcome', { ref: seg[1] });
    if (seg[0] === 'join' && seg[1]) return pub('welcome', { network: seg[1], leader: true });
    return pub('welcome', { network: '' });
  }
  if (u.status === 'pending_profile') return pub('welcome', { complete: true, network: u.network || '' });
  const sh = (name, title, params = {}) => ({ name, title, params, shell: true });
  if (u.is_hq && u.must_change_password) return sh('settings', 'Choose your own password');      // a staff login made with a temporary password opens nothing else until it is changed
  switch (seg[0]) {
    case 'leaders': if (!u.is_hq) return sh(u.is_leader ? 'leader' : 'home', u.is_leader ? 'Leadership dashboard' : 'Home');
      return !seg[1] ? sh('leader', 'Leader dashboards', { pick: true }) : seg[2] === 'members' ? sh('members', 'Members in this leader\'s area', { scope: 'leader', as: seg[1] }) : sh('leader', 'Leader dashboard', { as: seg[1] });
    case 'members': return u.is_hq ? sh('members', 'Members', { id: seg[1] }) : u.is_leader ? sh('members', 'My members', { scope: 'leader' }) : sh('home', 'Home');
    case 'announcements': return sh('announcements', 'Announcements', { id: seg[1] });
    case 'reporting': return (u.is_hq || u.is_leader) ? sh('reporting', 'Reporting and accountability', { tab: seg[1], id: seg[2] }) : sh('home', 'Home');
    case 'inspiration': return u.is_hq ? sh('inspiration', 'Inspiration messages', { tab: seg[1] }) : sh(u.is_leader ? 'leader' : 'home', u.is_leader ? 'Leadership dashboard' : 'Home');
    case 'messages': return sh('messages', u.is_hq ? 'Messages' : 'Messages with HQ', { tab: seg[1], id: seg[2] });
    case 'library': return sh('library', 'Document library');
    case 'journey': return sh('journey', 'My seven-day journey');
    case 'community': return sh('community', 'WODDI Community', { tab: seg[1] });
    case 'leadership': return sh('leadership', 'Leadership', { tab: seg[1] });
    case 'reports': return (u.is_hq || (u.is_leader && !u.he_for_she)) ? sh('reports', 'Reports') : sh('home', 'Home');
    case 'communications': return u.is_hq ? sh('communications', 'Communications') : sh('announcements', 'Announcements', {});
    case 'institute': return sh('institute', 'Institute');
    case 'integrations': return u.is_hq ? sh('integrations', 'Integrations') : sh('home', 'Home');
    case 'invite': return sh('invite', 'My 3 referrals');
    case 'chapter': return sh('chapter', seg[1] ? 'Chapter' : 'My chapter', { id: seg[1] });
    case 'chapters': return (u.is_hq || u.is_leader || u.zone_supervisor) ? sh('chapters', 'Chapters') : sh('chapter', 'My chapter', {});
    case 'rules': return u.is_hq ? sh('rules', 'System rules') : sh('home', 'Home');
    case 'help': return sh('help', 'Help and private reports');
    case 'voices': return u.is_hq ? sh('voices', 'Guide voices') : sh('home', 'Home');
    case 'staff': return u.is_hq ? sh('staff', 'Staff and logins') : sh('home', 'Home');
    case 'calendar': return sh('calendar', 'Annual calendar');
    case 'cases': return u.is_hq ? sh('cases', 'Confidential cases') : sh('help', 'Help and private reports');
    case 'coverage': return u.is_hq ? sh('coverage', 'Digital coverage') : sh('home', 'Home');
    case 'handoff': return u.is_hq ? sh('handoff', 'Handoff register') : sh('home', 'Home');
    case 'tasks': return sh('tasks', 'Tasks', { tab: seg[1] });
    case 'meetings': return sh('meetings', 'Meetings', { tab: seg[1] });
    case 'structure': return (u.is_hq || u.is_leader) ? sh('structure', 'Leadership structure') : sh('home', 'Home');
    case 'referrals': return (u.is_hq || u.is_leader) ? sh('referrals', 'Referral and activation', { tab: seg[1], id: seg[2] }) : sh('home', 'Home');
    case 'onboarding': return (u.is_hq || u.is_leader) ? sh('onboarding', 'Onboarding', { user: seg[1] }) : sh('home', 'Home');
    case 'appointment': return sh('onboarding', 'Your appointment', { appointment: true });
    case 'activation': return sh('activation', 'My Activation');
    case 'notifications': return sh('notifications', 'Notifications');
    case 'settings': return sh('settings', 'Settings');
    case 'login': case 'join': case 'r': location.hash = '#/'; // signed in already
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
  if (r.shell) { if (!shellEls) buildShell(); shellEls.title.textContent = t(r.title); markNav(path); outlet = shellEls.page; shellEls.shell.classList.remove('nav-open'); }
  else { shellEls = null; clear(root); outlet = root; }
  document.title = (r.title ? t(r.title) + ' · ' : '') + 'WDOS';
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
export async function setLanguage(lang) { await i18n.set(lang); if (app.user) api('/me/lang', { lang }).catch(() => {}); shellEls = null; render(); }
export async function signOut() { stopPulse(); await api('/auth/logout', {}).catch(() => {}); try { localStorage.removeItem('wdos.join'); sessionStorage.clear(); if (window.caches) { for (const k of await caches.keys()) if (/api|data|runtime/i.test(k)) await caches.delete(k); } } catch { /* nothing kept */ } app.user = null; shellEls = null; go('#/login'); }
export function signedIn(user) { app.user = user; shellEls = null; startPulse(); pushSync(app.config.vapid); go('#/'); }

/* ----------------------------------------------------------------- shell -- */
function navItems() {
  const u = app.user; const comm = [['Communication'], ['#/community', 'spark', 'WODDI Community'], ['#/institute', 'award', 'Institute'], ['#/messages', 'send', u.is_hq ? 'Messages' : 'Messages with HQ'], ['#/announcements', 'megaphone', 'Announcements'], ['#/library', 'book', 'Document library'], ['#/notifications', 'bell', 'Notifications'], ['Account'], ['#/help', 'book', 'Help and private reports'], ['#/settings', 'sliders', 'Settings']];
  if (u.is_hq) return [['Overview'], ['#/', 'grid', 'Dashboard'], ['#/members', 'users', 'Members'], ['#/leaders', 'eye', 'Leader dashboards'], ['#/leadership', 'tree', 'Leadership'], ['#/structure', 'users', 'Who holds each seat'], ['#/chapters', 'grid', 'Chapters'], ['#/referrals', 'route', 'Referral and activation'], ['Work'], ['#/reports', 'chart', 'Reports'], ['#/reporting', 'book', 'Leader reports'], ['#/tasks', 'tasks', 'Tasks'], ['#/meetings', 'calendar', 'Meetings'], ['#/calendar', 'calendar', 'Annual calendar'], ['Communication'], ['#/communications', 'megaphone', 'Communications'], ['#/inspiration', 'award', 'Inspiration messages'], ...comm.slice(1, -3), ['Administration'], ['#/staff', 'users', 'Staff and logins'], ['#/voices', 'volume', 'Guide voices'], ['#/rules', 'sliders', 'System rules'], ['#/handoff', 'check', 'Handoff register'], ['#/coverage', 'chart', 'Digital coverage'], ['#/cases', 'eye', 'Confidential cases'], ['#/integrations', 'link', 'Integrations'], ...comm.slice(-3)];
  if (u.is_leader) return [['My leadership'], ['#/', 'grid', 'Dashboard'], ['#/members', 'users', 'My members'], ['#/chapters', 'grid', 'Chapters'], u.chapter || (u.seat && u.seat.level === 'chapter') ? ['#/chapter', 'users', 'My chapter'] : null, ['#/leadership', 'tree', 'Leadership'], ['#/structure', 'users', 'Who holds each seat'], ['#/referrals', 'route', 'Referral links'], ['#/onboarding', 'book', 'Onboarding'], ['Work'], u.he_for_she ? null : ['#/reports', 'chart', 'Reports'], ['#/reporting', 'book', 'My reports'], ['#/tasks', 'tasks', 'Tasks'], ['#/meetings', 'calendar', 'Meetings'], ['#/calendar', 'calendar', 'Annual calendar'], ...comm].filter(Boolean);
  return [['My network'], ['#/', 'grid', 'Home'], u.journey ? ['#/journey', 'route', 'My seven-day journey'] : null, ['#/chapter', 'users', 'My chapter'], ['#/invite', 'send', 'My 3 referrals'], ['#/leadership', 'tree', 'Leadership'], u.zone_supervisor ? ['#/chapters', 'grid', 'My zone chapters'] : null, u.todo && u.todo.activation ? ['#/activation', 'route', 'My Activation'] : null, ['#/tasks', 'tasks', 'Tasks'], ['#/meetings', 'calendar', 'Meetings'], ['#/calendar', 'calendar', 'Annual calendar'], ...comm].filter(Boolean);
}
function markNav(path) {
  const first = '#/' + (path.split('/').filter(Boolean)[0] || '');
  [...shellEls.nav.querySelectorAll('a'), ...shellEls.tabbar.querySelectorAll('a')].forEach((a) => { if (a.getAttribute('href') === first) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
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
    h('div', { class: 'side__motto' }, h('b', { text: 'No Woman Is Left Out' }), h('span', { text: 'Low-data · Chapter-first · Automated support' })),
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
    h('header', { class: 'top' }, h('button', { class: 'iconbtn top__menu', type: 'button', 'aria-label': 'Open the menu', 'aria-controls': 'side', onclick: () => shell.classList.add('nav-open') }, icon('menu')), title, h('div', { class: 'grow' }), langPicker(setLanguage), themeBtn, bellWrap, meWrap),
    h('main', {}, page));
  /* On a phone the main destinations sit in a bar at the bottom, as in a native app. "More" opens the full menu. */
  const tabs = u.is_hq ? [['#/', 'grid', 'Dashboard'], ['#/members', 'users', 'Members'], ['#/reporting', 'chart', 'Reporting'], ['#/notifications', 'bell', 'Alerts']]
    : u.is_leader ? [['#/', 'grid', 'Dashboard'], ['#/tasks', 'tasks', 'Tasks'], ['#/reporting', 'chart', 'Reporting'], ['#/notifications', 'bell', 'Alerts']]
    : [['#/', 'grid', 'Home'], ['#/chapter', 'users', 'Chapter'], ['#/messages', 'send', 'Messages'], ['#/notifications', 'bell', 'Alerts']];
  const tabbar = h('nav', { class: 'tabbar', 'aria-label': 'Quick menu' }, tabs.map((it) => h('a', { href: it[0] }, h('span', { class: 'tabbar__ico' }, icon(it[1], 22), it[0] === '#/notifications' ? h('i', { class: 'tabbar__n', hidden: true, 'data-unread': '' }) : null), h('span', { text: it[2] }))),
    h('button', { type: 'button', 'aria-controls': 'side', onclick: () => shell.classList.add('nav-open') }, h('span', { class: 'tabbar__ico' }, icon('menu', 22)), h('span', { text: 'More' })));
  shell.append(side, h('div', { class: 'scrim', onclick: () => shell.classList.remove('nav-open') }), main, tabbar);
  root.append(shell);
  shellEls = { shell, nav, title, page, tabbar };
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
    if (announce && app.latest && r.fresh.length) chime();
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
  await i18n.load(i18n.saved());
  try {
    const [cfg, me] = await Promise.all([api('/config'), api('/me')]);
    app.config = cfg; app.user = me.user;
    if (app.user) pushSync(cfg.vapid);
  } catch (e) {
    clear(root).append(h('div', { class: 'public' }, h('div', { class: 'signin' }, h('div', { class: 'card' }, empty('x', 'WDOS cannot start', e.message, h('button', { class: 'btn', onclick: () => location.reload() }, 'Reload'))))));
    return;
  }
  if (app.user) startPulse();
  render();
})();
