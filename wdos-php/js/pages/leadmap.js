/* v109.46: the leadership map. Choose a country, a state or region, an LGA or district, a ward or community cluster and a chapter, and see
 * every leadership seat there, filled or vacant, the leaders above, and the places below with their own seats.
 * HQ sees everything. A leader sees her own area and the leadership above her. A State Envoy sees her state in both networks and shares a
 * link for each vacant seat. The server decides all of this; this page only draws what it is given. */
import { h, t, tf, icon, avatar, avatarOn, flag, netTag, card, sk, empty, agoIn, fmt, fmtDate, toast, tabs } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { kpi } from './hq.js';

const KEYS = ['cc', 'state', 'lga', 'comm', 'chapter'];
const STATUS = { acting: ['Acting', 'tag--wgmn'], pending: ['Pending', ''], on_leave: ['On leave', ''], suspended: ['Suspended', 'tag--bad'] };
export const query = () => new URLSearchParams((location.hash.split('?')[1] || ''));
export const mapUrl = (place, extra = {}) => { const q = new URLSearchParams(); KEYS.forEach((k) => { if (place && place[k]) q.set(k, place[k]); }); Object.entries(extra).forEach(([k, v]) => { if (v) q.set(k, v); }); const s = q.toString(); return '#/leadmap' + (s ? '?' + s : ''); };
const pct = (v) => v === null || v === undefined ? '–' : v + '%';
const roomChatUrl = (net, place) => { const p = new URLSearchParams({ network: net }); ['cc', 'state', 'lga', 'comm'].forEach((k) => place[k] && p.set(k, place[k])); return '#/rooms/r?' + p.toString(); };
export const seen = (x) => x ? tf('Seen {1}', agoIn(x)) : t('Not signed in yet');

/** How she came: "Referred by Ada (a WODDI Envoy) · 3-Day Referral Activation, completed". */
export function cameBy(j) {
  if (!j) return t('Appointed by WODDI HQ');
  const parts = [];
  if (j.referral) parts.push(j.referral.by ? tf('Referred by {1}', j.referral.by) + (j.referral.category_label ? ' (' + t(j.referral.category_label) + ')' : '') : t(j.referral.category_label || 'Referred'));
  if (j.activation) parts.push(t(j.activation.route_label) + ', ' + t({ completed: 'completed', in_progress: 'in progress', submitted: 'submitted', returned: 'returned', incomplete: 'not finished' }[j.activation.status] || j.activation.status));
  return parts.length ? parts.join(' · ') : t('Appointed by WODDI HQ');
}

/** Her figures in one line of small tiles. */
export function miniKpis(k) {
  if (!k) return null;
  const tile = (label, v, bad) => h('div', { class: 'lm-mk' + (bad ? ' is-bad' : '') }, h('b', { text: String(v) }), h('small', { text: label }));
  return h('div', { class: 'lm-mks' },
    tile('Leaders brought', k.leaders_brought), tile('Meetings attended', k.meetings_attended), tile('On time', pct(k.punctuality)),
    tile('Reports on time', `${k.reports_on_time}/${k.reports_due}`, k.reports_overdue > 0), tile('Tasks done on time', `${k.tasks_on_time}/${k.tasks}`, k.tasks_overdue > 0),
    tile('Programmes', k.programmes), tile('Score', k.score));
}

