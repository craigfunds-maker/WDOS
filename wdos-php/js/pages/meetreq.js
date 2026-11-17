/* Video meetings a leader asks for in her country or area. HQ approves, and the room opens at once (v109.2).
 * The call runs on Jitsi inside WDOS (the page stays open around it). With the JaaS keys set it has no time limit; the public
 * meet.jit.si server cuts embedded calls after 5 minutes, so the stage also offers the same room in a new tab. Minutes and attendance go to everyone invited and to HQ. */
import { h, t, icon, avatar, card, sk, empty, ago, fmt, toast, input, field, tabs, i18n } from '../ui.js';
import { api } from '../api.js';
import { uploadButton, packFile } from '../files.js';
import { app, go } from '../app.js';
import { countdown } from './meetings.js';

const ST = { pending: ['Waiting for HQ approval', ''], approved: ['Approved', 'wnnn'], declined: ['Not approved', 'bad'], cancelled: ['Cancelled', 'bad'], held: ['Held, minutes recorded', 'wgmn'] };
const stTag = (s) => h('span', { class: 'tag' + (ST[s]?.[1] ? ' tag--' + ST[s][1] : ''), text: ST[s]?.[0] || s });
const utc = (s) => new Date(String(s).replace(' ', 'T') + 'Z');
const whenText = (m) => { const d = utc(m.starts_at); return d.toLocaleDateString(loc(), { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + ', ' + d.toLocaleTimeString(loc(), { hour: '2-digit', minute: '2-digit' }) + ' · ' + tf('{1} minutes', m.duration_min); };

export async function render(root, params) {
  if (params.id && /^\d+$/.test(params.id)) return detail(root, params.id);
  const view = ['pending', 'approved', 'held'].includes(params.id) ? params.id : '';
  root.append(tabs([['', 'Upcoming'], ['past', 'Past'], ['requests', 'Video meetings']], 'requests', '#/meetings'));
  const formHost = h('div'), list = h('div', {}, sk.rows(4)), tools = h('div', { class: 'toolbar' });
  root.append(h('div', { class: 'panel', style: 'margin-bottom:16px;display:grid;gap:6px' }, h('b', { text: t('Video meetings in your country or area') }),
    h('small', { class: 'muted', text: t('Fill the form. When WODDI HQ approves it, the Join button is ready at once and you can tell your people. The call runs on Jitsi Meet: free, with no time limit. After the meeting, record the minutes and how many attended. They go to everyone invited and to HQ.') })),
    formHost, h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px' }, tabs([['', 'All'], ['pending', 'Waiting'], ['approved', 'Approved'], ['held', 'Held']], view, '#/meetings/requests'), h('span', { class: 'grow' }), tools),
    card(app.user.is_hq ? 'Requests from leaders' : 'My video meetings', null, list));
  async function load() {
    let d; try { d = await api('/meet-requests' + (view ? '?status=' + view : '')); } catch (e) { list.replaceChildren(empty('x', 'Video meetings did not load', e.message)); return; }
    tools.replaceChildren(d.can_request ? h('button', { class: 'btn', 'data-tour': 'mr-ask', type: 'button', onclick: () => { if (formHost.firstChild) formHost.replaceChildren(); else formHost.replaceChildren(requestForm(d, (id) => go('#/meetings/requests/' + id))); } }, icon('plus', 18), t('Ask for a video meeting')) : '');
    list.replaceChildren(d.items.length ? h('div', { class: 'list list--stack reveal' }, d.items.map((m) => h('a', { class: 'list__link', href: '#/meetings/requests/' + m.id }, h('div', { class: 'nrow__ico' }, icon('calendar', 18)),
      h('div', { class: 'grow' }, h('b', { text: m.title }), h('small', { text: [m.request_no, whenText(m), m.leader_name, [m.region, m.country].filter(Boolean).join(', ')].filter(Boolean).join(' · ') })),
      h('div', { class: 'tagrow' }, m.network ? h('span', { class: 'tag', text: m.network }) : null, stTag(m.status))))) : empty('calendar', 'No video meetings here', d.can_request ? 'Press Ask for a video meeting to send your first request to HQ.' : 'Leaders ask for video meetings here. You see the ones you are invited to.'));
    if (app.user.is_hq && d.pending) toast(tf('{1} video meeting requests wait for approval', d.pending), '', 'info');
  }
  load();
}

function requestForm(d, done) {
  const me = d.me; const F = {
    leader: input({ maxlength: 190, value: me.title ? `${me.name}, ${me.title}` : me.name }), region: input({ maxlength: 190, value: me.region || '' }),
    title: input({ maxlength: 190, placeholder: t('For example: Plateau State coordinators planning meeting') }), reason: h('textarea', { class: 'input', rows: 3, maxlength: 3000, placeholder: t('Why the meeting is needed and what it should decide') }),
    who: h('textarea', { class: 'input', rows: 2, maxlength: 3000, placeholder: t('For example: the 17 LGA coordinators of Plateau State and their assistants') }), count: input({ type: 'number', min: 1, max: 100000, inputmode: 'numeric', placeholder: '25' }),
    date: input({ type: 'datetime-local' }), dur: h('select', { class: 'input' }, [30, 45, 60, 90, 120, 180, 240].map((m) => h('option', { value: m, text: tf('{1} minutes', m) }))),
    graphics: h('textarea', { class: 'input', rows: 2, maxlength: 2000, placeholder: t('For example: a flyer with the date and time, a banner for the call background') }), other: h('textarea', { class: 'input', rows: 2, maxlength: 2000, placeholder: t('Anything else you need from HQ (optional)') }) };
  F.dur.value = '60';
  let flier = null; const flierShow = h('div', { style: 'display:grid;gap:8px' });
  const flierBtn = flierPicker((f) => { flier = f; flierBtn.hidden = true; flierShow.replaceChildren(flierPreview(f, true), h('button', { class: 'btn btn--quiet btn--s', type: 'button', style: 'justify-self:start', onclick: () => { flier = null; flierShow.replaceChildren(); flierBtn.hidden = false; } }, icon('x', 16), t('Remove'))); });
  const min = new Date(Date.now() + 2 * 3600e3); min.setMinutes(0, 0, 0); F.date.min = new Date(min.getTime() - min.getTimezoneOffset() * 60e3).toISOString().slice(0, 16);
  const picked = new Map(); const chips = h('div', { class: 'tagrow' }); const results = h('div', { class: 'picklist' }); const q = input({ type: 'search', placeholder: t('Search leaders by name'), 'aria-label': t('Invite people in WDOS') });
  const paint = () => chips.replaceChildren(...[...picked.values()].map((p) => h('span', { class: 'tag' }, `${p.first_name} ${p.last_name}`, h('button', { class: 'tag__x', type: 'button', 'aria-label': t('Remove'), onclick: () => { picked.delete(p.id); paint(); } }, icon('x', 12)))));
  let qt; q.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(async () => { if (q.value.trim().length < 2) { results.replaceChildren(); return; } try { const r = await api('/dm/people?q=' + encodeURIComponent(q.value.trim())); results.replaceChildren(...r.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { picked.set(p.id, p); paint(); q.value = ''; results.replaceChildren(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.title }))))); } catch (e) { results.replaceChildren(h('p', { class: 'faint', text: e.message })); } }, 250); });
  const err = h('p', { class: 'err', role: 'alert' });
  const send = h('button', { class: 'btn', type: 'button', onclick: async () => {
    err.textContent = ''; if (!F.date.value) { err.textContent = t('Choose the date and time of the meeting.'); return; }
    const start = new Date(F.date.value); send.disabled = true;
    try { const r = await api('/meet-requests', { leader_name: F.leader.value, region: F.region.value, country_code: me.country_code || '', title: F.title.value, reason: F.reason.value, attendees_text: F.who.value, people: [...picked.keys()], expected_count: Number(F.count.value) || 0,
        starts_at: start.toISOString().slice(0, 16).replace('T', ' '), duration_min: Number(F.dur.value), tz: Intl.DateTimeFormat().resolvedOptions().timeZone || '', graphics: F.graphics.value, other_needs: F.other.value, flier_file: flier ? flier.id : 0 });
      toast(tf('Request {1} sent to HQ', r.request_no), 'You are told by notification and email when HQ decides.', 'ok'); done(r.id); }
    catch (e) { err.textContent = e.message; send.disabled = false; } } }, t('Send the request to HQ'));
  return card('Ask for a video meeting', 'Every field helps HQ approve quickly. The meeting time is in your own time zone.',
    h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'row row--2' }, field('Leader asking', F.leader), field('Country and region', F.region)), field('Meeting title', F.title), field('Reason for the meeting', F.reason),
      field('Who will attend', F.who, 'Name the group, or list the people. Leaders in WDOS can also be invited below.'), field('Invite leaders in WDOS (optional)', h('div', { style: 'display:grid;gap:8px' }, chips, q, results), 'They are told when HQ approves, and get the Join button.'),
      h('div', { class: 'row row--2' }, field('Expected number of people', F.count), field('How long', F.dur)), field('Date and time', F.date, tf('Ask at least one hour before. HQ needs time to approve. Times are in this device\'s time zone: {1}.', Intl.DateTimeFormat().resolvedOptions().timeZone || 'GMT')),
      field('Meeting flier (optional)', h('div', { style: 'display:grid;gap:8px' }, flierShow, flierBtn), 'A picture (JPG or PNG) or a PDF, up to 10 MB. Everyone invited sees it on the meeting page. No flier yet? Ask WODDI for one below.'),
      field('Graphics you need from WODDI', F.graphics), field('Anything else', F.other), err, h('div', {}, send)));
}

