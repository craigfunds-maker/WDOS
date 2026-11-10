/* The full notification history. */
import { h, card, sk, empty } from '../ui.js';
import { api } from '../api.js';
import { notifRow, setUnread } from '../app.js';

export async function render(root) {
  const list = h('div', {}, sk.rows(6)); const more = h('div', { style: 'text-align:center;padding-top:14px' });
  const markAll = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { const r = await api('/notifications/read', {}); setUnread(r.unread); list.querySelectorAll('.is-new').forEach((x) => x.classList.remove('is-new')); } }, 'Mark all read');
  root.append(card('Notifications', 'Announcements, appointments, reminders and security messages', h('div', {}, list, more), markAll));
  let last = 0, first = true;
  const load = async () => {
    let r; try { r = await api('/notifications' + (last ? '?before=' + last : '')); } catch (e) { list.replaceChildren(empty('x', 'Notifications did not load', e.message)); return; }
    if (first) { list.replaceChildren(); first = false; setUnread(r.unread); }
    if (!r.items.length && !last) { list.replaceChildren(empty('bell', 'You are all caught up', 'New announcements and reminders arrive here, and on this phone if notifications are on.', h('a', { class: 'btn btn--quiet btn--s', href: '#/settings', text: 'Notification settings' }))); return; }
    r.items.forEach((n) => list.append(notifRow(n))); last = r.items.at(-1)?.id || last;
    more.replaceChildren(r.items.length === 30 ? h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Show earlier') : '');
  };
  load();
  const onNew = () => { if (!root.isConnected) return window.removeEventListener('wdos:notified', onNew); last = 0; first = true; load(); };
  window.addEventListener('wdos:notified', onNew);
}
