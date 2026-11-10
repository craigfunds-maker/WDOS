/* Referral links, the people who came through them, and (for HQ) the activation register. Wording follows the current WDOS. */
import { h, icon, avatar, flag, card, sk, empty, fmt, fmtDate, toast, input, field, tabs, netTag } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { kpi } from './hq.js';

const urlOf = (code) => `${location.origin}/#/r/${code}`;
const ACT_ST = { in_progress: 'In progress', submitted: 'Awaiting review', returned: 'Returned', completed: 'Decided', incomplete: 'Incomplete' };

export async function render(root, params) {
  const hq = app.user.is_hq; const tab = params.tab || '';
  if (tab === 'activations' && params.id && hq) return candidate(root, params.id);
  root.append(tabs([['', hq ? 'Referral links' : 'My referral links'], ['people', 'People referred'], ...(hq ? [['activations', 'Activation register']] : [])], tab, '#/referrals'));
  const body = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(body);
  if (tab === 'activations' && hq) return register(body);
  let d; try { d = await api('/referrals' + (hq ? '?scope=all' : '')); } catch (e) { body.replaceChildren(h('div', { class: 'card' }, empty('x', 'Referrals did not load', e.message))); return; }
  if (tab === 'people') return people(body, d);
  links(body, d, () => render(Object.assign(root, { innerHTML: '' }), params));
}

function links(body, d, reload) {
  const title = (p) => (d.network === 'WNNN' ? p.title_wnnn : p.title_wgmn);
  const pos = h('select', { class: 'input' }, h('option', { value: '', text: 'Join as a member' }), d.positions.map((p) => h('option', { value: p.code, text: d.is_hq ? `${p.title_wgmn} / ${p.title_wnnn}` : title(p) })));
  const net = d.is_hq ? h('select', { class: 'input' }, ['WGMN', 'WNNN'].map((n) => h('option', { value: n, text: n }))) : null; const country = d.is_hq ? h('select', { class: 'input' }, h('option', { value: '', text: 'Any location' })) : null; const state = d.is_hq ? h('select', { class: 'input' }, h('option', { value: '', text: 'Whole country' })) : null;
  if (d.is_hq) { api('/geo/countries').then((r) => country.append(...r.countries.map((c) => h('option', { value: c.id, text: c.name })))).catch(() => {});
    country.addEventListener('change', async () => { state.replaceChildren(h('option', { value: '', text: 'Whole country' })); if (!country.value) return; try { const r = await api('/geo/children/' + country.value); state.append(...r.units.map((s) => h('option', { value: s.id, text: s.name }))); } catch { /* country level only */ } }); }
  const area = input({ maxlength: 120, placeholder: 'LGA, cluster or chapter' }); const label = input({ maxlength: 160, placeholder: 'e.g. Kano State recruitment drive' }); const campaign = input({ maxlength: 160, placeholder: 'e.g. September 2026 mobilisation' }); const err = h('p', { class: 'err', role: 'alert' });
  const areaWrap = h('div', { hidden: true }, field('Name of the place', area)); pos.addEventListener('change', () => { const p = d.positions.find((x) => x.code === pos.value); areaWrap.hidden = !p || !['lga', 'community_cluster', 'chapter'].includes(p.level); });
  const make = h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = '';
    try { await api('/referrals/link', { network: net?.value, position: pos.value, org_unit_id: Number(state?.value || country?.value || 0), area_label: area.value, label: label.value, campaign: campaign.value }); toast('Link ready.', '', 'ok'); reload(); } catch (e) { err.textContent = e.message; } } }, 'Create link');
  const copy = async (l, btn) => { const text = l.kind === 'leader' ? `You have been referred for WODDI volunteer leadership. Open this link to create your WDOS account and begin your three day activation: ${urlOf(l.code)}` : `You are invited to join WODDI. Register here: ${urlOf(l.code)}`;
    try { if (navigator.share) await navigator.share({ text }); else { await navigator.clipboard.writeText(urlOf(l.code)); btn.textContent = 'Link copied'; } } catch { /* cancelled */ } };
  const row = (l) => { const share = h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => copy(l, share) }, 'Copy link');
    return h('div', {}, h('div', { class: 'nrow__ico' }, icon('link', 18)), h('div', { class: 'grow' }, h('b', { text: l.kind === 'leader' ? `${l.role}, ${l.place || 'any location'}` : (l.label || 'Join as a member') + (l.place ? `, ${l.place}` : '') }),
      h('small', { class: 'num', text: urlOf(l.code) }), h('small', { text: `${fmt(l.visits30)} visits in the last 30 days · ${fmt(l.candidates)} candidates · ${fmt(l.registered - l.candidates)} members` + (d.is_hq ? ` · owner ${l.owner}` : '') + (l.campaign && l.campaign !== 'general' ? ` · ${l.campaign}` : '') })),
      h('div', { class: 'tagrow' }, netTag(l.network), l.active ? null : h('span', { class: 'tag tag--bad', text: 'Not active' }), share, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/referrals/link/' + l.id, { active: !l.active }); reload(); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, l.active ? 'Deactivate' : 'Activate'))); };
  body.replaceChildren(
    card(d.is_hq ? 'All referral links' : 'My referral links', d.is_hq ? 'Campaign links for any network, country, unit or position. Every visit and every registration through a link is recorded with its source.' : 'Share a link for each vacant seat under you. Anyone who registers through it is recorded as your referral; Headquarters decides selection and appointment.',
      d.links.length ? h('div', { class: 'list reveal' }, d.links.map(row)) : empty('link', 'No links yet', 'Create the first link below and share it.')),
    card(d.is_hq ? 'Create a campaign link' : 'Create a link for a position under me', 'A link with a position starts the three day Referral Activation for whoever registers through it. A link without one is for women joining as ordinary members.',
      h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, net ? field('Network', net) : null, field('Position', pos), country ? field('Country', country) : null, state ? field('State or region', state) : null, field('Label', label), d.is_hq ? field('Campaign', campaign) : null), areaWrap, err, h('div', {}, make))));
}

