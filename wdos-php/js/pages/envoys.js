/* HQ: WODDI Envoys. Who they are, what their links bring in, how far the people they referred are in the 3 days, and opening a new envoy. */
import { h, t, tf, icon, avatar, card, sk, empty, ago, fmt, toast, input, field } from '../ui.js';
import { api } from '../api.js';
import { flagText } from './envoy.js';
import { kpi } from './hq.js';
import { peopleTable } from './envoy.js';

export async function render(root, params) {
  if (params.id) return one(root, params.id);
  const kp = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()), list = h('div', {}, sk.rows(4)), formHost = h('div'), tempBox = h('div');
  root.append(kp, tempBox, card('WODDI Envoys', 'Each envoy has one referral link. People who join through it go through the 3-day activation, and the envoy follows them up.', list, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn', type: 'button', onclick: () => { formHost.hidden = !formHost.hidden; } }, icon('plus', 18), t('Add an envoy')),
    h('a', { class: 'btn btn--ghost', href: '#/hq/logins/envoys' }, icon('key', 18), ' ', t('Sign-in details for many envoys (PDF)')), h('a', { class: 'btn btn--lime', href: '#/hq/drive?who=envoys' }, icon('send', 18), ' ', t('Launch pack on WhatsApp')))), formHost);
  formHost.hidden = true;
  const F = { first: input({ maxlength: 80, autocomplete: 'off' }), last: input({ maxlength: 80, autocomplete: 'off' }), email: input({ type: 'email', maxlength: 190, autocomplete: 'off' }), phone: input({ type: 'tel', maxlength: 32, autocomplete: 'off' }), country: h('select', { class: 'input' }, h('option', { value: '', text: t('Choose the country') })) };
  const invite = h('input', { type: 'checkbox' }); invite.checked = true; const err = h('p', { class: 'err', role: 'alert' });
  api('/geo/countries').then((r) => F.country.append(...r.countries.map((c) => h('option', { value: c.code, text: c.name })))).catch(() => {});
  formHost.append(card('Add a WODDI Envoy', 'The envoy gets a limited dashboard: her link, the people she referred and their 3 days, her tasks and her messages. Nothing else.',
    h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'row row--2' }, field('First name', F.first), field('Surname', F.last)), h('div', { class: 'row row--2' }, field('Email (she signs in with it)', F.email), field('Phone', F.phone)), field('Country', F.country),
      h('label', { class: 'check' }, invite, h('span', { text: t('Email her a link to choose her own password') })), err,
      h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async (e) => { err.textContent = ''; e.currentTarget.disabled = true;
        try { const r = await api('/hq/envoys', { first_name: F.first.value, last_name: F.last.value, email: F.email.value, phone: F.phone.value, country_code: F.country.value, send_invite: invite.checked });
          tempBox.replaceChildren(h('div', { class: 'panel', role: 'status', style: 'display:grid;gap:8px;margin-bottom:16px' }, h('b', { text: r.invited ? t('Envoy added. She has been emailed a link to choose her password.') : t('Envoy added. Give her these now. The password is shown only once.') }),
            h('div', { class: 'kv' }, h('div', {}, h('span', { text: t('Signs in with') }), h('b', { text: r.email })), r.invited ? null : h('div', {}, h('span', { text: t('Temporary password') }), h('b', { style: 'font-family:ui-monospace,monospace', text: r.temporary_password })), h('div', {}, h('span', { text: t('Referral link') }), h('b', { style: 'overflow-wrap:anywhere', text: r.link })))));
          Object.values(F).forEach((x) => { if (x.tagName === 'INPUT') x.value = ''; }); formHost.hidden = true; load(); }
        catch (ex) { err.textContent = ex.message; } e.currentTarget.disabled = false; } }, t('Add envoy'))))));
  async function load() {
    let d; try { d = await api('/hq/envoys'); } catch (e) { list.replaceChildren(empty('x', 'Envoys did not load', e.message)); kp.replaceChildren(); return; }
    const k = d.totals;
    kp.replaceChildren(kpi('users', 'WODDI Envoys', k.envoys || 0, [h('span', { text: tf('{1} active', fmt(k.active || 0)) })]), kpi('eye', 'Link visits, 30 days', k.visits_30 || 0, [h('span', { text: tf('{1} in total', fmt(k.visits_total || 0)) })]),
      kpi('route', 'People referred', k.referred || 0, [h('span', { text: tf('{1} in the last 30 days', fmt(k.referred_30 || 0)) })]), kpi('check', 'Finished the 3 days', k.three_days_done || 0, [h('span', { text: tf('{1} assessments submitted', fmt(k.assessment_submitted || 0)) })]));
    list.replaceChildren(d.envoys.length ? h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, ['Envoy', 'Visits (30 days)', 'Referred', 'Started', 'Finished 3 days', 'Messages sent', 'Last seen', ''].map((c, i) => h('th', { class: [1, 5, 6].includes(i) ? 'hide-s' : '', text: c })))),
      h('tbody', {}, d.envoys.map((e) => h('tr', { style: 'cursor:default' },
        h('td', {}, h('div', { class: 'who' }, avatar(e, 34), h('div', {}, h('b', {}, h('a', { href: '#/envoys/' + e.id, text: `${e.first_name} ${e.last_name}` })), h('small', { class: 'muted', text: [e.country, e.email].filter(Boolean).join(' · ') }), e.status === 'pending' ? h('span', { class: 'tag tag--wgmn', text: t('Waiting for approval') }) : e.status !== 'active' ? h('span', { class: 'tag tag--bad', text: t('Paused') }) : null, e.source === 'self' ? h('small', { class: 'faint', text: t('Signed up herself') }) : null))),
        h('td', { class: 'hide-s', text: fmt(e.kpis.visits_30) }), h('td', { text: fmt(e.kpis.referred) }), h('td', { text: fmt(e.kpis.activation_started) }), h('td', { text: fmt(e.kpis.three_days_done) }), h('td', { class: 'hide-s', text: fmt(e.kpis.messages_sent_30) }),
        h('td', { class: 'hide-s' }, h('small', { text: e.last_seen_at ? ago(e.last_seen_at) : t('Never') })),
        h('td', {}, h('div', { style: 'display:flex;gap:6px;flex-wrap:wrap;min-width:230px' }, h('a', { class: 'btn btn--quiet btn--s', href: '#/envoys/' + e.id }, t('Open')), h('a', { class: 'btn btn--ghost btn--s', href: '#/inbox/to/' + e.id }, t('Message')),
          h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { if (!confirm(e.status === 'active' ? t('Pause this envoy? Her link stops working and she is signed out.') : e.status === 'pending' ? t('Approve this envoy? Her referral link switches on and she is told.') : t('Switch this envoy back on?'))) return; try { await api('/hq/envoys/' + e.id, { active: e.status !== 'active' }); load(); } catch (x) { toast('Not changed', x.message, 'bad'); } } }, e.status === 'active' ? t('Pause') : e.status === 'pending' ? t('Approve') : t('Switch on'))))))))) : empty('users', 'No envoys yet', 'Add the first envoy with the button below.'));
  }
  load();
}

