/* Small DOM toolkit: elements, icons, avatars, toasts, skeletons, empty states. No framework. */
/* Languages. The dictionary is keyed by the English sentence, so a sentence with no translation yet simply stays in English. */
export const LANGS = { en: 'English', fr: 'Français', pt: 'Português', ar: 'العربية', sw: 'Kiswahili' };
export const i18n = {
  lang: 'en', dict: null,
  saved() { try { const l = localStorage.getItem('wdos.lang'); return LANGS[l] ? l : 'en'; } catch { return 'en'; } },
  async load(lang) {
    this.lang = LANGS[lang] ? lang : 'en'; this.dict = null;
    if (this.lang !== 'en') { try { const r = await fetch(`/assets/i18n/${this.lang}.json`); if (r.ok) this.dict = await r.json(); } catch { /* stays English */ } }
    document.documentElement.lang = this.lang; document.documentElement.dir = this.lang === 'ar' ? 'rtl' : 'ltr';
  },
  async set(lang) { try { localStorage.setItem('wdos.lang', lang); } catch { /* private mode */ } await this.load(lang); },
};
export const t = (s) => { if (!i18n.dict || typeof s !== 'string') return s; const k = s.trim(); const v = k && i18n.dict[k]; return v ? s.replace(k, () => v) : s; };
const T_ATTR = { placeholder: 1, 'aria-label': 1, title: 1, alt: 1, 'data-tip': 1 };
export function h(tag, attrs = {}, ...kids) {
  const n = document.createElement(tag);
  for (let [k, v] of Object.entries(attrs || {})) {
    if (v === null || v === undefined || v === false) continue;
    if (T_ATTR[k] || k === 'text') v = t(v);
    if (k === 'class') n.className = v;
    else if (k === 'text') n.textContent = v;
    else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
    else if (k === 'value') n.value = v;
    else if (v === true) n.setAttribute(k, '');
    else n.setAttribute(k, v);
  }
  for (const k of kids.flat(Infinity)) if (k !== null && k !== undefined && k !== false) n.append(k.nodeType ? k : document.createTextNode(typeof k === 'string' ? t(k) : String(k)));
  return n;
}
export const clear = (n) => { while (n.firstChild) n.removeChild(n.firstChild); return n; };

