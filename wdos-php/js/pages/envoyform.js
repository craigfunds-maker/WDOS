/* v109.45: the WODDI Envoy Appointment Acceptance & Commitment Form, in the WDOS design (Azeez, 22 Nov 2026).
 * For an envoy who has not accepted, this is every page (the server refuses everything else). Part A is filled in from what
 * WDOS has; anything WDOS does not have is left open and marked "Please fill". She ticks Parts B and C, writes her first
 * contribution (Part D), signs with her finger (Part E) and accepts. Afterwards it is her record, with HQ's Part F once given.
 * HQ opens the same view for any envoy (#/hq/envoys/<id>/form) and acknowledges it there. */
import { h, t, tf, icon, toast, fmtDate } from '../ui.js';
import { api } from '../api.js';
import { app, reshell, go, afterSignIn } from '../app.js';

const NETS = [['WGMN', 'WGMN'], ['WNNN', 'WNNN'], ['Cross-cutting', 'Cross-cutting / Other WODDI Programmes']];

function head(title, sub) {
  return h('div', { class: 'eform__head' },
    h('div', { class: 'eform__brand' }, h('img', { src: '/assets/logos/woddi-email.png', alt: 'WODDI', width: 92, height: 60 }),
      h('div', {}, h('small', { text: 'WOMEN OF DIVINE DESTINY INITIATIVE (WODDI)' }), h('b', { text: t('No woman is left out') }))),
    h('h1', { text: t(title) }), h('p', { class: 'eform__tag', text: t('A strategic bridge. A trusted advocate. A contributor to meaningful impact.') }),
    sub ? h('p', { class: 'eform__sub', text: t(sub) }) : null);
}
const part = (letter, title, ...kids) => h('section', { class: 'eform__part' }, h('h2', {}, h('span', { class: 'eform__letter', text: letter }), t(title)), ...kids);

export async function render(root, params) {
  root.append(h('div', { class: 'card', style: 'padding:20px' }, h('p', { class: 'muted', text: t('Loading your appointment form…') })));
  let d;
  try { d = params.hq ? await api('/hq/envoy-acceptances/' + params.id) : await api('/envoy/acceptance'); }
  catch (e) { root.replaceChildren(h('div', { class: 'card', style: 'padding:20px' }, h('p', { class: 'err', text: e.message }))); return; }
  root.replaceChildren(d.record ? viewRecord(d, params) : params.hq ? notYet(d) : fill(d));
}

function notYet(d) {
  return h('div', { class: 'eform' }, head('WODDI Envoy Appointment', 'Acceptance & Commitment Form'),
    h('div', { class: 'eform__note' }, icon('clock', 18), h('span', { text: tf('{1} has not accepted her appointment yet. WDOS reminds her by email and on her phone.', d.envoy.name) })));
}

