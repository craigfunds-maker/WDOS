/* Reporting and Accountability. One report per unit and period: the accountable leader writes it, deputies
 * contribute, the supervisor or HQ reviews. Report forms are drawn from the template definitions loaded
 * from the approved WDOS templates; system figures inside a report are counted, never typed. */
import { h, icon, avatar, flag, card, sk, empty, ago, fmt, fmtDate, toast, netTag } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { kpi } from './hq.js';

const TABS = [['', 'Dashboard'], ['mine', 'My reports'], ['records', 'Operational records'], ['review', 'Reports I supervise'], ['compliance', 'Compliance'], ['issues', 'Risks and escalations'], ['archive', 'Approved and archive']];
const ST = { draft: ['Draft', ''], submitted: ['Submitted', 'wgmn'], under_review: ['Under review', 'wgmn'], returned: ['Returned, action required', 'bad'], approved: ['Approved', 'wnnn'], escalated: ['Escalated', 'bad'], superseded: ['Superseded', ''], not_started: ['Not started', ''] };
const ACT = { submit: 'Submitted', resubmit: 'Resubmitted', return: 'Returned', approve: 'Approved', escalate: 'Escalated', version: 'New version' };
const CATS = { member_engagement: 'Member engagement or follow-up', mobilisation: 'Mobilisation or referral', leadership_followup: 'Leadership follow-up or supervision', recruitment_support: 'Recruitment or activation support', learning_support: 'Learning or Institute support', meeting: 'Meeting or coordination', programme: 'Programme or activity implementation', stakeholder: 'Stakeholder or partnership engagement', digital_support: 'Digital access or inclusion support', communication: 'Communication or visibility', task_completed: 'Task or deliverable completion', issue: 'Issue or escalation', other: 'Other approved activity' };
const ROUTES = { supervisor: 'Immediate supervisor', country: 'Country Representative', hofco: 'HOFCO', hq: 'HQ functional authority', management: 'Management' };
const ISSUE_ST = { open: 'Open', in_progress: 'In progress', awaiting_decision: 'Awaiting decision', resolved: 'Resolved', closed: 'Closed' };
const METRIC = { leadership: ['Leadership', { filled: 'Seats filled', active: 'Active leaders', inactive: 'Inactive (30 days)', appointments: 'Appointments this period', exits: 'Exits' }], membership: ['Membership', { opening: 'Opening', new: 'New', closing: 'Closing', active: 'Active (30 days)' }],
  compliance: ['Reporting compliance', { units: 'Direct units', expected: 'Expected', submitted: 'Submitted', on_time: 'On time', approved: 'Approved', returned: 'Returned', overdue: 'Overdue', not_submitted: 'Not submitted' }], issues: ['Risks and issues', { open: 'Open', red_critical: 'Red or critical', resolved: 'Resolved this period' }],
  activities: ['Operational records', { total: 'Records', people_reached: 'People reached', follow_ups_open: 'Follow-ups open', escalations: 'Escalations' }] };
const tag = (s) => h('span', { class: 'tag' + (ST[s]?.[1] ? ' tag--' + ST[s][1] : ''), text: ST[s]?.[0] || s });
const tplTitle = (code, list) => list.find((t) => t.code === code)?.title || code.replace(/_/g, ' ');
const periodLabel = (r) => r.template.startsWith('monthly') ? fmtDate(r.period_start, { month: 'long', year: 'numeric' }) : r.period_start === r.period_end ? fmtDate(r.period_start) : `${fmtDate(r.period_start, { day: 'numeric', month: 'short' })} to ${fmtDate(r.period_end)}`;
const input = (attrs) => h('input', { class: 'input', ...attrs });
const field = (label, node, hint) => h('label', { class: 'field' }, h('span', { text: label }), node, hint ? h('small', { text: hint }) : null);

export async function render(root, params) {
  const tab = params.tab || '';
  if (tab === 'r' && params.id) return reportView(root, params.id);
  if (tab === 'new' && params.id) { try { const r = await api('/reporting/open', { template: params.id }); return go('#/reporting/r/' + r.id); } catch (e) { root.append(h('div', { class: 'card' }, empty('x', 'This report could not be opened', e.message, h('a', { class: 'btn btn--quiet btn--s', href: '#/reporting', text: 'Back to Reporting' })))); return; } }
  const hq = app.user.is_hq;
  const tabs = TABS.filter(([k]) => !(hq && (k === 'mine' || k === 'records')));
  root.append(h('div', { class: 'seg rtabs', role: 'tablist' }, tabs.map(([k, l]) => h('button', { type: 'button', role: 'tab', 'aria-pressed': String(k === tab), onclick: () => go('#/reporting' + (k ? '/' + k : '')), text: l }))));
  const body = h('div', { style: 'display:grid;gap:20px' }); root.append(body);
  let home; try { home = await api('/reporting/home'); } catch (e) { body.append(h('div', { class: 'card' }, empty('x', 'Reporting did not load', e.message))); return; }
  const views = { '': dashboard, mine: (b, hm) => listTab('mine', b, hm), review: (b, hm) => listTab('review', b, hm), archive: (b, hm) => listTab('archive', b, hm), records, compliance, issues };
  (views[tab] || dashboard)(body, home);
}