const I = {
  grid: ['r:3,3,7,7,1.5', 'r:14,3,7,7,1.5', 'r:14,14,7,7,1.5', 'r:3,14,7,7,1.5'],
  users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'c:9,7,4', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
  megaphone: ['M3 11l18-5v12L3 14v-3z', 'M11.6 16.8a3 3 0 1 1-5.8-1.6'],
  spark: ['M12 3l1.9 5.6L19.5 10.5 13.9 12.4 12 18l-1.9-5.6L4.5 10.5l5.6-1.9z', 'M19 3v4', 'M21 5h-4', 'M5 17v3', 'M6.5 18.5h-3'],
  bell: ['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9', 'M10.3 21a1.94 1.94 0 0 0 3.4 0'],
  sliders: ['M4 21v-7', 'M4 10V3', 'M12 21v-9', 'M12 8V3', 'M20 21v-5', 'M20 12V3', 'M1 14h6', 'M9 8h6', 'M17 16h6'],
  collapse: ['M11 17l-5-5 5-5', 'M18 17l-5-5 5-5'],
  menu: ['M3 12h18', 'M3 6h18', 'M3 18h18'],
  sun: ['c:12,12,4', 'M12 2v2', 'M12 20v2', 'M4.93 4.93l1.41 1.41', 'M17.66 17.66l1.41 1.41', 'M2 12h2', 'M20 12h2', 'M6.34 17.66l-1.41 1.41', 'M19.07 4.93l-1.41 1.41'],
  moon: ['M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z'],
  logout: ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'M16 17l5-5-5-5', 'M21 12H9'],
  search: ['c:11,11,8', 'M21 21l-4.3-4.3'],
  fingerprint: ['M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4', 'M14 13.12c0 2.38 0 6.38-1 8.88', 'M17.29 21.02c.12-.6.43-2.3.5-3.02', 'M2 12a10 10 0 0 1 18-6', 'M2 16h.01', 'M21.8 16c.2-2 .131-5.354 0-6', 'M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2', 'M8.65 22c.21-.66.45-1.32.57-2', 'M9 6.8a6 6 0 0 1 9 5.2v2'],
  up: ['M7 17L17 7', 'M7 7h10v10'], down: ['M7 7l10 10', 'M17 7v10H7'], flat: ['M5 12h14'],
  chevdown: ['M6 9l6 6 6-6'],
  eye: ['M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z', 'c:12,12,3'],
  check: ['M20 6L9 17l-5-5'], x: ['M18 6L6 18', 'M6 6l12 12'], plus: ['M12 5v14', 'M5 12h14'],
  shield: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
  pin: ['M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z', 'c:12,10,3'],
  key: ['c:7.5,15.5,5.5', 'M21 2l-9.6 9.6', 'M15.5 7.5l3 3L22 7l-3-3'],
  user: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'c:12,7,4'],
  send: ['M22 2L11 13', 'M22 2l-7 20-4-9-9-4 20-7z'],
  inbox: ['M22 12h-6l-2 3h-4l-2-3H2', 'M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z'],
  globe: ['c:12,12,10', 'M2 12h20', 'M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z'],
  award: ['c:12,8,6', 'M15.5 12.9L17 22l-5-3-5 3 1.5-9.1'],
  chart: ['M3 3v18h18', 'M7 15l4-4 3 3 5-6'],
  camera: ['M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z', 'c:12,13,4'],
  cake: ['M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8', 'M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1', 'M2 21h20', 'M12 7v4', 'M12 3v1'],
  clock: ['c:12,12,10', 'M12 6v6l4 2'],
  tasks: ['M9 11l3 3L22 4', 'M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
  calendar: ['r:3,4,18,18,2', 'M16 2v4', 'M8 2v4', 'M3 10h18'],
  tree: ['r:9,2,6,6,1', 'r:2,16,6,6,1', 'r:16,16,6,6,1', 'M12 8v4', 'M5 16v-2a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2'],
  link: ['M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71', 'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'],
  book: ['M4 19.5A2.500 2.500 0 0 1 6.500 17H20', 'M6.500 2H20v20H6.500A2.500 2.500 0 0 1 4 19.500v-15A2.500 2.500 0 0 1 6.500 2z'],
  route: ['c:6,19,3', 'c:18,5,3', 'M9 19h8.500a3.500 3.500 0 0 0 0-7h-11a3.500 3.500 0 0 1 0-7H15'],
};
const NS = 'http://www.w3.org/2000/svg';
export function icon(name, size = 20) {
  const s = document.createElementNS(NS, 'svg');
  s.setAttribute('viewBox', '0 0 24 24'); s.setAttribute('width', size); s.setAttribute('height', size);
  s.setAttribute('fill', 'none'); s.setAttribute('stroke', 'currentColor'); s.setAttribute('stroke-width', '1.75');
  s.setAttribute('stroke-linecap', 'round'); s.setAttribute('stroke-linejoin', 'round'); s.setAttribute('aria-hidden', 'true'); s.setAttribute('class', 'ico');
  for (const d of I[name] || I.grid) {
    let e;
    if (d.startsWith('c:')) { const [cx, cy, r] = d.slice(2).split(','); e = document.createElementNS(NS, 'circle'); e.setAttribute('cx', cx); e.setAttribute('cy', cy); e.setAttribute('r', r); }
    else if (d.startsWith('r:')) { const [x, y, w, ht, rx] = d.slice(2).split(','); e = document.createElementNS(NS, 'rect'); e.setAttribute('x', x); e.setAttribute('y', y); e.setAttribute('width', w); e.setAttribute('height', ht); e.setAttribute('rx', rx); }
    else { e = document.createElementNS(NS, 'path'); e.setAttribute('d', d); }
    s.append(e);
  }
  return s;
}
export function googleMark() {
  const d = h('span'); d.innerHTML = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.6 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.5-4.7 7.2l7.6 5.9c4.4-4.1 7-10.1 7-17.6z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.4-.8-3-.8-4.7s.3-3.3.8-4.7l-7.9-6.1C1 16.500 0 20.100 0 24s1 7.500 2.600 10.800l7.900-6.100z"/><path fill="#34A853" d="M24 48c6.300 0 11.700-2.100 15.600-5.700l-7.600-5.900c-2.100 1.400-4.800 2.300-8 2.300-6.300 0-11.600-4.100-13.500-9.800l-7.900 6.100C6.500 42.600 14.600 48 24 48z"/></svg>';
  return d.firstChild;
}
export function avatar(p, size = 40) {
  const initials = ((p.first_name || '?')[0] + (p.last_name || '')[0] || '').toUpperCase();
  if (p.has_photo && p.has_photo !== '0') {
    return h('img', { class: 'avatar', src: `/api/photo/${p.id}${p._v ? '?v=' + p._v : ''}`, alt: '', width: size, height: size, style: `width:${size}px;height:${size}px`, loading: 'lazy' });
  }
  return h('span', { class: 'avatar', style: `width:${size}px;height:${size}px;font-size:${Math.round(size * 0.36)}px`, 'aria-hidden': 'true', text: initials });
}
export const flag = (code) => code ? h('img', { class: 'flag', src: `/assets/flags/${String(code).toLowerCase()}.svg`, alt: '', loading: 'lazy' }) : null;
export const netTag = (n) => n ? h('span', { class: 'tag tag--' + n.toLowerCase(), text: n }) : null;
export const NETS = {
  WGMN: { name: 'WODDI Good Mother Network', short: 'WGMN', age: 'Ages 30 and above', line: 'Nurturing women. Shaping generations.', disc: '/assets/logos/wgmn-disc.png' },
  WNNN: { name: 'WODDI Nurture NextGen Network', short: 'WNNN', age: 'Ages 18 to 29', line: 'Becoming more. Becoming ready.', disc: '/assets/logos/wnnn-disc.png' },
};