async function detail(root, id) {
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/meetings/requests', style: 'margin-bottom:12px' }, t('Back to video meetings')));
  const box = h('div', { style: 'display:grid;gap:20px' }, sk.rows(5)); root.append(box);
  async function load() {
    let d; try { d = await api('/meet-requests/' + id); } catch (e) { box.replaceChildren(card('Video meeting', null, empty('x', 'This meeting did not open', e.message))); return; }
    const m = d.meeting; const out = [];
    const facts = h('dl', { class: 'dl' }, [['Request number', m.request_no], ['Leader asking', m.leader_name], ['Country and region', [m.region, m.country].filter(Boolean).join(', ') || '-'], ['When', whenText(m)], ['Expected number of people', m.expected_count ? fmt(m.expected_count) : '-'], ['Network', m.network || '-'], ['Asked', ago(m.created_at)]].flatMap(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })]));
    out.push(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' }, h('div', { class: 'tagrow' }, stTag(m.status), m.status === 'approved' ? countdown({ starts_at: m.starts_at, ends_at: new Date(utc(m.starts_at).getTime() + m.duration_min * 60e3).toISOString().slice(0, 19).replace('T', ' ') }) : null),
      h('h2', { class: 'display', style: 'font-size:24px;margin:0', text: m.title }), facts,
      h('div', {}, h('b', { text: t('Reason for the meeting') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.reason })),
      h('div', {}, h('b', { text: t('Who will attend') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.attendees_text }), m.people.length ? h('div', { class: 'tagrow', style: 'margin-top:8px' }, m.people.map((p) => h('span', { class: 'tag', text: `${p.first_name} ${p.last_name}` }))) : null),
      m.graphics ? h('div', {}, h('b', { text: t('Graphics needed from WODDI') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.graphics })) : null,
      m.other_needs ? h('div', {}, h('b', { text: t('Anything else') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.other_needs })) : null,
      m.hq_note ? h('div', { class: 'panel' }, h('b', { text: t('Note from WODDI HQ') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.hq_note })) : null)));
    /* the gate */
    const gate = { locked: m.status === 'pending' ? 'The room unlocks after WODDI HQ approves this request.' : 'There is no room for this meeting.', not_yet: 'Approved. The room opens shortly.', open: 'Approved. The room is open now. Tap Join the video meeting.', closed: 'This meeting has ended.' }[m.gate];
    const join = h('button', { class: 'btn btn--lime', 'data-tour': 'mr-join', type: 'button', disabled: m.gate !== 'open', onclick: async () => { try { const r = await api(`/meet-requests/${id}/join`, {}); openStage(m, r, load); } catch (e) { toast('The room is not open', e.message, 'bad'); } } }, icon('send', 18), t('Join the video meeting'));
    const callCard = card('Video call', 'The call opens here inside WDOS. Allow the camera and microphone when your phone asks. Press Leave the call when you finish.',
      h('div', { style: 'display:grid;gap:10px' }, h('p', { style: 'margin:0', text: t(gate) }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, join, m.can_cancel ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { if (!confirm(t('Cancel this video meeting? HQ and everyone invited are told.'))) return; try { await api(`/meet-requests/${id}/cancel`, {}); load(); } catch (e) { toast('Not cancelled', e.message, 'bad'); } } }, t('Cancel the meeting')) : null),
        h('small', { class: 'faint', text: m.joins === 1 ? t('1 person has opened the room so far.') : tf('{1} people have opened the room so far.', fmt(m.joins)) })));
    if (m.flier || m.can_flier) out.push(flierCard(m, id, load));
    /* v109.2: once approved, Join and Tell your people come first, above the details */
    if (m.status === 'approved') out.unshift(callCard); else out.push(callCard);
    if (m.can_notify) out.splice(m.status === 'approved' ? 1 : out.length, 0, tellCard(m, id, load));
    /* HQ decision */
    if (m.can_decide) { const note = h('textarea', { class: 'input', rows: 2, maxlength: 2000, placeholder: t('Note to the leader (required when not approving)') });
      const act = async (decision) => { try { await api(`/meet-requests/${id}/decide`, { decision, note: note.value }); toast(decision === 'approve' ? 'Approved' : 'Not approved', decision === 'approve' ? 'The Join button is ready now. The leader and everyone invited are told.' : 'The leader is told by notification and email.', 'ok'); load(); } catch (e) { toast('Not recorded', e.message, 'bad'); } };
      out.push(card('HQ decision', m.status === 'approved' ? 'Already approved. You can still withdraw the approval with a note.' : 'Approve to open the room at once and tell the leader, or decline with the reason.', h('div', { style: 'display:grid;gap:10px' }, note, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, m.status === 'pending' ? h('button', { class: 'btn btn--lime', type: 'button', onclick: () => act('approve') }, t('Approve')) : null, h('button', { class: 'btn btn--danger', type: 'button', onclick: () => act('decline') }, m.status === 'approved' ? t('Withdraw approval') : t('Do not approve')))))); }
    /* minutes: the text, the leaders who attended, a minutes document and a recording (v109.2) */
    if (m.minutes) out.push(minutesView(m));
    else if (m.can_minutes) out.push(minutesForm(m, id, load));
    box.replaceChildren(...out);
  }
  load();
}

