/* The candidate's activation journey: one day at a time, then the Understanding and Readiness Assessment.
 * Wording follows the current WDOS. The days and the assessment are content loaded from the current WDOS. */
import { h, card, sk, empty, fmtDate, toast, docReader } from '../ui.js';
import { api } from '../api.js';

export async function render(root) {
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(box);
  async function draw() {
    let a; try { a = (await api('/activation')).activation; } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'Your activation did not load', e.message))); return; }
    if (!a) { box.replaceChildren(h('div', { class: 'card' }, empty('route', 'No activation in progress', 'You do not currently have an activation to complete. If you were referred, open the referral form for your network.', h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;justify-content:center' }, h('a', { class: 'btn btn--quiet btn--s', href: '#/', text: 'Go to my home page' }))))); return; }
    const days = a.days, done = days.filter((d) => d.status === 'completed').length; const assessed = ['submitted', 'completed'].includes(a.status); const parts = [];
    parts.push(h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:12px' }, h('span', { class: 'tag', style: 'justify-self:start', text: a.route_label }),
      h('h2', { class: 'display', style: 'font-size:26px;margin:0', text: `Welcome, ${a.first_name}` }),
      h('p', { class: 'muted', style: 'margin:0', text: `You are being considered for ${a.role_title}, ${a.unit}, in ${a.network}.` }), a.referrer ? h('p', { class: 'muted', style: 'margin:0', text: `Referred by ${a.referrer}` }) : null,
      h('div', { style: 'display:flex;gap:12px;align-items:center' }, h('div', { class: 'pbar' }, h('span', { style: `width:${Math.round((done / Math.max(1, a.content.days_expected)) * 100)}%` })), h('b', { class: 'num', text: `${done}/${a.content.days_expected}` })),
      h('div', { class: 'dots' }, Array.from({ length: a.content.days_expected }, (_, i) => { const d = days.find((x) => x.day === i + 1); return h('span', { class: 'dot' + (d?.status === 'completed' ? ' is-done' : ''), text: d?.status === 'completed' ? '✓' : String(i + 1) }); }), h('span', { class: 'dot' + (assessed ? ' is-done' : ''), title: 'Readiness assessment', text: 'A' })),
      h('p', { class: 'muted', style: 'margin:0', text: `Complete by ${fmtDate(a.deadline)}` + (a.extension_status ? ' · ' + { requested: 'Extension requested', approved: 'Extension approved', declined: 'Extension not approved' }[a.extension_status] : '') }),
      h('p', { class: 'notice', text: a.notice }))));
    if (a.status === 'incomplete') {
      const reason = h('textarea', { class: 'input', rows: 3, placeholder: 'Tell us briefly why you need more time' });
      parts.push(card('Your activation period has ended', 'The time allowed for this activation has passed. You can ask for an extension below, and a person will review it.', a.extension_status ? h('p', { class: 'muted', text: a.extension_status === 'requested' ? 'Your request has been sent for review.' : 'Extension not approved' })
        : h('div', { style: 'display:grid;gap:12px' }, reason, h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async () => { try { await api('/activation/extension', { reason: reason.value }); toast('Your request has been sent for review.', '', 'ok'); draw(); } catch (e) { toast('Not sent', e.message, 'bad'); } } }, 'Ask for an extension')))));
    } else if (a.status === 'submitted') parts.push(card('Assessment submitted', null, h('div', {}, h('p', { text: `Thank you, ${a.first_name}. Your assessment has been received and is now with headquarters for review. We will update you here.` }), h('p', { class: 'muted', text: 'Submitted ' + fmtDate(a.submitted_at) }))));
    else if (a.status === 'completed') parts.push(card('Your outcome', null, h('p', { text: a.disclosed || 'Your assessment has been reviewed. Headquarters will contact you about the next step.' })));
    else {
      const cur = days.find((d) => d.status !== 'completed');
      if (cur) parts.push(dayCard(a, cur, draw));
      else if (!a.content.complete) parts.push(h('div', { class: 'card' }, empty('clock', `Day ${done} complete`, 'The rest of this activation is still being loaded by WODDI HQ. Your progress is saved. Come back to this page to continue.')));
      else parts.push(assessment(a, draw));
    }
    box.replaceChildren(...parts);
  }
  draw();
}

