/* v109.46: the 3-Day and 7-Day Leadership Activation Framework on screen.
 * candidateView: her tasks day by day, each with what to do, the evidence to give, its status, due date, score and HQ's remarks.
 * hqView: HQ reviews each task's evidence, sets the score (WDOS suggests one), verifies it or returns it for corrections, confirms the
 * reporting contact, and makes the final decision: Activated, Pending Corrections or Requiring Support. */
import { h, t, tf, icon, card, empty, fmtDate, toast, input, field } from './ui.js';
import { api } from './api.js';
import { uploadButton } from './files.js';

const ST_CLS = { not_started: '', in_progress: '', submitted: 'tag--wgmn', pending_corrections: 'tag--bad', verified: 'tag--wnnn', requiring_support: 'tag--bad', activated: 'tag--wnnn' };
const HOW = ['Phone call', 'WDOS message', 'WDOS voice call', 'WhatsApp', 'In person'];
const PSTAT = [['identified', 'Identified'], ['contacted', 'Contacted'], ['interested', 'Interested'], ['screening', 'In screening'], ['registered', 'Registered in WDOS'], ['declined', 'Declined']];
const tag = (status, label) => h('span', { class: 'tag ' + (ST_CLS[status] || ''), text: t(label) });
const area = (rows = 3, v = '') => { const x = h('textarea', { class: 'input', rows }); x.value = v || ''; return x; };

/** The header both see: route, seat, scores per stage, the three gates and where she stands. */
function summary(a) {
  const f = a.fw; const gate = (ok, l) => h('li', { class: ok ? 'is-ok' : '' }, icon(ok ? 'check' : 'clock', 16), h('span', { text: t(l) }));
  const stage = (l, v, pass) => h('div', { class: 'lm-mk' + (v !== null && v < 80 ? ' is-bad' : '') }, h('b', { text: v === null ? '–' : `${v}/100` }), h('small', { text: l + (pass === true ? ' · ' + t('passed') : '') }));
  return h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:center' }, h('span', { class: 'tag tag--wgmn', text: a.route_label }), tag(f.decision, f.decision_label), h('span', { class: 'tag', text: tf('Day {1} of {2} done', f.days_done, f.days_total) }), h('span', { class: 'tag', text: tf('Complete by {1}', fmtDate(a.deadline)) })),
    h('p', { style: 'margin:0', text: tf('Screening for {1}, {2} ({3}).', a.role_title, a.unit, a.network) }),
    h('div', { class: 'lm-mks' }, stage(t('Days 1 to 3'), f.stage_a, f.pass_a), f.stage_b !== null ? stage(t('Days 4 to 7'), f.stage_b, f.pass_b) : null, h('div', { class: 'lm-mk' }, h('b', { text: `${f.score}/100` }), h('small', { text: f.stage_b !== null ? t('Overall (average of both stages)') : t('Overall') }))),
    h('div', {}, h('b', { text: 'Mandatory steps' }), h('ul', { class: 'fw-gates' }, gate(f.gates.consent, 'Signed consent'), gate(f.gates.access, 'Verified WDOS sign-in'), gate(f.gates.reporting, 'Reporting officer confirmed the contact'), f.gates.report !== undefined ? gate(f.gates.report, 'Day 7 report sent') : null)),
    h('p', { class: 'faint', style: 'margin:0', text: f.stage_b !== null ? t('To pass: at least 80 in each stage, all mandatory steps done, verified evidence and no compliance breach. WODDI HQ makes the final decision.') : t('To pass: at least 80 out of 100 and all three mandatory steps done. A missing step means Pending Corrections even at 80 or more. WODDI HQ makes the final decision.') }),
    a.support_note ? h('p', { class: 'notice', style: 'margin:0', text: tf('WODDI HQ is arranging support: {1}', a.support_note) }) : null,
    a.compliance_breach ? h('p', { class: 'notice notice--bad', style: 'margin:0', text: tf('Compliance breach recorded: {1}', a.compliance_breach) }) : null));
}

