/* Meetings: schedule, attendance, minutes, and the actions that become tasks. Wording follows the current WDOS. */
import { h, icon, avatar, card, sk, empty, fmt, toast, input, field, tabs, netTag, linkify } from '../ui.js';
import { api } from '../api.js';
import { taskRow, peoplePicker } from './tasks.js';

const when = (s) => new Date(s.replace(' ', 'T') + 'Z');
const fmtWhen = (a, b) => { const x = when(a), y = when(b); return x.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + ', ' + x.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) + ' to ' + y.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }); };
const MST = { scheduled: ['Scheduled', 'wgmn'], completed: ['Completed', 'wnnn'], cancelled: ['Cancelled', 'bad'] };
const mTag = (s) => h('span', { class: 'tag tag--' + MST[s][1], text: MST[s][0] });
const MODES = { virtual: 'Online', in_person: 'In person', hybrid: 'In person and online' };

export async function render(root, params) {
  if (params.tab && /^\d+$/.test(params.tab)) return detail(root, params.tab);
  const scope = params.tab === 'past' ? 'past' : '';
  const list = h('div', {}, sk.rows(4)); const formSlot = h('div'); const tools = h('div', { class: 'toolbar' });
  root.append(tabs([['', 'Upcoming'], ['past', 'Past']], scope, '#/meetings'), formSlot, card(scope ? 'Past meetings' : 'Upcoming meetings', null, list, tools));
  async function load() {
    let r; try { r = await api('/meetings?scope=' + (scope || 'upcoming')); } catch (e) { list.replaceChildren(empty('x', 'Meetings did not load', e.message)); return; }
    tools.replaceChildren(r.can_schedule ? h('button', { class: 'btn', type: 'button', onclick: () => { if (formSlot.firstChild) formSlot.replaceChildren(); else formSlot.replaceChildren(newForm(r, () => { formSlot.replaceChildren(); load(); })); } }, icon('plus', 18), 'Schedule a meeting') : '');
    list.replaceChildren(r.items.length ? h('div', { class: 'list list--stack reveal' }, r.items.map((m) => h('a', { class: 'list__link', href: '#/meetings/' + m.id }, h('div', { class: 'nrow__ico' }, icon('calendar', 18)),
      h('div', { class: 'grow' }, h('b', { text: m.title }), h('small', { text: `${fmtWhen(m.starts_at, m.ends_at)} · hosted by ${m.host}` })), h('div', { class: 'tagrow' }, m.link && m.status === 'scheduled' ? h('span', { class: 'btn btn--quiet btn--s', role: 'link', onclick: (e) => { e.preventDefault(); e.stopPropagation(); window.open(m.link, '_blank', 'noopener'); } }, 'Join') : null, h('span', { class: 'tag', text: MODES[m.mode] }), mTag(m.status)))))
      : empty('calendar', 'No meetings here', r.can_schedule ? 'Schedule a meeting and everyone invited is notified in the app and on their phones.' : 'Meetings for your unit will appear here once scheduled.'));
  }
  load();
}
function newForm(r, done) {
  const title = input({ maxlength: 190 }); const mode = h('select', { class: 'input' }, Object.entries(r.modes).map(([v, l]) => h('option', { value: v, text: l })));
  const loc = input({ maxlength: 400 }); const locHint = h('small', { text: 'Paste the meeting link here.' }); mode.addEventListener('change', () => { locHint.textContent = mode.value === 'in_person' ? 'Address of the meeting.' : mode.value === 'hybrid' ? 'Address, and the video call link.' : 'Paste the meeting link here.'; });
  const start = input({ type: 'datetime-local' }), end = input({ type: 'datetime-local' }); const aud = h('select', { class: 'input' }, Object.entries(r.audiences).map(([v, l]) => h('option', { value: v, text: l })));
  start.addEventListener('change', () => { if (start.value && !end.value) { const d = new Date(new Date(start.value).getTime() + 3600000); end.value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16); } });
  const agenda = h('textarea', { class: 'input', rows: 4 }); const err = h('p', { class: 'err', role: 'alert' });
  const save = h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; save.disabled = true;
    try { const iso = (v) => v ? new Date(v).toISOString() : ''; const x = await api('/meetings', { title: title.value, mode: mode.value, location: loc.value, starts_at: iso(start.value), ends_at: iso(end.value), audience: aud.value, agenda: agenda.value });
      toast('Meeting scheduled', `${fmt(x.invited)} people notified in the app and by push.` + (x.mail_on ? ` ${fmt(x.emails)} emails are being sent.` : ' Emails are not set up on the server yet.'), 'ok'); done(); } catch (e) { err.textContent = e.message; save.disabled = false; } } }, 'Schedule');
  return card('Schedule a meeting', 'Times are in your own time zone. Each person invited sees the meeting in their own.', h('div', { style: 'display:grid;gap:14px' }, field('Meeting title', title), h('div', { class: 'grid2' }, field('Starts', start), field('Ends', end), field('Format', mode), field('Who is invited?', aud)),
    h('label', { class: 'field' }, h('span', { text: 'Location or link' }), loc, locHint), field('Agenda (optional)', agenda), err, h('div', {}, save)));
}
async function detail(root, id) {
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/meetings', style: 'justify-self:start;margin-bottom:12px' }, 'Back to meetings'), box);
  async function load() {
    let d; try { d = await api('/meetings/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This meeting did not open', e.message))); return; }
    const m = d.meeting; const parts = [];
    /* Will you attend? One touch. The host sees the totals. */
    const rsvpBox = h('div', { style: 'display:grid;gap:8px' });
    const paintRsvp = (r) => rsvpBox.replaceChildren(h('b', { text: 'Will you attend?' }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, [['yes', 'Yes'], ['maybe', 'Maybe'], ['no', 'No']].map(([v, l]) => h('button', { class: 'btn btn--s ' + (r.mine === v ? '' : 'btn--ghost'), type: 'button', 'aria-pressed': r.mine === v ? 'true' : 'false', onclick: async () => { try { await api(`/meetings/${id}/rsvp`, { answer: v }); paintRsvp(await api(`/meetings/${id}/rsvp`)); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, l))),
      h('small', { class: 'muted', text: `Replies so far: ${fmt(r.counts.yes)} yes, ${fmt(r.counts.maybe)} maybe, ${fmt(r.counts.no)} no` }));
    api(`/meetings/${id}/rsvp`).then(paintRsvp).catch(() => {});
    parts.push(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' }, h('div', { class: 'tagrow' }, mTag(m.status), h('span', { class: 'tag', text: MODES[m.mode] }), m.network !== 'ALL' ? netTag(m.network) : null),
      h('h2', { class: 'display', style: 'font-size:26px;margin:0', text: m.title }),
      h('dl', { class: 'dl' }, [['When', fmtWhen(m.starts_at, m.ends_at)], ['Hosted by', m.host], ['For', m.scope_label], [m.link ? 'Meeting link' : 'Where', m.location]].filter((x) => x[1]).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', {}, k === 'Hosted by' || k === 'When' || k === 'For' ? v : linkify(v))])),
      m.link && m.status === 'scheduled' ? h('div', {}, h('a', { class: 'btn', href: m.link, target: '_blank', rel: 'noopener' }, icon('link', 18), 'Join meeting')) : null,
      m.status === 'scheduled' ? rsvpBox : null,
      m.agenda ? h('div', {}, h('b', { text: 'Agenda' }), h('p', { style: 'white-space:pre-wrap;margin:6px 0 0' }, linkify(m.agenda))) : null,
      m.minutes ? h('div', {}, h('b', { text: 'Minutes' }), h('p', { style: 'white-space:pre-wrap;margin:6px 0 0' }, linkify(m.minutes))) : null,
      d.my_mark ? h('p', { class: 'muted', text: 'Your attendance: ' + (d.my_mark === 'present' ? 'Present' : 'Absent') }) : null)));
    if (d.tasks.length) parts.push(card('Actions from this meeting', 'Each action is a task with its own owner and status.', h('div', { class: 'list' }, d.tasks.map((t) => taskRow(t)))));
    if (d.host && m.status !== 'cancelled') {
      const marks = {}; d.people.forEach((p) => { marks[p.id] = p.mark || ''; }); let dirty = false;
      const rows = d.people.map((p) => { const seg = h('div', { class: 'seg' }, [['present', 'Present'], ['absent', 'Absent']].map(([v, l]) => h('button', { type: 'button', 'aria-pressed': String(marks[p.id] === v), onclick: (e) => { marks[p.id] = marks[p.id] === v ? '' : v; dirty = true; seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', 'false')); if (marks[p.id]) e.currentTarget.setAttribute('aria-pressed', 'true'); } }, l)));
        return h('div', {}, avatar(p, 36), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.state_region || '' })), seg); });
      parts.push(card('Attendance', `${fmt(d.invited)} invited · ${d.present} present · ${d.absent} absent` + (d.invited > d.people.length ? ` · showing the first ${d.people.length}` : ''), d.people.length ? h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'list' }, rows),
        h('div', {}, h('button', { class: 'btn btn--quiet', type: 'button', onclick: async () => { if (!dirty) { toast('Mark at least one person before saving.', '', 'bad'); return; } try { await api(`/meetings/${id}/attendance`, { marks }); toast('Attendance saved', '', 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Save attendance')))
        : empty('users', 'Nobody is in this audience yet', 'No one matches the people this meeting was called for.')));
    }
    if (d.host && m.status === 'scheduled') {
      const minutes = h('textarea', { class: 'input', rows: 5, placeholder: 'What was discussed and decided' }); const acts = []; const actBox = h('div', { style: 'display:grid;gap:10px' });
      const addAct = () => { const a = { action: input({ placeholder: 'Action', maxlength: 190 }), deadline: input({ type: 'date', 'aria-label': 'Deadline' }), picker: peoplePicker(false) }; acts.push(a);
        actBox.append(h('div', { class: 'panel', style: 'display:grid;gap:8px' }, h('div', { class: 'grid2' }, a.action, a.deadline), h('small', { class: 'faint', text: 'Owner. Leave empty to keep the action yourself.' }), a.picker.node)); };
      parts.push(card('Close with minutes', 'Closing records the minutes and turns each action into a task for its owner.', h('div', { style: 'display:grid;gap:14px' }, field('Minutes', minutes), h('b', { text: 'Decisions and actions' }), actBox,
        h('div', {}, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: addAct }, icon('plus', 16), 'Add an action')),
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, h('button', { class: 'btn', type: 'button', onclick: async () => { try { const x = await api(`/meetings/${id}/close`, { minutes: minutes.value, actions: acts.filter((a) => a.action.value.trim()).map((a) => ({ action: a.action.value, deadline: a.deadline.value, owner_id: a.picker.ids()[0] || 0 })) }); toast('Meeting closed', x.tasks ? `${x.tasks} tasks created.` : '', 'ok'); load(); } catch (e) { toast('Not closed', e.message, 'bad'); } } }, 'Close with minutes'),
          h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { if (!confirm('Cancel this meeting? Everyone invited is told.')) return; try { await api(`/meetings/${id}/cancel`, {}); toast('Meeting cancelled', '', 'ok'); load(); } catch (e) { toast('Not cancelled', e.message, 'bad'); } } }, 'Cancel meeting')))));
    }
    box.replaceChildren(...parts);
  }
  load();
}