/* ------------------------------------------------------------ dashboard -- */
function dashboard(body, d) {
  const c = d.counts, hq = d.is_hq;
  if (!d.templates.length) body.append(h('div', { class: 'card' }, empty('inbox', 'Report forms are not loaded yet', hq ? 'Open /api/setup, section 7, to load the approved report templates from the current WDOS. Operational records and issues already work.' : 'WODDI HQ is loading the report forms. You can already record activity and raise issues.', hq ? h('a', { class: 'btn btn--quiet btn--s', href: '/api/setup', text: 'Open setup' }) : null)));
  body.append(h('div', { class: 'row row--4' },
    hq ? kpi('inbox', 'Awaiting HQ review', c.awaiting_review, [h('span', { text: 'From units reporting to HQ' })]) : kpi('clock', 'Reports to finish', c.due, [h('span', { text: c.returned ? `${c.returned} returned for correction` : 'Drafts and returned reports' })]),
    kpi('check', 'Approved this month', c.approved_month, [h('span', { text: hq ? 'Across both networks' : 'For your unit' })]),
    hq ? kpi('users', 'Units reporting to HQ', d.supervisor?.units || 0, [h('span', { text: d.supervisor ? `${d.supervisor.overdue} overdue for ${fmtDate(d.supervisor.period_start, { month: 'long' })}` : 'No leaders seated yet' })]) : kpi('inbox', 'Awaiting my review', c.awaiting_review, [h('span', { text: 'From units below your seat' })]),
    kpi('shield', 'Open issues', c.open_issues, [h('span', { text: 'Risks and escalations in scope' })])));
  if (!hq) {
    const u = d.unit;
    if (!u) body.append(h('div', { class: 'card' }, empty('award', 'No leadership unit', 'Formal reports belong to a leadership unit. Once HQ seats you, your unit\'s reports appear here.')));
    else {
      const m = d.monthly; const actions = h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' });
      if (m) actions.append(m.id ? h('a', { class: 'btn', href: '#/reporting/r/' + m.id, text: m.status === 'returned' ? 'Correct and resubmit' : ['draft'].includes(m.status) ? 'Continue monthly report' : 'Open monthly report' }) : u.principal ? h('a', { class: 'btn', href: '#/reporting/new/monthly_leadership', text: 'Start monthly report' }) : h('span', { class: 'muted', text: `${u.accountable || 'The accountable leader'} opens the report. You contribute once it is open.` }));
      d.templates.filter((t) => t.code !== 'monthly_leadership' && u.principal).forEach((t) => actions.append(h('a', { class: 'btn btn--quiet', href: '#/reporting/new/' + t.code, text: t.title })));
      actions.append(h('a', { class: 'btn btn--ghost', href: '#/reporting/records', text: 'Record activity' }), h('a', { class: 'btn btn--ghost', href: '#/reporting/issues', text: 'Raise an issue' }));
      body.append(card(`My unit: ${u.label}`, `${u.role}. Reporting supervisor: ${u.supervisor}.`, h('div', { style: 'display:grid;gap:14px' },
        m ? h('p', {}, `Monthly report for ${fmtDate(m.period_start, { month: 'long', year: 'numeric' })}: due ${fmtDate(m.due_on)}. `, tag(m.status), m.overdue ? h('span', { class: 'tag tag--bad', style: 'margin-left:6px', text: 'Overdue' }) : null) : null, actions)));
    }
  }
  if (d.supervisor) {
    const s = d.supervisor;
    body.append(card(hq ? 'Units reporting to HQ' : 'My reporting structure', `Monthly report for ${fmtDate(s.period_start, { month: 'long', year: 'numeric' })}, due ${fmtDate(s.due_on)}. Exceptions first.`,
      h('div', { class: 'row row--4' }, [['Overdue', s.overdue, 'bad'], ['Returned', s.returned, 'bad'], ['Awaiting review', s.submitted, 'wgmn'], ['Approved', s.approved, 'wnnn']].map(([l, n, tone]) => h('div', { class: 'panel' }, h('div', { class: 'kpi__value', style: 'font-size:30px', text: fmt(n) }), h('span', { class: 'tag tag--' + tone, style: 'justify-self:start', text: l })))),
      h('a', { class: 'btn btn--ghost btn--s', href: '#/reporting/compliance', text: 'Open compliance' })));
  }
}

