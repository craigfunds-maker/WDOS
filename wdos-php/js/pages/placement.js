/* v109.37: who waits for a chapter, by area (Azeez, 20 Nov 2026). Each area says why: no location yet (she is asked to add it),
 * a chapter with room exists (WDOS places her by itself), or no chapter there yet (HQ can open one and place them all at once). */
import { h, t, tf, icon, card, sk, empty, toast, fmt, netTag, flag } from '../ui.js';
import { api } from '../api.js';

const WHY = { no_location: ['No LGA, city or community', 'WDOS asks her to add it. She is placed as soon as she does.'], placeable: ['A chapter here has room', 'WDOS places her by itself within minutes.'], no_chapter: ['No chapter here yet', 'Open one and everyone waiting here is placed in it.'] };
export async function render(root) {
  const box = h('div', {}, sk.rows(8));
  root.append(h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { class: 'display', text: t('Members waiting for a chapter') }), h('p', { text: t('By area, largest first, with the reason and what to do.') }))), box);
  async function load() {
    let d; try { d = await api('/hq/placement'); } catch (e) { box.replaceChildren(empty('x', 'This did not load', e.message)); return; }
    const total = d.groups.reduce((a, g) => a + g.n, 0); const by = (r) => d.groups.filter((g) => g.reason === r).reduce((a, g) => a + g.n, 0);
    const row = (g) => {
      const area = [g.community, g.lga, g.state, g.country_name].filter(Boolean).join(', ') || t('No location at all');
      const open = g.can_open ? h('button', { class: 'btn btn--s', type: 'button', onclick: async (e) => { if (!confirm(tf('Open a {1} chapter in {2} and place the {3} women waiting there?', g.network, area, g.n))) return; e.target.disabled = true;
        try { const r = await api('/hq/placement/open', { network: g.network, country: g.country, state: g.state, lga: g.lga, community: g.community }); toast(t('Chapter opened'), tf('{1}: {2} placed', r.name, fmt(r.placed)), 'ok'); load(); } catch (x) { toast(t('Not opened'), x.message, 'bad'); e.target.disabled = false; } } }, icon('plus', 15), ' ', t('Open a chapter here')) : null;
      return h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, flag(g.country), h('div', { class: 'grow', style: 'min-width:220px' }, h('b', { text: area }), h('small', { class: 'muted', style: 'display:block', text: t(WHY[g.reason][0]) + '. ' + t(WHY[g.reason][1]) })),
        netTag(g.network), h('span', { class: 'tag', text: fmt(g.n) }), open);
    };
    box.replaceChildren(h('div', { style: 'display:grid;gap:18px' },
      h('div', { class: 'metrics' }, h('div', {}, h('div', { class: 'metrics__n num', text: fmt(total) }), h('small', { text: t('Waiting') })), h('div', {}, h('div', { class: 'metrics__n num', text: fmt(by('no_location')) }), h('small', { text: t('No location yet') })),
        h('div', {}, h('div', { class: 'metrics__n num', text: fmt(by('no_chapter')) }), h('small', { text: t('No chapter in their area') })), h('div', {}, h('div', { class: 'metrics__n num', text: fmt(by('placeable')) }), h('small', { text: t('Being placed by WDOS') }))),
      h('p', { class: 'muted', style: 'margin:0', text: d.auto_create ? t('WDOS opens new chapters by itself where none has room.') : tf('WDOS does not open chapters by itself (Rules). You can open one here where at least {1} women wait.', d.min) }),
      plain(d.groups.length ? h('div', { class: 'list list--stack' }, d.groups.map(row)) : empty('check', t('Nobody is waiting'), t('Every member is in a chapter.')))));
  }
  load();
}
const plain = (body) => h('section', { class: 'card' }, h('div', { class: 'card__body' }, body));