/* ---- minutes ---- */
const personRow = (p, extra) => h('div', { class: 'list__link', style: 'cursor:default' }, avatar(p, 34), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), p.title ? h('small', { text: p.title }) : null), extra || null);
function filesView(files) {
  return files.map((f) => f.role === 'recording'
    ? h('div', { style: 'display:grid;gap:6px' }, h('b', { text: t('Recording') }), f.mime.startsWith('video/') ? h('video', { controls: true, preload: 'metadata', src: f.url, style: 'width:100%;max-height:320px;border-radius:12px' }) : h('audio', { controls: true, preload: 'metadata', src: f.url, style: 'width:100%' }),
      h('a', { class: 'btn btn--ghost btn--s', href: f.url + '?dl=1', style: 'justify-self:start' }, icon('link', 16), t('Download the recording')))
    : h('a', { class: 'btn btn--ghost', href: f.url, target: '_blank', rel: 'noopener', style: 'justify-self:start' }, icon('book', 16), tf('Minutes document: {1}', f.name)));
}
function minutesView(m) {
  const mc = card('Minutes and attendance', tf('Recorded {1}. Sent to the volunteer leaders and to HQ, in the app and by email.', ago(m.minutes_at)), h('div', { style: 'display:grid;gap:14px' },
    h('div', { class: 'kv' }, h('div', {}, h('span', { text: t('Number who attended') }), h('b', { text: fmt(m.attendance_count) }))),
    m.attended && m.attended.length ? h('div', { style: 'display:grid;gap:6px' }, h('b', { text: tf('Leaders who attended ({1})', fmt(m.attended.length)) }), h('div', { class: 'list' }, m.attended.map((p) => personRow(p)))) : null,
    m.attendance_names ? h('div', {}, h('b', { text: t('Others present') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.attendance_names })) : null,
    h('div', {}, h('b', { text: t('Minutes') }), h('p', { style: 'white-space:pre-wrap;margin:4px 0 0', text: m.minutes })),
    ...filesView(m.minute_files || [])));
  mc.dataset.tour = 'mr-minutes'; return mc;
}
function recorder(onFile) {
  /* a voice note of the minutes, recorded on the phone; uploaded as a small audio file */
  const state = h('small', { class: 'faint', 'aria-live': 'polite' }); const preview = h('div');
  let rec = null; let chunks = []; let t0 = 0; let tick = null;
  const btn = h('button', { class: 'btn btn--quiet', type: 'button' }, icon('volume', 18), t('Record a voice note'));
  const stop = () => { try { rec && rec.state !== 'inactive' && rec.stop(); } catch { /* stopped */ } };
  btn.onclick = async () => {
    if (rec && rec.state === 'recording') { stop(); return; }
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { toast('Not available here', 'This browser cannot record. Upload a recording from your phone instead.', 'bad'); return; }
    let stream; try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch { toast('Microphone blocked', 'Allow the microphone for WDOS, then try again.', 'bad'); return; }
    const type = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4'].find((x) => MediaRecorder.isTypeSupported?.(x)) || '';
    rec = new MediaRecorder(stream, type ? { mimeType: type, audioBitsPerSecond: 32000 } : {}); chunks = [];
    rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
    rec.onstop = async () => {
      clearInterval(tick); stream.getTracks().forEach((x) => x.stop()); btn.replaceChildren(icon('volume', 18), t('Record again'));
      const mt = rec.mimeType || 'audio/webm'; const blob = new Blob(chunks, { type: mt }); const ext = mt.includes('ogg') ? 'ogg' : mt.includes('mp4') ? 'm4a' : 'webm';
      preview.replaceChildren(h('audio', { controls: true, src: URL.createObjectURL(blob), style: 'width:100%' }));
      await onFile(new File([blob], `voice-note-minutes.${ext}`, { type: mt }));
    };
    rec.start(1000); t0 = Date.now(); btn.replaceChildren(icon('x', 18), t('Stop recording'));
    tick = setInterval(() => { const s = Math.round((Date.now() - t0) / 1000); state.textContent = tf('Recording… {1}', `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`); if (s >= 3600) stop(); }, 500);
  };
  return h('div', { style: 'display:grid;gap:8px' }, h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, btn, state), preview);
}
function minutesForm(m, id, reload) {
  const mins = h('textarea', { class: 'input', rows: 7, maxlength: 12000, placeholder: t('What was discussed, what was decided, and who will do what') });
  const joinedIds = new Set((m.joined || []).map((p) => p.id));
  const roster = [...(m.joined || []), ...(m.people || []).filter((p) => !joinedIds.has(p.id))];
  const ticks = new Map();
  const list = roster.length ? h('div', { class: 'list' }, roster.map((p) => { const cb = h('input', { type: 'checkbox', checked: joinedIds.has(p.id), 'aria-label': `${p.first_name} ${p.last_name}`, onchange: () => { cnt.value = Math.max(Number(cnt.value) || 0, [...ticks.values()].filter((c) => c.checked).length); } }); ticks.set(p.id, cb);
    return h('label', { class: 'list__link', style: 'cursor:pointer' }, cb, avatar(p, 34), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: [p.title, joinedIds.has(p.id) ? t('Opened the room in WDOS') : t('Invited')].filter(Boolean).join(' · ') }))); }))
    : h('p', { class: 'faint', text: t('Nobody opened the room through WDOS. Write the names below.') });
  const cnt = input({ type: 'number', min: 1, inputmode: 'numeric', value: joinedIds.size || '' });
  const names = h('textarea', { class: 'input', rows: 3, maxlength: 6000, placeholder: t('Names of others present, one per line (optional)') });
  const err = h('p', { class: 'err', role: 'alert' });
  let docFile = null; let recFile = null;
  const docShow = h('div'); const recShow = h('small', { class: 'faint' });
  const showDoc = () => docShow.replaceChildren(docFile ? h('div', { class: 'tagrow' }, h('span', { class: 'tag', text: docFile.name }), h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { docFile = null; showDoc(); } }, t('Remove'))) : '');
  const upRec = async (f) => {
    if (f.size > 15 * 1048576) { toast('That recording is too large', 'The limit is 15 MB. Record a shorter voice note.', 'bad'); return; }
    recShow.textContent = tf('Uploading {1}…', f.name);
    try { const r = await api('/files', { name: f.name, data: await packFile(f), purpose: 'recording' }); recFile = r.file; recShow.textContent = tf('Recording ready: {1}', r.file.name); }
    catch (e) { recShow.textContent = ''; toast('Not uploaded', e.message, 'bad'); }
  };
  const pickRec = h('input', { type: 'file', accept: 'audio/*,video/mp4,.mp3,.m4a,.ogg,.webm,.wav,.mp4', hidden: true, onchange: (e) => { const f = e.target.files[0]; e.target.value = ''; if (f) upRec(f); } });
  const send = h('button', { class: 'btn btn--lime', type: 'button', onclick: async () => {
    err.textContent = ''; send.disabled = true;
    try {
      const r = await api(`/meet-requests/${id}/minutes`, { minutes: mins.value, attendance_count: Number(cnt.value) || 0, attendance_names: names.value, attended: [...ticks].filter(([, c]) => c.checked).map(([k]) => k), minutes_file: docFile?.id || null, recording_file: recFile?.id || null });
      toast('Minutes sent', tf('{1} leaders and WODDI HQ were told in the app and by email.', fmt(r.told)), 'ok'); reload();
    } catch (e) { err.textContent = e.message; send.disabled = false; }
  } }, icon('send', 16), t('Send the minutes'));
  const rc = card('Record the minutes', 'Send the minutes, the leaders who attended, and a document or recording if you have one. The volunteer leaders and WODDI HQ get them in the app, on the phone and by email, and HQ sees them on the HQ dashboard.', h('div', { style: 'display:grid;gap:14px' },
    field('Minutes', mins),
    field('Leaders who attended', list, 'People WDOS saw open the room are ticked. Untick anyone who did not stay.'),
    field('Number who attended', cnt, 'Everyone present, including people who joined together on one phone.'),
    field('Others present', names),
    field('Minutes document (optional)', h('div', { style: 'display:grid;gap:8px' }, uploadButton(t('Upload the minutes document'), 'task', (f) => { docFile = f; showDoc(); }), docShow)),
    field('Recording (optional)', h('div', { style: 'display:grid;gap:8px' }, recorder(upRec),
      h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => pickRec.click() }, icon('plus', 18), t('Upload a recording')), pickRec), recShow,
      h('small', { class: 'faint', text: t('Audio (MP3, M4A, OGG, WEBM, WAV) or an MP4 video, up to 15 MB.') }))),
    err, h('div', {}, send)));
  rc.dataset.tour = 'mr-minutes'; return rc;
}

