/* Meetings: schedule, attendance, minutes, and the actions that become tasks. Wording follows the current WDOS. */
import { h, t, icon, avatar, card, sk, empty, fmt, toast, input, field, tabs, netTag, linkify, editForm, delButton } from '../ui.js';
import { api } from '../api.js';
import { taskRow, peoplePicker } from './tasks.js';
import { whenMine, zonedToUtc, utcToZoned, timeIn, myTz, TZ_CHOICES, TZ_PREVIEW, offsetText } from '../tz.js';
import { flierView, shrinkImage, bigCountdown, flierTemplate, renderEmailFliers, paintFlierWords } from '../flier.js';
import { uploadButton, docList } from '../files.js';
import { i18n } from '../ui.js';

const when = (s) => new Date(s.replace(' ', 'T') + 'Z');
// v109.53: in the phone's own time zone, with the zone named, so a leader in Kenya reads Kenya time
const fmtWhen = (a, b) => whenMine(when(a), when(b));
const MST = { scheduled: ['Scheduled', 'wgmn'], completed: ['Completed', 'wnnn'], cancelled: ['Cancelled', 'bad'] };
const mTag = (s) => h('span', { class: 'tag tag--' + MST[s][1], text: MST[s][0] });
const MODES = { virtual: 'Online', in_person: 'In person', hybrid: 'In person and online' };
/* A live countdown to the start. It reads the phone's own clock, so it is right in every time zone. */
export function countdown(m) {
  const el = h('span', { class: 'countdown', role: 'timer' }); const st = when(m.starts_at).getTime(), en = when(m.ends_at).getTime();
  const paint = () => { if (!el.isConnected && el.dataset.on) { clearInterval(tm); return; } el.dataset.on = '1'; const now = Date.now(); let d = Math.floor((st - now) / 1000);
    if (now >= st && now <= en) { el.textContent = t('Happening now'); el.classList.add('is-live'); return; } if (now > en) { el.textContent = t('Ended'); el.classList.remove('is-live'); clearInterval(tm); return; }
    const days = Math.floor(d / 86400); d -= days * 86400; const hrs = Math.floor(d / 3600); d -= hrs * 3600; const min = Math.floor(d / 60); const sec = d - min * 60;
    el.textContent = t('Starts in') + ' ' + (days ? `${days}d ${hrs}h ${min}m` : hrs ? `${hrs}h ${String(min).padStart(2, '0')}m ${String(sec).padStart(2, '0')}s` : `${min}m ${String(sec).padStart(2, '0')}s`); };
  const tm = setInterval(paint, 1000); paint(); return el;
}
const shareText = (m) => [m.title, fmtWhen(m.starts_at, m.ends_at), m.link ? 'Join: ' + m.link : m.location, m.agenda ? '\nAgenda\n' + m.agenda : '', '\n' + location.origin + '/#/meetings/' + m.id].filter(Boolean).join('\n');

