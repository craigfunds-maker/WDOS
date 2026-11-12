/* The front door. A newcomer picks her network by its logo, and the form that
 * opens carries that network all the way into her record. The same form
 * finishes a Google sign-up (complete = true). */
import { h, t, clear, icon, NETS, googleMark, theme, toast, langPills, langPicker, listenBtn, guideBtn, stopAllListening, GUIDE, GUIDE_AUDIO, GUIDE_VIDEO, i18n, choices, said } from '../ui.js';
import { api } from '../api.js';
import { app, signedIn, refreshUser, go, signOut, setLanguage } from '../app.js';

export function pubBar(right, withLang = true) {
  const tb = h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Switch between dark and light', onclick: () => { theme.toggle(); tb.replaceChildren(icon(theme.get() === 'light' ? 'moon' : 'sun')); } }, icon(theme.get() === 'light' ? 'moon' : 'sun'));
  return h('div', { class: 'pubtop' }, withLang ? langPills(setLanguage) : null, h('header', { class: 'pubbar' }, h('a', { href: '#/', 'aria-label': 'WODDI home' }, h('img', { src: '/assets/logos/woddi-disc.png', alt: 'WODDI, The Nurturer' })), h('div', { class: 'grow' }), tb, right || null));
}
export const pubFoot = () => h('footer', { class: 'pubfoot' }, h('span', { text: 'WODDI Digital Operating System' }), h('span', { text: 'No Woman Is Left Out' }), h('span', { class: 'num', text: 'Build ' + (app.config.build || '') }));

const NET_ABOUT = {
  WGMN: ['For Women Aged 30 and Above', 'A network for women to grow in purpose, strengthen family and societal influence, and build a lasting legacy.'],
  WNNN: ['For Women Aged 18 to 29', 'A network for young women to build a strong identity, discover purpose, develop leadership skills and become ready for greater influence.'],
};
const AFRICA = 'AO BF BI BJ BW CD CF CG CI CM CV DJ DZ EG EH ER ET GA GH GM GN GQ GW KE KM LR LS LY MA MG ML MR MU MW MZ NA NE NG RW SC SD SL SN SO SS ST SZ TD TG TN TZ UG ZA ZM ZW'.split(' ');
/* A registration that was interrupted can be picked up again on the same phone. The phone keeps only a random token:
 * the details wait on the WDOS server for two days and are removed when the membership is made. The PIN is never kept anywhere. */
