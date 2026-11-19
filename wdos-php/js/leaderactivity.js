/* v109.25: HQ sees everything volunteer leaders do, as it happens: tasks, calls, group calls, meetings, reports, messages,
 * field work, chapter posts, sign-ins and the rest. Every row opens what it is about (the task, the meeting, the report,
 * the help desk thread, the member), or, for calls, messages, field records and sign-ins, a detail panel. Every name
 * opens that leader's own timeline, with one touch to message her, ring her, open her dashboard or her team profile.
 * The list refreshes itself every 15 seconds; new items are marked. */
import { h, t, tf, icon, empty, sk, fmt, ago, netTag, avatar, toast, fmtDate } from './ui.js';
import { api } from './api.js';
import { go } from './app.js';

const KICON = { tasks: 'tasks', calls: 'phone', meetings: 'calendar', reports: 'book', messages: 'send', field: 'pin', access: 'key', other: 'check' };
const ST_BAD = new Set(['missed', 'declined', 'cancelled', 'returned', 'escalated', 'no', 'absent', 'hidden', 'escalated']);
const mins = (s) => s == null ? '' : s < 60 ? tf('{1} seconds', s) : tf('{1} min', Math.round(s / 60));
const CST = { ringing: 'Ringing', answered: 'Answered', ended: 'Ended', missed: 'Missed', declined: 'Declined', cancelled: 'Not answered', live: 'On now', left: 'Left', joined: 'On the call', returned: 'Returned', escalated: 'Escalated', no: 'Cannot attend', absent: 'Absent', hidden: 'Hidden' };
const stl = (x) => t(CST[x] || x);
const when = (x) => x ? fmtDate(x, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '–';

/* ---- the detail panel ---- */
function panel(title, body) {
  document.querySelector('.hwpanel')?.remove();
  const close = () => wrap.remove();
  const wrap = h('div', { class: 'hwpanel', role: 'dialog', 'aria-modal': 'true', 'aria-label': title, onclick: (e) => { if (e.target === wrap) close(); } },
    h('div', { class: 'hwpanel__box card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
      h('div', { style: 'display:flex;align-items:center;gap:10px' }, h('h3', { style: 'margin:0;flex:1', text: title }), h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: close, 'aria-label': t('Close') }, '✕')),
      body)));
  document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); } });
  document.body.append(wrap); return close;
}
const personBtn = (p, onLeader) => p && p.id ? h('button', { type: 'button', class: 'linkish', onclick: () => onLeader(p.id) }, p.name) : h('span', { text: p ? p.name : '–' });
const line = (label, value) => h('div', { style: 'display:flex;gap:10px;justify-content:space-between;border-bottom:1px solid var(--line);padding:6px 0' }, h('small', { class: 'muted', text: t(label) }), typeof value === 'string' ? h('b', { text: value }) : value);