/** What she sent, written out for reading (both views). */
function evidenceView(task, a) {
  const e = task.evidence || {}; const L = (l, v) => v ? h('div', {}, h('small', { class: 'faint', text: t(l) }), h('p', { style: 'margin:2px 0 0;white-space:pre-wrap', text: v })) : null;
  const rows = (list, cols) => list && list.length ? h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, cols.map(([, l]) => h('th', { text: l })))), h('tbody', {}, list.map((r) => h('tr', {}, cols.map(([k]) => h('td', { text: k === 'status' ? t((PSTAT.find((p) => p[0] === r[k]) || [0, r[k]])[1]) : r[k] || '' }))))))) : null;
  const out = [];
  if (task.key === 'consent') out.push(L('Signed', e.signature ? `${e.signature} · ${fmtDate(e.signed_at)}` : ''));
  if (task.key === 'access') out.push(L('Verified sign-in', e.signed_in_at ? fmtDate(e.signed_in_at) : ''));
  if (task.key === 'orientation' || task.key === 'report') out.push(L(task.key === 'report' ? 'Compliance questions' : 'Orientation questions', e.quiz_of ? tf('{1} of {2} right', e.quiz_right, e.quiz_of) : ''));
  if (task.key === 'contact') out.push(L('Reporting officer', e.officer), L('How and when', [e.how, e.when].filter(Boolean).join(' · ')), L('What was agreed', e.agreed), L('Assistant or counterpart', e.counterpart),
    h('p', { style: 'margin:0' }, task.confirmed_at ? h('span', { class: 'tag tag--wnnn', text: tf('Confirmed by {1}', task.confirmed_by) }) : h('span', { class: 'tag', text: 'Waiting for the reporting officer to confirm' })));
  if (task.key === 'priorities') out.push(h('ol', { style: 'margin:0;padding-left:20px' }, (e.priorities || []).map((p) => h('li', { text: p }))), L('Vacancies she knows of', e.vacancies));
  if (task.key === 'coverage') out.push(L('Coverage report', e.summary), L('Gaps', e.gaps), L('Corrections', e.corrections), e.confirmed_list ? h('small', { class: 'faint', text: 'She checked the list WDOS showed her.' }) : null);
  if (task.key === 'referrals') out.push(rows(e.people, [['name', t('Person')], ['for', t('For')], ['status', t('Screening')], ['note', t('Note')]]), L('Engagement evidence', e.engagement), h('small', { class: 'faint', text: tf('{1} joined WDOS through her link since she started', e.via_link || 0) }));
  if (task.key === 'engagement') out.push(rows(e.log, [['who', t('Who')], ['how', t('How')], ['when', t('When')], ['outcome', t('Outcome')]]));
  if (task.key === 'plan') out.push(rows(e.actions, [['what', t('Action')], ['by', t('By when')], ['with', t('With whom')]]));
  if (task.key === 'report') out.push(L('Day 7 report', e.report));
  if (task.files && task.files.length) out.push(h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, task.files.map((id, i) => h('a', { class: 'btn btn--ghost btn--s', href: '/api/files/' + id, target: '_blank', rel: 'noopener' }, icon('book', 16), h('span', { text: tf('Document {1}', i + 1) })))));
  return h('div', { style: 'display:grid;gap:8px' }, out.filter(Boolean));
}

