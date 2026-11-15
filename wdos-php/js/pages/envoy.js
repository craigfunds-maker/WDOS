/* The WODDI Envoy's own dashboard: one referral link, the people who joined through it, how far each is in the 3-day activation, tasks, and messages. */
import { h, t, icon, avatar, card, sk, empty, ago, fmt, fmtDate, toast, greeting } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { kpi } from './hq.js';

export function stepsBar(a) {
  if (!a) return h('span', { class: 'tag', text: t('Not started') });
  const dots = h('span', { class: 'steps3', 'aria-label': tf('{1} of 3 days done', a.days_done) }, [1, 2, 3].map((n) => h('i', { class: n <= a.days_done ? 'is-done' : n <= a.days_opened ? 'is-open' : '' })));
  const label = a.decided ? t('Assessment decided') : a.assessment_submitted ? t('Assessment submitted') : a.days_done >= 3 ? t('3 days done') : a.days_opened ? tf('Day {1} of 3', Math.min(3, a.days_done + 1)) : t('Not started');
  return h('span', { style: 'display:inline-flex;gap:8px;align-items:center' }, dots, h('small', { text: label }));
}
export function peopleTable(people, opts = {}) {
  if (!people.length) return empty('users', opts.emptyTitle || 'Nobody has joined through this link yet', opts.emptyText || 'Share the link. Each person who joins appears here, with how far she is in her 3 days.');
  return h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Person', 'Joined', '3-day activation', 'Last seen', ''].map((c, i) => h('th', { class: i === 1 || i === 3 || i === 4 ? 'hide-s' : '', text: c })))),
    h('tbody', {}, people.map((p) => h('tr', { style: 'cursor:default' },
      h('td', {}, h('div', { class: 'who' }, avatar(p, 34), h('div', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { class: 'muted', text: [p.network, p.role, p.state_region, p.country].filter(Boolean).join(' · ') }),
        opts.message === false ? null : h('a', { class: 'btn btn--quiet btn--s show-s', 'data-tour': 'envoy-msg', href: '#/inbox/to/' + p.id, style: 'margin-top:6px' }, icon('send', 16), t('Message'))))),
      h('td', { class: 'hide-s' }, h('small', { text: fmtDate(p.referred_at) })),
      h('td', {}, stepsBar(p.activation), p.activation && p.activation.deadline ? h('small', { class: 'faint', style: 'display:block', text: tf('Finish by {1}', fmtDate(p.activation.deadline)) }) : null),
      h('td', { class: 'hide-s' }, h('small', { text: p.last_seen_at ? ago(p.last_seen_at) : t('Never') })),
      h('td', { class: 'hide-s' }, opts.message === false ? null : h('a', { class: 'btn btn--quiet btn--s', href: '#/inbox/to/' + p.id }, icon('send', 16), t('Message'))))))));
}

export async function render(root) {
  const top = h('div', {}, sk.rows(2)), kp = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()), ppl = h('div', {}, sk.rows(4)), side = h('div', {}, sk.rows(2));
  root.append(h('section', { class: 'hero' }, h('h1', { class: 'display', text: tf('{1}, {2}', greeting(), app.user.first_name) }), h('p', { text: t('You are a WODDI Envoy. Share your link, follow up the people who join, and help each one finish her 3 days.') })),
    top, kp, h('div', { class: 'row row--2-1' }, card('People who joined through your link', 'Their 3-day activation, day by day. Message anyone who has stopped.', ppl), side));
  let d; try { d = await api('/envoy/home'); } catch (e) { top.replaceChildren(h('div', { class: 'card' }, empty('x', 'Your dashboard did not load', e.message))); kp.replaceChildren(); ppl.replaceChildren(); side.replaceChildren(); return; }
  const k = d.kpis;
  const copy = async () => { try { await navigator.clipboard.writeText(d.link); toast('Link copied', 'Paste it into WhatsApp, a text or an email.', 'ok'); } catch { toast('Copy it by hand', d.link, 'info'); } };
  const share = navigator.share ? h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => navigator.share({ title: 'WODDI', text: t('Join WODDI through my link:'), url: d.link }).catch(() => {}) }, icon('send', 18), t('Share')) : null;
  top.replaceChildren(card('Your referral link', d.status === 'active' ? 'Anyone who joins through this link is counted as yours.' : d.status === 'pending' ? 'HQ is checking your sign-up. Your link switches on when HQ approves you. You are told by notification and email.' : 'Your link is paused by HQ. Nobody can join through it until HQ switches it back on.',
    h('div', { style: 'display:grid;gap:10px' }, h('code', { class: 'reflink', style: 'overflow-wrap:anywhere;font-size:15px', text: d.link }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn', 'data-tour': 'envoy-copy', type: 'button', onclick: copy }, t('Copy link')), share,
      h('a', { class: 'btn btn--quiet', 'data-tour': 'envoy-wa', href: 'https://wa.me/?text=' + encodeURIComponent(t('Join WODDI through my link:') + ' ' + d.link), target: '_blank', rel: 'noopener' }, t('Send on WhatsApp'))))));
  kp.replaceChildren(kpi('eye', 'Visits to your link', k.visits_30, [h('span', { text: tf('{1} in total', fmt(k.visits_total)) })]), kpi('users', 'People who joined', k.referred, [h('span', { text: tf('{1} in the last 30 days', fmt(k.referred_30)) })]),
    kpi('route', 'Finished the 3 days', k.three_days_done, [h('span', { text: tf('{1} started', fmt(k.activation_started)) })]), kpi('check', 'Assessments submitted', k.assessment_submitted, [h('span', { text: tf('{1} active this week', fmt(k.active_7)) })]));
  ppl.replaceChildren(peopleTable(d.people));
  side.replaceChildren(card('My tasks', null, h('div', { style: 'display:grid;gap:10px' }, h('div', { class: 'kv' }, h('div', {}, h('span', { text: t('Open') }), h('b', { text: fmt(d.tasks.open) })), h('div', {}, h('span', { text: t('Overdue') }), h('b', { text: fmt(d.tasks.overdue) })), h('div', {}, h('span', { text: t('Submitted') }), h('b', { text: fmt(d.tasks.submitted) }))), h('a', { class: 'btn btn--quiet', href: '#/tasks' }, t('Open my tasks')))),
    card('Messages', null, h('div', { style: 'display:grid;gap:10px' }, h('p', { style: 'margin:0', text: d.unread ? tf('{1} unread', d.unread) : t('No unread messages.') }), h('a', { class: 'btn btn--quiet', href: '#/inbox' }, t('Open messages')))),
    card('How to help', null, h('ol', { class: 'steps', style: 'margin:0;padding-left:20px;display:grid;gap:6px' }, ['Share your link with women who should join WODDI.', 'Check this page each day. A grey dot means a day not yet done.', 'Send a short message to anyone who has stopped. She gets it in the app, on her phone and by email.', 'Finish the tasks HQ gives you.'].map((x) => h('li', { text: t(x) })))));
}
