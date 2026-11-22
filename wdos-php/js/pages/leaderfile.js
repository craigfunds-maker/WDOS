/* v109.46: one volunteer leader's activity file, for full accountability: where she comes from, how she came (Envoy or leader referral,
 * 3-Day or 7-Day activation), her seat and the leaders above her, her figures, and every recorded action in the period, newest first.
 * Open to WODDI HQ and to the leaders above her in her own network. */
import { h, t, tf, icon, avatar, flag, netTag, card, sk, empty, fmtDate, agoIn } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';
import { cameBy, miniKpis, mapUrl, seen } from './leadmap.js';

const KIND = { task: ['tasks', 'Task'], report: ['book', 'Report'], meeting: ['calendar', 'Meeting'], programme: ['spark', 'Programme'], referral: ['send', 'Referral'], log: ['check', 'Action'] };
const WHAT = { completed: 'completed', open: 'open', in_progress: 'in progress', submitted: 'submitted for approval', cancelled: 'cancelled', submitted_on_time: 'submitted on time', submitted_late: 'submitted late',
  overdue: 'overdue', due: 'due', present: 'present', absent: 'absent', invited: 'invited', joined_on_time: 'joined on time', joined_late: 'joined late', leader: 'now a volunteer leader', candidate: 'referred for a seat',
  member: 'joined as a member', appointed: 'appointed', seat_closed: 'left a seat', seat_link: 'made a link for a vacant seat', report_submitted: 'submitted a report', registered: 'joined WDOS' };

export async function render(root, params) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(6))); root.append(box);
  const days = Number(new URLSearchParams(location.hash.split('?')[1] || '').get('days')) || 30;
  let d; try { d = await api(`/leadmap/leader/${encodeURIComponent(params.id)}?days=${days}`); }
  catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This file did not open', e.message, h('a', { class: 'btn btn--quiet btn--s', href: '#/leadmap', text: 'Back to the leadership map' })))); return; }
  const p = d.person, s = d.seat, k = d.kpis, j = d.journey;
  const per = h('div', { class: 'seg', role: 'group', 'aria-label': t('Period') }, [7, 30, 90, 365].map((x) => h('a', { class: 'seg__a', href: `#/leader-file/${p.id}?days=${x}`, 'aria-pressed': String(x === d.days), text: x === 365 ? t('12 months') : tf('{1} days', x) })));
  const fact = (l, v) => [h('dt', { text: l }), h('dd', { text: v || '–' })];
  box.replaceChildren(
    h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:flex;gap:16px;align-items:center;flex-wrap:wrap' }, avatar(p, 72),
      h('div', { class: 'grow' }, h('h2', { class: 'display', style: 'margin:0;font-size:26px', text: `${p.first_name} ${p.last_name}` }), h('div', { text: `${s.title}, ${s.place_name}` }),
        h('div', { class: 'tagrow', style: 'justify-content:flex-start;margin-top:6px' }, netTag(s.network), flag(p.country_code), h('span', { class: 'tag', text: seen(p.last_seen_at) }), s.acceptance === 'pending' ? h('span', { class: 'tag', text: 'Agreement not accepted' }) : null)),
      h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('a', { class: 'btn btn--ghost btn--s', href: mapUrl(s.place), text: 'See her place on the map' }), d.is_hq ? h('a', { class: 'btn btn--ghost btn--s', href: '#/members/' + p.id, text: 'Member record' }) : null,
        h('a', { class: 'btn btn--quiet btn--s', href: '#/team/' + p.id }, icon('send', 16), h('span', { text: 'Message, call or give a task' }))))),
    h('div', { class: 'row row--4' },
      kpi('award', 'Score', k.score, [h('span', { text: tf('Last {1} days', d.days) })]),
      kpi('users', 'Leaders she brought', k.leaders_brought, [h('span', { text: tf('{1} in all · {2} candidates · {3} members', k.leaders_total, k.candidates, k.members_brought) })]),
      kpi('calendar', 'Meetings attended', k.meetings_attended, [h('span', { text: k.punctuality === null ? t('No joins recorded yet') : tf('{1}% on time · {2} held by her', k.punctuality, k.meetings_held) })]),
      kpi('book', 'Reports on time', `${k.reports_on_time}/${k.reports_due}`, [h('span', { text: tf('{1} late · {2} overdue', k.reports_late, k.reports_overdue) })])),
    h('div', { class: 'row row--2' },
      card('Where she comes from and how she came', null, h('dl', { class: 'lm-facts' }, fact('Comes from', p.from), fact('Joined WDOS', fmtDate(p.created_at)), fact('How she came', cameBy(j)),
        j && j.activation ? fact('Activation', `${t(j.activation.route_label)}: ${t(j.activation.status)}${j.activation.started_at ? ' · ' + tf('started {1}', fmtDate(j.activation.started_at)) : ''}${j.activation.decided_at ? ' · ' + tf('decided {1}', fmtDate(j.activation.decided_at)) : ''}`) : null,
        fact('Serving since', fmtDate(s.since)), p.phone ? fact('Phone', p.phone) : null, p.email ? fact('Email', p.email) : null)),
      card('The leaders above her', 'Her reports go up this line.', d.chain.length ? h('div', { class: 'list' }, d.chain.map((c) => h('div', {}, c.filled ? avatar(c.person, 36) : h('span', { class: 'lm-vac lm-vac--s' }, icon('user', 16)),
        h('div', { class: 'grow' }, h('b', { text: c.filled ? `${c.person.first_name} ${c.person.last_name}` : t('Vacant') }), h('small', { text: `${c.title} · ${c.place}` }))))) : h('p', { class: 'faint', text: 'She reports straight to WODDI HQ.' }))),
    card('Her figures', null, h('div', { style: 'display:grid;gap:12px' }, per, miniKpis(k), h('p', { class: 'faint', style: 'margin:0', text: tf('{1} actions recorded in WDOS · {2} programmes reaching {3} people · {4} tasks open, {5} overdue', k.actions, k.programmes, k.people_reached, k.tasks_open, k.tasks_overdue) }))),
    card('Everything she did in this period', 'Newest first. Each line is a record in WDOS.', d.timeline.length ? h('ol', { class: 'lm-tl' }, d.timeline.map((x) => { const kd = KIND[x.kind] || KIND.log;
      return h('li', {}, h('span', { class: 'lm-tl__ico' }, icon(kd[0], 16)), h('div', { class: 'grow' }, h('b', { text: t(kd[1]) + ': ' + t(WHAT[x.what] || String(x.what || '').replace(/_/g, ' ')) }), x.detail ? h('div', { text: x.detail }) : null,
        h('small', { class: 'faint', text: fmtDate(x.at) + (x.due ? ' · ' + tf('due {1}', fmtDate(x.due)) : '') })));
    })) : empty('clock', 'Nothing recorded in this period', 'Choose a longer period, or follow up with her.')));
}