/** Rows she can add to: the referral register, the engagement log, the action plan. */
function rowList(cols, min = 1, start = []) {
  const box = h('div', { style: 'display:grid;gap:8px' }); const list = [];
  const add = (v = {}) => {
    const cells = cols.map(([k, l, kind]) => { const el = kind === 'status' ? h('select', { class: 'input', 'aria-label': t(l) }, PSTAT.map(([x, y]) => h('option', { value: x, text: y }))) : kind === 'how' ? h('select', { class: 'input', 'aria-label': t(l) }, HOW.map((x) => h('option', { value: x, text: x }))) : input({ placeholder: t(l), 'aria-label': t(l) }); if (v[k]) el.value = v[k]; return [k, el]; });
    const row = h('div', { class: 'fw-row' }, cells.map(([, el]) => el)); list.push(cells); box.append(row);
  };
  (start.length ? start : Array.from({ length: min }, () => ({}))).forEach(add);
  const more = h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => add() }, icon('plus', 16), h('span', { text: 'Add another' }));
  return { el: h('div', { style: 'display:grid;gap:8px' }, box, h('div', {}, more)), value: () => list.map((cells) => Object.fromEntries(cells.map(([k, el]) => [k, el.value.trim()]))).filter((r) => (r[cols[0][0]] || '').trim() !== '') };
}
function quiz(qs, name, prev) {
  const picked = []; const box = h('ol', { class: 'fw-quiz' }, qs.map((q, i) => h('li', {}, h('b', { text: q.q }), h('div', { class: 'fw-opts', role: 'radiogroup', 'aria-label': q.q },
    q.options.map((o, j) => { const r = h('input', { type: 'radio', name: `${name}-${i}`, value: String(j), onchange: () => { picked[i] = j; } }); if (prev && prev[i] === j) { r.checked = true; picked[i] = j; } return h('label', { class: 'fw-opt' }, r, h('span', { text: o })); })))));
  return { el: box, value: () => qs.map((_, i) => picked[i]), complete: () => qs.every((_, i) => picked[i] !== undefined) };
}