/* time */
const parse = (s) => (s ? new Date(String(s).replace(' ', 'T') + 'Z') : null);
export function ago(s) {
  const d = parse(s); if (!d) return 'Never';
  const m = Math.round((Date.now() - d) / 60000);
  if (m < 1) return 'Just now'; if (m < 60) return m + ' min ago';
  const hr = Math.round(m / 60); if (hr < 24) return hr + (hr === 1 ? ' hour ago' : ' hours ago');
  const dy = Math.round(hr / 24); if (dy < 7) return dy + (dy === 1 ? ' day ago' : ' days ago');
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: d.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' });
}
/** For use mid-sentence: 'seen 3 days ago', but dates keep their capitals. */
export const agoIn = (s) => { const t = ago(s); return /ago|now/i.test(t) ? t.toLowerCase() : 'on ' + t; };
export const fmtDate = (s, o = { day: 'numeric', month: 'short', year: 'numeric' }) => { const d = parse(s && s.length === 10 ? s + ' 00:00:00' : s); return d ? d.toLocaleDateString(undefined, { timeZone: 'UTC', ...o }) : ''; };
export const fmt = (n) => Number(n || 0).toLocaleString();

/* toasts */
let tray;
export function toast(title, body = '', kind = 'info', onclick) {
  if (!tray) { tray = h('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' }); document.body.append(tray); }
  const t = h('div', { class: 'toast toast--' + kind, onclick: () => { t.remove(); onclick && onclick(); } },
    icon(kind === 'ok' ? 'check' : kind === 'bad' ? 'x' : 'bell', 20), h('div', {}, h('b', { text: title }), body ? h('p', { text: body }) : null), h('span', { class: 'faint' }, icon('x', 16)));
  tray.append(t); setTimeout(() => t.remove(), kind === 'bad' ? 9000 : 6000);
}

