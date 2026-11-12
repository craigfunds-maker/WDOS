/* Connected WODDI ecosystem: one Person ID, and what is and is not linked to WDOS today. */
import { h, card, sk, empty, fmt, fmtDate } from '../ui.js';
import { api } from '../api.js';

export async function render(root) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(5))); root.append(box);
  let d; try { d = await api('/hq/integrations'); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This page did not load', e.message))); return; }
  const tag = (s) => h('span', { class: 'tag ' + (s === 'In WDOS' || s === 'Connected' || s === 'On' ? 'tag--wnnn' : s.startsWith('Ready') ? 'tag--wgmn' : ''), text: s.split(':')[0] });
  box.replaceChildren(card('One WODDI Person ID', 'Each person is stored once. Specialist systems keep their own records against the same ID, so nobody registers twice.', h('div', { class: 'tiers' }, h('div', { class: 'tier' }, h('small', { text: 'Identity and authorisation' }), h('b', { text: `${fmt(d.person_ids)} Person IDs` })), h('div', { class: 'tiers__arrow', 'aria-hidden': 'true', text: '↕' }),
      h('div', { class: 'tier' }, h('small', { text: 'WDOS operational services' }), h('b', { text: 'Membership · Leadership · Chapters · Tasks · Reports · Communications' })))),
    card('Systems', 'Said plainly: what is linked today and what is not', h('div', { class: 'list list--stack list--wrap' }, d.systems.map((s) => h('div', { class: 'list__link' }, h('div', { class: 'grow' }, h('b', { text: s[0] }), h('small', { text: s[1] + ' · ' + s[3] })), h('div', { class: 'tagrow' }, tag(s[2])))))),
    card('Services WDOS depends on', null, h('div', { class: 'list' }, d.services.map((s) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: s[0] }), s[1].includes(':') ? h('small', { text: s[1].split(':').slice(1).join(':').trim() }) : null), tag(s[1]))))),
    h('small', { class: 'faint', text: 'As of ' + fmtDate(d.as_of, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + ' (GMT).' }));
}