/** The form for one task. send(body) posts it. */
function taskForm(task, a, send) {
  const e = task.evidence || {}; const files = [...(task.files || [])]; const fileNote = h('small', { class: 'faint' });
  const up = uploadButton(t('Attach a document or a photo (optional)'), 'evidence', async (f) => { files.push(f.id); fileNote.textContent = tf('{1} document(s) attached', files.length); });
  const go = (body, check) => h('button', { class: 'btn btn--primary', type: 'button', onclick: async (ev) => { if (check && !check()) return; ev.target.disabled = true; try { await send({ ...body(), files }); } finally { ev.target.disabled = false; } } }, icon('send', 16), h('span', { text: task.status === 'pending_corrections' ? t('Send the corrected task') : t('Send this task') }));
  const tick = (l, on) => { const c = h('input', { type: 'checkbox', checked: !!on || null }); return [c, h('label', { class: 'fw-tick' }, c, h('span', { text: l }))]; };
  switch (task.key) {
    case 'consent': {
      const [c1, l1] = tick(t('I accept the nomination for this leadership seat and give my consent to WODDI keeping my details for my volunteer role.'), e.consent);
      const [c2, l2] = tick(t('I will keep to the volunteer rules: no fundraising without authorisation, no dues or levies, no local bank accounts, contracts or commitments in WODDI\'s name.'), e.compliance);
      const [c3, l3] = tick(t('I have received my activation kit and the description of the role.'), e.kit);
      const sig = input({ placeholder: t('Type your full name'), value: e.signature || '' });
      return h('div', { style: 'display:grid;gap:10px' }, l1, l2, l3, field(t('Your signature (type your full name)'), sig), h('small', { class: 'faint', text: 'Full points need a complete profile: phone, date of birth, LGA, community and a photograph. Update them in Settings.' }), go(() => ({ consent: c1.checked, compliance: c2.checked, kit: c3.checked, signature: sig.value })));
    }
    case 'orientation': {
      const q = quiz(a.quiz_a, 'qa', e.quiz); const [rd, lr] = tick(t('I have read the orientation.'), e.read);
      return h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'fw-read' }, a.orientation.map(([ttl, body]) => h('section', {}, h('h4', { text: ttl }), h('p', { text: body })))), lr, h('b', { text: 'Orientation questions' }), q.el,
        go(() => ({ read: rd.checked, quiz: q.value() }), () => { if (!q.complete()) { toast(t('Answer every question'), '', 'bad'); return false; } return true; }));
    }
    case 'contact': {
      const off = h('select', { class: 'input' }, a.officers.map((o) => h('option', { value: String(o.id), text: o.id ? `${o.name}, ${o.title}${o.place ? ' · ' + o.place : ''}` : t('WODDI HQ (no leader serves above this seat yet)') })));
      const how = h('select', { class: 'input' }, HOW.map((x) => h('option', { value: x, text: x }))); if (e.how) how.value = e.how;
      const when = input({ placeholder: t('Day and time'), value: e.when || '' }); const agreed = area(3, e.agreed); const cp = input({ placeholder: t('Name and role (optional)'), value: e.counterpart || '' });
      return h('div', { style: 'display:grid;gap:10px' }, field(t('Your reporting officer'), off, t('She will be asked in WDOS to confirm that you contacted her.')), h('div', { class: 'grid2' }, field(t('How you contacted her'), how), field(t('When'), when)),
        field(t('What you agreed'), agreed), field(t('Assistant or counterpart you also contacted'), cp), go(() => ({ officer: Number(off.value), how: how.value, when: when.value, agreed: agreed.value, counterpart: cp.value })));
    }
    case 'priorities': {
      const p = [0, 1, 2].map((i) => area(2, (e.priorities || [])[i])); const vac = area(2, e.vacancies);
      return h('div', { style: 'display:grid;gap:10px' }, p.map((x, i) => field(tf('Priority {1}', i + 1), x)), field(t('Vacant seats you already know of (optional)'), vac), go(() => ({ priorities: p.map((x) => x.value), vacancies: vac.value })));
    }
    case 'coverage': {
      const c = a.coverage; const sum = area(5, e.summary); const gaps = area(2, e.gaps); const corr = area(2, e.corrections); const [ck, lc] = tick(t('I checked this list against what I know on the ground.'), e.confirmed_list);
      return h('div', { style: 'display:grid;gap:12px' }, h('div', { class: 'panel', style: 'display:grid;gap:8px' }, h('b', { text: tf('Leadership in {1} today', c.place) }),
        h('div', { class: 'lm-cell' }, c.seats.map((s) => h('span', { class: 'lm-chip' + (s.filled ? '' : ' is-vacant') }, icon('user', 14), h('span', { text: s.filled ? s.name : t('Vacant') }), h('small', { text: s.title })))),
        c.below.length ? h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, [c.below_label, t('Principal leader'), t('Vacant seats'), t('Members')].map((x) => h('th', { text: x })))),
          h('tbody', {}, c.below.map((r) => h('tr', {}, h('td', { text: r.name }), h('td', { text: r.principal || t('Vacant') }), h('td', { class: 'num', text: String(r.vacant) }), h('td', { class: 'num', text: String(r.members) })))))) : null),
        lc, field(t('Your coverage and vacancy report'), sum, t('Which seats are filled, which are vacant, and what you found on the ground.')), field(t('Gaps you found'), gaps), field(t('Anything on the list that needs correcting'), corr), up, fileNote,
        go(() => ({ summary: sum.value, gaps: gaps.value, corrections: corr.value, confirmed_list: ck.checked })));
    }
    case 'referrals': {
      const r = rowList([['name', 'Name'], ['for', 'For which seat, or member'], ['status', 'Screening', 'status'], ['note', 'Note']], 2, e.people || []); const eng = area(3, e.engagement);
      return h('div', { style: 'display:grid;gap:12px' }, h('p', { class: 'faint', style: 'margin:0', text: 'Quality counts, not numbers. Record each person you approached and how far screening has gone. Nobody needs to be appointed within these seven days.' }), r.el,
        field(t('Engagement evidence: what you did to reach people'), eng), up, fileNote, go(() => ({ people: r.value(), engagement: eng.value })));
    }
    case 'engagement': { const r = rowList([['who', 'Who'], ['how', 'How', 'how'], ['when', 'When'], ['outcome', 'Outcome']], 3, e.log || []); return h('div', { style: 'display:grid;gap:12px' }, r.el, up, fileNote, go(() => ({ log: r.value() }))); }
    case 'plan': { const r = rowList([['what', 'Action'], ['by', 'By when'], ['with', 'With whom']], 3, e.actions || []); return h('div', { style: 'display:grid;gap:12px' }, r.el, go(() => ({ actions: r.value() }))); }
    case 'report': {
      const rep = area(6, e.report); const q = quiz(a.quiz_b, 'qb', e.quiz);
      return h('div', { style: 'display:grid;gap:12px' }, field(t('Your Day 7 report'), rep, t('What you did on Days 1 to 7, what you found, and what comes next.')), up, fileNote, h('b', { text: 'Compliance questions' }), q.el,
        go(() => ({ report: rep.value, quiz: q.value() }), () => { if (!q.complete()) { toast(t('Answer every question'), '', 'bad'); return false; } return true; }));
    }
  }
  return null;
}

