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
  const meet = h('div', {}, sk.rows(2));
  import('./meetings.js').then(({ countdown }) => api('/hq/meetings/summary').then((r) => { const row = (m) => h('a', { class: 'list__link', href: '#/meetings/' + m.id, style: 'flex-wrap:wrap' }, h('div', { class: 'nrow__ico' }, icon('calendar', 18)),
      h('div', { class: 'grow', style: 'min-width:220px' }, h('b', { text: m.title }), h('small', { text: new Date(m.starts_at.replace(' ', 'T') + 'Z').toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + ' · ' + m.scope_label }), m.upcoming ? countdown(m) : null),
      h('div', { class: 'tagrow' }, h('span', { class: 'tag', text: `${fmt(m.invited)} invited` }), m.upcoming ? h('span', { class: 'tag tag--wnnn', text: `${m.rsvp.yes} yes · ${m.rsvp.maybe} maybe · ${m.rsvp.no} no` }) : h('span', { class: 'tag ' + (m.present ? 'tag--wnnn' : 'tag--wgmn'), text: m.present + m.absent ? `${m.present} present · ${m.absent} absent` : 'Attendance not marked' }), h('span', { class: 'tag', text: `${fmt(m.opened_link)} opened the link` })));
    const up = r.items.filter((m) => m.upcoming), past = r.items.filter((m) => !m.upcoming);
    meet.replaceChildren(r.items.length ? h('div', { style: 'display:grid;gap:14px' }, up.length ? h('div', { class: 'list list--stack' }, up.map(row)) : h('p', { class: 'muted', style: 'margin:0', text: 'No meeting is scheduled.' }), past.length ? h('div', {}, h('small', { class: 'eyebrow', text: 'Just held' }), h('div', { class: 'list list--stack' }, past.map(row))) : null, r.mail_on ? null : h('small', { class: 'faint', text: 'Email is switched off on the server, so invitations, reminders and minutes go in the app and by phone notification only.' }))
      : empty('calendar', 'No meetings yet', 'Schedule one and everyone invited is told and reminded automatically.')); })).catch((e) => meet.replaceChildren(empty('x', 'Meetings did not load', e.message)));
  let days = Number(sessionStorage.getItem('wdos.days')) || 30;
  const u = app.user;
  const seg = h('div');
  const hero = h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { class: 'display', text: `${greeting()}, ${u.first_name}` }),
    h('p', { text: new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + '. Here is where both networks stand.' })), seg);
  const kpis = h('div', { class: 'row row--4' });
  /* Operations: figures the readiness checklist asks HQ to watch. Each line says plainly when WDOS has nothing to measure yet. */
  const ops = h('div', {}, sk.rows(4)), terms = h('div', {}, sk.rows(3));
  const line = (label, value, note, tone) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: label }), note ? h('small', { text: note }) : null), h('span', { class: 'tag' + (tone ? ' tag--' + tone : ''), text: value }));
  const loadOps = () => api('/hq/operations').then((o) => {
    const a = o.automation, d = o.delivery, sp = o.support; const pw = h('input', { class: 'input', type: 'password', id: 'backup-pw', autocomplete: 'current-password', placeholder: 'Your password', style: 'max-width:220px' }); const bmsg = h('small', { class: 'muted', role: 'status' });
    const take = h('button', { class: 'btn btn--s', type: 'button', id: 'backup-go', onclick: async () => { bmsg.textContent = ''; take.disabled = true;
      try { const r = await fetch('/api/hq/backup', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WDOS': '1' }, body: JSON.stringify({ password: pw.value }) });
        if (!r.ok) { const j = await r.json().catch(() => ({})); throw new Error(j.message || 'The backup was not made.'); }
        const blob = await r.blob(); const name = (r.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/)?.[1] || 'wdos-backup.jsonl.gz'; const link = h('a', { href: URL.createObjectURL(blob), download: name }); document.body.append(link); link.click(); link.remove();
        pw.value = ''; bmsg.textContent = `Saved to this computer as ${name} (${fmt(Math.round(blob.size / 1024))} KB). Keep it somewhere private: it holds members' details.`; loadOps();
      } catch (e) { bmsg.textContent = e.message; } take.disabled = false; } }, 'Take a backup now');
    ops.replaceChildren(h('div', { class: 'list list--stack' },
      line('Automation share of routine work', a.verified_share === null ? 'Not calculable yet' : a.verified_share + '%', a.verified_share === null ? `${a.weighted} of ${a.workflows} workflows have a weight. Enter minutes and weights on the Digital coverage page. Target ${a.target}%.` : `Verified by a named person. Declared: ${a.declared_share}%. Target ${a.target}%.`, a.verified_share !== null && a.verified_share >= a.target ? 'wnnn' : ''),
      line('Phone notifications, last 7 days', `${fmt(d.push_sent_7d)} sent, ${fmt(d.push_failed_7d)} failed`, d.waiting ? `${fmt(d.waiting)} waiting to go out.` : 'Nothing waiting.', d.push_failed_7d ? 'bad' : ''),
      line('Email, last 7 days', d.email_on ? `${fmt(d.mail_sent_7d)} sent, ${fmt(d.mail_failed_7d)} failed` : 'Switched off', d.email_on ? '' : 'Email needs BREVO_API_KEY and MAIL_FROM in Render. Until then password reset by email and email reminders do not go out.', d.email_on ? (d.mail_failed_7d ? 'bad' : '') : 'bad'),
      line('Support: private reports open', fmt(sp.open_cases), sp.open_cases ? `The oldest has waited ${sp.oldest_open_days} days. ${fmt(sp.cases_30d)} received in 30 days.` : `${fmt(sp.cases_30d)} received in 30 days.`, sp.open_cases ? 'bad' : ''),
      line('Uptime', 'Not measured here', o.uptime.note),
      line('Last backup', o.backup.last ? fmtDate(o.backup.last) : 'Never', o.backup.last ? `Taken by ${o.backup.by} (GMT ${o.backup.last}).` : 'No backup has been taken from WDOS yet.', o.backup.last ? '' : 'bad')),
      u.role === 'super_admin' || u.is_super ? h('div', { class: 'panel', style: 'margin-top:14px;display:grid;gap:10px' }, h('b', { text: 'Backup' }), h('small', { class: 'muted', text: 'One file with everything WDOS holds, for a super administrator only. It is made now, downloaded to this computer and not kept on the server. Type your password to confirm.' }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center' }, pw, take), bmsg) : null);
  }).catch((e) => ops.replaceChildren(empty('x', 'This did not load', e.message)));
  const loadTerms = () => api('/hq/terms').then((r) => {
    const row = (x, ended) => h('a', { class: 'list__link', href: '#/members/' + x.user_id }, h('div', { class: 'grow' }, h('b', { text: x.name }), h('small', { text: [x.title, x.area, x.network].filter(Boolean).join(' · ') })), h('span', { class: 'tag' + (ended || x.days_left < 0 ? ' tag--bad' : ''), text: ended ? 'Ended ' + fmtDate(x.ended_at) : x.days_left < 0 ? `${-x.days_left} days over` : x.days_left === 0 ? 'Ends today' : `${x.days_left} days left` }));
    terms.replaceChildren(h('small', { class: 'muted', style: 'display:block;margin-bottom:10px', text: `${fmt(r.with_end_date)} of ${fmt(r.open_seats)} open appointments have an end date. ` + (r.rule === 'auto' ? 'Rule: a seat ends by itself on its last day.' : 'Rule: HQ is told, and the seat stays until HQ acts.') }),
      r.due.length || r.ended.length ? h('div', { class: 'list list--wrap' }, r.due.map((x) => row(x, false)), r.ended.map((x) => row(x, true))) : empty('award', 'No term is ending in the next 60 days', 'Set the last day of a term on the member\'s page, under Term of this appointment.'));
  }).catch((e) => terms.replaceChildren(empty('x', 'This did not load', e.message)));
  loadOps(); loadTerms();
  const trend = h('div'), split = h('div'), countries = h('div'), seats = h('div'), recent = h('div'), feed = h('div');
  root.append(hero, pushNudge(), kpis,
    h('div', { class: 'row row--2-1' }, card('New members', 'Registrations per day, by network', trend, legend([{ name: 'WGMN', color: seriesColor.WGMN }, { name: 'WNNN', color: seriesColor.WNNN }])), card('Network split', 'All members to date', split)),
    card('Meetings', 'Coming up and just held: who was invited, who replied, who opened the link and who attended', meet, h('a', { class: 'btn btn--ghost btn--s', href: '#/meetings', text: 'Schedule or open meetings' })),
    h('div', { class: 'row row--2' }, card('Operations', 'Automation share, delivery, support and backup', ops), card('Leader terms', 'Appointments with an end date, and seats that became vacant when a term ended', terms, h('a', { class: 'btn btn--ghost btn--s', href: '#/structure', text: 'Fill a vacant seat' }))),
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
      kpi('globe', 'Countries with members', k.countries, [h('span', { text: 'In Africa and the diaspora' })]));
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
