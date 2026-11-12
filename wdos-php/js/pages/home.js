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
  /* the member's own dashboard: chapter, journey, referrals, learning */
  const tiles = h('div', { class: 'row row--4' }); root.append(tiles);
  const tile = (ic, label, value, foot, href, ext) => h('a', { class: 'card kpi kpi--link', href, ...(ext ? { target: '_blank', rel: 'noopener' } : {}) }, h('div', { class: 'kpi__label' }, icon(ic, 17), h('span', { text: label })), h('div', { class: 'kpi__value', style: String(value).length > 6 ? 'font-size:21px;line-height:1.25;letter-spacing:0' : '', text: String(value) }), h('div', { class: 'kpi__foot' }, h('span', { text: foot })));
  const jr = u.journey; const paintTiles = (ref) => tiles.replaceChildren(
    tile('users', 'My chapter', u.chapter ? u.chapter.name : 'Pending', u.chapter ? `${u.chapter.members} women in your chapter` : 'Open to see what is needed', '#/chapter'),
    tile('route', 'Seven-day journey', jr ? (jr.status === 'active' ? 'Active' : `${jr.done}/${jr.total}`) : 'Active', jr ? (jr.status === 'active' ? 'You completed your seven days' : 'Steps completed') : 'Member before the journey began', '#/journey'),
    tile('send', 'Referral progress', ref ? `${ref.registered}/${ref.target}` : '0/3', ref ? `${ref.verified} completed their seven days` : 'Invite three women', '#/invite'),
    tile('book', 'Learning', 'Institute', 'Courses and the volunteer pathway', '#/institute'));
  root.append(h('a', { class: 'card nudge', href: '#/community', style: 'text-decoration:none;color:inherit' }, h('div', { class: 'card__body', style: 'display:flex;gap:14px;align-items:center' }, h('div', { class: 'nrow__ico' }, icon('spark', 18)), h('div', { class: 'grow' }, h('b', { text: 'WODDI Community' }), h('p', { class: 'muted', style: 'margin:2px 0 0', text: 'Your chapter, your network at every level, topic circles and events.' })))));
  paintTiles(null); api('/my/referrals').then((d) => paintTiles(d.referrals)).catch(() => {});
  if (jr && jr.status !== 'active') root.append(h('section', { class: 'card nudge' }, h('div', { class: 'card__body', style: 'display:flex;gap:16px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'grow' }, h('b', { text: 'Your next step is ready' }), h('p', { class: 'muted', style: 'margin:4px 0 0', text: 'One short step each day for seven days. You can read it or listen to it.' })), h('a', { class: 'btn', href: '#/journey', text: 'Continue my seven days' }))));
  const LN = { ar: 'Arabic', en: 'English', fr: 'French', pt: 'Portuguese', sw: 'Swahili' };
  const go = (label, href, done) => h('a', { class: 'list__link', href }, h('span', { class: 'step__dot' + (done ? ' is-done' : ''), style: done ? 'background:var(--lime);color:#12210A;border-color:transparent' : '' }, done ? icon('check', 15) : null), h('div', { class: 'grow' }, h('b', { text: label })), h('span', { class: 'btn btn--ghost btn--s', text: 'Open' }));
  root.append(h('div', { class: 'row row--2' },
    card('Your next steps', null, h('div', { class: 'list' }, go('Continue your seven-day journey', '#/journey', jr ? jr.status === 'active' : true), go('Invite three friends to join WODDI', '#/invite', false), go('Visit your chapter community', '#/community', false)), h('span', { class: 'tag tag--wgmn', text: 'Priority' })),
    h('div', { style: 'display:grid;gap:18px;align-content:start' },
      card('My membership', null, h('div', { class: 'kv' }, [['Network', u.network], ['Role', u.seat ? u.seat.title : u.volunteer ? 'Member and volunteer' : 'Member'], ['Chapter', u.chapter ? u.chapter.name : 'Pending placement'], ['Language', LN[u.lang] || 'English'], ['Status', jr && jr.status !== 'active' ? 'On your seven days' : 'Active'], ['Person ID', u.person_id]].map(([a, b]) => h('div', {}, h('span', { text: a }), h('b', { text: b || '' }))))),
      card('Quick links', null, h('div', { class: 'list' }, go('WODDI Institute', '#/institute', false), go('WODDI Community', '#/community', false), go('Chapters in my community', '#/chapter', false), go('Need help? Write to WODDI HQ', '#/messages/new', false))))));
  root.append(pushNudge());
  if (u.network === 'WGMN' && u.country_code) root.append(goodMotherCard(u.country_code, u.country));
  const today = h('div', { hidden: true }); root.append(today);
  api('/inspiration/today?lang=' + document.documentElement.lang).then((r) => { if (r.card) { today.hidden = false; today.append(inspCard(r.card)); } }).catch(() => {});
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
