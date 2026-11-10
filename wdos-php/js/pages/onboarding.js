/* Appointment acceptance and guided onboarding. One step at a time: a reading step opens the document here and
 * Next becomes available once the leader has read to the end of it. Wording follows the current WDOS. */
import { h, card, sk, empty, fmtDate, toast, docReader } from '../ui.js';
import { api } from '../api.js';
import { app, refreshUser, go } from '../app.js';

export async function render(root, params) {
  if (params.appointment) return appointment(root);
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(box);
  async function draw() {
    let d; try { d = await api('/onboarding' + (params.user ? '?user=' + params.user : '')); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'Onboarding did not load', e.message))); return; }
    const steps = d.steps, done = steps.filter((s) => s.done_at).length, left = steps.filter((s) => s.required && !s.done_at).length; const parts = [];
    parts.push(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:12px' }, h('h2', { class: 'display', style: 'font-size:24px;margin:0', text: d.read_only ? 'Onboarding record' : left ? 'Your onboarding' : 'Onboarding complete' }),
      h('p', { class: 'muted', style: 'margin:0', text: d.read_only ? d.who : left ? 'Work through each step in order. The document opens here, and the Next button becomes available once you have read to the end of it.' : 'Every required step is finished and your full workspace is open.' }),
      h('div', { style: 'display:flex;gap:12px;align-items:center' }, h('div', { class: 'pbar' }, h('span', { style: `width:${steps.length ? Math.round((done / steps.length) * 100) : 0}%` })), h('b', { class: 'num', text: `${done}/${steps.length}` })),
      h('div', { class: 'dots' }, steps.map((s, i) => h('span', { class: 'dot' + (s.done_at ? ' is-done' : ''), title: `${i + 1}. ${s.title}`, text: s.done_at ? '✓' : String(i + 1) }))),
      d.starter && app.user.is_hq ? h('p', { class: 'faint', style: 'margin:0;font-size:13.5px', text: 'These are the starter steps. Load the approved step list from the current WDOS on the setup page, section 8.' }) : null)));
    const cur = d.read_only ? null : steps.find((s) => !s.done_at);
    if (cur) parts.push(stepCard(cur, steps.indexOf(cur) + 1, steps.length, async () => { await refreshUser().catch(() => {}); draw(); }));
    else if (!d.read_only) parts.push(h('section', { class: 'card' }, h('div', { class: 'card__body' }, h('h3', { text: 'You have completed your onboarding' }), h('p', { class: 'muted', text: 'Your full workspace is now open. Your record stays here for HQ and your supervisor.' }), h('a', { class: 'btn', href: '#/', text: 'Go to my dashboard' }))));
    parts.push(card(d.read_only ? 'Record' : 'My onboarding record', null, h('div', { class: 'list' }, steps.map((s) => h('div', {}, h('span', { class: 'dot' + (s.done_at ? ' is-done' : ''), text: s.done_at ? '✓' : String(s.seq) }), h('div', { class: 'grow' }, h('b', { text: s.title }), s.detail ? h('small', { text: s.detail }) : null, s.note && d.read_only ? h('small', { text: 'Declared: ' + s.note }) : null), h('small', { style: 'flex:none', text: s.done_at ? fmtDate(s.done_at) : 'Not yet' }))))));
    box.replaceChildren(...parts);
  }
  draw();
}
function stepCard(s, n, of, done) {
  const started = Date.now(); const min = Math.min(Number(s.min_seconds) || 0, 90); let reached = s.mode !== 'read', opened = s.mode !== 'visit';
  const next = h('button', { class: 'btn', type: 'button', disabled: true }, 'Next'); const hint = h('p', { class: 'muted', style: 'margin:0;font-size:14px' }); const note = h('textarea', { class: 'input', rows: 3, placeholder: 'For example: none' });
  const secs = () => Math.round((Date.now() - started) / 1000); const ready = () => reached && opened && (s.mode === 'declare' || secs() >= min);
  const refresh = () => { const left = Math.max(0, min - secs()); next.disabled = !ready();
    next.textContent = ready() ? 'Next' : !opened ? 'Open the page first' : !reached ? 'Read to the end first' : `Next (${left || 1}s)`;
    hint.textContent = s.mode === 'read' ? (reached ? 'You have reached the end. You can continue.' : 'Scroll to the end of the document to continue.') : s.mode === 'visit' ? (opened ? 'Page opened. You can continue.' : 'Open the page, look at it, then come back here to continue.') : 'Complete the declaration, then continue.'; };
  const tick = setInterval(() => { if (!next.isConnected) clearInterval(tick); else refresh(); }, 1000);
  let viewer;
  if (s.mode === 'read' && s.doc_url) viewer = docReader(s.doc_url, () => { reached = true; refresh(); });
  else if (s.mode === 'visit') viewer = h('div', { class: 'panel' }, h('p', { text: 'This step is done on another page in WDOS. Open it, then return here.' }), h('a', { class: 'btn btn--quiet', href: s.href || '#/', target: '_blank', rel: 'noopener', onclick: () => { opened = true; setTimeout(refresh, 50); } }, 'Open the page'));
  else viewer = h('div', { class: 'panel', style: 'display:grid;gap:10px' }, h('p', { style: 'margin:0', text: 'State any conflict of interest, or write "none" to confirm you have none.' }), note);
  next.addEventListener('click', async () => { next.disabled = true;
    try { await api('/onboarding/' + s.code, { seconds: secs(), reached_end: reached, note: note.value }); clearInterval(tick); toast('Finished: ' + s.title, '', 'ok'); done(); } catch (e) { toast('Not saved', e.message, 'bad'); refresh(); } });
  setTimeout(refresh, 30);
  return h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' }, h('span', { class: 'tag', style: 'justify-self:start', text: `Step ${n} of ${of}` }), h('h2', { style: 'margin:0', text: s.title }), s.detail ? h('p', { class: 'muted', style: 'margin:0', text: s.detail }) : null, viewer, hint, h('div', {}, next)));
}

