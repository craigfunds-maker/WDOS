/* HQ: record the spoken joining guide in a real voice, one language and one step at a time. A recording here is what members hear when they press Listen. */
import { h, card, sk, empty, fmtDate, toast, i18n, LANGS, LANG_NAMES, GUIDE, GUIDE_INTRO, GUIDE_AUDIO, guideRecorded } from '../ui.js';
import { api } from '../api.js';

const b64 = (blob) => new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(String(r.result).split(',')[1]); r.onerror = rej; r.readAsDataURL(blob); });
export async function render(root) {
  const langSel = h('select', { class: 'input', style: 'max-width:260px', 'aria-label': 'Language' }, Object.keys(LANGS).map((l) => h('option', { value: l, text: `${LANG_NAMES[l]} (${LANGS[l]})` })));
  langSel.value = ['ha', 'ig', 'yo'].includes(i18n.lang) ? i18n.lang : 'ha';
  const list = h('div', {}, sk.rows(6)), status = h('div', { class: 'tagrow' });
  root.append(card('Record the guide in a real voice', 'A member who presses Listen hears the recording made here. It replaces the computer voice, and it is the only voice for Hausa, Igbo and Yoruba. Record in a quiet room, close to the microphone, slowly and clearly. Correct the wording as you read if it is not how people really say it.', h('div', { style: 'display:grid;gap:12px' }, h('div', { style: 'display:flex;gap:12px;flex-wrap:wrap;align-items:center' }, langSel, status), list)));
  let recorder = null, chunks = [], recStep = -1;
  async function load() {
    let d, dict = {}; try { d = await api('/hq/guide-audio'); } catch (e) { list.replaceChildren(empty('x', 'This page did not load', e.message)); return; }
    const lang = langSel.value; if (lang !== 'en') { try { dict = await (await fetch(`/assets/i18n/${lang}.json`)).json(); } catch { dict = {}; } }
    const have = {}; d.items.filter((x) => x.lang === lang).forEach((x) => { have[x.step] = x; });
    status.replaceChildren(...Object.keys(LANGS).map((l) => { const n = d.items.filter((x) => x.lang === l && x.step > 0).length; return h('span', { class: 'tag ' + (n === 7 ? 'tag--wnnn' : n ? 'tag--wgmn' : GUIDE_AUDIO.includes(l) ? '' : 'tag--bad'), text: `${LANG_NAMES[l]}: ${n === 7 ? 'recorded' : n ? n + ' of 7 recorded' : GUIDE_AUDIO.includes(l) ? 'computer voice' : 'no voice yet'}` }); }));
    const lines = [GUIDE_INTRO, ...GUIDE];
    list.replaceChildren(...lines.map((en, step) => { const text = dict[en] || en; const mine = have[step]; const player = h('div'); const recBtn = h('button', { class: 'btn btn--s recbtn', type: 'button' }, 'Record'); const file = h('input', { type: 'file', accept: 'audio/*,video/webm,video/mp4', hidden: true });
      const save = async (blob) => { if (blob.size > 3 * 1024 * 1024) { toast('Too large', 'Record a shorter take, under 3 MB.', 'bad'); return; } try { await api('/hq/guide-audio', { lang, step, mime: blob.type || 'audio/webm', data: await b64(blob) }); toast('Saved', `${LANG_NAMES[lang]}, ${step ? 'step ' + step : 'opening line'}`, 'ok'); guideRecorded(true); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } };
      recBtn.onclick = async () => {
        if (recorder && recStep === step) { recorder.stop(); return; }
        if (recorder) { toast('Finish the other recording first', '', 'bad'); return; }
        let stream; try { stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }); } catch { toast('The microphone is blocked', 'Allow the microphone for this site in the browser, or use Upload a file.', 'bad'); return; }
        chunks = []; recStep = step; recorder = new MediaRecorder(stream); recorder.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
        recorder.onstop = () => { stream.getTracks().forEach((tr) => tr.stop()); const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' }); recorder = null; recStep = -1; recBtn.classList.remove('is-rec'); recBtn.textContent = 'Record again';
          const url = URL.createObjectURL(blob); player.replaceChildren(h('audio', { src: url, controls: true, style: 'width:100%;max-width:420px' }), h('div', { style: 'display:flex;gap:8px;margin-top:8px' }, h('button', { class: 'btn btn--s', type: 'button', onclick: () => save(blob) }, 'Keep this recording'), h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => player.replaceChildren() }, 'Discard'))); };
        recorder.start(); recBtn.classList.add('is-rec'); recBtn.textContent = 'Stop recording';
      };
      file.onchange = () => { if (file.files[0]) save(file.files[0]); };
      return h('div', { class: 'recrow' }, h('small', { class: 'eyebrow', text: step ? `Step ${step}` : 'Opening line (used in the video)' }), h('p', { lang, dir: lang === 'ar' ? 'rtl' : 'ltr', text }), lang !== 'en' ? h('small', { class: 'faint', text: en }) : null,
        mine ? h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:center' }, h('audio', { src: `/api/public/guide-audio/${lang}/${step}?v=${mine.v}`, controls: true, preload: 'none', style: 'max-width:320px' }), h('small', { class: 'muted', text: `Recorded by ${mine.recorded_by || 'HQ'} on ${fmtDate(mine.updated_at)}` })) : h('small', { class: 'muted', text: GUIDE_AUDIO.includes(lang) && step ? 'No recording. Members hear the computer voice.' : 'No recording yet. Members hear nothing for this step.' }),
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, recBtn, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => file.click() }, 'Upload a file'), file, mine ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { await api('/hq/guide-audio/delete', { lang, step }); guideRecorded(true); load(); } catch (e) { toast('Not removed', e.message, 'bad'); } } }, 'Remove') : null), player); }));
  }
  langSel.addEventListener('change', load); load();
}
