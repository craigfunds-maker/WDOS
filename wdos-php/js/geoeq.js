/* v109.5: what "Community cluster" and "Booth" are called where she lives. The WODDI community cluster is the ward,
 * or its equivalent, below the LGA or district; the booth is the polling unit or polling station inside it.
 * Nigeria's wards and polling units come from the INEC register (assets/geo/ng). Elsewhere she types her own,
 * and this hint tells her what to type. Terms follow each country's electoral or local-government administration. */
const FR = ['commune, quartier or village', 'bureau de vote'], PT = ['bairro or comuna', 'assembleia de voto'];
export const GEO_EQ = {
  NG: ['ward (INEC registration area)', 'polling unit'], GH: ['electoral area', 'polling station'], KE: ['ward', 'polling station'],
  UG: ['parish or ward', 'polling station'], TZ: ['ward (kata)', 'polling station'], ZA: ['municipal ward', 'voting station'],
  ZM: ['ward', 'polling station'], ZW: ['ward', 'polling station'], MW: ['ward', 'polling centre'], RW: ['cell (akagari)', 'polling station'],
  BI: ['colline or quartier', 'bureau de vote'], SL: ['ward', 'polling centre'], LR: ['community within the electoral district', 'polling place'],
  GM: ['ward', 'polling station'], BW: ['ward', 'polling station'], NA: ['locality within the constituency', 'polling station'],
  LS: ['community council area', 'polling station'], SZ: ['umphakatsi (chiefdom) within the inkhundla', 'polling station'],
  ET: ['kebele', 'polling station'], ER: ['kebabi (village administration)', 'polling station'], SO: ['village or neighbourhood', 'polling station'],
  SD: ['administrative unit', 'polling centre'], SS: ['boma or payam', 'polling centre'], EG: ['shiakha (village or neighbourhood)', 'polling station'],
  SC: ['district', 'polling station'], MU: ['village or municipal ward', 'polling station'], GQ: ['consejo de poblado', 'mesa electoral'],
  CD: ['quartier or groupement', 'bureau de vote'], CM: ['commune or quartier', 'bureau de vote'],
  SN: FR, CI: FR, BJ: FR, TG: FR, BF: FR, ML: FR, NE: FR, GN: FR, TD: FR, CF: FR, CG: FR, GA: FR, MG: ['fokontany', 'bureau de vote'], DJ: FR, KM: FR, MR: FR,
  MA: ['commune or arrondissement', 'bureau de vote'], DZ: ['commune', 'bureau de vote'], TN: ['imada (sector)', 'bureau de vote'], LY: ['mahalla (branch municipality)', 'polling station'],
  AO: PT, MZ: ['bairro or localidade', 'posto de votação'], GW: ['sector or tabanca', 'assembleia de voto'], CV: ['freguesia or zona', 'assembleia de voto'], ST: PT,
  US: ['neighbourhood or voting precinct', 'polling place'], GB: ['electoral ward', 'polling station'], IE: ['electoral division', 'polling station'],
  CA: ['ward or polling division', 'polling station'], AU: ['suburb or ward', 'polling place'], NZ: ['suburb or ward', 'voting place'],
  FR: ['commune or quartier', 'bureau de vote'], BE: ['commune', 'bureau de vote'], CH: ['commune or Gemeinde', 'bureau de vote'], LU: ['commune', 'bureau de vote'],
  DE: ['Stadtteil or Gemeinde', 'Wahllokal'], AT: ['Gemeinde or Bezirk', 'Wahllokal'], NL: ['wijk (neighbourhood)', 'stembureau'],
  IT: ['quartiere or comune', 'seggio elettorale'], ES: ['barrio or municipio', 'mesa electoral'], PT: ['freguesia', 'assembleia de voto'], BR: ['bairro', 'seção eleitoral'],
  SE: ['valdistrikt (voting district)', 'vallokal'], NO: ['bydel or kommune', 'valglokale'], DK: ['afstemningsområde (polling district)', 'valgsted'], FI: ['äänestysalue (voting district)', 'äänestyspaikka'],
  AE: ['community or district', 'local centre'], SA: ['district (hayy)', 'local centre'], QA: ['zone', 'local centre'], KW: ['area', 'local centre'],
  IN: ['ward or panchayat', 'polling booth'], CN: ['community (shequ)', 'local centre'], JM: ['electoral division', 'polling station'], TT: ['electoral district', 'polling station'],
};
const DEFAULT = ['community or neighbourhood', 'polling station or local centre'];
export const geoEq = (code) => GEO_EQ[code] || DEFAULT;
export const geoSlug = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
