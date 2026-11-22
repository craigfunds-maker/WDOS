/* Documents on reports and tasks: pick a file on the phone or computer, upload it, list what is attached, open it, remove it. */
import { h, icon, ago, toast } from './ui.js';
import { api } from './api.js';

export const kb = (n) => n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
const KIND = { 'application/pdf': 'PDF', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word', 'application/msword': 'Word', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel', 'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint', 'text/csv': 'CSV', 'image/jpeg': 'Picture', 'image/png': 'Picture' };
export const readB64 = (file) => new Promise((ok, bad) => { const r = new FileReader(); r.onload = () => ok(String(r.result)); r.onerror = () => bad(new Error(tr('That file could not be read. Choose it again.'))); r.readAsDataURL(file); });
/* v109.5: the live server refuses one request over about 1.5 MB, so a file larger than 600 KB goes up in 600 KB pieces
 * and the call that saves it names the upload ('upload:<id>') instead of carrying the data. Small files go inline. */
const PART = 600 * 1024;
const b64of = (buf) => { let s = ''; const u = new Uint8Array(buf); for (let i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); };
export async function packFile(file, onProgress) {
  if (file.size <= PART) return readB64(file);
  const buf = await file.arrayBuffer(); const total = Math.ceil(buf.byteLength / PART);
  const id = [...crypto.getRandomValues(new Uint8Array(16))].map((x) => x.toString(16).padStart(2, '0')).join('');
  for (let seq = 0; seq < total; seq++) {
    let tries = 0;
    for (;;) {
      try { await api('/uploads/part', { upload: id, seq, total, data: b64of(buf.slice(seq * PART, (seq + 1) * PART)) }); break; }
      catch (e) { if (++tries >= 3) throw e; await new Promise((r) => setTimeout(r, 1500 * tries)); }   // a weak connection: try that piece again
    }
    onProgress?.(Math.round(((seq + 1) / total) * 100));
  }
  return 'upload:' + id;
}
const tr = (s) => (globalThis.tr ? globalThis.tr(s) : s);

/** A button that opens the file chooser and uploads. purpose 'report' accepts PDF and Word only. onDone(file) runs after the upload. */
export function uploadButton(label, purpose, onDone, opts = {}) {
  const accept = purpose === 'report' ? '.pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    : purpose === 'room' ? '.pdf,.doc,.docx,.xlsx,.pptx,.csv,.jpg,.jpeg,.png,.mp4,.webm,.mp3,.m4a,.ogg,.wav,image/*,video/mp4,video/webm,audio/*' : '.pdf,.doc,.docx,.xlsx,.pptx,.csv,.jpg,.jpeg,.png';
  const maxMb = purpose === 'room' ? 15 : 10;
  const pick = h('input', { type: 'file', accept, hidden: true, multiple: !!opts.multiple });
  const state = h('small', { class: 'faint', 'aria-live': 'polite' });
  const btn = h('button', { class: opts.cls || 'btn btn--quiet', type: 'button', onclick: () => pick.click() }, icon('plus', 18), label);
  pick.addEventListener('change', async () => {
    const files = [...pick.files]; pick.value = ''; if (!files.length) return;
    for (const f of files) {
      if (f.size > maxMb * 1048576) { toast(tr('That file is too large'), maxMb === 15 ? tr('The limit is 15 MB. Share a longer video as a link.') : tr('The limit is 10 MB. Save the report as a PDF, or make the pictures in it smaller.'), 'bad'); continue; }
      btn.disabled = true; state.textContent = tf('Uploading {1}…', f.name);
      try { const r = await api('/files', { name: f.name, data: await packFile(f, (p) => { state.textContent = tf('Uploading {1}… {2}%', f.name, p); }), purpose }); await onDone(r.file); state.textContent = ''; }
      catch (e) { state.textContent = ''; toast(tr('Not uploaded'), e.message, 'bad'); }
      btn.disabled = false;
    }
  });
  return h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, btn, pick, state,
    h('small', { class: 'faint', text: purpose === 'report' ? tr('PDF or Word (.docx), up to 10 MB.') : purpose === 'room' ? tr('Documents and pictures up to 10 MB; voice notes and MP4 videos up to 15 MB.') : tr('PDF, Word, Excel, PowerPoint, CSV or a picture, up to 10 MB.') }));
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
