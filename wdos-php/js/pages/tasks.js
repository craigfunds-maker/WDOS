/* Tasks: what I must do, what I assigned, and what is open in my area. Wording follows the current WDOS. */
import { h, icon, avatar, card, sk, empty, ago, fmt, fmtDate, toast, input, field, tabs } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { kpi } from './hq.js';

const SCOPES = [['', 'My tasks'], ['assigned', 'Assigned by me'], ['area', 'My area']];
export const TASK_ST = { open: ['Open', ''], in_progress: ['In progress', 'wgmn'], submitted: ['Submitted for review', 'wgmn'], completed: ['Completed', 'wnnn'], cancelled: ['Cancelled', ''] };
const PRI = { low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent' };
export const taskTag = (s) => h('span', { class: 'tag' + (TASK_ST[s]?.[1] ? ' tag--' + TASK_ST[s][1] : ''), text: TASK_ST[s]?.[0] || s });

export function taskRow(t, showWho = 'assignee') {
  return h('a', { class: 'list__link', href: '#/tasks/' + t.id }, avatar(t.assignee, 38),
    h('div', { class: 'grow' }, h('b', { text: t.title }), h('small', {}, `${t.ref} · ${t.scope} · `, showWho === 'by' ? tf('assigned by {1}', t.by.name) : tf('owner {1} {2}', t.assignee.first_name, t.assignee.last_name), t.due_on ? ` · ${t.overdue ? 'overdue since' : 'due'} ${fmtDate(t.due_on)}` : '', t.items ? ` · ${t.items_done}/${t.items} checked` : '')),
    h('div', { class: 'tagrow' }, t.priority === 'high' || t.priority === 'urgent' ? h('span', { class: 'tag tag--bad', text: PRI[t.priority] }) : null, t.overdue ? h('span', { class: 'tag tag--bad', text: 'Overdue' }) : null, t.requires_approval && t.status !== 'completed' ? h('span', { class: 'tag', text: 'Approval required' }) : null, t.evidence ? h('span', { class: 'tag tag--wnnn', text: 'Evidence' }) : null, taskTag(t.status)));
}

export async function render(root, params) {
  if (params.tab && /^\d+$/.test(params.tab)) return detail(root, params.tab);
  const scope = SCOPES.some(([k]) => k === params.tab) ? params.tab || '' : '';
  const leader = app.user.is_hq || app.user.is_leader;
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi());
  const status = h('select', { class: 'input', 'aria-label': 'Status', style: 'width:auto' }, [['active', 'Open tasks'], ['', 'All statuses'], ['overdue', 'Overdue only'], ['completed', 'Completed'], ['cancelled', 'Cancelled']].map(([v, l]) => h('option', { value: v, text: l })));
  const search = input({ type: 'search', placeholder: 'Search tasks…', 'aria-label': 'Search tasks', style: 'max-width:260px' });
  const list = h('div', {}, sk.rows(5)); const formSlot = h('div'); const extra = h('div', { class: 'tagrow', style: 'max-width:none;justify-content:flex-start' }); const chapFilter = h('select', { class: 'input', 'aria-label': 'Chapter', hidden: true }, h('option', { value: '', text: 'Every chapter' }));
  const newBtn = h('button', { class: 'btn', type: 'button', onclick: () => { if (formSlot.firstChild) formSlot.replaceChildren(); else formSlot.replaceChildren(newForm(leader, () => { formSlot.replaceChildren(); load(); })); } }, icon('plus', 18), 'New task');
  if (leader) root.append(tabs(SCOPES, scope, '#/tasks'));
  root.append(kpis, extra, formSlot, card(SCOPES.find(([k]) => k === scope)[1], null, list, h('div', { class: 'toolbar' }, search, status, chapFilter, newBtn)));
  if (leader) { api('/chapters').then((r) => { if (r.items.length) { chapFilter.append(...r.items.map((c) => h('option', { value: c.id, text: c.name }))); chapFilter.hidden = false; } }).catch(() => {}); chapFilter.addEventListener('change', load); }
  let timer;
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); }); status.addEventListener('change', load);
  async function load() {
    let r;
    try { r = await api(`/tasks?scope=${scope || 'mine'}&status=${status.value}&q=${encodeURIComponent(search.value)}` + (chapFilter.value ? '&chapter=' + chapFilter.value : '')); }
    catch (e) { kpis.replaceChildren(); list.replaceChildren(empty('x', 'Tasks did not load', e.message, h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Try again'))); return; }
    const c = r.counts;
    kpis.replaceChildren(kpi('tasks', 'My open tasks', c.mine, [h('span', { text: 'Assigned to you' })]), kpi('clock', 'My overdue tasks', c.overdue, [h('span', { text: 'Past the due date' })]),
      kpi('inbox', 'Tasks awaiting your review', c.to_approve, [h('span', { text: 'Submitted by the people you assigned' })]), kpi('send', 'Open tasks I assigned', c.sent, [h('span', { text: 'Still with the assignee' })]));
    extra.replaceChildren(h('span', { class: 'tag', text: tf('{1} in this view', fmt(r.total)) }), h('span', { class: 'tag', text: tf('{1} chapter-scoped', fmt(r.chapter_scoped)) }), h('span', { class: 'tag' + (r.escalations ? ' tag--bad' : ''), text: tf('{1} past due (escalations)', fmt(r.escalations)) }));
    list.replaceChildren(r.items.length ? h('div', { class: 'list reveal' }, r.items.map((t) => taskRow(t, scope === '' ? 'by' : 'assignee')), r.total > r.items.length ? h('p', { class: 'faint', style: 'padding:12px 4px', text: tf('Showing the first {1} of {2} tasks. Use the filters to narrow.', r.items.length, fmt(r.total)) }) : null)
      : empty('tasks', 'No tasks here', scope === 'assigned' ? 'Tasks you assign to other leaders are tracked here.' : 'Tasks in this view will appear as they are created.'));
  }
  load();
}

/* ---------------------------------------------------------------- new task -- */
export function peoplePicker(multi = true) {
  const picked = new Map(); const chips = h('div', { class: 'tagrow' }); const results = h('div', { class: 'picklist' });
  const q = input({ type: 'search', placeholder: 'Search by name', 'aria-label': 'Search people by name' });
  const paint = () => chips.replaceChildren(...[...picked.values()].map((p) => h('span', { class: 'tag' }, `${p.first_name} ${p.last_name}`, h('button', { class: 'tag__x', type: 'button', 'aria-label': 'Remove', onclick: () => { picked.delete(p.id); paint(); } }, icon('x', 12)))));
  let timer; const find = async () => {
    try { const r = await api('/tasks/people?q=' + encodeURIComponent(q.value));
      results.replaceChildren(...(r.people.length ? r.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { if (!multi) picked.clear(); picked.set(p.id, p); paint(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.title || '' })))) : [h('p', { class: 'faint', text: 'No one found.' })]));
    } catch (e) { results.replaceChildren(h('p', { class: 'faint', text: e.message })); } };
  q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(find, 250); }); q.addEventListener('focus', () => { if (!results.firstChild) find(); });
  return { node: h('div', { style: 'display:grid;gap:8px' }, chips, q, results), ids: () => [...picked.keys()] };
}
function newForm(leader, done, opts = {}) {
  const title = input({ maxlength: 190, placeholder: 'Task title' }); const details = h('textarea', { class: 'input', rows: 3, placeholder: 'Start typing the details…' });
  const pri = h('select', { class: 'input' }, Object.entries(PRI).map(([v, l]) => h('option', { value: v, text: l }))); pri.value = 'normal';
  const due = input({ type: 'date' }); const approval = h('input', { type: 'checkbox' }); const checklist = h('textarea', { class: 'input', rows: 3, placeholder: 'One checklist item per line' });
  const who = h('select', { class: 'input' }, h('option', { value: 'me', text: 'Myself' }), leader ? h('option', { value: 'others', text: 'People in my area' }) : null);
  /* scope: one chapter, or every chapter (the wider area) */
  const chap = h('select', { class: 'input' }, h('option', { value: '', text: 'Not tied to one chapter' })); if (opts.chapter) chap.append(h('option', { value: opts.chapter, text: 'The chapter of the first task' })), (chap.value = String(opts.chapter));
  if (leader) api('/chapters').then((r) => chap.append(...r.items.filter((c) => c.id !== opts.chapter).map((c) => h('option', { value: c.id, text: c.name })))).catch(() => {});
  const picker = peoplePicker(true); const pickWrap = h('div', { hidden: true }, field('Who should receive this task', picker.node, 'Each person gets a separate task with its own tracked status. You can only reach people within your authority.'));
  const apprWrap = h('label', { class: 'check', hidden: true }, approval, h('span', {}, h('b', { text: 'Requires approval' }), h('br'), h('small', { class: 'faint', text: 'Only you or a leader over the unit can mark it completed.' })));
  who.addEventListener('change', () => { pickWrap.hidden = apprWrap.hidden = who.value !== 'others'; });
  const err = h('p', { class: 'err', role: 'alert' });
  const save = h('button', { class: 'btn', type: 'button', onclick: async () => {
    err.textContent = ''; const ids = who.value === 'others' ? picker.ids() : [app.user.id];
    if (who.value === 'others' && !ids.length) { err.textContent = 'Find recipients first.'; return; }
    save.disabled = true;
    try { const r = await api('/tasks', { title: title.value, details: details.value, priority: pri.value, due_on: due.value, requires_approval: approval.checked, assignees: ids, checklist: checklist.value.split('\n'), chapter_id: Number(chap.value) || 0, parent_id: opts.parent || 0 });
      toast(r.created > 1 ? tf('Task assigned to {1} leaders', r.created) : 'Task created', '', 'ok'); done(); }
    catch (e) { err.textContent = e.message; save.disabled = false; } } }, 'Create task');
  if (opts.parent && leader) { who.value = 'others'; who.dispatchEvent(new Event('change')); }
  return card(opts.parent ? 'Delegate part of this task' : 'New task', opts.parent ? 'The new task stays linked to this one. Each task has one person accountable for it.' : null, h('div', { style: 'display:grid;gap:14px' }, field('Task title', title), field('Details', details), h('div', { class: 'grid2' }, field('Priority', pri), field('Due date (optional)', due), field('Assign to', who), leader ? field('Chapter', chap) : null), pickWrap, apprWrap, field('Checklist (optional)', checklist), err, h('div', {}, save)));
}

