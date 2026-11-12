/* Volunteer leadership dashboard: everything is scoped to the leader's own seat and network. */
import { h, clear, icon, avatar, flag, netTag, card, sk, empty, ago, agoIn, fmt, greeting, fmtDate, NETS, inspCard } from '../ui.js';
import { pushNudge } from '../pushnudge.js';
import { goodMotherArt } from '../goodmother.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { lineChart, bars, sparkline, seriesColor } from '../charts.js';
import { rangeSeg, delta, kpi, peopleList } from './hq.js';

export async function render(root, params = {}) {
  if (params.pick) return picker(root);
  let u = app.user; let days = Number(sessionStorage.getItem('wdos.days')) || 30;
  /* HQ opening a leader's dashboard: the same page, drawn from that leader's seat, read-only. */
  const viewing = !!params.as; const as = viewing ? '&as=' + encodeURIComponent(params.as) : '';
  if (viewing) {
    try { u = (await api(`/leader/dashboard?days=${days}${as}`)).person; }
    catch (e) { root.append(h('div', { class: 'card' }, empty('x', 'This dashboard did not open', e.message, h('a', { class: 'btn btn--quiet btn--s', href: '#/leaders', text: 'Back to leader dashboards' })))); return; }
    root.append(h('section', { class: 'card viewas' }, h('div', { class: 'card__body nudge__row' }, h('div', { class: 'nrow__ico' }, icon('eye', 18)), h('div', { class: 'grow' }, h('b', { text: tf('You are viewing the dashboard of {1} {2}', u.first_name, u.last_name) }), h('p', { class: 'muted', style: 'margin:4px 0 0', text: 'This is what this leader sees. You are signed in as WODDI HQ and nothing here can be changed from this view.' })),
      h('a', { class: 'btn btn--quiet btn--s', href: '#/members/' + u.id, text: 'Open member record' }), h('a', { class: 'btn btn--ghost btn--s', href: '#/leaders', text: 'All leader dashboards' }))));
  }
  const net = NETS[u.seat?.network || u.network] || NETS.WGMN; const color = seriesColor[u.seat?.network || u.network] || seriesColor.WGMN;
  const seg = h('div'), meta = h('div', { class: 'hero__meta' });
  const gmMap = u.network === 'WGMN' && u.country_code;
  const hero = h('section', { class: 'hero' }, h('div', { class: 'hero__id' }, h('img', { class: 'hero__disc', src: net.disc, alt: net.name }), avatar(u, 76)), h('div', { class: 'grow' }, h('h1', { class: 'display', text: viewing ? `${u.first_name} ${u.last_name}` : tf('{1}, {2}', greeting(), u.first_name) }), h('p', { text: u.seat?.title || '' }), meta), gmMap ? h('figure', { class: 'hero__map', title: 'What a Good Mother is called in ' + (u.country || 'your country') }, goodMotherArt(u.country_code, u.country)) : null, seg);
  const work = h('div', { style: 'display:grid;gap:20px' }); const kpis = h('div', { class: 'row row--4' }); const trend = h('div'), breakdown = h('div'), team = h('div'), recent = h('div');
  const breakCard = card('Where your members are', null, breakdown);
  const today = h('div', { hidden: true });
  if (!viewing) api('/inspiration/today?lang=' + document.documentElement.lang).then((r) => { if (r.card) { today.hidden = false; today.append(inspCard(r.card)); } }).catch(() => {});
  const todo = viewing ? {} : u.todo || {}; const nudge = (title, text, href, label) => h('section', { class: 'card nudge' }, h('div', { class: 'card__body', style: 'display:flex;gap:16px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'grow' }, h('b', { text: title }), h('p', { class: 'muted', style: 'margin:4px 0 0', text })), h('a', { class: 'btn', href, text: label })));
  if (todo.appointment) root.append(hero, nudge('Accept your appointment', 'Read the Volunteer Leadership Agreement to the end and accept it to take up your seat.', '#/appointment', 'Read the Agreement'));
  else if (todo.onboarding > 0) root.append(hero, nudge('Your onboarding', (todo.onboarding === 1 ? tf('{1} required step is still to finish. Work through each step in order.', todo.onboarding) : tf('{1} required steps are still to finish. Work through each step in order.', todo.onboarding)), '#/onboarding', 'Continue my onboarding'));
  root.append(hero, viewing ? '' : pushNudge(), today, work, kpis, h('div', { class: 'row row--2-1' }, card('New members in your area', 'Registrations per day', trend), breakCard),
    h('div', { class: 'row row--2' }, card('Your leadership team', 'Leaders serving under your seat', team), card('Newest members', null, recent, h('a', { class: 'btn btn--ghost btn--s', href: viewing ? `#/leaders/${u.id}/members` : '#/members', text: viewing ? 'All members in this area' : 'All my members' }))));

  async function load() {
    seg.replaceChildren(rangeSeg(days, (d) => { days = d; sessionStorage.setItem('wdos.days', d); load(); }));
    kpis.replaceChildren(sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()); trend.replaceChildren(sk.chart(250)); breakdown.replaceChildren(sk.bars(6)); team.replaceChildren(sk.rows(4)); recent.replaceChildren(sk.rows(4));
    let d;
    try { d = await api('/leader/dashboard?days=' + days + as); }
    catch (e) { kpis.replaceChildren(); [trend, breakdown, team, recent].forEach((x) => x.replaceChildren(empty('x', 'Could not load this', e.message, h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Try again')))); return; }
    const k = d.kpis;
    meta.replaceChildren(flag(d.seat.country_code), netTag(d.seat.network), h('span', { class: 'tag', text: d.seat.status === 'appointed' ? 'Appointed' : d.seat.status === 'acting' ? 'Acting' : 'Pending confirmation' }), h('span', { class: 'tag', text: 'Since ' + fmtDate(d.seat.since) }), u.member_no ? h('span', { class: 'tag num', text: u.member_no }) : null);
    kpis.replaceChildren(
      kpi('users', 'Members in your area', k.members, [h('span', { text: tf('{1} active this week', fmt(k.active_7d)) })]),
      kpi('plus', tf('Joined in {1} days', days), k.new_now, [h('span', {}, delta(k.new_now, k.new_prev, days)), sparkline(d.series.map((p) => p.WGMN + p.WNNN), color)]),
      kpi('award', 'Leaders under you', k.team, [h('span', { text: d.reports_to ? tf('You report to {1} {2}', d.reports_to.first_name, d.reports_to.last_name) : 'You report to WODDI HQ' })]),
      kpi('camera', 'Profiles with a photograph', k.with_photo, [h('span', { text: k.members ? Math.round((k.with_photo / k.members) * 100) + '% of your members' : 'No members yet' })]));
    const w = d.workspace || {}; const LN = { ar: 'Arabic', en: 'English', fr: 'French', pt: 'Portuguese', sw: 'Swahili' };
    const go = (label, href, note) => h('a', { class: 'list__link', href: viewing ? null : href }, h('div', { class: 'nrow__ico' }, icon('check', 18)), h('div', { class: 'grow' }, h('b', { text: label }), note ? h('small', { text: note }) : null), viewing ? null : h('span', { class: 'btn btn--ghost btn--s', text: 'Open' }));
    work.replaceChildren(h('div', { class: 'row row--4' }, kpi('users', 'Assigned membership', k.members, [h('span', { text: 'Members within your scope' })]), kpi('grid', 'Chapters', w.chapters || 0, [h('span', { text: tf('{1} developing, {2} at capacity', fmt(w.developing || 0), fmt(w.at_capacity || 0)) })]),
        kpi('tasks', 'Open tasks', w.open_tasks || 0, [h('span', { text: w.overdue ? tf('{1} need attention', fmt(w.overdue)) : 'None overdue' })]), kpi('chart', 'Reports submitted', w.reports_rate === null || w.reports_rate === undefined ? 'None due' : w.reports_rate + '%', [h('span', { text: w.reports_due ? tf('{1} of {2} due in 90 days', fmt(w.reports_submitted), fmt(w.reports_due)) : 'No report was due in 90 days' })])),
      h('div', { class: 'row row--2' }, card('Operational priorities', null, h('div', { class: 'list' }, w.campus ? [go('Share your outreach link on campus', '#/invite', 'Your link and who came through it'), go('Your tasks', '#/tasks')] : [go('Review chapter capacity alerts', '#/chapters', tf('{1} at capacity', fmt(w.at_capacity || 0))), go('Review tasks and submitted evidence', '#/tasks', tf('{1} open', fmt(w.open_tasks || 0))), w.he_for_she ? null : go('Inspect chapter-level reporting', '#/reports')].filter(Boolean)), h('span', { class: 'tag tag--wgmn', text: 'Priority' })),
        card('My leadership scope', null, h('div', { class: 'kv' }, [['Network', d.seat.network], ['Role', d.seat.title], ['Scope', d.seat.unit], ['Appointment', (w.acting ? 'Acting' : 'Appointed') + (w.he_for_she ? ', He-for-She Champion' : '') + (w.campus ? ', outreach role' : '')], ['Since', fmtDate(d.seat.since)], ['Language', LN[app.user.lang] || 'English']].map(([a, b]) => h('div', {}, h('span', { text: a }), h('b', { text: b || '' })))))));
    breakCard.querySelector('.card__head p')?.remove(); breakCard.querySelector('.card__head .grow').append(h('p', { text: 'By ' + d.breakdown_label.replace(/^(State|Community)/, (m) => m.toLowerCase()) }));
    const share = () => h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async (e) => { const link = `${location.origin}/#/join/${d.seat.network}`; try { if (navigator.share) await navigator.share({ title: net.name, text: tf('Join {1} on WDOS', net.name), url: link }); else { await navigator.clipboard.writeText(link); e.target.textContent = 'Link copied'; } } catch { /* cancelled */ } } }, tf('Share the {1} joining link', d.seat.network));
    if (!k.members) {
      trend.replaceChildren(empty('chart', 'No members in your area yet', tf('When women in {1} join {2}, their registrations are charted here.', d.seat.unit, d.seat.network), share()));
      breakdown.replaceChildren(empty('pin', 'Nothing to map yet', 'Your members are grouped by where they live once they join.'));
    } else {
      clear(trend).classList.add('reveal'); lineChart(trend, { labels: d.series.map((p) => p.date), series: [{ name: d.seat.network, color, values: d.series.map((p) => p[d.seat.network]) }] });
      bars(clear(breakdown), { rows: d.breakdown.map((b) => ({ label: b.label, segments: [{ name: d.seat.network, color, value: Number(b.total) }] })) });
    }
    team.replaceChildren(d.team.length ? h('div', { class: 'list reveal' }, d.team.map((p) => h('div', {}, avatar(p, 40), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: `${p.title}, ${p.area_label || p.unit}` })), h('small', { style: 'flex:none', text: p.last_seen_at ? tf('Seen {1}', agoIn(p.last_seen_at)) : 'Not signed in yet' }))))
      : empty('award', 'No leaders under your seat yet', 'WODDI HQ appoints leaders. As seats below yours are filled, they are listed here with when they were last active.'));
    recent.replaceChildren(d.recent.length ? peopleList(d.recent) : empty('user', 'Nobody has joined yet', 'Share your network joining link to bring in your first members.', share()));
    void icon;
  }
  load();
}

/* HQ: every seated leader, to open any of their dashboards. */
async function picker(root) {
  const list = h('div', {}, sk.rows(6)); const search = h('input', { class: 'input', type: 'search', placeholder: 'Search by name, position or place', 'aria-label': 'Search leaders', style: 'max-width:300px' });
  const net = h('select', { class: 'input', 'aria-label': 'Network', style: 'width:auto' }, [['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']].map(([v, l]) => h('option', { value: v, text: l })));
  const country = h('select', { class: 'input', 'aria-label': 'Country', style: 'width:auto' }, h('option', { value: '', text: 'All countries' }));
  root.append(card('Leader dashboards', 'Open any volunteer leader\'s dashboard exactly as that leader sees it. Read-only.', list, h('div', { class: 'toolbar' }, search, net, country)));
  let d; try { d = await api('/structure'); } catch (e) { list.replaceChildren(empty('x', 'Leaders did not load', e.message)); return; }
  const leaders = d.rows.filter((r) => r.kind === 'filled').sort((a, b) => a.rank - b.rank || a.holder.first_name.localeCompare(b.holder.first_name));
  const have = new Set(leaders.map((r) => r.country_code)); country.append(...d.countries.filter((c) => have.has(c.code)).map((c) => h('option', { value: c.code, text: c.name })));
  const paint = () => { const q = search.value.trim().toLowerCase();
    const rows = leaders.filter((r) => (!net.value || r.network === net.value) && (!country.value || r.country_code === country.value) && (!q || `${r.holder.first_name} ${r.holder.last_name} ${r.title} ${r.area} ${r.unit}`.toLowerCase().includes(q)));
    list.replaceChildren(rows.length ? h('div', { class: 'list reveal' }, rows.slice(0, 200).map((r) => h('a', { class: 'list__link', href: '#/leaders/' + r.holder.id }, avatar(r.holder, 40),
      h('div', { class: 'grow' }, h('b', { text: `${r.holder.first_name} ${r.holder.last_name}` }), h('small', { text: `${r.title}, ${r.area ? r.area + ', ' : ''}${r.unit} · ${r.holder.last_seen_at ? tf('seen {1}', agoIn(r.holder.last_seen_at)) : 'not signed in yet'}` })),
      h('div', { class: 'tagrow' }, flag(r.country_code), netTag(r.network), h('span', { class: 'btn btn--quiet btn--s', text: 'Open dashboard' })))), rows.length > 200 ? h('p', { class: 'faint', style: 'padding:12px 4px', text: tf('Showing the first 200 of {1} leaders. Search or filter to narrow.', fmt(rows.length)) }) : null)
      : empty('award', 'No leader matches', leaders.length ? 'Change the search or the filters.' : 'No leadership seat is filled yet. Appoint leaders from the Members page.'));
  };
  [search, net, country].forEach((x) => x.addEventListener('input', paint)); paint();
}
