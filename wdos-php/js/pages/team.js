/* v109.24: my leaders. A leader opens any leader under her seat: when she was last on WDOS, her figures, her reports and
 * tasks, and one touch to message her, ring her in WDOS or on her phone, give her a task or invite her to a meeting.
 * Choose several leaders on the list to start a group voice call or a meeting with them.
 * The server decides who is "under her seat" (the same rule as tasks and messages); this page only shows what it is given. */
import { h, t, tf, icon, avatar, netTag, card, sk, empty, agoIn, fmt, fmtDate, toast, input, field } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { kpi } from './hq.js';
import { ring, groupCall } from './calls.js';
import { newForm as meetingForm } from './meetings.js';

const TASK_ST = { open: 'Open', in_progress: 'In progress', submitted: 'Waiting for approval', completed: 'Completed', cancelled: 'Cancelled' };
const REP_ST = { draft: 'Draft', submitted: 'Submitted', under_review: 'Under review', returned: 'Returned', approved: 'Approved', escalated: 'Escalated', not_started: 'Not started' };
const seen = (x) => x ? tf('Seen {1}', agoIn(x)) : t('Not signed in yet');

export async function render(root, params) {
  if (params.id && /^\d+$/.test(params.id)) return profile(root, Number(params.id));
  return list(root);
}

/* ---- the list: tap a leader to open her; tick several for a group call or a meeting ---- */
async function list(root) {
  const picked = new Map();
  const search = input({ type: 'search', placeholder: t('Search a leader by name'), 'aria-label': t('Search a leader by name') });
  const box = h('div', {}, sk.rows(6));
  const bar = h('div', { class: 'toolbar', style: 'flex-wrap:wrap' });
  root.append(card('My leaders', 'Tap a leader to see her work and reach her. Tick several to call them together or invite them to a meeting.', h('div', { style: 'display:grid;gap:12px' }, search, bar, box)));
  let d = null, max = 8;
  const withHq = h('input', { type: 'checkbox', style: 'width:20px;height:20px', onchange: () => paintBar() });
  const paintBar = () => {
    const n = picked.size, all = n + (withHq.checked ? 1 : 0);
    bar.replaceChildren(
      h('span', { class: 'tag', text: n ? tf('{1} chosen', n) : t('Tick leaders to act on several at once') }),
      d && d.can_call && !app.user.is_hq ? h('label', { style: 'display:flex;gap:6px;align-items:center' }, withHq, h('span', { text: t('Bring in WODDI HQ too') })) : null,
      d && d.can_call ? h('button', { class: 'btn btn--s', type: 'button', disabled: !n || all > max - 1, onclick: () => groupCall([...picked.values(), ...(withHq.checked ? [{ id: 'hq', name: 'WODDI HQ' }] : [])]) }, icon('phone', 16), ' ', all > max - 1 ? tf('Group call: {1} people at most', max - 1) : t('Group call')) : null,
      h('button', { class: 'btn btn--ghost btn--s', type: 'button', disabled: !n, onclick: () => meetWith([...picked.values()]) }, icon('calendar', 16), ' ', t('Invite to a meeting')),
      n ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { picked.clear(); paint(); } }, t('Clear')) : null);
  };
  const meetSlot = h('div'); root.append(meetSlot);
  async function meetWith(people) {
    let r; try { r = await api('/meetings'); } catch (e) { toast(e.message, '', 'error'); return; }
    meetSlot.replaceChildren(meetingForm(r, () => { meetSlot.replaceChildren(); picked.clear(); paint(); }, { invitees: people }));
    meetSlot.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function paint() {
    paintBar();
    const q = search.value.trim().toLowerCase();
    const rows = d.people.filter((p) => !q || `${p.first_name} ${p.last_name}`.toLowerCase().includes(q));
    box.replaceChildren(rows.length ? h('div', { class: 'list list--stack' }, rows.map((p) => {
      const tick = h('input', { type: 'checkbox', checked: picked.has(p.id), 'aria-label': tf('Choose {1}', `${p.first_name} ${p.last_name}`), style: 'width:22px;height:22px;flex:none',
        onchange: (e) => { if (e.target.checked) picked.set(p.id, { id: p.id, name: `${p.first_name} ${p.last_name}`, first_name: p.first_name, last_name: p.last_name }); else picked.delete(p.id); paintBar(); } });
      return h('div', { class: 'list__link', style: 'display:flex;align-items:center;gap:10px;flex-wrap:nowrap' }, tick,
        h('a', { href: '#/team/' + p.id, style: 'display:flex;align-items:center;gap:10px;flex:1;min-width:0;color:inherit;text-decoration:none' }, avatar(p, 40),
          h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: `${p.title}, ${p.area}` }),
            h('small', { style: 'display:block', class: p.last_seen_at ? 'muted' : 'bad', text: seen(p.last_seen_at) })), icon('chevright', 16)));
    })) : empty('users', q ? 'No leader by that name' : 'No leaders under your seat yet', 'WODDI HQ appoints leaders. As seats below yours are filled, they appear here.'));
  }
  try { d = await api('/team'); max = d.max_group || 8; } catch (e) { box.replaceChildren(empty('x', 'Your leaders did not load', e.message)); return; }
  search.addEventListener('input', paint); paint();
}