function taskHead(task) {
  return h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { text: task.title }), tag(task.status, task.status_label),
    h('span', { class: 'tag', text: tf('Due {1}', fmtDate(task.due_on)) }), h('span', { class: 'tag' + (task.reviewed ? ' tag--wnnn' : ''), text: tf('{1} of {2} points', task.score, task.points) + (task.status === 'not_started' ? '' : task.reviewed ? ' · ' + t('checked by HQ') : ' · ' + t('suggested by WDOS')) }),
    task.gate ? h('span', { class: 'tag', text: 'Mandatory' }) : null);
}

export function candidateView(a, reload) {
  const f = a.fw; const parts = [summary(a)];
  if (a.status === 'incomplete') return parts;          // the extension request is drawn by the page
  if (['completed'].includes(a.status)) { parts.push(card('Your outcome', null, h('p', { text: a.disclosed || (f.decision === 'activated' ? t('WODDI HQ has activated you. Open your dashboard and accept your Volunteer Leadership Agreement.') : t('Your activation has been reviewed. Headquarters will contact you about the next step.')) }))); return parts; }
  const send = (key) => async (body) => { try { const r = await api('/activation/task/' + key, body); toast(t('Sent'), tf('WDOS suggests {1} points for this task until HQ checks it.', r.auto_score), 'ok'); reload(); } catch (e) { toast(t('Not sent yet'), e.message, 'bad'); } };
  let open = true; const days = [...new Set(f.tasks.map((x) => x.day))];
  days.forEach((d) => {
    const list = f.tasks.filter((x) => x.day === d); const locked = !open;
    parts.push(card(tf('Day {1}', d), d <= 3 ? t('Initial activation: readiness, access, alignment, communication, accountability') : t('Operational activation: early action, mapping, mobilisation, reporting'),
      h('div', { style: 'display:grid;gap:14px' }, list.map((task) => h('div', { class: 'panel fw-task' + (task.status === 'pending_corrections' ? ' is-bad' : '') },
        taskHead(task), h('p', { style: 'margin:0', text: task.does }), h('small', { class: 'faint', text: tf('Evidence: {1}', task.evidence_needed) }),
        task.remarks && task.status === 'pending_corrections' ? h('p', { class: 'notice notice--bad', style: 'margin:0', text: tf('Remarks from WODDI HQ: {1}', task.remarks) }) : null,
        task.key === 'access' ? evidenceView(task, a) : locked ? h('p', { class: 'faint', style: 'margin:0', text: tf('Opens when Day {1} is sent.', d - 1) })
          : ['submitted', 'verified'].includes(task.status) ? evidenceView(task, a) : taskForm(task, a, send(task.key)))))));
    if (list.some((x) => !['submitted', 'verified'].includes(x.status))) open = false;
  });
  return parts;
}

