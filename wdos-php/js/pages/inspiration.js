/* Inspiration: today's message in full, then everything released so far. HQ also sees what is scheduled next. */
import { h, card, sk, empty, inspCard, netTag } from '../ui.js';
import { api } from '../api.js';

export async function render(root) {
  const top = h('div', {}, sk.chart(250)); const wk = h('div', {}, sk.rows(3)); const mo = h('div', {}, sk.rows(3));
  let network = '';
  const seg = h('div'); const moCard = card('Monthly leadership inspiration', 'Released on the first Wednesday of each month', mo);
  root.append(top, card('Weekly inspiration', 'A new message every Monday at 7:00 in the morning, Lagos time', wk, seg), moCard);
  const mini = (c, onOpen) => h('button', { type: 'button', class: 'inspmini' + (c.released ? '' : ' is-later'), onclick: () => onOpen(c) }, h('img', { src: c.image, alt: '', loading: 'lazy' }),
    h('div', {}, h('small', {}, c.label, c.current ? h('span', { class: 'tag tag--wgmn', text: 'Current' }) : null, c.released ? null : h('span', { class: 'tag', text: 'Scheduled' })), h('b', { text: c.headline }), c.key_thought || c.theme ? h('p', { text: c.key_thought || c.theme }) : null));
  async function load() {
    let d; try { d = await api('/inspiration/library' + (network ? '?network=' + network : '')); } catch (e) { top.replaceChildren(empty('x', 'Inspiration did not load', e.message)); return; }
    network = d.network;
    const open = (c) => { top.replaceChildren(inspCard({ ...c, label: (c.kind === 'weekly' ? 'Weekly inspiration, ' : 'Monthly leadership inspiration, ') + c.label.toLowerCase().replace(/^\w/, (x) => x.toUpperCase()) }, true)); window.scrollTo({ top: 0, behavior: 'smooth' }); };
    seg.replaceChildren(d.is_hq ? h('div', { class: 'seg', role: 'group', 'aria-label': 'Network' }, ['WGMN', 'WNNN'].map((n) => h('button', { type: 'button', 'aria-pressed': String(n === network), onclick: () => { network = n; load(); }, text: n }))) : netTag(network) || '');
    const today = await api('/inspiration/today').catch(() => ({ card: null }));
    const cur = today.card || d.weekly.find((w) => w.current) || d.weekly.find((w) => w.released) || d.monthly.find((m) => m.current);
    if (cur) open(today.card ? cur : cur); else top.replaceChildren(h('div', { class: 'card' }, empty('spark', 'No message yet', d.week < 1 ? 'The weekly programme has not started yet.' : 'Your first message arrives on Monday morning.')));
    if (today.card) top.replaceChildren(inspCard(today.card, true));
    wk.replaceChildren(d.weekly.length ? h('div', { class: 'inspgrid reveal' }, d.weekly.map((c) => mini(c, open)))
      : empty('spark', 'Nothing released yet', network ? 'The first message appears here on its Monday.' : 'Weekly messages are written for WGMN and WNNN members.'));
    if (d.is_hq && d.week > d.weeks_loaded) wk.prepend(h('p', { class: 'err', style: 'margin-bottom:14px', text: `This is week ${d.week} of the programme, but only weeks 1 to ${d.weeks_loaded} are loaded. No weekly message is going out until more weeks are added.` }));
    moCard.hidden = !d.monthly.length && !d.is_hq;
    mo.replaceChildren(d.monthly.length ? h('div', { class: 'inspgrid reveal' }, d.monthly.map((c) => mini(c, open))) : empty('spark', 'Nothing released yet', 'The first monthly message appears on the first Wednesday.'));
  }
  load();
}