async function one(root, id) {
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/envoys', style: 'margin-bottom:12px' }, t('Back to envoys')));
  const box = h('div', {}, sk.rows(4)); root.append(box);
  let d; try { d = await api('/hq/envoys/' + id); } catch (e) { box.replaceChildren(card('Envoy', null, empty('x', 'This envoy did not open', e.message))); return; }
  const k = d.kpis;
  box.replaceChildren(h('div', { class: 'row row--4' }, kpi('eye', 'Link visits, 30 days', k.visits_30, [h('span', { text: tf('{1} in total', fmt(k.visits_total)) })]), kpi('users', 'People referred', k.referred, [h('span', { text: tf('{1} in the last 30 days', fmt(k.referred_30)) })]),
    kpi('route', 'Finished the 3 days', k.three_days_done, [h('span', { text: tf('{1} started', fmt(k.activation_started)) })]), kpi('send', 'Follow-up messages, 30 days', k.messages_sent_30, [h('span', { text: tf('{1} active this week', fmt(k.active_7)) })])),
    d.envoy.source === 'self' ? card('Her sign-up details', '', h('dl', { class: 'dl' }, [['Phone number', d.envoy.phone], ['Country', d.envoy.country], ['State or region', d.envoy.state_region], ['City or town', d.envoy.city], ['Organisation or group', d.envoy.organisation], ['Where she will share her link', d.envoy.reach_plan], ['How she heard about WODDI', d.envoy.heard_from], ['Signed up', d.envoy.created_at ? ago(d.envoy.created_at) : '']].filter(([, v]) => v).flatMap(([k, v]) => [h('dt', { text: t(k) }), h('dd', { text: v })]))) : null,
    d.leaders ? card('Volunteer leaders she brought in', 'Overdue tasks, not signing in and leaving a seat are flagged; she is told each day.', d.leaders.length ? h('div', { class: 'list list--stack' }, d.leaders.map((l) => h('a', { class: 'list__link', href: '#/members/' + l.id, style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' },
      h('div', { class: 'grow', style: 'min-width:180px' }, h('b', { text: `${l.first_name} ${l.last_name}`.trim() }), h('small', { text: [l.title, l.last_seen_at ? tf('Seen {1}', ago(l.last_seen_at)) : t('Never signed in')].filter(Boolean).join(' · ') })),
      ...(l.flags.length ? l.flags.map((f) => h('span', { class: 'tag tag--bad', text: flagText(f) })) : [h('span', { class: 'tag tag--wnnn', text: t('On track') })])))) : empty('users', 'No volunteer leaders yet', '')) : null,
    card(`${d.envoy.first_name} ${d.envoy.last_name}`, d.envoy.link, peopleTable(d.people, { message: true })));
}
