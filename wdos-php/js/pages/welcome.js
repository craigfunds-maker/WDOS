/* The front door. A newcomer picks her network by its logo, and the form that
 * opens carries that network all the way into her record. The same form
 * finishes a Google sign-up (complete = true). */
import { h, clear, icon, NETS, googleMark, theme, toast } from '../ui.js';
import { api } from '../api.js';
import { app, signedIn, refreshUser, go, signOut } from '../app.js';

export function pubBar(right) {
  const tb = h('button', { class: 'iconbtn', type: 'button', 'aria-label': 'Switch between dark and light', onclick: () => { theme.toggle(); tb.replaceChildren(icon(theme.get() === 'light' ? 'moon' : 'sun')); } }, icon(theme.get() === 'light' ? 'moon' : 'sun'));
  return h('header', { class: 'pubbar' }, h('a', { href: '#/', 'aria-label': 'WODDI home' }, h('img', { src: '/assets/logos/woddi.png', alt: 'WODDI, The Nurturer' })), h('div', { class: 'grow' }), tb, right || null);
}
export const pubFoot = () => h('footer', { class: 'pubfoot' }, h('span', { text: 'WODDI Digital Operating System' }), h('span', { text: 'No Woman Is Left Out' }), h('span', { class: 'num', text: 'Build ' + (app.config.build || '') }));

const ageOf = (dob) => { const d = new Date(dob + 'T00:00:00Z'); if (isNaN(d)) return null; const n = new Date(); let a = n.getUTCFullYear() - d.getUTCFullYear(); if (n.getUTCMonth() < d.getUTCMonth() || (n.getUTCMonth() === d.getUTCMonth() && n.getUTCDate() < d.getUTCDate())) a--; return a; };

