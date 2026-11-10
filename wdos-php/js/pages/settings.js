/* Settings: profile and photograph, password, fingerprint devices, notifications on this device and per type, theme. */
import { h, icon, avatar, card, sk, toast, agoIn, theme, langPicker } from '../ui.js';
import { api, squarePhoto, fingerprintSupported, fingerprintEnroll, deviceLabel, pushState, pushEnable, pushDisable } from '../api.js';
import { app, refreshUser, setLanguage } from '../app.js';

const sw = (checked, onchange, label) => { const i = h('input', { type: 'checkbox', 'aria-label': label }); i.checked = !!checked; i.addEventListener('change', () => onchange(i.checked, i)); return h('label', { class: 'switch' }, i, h('i')); };

export async function render(root) {
  const u = app.user;
  /* profile */
  const pic = h('div', {}, avatar(u, 84)); const file = h('input', { type: 'file', accept: 'image/*', hidden: true });
  file.addEventListener('change', async () => { if (!file.files[0]) return; try { const data = await squarePhoto(file.files[0]); const r = await api('/me/photo', { data }); await refreshUser(); app.user._v = r.v; pic.replaceChildren(avatar(app.user, 84)); toast('Photograph saved', '', 'ok'); } catch (e) { toast('Photograph not saved', e.message, 'bad'); } file.value = ''; });
  const f = { first_name: h('input', { class: 'input', id: 's-fn', value: u.first_name, maxlength: 80 }), last_name: h('input', { class: 'input', id: 's-ln', value: u.last_name, maxlength: 80 }), phone: h('input', { class: 'input', id: 's-ph', type: 'tel', value: u.phone, maxlength: 24 }), lga: h('input', { class: 'input', id: 's-lga', value: u.lga, maxlength: 120 }), community: h('input', { class: 'input', id: 's-com', value: u.community, maxlength: 120 }) };
  const pBtn = h('button', { class: 'btn', type: 'submit', text: 'Save changes' });
  const pForm = h('form', { style: 'display:grid;gap:16px' }, h('div', { class: 'photo' }, pic, h('div', {}, h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => file.click() }, icon('camera', 16), u.has_photo ? 'Change photograph' : 'Add photograph'), h('div', { class: 'faint', style: 'font-size:13px;margin-top:6px', text: 'A clear photo of your face. It is cropped to a square.' })), file),
    h('div', { class: 'grid2' }, h('label', { class: 'field', for: 's-fn' }, h('span', { text: 'First name' }), f.first_name), h('label', { class: 'field', for: 's-ln' }, h('span', { text: 'Surname' }), f.last_name), h('label', { class: 'field', for: 's-ph' }, h('span', { text: 'Phone' }), f.phone), h('label', { class: 'field' }, h('span', { text: 'Email' }), h('input', { class: 'input', value: u.email, disabled: true })),
      u.is_hq && !u.network ? null : h('label', { class: 'field', for: 's-lga' }, h('span', { text: 'LGA or district' }), f.lga), u.is_hq && !u.network ? null : h('label', { class: 'field', for: 's-com' }, h('span', { text: 'Community or ward' }), f.community)),
    h('div', {}, pBtn));
  pForm.addEventListener('submit', async (e) => { e.preventDefault(); pBtn.disabled = true; try { await api('/me', Object.fromEntries(Object.entries(f).map(([k, v]) => [k, v.value]))); await refreshUser(); toast('Changes saved', '', 'ok'); } catch (ex) { toast('Not saved', ex.message, 'bad'); } pBtn.disabled = false; });

  /* password */
  const cur = h('input', { class: 'input', id: 's-cur', type: 'password', autocomplete: 'current-password' }), nw = h('input', { class: 'input', id: 's-new', type: 'password', autocomplete: 'new-password', minlength: 8 });
  const pwMsg = h('p', { class: 'err', role: 'alert' }); const pwBtn = h('button', { class: 'btn btn--quiet', type: 'submit', text: u.has_password ? 'Change password' : 'Set a password' });
  const pwForm = h('form', { style: 'display:grid;gap:14px' }, u.must_change_password ? h('p', { class: 'okmsg', text: 'You signed in with a temporary password from HQ. Choose your own now.' }) : null,
    u.has_password ? h('label', { class: 'field', for: 's-cur' }, h('span', { text: u.must_change_password ? 'Temporary password' : 'Current password' }), cur) : h('p', { class: 'muted', style: 'font-size:14px', text: 'You joined with Google. Setting a password lets you also sign in with your email.' }),
    h('label', { class: 'field', for: 's-new' }, h('span', { text: 'New password' }), nw, h('small', { text: 'At least 8 characters.' })), pwMsg, h('div', {}, pwBtn));
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
    const text = { on: 'On for this device', off: 'Off for this device', blocked: 'Blocked in this browser. Allow notifications from the padlock beside the address.', unsupported: 'Not available in this browser. On an iPhone, add WDOS to the Home Screen first, then open it from there.' }[st];
    devRow.replaceChildren(h('div', { style: 'display:flex;gap:14px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'nrow__ico' }, icon('bell', 18)), h('div', { style: 'flex:1;min-width:180px' }, h('b', { text: 'Phone and desktop notifications' }), h('div', { class: 'faint', style: 'font-size:13.5px', text })),
      st === 'on' || st === 'off' ? sw(st === 'on', async (on, input) => { try { if (on) await pushEnable(app.config.vapid); else await pushDisable(); } catch (e) { toast('Notifications not switched on', e.message, 'bad'); input.checked = false; } paintDevice(); }, 'Notifications on this device') : null,
      st === 'on' ? h('button', { class: 'btn btn--ghost btn--s', onclick: async (e) => { e.currentTarget.disabled = true; try { const r = await api('/push/test', {}); toast(r.sent ? 'Test sent' : 'Test queued', r.sent ? 'It should appear on this device within a few seconds.' : 'The push service did not accept it yet. It will retry.', r.sent ? 'ok' : 'info'); } catch (ex) { toast('Test not sent', ex.message, 'bad'); } e.target.disabled = false; } }, 'Send a test') : null));
  };
  const loadPrefs = async () => { const r = await api('/notifications/prefs');
    prefs.replaceChildren(h('div', { class: 'prefrow prefhead' }, h('span', { text: 'Tell me about' }), h('span', { text: 'In app' }), h('span', { text: 'Push' })), ...r.prefs.map((p) => { const st = { in_app: p.in_app, push: p.push }; const save = () => api('/notifications/prefs', { kind: p.kind, ...st }).catch((e) => toast('Not saved', e.message, 'bad'));
      return h('div', { class: 'prefrow' }, h('span', { text: p.label }), p.kind === 'account' ? h('span', { class: 'faint', style: 'font-size:12.5px', text: 'Always' }) : sw(p.in_app, (v) => { st.in_app = v; save(); }, p.label + ' in the app'), sw(p.push, (v) => { st.push = v; save(); }, p.label + ' by push')); }),
      r.push_configured ? null : h('p', { class: 'faint', style: 'font-size:13px;margin-top:10px', text: 'Push keys are not set on the server yet, so only in-app notifications are being delivered.' })); };

  /* appearance */
  const themeSeg = h('div', { class: 'seg', role: 'group', 'aria-label': 'Theme' }, [['dark', 'Dark', 'moon'], ['light', 'Light', 'sun']].map(([v, l, ic]) => h('button', { type: 'button', 'aria-pressed': String(theme.get() === v), style: 'display:inline-flex;gap:8px;align-items:center', onclick: (e) => { theme.set(v); themeSeg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget))); } }, icon(ic, 16), l)));

  root.append(h('div', { class: 'setgrid' }, card('Your profile', u.member_no ? 'Membership number ' + u.member_no : null, pForm), card('Sign-in and security', null, security),
    card('Notifications', 'Choose what reaches you, and where', h('div', { style: 'display:grid;gap:16px' }, devRow, prefs)), card('Appearance', 'Saved on this device', h('div', { style: 'display:grid;gap:16px' }, themeSeg, h('label', { class: 'field' }, h('span', { text: 'Language' }), langPicker(setLanguage), h('small', { text: 'Menus, page titles, the joining pages and common buttons are translated. Other text stays in English for now.' }))))));
  loadFp(); paintDevice(); loadPrefs();
}
