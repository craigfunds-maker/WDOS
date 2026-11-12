/* Messages between a person and WODDI HQ. Everyone writes to HQ; HQ answers from one shared inbox and can start a conversation with anyone. */
import { h, icon, avatar, flag, card, sk, empty, ago, toast, input, field, tabs, netTag, linkify } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { kpi } from './hq.js';

export async function render(root, params) {
  if (params.tab === 'new') return compose(root, params.id);
  if (params.tab && /^\d+$/.test(params.tab)) return thread(root, params.tab);
  const hq = app.user.is_hq; const tab = ['open', 'resolved', 'unread'].includes(params.tab) ? params.tab : '';
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi()); const list = h('div', {}, sk.rows(5));
  const search = hq ? input({ type: 'search', placeholder: 'Search by name or subject', 'aria-label': 'Search messages', style: 'max-width:260px' }) : null;
  root.append(tabs(hq ? [['', 'All'], ['unread', 'Unread'], ['open', 'Open'], ['resolved', 'Resolved']] : [['', 'All'], ['open', 'Open'], ['resolved', 'Resolved']], tab, '#/messages'), hq ? kpis : '',
    card(hq ? 'Messages from members and leaders' : 'My messages with WODDI HQ', hq ? 'One inbox shared by HQ staff. Replies are signed with your name.' : 'Write to WODDI HQ here. You are told in the app, on your phone and by email when HQ replies.', list,
      h('div', { class: 'toolbar' }, search, h('a', { class: 'btn', href: '#/messages/new' }, icon('plus', 18), hq ? 'Write to someone' : 'New message'))));
  let timer; if (search) search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
  async function load() {
    let r; try { r = await api('/messages?status=' + tab + (search ? '&q=' + encodeURIComponent(search.value) : '')); } catch (e) { kpis.replaceChildren(); list.replaceChildren(empty('x', 'Messages did not load', e.message)); return; }
    if (hq) kpis.replaceChildren(kpi('inbox', 'Waiting for a reply', r.counts.unread, [h('span', { text: 'Unread by HQ' })]), kpi('send', 'Open conversations', r.counts.open, [h('span', { text: 'Not yet resolved' })]), kpi('check', 'All conversations', r.counts.total, [h('span', { text: 'Since messages began' })]));
    list.replaceChildren(r.items.length ? h('div', { class: 'list list--stack reveal' }, r.items.map((t) => h('a', { class: 'list__link' + (t.unread ? ' is-unread' : ''), href: '#/messages/' + t.id }, hq ? avatar(t.person, 40) : h('div', { class: 'nrow__ico' }, icon('send', 18)),
      h('div', { class: 'grow' }, h('b', { text: (hq ? `${t.person.first_name} ${t.person.last_name}: ` : '') + t.subject }), h('small', { text: (t.last_from_hq ? 'WODDI HQ: ' : hq ? '' : 'You: ') + t.snippet })),
      h('div', { class: 'tagrow' }, t.unread ? h('span', { class: 'tag tag--wgmn', text: t.unread + ' new' }) : null, h('span', { class: 'tag' + (t.status === 'resolved' ? ' tag--wnnn' : ''), text: t.status === 'resolved' ? 'Resolved' : 'Open' }), h('small', { class: 'faint', text: ago(t.last_at) })))))
      : empty('send', 'No messages here', hq ? 'When a member or leader writes to HQ, the message arrives here.' : 'Start a conversation with WODDI HQ with the New message button.'));
  }
  load();
}

