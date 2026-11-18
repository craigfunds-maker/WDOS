/* Voice calls (v109.13). A volunteer leader rings another leader in her own country and network, or rings WODDI HQ.
 * The server decides who can be rung. While a call rings, the other person's open WDOS shows Answer and Decline
 * (see ringWatch below) and her phone gets a notification. Nobody answering in 45 seconds makes it a missed call. */
import { h, t, tf, icon, avatar, card, sk, empty, ago, toast, input } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { openStage } from './meetreq.js';

const ST = { ringing: 'Ringing', answered: 'Answered', ended: 'Ended', missed: 'Missed', declined: 'Declined', cancelled: 'Not answered' };

export async function render(root, params) {
  const intro = h('div', { class: 'panel', style: 'margin-bottom:16px;display:grid;gap:6px' },
    h('b', { text: t('Ring a leader or WODDI HQ') }),
    h('small', { class: 'muted', text: t('Voice calls go through WDOS, with the camera off. You can ring the volunteer leaders in your own country and network, and WODDI HQ. Keep WDOS open to hear calls ringing; your phone also gets a notification.') }));
  const hqBtn = h('button', { class: 'btn', type: 'button', onclick: () => ring('hq', 'WODDI HQ', '') }, icon('phone', 18), ' ', t('Call WODDI HQ'));
  const search = input({ type: 'search', placeholder: t('Search a leader by name'), 'aria-label': t('Search a leader by name') });
  const people = h('div', {}, sk.rows(4)), recent = h('div', {}, sk.rows(3));
  root.append(intro);
  if (!app.user.is_hq) root.append(h('div', { style: 'margin-bottom:16px' }, hqBtn));
  root.append(card(app.user.is_hq ? 'Serving leaders' : 'Leaders you can ring', null, h('div', { style: 'display:grid;gap:10px' }, search, people)), card('Recent calls', null, recent));

  let timer = null;
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 350); });
  async function load() {
    let d; try { d = await api('/calls' + (search.value.trim() ? '?q=' + encodeURIComponent(search.value.trim()) : '')); }
    catch (e) { people.replaceChildren(empty('x', 'Calls did not load', e.message)); recent.replaceChildren(); return; }
    people.replaceChildren(d.people.length ? h('div', { class: 'list list--stack' }, d.people.map((p) => h('div', { class: 'list__link', style: 'display:flex;align-items:center;gap:10px' },
      avatar(p, 40), h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: p.name }), h('small', { text: [p.title, p.network].filter(Boolean).join(' · ') })),
      h('button', { class: 'btn btn--s', type: 'button', 'aria-label': tf('Ring {1}', p.name), onclick: () => ring(p.id, p.name, p.title) }, icon('phone', 16), ' ', t('Call')))))
      : empty('users', search.value.trim() ? 'No leader by that name' : 'No other leaders yet', app.user.is_hq ? 'Leaders appear here once they hold a seat.' : 'Leaders in your country and network appear here once they hold a seat. You can always ring WODDI HQ.'));
    recent.replaceChildren(d.recent.length ? h('div', { class: 'list list--stack' }, d.recent.map((c) => h('div', { class: 'list__link', style: 'display:flex;align-items:center;gap:10px' },
      h('div', { class: 'nrow__ico' }, icon('phone', 18)),
      h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: (c.role === 'caller' ? t('To') : t('From')) + ': ' + (c.with || 'WODDI HQ') }), h('small', { text: [t(ST[c.status] || c.status), ago(c.created_at)].join(' · ') })),
      (c.with_id || (c.to_hq && c.role === 'caller')) && d.can_call ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => ring(c.role === 'caller' && c.to_hq ? 'hq' : c.with_id, c.with || 'WODDI HQ', c.with_title) }, t('Call back')) : null)))
      : empty('phone', 'No calls yet', 'Your calls will be listed here.'));
  }
  load();
  if (params.id && /^\d+$/.test(params.id)) {     // opened from a phone notification: answer if it is still ringing
    try { const r = await api('/calls/' + params.id); if (r.call.status === 'ringing' && r.call.role === 'callee') incoming(r.call); } catch { /* gone */ }
  }
}

