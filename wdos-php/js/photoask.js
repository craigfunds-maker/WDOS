/* v109.33: after she signs in without a profile photograph, WDOS asks her to take one with the camera or choose one from
 * her phone (Azeez, 19 Nov 2026). It opens over her dashboard, never in place of it. The photo is cropped to a square in the browser and becomes her profile picture everywhere in WDOS. */
import { h, t, icon, avatar, toast } from './ui.js';
import { api, squarePhoto } from './api.js';
import { app, refreshUser, reshell } from './app.js';

/* v109.44 (Azeez, 21 Nov 2026: "this should keep showing on their dashboard until they snap or upload their profile pictures"):
 * the question opens every time she opens WDOS without a photo, not once per sign-in, and a card stays at the top of her
 * dashboard until the photo is saved. "Not now" only closes it for this visit. */
let askedThisVisit = false;
export function needsPhoto(u) {
  if (!u || u.is_hq || u.has_photo || u.status === 'pending_profile') return false;
  return !askedThisVisit;
}
/** The card at the top of her dashboard while she has no photo. It cannot be dismissed. */
export function photoNudge(u) {
  if (!u || u.is_hq || u.has_photo || u.status === 'pending_profile') return '';
  return h('section', { class: 'card nudge photonudge', id: 'photo-nudge' }, h('div', { class: 'card__body' }, avatar(u, 56),
    h('div', { class: 'grow', style: 'min-width:220px' }, h('b', {}, icon('camera', 18), ' ', t('Add your profile photo')), h('p', { class: 'muted', style: 'margin:4px 0 0', text: t('Leaders and members can see who you are. Take a clear photo of your face now, or choose one from your phone.') })),
    h('button', { class: 'btn btn--lime', type: 'button', onclick: () => askPhoto() }, icon('camera', 18), t('Take or upload a photo'))));
}
const squareFromCanvas = (video, size = 512) => {
  const w = video.videoWidth, hgt = video.videoHeight, s = Math.min(w, hgt); const c = document.createElement('canvas'); c.width = c.height = size;
  const g = c.getContext('2d'); g.translate(size, 0); g.scale(-1, 1);            // the same way round as the mirror she saw
  g.drawImage(video, (w - s) / 2, (hgt - s) / 2, s, s, 0, 0, size, size);
  return c.toDataURL('image/jpeg', 0.84);
};

export function askPhoto(after = () => {}) {
  askedThisVisit = true;
  document.querySelector('.hwpanel.photoask')?.remove();
  let stream = null; let data = null;
  const stop = () => { if (stream) stream.getTracks().forEach((x) => x.stop()); stream = null; };
  const close = () => { stop(); wrap.remove(); document.removeEventListener('keydown', esc); after(); };
  const esc = (e) => { if (e.key === 'Escape') close(); };
  const u = app.user;
  const err = h('p', { class: 'err', role: 'alert', style: 'margin:0' });
  const stage = h('div', { class: 'photoask__stage' }, avatar(u, 140));
  const upload = h('input', { type: 'file', accept: 'image/*', hidden: true });
  const phoneCam = h('input', { type: 'file', accept: 'image/*', capture: 'user', hidden: true });   // phones without live camera access
  const actions = h('div', { class: 'photoask__actions' });
  const title = h('h3', { style: 'margin:0', text: t('Add your profile photo') });
  const lead = h('p', { class: 'muted', style: 'margin:0', text: t('Leaders and members can see who you are. Take a clear photo of your face now, or choose one from your phone.') });

  const preview = (d) => {
    data = d; stop(); err.textContent = '';
    stage.replaceChildren(h('img', { src: d, alt: t('Your new photo'), class: 'photoask__img' }));
    actions.replaceChildren(
      h('button', { class: 'btn btn--lime', type: 'button', onclick: save }, icon('check', 18), t('Use this photo')),
      h('button', { class: 'btn btn--ghost', type: 'button', onclick: start }, icon('back', 18), t('Choose again')));
  };
  const fromFile = async (inp) => { const f = inp.files[0]; inp.value = ''; if (!f) return; try { preview(await squarePhoto(f)); } catch (e) { err.textContent = e.message; } };
  upload.addEventListener('change', () => fromFile(upload)); phoneCam.addEventListener('change', () => fromFile(phoneCam));

  async function camera() {
    err.textContent = '';
    if (!navigator.mediaDevices?.getUserMedia) { phoneCam.click(); return; }
    try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } }, audio: false }); }
    catch { err.textContent = t('WDOS could not open your camera. Allow the camera for this site, or choose a photo from your phone instead.'); phoneCam.click(); return; }
    const video = h('video', { class: 'photoask__img photoask__live', autoplay: true, playsinline: true, muted: true });
    video.srcObject = stream; stage.replaceChildren(video);
    actions.replaceChildren(
      h('button', { class: 'btn btn--lime', type: 'button', onclick: () => { if (!video.videoWidth) return; preview(squareFromCanvas(video)); } }, icon('camera', 18), t('Snap the photo')),
      h('button', { class: 'btn btn--ghost', type: 'button', onclick: start }, t('Cancel')));
  }
  async function save() {
    if (!data) return; const b = actions.querySelector('button'); if (b) b.disabled = true;
    try {
      const r = await api('/me/photo', { data }); await refreshUser(); app.user._v = r.v;
      toast(t('Your profile photo is saved'), t('You can change it any time in Settings.'), 'ok'); close(); document.getElementById('photo-nudge')?.remove(); reshell();
    } catch (e) { err.textContent = e.message; if (b) b.disabled = false; }
  }
  function start() {
    stop(); data = null; err.textContent = ''; stage.replaceChildren(avatar(u, 140));
    actions.replaceChildren(
      h('button', { class: 'btn btn--lime', type: 'button', onclick: camera }, icon('camera', 18), t('Take a photo')),
      h('button', { class: 'btn', type: 'button', onclick: () => upload.click() }, icon('up', 18), t('Upload a photo')),
      h('button', { class: 'btn btn--quiet', type: 'button', onclick: close }, t('Not now')));
  }
  const wrap = h('div', { class: 'hwpanel photoask', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('Add your profile photo') },
    h('div', { class: 'hwpanel__box card' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px;justify-items:center;text-align:center' },
      title, lead, stage, actions, err, upload, phoneCam)));
  start(); document.addEventListener('keydown', esc); document.body.append(wrap);
}
