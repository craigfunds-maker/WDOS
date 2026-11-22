/* v109.46: the volunteer leader leaderboard. Every figure comes from WDOS records over the chosen period: leaders and members she brought in,
 * meetings attended and on time, meetings and programmes held, reports and tasks on time, and what is overdue. The score is a published
 * formula shown on the page, so anyone can check a rank by hand. HQ sees every leader; a leader sees herself and those under her. */
import { h, t, tf, icon, avatar, flag, netTag, card, sk, empty, fmt, tabs } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { kpi } from './hq.js';
import { query, mapUrl, seen } from './leadmap.js';

const COLS = [['score', 'Score'], ['leaders_brought', 'Leaders brought'], ['members_brought', 'Members brought'], ['meetings_attended', 'Meetings attended'], ['punctuality', 'On time'],
  ['meetings_held', 'Meetings held'], ['reports_on_time', 'Reports on time'], ['tasks_on_time', 'Tasks on time'], ['programmes', 'Programmes']];
const LEVELS = [['', 'Every level'], ['country', 'Country'], ['state_region', 'State or region'], ['lga', 'LGA or district'], ['community_cluster', 'Ward or community cluster'], ['chapter', 'Chapter']];
const RULE = { leaders_brought: 'each leader she brought in', members_brought: 'each member she brought in', meetings_attended: 'each meeting attended', punctual: 'each meeting joined on time',
  meetings_held: 'each meeting she held', reports_on_time: 'each report on time', reports_late: 'each report in late', tasks_on_time: 'each task done on time', tasks_late: 'each task done late',
  programmes: 'each programme or outreach recorded', reports_overdue: 'each report overdue', tasks_overdue: 'each task overdue' };

export async function render(root) {
  const q = query();
  root.append(tabs([['', 'Structure'], ['board', 'Leaderboard']], 'board', '#/leadmap'));
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()); const box = h('div', {}, sk.rows(8)); const tools = h('div', { class: 'toolbar', style: 'flex-wrap:wrap' });
  const rules = h('details', { class: 'lm-rules' });
  root.append(kpis, card('Volunteer leader leaderboard', 'From the records in WDOS for the period you choose. Tap a leader to open her activity file.', h('div', { style: 'display:grid;gap:12px;min-width:0;grid-template-columns:minmax(0,1fr)' }, tools, box, rules)));
  const params = new URLSearchParams(); ['cc', 'state', 'lga', 'comm', 'network', 'level', 'days', 'sort'].forEach((k) => q.get(k) && params.set(k, q.get(k)));
  let d; try { d = await api('/leadmap/leaderboard?' + params.toString()); }
  catch (e) { kpis.replaceChildren(); box.replaceChildren(empty('chart', 'The leaderboard did not open', e.message)); return; }
  const set = (k, v) => { const p = new URLSearchParams(q); if (v) p.set(k, v); else p.delete(k); go('#/leadmap/board?' + p.toString()); };
  const sel = (label, opts, cur, k) => { const s = h('select', { class: 'input', style: 'width:auto', 'aria-label': t(label) }, opts.map(([v, l]) => h('option', { value: v, text: l }))); s.value = cur || ''; s.addEventListener('change', () => set(k, s.value)); return s; };
  tools.append(sel('Period', [[30, 'Last 30 days'], [7, 'Last 7 days'], [90, 'Last 90 days'], [365, 'Last 12 months']].map(([v, l]) => [String(v), l]), String(d.days), 'days'),
    sel('Level', LEVELS, q.get('level'), 'level'), d.networks.length > 1 ? sel('Network', [['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']], q.get('network'), 'network') : null,
    sel('Rank by', COLS, d.sort, 'sort'), h('span', { class: 'faint', text: d.place.cc ? tf('Area: {1}', d.name) : t('Area: all of WODDI') }),
    h('a', { class: 'btn btn--ghost btn--s', href: mapUrl(d.place), text: 'Choose the area on the map' }));
  const s = d.sum;
  kpis.replaceChildren(kpi('award', 'Leaders on the board', s.leaders, [h('span', { text: tf('{1} brought in new leaders', s.leaders_brought) })]),
    kpi('calendar', 'Meetings attended', s.meetings_attended, [h('span', { text: s.punctuality === null ? t('No joins recorded yet') : tf('{1}% joined on time', s.punctuality) })]),
    kpi('book', 'Reports on time', s.reports_on_time, [h('span', { text: tf('{1} overdue', s.reports_overdue) })]),
    kpi('tasks', 'Tasks done on time', s.tasks_on_time, [h('span', { text: tf('{1} overdue · {2} programmes, {3} people reached', s.tasks_overdue, s.programmes, fmt(s.people_reached)) })]));
  if (!d.rows.length) box.replaceChildren(empty('award', 'No leader in this area yet', 'Leaders appear here once WODDI HQ records their seats.'));
  else box.replaceChildren(h('div', { class: 'tablewrap', style: 'min-width:0;max-width:100%;overflow-x:auto' }, h('table', { class: 'table lm-board' },
    h('thead', {}, h('tr', {}, h('th', { class: 'num', text: '#' }), h('th', { text: 'Leader' }), COLS.map(([k, l]) => h('th', { class: 'num' + (k === d.sort ? ' is-sorted' : ''), text: l })), h('th', { class: 'num', text: 'Overdue' }))),
    h('tbody', {}, d.rows.map((r) => { const k = r.kpis;
      return h('tr', { style: 'cursor:pointer', onclick: () => go('#/leader-file/' + r.id) },
        h('td', { class: 'num' }, r.rank <= 3 ? h('span', { class: 'lm-medal lm-medal--' + r.rank, text: String(r.rank) }) : String(r.rank)),
        h('td', {}, h('div', { class: 'who' }, avatar(r, 36), h('div', {}, h('b', { text: `${r.first_name} ${r.last_name}` }), h('small', { text: `${r.title} · ${r.place}` }),
          h('small', { class: 'faint', text: [r.came_by, r.route, seen(r.last_seen_at)].filter(Boolean).join(' · ') })), flag(r.cc), netTag(r.network))),
        h('td', { class: 'num' }, h('b', { text: String(k.score) })), h('td', { class: 'num', text: String(k.leaders_brought) }), h('td', { class: 'num', text: String(k.members_brought) }),
        h('td', { class: 'num', text: `${k.meetings_attended}` }), h('td', { class: 'num', text: k.punctuality === null ? '–' : k.punctuality + '%' }), h('td', { class: 'num', text: String(k.meetings_held) }),
        h('td', { class: 'num', text: `${k.reports_on_time}/${k.reports_due}` }), h('td', { class: 'num', text: `${k.tasks_on_time}/${k.tasks}` }), h('td', { class: 'num', text: String(k.programmes) }),
        h('td', { class: 'num' }, k.reports_overdue + k.tasks_overdue ? h('span', { class: 'tag tag--bad', text: String(k.reports_overdue + k.tasks_overdue) }) : '0'));
    })))));
  rules.append(h('summary', { text: 'How the score is worked out' }), h('ul', {}, Object.entries(d.score_rules).map(([k, w]) => h('li', { text: (w > 0 ? '+' : '') + w + ' · ' + t(RULE[k] || k) }))),
    h('p', { class: 'faint', text: tf('On time means she joined within {1} minutes of the start. Meetings count when they were called in WDOS (attendance marked or the video room joined). A leader with no record for something scores nothing for it, never an estimate.', d.punctual_min) }));
}
