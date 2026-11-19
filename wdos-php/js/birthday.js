/* v109.22: a member WDOS has no date of birth for (for example, someone brought across from the old system) is asked for
 * it on her dashboard, so WODDI can greet her on her birthday in the app, on her phone and by email. Once saved it can
 * only be corrected by HQ. */
import { h, t, icon, toast } from './ui.js';
import { api } from './api.js';
import { refreshUser } from './app.js';
export function birthdayAsk() {
  const max = new Date(Date.now() - 18 * 365.25 * 864e5).toISOString().slice(0, 10);
  const inp = h('input', { class: 'input', type: 'date', id: 'bday', max, min: '1915-01-01', 'aria-label': t('Your date of birth'), style: 'max-width:220px' });
  const btn = h('button', { class: 'btn', type: 'button', onclick: async () => {
    if (!inp.value) { inp.focus(); toast(t('Choose your date of birth'), '', 'bad'); return; }
    btn.disabled = true;
    try { await api('/me/birthday', { dob: inp.value }); await refreshUser(); box.replaceChildren(h('div', { class: 'card__body' }, h('b', { text: t('Thank you. WODDI will celebrate you on your birthday.') }))); }
    catch (e) { toast(t('Not saved'), e.message, 'bad'); btn.disabled = false; } } }, t('Save'));
  const box = h('section', { class: 'card nudge', id: 'bday-card' }, h('div', { class: 'card__body', style: 'display:flex;gap:14px;align-items:center;flex-wrap:wrap' },
    h('div', { class: 'grow', style: 'min-width:220px' }, h('b', {}, icon('calendar', 18), ' ', t('When is your birthday?')), h('p', { class: 'muted', style: 'margin:4px 0 0', text: t('Add your date of birth so WODDI can celebrate you on your day, in the app, on your phone and by email.') })),
    inp, btn));
  return box;
}