export async function render(root, params) {
  if (params.tab && /^\d+$/.test(params.tab)) return detail(root, params.tab);
  const scope = params.tab === 'past' ? 'past' : '';
  const list = h('div', {}, sk.rows(4)); const formSlot = h('div'); const tools = h('div', { class: 'toolbar' }); const designSlot = h('div');
  root.append(tabs([['', 'Upcoming'], ['past', 'Past'], ['requests', 'Video meetings']], scope, '#/meetings'), formSlot, card(scope ? 'Past meetings' : 'Upcoming meetings', null, list, tools), designSlot);
  async function load() {
    let r; try { r = await api('/meetings?scope=' + (scope || 'upcoming')); } catch (e) { list.replaceChildren(empty('x', 'Meetings did not load', e.message)); return; }
    if (r.audiences && r.audiences['hq_team:ALL'] && !designSlot.firstChild) designSlot.append(flierDesign());
    tools.replaceChildren(r.can_schedule ? h('button', { class: 'btn', type: 'button', onclick: () => { if (formSlot.firstChild) formSlot.replaceChildren(); else formSlot.replaceChildren(newForm(r, () => { formSlot.replaceChildren(); load(); })); } }, icon('plus', 18), 'Schedule a meeting') : '');
    list.replaceChildren(r.items.length ? h('div', { class: 'list list--stack reveal' }, r.items.map((m) => h('a', { class: 'list__link', href: '#/meetings/' + m.id }, h('div', { class: 'nrow__ico' }, icon('calendar', 18)),
      h('div', { class: 'grow' }, h('b', {}, m.kind === 'talk_show' ? h('span', { class: 'tag tag--wgmn', style: 'margin-inline-end:8px', text: 'Talk show' }) : null, h('span', { text: m.title })), h('small', { text: tf('{1} · hosted by {2}', fmtWhen(m.starts_at, m.ends_at), m.host) }), m.status === 'scheduled' ? countdown(m) : null), h('div', { class: 'tagrow' }, m.status === 'scheduled' && m.yes ? h('span', { class: 'tag tag--wnnn', text: tf('{1} will attend', fmt(m.yes)) }) : null, m.has_flier ? h('span', { class: 'tag', text: t('Flier') }) : null,
        m.can_change ? h('span', { onclick: (e) => { e.preventDefault(); e.stopPropagation(); } }, delButton('Delete', async () => { await api(`/meetings/${m.id}/delete`, { confirm: true }); toast(t('Meeting deleted'), '', 'ok'); load(); })) : null, m.link && m.status === 'scheduled' ? h('span', { class: 'btn btn--quiet btn--s', role: 'link', onclick: (e) => { e.preventDefault(); e.stopPropagation(); window.open(m.link, '_blank', 'noopener'); } }, 'Join') : null, h('span', { class: 'tag', text: MODES[m.mode] }), mTag(m.status)))))
      : empty('calendar', 'No meetings here', r.can_schedule ? 'Schedule a meeting and everyone invited is notified in the app and on their phones.' : 'Meetings for your unit will appear here once scheduled.'));
  }
  load();
}
export function newForm(r, done, opts = {}) {
  const title = input({ maxlength: 190 }); const mode = h('select', { class: 'input' }, Object.entries(r.modes).map(([v, l]) => h('option', { value: v, text: l })));
  const loc = input({ maxlength: 400 }); const locHint = h('small', { text: 'Paste the meeting link here.' }); mode.addEventListener('change', () => { locHint.textContent = mode.value === 'in_person' ? 'Address of the meeting.' : mode.value === 'hybrid' ? 'Address, and the video call link.' : 'Paste the meeting link here.'; });
  const start = input({ type: 'datetime-local' }), end = input({ type: 'datetime-local' }); 
  /* v109.55: tick as many groups as needed, and add people by name on top (Azeez, 24 Nov 2026) */
  const groupOpts = Object.entries(r.audiences).filter(([v]) => !v.startsWith('people:')); const ticked = new Set();
  const aud = h('fieldset', { class: 'audpick' }, h('legend', { class: 'sr-only', text: t('Who is invited?') }), ...groupOpts.map(([v, l]) => { const c = h('input', { type: 'checkbox', value: v, onchange: (e) => { e.target.checked ? ticked.add(v) : ticked.delete(v); paintCount(); } }); return h('label', { class: 'check' }, c, h('span', { text: t(l) })); }));
  const audCount = h('small', { class: 'muted' }); const paintCount = () => { const n = ticked.size, p = chosen.size; audCount.textContent = !n && !p ? t('Tick one or more groups, or add people by name below. You can do both.') : tf('Groups ticked: {1}. People added: {2}.', n, p); };
  /* v109.53: the time zone the time is typed in. Nigeria (WODDI HQ) first, then this phone's own zone if it is another one. */
  const mine = myTz(); const now = new Date();
  const zoneOpt = (tz, name) => h('option', { value: tz, text: `${t(name)} (${offsetText(now, tz)})` });
  const tzSel = h('select', { class: 'input' }, ...(TZ_CHOICES.some(([z]) => z === mine) ? [] : [zoneOpt(mine, t('This phone') + ': ' + mine.replace(/_/g, ' '))]), ...TZ_CHOICES.map(([z, n]) => zoneOpt(z, n)));
  tzSel.value = TZ_CHOICES.some(([z]) => z === mine) ? mine : 'Africa/Lagos';
  const elsewhere = h('small', { class: 'muted' });
  const paintElsewhere = () => {
    if (!start.value) { elsewhere.textContent = t('Everyone invited sees the meeting in their own time.'); return; }
    const d = zonedToUtc(start.value, tzSel.value);
    elsewhere.textContent = t('Everyone invited sees it in their own time, for example:') + ' ' + TZ_PREVIEW.map(([z, n]) => `${t(n)} ${timeIn(d, z)}`).join(' · ');
  };
  start.addEventListener('change', () => { if (start.value && !end.value) end.value = utcToZoned(new Date(zonedToUtc(start.value, tzSel.value).getTime() + 3600000), tzSel.value); paintElsewhere(); });
  tzSel.addEventListener('change', paintElsewhere); paintElsewhere();
  const agenda = h('textarea', { class: 'input', rows: 4 }); const err = h('p', { class: 'err', role: 'alert' });
  const kind = h('select', { class: 'input' }, [['meeting', 'Meeting'], ['talk_show', 'Talk show']].map(([v, l]) => h('option', { value: v, text: l })));
  /* v109.24: a meeting with the leaders she chooses */
  const chosen = new Map((opts.invitees || []).map((p) => [p.id, p])); const chips = h('div', { class: 'tagrow', style: 'justify-content:flex-start' }); const found = h('div', { class: 'picklist' });
  const who = input({ type: 'search', placeholder: t('Search a leader by name'), 'aria-label': t('Search a leader by name') });
  const paintChosen = () => { paintCount(); chips.replaceChildren(...[...chosen.values()].map((p) => h('span', { class: 'tag' }, p.name, h('button', { class: 'tag__x', type: 'button', 'aria-label': t('Remove'), onclick: () => { chosen.delete(p.id); paintChosen(); } }, icon('x', 12))))); };
  let wt; who.addEventListener('input', () => { clearTimeout(wt); wt = setTimeout(async () => { const q = who.value.trim(); if (q.length < 2) { found.replaceChildren(); return; }
    try { const x = await api('/tasks/people?q=' + encodeURIComponent(q)); found.replaceChildren(...(x.people.length ? x.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { chosen.set(p.id, { id: p.id, name: `${p.first_name} ${p.last_name}` }); paintChosen(); who.value = ''; found.replaceChildren(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.title || '' })))) : [h('p', { class: 'faint', text: t('No one found inside your area.') })])); } catch (e) { found.replaceChildren(h('p', { class: 'faint', text: e.message })); } }, 250); });
  const peopleKey = Object.keys(r.audiences).find((k) => k.startsWith('people:'));
  const pickBox = h('div', { style: 'display:grid;gap:8px', hidden: !peopleKey }, h('b', { text: t('Add people by name') }), chips, who, found);
  paintChosen();
  const isHq = !!r.audiences['hq_team:ALL']; const country = h('select', { class: 'input' }, h('option', { value: '', text: 'Every country' }));
  if (isHq) api('/geo/countries').then((x) => country.append(...x.countries.map((c) => h('option', { value: c.id, text: c.name })))).catch(() => {});
  /* v109.53: a Meet link for the meeting's own date, not a call that starts now. meet.google.com/new opened a live meeting at once
   * (Azeez, 24 Nov 2026). Google Calendar opens a new event at the time chosen above; adding Google Meet to it gives a link for that time. */
  const gcal = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
  const meetBtn = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => {
    err.textContent = '';
    if (!start.value) { err.textContent = t('Choose when the meeting starts first, so the Google Meet link is made for that time.'); start.focus(); return; }
    const s0 = zonedToUtc(start.value, tzSel.value), e0 = end.value ? zonedToUtc(end.value, tzSel.value) : new Date(s0.getTime() + 3600000);
    const u = 'https://calendar.google.com/calendar/render?' + new URLSearchParams({ action: 'TEMPLATE', text: title.value || 'WODDI meeting', dates: gcal(s0) + '/' + gcal(e0), details: agenda.value || '', ctz: tzSel.value });
    window.open(u, '_blank', 'noopener');
  } }, icon('link', 16), t('Make a Google Meet link for this time'));
  /* v109.56: the flier. A picture she uploads, or WDOS makes one from the details and her words (translated for each leader). */
  let flierImg = ''; const fHead = input({ maxlength: 190, placeholder: t('Headline. Leave empty to use the title') }); const fText = h('textarea', { class: 'input', rows: 4, maxlength: 6000, placeholder: t('A short message for the flier: why to come, who is speaking, what to bring') });
  const fFile = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'input' }); const fPrev = h('div', { class: 'flier-prev' }); const fErr = h('p', { class: 'err', role: 'alert' });
  const formMeeting = () => ({ id: 0, title: title.value || t('Meeting'), kind: kind.value, mode: mode.value, location: loc.value, link: /^https?:\/\//.test(loc.value.trim()) ? loc.value.trim() : '',
    starts_at: start.value ? zonedToUtc(start.value, tzSel.value) : new Date(Date.now() + 86400000), ends_at: end.value ? zonedToUtc(end.value, tzSel.value) : new Date(Date.now() + 90000000) });
  const preview = () => fPrev.replaceChildren(flierView(formMeeting(), { image: flierImg, headline: fHead.value.trim() || title.value, body: fText.value.trim() }));
  const rmImg = h('button', { class: 'btn btn--ghost btn--s', type: 'button', hidden: true, onclick: () => { flierImg = ''; fFile.value = ''; rmImg.hidden = true; preview(); } }, t('Remove the picture'));
  fFile.addEventListener('change', async () => { fErr.textContent = ''; const f = fFile.files[0]; if (!f) return; try { flierImg = await shrinkImage(f); rmImg.hidden = false; preview(); } catch (e) { fErr.textContent = e.message; fFile.value = ''; } });
  const flierBox = h('details', { class: 'panel flierform' }, h('summary', {}, h('b', { text: t('Flier (optional)') }), ' ', h('small', { class: 'muted', text: t('Upload a flier, or let WDOS make one from the details') })),
    h('div', { style: 'display:grid;gap:12px;margin-top:12px' },
      field('Upload a flier picture (optional)', fFile, h('small', { class: 'muted', text: t('JPG, PNG or WebP. WDOS makes it smaller for phones with little data.') })), rmImg, fErr,
      field('Flier headline', fHead), field('Flier message', fText),
      h('small', { class: 'muted', text: t('The headline and message are translated into each leader\'s own language. Words inside an uploaded picture cannot be translated, so the translated words show under the picture.') }),
      h('div', {}, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: preview }, icon('eye', 16), t('Preview the flier'))), fPrev));
  const save = h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; save.disabled = true;
    try { const iso = (v) => v ? zonedToUtc(v, tzSel.value).toISOString() : ''; const x = await api('/meetings', { title: title.value, mode: mode.value, location: loc.value, starts_at: iso(start.value), ends_at: iso(end.value), audiences: [...ticked, ...(chosen.size && peopleKey ? [peopleKey] : [])], invitees: [...chosen.keys()], agenda: agenda.value, kind: kind.value, country_id: isHq ? Number(country.value) || 0 : 0,
        flier_image: flierImg, flier_headline: fHead.value.trim() || (flierImg || fText.value.trim() ? title.value.trim() : ''), flier_text: fText.value.trim() });
      toast('Meeting scheduled', tf('{1} people notified in the app and by push.', fmt(x.invited)) + (x.mail_on ? (x.emails_held ? ' ' + t('The emails go out as soon as the flier is ready in each language.') : tf(' {1} emails are being sent.', fmt(x.emails))) : ' Emails are not set up on the server yet.'), 'ok');
      /* v109.58: with a flier, this browser draws it in each language for the emails, then sends the invitations (the server sends them after 4 minutes anyway) */
      if (x.emails_held) { const nets = new Set([...ticked].map((k) => k.split(':')[1])); const fm = { ...formMeeting(), id: x.id, network: nets.size === 1 ? [...nets][0] : 'ALL', link: /^https?:\/\//.test(loc.value.trim()) ? loc.value.trim() : '' };
        try { await renderEmailFliers(x.id, fm, (msg) => { save.textContent = msg; }, { send: true }); toast(t('Invitations sent'), t('Each person gets the flier in her own language.'), 'ok'); } catch (e2) { toast(t('The emails will go out shortly'), e2.message, 'info'); } }
      done(); } catch (e) { err.textContent = e.message; save.disabled = false; save.textContent = t('Schedule'); } } }, 'Schedule');
  return card('Schedule a meeting', 'Choose the time zone you are typing the time in. Each person invited sees the meeting in their own time.', h('div', { style: 'display:grid;gap:14px' }, field('Title', title), h('div', { class: 'grid2' }, field('Meeting or talk show?', kind), field('Starts', start), field('Ends', end), field('Time zone of these times', tzSel), field('Format', mode), isHq ? field('Country', country) : null), elsewhere, h('div', { class: 'field' }, h('span', { text: t('Who is invited?') }), aud, audCount), pickBox,
    h('label', { class: 'field' }, h('span', { text: 'Location or link' }), loc, locHint),
    h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:center' }, meetBtn, h('small', { class: 'muted', style: 'flex:1;min-width:220px', text: 'This does not start a call now. Google Calendar opens a new event at the time above: tap Add Google Meet video conferencing, save it, then copy the Meet link and paste it above. In the call, people can turn on captions and choose their own language for translated speech.' })),
    field('Agenda (optional)', agenda), flierBox,
    h('small', { class: 'muted', text: 'Everyone invited is told now, reminded a week before, three days before, the day before, one hour before and as it starts, in the app and on their phones. Emails go out too when email is switched on, with the flier and buttons to say if they will attend.' }), err, h('div', {}, save)));
}
async function detail(root, id) {
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/meetings', style: 'justify-self:start;margin-bottom:12px' }, 'Back to meetings'), box);
  async function load() {
    let d; try { d = await api('/meetings/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This meeting did not open', e.message))); return; }
    const m = d.meeting; const parts = [];
    /* Will you attend? One touch. The host sees the totals. */
    const rsvpBox = h('div', { style: 'display:grid;gap:8px' });
    const paintRsvp = (r) => rsvpBox.replaceChildren(h('b', { text: 'Will you attend?' }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, [['yes', 'Yes'], ['maybe', 'Maybe'], ['no', 'No']].map(([v, l]) => h('button', { class: 'btn btn--s ' + (r.mine === v ? '' : 'btn--ghost'), type: 'button', 'aria-pressed': r.mine === v ? 'true' : 'false', onclick: async () => { try { await api(`/meetings/${id}/rsvp`, { answer: v }); paintRsvp(await api(`/meetings/${id}/rsvp`)); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, l))),
      h('small', { class: 'muted', text: tf('Replies so far: {1} yes, {2} maybe, {3} no', fmt(r.counts.yes), fmt(r.counts.maybe), fmt(r.counts.no)) }));
    api(`/meetings/${id}/rsvp`).then(paintRsvp).catch(() => {});
    parts.push(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' }, h('div', { class: 'tagrow' }, mTag(m.status), m.kind === 'talk_show' ? h('span', { class: 'tag tag--wgmn', text: 'Talk show' }) : null, h('span', { class: 'tag', text: MODES[m.mode] }), m.network !== 'ALL' ? netTag(m.network) : null),
      h('h2', { class: 'display', style: 'font-size:26px;margin:0', text: m.title }),
      h('dl', { class: 'dl' }, [['When', fmtWhen(m.starts_at, m.ends_at)], ['Hosted by', m.host], ['For', m.scope_label], [m.link ? 'Meeting link' : 'Where', m.location]].filter((x) => x[1]).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', {}, k === 'Hosted by' || k === 'When' || k === 'For' ? v : linkify(v))])),
      m.status === 'scheduled' ? h('div', { class: 'countbox' }, icon('calendar', 20), countdown(m)) : null,
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, m.link && m.status === 'scheduled' ? h('a', { class: 'btn', href: m.link, target: '_blank', rel: 'noopener', onclick: () => { api(`/meetings/${id}/join`, {}).catch(() => {}); } }, icon('link', 18), 'Join meeting') : null,
        h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { const text = shareText(m); try { if (navigator.share) await navigator.share({ title: m.title, text }); else { await navigator.clipboard.writeText(text); toast('Copied', 'Paste it in a message to share the agenda and the link.', 'ok'); } } catch { /* she closed the share sheet */ } } }, icon('send', 18), m.agenda ? 'Share the agenda' : 'Share this meeting')),
      m.link && m.status === 'scheduled' && /meet\.google\.com/.test(m.link) ? h('small', { class: 'muted', text: 'This is a Google Meet call. Inside the call, turn on captions and choose your language to follow in your own language.' }) : null,
      m.status === 'scheduled' ? rsvpBox : null,
      m.agenda ? h('div', {}, h('b', { text: 'Agenda' }), h('p', { style: 'white-space:pre-wrap;margin:6px 0 0' }, linkify(m.agenda))) : null,
      m.minutes ? h('div', {}, h('b', { text: 'Minutes' }), h('p', { style: 'white-space:pre-wrap;margin:6px 0 0' }, linkify(m.minutes))) : null,
      d.my_mark ? h('p', { class: 'muted', text: 'Your attendance: ' + (d.my_mark === 'present' ? 'Present' : 'Absent') }) : null)));
    if (d.flier) parts.push(card('Flier', d.flier.lang !== i18n.lang && i18n.lang !== 'en' ? t('The translation into your language is being prepared. Here are the words as written.') : null, flierView(m, d.flier)));
    if (d.host && m.status !== 'cancelled') parts.push(whoWillAttend(id, m, d.replies));
    if (d.host && m.status === 'scheduled') parts.push(flierAdmin(id, m, load));
    /* v109.58: slides and documents. Everyone invited can open them; they go out with the minutes when the meeting is closed. */
    if ((d.files && d.files.length) || d.host) parts.push(card('Slides and documents', d.host ? t('Add the slides before or after the meeting. They go to everyone invited with the minutes.') : null,
      h('div', { style: 'display:grid;gap:12px' }, docList(d.files || [], { emptyText: t('No slides yet.'), canRemove: () => d.host, onRemove: async (f) => { await api(`/meetings/${id}/files/${f.id}/remove`, {}); load(); } }),
        d.host ? uploadButton(t('Add slides or a document'), '', async (file) => { const x = await api(`/meetings/${id}/files`, { file_id: file.id }); toast(t('Added'), x.told ? tf('{1} people told.', fmt(x.told)) : '', 'ok'); load(); }) : null)));
    if (d.tasks.length) parts.push(card('Actions from this meeting', 'Each action is a task with its own owner and status.', h('div', { class: 'list' }, d.tasks.map((t) => taskRow(t)))));
    if (d.host && m.status !== 'cancelled') {
      const marks = {}; d.people.forEach((p) => { marks[p.id] = p.mark || ''; }); let dirty = false;
      const rows = d.people.map((p) => { const seg = h('div', { class: 'seg' }, [['present', 'Present'], ['absent', 'Absent']].map(([v, l]) => h('button', { type: 'button', 'aria-pressed': String(marks[p.id] === v), onclick: (e) => { marks[p.id] = marks[p.id] === v ? '' : v; dirty = true; seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', 'false')); if (marks[p.id]) e.currentTarget.setAttribute('aria-pressed', 'true'); } }, l)));
        return h('div', { style: 'flex-wrap:wrap' }, avatar(p, 36), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: [p.state_region, p.rsvp ? 'Replied ' + p.rsvp : '', p.joined ? 'Opened the meeting link' : ''].filter(Boolean).join(' · ') })), seg); });
      parts.push(card('Attendance', tf('{1} invited · {2} opened the meeting link · {3} present · {4} absent', fmt(d.invited), fmt(d.opened_link), d.present, d.absent) + (d.invited > d.people.length ? tf(' · showing the first {1}', d.people.length) : ''), d.people.length ? h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'list' }, rows),
        h('div', {}, h('button', { class: 'btn btn--quiet', type: 'button', onclick: async () => { if (!dirty) { toast('Mark at least one person before saving.', '', 'bad'); return; } try { await api(`/meetings/${id}/attendance`, { marks }); toast('Attendance saved', '', 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Save attendance')))
        : empty('users', 'Nobody is in this audience yet', 'No one matches the people this meeting was called for.')));
    }
    if (d.host && m.status === 'scheduled') {
      const minutes = h('textarea', { class: 'input', rows: 5, placeholder: 'What was discussed and decided' }); const acts = []; const actBox = h('div', { style: 'display:grid;gap:10px' });
      const addAct = () => { const a = { action: input({ placeholder: 'Action', maxlength: 190 }), deadline: input({ type: 'date', 'aria-label': 'Deadline' }), picker: peoplePicker(false) }; acts.push(a);
        actBox.append(h('div', { class: 'panel', style: 'display:grid;gap:8px' }, h('div', { class: 'grid2' }, a.action, a.deadline), h('small', { class: 'faint', text: 'Owner. Leave empty to keep the action yourself.' }), a.picker.node)); };
      parts.push(card('Close with minutes', 'Closing records the minutes and turns each action into a task for its owner.', h('div', { style: 'display:grid;gap:14px' }, field('Minutes', minutes), h('b', { text: 'Decisions and actions' }), actBox,
        h('div', {}, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: addAct }, icon('plus', 16), 'Add an action')),
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, h('button', { class: 'btn', type: 'button', onclick: async () => { try { const x = await api(`/meetings/${id}/close`, { minutes: minutes.value, actions: acts.filter((a) => a.action.value.trim()).map((a) => ({ action: a.action.value, deadline: a.deadline.value, owner_id: a.picker.ids()[0] || 0 })) }); toast('Meeting closed', x.tasks ? tf('{1} tasks created.', x.tasks) : '', 'ok'); load(); } catch (e) { toast('Not closed', e.message, 'bad'); } } }, 'Close with minutes'),
          h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { if (!confirm('Cancel this meeting? Everyone invited is told.')) return; try { await api(`/meetings/${id}/cancel`, {}); toast('Meeting cancelled', '', 'ok'); load(); } catch (e) { toast('Not cancelled', e.message, 'bad'); } } }, 'Cancel meeting')))));
    }
    /* v109.54: the host or HQ changes the meeting (everyone invited is told, each in her own time) or deletes it */
    if (d.host) {
      const slot = h('div'); const zone = myTz();
      const editBtn = m.status === 'scheduled' ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { if (slot.firstChild) { slot.replaceChildren(); return; }
        slot.replaceChildren(editForm('Change this meeting', [{ k: 'title', label: 'Title', value: m.title }, { k: 'mode', label: 'Format', type: 'select', options: Object.entries(MODES), value: m.mode },
          { k: 'location', label: 'Location or link', value: m.location, max: 400 }, { k: 'starts', label: 'Starts', type: 'datetime-local', value: utcToZoned(when(m.starts_at), zone), hint: tf('In your own time ({1})', offsetText(new Date(), zone)) },
          { k: 'ends', label: 'Ends', type: 'datetime-local', value: utcToZoned(when(m.ends_at), zone) }, { k: 'agenda', label: 'Agenda (optional)', type: 'textarea', value: m.agenda || '', max: 6000 }],
          async (v) => { const st = v.starts ? zonedToUtc(v.starts, zone) : null; let en = v.ends ? zonedToUtc(v.ends, zone) : null;
            if (st && (!en || en <= st)) en = new Date(st.getTime() + Math.max(15 * 60000, when(m.ends_at) - when(m.starts_at)));   // moved the start only: it keeps its length
            const x = await api(`/meetings/${id}/edit`, { title: v.title, mode: v.mode, location: v.location, starts_at: st ? st.toISOString() : '', ends_at: en ? en.toISOString() : '', agenda: v.agenda });
            toast(t('Meeting changed'), x.told ? tf('{1} people told, each in their own time.', fmt(x.told)) : '', 'ok'); load(); }, () => slot.replaceChildren())); } }, icon('edit', 16), ' ', t('Edit')) : null;
      parts.push(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:12px' }, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center' }, h('b', { class: 'grow', text: t('Change or delete') }), editBtn,
        delButton('Delete meeting', async () => { const x = await api(`/meetings/${id}/delete`, { confirm: true }); toast(t('Meeting deleted'), x.told ? tf('{1} people told it will not hold.', fmt(x.told)) : '', 'ok'); location.hash = '#/meetings'; })), slot)));
    }
    box.replaceChildren(...parts);
  }
  load();
}

