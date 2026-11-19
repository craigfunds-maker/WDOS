/* v109.22: HQ dashboard panel: birthdays today and this week, members WDOS has no date of birth for, today's thought,
 * and every automated greeting and inspiration send with how many people it reached in the app, by phone and by email. */
import { h, t, tf, icon, empty, sk, fmt, ago } from './ui.js';
import { api } from './api.js';
const KIND = { birthday: 'Birthday greetings', daily: 'Thought for today', weekly: 'Weekly inspiration', monthly: 'Monthly inspiration', dob_ask: 'Asked for a date of birth' };
export function celebrationsPanel() {
  const box = h('div', { style: 'display:grid;gap:16px' }, sk.rows(4));
  api('/hq/celebrations').then((r) => {
    const person = (p, extra) => h('div', { class: 'list__link', style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' },
      h('div', { class: 'grow', style: 'min-width:160px' }, h('b', { text: p.name }), h('small', { text: [p.network, p.country, p.leader ? t('Volunteer leader') : t('Member'), extra].filter(Boolean).join(' · ') })),
      h('span', { class: 'tag', title: t('Phone notifications'), text: p.phone_alerts ? t('Phone: on') : t('Phone: off') }), h('span', { class: 'tag', text: p.email ? t('Email: yes') : t('Email: none') }));
    const miss = r.missing.reduce((a, m) => a + m.members, 0), missL = r.missing.reduce((a, m) => a + m.leaders, 0);
    box.replaceChildren(
      h('div', {}, h('b', { text: tf('Birthdays today: {1}', r.today.length) }), r.today.length ? h('div', { class: 'list list--stack', style: 'margin-top:8px' }, r.today.map((p) => person(p))) : h('p', { class: 'muted', style: 'margin:4px 0 0', text: t('No birthdays today.') })),
      h('div', {}, h('b', { text: tf('In the next seven days: {1}', r.next.length) }), r.next.length ? h('div', { class: 'list list--stack', style: 'margin-top:8px' }, r.next.slice(0, 12).map((p) => person(p, new Date(p.on + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })))) : null),
      h('div', { class: 'panel', style: 'display:grid;gap:4px' }, h('b', { text: tf('Members with no date of birth: {1}', fmt(miss)) }),
        h('small', { class: 'muted', text: miss ? tf('{1} of them are volunteer leaders. Each is asked for it on her dashboard; until she adds it, WDOS cannot greet her.', fmt(missL)) : t('Every member has a date of birth.') }),
        miss ? h('a', { class: 'btn btn--ghost btn--s', href: '#/hq/birthdays', style: 'justify-self:start' }, t('See who')) : null),
      h('div', { class: 'panel', style: 'display:grid;gap:4px' }, h('b', { text: t('Thought for today') }), h('small', { text: 'WGMN: ' + (r.daily.WGMN || '–') }), h('small', { text: 'WNNN: ' + (r.daily.WNNN || '–') })),
      h('div', {}, h('b', { text: t('What went out') }), r.mail ? null : h('p', { class: 'muted', style: 'margin:4px 0', text: t('Email is not set up, so these went in the app and by phone only.') }),
        r.sends.length ? h('div', { class: 'tablewrap', style: 'margin-top:8px' }, h('table', { class: 'table' },
          h('thead', {}, h('tr', {}, ['Message', 'When', 'In the app', 'Phone', 'Email'].map((x) => h('th', { text: t(x) })))),
          h('tbody', {}, r.sends.map((s) => h('tr', {}, h('td', { text: s.label }), h('td', { text: ago(s.at) }), h('td', { class: 'num', text: fmt(s.in_app) }), h('td', { class: 'num', text: fmt(s.push) }), h('td', { class: 'num', text: fmt(s.email) }))))))
          : h('p', { class: 'muted', style: 'margin:4px 0 0', text: t('Nothing has gone out since this record began. The next thought for today goes from 07:00 Lagos time.') })));
  }).catch((e) => box.replaceChildren(empty('x', 'This did not load', e.message)));
  return box;
}
/* #/hq/birthdays: the members WDOS has no date of birth for */
export async function missingPage(root) {
  const list = h('div', {}, sk.rows(6)); root.append(h('p', { class: 'muted', text: t('Members WDOS has no date of birth for. Each one is asked for it on her dashboard. Leaders are listed first.') }), list);
  try { const r = await api('/hq/celebrations/missing');
    list.replaceChildren(r.members.length ? h('div', { class: 'list list--stack' }, r.members.map((m) => h('a', { class: 'list__link', href: '#/members/' + m.id }, h('div', { class: 'grow' }, h('b', { text: m.name }), h('small', { text: [m.member_no, m.network, m.country, m.leader ? t('Volunteer leader') : ''].filter(Boolean).join(' · ') })), icon('chevron', 16)))) : empty('check', 'Everyone has a date of birth', ''));
  } catch (e) { list.replaceChildren(empty('x', 'This did not load', e.message)); }
}