/* ---- the meeting flier (v109.2) ---- */
function flierPicker(onDone, label) {
  const inp = h('input', { type: 'file', accept: 'image/jpeg,image/png,application/pdf', hidden: true });
  const btn = h('button', { class: 'btn btn--quiet', 'data-tour': 'mr-flier', type: 'button', onclick: () => inp.click() }, icon('plus', 16), t(label || 'Upload the meeting flier'));
  inp.addEventListener('change', async () => {
    const f = inp.files[0]; inp.value = ''; if (!f) return;
    if (f.size > 10 * 1048576) { toast('That file is too large', 'The limit is 10 MB. Save the flier as a smaller picture.', 'bad'); return; }
    btn.disabled = true; const old = [...btn.childNodes]; btn.replaceChildren(t('Uploading…'));
    try { const r = await api('/files', { name: f.name, data: await packFile(f, (p) => btn.replaceChildren(tf('Uploading… {1}%', p))), purpose: 'flier' }); if (!/^(image\/(jpeg|png)|application\/pdf)$/.test(r.file.mime)) throw new Error(t('The flier must be a picture (JPG or PNG) or a PDF.')); onDone(r.file); }
    catch (e) { toast('The flier did not upload', e.message, 'bad'); }
    btn.disabled = false; btn.replaceChildren(...old);
  });
  return h('div', {}, btn, inp);
}
function flierPreview(f, small) {
  const url = f.url || `/api/files/${f.id}`;
  return f.mime === 'application/pdf'
    ? h('a', { class: 'btn btn--ghost', href: url, target: '_blank', rel: 'noopener', style: 'justify-self:start' }, icon('book', 16), tf('Open the flier: {1}', f.name))
    : h('a', { href: url, target: '_blank', rel: 'noopener', 'aria-label': t('Open the flier full size') }, h('img', { src: url, alt: t('Meeting flier'), loading: 'lazy', style: `display:block;max-width:100%;max-height:${small ? 220 : 520}px;border-radius:12px;border:1px solid var(--line,#e5e5e5)` }));
}
async function shareFlier(m) {
  const url = m.flier.url || `/api/files/${m.flier.id}`;
  try {
    const blob = await (await fetch(url, { credentials: 'same-origin' })).blob(); const file = new File([blob], m.flier.name, { type: m.flier.mime });
    if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], title: m.title, text: tf('{1}, {2}', m.title, whenText(m)) }); return; }
  } catch (e) { if (e && e.name === 'AbortError') return; }
  toast('Sharing a picture is not available here', 'Tap Download the flier, then send it in WhatsApp.', 'info');
}
function flierCard(m, id, reload) {
  const body = h('div', { style: 'display:grid;gap:12px' });
  if (m.flier) body.append(flierPreview(m.flier, false), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
    h('a', { class: 'btn btn--ghost', href: (m.flier.url || `/api/files/${m.flier.id}`) + '?dl=1' }, icon('link', 16), t('Download the flier')),
    h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => shareFlier(m) }, icon('send', 16), t('Share the flier'))));
  else body.append(h('p', { class: 'faint', style: 'margin:0', text: t('No flier yet. Upload one, or HQ can add it.') }));
  if (m.can_flier) body.append(flierPicker(async (f) => { try { await api(`/meet-requests/${id}/flier`, { file_id: f.id }); toast('Flier saved', m.my_role === 'hq' ? 'The leader is told by notification and email.' : 'Everyone invited sees it on this page.', 'ok'); reload(); } catch (e) { toast('The flier was not saved', e.message, 'bad'); } }, m.flier ? 'Change the flier' : 'Upload the meeting flier'));
  return card('Meeting flier', 'Share it with your people before the meeting.', body);
}