/* ---- the form she fills ---- */
function fill(d) {
  const p = d.prefill, F = {};
  const missing = [];
  const field = (key, label, opts = {}) => {
    const val = p[key] || '';
    const el = opts.area ? h('textarea', { class: 'input', id: 'ef-' + key, rows: 3, maxlength: 1000, placeholder: opts.ph ? t(opts.ph) : '' }) : h('input', { class: 'input', id: 'ef-' + key, maxlength: opts.max || 190, type: opts.type || 'text', placeholder: opts.ph ? t(opts.ph) : '' });
    el.value = val; F[key] = el;
    const need = !val && !opts.optional; if (need) missing.push(key);
    return h('label', { class: 'field' + (need ? ' eform__need' : val ? ' eform__have' : ''), for: 'ef-' + key },
      h('span', {}, t(label), need ? h('em', { class: 'eform__fill', text: t('Please fill') }) : val ? h('em', { class: 'eform__ok' }, icon('check', 13), t('From your WDOS record')) : null), el,
      opts.hint ? h('small', { text: t(opts.hint) }) : null);
  };
  // network: three choices like the paper form
  F.network = p.network || '';
  const netRow = h('div', { class: 'eform__nets', role: 'radiogroup', 'aria-label': t('Primary network') }, NETS.map(([v, label]) => {
    const b = h('button', { type: 'button', class: 'eform__net', role: 'radio', 'aria-checked': String(F.network === v), onclick: () => { F.network = v; netRow.querySelectorAll('.eform__net').forEach((x, i) => x.setAttribute('aria-checked', String(NETS[i][0] === v))); } }, t(label));
    return b;
  }));
  const ticks = (list, prefix) => list.map((text, i) => { const c = h('input', { type: 'checkbox', id: prefix + i }); return [c, h('label', { class: 'eform__tick', for: prefix + i }, c, h('span', { text: t(text) }))]; });
  const B = ticks(d.part_b, 'eb'), C = ticks(d.part_c, 'ec');
  const decl = h('input', { type: 'checkbox', id: 'edecl' });
  F.signed_name = h('input', { class: 'input', id: 'ef-signed', maxlength: 190 }); F.signed_name.value = p.full_name || '';
  const pad = signaturePad();
  const err = h('p', { class: 'err', role: 'alert' });
  const btn = h('button', { class: 'btn btn--lg', type: 'submit' }, icon('award', 18), ' ', t('Accept my appointment'));
  const progress = h('div', { class: 'eform__progress', 'aria-live': 'polite' });
  const count = () => { const n = B.filter(([c]) => c.checked).length + C.filter(([c]) => c.checked).length, all = B.length + C.length; progress.textContent = tf('{1} of {2} statements ticked', n, all); progress.classList.toggle('is-done', n === all); };
  [...B, ...C].forEach(([c]) => c.addEventListener('change', count)); count();

  const form = h('form', { class: 'eform', novalidate: true, onsubmit: async (ev) => {
    ev.preventDefault(); err.textContent = ''; root2.querySelectorAll('[aria-invalid]').forEach((x) => x.removeAttribute('aria-invalid'));
    const body = {}; for (const k of ['full_name', 'country', 'profession', 'organisation', 'email', 'phone', 'focus', 'first_30_days', 'support_needed', 'preferred_contact']) body[k] = F[k] ? F[k].value.trim() : '';
    body.network = F.network; body.signed_name = F.signed_name.value.trim();
    body.part_b = B.map(([c], i) => c.checked ? i : -1).filter((i) => i >= 0); body.part_c = C.map(([c], i) => c.checked ? i : -1).filter((i) => i >= 0);
    body.declaration = decl.checked; body.signature = pad.empty() ? '' : pad.data();
    if (pad.typed()) { body.signature_typed = true; body.signature_text = pad.text(); body.signature = ''; }
    btn.disabled = true;
    try {
      await api('/envoy/acceptance', body);
      toast(t('Thank you for accepting your appointment'), t('Your envoy dashboard is open.'), 'good');
      app.user = (await api('/me')).user; reshell(); go('#/'); setTimeout(afterSignIn, 900);
    } catch (e) {
      btn.disabled = false; err.textContent = e.message;
      const map = { part_b: 'eb0', part_c: 'ec0', declaration: 'edecl', signature: pad.typed() ? 'esig-typed' : 'esig', signed_name: 'ef-signed' };
      const el = document.getElementById(map[e.code] || 'ef-' + e.code); if (el) { el.setAttribute('aria-invalid', 'true'); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus?.(); }
    }
  } },
  head('WODDI Envoy Appointment', 'Acceptance & Commitment Form'),
  h('div', { class: 'eform__intro' }, icon('award', 20), h('div', {},
    h('b', { text: tf('Welcome, {1}. Please accept your appointment to open your dashboard.', app.user?.first_name || '') }),
    h('p', { text: t('This records your acceptance of the honorary, non-salaried, strategic appointment and the standards guiding the WODDI Envoy relationship. We have filled in what WDOS already knows. Check it, fill anything marked “Please fill”, tick Parts B and C, and sign.') }))),
  part('A', 'Envoy details', h('div', { class: 'eform__grid' },
    field('full_name', 'Full name and title'), field('country', 'Country'),
    field('profession', 'Current position or profession', { ph: 'For example: Teacher, Nurse, Business owner' }), field('organisation', 'Organisation or institution'),
    field('email', 'Email', { type: 'email', optional: true }), field('phone', 'Phone or WhatsApp', { type: 'tel', max: 40 }),
    h('div', { class: 'field' + (F.network ? ' eform__have' : ' eform__need'), id: 'ef-network' }, h('span', {}, t('Primary network'), F.network ? h('em', { class: 'eform__ok' }, icon('check', 13), t('From your WDOS record')) : h('em', { class: 'eform__fill', text: t('Please choose') })), netRow),
    h('div', { class: 'field eform__have' }, h('span', {}, t('Effective date'), h('em', { class: 'eform__ok' }, icon('check', 13), t('Your appointment date'))), h('div', { class: 'input eform__fixed', text: p.effective_date ? fmtDate(p.effective_date) : '' })),
    h('div', { class: 'eform__wide' }, field('focus', 'Principal focus or area of contribution', { area: true, ph: 'Where your experience, network or influence can create the greatest value' })))),
  part('B', 'Appointment acceptance', h('p', { class: 'muted', text: t('Tick each statement.') }), ...B.map(([, l]) => l)),
  part('C', 'Commitment to WODDI standards', h('p', { class: 'muted', text: t('I commit to:') }), ...C.map(([, l]) => l)),
  part('D', 'First alignment and practical contribution', h('p', { class: 'muted', text: t('The Envoy relationship begins with clarity and one meaningful action.') }),
    field('first_30_days', 'One practical contribution I can begin with in the first 30 days', { area: true }),
    field('support_needed', 'Any support, information or connection I need from WODDI HQ to begin effectively', { area: true, optional: true })),
  part('E', 'Declaration and signature',
    h('label', { class: 'eform__tick eform__decl', for: 'edecl' }, decl, h('span', { text: t('I confirm that the information provided is accurate to the best of my knowledge. I have read and understood the WODDI Envoy Appointment & Engagement Pack and accept the appointment on the terms set out in the Pack and this form.') })),
    h('div', { class: 'eform__grid' },
      h('label', { class: 'field', for: 'ef-signed' }, h('span', { text: t('Envoy name') }), F.signed_name),
      h('div', { class: 'field eform__have' }, h('span', { text: t('Date') }), h('div', { class: 'input eform__fixed', text: fmtDate(d.today) })),
      field('preferred_contact', 'Preferred contact or WhatsApp number', { type: 'tel', max: 40, optional: true })),
    h('div', { class: 'field' }, h('span', { text: t('Envoy signature') }), pad.el)),
  h('div', { class: 'eform__foot' }, progress, err, btn,
    h('small', { class: 'muted', text: t('Part F (WODDI acknowledgement) is added automatically when you accept, with your official reference.') })));
  if (!F.preferred_contact.value) F.preferred_contact.value = p.phone || '';
  const root2 = h('div', {}, form);
  if (missing.length) setTimeout(() => F[missing[0]]?.focus?.({ preventScroll: true }), 400);
  return root2;
}

