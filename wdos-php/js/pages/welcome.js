/* The front door. A newcomer picks her network by its logo, and the form that
 * opens carries that network all the way into her record. The same form
 * finishes a Google sign-up (complete = true). */
import { h, clear, icon, NETS, googleMark, theme, toast, langPills, listenBtn, i18n } from '../ui.js';
import { africaMap } from '../goodmother.js';
import { api } from '../api.js';
import { app, signedIn, refreshUser, go, signOut, setLanguage } from '../app.js';

export function pubBar(right) {
  const tb = h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Switch between dark and light', onclick: () => { theme.toggle(); tb.replaceChildren(icon(theme.get() === 'light' ? 'moon' : 'sun')); } }, icon(theme.get() === 'light' ? 'moon' : 'sun'));
  return h('div', { class: 'pubtop' }, h('header', { class: 'pubbar' }, h('a', { href: '#/', 'aria-label': 'WODDI home' }, h('img', { src: '/assets/logos/woddi.png', alt: 'WODDI, The Nurturer' })), h('div', { class: 'grow' }), tb, right || null), langPills(setLanguage));
}
export const pubFoot = () => h('footer', { class: 'pubfoot' }, h('span', { text: 'WODDI Digital Operating System' }), h('span', { text: 'No Woman Is Left Out' }), h('span', { class: 'num', text: 'Build ' + (app.config.build || '') }));

const NET_ABOUT = {
  WGMN: ['For Women Aged 30 and Above', 'A network for women to grow in purpose, strengthen family and societal influence, and build a lasting legacy.'],
  WNNN: ['For Women Aged 18 to 29', 'A network for young women to build a strong identity, discover purpose, develop leadership skills and become ready for greater influence.'],
};
const AFRICA = 'AO BF BI BJ BW CD CF CG CI CM CV DJ DZ EG EH ER ET GA GH GM GN GQ GW KE KM LR LS LY MA MG ML MR MU MW MZ NA NE NG RW SC SD SL SN SO SS ST SZ TD TG TN TZ UG ZA ZM ZW'.split(' ');
/* A registration that was interrupted can be picked up again on the same phone. The password is never kept. */
const DRAFT = 'wdos.join';
const draft = { get() { try { return JSON.parse(localStorage.getItem(DRAFT) || 'null') || {}; } catch { return {}; } }, set(d) { try { localStorage.setItem(DRAFT, JSON.stringify(d)); } catch { /* private mode */ } }, clear() { try { localStorage.removeItem(DRAFT); } catch { /* ignore */ } } };
const ageOf = (dob) => { const d = new Date(dob + 'T00:00:00Z'); if (isNaN(d)) return null; const n = new Date(); let a = n.getUTCFullYear() - d.getUTCFullYear(); if (n.getUTCMonth() < d.getUTCMonth() || (n.getUTCMonth() === d.getUTCMonth() && n.getUTCDate() < d.getUTCDate())) a--; return a; };

