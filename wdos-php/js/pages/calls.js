/* Voice calls (v109.13, direct audio from v109.14). A volunteer leader rings another leader in her own country and
 * network, or rings WODDI HQ. The server decides who can be rung. While a call rings, the other person's open WDOS
 * shows Answer and Decline (see ringWatch below) and her phone gets a notification. Nobody answering in 45 seconds
 * makes it a missed call. The audio goes straight between the two browsers (WebRTC); there is no Jitsi and no
 * outside meeting room. WDOS passes only the offer and the answer that let the two browsers find each other. */
import { h, t, tf, icon, avatar, card, sk, empty, ago, toast, input } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';

const ST = { ringing: 'Ringing', answered: 'Answered', ended: 'Ended', missed: 'Missed', declined: 'Declined', cancelled: 'Not answered' };

export async function render(root, params) {
  const intro = h('div', { class: 'panel', style: 'margin-bottom:16px;display:grid;gap:6px' },
    h('b', { text: t('Ring a leader or WODDI HQ') }),
    h('small', { class: 'muted', text: t('Voice calls go straight from your phone to the phone of the person you call, through WDOS, with sound only. You can ring the volunteer leaders in your own country and network, and WODDI HQ. Keep WDOS open to hear calls ringing; your phone also gets a notification.') }));
  const hqBtn = h('button', { class: 'btn', type: 'button', onclick: () => ring('hq', 'WODDI HQ', '') }, icon('phone', 18), ' ', t('Call WODDI HQ'));
  const search = input({ type: 'search', placeholder: t('Search a leader by name'), 'aria-label': t('Search a leader by name') });
  const people = h('div', {}, sk.rows(4)), recent = h('div', {}, sk.rows(3));
  root.append(intro);
  if (!app.user.is_hq) root.append(h('div', { style: 'margin-bottom:16px' }, hqBtn));
  const picked = new Map(); const gbar = h('div', { class: 'toolbar', style: 'flex-wrap:wrap' }); let max = 8;
  const withHq = h('input', { type: 'checkbox', style: 'width:20px;height:20px', onchange: () => paintG() });
  const paintG = () => { const n = picked.size + (withHq.checked ? 1 : 0); gbar.replaceChildren(h('span', { class: 'tag', text: picked.size ? tf('{1} chosen', picked.size) : t('Tick leaders to call them together') }),
    app.user.is_hq ? null : h('label', { style: 'display:flex;gap:6px;align-items:center' }, withHq, h('span', { text: t('Bring in WODDI HQ too') })),
    h('button', { class: 'btn btn--s', type: 'button', disabled: picked.size < 1 || n > max - 1, onclick: () => groupCall([...picked.values(), ...(withHq.checked ? [{ id: 'hq', name: 'WODDI HQ' }] : [])]) }, icon('phone', 16), ' ', n > max - 1 ? tf('Group call: {1} people at most', max - 1) : t('Group call'))); };
  const groups = h('div');
  root.append(card(app.user.is_hq ? 'Serving leaders' : 'Leaders you can ring', null, h('div', { style: 'display:grid;gap:10px' }, search, gbar, people)), groups, card('Recent calls', null, recent));

  let timer = null;
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 350); });
  async function load() {
    let d; try { d = await api('/calls' + (search.value.trim() ? '?q=' + encodeURIComponent(search.value.trim()) : '')); }
    catch (e) { people.replaceChildren(empty('x', 'Calls did not load', e.message)); recent.replaceChildren(); return; }
    max = d.max_group || 8; paintG();
    const live = (d.groups || []).filter((g) => g.status === 'live');
    groups.replaceChildren(live.length ? card('Group calls on now', null, h('div', { class: 'list list--stack' }, live.map((g) => h('div', { class: 'list__link', style: 'display:flex;align-items:center;gap:10px' },
      h('div', { class: 'nrow__ico' }, icon('users', 18)), h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: tf('Group call from {1}', g.host) }), h('small', { text: g.people.filter((x) => x.status === 'joined').map((x) => x.name).join(', ') })),
      g.my_status !== 'joined' ? h('button', { class: 'btn btn--s', type: 'button', onclick: () => joinGroup(g.id) }, icon('phone', 16), ' ', t('Join')) : null)))) : '');
    people.replaceChildren(d.people.length ? h('div', { class: 'list list--stack' }, d.people.map((p) => h('div', { class: 'list__link', style: 'display:flex;align-items:center;gap:10px;flex-wrap:nowrap' },
      h('input', { type: 'checkbox', checked: picked.has(p.id), 'aria-label': tf('Choose {1}', p.name), style: 'width:22px;height:22px;flex:none', onchange: (e) => { if (e.target.checked) picked.set(p.id, { id: p.id, name: p.name }); else picked.delete(p.id); paintG(); } }),
      avatar(p, 40), h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: p.name }), h('small', { text: [p.title, p.network].filter(Boolean).join(' · ') })),
      h('button', { class: 'btn btn--s', style: 'flex:none', type: 'button', 'aria-label': tf('Ring {1}', p.name), onclick: () => ring(p.id, p.name, p.title) }, icon('phone', 16), ' ', t('Call')))))
      : empty('users', search.value.trim() ? 'No leader by that name' : 'No other leaders yet', app.user.is_hq ? 'Leaders appear here once they hold a seat.' : 'Leaders in your country and network appear here once they hold a seat. You can always ring WODDI HQ.'));
    recent.replaceChildren(d.recent.length ? h('div', { class: 'list list--stack' }, d.recent.map((c) => h('div', { class: 'list__link', style: 'display:flex;align-items:center;gap:10px' },
      h('div', { class: 'nrow__ico' }, icon('phone', 18)),
      h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: (c.role === 'caller' ? t('To') : t('From')) + ': ' + (c.with || 'WODDI HQ') }), h('small', { text: [t(ST[c.status] || c.status), ago(c.created_at)].join(' · ') })),
      (c.with_id || (c.to_hq && c.role === 'caller')) && d.can_call ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => ring(c.role === 'caller' && c.to_hq ? 'hq' : c.with_id, c.with || 'WODDI HQ', c.with_title) }, t('Call back')) : null)))
      : empty('phone', 'No calls yet', 'Your calls will be listed here.'));
  }
  load();
  if (params.id && /^g\d+$/.test(params.id)) {    // a group call, from a phone notification: offer to join while it is on
    try { const r = await api('/gcalls/' + params.id.slice(1)); if (r.call.status === 'live' && r.call.my_status !== 'joined') incoming({ id: r.call.id, group: true, with: r.call.host, on: r.call.people.filter((x) => x.status === 'joined').length }); else if (r.call.status !== 'live') toast(t('This group call has ended.'), '', 'info'); } catch { /* gone */ }
  }
  if (params.id && /^\d+$/.test(params.id)) {     // opened from a phone notification: answer if it is still ringing
    try { const r = await api('/calls/' + params.id); if (r.call.status === 'ringing' && r.call.role === 'callee') incoming(r.call); } catch { /* gone */ }
  }
}

