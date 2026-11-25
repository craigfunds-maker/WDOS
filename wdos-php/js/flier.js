/* v109.56: meeting fliers (Azeez, 25 Nov 2026). A flier is either the picture HQ uploaded, or one WDOS draws from the
 * meeting's details and the words HQ wrote, in the language the app is showing. The date and time are in the phone's own
 * time zone. The countdown card sits at the top of every dashboard, with one-touch replies. */
import { h, t, tf, i18n, icon, toast } from './ui.js';
import { api } from './api.js';
import { whenMine, timeIn } from './tz.js';

const when = (s) => (s instanceof Date ? s : new Date(String(s).replace(' ', 'T') + 'Z'));
const MAGENTA = '#D4006A', LEMON = '#7CB518', PLUM = '#2A0D1C';
const KIND = { meeting: 'Meeting', talk_show: 'Talk show' };
const MODE = { virtual: 'Online', in_person: 'In person', hybrid: 'In person and online' };
let logoP = null;
const logo = () => logoP || (logoP = new Promise((ok) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = '/assets/logos/woddi-disc.png'; }));

/** Lines of text that fit a width; at most `max` lines, the last one ending in "…" when cut. */
function wrap(ctx, text, width, max) {
  const out = [];
  for (const para of String(text || '').split(/\n+/)) {
    let line = '';
    for (const w of para.split(/\s+/).filter(Boolean)) {
      const test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > width && line) { out.push(line); line = w; } else line = test;
      if (out.length >= max) break;
    }
    if (line && out.length < max) out.push(line);
    if (out.length >= max) break;
  }
  const all = String(text || '').split(/\s+/).filter(Boolean).length, used = out.join(' ').split(/\s+/).filter(Boolean).length;
  if (used < all && out.length) { let l = out[out.length - 1]; while (l && ctx.measureText(l + '…').width > width) l = l.slice(0, -1); out[out.length - 1] = l + '…'; }
  return out;
}
function rrect(ctx, x, y, w, hh, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + hh, r); ctx.arcTo(x + w, y + hh, x, y + hh, r); ctx.arcTo(x, y + hh, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }

let tplP = null;
/** The flier design HQ uploaded (a background made in Canva, for example), or null for WDOS's own background. */
export const flierTemplate = (fresh) => (!fresh && tplP) || (tplP = api('/meetings/flier-template').then((d) => d.url ? new Promise((ok) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = d.url; }) : null).catch(() => null));
let qrMod = null;
/** A QR code drawn on the canvas: white card, dark modules, a caption under it. */
async function drawQr(x, url, left, top, size, caption) {
  try {
    const { default: qrcode } = await (qrMod || (qrMod = import('/js/vendor/qrcode.js')));
    const q = qrcode(0, 'M'); q.addData(url); q.make(); const n = q.getModuleCount(); const pad = 18, inner = size - 2 * pad, cell = inner / n;
    x.save(); x.shadowColor = 'rgba(0,0,0,.35)'; x.shadowBlur = 24; x.fillStyle = '#fff'; rrect(x, left, top, size, size + 44, 22); x.fill(); x.restore();
    x.fillStyle = PLUM; for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) x.fillRect(Math.floor(left + pad + c * cell), Math.floor(top + pad + r * cell), Math.ceil(cell), Math.ceil(cell));
    x.save(); x.direction = 'ltr'; x.textAlign = 'center'; x.fillStyle = MAGENTA; let cz = 22; x.font = `800 ${cz}px Inter, Arial, sans-serif`; while (cz > 12 && x.measureText(caption).width > size - 16) { cz -= 1; x.font = `800 ${cz}px Inter, Arial, sans-serif`; } x.fillText(caption, left + size / 2, top + size + 26); x.restore();
  } catch { /* no QR code; the flier still works */ }
}

