/* System rules: the approved governance rules WDOS runs on, each change kept with its date and who made it.
 * Also where HQ watches the automation: the seven-day journey, the exceptions list, and message delivery. */
import { h, card, sk, empty, fmt, fmtDate, toast, input, netTag, flag } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';

export async function render(root) {
  const rules = h('div', {}, sk.rows(5)), decisions = h('div', {}, sk.rows(3)), history = h('div', {}, sk.rows(3)), watch = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()));
  root.append(watch, card('Rules in force', 'WDOS applies these by itself. A change starts on the date you give and the old value stays in the history.', rules),
    card('Decisions still needed from management', 'From the implementation amendments of 11 November 2026. WDOS runs on the value shown until a decision is recorded.', decisions), card('History of changes', 'Most recent first', history));
  async function load() {
    let d, j, del; try { [d, j, del] = await Promise.all([api('/policy'), api('/hq/journeys'), api('/hq/delivery')]); } catch (e) { rules.replaceChildren(empty('x', 'The rules did not load', e.message)); return; }
    rules.replaceChildren(h('div', { class: 'list' }, d.rules.map((r) => { const val = r.type === 'choice' ? h('select', { class: 'input', style: 'max-width:330px' }, Object.entries(r.choices).map(([v, l]) => h('option', { value: v, text: l }))) : input({ type: 'number', min: r.min, max: r.max, style: 'max-width:110px' }); val.value = r.value;
      const from = input({ type: 'date', style: 'max-width:170px', 'aria-label': 'Starts on' }); const note = input({ placeholder: 'Reason for the change', maxlength: 400, style: 'max-width:260px' });
      return h('div', { style: 'flex-wrap:wrap;gap:10px' }, h('div', { class: 'grow', style: 'min-width:220px' }, h('b', { text: r.label }), h('small', { text: 'Now: ' + (r.type === 'choice' ? r.choices[r.value] || r.value : r.value) })), val, from, note,
        h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/policy', { key: r.key, value: val.value, effective_from: from.value, note: note.value }); toast('Rule recorded', from.value ? 'It starts on ' + fmtDate(from.value) + '.' : 'It applies from today.', 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Save')); })));
    decisions.replaceChildren(h('div', { class: 'list' }, d.decisions.map((x, i) => h('div', {}, h('span', { class: 'jday__n', text: String(i + 1) }), h('div', { class: 'grow' }, h('b', { text: x.title }), h('small', { text: x.text })), x.current ? h('span', { class: 'tag', text: 'Now: ' + x.current }) : h('span', { class: 'tag tag--wgmn', text: 'Open' })))));
    history.replaceChildren(h('div', { class: 'list' }, d.history.map((x) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: `${x.rule_key} = ${x.rule_value}` }), h('small', { text: [`from ${fmtDate(x.effective_from)}`, 'by ' + x.changed_by, x.note].filter(Boolean).join(' · ') })), h('small', { class: 'faint', text: fmtDate(x.created_at) })))));
    const s = j.summary; const p = del.push || {}, m = del.mail || {};
    watch.replaceChildren(h('div', { class: 'row row--4' }, kpi('route', 'On the seven-day journey', s.in_progress + s.retry, [h('span', { text: `${fmt(s.extended)} given more time automatically` })]), kpi('check', 'Activated automatically', s.active, [h('span', { text: 'No staff step involved' })]),
      kpi('eye', 'Exceptions for HQ', s.review, [h('span', { text: 'Possible duplicate accounts' })]), kpi('send', 'Phone notifications', (p.sent || 0), [h('span', { text: `${fmt(p.queued || 0)} queued, ${fmt(p.failed || 0)} failed` })])),
      card('Exceptions', 'The only part of the journey a person looks at. Nothing is removed automatically, and each decision here is logged.', j.exceptions.length ? h('div', { class: 'list' }, j.exceptions.map((x) => h('div', { style: 'flex-wrap:wrap;gap:10px' }, h('div', { class: 'grow' }, h('a', { href: '#/members/' + x.id, text: `${x.first_name} ${x.last_name}` }), h('small', { text: [x.member_no, 'another account has the same details'].filter(Boolean).join(' · ') })), flag(x.country_code), netTag(x.network),
        h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { try { await api('/hq/journeys/' + x.id, { decision: 'clear' }); toast('Cleared', 'The member is now active.', 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Clear, make active'),
        h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/hq/journeys/' + x.id, { decision: 'hold' }); toast('Kept under review', '', 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Keep under review')))) : empty('check', 'No exceptions', 'Every journey so far has been decided by WDOS itself.')),
      card('Message delivery', 'Sent means WDOS handed the message over. It does not mean it was read.', h('div', { class: 'list' }, [['Phone notifications', `${fmt(p.queued || 0)} queued · ${fmt(p.sent || 0)} sent · ${fmt(p.failed || 0)} failed`], ['Emails', `${fmt(m.queued || 0)} queued · ${fmt(m.sent || 0)} sent · ${fmt(m.failed || 0)} failed`], ['In-app messages, last 30 days', `${fmt(del.created)} created · ${fmt(del.read)} opened by the member`]].map(([a, b]) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: a }), h('small', { text: b })))))),
      h('small', { class: 'faint', text: 'Figures as of ' + fmtDate(j.as_of, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + ' (GMT).' }));
  }
  load();
}
