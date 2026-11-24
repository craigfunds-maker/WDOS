/* v109.53: meeting times in each person's own time (Azeez, 24 Nov 2026).
 * Times are kept in UTC. On screen they are written in the phone's own time zone with the zone named ("10:00 WAT"), so a leader
 * in Kenya reads Kenya time. The person scheduling chooses which time zone she typed the time in; Nigeria (WODDI HQ) is first. */
import { api } from './api.js';
import { t } from './ui.js';

export const myTz = () => { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Lagos'; } catch { return 'Africa/Lagos'; } };
const loc = () => document.documentElement.lang || 'en';

/** Minutes a time zone is ahead of UTC at that moment. */
export function tzOffset(date, tz) {
  const p = Object.fromEntries(new Intl.DateTimeFormat('en-US', { timeZone: tz, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' })
    .formatToParts(date).filter((x) => x.type !== 'literal').map((x) => [x.type, +x.value]));
  return (Date.UTC(p.year, p.month - 1, p.day, p.hour % 24, p.minute, p.second) - Math.floor(date.getTime() / 1000) * 1000) / 60000;
}
/** "2026-11-27T10:00" typed as time in tz, as a Date. Two passes, so a change of clocks on that day is handled. */
export function zonedToUtc(value, tz) {
  const [d, tm] = value.split('T'); const [y, mo, da] = d.split('-').map(Number); const [hh, mi] = tm.split(':').map(Number);
  const guess = Date.UTC(y, mo - 1, da, hh, mi);
  let out = guess - tzOffset(new Date(guess), tz) * 60000; out = guess - tzOffset(new Date(out), tz) * 60000;
  return new Date(out);
}
/** The same moment as a datetime-local value in tz. */
export function utcToZoned(date, tz) { return new Date(date.getTime() + tzOffset(date, tz) * 60000).toISOString().slice(0, 16); }
/** "UTC+1", "UTC+5:30", "UTC" */
export function offsetText(date, tz) { const m = tzOffset(date, tz); if (!m) return 'UTC'; const a = Math.abs(m); return 'UTC' + (m > 0 ? '+' : '-') + Math.floor(a / 60) + (a % 60 ? ':' + String(a % 60).padStart(2, '0') : ''); }
/* Short names for the African zones (the same ones the server writes: WAT, CAT, EAT), because many phones only know "GMT+1". */
const SAST = ['Africa/Johannesburg', 'Africa/Maseru', 'Africa/Mbabane']; const EET = ['Africa/Cairo', 'Africa/Tripoli'];
function zoneAbbr(date, tz) {
  for (const l of ['en-US', 'en-GB']) {     // en-US knows EST and PST, en-GB knows GMT and BST
    const z = new Intl.DateTimeFormat(l, { timeZone: tz, timeZoneName: 'short' }).formatToParts(date).find((x) => x.type === 'timeZoneName');
    if (z && /^[A-Z]{2,5}$/.test(z.value)) return z.value;
  }
  if (!/^Africa\//.test(tz)) return '';
  const m = tzOffset(date, tz);
  return m === 0 ? 'GMT' : m === 60 ? 'WAT' : m === 120 ? (SAST.includes(tz) ? 'SAST' : EET.includes(tz) ? 'EET' : 'CAT') : m === 180 ? 'EAT' : '';
}
/** "10:00 WAT (UTC+1)", the same way the server writes it; "14:30 UTC+5:30" where a zone has no short name. */
export function timeIn(date, tz, opts = {}) {
  const zone = tz || myTz(); const ab = zoneAbbr(date, zone); const off = offsetText(date, zone);
  return date.toLocaleTimeString(loc(), { hour: '2-digit', minute: '2-digit', timeZone: zone }) + (opts.zone === false ? '' : ' ' + (ab ? `${ab} (${off})` : off));
}
/** "Thu, 27 Nov 2026, 10:00 to 11:00 WAT (your time)" in the phone's own zone. */
export function whenMine(a, b) {
  const x = a instanceof Date ? a : new Date(String(a).replace(' ', 'T') + 'Z'); const y = b ? (b instanceof Date ? b : new Date(String(b).replace(' ', 'T') + 'Z')) : null;
  return x.toLocaleDateString(loc(), { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) + ', ' + (y ? timeIn(x, null, { zone: false }) + ' ' + t('to') + ' ' + timeIn(y, null) : timeIn(x, null)) + ', ' + t('your time');
}
/** The time zones offered when scheduling: Nigeria first, the phone's own zone, then the WODDI countries and the diaspora. */
export const TZ_CHOICES = [['Africa/Lagos', 'Nigeria'], ['Africa/Accra', 'Ghana'], ['Africa/Douala', 'Cameroon'], ['Africa/Nairobi', 'Kenya, Uganda, Tanzania'], ['Africa/Johannesburg', 'South Africa, Malawi, Zambia, Zimbabwe'],
  ['Africa/Cairo', 'Egypt'], ['Africa/Kinshasa', 'DR Congo (Kinshasa)'], ['Europe/London', 'United Kingdom'], ['Europe/Paris', 'France'], ['America/New_York', 'USA (New York)'], ['America/Chicago', 'USA (Texas)'],
  ['America/Los_Angeles', 'USA (California)'], ['America/Toronto', 'Canada (Toronto)'], ['Asia/Dubai', 'United Arab Emirates'], ['Australia/Sydney', 'Australia (Sydney)']];
/** A few places to show how a time reads elsewhere. */
export const TZ_PREVIEW = [['Africa/Lagos', 'Nigeria'], ['Africa/Accra', 'Ghana'], ['Africa/Nairobi', 'Kenya'], ['Africa/Johannesburg', 'South Africa'], ['Europe/London', 'UK'], ['America/New_York', 'New York']];

/** Tell WDOS which time zone this phone is in, so emails and notifications show times in her own time. Once a day at most. */
export function reportTz() {
  const tz = myTz(); let last = ''; try { last = localStorage.getItem('wdos.tz') || ''; } catch { /* */ }
  const today = new Date().toISOString().slice(0, 10);
  if (last === tz + '|' + today) return;
  api('/me/tz', { tz }).then(() => { try { localStorage.setItem('wdos.tz', tz + '|' + today); } catch { /* */ } }).catch(() => {});
}