const DRAFT = 'wdos.join';
const draft = {
  ref() { try { const r = JSON.parse(localStorage.getItem(DRAFT) || 'null') || {}; return { t: typeof r.t === 'string' ? r.t : '', k: typeof r.k === 'string' ? r.k : '', old: r.t ? null : r }; } catch { return { t: '', k: '', old: null }; } },
  keep(t, k) { try { localStorage.setItem(DRAFT, JSON.stringify({ t, k })); } catch { /* private mode */ } },
  key() { const r = this.ref(); if (r.k) return r.k; const k = [...crypto.getRandomValues(new Uint8Array(18))].map((b) => b.toString(16).padStart(2, '0')).join(''); this.keep(r.t, k); return k; },
  async load() {
    const r = this.ref();
    if (r.old && r.old.first_name !== undefined) { this.keep('', ''); return r.old; }      // a draft from an earlier version, kept on the phone: use it once and take it off the phone
    if (!r.t) return {};
    try { const d = await api('/public/draft/read', { token: r.t }); return d.data || {}; } catch { return {}; }
  },
  timer: 0, last: '',
  set(d) { const j = JSON.stringify(d); if (j === this.last) return; this.last = j; clearTimeout(this.timer);
    this.timer = setTimeout(async () => { try { const r = this.ref(); const x = await api('/public/draft', { token: r.t, data: d }); if (x.token) this.keep(x.token, this.ref().k); } catch { /* offline: the page still holds what was typed */ } }, 900); },
  clear() { clearTimeout(this.timer); const r = this.ref(); try { localStorage.removeItem(DRAFT); } catch { /* ignore */ } if (r.t) api('/public/draft/forget', { token: r.t }).catch(() => {}); },
};
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
  const saved = complete ? {} : await draft.load();
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
      cards[k].querySelector('.net__pick').replaceChildren(...(on ? [icon('check', 16), t('Selected')] : [t('Choose ' + k)]));
    }
    if (plain && stage !== 'form') { netErr.textContent = ''; return; }           // everything follows in order: the form opens only after Continue
    buildForm(); wrap.classList.add('is-open');
    if (scroll) setTimeout(() => (inner.firstElementChild || inner).scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
  }
  /* One screen at a time: 01 language, 02 network, then the form's own steps. */
  let stage = 'lang'; const netErr = h('p', { class: 'err', role: 'alert', style: 'text-align:center' }); let secLang = null, secNet = null;
  function setStage(to) {
    stage = to; stopAllListening(); if (secLang) secLang.hidden = to !== 'lang'; if (secNet) secNet.hidden = to !== 'network'; wrap.hidden = to !== 'form'; document.querySelector('main.join')?.classList.toggle('join--map', to === 'form' && wrap.classList.contains('formwrap--map'));
    window.scrollTo({ top: 0, behavior: 'auto' }); const hd = (to === 'lang' ? secLang : to === 'network' ? secNet : wrap)?.querySelector('h1, h2, legend'); if (hd) { hd.setAttribute('tabindex', '-1'); hd.focus({ preventScroll: true }); }
  }
  for (const k of Object.keys(cards)) cards[k].querySelector('.net__pick').replaceChildren(t('Choose ' + k));

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
  /* Community Clusters already known in the chosen LGA or city are offered as she types, so the same cluster is not spelled three ways */
  const clusterList = h('datalist', { id: 'f-clusters' });
  const fillClusters = async () => { const c = countries.find((x) => String(x.id) === F.country.value); if (!c) return; const lga = lgaMode === 'select' ? F.lgaSel.value : F.lga.value; try { const r = await api(`/geo/clusters?country=${c.code}&lga=${encodeURIComponent(lga === NOT_LISTED ? '' : lga)}`); clusterList.replaceChildren(...r.clusters.map((n) => opt(n, n))); } catch { /* she can still type it */ } };
  async function fillLgas() {
    const c = countries.find((x) => String(x.id) === F.country.value); lgaMode = 'text';
    if (c && c.code === 'NG' && stateMode !== 'text' && stateName()) {
      geoLgas = geoLgas || await getJson('/assets/geo/ng-lgas.json'); const list = geoLgas[stateName()];
      if (list && list.length) { F.lgaSel.replaceChildren(h('option', { value: '', text: 'Choose your LGA' }), ...list.map((n) => opt(n, n)), h('option', { value: NOT_LISTED, text: 'My LGA is not listed' })); lgaMode = 'select'; }
    }
    paintLga();
  }
  F.state.addEventListener('change', () => { if (F.state.value === NOT_LISTED) { stateMode = 'text'; F.stateText.value = ''; paintState(); F.stateText.focus(); } fillLgas(); });
  F.lgaSel.addEventListener('change', () => { if (F.lgaSel.value === NOT_LISTED) { lgaMode = 'text'; F.lga.value = ''; paintLga(); F.lga.focus(); } fillClusters(); }); F.lga.addEventListener('change', fillClusters);
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
    paintState(); await fillLgas(); fillClusters();
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

  function buildForm() {
    const n = NETS[chosen]; const other = chosen === 'WGMN' ? 'WNNN' : 'WGMN';
    const err = h('p', { class: 'err', role: 'alert' });
    said(err);                                                                      // every message shown here is said in her language
    const tf = (s, vars) => t(s).replace(/\{(\w+)\}/g, (m, k) => vars[k] ?? m); let volRoleBox = null; F.vol = null; F.volRole = null;
    const ageNote = h('small', {});
    const submit = h('button', { class: 'btn btn--block', type: 'submit', text: complete ? `Finish joining ${chosen}` : candidate ? 'Create my account and begin my Referral Activation' : 'Create my account' });
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
      h('fieldset', { 'data-eyebrow': 'Simple details' }, h('legend', { text: 'Your details' }), h('p', { class: 'muted', style: 'margin:0 0 12px', text: 'Only the essential details now. No OTP and no long registration form.' }), h('div', { class: 'grid2' },
        field('first_name', 'First name', { required: true, maxlength: 80, autocomplete: 'given-name', value: u?.first_name || saved.first_name || '' }),
        field('last_name', 'Surname', { required: true, maxlength: 80, autocomplete: 'family-name', value: u?.last_name || saved.last_name || '' }),
        h('label', { class: 'field', for: 'f-dob' }, h('span', { text: 'Date of birth' }), F.dob || (F.dob = h('input', { class: 'input', id: 'f-dob', type: 'date', required: true, autocomplete: 'bday', value: saved.dob || '' })), ageNote),
        complete ? field('contact', 'Phone or WhatsApp number', { type: 'tel', required: true, maxlength: 24, autocomplete: 'tel', placeholder: '+234 801 234 5678' }, 'Include the country code.')
          : h('div', { class: 'grid2', style: 'grid-column:1/-1' }, field('email', 'Email address', { type: 'email', inputmode: 'email', autocapitalize: 'none', required: true, maxlength: 190, autocomplete: 'email', placeholder: 'name@example.com', value: saved.email || (String(saved.contact || '').includes('@') ? saved.contact : '') }, 'You sign in with this email. No code is sent to you.'),
              field('contact', 'Phone number', { type: 'tel', inputmode: 'tel', required: true, maxlength: 24, autocomplete: 'tel', placeholder: '+234 801 234 5678', value: String(saved.contact || '').includes('@') ? '' : saved.contact || '' }, 'With the country code. WODDI reaches you on it.')))),
      h('fieldset', { 'data-eyebrow': 'Find your community' }, h('legend', { text: 'Your location' }), h('p', { class: 'muted', style: 'margin:0 0 12px', text: `Your location helps WDOS place you in a suitable ${chosen} chapter. No woman is turned away because her chapter is full.` }), h('div', { class: 'grid2' },
        h('label', { class: 'field', for: 'f-country' }, h('span', { text: 'Country' }), F.country), stateSlot,
        lgaSlot, field('community', 'Community or ward', { maxlength: 120, value: saved.community || '', list: 'f-clusters' }), clusterList, field('ward', 'Official ward (optional)', { maxlength: 120, value: saved.ward || '' }, 'Only if it is different from your community.')), h('p', { class: 'notice', style: 'margin-top:14px', text: 'Official ward and WODDI community cluster are separate records. A neighbourhood name is not assumed to be an official government ward.' })),
      refBlock,
      h('fieldset', { 'data-eyebrow': 'Your agreement' }, h('legend', { text: 'Your agreement' }), h('p', { class: 'muted', style: 'margin:0 0 6px', text: 'A simple summary of membership and privacy information. Press Listen to hear it.' }),
        h('div', { class: 'panel', style: 'margin-bottom:14px' }, h('b', { text: 'Membership summary' }), h('p', { style: 'margin:6px 0 0', text: 'WODDI uses information to manage membership, learning, chapters and approved communications. Participation is voluntary. Unauthorised dues, local accounts and fundraising are not permitted.' }), h('p', { class: 'muted', style: 'margin:6px 0 0;font-size:14px', text: 'WODDI keeps your name, phone number and location so your chapter and your leaders can reach you. WODDI does not sell or share them outside WODDI.' })),
        h('label', { class: 'check' }, consent, h('span', { text: 'I agree to the membership guidance and privacy notice, and to the WODDI Code of Conduct.' })),
        complete || link || leaderForm ? null : h('div', { class: 'panel', style: 'margin-top:16px;display:grid;gap:12px' }, F.vol = choices('f-vol', 'Would you like to volunteer with WODDI?', [['', 'Not now. I am joining as a member'], ['volunteer', 'Yes, I want to volunteer in my chapter'], ['lead', 'Yes, and I am interested in a leadership role']], (v) => { volRoleBox.hidden = v !== 'lead'; }),
          volRoleBox = h('label', { class: 'field', for: 'f-volrole', hidden: true }, h('span', { text: 'Which role are you interested in?' }), F.volRole = h('select', { class: 'input', id: 'f-volrole', style: 'font-size:15px' }, h('option', { value: '', text: 'Choose a role' })), h('small', { class: 'muted', text: 'This records your interest. WODDI Headquarters appoints leaders after you are an active member.' }))),
        complete || link ? null : h('label', { class: 'field', for: 'f-source', style: 'margin-top:16px' }, h('span', { text: 'How did you hear about WODDI? (optional)' }), F.source = h('select', { class: 'input', id: 'f-source' }, h('option', { value: '', text: 'Choose one' }))),
        complete ? null : h('div', { style: 'margin-top:16px' }, field('password', 'Choose your password or PIN', { type: 'password', required: true, minlength: 6, autocomplete: 'new-password' }, 'At least 6 letters or numbers. Choose one you will remember. No code is sent to you.'))),
      err, submit,
      complete ? h('p', { class: 'muted', style: 'margin-top:12px;font-size:14px' }, 'Signed in as ' + (u?.email || '') + '. ', h('a', { href: '#', onclick: (e) => { e.preventDefault(); signOut(); }, text: 'Use a different account' })) : null);
    const checkAge = () => {
      const a = F.dob.value ? ageOf(F.dob.value) : null; ageNote.replaceChildren(); ageNote.style.color = '';
      if (a === null) { ageNote.textContent = t(chosen === 'WNNN' ? 'WNNN is for ages 18 to 29.' : 'WGMN is for ages 30 and above.'); return true; }
      if (a < 18) { ageNote.style.color = 'var(--bad)'; ageNote.textContent = t('WODDI networks are open to women aged 18 and above.'); return false; }
      const fits = a <= 29 ? 'WNNN' : 'WGMN';
      if (fits !== chosen && link) { ageNote.style.color = 'var(--bad)'; ageNote.textContent = tf('This link is for {c}. From your date of birth you belong to {n}. Ask your referrer for the correct link.', { c: chosen, n: fits }); return false; }
      if (fits !== chosen) { ageNote.style.color = 'var(--bad)'; ageNote.append(tf('At {a}, your network is {n}.', { a, n: fits }) + ' ', h('a', { href: '#', onclick: (e) => { e.preventDefault(); choose(fits, false); } }, tf('Switch to {n}', { n: fits }))); return false; }
      ageNote.textContent = tf('Age {a}. This matches {n}.', { a, n: chosen }); return true;
    };
    F.dob.onchange = checkAge; F.dob.oninput = checkAge; checkAge();
    const calm = (e) => { if (!err.textContent && !form.querySelector('.err--field')) return; err.textContent = ''; form.querySelectorAll('.err--field').forEach((x) => x.remove()); e.target.removeAttribute?.('aria-invalid'); };
    form.addEventListener('input', calm); form.addEventListener('change', calm);      // a message about a field goes away once she starts to correct it
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); err.textContent = '';
      if (!checkAge()) { err.textContent = 'Your date of birth does not match this network. Use the switch link under the date.'; F.dob.focus(); return; }
      const body = { network: chosen, first_name: F.first_name.value, last_name: F.last_name.value, dob: F.dob.value, phone: F.contact.value, country_id: Number(F.country.value) || 0,
        state_id: stateMode === 'select' ? Number(F.state.value) || 0 : 0, state_region: stateMode === 'select' ? '' : stateName(), lga: lgaMode === 'select' ? F.lgaSel.value : F.lga.value, community: F.community.value, ward: diaspora() ? '' : F.ward.value, consent: consent.checked };
      if (stateMode === 'list' && !body.state_region && !diaspora()) { err.textContent = 'Choose your state or region.'; F.state.focus(); return; }
      if (lgaMode === 'select' && !body.lga) { err.textContent = 'Choose your LGA.'; F.lgaSel.focus(); return; }
      if (!complete) { body.email = F.email.value.trim(); body.password = F.password.value; if (F.vol) body.volunteer_interest = F.vol.value === 'lead' ? F.volRole.value : F.vol.value; const last = checkStep(steps[steps.length - 1]); if (last) { err.textContent = last; return; } if (!consent.checked) { err.textContent = 'Please review and accept the membership guidance.'; consent.focus(); return; } }
      body.lang = i18n.lang; if (!complete) { body.idem = draft.key(); body.draft = draft.ref().t; }
      if (F.source && F.source.value) body.source = F.source.value;
      { const c = new URLSearchParams(location.search).get('c'); if (c) body.campaign = c.slice(0, 40); }
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
        if (ex.code === 'offline') {                           // no connection: keep everything on this phone and send it by itself when the connection returns
          err.textContent = 'No connection. Keep this page open. WDOS will send your details by itself when the connection returns, and you will not be registered twice.'; submit.disabled = false; submit.textContent = label;
          window.addEventListener('online', () => { if (form.isConnected) form.requestSubmit(); }, { once: true }); return;
        }
        submit.disabled = false; submit.textContent = label;
        const map = { name: 'first_name', phone: 'contact', phone_taken: 'contact', dob: 'dob', age: 'dob', age_network: 'dob', country: 'country', email: complete ? 'contact' : 'email', email_taken: complete ? 'contact' : 'email', password: 'password', consent: 'consent', state: stateMode === 'text' ? 'stateText' : 'state' };
        const bad = F[map[ex.code]]; if (bad) { const fs = bad.closest('fieldset'); const i = steps.indexOf(fs); if (i >= 0) show(i); }
        // The reason is shown AFTER the screen changes (changing screen clears the old message), both under the field and above the buttons.
        const taken = ex.code === 'email_taken' || ex.code === 'phone_taken';
        const say = taken ? [h('span', { text: ex.message }), ' ', h('a', { class: 'btn btn--s', href: '#/login', style: 'margin:8px 8px 0 0', text: 'Sign in' }), h('small', { class: 'muted', style: 'display:block;margin-top:6px', text: 'If you do not remember your password, choose Forgot password on the sign-in page.' })] : [h('span', { text: ex.message })];
        err.replaceChildren(...say);
        form.querySelectorAll('.err--field').forEach((x) => x.remove());
        if (bad) { const note = h('p', { class: 'err err--field', role: 'alert', style: 'margin:6px 0 0', text: ex.message }); (bad.closest('label, .field') || bad).after(note); bad.setAttribute('aria-invalid', 'true'); bad.addEventListener('input', () => { note.remove(); bad.removeAttribute('aria-invalid'); }, { once: true }); bad.focus({ preventScroll: true }); note.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        else err.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
    });
    if (F.vol) { F.vol.value = saved.vol || ''; api('/public/volunteer-roles').then((r) => { F.volRole.append(...r.roles.map((p) => h('option', { value: p.code, text: chosen === 'WNNN' ? p.title_wnnn : p.title_wgmn }))); if (saved.vol_role) F.volRole.value = saved.vol_role; volRoleBox.hidden = F.vol.value !== 'lead'; }).catch(() => {}); }
    if (F.source) api('/public/sources').then((r) => { F.source.append(...Object.entries(r.sources).map(([k, l]) => h('option', { value: k, text: l }))); if (saved.source) F.source.value = saved.source; }).catch(() => {});
    const steps = [...form.querySelectorAll(':scope > fieldset')]; const bar = h('div', { class: 'jbar jbar--form', role: 'img' }); const stepLabel = h('small', { class: 'faint' });
    const back = h('button', { class: 'btn btn--ghost', type: 'button', text: 'Back' }); const next = h('button', { class: 'btn', type: 'button', text: 'Continue' });
    const listen = plain ? guideBtn(() => stepNo + 2) : listenBtn(() => { const fs = steps[stepNo]; return [fs.querySelector('legend')?.textContent, ...[...fs.querySelectorAll('label > span, p, small')].map((x) => x.textContent)]; });
    const navRow = h('div', { class: 'stepnav' }, back, h('div', { class: 'grow' }), next); submit.remove(); navRow.append(submit);
    form.insertBefore(h('div', { class: 'stephead' }, bar, h('div', { style: 'display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap' }, stepLabel, listen)), steps[0]); form.insertBefore(navRow, err.nextSibling);
    /* what must be right before she can leave a screen: said in plain words, on that screen */
    const checkStep = (fs) => {
      const has = (el) => fs.contains(el);
      if (has(F.first_name)) { if (F.first_name.value.trim().length < 2 || F.last_name.value.trim().length < 2) { F.first_name.focus(); return 'Enter your first name and surname.'; } if (!F.dob.value) { F.dob.focus(); return 'Enter your date of birth.'; } if (!checkAge()) { F.dob.focus(); return 'Your date of birth does not match this network. Use the switch link under the date.'; } if (F.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(F.email.value.trim())) { F.email.focus(); return F.email.value.trim() ? 'That email address is not complete.' : 'Enter your email address. You sign in with it.'; } const digits = F.contact.value.replace(/[^\d]/g, '').length; if (digits < 8 || digits > 15) { F.contact.focus(); return 'Enter your phone number with the country code, for example +234 801 234 5678.'; } }
      if (has(F.country)) { if (!F.country.value) { F.country.focus(); return 'Choose your country.'; } if (!diaspora() && !stateName() && !(stateMode === 'select' && F.state.value)) { (stateMode === 'text' ? F.stateText : F.state).focus(); return 'Choose your state or region.'; } }
      if (F.vol && has(F.vol) && F.vol.value === 'lead' && !F.volRole.value) { F.volRole.focus(); return 'Choose the leadership role you are interested in.'; }
      if (F.password && has(F.password)) { if (F.password.value.length < 6) { F.password.focus(); return 'Choose a password or PIN of at least 6 letters or numbers.'; } }
      if (F.position && has(F.position) && !link) { if (!F.position.value) return 'Choose the position you are being considered for.'; if (!F.refCat.value) return 'Please tell us who referred you.'; if (!F.refName.value.trim()) return 'Please type the name of the person who referred you.'; }
      return '';
    };
    const save = () => { if (complete) return; draft.set({ network: chosen, step: stepNo, first_name: F.first_name.value, last_name: F.last_name.value, dob: F.dob.value, email: F.email ? F.email.value : '', contact: F.contact.value, vol: F.vol ? F.vol.value : '', vol_role: F.volRole ? F.volRole.value : '', country_id: F.country.value, state: stateName(), lga: lgaMode === 'select' ? F.lgaSel.value : F.lga.value, community: F.community.value, ward: F.ward ? F.ward.value : '', source: F.source ? F.source.value : '' }); };
    function show(i) {
      if (F.paintMap) F.paintMap();
      stepNo = Math.max(0, Math.min(steps.length - 1, i)); steps.forEach((fs, k) => { fs.hidden = k !== stepNo; });
      const lead = complete ? 0 : 2, total = steps.length + lead, at = stepNo + lead;            // 01 language and 02 network are already done on this screen
      bar.replaceChildren(...Array.from({ length: total }, (_, k) => h('span', { class: k < at ? 'is-done' : k === at ? 'is-open' : '' }))); bar.setAttribute('aria-label', `Step ${at + 1} of ${total}`);
      stepLabel.className = 'eyebrow'; stepLabel.textContent = `${String(at + 1).padStart(2, '0')} · ${t(steps[stepNo].dataset.eyebrow || steps[stepNo].querySelector('legend').textContent)}`; back.hidden = stepNo === 0 && !plain; next.hidden = stepNo === steps.length - 1; submit.hidden = stepNo !== steps.length - 1; err.textContent = ''; save();
    }
    back.onclick = () => { stopAllListening(); if (stepNo === 0 && plain) setStage('network'); else show(stepNo - 1); };
    next.onclick = () => { const problem = checkStep(steps[stepNo]); if (problem) { err.textContent = problem; return; } stopAllListening(); show(stepNo + 1); if (plain) window.scrollTo({ top: 0, behavior: 'auto' }); else form.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
    form.addEventListener('input', save); form.addEventListener('change', save);
    form.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && stepNo < steps.length - 1) { e.preventDefault(); next.click(); } });
    show(Math.min(stepNo, steps.length - 1));
    /* The WODDI Good Mother Network map of Africa stands above the WGMN form, centred and small on every screen.
       It lights up her country once she has chosen it. */
    if (plain && chosen === 'WGMN') {
      const mapSlot = h('aside', { class: 'joinmap__map', 'aria-label': 'WODDI Good Mother Network map of Africa' }); let shown = null;
      const paintMap = () => { const code = countries.find((x) => String(x.id) === F.country.value)?.code || ''; if (code === shown) return; shown = code; import('../goodmother.js').then((m) => mapSlot.replaceChildren(m.africaMap(code || undefined))).catch(() => {}); };
      paintMap(); F.country.addEventListener('change', paintMap); F.paintMap = paintMap;
      inner.replaceChildren(h('div', { class: 'joinmap' }, mapSlot, form)); wrap.classList.add('formwrap--map'); document.querySelector('main.join')?.classList.add('join--map');
    } else { F.paintMap = null; inner.replaceChildren(form); wrap.classList.remove('formwrap--map'); document.querySelector('main.join')?.classList.remove('join--map'); }
    void other;
  }

  const plain = !complete && !link && !leaderForm;
  /* Video guidance: WODDI's own film when HQ has linked one, otherwise the short captioned guide that ships with WDOS. Nothing loads until she asks. */
  let guide = { video_url: '', transcript: '' };
  if (plain) api('/public/welcome').then((m) => { guide = m; }).catch(() => {});
  function openVideo() {
    const vl = GUIDE_VIDEO.includes(i18n.lang) ? i18n.lang : 'en'; const src = guide.video_url || `/assets/video/wdos-join-guide-${vl}.mp4`; const own = !guide.video_url; stopAllListening();
    const v = h('video', { src, controls: true, autoplay: true, playsinline: true, preload: 'metadata', poster: own ? `/assets/video/wdos-join-guide-${vl}.jpg` : null, style: 'width:100%;max-height:70vh;border-radius:14px;background:#000' });
    const close = () => { v.pause(); dlg.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (e) => { if (e.key === 'Escape') close(); };
    const dlg = h('div', { class: 'vmodal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Video guidance', onclick: (e) => { if (e.target === dlg) close(); } },
      h('div', { class: 'vmodal__box' }, h('div', { style: 'display:flex;align-items:center;gap:10px' }, h('b', { class: 'grow', text: 'How to join the WODDI Digital Operating System' }), h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Close the video', onclick: close }, icon('x'))),
        v, h('details', {}, h('summary', { text: 'Read the words instead' }), own ? h('ol', { style: 'margin:8px 0 0;padding-inline-start:20px;display:grid;gap:6px' }, GUIDE.map((w) => h('li', { text: w }))) : h('p', { text: guide.transcript || '' }))));
    document.body.append(dlg); document.addEventListener('keydown', onKey); dlg.querySelector('.iconbtn').focus();
  }
  const startLang = langPicker(setLanguage); startLang.id = 'lang-start'; startLang.classList.add('lpick', 'lpick--card');
  const stepBar = (at) => h('div', { class: 'jbar jbar--form', role: 'img', 'aria-label': `Step ${at + 1} of 5` }, [0, 1, 2, 3, 4].map((k) => h('span', { class: k < at ? 'is-done' : k === at ? 'is-open' : '' })));
  const headline = h(plain ? 'h2' : 'h1', { class: 'display' + (plain ? ' join__pick' : ''), text: complete ? 'One more step to join your network' : link ? `You have been invited by ${link.owner}` : leaderForm ? 'Join WODDI leadership consideration' : 'Choose the network you are joining' });
  const lede = h('p', { class: 'lede' + (plain ? ' lede--c' : ''), text: link ? (link.position ? `To apply for the position of ${link.role} in ${link.place || 'your area'} (${link.network}). A referral records who invited you. Selection and appointment remain with WODDI Headquarters after registration, activation and assessment.` : `To join ${link.network} in ${link.place || 'your area'}.`)
    : leaderForm ? 'You have been referred by a WODDI leader for volunteer leadership consideration. Tell us who you are, the position you are being considered for and where you will serve, then create your WDOS account to begin your three day activation.' : complete ? 'Your Google account is connected. Pick your network and tell us where you are, so your leaders can find you.' : 'WODDI has two membership networks designed for different age groups. Select the network that matches your age category to access its membership registration form. Your profile and membership records will automatically be created within your chosen network upon registration.' });
  if (plain) {
    secLang = h('section', { class: 'startcard' }, h('span', { class: 'startcard__pill', text: 'No Woman Is Left Out' }), stepBar(0),
      h('h1', { class: 'startcard__title', text: 'Welcome to WODDI Digital Operating System (WDOS)' }),
      h('p', { class: 'startcard__lead' }, h('span', { text: 'Join a growing network of women across Africa and the diaspora committed to' }), ' ', h('b', { text: 'personal growth, leadership, service and lasting generational impact.' })),
      h('p', { class: 'startcard__sub', text: 'Choose a language before you register. You can change it at any time.' }),
      h('label', { class: 'field', for: 'lang-start' }, h('span', { text: 'Choose your language' }), startLang),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, guideBtn(0), h('button', { class: 'btn btn--quiet', type: 'button', id: 'video-guide', onclick: openVideo }, icon('play', 18), 'Video guidance')),
      h('div', { class: 'stagenav' }, h('div', { class: 'grow' }), h('button', { class: 'btn', type: 'button', id: 'start-continue', onclick: () => setStage('network') }, 'Continue', icon('chevright', 18))));
    secNet = h('section', { class: 'stage', hidden: true }, h('div', { class: 'stage__head' }, stepBar(1), h('div', { style: 'display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap' }, h('span', { class: 'eyebrow', text: '02 · Choose network' }), guideBtn(1))),
      headline, lede, nets, netErr,
      h('div', { class: 'stagenav' }, h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => setStage('lang') }, 'Back'), h('div', { class: 'grow' }),
        h('button', { class: 'btn', type: 'button', id: 'net-continue', onclick: () => { if (!chosen) { netErr.textContent = t('Touch your network first, then press Continue.'); return; } stage = 'form'; buildForm(); wrap.classList.add('is-open'); setStage('form'); } }, 'Continue', icon('chevright', 18))));
    wrap.hidden = true;
  }
  root.append(h('div', { class: 'public' },
    pubBar(complete ? null : h('a', { class: 'btn btn--quiet', href: '#/login', text: 'Sign in' })),
    h('main', { class: 'join' }, ...(plain ? [secLang, secNet, wrap] : [headline, lede, nets, wrap])),
    pubFoot()));
  if (plain && chosen && saved.first_name) { choose(chosen, false); stage = 'form'; buildForm(); wrap.classList.add('is-open'); setStage('form'); }     // an interrupted registration opens where she stopped
  else if (plain) { if (chosen) choose(chosen, false); }
  else if (chosen) choose(chosen, false);
  if (link) { const otherCard = cards[link.network === 'WGMN' ? 'WNNN' : 'WGMN']; otherCard.hidden = true; otherCard.querySelector('input').disabled = true; }
}
export { go };
