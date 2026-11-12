/* HQ: the 80 percent instrument from section 15 of the production handoff. WDOS does the arithmetic; people supply weights and verification. */
import { h, card, sk, empty, fmtDate, toast, input, fmt } from '../ui.js';
import { api } from '../api.js';

const COLS = ['workflow_id', 'area', 'workflow_name', 'steps', 'trigger', 'outcome', 'frequency', 'manual_minutes_baseline', 'eligible_weight', 'digitally_executable', 'exception_coverage', 'used_in_production', 'test_id', 'verified_by', 'verification_date', 'source_system'];
const csv = (rows) => rows.map((r) => r.map((v) => { const s = v === null || v === undefined ? '' : String(v); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; }).join(',')).join('\r\n');
export function download(name, text) { const a = h('a', { href: URL.createObjectURL(new Blob(['﻿' + text], { type: 'text/csv' })), download: name }); document.body.append(a); a.click(); a.remove(); }
const yn = (v) => { const s = h('select', { class: 'input', style: 'max-width:92px' }, ['yes', 'part', 'no'].map((x) => h('option', { value: x, text: x }))); s.value = v; return s; };

export async function render(root) {
  const head = h('div', {}, sk.rows(2)), list = h('div', {}, sk.rows(6)); let items = [];
  root.append(card('Digital coverage of routine work', 'Coverage is the weight of workflows that run fully through WDOS and have been verified by a named person, divided by the weight of all eligible routine workflows. Workflows WDOS does not do stay in the list and count against the figure.', head,
    h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => download('workflow_catalogue.csv', csv([COLS, ...items.map((w) => [w.workflow_id, w.area, w.workflow_name, w.steps, w.trigger_text, w.outcome, w.frequency, w.manual_minutes_baseline, w.eligible_weight, w.digitally_executable, w.exception_coverage, w.used_in_production, w.test_id, w.verified_by, w.verification_date, w.source_system])])) }, 'Download as CSV')),
    card('Workflow catalogue', 'The business owner and QA agree the weights. "Digital" and "exceptions covered" are the developer\'s declaration until someone verifies the row.', list));
  async function load() {
    let d; try { d = await api('/hq/workflows'); } catch (e) { list.replaceChildren(empty('x', 'The catalogue did not load', e.message)); return; }
    items = d.items; const s = d.score;
    head.replaceChildren(h('div', { class: 'kv' },
      h('div', {}, h('span', { text: 'Verified digital coverage' }), h('b', { text: s.score === null ? 'Cannot be stated yet: no weights have been agreed' : s.score + '% (target 80%)' })),
      h('div', {}, h('span', { text: 'Declared by the developer, not yet verified' }), h('b', { text: s.declared_score === null ? 'No weights yet' : s.declared_score + '%' })),
      h('div', {}, h('span', { text: 'Workflows still without a weight' }), h('b', { text: `${fmt(s.unweighted)} of ${fmt(items.length)}` })),
      d.estimates_on ? h('div', {}, h('span', { text: 'Where the weights came from' }), h('b', { text: 'A starting estimate proposed by Claude on ' + fmtDate(d.estimates_on) + '. Change any of them. A workflow counts only after a named person verifies it.' })) : null,
      h('div', {}, h('span', { text: 'As of' }), h('b', { text: fmtDate(d.as_of, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + ' GMT' }))));
    const row = (w) => { const wt = input({ type: 'number', min: 0, max: 1000, step: '0.5', placeholder: 'Weight', style: 'max-width:96px', 'aria-label': 'Weight' }); wt.value = w.eligible_weight ?? ''; const mins = input({ type: 'number', min: 0, placeholder: 'Minutes by hand', style: 'max-width:130px', 'aria-label': 'Minutes by hand before WDOS' }); mins.value = w.manual_minutes_baseline ?? '';
      const dig = yn(w.digitally_executable), exc = yn(w.exception_coverage), used = yn(w.used_in_production); const by = input({ maxlength: 120, placeholder: 'Verified by', style: 'max-width:170px' }); by.value = w.verified_by; const on = input({ type: 'date', style: 'max-width:160px', 'aria-label': 'Verified on' }); on.value = w.verification_date || '';
      const lab = (txt, n) => h('label', { style: 'display:grid;gap:2px;font-size:12px' }, h('span', { class: 'faint', text: txt }), n);
      return h('div', { class: 'list__link', style: 'display:grid;gap:8px;grid-template-columns:minmax(0,1fr)' }, h('div', { style: 'min-width:0' }, h('b', { style: 'overflow-wrap:anywhere', text: `${w.workflow_id}. ${w.workflow_name}` }), h('small', { style: 'display:block;white-space:normal;overflow-wrap:anywhere', text: [w.area, w.steps, w.test_id ? 'Tests: ' + w.test_id : ''].filter(Boolean).join(' · ') })),
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:end' }, lab('Weight', wt), lab('Minutes by hand', mins), lab('Fully digital', dig), lab('Exceptions covered', exc), lab('Used on live', used), lab('Verified by', by), lab('Verified on', on),
          h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/hq/workflows', { ...w, eligible_weight: wt.value, manual_minutes_baseline: mins.value, digitally_executable: dig.value, exception_coverage: exc.value, used_in_production: used.value, verified_by: by.value, verification_date: on.value }); toast('Saved', w.workflow_id, 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Save'))); };
    const nid = input({ maxlength: 12, placeholder: 'ID, for example W31', style: 'max-width:170px' }), nar = input({ maxlength: 60, placeholder: 'Area', style: 'max-width:170px' }), nn = input({ maxlength: 190, placeholder: 'Name of the workflow', style: 'min-width:240px;flex:1' });
    list.replaceChildren(h('div', { class: 'list list--stack' }, items.map(row)), h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;margin-top:14px' }, nid, nar, nn, h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { try { await api('/hq/workflows', { workflow_id: nid.value, area: nar.value, workflow_name: nn.value }); load(); } catch (e) { toast('Not added', e.message, 'bad'); } } }, 'Add a workflow')));
  }
  load();
}
