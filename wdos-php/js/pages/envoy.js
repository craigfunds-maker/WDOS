/* The WODDI Envoy's own dashboard: one referral link, the people who joined through it, how far each is in the 3-day activation, tasks, and messages. */
import { h, t, tf, icon, avatar, card, sk, empty, ago, fmt, fmtDate, toast, greeting, netTag } from '../ui.js';
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

/** A flag in her language: the number goes into a whole sentence, so it translates. */
export function flagText(f) {
  if (f.kind === 'task_overdue') return f.n === 1 ? t('1 task overdue') : tf('{1} tasks overdue', f.n);
  if (f.kind === 'no_signin') return tf('Not signed in for {1} days', f.n);
  return t(f.text);
}
/** v109.31: the volunteer leaders she brought in, the ones who need her first: overdue tasks, not signing in, stopped serving. */
function leadersList(list) {
  if (!list.length) return empty('users', 'No volunteer leaders yet', 'When someone who joined through your link is appointed to a leadership seat, she appears here, with her tasks and when she last signed in.');
  return h('div', { class: 'list list--stack' }, list.map((l) => h('div', { class: 'list__link', style: 'display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap' }, avatar(l, 40),
    h('div', { class: 'grow', style: 'min-width:180px;display:grid;gap:3px' }, h('b', { text: `${l.first_name} ${l.last_name}`.trim() }),
      h('small', { text: [l.title, l.serving ? '' : t('no longer serving')].filter(Boolean).join(' · ') }),
      h('small', { class: 'muted', text: [l.last_seen_at ? tf('Seen {1}', ago(l.last_seen_at)) : t('Never signed in'), tf('{1} open tasks', l.tasks_open), tf('{1} done', l.tasks_done)].join(' · ') }),
      l.flags.length ? h('div', { class: 'tagrow', style: 'justify-content:flex-start' }, l.flags.map((f) => h('span', { class: 'tag tag--bad', text: flagText(f) }))) : h('span', { class: 'tag tag--wnnn', style: 'justify-self:start', text: t('On track') })),
    h('div', { style: 'display:flex;gap:6px;flex-wrap:wrap' }, netTag(l.network), h('a', { class: 'btn btn--s', href: '#/inbox/to/' + l.id }, icon('send', 16), ' ', t('Message'))))));
}

export async function render(root) {
  const top = h('div', {}, sk.rows(2)), kp = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()), lead = h('div', {}, sk.rows(4)), ppl = h('div', {}, sk.rows(3)), side = h('div', {}, sk.rows(2));
  const sub = h('p', { text: t('Share your referral link. Volunteer leaders who join through it are yours to follow up: WDOS tells you when one has an overdue task, stops signing in or leaves her seat.') });
  root.append(h('section', { class: 'hero' }, h('h1', { class: 'display', text: tf('{1}, {2}', greeting(), app.user.first_name) }), sub),
    top, kp, h('div', { class: 'row row--2-1' }, h('div', { style: 'display:grid;gap:20px' }, card('Volunteer leaders you brought in', 'Those who need you are at the top. Send her a message.', lead), card('Everyone who joined through your link', 'Members and leaders, and how far each is in her first days.', ppl)), side));
  let d; try { d = await api('/envoy/home'); } catch (e) { top.replaceChildren(h('div', { class: 'card' }, empty('x', 'Your dashboard did not load', e.message))); kp.replaceChildren(); lead.replaceChildren(); ppl.replaceChildren(); side.replaceChildren(); return; }
  if (d.designation) sub.before(h('p', { class: 'muted', style: 'margin:0', text: d.designation }));
  const k = d.kpis, L = d.leader_kpis || { brought_in: 0, serving: 0, attention: 0, overdue: 0 };
  const msg = t('Join WODDI through my link:') + ' ' + d.link;
  const copy = async () => { try { await navigator.clipboard.writeText(d.link); toast(t('Link copied'), t('Paste it into WhatsApp, a text or an email.'), 'ok'); } catch { toast(t('Copy it by hand'), d.link, 'info'); } };
  const share = navigator.share ? h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => navigator.share({ title: 'WODDI', text: msg }).catch(() => {}) }, icon('send', 18), t('Share')) : null;
  top.replaceChildren(card('Your referral link', d.status === 'active' ? 'Everyone who joins through this link is counted as yours.' : d.status === 'pending' ? 'HQ is checking your sign-up. Your link switches on when HQ approves you.' : 'Your link is paused by HQ. Nobody can join through it until HQ switches it back on.',
    h('div', { style: 'display:grid;gap:10px' }, h('code', { class: 'reflink', style: 'overflow-wrap:anywhere;font-size:15px', text: d.link }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn', 'data-tour': 'envoy-copy', type: 'button', onclick: copy }, t('Copy link')), share,
      h('a', { class: 'btn btn--quiet', 'data-tour': 'envoy-wa', href: 'https://wa.me/?text=' + encodeURIComponent(msg), target: '_blank', rel: 'noopener' }, t('Send on WhatsApp'))))));
  kp.replaceChildren(kpi('award', t('Volunteer leaders you brought in'), L.brought_in, [h('span', { text: tf('{1} serving now', fmt(L.serving)) })]),
    kpi('bell', t('Need your attention'), L.attention, [h('span', { class: L.overdue ? 'bad' : '', text: L.overdue ? tf('{1} tasks overdue', fmt(L.overdue)) : t('No task overdue') })]),
    kpi('users', t('People who joined'), k.referred, [h('span', { text: tf('{1} in the last 30 days', fmt(k.referred_30)) })]),
    kpi('eye', t('Visits to your link'), k.visits_30, [h('span', { text: tf('{1} in total', fmt(k.visits_total)) })]));
  lead.replaceChildren(leadersList(d.leaders || []));
  ppl.replaceChildren(peopleTable(d.people));
  side.replaceChildren(
    card('Messages', null, h('div', { style: 'display:grid;gap:10px' }, h('p', { style: 'margin:0', text: d.unread ? tf('{1} unread', d.unread) : t('No unread messages.') }), h('a', { class: 'btn btn--quiet', href: '#/inbox' }, t('Open messages')))),
    card('What you do', null, h('ol', { class: 'steps', style: 'margin:0;padding-left:20px;display:grid;gap:6px' }, ['Share your referral link with women who should serve as volunteer leaders.', 'Check this page each day. Leaders who need you are at the top.', 'When WDOS tells you a leader has an overdue task, has stopped signing in or has left her seat, message or call her.'].map((x) => h('li', { text: t(x) })))));
}