async function compose(root, toId) {
  const hq = app.user.is_hq; let to = null; const subject = input({ maxlength: 190 }); const body = h('textarea', { class: 'input', rows: 6, placeholder: 'Write your message' }); const err = h('p', { class: 'err', role: 'alert' });
  const cat = h('select', { class: 'input' }); const who = h('div'); const find = input({ type: 'search', placeholder: 'Search by name, email or membership number' }); const results = h('div', { class: 'picklist' });
  api('/messages').then((r) => cat.append(...Object.entries(r.categories).map(([v, l]) => h('option', { value: v, text: l })))).catch(() => {});
  const paintWho = () => who.replaceChildren(to ? h('div', { class: 'who' }, avatar(to, 40), h('div', {}, h('b', { text: `${to.first_name} ${to.last_name}` }), h('small', { text: [to.member_no, to.state_region].filter(Boolean).join(' · ') })), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => { to = null; paintWho(); } }, 'Change')) : h('div', { style: 'display:grid;gap:8px' }, find, results));
  let timer; find.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(async () => { try { const r = await api('/messages/people?q=' + encodeURIComponent(find.value)); results.replaceChildren(...r.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { to = p; paintWho(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: [p.network, p.state_region, p.member_no].filter(Boolean).join(' · ') }))))); } catch { /* keep typing */ } }, 250); });
  if (hq && toId) { try { const m = await api('/hq/members/' + toId); to = m.member || m; } catch { /* choose by search */ } }
  if (hq) paintWho();
  const send = h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; if (hq && !to) { err.textContent = 'Choose the person you are writing to.'; return; } send.disabled = true;
    try { const r = await api('/messages', { subject: subject.value, body: body.value, category: cat.value, to: to ? to.id : 0 }); toast('Message sent', hq ? '' : 'WODDI HQ has been notified.', 'ok'); location.hash = '#/messages/' + r.id; } catch (e) { err.textContent = e.message; send.disabled = false; } } }, icon('send', 18), 'Send message');
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/messages', style: 'justify-self:start' }, 'Back to messages'), card(hq ? 'Write to a member or leader' : 'New message to WODDI HQ', hq ? 'The person is told in the app, by push and by email.' : 'HQ staff read every message. Choose the closest topic so it reaches the right person.',
    h('div', { style: 'display:grid;gap:14px' }, hq ? field('To', who) : null, h('div', { class: 'grid2' }, field('Subject', subject), field('Topic', cat)), field('Message', body), err, h('div', {}, send))));
}

async function thread(root, id) {
  const box = h('div', { style: 'display:grid;gap:16px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/messages', style: 'justify-self:start' }, 'Back to messages'), box);
  async function load(scroll) {
    let d; try { d = await api('/messages/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This conversation did not open', e.message))); return; }
    const t = d.thread, hq = d.is_hq; const body = h('textarea', { class: 'input', rows: 3, placeholder: hq ? 'Reply as WODDI HQ' : 'Write your reply' });
    const send = h('button', { class: 'btn', type: 'button', onclick: async () => { send.disabled = true; try { await api(`/messages/${id}/reply`, { body: body.value }); load(true); } catch (e) { toast('Not sent', e.message, 'bad'); send.disabled = false; } } }, icon('send', 18), 'Send');
    const chat = h('div', { class: 'chat' }, d.messages.map((m) => { const mine = Number(m.sender_id) === app.user.id; const fromHq = Number(m.from_hq) === 1;
      return h('div', { class: 'bubble' + (mine ? ' is-mine' : '') + (fromHq ? ' is-hq' : '') }, h('small', { text: (fromHq ? tf('WODDI HQ ({1})', m.first_name) : `${m.first_name} ${m.last_name}`) + ' · ' + ago(m.created_at) }), h('p', {}, linkify(m.body))); }));
    box.replaceChildren(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
      h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, hq ? avatar(t.person, 44) : null, h('div', { class: 'grow' }, h('h2', { style: 'margin:0;font-size:20px', text: t.subject }), h('small', { class: 'faint', text: (hq ? `${t.person.first_name} ${t.person.last_name} · ` : 'With WODDI HQ · ') + t.category_label })),
        hq ? flag(t.person.country_code) : null, hq && t.person.network ? netTag(t.person.network) : null, h('span', { class: 'tag' + (t.status === 'resolved' ? ' tag--wnnn' : ''), text: t.status === 'resolved' ? 'Resolved' : 'Open' }),
        hq ? h('a', { class: 'btn btn--ghost btn--s', href: '#/members/' + t.person.id, text: 'Member record' }) : null,
        hq ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { await api(`/messages/${id}/status`, { status: t.status === 'resolved' ? 'open' : 'resolved' }); load(); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, t.status === 'resolved' ? 'Reopen' : 'Mark resolved') : null),
      chat, t.status === 'resolved' && !hq ? h('p', { class: 'faint', style: 'margin:0', text: 'WODDI HQ marked this as resolved. Reply below to open it again.' }) : null, body, h('div', {}, send))));
    if (scroll !== false) chat.lastElementChild?.scrollIntoView({ block: 'nearest' });
  }
  load();
}
