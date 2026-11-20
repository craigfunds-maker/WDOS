/* v109.37: the sign-in drive (Azeez, 20 Nov 2026). Volunteer leaders and WODDI Envoys who have never signed in, each with one button
 * that makes fresh sign-in details and opens WhatsApp with the message already written (the envoy's message carries her referral link
 * and the video guide). HQ sends it from its own phone. Those with an email also get a weekly link to choose a password, until they sign in. */
import { h, t, tf, icon, card, sk, empty, toast, fmt, ago, avatar, netTag, flag } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';

export async function render(root, params = {}) {
  const qs = new URLSearchParams(location.hash.split('?')[1] || '');
  let who = qs.get('who') === 'envoys' ? 'envoys' : 'leaders'; let country = qs.get('country') || ''; let network = '';
  const tabs = h('div', { class: 'seg', role: 'group' }); const head = h('div'); const list = h('div', {}, sk.rows(6)); const filt = h('div', { class: 'toolbar', style: 'flex-wrap:wrap' });
  root.append(h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { class: 'display', text: t('Sign-in drive') }),
    h('p', { text: t('Volunteer leaders and envoys who have never signed in. Press Send on WhatsApp: WDOS makes fresh sign-in details and opens WhatsApp with the message ready. You press Send in WhatsApp.') }))),
    tabs, head, plain(h('div', { style: 'display:grid;gap:12px' }, filt, list)));
  const paintTabs = () => tabs.replaceChildren(...[['leaders', 'Volunteer leaders'], ['envoys', 'WODDI Envoys']].map(([k, l]) => h('button', { type: 'button', 'aria-pressed': String(k === who), onclick: () => { who = k; load(); } }, t(l))));
  async function send(p, btn, again) {
    btn.disabled = true;
    try {
      const r = await api(`/hq/signin-drive/${p.id}/pack`, again ? { confirm: true } : {});
      window.open(r.wa_url, '_blank', 'noopener');
      if (!r.has_number) toast(t('Choose her in WhatsApp'), t('Her phone number has no country code WDOS knows, so WhatsApp opened without a contact. Pick her, then press Send.'), 'info');
      else toast(t('WhatsApp is open'), t('Check the message and press Send in WhatsApp.'), 'ok');
      p.pack_at = new Date().toISOString().slice(0, 19).replace('T', ' '); p.packs++; paint();
    } catch (e) { toast(t('Not sent'), e.message, 'bad'); btn.disabled = false; }
  }
  let d = null;
  function paint() {
    const T = d.totals;
    head.replaceChildren(h('div', { class: 'row row--4' },
      kpi('users', t('Never signed in'), T.never, [h('span', { text: who === 'envoys' ? t('WODDI Envoys') : t('Volunteer leaders') })]),
      kpi('send', t('Sign-in details sent'), T.packs_sent, [h('span', { text: t('On WhatsApp, by HQ') })]),
      kpi('check', t('Signed in after we sent'), T.signed_in_after_pack, [h('span', { text: t('Since the first message') })]),
      kpi('inbox', t('Weekly email reminders'), T.emailed, [h('span', { text: d.email_on ? t('Every Tuesday, with a link to choose a password') : t('Email is switched off') })])));
    list.replaceChildren(d.people.length ? h('div', { class: 'list list--stack' }, d.people.map((p) => {
      const btn = h('button', { class: 'btn btn--lime btn--s', type: 'button', onclick: () => send(p, btn, false) }, icon('send', 15), ' ', p.pack_at ? t('Send again') : t('Send on WhatsApp'));
      return h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, avatar(p, 40),
        h('div', { class: 'grow', style: 'min-width:200px;display:grid;gap:2px' }, h('b', { text: p.name }), h('small', { text: p.position }),
          h('small', { class: 'muted', text: [p.phone || t('No phone number'), p.email ? t('has email') : '', p.pack_at ? tf('sent {1}', ago(p.pack_at)) + (p.packs > 1 ? ` (${p.packs}x)` : '') : t('not sent yet'), p.nudges ? tf('{1} emails', p.nudges) : ''].filter(Boolean).join(' · ') })),
        h('div', { style: 'display:flex;gap:6px;align-items:center' }, flag(p.country), p.network ? netTag(p.network) : null, p.phone ? btn : h('a', { class: 'btn btn--ghost btn--s', href: '#/members/' + p.id }, t('Add her phone'))));
    })) : empty('check', t('Everyone has signed in'), who === 'envoys' ? t('Every active envoy has signed in at least once.') : t('Every seated volunteer leader has signed in at least once.')));
  }
  async function load() {
    paintTabs(); list.replaceChildren(sk.rows(6));
    try { d = await api('/hq/signin-drive?' + new URLSearchParams({ who, country, network })); } catch (e) { list.replaceChildren(empty('x', 'This did not load', e.message)); return; }
    const cs = h('select', { class: 'input', style: 'width:auto' }, h('option', { value: '', text: t('All countries') }), ...d.countries.sort((a, b) => a.name.localeCompare(b.name)).map((c) => h('option', { value: c.code, text: c.name }))); cs.value = country;
    cs.addEventListener('change', () => { country = cs.value; load(); });
    const ns = h('select', { class: 'input', style: 'width:auto' }, [['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']].map(([v, l]) => h('option', { value: v, text: t(l) }))); ns.value = network;
    ns.addEventListener('change', () => { network = ns.value; load(); });
    filt.replaceChildren(cs, ns, h('small', { class: 'muted', text: t('Each press makes a new temporary password. Her old one stops working. She chooses her own after she signs in.') }));
    paint();
  }
  load();
}
const plain = (body) => h('section', { class: 'card' }, h('div', { class: 'card__body' }, body));