function people(body, d) {
  body.replaceChildren(card(`People who came through referral links (${d.people.length})`, 'A referral records who invited someone. Selection and appointment remain with WODDI Headquarters after registration, activation and assessment.',
    d.people.length ? h('div', { class: 'tablewrap reveal' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Person', 'Location', 'Referred by', 'Came from', 'Date'].map((x) => h('th', { text: x })))),
      h('tbody', {}, d.people.map((p) => h('tr', { style: d.is_hq ? '' : 'cursor:default', onclick: () => { if (d.is_hq) location.hash = '#/members/' + p.id; } },
        h('td', {}, h('div', { class: 'who' }, avatar(p, 34), h('div', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.role ? `${p.role}${p.activation ? ' · ' + (ACT_ST[p.activation] || p.activation) : ''}` : 'Membership' })))),
        h('td', {}, h('div', { style: 'display:flex;gap:8px;align-items:center' }, flag(p.country_code), h('span', { text: p.state_region || '' }), netTag(p.network))), h('td', { text: p.referrer_name || '' }), h('td', { text: p.code ? (p.label || p.code) : 'Referral form' }), h('td', { class: 'num', text: fmtDate(p.created_at) }))))))
      : empty('users', 'Nobody has registered through a link yet', 'People who register through one of your links are listed here with the position each was referred for.')));
}

