/* Leadership and jurisdiction architecture: the tiers and the titles for each network, in Africa and in the diaspora.
 * Titles come from the positions WDOS holds, so a title changed by a management decision shows here at once. */
import { h, card, sk, empty, tabs, fmt } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';

export async function render(root, params) {
  const mode = params.tab === 'diaspora' ? 'diaspora' : 'africa'; let net = app.user.network === 'WNNN' ? 'WNNN' : 'WGMN';
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(6))); root.append(tabs([['', 'Africa'], ['diaspora', 'Diaspora']], mode === 'africa' ? '' : 'diaspora', '#/leadership'), box);
  let d; try { d = await api('/leadership/architecture'); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This page did not load', e.message))); return; }
  const paint = () => {
    const rows = d[mode]; const pick = h('select', { class: 'input', 'aria-label': 'Network', style: 'max-width:260px' }, h('option', { value: 'WGMN', text: 'WGMN: Good Mother' }), h('option', { value: 'WNNN', text: 'WNNN: Next-Gen' })); pick.value = net; pick.onchange = () => { net = pick.value; paint(); };
    box.replaceChildren(h('div', { class: 'row row--2' },
      card(mode === 'africa' ? 'Africa: six operational leadership tiers' : 'Diaspora: country, state where needed, city, chapter', 'Deputies and assistants support their principal. They are not an extra approval step.', h('div', { class: 'tiers' }, rows.flatMap((r, i) => [i ? h('div', { class: 'tiers__arrow', 'aria-hidden': 'true', text: '↓' }) : null, h('div', { class: 'tier' }, h('small', { text: r[0] }), h('b', { text: r[1][net] }))]),
        h('div', { class: 'tiers__arrow', 'aria-hidden': 'true', text: '↓' }), h('div', { class: 'tier tier--end' }, h('small', { text: 'Members and volunteers' }), h('b', { text: 'One Person ID. One chapter in each network membership.' }))), pick),
      h('div', { style: 'display:grid;gap:18px;align-content:start' },
        card('Additional authorised roles', null, h('div', { class: 'list' }, d.extra.map((x) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: x[0] }), h('small', { text: x[1] })), h('span', { class: 'tag', text: x[2] }))))),
        mode === 'africa' ? card('A title awaiting a decision', null, h('p', { class: 'notice', style: 'margin:0', text: tf('The African structure document names the WNNN country leader Country Lead. The diaspora document says Country Representative for both networks. WDOS shows {1} for Africa until management records its choice on the System rules page. Neither source has been replaced.', d.wnnn_country_title) }))
          : card('Example: United States', 'A city leader may report straight to the Country Representative where no state layer is needed.', h('div', { class: 'tiers' }, d.example.flatMap((x, i) => [i ? h('div', { class: 'tiers__arrow', 'aria-hidden': 'true', text: '↓' }) : null, h('div', { class: 'tier' }, h('b', { text: x }))]))),
        card('In WDOS today', null, h('div', { class: 'jcount', style: 'grid-template-columns:repeat(2,minmax(0,1fr))' }, [['Leadership seats held', d.counts.seats], ['He-for-She Champions', d.counts.he_for_she], ['Acting officers', d.counts.acting], ['Campus Ambassadors', d.counts.campus]].map(([l, n]) => h('div', {}, h('b', { class: 'num', text: fmt(n) }), h('small', { text: l })))),
          app.user.is_hq || app.user.is_leader ? h('a', { class: 'btn btn--ghost btn--s', href: '#/structure', text: 'Who holds each seat' }) : null))));
  };
  paint();
}