/* v109.56: the host's flier tools: the picture, the words, and each language. */
function flierAdmin(id, m, reload) {
  const body = h('div', { style: 'display:grid;gap:14px' }, sk.rows(2));
  const sec = card('Flier and translations', 'Each leader sees the flier\'s words in her own language, on her dashboard and in her emails.', body);
  (async () => {
    let d; try { d = await api(`/meetings/${id}/flier/langs`); } catch (e) { body.replaceChildren(h('p', { class: 'err', text: e.message })); return; }
    let img = ''; let removeImg = false; const fFile = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'input' }); const fErr = h('p', { class: 'err', role: 'alert' });
    fFile.addEventListener('change', async () => { fErr.textContent = ''; const f = fFile.files[0]; if (!f) return; try { img = await shrinkImage(f); removeImg = false; } catch (e) { fErr.textContent = e.message; fFile.value = ''; } });
    const head = input({ maxlength: 190 }); head.value = d.headline; const text = h('textarea', { class: 'input', rows: 4, maxlength: 6000 }); text.value = d.text;
    const saveBtn = h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { fErr.textContent = ''; saveBtn.disabled = true;
      try { const x = await api(`/meetings/${id}/flier`, { image: img, remove_image: removeImg, headline: head.value.trim(), text: text.value.trim() });
        toast(t('Flier saved'), x.translating ? t('The words are being translated into each language. This takes a minute or two.') : '', 'ok');
        saveBtn.textContent = t('Making the flier for the emails…'); try { await renderEmailFliers(id, m, (msg) => { saveBtn.textContent = msg; }); } catch { /* the emails then carry the words without the picture */ } reload(); } catch (e) { fErr.textContent = e.message; saveBtn.disabled = false; } } }, t('Save the flier'));
    const langRows = h('div', { class: 'list' });
    const paintLangs = (langs) => langRows.replaceChildren(...langs.map((l) => { const slot = h('div');
      return h('div', { style: 'display:grid;gap:6px' }, h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { class: 'grow', text: t(l.name) }),
        h('span', { class: 'tag ' + (l.ready ? 'tag--wnnn' : ''), text: l.ready ? (l.source === 'hq' ? t('Written by HQ') : t('Translated')) : t('Not translated yet') }),
        d.leaders_by_lang[l.lang] ? h('small', { class: 'muted', text: tf('{1} invited read this language', d.leaders_by_lang[l.lang]) }) : null,
        h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { if (slot.firstChild) { slot.replaceChildren(); return; }
          slot.replaceChildren(editForm(tf('The flier in {1}', t(l.name)), [{ k: 'headline', label: 'Flier headline', value: l.headline || '', max: 250 }, { k: 'text', label: 'Flier message', type: 'textarea', value: l.body || '', max: 6000 }],
            async (v) => { const x = await api(`/meetings/${id}/flier/langs/${l.lang}`, { headline: v.headline, text: v.text }); toast(t('Saved'), '', 'ok'); paintLangs(x.langs); renderEmailFliers(id, m).catch(() => {}); }, () => slot.replaceChildren())); } }, icon('edit', 16), ' ', t('Check or correct'))), slot); }));
    paintLangs(d.langs);
    const trBtn = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { trBtn.disabled = true; try { const x = await api(`/meetings/${id}/flier/translate`, { redo: true }); paintLangs(x.langs); toast(t('Translated'), x.failed.length ? tf('{1} languages did not translate. Try again later or type them yourself.', x.failed.length) : '', x.failed.length ? 'bad' : 'ok'); } catch (e) { toast(t('Not translated'), e.message, 'bad'); } trBtn.disabled = false; } }, icon('globe', 16), t('Translate now'));
    body.replaceChildren(
      d.image ? h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('img', { src: d.image, alt: '', style: 'width:84px;border-radius:10px' }), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: (e) => { removeImg = true; img = ''; e.currentTarget.textContent = t('The picture will be removed when you save'); } }, t('Remove the picture'))) : null,
      field(d.image ? 'Change the flier picture' : 'Upload a flier picture (optional)', fFile), fErr, field('Flier headline', head), field('Flier message', text), h('div', {}, saveBtn),
      h('hr', { class: 'sep' }), h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { class: 'grow', text: t('Languages') }), d.ai_on ? trBtn : null),
      d.ai_on ? null : h('p', { class: 'muted', text: t('Automatic translation is not set up on this server. Type each language yourself, or ask the IT Lead to switch on an AI lane.') }),
      d.headline || d.text ? langRows : h('p', { class: 'muted', text: t('Write a headline or message above first. The languages follow.') }),
      d.image ? pictureLangs(id, m, d) : null);
  })();
  return sec;
}
/* v109.56: who will attend. The host sees each answer, asks those who have not replied, and downloads the list. */
function whoWillAttend(id, m, rc) {
  const body = h('div', { style: 'display:grid;gap:14px' }, sk.rows(2));
  const sec = card('Who will attend', 'Replies from the app and from the email buttons. Counts change as people reply.', body);
  (async () => {
    let d; try { d = await api(`/meetings/${id}/replies`); } catch (e) { body.replaceChildren(h('p', { class: 'err', text: e.message })); return; }
    let tab = m.status === 'scheduled' && Date.now() < new Date(String(m.starts_at).replace(' ', 'T') + 'Z').getTime() ? 'yes' : 'joined'; const list = h('div', { class: 'list' }); const tabsRow = h('div', { class: 'seg' });
    const groups = { joined: (p) => !!p.joined_at, yes: (p) => p.answer === 'yes', maybe: (p) => p.answer === 'maybe', no: (p) => p.answer === 'no', none: (p) => !p.answer };
    const NAMES = { joined: 'Joined through WDOS', yes: 'Will attend', maybe: 'Maybe', no: 'Cannot attend', none: 'No reply yet' };
    const num = { joined: d.joined || 0, yes: d.counts.yes, maybe: d.counts.maybe, no: d.counts.no, none: d.no_reply };
    const paint = () => { tabsRow.replaceChildren(...Object.keys(groups).map((k) => h('button', { type: 'button', 'aria-pressed': String(k === tab), onclick: () => { tab = k; paint(); } }, `${t(NAMES[k])} (${fmt(num[k])})`)));
      const ps = d.people.filter(groups[tab]);
      list.replaceChildren(...(ps.length ? ps.slice(0, 500).map((p) => h('div', {}, avatar(p, 34), h('div', { class: 'grow' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: [p.designation, p.state_region].filter(Boolean).join(' · ') })))) : [h('p', { class: 'muted', text: t('Nobody here yet.') })])); };
    const csv = () => { const rows = [['Name', 'Designation', 'State or region', 'Country', 'Reply', 'Joined through WDOS']].concat(d.people.map((p) => [`${p.first_name} ${p.last_name}`, p.designation || '', p.state_region || '', p.country_code || '', p.answer || 'no reply', p.joined_at || '']));
      const blob = new Blob([rows.map((r) => r.map((v) => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n')], { type: 'text/csv' }); const a = h('a', { href: URL.createObjectURL(blob), download: 'meeting-' + id + '-replies.csv' }); document.body.append(a); a.click(); a.remove(); };
    const nudge = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { nudge.disabled = true; try { const x = await api(`/meetings/${id}/nudge`, {}); toast(t('Asked'), tf('{1} people asked in the app.', fmt(x.asked)) + (x.emails ? ' ' + tf('{1} emails are being sent.', fmt(x.emails)) : ''), 'ok'); } catch (e) { toast(t('Not sent'), e.message, 'bad'); nudge.disabled = false; } } }, icon('bell', 16), t('Ask those who have not replied'));
    body.replaceChildren(
      h('div', { class: 'row row--4' }, [['joined', 'Joined through WDOS'], ['yes', 'Will attend'], ['maybe', 'Maybe'], ['no', 'Cannot attend'], ['none', 'No reply yet']].map(([k, l]) => h('div', { class: 'card kpi' }, h('div', { class: 'kpi__label' }, h('span', { text: t(l) })), h('div', { class: 'kpi__value num', text: fmt(num[k]) })))),
      h('small', { class: 'muted', text: tf('{1} invited', fmt(d.invited)) + (d.invited > d.shown ? ' · ' + tf('showing the first {1}', fmt(d.shown)) : '') }),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, d.no_reply && m.status === 'scheduled' ? nudge : null, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: csv }, icon('download', 16), t('Download the list'))),
      tabsRow, list);
    paint();
  })();
  return sec;
}