const imgCache = {};
const loadImg = (src) => imgCache[src] || (imgCache[src] = new Promise((ok) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; }));
let mapP = null;
const africa = () => mapP || (mapP = fetch('/assets/geo/africa.json').then((r) => r.json()).catch(() => null));
const NET_DISC = { WGMN: '/assets/logos/wgmn-disc.png', WNNN: '/assets/logos/wnnn-disc.png' };
/** v109.58: the map of Africa, every country outlined, with the network's logo on it (Azeez, 25 Nov 2026). */
async function drawAfrica(x, left, top, size, net) {
  const d = await africa(); if (!d) return;
  const s = size / Math.max(d.w, d.h); x.save(); x.translate(left + (size - d.w * s) / 2, top + (size - d.h * s) / 2); x.scale(s, s);
  x.lineWidth = 1.6 / s * 0.6; x.strokeStyle = 'rgba(255,255,255,.55)'; x.fillStyle = net === 'WNNN' ? 'rgba(124,181,24,.55)' : 'rgba(212,0,106,.55)';
  for (const k of Object.keys(d.c)) { try { const p = new Path2D(d.c[k].d); x.fill(p); x.stroke(p); } catch { /* one shape */ } }
  x.restore();
  const disc = NET_DISC[net] ? await loadImg(NET_DISC[net]) : null;
  if (disc) { const r = size * 0.24; const cx = left + size * 0.2, cy = top + size * 0.8; x.save(); x.shadowColor = 'rgba(0,0,0,.4)'; x.shadowBlur = 16; x.beginPath(); x.arc(cx, cy, r, 0, Math.PI * 2); x.fillStyle = '#fff'; x.fill(); x.restore();
    x.save(); x.beginPath(); x.arc(cx, cy, r - 4, 0, Math.PI * 2); x.clip(); x.drawImage(disc, cx - r + 4, cy - r + 4, (r - 4) * 2, (r - 4) * 2); x.restore(); }
}

/** Draw the flier for a meeting on a 1080 x 1350 canvas (the shape social apps show best). Returns the canvas.
 *  The words are in the language the app is set to (the email pictures are drawn once per language). On HQ's design the words
 *  sit in the calm left part; the network's logo on the map of Africa top right; a QR code to the meeting bottom right.
 *  v109.58: an uploaded flier picture is placed inside, so its meeting still gets the words in each reader's language.
 *  opts.tz: draw the time in this zone (the email pictures use Nigeria time, with the zone named). */