function seatCard(s, d, onLink) {
  const net = netTag(s.network);
  if (!s.filled) {
    return h('div', { class: 'lm-seat is-vacant' },
      h('div', { class: 'lm-seat__top' }, h('div', { class: 'lm-vac', 'aria-hidden': 'true' }, icon('user', 22)), h('div', { class: 'grow' }, h('b', { text: s.title }), h('div', { class: 'tagrow', style: 'justify-content:flex-start' }, net, h('span', { class: 'tag tag--bad', text: 'Vacant' })))),
      s.candidates ? h('p', { class: 'faint', style: 'margin:0', text: tf('{1} candidate(s) in activation for this seat', s.candidates) }) : h('p', { class: 'faint', style: 'margin:0', text: 'Nobody holds this seat yet.' }),
      d.can_link ? h('button', { class: 'btn btn--primary btn--s', type: 'button', onclick: () => onLink(s) }, icon('link', 16), h('span', { text: 'Get the referral link for this seat' })) : null);
  }
  const p = s.person; const st = STATUS[s.status];
  return h('div', { class: 'lm-seat' },
    h('div', { class: 'lm-seat__top' }, avatarOn(p, 52), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('div', { class: 'faint', text: s.title }),
      h('div', { class: 'tagrow', style: 'justify-content:flex-start;margin-top:4px' }, net, st ? h('span', { class: 'tag ' + st[1], text: st[0] }) : null, s.he_for_she ? h('span', { class: 'tag tag--wnnn', text: 'He-for-She Champion' }) : null,
        s.acceptance === 'pending' ? h('span', { class: 'tag', text: 'Agreement not accepted' }) : null))),
    h('dl', { class: 'lm-facts' },
      h('dt', { text: 'Comes from' }), h('dd', { text: p.from || '–' }),
      h('dt', { text: 'How she came' }), h('dd', { text: cameBy(s.journey) }),
      h('dt', { text: 'Serving since' }), h('dd', { text: s.since ? fmtDate(s.since) : '–' }),
      h('dt', { text: 'Last on WDOS' }), h('dd', { text: seen(p.last_seen_at) + (p.last_seen_at && Date.now() - new Date(p.last_seen_at.replace(' ', 'T') + 'Z') < 300000 ? ' · ' + t('online now') : '') })),
    miniKpis(s.kpis),
    d.kpis_on ? h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
      h('a', { class: 'btn btn--quiet btn--s', href: '#/leader-file/' + p.id }, icon('eye', 16), h('span', { text: 'Open her activity file' })),
      d.role === 'hq' ? h('a', { class: 'btn btn--ghost btn--s', href: '#/leaders/' + p.id, text: 'Dashboard' }) : null,
      p.phone ? h('a', { class: 'btn btn--ghost btn--s', href: 'tel:' + p.phone }, icon('phone', 16), h('span', { text: 'Call' })) : null) : null);
}

function linkBox(link) {
  const msg = tf('WODDI is looking for a {1} for {2}. If this is you, or someone you know, join through this link: {3}', link.title, link.place_name, link.url);
  const copy = async () => { try { await navigator.clipboard.writeText(link.url); toast(t('Link copied'), link.url, 'ok'); } catch { toast(t('Copy this link'), link.url); } };
  return h('div', { class: 'lm-link' },
    h('b', { text: tf('Your link for {1}, {2}', link.title, link.place_name) }),
    h('div', { class: 'lm-link__url' }, h('code', { text: link.url }), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: copy, text: 'Copy' })),
    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
      h('a', { class: 'btn btn--primary btn--s', href: 'https://wa.me/?text=' + encodeURIComponent(msg), target: '_blank', rel: 'noopener' }, icon('send', 16), h('span', { text: 'Share on WhatsApp' })),
      navigator.share ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => navigator.share({ title: 'WODDI', text: msg }).catch(() => {}), text: 'Share another way' }) : null),
    h('small', { class: 'faint', text: 'Whoever joins through this link starts her 3-Day Referral Activation for this seat. WODDI HQ approves before she is appointed. You are credited for her.' }));
}

