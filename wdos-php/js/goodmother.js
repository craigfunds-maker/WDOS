/* The WODDI Good Mother Network map: what "Good Mother" is called across Africa.
 * The phrases are taken from the Founder's map artwork exactly as written there. The language named beside each
 * phrase is our reading of it and is shown small, so it can be corrected without touching the phrase.
 * Country shapes come from public geography data (assets/geo/africa.json). */
import { h } from './ui.js';

export const GOOD_MOTHER = {
  MA: ['Morocco', [['Umm mzyana', 'Arabic'], ['Yemma yelhan', 'Tamazight']]],
  DZ: ['Algeria', [['Umm mliha', 'Arabic'], ['Yemma yelhan', 'Tamazight']]],
  LY: ['Libya', [['Umm tayyiba', 'Arabic']]],
  EG: ['Egypt', [['Umm kwayyisa', 'Arabic'], ['Een masil', 'Nubian']]],
  MR: ['Mauritania', [['Umm saliha', 'Arabic'], ['Yaaye moyyo', 'Pulaar']]],
  ML: ['Mali', [['Denba ɲuman', 'Bambara'], ['Neene moyyo', 'Fulfulde'], ['Nya hanno', 'Songhai']]],
  NE: ['Niger', [['Uwa nagari', 'Hausa'], ['Nya hanno', 'Zarma'], ['Anna tolaghat', 'Tamasheq']]],
  TD: ['Chad', [['Umm saliha', 'Arabic'], ['Ya ngəla', 'Kanuri']]],
  SD: ['Sudan', [['Umm saliha', 'Arabic'], ['Een masil', 'Nubian']]],
  SN: ['Senegal', [['Yaay bu baax', 'Wolof']]],
  BF: ['Burkina Faso', [['Ma sóma', 'Mooré']]],
  GN: ['Guinea', [['Neene moyyo', 'Pular']]],
  CI: ["Côte d'Ivoire", [['Ba ɲuman', 'Dioula']]],
  GH: ['Ghana', [['Maame pa', 'Twi']]],
  NG: ['Nigeria', [['Uwa nagari', 'Hausa'], ['Ìyá rere', 'Yorùbá'], ['Nneoma', 'Igbo']]],
  CM: ['Cameroon', [['Bonne mère', 'French']]],
  CF: ['Central African Republic', [['Mama nzoni', 'Sango']]],
  SS: ['South Sudan', [['Mama kwes', 'Juba Arabic']]],
  ET: ['Ethiopia', [['Haadha gaarii', 'Afaan Oromoo'], ['Bego enat', 'Amharic']]],
  SO: ['Somalia', [['Hooyo wanaagsan', 'Somali']]],
  GA: ['Gabon', [['Bonne mère', 'French']]],
  CD: ['DR Congo', [['Mayo musuma', 'Bemba'], ['Mama malamu', 'Lingala']]],
  RW: ['Rwanda', [['Umubyeyi mwiza', 'Kinyarwanda']]],
  KE: ['Kenya', [['Mama mwema', 'Kiswahili']]],
  TZ: ['Tanzania', [['Mama mwema', 'Kiswahili']]],
  AO: ['Angola', [['Mama yiwa', 'Umbundu'], ['Mama wambote', 'Kikongo']]],
  ZM: ['Zambia', [['Mayo musuma', 'Bemba']]],
  MW: ['Malawi', [['Mayi wabwino', 'Chichewa']]],
  MZ: ['Mozambique', [['Mãe boa', 'Portuguese']]],
  ZW: ['Zimbabwe', [['Amai vakanaka', 'Shona']]],
  NA: ['Namibia', [['Meme omwaanawa', 'Oshiwambo']]],
  BW: ['Botswana', [['Mme yo molemo', 'Setswana']]],
  ZA: ['South Africa', [['Umama omuhle', 'isiZulu'], ['Umama olungileyo', 'isiXhosa'], ['Goeie ma', 'Afrikaans']]],
  MG: ['Madagascar', [['Reny tsara', 'Malagasy']]],
};
const OTHER = { TN: 'Tunisia', BJ: 'Benin', BI: 'Burundi', CV: 'Cabo Verde', KM: 'Comoros', CG: 'Congo', DJ: 'Djibouti', GQ: 'Equatorial Guinea', ER: 'Eritrea', SZ: 'Eswatini', GM: 'Gambia', GW: 'Guinea-Bissau', LS: 'Lesotho', LR: 'Liberia', MU: 'Mauritius', ST: 'São Tomé and Príncipe', SC: 'Seychelles', SL: 'Sierra Leone', TG: 'Togo', UG: 'Uganda' };
export const PLAQUE = 'This map represents the WODDI Good Mother Network across Africa, capturing the meaning of “Good Mother” in diverse languages and dialects across the continent, ensuring that every woman, regardless of local culture or background, lives up to the identity of her calling A Good Mother.';

const NS = 'http://www.w3.org/2000/svg';
const s = (tag, attrs = {}, ...kids) => { const n = document.createElementNS(NS, tag); for (const [k, v] of Object.entries(attrs)) if (v !== null && v !== undefined) n.setAttribute(k, v); n.append(...kids); return n; };
const TONES = [['#F4C766', '#2A1708'], ['#FBE3A4', '#2A1708'], ['#8C4A22', '#FFF6E0'], ['#2B1A15', '#FFF6E0']];
let shapes = null;
const loadShapes = () => shapes || (shapes = fetch('/assets/geo/africa.json').then((r) => { if (!r.ok) throw new Error('map'); return r.json(); }).catch((e) => { shapes = null; throw e; }));
const nameOf = (code) => (GOOD_MOTHER[code] ? GOOD_MOTHER[code][0] : OTHER[code] || '');

