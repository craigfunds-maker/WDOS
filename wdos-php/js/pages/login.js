/* Sign in: password, Google, or fingerprint. Also the forgotten-password and reset-link screens. */
import { h, icon, googleMark, toast } from '../ui.js';
import { api, fingerprintSupported, fingerprintLogin } from '../api.js';
import { app, signedIn, go } from '../app.js';
import { pubBar, pubFoot } from './welcome.js';

const HASH_ERRORS = { google_off: 'Google sign-in is not switched on yet.', google_failed: 'Google sign-in did not complete. Try again.', google_unverified: 'Google has not verified that email address.', suspended: 'This account is suspended. Contact WODDI HQ.' };

export async function render(root, params) {
  const err = h('p', { class: 'err', role: 'alert' });
  const q = new URLSearchParams((location.hash.split('?')[1] || ''));
  if (q.get('e')) err.textContent = HASH_ERRORS[q.get('e')] || 'Sign-in did not complete.';
  const card = h('div', { class: 'card' });
  let lastEmail = ''; try { lastEmail = localStorage.getItem('wdos.email') || ''; } catch { /* ignore */ }

  const signInView = () => {
    const email = h('input', { class: 'input', id: 'l-email', type: 'email', required: true, autocomplete: 'username webauthn', value: lastEmail });
    const pw = h('input', { class: 'input', id: 'l-pw', type: 'password', required: true, autocomplete: 'current-password' });
    const btn = h('button', { class: 'btn btn--block', type: 'submit', text: 'Sign in' });
    const form = h('form', { style: 'display:grid;gap:14px', novalidate: true },
      h('label', { class: 'field', for: 'l-email' }, h('span', { text: 'Email address' }), email),
      h('label', { class: 'field', for: 'l-pw' }, h('span', { style: 'display:flex;justify-content:space-between' }, 'Password', h('a', { href: '#', style: 'font-weight:500', onclick: (e) => { e.preventDefault(); card.replaceChildren(...forgotView(email.value)); }, text: 'Forgot password?' })), pw),
      err, btn);
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); err.textContent = ''; btn.disabled = true; btn.textContent = 'Signing in…';
      try { const r = await api('/auth/login', { email: email.value, password: pw.value }); try { localStorage.setItem('wdos.email', email.value.trim().toLowerCase()); } catch { /* ignore */ } signedIn(r.user); if (r.user.must_change_password) toast('Choose your own password', 'You signed in with a temporary password. Change it in Settings.', 'info', () => go('#/settings')); }
      catch (ex) { err.textContent = ex.message; btn.disabled = false; btn.textContent = 'Sign in'; pw.select(); }
    });
    const fp = fingerprintSupported() ? h('button', { class: 'fpbtn', type: 'button', onclick: async () => {
      err.textContent = '';
      try { const r = await fingerprintLogin(email.value.trim()); signedIn(r.user); }
      catch (ex) { if (ex.name === 'NotAllowedError') err.textContent = 'Fingerprint sign-in was cancelled, or this device has not been set up yet. Sign in with your password once, then switch it on in Settings.'; else err.textContent = ex.message; }
    } }, icon('fingerprint', 20), 'Sign in with fingerprint') : null;
    const g = app.config.google ? h('a', { class: 'gbtn', href: '/api/auth/google/start' }, googleMark(), 'Continue with Google')
      : h('span', { class: 'gbtn', 'aria-disabled': 'true', title: 'HQ IT has not added the Google keys yet' }, googleMark(), 'Continue with Google');
    return [h('h1', { class: 'display', text: 'Sign in to WDOS' }), h('p', { class: 'muted', text: 'For WGMN and WNNN members, volunteer leaders and HQ.' }), form, h('div', { class: 'or', text: 'or' }), g, fp,
      h('p', { class: 'muted', style: 'text-align:center;font-size:14.5px;margin-top:4px' }, 'New to WODDI? ', h('a', { href: '#/', text: 'Choose your network and join' }))];
  };
  const forgotView = (prefill) => {
    const email = h('input', { class: 'input', id: 'fg-email', type: 'email', required: true, value: prefill || '', autocomplete: 'email' });
    const msg = h('p', { class: 'okmsg', role: 'status' }); const btn = h('button', { class: 'btn btn--block', type: 'submit', text: 'Send reset link' });
    const form = h('form', { style: 'display:grid;gap:14px' }, h('label', { class: 'field', for: 'fg-email' }, h('span', { text: 'Email address' }), email), msg, btn);
    form.addEventListener('submit', async (e) => { e.preventDefault(); btn.disabled = true; try { const r = await api('/auth/forgot', { email: email.value }); msg.textContent = r.message; } catch (ex) { msg.className = 'err'; msg.textContent = ex.message; } btn.disabled = false; });
    return [h('h1', { class: 'display', text: 'Reset your password' }), h('p', { class: 'muted', text: 'Enter the email you joined with. If email is not available, WODDI HQ can reset your password for you.' }), form, h('a', { href: '#', onclick: (e) => { e.preventDefault(); card.replaceChildren(...signInView()); }, text: 'Back to sign in' })];
  };
  const resetView = (token) => {
    const pw = h('input', { class: 'input', id: 'rs-pw', type: 'password', minlength: 8, required: true, autocomplete: 'new-password' });
    const msg = h('p', { class: 'err', role: 'alert' }); const btn = h('button', { class: 'btn btn--block', type: 'submit', text: 'Save new password' });
    const form = h('form', { style: 'display:grid;gap:14px' }, h('label', { class: 'field', for: 'rs-pw' }, h('span', { text: 'New password' }), pw, h('small', { text: 'At least 8 characters.' })), msg, btn);
    form.addEventListener('submit', async (e) => { e.preventDefault(); btn.disabled = true; try { await api('/auth/reset', { token, password: pw.value }); toast('Password saved', 'Sign in with your new password.', 'ok'); go('#/login'); } catch (ex) { msg.textContent = ex.message; btn.disabled = false; } });
    return [h('h1', { class: 'display', text: 'Choose a new password' }), form];
  };
  card.append(...(params.reset ? resetView(params.reset) : signInView()));
  root.append(h('div', { class: 'public' }, pubBar(h('a', { class: 'btn btn--quiet', href: '#/', text: 'Join a network' })), h('main', { class: 'signin' }, card), pubFoot()));
}