/* ---- telling people about an approved meeting ---- */
async function copyText(txt) {
  try { await navigator.clipboard.writeText(txt); return true; } catch { /* old phones */ }
  const ta = h('textarea', { style: 'position:fixed;opacity:0' }); ta.value = txt; document.body.append(ta); ta.select(); let ok = false; try { ok = document.execCommand('copy'); } catch { ok = false; } ta.remove(); return ok;
}
function tellCard(m, id, reload) {
  const link = location.origin + '/#/meetings/requests/' + id;
  const msg = tf('You are invited to a WODDI video meeting: {1}, {2}. Open it in WDOS and tap Join the video meeting: {3}', m.title, whenText(m), link);
  const picked = new Map(); const chips = h('div', { class: 'tagrow' }); const results = h('div', { class: 'picklist' });
  const q = input({ type: 'search', placeholder: t('Search people by name'), 'aria-label': t('Choose people to tell') });
  const note = h('textarea', { class: 'input', rows: 2, maxlength: 600, placeholder: t('A short message (optional)') });
  const err = h('p', { class: 'err', role: 'alert' });
  const paint = () => chips.replaceChildren(...[...picked.values()].map((p) => h('button', { type: 'button', class: 'tag', title: t('Remove'), onclick: () => { picked.delete(p.id); paint(); } }, `${p.first_name} ${p.last_name} ✕`)));
  let qt; q.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(async () => { if (q.value.trim().length < 2) { results.replaceChildren(); return; } try { const r = await api('/dm/people?q=' + encodeURIComponent(q.value.trim())); results.replaceChildren(...r.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { picked.set(p.id, p); paint(); q.value = ''; results.replaceChildren(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.title }))))); } catch (e) { results.replaceChildren(h('p', { class: 'faint', text: e.message })); } }, 250); });
  const send = async (everyone) => { err.textContent = ''; try { const r = await api(`/meet-requests/${id}/notify`, { people: [...picked.keys()], everyone_invited: everyone, message: note.value }); toast('People told', tf('{1} people were told in WDOS, on the phone and by email.', fmt(r.told)), 'ok'); reload(); } catch (e) { err.textContent = e.message; } };
  const copyBtn = (label, txt, tour) => h('button', { class: 'btn btn--ghost', 'data-tour': tour || null, type: 'button', onclick: async () => { const ok = await copyText(txt); toast(ok ? 'Copied' : 'Not copied', ok ? 'Paste it in any chat or message.' : 'Press and hold the link to copy it.', ok ? 'ok' : 'bad'); } }, icon('link', 16), t(label));
  const share = h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { if (navigator.share) { try { await navigator.share({ title: m.title, text: msg }); } catch { /* closed */ } } else window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener'); } }, icon('send', 16), t('Share'));
  const wa = h('a', { class: 'btn btn--ghost', href: 'https://wa.me/?text=' + encodeURIComponent(msg), target: '_blank', rel: 'noopener' }, t('Send on WhatsApp'));
  return card('Tell your people', 'The room is open. Tell people in WDOS, or copy the link and send it.',
    h('div', { style: 'display:grid;gap:12px' },
      h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, m.people.length ? h('button', { class: 'btn', type: 'button', onclick: () => send(true) }, icon('bell', 16), tf('Notify everyone invited ({1})', fmt(m.people.length))) : null),
      field('Notify more people in WDOS', h('div', { style: 'display:grid;gap:8px' }, chips, q, results), 'They are added to the meeting and told in the app, on the phone and by email.'),
      field('Message', note), err,
      h('div', {}, h('button', { class: 'btn btn--lime', 'data-tour': 'mr-notify', type: 'button', onclick: () => send(false) }, icon('bell', 16), t('Notify the people I chose'))),
      h('hr', { class: 'sep' }),
      h('b', { text: t('Copy the meeting link and send it') }),
      h('input', { class: 'input', readonly: true, value: link, 'aria-label': t('Meeting link'), onfocus: (e) => e.target.select() }),
      h('small', { class: 'faint', text: t('People open this link, sign in to WDOS and tap Join the video meeting. Only people invited can join.') }),
      h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, copyBtn('Copy meeting link', link, 'mr-copy'), copyBtn('Copy invitation message', msg), share, wa),
      m.guest_link ? h('details', {}, h('summary', { text: t('Guests without a WDOS account') }), h('div', { style: 'display:grid;gap:8px;margin-top:8px' },
        h('small', { class: 'faint', text: t('Anyone with this call link can enter the call without signing in. Send it only to guests you trust.') }),
        h('input', { class: 'input', readonly: true, value: m.guest_link, 'aria-label': t('Guest call link'), onfocus: (e) => e.target.select() }),
        h('div', {}, copyBtn('Copy guest call link', m.guest_link)))) : null));
}