/* The whole continent. Large countries carry their phrases on the map; every country answers a tap. */
function drawAfrica(M, pick, highlight) {
  const svg = s('svg', { viewBox: `0 0 ${M.w} ${M.h}`, class: 'gm__svg', role: 'img', 'aria-label': 'Map of Africa showing what Good Mother is called in each country' });
  const land = s('g'), labels = s('g', { 'pointer-events': 'none' }); let on = null;
  for (const [code, c] of Object.entries(M.c)) {
    const real = !code.startsWith('_'); const fill = real ? TONES[c.t][0] : code === '_SOL' && M.c.SO ? TONES[M.c.SO.t][0] : '#6b5645';
    const p = s('path', { d: c.d, fill, class: 'gm__c' + (code === highlight ? ' is-on' : '') + (real ? '' : ' gm__c--plain'), tabindex: real ? '0' : null, 'aria-label': real ? nameOf(code) : null });
    if (real) { const go = () => { if (on) on.classList.remove('is-on'); on = p; p.classList.add('is-on'); land.append(p); pick(code); };
      p.addEventListener('click', go); p.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } }); if (code === highlight) on = p; }
    land.append(p);
    const g = GOOD_MOTHER[code];
    if (real && g) {
      const lines = [g[0], ...g[1].map((x) => x[0])]; const longest = Math.max(...lines.map((x) => x.length)); const size = Math.sqrt(c.a);
      const fs = Math.min(18, size / (lines.length * 1.55), (size * 1.5) / longest); if (fs < 7.2) continue;
      const lh = fs * 1.16; const y0 = c.p[1] - ((lines.length - 1) * lh) / 2;
      lines.forEach((line, i) => labels.append(s('text', { x: c.p[0], y: y0 + i * lh, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': (i ? fs : fs * 0.9).toFixed(1), 'font-weight': i ? 700 : 600, fill: TONES[c.t][1], stroke: TONES[c.t][0], 'stroke-width': (fs / 4.5).toFixed(1), 'paint-order': 'stroke', 'stroke-linejoin': 'round' }, line)));
    }
  }
  if (on) land.append(on);
  svg.append(land, labels);
  return svg;
}

/** The map as it stands on the WGMN joining form. */
export function africaMap(highlight) {
  const stage = h('div', { class: 'gm__stage' }, h('div', { class: 'gm__wait', text: 'Drawing the map…' }));
  const cap = h('div', { class: 'gm__cap', 'aria-live': 'polite' });
  const show = (code) => { const g = GOOD_MOTHER[code];
    cap.replaceChildren(h('b', { text: nameOf(code) }), ...(g ? g[1].map(([p, l]) => h('span', { class: 'gm__ph' }, p, h('small', { text: l }))) : [h('span', { class: 'gm__none', text: 'The map does not carry a phrase for this country yet.' })])); };
  cap.append(h('span', { class: 'gm__none', text: 'Tap a country to read what a Good Mother is called there.' }));
  loadShapes().then((M) => { stage.replaceChildren(drawAfrica(M, show, highlight)); if (highlight) show(highlight); }).catch(() => stage.replaceChildren(h('div', { class: 'gm__wait', text: 'The map did not load. The form below still works.' })));
  return h('figure', { class: 'gm' },
    h('div', { class: 'gm__head' }, h('img', { src: '/assets/logos/woddi.png', alt: 'WODDI, The Nurturer' }), h('div', { class: 'gm__title', text: 'WODDI Good Mother Network' }), h('img', { class: 'gm__disc', src: '/assets/logos/wgmn-disc.png', alt: '' })),
    h('div', { class: 'gm__body' }, h('div', {}, stage, cap), h('figcaption', { class: 'gm__plaque' }, h('span', { 'aria-hidden': 'true', text: '❦' }), h('p', { text: PLAQUE }), h('span', { 'aria-hidden': 'true', text: '❦' }))));
}

/** The member's own country, for the dashboard: its shape and what a Good Mother is called in its major languages. */
export function goodMotherCard(code, countryName) {
  const g = GOOD_MOTHER[code]; const name = countryName || nameOf(code);
  const art = h('div', { class: 'gmc__art' });
  loadShapes().then((M) => {
    const c = M.c[code]; if (!c) return;
    if (g) { const pad = 14, w = c.b[2] - c.b[0], ht = c.b[3] - c.b[1];
      art.replaceChildren(s('svg', { viewBox: `${c.b[0] - pad} ${c.b[1] - pad} ${w + pad * 2} ${ht + pad * 2}`, class: 'gmc__svg', role: 'img', 'aria-label': 'Map of ' + name }, s('path', { d: c.d, fill: '#F4C766', stroke: '#8C4A22', 'stroke-width': Math.max(1.2, w / 140), 'stroke-linejoin': 'round' })));
    } else art.replaceChildren(drawAfrica(M, () => {}, code));
  }).catch(() => {});
  return h('section', { class: 'card gmc' }, art,
    h('div', { class: 'gmc__text' }, h('span', { class: 'gmc__kicker', text: 'WODDI Good Mother Network' }), h('h2', { class: 'display', text: g ? `A Good Mother in ${name}` : 'A Good Mother across Africa' }),
      g ? h('p', { class: 'muted', text: `What a Good Mother is called in the major languages of ${name}.` }) : null,
      g ? h('div', { class: 'gmc__list' }, g[1].map(([p, l]) => h('div', { class: 'gmc__ph' }, h('b', { text: p }), h('small', { text: l })))) : h('p', { class: 'muted', text: PLAQUE })));
}
