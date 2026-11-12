/* Reporting and network intelligence: drill from the continent down to chapter. Every figure is counted by WDOS from
 * its own records and carries the time it was counted. What a leader submitted, what was checked and what was approved
 * are shown as separate numbers, never mixed. */
import { h, card, sk, empty, fmt, fmtDate, netTag } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { kpi } from './hq.js';

const LEVEL = { country: 'Country', state: 'State or region', lga: 'LGA, district or city', community: 'Community or cluster', chapter: 'Chapter' };
const COLS = [['members', 'Members'], ['pending', 'Waiting for a chapter'], ['active_7d', 'Active this week'], ['on_journey', 'On the seven days'], ['activated', 'Activated'], ['referrals', 'Referrals'], ['leaders', 'Leaders'], ['attended', 'Attendance, 90 days'], ['tasks_open', 'Open tasks']];
const RSTAT = { draft: 'Being written', submitted: 'Submitted', returned: 'Returned for correction', reviewed: 'Checked', approved: 'Approved', nil: 'Nil report' };

export async function render(root) {
  const path = {}; let network = '';
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()); const crumbs = h('nav', { class: 'crumbs' }); const table = h('div', {}, sk.rows(5)); const side = h('div', { style: 'display:grid;gap:18px;align-content:start' }); const stamp = h('small', { class: 'faint' });
  const net = h('select', { class: 'input', 'aria-label': 'Network' }, [['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']].map(([v, l]) => h('option', { value: v, text: l }))); net.onchange = () => { network = net.value; load(); };
  const dl = h('button', { class: 'btn btn--ghost btn--s', type: 'button' }, 'Download as a file'); let last = null;
  dl.onclick = () => { if (!last) return; const esc = (v) => '"' + String(v).replace(/"/g, '""') + '"'; const lines = [[LEVEL[last.level], ...COLS.map((c) => c[1])].map(esc).join(','), ...last.rows.map((r) => [r.label, ...COLS.map((c) => r[c[0]])].map(esc).join(','))];
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv' })); a.download = 'wdos-network-report.csv'; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); api('/reports/exported', { what: 'Network report, ' + LEVEL[last.level] }).catch(() => {}); };
  root.append(kpis, h('div', { class: 'row row--2-1' }, card('Hierarchy', 'Select a row to go one level down. Member details open only for those allowed to see them.', h('div', { style: 'display:grid;gap:14px;grid-template-columns:minmax(0,1fr)' }, crumbs, table, stamp), h('div', { class: 'toolbar' }, app.user.is_hq ? net : null, dl)), side));
  async function load() {
    let r; try { r = await api('/reports/auto?' + Object.entries({ ...path, network }).filter(([, v]) => v !== '').map(([k, v]) => k + '=' + encodeURIComponent(v)).join('&')); } catch (e) { kpis.replaceChildren(); table.replaceChildren(empty('x', 'The report did not load', e.message)); return; }
    last = r; const d = r.distribution; const pct = (n) => (d.total ? Math.round((n / d.total) * 100) : 0);
    kpis.replaceChildren(kpi('users', 'Memberships in this view', d.total, [h('span', { text: 'Counted from membership records' })]), kpi('check', 'Registered and active', d.active, [h('span', { text: `${pct(d.active)}% of this view` })]), kpi('route', 'Onboarding', d.onboarding, [h('span', { text: 'On their seven days' })]), kpi('plus', 'Pending placement', d.pending, [h('span', { text: 'Waiting for a chapter' })]));
    const keys = Object.keys(path); crumbs.replaceChildren(h('button', { type: 'button', class: 'linkbtn', onclick: () => { keys.forEach((k) => delete path[k]); load(); } }, app.user.is_hq ? 'Continental' : 'My area'), ...keys.map((k, i) => h('span', {}, ' / ', h('button', { type: 'button', class: 'linkbtn', onclick: () => { keys.slice(i + 1).forEach((x) => delete path[x]); load(); } }, path[k] === '(none)' ? 'Not stated' : path[k]))));
    table.replaceChildren(r.rows.length ? h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, h('th', { text: LEVEL[r.level] }), COLS.map((c) => h('th', { class: 'num', text: c[1] })))),
      h('tbody', {}, r.rows.map((x) => { const go = () => { if (r.level === 'chapter') { if (x.key !== '0') location.hash = '#/chapter/' + x.key; } else { path[r.level] = x.key; load(); } };
        return h('tr', { class: 'is-link', tabindex: 0, onclick: go, onkeydown: (e) => { if (e.key === 'Enter') go(); } }, h('td', {}, h('b', { text: x.label }), x.chapter_status ? h('small', { class: 'faint', style: 'display:block', text: x.chapter_status }) : null), COLS.map((c) => h('td', { class: 'num', text: fmt(x[c[0]]) }))); })))) : empty('chart', 'Nothing to report here yet', 'Figures appear as members register.'));
    stamp.textContent = `${r.source} Counted on ${fmtDate(r.as_of, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })} (GMT). Course progress: ${r.course_progress}`;
    const bar = (label, n, cls) => h('div', { style: 'display:grid;grid-template-columns:150px 1fr 52px;gap:10px;align-items:center' }, h('small', { text: label }), h('div', { class: 'meter ' + cls }, h('span', { style: `width:${pct(n)}%` })), h('b', { class: 'num', style: 'text-align:end', text: pct(n) + '%' }));
    const rs = Object.entries(r.report_status);
    side.replaceChildren(card('Network distribution', 'Every membership in this view is in one group', h('div', { style: 'display:grid;gap:10px' }, bar('Registered and active', d.active, 'is-ok'), bar('Onboarding', d.onboarding, ''), bar('Pending placement', d.pending, ''), bar('Inactive', d.inactive, 'is-full'),
        h('small', { class: d.total === d.active + d.onboarding + d.pending + d.inactive ? 'faint' : 'err', text: `${fmt(d.active)} + ${fmt(d.onboarding)} + ${fmt(d.pending)} + ${fmt(d.inactive)} = ${fmt(d.total)}` }))),
      card('Submitted, checked, approved', 'Reports written by leaders, by where each one stands. A submitted figure is not treated as approved.', rs.length ? h('div', { class: 'list' }, rs.map(([s, n]) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: RSTAT[s] || s })), h('span', { class: 'tag num', text: fmt(n) })))) : h('p', { class: 'muted', style: 'margin:0', text: 'No leader report has been written yet.' }), h('a', { class: 'btn btn--ghost btn--s', href: '#/reporting', text: 'Leader reports' })),
      card('How these figures are kept honest', null, h('div', { class: 'list list--wrap' }, [['Single source', 'Membership counts come from current membership records, not from typed-in totals.'], ['Verified status', 'Submitted, checked and approved are separate values.'], ['Permanent history', 'Chapter transfers and changes stay traceable on each member.']].map(([a, b]) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: a }), h('small', { text: b })))))));
  }
  load();
}
