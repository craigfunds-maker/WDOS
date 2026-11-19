/* v109.25: a small PDF writer for tables (A4 landscape, Helvetica and Courier), made on this computer.
 * Used for the leader logins sheet, so the temporary passwords never leave the page that received them.
 * Text outside Latin-1 is simplified (accents kept where Latin-1 has them, others reduced to the base letter). */
const W = 842, H = 595, M = 36;
const latin = (s) => String(s ?? '').normalize('NFC').replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/[–—]/g, '-').replace(/…/g, '...')
  .split('').map((c) => c.charCodeAt(0) < 256 ? c : (c.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\x00-\xff]/g, '?'))).join('');
const esc = (s) => latin(s).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
const width = (s, size, mono) => latin(s).length * size * (mono ? 0.6 : 0.53);
function wrap(text, size, max, mono) {
  const out = []; let line = '';
  for (const word of latin(text).split(/\s+/)) {
    const tryL = line ? line + ' ' + word : word;
    if (width(tryL, size, mono) <= max) { line = tryL; continue; }
    if (line) out.push(line);
    let w = word; while (width(w, size, mono) > max) { const n = Math.max(1, Math.floor(max / (size * (mono ? 0.6 : 0.53)))); out.push(w.slice(0, n)); w = w.slice(n); }
    line = w;
  }
  if (line) out.push(line);
  return out.length ? out : [''];
}
/** table({ title, subtitle, note, footer, columns: [{ label, width, mono, bold }], rows: [[...]] }) → Blob (application/pdf) */
export function tablePdf(o) {
  const pages = []; let ops = [], y = 0, page = 0;
  const txt = (x, yy, s, size, font = 'F1', rgb = '0 0 0') => ops.push(`BT ${rgb} rg /${font} ${size} Tf ${x.toFixed(1)} ${yy.toFixed(1)} Td (${esc(s)}) Tj ET`);
  const line = (x1, y1, x2, y2, rgb = '0.85 0.85 0.85') => ops.push(`${rgb} RG 0.6 w ${x1} ${y1} m ${x2} ${y2} l S`);
  const rect = (x, yy, w, hh, rgb) => ops.push(`${rgb} rg ${x} ${yy} ${w} ${hh} re f`);
  const cols = o.columns; const total = cols.reduce((a, c) => a + c.width, 0); const scale = (W - 2 * M) / total;
  const xs = []; let acc = M; cols.forEach((c) => { xs.push(acc); acc += c.width * scale; });
  const head = () => {
    page++; ops = []; y = H - M;
    rect(0, H - 8, W, 8, '0.831 0 0.416');
    txt(M, y - 6, o.title, 15, 'F2', '0.831 0 0.416'); y -= 24;
    if (o.subtitle) { txt(M, y, o.subtitle, 9, 'F1', '0.3 0.3 0.3'); y -= 13; }
    if (o.note) { for (const l of wrap(o.note, 9, W - 2 * M)) { txt(M, y, l, 9, 'F2', '0.5 0.1 0.1'); y -= 12; } }
    y -= 6; rect(M, y - 6, W - 2 * M, 18, '0.95 0.93 0.95');
    cols.forEach((c, i) => txt(xs[i] + 4, y, c.label, 8.5, 'F2', '0.2 0.2 0.2')); y -= 16;
  };
  const foot = () => {
    line(M, M + 14, W - M, M + 14);
    txt(M, M + 2, o.footer || '', 8, 'F1', '0.35 0.35 0.35');
    txt(W - M - 60, M + 2, 'Page ' + page, 8, 'F1', '0.35 0.35 0.35');
    pages.push(ops.join('\n'));
  };
  head();
  o.rows.forEach((r, ri) => {
    const cells = r.map((v, i) => wrap(v, 9, cols[i].width * scale - 8, cols[i].mono));
    const hgt = Math.max(...cells.map((c) => c.length)) * 11 + 6;
    if (y - hgt < M + 24) { foot(); head(); }
    if (ri % 2) rect(M, y - hgt + 9, W - 2 * M, hgt, '0.985 0.975 0.985');
    cells.forEach((c, i) => c.forEach((l, k) => txt(xs[i] + 4, y - k * 11, l, 9, cols[i].mono ? 'F3' : cols[i].bold ? 'F2' : 'F1')));
    y -= hgt; line(M, y + 9, W - M, y + 9);
  });
  foot();
  // assemble
  const objs = []; const add = (s) => { objs.push(s); return objs.length; };
  const fonts = [add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'), add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'), add('<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>')];
  const pagesId = objs.length + 1 + pages.length * 2; const kids = [];
  pages.forEach((c) => { const cid = add(`<< /Length ${c.length} >>\nstream\n${c}\nendstream`); kids.push(add(`<< /Type /Page /Parent ${pagesId} 0 R /MediaBox [0 0 ${W} ${H}] /Contents ${cid} 0 R /Resources << /Font << /F1 ${fonts[0]} 0 R /F2 ${fonts[1]} 0 R /F3 ${fonts[2]} 0 R >> >> >>`)); });
  add(`<< /Type /Pages /Kids [${kids.map((k) => k + ' 0 R').join(' ')}] /Count ${kids.length} >>`);
  const cat = add(`<< /Type /Catalog /Pages ${pagesId} 0 R >>`);
  let out = '%PDF-1.4\n%\xe2\xe3\xcf\xd3\n'; const offs = [];
  objs.forEach((s, i) => { offs.push(out.length); out += `${i + 1} 0 obj\n${s}\nendobj\n`; });
  const xref = out.length;
  out += `xref\n0 ${objs.length + 1}\n0000000000 65535 f \n` + offs.map((n) => String(n).padStart(10, '0') + ' 00000 n \n').join('') + `trailer\n<< /Size ${objs.length + 1} /Root ${cat} 0 R >>\nstartxref\n${xref}\n%%EOF`;
  const bytes = new Uint8Array(out.length); for (let i = 0; i < out.length; i++) bytes[i] = out.charCodeAt(i) & 0xff;
  return new Blob([bytes], { type: 'application/pdf' });
}
export function download(blob, name) {
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.append(a); a.click();
  setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 4000);
}
