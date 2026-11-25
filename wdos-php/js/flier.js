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

/** Draw the flier for a meeting on a 1080 x 1350 canvas (the shape social apps show best). Returns the canvas. */
export async function drawFlier(m, f = {}) {
  const W = 1080, H = 1350, P = 72; const c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
  try { await Promise.all([document.fonts.load('700 70px Fraunces'), document.fonts.load('600 34px Inter')]); } catch { /* system fonts */ }
  const rtl = (i18n.lang || 'en') === 'ar'; x.direction = rtl ? 'rtl' : 'ltr'; x.textAlign = rtl ? 'right' : 'left'; const X = rtl ? W - P : P; const TW = W - 2 * P;
  const g = x.createLinearGradient(0, 0, W, H); g.addColorStop(0, MAGENTA); g.addColorStop(0.55, '#8E0A4A'); g.addColorStop(1, PLUM); x.fillStyle = g; x.fillRect(0, 0, W, H);
  x.globalAlpha = 0.10; x.fillStyle = '#fff'; x.beginPath(); x.arc(W - 120, 160, 260, 0, Math.PI * 2); x.fill(); x.beginPath(); x.arc(90, H - 260, 200, 0, Math.PI * 2); x.fill(); x.globalAlpha = 1;
  const lg = await logo(); let y = P;
  if (lg) { const s = 132; x.save(); x.beginPath(); x.arc((rtl ? W - P - s / 2 : P + s / 2), y + s / 2, s / 2, 0, Math.PI * 2); x.fillStyle = '#fff'; x.fill(); x.clip(); x.drawImage(lg, rtl ? W - P - s : P, y, s, s); x.restore(); }
  const tx = rtl ? W - P - 160 : P + 160;
  x.fillStyle = LEMON; x.font = '800 26px Inter, Arial, sans-serif'; x.fillText(t('No Woman Is Left Out').toUpperCase(), tx, y + 54);
  x.fillStyle = '#fff'; x.font = '600 30px Inter, Arial, sans-serif'; x.fillText('WODDI', tx, y + 98);
  y += 190;
  // the kind of event
  x.font = '800 26px Inter, Arial, sans-serif'; const kind = t(KIND[m.kind] || 'Meeting').toUpperCase(); const kw = x.measureText(kind).width + 44;
  x.fillStyle = 'rgba(255,255,255,.16)'; rrect(x, rtl ? W - P - kw : P, y, kw, 50, 25); x.fill(); x.fillStyle = '#fff'; x.fillText(kind, rtl ? W - P - 22 : P + 22, y + 35); y += 92;
  // the headline
  let fs = 76; x.font = `700 ${fs}px Fraunces, Georgia, serif`; let lines = wrap(x, f.headline || m.title, TW, 4);
  if (lines.length > 3) { fs = 62; x.font = `700 ${fs}px Fraunces, Georgia, serif`; lines = wrap(x, f.headline || m.title, TW, 4); }
  const lh = fs * 1.14; x.fillStyle = '#fff'; for (const l of lines) { y += lh; x.fillText(l, X, y); } y += 40;
  // when, in the phone's own time
  const st = when(m.starts_at), en = when(m.ends_at);
  const dateTxt = st.toLocaleDateString(i18n.lang || 'en', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  const timeTxt = timeIn(st, null, { zone: false }) + ' ' + t('to') + ' ' + timeIn(en, null);
  x.fillStyle = LEMON; rrect(x, P, y, TW, 150, 26); x.fill(); x.fillStyle = '#12210A';
  x.font = '800 40px Inter, Arial, sans-serif'; x.fillText(dateTxt, X + (rtl ? -34 : 34), y + 62); x.font = '600 32px Inter, Arial, sans-serif'; x.fillText(timeTxt + ' · ' + t('your time'), X + (rtl ? -34 : 34), y + 114); y += 190;
  // where
  const where = m.mode === 'in_person' ? m.location : (m.link ? t(MODE[m.mode] || 'Online') + ' · ' + m.link.replace(/^https?:\/\//, '').slice(0, 44) : t(MODE[m.mode] || 'Online'));
  x.fillStyle = '#fff'; x.font = '600 32px Inter, Arial, sans-serif'; for (const l of wrap(x, where, TW, 2)) { x.fillText(l, X, y); y += 44; } y += 18;
  // the message
  if (f.body) { x.font = '400 32px Inter, Arial, sans-serif'; x.fillStyle = 'rgba(255,255,255,.92)'; const room = Math.max(1, Math.floor((H - 170 - y) / 46)); for (const l of wrap(x, f.body, TW, room)) { x.fillText(l, X, y + 10); y += 46; } }
  // the foot
  x.fillStyle = LEMON; x.fillRect(0, H - 120, W, 120); x.fillStyle = '#12210A'; x.font = '800 32px Inter, Arial, sans-serif';
  x.fillText(t('Open WDOS to reply and join'), X, H - 68); x.font = '600 26px Inter, Arial, sans-serif'; x.fillText(location.host, X, H - 30);
  return c;
}

/** The flier as it shows on a page: the uploaded picture, or the drawn one, with a button to save it. */
export function flierView(m, f, opts = {}) {
  const box = h('div', { class: 'flier' });
  const save = (href, name) => h('a', { class: 'btn btn--ghost btn--s', href, download: name }, icon('download', 16), t('Save the flier'));
  if (f && f.image) {
    box.append(h('img', { class: 'flier__img', src: f.image, alt: f.headline || m.title, loading: 'lazy' }));
    if (f.headline || f.body) box.append(h('div', { class: 'flier__words', dir: i18n.lang === 'ar' ? 'rtl' : 'auto' }, f.headline ? h('b', { text: f.headline }) : null, f.body ? h('p', { text: f.body }) : null));
    if (!opts.compact) box.append(h('div', {}, save(f.image, 'flier-' + m.id + '.' + (f.image.split('?')[0].split('.').pop() || 'jpg'))));
  } else {
    const img = h('img', { class: 'flier__img', alt: (f && f.headline) || m.title }); box.append(img); const foot = h('div'); if (!opts.compact) box.append(foot);
    drawFlier(m, f || {}).then((c) => { const url = c.toDataURL('image/jpeg', 0.9); img.src = url; foot.replaceChildren(save(url, 'flier-' + m.id + '.jpg')); }).catch(() => { box.replaceChildren(h('b', { text: (f && f.headline) || m.title })); });
  }
  return box;
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
