/* The WODDI Good Mother Network map: what "Good Mother" is called across Africa.
 * The phrases are taken from the Founder's map artwork exactly as written there. The language named beside each
 * phrase is our reading of it and is shown small, so it can be corrected without touching the phrase.
 * Country shapes come from public geography data (assets/geo/africa.json). */
import { h, t, i18n } from './ui.js';

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
/* WODDI brand: lemon green and magenta, each in two strengths so neighbouring countries stay distinct */
const TONES = [['#7CB518', '#12210A'], ['#CDEB8B', '#12210A'], ['#D4006A', '#FFFFFF'], ['#8A0046', '#FFFFFF']];
let shapes = null;
const loadShapes = () => shapes || (shapes = fetch('/assets/geo/africa.json').then((r) => { if (!r.ok) throw new Error('map'); return r.json(); }).catch((e) => { shapes = null; throw e; }));
const nameOf = (code) => (GOOD_MOTHER[code] ? GOOD_MOTHER[code][0] : OTHER[code] || '');

/* The whole continent. Large countries carry their phrases on the map; every country answers a tap. */
function drawAfrica(M, pick, highlight) {
  const svg = s('svg', { viewBox: `0 0 ${M.w} ${M.h}`, class: 'gm__svg', role: 'img', 'aria-label': 'Map of Africa showing what Good Mother is called in each country' });
  const land = s('g'), labels = s('g', { 'pointer-events': 'none' }); let on = null;
  for (const [code, c] of Object.entries(M.c)) {
    const real = !code.startsWith('_'); const fill = real ? TONES[c.t][0] : code === '_SOL' && M.c.SO ? TONES[M.c.SO.t][0] : '#5d5560';
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

/** The member's own country, for the dashboard: its shape, with what a Good Mother is called in its major languages written inside it. */
export function goodMotherCard(code, givenName) {
  const g = GOOD_MOTHER[code] || POSTERS.has(code); const name = countryName(code, givenName); const art = goodMotherArt(code, givenName);
  return h('section', { class: 'card gmc' + (g ? '' : ' gmc--africa') + (POSTERS.has(code) ? ' gmc--poster' : '') }, art,
    h('div', { class: 'gmc__text' }, h('span', { class: 'gmc__kicker', text: 'WODDI Good Mother Network' }), h('h2', { class: 'display' }, g ? tf('A Good Mother in {country}', { country: name }) : t('A Good Mother across Africa')),
      h('p', { class: 'muted' }, POSTERS.has(code) ? tf('What a Good Mother is called across the regions and languages of {country}. Select the map to read it at full size.', { country: name }) : g ? tf('What a Good Mother is called in the major languages of {country}.', { country: name }) : t(PLAQUE))),
    g ? countryPlaque(code, givenName) : null);
}

/* Countries with a finished WODDI Good Mother country map (in assets/goodmother): all 55 in Africa and 130 in the diaspora.
   38 African maps are the Founder's artwork; 17 were drawn in the same style on 12 Nov 2026 (afmaps/gen.py) and their phrases await a native reviewer.
   Mauritania's regions come from Natural Earth; the other 16 from geoBoundaries. */
const POSTERS_AFRICA = new Set(["AO", "BF", "BI", "BJ", "BW", "CD", "CF", "CG", "CI", "CM", "CV", "DJ", "DZ", "EG", "EH", "ER", "ET", "GA", "GH", "GM", "GN", "GQ", "GW", "KE", "KM", "LR", "LS", "LY", "MA", "MG", "ML", "MR", "MU", "MW", "MZ", "NA", "NE", "NG", "RW", "SC", "SD", "SL", "SN", "SO", "SS", "ST", "SZ", "TD", "TG", "TN", "TZ", "UG", "ZA", "ZM", "ZW"]);
const POSTERS_DIASPORA = new Set(["AD", "AE", "AF", "AG", "AL", "AM", "AR", "AT", "AU", "AZ", "BA", "BB", "BD", "BE", "BG", "BH", "BN", "BO", "BR", "BS", "BT", "BY", "BZ", "CA", "CH", "CL", "CN", "CO", "CR", "CU", "CY", "CZ", "DE", "DK", "DM", "DO", "EC", "EE", "ES", "FI", "FJ", "FR", "GB", "GD", "GE", "GR", "GT", "GY", "HN", "HR", "HT", "HU", "ID", "IE", "IL", "IN", "IQ", "IR", "IS", "IT", "JM", "JO", "JP", "KG", "KH", "KN", "KP", "KR", "KW", "KZ", "LA", "LB", "LC", "LI", "LK", "LT", "LU", "LV", "MC", "MD", "ME", "MK", "MM", "MN", "MT", "MV", "MX", "MY", "NI", "NL", "NO", "NP", "NZ", "OM", "PA", "PE", "PG", "PH", "PK", "PL", "PT", "PY", "QA", "RO", "RS", "RU", "SA", "SE", "SG", "SI", "SK", "SM", "SR", "SV", "SY", "TH", "TJ", "TL", "TM", "TR", "TT", "UA", "US", "UY", "UZ", "VC", "VE", "VN", "VU", "YE"]);
const POSTERS = new Set([...POSTERS_AFRICA, ...POSTERS_DIASPORA]);
export const hasGoodMotherMap = (code) => POSTERS.has(code) || !!GOOD_MOTHER[code] || !!OTHER[code];
const tf = (s, vars) => t(s).replace(/\{(\w+)\}/g, (m, k2) => vars[k2] ?? m);
/** The country's name in the language she chose, where the phone knows it; otherwise the name WDOS holds. */
function countryName(code, fallback) { try { const n = new Intl.DisplayNames([i18n.lang || 'en'], { type: 'region' }).of(code); if (n && n !== code) return n; } catch { /* older phone */ } return fallback || nameOf(code); }
/* The plaque. It used to be drawn inside each picture, in a different corner from map to map and in English only.
   It now stands under every map, in the same place and the same style, in the language she chose. */
const PLAQUE_COUNTRY = 'This map represents the WODDI Good Mother Network across {country}, capturing the meaning of “Good Mother” in diverse languages and dialects across the country. It reflects our commitment to ensuring that every woman, regardless of culture or background, lives out the full essence of her identity. A Good Mother.';
const PLAQUE_DIASPORA = 'This map represents the WODDI Good Mother Network in the diaspora across {country}, capturing the meaning of “Good Mother” in the diverse languages and dialects of the country. It reflects our commitment to ensuring that every woman, regardless of culture or background, lives out the full essence of her identity. A Good Mother.';
export function countryPlaque(code, fallbackName) {
  return h('figcaption', { class: 'gmplaque' }, h('span', { 'aria-hidden': 'true', text: '❦' }), h('p', {}, tf(POSTERS_DIASPORA.has(code) ? PLAQUE_DIASPORA : PLAQUE_COUNTRY, { country: countryName(code, fallbackName) })), h('span', { 'aria-hidden': 'true', text: '❦' }));
}
function openPoster(code, name) {
  const close = () => { box.remove(); document.removeEventListener('keydown', esc); }; const esc = (e) => { if (e.key === 'Escape') close(); };
  const box = h('div', { class: 'gmview', role: 'dialog', 'aria-modal': 'true', 'aria-label': tf('WODDI Good Mother Network map of {1}', name), onclick: (e) => { if (e.target === box) close(); } },
    h('button', { class: 'gmview__x', type: 'button', 'aria-label': 'Close', onclick: close }, '×'),
    h('figure', { class: 'gmview__in' }, h('img', { src: `/assets/goodmother/${code}.webp`, alt: tf('WODDI Good Mother Network map of {1}, showing what a Good Mother is called in its regions and languages', name) }), countryPlaque(code, name)));
  document.addEventListener('keydown', esc); document.body.append(box); box.querySelector('button').focus();
}

/** Only the picture: the country's shape with the Good Mother names inside it. Used in the leader dashboard header. */
export function goodMotherArt(code, countryName) {
  const g = GOOD_MOTHER[code]; const name = countryName || nameOf(code);
  if (POSTERS.has(code)) return h('div', { class: 'gmc__art' }, h('button', { class: 'gmposter', type: 'button', title: 'Open the map at full size', onclick: () => openPoster(code, name) },
    h('img', { src: `/assets/goodmother/${code}-s.webp`, width: 480, height: 625, loading: 'lazy', alt: tf('WODDI Good Mother Network map of {1}', name) })));
  const art = h('div', { class: 'gmc__art' });
  loadShapes().then((M) => {
    const c = M.c[code]; if (!c) return;
    if (!g) { art.replaceChildren(drawAfrica(M, () => {}, code)); return; }
    const w = c.b[2] - c.b[0], ht = c.b[3] - c.b[1], pad = Math.max(w, ht) * 0.05;
    // the picture is the country's own box, widened when the country is narrow so the words keep a readable size
    const vw = Math.max(w, ht * 0.9) + pad * 2, vh = ht + pad * 2, vx = c.p[0] - vw / 2 < c.b[0] - pad ? Math.min(c.b[0] - pad, c.p[0] - vw / 2) : c.b[0] - pad - (vw - w - pad * 2) / 2, vy = c.b[1] - pad;
    const lines = g[1]; const longest = Math.max(...lines.map(([p, l]) => p.length + l.length * 0.55 + 3));
    const fit = Math.min(vw / 9, (Math.min(w, vw * 0.8) * 1.7) / longest, ht / (lines.length * 1.9));
    const fs = Math.min((vw * 1.7) / longest, Math.max(fit, vw / 15)); /* a narrow country still gets words large enough to read */ const lh = fs * 1.5; const y0 = c.p[1] - ((lines.length - 1) * lh) / 2;
    const svg = s('svg', { viewBox: `${vx} ${vy} ${vw} ${vh}`, class: 'gmc__svg', role: 'img', 'aria-label': tf('Map of {1}: ', name) + lines.map(([p, l]) => `${p} (${l})`).join(', ') },
      s('path', { d: c.d, fill: '#7CB518', stroke: '#D4006A', 'stroke-width': vw / 160, 'stroke-linejoin': 'round' }));
    lines.forEach(([p, l], i) => { const t = s('text', { x: c.p[0], y: y0 + i * lh, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': fs.toFixed(2), 'font-weight': 800, fill: '#12210A', stroke: '#7CB518', 'stroke-width': (fs / 3.2).toFixed(2), 'paint-order': 'stroke', 'stroke-linejoin': 'round' }, p);
      t.append(s('tspan', { 'font-size': (fs * 0.56).toFixed(2), 'font-weight': 600, fill: '#1F3A0C', dx: (fs * 0.35).toFixed(2) }, l)); svg.append(t); });
    art.replaceChildren(svg);
  }).catch(() => { if (g) art.replaceChildren(h('div', { class: 'gmc__list' }, g[1].map(([p, l]) => h('div', { class: 'gmc__ph' }, h('b', { text: p }), h('small', { text: l }))))); });
  return art;
}
