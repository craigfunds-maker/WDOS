/* A course certificate: drawn on a canvas so it can be downloaded as a picture or printed, with the Founder's signature
 * and a QR code that opens the public check page (#/verify/<code>). The check page shows only whether it is genuine. */
import { h, t, tf, icon, card, empty, sk, fmtDate, i18n } from '../ui.js';
import { api } from '../api.js';

const load = (src) => new Promise((ok) => { const im = new Image(); im.onload = () => ok(im); im.onerror = () => ok(null); im.src = src; });

export async function verifyUrl(code) { return `${location.origin}/#/verify/${code}`; }

export async function render(root, params = {}) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(3))); root.append(box);
  let c; try { c = (await api('/certificates/' + encodeURIComponent(params.code || ''))).certificate; } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This certificate was not found', e.message))); return; }
  let title = c.course;
  try { const L = ['ar', 'en', 'fr', 'pt', 'sw'].includes(i18n.lang) ? i18n.lang : 'en'; const d = await (await fetch(`/assets/courses/${L}/index.json`)).json(); const x = d.courses.find((y) => y.no === c.course_no); if (x) title = x.title; } catch { /* English title */ }
  if (!c.course_no) title = t('Volunteer Leadership Personal Development Programme');
  const canvas = await draw(c, title);
  canvas.className = 'cert-canvas'; canvas.setAttribute('role', 'img'); canvas.setAttribute('aria-label', tf('Certificate for {1}: {2}', c.name, title));
  const url = await verifyUrl(c.code);
  const download = () => { const a = document.createElement('a'); a.download = `WODDI-certificate-${c.code}.png`; a.href = canvas.toDataURL('image/png'); a.click(); };
  const share = async () => { try { const blob = await new Promise((r) => canvas.toBlob(r, 'image/png')); const file = new File([blob], `WODDI-certificate-${c.code}.png`, { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [file] })) await navigator.share({ files: [file], title: 'WODDI', text: tf('My WODDI certificate. Check it here: {1}', url) });
    else await navigator.share({ title: 'WODDI', text: tf('My WODDI certificate. Check it here: {1}', url), url }); } catch { /* closed */ } };
  box.replaceChildren(
    h('a', { href: c.course_no ? `#/courses/${c.course_no}` : '#/courses', class: 'btn btn--quiet btn--s', style: 'justify-self:start' }, icon('back', 16), t('Personal development')),
    card(t('Your certificate'), tf('Issued {1}. Certificate number {2}.', fmtDate(c.issued_at), c.code), h('div', { style: 'display:grid;gap:14px' },
      h('div', { class: 'cert-wrap' }, canvas),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' },
        h('button', { class: 'btn btn--lime', type: 'button', onclick: download }, icon('award', 18), t('Download the certificate')),
        navigator.share ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: share }, icon('send', 18), t('Share it')) : null,
        h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { const w = window.open(''); if (!w) return; w.document.write(`<title>${c.code}</title><img src="${canvas.toDataURL('image/png')}" style="width:100%">`); w.document.close(); w.onload = () => w.print(); } }, t('Print')),
        h('a', { class: 'btn btn--quiet', href: `#/verify/${c.code}` }, t('See the public check page'))),
      h('p', { class: 'faint', style: 'margin:0', text: t('Anyone who scans the QR code sees that the certificate is genuine: your name, the course and the date. Nothing else about you is shown.') }))));
}