/** Ring someone: the caller waits in the room and hears when the other person comes in. */
export async function ring(to, name, title) {
  let r; try { r = await api('/calls', { to }); } catch (e) { toast(e.message, '', 'error'); return; }
  const id = r.call.id; let done = false;
  const st = h('p', { class: 'callstage__status', 'aria-live': 'polite', text: tf('Ringing {1}…', name) });
  const stage = openStage({ title: tf('Voice call: {1}', name), request_no: '' }, r.join, () => { done = true; api('/calls/' + id + '/end', {}).catch(() => {}); }, { audio: true, sub: title || '', top: st });
  if (!stage) return;
  const tone = ringback();
  const poll = async () => {
    if (done) { tone.stop(); return; }
    let c; try { c = (await api('/calls/' + id)).call; } catch { setTimeout(poll, 4000); return; }
    if (c.status === 'answered') { tone.stop(); st.textContent = tf('{1} answered', c.with || name); setTimeout(() => { st.hidden = true; }, 3000); return; }
    if (['missed', 'declined', 'cancelled', 'ended'].includes(c.status)) { tone.stop(); st.textContent = c.status === 'declined' ? tf('{1} is busy and declined the call.', name) : tf('{1} did not answer. They will see a missed call.', name); setTimeout(() => stage.leave(), 4000); return; }
    setTimeout(poll, 3000);
  };
  setTimeout(poll, 3000);
}

/* ---- incoming calls: the open app asks every few seconds ---- */
let shown = null;
export function ringWatch() {
  const u = app.user; if (!u || !(u.is_hq || u.is_leader) || ringWatch.on) return; ringWatch.on = true;
  const tick = async () => {
    if (!app.user) { ringWatch.on = false; return; }
    if (document.visibilityState === 'visible' && !document.querySelector('.callstage')) {
      try { const d = await api('/calls/ring'); const c = d.calls[0];
        if (c && (!shown || shown.id !== c.id)) incoming(c);
        if (!c && shown) shown.close(); } catch { /* offline: try again */ }
    }
    setTimeout(tick, document.visibilityState === 'visible' ? 5000 : 15000);
  };
  setTimeout(tick, 2000);
}
function incoming(c) {
  if (shown) shown.close();
  const tone = ringtone();
  const close = () => { tone.stop(); box.remove(); shown = null; };
  const answer = async () => {
    close(); let r; try { r = await api('/calls/' + c.id + '/answer', {}); } catch (e) { toast(e.message, '', 'error'); return; }
    openStage({ title: tf('Voice call: {1}', c.with), request_no: '' }, r.join, () => api('/calls/' + c.id + '/end', {}).catch(() => {}), { audio: true, sub: c.with_title || '' });
  };
  const decline = () => { close(); api('/calls/' + c.id + '/decline', {}).catch(() => {}); };
  const box = h('div', { class: 'ringbox', role: 'alertdialog', 'aria-live': 'assertive', 'aria-label': tf('Voice call from {1}', c.with) },
    h('div', { class: 'ringbox__ico' }, icon('phone', 26)),
    h('div', { class: 'grow' }, h('b', { text: c.with }), h('small', { text: [c.with_title, c.to_hq ? t('Calling WODDI HQ') : t('Voice call')].filter(Boolean).join(' · ') })),
    h('div', { class: 'ringbox__btns' },
      h('button', { class: 'btn ringbox__no', type: 'button', onclick: decline }, t('Decline')),
      h('button', { class: 'btn ringbox__yes', type: 'button', onclick: answer }, icon('phone', 16), ' ', t('Answer'))));
  document.body.append(box); shown = { id: c.id, close };
  if (navigator.vibrate) try { navigator.vibrate([400, 200, 400, 200, 400]); } catch { /* not allowed */ }
  setTimeout(() => { if (shown && shown.id === c.id) close(); }, 46000);
}

/* A soft ring made in the browser (no sound file to download on a slow network). */
function beeper(pattern) {
  let ctx = null, stopped = false, t0 = null;
  try { ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch { return { stop() {} }; }
  const play = () => {
    if (stopped) return;
    for (const [at, len, f] of pattern) { const o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = f; g.gain.value = 0.08; o.connect(g); g.connect(ctx.destination); o.start(ctx.currentTime + at); o.stop(ctx.currentTime + at + len); }
    t0 = setTimeout(play, 3000);
  };
  play();
  return { stop() { stopped = true; clearTimeout(t0); try { ctx.close(); } catch { /* closed */ } } };
}
const ringtone = () => beeper([[0, 0.35, 880], [0.45, 0.35, 880], [1.0, 0.35, 660]]);
const ringback = () => beeper([[0, 1.0, 425]]);