export async function render(root, params) {
  const complete = !!params.complete; const u = app.user;
  /* A referral: either a link someone shared (#/r/CODE) or the network's referral form (#/join/WGMN). */
  let link = null, refMeta = null; const leaderForm = !!params.leader && !complete;
  if (params.ref && !complete) {
    try { const r = await api('/ref/' + encodeURIComponent(params.ref)); link = r.link; params.network = link.network; }
    catch (e) { root.append(h('div', { class: 'public' }, pubBar(h('a', { class: 'btn btn--quiet', href: '#/login', text: 'Sign in' })), h('main', { class: 'join' }, h('div', { class: 'card', style: 'max-width:560px;margin:40px auto' }, h('div', { class: 'card__body', style: 'display:grid;gap:12px;text-align:center' }, h('h2', { text: 'This invitation link is not active' }), h('p', { class: 'muted', text: 'Ask the person who sent it for a new link, or join WODDI directly.' }), h('div', {}, h('a', { class: 'btn', href: '#/', text: 'Join as a member' }))))), pubFoot())); return; }
  }
  const candidate = leaderForm || (link && link.position);
  if (candidate) refMeta = await api('/positions').catch(() => null);
  const saved = complete ? {} : draft.get();
  let chosen = ['WGMN', 'WNNN'].includes(String(params.network).toUpperCase()) ? String(params.network).toUpperCase() : (!link && !leaderForm && saved.network) || '';
  let stepNo = Number(saved.step) || 0;
  const nets = h('div', { class: 'nets', role: 'radiogroup', 'aria-label': 'Choose your network' });
  const wrap = h('div', { class: 'formwrap' }); const inner = h('div'); wrap.append(inner);

  const cardFor = (code) => {
    const n = NETS[code];
    const input = h('input', { type: 'radio', name: 'network', value: code, 'aria-label': `${n.name}. ${n.age}.` });
    const c = h('label', { class: 'net net--' + code.toLowerCase() }, input, h('img', { class: 'net__disc', src: n.disc, alt: '' }),
      h('div', { class: 'net__text' }, h('div', { class: 'net__name', text: n.name }), h('div', { class: 'net__for' }, icon('users', 18), h('span', { text: NET_ABOUT[code][0] })), h('p', { class: 'net__desc', text: NET_ABOUT[code][1] }),
      h('div', { class: 'net__pick' })));
    input.addEventListener('change', () => choose(code, true));
    return c;
  };
  const cards = { WGMN: cardFor('WGMN'), WNNN: cardFor('WNNN') };
  nets.append(cards.WGMN, cards.WNNN);

  function choose(code, scroll) {
    chosen = code; nets.classList.add('has-choice');
    for (const k of Object.keys(cards)) {
      const on = k === code; cards[k].classList.toggle('is-on', on); cards[k].querySelector('input').checked = on;
      cards[k].querySelector('.net__pick').replaceChildren(...(on ? [icon('check', 16), 'Selected'] : ['Choose ' + k]));
    }
    buildForm(); wrap.classList.add('is-open');
    if (scroll) setTimeout(() => (inner.firstElementChild || inner).scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
  }
  for (const k of Object.keys(cards)) cards[k].querySelector('.net__pick').replaceChildren('Choose ' + k);

  /* fields live outside buildForm so switching network keeps what she typed */
  const F = {};
  const field = (key, label, attrs = {}, hint) => { F[key] = F[key] || h('input', { class: 'input', id: 'f-' + key, ...attrs }); return h('label', { class: 'field', for: 'f-' + key }, h('span', { text: label }), F[key], hint ? h('small', { text: hint }) : null); };
  F.country = h('select', { class: 'input', id: 'f-country', required: true }, h('option', { value: '', text: 'Choose your country' }));
  /* State and LGA. A list is offered wherever one exists: the states WDOS already holds as units (Nigeria), the
   * published list of states or regions for every other country, and the 774 LGAs of Nigeria. Anything not on a
   * list can still be typed, so nobody is blocked by a gap in the lists. */
  const NOT_LISTED = '__type__';
  F.state = h('select', { class: 'input', id: 'f-state' }); F.stateText = h('input', { class: 'input', id: 'f-state', maxlength: 120, autocomplete: 'address-level1' });
  F.lgaSel = h('select', { class: 'input', id: 'f-lga' }); F.lga = h('input', { class: 'input', id: 'f-lga', maxlength: 120 });
  let countries = [], stateMode = 'text', lgaMode = 'text', geoStates = null, geoLgas = null;
  const getJson = (url) => fetch(url).then((r) => (r.ok ? r.json() : {})).catch(() => ({}));
  const stateSlot = h('div'), lgaSlot = h('div');
  const paintState = () => stateSlot.replaceChildren(h('label', { class: 'field', for: 'f-state' }, h('span', { text: 'State or region' }), stateMode === 'text' ? F.stateText : F.state));
  const diaspora = () => { const c = countries.find((x) => String(x.id) === F.country.value); return !!c && !AFRICA.includes(c.code); };
  const paintLga = () => { lgaSlot.replaceChildren(h('label', { class: 'field', for: 'f-lga' }, h('span', { text: diaspora() ? 'City' : 'LGA or district' }), lgaMode === 'select' ? F.lgaSel : F.lga)); const cl = document.querySelector('label[for="f-community"] > span'); if (cl) cl.textContent = diaspora() ? 'Neighbourhood (optional)' : 'Community or ward'; const wl = document.querySelector('label[for="f-ward"]'); if (wl) wl.hidden = diaspora(); const sl = document.querySelector('label[for="f-state"] > span'); if (sl) sl.textContent = diaspora() ? 'State or province (optional)' : 'State or region'; };
  const opt = (value, text) => { const o = document.createElement('option'); o.value = value; o.textContent = text; return o; };
  const stateName = () => (stateMode === 'text' ? F.stateText.value.trim() : F.state.value && F.state.value !== NOT_LISTED ? F.state.selectedOptions[0].textContent : '');
  async function fillLgas() {
    const c = countries.find((x) => String(x.id) === F.country.value); lgaMode = 'text';
    if (c && c.code === 'NG' && stateMode !== 'text' && stateName()) {
      geoLgas = geoLgas || await getJson('/assets/geo/ng-lgas.json'); const list = geoLgas[stateName()];
      if (list && list.length) { F.lgaSel.replaceChildren(h('option', { value: '', text: 'Choose your LGA' }), ...list.map((n) => opt(n, n)), h('option', { value: NOT_LISTED, text: 'My LGA is not listed' })); lgaMode = 'select'; }
    }
    paintLga();
  }
  F.state.addEventListener('change', () => { if (F.state.value === NOT_LISTED) { stateMode = 'text'; F.stateText.value = ''; paintState(); F.stateText.focus(); } fillLgas(); });
  F.lgaSel.addEventListener('change', () => { if (F.lgaSel.value === NOT_LISTED) { lgaMode = 'text'; F.lga.value = ''; paintLga(); F.lga.focus(); } });
  const onCountry = async () => {
    const c = countries.find((x) => String(x.id) === F.country.value);
    stateMode = 'text'; F.stateText.value = '';
    if (c && Number(c.children) > 0) {
      try { const r = await api('/geo/children/' + c.id); F.state.replaceChildren(h('option', { value: '', text: 'Choose your state' }), ...r.units.map((u2) => opt(u2.id, u2.name))); stateMode = 'select'; } catch { /* fall back to the published list or typing */ }
    }
    if (c && stateMode === 'text') {
      geoStates = geoStates || await getJson('/assets/geo/states.json'); const list = geoStates[c.code];
      if (list && list.length) { F.state.replaceChildren(h('option', { value: '', text: 'Choose your state or region' }), ...list.map((n) => opt(n, n)), h('option', { value: NOT_LISTED, text: 'My state or region is not listed' })); stateMode = 'list'; }
    }
    paintState(); await fillLgas();
  };
  F.country.addEventListener('change', onCountry);
  /* bring back where she had reached: country, then state, then LGA, as each list arrives */
  const restorePlace = async () => {
    if (!saved.country_id || ![...F.country.options].some((o) => o.value === String(saved.country_id))) return;
    F.country.value = String(saved.country_id); await onCountry();
    if (saved.state) { if (stateMode === 'text') F.stateText.value = saved.state; else { const o = [...F.state.options].find((x) => x.textContent === saved.state); if (o) F.state.value = o.value; else { stateMode = 'text'; F.stateText.value = saved.state; paintState(); } } await fillLgas(); }
    if (saved.lga) { if (lgaMode === 'select' && [...F.lgaSel.options].some((o) => o.value === saved.lga)) F.lgaSel.value = saved.lga; else { lgaMode = 'text'; F.lga.value = saved.lga; paintLga(); } }
  };
  api('/geo/countries').then((r) => { countries = r.countries; F.country.append(...countries.map((c) => opt(c.id, c.name))); restorePlace(); }).catch(() => toast('Countries did not load', 'Check your connection and reload the page.', 'bad'));
  paintState(); paintLga();
  let gmap = null;

  function buildForm() {
    const n = NETS[chosen]; const other = chosen === 'WGMN' ? 'WNNN' : 'WGMN';
    const err = h('p', { class: 'err', role: 'alert' });
    const ageNote = h('small', {});
    const submit = h('button', { class: 'btn btn--block', type: 'submit', text: complete ? `Finish joining ${chosen}` : candidate ? 'Create my account and begin my Referral Activation' : `Join ${chosen}` });
    const consent = F.consent || (F.consent = h('input', { type: 'checkbox', id: 'f-consent' }));
    let refBlock = null;
    if (candidate && refMeta) {
      F.position = F.position || h('select', { class: 'input', id: 'f-position' }); F.refCat = F.refCat || h('select', { class: 'input', id: 'f-refcat' }, h('option', { value: '', text: 'Choose' }), Object.entries(refMeta.categories).map(([v, l]) => h('option', { value: v, text: l }))); F.refName = F.refName || h('input', { class: 'input', id: 'f-refname', maxlength: 160 });
      const keep = F.position.value; F.position.replaceChildren(h('option', { value: '', text: 'Choose the position' }), ...refMeta.positions.map((p) => h('option', { value: p.code, text: chosen === 'WNNN' ? p.title_wnnn : p.title_wgmn }))); F.position.value = keep;
      refBlock = h('fieldset', {}, h('legend', { text: 'The position and where you will serve' }),
        link ? h('p', { class: 'muted', style: 'margin:0 0 12px', text: `You have been referred by ${link.owner} for consideration as ${link.role}${link.place ? ', ' + link.place : ''}.` })
          : h('div', { class: 'grid2' }, h('label', { class: 'field', for: 'f-position' }, h('span', { text: 'Position being considered for' }), F.position), h('label', { class: 'field', for: 'f-refcat' }, h('span', { text: 'Who referred you?' }), F.refCat), h('label', { class: 'field', for: 'f-refname' }, h('span', { text: 'Name of the person who referred you' }), F.refName)),
        h('p', { class: 'notice', text: refMeta.notice }));
    }
    const form = h('form', { class: 'jform reveal', novalidate: true },
      h('div', { class: 'jform__head' }, h('img', { src: n.disc, alt: '' }), h('div', {}, h('h2', { text: complete ? 'Finish your profile' : `Join ${chosen}` }), h('p', { class: 'muted', text: `${n.name}. ${n.age}.` }))),
      !complete && app.config.google ? h('div', { style: 'margin-top:18px' }, h('a', { class: 'gbtn', href: '/api/auth/google/start?network=' + chosen }, googleMark(), `Continue with Google as ${chosen}`), h('div', { class: 'or', text: 'or fill in the form' })) : null,
      h('fieldset', {}, h('legend', { text: 'About you' }), h('div', { class: 'grid2' },
        field('first_name', 'First name', { required: true, maxlength: 80, autocomplete: 'given-name', value: u?.first_name || saved.first_name || '' }),
        field('last_name', 'Surname', { required: true, maxlength: 80, autocomplete: 'family-name', value: u?.last_name || saved.last_name || '' }),
        h('label', { class: 'field', for: 'f-dob' }, h('span', { text: 'Date of birth' }), F.dob || (F.dob = h('input', { class: 'input', id: 'f-dob', type: 'date', required: true, autocomplete: 'bday', value: saved.dob || '' })), ageNote),
        field('phone', 'Phone or WhatsApp number', { type: 'tel', required: true, maxlength: 24, autocomplete: 'tel', placeholder: '+234 801 234 5678', value: saved.phone || '' }, 'Include the country code.'))),
      h('fieldset', {}, h('legend', { text: 'Where you are' }), h('div', { class: 'grid2' },
        h('label', { class: 'field', for: 'f-country' }, h('span', { text: 'Country' }), F.country), stateSlot,
        lgaSlot, field('community', 'Community or ward', { maxlength: 120, value: saved.community || '' }), field('ward', 'Official ward (optional)', { maxlength: 120, value: saved.ward || '' }, 'Only if it is different from your community.'))),
      refBlock,
      complete ? null : h('fieldset', {}, h('legend', { text: 'Your sign-in' }), h('div', { class: 'grid2' },
        field('email', 'Email address (optional)', { type: 'email', maxlength: 190, autocomplete: 'email', value: saved.email || '' }, 'No email? Leave this empty and sign in with your phone number.'),
        field('password', 'Password or PIN', { type: 'password', required: true, minlength: 6, autocomplete: 'new-password' }, 'At least 6 letters or numbers. Choose one you will remember. No code is sent to you.'))),
      h('fieldset', {}, h('legend', { text: 'Your agreement' }), h('p', { class: 'muted', style: 'margin:0 0 12px', text: 'WODDI keeps your name, phone number and location so your chapter and your leaders can reach you. WODDI does not sell or share them outside WODDI.' }), h('label', { class: 'check' }, consent, h('span', { text: 'I agree to the WODDI Code of Conduct and to WODDI HQ contacting me about my network.' }))),
      err, submit,
      complete ? h('p', { class: 'muted', style: 'margin-top:12px;font-size:14px' }, 'Signed in as ' + (u?.email || '') + '. ', h('a', { href: '#', onclick: (e) => { e.preventDefault(); signOut(); }, text: 'Use a different account' })) : null);
    const checkAge = () => {
      const a = F.dob.value ? ageOf(F.dob.value) : null; ageNote.replaceChildren(); ageNote.style.color = '';
      if (a === null) { ageNote.textContent = chosen === 'WNNN' ? 'WNNN is for ages 18 to 29.' : 'WGMN is for ages 30 and above.'; return true; }
      if (a < 18) { ageNote.style.color = 'var(--bad)'; ageNote.textContent = 'WODDI networks are open to women aged 18 and above.'; return false; }
      const fits = a <= 29 ? 'WNNN' : 'WGMN';
      if (fits !== chosen && link) { ageNote.style.color = 'var(--bad)'; ageNote.textContent = `This link is for ${chosen}. From your date of birth you belong to ${fits}. Ask your referrer for the correct link.`; return false; }
      if (fits !== chosen) { ageNote.style.color = 'var(--bad)'; ageNote.append(`At ${a}, your network is ${fits}. `, h('a', { href: '#', onclick: (e) => { e.preventDefault(); choose(fits, false); }, text: `Switch to ${fits}` })); return false; }
      ageNote.textContent = `Age ${a}. This matches ${chosen}.`; return true;
    };
    F.dob.onchange = checkAge; F.dob.oninput = checkAge; checkAge();
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); err.textContent = '';
      if (!checkAge()) { err.textContent = 'Your date of birth does not match this network. Use the switch link under the date.'; F.dob.focus(); return; }
      const body = { network: chosen, first_name: F.first_name.value, last_name: F.last_name.value, dob: F.dob.value, phone: F.phone.value, country_id: Number(F.country.value) || 0,
        state_id: stateMode === 'select' ? Number(F.state.value) || 0 : 0, state_region: stateMode === 'select' ? '' : stateName(), lga: lgaMode === 'select' ? F.lgaSel.value : F.lga.value, community: F.community.value, ward: diaspora() ? '' : F.ward.value, consent: consent.checked };
      if (stateMode === 'list' && !body.state_region && !diaspora()) { err.textContent = 'Choose your state or region.'; F.state.focus(); return; }
      if (lgaMode === 'select' && !body.lga) { err.textContent = 'Choose your LGA.'; F.lgaSel.focus(); return; }
      if (!complete) { body.email = F.email.value.trim(); body.password = F.password.value; }
      body.lang = i18n.lang;
      if (link) body.referral = { code: link.code };
      else if (candidate) {
        if (!F.position.value) { err.textContent = 'Choose the position you are being considered for.'; F.position.focus(); return; }
        if (!F.refCat.value) { err.textContent = 'Please tell us who referred you.'; F.refCat.focus(); return; }
        if (!F.refName.value.trim()) { err.textContent = 'Please type the name of the person who referred you.'; F.refName.focus(); return; }
        body.referral = { position: F.position.value, category: F.refCat.value, name: F.refName.value };
      }
      submit.disabled = true; const label = submit.textContent; submit.textContent = 'Creating your membership…';
      try {
        const r = await api(complete ? '/auth/complete-profile' : '/auth/register', body);
        toast(`Welcome to ${chosen}`, `Your membership number is ${r.user.member_no}.`, 'ok');
        draft.clear();
        if (complete) { await refreshUser(); }
        signedIn(r.user);
        if (r.user.todo && r.user.todo.activation) go('#/activation'); else if (r.user.journey) go('#/journey');
      } catch (ex) {
        err.textContent = ex.message; submit.disabled = false; submit.textContent = label;
        const map = { name: 'first_name', phone: 'phone', phone_taken: 'phone', dob: 'dob', age: 'dob', age_network: 'dob', country: 'country', email: 'email', email_taken: 'email', password: 'password', consent: 'consent', state: stateMode === 'text' ? 'stateText' : 'state' };
        const bad = F[map[ex.code]]; if (bad) { const fs = bad.closest('fieldset'); const i = steps.indexOf(fs); if (i >= 0) show(i); bad.focus(); }
      }
    });
    const steps = [...form.querySelectorAll(':scope > fieldset')]; const bar = h('div', { class: 'jbar jbar--form', role: 'img' }); const stepLabel = h('small', { class: 'faint' });
    const back = h('button', { class: 'btn btn--ghost', type: 'button', text: 'Back' }); const next = h('button', { class: 'btn', type: 'button', text: 'Continue' });
    const listen = listenBtn(() => { const fs = steps[stepNo]; return [fs.querySelector('legend')?.textContent, ...[...fs.querySelectorAll('label > span, p, small')].map((x) => x.textContent)]; });
    const navRow = h('div', { class: 'stepnav' }, back, h('div', { class: 'grow' }), next); submit.remove(); navRow.append(submit);
    form.insertBefore(h('div', { class: 'stephead' }, bar, h('div', { style: 'display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap' }, stepLabel, listen)), steps[0]); form.insertBefore(navRow, err.nextSibling);
    /* what must be right before she can leave a screen: said in plain words, on that screen */
    const checkStep = (fs) => {
      const has = (el) => fs.contains(el);
      if (has(F.first_name)) { if (F.first_name.value.trim().length < 2 || F.last_name.value.trim().length < 2) { F.first_name.focus(); return 'Enter your first name and surname.'; } if (!F.dob.value) { F.dob.focus(); return 'Enter your date of birth.'; } if (!checkAge()) { F.dob.focus(); return 'Your date of birth does not match this network. Use the switch link under the date.'; } if (F.phone.value.replace(/[^\d]/g, '').length < 8) { F.phone.focus(); return 'Enter your phone number with the country code.'; } }
      if (has(F.country)) { if (!F.country.value) { F.country.focus(); return 'Choose your country.'; } if (!diaspora() && !stateName() && !(stateMode === 'select' && F.state.value)) { (stateMode === 'text' ? F.stateText : F.state).focus(); return 'Choose your state or region.'; } }
      if (F.password && has(F.password)) { if (F.email.value.trim() && !/^\S+@\S+\.\S+$/.test(F.email.value.trim())) { F.email.focus(); return 'That email address is not complete. You can also leave it empty.'; } if (F.password.value.length < 6) { F.password.focus(); return 'Choose a password or PIN of at least 6 letters or numbers.'; } }
      if (F.position && has(F.position) && !link) { if (!F.position.value) return 'Choose the position you are being considered for.'; if (!F.refCat.value) return 'Please tell us who referred you.'; if (!F.refName.value.trim()) return 'Please type the name of the person who referred you.'; }
      return '';
    };
    const save = () => { if (complete) return; draft.set({ network: chosen, step: stepNo, first_name: F.first_name.value, last_name: F.last_name.value, dob: F.dob.value, phone: F.phone.value, country_id: F.country.value, state: stateName(), lga: lgaMode === 'select' ? F.lgaSel.value : F.lga.value, community: F.community.value, ward: F.ward ? F.ward.value : '', email: F.email ? F.email.value : '' }); };
    function show(i) {
      stepNo = Math.max(0, Math.min(steps.length - 1, i)); steps.forEach((fs, k) => { fs.hidden = k !== stepNo; });
      bar.replaceChildren(...steps.map((_, k) => h('span', { class: k < stepNo ? 'is-done' : k === stepNo ? 'is-open' : '' }))); bar.setAttribute('aria-label', `Step ${stepNo + 1} of ${steps.length}`);
      stepLabel.textContent = `${stepNo + 1} / ${steps.length}`; back.hidden = stepNo === 0; next.hidden = stepNo === steps.length - 1; submit.hidden = stepNo !== steps.length - 1; err.textContent = ''; save();
    }
    back.onclick = () => show(stepNo - 1);
    next.onclick = () => { const problem = checkStep(steps[stepNo]); if (problem) { err.textContent = problem; return; } show(stepNo + 1); form.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
    form.addEventListener('input', save); form.addEventListener('change', save);
    form.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && stepNo < steps.length - 1) { e.preventDefault(); next.click(); } });
    show(Math.min(stepNo, steps.length - 1));
    if (chosen === 'WGMN' && !complete) inner.replaceChildren(gmap || (gmap = africaMap()), form); else inner.replaceChildren(form);
    void other;
  }

  const plain = !complete && !link && !leaderForm;
  /* optional: WODDI's own short video or recorded audio, never loaded until she presses play, with the words written out */
  const media = h('div', { class: 'wmedia', hidden: true });
  if (plain) api('/public/welcome').then((m) => { if (!m.video_url && !m.audio_url) return; media.hidden = false; media.append(m.video_url ? h('video', { src: m.video_url, controls: true, preload: 'none', playsinline: true }) : null, m.audio_url ? h('audio', { src: m.audio_url, controls: true, preload: 'none' }) : null, m.transcript ? h('details', {}, h('summary', { text: 'Read the words instead' }), h('p', { text: m.transcript })) : null); }).catch(() => {});
  root.append(h('div', { class: 'public' },
    pubBar(complete ? null : h('a', { class: 'btn btn--quiet', href: '#/login', text: 'Sign in' })),
    h('main', { class: 'join' },
      plain ? h('section', { class: 'welcome' }, h('h1', { class: 'welcome__title' }, h('span', { text: 'Welcome to' }), h('span', { text: 'WODDI Digital Operating System (WDOS)' })),
        h('p', { class: 'welcome__sub' }, h('span', { text: 'Join a growing network of women across Africa and the diaspora committed to' }), h('b', { text: 'personal growth, leadership, service and lasting generational impact.' }))) : null,
      h(plain ? 'h2' : 'h1', { class: 'display' + (plain ? ' join__pick' : ''), text: complete ? 'One more step to join your network' : link ? `You have been invited by ${link.owner}` : leaderForm ? 'Join WODDI leadership consideration' : 'Choose the network you are joining' }),
      h('p', { class: 'lede' + (plain ? ' lede--c' : ''), text: link ? (link.position ? `To apply for the position of ${link.role} in ${link.place || 'your area'} (${link.network}). A referral records who invited you. Selection and appointment remain with WODDI Headquarters after registration, activation and assessment.` : `To join ${link.network} in ${link.place || 'your area'}.`)
        : leaderForm ? 'You have been referred by a WODDI leader for volunteer leadership consideration. Tell us who you are, the position you are being considered for and where you will serve, then create your WDOS account to begin your three day activation.' : complete ? 'Your Google account is connected. Pick your network and tell us where you are, so your leaders can find you.' : 'WODDI has two membership networks designed for different age groups. Select the network that matches your age category to access its membership registration form. Your profile and membership records will automatically be created within your chosen network upon registration.' }),
      plain ? h('div', { style: 'display:flex;justify-content:center;margin:-6px 0 14px' }, listenBtn(['Welcome to WODDI Digital Operating System', 'Join a growing network of women across Africa and the diaspora committed to personal growth, leadership, service and lasting generational impact.', 'WODDI has two membership networks designed for different age groups. Select the network that matches your age category to access its membership registration form.', 'WODDI Good Mother Network is for women aged 30 and above.', 'WODDI Nurture NextGen Network is for women aged 18 to 29.'], 'Listen to the welcome')) : null,
      plain ? media : null,
      nets, plain ? h('p', { class: 'muted', style: 'text-align:center;font-size:14px;margin:14px auto 0;max-width:62ch', text: 'He-for-She Champions do not register here. They serve through an approved appointment as a deputy or an administrative secretary, made by WODDI HQ.' }) : null, wrap),
    pubFoot()));
  if (chosen) choose(chosen, false);
  if (link) { const otherCard = cards[link.network === 'WGMN' ? 'WNNN' : 'WGMN']; otherCard.hidden = true; otherCard.querySelector('input').disabled = true; }
}
export { go };