/* -------------------------------------------------------------- appointment -- */
async function appointment(root) {
  const box = h('div', { class: 'card' }, sk.rows(3)); root.append(box);
  let d; try { d = await api('/appointment'); } catch (e) { box.replaceChildren(empty('x', 'This page did not load', e.message)); return; }
  const s = d.seat;
  if (!s || !['pending', 'declined', 'accepted'].includes(s.acceptance)) { box.replaceChildren(empty('award', 'No appointment is waiting', 'When WODDI HQ appoints you to a leadership seat, the Volunteer Leadership Agreement opens here for you to read and accept.', h('a', { class: 'btn btn--quiet btn--s', href: '#/', text: 'Go to my dashboard' }))); return; }
  if (s.acceptance === 'accepted') { box.replaceChildren(empty('check', 'Agreement accepted', `You accepted the Volunteer Leadership Agreement on ${fmtDate(s.accepted_at)} as ${s.title}.`, h('a', { class: 'btn btn--s', href: '#/onboarding', text: 'Continue my onboarding' }))); return; }
  let reached = false; const accept = h('button', { class: 'btn', type: 'button', disabled: true }, 'I Accept'); const hint = h('p', { class: 'muted', style: 'margin:0;font-size:14px', text: 'Scroll to the end of the document to continue.' });
  const answer = async (yes) => { if (!yes && !confirm('Decline the Volunteer Leadership Agreement?')) return;
    try { await api('/appointment', { accept: yes }); await refreshUser(); toast(yes ? 'Agreement accepted' : 'You declined the Agreement', yes ? '' : 'Your appointment pauses here. Speak with HQ when you are ready.', yes ? 'ok' : 'bad'); go(yes ? '#/onboarding' : '#/'); } catch (e) { toast('Not saved', e.message, 'bad'); } };
  accept.addEventListener('click', () => answer(true));
  box.replaceChildren(h('div', { class: 'card__body', style: 'display:grid;gap:14px' }, h('h2', { class: 'display', style: 'font-size:24px;margin:0', text: 'Volunteer Leadership Agreement' }), h('p', { class: 'muted', style: 'margin:0', text: `You have been appointed ${s.title} (${s.network}). Read the Agreement to the end, then accept it to take up your seat.` }),
    s.acceptance === 'declined' ? h('p', { class: 'err', text: 'You declined the Agreement. Your appointment pauses here. You can still accept it below, or speak with HQ.' }) : null,
    docReader(d.doc, () => { reached = true; accept.disabled = false; hint.textContent = 'You have reached the end. You can continue.'; }, 'Volunteer Leadership Agreement'), hint,
    h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, accept, s.acceptance === 'pending' ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => answer(false) }, 'Decline') : null)));
  void reached;
}
