/* v109.45: HQ sees which WODDI Envoys have filled and accepted their Appointment Acceptance & Commitment Form, opens each form,
 * acknowledges it (Part F), and can send a reminder to everyone still waiting. */
import { h, t, tf, icon, toast, fmtDate, ago, empty } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';

export async function render(root) {
  root.append(h('p', { class: 'muted', text: t('Loading…') }));
  let d; try { d = await api('/hq/envoy-acceptances'); } catch (e) { root.replaceChildren(h('p', { class: 'err', text: e.message })); return; }
  const T = d.totals; let filter = 'all';
  const list = h('div', { class: 'card', style: 'padding:6px 0' });
  const paint = () => {
    const rows = d.envoys.filter((e) => filter === 'all' || (filter === 'accepted' ? e.accepted_at : filter === 'pending' ? !e.accepted_at : e.accepted_at && !e.acknowledged_at));
    list.replaceChildren(...(rows.length ? rows.map((e) => h('a', { class: 'listrow', href: '#/envoys/' + e.id + '/form', style: 'display:flex;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid var(--line-soft);color:inherit;text-decoration:none' },
      h('span', { class: 'eform__box' + (e.accepted_at ? ' is-on' : ''), style: 'width:26px;height:26px' }, e.accepted_at ? icon('check', 15) : null),
      h('div', { class: 'grow' }, h('b', { text: e.name }), h('small', { class: 'muted', style: 'display:block', text: e.designation || t('WODDI Envoy') })),
      h('div', { style: 'text-align:right' }, e.accepted_at
        ? h('span', { class: 'eform__ref' + (e.acknowledged_at ? '' : ' eform__ref--wait'), text: e.acknowledged_at ? t('Accepted and acknowledged') : t('Accepted, waiting for HQ') })
        : h('span', { class: 'eform__ref eform__ref--wait', text: t('Not yet accepted') }),
        h('small', { class: 'muted', style: 'display:block;margin-top:4px', text: e.accepted_at ? fmtDate(e.accepted_at) : (e.reminders ? tf('{1} reminders sent', e.reminders) : t('No reminder yet')) + (e.last_seen_at ? ' · ' + tf('seen {1}', ago(e.last_seen_at)) : ' · ' + t('never signed in')) })))) : [empty('award', t('Nobody here'), t('No envoy matches this filter.'))]));
  };
  const seg = h('div', { class: 'seg', role: 'group' }, [['all', 'All'], ['accepted', 'Accepted'], ['pending', 'Not yet'], ['ack', 'Waiting for HQ']].map(([k, l]) =>
    h('button', { type: 'button', 'aria-pressed': String(k === filter), onclick: (ev) => { filter = k; seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === ev.currentTarget))); paint(); } }, t(l))));
  const remind = h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => {
    remind.disabled = true; try { const r = await api('/hq/envoy-acceptances/remind', {}); toast(t('Reminders sent'), tf('{1} envoys reminded by email and on their phones.', r.reminded), 'good'); } catch (e) { toast(t('Not sent'), e.message, 'bad'); } remind.disabled = false; } }, icon('send', 16), ' ', t('Remind everyone not yet accepted'));
  root.replaceChildren(
    h('div', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { text: t('Envoy appointment forms') }), h('p', { text: t('Who has filled and accepted the WODDI Envoy Appointment Acceptance & Commitment Form. Envoys who have not are reminded by email and on their phones on days 0, 2, 5, 9 and 14.') }))),
    h('div', { class: 'grid', style: 'grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px' },
      kpi('users', 'Envoys', T.envoys, ''), kpi('award', 'Accepted', T.accepted, T.envoys ? Math.round(100 * T.accepted / T.envoys) + '%' : ''), kpi('clock', 'Not yet', T.pending, ''), kpi('check', 'Acknowledged by HQ', T.acknowledged, '')),
    h('div', { style: 'display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center' }, seg, remind), list);
  paint();
}