export async function drawFlier(m, f = {}, opts = {}) {
  const W = 1080, H = 1350, P = 72; const c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
  try { await Promise.all([document.fonts.load('700 70px Fraunces'), document.fonts.load('600 34px Inter')]); } catch { /* system fonts */ }
  const tpl = await flierTemplate(); const photo = f.image ? await loadImg(f.image) : null;
  const rtl = (i18n.lang || 'en') === 'ar'; x.direction = rtl ? 'rtl' : 'ltr'; x.textAlign = rtl ? 'right' : 'left';
  const TW = tpl && !photo ? 640 : W - 2 * P; const X = rtl ? P + TW : P;
  if (tpl) { const s = Math.max(W / tpl.naturalWidth, H / tpl.naturalHeight); x.drawImage(tpl, (W - tpl.naturalWidth * s) / 2, (H - tpl.naturalHeight * s) / 2, tpl.naturalWidth * s, tpl.naturalHeight * s);
    const sh = x.createLinearGradient(0, 0, W * 0.75, 0); sh.addColorStop(0, 'rgba(20,4,12,.55)'); sh.addColorStop(1, 'rgba(20,4,12,0)'); x.fillStyle = sh; x.fillRect(0, 0, W, H * 0.86);
    if (photo) { x.fillStyle = 'rgba(20,4,12,.55)'; x.fillRect(0, 0, W, H); } }
  else { const g = x.createLinearGradient(0, 0, W, H); g.addColorStop(0, MAGENTA); g.addColorStop(0.55, '#8E0A4A'); g.addColorStop(1, PLUM); x.fillStyle = g; x.fillRect(0, 0, W, H);
    x.globalAlpha = 0.10; x.fillStyle = '#fff'; x.beginPath(); x.arc(W - 120, 160, 260, 0, Math.PI * 2); x.fill(); x.beginPath(); x.arc(90, H - 260, 200, 0, Math.PI * 2); x.fill(); x.globalAlpha = 1; }
  // top: the WODDI logo and slogan; the network on the map of Africa
  const lg = await logo(); let y = P - 20; const s0 = 112; const lx = rtl ? W - P - s0 : P;
  if (lg) { x.save(); x.beginPath(); x.arc(lx + s0 / 2, y + s0 / 2, s0 / 2, 0, Math.PI * 2); x.fillStyle = '#fff'; x.fill(); x.clip(); x.drawImage(lg, lx, y, s0, s0); x.restore(); }
  const tx = rtl ? lx - 22 : lx + s0 + 22;
  x.fillStyle = '#C9F27A'; x.font = '800 23px Inter, Arial, sans-serif'; x.fillText(t('No Woman Is Left Out').toUpperCase(), tx, y + 46);
  x.fillStyle = '#fff'; x.font = '600 27px Inter, Arial, sans-serif'; x.fillText(m.network === 'WGMN' ? 'WODDI · WGMN' : m.network === 'WNNN' ? 'WODDI · WNNN' : 'WODDI', tx, y + 86);
  const ms = photo ? 210 : 260; await drawAfrica(x, rtl ? P - 10 : W - P - ms + 10, 26, ms, m.network);
  y = photo ? 300 : 230;
  if (photo) {          // the uploaded picture, whole, in a white frame
    const ph = 470, pw = W - 2 * P; const sc = Math.min(pw / photo.naturalWidth, ph / photo.naturalHeight); const dw = photo.naturalWidth * sc, dh = photo.naturalHeight * sc;
    x.save(); x.shadowColor = 'rgba(0,0,0,.45)'; x.shadowBlur = 22; x.fillStyle = '#fff'; rrect(x, (W - dw) / 2 - 8, y - 8, dw + 16, dh + 16, 18); x.fill(); x.restore();
    x.drawImage(photo, (W - dw) / 2, y, dw, dh); y += dh + 30;
  }
  x.font = '800 24px Inter, Arial, sans-serif'; const kind = t(KIND[m.kind] || 'Meeting').toUpperCase(); const kw = x.measureText(kind).width + 44;
  x.fillStyle = LEMON; rrect(x, rtl ? X - kw : X, y, kw, 46, 23); x.fill(); x.fillStyle = '#12210A'; x.fillText(kind, rtl ? X - 22 : X + 22, y + 32); y += 70;
  let fs = photo ? 56 : 74; const maxL = photo ? 2 : 4; x.font = `700 ${fs}px Fraunces, Georgia, serif`; let lines = wrap(x, f.headline || m.title, TW, maxL);
  if (!photo && lines.length > 3) { fs = 60; x.font = `700 ${fs}px Fraunces, Georgia, serif`; lines = wrap(x, f.headline || m.title, TW, 4); }
  x.fillStyle = '#fff'; x.save(); x.shadowColor = 'rgba(0,0,0,.35)'; x.shadowBlur = 12; for (const l of lines) { y += fs * 1.12; x.fillText(l, X, y); } x.restore(); y += 28;
  const st = when(m.starts_at), en = when(m.ends_at); const zone = opts.tz || null;
  const dateTxt = st.toLocaleDateString(i18n.lang || 'en', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', ...(zone ? { timeZone: zone } : {}) });
  const tline = timeIn(st, zone, { zone: false }) + ' ' + t('to') + ' ' + timeIn(en, zone) + (zone ? '' : ' · ' + t('your time'));
  const fit = (txt, size, wt) => { let z = size; x.font = `${wt} ${z}px Inter, Arial, sans-serif`; while (z > 18 && x.measureText(txt).width > TW - 60) { z -= 2; x.font = `${wt} ${z}px Inter, Arial, sans-serif`; } return z; };
  const ds = fit(dateTxt, photo ? 32 : 36, 800); const dW = x.measureText(dateTxt).width; const ts = fit(tline, photo ? 26 : 28, 600); const tW = x.measureText(tline).width;
  const dw = Math.min(TW, Math.max(dW, tW) + 60); const bh = photo ? 112 : 130;
  x.fillStyle = LEMON; rrect(x, rtl ? X - dw : X, y, dw, bh, 22); x.fill(); x.fillStyle = '#12210A'; const ix = rtl ? X - 30 : X + 30;
  x.font = `800 ${ds}px Inter, Arial, sans-serif`; x.fillText(dateTxt, ix, y + bh * 0.42); x.font = `600 ${ts}px Inter, Arial, sans-serif`; x.fillText(tline, ix, y + bh * 0.78); y += bh + 34;
  const where = m.mode === 'in_person' ? m.location : (m.link ? t(MODE[m.mode] || 'Online') + ' · ' + t('Join through WDOS') : t(MODE[m.mode] || 'Online'));
  const textW = Math.min(TW, W - 2 * P - 260);      // keep clear of the QR code
  x.fillStyle = '#fff'; x.font = '600 30px Inter, Arial, sans-serif'; for (const l of wrap(x, where, textW, 2)) { x.fillText(l, X, y); y += 42; } y += 10;
  const foot = H - 150;
  if (f.body) { x.font = '400 29px Inter, Arial, sans-serif'; x.fillStyle = 'rgba(255,255,255,.95)'; const room = Math.max(1, Math.floor((foot - y) / 40)); for (const l of wrap(x, f.body, textW, room)) { x.fillText(l, X, y + 8); y += 40; } }
  if (!tpl) { x.fillStyle = LEMON; x.fillRect(0, H - 120, W, 120); }
  x.fillStyle = tpl ? '#fff' : '#12210A'; x.font = '800 32px Inter, Arial, sans-serif'; const FX = rtl ? W - P : P; x.fillText(t('Open WDOS to reply and join'), FX, H - 92);
  x.font = '600 26px Inter, Arial, sans-serif'; x.fillStyle = tpl ? '#C9F27A' : '#12210A'; x.fillText(location.host + (m.id ? '/#/meetings/' + m.id : ''), FX, H - 52);
  const qs = 210; await drawQr(x, location.origin + '/' + (m.id ? '#/meetings/' + m.id : ''), rtl ? P - 20 : W - P - qs + 20, H - qs - 44 - 40, qs, t('Scan to open'));
  return c;
}

/** The flier as it shows on a page, drawn in the reader's language (an uploaded picture sits inside it), with a button to save
 *  it and, when there is one, the original picture. */
export function flierView(m, f, opts = {}) {
  const box = h('div', { class: 'flier' });
  const save = (href, name, label) => h('a', { class: 'btn btn--ghost btn--s', href, download: name }, icon('download', 16), t(label || 'Save the flier'));
  const img = h('img', { class: 'flier__img', alt: (f && f.headline) || m.title }); box.append(img); const foot = h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }); if (!opts.compact) box.append(foot);
  drawFlier(m, f || {}).then((c) => { const url = c.toDataURL('image/jpeg', 0.9); img.src = url;
    foot.replaceChildren(save(url, 'flier-' + m.id + '-' + (i18n.lang || 'en') + '.jpg'), f && f.image ? save(f.image, 'flier-' + m.id + '-original.jpg', 'Original picture') : ''); })
    .catch(() => { box.replaceChildren(f && f.image ? h('img', { class: 'flier__img', src: f.image, alt: '' }) : h('b', { text: (f && f.headline) || m.title })); });
  return box;
}