/* ---- the call, inside WDOS ---- */
const libs = new Map();
function loadLib(src) {
  if (window.JitsiMeetExternalAPI && libs.get(src) === true) return Promise.resolve();
  if (!libs.has(src) || libs.get(src) === false) libs.set(src, new Promise((res, rej) => { const sc = document.createElement('script'); sc.src = src; sc.async = true; sc.onload = () => { libs.set(src, true); res(); }; sc.onerror = () => { libs.set(src, false); rej(new Error('lib')); }; document.head.append(sc); setTimeout(() => rej(new Error('timeout')), 15000); }));
  const p = libs.get(src); return p === true ? Promise.resolve() : p;
}
export function openStage(m, r, onClose) {
  if (document.querySelector('.callstage')) return;
  const jaas = r.engine === 'jaas' && r.jwt && r.app_id; let api = null; let joined = false;
  const frame = h('div', { class: 'callstage__frame', 'aria-label': t('Video call') });
  const status = h('p', { class: 'callstage__status', 'aria-live': 'polite', text: t('Connecting to the call…') });
  const tab = h('a', { class: 'btn btn--quiet btn--s', href: r.url, target: '_blank', rel: 'noopener' }, t('Open in a new tab instead'));
  const leave = () => { try { api?.dispose(); } catch { /* gone */ } api = null; wrap.remove(); document.removeEventListener('keydown', esc); document.body.classList.remove('in-call'); onClose && onClose(); };
  const esc = (e) => { if (e.key === 'Escape' && !joined) leave(); };
  const note = r.limit_minutes ? h('p', { class: 'callstage__note', text: tf('This call uses the free public Jitsi server, which stops calls shown inside other sites after {1} minutes. If it stops, press Open in a new tab instead and you return to the same room.', r.limit_minutes) }) : null;
  const wrap = h('div', { class: 'callstage', role: 'dialog', 'aria-modal': 'true', 'aria-label': m.title },
    h('div', { class: 'callstage__bar' }, h('div', { class: 'grow' }, h('b', { text: m.title }), h('small', { text: [m.request_no, r.moderator ? t('You run this meeting') : ''].filter(Boolean).join(' · ') })), tab,
      h('button', { class: 'btn btn--s callstage__leave', type: 'button', onclick: leave }, t('Leave the call'))),
    note, status, frame);
  document.body.append(wrap); document.body.classList.add('in-call'); document.addEventListener('keydown', esc);
  const domain = jaas ? '8x8.vc' : r.domain;
  loadLib(jaas ? `https://8x8.vc/${r.app_id}/external_api.js` : `https://${r.domain}/external_api.js`).then(() => {
    api = new window.JitsiMeetExternalAPI(domain, {
      roomName: jaas ? `${r.app_id}/${r.room}` : r.room, jwt: jaas ? r.jwt : undefined, parentNode: frame, width: '100%', height: '100%', lang: i18n.lang,
      userInfo: { displayName: r.name, email: r.email || undefined },
      configOverwrite: { prejoinConfig: { enabled: false }, disableDeepLinking: true, startWithAudioMuted: true, startWithVideoMuted: false, subject: m.title, disableThirdPartyRequests: true, enableClosePage: false },
      interfaceConfigOverwrite: { MOBILE_APP_PROMO: false, SHOW_CHROME_EXTENSION_BANNER: false },
    });
    api.addListener('videoConferenceJoined', () => { joined = true; status.textContent = ''; status.hidden = true; });
    api.addListener('readyToClose', leave);
    setTimeout(() => { if (!joined && api) status.textContent = t('The call is taking long to start. Check your connection, or press Open in a new tab instead.'); }, 25000);
  }).catch(() => { status.textContent = t('The call could not load here. Press Open in a new tab instead.'); });
}
