/* Volunteer leadership dashboard: everything is scoped to the leader's own seat and network. */
import { h, clear, icon, avatar, flag, netTag, card, sk, empty, ago, agoIn, fmt, greeting, fmtDate, NETS, inspCard } from '../ui.js';
import { goodMotherCard } from '../goodmother.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { lineChart, bars, sparkline, seriesColor } from '../charts.js';
import { rangeSeg, delta, kpi, peopleList } from './hq.js';

export async function render(root) {
  const u = app.user; let days = Number(sessionStorage.getItem('wdos.days')) || 30;
  const net = NETS[u.seat?.network || u.network] || NETS.WGMN; const color = seriesColor[u.seat?.network || u.network] || seriesColor.WGMN;
  const seg = h('div'), meta = h('div', { class: 'hero__meta' });
  const hero = h('section', { class: 'hero' }, avatar(u, 76), h('div', { class: 'grow' }, h('h1', { class: 'display', text: `${greeting()}, ${u.first_name}` }), h('p', { text: u.seat?.title || '' }), meta), h('img', { class: 'hero__disc', src: net.disc, alt: net.name }), seg);
  const kpis = h('div', { class: 'row row--4' }); const trend = h('div'), breakdown = h('div'), team = h('div'), recent = h('div');
  const breakCard = card('Where your members are', null, breakdown);
  const today = h('div', { hidden: true });
  api('/inspiration/today').then((r) => { if (r.card) { today.hidden = false; today.append(inspCard(r.card)); } }).catch(() => {});
  const todo = u.todo || {}; const nudge = (title, text, href, label) => h('section', { class: 'card nudge' }, h('div', { class: 'card__body', style: 'display:flex;gap:16px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'grow' }, h('b', { text: title }), h('p', { class: 'muted', style: 'margin:4px 0 0', text })), h('a', { class: 'btn', href, text: label })));
  if (todo.appointment) root.append(hero, nudge('Accept your appointment', 'Read the Volunteer Leadership Agreement to the end and accept it to take up your seat.', '#/appointment', 'Read the Agreement'));
  else if (todo.onboarding > 0) root.append(hero, nudge('Your onboarding', `${todo.onboarding} required ${todo.onboarding === 1 ? 'step is' : 'steps are'} still to finish. Work through each step in order.`, '#/onboarding', 'Continue my onboarding'));
  root.append(hero, today, kpis, u.network === 'WGMN' && u.country_code ? goodMotherCard(u.country_code, u.country) : '', h('div', { class: 'row row--2-1' }, card('New members in your area', 'Registrations per day', trend), breakCard),
    h('div', { class: 'row row--2' }, card('Your leadership team', 'Leaders serving under your seat', team), card('Newest members', null, recent, h('a', { class: 'btn btn--ghost btn--s', href: '#/members', text: 'All my members' }))));

  async function load() {
    seg.replaceChildren(rangeSeg(days, (d) => { days = d; sessionStorage.setItem('wdos.days', d); load(); }));
    kpis.replaceChildren(sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()); trend.replaceChildren(sk.chart(250)); breakdown.replaceChildren(sk.bars(6)); team.replaceChildren(sk.rows(4)); recent.replaceChildren(sk.rows(4));
    let d;
    try { d = await api('/leader/dashboard?days=' + days); }
    catch (e) { kpis.replaceChildren(); [trend, breakdown, team, recent].forEach((x) => x.replaceChildren(empty('x', 'Could not load this', e.message, h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Try again')))); return; }
    const k = d.kpis;
    meta.replaceChildren(flag(d.seat.country_code), netTag(d.seat.network), h('span', { class: 'tag', text: d.seat.status === 'appointed' ? 'Appointed' : d.seat.status === 'acting' ? 'Acting' : 'Pending confirmation' }), h('span', { class: 'tag', text: 'Since ' + fmtDate(d.seat.since) }), u.member_no ? h('span', { class: 'tag num', text: u.member_no }) : null);
    kpis.replaceChildren(
      kpi('users', 'Members in your area', k.members, [h('span', { text: `${fmt(k.active_7d)} active this week` })]),
      kpi('plus', `Joined in ${days} days`, k.new_now, [h('span', {}, delta(k.new_now, k.new_prev, days)), sparkline(d.series.map((p) => p.WGMN + p.WNNN), color)]),
      kpi('award', 'Leaders under you', k.team, [h('span', { text: d.reports_to ? `You report to ${d.reports_to.first_name} ${d.reports_to.last_name}` : 'You report to WODDI HQ' })]),
      kpi('camera', 'Profiles with a photograph', k.with_photo, [h('span', { text: k.members ? Math.round((k.with_photo / k.members) * 100) + '% of your members' : 'No members yet' })]));
    breakCard.querySelector('.card__head p')?.remove(); breakCard.querySelector('.card__head .grow').append(h('p', { text: 'By ' + d.breakdown_label.replace(/^(State|Community)/, (m) => m.toLowerCase()) }));
    const share = () => h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async (e) => { const link = `${location.origin}/#/join/${d.seat.network}`; try { if (navigator.share) await navigator.share({ title: net.name, text: `Join ${net.name} on WDOS`, url: link }); else { await navigator.clipboard.writeText(link); e.target.textContent = 'Link copied'; } } catch { /* cancelled */ } } }, `Share the ${d.seat.network} joining link`);
    if (!k.members) {
      trend.replaceChildren(empty('chart', 'No members in your area yet', `When women in ${d.seat.unit} join ${d.seat.network}, their registrations are charted here.`, share()));
      breakdown.replaceChildren(empty('pin', 'Nothing to map yet', 'Your members are grouped by where they live once they join.'));
    } else {
      clear(trend).classList.add('reveal'); lineChart(trend, { labels: d.series.map((p) => p.date), series: [{ name: d.seat.network, color, values: d.series.map((p) => p[d.seat.network]) }] });
      bars(clear(breakdown), { rows: d.breakdown.map((b) => ({ label: b.label, segments: [{ name: d.seat.network, color, value: Number(b.total) }] })) });
    }
    team.replaceChildren(d.team.length ? h('div', { class: 'list reveal' }, d.team.map((p) => h('div', {}, avatar(p, 40), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: `${p.title}, ${p.area_label || p.unit}` })), h('small', { style: 'flex:none', text: p.last_seen_at ? 'Seen ' + agoIn(p.last_seen_at) : 'Not signed in yet' }))))
      : empty('award', 'No leaders under your seat yet', 'WODDI HQ appoints leaders. As seats below yours are filled, they are listed here with when they were last active.'));
    recent.replaceChildren(d.recent.length ? peopleList(d.recent) : empty('user', 'Nobody has joined yet', 'Share your network joining link to bring in your first members.', share()));
    void icon;
  }
  load();
}
