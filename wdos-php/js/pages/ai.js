/* HQ: AI assistant. Every panel shows the numbers WDOS counted itself, and beside them a draft a person reads and decides on.
 * When no AI lane answers, the counted numbers are still here: nothing on this page depends on a provider being up. */
import { h, card, sk, empty, fmt, fmtDate, toast, i18n, listenBtn } from '../ui.js';
import { api } from '../api.js';

const WHY = {
  off: 'AI features are switched off in System rules. The counted figures below are complete without it.',
  no_key: 'No AI key is set on the server yet, so there is no written draft. The counted figures below are complete without it.',
  needs_paid_key: 'This draft uses words written by leaders, and the rule in System rules keeps that text off free AI lanes. It needs a paid key before real data.',
  daily_cap: 'Today\'s AI allowance is used up. Try again tomorrow. The counted figures below are complete without it.',
  all_failed: 'No AI lane answered just now. Nothing is broken: the counted figures below are complete. Try again in a few minutes.',
  nothing_recorded: 'No report or issue is recorded for this country and month, so there is nothing to summarise.',
};
const why = (r) => WHY[r] || WHY.all_failed;
const note = (text, kind = '') => h('p', { class: 'small' + (kind ? ' ' + kind : ''), style: 'margin:0', text });
const draft = (o, label) => h('div', { style: 'display:grid;gap:10px' },
  h('div', { class: 'tagrow' }, h('span', { class: 'tag', text: 'AI draft' }), h('span', { class: 'tag', text: 'A person decides' }), listenBtn(() => o.body)),
  h('div', { style: 'white-space:pre-wrap;line-height:1.6', text: o.body }),
  h('small', { class: 'faint', text: tf('{1} · written {2} (GMT) by lane {3}', label, fmtDate(o.created_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }), o.lane) }));
const stat = (label, v) => h('div', { class: 'card', style: 'padding:14px' }, h('small', { class: 'faint', text: label }), h('div', { class: 'display', style: 'font-size:1.6rem', text: v === null || v === undefined ? t0('not recorded') : fmt(v) }));
const t0 = (s) => (globalThis.tr ? globalThis.tr(s) : s);
const WHYGAP = { no_leader: 'No leader seated', no_chapter: 'No chapter', waiting: 'Members waiting for a chapter', below_30: 'Fewer than 30 members' };
const LABEL = { members_total: 'All members', joined_last_7_days: 'Joined in the last 7 days', joined_previous_7_days: 'Joined in the 7 days before', wgmn_joined_last_7_days: 'WGMN joined, 7 days', wnnn_joined_last_7_days: 'WNNN joined, 7 days', countries_with_members: 'Countries with members',
  members_active_last_7_days: 'Members active, 7 days', chapters_total: 'Chapters', chapters_opened_last_7_days: 'Chapters opened, 7 days', chapters_at_capacity: 'Chapters at capacity', meetings_held_last_7_days: 'Meetings held, 7 days', reports_submitted_last_7_days: 'Reports submitted, 7 days', reports_approved_last_7_days: 'Reports approved, 7 days', leaders_seated: 'Leaders seated', leaders_appointed_last_7_days: 'Leaders appointed, 7 days',
  reports_overdue: 'Reports overdue', issues_open: 'Issues open', issues_open_red_or_critical: 'Issues open, red or critical', private_reports_open: 'Private reports open', members_waiting_for_a_chapter: 'Members waiting for a chapter', chapters_with_no_leader: 'Chapters with no leader', phone_notifications_failed_last_7_days: 'Phone notifications failed, 7 days' };