/* ------------------------------------------------------- lists of reports -- */
async function listTab(kind, body, home) {
  const box = h('div', {}, sk.rows(4));
  const titles = { mine: ['My reports', 'Drafts, submitted and returned reports for your unit'], review: ['Reports I supervise', 'Submitted by the units directly below you'], archive: ['Approved and archive', 'Approved reports are locked. Superseded versions stay here with their history.'] };
  body.append(card(titles[kind][0], titles[kind][1], box));
  let r; try { r = await api('/reporting/list?tab=' + kind); } catch (e) { box.replaceChildren(empty('x', 'Reports did not load', e.message)); return; }
  if (!r.items.length) { box.replaceChildren(empty('inbox', { mine: 'No reports yet', review: 'Nothing awaits your review', archive: 'Nothing archived yet' }[kind], { mine: 'Start your monthly report from the Dashboard tab.', review: 'Reports appear here when the units below you submit them.', archive: 'Approved reports are kept here.' }[kind])); return; }
  box.replaceChildren(h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Report', 'Unit', 'Period', 'Status', 'Submitted'].map((c, i) => h('th', { class: i === 1 || i === 4 ? 'hide-s' : '', text: c })))),
    h('tbody', {}, r.items.map((x) => { const tr = h('tr', { tabindex: 0 }, h('td', {}, h('b', { text: tplTitle(x.template, home.templates) }), h('div', { class: 'faint num', style: 'font-size:12.5px', text: `${x.report_no || ''} · v${x.version}` })), h('td', { class: 'hide-s' }, x.unit_label, x.owner ? h('div', { class: 'faint', style: 'font-size:12.5px', text: x.owner }) : null), h('td', { text: periodLabel(x) }), h('td', {}, tag(x.status), x.on_time === false ? h('span', { class: 'tag tag--bad', style: 'margin-left:6px', text: 'Late' }) : null), h('td', { class: 'hide-s faint', text: x.submitted_at ? ago(x.submitted_at) : '' }));
      const open = () => go('#/reporting/r/' + x.id); tr.addEventListener('click', open); tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); }); return tr; })))));
}
/* ---------------------------------------------------------- one report -- */
async function reportView(root, id) {
  root.append(h('a', { href: '#/reporting', text: 'Back to Reporting' }));
  const host = h('div', { style: 'display:grid;gap:20px' }, sk.chart(180), sk.chart(260)); root.append(host);
  let d; try { d = await api('/reporting/report/' + id); } catch (e) { host.replaceChildren(h('div', { class: 'card' }, empty('x', 'This report did not open', e.message))); return; }
  const r = d.report, t = d.template, rights = d.rights, edit = rights.edit; const answers = r.answers && typeof r.answers === 'object' ? r.answers : {};
  const state = { is_nil: r.is_nil, nil_reason: r.nil_reason || '' };
  const saved = h('span', { class: 'faint', style: 'font-size:13px', 'aria-live': 'polite', text: edit ? 'Changes save as you type' : '' });
  let timer; const save = () => { if (!edit) return; saved.textContent = 'Saving…'; clearTimeout(timer); timer = setTimeout(async () => { try { await api(`/reporting/report/${id}/save`, { answers, is_nil: state.is_nil, nil_reason: state.nil_reason }); saved.textContent = 'Saved ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); } catch (e) { saved.textContent = e.message; } }, 800); };
  const flush = async () => { clearTimeout(timer); if (edit) await api(`/reporting/report/${id}/save`, { answers, is_nil: state.is_nil, nil_reason: state.nil_reason }); };
  const out = [];
  out.push(h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { class: 'display', text: t ? t.title : r.template }), h('p', { text: `${r.unit_label}. ${periodLabel(r)}.` }),
    h('div', { class: 'hero__meta' }, tag(r.status), h('span', { class: 'tag num', text: `${r.report_no} · v${r.version}` }), r.due_on ? h('span', { class: 'tag', text: 'Due ' + fmtDate(r.due_on) }) : null, h('span', { class: 'tag', text: 'Accountable leader: ' + (r.owner || '') }), h('span', { class: 'tag', text: 'Supervisor: ' + r.supervisor }), r.on_time === false ? h('span', { class: 'tag tag--bad', text: 'Submitted late' }) : null)),
    h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => window.print() }, 'Print or save as PDF')));
  if (!t) { out.push(h('div', { class: 'card' }, empty('inbox', 'This report\'s form is not loaded', 'The template for this report type is missing. HQ can load it from /api/setup, section 7.'))); host.replaceChildren(...out); return; }
  const returned = [...d.reviews].reverse().find((v) => v.action === 'return');
  if (r.status === 'returned' && returned) out.push(h('div', { class: 'panel', style: 'border-color:var(--bad)' }, h('b', { text: `Returned by ${returned.by_name}` }), h('p', { text: returned.note })));

  /* nil declaration */
  const sectionsWrap = h('div', { style: 'display:grid;gap:20px' });
  if (t.cadence === 'monthly' && (edit || r.is_nil)) {
    const nil = h('input', { type: 'checkbox', id: 'rp-nil', disabled: !edit }); nil.checked = state.is_nil;
    const reason = input({ placeholder: 'Reason for no activity', value: state.nil_reason, hidden: !state.is_nil, disabled: !edit, maxlength: 400 });
    nil.addEventListener('change', () => { state.is_nil = nil.checked; reason.hidden = !nil.checked; sectionsWrap.hidden = nil.checked; save(); }); reason.addEventListener('input', () => { state.nil_reason = reason.value; save(); });
    out.push(h('div', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:10px;padding-top:18px' }, h('label', { class: 'check', for: 'rp-nil' }, nil, h('span', { text: 'Nil or no-activity declaration for this period (reason and supervisor review required)' })), reason)));
    sectionsWrap.hidden = state.is_nil;
  }
  /* sections from the template */
  const fieldNode = (f) => {
    const dis = !edit; let node;
    if (f.type === 'rows') node = rowsNode(f);
    else if (f.type === 'check') { const c = h('input', { type: 'checkbox', disabled: dis }); c.checked = !!answers[f.key]; c.addEventListener('change', () => { answers[f.key] = c.checked; save(); }); return h('label', { class: 'check', 'data-key': f.key }, c, h('span', { text: f.label + (f.required ? ' *' : '') })); }
    else if (f.type === 'select') { node = h('select', { class: 'input', disabled: dis }, h('option', { value: '', text: 'Choose' }), (f.options || []).map((o) => h('option', { value: o, text: o }))); node.value = answers[f.key] ?? ''; node.addEventListener('change', () => { answers[f.key] = node.value; save(); }); }
    else if (f.type === 'textarea') { node = h('textarea', { class: 'input', rows: 4, disabled: dis }); node.value = answers[f.key] || ''; node.addEventListener('input', () => { answers[f.key] = node.value; save(); }); }
    else { node = input({ type: f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text', disabled: dis }); node.value = answers[f.key] ?? ''; node.addEventListener('input', () => { answers[f.key] = node.value; save(); }); }
    return h('div', { class: 'field', 'data-key': f.key }, h('span', { text: f.label + (f.required ? ' *' : '') }), node, f.hint ? h('small', { text: f.hint }) : null);
  };
  function rowsNode(f) {
    const rows = Array.isArray(answers[f.key]) ? answers[f.key] : (answers[f.key] = []); const box = h('div', { style: 'display:grid;gap:10px' }); const cols = f.cols || f.columns || [];
    const draw = () => { box.replaceChildren(); if (!rows.length) box.append(h('p', { class: 'faint', style: 'font-size:14px', text: 'No rows yet.' }));
      rows.forEach((row, i) => { const wide = cols.length > 5; const line = h('div', { class: 'rprow' + (wide ? ' rprow--wide' : ''), style: wide ? '' : `grid-template-columns:repeat(${cols.length},minmax(0,1fr)) auto` });
        cols.forEach((c) => { let inp; if (c.type === 'select') { inp = h('select', { class: 'input', disabled: !edit, 'aria-label': c.label }, h('option', { value: '', text: c.label }), (c.options || []).map((o) => h('option', { value: o, text: o }))); inp.value = row[c.key] ?? ''; inp.addEventListener('change', () => { row[c.key] = inp.value; save(); }); }
          else { inp = input({ type: c.type === 'date' ? 'date' : c.type === 'number' ? 'number' : 'text', placeholder: c.label, 'aria-label': c.label, disabled: !edit }); inp.value = row[c.key] ?? ''; inp.addEventListener('input', () => { row[c.key] = inp.value; save(); }); } line.append(inp); });
        line.append(edit ? h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Remove this row', onclick: () => { rows.splice(i, 1); draw(); save(); } }, icon('x', 18)) : h('span')); box.append(line); });
      const full = f.max && rows.length >= f.max;
      if (edit) box.append(h('div', { style: 'display:flex;gap:12px;align-items:center' }, h('button', { class: 'btn btn--ghost btn--s', type: 'button', disabled: full, onclick: () => { rows.push({}); draw(); save(); } }, icon('plus', 16), 'Add row'), f.max ? h('small', { class: 'faint', text: `${rows.length} of ${f.max}` }) : null)); };
    draw(); return box;
  }
  const NOT_YET = { pipeline: 'Recruitment pipeline', learning: 'Learning and WODDI Institute', programmes: 'Programmes', meetings: 'Meetings', meeting: 'Meeting record', tasks: 'Tasks and deliverables', support: 'Support cases' };
  const autoBlock = (key) => { const g = METRIC[key], data = d.snapshot?.[key];
    if (key === 'identity' && data) return h('dl', { class: 'dl' }, [['Unit', data.unit], ['Accountable leader', data.leader], ['Position', data.role], ['Reporting supervisor', data.supervisor], ['Period', data.period_start === data.period_end ? fmtDate(data.period_start) : `${fmtDate(data.period_start)} to ${fmtDate(data.period_end)}`], ['Due', data.due_on ? fmtDate(data.due_on) : ''], ['Report number', `${data.report_no}, version ${data.version}`]].filter((x) => x[1]).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })]));
    if (NOT_YET[key]) return h('div', { class: 'panel' }, h('b', { text: NOT_YET[key] }), h('p', { class: 'faint', style: 'font-size:13.5px', text: 'No system data for this section yet. This part of WDOS has not been moved to this version.' }));
    if (!g || !data) return h('div', { class: 'panel' }, h('b', { text: g ? g[0] : key.replace(/_/g, ' ') }), h('p', { class: 'faint', style: 'font-size:13.5px', text: 'No system data for this section yet.' }));
    return h('div', { class: 'panel' }, h('b', { text: g[0] + (r.frozen ? ' (as submitted)' : ' (live count)') }), h('div', { class: 'metrics' }, Object.entries(g[1]).filter(([k]) => k in data).map(([k, l]) => h('div', {}, h('div', { class: 'metrics__n num', text: fmt(data[k]) }), h('small', { text: l }))))); };
  for (const sec of t.sections) {
    const fin = sec.conditional === 'finance'; if (fin && !answers.has_finance && !edit) continue;
    const wrap = h('div', { style: 'display:grid;gap:14px', hidden: fin && !answers.has_finance }, (sec.fields || []).map(fieldNode));
    const finToggle = fin && edit ? (() => { const c = h('input', { type: 'checkbox' }); c.checked = !!answers.has_finance; c.addEventListener('change', () => { answers.has_finance = c.checked; wrap.hidden = !c.checked; save(); }); return h('label', { class: 'check' }, c, h('span', { text: 'This unit holds approved financial or resource responsibility this period' })); })() : null;
    sectionsWrap.append(card(`${sec.key}. ${sec.title}`, sec.hint || null, h('div', { style: 'display:grid;gap:14px' }, (sec.auto || []).map(autoBlock), finToggle, wrap)));
  }
  out.push(sectionsWrap);

  /* evidence (links) */
  const evBox = h('div'); const canAdd = edit || rights.contribute;
  const drawEv = (list) => evBox.replaceChildren(list.length ? h('div', { class: 'list' }, list.map((e) => h('div', {}, h('div', { class: 'grow' }, h('a', { href: e.url, target: '_blank', rel: 'noopener', text: e.url.length > 70 ? e.url.slice(0, 70) + '…' : e.url }), h('small', { text: [e.note, e.by_name, ago(e.created_at)].filter(Boolean).join(' · ') })), edit ? h('button', { class: 'btn btn--ghost btn--s', onclick: async () => { await api(`/reporting/report/${id}/evidence/${e.id}/delete`, {}); reload(); } }, 'Remove') : null))) : h('p', { class: 'faint', style: 'font-size:14px', text: 'No evidence attached yet.' }));
  drawEv(d.evidence);
  const eu = input({ placeholder: 'https://link to the record', type: 'url' }), en = input({ placeholder: 'What this evidence shows', maxlength: 300 });
  out.push(card('Evidence' + (t.requires_evidence ? ' (required)' : ''), 'Links to attendance records, approved photos or documents. Never attach passwords or confidential case detail.', h('div', { style: 'display:grid;gap:12px' }, evBox,
    canAdd ? h('div', { class: 'grid2' }, eu, en, h('div', {}, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { await api(`/reporting/report/${id}/evidence`, { url: eu.value.trim(), note: en.value }); reload(); } catch (e) { toast('Link not added', e.message, 'bad'); } } }, 'Add link'))) : null)));
  /* contributions */
  if (d.contributions.length || rights.contribute) { const note = h('textarea', { class: 'input', rows: 3, placeholder: 'Add your contribution to this unit report (evidence, delegated work, updates)' });
    out.push(card('Contributions from deputies and assistants', null, h('div', { style: 'display:grid;gap:12px' }, d.contributions.length ? h('div', { class: 'list' }, d.contributions.map((c) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: c.by_name }), h('p', { style: 'white-space:pre-wrap;font-size:14.5px', text: c.note })), h('small', { style: 'flex:none', text: ago(c.created_at) })))) : h('p', { class: 'faint', style: 'font-size:14px', text: 'No contributions yet.' }),
      rights.contribute ? h('div', { style: 'display:grid;gap:8px' }, note, h('div', {}, h('button', { class: 'btn btn--quiet btn--s', onclick: async () => { try { await api(`/reporting/report/${id}/contribution`, { note: note.value }); toast('Contribution added', '', 'ok'); reload(); } catch (e) { toast('Not added', e.message, 'bad'); } } }, 'Add contribution'))) : null))); }
  /* history */
  if (d.reviews.length) out.push(card('Submission and review history', null, h('div', { class: 'list' }, d.reviews.map((v) => h('div', {}, h('span', { class: 'tag' + (v.action === 'approve' ? ' tag--wnnn' : v.action === 'return' || v.action === 'escalate' ? ' tag--bad' : ' tag--wgmn'), text: ACT[v.action] || v.action }), h('div', { class: 'grow' }, h('b', { text: v.by_name }), h('small', { text: `v${v.version}` + (v.note ? ' · ' + v.note : '') })), h('small', { style: 'flex:none', text: ago(v.created_at) }))))));
  /* actions */
  const problems = h('div');
  const bar = h('div', { class: 'rpbar' }, saved, h('span', { class: 'grow' }));
  if (edit) bar.append(h('button', { class: 'btn', type: 'button', onclick: async (e) => { const b = e.currentTarget; b.disabled = true; problems.replaceChildren();
    try { await flush(); const res = await api(`/reporting/report/${id}/submit`, {});
      if (!res.submitted) { problems.replaceChildren(h('div', { class: 'panel', style: 'border-color:var(--bad)' }, h('b', { text: 'Cannot submit yet' }), h('p', { class: 'muted', style: 'font-size:14px', text: 'Complete the following before submitting:' }), h('ul', { style: 'margin:0;padding-left:20px' }, res.errors.map((x) => h('li', { text: x }))))); problems.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      else { toast(`Report ${res.report_no} submitted for review`, res.warnings.length ? 'Accepted with notes: ' + res.warnings.join(' ') : '', res.warnings.length ? 'info' : 'ok'); reload(); return; } }
    catch (ex) { toast('Not submitted', ex.message, 'bad'); } b.disabled = false; } }, r.status === 'returned' ? 'Resubmit report' : 'Submit report'));
  if (rights.review) { const note = h('textarea', { class: 'input', rows: 2, placeholder: 'Reviewer note (required when returning)' });
    const act = async (action) => { try { await api(`/reporting/report/${id}/review`, { action, note: note.value }); toast({ approve: 'Approved', return: 'Returned for correction', escalate: 'Escalated to HQ' }[action], '', 'ok'); reload(); } catch (e) { toast('Not recorded', e.message, 'bad'); } };
    out.push(card('Review', 'Approve it, return it with the reason, or escalate it above you.', h('div', { style: 'display:grid;gap:12px' }, note, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn btn--lime', onclick: () => act('approve') }, 'Approve'), h('button', { class: 'btn btn--danger', onclick: () => act('return') }, 'Return for correction'), app.user.is_hq ? null : h('button', { class: 'btn btn--ghost', onclick: () => act('escalate') }, 'Escalate'))))); }
  if (rights.new_version) bar.append(h('button', { class: 'btn btn--quiet', type: 'button', onclick: async () => { if (!confirm('Create a new version of this approved report? The approved version stays in the archive.')) return; const n = await api(`/reporting/report/${id}/new-version`, {}); go('#/reporting/r/' + n.id); } }, 'Correct (new version)'));
  out.push(problems, bar);
  host.replaceChildren(...out);
  function reload() { clearTimeout(timer); root.replaceChildren(); reportView(root, id); }
}

/* -------------------------------------------------- operational records -- */
async function records(body) {
  const f = { category: h('select', { class: 'input' }, Object.entries(CATS).map(([v, l]) => h('option', { value: v, text: l }))), happened_at: input({ type: 'datetime-local', value: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16) }), what: h('textarea', { class: 'input', rows: 3 }), people_reached: input({ type: 'number', min: 0, value: 0 }), people_category: input({ placeholder: 'e.g. members, young women, community leaders' }), result: h('textarea', { class: 'input', rows: 2 }), follow_up_action: input({ placeholder: 'What must happen next' }), follow_up_due: input({ type: 'date' }), evidence_ref: input({ placeholder: 'Link, file name or register reference' }), escalate: h('input', { type: 'checkbox' }) };
  const msg = h('p', { class: 'err', role: 'alert' }); const btn = h('button', { class: 'btn', type: 'submit', text: 'Save record' }); const list = h('div', {}, sk.rows(4));
  const form = h('form', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, field('Activity category', f.category), field('Date and time', f.happened_at)), field('What happened?', f.what), h('div', { class: 'grid2' }, field('People reached or involved', f.people_reached), field('Category of people', f.people_category)), field('Result or output', f.result), h('div', { class: 'grid2' }, field('Follow-up action', f.follow_up_action), field('Follow-up deadline', f.follow_up_due)), field('Evidence reference or link', f.evidence_ref), h('label', { class: 'check' }, f.escalate, h('span', { text: 'Escalation required' })), msg, h('div', {}, btn));
  form.addEventListener('submit', async (e) => { e.preventDefault(); msg.textContent = ''; btn.disabled = true;
    try { await api('/reporting/activity', { ...Object.fromEntries(Object.entries(f).map(([k, n]) => [k, n.type === 'checkbox' ? n.checked : n.value])) }); toast('Activity recorded', '', 'ok'); f.what.value = f.result.value = f.follow_up_action.value = f.evidence_ref.value = ''; f.people_reached.value = 0; f.escalate.checked = false; load(); } catch (ex) { msg.textContent = ex.message; } btn.disabled = false; });
  body.append(h('div', { class: 'row row--2' }, card('Record activity', 'Record once, close to the time it happens. This record feeds your reports and your supervisor\'s view; you will not be asked for it again.', form), card('Recent records in my scope', null, list)));
  async function load() { let r; try { r = await api('/reporting/activities'); } catch (e) { list.replaceChildren(empty('x', 'Records did not load', e.message)); return; }
    list.replaceChildren(r.items.length ? h('div', { class: 'list' }, r.items.map((a) => h('div', { style: 'align-items:flex-start' }, h('div', { class: 'grow' }, h('b', { style: 'white-space:normal', text: a.what }), h('small', { style: 'white-space:normal', text: [CATS[a.category], a.by_name, fmtDate(a.happened_at), a.people_reached ? `${fmt(a.people_reached)} reached` : '', a.result].filter(Boolean).join(' · ') }),
      a.follow_up_action ? h('small', { style: 'white-space:normal;color:' + (a.follow_up_done ? 'var(--ink-3)' : 'var(--warn)'), text: (a.follow_up_done ? 'Follow-up done: ' : 'Follow-up' + (a.follow_up_due ? ' by ' + fmtDate(a.follow_up_due) : '') + ': ') + a.follow_up_action }) : null),
      a.escalate ? h('span', { class: 'tag tag--bad', text: 'Escalated' }) : null, a.follow_up_action && !a.follow_up_done && a.user_id === app.user.id ? h('button', { class: 'btn btn--ghost btn--s', onclick: async () => { await api(`/reporting/activity/${a.id}/done`, {}); load(); } }, 'Mark done') : null)))
      : empty('inbox', 'No records yet', 'Each activity you record appears here and is counted in your monthly report.')); }
  load();
}

/* ------------------------------------------------------------ compliance -- */
async function compliance(body, home) {
  const box = h('div', {}, sk.rows(5)); const crumbs = h('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap' }); const trail = [];
  const tools = h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' });
  const c = card('Reporting compliance', 'Expected against submitted, by reporting unit. Open any unit that has units below it.', h('div', { style: 'display:grid;gap:14px' }, crumbs, box), tools); body.append(c);
  if (home.is_hq) { tools.append(h('button', { class: 'btn btn--quiet btn--s', onclick: async (e) => { e.currentTarget.disabled = true; try { const r = await api('/reporting/run-reminders', {}); toast('Reminders run', r.note || `${r.notices} notices, ${r.escalations} escalations. ${r.days_from_due < 0 ? -r.days_from_due + ' days before the due date' : r.days_from_due + ' days after the due date'}.`, 'ok'); } catch (ex) { toast('Not run', ex.message, 'bad'); } e.target.disabled = false; } }, 'Run reminders now'), h('button', { class: 'btn btn--ghost btn--s', onclick: () => configure(body) }, 'Configure')); }
  async function load(of) {
    box.replaceChildren(sk.rows(4)); let r; try { r = await api('/reporting/compliance' + (of ? '?of=' + of : '')); } catch (e) { box.replaceChildren(empty('x', 'Compliance did not load', e.message)); return; }
    crumbs.replaceChildren(h('button', { class: 'btn btn--ghost btn--s', disabled: !trail.length, onclick: () => { trail.length = 0; load(); } }, home.is_hq ? 'WODDI HQ' : 'My scope'), ...trail.map((t, i) => h('button', { class: 'btn btn--ghost btn--s', disabled: i === trail.length - 1, onclick: () => { trail.length = i + 1; load(t.id); } }, t.name)), r.period_start ? h('span', { class: 'faint', style: 'font-size:13.5px', text: `${fmtDate(r.period_start, { month: 'long', year: 'numeric' })}, due ${fmtDate(r.due_on)}` }) : null);
    if (!r.loaded) { box.replaceChildren(empty('inbox', 'The monthly report form is not loaded yet', 'Compliance is measured against the monthly leadership report. It starts counting once that form is loaded.')); return; }
    if (!r.rows.length) { box.replaceChildren(empty('users', 'No units in this scope', 'Units appear here when leaders are seated below this level.')); return; }
    r.rows.sort((a, b) => Number(b.overdue) - Number(a.overdue));
    box.replaceChildren(h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Accountable leader', 'Unit', 'Status', 'Submitted', ''].map((x, i) => h('th', { class: i === 3 ? 'hide-s' : '', text: x })))),
      h('tbody', {}, r.rows.map((n) => h('tr', { style: 'cursor:default' }, h('td', {}, h('div', { class: 'who' }, avatar({ id: n.user_id, first_name: n.first_name, last_name: n.last_name, has_photo: n.has_photo }, 36), h('div', {}, h('b', { text: `${n.first_name} ${n.last_name}` }), h('small', { text: n.title })))), h('td', {}, h('span', { style: 'display:inline-flex;gap:8px;align-items:center' }, flag(n.country_code), n.unit)),
        h('td', {}, tag(n.status), n.overdue ? h('span', { class: 'tag tag--bad', style: 'margin-left:6px', text: 'Overdue' }) : n.late ? h('span', { class: 'tag tag--bad', style: 'margin-left:6px', text: 'Late' }) : null), h('td', { class: 'hide-s faint', text: n.submitted_at ? ago(n.submitted_at) : '' }),
        h('td', { style: 'text-align:right;white-space:nowrap' }, n.report_id ? h('a', { class: 'btn btn--ghost btn--s', href: '#/reporting/r/' + n.report_id, text: 'Open' }) : null, n.children ? h('button', { class: 'btn btn--ghost btn--s', style: 'margin-left:6px', onclick: () => { trail.push({ id: n.user_id, name: n.unit }); load(n.user_id); } }, `${n.children} below`) : null)))))));
  }
  load();
}
async function configure(body) {
  const r = await api('/reporting/config'); const c = r.config; const auto = h('input', { type: 'checkbox' }); auto.checked = !!c.auto; const f = { due_day: input({ type: 'number', min: 1, max: 28, value: c.due_day }), before: input({ value: c.before.join(', ') }), overdue: input({ value: c.overdue.join(', ') }), supervisor_day: input({ type: 'number', min: 1, value: c.supervisor_day }), escalation_day: input({ type: 'number', min: 1, value: c.escalation_day }) };
  const panel = card('Reminder settings', 'The monthly report is due on this day of the following month. Lists are days, separated by commas.', h('div', { style: 'display:grid;gap:14px' }, h('label', { class: 'check' }, auto, h('span', { text: 'Send reminders automatically every morning. While this is off, reminders go out only when HQ presses Run reminders now.' })), h('div', { class: 'grid2' }, field('Monthly due day', f.due_day), field('Reminders, days before due', f.before), field('Overdue notices, days after due', f.overdue), field('Tell the supervisor, days overdue', f.supervisor_day), field('Escalate to HQ, days overdue', f.escalation_day)),
    h('div', {}, h('button', { class: 'btn btn--s', onclick: async () => { try { await api('/reporting/config', { auto: auto.checked, ...Object.fromEntries(Object.entries(f).map(([k, n]) => [k, n.value])) }); toast('Settings saved', '', 'ok'); panel.remove(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Save settings'))));
  body.append(panel); panel.scrollIntoView({ behavior: 'smooth' });
}

/* ---------------------------------------------------------------- issues -- */
async function issues(body) {
  const f = { title: input({ maxlength: 190 }), category: h('select', { class: 'input' }, Object.entries(CATS).map(([v, l]) => h('option', { value: v, text: l }))), severity: h('select', { class: 'input' }, [['green', 'Green'], ['amber', 'Amber'], ['red', 'Red'], ['critical', 'Critical']].map(([v, l]) => h('option', { value: v, text: l }))), impact: h('textarea', { class: 'input', rows: 2 }), action_taken: h('textarea', { class: 'input', rows: 2 }), decision_required: h('textarea', { class: 'input', rows: 2 }), escalated_to: h('select', { class: 'input' }, Object.entries(ROUTES).map(([v, l]) => h('option', { value: v, text: l }))), due_on: input({ type: 'date' }) };
  f.severity.value = 'amber'; const msg = h('p', { class: 'err', role: 'alert' }); const btn = h('button', { class: 'btn', type: 'submit', text: 'Raise issue' }); const list = h('div', {}, sk.rows(4));
  const form = h('form', { style: 'display:grid;gap:14px' }, field('Issue', f.title), h('div', { class: 'grid2' }, field('Category', f.category), field('Severity', f.severity)), field('Impact', f.impact), field('Action already taken', f.action_taken), field('Decision or support required', f.decision_required), h('div', { class: 'grid2' }, field('Escalate to', f.escalated_to), field('Deadline', f.due_on)), msg, h('div', {}, btn));
  form.addEventListener('submit', async (e) => { e.preventDefault(); msg.textContent = ''; btn.disabled = true; try { const r = await api('/reporting/issue', Object.fromEntries(Object.entries(f).map(([k, n]) => [k, n.value]))); toast(`Issue ${r.issue_no} raised`, '', 'ok'); ['title', 'impact', 'action_taken', 'decision_required', 'due_on'].forEach((k) => { f[k].value = ''; }); load(); } catch (ex) { msg.textContent = ex.message; } btn.disabled = false; });
  body.append(h('div', { class: 'row row--2' }, card('Raise an issue', 'Non-confidential operational issues that need action above your level. Safeguarding and complaints go to HQ through the confidential channel, never here.', form), card('Issues in my scope', 'Critical and red first', list)));
  async function load() { let r; try { r = await api('/reporting/issues'); } catch (e) { list.replaceChildren(empty('x', 'Issues did not load', e.message)); return; }
    list.replaceChildren(r.items.length ? h('div', { style: 'display:grid;gap:12px' }, r.items.map((i) => { const sel = h('select', { class: 'input', style: 'width:auto;min-height:36px;padding-top:4px;padding-bottom:4px', 'aria-label': 'Status' }, Object.entries(ISSUE_ST).map(([v, l]) => h('option', { value: v, text: l }))); sel.value = i.status; sel.addEventListener('change', async () => { try { await api('/reporting/issue/' + i.id, { status: sel.value }); toast('Status updated', '', 'ok'); load(); } catch (e) { toast('Not updated', e.message, 'bad'); } });
      return h('article', { class: 'panel', style: ['resolved', 'closed'].includes(i.status) ? 'opacity:.65' : '' }, h('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap' }, h('span', { class: 'sev sev--' + i.severity, text: i.severity[0].toUpperCase() + i.severity.slice(1) }), h('b', { style: 'flex:1;min-width:180px', text: `${i.issue_no}: ${i.title}` }), netTag(i.network)),
        i.impact ? h('p', { style: 'font-size:14.5px' }, h('span', { class: 'faint', text: 'Impact: ' }), i.impact) : null, i.action_taken ? h('p', { style: 'font-size:14.5px' }, h('span', { class: 'faint', text: 'Action taken: ' }), i.action_taken) : null, i.decision_required ? h('p', { style: 'font-size:14.5px' }, h('span', { class: 'faint', text: 'Decision required: ' }), i.decision_required) : null,
        h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between' }, h('small', { class: 'faint', text: [i.by_name, i.unit, 'to ' + ROUTES[i.escalated_to], i.due_on ? 'by ' + fmtDate(i.due_on) : '', ago(i.created_at)].filter(Boolean).join(' · ') }), sel)); }))
      : empty('shield', 'No issues', 'Risks and escalations raised in your scope are tracked here until they are resolved.')); }
  load();
}