export async function render(root, params) {
  const complete = !!params.complete; const u = app.user;
  let chosen = ['WGMN', 'WNNN'].includes(String(params.network).toUpperCase()) ? String(params.network).toUpperCase() : '';
  const nets = h('div', { class: 'nets', role: 'radiogroup', 'aria-label': 'Choose your network' });
  const wrap = h('div', { class: 'formwrap' }); const inner = h('div'); wrap.append(inner);

  const cardFor = (code) => {
    const n = NETS[code];
    const input = h('input', { type: 'radio', name: 'network', value: code, 'aria-label': `${n.name}. ${n.age}.` });
    const c = h('label', { class: 'net net--' + code.toLowerCase() }, input, h('img', { class: 'net__disc', src: n.disc, alt: '', width: 240, height: 240 }),
      h('div', {}, h('div', { class: 'net__name', text: n.name }), h('div', { class: 'net__age', text: n.age })), h('div', { class: 'net__line', text: n.line }),
      h('div', { class: 'net__pick' }));
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
    if (scroll) setTimeout(() => inner.querySelector('.jform')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
  }
  for (const k of Object.keys(cards)) cards[k].querySelector('.net__pick').replaceChildren('Choose ' + k);

  /* fields live outside buildForm so switching network keeps what she typed */
  const F = {};
  const field = (key, label, attrs = {}, hint) => { F[key] = F[key] || h('input', { class: 'input', id: 'f-' + key, ...attrs }); return h('label', { class: 'field', for: 'f-' + key }, h('span', { text: label }), F[key], hint ? h('small', { text: hint }) : null); };
  F.country = h('select', { class: 'input', id: 'f-country', required: true }, h('option', { value: '', text: 'Choose your country' }));
  F.state = h('select', { class: 'input', id: 'f-state' }); F.stateText = h('input', { class: 'input', id: 'f-state', maxlength: 120, autocomplete: 'address-level1' });
  let countries = [], stateMode = 'text';
  const stateSlot = h('div');
  const paintState = () => stateSlot.replaceChildren(h('label', { class: 'field', for: 'f-state' }, h('span', { text: 'State or region' }), stateMode === 'select' ? F.state : F.stateText));
  F.country.addEventListener('change', async () => {
    const c = countries.find((x) => String(x.id) === F.country.value);
    stateMode = 'text';
    if (c && Number(c.children) > 0) {
      try { const r = await api('/geo/children/' + c.id); clear(F.state).append(h('option', { value: '', text: 'Choose your state' }), ...r.units.map((s) => h('option', { value: s.id, text: s.name }))); stateMode = 'select'; } catch { /* fall back to typing */ }
    }
    paintState();
  });
  api('/geo/countries').then((r) => { countries = r.countries; F.country.append(...countries.map((c) => h('option', { value: c.id, text: c.name }))); }).catch(() => toast('Countries did not load', 'Check your connection and reload the page.', 'bad'));
  paintState();

  function buildForm() {
    const n = NETS[chosen]; const other = chosen === 'WGMN' ? 'WNNN' : 'WGMN';
    const err = h('p', { class: 'err', role: 'alert' });
    const ageNote = h('small', {});
    const submit = h('button', { class: 'btn btn--block', type: 'submit', text: complete ? `Finish joining ${chosen}` : `Join ${chosen}` });
    const consent = F.consent || (F.consent = h('input', { type: 'checkbox', id: 'f-consent' }));
    const form = h('form', { class: 'jform reveal', novalidate: true },
      h('div', { class: 'jform__head' }, h('img', { src: n.disc, alt: '' }), h('div', {}, h('h2', { text: complete ? 'Finish your profile' : `Join ${chosen}` }), h('p', { class: 'muted', text: `${n.name}. ${n.age}.` }))),
      !complete && app.config.google ? h('div', { style: 'margin-top:18px' }, h('a', { class: 'gbtn', href: '/api/auth/google/start?network=' + chosen }, googleMark(), `Continue with Google as ${chosen}`), h('div', { class: 'or', text: 'or fill in the form' })) : null,
      h('fieldset', {}, h('legend', { text: 'About you' }), h('div', { class: 'grid2' },
        field('first_name', 'First name', { required: true, maxlength: 80, autocomplete: 'given-name', value: u?.first_name || '' }),
        field('last_name', 'Surname', { required: true, maxlength: 80, autocomplete: 'family-name', value: u?.last_name || '' }),
        h('label', { class: 'field', for: 'f-dob' }, h('span', { text: 'Date of birth' }), F.dob || (F.dob = h('input', { class: 'input', id: 'f-dob', type: 'date', required: true, autocomplete: 'bday' })), ageNote),
        field('phone', 'Phone or WhatsApp number', { type: 'tel', required: true, maxlength: 24, autocomplete: 'tel', placeholder: '+234 801 234 5678' }, 'Include the country code.'))),
      h('fieldset', {}, h('legend', { text: 'Where you are' }), h('div', { class: 'grid2' },
        h('label', { class: 'field', for: 'f-country' }, h('span', { text: 'Country' }), F.country), stateSlot,
        field('lga', 'LGA or district', { maxlength: 120 }), field('community', 'Community or ward', { maxlength: 120 }))),
      complete ? null : h('fieldset', {}, h('legend', { text: 'Your sign-in' }), h('div', { class: 'grid2' },
        field('email', 'Email address', { type: 'email', required: true, maxlength: 190, autocomplete: 'email' }),
        field('password', 'Password', { type: 'password', required: true, minlength: 8, autocomplete: 'new-password' }, 'At least 8 characters.'))),
      h('label', { class: 'check', style: 'margin-top:22px' }, consent, h('span', { text: 'I agree to the WODDI Code of Conduct and to WODDI HQ contacting me about my network.' })),
      err, submit,
      complete ? h('p', { class: 'muted', style: 'margin-top:12px;font-size:14px' }, 'Signed in as ' + (u?.email || '') + '. ', h('a', { href: '#', onclick: (e) => { e.preventDefault(); signOut(); }, text: 'Use a different account' })) : null);
    const checkAge = () => {
      const a = F.dob.value ? ageOf(F.dob.value) : null; ageNote.replaceChildren(); ageNote.style.color = '';
      if (a === null) { ageNote.textContent = chosen === 'WNNN' ? 'WNNN is for ages 18 to 29.' : 'WGMN is for ages 30 and above.'; return true; }
      if (a < 18) { ageNote.style.color = 'var(--bad)'; ageNote.textContent = 'WODDI networks are open to women aged 18 and above.'; return false; }
      const fits = a <= 29 ? 'WNNN' : 'WGMN';
      if (fits !== chosen) { ageNote.style.color = 'var(--bad)'; ageNote.append(`At ${a}, your network is ${fits}. `, h('a', { href: '#', onclick: (e) => { e.preventDefault(); choose(fits, false); }, text: `Switch to ${fits}` })); return false; }
      ageNote.textContent = `Age ${a}. This matches ${chosen}.`; return true;
    };
    F.dob.onchange = checkAge; F.dob.oninput = checkAge; checkAge();
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); err.textContent = '';
      if (!checkAge()) { err.textContent = 'Your date of birth does not match this network. Use the switch link under the date.'; F.dob.focus(); return; }
      const body = { network: chosen, first_name: F.first_name.value, last_name: F.last_name.value, dob: F.dob.value, phone: F.phone.value, country_id: Number(F.country.value) || 0,
        state_id: stateMode === 'select' ? Number(F.state.value) || 0 : 0, state_region: stateMode === 'text' ? F.stateText.value : '', lga: F.lga.value, community: F.community.value, consent: consent.checked };
      if (!complete) { body.email = F.email.value; body.password = F.password.value; }
      submit.disabled = true; const label = submit.textContent; submit.textContent = 'Creating your membership…';
      try {
        const r = await api(complete ? '/auth/complete-profile' : '/auth/register', body);
        toast(`Welcome to ${chosen}`, `Your membership number is ${r.user.member_no}.`, 'ok');
        if (complete) { await refreshUser(); }
        signedIn(r.user);
      } catch (ex) {
        err.textContent = ex.message; submit.disabled = false; submit.textContent = label;
        const map = { name: 'first_name', phone: 'phone', dob: 'dob', age: 'dob', age_network: 'dob', country: 'country', email: 'email', email_taken: 'email', password: 'password', consent: 'consent', state: stateMode === 'select' ? 'state' : 'stateText' };
        F[map[ex.code]]?.focus();
      }
    });
    inner.replaceChildren(form);
    void other;
  }

  root.append(h('div', { class: 'public' },
    pubBar(complete ? null : h('a', { class: 'btn btn--quiet', href: '#/login', text: 'Sign in' })),
    h('main', { class: 'join' },
      h('h1', { class: 'display', text: complete ? 'One more step to join your network' : 'Choose the network you are joining' }),
      h('p', { class: 'lede', text: complete ? 'Your Google account is connected. Pick your network and tell us where you are, so your leaders can find you.' : 'WODDI has two networks, by age. Select a logo to open its membership form. Your record is created inside that network from the first moment.' }),
      nets, wrap),
    pubFoot()));
  if (chosen) choose(chosen, false);
}
export { go };