async function detail(it, onLeader) {
  const r = it.ref;
  const close = panel(t(it.what), h('div', {}, sk.rows(3)));
  let d; try { d = await api(`/hq/watch/item/${r.type}/${r.id}`); } catch (e) { close(); toast(e.message, '', 'error'); return; }
  const box = document.querySelector('.hwpanel .card__body'); const pick = (id) => { close(); onLeader(id); };
  let body;
  if (d.type === 'call') body = h('div', {}, line('From', personBtn(d.caller, pick)), line('To', personBtn(d.callee, pick)), line('Status', stl(d.status)), line('Rang', when(d.rang_at)), line('Answered', when(d.answered_at)),
    line('Ended', when(d.ended_at)), line('Length', d.seconds != null ? mins(d.seconds) : '–'), h('p', { class: 'muted', text: t(d.note) }),
    d.between.length > 1 ? h('div', {}, h('b', { text: t('Calls between them') }), ...d.between.map((c) => h('small', { style: 'display:block', text: `${when(c.created_at)} · ${stl(c.status)}` }))) : null);
  else if (d.type === 'gcall') body = h('div', {}, line('Started by', personBtn(d.host, pick)), line('Status', t(d.status === 'live' ? 'On now' : 'Ended')), line('Started', when(d.started_at)), line('Length', d.seconds != null ? mins(d.seconds) : '–'),
    h('b', { style: 'display:block;margin-top:10px', text: t('Who was on it') }),
    h('div', { class: 'list list--stack' }, d.people.map((p) => h('div', { class: 'list__link', style: 'display:flex;gap:10px;align-items:center' }, h('div', { class: 'grow' }, personBtn(p, pick), h('small', { style: 'display:block', text: p.title })),
      h('span', { class: 'tag' + (ST_BAD.has(p.status) ? ' tag--bad' : ''), text: t({ joined: 'On the call', left: 'Was on the call', ringing: 'Ringing', missed: 'Did not answer', declined: 'Declined' }[p.status] || p.status) }), p.seconds ? h('small', { text: mins(p.seconds) }) : null))),
    h('p', { class: 'muted', text: t(d.note) }));
  else if (d.type === 'dm') body = h('div', {}, line('From', personBtn(d.from, pick)), line('To', d.several ? tf('{1} people', d.several.recipients) : personBtn(d.to, pick)), line('Subject', d.subject || '–'), line('Sent', when(d.at)),
    line('Messages between them', String(d.thread_count)),
    d.can_read ? h('div', { style: 'display:grid;gap:8px;margin-top:8px' }, ...d.thread.map((m) => h('div', { class: 'panel' }, h('small', { class: 'muted', text: `${m.from} · ${when(m.at)}` }), m.subject ? h('b', { style: 'display:block', text: m.subject }) : null, h('p', { style: 'margin:4px 0 0;white-space:pre-wrap', text: m.body }))))
      : h('p', { class: 'panel', text: t('The words of messages between leaders are private. WODDI HQ can choose to read them: switch it on at the top of the activity page. Each reading is recorded.') }));
  else if (d.type === 'record') { const x = d.record; body = h('div', {}, line('By', personBtn(d.by, pick)), line('What', x.what), line('Kind', x.category || '–'), line('When', when(x.happened_at)), line('People reached', String(x.people_reached ?? 0) + (x.people_category ? ' · ' + x.people_category : '')),
    line('Result', x.result || '–'), line('Follow-up', x.follow_up_action ? `${x.follow_up_action}${x.follow_up_due ? ' · ' + fmtDate(x.follow_up_due) : ''}${Number(x.follow_up_done) ? ' · ' + t('done') : ''}` : '–'), Number(x.escalate) ? h('span', { class: 'tag tag--bad', text: t('Raised to her supervisor') }) : null); }
  else if (d.type === 'signin') body = h('div', {}, line('Who', personBtn(d.who, pick)), line('Last seen', d.last_seen_at ? ago(d.last_seen_at) : '–'), h('b', { style: 'display:block;margin-top:10px', text: t('Recent sign-ins') }),
    ...d.sessions.map((s) => h('small', { style: 'display:block;padding:4px 0', text: `${when(s.at)} · ${t(s.method || 'password')} · ${s.device}` })));
  box.lastChild.replaceWith(body);
}

/* ---- one row ---- */
function row(it, onLeader, fresh) {
  const open = () => { if (it.link) go(it.link); else if (['call', 'gcall', 'dm', 'record', 'signin'].includes(it.ref.type) && it.ref.id) detail(it, onLeader); };
  const L = it.leader || { name: '–' };
  return h('div', { class: 'list__link hwrow' + (fresh ? ' is-new' : ''), role: 'button', tabindex: 0, onclick: open, onkeydown: (e) => { if (e.key === 'Enter') open(); }, style: 'display:flex;gap:10px;align-items:flex-start;cursor:pointer' },
    h('div', { class: 'nrow__ico' }, icon(KICON[it.kind] || 'check', 18)),
    h('div', { class: 'grow', style: 'min-width:0;display:grid;gap:2px' },
      h('div', {}, h('button', { type: 'button', class: 'linkish', onclick: (e) => { e.stopPropagation(); onLeader(it.leader_id); } }, L.name), h('span', { text: ' · ' + t(it.what) }),
        it.with ? h('span', {}, ' · ', h('button', { type: 'button', class: 'linkish', onclick: (e) => { e.stopPropagation(); onLeader(it.with.id); } }, it.with.name)) : null),
      h('small', { text: [L.title, L.country].filter(Boolean).join(' · ') }),
      it.detail ? h('small', { class: 'muted', style: 'overflow-wrap:anywhere', text: it.detail }) : null),
    h('div', { style: 'flex:none;display:grid;gap:4px;justify-items:end' }, L.network ? netTag(L.network) : null,
      it.status && ST_BAD.has(it.status) ? h('span', { class: 'tag tag--bad', text: stl(it.status) }) : null, h('small', { text: ago(it.at) }), icon('chevright', 14)));
}