export function hqView(a, reload, act) {
  const f = a.fw; const parts = [summary(a)]; const id = a.id;
  const review = (key, body, msg) => api(`/hq/activations/${id}/task/${key}`, body).then(() => { toast(msg, '', 'ok'); reload(); }).catch((e) => toast(t('Not saved'), e.message, 'bad'));
  parts.push(card('Tasks and evidence', 'Read each piece of evidence. Verify it with a score (WDOS suggests one), or return it with remarks so she can correct and resend it.', h('div', { style: 'display:grid;gap:14px' }, f.tasks.map((task) => {
    const sc = input({ type: 'number', min: 0, max: task.points, value: String(task.score), style: 'width:90px', 'aria-label': tf('Score out of {1}', task.points) }); const rem = input({ placeholder: t('Remarks (she reads these when a task is returned)'), value: task.remarks || '' });
    const sent = ['submitted', 'verified', 'pending_corrections'].includes(task.status);
    return h('div', { class: 'panel fw-task' + (task.status === 'pending_corrections' ? ' is-bad' : '') }, taskHead(task), h('small', { class: 'faint', text: `${task.criterion} · ${t('Evidence')}: ${task.evidence_needed}` }),
      task.submitted_at ? h('small', { class: 'faint', text: tf('Sent {1}', fmtDate(task.submitted_at)) + (task.resubmissions ? ' · ' + tf('resent {1} time(s)', task.resubmissions) : '') + (task.reviewer ? ' · ' + tf('reviewed by {1} on {2}', task.reviewer, fmtDate(task.reviewed_at)) : '') }) : null,
      sent ? evidenceView(task, a) : h('p', { class: 'faint', style: 'margin:0', text: 'Not sent yet.' }),
      sent && a.status !== 'completed' ? h('div', { class: 'fw-review' }, field(tf('Score out of {1}', task.points), sc), field(t('Remarks'), rem),
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:end' },
          h('button', { class: 'btn btn--lime btn--s', type: 'button', onclick: () => review(task.key, { action: 'verify', score: sc.value === '' ? null : Number(sc.value), remarks: rem.value }, t('Verified')) }, icon('check', 16), h('span', { text: task.key === 'contact' && !task.confirmed_at ? t('Confirm the contact and verify') : t('Verify') })),
          h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => review(task.key, { action: 'correct', remarks: rem.value }, t('Returned for corrections')) }, t('Return for corrections')))) : null);
  }))));
  if (a.status !== 'completed') {
    const note = area(2); const br = area(2, a.compliance_breach);
    parts.push(card('Activation decision', 'WODDI HQ makes the final decision. Scores and records stay visible in WDOS.', h('div', { style: 'display:grid;gap:14px' },
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' },
        h('button', { class: 'btn btn--lime', type: 'button', disabled: !(f.passed && f.verified_all) || null, onclick: () => { if (confirm(tf('Activate {1} {2} as {3}, {4}? She is appointed to the seat and told in the app, on the phone and by email.', a.first_name, a.last_name, a.role_title, a.unit))) act({ action: 'approve' }, t('Activated and appointed')); } }, icon('check', 18), h('span', { text: 'Activate and appoint' })),
        a.status === 'support' ? h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => act({ action: 'resume' }, t('Activation reopened')) }, t('Support given: reopen her activation')) : null),
      !(f.passed && f.verified_all) ? h('p', { class: 'faint', style: 'margin:0', text: !f.passed ? tf('Not yet passed: {1}', [f.missing_gates.length ? tf('missing {1}', f.missing_gates.map((x) => t(x)).join(', ')) : '', f.stage_a < 80 ? tf('Days 1 to 3 at {1}', f.stage_a) : '', f.stage_b !== null && f.stage_b < 80 ? tf('Days 4 to 7 at {1}', f.stage_b) : '', f.breach ? t('a compliance breach is recorded') : '', !f.final ? t('tasks still to come') : ''].filter(Boolean).join('; ')) : t('Verify every task before activating her.') }) : null,
      h('div', { class: 'grid2' }, field(t('Requiring support: what help she needs'), note), field(t('Critical compliance breach (leave empty to clear)'), br)),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => act({ action: 'support', note: note.value }, t('Marked Requiring Support')) }, t('Mark Requiring Support')),
        h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => act({ action: 'breach', note: br.value }, br.value.trim() ? t('Breach recorded') : t('Breach cleared')) }, t('Save the compliance note'))))));
  }
  return parts;
}
