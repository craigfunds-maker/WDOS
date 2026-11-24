/* v109.25: a small PDF writer for tables (A4 landscape, Helvetica and Courier), made on this computer.
 * Used for the leader logins sheet, so the temporary passwords never leave the page that received them.
 * Text outside Latin-1 is simplified (accents kept where Latin-1 has them, others reduced to the base letter). */
const W = 842, H = 595, M = 36;
const latin = (s) => String(s ?? '').normalize('NFC').replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/[–—]/g, '-').replace(/…/g, '...')
  .split('').map((c) => c.charCodeAt(0) < 256 ? c : (c.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\x00-\xff]/g, '?'))).join('');
const esc = (s) => latin(s).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
// Helvetica and Helvetica-Bold widths (per 1000 units) for characters 32 to 255, so text wraps and right-aligns where it really ends
const HV = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,761,222,556,333,1000,556,556,333,1000,667,333,1000,761,611,761,761,222,222,333,333,350,556,1000,333,1000,500,333,944,761,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
const HB = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,761,278,556,500,1000,556,556,333,1000,667,333,1000,761,611,761,761,278,278,500,500,350,556,1000,333,1000,556,333,944,761,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];
const width = (s, size, mono, bold) => { const t = latin(s); if (mono) return t.length * size * 0.6; const tb = bold ? HB : HV; let n = 0; for (const c of t) { const k = c.charCodeAt(0) - 32; n += k >= 0 && k < tb.length ? tb[k] : 556; } return n * size / 1000; };
function wrap(text, size, max, mono, bold) {
  const out = []; let line = '';
  for (const word of latin(text).split(/\s+/)) {
    const tryL = line ? line + ' ' + word : word;
    if (width(tryL, size, mono, bold) <= max) { line = tryL; continue; }
    if (line) out.push(line);
    let w = word; while (width(w, size, mono, bold) > max) { const n = Math.max(1, Math.floor(max / (size * 0.6))); out.push(w.slice(0, n)); w = w.slice(n); }
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

/* v109.51: the PDF for "logins for a whole network": a cover with the totals and how to sign in, a table of countries, then each
 * country in turn, its leaders grouped by level (country, state or region, LGA, ward or cluster, chapter, envoys). A4 landscape.
 * m = { network, title, made, host, note, people: [{ country_name, level, name, position, sign_in_with, password, status }] } */
const MAG = '0.831 0 0.416', LIME = '0.486 0.71 0.094', INK = '0.165 0.078 0.125', GREY = '0.42 0.35 0.39';
const LEVEL_NAMES = [['country', 'Country leadership'], ['state_region', 'State or region'], ['lga', 'LGA, district or city'], ['community_cluster', 'Ward, community or cluster'], ['chapter', 'Chapter'], ['envoy', 'WODDI Envoys']];
export function networkLoginsPdf(m) {
  const pages = []; let ops = [], y = 0;
  const txt = (x, yy, s, size, font = 'F1', rgb = INK) => ops.push(`BT ${rgb} rg /${font} ${size} Tf ${x.toFixed(1)} ${yy.toFixed(1)} Td (${esc(s)}) Tj ET`);
  const rtxt = (xr, yy, s, size, font = 'F1', rgb = INK) => txt(xr - width(s, size, font === 'F3', font === 'F2'), yy, s, size, font, rgb);
  const rect = (x, yy, w, hh, rgb) => ops.push(`${rgb} rg ${x.toFixed(1)} ${yy.toFixed(1)} ${w.toFixed(1)} ${hh.toFixed(1)} re f`);
  const hline = (x1, x2, yy, rgb = '0.9 0.84 0.88') => ops.push(`${rgb} RG 0.5 w ${x1} ${yy.toFixed(1)} m ${x2} ${yy.toFixed(1)} l S`);
  const L = M, R = W - M, BOTTOM = M + 22;
  const newPage = () => { if (ops.length) pages.push(ops); ops = []; rect(0, H - 7, W * 0.72, 7, MAG); rect(W * 0.72, H - 7, W * 0.28, 7, LIME); y = H - 30; };
  const room = (need) => y - need >= BOTTOM;
  const P = m.people; const pw = P.filter((p) => p.password).length;
  const countries = []; const byC = new Map();
  P.forEach((p) => { if (!byC.has(p.country_name)) { byC.set(p.country_name, []); countries.push(p.country_name); } byC.get(p.country_name).push(p); });
  // ---- cover
  newPage();
  txt(L, y, 'WODDI  ·  WDOS', 9, 'F2', MAG); y -= 30;
  txt(L, y, m.title, 24, 'F2'); y -= 20;
  txt(L, y, `${m.scope || 'All countries  ·  every leadership level'}  ·  ${m.made}`, 10, 'F1', GREY); y -= 30;
  const stats = [['Leaders listed', P.length], ['Countries', countries.length], ['New logins in this PDF', pw], ['Already signed in', P.filter((p) => p.status === 'active' || p.status === 'google').length]];
  const bw = (R - L - 3 * 12) / 4;
  stats.forEach(([k, v], i) => { const x = L + i * (bw + 12); rect(x, y - 52, bw, 58, i === 2 ? MAG : '0.985 0.95 0.97'); txt(x + 14, y - 18, String(v), 22, 'F2', i === 2 ? '1 1 1' : MAG); txt(x + 14, y - 40, k, 9.5, 'F2', i === 2 ? '1 1 1' : INK); });
  y -= 78;
  // how to sign in, beside the confidentiality note
  const half = (R - L - 16) / 2;
  rect(L, y - 96, half, 102, '0.96 0.98 0.93'); rect(L, y - 96, 4, 102, LIME);
  txt(L + 16, y - 14, 'How each leader signs in', 11, 'F2');
  [`1.  Go to ${m.host}`, '2.  Type the email or phone number on her line.', '3.  Type the temporary password exactly. Capital letters matter.', '4.  WDOS then asks her to choose her own password.']
    .forEach((s, i) => txt(L + 16, y - 34 - i * 15, s, 9.5));
  const nx = L + half + 16;
  rect(nx, y - 96, half, 102, '0.99 0.94 0.95'); rect(nx, y - 96, 4, 102, MAG);
  txt(nx + 16, y - 14, 'Confidential', 11, 'F2', MAG);
  wrap(m.note, 9.5, half - 30).slice(0, 5).forEach((s, i) => txt(nx + 16, y - 34 - i * 14, s, 9.5));
  y -= 122;
  // countries table
  const cc = [['Country', 0, 'l'], ['Leaders', 0.40, 'r'], ['New logins', 0.52, 'r'], ['Already signed in', 0.67, 'r'], ['Login sent before, not used', 0.86, 'r'], ['No email or phone', 1, 'r']];
  const cx = (f) => L + f * (R - L);
  const chead = () => { rect(L, y - 6, R - L, 20, INK); cc.forEach(([l, f, a], i) => a === 'l' ? txt(L + 8, y, l, 9, 'F2', '1 1 1') : rtxt(cx(f) - 8, y, l, 9, 'F2', '1 1 1')); y -= 20; };
  txt(L, y, 'Leaders by country', 13, 'F2'); y -= 22; chead();
  const count = (list, f) => list.filter(f).length;
  const crow = (name, list, bold) => {
    if (!room(18)) { newPage(); chead(); }
    const v = [name, list.length, count(list, (p) => p.password), count(list, (p) => p.status === 'active' || p.status === 'google'), count(list, (p) => p.status === 'pending' && !p.password), count(list, (p) => p.status === 'cannot')];
    if (bold) rect(L, y - 6, R - L, 18, '0.985 0.95 0.97');
    cc.forEach(([, f, a], i) => a === 'l' ? txt(L + 8, y, String(v[i]), 9.5, bold ? 'F2' : 'F1') : rtxt(cx(f) - 8, y, String(v[i]), 9.5, bold || i === 2 ? 'F2' : 'F1', i === 2 && v[i] ? MAG : INK));
    y -= 18; hline(L, R, y + 12);
  };
  countries.forEach((c) => crow(c, byC.get(c), false)); crow('All countries', P, true);
  // ---- each country
  const cols = [['No.', 4], ['Name', 19], ['Designation', 33], ['Sign in with', 25], ['Temporary password', 15]];
  const tot = cols.reduce((a, c) => a + c[1], 0); const xs = []; let acc = L; cols.forEach(([, w]) => { xs.push(acc); acc += w / tot * (R - L); });
  const cw = (i) => cols[i][1] / tot * (R - L) - 10;
  const STATUS = { active: 'Already signed in. Uses her own password.', google: 'Signs in with Google.', pending: 'Login sent before. Not used yet.', cannot: 'Add an email or phone number first.', notdone: 'Not done yet. Run it again.' };
  countries.forEach((c) => {
    const list = byC.get(c); let n = 0;
    const bar = (more) => {
      rect(L, y - 10, R - L, 26, INK); txt(L + 10, y - 1, c + (more ? '  (continued)' : ''), 13, 'F2', '1 1 1');
      rtxt(R - 10, y - 1, `${list.length} leaders  ·  ${count(list, (p) => p.password)} new logins`, 9.5, 'F2', '1 1 1'); y -= 30;
      rect(L, y - 6, R - L, 18, '0.985 0.95 0.97'); cols.forEach(([l], i) => txt(xs[i] + 5, y, l, 8.5, 'F2', MAG)); y -= 20;
    };
    newPage(); bar(false);
    let group = null;
    list.forEach((p) => {
      const g = (LEVEL_NAMES.find(([k]) => k === p.level) || [, 'Other'])[1];
      const cells = [String(++n), p.name, p.position, p.sign_in_with || '-', p.password || STATUS[p.status] || ''].map((v, i) => wrap(v, i === 4 && !p.password ? 8 : (i === 4 ? 10 : 9), cw(i), i === 4 && !!p.password, i === 1));
      const hgt = Math.max(...cells.map((x) => x.length)) * 11 + 7;
      if (g !== group) { if (!room(hgt + 20)) { newPage(); bar(true); } rect(L, y - 5, R - L, 16, '0.95 0.975 0.91'); txt(L + 6, y, g, 9, 'F2', '0.29 0.45 0.04'); y -= 18; group = g; }
      if (!room(hgt)) { newPage(); bar(true); }
      cells.forEach((lines, i) => lines.forEach((l, k) => {
        const font = i === 4 ? (p.password ? 'F3' : 'F4') : i === 1 ? 'F2' : 'F1';
        txt(xs[i] + 5, y - k * 11, l, i === 4 && !p.password ? 8 : (i === 4 ? 10 : 9), font, i === 4 && !p.password ? GREY : INK);
      }));
      y -= hgt; hline(L, R, y + 8);
    });
  });
  pages.push(ops);
  // footers, now that the page count is known
  const texts = pages.map((o, i) => { ops = o; hline(L, R, M + 12, '0.88 0.8 0.85');
    txt(L, M, `WDOS logins  ·  ${m.network}  ·  CONFIDENTIAL  ·  ${m.made}`, 8, 'F1', GREY); rtxt(R, M, `Page ${i + 1} of ${pages.length}`, 8, 'F1', GREY); return ops.join('\n'); });
  return assemble(texts);
}
function assemble(pages) {
  const objs = []; const add = (s) => { objs.push(s); return objs.length; };
  const fonts = ['Helvetica', 'Helvetica-Bold', 'Courier-Bold', 'Helvetica-Oblique'].map((f) => add(`<< /Type /Font /Subtype /Type1 /BaseFont /${f} /Encoding /WinAnsiEncoding >>`));
  const pagesId = objs.length + 1 + pages.length * 2; const kids = [];
  pages.forEach((c) => { const cid = add(`<< /Length ${c.length} >>\nstream\n${c}\nendstream`); kids.push(add(`<< /Type /Page /Parent ${pagesId} 0 R /MediaBox [0 0 ${W} ${H}] /Contents ${cid} 0 R /Resources << /Font << /F1 ${fonts[0]} 0 R /F2 ${fonts[1]} 0 R /F3 ${fonts[2]} 0 R /F4 ${fonts[3]} 0 R >> >> >>`)); });
  add(`<< /Type /Pages /Kids [${kids.map((k) => k + ' 0 R').join(' ')}] /Count ${kids.length} >>`);
  const cat = add(`<< /Type /Catalog /Pages ${pagesId} 0 R >>`);
  let out = '%PDF-1.4\n%\xe2\xe3\xcf\xd3\n'; const offs = [];
  objs.forEach((s, i) => { offs.push(out.length); out += `${i + 1} 0 obj\n${s}\nendobj\n`; });
  const xref = out.length;
  out += `xref\n0 ${objs.length + 1}\n0000000000 65535 f \n` + offs.map((n) => String(n).padStart(10, '0') + ' 00000 n \n').join('') + `trailer\n<< /Size ${objs.length + 1} /Root ${cat} 0 R >>\nstartxref\n${xref}\n%%EOF`;
  const bytes = new Uint8Array(out.length); for (let i = 0; i < out.length; i++) bytes[i] = out.charCodeAt(i) & 0xff;
  return new Blob([bytes], { type: 'application/pdf' });
}