/** v109.58: the host's browser draws the flier once per language (with the translated words, Nigeria time) and sends the
 *  pictures for the emails; then the invitations go out. onStep tells the screen how far it has got. */
export async function renderEmailFliers(id, m, onStep = () => {}, opts = {}) {
  const LANGS = ['en', 'fr', 'pt', 'sw', 'ar', 'ha', 'yo', 'ig'];
  let d = await api(`/meetings/${id}/flier/langs`);
  if (d.headline || d.text) for (let i = 0; i < 24 && d.ai_on && d.langs.some((l) => !l.ready); i++) { onStep(t('Translating the flier…')); await new Promise((r) => setTimeout(r, 5000)); d = await api(`/meetings/${id}/flier/langs`); }
  const keep = { lang: i18n.lang, dict: i18n.dict, html: document.documentElement.lang };
  try {
    for (const l of LANGS) {
      onStep(tf('Making the flier in {1}…', l.toUpperCase()));
      const tr = d.langs.find((x) => x.lang === l); const f = { image: d.image || '', headline: (tr && tr.headline) || d.headline || m.title, body: (tr && tr.ready ? tr.body : d.text) || '' };
      i18n.lang = l; i18n.dict = l === 'en' ? null : await fetch(`/assets/i18n/${l}.json`).then((r) => r.json()).catch(() => null); document.documentElement.lang = l;
      const c = await drawFlier({ ...m, id }, f, { tz: 'Africa/Lagos' });
      await api(`/meetings/${id}/flier/render/${l}`, { image: c.toDataURL('image/jpeg', 0.86) });
    }
  } finally { i18n.lang = keep.lang; i18n.dict = keep.dict; document.documentElement.lang = keep.html; }
  if (opts.send) { onStep(t('Sending the invitations…')); return api(`/meetings/${id}/send-invites`, {}); }
  return { ok: true };
}

