/* HQ: staff logins for the admin dashboard, and how each leader signs in. Passwords are never shown because WDOS does not keep them in a readable form. */
import { h, card, sk, empty, fmt, fmtDate, ago, toast, input, field, netTag } from '../ui.js';
import { api } from '../api.js';
import { download } from './coverage.js';

const csv = (rows) => rows.map((r) => r.map((v) => { const s = String(v ?? ''); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; }).join(',')).join('\r\n');
/* A temporary password is shown once, with a copy button, and is gone when the box is closed. */
function showTemp(host, who, login, temp) {
  host.replaceChildren(h('div', { class: 'panel', role: 'status', style: 'display:grid;gap:8px' }, h('b', { text: 'Give these to ' + who + ' now. The password is shown only once.' }),
    h('div', { class: 'kv' }, h('div', {}, h('span', { text: 'Signs in with' }), h('b', { style: 'overflow-wrap:anywhere', text: login })), h('div', {}, h('span', { text: 'Temporary password' }), h('b', { style: 'font-family:ui-monospace,monospace;font-size:18px;letter-spacing:.04em', text: temp }))),
    h('small', { class: 'muted', text: 'At first sign-in WDOS asks for a new password. Pass this on in person or by a private message, never in a group.' }),
    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { try { await navigator.clipboard.writeText(`WDOS sign-in: ${login}\nTemporary password: ${temp}\nhttps://${location.host}`); toast('Copied', '', 'ok'); } catch { toast('Copy it by hand', '', 'bad'); } } }, 'Copy'), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => host.replaceChildren() }, 'I have passed it on'))));
  host.scrollIntoView({ block: 'center', behavior: 'smooth' });
}
export async function render(root) {
  const staff = h('div', {}, sk.rows(3)), leaders = h('div', {}, sk.rows(5)), tempBox = h('div'), formHost = h('div'); let leaderRows = [];
  const find = input({ type: 'search', placeholder: 'Find a leader by name, email or area', style: 'max-width:340px' });
  root.append(tempBox,
    card('HQ staff logins', 'People who can open the HQ admin dashboard', h('div', { style: 'display:grid;gap:16px;grid-template-columns:minmax(0,1fr)' }, staff, formHost)),
    card('How each leader signs in', 'WDOS does not keep any password in a form that can be read, so nobody, including HQ, can look one up. You can see what each leader signs in with, and give a new temporary password when she is locked out.', h('div', { style: 'display:grid;gap:12px;grid-template-columns:minmax(0,1fr)' }, find, leaders),
      h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => download('wdos-leader-sign-in.csv', csv([['Name', 'Position', 'Network', 'Area', 'Signs in with', 'Kind', 'Last seen (GMT)'], ...leaderRows.map((l) => [l.name, l.title, l.network, l.area, l.signs_in_with, l.kind, l.last_seen_at || 'Never'])])) }, 'Download the list')));
  const reset = async (p) => { try { const r = await api(`/hq/members/${p.id}/reset-password`, {}); showTemp(tempBox, p.name, p.signs_in_with, r.temporary_password); } catch (e) { toast('Not reset', e.message, 'bad'); } };
  const confirmBtn = (label, fn) => { let armed = false; const b = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => { if (!armed) { armed = true; b.textContent = 'Press again to confirm'; setTimeout(() => { armed = false; b.textContent = label; }, 4000); return; } fn(); } }, label); return b; };
  async function loadStaff() {
    let d; try { d = await api('/hq/staff'); } catch (e) { staff.replaceChildren(empty('x', 'The staff list did not load', e.message)); return; }
    staff.replaceChildren(h('div', { class: 'list list--wrap' }, d.items.map((p) => h('div', { style: 'flex-wrap:wrap' }, h('div', { class: 'grow', style: 'min-width:220px' }, h('b', { text: p.name + (p.id === d.me ? ' (you)' : '') }), h('small', { style: 'overflow-wrap:anywhere', text: `${p.signs_in_with} · ${p.password}` }), h('small', { class: 'faint', text: 'Last seen ' + (p.last_seen_at ? ago(p.last_seen_at) : 'never') })),
      h('span', { class: 'tag ' + (p.role === 'super_admin' ? 'tag--wgmn' : ''), text: p.role === 'super_admin' ? 'Super administrator' : 'HQ staff' }), p.status === 'suspended' ? h('span', { class: 'tag tag--bad', text: 'Switched off' }) : null,
      d.can_manage && p.id !== d.me && p.role !== 'super_admin' ? h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, confirmBtn('New temporary password', () => reset(p)), confirmBtn(p.status === 'suspended' ? 'Switch back on' : 'Switch off', async () => { try { await api('/hq/staff/' + p.id, { active: p.status === 'suspended' }); loadStaff(); } catch (e) { toast('Not changed', e.message, 'bad'); } })) : null))));
    if (!formHost.firstChild) {
      if (!d.can_manage) { formHost.append(h('p', { class: 'muted', style: 'margin:0', text: 'Only a super administrator can create or switch off staff logins.' })); return; }
      const F = { first: input({ maxlength: 80, autocomplete: 'off' }), last: input({ maxlength: 80, autocomplete: 'off' }), email: input({ type: 'email', maxlength: 190, autocomplete: 'off' }), phone: input({ type: 'tel', maxlength: 32, autocomplete: 'off' }) }; const err = h('p', { class: 'err', role: 'alert' });
      formHost.append(h('div', { class: 'panel', style: 'display:grid;gap:14px' }, h('b', { text: 'Create a staff login' }), h('small', { class: 'muted', text: 'The new person sees everything HQ sees: every member, both networks and the confidential cases. Create a login only for WODDI staff who need that.' }),
        h('div', { class: 'row row--2' }, field('First name', F.first), field('Surname', F.last)), h('div', { class: 'row row--2' }, field('Email (she signs in with it)', F.email), field('Phone (optional)', F.phone)), err,
        h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; try { const r = await api('/hq/staff', { first_name: F.first.value, last_name: F.last.value, email: F.email.value, phone: F.phone.value }); showTemp(tempBox, F.first.value, r.email, r.temporary_password); Object.values(F).forEach((x) => { x.value = ''; }); loadStaff(); } catch (e) { err.textContent = e.message; } } }, 'Create the login'))));
    }
  }
  const paint = () => { const q = find.value.trim().toLowerCase(); const list = leaderRows.filter((l) => !q || [l.name, l.signs_in_with, l.area, l.title].join(' ').toLowerCase().includes(q));
    leaders.replaceChildren(list.length ? h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Leader', 'Position', 'Signs in with', 'Password', 'Last seen', ''].map((c) => h('th', { text: c })))),
      h('tbody', {}, list.slice(0, 300).map((l) => h('tr', { style: 'cursor:default' }, h('td', {}, h('div', { class: 'who' }, h('div', {}, h('b', {}, h('a', { href: '#/members/' + l.id, text: l.name })), h('small', { text: l.member_no || '' })))), h('td', {}, h('div', {}, netTag(l.network), ' ', h('span', { text: l.title })), h('small', { class: 'muted', text: l.area })),
        h('td', {}, h('b', { style: 'overflow-wrap:anywhere', text: l.signs_in_with || 'Not recorded' }), h('small', { class: 'muted', style: 'display:block', text: l.kind + (l.fingerprint ? ' · fingerprint added' : '') })), h('td', {}, h('small', { text: l.password })), h('td', {}, h('small', { text: l.last_seen_at ? ago(l.last_seen_at) : 'Never' })),
        h('td', {}, confirmBtn('New temporary password', () => reset(l)))))))) : empty('users', q ? 'No leader matches' : 'No leader holds a seat yet', ''),
      ...(list.length > 300 ? [h('small', { class: 'faint', text: `Showing 300 of ${fmt(list.length)}. Type in the box to narrow the list.` })] : [])); };
  find.addEventListener('input', paint);
  loadStaff(); api('/hq/leader-logins').then((d) => { leaderRows = d.items; paint(); }).catch((e) => leaders.replaceChildren(empty('x', 'The list did not load', e.message)));
}