/* v109.57: HQ's flier design. A background made in Canva (or anywhere); WDOS writes each meeting's words, the date in each
 * reader's own time and a QR code on top. Removing it brings back WDOS's own background. */
function flierDesign() {
  const prev = h('div', { class: 'flier-prev' }); const err = h('p', { class: 'err', role: 'alert' }); const file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'input' });
  const sample = () => ({ id: 0, title: t('Leaders forum'), kind: 'meeting', mode: 'virtual', location: 'https://meet.google.com/abc-defg-hij', link: 'https://meet.google.com/abc-defg-hij', starts_at: new Date(Date.now() + 7 * 86400000), ends_at: new Date(Date.now() + 7 * 86400000 + 3600000) });
  const show = async (fresh) => { await flierTemplate(fresh); prev.replaceChildren(flierView(sample(), { headline: t('Leaders forum'), body: t('A short message for the flier: why to come, who is speaking, what to bring') })); };
  const rm = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/hq/flier-template', { remove: true }); toast(t('Design removed'), t('Fliers use the WDOS background again.'), 'ok'); show(true); } catch (e) { err.textContent = e.message; } } }, t('Use the WDOS background'));
  file.addEventListener('change', async () => { err.textContent = ''; const f = file.files[0]; if (!f) return;
    try { const img = await shrinkImage(f, 1350); await api('/hq/flier-template', { image: img }); toast(t('Flier design saved'), t('Every flier WDOS makes now uses this design.'), 'ok'); file.value = ''; show(true); } catch (e) { err.textContent = e.message; file.value = ''; } });
  const box = card('Flier design', 'The background for every flier WDOS makes. Design it in Canva at 1080 by 1350 pixels (portrait), keep the left side calm for the words and the bottom right corner clear for the QR code, and upload it here.',
    h('div', { style: 'display:grid;gap:12px' }, field('Upload a flier design', file), err, h('div', {}, rm), prev));
  show(false); return box;
}

