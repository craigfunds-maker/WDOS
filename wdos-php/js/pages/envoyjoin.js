/* Become a WODDI Envoy: the public sign-up page. With email and password, or with Google (Google brings her back here to add the rest). */
import { h, t, tf, icon, googleMark, toast, said, field } from '../ui.js';
import { api } from '../api.js';
import { app, signedIn, go } from '../app.js';
import { pubBar, pubFoot } from './welcome.js';

export async function render(root, params) {
  const complete = !!params.complete;               // signed in with Google, details still to give
  const card = h('div', { class: 'card' }, h('p', { class: 'muted', text: t('Loading…') }));
  root.append(h('div', { class: 'public' }, pubBar(complete ? h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => import('../app.js').then((m) => m.signOut()) }, t('Sign out')) : h('a', { class: 'btn btn--quiet', href: '#/login', text: t('Sign in') })),
    h('main', { class: 'signin envoyjoin' }, card), pubFoot()));
  let info = { mode: 'open', google: !!app.config?.google };
  try { info = await api('/envoy/join-info'); } catch { /* defaults */ }
  if (info.mode === 'closed') { card.replaceChildren(h('h1', { class: 'display', text: t('Become a WODDI Envoy') }), h('p', { text: t('WODDI is not taking new envoys through this page at the moment. Write to WODDI HQ.') })); return; }

  const u = app.user || {};
  const inp = (id, attrs = {}) => h('input', { class: 'input', id, ...attrs });
  const F = {
    first: inp('ej-first', { autocomplete: 'given-name', value: u.first_name || '' }), last: inp('ej-last', { autocomplete: 'family-name', value: u.last_name || '' }),
    email: inp('ej-email', { type: 'email', autocomplete: 'email', inputmode: 'email', autocapitalize: 'none' }), phone: inp('ej-phone', { type: 'tel', autocomplete: 'tel', inputmode: 'tel', placeholder: '+234…' }),
    country: h('select', { class: 'input', id: 'ej-country' }, h('option', { value: '', text: t('Choose your country') })),
    state: inp('ej-state', { autocomplete: 'address-level1' }), city: inp('ej-city', { autocomplete: 'address-level2' }), org: inp('ej-org'),
    plan: h('textarea', { class: 'input', id: 'ej-plan', rows: 3, placeholder: t('For example: my church women\'s fellowship, my market association and my WhatsApp groups') }),
    heard: inp('ej-heard'), pw: inp('ej-pw', { type: 'password', autocomplete: 'new-password', minlength: 8 }),
    consent: h('input', { type: 'checkbox', id: 'ej-consent' }),
  };
  api('/geo/countries').then((r) => { F.country.append(...r.countries.map((c) => h('option', { value: c.code, text: c.name }))); if (u.country_code) F.country.value = u.country_code; }).catch(() => toast('Countries did not load', 'Check your connection and reload the page.', 'bad'));
  const err = said(h('p', { class: 'err', role: 'alert' }));
  const btn = h('button', { class: 'btn btn--block', type: 'submit' }, complete ? t('Finish and open my dashboard') : t('Create my envoy account'));
  const g = info.google ? h('a', { class: 'gbtn', href: '/api/auth/google/start?as=envoy' }, googleMark(), t('Sign up with Google'))
    : h('span', { class: 'gbtn', 'aria-disabled': 'true', title: t('HQ IT has not added the Google keys yet') }, googleMark(), t('Sign up with Google'));
  const form = h('form', { style: 'display:grid;gap:14px', novalidate: true },
    h('div', { class: 'row row--2' }, field(t('First name'), F.first), field(t('Surname'), F.last)),
    complete ? h('p', { class: 'muted', style: 'margin:0', text: tf('You signed up with Google as {1}.', u.email || '') }) : field(t('Email address'), F.email, t('You sign in with it, and WDOS writes to you here.')),
    field(t('Phone number'), F.phone, t('With the country code, for example +2348012345678.')),
    field(t('Country'), F.country),
    h('div', { class: 'row row--2' }, field(t('State or region'), F.state), field(t('City or town (optional)'), F.city)),
    field(t('Where will you share your link?'), F.plan, t('Groups, places or communities where you will invite women to join WODDI.')),
    field(t('Organisation or group you belong to (optional)'), F.org),
    field(t('How did you hear about WODDI? (optional)'), F.heard),
    complete ? null : field(t('Choose a password'), F.pw, t('At least 8 characters.')),
    h('label', { class: 'check' }, F.consent, h('span', { text: t('I agree to the WODDI Envoy terms: I will invite women honestly, never ask anyone for money, and keep the details of the people I follow up private. WODDI keeps my details to run the envoy programme.') })),
    err, btn);
  form.addEventListener('submit', async (e) => {
    e.preventDefault(); err.textContent = ''; btn.disabled = true;
    try {
      const r = await api('/envoy/join', { first_name: F.first.value, last_name: F.last.value, email: F.email.value, phone: F.phone.value, country_code: F.country.value, state_region: F.state.value, city: F.city.value, organisation: F.org.value, reach_plan: F.plan.value, heard_from: F.heard.value, password: F.pw.value, consent: F.consent.checked });
      toast(t('Welcome, WODDI Envoy'), r.live ? t('Your referral link is ready on your dashboard.') : t('HQ is checking your sign-up. Your link switches on when HQ approves you.'), 'ok');
      signedIn(r.user); go('#/');
    } catch (ex) { err.textContent = ex.message; btn.disabled = false; const k = { name: F.first, email: F.email, phone: F.phone, country: F.country, state_region: F.state, reach_plan: F.plan, password: F.pw, consent: F.consent }[ex.code]; if (k) k.focus(); }
  });
  card.replaceChildren(
    h('div', { class: 'envoyjoin__head' }, h('span', { class: 'envoyjoin__ico' }, icon('users', 26)), h('div', {}, h('h1', { class: 'display', text: complete ? t('Finish your envoy sign-up') : t('Become a WODDI Envoy') }),
      h('p', { class: 'muted', style: 'margin:4px 0 0', text: t('An envoy shares her own WODDI link, welcomes the women who join through it and helps each one finish her 3 days. You get a simple dashboard to follow them up.') }))),
    complete ? null : g, complete ? null : h('div', { class: 'or', text: t('or') }), form,
    complete ? null : h('p', { class: 'muted', style: 'text-align:center;font-size:14.5px;margin:4px 0 0' }, t('Already an envoy?') + ' ', h('a', { href: '#/login', text: t('Sign in') })));
}
