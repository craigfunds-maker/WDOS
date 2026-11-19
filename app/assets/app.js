/* WODDI Institute front-end helpers: sheets, theme, push notifications, small async actions. */
(function () {
  'use strict';
  var W = window.WODDI || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function api(action, data) {
    var body = new URLSearchParams(data || {}); body.set('_csrf', W.csrf);
    return fetch(W.base + '/api/' + action, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); });
  }
  window.woddiApi = api;
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-open]'); if (t) { var el = $(t.getAttribute('data-open')); if (el) el.hidden = false; return; }
    t = e.target.closest('[data-close]'); if (t) { var w = t.closest('.sheetwrap'); if (w) w.hidden = true; return; }
    t = e.target.closest('[data-theme]'); if (t) { var d = document.documentElement.classList.toggle('dark'); document.cookie = 'theme=' + (d ? 'dark' : 'light') + ';path=/;max-age=31536000;samesite=lax'; t.innerHTML = '<i class="fa-solid ' + (d ? 'fa-sun' : 'fa-moon') + '"></i>'; return; }
    t = e.target.closest('[data-toggle]'); if (t) { var x = $(t.getAttribute('data-toggle')); if (x) { x.hidden = !x.hidden; t.classList.toggle('on', !x.hidden); } return; }
    t = e.target.closest('[data-like]'); if (t) { api('like', { post: t.getAttribute('data-like'), kind: t.getAttribute('data-kind') || 'forum' }).then(function (r) { if (r.ok) { t.querySelector('span').textContent = r.count; t.querySelector('i').className = (r.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart'; } }); return; }
    t = e.target.closest('[data-confirm]'); if (t && !window.confirm(t.getAttribute('data-confirm'))) e.preventDefault();
  });
  /* Listen: read the lesson aloud with the phone's own voice */
  var speaking = false;
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-listen]'); if (!t || !window.speechSynthesis) return;
    if (speaking) { speechSynthesis.cancel(); speaking = false; t.classList.remove('on'); return; }
    var src = $(t.getAttribute('data-listen')); if (!src) return;
    var u = new SpeechSynthesisUtterance(src.innerText.slice(0, 12000)); u.lang = t.getAttribute('data-lang') || 'en'; u.rate = 0.95;
    u.onend = function () { speaking = false; t.classList.remove('on'); };
    speechSynthesis.speak(u); speaking = true; t.classList.add('on');
  });
  /* Auto-saving notes */
  $$('[data-note]').forEach(function (ta) {
    var timer, ind = $('#note-state');
    ta.addEventListener('input', function () { clearTimeout(timer); if (ind) ind.textContent = 'Saving...'; timer = setTimeout(function () { api('note', { course: ta.getAttribute('data-course'), lesson: ta.getAttribute('data-note'), note: ta.value }).then(function () { if (ind) ind.textContent = 'Saved'; }); }, 700); });
  });
  /* One question at a time */
  $$('[data-quiz]').forEach(function (form) {
    var qs = $$('.q', form), i = 0, next = $('[data-next]', form), back = $('[data-back]', form), count = $('[data-count]', form), fill = $('[data-fill]', form);
    function show() {
      qs.forEach(function (q, n) { q.hidden = n !== i; });
      if (count) count.textContent = 'Question ' + (i + 1) + ' of ' + qs.length; if (fill) fill.style.width = Math.round((i + 1) / qs.length * 100) + '%';
      next.textContent = i === qs.length - 1 ? 'Submit' : 'Next'; next.disabled = !$('input:checked', qs[i]); back.hidden = i === 0;
    }
    form.addEventListener('change', function () { next.disabled = !$('input:checked', qs[i]); });
    next.addEventListener('click', function () { if (i < qs.length - 1) { i++; show(); window.scrollTo(0, 0); } else form.submit(); });
    back.addEventListener('click', function () { if (i > 0) { i--; show(); } });
    show();
  });
  /* Register: step by step */
  $$('[data-steps]').forEach(function (form) {
    var steps = $$('.step', form);
    function go(n) { steps.forEach(function (s) { s.hidden = s.getAttribute('data-step') !== String(n); }); window.scrollTo(0, 0); }
    form.addEventListener('click', function (e) {
      var t = e.target.closest('[data-go]'); if (!t) return;
      var cur = t.closest('.step'), n = t.getAttribute('data-go');
      if (t.hasAttribute('data-net')) { $('[name=pathway]', form).value = t.getAttribute('data-net'); $$('[data-netname]', form).forEach(function (x) { x.textContent = t.getAttribute('data-netname-v'); }); }
      if (t.hasAttribute('data-check') && cur) { var bad = $$('input[required]', cur).filter(function (x) { return !x.value.trim(); })[0]; if (bad) { bad.focus(); bad.reportValidity && bad.reportValidity(); return; } }
      go(n);
    });
    go(form.getAttribute('data-steps') || 1);
  });
  /* Service worker and push */
  function key8(b64) { var p = '='.repeat((4 - b64.length % 4) % 4), s = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')), a = new Uint8Array(s.length); for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i); return a; }
  function b64(buf) { return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function subscribe(reg) {
    if (!W.vapid || !reg.pushManager) return Promise.resolve(false);
    return reg.pushManager.getSubscription().then(function (sub) {
      var same = false;
      try { var k = sub && sub.options && sub.options.applicationServerKey; same = !!k && b64(k) === W.vapid; } catch (e) {}
      if (sub && !same) return sub.unsubscribe().then(function () { return null; });
      return sub;
    }).then(function (sub) { return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key8(W.vapid) }); })
      .then(function (sub) { var j = sub.toJSON(); return api('push-subscribe', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }); }).then(function () { return true; });
  }
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(W.base + '/sw.js').then(function (reg) {
      if (W.signedIn && window.Notification && Notification.permission === 'granted') subscribe(reg).catch(function () {});
      window.woddiEnablePush = function () {
        if (!window.Notification) return Promise.resolve('unsupported');
        return Notification.requestPermission().then(function (p) { return p === 'granted' ? subscribe(reg).then(function () { return 'granted'; }) : p; });
      };
    }).catch(function () {});
  }
  var pb = $('[data-push]');
  if (pb) pb.addEventListener('click', function () {
    if (!window.woddiEnablePush) { pb.textContent = 'Not supported on this browser'; return; }
    window.woddiEnablePush().then(function (r) { pb.textContent = r === 'granted' ? 'Notifications are on' : 'Notifications are blocked in your browser settings'; });
  });
})();
