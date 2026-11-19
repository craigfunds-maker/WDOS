/* WODDI Institute certificate, drawn on the original template exactly as the previous site drew it:
   same template image, same positions, fonts and colours. Needs qrcode.js for the verification QR. */
(function () {
  'use strict';
  var W = 1536, H = 1024, MAG = '#D4006A', BLACK = '#111111';
  var MASK = { studentName: [492, 468, 560, 98], courseName: [540, 618, 480, 62], date: [1238, 418, 140, 30], duration: [1238, 562, 140, 30], certId: [1218, 697, 180, 27] };
  var tpl = null;
  function template(src) { return tpl || (tpl = new Promise(function (ok, no) { var i = new Image(); i.onload = function () { ok(i); }; i.onerror = function () { tpl = null; no(new Error('template')); }; i.src = src; })); }
  function clean(v, f) { return String(v || f || '').trim().replace(/\s+/g, ' ').replace(/[–—]/g, '-'); }
  function mask(ctx, k) { var r = MASK[k]; ctx.save(); ctx.fillStyle = '#FFFFFF'; ctx.fillRect(r[0], r[1], r[2], r[3]); ctx.restore(); }
  function fit(ctx, text, family, max, min, width, style) { var s = max, p = style ? style + ' ' : ''; do { ctx.font = p + s + 'px ' + family; if (ctx.measureText(text).width <= width) return s; s -= 2; } while (s >= min); return min; }
  function fmtDate(iso) { var d = iso ? new Date(String(iso).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(iso) ? '' : 'Z')) : new Date(); if (isNaN(d.getTime())) d = new Date(); var m = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']; return String(d.getUTCDate()).padStart(2, '0') + ' ' + m[d.getUTCMonth()] + ' ' + d.getUTCFullYear(); }
  function meta(ctx, k, text, x, y) { mask(ctx, k); ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = MAG; ctx.font = 'bold 15px "Plus Jakarta Sans",Arial,sans-serif'; try { ctx.letterSpacing = '1px'; } catch (e) {} ctx.fillText(clean(text).toUpperCase(), x, y); ctx.restore(); }

  window.woddiCertificate = function (canvas, o) {
    canvas.width = W; canvas.height = H; var ctx = canvas.getContext('2d');
    return template(o.template).then(function (img) {
      ctx.drawImage(img, 0, 0, W, H);
      /* learner name */
      var name = clean(o.name, 'Student Name'); mask(ctx, 'studentName'); ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic'; ctx.fillStyle = MAG; ctx.shadowColor = 'rgba(212,0,106,0.10)'; ctx.shadowBlur = 6;
      fit(ctx, name, '"Brush Script MT","Segoe Script","Lucida Handwriting",cursive', 86, 50, 560, 'italic'); ctx.fillText(name, 768, 545); ctx.restore();
      /* course name, up to two lines */
      var course = clean(o.course, 'WODDI Course').toUpperCase(); mask(ctx, 'courseName'); ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = BLACK;
      var fam = '"Times New Roman",Georgia,serif', size = fit(ctx, course, fam, 48, 27, 540, 'bold'); ctx.font = 'bold ' + size + 'px ' + fam;
      var lines = [], line = ''; course.split(' ').forEach(function (w) { var c = line ? line + ' ' + w : w; if (ctx.measureText(c).width > 540 && line) { lines.push(line); line = w; } else line = c; }); if (line) lines.push(line); lines = lines.slice(0, 2);
      var y = lines.length > 1 ? 642 : 654; lines.forEach(function (l, i) { ctx.fillText(l, 768, y + i * (size + 4)); }); ctx.restore();
      /* right column */
      meta(ctx, 'date', fmtDate(o.issued), 1307, 433);
      var dur = clean(o.hours, '60'); meta(ctx, 'duration', /hour/i.test(dur) ? dur : dur + ' HOURS', 1307, 577);
      meta(ctx, 'certId', o.code || 'PENDING', 1307, 707);
      var co = clean(o.cohort, '').replace(/\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/g, function (m) { return m.slice(0, 3); });
      if (co) { ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#2B2B2B'; ctx.font = '500 13px "Plus Jakarta Sans",Arial,sans-serif'; try { ctx.letterSpacing = '1px'; } catch (e) {} ctx.fillText('COHORT', 1307, 770); ctx.fillStyle = MAG; var cs = fit(ctx, co.toUpperCase(), '"Plus Jakarta Sans",Arial,sans-serif', 15, 11, 190, 'bold'); ctx.font = 'bold ' + cs + 'px "Plus Jakarta Sans",Arial,sans-serif'; ctx.fillText(co.toUpperCase(), 1307, 793); ctx.strokeStyle = 'rgba(201,168,92,0.75)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(1245, 815); ctx.lineTo(1369, 815); ctx.stroke(); ctx.restore(); }
      /* verification block with a real QR code */
      var SANS = '"Josefin Sans","Montserrat",Inter,"Plus Jakarta Sans",Arial,sans-serif';
      ctx.save(); ctx.fillStyle = '#ffffff'; ctx.fillRect(79, 791, 110, 108); ctx.fillRect(197, 823, 275, 68); ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
      if (o.code && window.qrcode) {
        var qr = window.qrcode(0, 'L'); qr.addData(o.verify); qr.make(); var n = qr.getModuleCount(), cell = 82 / n, x0 = 93, y0 = 803.5; ctx.fillStyle = '#1C1209';
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (qr.isDark(r, c)) ctx.fillRect(x0 + c * cell, y0 + r * cell, Math.ceil(cell), Math.ceil(cell));
      }
      ctx.strokeStyle = '#3a3a3a'; ctx.lineWidth = 1.5; ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(85.5, 796.5, 97, 96, 7); else ctx.rect(85.5, 796.5, 97, 96); ctx.stroke();
      ctx.fillStyle = '#2b2b2b'; ctx.font = '400 15px ' + SANS; ctx.fillText('Scan the QR code or visit', 201, 840);
      ctx.fillStyle = '#C4006A'; ctx.font = '700 16px ' + SANS; ctx.fillText(o.verifyLabel, 201, 861);
      ctx.fillStyle = '#2b2b2b'; ctx.font = '400 13px ' + SANS; ctx.fillText('Certificate ID ' + (o.code || ''), 201, 881); ctx.restore();
      return canvas;
    });
  };
  function file(o) { return 'WODDI-certificate-' + (o.code || 'copy'); }
  document.querySelectorAll('canvas[data-cert]').forEach(function (cv) {
    var o = JSON.parse(cv.getAttribute('data-cert')), box = cv.closest('[data-certbox]');
    var ready = window.woddiCertificate(cv, o).catch(function () { cv.insertAdjacentHTML('afterend', '<p class="mut sm">The certificate picture could not load. Check your connection and reload.</p>'); });
    if (!box) return;
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-cert-png],[data-cert-pdf]'); if (!b) return;
      ready.then(function () {
        if (b.hasAttribute('data-cert-png')) { cv.toBlob(function (blob) { var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = file(o) + '.png'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 800); }, 'image/png'); return; }
        var w = window.open('', '_blank'); if (!w) { alert('Allow pop-ups for this site to save the PDF.'); return; }
        w.document.write('<!doctype html><title>' + file(o) + '</title><style>@page{size:landscape;margin:0}html,body{margin:0;background:#fff}img{width:100vw;height:100vh;object-fit:contain;display:block}</style><img src="' + cv.toDataURL('image/jpeg', 0.95) + '" onload="setTimeout(function(){print()},250)">'); w.document.close();
      });
    });
  });
})();
