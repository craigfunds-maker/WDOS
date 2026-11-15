/* Video meetings a leader asks for in her country or area. HQ approves, then the room unlocks 30 minutes before the start.
 * The call runs on Jitsi inside WDOS (the page stays open around it). With the JaaS keys set it has no time limit; the public
 * meet.jit.si server cuts embedded calls after 5 minutes, so the stage also offers the same room in a new tab. Minutes and attendance go to everyone invited and to HQ. */
import { h, t, icon, avatar, card, sk, empty, ago, fmt, toast, input, field, tabs, i18n } from '../ui.js';
import { api } from '../api.js';
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
    h('small', { class: 'muted', text: t('Fill the form. WODDI HQ approves it, then the Join button unlocks 30 minutes before the start. The call runs on Jitsi Meet: free, with no time limit. After the meeting, record the minutes and how many attended. They go to everyone invited and to HQ.') })),
    formHost, h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px' }, tabs([['', 'All'], ['pending', 'Waiting'], ['approved', 'Approved'], ['held', 'Held']], view, '#/meetings/requests'), h('span', { class: 'grow' }), tools),
    card(app.user.is_hq ? 'Requests from leaders' : 'My video meetings', null, list));
  async function load() {
    let d; try { d = await api('/meet-requests' + (view ? '?status=' + view : '')); } catch (e) { list.replaceChildren(empty('x', 'Video meetings did not load', e.message)); return; }
    tools.replaceChildren(d.can_request ? h('button', { class: 'btn', type: 'button', onclick: () => { if (formHost.firstChild) formHost.replaceChildren(); else formHost.replaceChildren(requestForm(d, (id) => go('#/meetings/requests/' + id))); } }, icon('plus', 18), t('Ask for a video meeting')) : '');
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
  const min = new Date(Date.now() + 2 * 3600e3); min.setMinutes(0, 0, 0); F.date.min = new Date(min.getTime() - min.getTimezoneOffset() * 60e3).toISOString().slice(0, 16);
  const picked = new Map(); const chips = h('div', { class: 'tagrow' }); const results = h('div', { class: 'picklist' }); const q = input({ type: 'search', placeholder: t('Search leaders by name'), 'aria-label': t('Invite people in WDOS') });
  const paint = () => chips.replaceChildren(...[...picked.values()].map((p) => h('span', { class: 'tag' }, `${p.first_name} ${p.last_name}`, h('button', { class: 'tag__x', type: 'button', 'aria-label': t('Remove'), onclick: () => { picked.delete(p.id); paint(); } }, icon('x', 12)))));
  let qt; q.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(async () => { if (q.value.trim().length < 2) { results.replaceChildren(); return; } try { const r = await api('/dm/people?q=' + encodeURIComponent(q.value.trim())); results.replaceChildren(...r.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { picked.set(p.id, p); paint(); q.value = ''; results.replaceChildren(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.title }))))); } catch (e) { results.replaceChildren(h('p', { class: 'faint', text: e.message })); } }, 250); });
  const err = h('p', { class: 'err', role: 'alert' });
  const send = h('button', { class: 'btn', type: 'button', onclick: async () => {
    err.textContent = ''; if (!F.date.value) { err.textContent = t('Choose the date and time of the meeting.'); return; }
    const start = new Date(F.date.value); send.disabled = true;
    try { const r = await api('/meet-requests', { leader_name: F.leader.value, region: F.region.value, country_code: me.country_code || '', title: F.title.value, reason: F.reason.value, attendees_text: F.who.value, people: [...picked.keys()], expected_count: Number(F.count.value) || 0,
        starts_at: start.toISOString().slice(0, 16).replace('T', ' '), duration_min: Number(F.dur.value), tz: Intl.DateTimeFormat().resolvedOptions().timeZone || '', graphics: F.graphics.value, other_needs: F.other.value });
      toast(tf('Request {1} sent to HQ', r.request_no), 'You are told by notification and email when HQ decides.', 'ok'); done(r.id); }
    catch (e) { err.textContent = e.message; send.disabled = false; } } }, t('Send the request to HQ'));
  return card('Ask for a video meeting', 'Every field helps HQ approve quickly. The meeting time is in your own time zone.',
    h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'row row--2' }, field('Leader asking', F.leader), field('Country and region', F.region)), field('Meeting title', F.title), field('Reason for the meeting', F.reason),
      field('Who will attend', F.who, 'Name the group, or list the people. Leaders in WDOS can also be invited below.'), field('Invite leaders in WDOS (optional)', h('div', { style: 'display:grid;gap:8px' }, chips, q, results), 'They are told when HQ approves, and get the Join button.'),
      h('div', { class: 'row row--2' }, field('Expected number of people', F.count), field('How long', F.dur)), field('Date and time', F.date, 'Ask at least one hour before. HQ needs time to approve.'),
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
    const gate = { locked: m.status === 'pending' ? 'The room unlocks after WODDI HQ approves this request.' : 'There is no room for this meeting.', not_yet: tf('Approved. The Join button unlocks 30 minutes before the start ({1}).', utc(m.opens_at).toLocaleString(loc(), { dateStyle: 'medium', timeStyle: 'short' })), open: 'The room is open.', closed: 'This meeting has ended.' }[m.gate];
    const join = h('button', { class: 'btn btn--lime', type: 'button', disabled: m.gate !== 'open', onclick: async () => { try { const r = await api(`/meet-requests/${id}/join`, {}); openStage(m, r, load); } catch (e) { toast('The room is not open', e.message, 'bad'); } } }, icon('send', 18), t('Join the video meeting'));
    out.push(card('Video call', 'The call opens here inside WDOS. Allow the camera and microphone when your phone asks. Press Leave the call when you finish.',
      h('div', { style: 'display:grid;gap:10px' }, h('p', { style: 'margin:0', text: t(gate) }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, join, m.can_cancel ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { if (!confirm(t('Cancel this video meeting? HQ and everyone invited are told.'))) return; try { await api(`/meet-requests/${id}/cancel`, {}); load(); } catch (e) { toast('Not cancelled', e.message, 'bad'); } } }, t('Cancel the meeting')) : null),
        h('small', { class: 'faint', text: tf('{1} people have opened the room so far.', fmt(m.joins)) }))));
    /* HQ decision */
    if (m.can_decide) { const note = h('textarea', { class: 'input', rows: 2, maxlength: 2000, placeholder: t('Note to the leader (required when not approving)') });
      const act = async (decision) => { try { await api(`/meet-requests/${id}/decide`, { decision, note: note.value }); toast(decision === 'approve' ? 'Approved' : 'Not approved', 'The leader is told by notification and email.', 'ok'); load(); } catch (e) { toast('Not recorded', e.message, 'bad'); } };
      out.push(card('HQ decision', m.status === 'approved' ? 'Already approved. You can still withdraw the approval with a note.' : 'Approve to unlock the room at the meeting time, or decline with the reason.', h('div', { style: 'display:grid;gap:10px' }, note, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, m.status === 'pending' ? h('button', { class: 'btn btn--lime', type: 'button', onclick: () => act('approve') }, t('Approve')) : null, h('button', { class: 'btn btn--danger', type: 'button', onclick: () => act('decline') }, m.status === 'approved' ? t('Withdraw approval') : t('Do not approve')))))); }
    /* minutes */
    if (m.minutes) out.push(card('Minutes and attendance', tf('Recorded {1}. Sent to everyone invited and to HQ.', ago(m.minutes_at)), h('div', { style: 'display:grid;gap:10px' }, h('div', { class: 'kv' }, h('div', {}, h('span', { text: t('Number who attended') }), h('b', { text: fmt(m.attendance_count) }))), m.attendance_names ? h('p', { style: 'white-space:pre-wrap;margin:0', text: m.attendance_names }) : null, h('p', { style: 'white-space:pre-wrap;margin:0', text: m.minutes }))));
    else if (m.can_minutes) { const mins = h('textarea', { class: 'input', rows: 6, maxlength: 12000, placeholder: t('What was discussed, what was decided, and who will do what') }); const cnt = input({ type: 'number', min: 1, inputmode: 'numeric' }); const names = h('textarea', { class: 'input', rows: 3, maxlength: 6000, placeholder: t('Names of those who attended (optional)') }); const err = h('p', { class: 'err', role: 'alert' });
      out.push(card('Record the minutes', 'Send the minutes and the number who attended. Everyone invited and WODDI HQ get them in the app, on the phone and by email.', h('div', { style: 'display:grid;gap:12px' }, field('Minutes', mins), field('Number who attended', cnt), field('Who attended', names), err,
        h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; try { await api(`/meet-requests/${id}/minutes`, { minutes: mins.value, attendance_count: Number(cnt.value) || 0, attendance_names: names.value }); toast('Minutes sent', '', 'ok'); load(); } catch (e) { err.textContent = e.message; } } }, t('Send the minutes')))))); }
    box.replaceChildren(...out);
  }
  load();
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