/* ------------------------------------------------------------------ detail -- */
async function detail(root, id) {
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/tasks', style: 'justify-self:start;margin-bottom:12px' }, 'Back to tasks'), box);
  async function load() {
    let d; try { d = await api('/tasks/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This task did not open', e.message))); return; }
    const t = d.task, r = d.rights; const act = h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' });
    const move = async (to, note) => { try { await api(`/tasks/${id}/status`, { to, note: note || '' }); toast('Task updated', '', 'ok'); load(); } catch (e) { toast('Not updated', e.message, 'bad'); } };
    const btn = (label, to, cls = 'btn', ask) => h('button', { class: cls, type: 'button', onclick: () => { if (ask) { const n = prompt(ask); if (n === null) return; move(to, n); } else move(to); } }, label);
    const active = ['open', 'in_progress'].includes(t.status);
    const dSlot = h('div'); const canLead = app.user.is_hq || app.user.is_leader;
    if ((r.mine || r.manage) && active && canLead) act.append(h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { if (dSlot.firstChild) dSlot.replaceChildren(); else dSlot.replaceChildren(newForm(true, () => { dSlot.replaceChildren(); toast('Delegated', 'The new task is linked to this one.', 'ok'); }, { parent: t.id, chapter: t.chapter_id })); } }, 'Delegate part of this'));
    if (r.mine && t.status === 'open') act.append(btn('Start', 'in_progress', 'btn btn--quiet'));
    if (r.mine && active && t.requires_approval && !r.manage) act.append(btn('Submit for review', 'submitted'));
    if (r.mine && active && (!t.requires_approval || r.manage)) act.append(btn('Mark completed', 'completed'));
    if (r.manage && t.status === 'submitted') act.append(btn('Approve completion', 'completed'), btn('Send back', 'in_progress', 'btn btn--quiet', 'What needs to be corrected?'));
    if (r.manage && ['open', 'in_progress', 'submitted'].includes(t.status)) act.append(btn('Cancel task', 'cancelled', 'btn btn--ghost'));
    if (r.manage && ['completed', 'cancelled'].includes(t.status)) act.append(btn('Reopen', 'open', 'btn btn--quiet'));
    const canEditItems = r.manage || t.by.id === t.assignee.id; const itemIn = input({ placeholder: 'Add a checklist item…', maxlength: 190 });
    const items = h('div', { style: 'display:grid;gap:8px' }, d.items.map((i) => { const cb = h('input', { type: 'checkbox', disabled: !active }); cb.checked = Number(i.done) === 1;
      cb.addEventListener('change', async () => { try { await api(`/tasks/${id}/item/${i.id}`, { done: cb.checked }); load(); } catch (e) { toast('Not saved', e.message, 'bad'); cb.checked = !cb.checked; } }); return h('label', { class: 'check' }, cb, h('span', { text: i.label })); }),
      canEditItems && active ? h('div', { style: 'display:flex;gap:8px' }, itemIn, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { await api(`/tasks/${id}/item`, { label: itemIn.value }); load(); } catch (e) { toast('Not added', e.message, 'bad'); } } }, 'Add')) : null,
      !d.items.length && !(canEditItems && active) ? h('p', { class: 'faint', text: 'No checklist on this task.' }) : null);
    const cIn = h('textarea', { class: 'input', rows: 2, placeholder: 'Write a comment…' });
    const evNote = h('textarea', { class: 'input', rows: 2, placeholder: 'What was done' }), evUrl = input({ type: 'url', placeholder: 'Link to a document or photograph (optional), https://' });
    const evs = d.comments.filter((c) => c.kind === 'evidence'); d.comments = d.comments.filter((c) => c.kind !== 'evidence');
    const evidence = h('div', { style: 'display:grid;gap:12px' }, evs.length ? h('div', { class: 'list' }, evs.map((c) => h('div', {}, h('div', { class: 'nrow__ico' }, icon('check', 18)), h('div', { class: 'grow' }, h('b', { text: `${c.first_name} ${c.last_name}` }), h('p', { style: 'margin:2px 0;white-space:pre-wrap', text: c.body }), c.url ? h('a', { class: 'tlink', href: c.url, target: '_blank', rel: 'noopener', text: c.url }) : null, h('small', { style: 'display:block', text: ago(c.created_at) }))))) : h('p', { class: 'faint', style: 'margin:0', text: 'No evidence has been added yet.' }),
      (r.mine || r.manage) ? h('div', { style: 'display:grid;gap:8px' }, evNote, evUrl, h('div', {}, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { await api(`/tasks/${id}/evidence`, { note: evNote.value, url: evUrl.value }); toast('Evidence added', '', 'ok'); load(); } catch (e) { toast('Not added', e.message, 'bad'); } } }, 'Add evidence'))) : null);
    const comments = h('div', { style: 'display:grid;gap:12px' }, d.comments.length ? h('div', { class: 'list' }, d.comments.map((c) => h('div', {}, avatar({ id: c.user_id, first_name: c.first_name, last_name: c.last_name, has_photo: c.has_photo }, 34), h('div', { class: 'grow' }, h('b', { text: `${c.first_name} ${c.last_name}` }), h('p', { style: 'margin:2px 0;white-space:pre-wrap', text: c.body }), h('small', { text: ago(c.created_at) }))))) : h('p', { class: 'faint', text: 'No comments yet.' }),
      cIn, h('div', {}, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { await api(`/tasks/${id}/comment`, { body: cIn.value }); load(); } catch (e) { toast('Not sent', e.message, 'bad'); } } }, 'Comment')));
    box.replaceChildren(
      h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' }, h('div', { class: 'tagrow' }, taskTag(t.status), t.overdue ? h('span', { class: 'tag tag--bad', text: 'Overdue' }) : null, h('span', { class: 'tag', text: 'Priority: ' + PRI[t.priority] }), t.requires_approval ? h('span', { class: 'tag', text: 'Approval required' }) : null, t.network ? h('span', { class: 'tag', text: t.network }) : null, t.chapter_id ? h('a', { class: 'tag', href: '#/chapter/' + t.chapter_id, text: 'Chapter task' }) : h('span', { class: 'tag', text: 'Area task' }), t.parent_id ? h('a', { class: 'tag', href: '#/tasks/' + t.parent_id, text: 'Delegated from another task' }) : null),
        h('small', { class: 'faint num', text: `${t.ref} · ${t.scope}` }), h('h2', { class: 'display', style: 'font-size:26px;margin:0', text: t.title }), dSlot, t.details ? h('p', { style: 'white-space:pre-wrap;margin:0', text: t.details }) : null,
        h('dl', { class: 'dl' }, [['Accountable owner', `${t.assignee.first_name} ${t.assignee.last_name}`], ['Assigned by', t.by.name], ['Due', t.due_on ? fmtDate(t.due_on) : 'No due date'], ['Created', fmtDate(t.created_at)], t.completed_at ? ['Completed', fmtDate(t.completed_at)] : null].filter(Boolean).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })])),
        t.from_report ? h('a', { href: '#/reporting/r/' + t.from_report, text: 'Open the report this task came from' }) : null, t.from_meeting ? h('a', { href: '#/meetings/' + t.from_meeting, text: 'Open the meeting this task came from' }) : null, act)),
      h('div', { class: 'row row--2' }, card(`Checklist (${t.items_done}/${t.items})`, null, items), card('Comments', null, comments)),
      card('Evidence', 'What shows the work was done. The person who assigned the task is told when evidence is added.', evidence));
  }
  load(); void go;
}
