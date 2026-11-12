/* HQ dashboard. Every figure is a count of real rows; an empty system shows empty states, never sample numbers. */
import { h, clear, icon, avatar, flag, netTag, card, sk, empty, ago, fmt, greeting, fmtDate } from '../ui.js';
import { pushNudge } from '../pushnudge.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { lineChart, donut, bars, sparkline, countUp, seriesColor, legend } from '../charts.js';

export function rangeSeg(days, onPick) {
  return h('div', { class: 'seg', role: 'group', 'aria-label': 'Period' }, [7, 30, 90].map((d) => h('button', { type: 'button', 'aria-pressed': String(d === days), onclick: () => onPick(d), text: d + ' days' })));
}
export function delta(now, prev, days) {
  if (!prev && !now) return h('span', { class: 'delta delta--flat' }, icon('flat', 14), 'No change');
  if (!prev) return h('span', { class: 'delta delta--up' }, icon('up', 14), 'First ' + days + ' days');
  const p = Math.round(((now - prev) / prev) * 100);
  return h('span', { class: 'delta delta--' + (p > 0 ? 'up' : p < 0 ? 'down' : 'flat') }, icon(p > 0 ? 'up' : p < 0 ? 'down' : 'flat', 14), (p > 0 ? '+' : '') + p + '%');
}
export function kpi(ic, label, value, foot) {
  const v = h('div', { class: 'kpi__value', text: '0' }); if (typeof value === 'string' && isNaN(Number(value))) { v.textContent = value; if (value.length > 6) v.style.cssText = 'font-size:24px;line-height:1.3;letter-spacing:0'; } else countUp(v, value);
  return h('div', { class: 'card kpi reveal' }, h('div', { class: 'kpi__label' }, icon(ic, 17), label), v, h('div', { class: 'kpi__foot' }, foot));
}
export function peopleList(items, hrefFor) {
  return h('div', { class: 'list' }, items.map((p) => h(hrefFor ? 'a' : 'div', { class: hrefFor ? 'list__link' : '', href: hrefFor ? hrefFor(p) : null }, avatar(p, 40),
    h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: [p.state_region, p.member_no].filter(Boolean).join(' · ') })), flag(p.country_code), netTag(p.network), h('small', { class: 'hide-s', style: 'flex:none', text: ago(p.created_at) }))));
}
const ACTION = { registered: 'joined', appointed: 'was appointed', seat_closed: 'left a leadership seat', password_reset: 'had a password reset by HQ', suspended: 'was suspended', reinstated: 'was reinstated', hq_access_given: 'was given HQ access', hq_access_removed: 'had HQ access removed', announcement: 'HQ sent an announcement', report_submitted: 'submitted a report', details_corrected: 'had details corrected by HQ', import: 'Accounts were brought across' };