/* empty states: one voice, always says what to do next */
export function empty(ic, title, text, action) {
  return h('div', { class: 'empty reveal' }, h('div', { class: 'empty__art' }, icon(ic, 28)), h('h3', { text: title }), h('p', { text }), action || null);
}
/* skeletons: the shape of what is coming, not a spinner */
export const sk = {
  kpi: () => h('div', { class: 'card kpi', 'aria-hidden': 'true' }, h('div', { class: 'sk sk--line', style: 'width:48%' }), h('div', { class: 'sk sk--num' }), h('div', { class: 'sk sk--line', style: 'width:70%' })),
  chart: (ht = 240) => h('div', { class: 'sk sk--chart', style: `height:${ht}px`, 'aria-hidden': 'true' }),
  rows: (n = 5) => h('div', { class: 'list', 'aria-hidden': 'true' }, Array.from({ length: n }, (_, i) => h('div', {}, h('div', { class: 'sk sk--dot' }), h('div', { class: 'grow', style: 'display:grid;gap:8px' }, h('div', { class: 'sk sk--line', style: `width:${62 - i * 6}%` }), h('div', { class: 'sk sk--line', style: `width:${38 - i * 3}%` }))))),
  bars: (n = 5) => h('div', { class: 'bars', 'aria-hidden': 'true' }, Array.from({ length: n }, (_, i) => h('div', { class: 'sk sk--line', style: `height:14px;width:${92 - i * 14}%` }))),
  table: (cols, n = 8) => Array.from({ length: n }, () => h('tr', { 'aria-hidden': 'true' }, Array.from({ length: cols }, (_, i) => h('td', {}, h('div', { class: 'sk sk--line', style: `width:${i === 0 ? 70 : 45 + (i * 13) % 40}%` }))))),
};
export function card(title, sub, body, right) {
  return h('section', { class: 'card' }, h('div', { class: 'card__head' }, h('div', { class: 'grow' }, h('h2', { text: title }), sub ? h('p', { text: sub }) : null), right || null), h('div', { class: 'card__body' }, body));
}
export const greeting = () => { const hr = new Date().getHours(); return hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening'; };
export const theme = {
  get: () => document.documentElement.dataset.theme === 'light' ? 'light' : 'dark',
  set(t) { document.documentElement.dataset.theme = t; try { localStorage.setItem('wdos.theme', t); } catch { /* private mode */ } document.querySelector('meta[name=theme-color]')?.setAttribute('content', t === 'light' ? '#FAF4F7' : '#130912'); window.dispatchEvent(new Event('wdos:theme')); },
  toggle() { this.set(this.get() === 'light' ? 'dark' : 'light'); },
};

/* An HQ inspiration message: picture on the right, words on the calm left side. The words are live text, never part of the picture. */
export const tonePhoto = (tone) => '/api/inspiration/photo?k=' + encodeURIComponent(`messages/${tone}.webp`);
/* The message card. The photograph is a royalty-free one HQ chose; when none is loaded the card stands on the brand colours.
 * The write-up opens in full on request, so a long message never crowds the dashboard. */
export function inspCard(c, full = false) {
  const birthday = c.kind === 'birthday'; const paras = String(c.body || '').split(/\n\s*\n|\r?\n/).map((x) => x.trim()).filter(Boolean);
  const body = paras.length ? h('div', { class: 'insp__body' + (full ? '' : ' is-clamped') }, paras.map((p) => h('p', { text: p }))) : null;
  const more = body && !full ? h('button', { class: 'insp__more', type: 'button', 'aria-expanded': 'false', onclick: () => { const open = body.classList.toggle('is-clamped') === false; more.setAttribute('aria-expanded', String(open)); more.textContent = t(open ? 'Show less' : 'Read the full message'); } }, 'Read the full message') : null;
  const img = c.image ? h('img', { src: c.image, alt: '', loading: 'lazy' }) : null; if (img) img.addEventListener('error', () => { img.remove(); card.classList.add('insp--plain'); });
  const card = h('section', { class: 'insp reveal' + (c.image ? '' : ' insp--plain'), 'aria-label': c.label }, img,
    h('div', { class: 'insp__text' }, h('div', { class: 'insp__label', text: c.label + (c.theme ? '. ' + c.theme : '') }),
      c.first_name ? h('div', { class: 'insp__dear', text: (birthday ? 'Happy birthday, ' : 'Dear ') + c.first_name + (birthday ? '.' : ',') }) : null,
      h('h2', { class: 'insp__head', text: c.headline }), c.key_thought ? h('p', { class: 'insp__thought', text: c.key_thought }) : null,
      body, more, c.reflection ? h('div', { class: 'insp__reflect' }, h('small', { text: 'Reflection' }), h('p', { text: c.reflection })) : null, h('div', { class: 'insp__sign', text: 'WODDI HQ' })));
  if (body && !full) requestAnimationFrame(() => { if (body.scrollHeight <= body.clientHeight + 4) { more.remove(); body.classList.remove('is-clamped'); } });
  return card;
}
export const TONES = [['celebration', 'Celebration'], ['gratitude', 'Gratitude'], ['courage', 'Courage'], ['calm', 'Calm'], ['growth', 'Growth'], ['purpose', 'Purpose']];

/* A document read inside WDOS. The text is fetched and placed in our own scroller, so we know when the reader reached the end. */
export function docReader(url, onEnd, title, sections) {
  const page = h('article', { class: 'doc' }, h('p', { class: 'muted', text: 'Opening the document…' }));
  const scroller = h('div', { class: 'doc__scroll', tabindex: '0' }, page); let told = false;
  const seen = () => { if (!told && scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 48) { told = true; onEnd && onEnd(); } };
  scroller.addEventListener('scroll', seen);
  fetch(url, { cache: 'no-cache' }).then((r) => { if (!r.ok) throw new Error(String(r.status)); return r.text(); }).then((html) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    doc.querySelectorAll('script, style, iframe, object, embed, link').forEach((x) => x.remove());
    let nodes = Array.from((doc.body && doc.body.childNodes.length ? doc.body : doc.documentElement).childNodes);
    if (sections && sections.length) { const want = sections.map((x) => String(x).trim().toLowerCase()); const keep = []; let on = false;   // only the sections named for this day
      for (const n of nodes) { if (n.nodeType === 1 && n.tagName === 'H2') { const tx = n.textContent.trim().toLowerCase(); on = want.some((w) => tx === w || tx.startsWith(w) || w.startsWith(tx)); } if (on) keep.push(n); }
      if (keep.length) nodes = keep; }
    page.replaceChildren(...nodes); setTimeout(seen, 60);
  }).catch(() => { page.replaceChildren(h('p', { text: 'This document could not be opened here. Open it in a new tab to read it, then continue.' })); told = true; onEnd && onEnd(); });
  return h('div', { class: 'docwrap' }, h('div', { class: 'doc__bar' }, h('b', { text: title || 'Read this document' }), h('a', { class: 'btn btn--ghost btn--s', href: url, target: '_blank', rel: 'noopener', text: 'Open in a new tab' })), scroller);
}
export const input = (attrs) => h('input', { class: 'input', ...attrs });
export const field = (label, node, hint) => h('label', { class: 'field' }, h('span', { text: label }), node, hint ? h('small', { text: hint }) : null);
export const tabs = (list, current, base) => h('div', { class: 'seg rtabs', role: 'tablist' }, list.map(([k, l]) => h('a', { role: 'tab', class: 'seg__a', 'aria-pressed': String(k === current), href: base + (k ? '/' + k : ''), text: l })));
export function langPicker(onPick) {
  const s = h('select', { class: 'input langpick', 'aria-label': 'Language' }, Object.entries(LANGS).map(([v, l]) => { const o = document.createElement('option'); o.value = v; o.textContent = l; return o; }));
  s.value = i18n.lang; s.addEventListener('change', () => onPick(s.value)); return s;
}
/* Language pills for the public pages. Languages still being prepared are listed but cannot be chosen yet. */
export const LANGS_SOON = ['Afrikaans', 'Amharic', 'Bambara', 'Chichewa', 'Fulfulde', 'Hausa', 'Igbo', 'Kinyarwanda', 'Kirundi', 'Lingala', 'Luganda', 'Malagasy', 'Oromo', 'Sesotho', 'Setswana', 'Shona', 'Somali', 'Tigrinya', 'Twi', 'Wolof', 'Xhosa', 'Yorùbá', 'Zulu'];
export function langPills(onPick) {
  const order = ['en', 'fr', 'ar', 'sw', 'pt'];
  const more = h('details', { class: 'lpill lpill--more' }, h('summary', {}, h('span', { text: 'Other African Languages' }), icon('chevdown', 16)),
    h('div', { class: 'lpill__menu' }, LANGS_SOON.map((l) => h('div', { class: 'lpill__soon' }, document.createTextNode(l), h('small', { text: 'Being prepared' })))));
  document.addEventListener('click', (e) => { if (more.isConnected && !more.contains(e.target)) more.removeAttribute('open'); });
  return h('nav', { class: 'lpills', 'aria-label': 'Language' }, order.map((code) => { const on = i18n.lang === code; const b = document.createElement('button');
    b.type = 'button'; b.className = 'lpill' + (on ? ' is-on' : ''); b.setAttribute('aria-pressed', String(on)); b.lang = code; if (on) b.append(icon('globe', 18)); b.append(document.createTextNode(LANGS[code]));
    b.addEventListener('click', () => { if (!on) onPick(code); }); return b; }), more);
}
/* Text with any web address in it turned into a link that can be tapped. */
export function linkify(text) {
  const out = []; const re = /(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g; let last = 0, m; const str = String(text || '');
  while ((m = re.exec(str))) { if (m.index > last) out.push(document.createTextNode(str.slice(last, m.index))); const a = document.createElement('a'); a.href = m[1]; a.target = '_blank'; a.rel = 'noopener'; a.textContent = m[1]; a.className = 'tlink'; out.push(a); last = m.index + m[1].length; }
  if (last < str.length) out.push(document.createTextNode(str.slice(last)));
  return out;
}
