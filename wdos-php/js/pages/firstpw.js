/* v109.31: the first thing someone sees after signing in with a temporary password from HQ: choose her own, and nothing else.
 * (19 Nov: envoys were sent to the full Settings page, with member fields that mean nothing to them.) The temporary password she
 * has just typed is kept in memory for this one step, so she is not asked for it again. */
import { h, t, tf, icon, toast } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';

export async function render(root) {
  const u = app.user;
  const temp = h('input', { class: 'input', type: 'password', autocomplete: 'current-password', id: 'fp-temp' });
  const pw = h('input', { class: 'input', type: 'password', autocomplete: 'new-password', id: 'fp-new', minlength: 6 });
  const pw2 = h('input', { class: 'input', type: 'password', autocomplete: 'new-password', id: 'fp-new2', minlength: 6 });
  const err = h('p', { class: 'err', role: 'alert' });
  const show = h('label', { style: 'display:flex;gap:8px;align-items:center' }, h('input', { type: 'checkbox', onchange: (e) => [temp, pw, pw2].forEach((x) => { x.type = e.target.checked ? 'text' : 'password'; }) }), h('span', { text: t('Show what I type') }));
  const btn = h('button', { class: 'btn btn--block', type: 'submit' }, t('Save my password'));
  const known = !!app.tempPassword;
  const how = u.email_is_phone ? tf('Next time, sign in with your phone number {1} and this password.', u.phone || '') : tf('Next time, sign in with {1} and this password.', u.email);
  root.append(h('section', { class: 'card', style: 'max-width:520px;margin:0 auto' }, h('div', { class: 'card__body', style: 'display:grid;gap:16px' },
    h('h1', { class: 'display', style: 'margin:0;font-size:28px', text: tf('Welcome, {1}', u.first_name) }),
    u.designation ? h('p', { class: 'muted', style: 'margin:0', text: u.designation }) : null,
    h('p', { style: 'margin:0', text: t('You signed in with a temporary password from WODDI HQ. Choose your own password now. It takes a few seconds.') }),
    h('form', { style: 'display:grid;gap:14px', novalidate: true, onsubmit: async (e) => {
      e.preventDefault(); err.textContent = '';
      if (pw.value.length < 6) { err.textContent = t('Choose a password of at least 6 letters or numbers.'); pw.focus(); return; }
      if (pw.value !== pw2.value) { err.textContent = t('The two passwords are not the same. Type them again.'); pw2.focus(); return; }
      btn.disabled = true;
      try {
        await api('/me/password', { current_password: known ? app.tempPassword : temp.value, new_password: pw.value });
        app.tempPassword = null; app.user.must_change_password = false;
        toast(t('Your password is saved'), how, 'ok'); go('#/');
      } catch (ex) { err.textContent = ex.message; btn.disabled = false; }
    } },
      known ? null : h('label', { class: 'field', for: 'fp-temp' }, h('span', { text: t('Temporary password from HQ') }), temp),
      h('label', { class: 'field', for: 'fp-new' }, h('span', { text: t('Your new password') }), pw, h('small', { text: t('At least 6 letters or numbers. Keep it to yourself.') })),
      h('label', { class: 'field', for: 'fp-new2' }, h('span', { text: t('Type it again') }), pw2), show, err, btn),
    h('p', { class: 'muted', style: 'margin:0;font-size:14px', text: how }))));
  (known ? pw : temp).focus();
}