/* ---- one leader ---- */
async function profile(root, id) {
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/team', style: 'margin-bottom:12px' }, t('All my leaders')));
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(box);
  let d; try { d = await api('/team/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This leader did not open', e.message))); return; }
  const p = d.person, k = d.kpis, name = `${p.first_name} ${p.last_name}`;
  const slot = h('div');
  const act = (ic, label, on, attrs = {}) => h(attrs.href ? 'a' : 'button', { class: 'btn ' + (attrs.cls || 'btn--ghost'), type: attrs.href ? null : 'button', href: attrs.href || null, onclick: on || null, style: 'justify-content:center' }, icon(ic, 18), ' ', t(label));
  const actions = h('div', { class: 'teamacts' },
    act('send', 'Message', null, { href: '#/inbox/to/' + p.id, cls: '' }),
    d.can.call ? act('phone', 'Call in WDOS', () => ring(p.id, name, p.title)) : null,
    p.phone ? act('phone', 'Call her phone', () => { api('/team/' + p.id + '/phoned', {}).catch(() => {}); location.href = 'tel:' + p.phone.replace(/[^\d+]/g, ''); }) : null,
    d.can.task ? act('tasks', 'Assign a task', () => { slot.replaceChildren(taskForm(p, () => slot.replaceChildren())); slot.scrollIntoView({ behavior: 'smooth', block: 'start' }); }) : null,
    d.can.meet ? act('calendar', 'Invite to a meeting', async () => { let r; try { r = await api('/meetings'); } catch (e) { toast(e.message, '', 'error'); return; } slot.replaceChildren(meetingForm(r, () => slot.replaceChildren(), { invitees: [{ id: p.id, name }] })); slot.scrollIntoView({ behavior: 'smooth', block: 'start' }); }) : null,
    d.can.dashboard ? act('eye', 'Open her dashboard', null, { href: '#/leaders/' + p.id }) : null);
  const hero = h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:16px' },
    h('div', { style: 'display:flex;gap:14px;align-items:center;flex-wrap:wrap' }, avatar(p, 64),
      h('div', { class: 'grow', style: 'min-width:0;display:grid;gap:4px' }, h('h2', { class: 'display', style: 'margin:0;font-size:24px', text: name }), h('span', { class: 'muted', text: p.title }),
        h('div', { class: 'tagrow', style: 'justify-content:flex-start' }, netTag(p.network), h('span', { class: 'tag' + (p.last_seen_at ? '' : ' tag--bad'), text: seen(p.last_seen_at) }),
          !p.accepted ? h('span', { class: 'tag tag--bad', text: t('Has not accepted the Leadership Agreement yet') }) : null,
          p.since ? h('span', { class: 'tag', text: tf('In this seat since {1}', fmtDate(p.since)) }) : null))),
    actions));
  const pct = (a, b) => b ? Math.round((a / b) * 100) + '%' : '–';
  const kpis = h('div', { class: 'row row--3' },
    kpi('users', t('Members in her area'), k.members, [h('span', { text: tf('{1} joined in the last 30 days', fmt(k.new30)) })]),
    kpi('award', t('Active this week'), k.active7, [h('span', { text: tf('{1} of her members', pct(k.active7, k.members)) })]),
    kpi('tree', t('Leaders under her'), k.leaders, [h('span', { text: t('Serving seats in her area') })]),
    kpi('tasks', t('Open tasks'), k.tasks_open, [h('span', { class: k.tasks_overdue ? 'bad' : '', text: k.tasks_overdue ? tf('{1} overdue', k.tasks_overdue) : t('None overdue') })]),
    kpi('check', t('Tasks done, 90 days'), k.tasks_done90, [h('span', { text: k.tasks_done90 ? tf('{1} on time', pct(k.tasks_ontime90, k.tasks_done90)) : t('Nothing finished yet') })]),
    kpi('calendar', t('Meetings attended, 90 days'), k.meetings_present90, [h('span', { text: k.meetings_marked90 ? tf('of {1} with attendance taken', k.meetings_marked90) : t('No attendance taken yet') })]));
  const reports = card('Her reports', 'Open a report to read it. As her supervisor you can review what she submits.', d.reports.length
    ? h('div', { class: 'list list--stack' }, d.reports.map((r) => h('a', { class: 'list__link', href: '#/reporting/r/' + r.id, style: 'display:flex;align-items:center;gap:10px' }, h('div', { class: 'nrow__ico' }, icon('book', 18)),
      h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: r.title }), h('small', { text: [fmtDate(r.period_start, { month: 'long', year: 'numeric' }), r.no].filter(Boolean).join(' · ') })),
      h('span', { class: 'tag' + (r.status === 'returned' || r.late ? ' tag--bad' : r.status === 'approved' ? ' tag--wnnn' : ''), text: t(REP_ST[r.status] || r.status) + (r.late ? ' · ' + t('late') : '') }))))
    : empty('book', 'No reports yet', 'Her monthly report appears here once she starts it.'));
  const tasks = card('Her tasks', null, d.tasks.length
    ? h('div', { class: 'list list--stack' }, d.tasks.map((x) => h('a', { class: 'list__link', href: '#/tasks/' + x.id, style: 'display:flex;align-items:center;gap:10px' }, h('div', { class: 'nrow__ico' }, icon('tasks', 18)),
      h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: x.title }), h('small', { text: x.due_on ? tf('Due {1}', fmtDate(x.due_on)) : t('No due date') })),
      h('span', { class: 'tag' + (x.overdue ? ' tag--bad' : x.status === 'completed' ? ' tag--wnnn' : ''), text: x.overdue ? t('Overdue') : t(TASK_ST[x.status] || x.status) }))))
    : empty('tasks', 'No tasks yet', d.can.task ? 'Use Assign a task above to give her one.' : ''));
  box.replaceChildren(hero, slot, kpis, h('div', { class: 'row row--2' }, reports, tasks));
}

