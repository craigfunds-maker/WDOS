/* The public certificate check that the QR code opens. Anyone can use it, signed in or not.
 * It shows whether the certificate is genuine: the name, the course and the date. Nothing else. */
import { h, t, tf, icon, fmtDate, langPicker } from '../ui.js';
import { api } from '../api.js';

export async function render(root, params = {}) {
  const code = String(params.code || '').toUpperCase().replace(/[^A-Z0-9-]/g, '');
  const input = h('input', { class: 'input', value: code, placeholder: 'WD-XXXX-XXXX-XXXX', 'aria-label': t('Certificate number'), maxlength: 24, style: 'text-transform:uppercase' });
  const out = h('div', { 'aria-live': 'polite' });
  const check = async (c) => {
    if (!c) { out.replaceChildren(); return; }
    out.replaceChildren(h('p', { class: 'faint', text: t('Checking…') }));
    let r; try { r = await api('/public/certificates/' + encodeURIComponent(c)); } catch { out.replaceChildren(h('p', { class: 'errmsg', text: t('The check did not complete. Try again.') })); return; }
    if (!r.found) { out.replaceChildren(h('div', { class: 'verify verify--bad' }, icon('x', 30), h('div', {}, h('b', { text: t('No certificate has this number') }), h('p', { text: t('Check the number on the certificate. A certificate that WDOS did not issue cannot be verified.') })))); return; }
    const x = r.certificate;
    out.replaceChildren(h('div', { class: 'verify ' + (x.valid ? 'verify--ok' : 'verify--bad') }, icon(x.valid ? 'check' : 'x', 30),
      h('div', { style: 'display:grid;gap:6px' }, h('b', { text: x.valid ? t('Genuine WODDI certificate') : t('This certificate has been withdrawn') }),
        h('p', { style: 'margin:0' }, h('span', { text: t('Awarded to') + ' ' }), h('b', { text: x.name })),
        h('p', { style: 'margin:0' }, h('span', { text: t('For') + ' ' }), h('b', { text: x.course_no ? x.course : t('Volunteer Leadership Personal Development Programme') })),
        h('p', { style: 'margin:0', text: tf('Issued {1}. Certificate number {2}.', fmtDate(x.issued_at, { day: 'numeric', month: 'long', year: 'numeric' }), x.code) }),
        x.network ? h('p', { class: 'faint', style: 'margin:0', text: x.network === 'WNNN' ? 'WODDI Nurture Next-Gen Network' : 'WODDI Good Mother Network' }) : null)));
  };
  root.append(h('main', { class: 'verify-page' },
    h('div', { class: 'verify-page__top' }, h('img', { src: '/assets/logos/woddi.png', alt: 'WODDI', style: 'height:56px' }), langPicker ? langPicker() : null),
    h('section', { class: 'card', style: 'display:grid;gap:14px;padding:clamp(16px,2.4vw,26px)' },
      h('h1', { class: 'display', style: 'margin:0', text: t('Check a WODDI certificate') }),
      h('p', { class: 'muted', style: 'margin:0', text: t('WODDI certificates carry a QR code. Scanning it opens this page. You can also type the certificate number.') }),
      h('form', { style: 'display:flex;gap:10px;flex-wrap:wrap', onsubmit: (e) => { e.preventDefault(); const v = input.value.trim().toUpperCase(); history.replaceState(null, '', '#/verify/' + v); check(v); } }, input, h('button', { class: 'btn', type: 'submit' }, t('Check'))),
      out),
    h('p', { class: 'faint', style: 'text-align:center', text: 'WODDI Digital Operating System (WDOS) · No Woman Is Left Out' })));
  check(code);
}
