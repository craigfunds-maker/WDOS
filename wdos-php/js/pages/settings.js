/* Settings: profile and photograph, password, fingerprint devices, notifications on this device and per type, theme. */
import { h, icon, avatar, card, sk, toast, agoIn, theme, langPicker, chime, soundOn } from '../ui.js';
import { api, squarePhoto, fingerprintSupported, fingerprintEnroll, deviceLabel, pushState, pushEnable, pushDisable } from '../api.js';
import { app, refreshUser, setLanguage } from '../app.js';
import { sendTest } from '../pushnudge.js';
import { isIos, isInstalled } from '../api.js';

const sw = (checked, onchange, label) => { const i = h('input', { type: 'checkbox', 'aria-label': label }); i.checked = !!checked; i.addEventListener('change', () => onchange(i.checked, i)); return h('label', { class: 'switch' }, i, h('i')); };

export async function render(root) {
  const u = app.user;
  /* profile */
  const pic = h('div', {}, avatar(u, 84)); const file = h('input', { type: 'file', accept: 'image/*', hidden: true });
  file.addEventListener('change', async () => { if (!file.files[0]) return; try { const data = await squarePhoto(file.files[0]); const r = await api('/me/photo', { data }); await refreshUser(); app.user._v = r.v; pic.replaceChildren(avatar(app.user, 84)); toast('Photograph saved', '', 'ok'); } catch (e) { toast('Photograph not saved', e.message, 'bad'); } file.value = ''; });
  const f = { first_name: h('input', { class: 'input', id: 's-fn', value: u.first_name, maxlength: 80 }), last_name: h('input', { class: 'input', id: 's-ln', value: u.last_name, maxlength: 80 }), phone: h('input', { class: 'input', id: 's-ph', type: 'tel', value: u.phone, maxlength: 24 }), lga: h('input', { class: 'input', id: 's-lga', value: u.lga, maxlength: 120 }), community: h('input', { class: 'input', id: 's-com', value: u.community, maxlength: 120 }), ward: h('input', { class: 'input', id: 's-ward', value: u.ward || '', maxlength: 120 }) };
  const pBtn = h('button', { class: 'btn', type: 'submit', text: 'Save changes' });
  const pForm = h('form', { style: 'display:grid;gap:16px' }, h('div', { class: 'photo' }, pic, h('div', {}, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => file.click() }, icon('camera', 16), u.has_photo ? 'Change photograph' : 'Add photograph'), h('div', { class: 'faint', style: 'font-size:13px;margin-top:6px', text: 'A clear photo of your face. It is cropped to a square.' })), file),
    h('div', { class: 'grid2' }, h('label', { class: 'field', for: 's-fn' }, h('span', { text: 'First name' }), f.first_name), h('label', { class: 'field', for: 's-ln' }, h('span', { text: 'Surname' }), f.last_name), h('label', { class: 'field', for: 's-ph' }, h('span', { text: 'Phone' }), f.phone), h('label', { class: 'field' }, h('span', { text: 'Email' }), h('input', { class: 'input', value: u.email, disabled: true })),
      u.is_hq && !u.network ? null : h('label', { class: 'field', for: 's-lga' }, h('span', { text: u.diaspora ? 'City' : 'LGA or district' }), f.lga), u.is_hq && !u.network ? null : h('label', { class: 'field', for: 's-com' }, h('span', { text: u.diaspora ? 'Neighbourhood (optional)' : 'Community or cluster' }), f.community), u.is_hq && !u.network || u.diaspora ? null : h('label', { class: 'field', for: 's-ward' }, h('span', { text: 'Official ward (optional)' }), f.ward, h('small', { text: 'Only if it is different from your WODDI community.' })), h('div', { class: 'field' }, h('span', { text: 'WODDI Person ID' }), h('div', { class: 'input num', style: 'display:flex;align-items:center', text: u.person_id || '' }), h('small', { text: 'One number for you across WDOS, the Community and the Institute.' }))),
    h('div', {}, pBtn));
  pForm.addEventListener('submit', async (e) => { e.preventDefault(); pBtn.disabled = true; try { await api('/me', Object.fromEntries(Object.entries(f).map(([k, v]) => [k, v.value]))); await refreshUser(); toast('Changes saved', '', 'ok'); } catch (ex) { toast('Not saved', ex.message, 'bad'); } pBtn.disabled = false; });

  /* password */
  const cur = h('input', { class: 'input', id: 's-cur', type: 'password', autocomplete: 'current-password' }), nw = h('input', { class: 'input', id: 's-new', type: 'password', autocomplete: 'new-password', minlength: 6 });
  const pwMsg = h('p', { class: 'err', role: 'alert' }); const pwBtn = h('button', { class: 'btn btn--quiet', type: 'submit', text: u.has_password ? 'Change password' : 'Set a password' });
  const pwForm = h('form', { style: 'display:grid;gap:14px' }, u.must_change_password ? h('p', { class: 'okmsg', text: 'You signed in with a temporary password from HQ. Choose your own now.' }) : null,
    u.has_password ? h('label', { class: 'field', for: 's-cur' }, h('span', { text: u.must_change_password ? 'Temporary password' : 'Current password' }), cur) : h('p', { class: 'muted', style: 'font-size:14px', text: 'You joined with Google. Setting a password lets you also sign in with your email.' }),
    h('label', { class: 'field', for: 's-new' }, h('span', { text: 'New password' }), nw, h('small', { text: 'At least 6 letters or numbers. If you forget it, sign in with your fingerprint and choose a new one here; the current one is then not needed.' })), pwMsg, h('div', {}, pwBtn));
  pwForm.addEventListener('submit', async (e) => { e.preventDefault(); pwMsg.textContent = ''; pwBtn.disabled = true; try { await api('/me/password', { current_password: cur.value, new_password: nw.value }); cur.value = nw.value = ''; await refreshUser(); toast('Password changed', '', 'ok'); } catch (ex) { pwMsg.textContent = ex.message; } pwBtn.disabled = false; });

  /* fingerprint */
  const fpList = h('div', {}, sk.rows(1)); const fpMsg = h('p', { class: 'err', role: 'alert' });
  const loadFp = async () => { const r = await api('/webauthn/credentials').catch(() => ({ credentials: [] })); fpList.replaceChildren(r.credentials.length ? h('div', { class: 'list' }, r.credentials.map((c) => h('div', {}, h('div', { class: 'nrow__ico' }, icon('fingerprint', 18)), h('div', { class: 'grow' }, h('b', { text: c.label }), h('small', { text: 'Added ' + agoIn(c.created_at) + (c.last_used_at ? ', last used ' + agoIn(c.last_used_at) : ', not used yet') })), h('button', { class: 'btn btn--ghost btn--s', onclick: async () => { await api(`/webauthn/credentials/${c.id}/delete`, {}); loadFp(); } }, 'Remove'))))
    : h('p', { class: 'muted', style: 'font-size:14px', text: 'No device is set up yet. Add this one to sign in with your fingerprint, face or screen lock instead of a password.' })); };
  const fpBtn = h('button', { class: 'btn btn--quiet', type: 'button', disabled: !fingerprintSupported(), onclick: async () => { fpMsg.textContent = ''; fpBtn.disabled = true; try { await fingerprintEnroll(deviceLabel()); toast('Fingerprint sign-in is on', 'Use "Sign in with fingerprint" next time on this device.', 'ok'); loadFp(); } catch (e) { fpMsg.textContent = e.name === 'NotAllowedError' ? 'Setup was cancelled, or this device has no fingerprint or screen lock set.' : e.name === 'InvalidStateError' ? 'This device is already set up.' : e.message; } fpBtn.disabled = false; } }, icon('fingerprint', 18), 'Set up fingerprint on this device');
  const security = h('div', { style: 'display:grid;gap:22px' }, pwForm, h('div', { style: 'display:grid;gap:12px;border-top:1px solid var(--line-soft);padding-top:18px' }, h('b', { text: 'Fingerprint sign-in' }), fpList, fpMsg, h('div', {}, fpBtn), fingerprintSupported() ? null : h('small', { class: 'faint', text: 'This browser does not support fingerprint sign-in.' })));

  /* notifications */
  const devRow = h('div', {}, sk.rows(1)); const prefs = h('div', {}, sk.rows(4));
  const paintDevice = async () => {
    const st = await pushState();
    const text = { on: 'On for this device', off: 'Off for this device', blocked: 'Blocked in this browser. Allow notifications from the padlock beside the address.', unsupported: isIos() && !isInstalled() ? 'On an iPhone, notifications only reach an installed app. Tap the Share button, choose Add to Home Screen, open WDOS from the new icon, then switch notifications on there.' : 'Not available in this browser.' }[st];
    devRow.replaceChildren(h('div', { style: 'display:flex;gap:14px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'nrow__ico' }, icon('bell', 18)), h('div', { style: 'flex:1;min-width:180px' }, h('b', { text: 'Phone and desktop notifications' }), h('div', { class: 'faint', style: 'font-size:13.5px', text })),
      st === 'on' || st === 'off' ? sw(st === 'on', async (on, input) => { try { if (on) await pushEnable(app.config.vapid); else await pushDisable(); } catch (e) { toast('Notifications not switched on', e.message, 'bad'); input.checked = false; } paintDevice(); }, 'Notifications on this device') : null,
      st === 'on' ? h('button', { class: 'btn btn--ghost btn--s', onclick: async (e) => { e.currentTarget.disabled = true; try { const r = await sendTest(); if (r.devices.some((d) => d.gone)) paintDevice(); } catch (ex) { toast('Test not sent', ex.message, 'bad'); } e.target.disabled = false; } }, 'Send a test') : null));
  };
  const loadPrefs = async () => { const r = await api('/notifications/prefs');
    prefs.replaceChildren(h('div', { class: 'prefrow prefhead' }, h('span', { text: 'Tell me about' }), h('span', { text: 'In app' }), h('span', { text: 'Push' })), ...r.prefs.map((p) => { const st = { in_app: p.in_app, push: p.push }; const save = () => api('/notifications/prefs', { kind: p.kind, ...st }).catch((e) => toast('Not saved', e.message, 'bad'));
      return h('div', { class: 'prefrow' }, h('span', { text: p.label }), p.kind === 'account' ? h('span', { class: 'faint', style: 'font-size:12.5px', text: 'Always' }) : sw(p.in_app, (v) => { st.in_app = v; save(); }, p.label + ' in the app'), sw(p.push, (v) => { st.push = v; save(); }, p.label + ' by push')); }),
      r.push_configured ? null : h('p', { class: 'faint', style: 'font-size:13px;margin-top:10px', text: 'Push keys are not set on the server yet, so only in-app notifications are being delivered.' })); };

  /* appearance */
  const soundBox = h('input', { type: 'checkbox', id: 's-sound' }); soundBox.checked = soundOn(); soundBox.addEventListener('change', () => { try { localStorage.setItem('wdos.sound', soundBox.checked ? 'on' : 'off'); } catch { /* private mode */ } if (soundBox.checked) chime(true); });
  const soundRow = h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, h('label', { class: 'check', for: 's-sound', style: 'flex:1;min-width:220px' }, soundBox, h('span', { text: 'Play the WDOS sound when something new arrives while WDOS is open' })), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => chime(true) }, icon('volume', 16), 'Hear it'));
  const themeSeg = h('div', { class: 'seg', role: 'group', 'aria-label': 'Theme' }, [['dark', 'Dark', 'moon'], ['light', 'Light', 'sun']].map(([v, l, ic]) => h('button', { type: 'button', 'aria-pressed': String(theme.get() === v), style: 'display:inline-flex;gap:8px;align-items:center', onclick: (e) => { theme.set(v); themeSeg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget))); } }, icon(ic, 16), l)));

  root.append(h('div', { class: 'setgrid' }, card('Your profile', u.member_no ? 'Membership number ' + u.member_no : null, pForm), card('Sign-in and security', null, security),
    card('Notifications', 'Choose what reaches you, and where', h('div', { style: 'display:grid;gap:16px' }, devRow, soundRow, prefs)), card('Appearance', 'Saved on this device', h('div', { style: 'display:grid;gap:16px' }, themeSeg, h('label', { class: 'field' }, h('span', { text: 'Language' }), langPicker(setLanguage), h('small', { text: 'The joining pages, sign-in and most of the member screens are translated. Leader and HQ screens are still mostly in English.' }))))));
  loadFp(); paintDevice(); loadPrefs();
}