export async function render(root) {
  const status = h('div', {}, sk.rows(3)), digest = h('div', {}, sk.rows(4)), brief = h('div', {}, sk.rows(4)), gaps = h('div', {}, sk.rows(4));
  root.append(card('AI assistant', 'AI writes drafts and points to where to look. It never appoints, sanctions, publishes or sends. Every number on this page is counted by WDOS from its own records.', status),
    card('Monthly report digest', 'What the leadership reports of one country say for one month: done, stuck and repeating issues.', digest),
    card('Weekly brief', 'One page from live counts: growth, activity, concerns and wins. Written every Monday when an AI key is set, or on request.', brief),
    card('Coverage gap finder', 'Where to recruit and appoint next. This is arithmetic on WDOS records, not a model: areas with members and thin structure come first.', gaps));

  /* ---- status and usage ---- */
  async function loadStatus() {
    let d; try { d = await api('/hq/ai'); } catch (e) { status.replaceChildren(empty('x', 'The AI status did not load', e.message)); return; }
    const lanes = d.lanes.length ? h('div', { class: 'tagrow' }, d.lanes.map((l) => h('span', { class: 'tag', text: l.lane + (l.paid ? ' · paid' : ' · free') }))) : note(why(d.enabled ? 'no_key' : 'off'));
    const test = h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { test.disabled = true; try { const r = await api('/hq/ai/test', {}); r.ok ? toast('AI answered', tf('Lane used: {1}', r.lane), 'ok') : toast('No AI answer', why(r.reason), 'bad'); } catch (e) { toast('No AI answer', e.message, 'bad'); } test.disabled = false; loadStatus(); } }, 'Test the AI lanes');
    const use = d.usage.length ? h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Feature', 'Lane', 'Calls', 'Succeeded', 'From cache', 'Average time', 'Last used'].map((x) => h('th', { text: x })))),
      h('tbody', {}, d.usage.map((u) => h('tr', {}, h('td', { text: u.feature }), h('td', { text: u.lane || '-' }), h('td', { text: fmt(u.calls) }), h('td', { text: fmt(u.succeeded) }), h('td', { text: fmt(u.from_cache) }), h('td', { text: u.avg_ms === null ? '-' : fmt(u.avg_ms) + ' ms' }), h('td', { text: fmtDate(u.last_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) })))))) : note('No AI call has been made in the last 30 days.');
    const fails = d.recent_failures.length ? h('div', { class: 'list' }, d.recent_failures.map((f) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: f.feature + ' · ' + f.lane }), h('small', { text: f.error })), h('small', { class: 'faint', text: fmtDate(f.created_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) })))) : null;
    status.replaceChildren(h('div', { style: 'display:grid;gap:14px' }, h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, h('b', { text: 'Lanes ready on this server' }), lanes, d.lanes.length ? test : null),
      note(tf('Used today: {1} of {2} calls. Results are kept and reused, so opening this page costs nothing.', fmt(d.used_today), fmt(d.daily_cap))),
      note(d.free_lane_text === 'off' ? 'Rule in force: text written by leaders never goes to a free AI lane.' : 'Rule in force: names, emails, phone numbers and links are removed before any text written by leaders goes to a free AI lane. Private reports are never sent to any lane.'),
      h('b', { text: 'AI calls, last 30 days' }), use, fails ? h('b', { text: 'Recent failures' }) : null, fails));
  }

  /* ---- 1. report digest ---- */
  const lastMonth = (() => { const d = new Date(); d.setUTCDate(1); d.setUTCMonth(d.getUTCMonth() - 1); return d.toISOString().slice(0, 7); })();
  const month = h('input', { class: 'input', type: 'month', value: lastMonth, max: new Date().toISOString().slice(0, 7), style: 'max-width:190px', 'aria-label': 'Month' }), country = h('select', { class: 'input', style: 'max-width:260px', 'aria-label': 'Country' });
  const dBody = h('div', {}, sk.rows(3)); let filled = false;
  const showDigest = (d, problem) => {
    const f = d.facts, parts = [];
    if (!f) { dBody.replaceChildren(empty('book', 'No leadership report has been written yet', 'A digest appears here once leaders submit monthly reports.')); return; }
    parts.push(h('div', { style: 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px' }, stat('Reports this month', f.reports.total), stat('Submitted, waiting for review', f.reports.submitted), stat('Approved', f.reports.approved), stat('Returned for correction', f.reports.returned)),
      h('div', { style: 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px' }, stat('Still in draft', f.reports.draft), stat('Late', f.reports.late), stat('No-activity declarations', f.reports.nil), stat('Open issues', f.open_issues)));
    if (f.repeating.length) parts.push(h('div', {}, h('b', { text: 'Issue categories seen this month and last month' }), h('div', { class: 'tagrow', style: 'margin-top:8px' }, f.repeating.map((r) => h('span', { class: 'tag', text: tf('{1}: {2} this month, {3} last month', r.category, r.this_month, r.last_month) })))));
    if (problem) parts.push(note(why(problem), 'faint'));
    if (d.digest) parts.push(draft(d.digest, f.country + ' ' + f.period), note('Names, emails, phone numbers and links were removed before the text left WDOS. Needs a paid key before unredacted data.', 'faint'));
    const go = h('button', { class: 'btn', type: 'button', onclick: async () => { go.disabled = true; go.textContent = t0('Writing…'); try { const r = await api('/hq/ai/report-digest', { country: country.value, period: month.value, lang: i18n.lang }); showDigest({ facts: r.facts, digest: r.digest || d.digest }, r.ok ? '' : r.reason); if (r.ok) toast('Digest written', '', 'ok'); } catch (e) { toast('Not written', e.message, 'bad'); go.disabled = false; go.textContent = t0('Write the digest'); } loadStatus(); } }, d.digest ? 'Write it again' : 'Write the digest');
    if (f.reports.total || f.issues.length) parts.push(h('div', {}, go)); else parts.push(note(why('nothing_recorded')));
    dBody.replaceChildren(h('div', { style: 'display:grid;gap:14px' }, parts));
  };
  async function loadDigest() {
    dBody.replaceChildren(sk.rows(3)); let d;
    try { d = await api('/hq/ai/report-digest?period=' + encodeURIComponent(month.value) + '&country=' + encodeURIComponent(country.value || '') + '&lang=' + i18n.lang); } catch (e) { dBody.replaceChildren(empty('x', 'The digest did not load', e.message)); return; }
    if (!filled) { filled = true; country.replaceChildren(...d.countries.map((c) => h('option', { value: c.code, text: c.name + ' (' + c.reports + ')' }))); if (d.country) country.value = d.country; }
    showDigest(d, '');
  }
  month.addEventListener('change', loadDigest); country.addEventListener('change', loadDigest);
  digest.replaceChildren(h('div', { style: 'display:grid;gap:14px' }, h('div', { style: 'display:flex;gap:12px;flex-wrap:wrap' }, country, month), dBody));

  /* ---- 3. weekly brief ---- */
  const showBrief = (d, problem) => {
    const groups = ['growth', 'activity', 'concerns'].map((g) => h('div', {}, h('b', { text: g === 'growth' ? 'Growth' : g === 'activity' ? 'Activity' : 'Concerns' }), h('div', { style: 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px', style: 'margin-top:8px' }, Object.entries(d.facts[g]).filter(([, v]) => !Array.isArray(v)).map(([k, v]) => stat(LABEL[k] || k, v)))));
    const top = d.facts.growth.top_countries_last_7_days || [];
    const go = h('button', { class: 'btn', type: 'button', onclick: async () => { go.disabled = true; go.textContent = t0('Writing…'); try { const r = await api('/hq/ai/brief', { lang: i18n.lang }); showBrief({ facts: r.facts, brief: r.brief || d.brief, period: r.period }, r.ok ? '' : r.reason); if (r.ok) toast('Brief written', '', 'ok'); } catch (e) { toast('Not written', e.message, 'bad'); go.disabled = false; } loadStatus(); } }, d.brief ? 'Write it again' : 'Write this week\'s brief');
    brief.replaceChildren(h('div', { style: 'display:grid;gap:16px' }, d.brief ? draft(d.brief, tf('Week {1}', d.period)) : null, problem ? note(why(problem), 'faint') : null, h('div', {}, go), ...groups,
      top.length ? h('div', {}, h('b', { text: 'Where women joined this week' }), h('div', { class: 'tagrow', style: 'margin-top:8px' }, top.map((c) => h('span', { class: 'tag', text: c.country + ': ' + fmt(c.joined) })))) : null));
  };
  async function loadBrief() { try { showBrief(await api('/hq/ai/brief?lang=' + i18n.lang), ''); } catch (e) { brief.replaceChildren(empty('x', 'The brief did not load', e.message)); } }

  /* ---- 6. coverage gaps ---- */
  async function loadGaps() {
    let d; try { d = await api('/hq/ai/coverage-gaps'); } catch (e) { gaps.replaceChildren(empty('x', 'The coverage gaps did not load', e.message)); return; }
    if (!d.areas.length && !d.thin_countries.length) { gaps.replaceChildren(empty('check', 'No gap found', 'Every area with members has a leader and a chapter.')); return; }
    const table = h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Priority', 'Country', 'State or region', 'Members', 'Joined, 30 days', 'Chapters', 'Leaders', 'Waiting', 'Why it is listed'].map((x) => h('th', { text: x })))),
      h('tbody', {}, d.areas.slice(0, 25).map((a, i) => h('tr', {}, h('td', { text: String(i + 1) }), h('td', { text: a.country }), h('td', { text: a.area === '(not recorded)' ? t0('not recorded') : a.area }), h('td', { text: fmt(a.members) }), h('td', { text: fmt(a.joined_30) }), h('td', { text: fmt(a.chapters) }), h('td', { text: fmt(a.leaders) }), h('td', { text: fmt(a.waiting) }), h('td', {}, h('div', { class: 'tagrow' }, a.why.map((w) => h('span', { class: 'tag', text: WHYGAP[w] || w })))))))));
    gaps.replaceChildren(h('div', { style: 'display:grid;gap:16px' }, note(tf('{1} areas have a gap. The first 25 are shown, ranked by: no leader, no chapter, members waiting, and how many women joined there in 30 days.', fmt(d.areas_total))), table,
      d.thin_countries.length ? h('div', {}, h('b', { text: 'Countries with fewer than 30 members or no country leader' }), h('div', { class: 'tagrow', style: 'margin-top:8px' }, d.thin_countries.map((c) => h('span', { class: 'tag', text: tf('{1}: {2} members, {3} country leaders', c.country, fmt(c.members), fmt(c.country_leaders)) })))) : null,
      d.african_countries_with_no_member.length ? h('div', {}, h('b', { text: 'African countries with no member yet' }), h('p', { class: 'small', style: 'margin:8px 0 0', text: d.african_countries_with_no_member.join(', ') })) : null));
  }
  loadStatus(); loadDigest(); loadBrief(); loadGaps();
}