function dayCard(a, d, done) {
  const started = Date.now(); const answers = { ...(d.answers || {}) }; const acked = new Set((d.acked || []).map(String)); const need = (d.content || []).length; const read = new Set(); let reached = need === 0 || d.reached_end;
  const next = h('button', { class: 'btn', type: 'button' }, 'Next'); const hint = h('p', { class: 'muted', style: 'margin:0;font-size:14px' });
  const secs = () => (d.seconds || 0) + Math.round((Date.now() - started) / 1000);
  const ready = () => reached && (d.questions || []).every((q) => (answers[q.key] || '').trim()) && acked.size >= (d.acks || []).length;
  const refresh = () => { next.disabled = !ready(); hint.textContent = !reached ? 'Scroll to the end of the document to continue.' : !ready() ? 'Answer every question and confirm each statement to continue.' : 'You have reached the end. You can continue.'; };
  let timer; const autosave = () => { clearTimeout(timer); timer = setTimeout(() => api('/activation/day/' + d.day, { answers, acked: [...acked], reached_end: reached, seconds: secs(), complete: false }).catch(() => {}), 800); };
  const readers = (d.content || []).map((c, i) => docReader(c.doc, () => { read.add(i); if (read.size >= need) { reached = true; autosave(); } refresh(); }, c.title || (c.sections && c.sections.length ? c.sections.join(' · ') : null), c.sections));
  const qs = (d.questions || []).map((q) => { const ta = h('textarea', { class: 'input', rows: 3, placeholder: 'Write your answer here' }); ta.value = answers[q.key] || ''; ta.addEventListener('input', () => { answers[q.key] = ta.value; refresh(); }); ta.addEventListener('change', autosave); return h('label', { class: 'field' }, h('span', { text: q.label }), ta); });
  const acks = (d.acks || []).map((text, i) => { const cb = h('input', { type: 'checkbox' }); cb.checked = acked.has(String(i)); cb.addEventListener('change', () => { if (cb.checked) acked.add(String(i)); else acked.delete(String(i)); autosave(); refresh(); }); return h('label', { class: 'check' }, cb, h('span', { text })); });
  next.addEventListener('click', async () => { next.disabled = true; clearTimeout(timer);
    try { await api('/activation/day/' + d.day, { answers, acked: [...acked], reached_end: reached, seconds: secs(), complete: true }); toast(`Day ${d.day} complete.`, '', 'ok'); done(); } catch (e) { toast('Not saved', e.message, 'bad'); refresh(); } });
  setTimeout(refresh, 30);
  return h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:16px' }, h('span', { class: 'tag', style: 'justify-self:start', text: `Day ${d.day} of ${a.content.days_expected}` }), h('h2', { style: 'margin:0', text: d.title }), d.instruction ? h('p', { class: 'muted', style: 'margin:0', text: d.instruction }) : null,
    d.needs_profile && a.profile ? h('div', { class: 'panel' }, h('b', { text: 'Your profile' }), h('dl', { class: 'dl', style: 'margin-top:8px' }, [['Name', `${a.first_name} ${a.last_name}`], ['Date of birth', a.profile.dob ? fmtDate(a.profile.dob) : ''], ['Phone', a.profile.phone], ['Email', a.profile.email], ['Lives in', [a.profile.community, a.profile.lga, a.profile.state_region, a.profile.country].filter(Boolean).join(', ')]].filter((x) => x[1]).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })])), h('small', { class: 'faint', text: 'If anything here is wrong, correct it in Settings or tell WODDI HQ before you continue.' })) : null,
    ...readers, d.notice ? h('p', { class: 'notice', text: d.notice }) : null, qs.length ? h('div', { style: 'display:grid;gap:14px' }, qs) : null, acks.length ? h('div', { style: 'display:grid;gap:10px' }, h('b', { text: 'Please confirm each of the following' }), acks) : null, hint, h('div', {}, next)));
}

