/* WODDI Institute certificate. Same artwork, positions and lettering as the original certificate:
   the template image with the learner's name, course, date, hours, certificate ID, cohort and a real QR code drawn on top. */
(function () {
  'use strict';
  var W = 1536, H = 1024, BASE = (window.WODDI && WODDI.base) || '';
  var TEMPLATE = BASE + '/assets/cert-template.jpg?v=2';
  var C = { magenta: '#D4006A', black: '#111111' };
  var SCRIPT = '"Brush Script MT","Segoe Script","Lucida Handwriting","Kaushan Script",cursive';
  var SANS = '"Plus Jakarta Sans",Inter,Arial,sans-serif', SERIF = '"Times New Roman",Georgia,serif';
  var MASK = { studentName: [492, 468, 560, 98], courseName: [540, 618, 480, 62], date: [1238, 418, 140, 30], duration: [1238, 562, 140, 30], certId: [1218, 697, 180, 27] };
  var tpl = null;
  function template() { return tpl || (tpl = new Promise(function (ok, no) { var i = new Image(); i.onload = function () { ok(i); }; i.onerror = function () { tpl = null; no(new Error('template')); }; i.src = TEMPLATE; })); }
  function fonts() {
    if (!document.fonts || !document.fonts.load) return Promise.resolve();
    return Promise.all([document.fonts.load('400 60px "Kaushan Script"'), document.fonts.load('700 15px "Plus Jakarta Sans"'), document.fonts.load('500 13px "Plus Jakarta Sans"')]).catch(function () {});
  }
  function clean(v, d) { v = String(v == null ? '' : v).replace(/\s+/g, ' ').trim(); return v || d; }
  function mask(ctx, k) { var r = MASK[k]; ctx.save(); ctx.fillStyle = '#fff'; ctx.fillRect(r[0], r[1], r[2], r[3]); ctx.restore(); }
  function fit(ctx, text, family, max, min, width, style) { var s = max; do { ctx.font = (style ? style + ' ' : '') + s + 'px ' + family; if (ctx.measureText(text).width <= width) return s; s -= 2; } while (s >= min); ctx.font = (style ? style + ' ' : '') + min + 'px ' + family; return min; }
  function fmtDate(iso) { var d = iso ? new Date(String(iso).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(iso) ? '' : 'Z')) : new Date(); if (isNaN(d.getTime())) d = new Date(); return ('0' + d.getUTCDate()).slice(-2) + ' ' + ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'][d.getUTCMonth()] + ' ' + d.getUTCFullYear(); }
  function meta(ctx, k, text, x, y) { mask(ctx, k); ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = C.magenta; ctx.font = 'bold 15px ' + SANS; try { ctx.letterSpacing = '1px'; } catch (e) {} ctx.fillText(String(text).toUpperCase(), x, y); ctx.restore(); }

  function draw(canvas, d) {
    return Promise.all([template(), fonts()]).then(function (r) {
      canvas.width = W; canvas.height = H; var ctx = canvas.getContext('2d'); ctx.drawImage(r[0], 0, 0, W, H);
      /* name */
      var name = clean(d.name, 'Student Name'); mask(ctx, 'studentName'); ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic'; ctx.fillStyle = C.magenta; ctx.shadowColor = 'rgba(212,0,106,0.10)'; ctx.shadowBlur = 6;
      fit(ctx, name, SCRIPT, 86, 50, 560, 'italic'); ctx.fillText(name, 768, 545); ctx.restore();
      /* course */
      var course = clean(d.course, 'WODDI Course').toUpperCase(); mask(ctx, 'courseName'); ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = C.black;
      var size = fit(ctx, course, SERIF, 48, 27, 540, 'bold'); ctx.font = 'bold ' + size + 'px ' + SERIF;
      var lines = [], line = ''; course.split(' ').forEach(function (w) { var t = line ? line + ' ' + w : w; if (ctx.measureText(t).width > 540 && line) { lines.push(line); line = w; } else line = t; }); if (line) lines.push(line);
      if (lines.length > 2) { size = 23; ctx.font = 'bold ' + size + 'px ' + SERIF; lines = []; line = ''; course.split(' ').forEach(function (w) { var t = line ? line + ' ' + w : w; if (ctx.measureText(t).width > 560 && line) { lines.push(line); line = w; } else line = t; }); if (line) lines.push(line); lines = lines.slice(0, 2); }
      var y = lines.length > 1 ? 642 : 654; lines.forEach(function (l, i) { ctx.fillText(l, 768, y + i * (size + 4)); }); ctx.restore();
      /* right column */
      meta(ctx, 'date', fmtDate(d.issued), 1307, 433);
      var hrs = clean(d.hours, '60'); meta(ctx, 'duration', /hour/i.test(hrs) ? hrs : hrs + ' HOURS', 1307, 577);
      meta(ctx, 'certId', clean(d.code, 'PENDING'), 1307, 707);
      var cohort = clean(d.cohort, '').replace(/\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/g, function (m) { return m.slice(0, 3); });
      if (cohort) { ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#2B2B2B'; ctx.font = '500 13px ' + SANS; try { ctx.letterSpacing = '1px'; } catch (e) {} ctx.fillText('COHORT', 1307, 770); ctx.fillStyle = C.magenta; var cs = fit(ctx, cohort.toUpperCase(), SANS, 15, 11, 190, 'bold'); ctx.font = 'bold ' + cs + 'px ' + SANS; ctx.fillText(cohort.toUpperCase(), 1307, 793); ctx.strokeStyle = 'rgba(201,168,92,0.75)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(1245, 815); ctx.lineTo(1369, 815); ctx.stroke(); ctx.restore(); }
      /* verification block with a real QR code */
      ctx.save(); ctx.fillStyle = '#fff'; ctx.fillRect(79, 791, 110, 108); ctx.fillRect(196, 822, 276, 68); ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
      var host = (d.verifyHost || location.host).replace(/^www\./, ''), url = d.verify || (location.origin + BASE + '/verify?code=' + encodeURIComponent(d.code || ''));
      if (d.code && window.qrcode) { try { var q = window.qrcode(0, 'L'); q.addData(url); q.make(); var n = q.getModuleCount(), cell = 82 / n; ctx.fillStyle = '#1C1209'; for (var a = 0; a < n; a++) for (var b = 0; b < n; b++) if (q.isDark(a, b)) ctx.fillRect(93 + b * cell, 803.5 + a * cell, Math.ceil(cell), Math.ceil(cell)); } catch (e) {} }
      ctx.fillStyle = '#2b2b2b'; ctx.font = '400 15px ' + SANS; ctx.fillText('Scan the QR code or visit', 201, 840);
      ctx.fillStyle = '#C4006A'; fit(ctx, host + '/verify', SANS, 16, 11, 268, '700'); ctx.fillText(host + '/verify', 201, 861);
      ctx.fillStyle = '#2b2b2b'; ctx.font = '400 13px ' + SANS; ctx.fillText('Certificate ID ' + clean(d.code, ''), 201, 881); ctx.restore();
      return canvas;
    });
  }
  function dataOf(el) { return { name: el.getAttribute('data-name'), course: el.getAttribute('data-course'), issued: el.getAttribute('data-issued'), hours: el.getAttribute('data-hours'), code: el.getAttribute('data-code'), cohort: el.getAttribute('data-cohort'), verify: el.getAttribute('data-verify'), verifyHost: el.getAttribute('data-host') }; }
  function fileName(d, ext) { return 'WODDI-Certificate-' + clean(d.code, 'certificate') + '.' + ext; }
  function save(blobOrUrl, name) { var a = document.createElement('a'); a.download = name; a.href = typeof blobOrUrl === 'string' ? blobOrUrl : URL.createObjectURL(blobOrUrl); document.body.appendChild(a); a.click(); setTimeout(function () { a.remove(); if (typeof blobOrUrl !== 'string') URL.revokeObjectURL(a.href); }, 1500); }
  function mountAll() {
    [].forEach.call(document.querySelectorAll('canvas[data-cert]'), function (cv) {
      if (cv.__done) return; cv.__done = true;
      draw(cv, dataOf(cv)).then(function () { cv.classList.add('ready'); }).catch(function () { var p = document.createElement('p'); p.className = 'mut sm'; p.textContent = 'The certificate picture could not load. Check your connection and reload the page.'; cv.replaceWith(p); });
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cert-png],[data-cert-pdf],[data-cert-share]'); if (!b) return;
    var cv = document.getElementById(b.getAttribute('data-for')); if (!cv || !cv.classList.contains('ready')) return; var d = dataOf(cv);
    if (b.hasAttribute('data-cert-png')) { cv.toBlob(function (bl) { save(bl, fileName(d, 'png')); }, 'image/png'); return; }
    if (b.hasAttribute('data-cert-pdf')) {
      var go = function () { var doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'pt', format: [W, H] }); doc.addImage(cv.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, W, H); doc.save(fileName(d, 'pdf')); };
      if (window.jspdf) return go(); var old = b.innerHTML; b.disabled = true; b.textContent = 'Preparing...';
      var s = document.createElement('script'); s.src = BASE + '/assets/vendor/jspdf.umd.min.js'; s.onload = function () { b.disabled = false; b.innerHTML = old; go(); }; s.onerror = function () { b.disabled = false; b.innerHTML = old; cv.toBlob(function (bl) { save(bl, fileName(d, 'png')); }); }; document.head.appendChild(s); return;
    }
    cv.toBlob(function (bl) { var f = new File([bl], fileName(d, 'png'), { type: 'image/png' }), text = 'I earned a WODDI Institute certificate in ' + d.course + '. Verify it here: ' + d.verify;
      if (navigator.canShare && navigator.canShare({ files: [f] })) navigator.share({ files: [f], title: 'My WODDI Institute certificate', text: text }).catch(function () {});
      else window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener'); }, 'image/png');
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountAll); else mountAll();
  window.woddiCertificate = { draw: draw };
})();