/** Ring someone. The caller's browser prepares the call and waits for the answer. */
export async function ring(to, name, title) {
  if (document.querySelector('.vcall')) return;
  let mic; try { mic = await getMic(); } catch (e) { toast(t('WDOS cannot use your microphone'), t('Allow the microphone for woddicrm.org in your browser settings, then try again.'), 'error'); return; }
  let r; try { r = await api('/calls', { to }); } catch (e) { mic.getTracks().forEach((x) => x.stop()); toast(e.message, '', 'error'); return; }
  const scr = callScreen(r.call.id, name, title, mic, 'caller');
  scr.say(tf('Ringing {1}…', name)); scr.tone(ringback());
  try {
    const pc = scr.peer(r.ice);
    await pc.setLocalDescription(await pc.createOffer({ offerToReceiveAudio: true }));
    await gathered(pc);
    await api('/calls/' + r.call.id + '/signal', { kind: 'offer', data: pc.localDescription.toJSON() });
  } catch (e) { scr.fail(t('The call could not start on this phone.')); return; }
  scr.listen(async (sig) => { if (sig.kind === 'answer' && !scr.pc.currentRemoteDescription) { scr.say(t('Connecting…')); await scr.pc.setRemoteDescription(sig.data); } });
}

/** Answer: take the call, read the caller's offer, send the answer. */
async function take(c) {
  let mic; try { mic = await getMic(); } catch { toast(t('WDOS cannot use your microphone'), t('Allow the microphone for woddicrm.org in your browser settings, then try again.'), 'error'); api('/calls/' + c.id + '/decline', {}).catch(() => {}); return; }
  let r; try { r = await api('/calls/' + c.id + '/answer', {}); } catch (e) { mic.getTracks().forEach((x) => x.stop()); toast(e.message, '', 'error'); return; }
  const scr = callScreen(c.id, c.with, c.with_title, mic, 'callee'); scr.say(t('Connecting…'));
  const pc = scr.peer(r.ice); let done = false;
  scr.listen(async (sig) => {
    if (sig.kind !== 'offer' || done) return; done = true;
    try {
      await pc.setRemoteDescription(sig.data);
      await pc.setLocalDescription(await pc.createAnswer());
      await gathered(pc);
      await api('/calls/' + c.id + '/signal', { kind: 'answer', data: pc.localDescription.toJSON() });
    } catch { scr.fail(t('The call could not connect.')); }
  });
}