function signaturePad() {
  const c = h('canvas', { class: 'eform__sig', id: 'esig', width: 900, height: 260, tabindex: 0, 'aria-label': t('Signature box') });
  const x = c.getContext('2d'); let drawing = false, used = false, last = null;
  const clear = () => { x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.strokeStyle = '#e6d5df'; x.lineWidth = 2; x.beginPath(); x.moveTo(40, 200); x.lineTo(860, 200); x.stroke(); used = false; };
  clear();
  const pos = (e) => { const r = c.getBoundingClientRect(); return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height]; };
  c.addEventListener('pointerdown', (e) => { drawing = true; last = pos(e); c.setPointerCapture(e.pointerId); e.preventDefault(); });
  c.addEventListener('pointermove', (e) => { if (!drawing) return; const p = pos(e); x.strokeStyle = '#231019'; x.lineWidth = 4.5; x.lineCap = 'round'; x.lineJoin = 'round'; x.beginPath(); x.moveTo(...last); x.lineTo(...p); x.stroke(); last = p; used = true; e.preventDefault(); });
  const up = () => { drawing = false; };
  c.addEventListener('pointerup', up); c.addEventListener('pointercancel', up);
  /* v109.49: if her phone makes drawing hard, she types her full name instead. It counts as her signature. */
  let typed = false;
  const typedIn = h('input', { class: 'input eform__typedsig', id: 'esig-typed', maxlength: 190, autocomplete: 'name', 'aria-label': t('Type your full name as your signature') });
  const drawBox = h('div', { class: 'eform__sigdraw' }, h('small', { class: 'faint', text: t('Sign in the box with your finger or mouse.') }), c, h('button', { type: 'button', class: 'btn btn--ghost btn--s', onclick: clear }, t('Clear')));
  const typeBox = h('div', { class: 'eform__sigtype', hidden: true }, typedIn, h('small', { text: t('Your typed full name counts as your signature.') }));
  const sw = h('button', { type: 'button', class: 'linkish eform__sigswitch' });
  const paint = () => { drawBox.hidden = typed; typeBox.hidden = !typed; sw.textContent = typed ? t('Sign with my finger instead') : t('Cannot sign on your phone? Type your full name instead'); };
  sw.addEventListener('click', () => { typed = !typed; paint(); if (typed) setTimeout(() => typedIn.focus(), 50); });
  paint();
  const wrap = h('div', { class: 'eform__sigwrap' }, drawBox, typeBox, sw);
  return { el: wrap, empty: () => (typed ? typedIn.value.trim().length < 3 : !used), data: () => c.toDataURL('image/png'), typed: () => typed, text: () => typedIn.value.trim() };
}

