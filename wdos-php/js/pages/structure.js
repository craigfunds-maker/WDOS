/* Leadership structure: every seat in scope, filled or vacant. */
import { h, avatar, flag, card, sk, empty, fmt, fmtDate, agoIn, netTag } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';

const LEVELS = { country: 'Country', state_region: 'State or region', lga: 'LGA or district', community_cluster: 'Community cluster', chapter: 'Chapter' };
const STATUS = { vacant: ['Vacant', 'bad'], appointed: ['Appointed', 'wnnn'], acting: ['Acting', 'wgmn'], pending: ['Pending', ''] };

export async function render(root) {
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()); const body = h('div', {}, sk.rows(6)); const tools = h('div', { class: 'toolbar' });
  const wrap = card('Leadership structure', 'Every leadership seat in scope, filled or vacant. Vacant seats are listed for countries, and for states where the state list is loaded.', body, tools);
  root.append(kpis, wrap);
  let d; try { d = await api('/structure'); } catch (e) { kpis.replaceChildren(); body.replaceChildren(empty('x', 'The structure did not load', e.message)); return; }
  const sel = (label, opts) => h('select', { class: 'input', 'aria-label': label, style: 'width:auto' }, opts.map(([v, l]) => h('option', { value: v, text: l })));
  const fNet = d.networks.length > 1 ? sel('Network', [['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']]) : null;
  const fCountry = d.countries.length > 1 ? sel('Country', [['', 'All countries'], ...d.countries.map((c) => [c.code, c.name])]) : null;
  const fLevel = sel('Level', [['', 'All levels'], ...Object.entries(LEVELS)]); const fStatus = sel('Status', [['', 'Any status'], ['vacant', 'Vacant'], ['appointed', 'Appointed'], ['acting', 'Acting'], ['pending', 'Pending']]);
  tools.append(...[fNet, fCountry, fLevel, fStatus].filter(Boolean));
  const paint = () => {
    const list = d.rows.filter((r) => (!fNet || !fNet.value || r.network === fNet.value) && (!fCountry || !fCountry.value || r.country_code === fCountry.value) && (!fLevel.value || r.level === fLevel.value) && (!fStatus.value || r.status === fStatus.value));
    const n = (s) => list.filter((r) => r.status === s).length; const filled = list.filter((r) => r.kind === 'filled').length;
    kpis.replaceChildren(kpi('tree', 'Seats', list.length, [h('span', { text: d.scope })]), kpi('award', 'Filled', filled, [h('span', { text: `${n('acting')} acting, ${n('pending')} pending` })]), kpi('user', 'Vacant', n('vacant'), [h('span', { text: 'Country and state lead seats' })]),
      kpi('check', 'Agreement accepted', list.filter((r) => r.acceptance === 'accepted').length, [h('span', { text: `${list.filter((r) => r.acceptance === 'pending').length} waiting to accept` })]));
    if (!list.length) { body.replaceChildren(empty('tree', 'No seat matches these filters', 'Change a filter to see more of the structure.')); return; }
    const shown = list.slice(0, 400);
    body.replaceChildren(h('div', { class: 'tablewrap reveal' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Position', 'Unit', 'Holder', 'Appointment', 'Since', d.is_hq ? '' : null].filter((x) => x !== null).map((x) => h('th', { text: x })))),
      h('tbody', {}, shown.map((r) => { const st = STATUS[r.status] || [r.status, ''];
        return h('tr', { style: 'cursor:default' }, h('td', {}, h('b', { text: r.title }), h('div', { class: 'faint', style: 'font-size:12.5px', text: LEVELS[r.level] || r.level })),
          h('td', {}, h('div', { style: 'display:flex;gap:8px;align-items:center' }, flag(r.country_code), h('span', { text: (r.area ? r.area + ', ' : '') + r.unit }), netTag(r.network))),
          h('td', {}, r.holder ? h('div', { class: 'who' }, avatar(r.holder, 34), h('div', {}, h('b', { text: `${r.holder.first_name} ${r.holder.last_name}` }), h('small', { text: r.holder.last_seen_at ? 'Seen ' + agoIn(r.holder.last_seen_at) : 'Not signed in yet' }))) : h('span', { class: 'faint', text: 'Nobody yet' })),
          h('td', {}, h('div', { class: 'tagrow' }, h('span', { class: 'tag' + (st[1] ? ' tag--' + st[1] : ''), text: st[0] }), r.acceptance === 'pending' ? h('span', { class: 'tag', text: 'Agreement not yet accepted' }) : r.acceptance === 'declined' ? h('span', { class: 'tag tag--bad', text: 'Agreement declined' }) : null, r.onboarding ? h('span', { class: 'tag', text: `Onboarding ${r.onboarding[0]}/${r.onboarding[1]}` }) : null)),
          h('td', { class: 'num', text: r.since ? fmtDate(r.since) : '' }),
          d.is_hq ? h('td', {}, r.holder ? h('div', { style: 'display:flex;gap:6px' }, h('a', { class: 'btn btn--quiet btn--s', href: '#/leaders/' + r.holder.id, text: 'Dashboard' }), h('a', { class: 'btn btn--ghost btn--s', href: '#/members/' + r.holder.id, text: 'Record' })) : h('a', { class: 'btn btn--quiet btn--s', href: '#/members', text: 'Fill seat' })) : null); })))),
      list.length > shown.length ? h('p', { class: 'faint', style: 'padding:12px 4px', text: `Showing the first ${shown.length} of ${fmt(list.length)} seats. Use the filters to narrow.` }) : null);
  };
  [fNet, fCountry, fLevel, fStatus].filter(Boolean).forEach((f) => f.addEventListener('change', paint)); paint();
}
