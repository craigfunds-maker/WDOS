/* HQ library of the automated messages: when each goes out, its write-up and its photograph. Read-only review page. */
import { h, card, sk, empty, fmtDate, netTag, inspCard, tabs } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';

export async function render(root, params) {
  const tab = ['monthly', 'pictures'].includes(params.tab) ? params.tab : '';
  const kpis = h('div', { class: 'row row--4' }, sk.kpi(), sk.kpi(), sk.kpi(), sk.kpi()); const body = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(5)));
  root.append(tabs([['', 'Weekly messages'], ['monthly', 'Monthly leadership messages'], ['pictures', 'Birthday and announcement pictures']], tab, '#/inspiration'), kpis, body);
  let d; try { d = await api('/hq/inspiration'); } catch (e) { kpis.replaceChildren(); body.replaceChildren(h('div', { class: 'card' }, empty('x', 'The messages did not load', e.message))); return; }
  const all = d.messages; const noPhoto = all.filter((m) => !m.image).length + d.tones.filter((x) => !x.image).length;
  kpis.replaceChildren(kpi('megaphone', 'Messages', all.length, [h('span', { text: `${all.filter((m) => m.kind === 'weekly').length} weekly, ${all.filter((m) => m.kind === 'monthly').length} monthly` })]),
    kpi('camera', 'With a photograph', all.filter((m) => m.image).length + d.tones.filter((x) => x.image).length, [h('span', { text: noPhoto ? `${noPhoto} still without one` : 'Every message has one' })]),
    kpi('book', 'With a write-up', d.with_text, [h('span', { text: d.with_text < all.length ? `${all.length - d.with_text} still without one` : 'Every message has one' })]),
    kpi('calendar', 'Next to go out', (all.filter((m) => m.date >= d.today).sort((a, b) => a.date.localeCompare(b.date))[0] || {}).date ? fmtDate(all.filter((m) => m.date >= d.today).sort((a, b) => a.date.localeCompare(b.date))[0].date) : 'None', [h('span', { text: 'Weekly on Mondays, monthly on the first Wednesday' })]));
  const credit = (c) => c ? h('small', { class: 'faint' }, 'Photograph: ', c.page_url ? h('a', { href: c.page_url, target: '_blank', rel: 'noopener', text: (c.photographer || 'Pexels') + ' on Pexels' }) : (c.photographer || 'Pexels')) : h('small', { class: 'faint', text: 'No photograph yet' });
  if (tab === 'pictures') { body.replaceChildren(card('Birthday and announcement pictures', 'The birthday greeting uses Celebration. HQ chooses any of the six when writing an announcement.', h('div', { class: 'inspgrid' }, d.tones.map((x) => h('div', { class: 'inspmini', style: 'cursor:default' }, x.image ? h('img', { src: x.image, alt: '', loading: 'lazy' }) : h('div', { class: 'inspmini__none' }), h('div', {}, h('b', { text: x.tone[0].toUpperCase() + x.tone.slice(1) }), credit(x.credit))))))); return; }
  const list = all.filter((m) => m.kind === (tab === 'monthly' ? 'monthly' : 'weekly')).sort((a, b) => a.date.localeCompare(b.date) || a.network.localeCompare(b.network));
  body.replaceChildren(...list.map((m) => h('div', { style: 'display:grid;gap:8px' }, h('div', { class: 'tagrow', style: 'justify-content:flex-start' }, h('span', { class: 'tag' + (m.date < d.today ? '' : ' tag--wnnn'), text: (m.date < d.today ? 'Sent ' : 'Goes out ') + fmtDate(m.date) }), m.network !== 'ALL' ? netTag(m.network) : h('span', { class: 'tag', text: 'All leaders' }), h('span', { class: 'tag', text: (m.kind === 'weekly' ? 'Week ' : 'Month ') + m.seq }), m.body ? null : h('span', { class: 'tag tag--bad', text: 'No write-up yet' }), m.langs && m.langs.length > 1 ? h('span', { class: 'tag', text: 'Also in: ' + m.langs.filter((l) => l !== 'en').join(', ') }) : null, credit(m.credit)),
    inspCard({ ...m, label: m.kind === 'weekly' ? 'Weekly inspiration, week ' + m.seq : 'Monthly leadership inspiration', first_name: '' }))));
}
