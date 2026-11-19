/* Sign in: password, Google, or fingerprint. Also the forgotten-password and reset-link screens. */
import { h, t, tf, icon, googleMark, toast, said } from '../ui.js';
import { api, fingerprintSupported, fingerprintLogin, bio } from '../api.js';
import { app, signedIn, go } from '../app.js';
import { pubBar, pubFoot } from './welcome.js';

const HASH_ERRORS = { google_off: 'Google sign-in is not switched on yet.', google_failed: 'Google sign-in did not complete. Try again.', google_unverified: 'Google has not verified that email address.', suspended: 'This account is suspended. Contact WODDI HQ.', envoy_closed: 'WODDI is not taking new envoys through this page at the moment. Write to WODDI HQ.' };

export async function render(root, params) {
  const err = said(h('p', { class: 'err', role: 'alert' }));
  const q = new URLSearchParams((location.hash.split('?')[1] || ''));
  if (q.get('e')) err.textContent = HASH_ERRORS[q.get('e')] || 'Sign-in did not complete.';
  const card = h('div', { class: 'card' });
  let lastEmail = ''; try { lastEmail = localStorage.getItem('wdos.email') || ''; } catch { /* ignore */ }

  const signInView = () => {
    const email = h('input', { class: 'input', id: 'l-email', type: 'text', inputmode: 'email', autocapitalize: 'none', required: true, autocomplete: 'username webauthn', value: lastEmail });
    const pw = h('input', { class: 'input', id: 'l-pw', type: 'password', required: true, autocomplete: 'current-password', style: 'padding-inline-end:76px' });
    const btn = h('button', { class: 'btn btn--block', type: 'submit', text: 'Sign in' });
    // v109.30: a password the browser filled in for another account stays behind when the number or email is changed (19 Nov:
    // envoys signing in on HQ's computer got HQ's saved password). Changing who signs in empties a password nobody typed.
    let pwByHand = false;
    ['keydown', 'beforeinput', 'paste'].forEach((ev) => pw.addEventListener(ev, () => { pwByHand = true; }));
    email.addEventListener('input', () => { if (!pwByHand && pw.value) pw.value = ''; });
    const eye = h('button', { type: 'button', class: 'btn btn--quiet btn--s', 'aria-pressed': 'false', style: 'position:absolute;inset-inline-end:6px;top:50%;transform:translateY(-50%)',
      onclick: () => { const show = pw.type === 'password'; pw.type = show ? 'text' : 'password'; eye.setAttribute('aria-pressed', String(show)); eye.textContent = t(show ? 'Hide' : 'Show'); pw.focus(); } }, t('Show'));
    const form = h('form', { style: 'display:grid;gap:14px', novalidate: true },
      h('label', { class: 'field', for: 'l-email' }, h('span', { text: 'Email address or phone number' }), email),
      h('label', { class: 'field', for: 'l-pw' }, h('span', { text: 'Password' }), h('div', { style: 'position:relative' }, pw, eye)),
      h('div', { class: 'forgot' }, h('a', { href: '#', onclick: (e) => { e.preventDefault(); card.replaceChildren(...forgotView(email.value)); }, text: 'Forgot password?' })),
      err, btn);
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); err.textContent = ''; btn.disabled = true; btn.textContent = t('Signing in…');
      try { const r = await api('/auth/login', { email: email.value, password: pw.value }); try { localStorage.setItem('wdos.email', email.value.trim().toLowerCase()); } catch { /* ignore */ } if (r.user.must_change_password) app.tempPassword = pw.value.trim(); signedIn(r.user); }
      catch (ex) { err.textContent = ex.message; btn.disabled = false; btn.textContent = t('Sign in'); pw.select(); }
    });
    const fp = fingerprintSupported() ? h('button', { class: 'fpbtn', type: 'button', onclick: async () => {
      err.textContent = '';
      try { const r = await fingerprintLogin(email.value.trim()); signedIn(r.user); }
      catch (ex) { if (ex.name === 'NotAllowedError') err.textContent = tf('{1} sign-in was cancelled, or this device has not been set up yet. Sign in with your password once, then switch it on in Settings.', t(bio().name)); else err.textContent = ex.message; }
    } }, icon(bio().icon, 20), tf('Sign in with {1}', t(bio().name))) : null;
    const g = app.config.google ? h('a', { class: 'gbtn', href: '/api/auth/google/start' }, googleMark(), 'Continue with Google')
      : h('span', { class: 'gbtn', 'aria-disabled': 'true', title: 'HQ IT has not added the Google keys yet' }, googleMark(), 'Continue with Google');
    return [h('h1', { class: 'display', text: 'Sign in to WDOS' }), form, h('div', { class: 'or', text: 'or' }), g, fp,
      h('p', { class: 'muted', style: 'text-align:center;font-size:14.5px;margin-top:4px' }, 'New to WODDI? ', h('a', { href: '#/', text: 'Choose your network and join' })),
      h('p', { class: 'muted', style: 'text-align:center;font-size:14.5px;margin:0' }, h('a', { href: '#/envoy/join', text: 'Become a WODDI Envoy' }))];
  };
  const forgotView = (prefill) => {
    const email = h('input', { class: 'input', id: 'fg-email', type: 'email', required: true, value: prefill || '', autocomplete: 'email' });
    const msg = said(h('p', { class: 'okmsg', role: 'status' })); const btn = h('button', { class: 'btn btn--block', type: 'submit', text: 'Send reset link' });
    const form = h('form', { style: 'display:grid;gap:14px' }, h('label', { class: 'field', for: 'fg-email' }, h('span', { text: 'Email address' }), email), msg, btn);
    form.addEventListener('submit', async (e) => { e.preventDefault(); btn.disabled = true; try { const r = await api('/auth/forgot', { email: email.value }); msg.textContent = r.message; } catch (ex) { msg.className = 'err'; msg.textContent = ex.message; } btn.disabled = false; });
    return [h('h1', { class: 'display', text: 'Reset your password' }), h('p', { class: 'muted', text: 'Enter the email you joined with. If email is not available, WODDI HQ can reset your password for you.' }), form, h('a', { href: '#', onclick: (e) => { e.preventDefault(); card.replaceChildren(...signInView()); }, text: 'Back to sign in' })];
  };
  const resetView = (token) => {
    const pw = h('input', { class: 'input', id: 'rs-pw', type: 'password', minlength: 8, required: true, autocomplete: 'new-password' });
    const msg = said(h('p', { class: 'err', role: 'alert' })); const btn = h('button', { class: 'btn btn--block', type: 'submit', text: 'Save new password' });
    const form = h('form', { style: 'display:grid;gap:14px' }, h('label', { class: 'field', for: 'rs-pw' }, h('span', { text: 'New password' }), pw, h('small', { text: 'At least 8 characters.' })), msg, btn);
    form.addEventListener('submit', async (e) => { e.preventDefault(); btn.disabled = true; try { await api('/auth/reset', { token, password: pw.value }); toast('Password saved', 'Sign in with your new password.', 'ok'); go('#/login'); } catch (ex) { msg.textContent = ex.message; btn.disabled = false; } });
    return [h('h1', { class: 'display', text: 'Choose a new password' }), form];
  };
  card.append(...(params.reset ? resetView(params.reset) : signInView()));
  root.append(h('div', { class: 'public' }, pubBar(h('a', { class: 'btn btn--quiet', href: '#/', text: 'Join a network' })), h('main', { class: 'signin' }, card), pubFoot()));
}