/* ---- her accepted form (and HQ's view of it) ---- */
function viewRecord(d, params) {
  const r = d.record, B = d.part_b, C = d.part_c;
  const row = (k, v) => h('div', { class: 'eform__row' }, h('span', { text: t(k) }), h('b', { text: v || '—' }));
  const done = (list, got) => list.map((text, i) => h('div', { class: 'eform__done' }, h('span', { class: 'eform__box' + (got.includes(i) ? ' is-on' : '') }, got.includes(i) ? icon('check', 14) : null), h('span', { text: t(text) })));
  const ack = r.ack;
  const ackPart = ack ? part('F', 'WODDI acknowledgement (official use)', h('div', { class: 'eform__grid' }, row('Name and title of authorised representative', ack.name + ', ' + ack.title), row('Date', fmtDate(ack.at)), row('Official reference', ack.ref), row('Effective date', r.effective_date ? fmtDate(r.effective_date) : '')))
    : params.hq ? ackForm(params.id) : part('F', 'WODDI acknowledgement (official use)', h('p', { class: 'muted', text: t('WODDI HQ will acknowledge your form here.') }));
  return h('div', { class: 'eform eform--done' },
    head('WODDI Envoy Appointment', 'Acceptance & Commitment Form'),
    h('div', { class: 'eform__stamp' }, icon('award', 20), h('span', { text: tf('Accepted on {1}', fmtDate(r.accepted_at)) }), ack ? h('span', { class: 'eform__ref', text: ack.ref }) : h('span', { class: 'eform__ref eform__ref--wait', text: t('Waiting for HQ acknowledgement') })),
    part('A', 'Envoy details', h('div', { class: 'eform__grid' }, row('Full name and title', r.full_name), row('Country', r.country), row('Current position or profession', r.profession), row('Organisation or institution', r.organisation),
      row('Email', r.email), row('Phone or WhatsApp', r.phone), row('Primary network', r.network === 'Cross-cutting' ? 'Cross-cutting / Other WODDI Programmes' : r.network), row('Effective date', r.effective_date ? fmtDate(r.effective_date) : ''),
      h('div', { class: 'eform__wide' }, row('Principal focus or area of contribution', r.focus)))),
    part('B', 'Appointment acceptance', ...done(B, r.part_b)),
    part('C', 'Commitment to WODDI standards', ...done(C, r.part_c)),
    part('D', 'First alignment and practical contribution', row('One practical contribution I can begin with in the first 30 days', r.first_30_days), row('Any support, information or connection I need from WODDI HQ', r.support_needed)),
    part('E', 'Declaration and signature', h('div', { class: 'eform__done' }, h('span', { class: 'eform__box is-on' }, icon('check', 14)), h('span', { text: t('Declaration confirmed.') })),
      h('div', { class: 'eform__grid' }, row('Envoy name', r.signed_name), row('Date', fmtDate(r.accepted_at)), row('Preferred contact or WhatsApp number', r.preferred_contact),
        h('div', { class: 'eform__row' }, h('span', { text: t('Envoy signature') }), (String(r.signature || '').startsWith('typed:') ? h('span', { class: 'eform__sigtyped' }, h('b', { text: String(r.signature).slice(6) }), h('small', { text: t('Typed signature') })) : h('img', { class: 'eform__sigimg', src: r.signature, alt: tf('Signature of {1}', r.signed_name) }))))),
    ackPart,
    h('div', { class: 'eform__foot' }, h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => window.print() }, icon('download', 16), ' ', t('Print or save as PDF')),
      params.hq ? h('a', { class: 'btn btn--ghost', href: '#/envoys/forms' }, t('All envoy forms')) : h('a', { class: 'btn btn--ghost', href: '#/' }, t('Back to my dashboard'))));
}
function ackForm(id) {
  const me = app.user || {};
  const name = h('input', { class: 'input', id: 'ack-name', maxlength: 190 }); name.value = `${me.first_name || ''} ${me.last_name || ''}`.trim();
  const title = h('input', { class: 'input', id: 'ack-title', maxlength: 190 }); title.value = me.designation || '';
  const ref = h('input', { class: 'input', id: 'ack-ref', maxlength: 80, placeholder: t('Leave empty and WDOS makes one') });
  const err = h('p', { class: 'err', role: 'alert' });
  const b = h('button', { class: 'btn', type: 'button', onclick: async () => {
    b.disabled = true; err.textContent = '';
    try { const r = await api('/hq/envoy-acceptances/' + id + '/acknowledge', { name: name.value, title: title.value, ref: ref.value }); toast(t('Acknowledged'), tf('Official reference {1}. She has been told by email and on her phone.', r.ref), 'good'); go('#/envoys/' + id + '/form', true); }
    catch (e) { err.textContent = e.message; b.disabled = false; }
  } }, icon('award', 16), ' ', t('Acknowledge for WODDI'));
  return part('F', 'WODDI acknowledgement (official use)', h('div', { class: 'eform__grid' },
    h('label', { class: 'field', for: 'ack-name' }, h('span', { text: t('Name of authorised representative') }), name),
    h('label', { class: 'field', for: 'ack-title' }, h('span', { text: t('Title') }), title),
    h('label', { class: 'field', for: 'ack-ref' }, h('span', { text: t('Official reference') }), ref)), err, b);
}