/* v109.59: the uploaded flier in each language: WDOS read its words, translated them and painted them over the picture.
 * The host checks each one, corrects the words of a language, or has the words read again. */
const LANG_NAMES = { en: 'English', fr: 'French', pt: 'Portuguese', sw: 'Swahili', ar: 'Arabic', ha: 'Hausa', yo: 'Yoruba', ig: 'Igbo' };
function pictureLangs(id, m, d) {
  const box = h('div', { style: 'display:grid;gap:12px' }); const status = h('small', { class: 'muted', 'aria-live': 'polite' });
  const redo = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { redo.disabled = true;
    try { const x = await renderEmailFliers(id, m, (msg) => { status.textContent = msg; }, { reread: true }); status.textContent = x.words ? tf('{1} parts of the flier were translated.', x.words) : t('WDOS found no words it could read on this picture.'); toast(t('Flier made in each language'), '', 'ok'); paint(await api(`/meetings/${id}/flier/langs`)); }
    catch (e) { status.textContent = e.message; } redo.disabled = false; } }, icon('globe', 16), d.words ? t('Read the words again and translate') : t('Translate the words on the picture'));
  const grid = h('div', { class: 'flier-langs' });
  const paint = (dd) => { d = dd; grid.replaceChildren(...['en', 'fr', 'pt', 'sw', 'ar', 'ha', 'yo', 'ig'].map((l) => { const slot = h('div');
    const url = (d.pictures || {})[l];
    return h('div', { class: 'flier-lang' }, url ? h('a', { href: url, target: '_blank', rel: 'noopener' }, h('img', { src: url, alt: t(LANG_NAMES[l]), loading: 'lazy' })) : h('div', { class: 'flier-lang__none', text: t('Not made yet') }),
      h('b', { text: t(LANG_NAMES[l]) }),
      l !== 'en' && d.words ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { if (slot.firstChild) { slot.replaceChildren(); return; }
        const cur = (d.words_by_lang || {})[l] || d.words.groups.map((g) => g.text);
        slot.replaceChildren(editForm(tf('The words on the flier in {1}', t(LANG_NAMES[l])), d.words.groups.map((g, i) => ({ k: 'g' + i, label: g.text.slice(0, 80), value: cur[i] || '', max: 600 })),
          async (v) => { const texts = d.words.groups.map((g, i) => v['g' + i]); await api(`/meetings/${id}/flier/words/${l}`, { texts });
            const c = await paintFlierWords(d.image, d.words, texts, l); await api(`/meetings/${id}/flier/render/${l}`, { image: c.toDataURL('image/jpeg', 0.88) });
            toast(t('Saved'), '', 'ok'); slot.replaceChildren(); paint(await api(`/meetings/${id}/flier/langs`)); }, () => slot.replaceChildren())); } }, icon('edit', 16), ' ', t('Correct the words')) : null, slot); })); };
  paint(d);
  box.append(h('hr', { class: 'sep' }), h('b', { text: t('Your flier picture in each language') }),
    h('small', { class: 'muted', text: t('WDOS reads the words on your picture, translates them and writes them over the picture in each language. Check each one; correct the words where needed.') }),
    h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, redo, status), grid);
  return box;
}
