/* Talks to /api. Also the device features: push subscription and fingerprint. */
export class ApiError extends Error { constructor(m, code, status, data) { super(m); this.code = code; this.status = status; this.data = data; } }
export async function api(path, body, method) {
  let res;
  try {
    res = await fetch('/api' + path, {
      method: method || (body === undefined ? 'GET' : 'POST'), credentials: 'same-origin',
      headers: body === undefined ? { 'X-WDOS': '1' } : { 'Content-Type': 'application/json', 'X-WDOS': '1' },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch { throw new ApiError('You appear to be offline. Check your connection and try again.', 'offline', 0); }
  let data = null; try { data = await res.json(); } catch { /* not JSON */ }
  if (!res.ok || (data && data.ok === false)) {
    if (res.status === 401 && data?.error === 'signed_out') window.dispatchEvent(new Event('wdos:signed-out'));
    throw new ApiError(data?.message || 'WDOS could not complete that. Please try again.', data?.error || 'error', res.status, data);
  }
  return data;
}

/* ---- base64url <-> bytes ---- */
const toB64u = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const fromB64u = (s) => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4)), (c) => c.charCodeAt(0));

/* ---- fingerprint (WebAuthn passkeys) ---- */
export const fingerprintSupported = () => !!(window.PublicKeyCredential && navigator.credentials);
export async function fingerprintEnroll(label) {
  const { challenge_id, publicKey } = await api('/webauthn/register/options', {});
  publicKey.challenge = fromB64u(publicKey.challenge); publicKey.user.id = fromB64u(publicKey.user.id);
  publicKey.excludeCredentials = (publicKey.excludeCredentials || []).map((c) => ({ ...c, id: fromB64u(c.id) }));
  const cred = await navigator.credentials.create({ publicKey });
  const r = cred.response;
  if (!r.getPublicKey || !r.getPublicKey()) throw new Error('This browser cannot set up fingerprint sign-in. Update it and try again.');
  return api('/webauthn/register/verify', { challenge_id, id: toB64u(cred.rawId), clientDataJSON: toB64u(r.clientDataJSON), authenticatorData: toB64u(r.getAuthenticatorData()), publicKey: toB64u(r.getPublicKey()), alg: r.getPublicKeyAlgorithm(), label });
}
export async function fingerprintLogin(emailHint = '') {
  const { challenge_id, publicKey } = await api('/webauthn/login/options', { email: emailHint });
  publicKey.challenge = fromB64u(publicKey.challenge);
  publicKey.allowCredentials = (publicKey.allowCredentials || []).map((c) => ({ ...c, id: fromB64u(c.id) }));
  const cred = await navigator.credentials.get({ publicKey });
  const r = cred.response;
  return api('/webauthn/login/verify', { challenge_id, id: toB64u(cred.rawId), clientDataJSON: toB64u(r.clientDataJSON), authenticatorData: toB64u(r.authenticatorData), signature: toB64u(r.signature) });
}
export function deviceLabel() {
  const ua = navigator.userAgent;
  const os = /Android/.test(ua) ? 'Android phone' : /iPhone|iPad/.test(ua) ? 'iPhone or iPad' : /Windows/.test(ua) ? 'Windows computer' : /Mac/.test(ua) ? 'Mac' : 'This device';
  const br = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /Firefox/.test(ua) ? 'Firefox' : /Chrome/.test(ua) ? 'Chrome' : /Safari/.test(ua) ? 'Safari' : '';
  return br ? `${os}, ${br}` : os;
}

/* ---- push ---- */
export const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
export async function pushState() {
  if (!pushSupported()) return 'unsupported';
  if (Notification.permission === 'denied') return 'blocked';
  const reg = await navigator.serviceWorker.getRegistration();
  const sub = reg && await reg.pushManager.getSubscription();
  return sub ? 'on' : 'off';
}
export async function pushEnable(vapid) {
  if (!pushSupported()) throw new Error(isIos() && !isInstalled() ? 'On an iPhone, notifications only work after WDOS is added to the Home Screen. Tap the Share button, choose Add to Home Screen, open WDOS from the new icon, then switch notifications on there.' : 'This browser cannot receive push notifications.');
  if (!vapid) throw new Error('Push keys are not set on the server yet. HQ IT needs to finish /api/setup.');
  const perm = await Notification.requestPermission();
  if (perm !== 'granted') throw new Error('Notifications are blocked for this site. Allow them from the padlock beside the address, then try again.');
  const reg = await navigator.serviceWorker.ready;
  let sub = await reg.pushManager.getSubscription();
  if (sub) { const cur = sub.options?.applicationServerKey ? toB64u(sub.options.applicationServerKey) : ''; if (cur && cur !== vapid) { await sub.unsubscribe(); sub = null; } }
  if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: fromB64u(vapid) });
  await api('/push/subscribe', { subscription: sub.toJSON() });
  return true;
}
/* Keep the server in step with this browser. A browser can hold a push address the server has lost, or one made
 * with older keys; either way notifications silently stop. Runs quietly each time the app opens. */
export async function pushSync(vapid) {
  try {
    if (!pushSupported() || !vapid || Notification.permission !== 'granted') return;
    const reg = await navigator.serviceWorker.ready; let sub = await reg.pushManager.getSubscription(); if (!sub) return;
    const cur = sub.options?.applicationServerKey ? toB64u(sub.options.applicationServerKey) : '';
    if (cur && cur !== vapid) { await sub.unsubscribe(); sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: fromB64u(vapid) }); }
    await api('/push/subscribe', { subscription: sub.toJSON() });
  } catch { /* never blocks the app */ }
}
export const isIos = () => /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
export const isInstalled = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
export async function pushDisable() {
  const reg = await navigator.serviceWorker.getRegistration();
  const sub = reg && await reg.pushManager.getSubscription();
  if (sub) { await api('/push/unsubscribe', { endpoint: sub.endpoint }).catch(() => {}); await sub.unsubscribe(); }
}
/* Resize a chosen photograph to a 512px square JPEG in the browser, so uploads stay small on slow connections. */
export function squarePhoto(file, size = 512) {
  return new Promise((resolve, reject) => {
    const img = new Image(); const url = URL.createObjectURL(file);
    img.onload = () => {
      const s = Math.min(img.width, img.height), c = document.createElement('canvas'); c.width = c.height = size;
      c.getContext('2d').drawImage(img, (img.width - s) / 2, (img.height - s) / 2, s, s, 0, 0, size, size);
      URL.revokeObjectURL(url); resolve(c.toDataURL('image/jpeg', 0.84));
    };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('That file is not a photograph WDOS can read.')); };
    img.src = url;
  });
}
