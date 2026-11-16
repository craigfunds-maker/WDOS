/* Old WDOS records: everything copied from the old (Supabase) WDOS, kept row for row so nothing is lost while each kind of
 * record moves to its new home. Read-only, for the super administrator. Counts are checked against the old system. */
import { h, card, sk, empty, fmt, ago, input, field } from '../ui.js';
import { api } from '../api.js';

export async function render(root) {
  const state = { table: '', email: '', q: '', page: 1 };
  const sum = h('div', {}, sk.rows(4)); const list = h('div', {}, sk.rows(6));
  const tsel = h('select', { class: 'input', 'aria-label': 'Table' }, h('option', { value: '', text: 'Every table' }));
  const email = input({ type: 'search', placeholder: 'Email of a person' }); const q = input({ type: 'search', placeholder: 'Words to find' });
  const go = h('button', { class: 'btn', type: 'button', onclick: () => { state.table = tsel.value; state.email = email.value.trim(); state.q = q.value.trim(); state.page = 1; load(); } }, 'Search');
  root.append(card('What was kept from the old WDOS', 'Each table of the old system, how many rows it had, and how many are kept here.', sum),
    card('Search the old records', 'By person, table or words. Each record is shown exactly as it was in the old WDOS.', h('div', { style: 'display:grid;gap:12px' },
      h('div', { class: 'grid2 grid3' }, field('Table', tsel), field('Person', email), field('Words', q)), h('div', {}, go), list)));
  const files = h('div', {});
  root.append(card('Stored files from the old WDOS', 'CVs, photos and task files. They stay private: only you can open them.', files));
  let first = true;
  async function load() {
    let d; try { d = await api('/hq/legacy?' + new URLSearchParams({ table: state.table, email: state.email, q: state.q, page: state.page })); } catch (e) { sum.replaceChildren(empty('x', 'This did not open', e.message)); return; }
    if (!d.ready || !d.tables.length) { sum.replaceChildren(empty('book', 'Nothing copied yet', 'The copy from the old WDOS has not been run on this server yet.')); list.replaceChildren(); return; }
    if (first) { first = false; d.tables.forEach((t) => tsel.append(h('option', { value: t.source_table, text: `${t.source_table} (${fmt(t.kept_rows)})` }))); }
    const ok = d.tables.filter((t) => t.note === 'complete').length;
    sum.replaceChildren(h('p', { style: 'margin:0 0 10px' }, h('b', { text: `${fmt(ok)} of ${fmt(d.tables.length)} tables complete. ${fmt(d.files)} stored files kept.` })),
      h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Table', 'Rows in the old WDOS', 'Rows kept here', 'Checked'].map((c) => h('th', { text: c })))),
        h('tbody', {}, d.tables.map((t) => h('tr', {}, h('td', { text: t.source_table }), h('td', { text: fmt(t.remote_rows) }), h('td', { text: fmt(t.kept_rows) }), h('td', {}, h('span', { class: 'tag' + (t.note === 'complete' ? ' tag--wnnn' : ' tag--bad'), text: t.note === 'complete' ? 'Complete' : 'Check' }))))))));
    if (state.page === 1) files.replaceChildren(d.file_list.length ? h('ul', { style: 'margin:0;padding-inline-start:18px' }, d.file_list.map((f) => h('li', {},
      h('a', { href: '/api/hq/legacy/file?' + new URLSearchParams({ bucket: f.bucket, path: f.path }), text: `${f.bucket} / ${f.path}` }), h('span', { class: 'faint', text: ` (${fmt(Math.round(f.size / 1024))} KB)` })))) : empty('book', 'No files yet', ''));
    list.replaceChildren(h('p', { class: 'faint', text: `${fmt(d.total)} records` }),
      ...d.items.map((r) => h('details', { class: 'panel', style: 'margin-bottom:8px' }, h('summary', { text: `${r.table} · ${r.email || 'no person'} · ${r.created_at ? ago(r.created_at) : ''}` }),
        h('pre', { style: 'white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;margin:8px 0 0', text: JSON.stringify(r.data, null, 2) }))),
      h('div', { style: 'display:flex;gap:8px' }, state.page > 1 ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { state.page--; load(); } }, 'Previous') : null,
        d.total > state.page * 25 ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { state.page++; load(); } }, 'Next') : null));
  }
  load();
}
