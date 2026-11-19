/* v109.24: HQ sees what volunteer leaders do with their teams, in every country and both networks: tasks given, messages,
 * voice and phone calls, group calls, meetings, reports reviewed, leaders' profiles opened. Read from the activity record. */
import { h, t, tf, icon, empty, sk, fmt, ago, netTag } from './ui.js';
import { api } from './api.js';

const ICON = { task_created: 'tasks', message_sent: 'send', voice_call: 'phone', phone_call: 'phone', group_call: 'users', group_call_add: 'users', group_call_join: 'users',
  meeting_scheduled: 'calendar', meeting_requested: 'calendar', team_viewed: 'eye', report_reviewed: 'book', report_submitted: 'book' };

function row(x) {
  return h('div', { class: 'list__link', style: 'display:flex;gap:10px;align-items:flex-start' }, h('div', { class: 'nrow__ico' }, icon(ICON[x.action] || 'check', 18)),
    h('div', { class: 'grow', style: 'min-width:0;display:grid;gap:2px' },
      h('b', { text: `${x.leader} · ${t(x.what)}` }),
      h('small', { text: [x.title, x.country].filter(Boolean).join(' · ') }),
      x.detail ? h('small', { class: 'muted', text: x.detail }) : null),
    h('div', { style: 'flex:none;display:grid;gap:4px;justify-items:end' }, x.network ? netTag(x.network) : null, h('small', { text: ago(x.at) })));
}

/** The HQ dashboard card: the latest things leaders did. */
export function leaderActivityPanel() {
  const box = h('div', { style: 'display:grid;gap:12px' }, sk.rows(4));
  api('/hq/leader-activity?days=30').then((r) => {
    const top = r.leaders.slice(0, 5);
    box.replaceChildren(
      r.items.length ? h('div', { class: 'list list--stack' }, r.items.slice(0, 8).map(row)) : empty('users', 'Nothing yet', 'When leaders give tasks, send messages, ring their team or hold meetings, it shows here.'),
      top.length ? h('div', { class: 'panel', style: 'display:grid;gap:4px' }, h('b', { text: t('Most active leaders, last 30 days') }),
        ...top.map((l) => h('small', { text: `${l.leader}, ${l.title}${l.country ? ' (' + l.country + ')' : ''}: ${tf('{1} actions', fmt(l.total))}` }))) : null,
      h('a', { class: 'btn btn--ghost btn--s', href: '#/hq/activity', style: 'justify-self:start' }, t('See all, by country and network')));
  }).catch((e) => box.replaceChildren(empty('x', 'This did not load', e.message)));
  return box;
}

/** The full page: filter by country, network, period and leader. */
export async function render(root) {
  const sel = (opts) => h('select', { class: 'input' }, opts.map(([v, l]) => h('option', { value: v, text: t(l) })));
  const country = sel([['', 'All countries']]); const network = sel([['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']]);
  const days = sel([['7', 'Last 7 days'], ['30', 'Last 30 days'], ['90', 'Last 90 days']]); days.value = '30';
  const lead = h('div'), list = h('div', {}, sk.rows(6));
  let leader = 0, filled = false;
  root.append(h('div', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
    h('h2', { class: 'display', style: 'margin:0;font-size:22px', text: t("Leaders' activity") }),
    h('p', { class: 'muted', style: 'margin:0', text: t('What volunteer leaders do with the leaders under them: tasks, messages, calls, group calls, meetings and reports.') }),
    h('div', { class: 'grid3', style: 'display:grid;gap:10px' }, country, network, days))), h('div', { class: 'row row--2', style: 'margin-top:20px' },
    h('section', { class: 'card' }, h('div', { class: 'card__body' }, h('h3', { style: 'margin-top:0', text: t('By leader') }), lead)),
    h('section', { class: 'card' }, h('div', { class: 'card__body' }, h('h3', { style: 'margin-top:0', text: t('Latest') }), list))));
  async function load() {
    const q = new URLSearchParams({ days: days.value, country: country.value, network: network.value, leader: String(leader || '') });
    list.replaceChildren(sk.rows(6));
    let r; try { r = await api('/hq/leader-activity?' + q); } catch (e) { list.replaceChildren(empty('x', 'This did not load', e.message)); return; }
    if (!filled) { filled = true; r.countries.forEach((c) => country.append(h('option', { value: c.cc, text: c.name || c.cc }))); }
    lead.replaceChildren(r.leaders.length ? h('div', { class: 'list list--stack' }, (leader ? [h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { leader = 0; load(); } }, t('Show every leader'))] : []).concat(r.leaders.map((l) =>
      h('button', { type: 'button', class: 'list__link', style: 'display:flex;gap:10px;align-items:center;text-align:start;width:100%;background:none;border:0', onclick: () => { leader = l.leader_id; load(); } },
        h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: l.leader }), h('small', { text: [l.title, l.country].filter(Boolean).join(' · ') }),
          h('small', { class: 'muted', style: 'display:block', text: Object.entries(l.counts).map(([k, n]) => `${t(r.labels[k] || k)}: ${n}`).join(' · ') })),
        h('div', { style: 'flex:none;display:grid;justify-items:end;gap:4px' }, h('b', { text: fmt(l.total) }), h('small', { text: ago(l.last) })))))) : empty('users', 'No activity', 'No leader activity for these choices.'));
    list.replaceChildren(r.items.length ? h('div', { class: 'list list--stack' }, r.items.map(row)) : empty('users', 'No activity', 'Nothing for these choices.'));
  }
  [country, network, days].forEach((x) => x.addEventListener('change', () => { leader = 0; load(); }));
  load();
}