export async function render(root) {
  const q = query(); const u = app.user;
  if (u.is_hq || u.is_leader) root.append(tabs([['', 'Structure'], ['board', 'Leaderboard']], '', '#/leadmap'));
  const head = h('div', { class: 'lm-head card' }, h('div', { class: 'card__body' }, sk.rows(2)));
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi());
  const body = h('div', { style: 'display:grid;gap:18px' });
  root.append(head, kpis, body);
  const params = new URLSearchParams(); KEYS.forEach((k) => q.get(k) && params.set(k, q.get(k))); ['network', 'days'].forEach((k) => q.get(k) && params.set(k, q.get(k)));
  let d; try { d = await api('/leadmap?' + params.toString()); }
  catch (e) { head.replaceChildren(h('div', { class: 'card__body' }, empty('tree', 'The leadership map did not open', e.message))); kpis.replaceChildren(); return; }
  const here = (extra = {}) => mapUrl(d.place, { network: q.get('network'), days: q.get('days'), ...extra });

  /* ---- the pickers: one per level, the locked ones are above her own seat ---- */
  const pick = h('div', { class: 'lm-pick' });
  d.levels.forEach((lv, i) => {
    const sel = h('select', { class: 'input', 'aria-label': t(lv.label), disabled: lv.locked || null }, h('option', { value: '', text: i === 0 ? t('All countries') : tf('All of this {1}', t(d.levels[i - 1]?.label || '')) }),
      lv.locked ? h('option', { value: lv.value, text: lv.text || lv.value, selected: true }) : lv.options.map((o) => h('option', { value: o.value, text: o.label })));
    if (!lv.locked) sel.value = lv.value || '';
    sel.addEventListener('change', () => { const pl = {}; for (let j = 0; j < i; j++) pl[KEYS[j]] = d.place[KEYS[j]]; pl[KEYS[i]] = sel.value; go(mapUrl(pl, { network: q.get('network'), days: q.get('days') })); });
    pick.append(h('label', { class: 'field' }, h('span', { text: lv.label }), sel));
  });
  if (d.networks.length > 1) {
    const ns = h('select', { class: 'input', 'aria-label': t('Network') }, [['', 'Both networks'], ['WGMN', 'WGMN: Good Mother'], ['WNNN', 'WNNN: Next-Gen']].map(([v, l]) => h('option', { value: v, text: l }))); ns.value = d.network && q.get('network') ? d.network : '';
    ns.addEventListener('change', () => go(mapUrl(d.place, { network: ns.value, days: q.get('days') }))); pick.append(h('label', { class: 'field' }, h('span', { text: 'Network' }), ns));
  }
  if (d.kpis_on) {
    const ds = h('select', { class: 'input', 'aria-label': t('Period') }, [7, 30, 90, 365].map((x) => h('option', { value: String(x), text: tf('Last {1} days', x) }))); ds.value = String(d.days);
    ds.addEventListener('change', () => go(mapUrl(d.place, { network: q.get('network'), days: ds.value }))); pick.append(h('label', { class: 'field' }, h('span', { text: 'Figures for' }), ds));
  }
  /* breadcrumbs: HQ > country > state ... each one a step back up */
  const crumbs = h('nav', { class: 'lm-crumbs', 'aria-label': t('Where you are in the structure') });
  const trail = [{ name: 'WODDI HQ', place: {} }, ...d.above.map((a) => ({ name: a.name, place: a.place })), d.depth ? { name: d.name, place: d.place, here: true } : null].filter(Boolean);
  trail.forEach((c, i) => { if (i) crumbs.append(h('span', { 'aria-hidden': 'true', text: '›' })); crumbs.append(c.here ? h('b', { text: c.name }) : (i === 0 && d.role !== 'hq') ? h('span', { class: 'faint', text: c.name }) : h('a', { href: mapUrl(c.place, { network: q.get('network'), days: q.get('days') }), text: c.name })); });
  head.replaceChildren(h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
    h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, d.place.cc ? flag(d.place.cc) : icon('globe', 26), h('div', { class: 'grow' }, h('h2', { class: 'display', style: 'margin:0;font-size:26px', text: d.depth ? d.name : t('WODDI around the world') }),
      h('small', { class: 'faint', text: d.depth ? t(d.level_label) + (d.view === 'up' ? ' · ' + t('the leadership above you') : '') : tf('{1} countries', d.children.length) })), crumbs),
    d.depth >= 1 && d.depth <= 4 && (d.role !== 'envoy' || d.depth <= 2) ? h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, (d.network ? [d.network] : d.networks).map((n) =>
      h('a', { class: 'btn btn--quiet btn--s', href: roomChatUrl(n, d.place) }, icon('send', 16), h('span', { text: tf('{1} region chat and forum', n) })))) : null,
    pick, d.role === 'envoy' ? h('p', { class: 'notice', style: 'margin:0', text: 'As State Envoy you support both state teams. You see who fills each seat and share links for the vacant ones. You are not their supervisor, so their activity figures are not shown here.' }) : null));

  /* ---- the figures for this place ---- */
  const a = d.totals.activations || {};
  kpis.replaceChildren(
    kpi('award', 'Volunteer leaders', d.totals.leaders, [h('span', { text: d.depth ? t('here and below') : t('across WODDI') })]),
    kpi('user', 'Vacant seats', d.totals.vacant_here + d.totals.vacant_next, [h('span', { text: d.depth ? tf('{1} here, {2} one level down', d.totals.vacant_here, d.totals.vacant_next) : tf('{1} country seats', d.totals.vacant_next) })]),
    kpi('users', 'Members', d.totals.members, [h('span', { text: d.network || t('Both networks') })]),
    kpi('route', 'Candidates in activation', (a.ref3 || 0) + (a.open7 || 0), [h('span', { text: tf('{1} on 3-Day, {2} on 7-Day', a.ref3 || 0, a.open7 || 0) })]));

  /* ---- the leadership above ---- */
  if (d.above.length) {
    body.append(card('The leadership above', 'Reports go up this line and directives come down it, from the chapter to WODDI HQ.', h('div', { class: 'lm-chain' },
      h('div', { class: 'lm-chain__step' }, h('small', { text: 'Level 0' }), h('b', { text: 'WODDI Headquarters' })),
      d.above.map((x, i) => h('a', { class: 'lm-chain__step', href: mapUrl(x.place, { network: q.get('network') }) }, h('small', { text: tf('Level {1}: {2}', i + 1, t(x.label)) + ' · ' + x.name }),
        h('div', { class: 'lm-chain__seats' }, x.seats.filter((s) => !s.extra).map((s) => h('span', { class: 'lm-chip' + (s.filled ? '' : ' is-vacant') }, s.filled ? avatar(s.person, 24) : icon('user', 16), h('span', { text: s.filled ? `${s.person.first_name} ${s.person.last_name}` : t('Vacant') }), h('small', { text: s.title })))))))));
  }

  /* ---- the seats here ---- */
  const linkSlot = h('div');
  const onLink = async (s) => {
    linkSlot.replaceChildren(h('div', { class: 'card__body' }, sk.rows(1)));
    try { const r = await api('/leadmap/seat-link', { ...d.place, network: s.network, position: s.position }); linkSlot.replaceChildren(linkBox(r.link)); linkSlot.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    catch (e) { linkSlot.replaceChildren(); toast(t('No link'), e.message, 'error'); }
  };
  if (d.depth) {
    const nets = [...new Set(d.seats.map((s) => s.network))];
    body.append(card(tf('Leadership in {1}', d.name), d.view === 'up' ? 'The leaders who serve above you.' : 'Every seat at this level, filled or vacant. Principal first, then the assistant or deputy.',
      h('div', { style: 'display:grid;gap:14px' }, nets.map((n) => h('div', { class: 'lm-seats' }, d.seats.filter((s) => s.network === n).map((s) => seatCard(s, d, onLink)))), linkSlot)));
  }

  /* ---- the places below ---- */
  if (d.child_label && d.children.length) {
    const search = h('input', { class: 'input', type: 'search', placeholder: tf('Find a {1}', t(d.child_label).toLowerCase()), 'aria-label': tf('Find a {1}', t(d.child_label)) });
    const only = h('select', { class: 'input', style: 'width:auto', 'aria-label': t('Show') }, [['', 'Every place'], ['vacant', 'With a vacant seat'], ['none', 'With no principal leader'], ['filled', 'Fully filled']].map(([v, l]) => h('option', { value: v, text: l })));
    if (['vacant', 'none', 'filled'].includes(q.get('show'))) only.value = q.get('show');      // v109.47: opened from a dashboard tile
    const nets = d.network ? [d.network] : d.networks;
    const tbl = h('div', { class: 'tablewrap' });
    const paint = () => {
      const s = search.value.trim().toLowerCase();
      const list = d.children.filter((c) => (!s || c.name.toLowerCase().includes(s)) && (!only.value || (only.value === 'vacant' ? c.vacant > 0 : only.value === 'filled' ? c.vacant === 0 : !c.slots.some((x) => x.principal && x.holder))));
      if (!list.length) { tbl.replaceChildren(empty('tree', 'No place matches', 'Change the search or the filter.')); return; }
      tbl.replaceChildren(h('table', { class: 'table' },
        h('thead', {}, h('tr', {}, h('th', { text: d.child_label }), nets.map((n) => h('th', { text: n })), h('th', { class: 'num', text: 'Vacant' }), h('th', { class: 'num', text: 'Leaders below' }), h('th', { class: 'num', text: 'Members' }), h('th', {}))),
        h('tbody', {}, list.map((c) => h('tr', { style: 'cursor:pointer', onclick: () => go(mapUrl(c.place, { network: q.get('network'), days: q.get('days') })) },
          h('td', {}, h('div', { style: 'display:flex;gap:8px;align-items:center' }, d.depth === 0 ? flag(c.place.cc) : null, h('b', { text: c.name }), c.network ? netTag(c.network) : null)),
          nets.map((n) => h('td', {}, h('div', { class: 'lm-cell' }, c.slots.filter((x) => x.network === n).map((x) => h('span', { class: 'lm-chip' + (x.holder ? '' : ' is-vacant'), title: x.title },
            x.holder ? avatar(x.holder, 22) : icon('user', 14), h('span', { text: x.holder ? `${x.holder.first_name} ${x.holder.last_name}` : t('Vacant') }), h('small', { text: x.principal ? '' : t('Asst.') })))))),
          h('td', { class: 'num' }, c.vacant ? h('span', { class: 'tag tag--bad', text: String(c.vacant) }) : h('span', { class: 'tag tag--wnnn', text: '0' })),
          h('td', { class: 'num', text: fmt(c.leaders) }), h('td', { class: 'num', text: fmt(c.members) }),
          h('td', {}, h('a', { class: 'btn btn--quiet btn--s', href: mapUrl(c.place, { network: q.get('network'), days: q.get('days') }), onclick: (e) => e.stopPropagation(), text: 'Open' })))))));
    };
    search.addEventListener('input', paint); only.addEventListener('change', paint); paint();
    const total = d.children.length, without = d.children.filter((c) => !c.slots.some((x) => x.principal && x.holder)).length;
    body.append(card(tf('{1} in {2}', t(d.child_label) + 's', d.depth ? d.name : t('WODDI')), tf('{1} places · {2} with no principal leader yet. Open a place to see its seats and share a link for a vacant one.', total, without),
      h('div', { style: 'display:grid;gap:12px;grid-template-columns:minmax(0,1fr)' }, h('div', { class: 'toolbar', style: 'flex-wrap:wrap' }, search, only), tbl)));
  } else if (d.depth === 4) body.append(card('Chapters', null, empty('grid', 'No chapter here yet', 'Chapters open by themselves as members join in this ward or community cluster.')));
}
