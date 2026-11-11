/* Document library: the documents leaders and members need, read inside WDOS or opened as files. HQ adds and archives. */
import { h, icon, card, sk, empty, fmtDate, toast, input, field, docReader } from '../ui.js';
import { api } from '../api.js';

const AUD = { all: 'Everyone', leaders: 'Leaders', hq: 'HQ only' };
const toData = (file) => new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file); });

export async function render(root) {
  const body = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); const reader = h('div'); root.append(reader, body);
  async function load() {
    let d; try { d = await api('/library'); } catch (e) { body.replaceChildren(h('div', { class: 'card' }, empty('x', 'The library did not load', e.message))); return; }
    const cats = [...new Set(d.items.map((x) => x.category))]; const parts = [];
    const open = (it) => { if (it.kind === 'doc') { reader.replaceChildren(card(it.title, it.description, docReader(it.url, null, it.title), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => reader.replaceChildren() }, 'Close'))); reader.scrollIntoView({ behavior: 'smooth' }); } else window.open(it.kind === 'file' ? `/api/library/${it.id}/file` : it.url, '_blank', 'noopener'); };
    for (const c of cats) parts.push(card(c, null, h('div', { class: 'list reveal' }, d.items.filter((x) => x.category === c).map((it) => h('div', { style: it.archived ? 'opacity:.55' : '' }, h('div', { class: 'nrow__ico' }, icon(it.kind === 'link' ? 'link' : 'book', 18)),
      h('div', { class: 'grow' }, h('b', { text: it.title }), h('small', { text: [it.description, it.kind === 'file' ? `${it.file_name} · ${Math.max(1, Math.round(it.size / 1024))} KB` : it.kind === 'link' ? 'Link' : 'Read in WDOS', d.is_hq ? 'For: ' + AUD[it.audience] : '', 'Added ' + fmtDate(it.created_at)].filter(Boolean).join(' · ') })),
      h('div', { class: 'tagrow' }, it.archived ? h('span', { class: 'tag tag--bad', text: 'Archived' }) : null, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => open(it) }, it.kind === 'doc' ? 'Read' : it.kind === 'file' ? 'Open file' : 'Open link'),
        d.is_hq ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/library/' + it.id, { archived: !it.archived }); load(); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, it.archived ? 'Restore' : 'Archive') : null))))));
    if (!d.items.length) parts.push(h('div', { class: 'card' }, empty('book', 'No documents yet', 'Documents shared by WODDI HQ appear here.')));
    if (d.is_hq) { const title = input({ maxlength: 190 }), desc = input({ maxlength: 600 }), cat = input({ maxlength: 60, placeholder: 'e.g. Leadership, Templates, Policies' }), url = input({ type: 'url', placeholder: 'https://' }); const file = h('input', { type: 'file', class: 'input' }); const aud = h('select', { class: 'input' }, Object.entries(AUD).map(([v, l]) => h('option', { value: v, text: l }))); aud.value = 'leaders'; const err = h('p', { class: 'err', role: 'alert' });
      const add = h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; add.disabled = true; try { const f = file.files[0]; if (f && f.size > 6000000) throw new Error('That file is too large. The limit is 6 MB.');
        await api('/library', { title: title.value, description: desc.value, category: cat.value, audience: aud.value, url: url.value, data: f ? await toData(f) : '', file_name: f ? f.name : '' }); toast('Added to the library', '', 'ok'); load(); } catch (e) { err.textContent = e.message; add.disabled = false; } } }, 'Add to the library');
      parts.push(card('Add a document', 'Upload a file up to 6 MB, or paste a link (for example to Google Drive). Choose who can see it.', h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, field('Title', title), field('Section', cat), field('Short description', desc), field('Who can see it', aud), field('File', file), field('Or a link', url)), err, h('div', {}, add)))); }
    body.replaceChildren(...parts);
  }
  load();
}