function taskForm(p, done) {
  const title = input({ maxlength: 190, 'aria-label': t('Task') }); const details = h('textarea', { class: 'input', rows: 3, maxlength: 4000 });
  const due = input({ type: 'date' }); const pr = h('select', { class: 'input' }, [['normal', 'Normal'], ['high', 'High'], ['urgent', 'Urgent'], ['low', 'Low']].map(([v, l]) => h('option', { value: v, text: t(l) })));
  const appr = h('input', { type: 'checkbox' }); const err = h('p', { class: 'err', role: 'alert' });
  const save = h('button', { class: 'btn', type: 'button', onclick: async () => {
    err.textContent = ''; save.disabled = true;
    try { await api('/tasks', { title: title.value, details: details.value, due_on: due.value, priority: pr.value, assignees: [p.id], requires_approval: appr.checked });
      toast(t('Task sent'), tf('{1} is told in WDOS and on her phone.', p.first_name), 'ok'); done(); }
    catch (e) { err.textContent = e.message; save.disabled = false; } } }, icon('send', 16), ' ', t('Send the task'));
  return card(tf('A task for {1}', p.first_name), null, h('div', { style: 'display:grid;gap:14px' }, field(t('What should she do?'), title), field(t('Details (optional)'), details),
    h('div', { class: 'grid2' }, field(t('Due on'), due), field(t('Priority'), pr)),
    h('label', { style: 'display:flex;gap:8px;align-items:center' }, appr, h('span', { text: t('I approve it when she marks it done') })), err,
    h('div', { style: 'display:flex;gap:10px' }, save, h('button', { class: 'btn btn--quiet', type: 'button', onclick: done }, t('Cancel')))));
}