export async function render(root) {
  let days = Number(sessionStorage.getItem('wdos.days')) || 30;
  const u = app.user;
  const seg = h('div');
  const hero = h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { class: 'display', text: `${greeting()}, ${u.first_name}` }),
    h('p', { text: new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + '. Here is where both networks stand.' })), seg);
  const kpis = h('div', { class: 'row row--4' });
  const trend = h('div'), split = h('div'), countries = h('div'), seats = h('div'), recent = h('div'), feed = h('div');
  root.append(hero, pushNudge(), kpis,
    h('div', { class: 'row row--2-1' }, card('New members', 'Registrations per day, by network', trend, legend([{ name: 'WGMN', color: seriesColor.WGMN }, { name: 'WNNN', color: seriesColor.WNNN }])), card('Network split', 'All members to date', split)),
    h('div', { class: 'row row--2' }, card('Members by country', 'The eight largest', countries), card('Volunteer leaders seated', 'Open seats by level', seats)),
    h('div', { class: 'row row--2' }, card('Newest members', null, recent, h('a', { class: 'btn btn--ghost btn--s', href: '#/members', text: 'All members' })), card('Recent activity', null, feed)));

  async function load() {
    seg.replaceChildren(rangeSeg(days, (d) => { days = d; sessionStorage.setItem('wdos.days', d); load(); }));
    kpis.replaceChildren(sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi());
    trend.replaceChildren(sk.chart(250)); split.replaceChildren(sk.chart(250)); countries.replaceChildren(sk.bars(6)); seats.replaceChildren(sk.bars(5)); recent.replaceChildren(sk.rows(5)); feed.replaceChildren(sk.rows(5));
    let d;
    try { d = await api('/hq/dashboard?days=' + days); }
    catch (e) { kpis.replaceChildren(); [trend, split, countries, seats, recent, feed].forEach((x) => x.replaceChildren(empty('x', 'Could not load this', e.message, h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Try again')))); return; }
    const k = d.kpis, totals = d.series.map((p) => p.WGMN + p.WNNN);
    kpis.replaceChildren(
      kpi('users', 'Members', k.members, [h('span', { text: `${fmt(k.active_7d)} active this week` })]),
      kpi('plus', `Joined in ${days} days`, k.new_now, [h('span', {}, delta(k.new_now, k.new_prev, days), k.new_prev ? ' on the previous ' + days : ''), sparkline(totals)]),
      kpi('award', 'Volunteer leaders seated', k.leaders, [h('span', { text: k.leaders ? 'Across both networks' : 'Appoint leaders from Members' })]),
      kpi('globe', 'Countries with members', k.countries, [h('span', { text: 'of 54 African countries' })]));
    if (!k.members) {
      const invite = () => h('a', { class: 'btn btn--quiet btn--s', href: '#/', onclick: (e) => { e.preventDefault(); navigator.clipboard?.writeText(location.origin + '/#/'); e.target.textContent = 'Link copied'; } }, 'Copy the joining link');
      trend.replaceChildren(empty('chart', 'No registrations yet', 'This chart draws itself as soon as the first member joins through the network page.', invite()));
      split.replaceChildren(empty('users', 'No members yet', 'The WGMN and WNNN split appears with the first registrations.'));
      countries.replaceChildren(empty('globe', 'No countries yet', 'Members choose a country when they join.'));
    } else {
      clear(trend).classList.add('reveal'); lineChart(trend, { labels: d.series.map((p) => p.date), series: [{ name: 'WGMN', color: seriesColor.WGMN, values: d.series.map((p) => p.WGMN) }, { name: 'WNNN', color: seriesColor.WNNN, values: d.series.map((p) => p.WNNN) }] });
      donut(clear(split), { label: 'members', items: [{ name: 'WGMN', color: seriesColor.WGMN, value: k.wgmn }, { name: 'WNNN', color: seriesColor.WNNN, value: k.wnnn }] });
      bars(clear(countries), { rows: d.by_country.map((c) => ({ label: c.name, lead: flag(c.code), segments: [{ name: 'WGMN', color: seriesColor.WGMN, value: Number(c.WGMN) }, { name: 'WNNN', color: seriesColor.WNNN, value: Number(c.WNNN) }] })) });
    }
    if (!k.leaders) seats.replaceChildren(empty('award', 'No leaders seated yet', 'Open a member, choose Appoint to a seat, and the leadership structure builds up here.', h('a', { class: 'btn btn--quiet btn--s', href: '#/members', text: 'Open Members' })));
    else bars(clear(seats), { rows: d.seats_by_level.map((l) => ({ label: l.label, segments: [{ name: 'WGMN', color: seriesColor.WGMN, value: l.WGMN }, { name: 'WNNN', color: seriesColor.WNNN, value: l.WNNN }] })) });
    recent.replaceChildren(d.recent.length ? peopleList(d.recent, (p) => '#/members/' + p.id) : empty('user', 'Nobody has joined yet', 'New members appear here the moment they register.'));
    feed.replaceChildren(d.activity.length ? h('div', { class: 'list reveal' }, d.activity.map((a) => h('div', {}, h('div', { class: 'nrow__ico' }, icon(a.action === 'registered' ? 'user' : a.action === 'announcement' ? 'megaphone' : a.action === 'appointed' ? 'award' : 'shield', 18)),
      h('div', { class: 'grow' }, h('b', { text: a.action === 'announcement' ? ACTION.announcement : `${a.subject} ${ACTION[a.action] || a.action}` }), h('small', { text: [a.detail, ago(a.created_at)].filter(Boolean).join(' · ') })))))
      : empty('clock', 'No activity yet', 'Registrations, appointments and HQ actions are recorded here.'));
    void fmtDate;
  }
  load();
  const onNote = (e) => { if (e.detail.some((n) => n.kind === 'registration') && root.isConnected) load(); };
  window.addEventListener('wdos:notified', onNote);
}