function assessment(a, done) {
  const F = a.form, ex = a.existing || {}; const st = { ...(ex.statements || {}) }, sa = { ...(ex.short_answers || {}) }; const conf = new Set((ex.confirmations || []).map(Number)); let readiness = ex.readiness || '', clar = ex.clarification_note || '', sig = ex.signature_name || `${a.first_name} ${a.last_name}`.trim();
  const bySec = {}; F.statements.forEach((x) => { (bySec[x.section] = bySec[x.section] || { key: x.section, title: x.section_title, items: [] }).items.push(x); });
  const sections = [{ key: 'info', title: 'Candidate information' }, ...Object.values(bySec).map((s) => ({ key: s.key, title: [s.key, s.title].filter(Boolean).join('. '), items: s.items })), { key: 'I', title: 'Short understanding questions' }, { key: 'J', title: 'Final confirmation and readiness' }];
  let idx = 0; const body = h('div', { style: 'display:grid;gap:14px' }); const bar = h('div'); const err = h('p', { class: 'err', role: 'alert' });
  const back = h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { if (idx > 0) { idx--; paint(); } } }, 'Back'); const next = h('button', { class: 'btn', type: 'button' }, 'Next');
  const problem = () => { const s = sections[idx];
    if (s.items && s.items.some((x) => !st[String(x.seq)])) return 'Please choose an option for every statement in this section.';
    if (s.key === 'I' && F.questions.some((q) => !(sa[String(q.seq)] || '').trim())) return 'Please answer each question briefly.';
    if (s.key === 'J') { if (conf.size < F.confirmations.length) return 'Please tick every confirmation.'; if (!readiness) return 'Please tell us how ready you feel.'; if (!sig.trim()) return 'Please type your name as your digital confirmation.'; }
    return ''; };
  next.addEventListener('click', async () => { const p = problem(); err.textContent = p; if (p) return;
    if (idx < sections.length - 1) { idx++; paint(); window.scrollTo(0, 0); return; }
    next.disabled = true;
    try { await api('/activation/assessment', { statements: st, short_answers: sa, clarification_note: clar, confirmations: [...conf], readiness, signature_name: sig }); toast('Your assessment has been received and is under review.', '', 'ok'); done(); } catch (e) { err.textContent = e.message; next.disabled = false; } });
  function paint() {
    const s = sections[idx]; err.textContent = ''; back.hidden = idx === 0; next.textContent = idx === sections.length - 1 ? 'Submit my assessment' : 'Next';
    bar.replaceChildren(h('div', { style: 'display:flex;gap:12px;align-items:center' }, h('div', { class: 'pbar' }, h('span', { style: `width:${Math.round(((idx + 1) / sections.length) * 100)}%` })), h('small', { class: 'faint', text: `Step ${idx + 1} of ${sections.length}` })));
    const kids = [h('h3', { style: 'margin:0', text: s.title })];
    if (s.key === 'info') kids.push(h('p', { class: 'muted', style: 'margin:0', text: 'This short assessment confirms that you have reviewed the Leadership Pack, understand the essential requirements of WODDI leadership, and are ready to serve within approved standards if selected and appointed. It is not an examination. Please answer honestly. If you need clarification, say so, and the right support will be provided.' }),
      h('p', { class: 'notice', text: 'Important: completing activation or this assessment does not mean you have been appointed.' }),
      h('dl', { class: 'dl' }, [['Name', `${a.first_name} ${a.last_name}`], ['Position being considered', a.role_title], ['Place', a.unit], ['Entry pathway', a.route === 'ref3' ? 'Referred candidate' : 'Open-call applicant'], ['Activation start date', fmtDate(a.started_at)]].flatMap(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })])),
      h('p', { class: 'muted', style: 'margin:0', text: 'For the statement sections, choose one option for each statement: Yes, I understand, or Need clarification. If you choose Need clarification, WODDI may follow up before a final decision is made.' }));
    if (s.items) s.items.forEach((x) => { const seg = h('div', { class: 'seg' }, [['yes', 'Yes, I understand'], ['clarify', 'Need clarification']].map(([v, l]) => h('button', { type: 'button', 'aria-pressed': String(st[String(x.seq)] === v), onclick: (e) => { st[String(x.seq)] = v; seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', 'false')); e.currentTarget.setAttribute('aria-pressed', 'true'); } }, l)));
      kids.push(h('div', { class: 'panel', style: 'display:grid;gap:10px' }, h('div', { style: 'display:flex;gap:10px' }, h('b', { class: 'num', text: String(x.seq) }), h('span', { text: x.text })), seg)); });
    if (s.key === 'I') { kids.push(h('p', { class: 'muted', style: 'margin:0', text: 'Keep your answers brief. These questions check understanding, not writing ability.' }));
      F.questions.forEach((q) => { const ta = h('textarea', { class: 'input', rows: 3, placeholder: 'Write your answer here' }); ta.value = sa[String(q.seq)] || ''; ta.addEventListener('input', () => { sa[String(q.seq)] = ta.value; }); kids.push(h('label', { class: 'field' }, h('span', { text: `${q.seq}. ${q.text}` }), ta)); });
      const cn = h('textarea', { class: 'input', rows: 2, placeholder: 'Please state briefly' }); cn.value = clar; cn.addEventListener('input', () => { clar = cn.value; }); kids.push(h('label', { class: 'field' }, h('span', { text: 'Is there any part of WODDI, the network, the leadership structure or your proposed role that you still need clarification on?' }), cn, h('small', { text: 'Leave empty if there is none.' }))); }
    if (s.key === 'J') { F.confirmations.forEach((c) => { const cb = h('input', { type: 'checkbox' }); cb.checked = conf.has(c.seq); cb.addEventListener('change', () => { if (cb.checked) conf.add(c.seq); else conf.delete(c.seq); }); kids.push(h('label', { class: 'check' }, cb, h('span', { text: c.text }))); });
      kids.push(h('b', { text: 'Candidate readiness' }), h('p', { style: 'margin:0', text: 'At this stage, do you feel ready to serve in the leadership position you are being considered for, if selected and formally appointed?' }));
      [['ready', 'Yes, I am ready'], ['interested', 'I am interested, but I need further clarification or support'], ['not_now', 'I do not wish to proceed at this time']].forEach(([v, l]) => { const rb = h('input', { type: 'radio', name: 'readiness' }); rb.checked = readiness === v; rb.addEventListener('change', () => { readiness = v; }); kids.push(h('label', { class: 'check' }, rb, h('span', { text: l }))); });
      const sg = h('input', { class: 'input', placeholder: 'Your full name', value: sig }); sg.addEventListener('input', () => { sig = sg.value; }); kids.push(h('label', { class: 'field' }, h('span', { text: 'Digital confirmation: type your full name' }), sg, h('small', { text: `Dated ${fmtDate(new Date().toISOString().slice(0, 10))} when you submit.` }))); }
    body.replaceChildren(...kids);
  }
  paint();
  return card('Understanding and Readiness Assessment', 'This short assessment confirms that you have reviewed the Leadership Pack and understand the essential requirements. It is not an examination.',
    h('div', { style: 'display:grid;gap:16px' }, a.status === 'returned' ? h('div', { class: 'panel' }, h('b', { text: 'Returned for correction' }), h('p', { style: 'margin:6px 0 0', text: a.return_note || 'Please read the note, correct your answers and submit again.' })) : null, bar, body, err, h('div', { style: 'display:flex;gap:10px' }, back, next)));
}
