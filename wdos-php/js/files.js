/* Documents on reports and tasks: pick a file on the phone or computer, upload it, list what is attached, open it, remove it. */
import { h, icon, ago, toast } from './ui.js';
import { api } from './api.js';

export const kb = (n) => n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
const KIND = { 'application/pdf': 'PDF', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word', 'application/msword': 'Word', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel', 'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint', 'text/csv': 'CSV', 'image/jpeg': 'Picture', 'image/png': 'Picture' };
const readB64 = (file) => new Promise((ok, bad) => { const r = new FileReader(); r.onload = () => ok(String(r.result)); r.onerror = () => bad(new Error(tr('That file could not be read. Choose it again.'))); r.readAsDataURL(file); });
const tr = (s) => (globalThis.tr ? globalThis.tr(s) : s);

/** A button that opens the file chooser and uploads. purpose 'report' accepts PDF and Word only. onDone(file) runs after the upload. */
export function uploadButton(label, purpose, onDone, opts = {}) {
  const accept = purpose === 'report' ? '.pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document' : '.pdf,.doc,.docx,.xlsx,.pptx,.csv,.jpg,.jpeg,.png';
  const pick = h('input', { type: 'file', accept, hidden: true, multiple: !!opts.multiple });
  const state = h('small', { class: 'faint', 'aria-live': 'polite' });
  const btn = h('button', { class: opts.cls || 'btn btn--quiet', type: 'button', onclick: () => pick.click() }, icon('plus', 18), label);
  pick.addEventListener('change', async () => {
    const files = [...pick.files]; pick.value = ''; if (!files.length) return;
    for (const f of files) {
      if (f.size > 10 * 1048576) { toast(tr('That file is too large'), tr('The limit is 10 MB. Save the report as a PDF, or make the pictures in it smaller.'), 'bad'); continue; }
      btn.disabled = true; state.textContent = tf('Uploading {1}…', f.name);
      try { const r = await api('/files', { name: f.name, data: await readB64(f), purpose }); await onDone(r.file); state.textContent = ''; }
      catch (e) { state.textContent = ''; toast(tr('Not uploaded'), e.message, 'bad'); }
      btn.disabled = false;
    }
  });
  return h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, btn, pick, state,
    h('small', { class: 'faint', text: purpose === 'report' ? tr('PDF or Word (.docx), up to 10 MB.') : tr('PDF, Word, Excel, PowerPoint, CSV or a picture, up to 10 MB.') }));
}

/** The list of documents. Each opens in a new tab (PDF and pictures) or downloads (Word, Excel). */
export function docList(docs, opts = {}) {
  if (!docs.length) return h('p', { class: 'faint', style: 'margin:0;font-size:14px', text: opts.emptyText || tr('No document attached yet.') });
  return h('div', { class: 'list' }, docs.map((d) => h('div', { style: 'flex-wrap:wrap' },
    h('div', { class: 'nrow__ico' }, icon('book', 18)),
    h('div', { class: 'grow', style: 'min-width:180px' }, h('b', { style: 'overflow-wrap:anywhere', text: d.name }),
      h('small', { text: [KIND[d.mime] || '', kb(d.size), d.role === 'brief' ? tr('From the person who gave the task') : d.role === 'work' ? tr('Finished work') : '', d.added_by, ago(d.created_at)].filter(Boolean).join(' · ') })),
    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
      h('a', { class: 'btn btn--quiet btn--s', href: d.url, target: '_blank', rel: 'noopener' }, ['application/pdf', 'image/jpeg', 'image/png'].includes(d.mime) ? tr('View') : tr('Download')),
      opts.canRemove && opts.canRemove(d) ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => opts.onRemove(d) }, tr('Remove')) : null))));
}