async function draw(c, title) {
  const W = 1600, H = 1131; const cv = document.createElement('canvas'); cv.width = W; cv.height = H; const g = cv.getContext('2d');
  const serif = getComputedStyle(document.documentElement).getPropertyValue('--font-display').trim() || 'Georgia, serif';
  const sans = getComputedStyle(document.body).fontFamily || 'Arial, sans-serif';
  const rtl = document.documentElement.dir === 'rtl';
  g.fillStyle = '#FFFDF8'; g.fillRect(0, 0, W, H);
  // frame: magenta and lemon green, the WODDI colours
  g.strokeStyle = '#D4006A'; g.lineWidth = 18; g.strokeRect(30, 30, W - 60, H - 60);
  g.strokeStyle = '#7CB518'; g.lineWidth = 4; g.strokeRect(62, 62, W - 124, H - 124);
  g.fillStyle = 'rgba(212,0,106,.06)'; g.beginPath(); g.arc(W - 120, 120, 260, 0, Math.PI * 2); g.fill(); g.fillStyle = 'rgba(124,181,24,.08)'; g.beginPath(); g.arc(120, H - 120, 240, 0, Math.PI * 2); g.fill();
  const [logo, sig, disc] = await Promise.all([load('/assets/logos/woddi.png'), load('/assets/logos/founder-signature.png'), load(c.network === 'WNNN' ? '/assets/logos/wnnn-disc.png' : '/assets/logos/wgmn-disc.png')]);
  if (logo) { const w = 250, hh = (logo.height / logo.width) * w; g.drawImage(logo, (W - w) / 2, 92, w, hh); }
  if (disc && c.network) g.drawImage(disc, W - 230, 96, 120, 120);
  g.textAlign = 'center'; g.direction = rtl ? 'rtl' : 'ltr';
  const text = (s, y, font, color) => { g.font = font; g.fillStyle = color; g.fillText(s, W / 2, y); };
  const fit = (s, max, size, weight, face) => { let f = size; do { g.font = `${weight} ${f}px ${face}`; f -= 2; } while (g.measureText(s).width > max && f > 18); return g.font; };
  text(t('CERTIFICATE OF COMPLETION').toUpperCase(), 330, `700 30px ${sans}`, '#D4006A');
  text(t('This certifies that'), 400, `400 28px ${sans}`, '#4a3a44');
  g.font = fit(c.name, 1200, 76, 700, serif); g.fillStyle = '#2A0F1F'; g.fillText(c.name, W / 2, 495);
  g.strokeStyle = '#7CB518'; g.lineWidth = 3; g.beginPath(); g.moveTo(W / 2 - 360, 525); g.lineTo(W / 2 + 360, 525); g.stroke();
  text(c.course_no ? t('has successfully completed the course') : t('has successfully completed all twelve courses of the'), 585, `400 28px ${sans}`, '#4a3a44');
  g.font = fit(title, 1250, 48, 700, serif); g.fillStyle = '#A80054'; g.fillText(title, W / 2, 655);
  text(t('WODDI Volunteer Leadership Personal Development Courses'), 712, `400 26px ${sans}`, '#4a3a44');
  text(tf('Awarded on {1}', fmtDate(c.issued_at, { day: 'numeric', month: 'long', year: 'numeric' })), 768, `600 26px ${sans}`, '#2A0F1F');
  // signature, bottom left; QR code, bottom right
  if (sig) { const w = 470, hh = (sig.height / sig.width) * w; g.drawImage(sig, 150, H - 140 - hh, w, hh); }
  g.textAlign = 'left'; g.font = `600 22px ${sans}`; g.fillStyle = '#4a3a44'; g.fillText(t('Founder, WODDI'), 160, H - 112);
  const url = await verifyUrl(c.code);
  try {
    const { default: qrcode } = await import('/js/vendor/qrcode.js');
    const q = qrcode(0, 'M'); q.addData(url); q.make(); const n = q.getModuleCount(); const size = 210, cell = size / n, x0 = W - 150 - size, y0 = H - 140 - size;
    g.fillStyle = '#FFFFFF'; g.fillRect(x0 - 12, y0 - 12, size + 24, size + 24); g.fillStyle = '#1d1d22';
    for (let r = 0; r < n; r++) for (let k = 0; k < n; k++) if (q.isDark(r, k)) g.fillRect(x0 + k * cell, y0 + r * cell, Math.ceil(cell), Math.ceil(cell));
    g.textAlign = 'center'; g.font = `600 20px ${sans}`; g.fillStyle = '#2A0F1F'; g.fillText(t('Scan to verify'), x0 + size / 2, H - 104); g.font = `400 19px ${sans}`; g.fillText(c.code, x0 + size / 2, H - 80);
  } catch { g.textAlign = 'center'; g.font = `400 20px ${sans}`; g.fillText(c.code, W - 255, H - 104); }
  g.textAlign = 'center'; g.font = `italic 400 18px ${sans}`; g.fillStyle = '#6b5a64'; g.fillText(t('This certificate records course completion. It does not confer a leadership appointment.'), W / 2, H - 76);
  return cv;
}