const getMic = () => navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false });
/** Wait until the browser has listed its network routes (at most 4 seconds), so one offer and one answer are enough. */
const gathered = (pc) => new Promise((res) => { if (pc.iceGatheringState === 'complete') return res(); const tm = setTimeout(res, 4000); pc.addEventListener('icegatheringstatechange', () => { if (pc.iceGatheringState === 'complete') { clearTimeout(tm); res(); } }); });

/** The call screen: name, state, timer, mute, speaker and hang up. It also asks WDOS for the other side's messages. */
function callScreen(id, name, title, mic, role) {
  const audio = h('audio', { autoplay: true, playsinline: true });
  const state = h('p', { class: 'vcall__state', 'aria-live': 'polite' }); const clock = h('p', { class: 'vcall__clock' });
  let pc = null, tone = null, ended = false, after = 0, started = 0, tick = null, poll = null, muted = false;
  const muteBtn = h('button', { class: 'vcall__btn', type: 'button', 'aria-pressed': 'false', onclick: () => { muted = !muted; mic.getAudioTracks().forEach((x) => { x.enabled = !muted; }); muteBtn.setAttribute('aria-pressed', String(muted)); muteBtn.lastChild.textContent = t(muted ? 'Unmute' : 'Mute'); } }, icon('mic', 22), h('span', { text: t('Mute') }));
  const endBtn = h('button', { class: 'vcall__btn vcall__end', type: 'button', onclick: () => hang(true) }, icon('phone', 24), h('span', { text: t('Hang up') }));
  const wrap = h('div', { class: 'vcall', role: 'dialog', 'aria-modal': 'true', 'aria-label': tf('Voice call: {1}', name) },
    h('div', { class: 'vcall__who' }, h('div', { class: 'vcall__ico' }, icon('phone', 34)), h('b', { text: name }), title ? h('small', { text: title }) : null, state, clock),
    h('div', { class: 'vcall__btns' }, muteBtn, endBtn), audio);
  document.body.append(wrap); document.body.classList.add('in-call');
  const say = (x) => { state.textContent = x; };
  function hang(mine, why) {
    if (ended) return; ended = true; clearInterval(tick); clearTimeout(poll); tone && tone.stop();
    if (mine) api('/calls/' + id + '/end', {}).catch(() => {});
    try { pc && pc.close(); } catch { /* closed */ } mic.getTracks().forEach((x) => x.stop());
    say(why || t('Call ended')); endBtn.disabled = true; muteBtn.disabled = true;
    setTimeout(() => { wrap.remove(); document.body.classList.remove('in-call'); }, why ? 3500 : 1200);
  }
  const scr = {
    say, get pc() { return pc; },
    tone(x) { tone = x; },
    fail(x) { hang(true, x); },
    peer(ice) {
      pc = new RTCPeerConnection({ iceServers: ice });
      mic.getTracks().forEach((tr) => pc.addTrack(tr, mic));
      pc.ontrack = (e) => { audio.srcObject = e.streams[0]; audio.play().catch(() => {}); };
      pc.onconnectionstatechange = () => {
        if (pc.connectionState === 'connected' && !started) { tone && tone.stop(); started = Date.now(); say(t('Connected')); tick = setInterval(() => { const s = Math.floor((Date.now() - started) / 1000); clock.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); }, 1000); }
        if (pc.connectionState === 'failed') hang(true, t('The call could not connect. The network may be blocking it. Try again, or send a message.'));
      };
      return pc;
    },
    listen(onSig) {
      const step = async () => {
        if (ended) return;
        try {
          const d = await api('/calls/' + id + '/signal?after=' + after);
          for (const sg of d.signals) { after = Math.max(after, sg.id); await onSig(sg); }
          if (d.status === 'declined') return hang(false, tf('{1} is busy and declined the call.', name));
          if (d.status === 'missed' || (d.status === 'cancelled' && role === 'callee')) return hang(false, role === 'caller' ? tf('{1} did not answer. They will see a missed call.', name) : t('The caller hung up.'));
          if (d.status === 'ended') return hang(false, t('The other person hung up.'));
        } catch { /* weak signal: ask again */ }
        poll = setTimeout(step, started ? 3000 : 1200);
      };
      step();
    },
  };
  return scr;
}