/** The HQ dashboard card: the latest things leaders did, live. */
export function leaderActivityPanel() {
  const box = h('div', { style: 'display:grid;gap:12px' }, sk.rows(4)); const list = h('div', { class: 'list list--stack' });
  const toLeader = (id) => go('#/hq/activity/' + id);
  let last = '', timer = null;
  const load = async () => {
    if (!box.isConnected && last) return;
    try { const r = await api('/hq/watch?days=7' + (last ? '&after=' + encodeURIComponent(last) : ''));
      if (!last) { box.replaceChildren(r.items.length ? list : empty('users', 'Nothing yet', 'When leaders give tasks, send messages, ring their team or hold meetings, it shows here.'), h('a', { class: 'btn btn--ghost btn--s', href: '#/hq/activity', style: 'justify-self:start' }, t('Open the full view'))); }
      r.items.slice().reverse().forEach((it) => list.prepend(row(it, toLeader, !!last)));
      while (list.children.length > 8) list.lastChild.remove();
      last = r.now;
    } catch (e) { if (!last) box.replaceChildren(empty('x', 'This did not load', e.message)); }
    timer = setTimeout(load, 20000);
  };
  load(); return box;
}

/** The full page. #/hq/activity, or #/hq/activity/<leader id> for one leader's timeline. */
export async function render(root, params = {}) {
  let leader = Number(params.leader || 0) || 0;
  const sel = (opts) => h('select', { class: 'input' }, opts.map(([v, l]) => h('option', { value: v, text: t(l) })));
  const country = sel([['', 'All countries']]); const network = sel([['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']]);
  const days = sel([['1', 'Today and yesterday'], ['7', 'Last 7 days'], ['30', 'Last 30 days'], ['90', 'Last 90 days']]); days.value = leader ? '30' : '7';
  const chips = h('div', { class: 'tagrow', style: 'justify-content:flex-start;flex-wrap:wrap' }); const on = new Set();
  const head = h('div'); const list = h('div', { class: 'list list--stack' }); const listBox = h('div', {}, sk.rows(8));
  const live = h('span', { class: 'tag tag--wnnn', text: t('Live') }); let paused = false;
  const pauseBtn = h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { paused = !paused; pauseBtn.textContent = t(paused ? 'Resume' : 'Pause'); live.textContent = t(paused ? 'Paused' : 'Live'); live.className = 'tag' + (paused ? '' : ' tag--wnnn'); } }, t('Pause'));
  const readBox = h('label', { style: 'display:flex;gap:8px;align-items:center' });
  root.append(h('div', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
    h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('h2', { class: 'display', style: 'margin:0;font-size:22px;flex:1', text: t("Leaders' activity") }), live, pauseBtn),
    h('p', { class: 'muted', style: 'margin:0', text: t('Everything volunteer leaders do in WDOS, as it happens. Tap any line to open it; tap a name to see everything that leader has done.') }),
    h('div', { class: 'grid3', style: 'display:grid;gap:10px' }, country, network, days), chips, readBox)), head, h('section', { class: 'card', style: 'margin-top:20px' }, h('div', { class: 'card__body' }, listBox)));
  let last = '', timer = null, filled = false, gen = 0;
  const setLeader = (id) => { if (!id) { leader = 0; go('#/hq/activity'); return; } leader = id; go('#/hq/activity/' + id); };
  const q = (extra = {}) => new URLSearchParams({ days: days.value, country: country.value, network: network.value, leader: String(leader || ''), kinds: [...on].join(','), ...extra });
  function paintHead(r) {
    const L = r.leader;
    if (!L) { head.replaceChildren(); return; }
    head.replaceChildren(h('section', { class: 'card', style: 'margin-top:20px' }, h('div', { class: 'card__body', style: 'display:grid;gap:12px' },
      h('div', { style: 'display:flex;gap:14px;align-items:center;flex-wrap:wrap' }, avatar(L, 56), h('div', { class: 'grow', style: 'min-width:0' }, h('b', { style: 'font-size:18px', text: L.name }), h('small', { style: 'display:block', text: [L.title || t('No seat'), L.country].filter(Boolean).join(' · ') }),
        h('small', { style: 'display:block', class: L.last_seen_at ? 'muted' : 'bad', text: L.last_seen_at ? tf('Seen {1}', ago(L.last_seen_at)) : t('Not signed in yet') })), L.network ? netTag(L.network) : null),
      h('div', { style: 'display:flex;flex-wrap:wrap;gap:8px' },
        h('a', { class: 'btn', href: '#/inbox/to/' + L.id }, icon('send', 18), ' ', t('Message')),
        h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { const m = await import('./pages/calls.js'); m.ring(L.id, L.name, L.title); } }, icon('phone', 18), ' ', t('Call in WDOS')),
        L.phone ? h('a', { class: 'btn btn--ghost', href: 'tel:' + L.phone.replace(/[^\d+]/g, '') }, icon('phone', 18), ' ', t('Call her phone')) : null,
        L.has_seat ? h('a', { class: 'btn btn--ghost', href: '#/team/' + L.id }, icon('chart', 18), ' ', t('Her figures, reports and tasks')) : null,
        L.has_seat ? h('a', { class: 'btn btn--ghost', href: '#/leaders/' + L.id }, icon('eye', 18), ' ', t('Open her dashboard')) : null,
        h('a', { class: 'btn btn--ghost', href: '#/members/' + L.id }, icon('user', 18), ' ', t('Her member record')),
        h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => setLeader(0) }, t('Back to every leader'))))));
  }
  function paintChips(r) {
    chips.replaceChildren(...Object.entries(r.kinds).map(([k, label]) => h('button', { type: 'button', class: 'chip' + (on.has(k) ? ' is-on' : ''), 'aria-pressed': String(on.has(k)), onclick: () => { on.has(k) ? on.delete(k) : on.add(k); load(); } },
      icon(KICON[k], 14), ' ', t(label), ' ', h('b', { text: fmt(r.counts[k] || 0) }))));
    readBox.replaceChildren(h('input', { type: 'checkbox', checked: r.read_messages, style: 'width:20px;height:20px', onchange: async (e) => {
      const want = e.target.checked;
      if (want && !confirmText()) { e.target.checked = false; return; }
      try { await api('/hq/watch/read-messages', { on: want }); toast(t(want ? 'HQ can now read messages between leaders. Each reading is recorded.' : 'Messages between leaders are private again.'), '', 'ok'); } catch (err) { e.target.checked = !want; toast(err.message, '', 'error'); } } }),
      h('span', { text: t('HQ may read the words of messages between leaders (each reading is recorded)') }));
  }
  const confirmText = () => true;      // a dialog would block the page; the switch itself is the decision, and it is recorded
  async function load() {
    const my = ++gen; clearTimeout(timer); last = ''; listBox.replaceChildren(sk.rows(8));
    let r; try { r = await api('/hq/watch?' + q()); } catch (e) { listBox.replaceChildren(empty('x', 'This did not load', e.message)); return; }
    if (my !== gen) return;
    if (!filled) { filled = true; r.countries.forEach((c) => country.append(h('option', { value: c.cc, text: c.name || c.cc }))); }
    paintHead(r); paintChips(r);
    list.replaceChildren(...r.items.map((it) => row(it, setLeader, false)));
    listBox.replaceChildren(r.items.length ? list : empty('users', 'No activity', leader ? 'Nothing from this leader for these choices.' : 'Nothing for these choices.'));
    last = r.now; timer = setTimeout(() => tick(my), 15000);
  }
  async function tick(my) {
    if (my !== gen || !root.isConnected) return;
    if (!paused && document.visibilityState === 'visible') {
      try { const r = await api('/hq/watch?' + q({ after: last }));
        if (my !== gen) return;
        if (r.items.length) { if (!list.isConnected) listBox.replaceChildren(list); r.items.slice().reverse().forEach((it) => list.prepend(row(it, setLeader, true))); }
        last = r.now;
      } catch { /* weak network: try again */ }
    }
    timer = setTimeout(() => tick(my), 15000);
  }
  [country, network, days].forEach((x) => x.addEventListener('change', load));
  load();
}