/** A live countdown: "3 days 4 hours 10 minutes", then "Happening now". */
export function bigCountdown(m) {
  const el = h('div', { class: 'cdown', role: 'timer', 'aria-live': 'off' }); const st = when(m.starts_at).getTime(), en = when(m.ends_at).getTime();
  const cell = (n, l) => h('div', { class: 'cdown__cell' }, h('b', { text: String(n).padStart(2, '0') }), h('small', { text: t(l) }));
  let tm; const paint = () => {
    if (el.dataset.on && !el.isConnected) { clearInterval(tm); return; } el.dataset.on = '1'; const now = Date.now();
    if (now >= st && now <= en) { el.replaceChildren(h('b', { class: 'cdown__live', text: t('Happening now') })); return; }
    if (now > en) { el.replaceChildren(h('b', { text: t('Ended') })); clearInterval(tm); return; }
    let d = Math.floor((st - now) / 1000); const days = Math.floor(d / 86400); d -= days * 86400; const hr = Math.floor(d / 3600); d -= hr * 3600; const mi = Math.floor(d / 60); const se = d - mi * 60;
    el.replaceChildren(cell(days, 'days'), cell(hr, 'hours'), cell(mi, 'minutes'), cell(se, 'seconds'));
  };
  tm = setInterval(paint, 1000); paint(); return el;
}

/** One-touch reply: yes, maybe, no. */
export function replyButtons(m, mine, onDone) {
  const row = h('div', { class: 'reply' }, h('b', { text: t('Will you attend?') }));
  const btns = h('div', { class: 'reply__btns' });
  const paint = (cur) => btns.replaceChildren(...[['yes', 'Yes, I will attend'], ['maybe', 'Maybe'], ['no', 'No, I cannot attend']].map(([v, l]) => h('button', { type: 'button', class: 'btn btn--s ' + (cur === v ? '' : 'btn--ghost'), 'aria-pressed': String(cur === v),
    onclick: async () => { try { await api(`/meetings/${m.id}/rsvp`, { answer: v }); paint(v); toast(t('Thank you. Your reply is saved.'), '', 'ok'); if (onDone) onDone(v); } catch (e) { toast(t('Not saved'), e.message, 'bad'); } } }, t(l))));
  paint(mine || ''); row.append(btns); return row;
}

/** The countdown card at the top of a dashboard: the next meetings she is invited to. Nothing shows when there are none. */
export function countdownCard() {
  const slot = h('div', { class: 'cdcards' });
  api('/meetings/countdown?lang=' + encodeURIComponent(i18n.lang || 'en')).then((d) => {
    if (!d.items.length) return;
    slot.replaceChildren(...d.items.map((m) => h('section', { class: 'card cdcard' }, h('div', { class: 'card__body cdcard__body' },
      h('div', { class: 'cdcard__flier' }, m.flier ? flierView(m, m.flier, { compact: true }) : flierView(m, { headline: m.title }, { compact: true })),
      h('div', { class: 'cdcard__main' },
        h('small', { class: 'cdcard__kicker', text: m.kind === 'talk_show' ? t('Talk show coming up') : t('Meeting coming up') }),
        h('h2', { class: 'display', text: (m.flier && m.flier.headline) || m.title }),
        h('p', { class: 'muted', text: whenMine(when(m.starts_at), when(m.ends_at)) }),
        bigCountdown(m),
        replyButtons(m, m.mine),
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, h('a', { class: 'btn btn--ghost btn--s', href: '#/meetings/' + m.id }, icon('calendar', 16), t('Open the meeting')),
          m.link ? h('a', { class: 'btn btn--s', href: m.link, target: '_blank', rel: 'noopener', onclick: () => { api(`/meetings/${m.id}/join`, {}).catch(() => {}); } }, icon('link', 16), t('Join meeting')) : null))))));
  }).catch(() => {});
  return slot;
}

/** Make a phone photo small enough for low data: at most 1080 px wide, JPEG. Returns a data: address. */
export function shrinkImage(file, max = 1080) {
  return new Promise((ok, no) => {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { no(new Error(t('Choose a JPG, PNG or WebP picture for the flier.'))); return; }
    const r = new FileReader(); r.onerror = () => no(new Error(t('That picture could not be read. Choose it again.')));
    r.onload = () => { const i = new Image(); i.onerror = () => no(new Error(t('That picture could not be read. Choose it again.')));
      i.onload = () => { const s = Math.min(1, max / i.width); const c = document.createElement('canvas'); c.width = Math.round(i.width * s); c.height = Math.round(i.height * s); c.getContext('2d').drawImage(i, 0, 0, c.width, c.height); ok(c.toDataURL('image/jpeg', 0.85)); };
      i.src = r.result; };
    r.readAsDataURL(file);
  });
}
export { tf };