/* ---- incoming calls: the open app asks every few seconds ---- */
let shown = null;
export function ringWatch() {
  const u = app.user; if (!u || !(u.is_hq || u.is_leader) || ringWatch.on) return; ringWatch.on = true;
  const tick = async () => {
    if (!app.user) { ringWatch.on = false; return; }
    if (document.visibilityState === 'visible' && !document.querySelector('.callstage, .vcall')) {
      try { const d = await api('/calls/ring'); const c = d.calls[0];
        if (c && (!shown || shown.id !== (c.group ? 'g' : '') + c.id)) incoming(c);
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
  const answer = () => { close(); c.group ? joinGroup(c.id) : take(c); };
  const decline = () => { close(); api((c.group ? '/gcalls/' : '/calls/') + c.id + '/decline', {}).catch(() => {}); };
  const box = h('div', { class: 'ringbox', role: 'alertdialog', 'aria-live': 'assertive', 'aria-label': tf('Voice call from {1}', c.with) },
    h('div', { class: 'ringbox__ico' }, icon('phone', 26)),
    h('div', { class: 'grow' }, h('b', { text: c.with }), h('small', { text: c.group ? tf('Group call, {1} people on', c.on ?? 1) : [c.with_title, c.to_hq ? t('Calling WODDI HQ') : t('Voice call')].filter(Boolean).join(' · ') })),
    h('div', { class: 'ringbox__btns' },
      h('button', { class: 'btn ringbox__no', type: 'button', onclick: decline }, t('Decline')),
      h('button', { class: 'btn ringbox__yes', type: 'button', onclick: answer }, icon('phone', 16), ' ', t('Answer'))));
  document.body.append(box); shown = { id: (c.group ? 'g' : '') + c.id, close };
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
  return { stop() { if (stopped) return; stopped = true; clearTimeout(t0); ctx.close().catch(() => {}); } };
}
const ringtone = () => beeper([[0, 0.35, 880], [0.45, 0.35, 880], [1.0, 0.35, 660]]);
const ringback = () => beeper([[0, 1.0, 425]]);

/* ---- group calls (v109.24): up to 8 people, each pair connected directly ---- */
/** Start a group call with the people chosen ([{id, name}]). */
export async function groupCall(people) {
  if (document.querySelector('.vcall') || !people.length) return;
  let mic; try { mic = await getMic(); } catch { toast(t('WDOS cannot use your microphone'), t('Allow the microphone for woddicrm.org in your browser settings, then try again.'), 'error'); return; }
  let r; try { r = await api('/gcalls', { to: people.map((p) => p.id) }); } catch (e) { mic.getTracks().forEach((x) => x.stop()); toast(e.message, '', 'error'); return; }
  groupScreen(r, mic, true);
}
/** Join a group call I was rung into (or rejoin one I left). */
export async function joinGroup(id) {
  if (document.querySelector('.vcall')) return;
  let mic; try { mic = await getMic(); } catch { toast(t('WDOS cannot use your microphone'), t('Allow the microphone for woddicrm.org in your browser settings, then try again.'), 'error'); api('/gcalls/' + id + '/decline', {}).catch(() => {}); return; }
  let r; try { r = await api('/gcalls/' + id + '/join', {}); } catch (e) { mic.getTracks().forEach((x) => x.stop()); toast(e.message, '', 'error'); return; }
  groupScreen(r, mic, false);
}
const GST = { ringing: 'Ringing', joined: 'On the call', left: 'Left', declined: 'Declined', missed: 'Did not answer' };
function groupScreen(r, mic, host) {
  const id = r.call.id, me = app.user.id, ice = r.ice; const pcs = new Map(); const sounds = h('div', { hidden: true });
  let call = r.call, after = 0, ended = false, poll = null, tick = null, started = 0, muted = false, tone = host ? ringback() : null;
  const state = h('p', { class: 'vcall__state', 'aria-live': 'polite' }); const clock = h('p', { class: 'vcall__clock' });
  const tiles = h('div', { class: 'vgroup' });
  const muteBtn = h('button', { class: 'vcall__btn', type: 'button', 'aria-pressed': 'false', onclick: () => { muted = !muted; mic.getAudioTracks().forEach((x) => { x.enabled = !muted; }); muteBtn.setAttribute('aria-pressed', String(muted)); muteBtn.lastChild.textContent = t(muted ? 'Unmute' : 'Mute'); } }, icon('mic', 22), h('span', { text: t('Mute') }));
  const addBtn = h('button', { class: 'vcall__btn', type: 'button', onclick: () => addPeople() }, icon('plus', 22), h('span', { text: t('Add people') }));
  const endBtn = h('button', { class: 'vcall__btn vcall__end', type: 'button', onclick: () => hang(true) }, icon('phone', 24), h('span', { text: t('Leave') }));
  const pickSlot = h('div', { class: 'vgroup__pick' });
  const wrap = h('div', { class: 'vcall', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('Group call') },
    h('div', { class: 'vcall__who' }, h('div', { class: 'vcall__ico' }, icon('users', 34)), h('b', { text: t('Group call') }), state, clock, tiles, pickSlot),
    h('div', { class: 'vcall__btns' }, muteBtn, addBtn, endBtn), sounds);
  document.body.append(wrap); document.body.classList.add('in-call');
  const say = (x) => { state.textContent = x; };
  const paint = () => {
    tiles.replaceChildren(...call.people.filter((p) => !p.me).map((p) => { const pc = pcs.get(p.id); const live = pc && pc.connectionState === 'connected';
      return h('div', { class: 'vgroup__p' + (live ? ' is-on' : '') }, avatar(p, 44), h('b', { text: p.name, style: 'max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap' }), h('small', { text: live ? t('Connected') : p.status === 'joined' ? t('Connecting…') : t(GST[p.status] || p.status) })); }),
      ...(call.hq_ringing ? [h('div', { class: 'vgroup__p' }, h('div', { class: 'nrow__ico', style: 'width:44px;height:44px' }, icon('globe', 22)), h('b', { text: 'WODDI HQ' }), h('small', { text: t('Ringing') }))] : []));
    const on = call.people.filter((p) => !p.me && p.status === 'joined').length;
    say(on ? tf('{1} on the call with you', on) : (call.hq_ringing || call.people.some((p) => p.status === 'ringing')) ? t('Ringing…') : t('Waiting for others to join'));
    addBtn.disabled = call.people.filter((p) => ['joined', 'ringing'].includes(p.status)).length + (call.hq_ringing ? 1 : 0) >= (call.max || 8);
  };
  function peer(other) {
    const old = pcs.get(other); if (old) try { old.close(); } catch { /* closed */ }
    const pc = new RTCPeerConnection({ iceServers: ice }); pcs.set(other, pc);
    mic.getTracks().forEach((tr) => pc.addTrack(tr, mic));
    const audio = h('audio', { autoplay: true, playsinline: true, 'data-peer': other }); sounds.append(audio);
    pc.ontrack = (e) => { audio.srcObject = e.streams[0]; audio.play().catch(() => {}); };
    pc.onconnectionstatechange = () => {
      if (pc.connectionState === 'connected' && !started) { tone && tone.stop(); tone = null; started = Date.now(); tick = setInterval(() => { const s = Math.floor((Date.now() - started) / 1000); clock.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); }, 1000); }
      if (['closed', 'failed'].includes(pc.connectionState)) audio.remove();
      paint();
    };
    return pc;
  }
  async function offerTo(other) {
    try { const pc = peer(other); await pc.setLocalDescription(await pc.createOffer({ offerToReceiveAudio: true })); await gathered(pc);
      await api('/gcalls/' + id + '/signal', { to: other, kind: 'offer', data: pc.localDescription.toJSON() }); } catch { /* she may have left; the next look will tell */ }
  }
  async function onSignal(sg) {
    if (sg.kind === 'offer') {
      const cur = pcs.get(sg.from);
      if (cur && cur.signalingState === 'have-local-offer' && me < sg.from) return;     // both offered at once: the lower number's offer wins
      try { const pc = peer(sg.from); await pc.setRemoteDescription(sg.data); await pc.setLocalDescription(await pc.createAnswer()); await gathered(pc);
        await api('/gcalls/' + id + '/signal', { to: sg.from, kind: 'answer', data: pc.localDescription.toJSON() }); } catch { /* try again on the next offer */ }
    } else if (sg.kind === 'answer') {
      const pc = pcs.get(sg.from); if (pc && pc.signalingState === 'have-local-offer') try { await pc.setRemoteDescription(sg.data); } catch { /* stale */ }
    }
  }
  function hang(mine, why) {
    if (ended) return; ended = true; clearTimeout(poll); clearInterval(tick); tone && tone.stop();
    if (mine) api('/gcalls/' + id + '/leave', {}).catch(() => {});
    for (const pc of pcs.values()) try { pc.close(); } catch { /* closed */ }
    mic.getTracks().forEach((x) => x.stop()); say(why || t('You left the call')); endBtn.disabled = true; muteBtn.disabled = true; addBtn.disabled = true;
    setTimeout(() => { wrap.remove(); document.body.classList.remove('in-call'); }, why ? 3000 : 1000);
  }
  async function addPeople() {
    if (pickSlot.firstChild) { pickSlot.replaceChildren(); return; }
    let d; try { d = await api('/calls'); } catch (e) { toast(e.message, '', 'error'); return; }
    const on = new Set(call.people.filter((p) => ['joined', 'ringing'].includes(p.status)).map((p) => p.id));
    const list = d.people.filter((p) => !on.has(p.id)).slice(0, 60);
    if (!app.user.is_hq && !call.hq_ringing && !call.hq_on) list.unshift({ id: 'hq', name: 'WODDI HQ', title: t('Rings everyone at HQ; the first to answer joins') });
    pickSlot.replaceChildren(h('div', { class: 'vgroup__list' }, list.length ? list.map((p) => h('button', { type: 'button', class: 'pick', onclick: async () => {
      try { const x = await api('/gcalls/' + id + '/add', { to: [p.id] }); call = x.call; paint(); pickSlot.replaceChildren(); toast(tf('Ringing {1}…', p.name), '', 'ok'); } catch (e) { toast(e.message, '', 'error'); } } },
      p.id === 'hq' ? h('div', { class: 'nrow__ico' }, icon('globe', 18)) : avatar(p, 30), h('span', {}, h('b', { text: p.name }), h('small', { text: p.title })))) : [h('p', { text: t('Everyone you can ring is already on the call.') })]));
  }
  const step = async () => {
    if (ended) return;
    try {
      const d = await api('/gcalls/' + id + '/signal?after=' + after);
      call = d.call;
      for (const sg of d.signals) { after = Math.max(after, sg.id); await onSignal(sg); }
      if (call.status !== 'live') return hang(false, t('The call has ended.'));
      for (const [pid, pc] of pcs) { const p = call.people.find((x) => x.id === pid); if (!p || p.status !== 'joined') { try { pc.close(); } catch { /* closed */ } pcs.delete(pid); sounds.querySelector(`[data-peer="${pid}"]`)?.remove(); } }
      if (host && tone && !call.hq_ringing && !call.people.some((p) => !p.me && ['ringing', 'joined'].includes(p.status))) return hang(true, t('Nobody answered. They will see a missed group call.'));
      paint();
    } catch { /* weak signal: ask again */ }
    poll = setTimeout(step, started ? 2500 : 1200);
  };
  paint(); (r.peers || []).forEach((pid) => offerTo(pid)); step();
}