/* ------------------------------------------------------ HQ activation register -- */
async function register(body) {
  let route = sessionStorage.getItem('wdos.actroute') || 'ref3';
  async function load() {
    let d; try { d = await api('/hq/activations?route=' + route); } catch (e) { body.replaceChildren(h('div', { class: 'card' }, empty('x', 'The register did not load', e.message))); return; }
    const c = d.counts; const seg = h('div', { class: 'seg' }, Object.entries(d.routes).map(([k, v]) => h('button', { type: 'button', 'aria-pressed': String(k === route), onclick: () => { route = k; sessionStorage.setItem('wdos.actroute', k); load(); } }, v.label)));
    const openForm = h('div');
    const parts = [h('div', { class: 'toolbar' }, seg, h('div', { class: 'grow' }), h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => { if (openForm.firstChild) openForm.replaceChildren(); else openForm.replaceChildren(openCard(route, load)); } }, icon('plus', 18), 'Open an activation for an applicant')), openForm,
      h('div', { class: 'row row--4' }, kpi('users', 'Registered', c.registered, [h('span', { text: `${c.started} started` })]), kpi('inbox', 'Awaiting review', c.awaiting_review, [h('span', { text: 'Assessment submitted' })]), kpi('check', 'Decided', c.decided, [h('span', { text: `${c.selected} selected` })]), kpi('clock', 'Incomplete', c.incomplete, [h('span', { text: 'Deadline passed' })]))];
    if (!d.content.complete) parts.push(h('div', { class: 'panel' }, h('b', { text: 'Programme content is not fully loaded' }), h('p', { class: 'faint', style: 'margin:6px 0 0;font-size:13.5px', text: `${d.content.days_loaded} of ${d.content.days_expected} days are loaded` + (d.content.assessment ? '.' : ', and the readiness assessment is not loaded.') + ' Candidates can work up to the last loaded day. Load the rest on the setup page, section 8.' })));
    parts.push(card(d.routes[route].label, route === 'open7' ? 'There is no public link for this route. HQ opens it for an applicant after screening.' : 'Referred candidates, WGMN and WNNN forms.',
      d.items.length ? h('div', { class: 'tablewrap reveal' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Candidate', 'Position', 'Stage', 'Deadline', 'Outcome'].map((x) => h('th', { text: x })))),
        h('tbody', {}, d.items.map((a) => h('tr', { tabindex: '0', onclick: () => { location.hash = '#/referrals/activations/' + a.id; } },
          h('td', {}, h('div', { class: 'who' }, avatar({ ...a, id: a.user_id }, 34), h('div', {}, h('b', { text: `${a.first_name} ${a.last_name}` }), h('small', { text: a.referrer ? 'Referred by ' + a.referrer : 'Opened by HQ' })))),
          h('td', {}, h('div', {}, h('span', { text: a.role }), h('div', { style: 'display:flex;gap:8px;align-items:center;margin-top:2px' }, flag(a.country_code), h('small', { class: 'faint', text: a.place }), netTag(a.network)))),
          h('td', {}, h('div', { class: 'tagrow' }, h('span', { class: 'tag' + (a.status === 'submitted' ? ' tag--wgmn' : a.status === 'incomplete' ? ' tag--bad' : ''), text: a.stage }), a.extension_status === 'requested' ? h('span', { class: 'tag tag--bad', text: 'Extension asked' }) : null)),
          h('td', { class: 'num', text: fmtDate(a.deadline) }), h('td', { text: a.outcome ? d.outcomes[a.outcome] : '' }))))))
        : empty('route', 'Nothing recorded yet', route === 'ref3' ? 'Candidates appear here as soon as they register through a referral link or the referral form.' : 'Open a 7-Day activation for an applicant after screening, with the button above.')));
    body.replaceChildren(...parts);
  }
  load();
}
function placeFields() {
  const pos = h('select', { class: 'input' }, h('option', { value: '', text: 'Choose the position' })); const country = h('select', { class: 'input' }, h('option', { value: '', text: 'Choose the country' })); const state = h('select', { class: 'input' }, h('option', { value: '', text: 'Whole country' })); const area = input({ maxlength: 120, placeholder: 'LGA, cluster or chapter, where the position needs one' });
  api('/positions').then((r) => pos.append(...r.positions.map((p) => h('option', { value: p.code, text: `${p.title_wgmn} / ${p.title_wnnn}` })))).catch(() => {});
  api('/geo/countries').then((r) => country.append(...r.countries.map((c) => h('option', { value: c.id, text: c.name })))).catch(() => {});
  country.addEventListener('change', async () => { state.replaceChildren(h('option', { value: '', text: 'Whole country' })); if (!country.value) return; try { const r = await api('/geo/children/' + country.value); state.append(...r.units.map((s) => h('option', { value: s.id, text: s.name }))); } catch { /* country only */ } });
  return { pos, country, state, area, unit: () => Number(state.value || country.value || 0) };
}
function openCard(route, done) {
  const email = input({ type: 'email', placeholder: 'Email of the applicant\'s WDOS account' }); const f = placeFields(); const err = h('p', { class: 'err', role: 'alert' });
  return card('Open an activation for an applicant', 'The applicant joins WDOS first. Opening the activation sends the applicant a notification and starts the deadline today.', h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, field('Applicant', email), field('Position being considered', f.pos), field('Country', f.country), field('State or region', f.state), field('Name of the place', f.area)), err,
    h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async () => { err.textContent = ''; try { await api('/hq/activations/open', { email: email.value, position: f.pos.value, org_unit_id: f.unit(), area_label: f.area.value, route }); toast('Activation opened', '', 'ok'); done(); } catch (e) { err.textContent = e.message; } } }, 'Open activation'))));
}
async function candidate(root, id) {
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(5))); root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/referrals/activations', style: 'justify-self:start;margin-bottom:12px' }, 'Back to the register'), box);
  async function load() {
    let d; try { d = await api('/hq/activations/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This candidate did not open', e.message))); return; }
    const a = d.activation, c = a.candidate, ex = a.existing; const parts = [];
    const act = async (payload, okMsg) => { try { const r = await api('/hq/activations/' + id, payload); toast(okMsg, r.title || '', 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } };
    parts.push(h('section', { class: 'hero' }, avatar(c, 72), h('div', { class: 'grow' }, h('h1', { class: 'display', text: `${c.first_name} ${c.last_name}` }), h('p', { text: `${a.role_title}, ${a.unit}` }),
      h('div', { class: 'hero__meta' }, flag(c.country_code), netTag(a.network), h('span', { class: 'tag', text: a.route_label }), h('span', { class: 'tag', text: ACT_ST[a.status] || a.status }), a.outcome ? h('span', { class: 'tag tag--wnnn', text: d.outcomes[a.outcome] }) : null, c.seated ? h('span', { class: 'tag tag--wnnn', text: 'Appointed' }) : null)), h('a', { class: 'btn btn--quiet btn--s', href: '#/members/' + c.id, text: 'Open member record' })));
    parts.push(card('Candidate information', null, h('dl', { class: 'dl' }, [['Email', c.email], ['Phone', c.phone], ['Lives in', [c.community, c.lga, c.state_region].filter(Boolean).join(', ')], ['Referred by', a.referrer || 'Opened by HQ'], ['Started', fmtDate(a.started_at)], ['Deadline', fmtDate(a.deadline)], a.submitted_at ? ['Assessment submitted', fmtDate(a.submitted_at)] : null].filter(Boolean).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', { text: v })]))));
    if (a.extension_status === 'requested') { const until = input({ type: 'date' });
      parts.push(card('Extension request', a.extension_reason, h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:end' }, field('New deadline', until), h('button', { class: 'btn', type: 'button', onclick: () => act({ action: 'extension', approve: true, until: until.value }, 'Extension approved') }, 'Approve'), h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => act({ action: 'extension', approve: false }, 'Extension declined') }, 'Decline')))); }
    parts.push(card('Activation days', null, a.days.length ? h('div', { style: 'display:grid;gap:12px' }, a.days.map((dd) => h('div', { class: 'panel', style: 'display:grid;gap:8px' }, h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { text: `Day ${dd.day}: ${dd.title}` }), h('span', { class: 'tag' + (dd.status === 'completed' ? ' tag--wnnn' : ''), text: dd.status === 'completed' ? 'Completed ' + fmtDate(dd.completed_at) : dd.status === 'in_progress' ? 'In progress' : 'Not started' })),
      ...(dd.questions || []).map((q) => h('div', {}, h('small', { class: 'faint', text: q.label }), h('p', { style: 'margin:2px 0 0;white-space:pre-wrap', text: dd.answers?.[q.key] || 'No answer yet' }))), (dd.acks || []).length ? h('small', { class: 'faint', text: `${dd.acked.length} of ${dd.acks.length} confirmations ticked` }) : null))) : h('p', { class: 'faint', text: 'No days are loaded for this route.' })));
    if (ex) { const clar = a.form.statements.filter((x) => ex.statements?.[String(x.seq)] === 'clarify');
      parts.push(card('Understanding and Readiness Assessment', `Signed ${ex.signature_name}, ${fmtDate(ex.submitted_at)}`, h('div', { style: 'display:grid;gap:14px' },
        h('div', { class: 'metrics' }, h('div', {}, h('div', { class: 'metrics__n num', text: String(a.form.statements.length - clar.length) }), h('small', { text: 'Yes, I understand' })), h('div', {}, h('div', { class: 'metrics__n num', text: String(clar.length) }), h('small', { text: 'Need clarification' })), h('div', {}, h('div', { class: 'metrics__n', style: 'font-size:18px', text: { ready: 'Yes, I am ready', interested: 'Interested, needs clarification or support', not_now: 'Does not wish to proceed now' }[ex.readiness] || '' }), h('small', { text: 'Candidate readiness' }))),
        clar.length ? h('div', {}, h('b', { text: 'Statements needing clarification' }), h('ul', { style: 'margin:6px 0 0;padding-left:20px' }, clar.map((x) => h('li', { text: `${x.seq}. ${x.text}` })))) : null,
        ...a.form.questions.map((q) => h('div', {}, h('small', { class: 'faint', text: `${q.seq}. ${q.text}` }), h('p', { style: 'margin:2px 0 0;white-space:pre-wrap', text: ex.short_answers?.[String(q.seq)] || '' }))),
        ex.clarification_note ? h('div', {}, h('small', { class: 'faint', text: 'Clarification asked for' }), h('p', { style: 'margin:2px 0 0;white-space:pre-wrap', text: ex.clarification_note })) : null))); }
    if (['submitted', 'completed'].includes(a.status)) {
      const outcome = h('select', { class: 'input' }, h('option', { value: '', text: 'Choose the outcome' }), Object.entries(d.outcomes).map(([v, l]) => h('option', { value: v, text: l }))); outcome.value = a.outcome || ''; const note = h('textarea', { class: 'input', rows: 2 }); note.value = a.outcome_note || ''; const disclosed = h('textarea', { class: 'input', rows: 2 }); disclosed.value = a.disclosed || ''; const ret = h('textarea', { class: 'input', rows: 2 });
      parts.push(card('Decision', 'The outcome and the internal note stay with HQ. The candidate reads only the message you write below.', h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, field('Outcome', outcome), field('Internal note', note)), field('Message shown to the candidate (optional)', disclosed, 'If left empty the candidate reads: Your assessment has been reviewed. Headquarters will contact you about the next step.'),
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, h('button', { class: 'btn', type: 'button', onclick: () => act({ action: 'outcome', outcome: outcome.value, note: note.value, disclosed: disclosed.value }, 'Outcome recorded') }, 'Record outcome'),
          a.outcome === 'selected' && !c.seated ? h('button', { class: 'btn btn--lime', type: 'button', onclick: () => { if (confirm(`Appoint ${c.first_name} as ${a.role_title}, ${a.unit}?`)) act({ action: 'appoint' }, 'Appointed'); } }, 'Appoint to this seat') : null),
        a.status === 'submitted' ? h('div', { style: 'display:grid;gap:10px;border-top:1px solid var(--line-soft);padding-top:14px' }, field('Note to the candidate (the candidate reads this)', ret), h('div', {}, h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => act({ action: 'return', note: ret.value }, 'Returned for correction') }, 'Return for correction'))) : null)));
    }
    box.replaceChildren(...parts);
  }
  load();
}
