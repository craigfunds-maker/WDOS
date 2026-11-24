/* The annual calendar: WODDI programmes and network dates. HQ enters them; WDOS reminds everyone they are for. */
import { h, icon, card, sk, empty, fmtDate, toast, input, field, editForm, delButton } from '../ui.js';
import { api } from '../api.js';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
export async function render(root) {
  let year = new Date().getFullYear(); const box = h('div', {}, sk.rows(5)); const title = h('b', { style: 'min-width:60px;text-align:center' });
  const nav = h('div', { style: 'display:flex;gap:10px;align-items:center' }, h('button', { class: 'btn btn--ghost btn--s', type: 'button', 'aria-label': 'Previous year', onclick: () => { year--; load(); } }, '‹'), title, h('button', { class: 'btn btn--ghost btn--s', type: 'button', 'aria-label': 'Next year', onclick: () => { year++; load(); } }, '›'));
  const formHost = h('div'); root.append(card('Annual calendar', 'Dates are shown as entered by WODDI HQ. Meeting times are on the Meetings page in your own local time.', box, nav), formHost);
  async function load() {
    title.textContent = String(year); let d; try { d = await api('/calendar?year=' + year); } catch (e) { box.replaceChildren(empty('x', 'The calendar did not load', e.message)); return; }
    const by = MONTHS.map(() => []); d.items.forEach((e) => { const m = Math.max(0, e.starts_on.slice(0, 4) < String(year) ? 0 : Number(e.starts_on.slice(5, 7)) - 1); by[m].push(e); });
    box.replaceChildren(d.items.length ? h('div', { style: 'display:grid;gap:16px' }, by.map((list, m) => list.length ? h('div', {}, h('small', { class: 'eyebrow', text: MONTHS[m] }), h('div', { class: 'list list--wrap' }, list.map((e) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: e.title }), h('small', { text: [e.starts_on === e.ends_on ? fmtDate(e.starts_on) : fmtDate(e.starts_on) + ' to ' + fmtDate(e.ends_on), e.programme_name, e.network === 'ALL' ? 'Both networks' : e.network, e.country].filter(Boolean).join(' · ') }), e.note ? h('small', { class: 'faint', text: e.note }) : null),
      d.can_edit ? (() => { const slot = h('div', { style: 'flex-basis:100%' });   // v109.54: change a date, or delete it
        return h('div', { style: 'display:contents' }, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { if (slot.firstChild) { slot.replaceChildren(); return; }
          slot.replaceChildren(editForm('Change this date', [{ k: 'title', label: 'Name of the event', value: e.title }, { k: 'programme', label: 'Programme', type: 'select', options: Object.entries(d.programmes), value: e.programme }, { k: 'starts_on', label: 'Starts on', type: 'date', value: e.starts_on }, { k: 'ends_on', label: 'Ends on (optional)', type: 'date', value: e.ends_on },
            { k: 'network', label: 'Network', type: 'select', options: [['ALL', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']], value: e.network }, { k: 'note', label: 'Note (optional)', type: 'textarea', value: e.note || '', max: 600, rows: 2 }],
            async (v) => { await api(`/calendar/${e.id}/edit`, { ...v, country_code: e.country_code || '' }); toast('Date changed', '', 'ok'); load(); }, () => slot.replaceChildren())); } }, icon('edit', 16), ' Edit'),
          delButton('Delete', async () => { await api(`/calendar/${e.id}/delete`, {}); toast('Date deleted', '', 'ok'); load(); })), slot); })() : null)))) : null)) : empty('calendar', 'No dates entered for ' + year, d.can_edit ? 'Add the first date below.' : 'WODDI HQ has not entered dates for this year yet.'));
    if (d.can_edit && !formHost.firstChild) {
      const F = { title: input({ maxlength: 190 }), prog: h('select', { class: 'input' }, Object.entries(d.programmes).map(([k, l]) => h('option', { value: k, text: l }))), from: input({ type: 'date' }), to: input({ type: 'date' }), net: h('select', { class: 'input' }, h('option', { value: 'ALL', text: 'Both networks' }), h('option', { value: 'WGMN', text: 'WGMN' }), h('option', { value: 'WNNN', text: 'WNNN' })), cc: h('select', { class: 'input' }, h('option', { value: '', text: 'Every country' })), note: input({ maxlength: 600 }) };
      api('/geo/countries').then((r) => F.cc.append(...r.countries.map((c) => h('option', { value: c.code, text: c.name })))).catch(() => {});
      formHost.append(card('Add a date', 'Everyone it is for is reminded one week before and one day before. Nobody has to press Send.', h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'row row--2' }, field('Name of the event', F.title), field('Programme', F.prog)), h('div', { class: 'row row--2' }, field('Starts on', F.from), field('Ends on (optional)', F.to)), h('div', { class: 'row row--2' }, field('For', F.net), field('Country', F.cc)), field('Note (optional)', F.note),
        h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async () => { try { await api('/calendar', { title: F.title.value, programme: F.prog.value, starts_on: F.from.value, ends_on: F.to.value, network: F.net.value, country_code: F.cc.value, note: F.note.value }); toast('Added to the calendar', '', 'ok'); year = Number(F.from.value.slice(0, 4)) || year; F.title.value = ''; F.note.value = ''; load(); } catch (e) { toast('Not added', e.message, 'bad'); } } }, 'Add to the calendar')))));
    }
  }
  load();
}
