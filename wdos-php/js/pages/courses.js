/* Volunteer Leadership Personal Development Courses: 12 courses, five short lessons each, a ten-question quiz and a
 * certificate that anyone can check with its QR code. Every lesson has a narrated picture "video" with captions in her
 * language, the full written lesson below it, and a private note box. Lessons open one after another; the quiz opens
 * when all five are done. The right answers stay on the server. */
import { h, t, tf, icon, card, sk, empty, toast, i18n } from '../ui.js';
import { api } from '../api.js';
import { go, app } from '../app.js';

const LANGS = ['ar', 'en', 'fr', 'ha', 'ig', 'pt', 'sw', 'yo'];
// WGMN and WNNN each have their own edition: the same twelve courses, with examples and practice for her network and age
export const courseNet = () => { const u = app.user || {}; let n = (u.network || '').toUpperCase(); if (u.is_hq) { try { n = sessionStorage.getItem('wdos.pdnet') || 'WGMN'; } catch { n = 'WGMN'; } } return n === 'WNNN' ? 'WNNN' : 'WGMN'; };
let cache = { key: '', data: null, cues: null };
async function content() {
  const lang = LANGS.includes(i18n.lang) ? i18n.lang : 'en'; const net = courseNet().toLowerCase();
  if (cache.key === lang + net && cache.data) return cache;
  const get = async (l) => {   // tried three times: one dropped request on a weak signal should not stop the lesson
    for (let i = 0; ; i++) {
      try { const r = await fetch(`/assets/courses/${l}/${net}.json`, { cache: 'no-cache' }); if (r.status === 404) throw Object.assign(new Error('missing'), { missing: true }); if (!r.ok) throw new Error('content'); return await r.json(); }
      catch (e) { if (e.missing || i >= 2) throw new Error(t('WDOS could not reach the server just now. Check your connection and try again.')); await new Promise((r) => setTimeout(r, 700 * (i + 1))); }
    }
  };
  let data; let used = lang;
  try { data = await get(lang); } catch { data = await get('en'); used = 'en'; }
  let cues = {}; try { cues = await (await fetch(`/assets/courses/voice/${used}/cues.json`, { cache: 'no-cache' })).json(); } catch { /* text only */ }
  cache = { key: lang + net, lang, used, net: courseNet(), data, cues };
  return cache;
}
const pad = (n) => String(n).padStart(2, '0');
const photo = (st, c, i) => st.photos[`course/c${pad(c)}-${((i - 1) % 5) + 1}`] || '';
// the course card and hero picture for her network: WNNN has its own pictures of young people
const thumb = (st, net, c) => (net === 'WNNN' && st.photos[`course/wnnn-c${pad(c)}`]) || photo(st, c, 1);
const hero = (st, net) => st.photos[`course/hero-${net.toLowerCase()}`] || photo(st, 1, 1);
const topic = (title) => title.charAt(0).toLowerCase() + title.slice(1);

export async function render(root, params = {}) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(5))); root.append(box);
  let C, st;
  try { [C, st] = await Promise.all([content(), api('/courses')]); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'The courses did not load', e.message || 'Check your connection and try again.'))); return; }
  const no = Number(params.course) || 0; const course = no ? C.data.courses.find((x) => x.no === no) : null;
  const sc = no ? st.courses.find((x) => x.no === no) : null;
  const note = C.used !== C.lang ? h('p', { class: 'notice', text: t('These lessons are not yet translated into your language, so they show in English.') }) : null;
  if (!course) return box.replaceChildren(...[note, listView(C, st)].filter(Boolean));
  if (params.part === 'quiz') return box.replaceChildren(...[note, quizView(C, st, course, sc)].filter(Boolean));
  const m = Number(params.part) || 0;
  if (m >= 1 && m <= 5) {
    const open = m === 1 || sc.modules_done.includes(m - 1);
    if (!open) { go(`#/courses/${no}`); toast(t('Finish the lessons in order'), t('Each lesson opens when the one before it is finished.'), 'info'); return; }
    return box.replaceChildren(...[note, lessonView(C, st, course, sc, m)].filter(Boolean));
  }
  box.replaceChildren(...[note, courseView(C, st, course, sc)].filter(Boolean));
}

/* ---------------------------------------------------------------- list -- */
function listView(C, st) {
  const passed = st.courses.filter((x) => x.passed).length;
  const head = h('section', { class: 'card pdx-hero' },
    h('div', { class: 'pdx-hero__text' }, h('span', { class: 'gmc__kicker', text: t('Volunteer Leadership') }), h('h2', { class: 'display', text: t('Personal Development Courses') }),
      h('span', { class: 'tag', text: C.net === 'WNNN' ? t('WNNN edition: young people aged 18 to 29') : t('WGMN edition: women and mothers in the community') }),
      app.user?.is_hq ? h('div', { class: 'seg' }, ['WGMN', 'WNNN'].map((n) => h('button', { type: 'button', class: 'btn btn--s ' + (C.net === n ? '' : 'btn--ghost'), onclick: () => { try { sessionStorage.setItem('wdos.pdnet', n); } catch { /* */ } cache.key = ''; window.dispatchEvent(new HashChangeEvent('hashchange')); }, text: n }))) : null,
      h('p', { class: 'muted', text: t('Twelve short courses for WGMN Coordinators, WNNN Leads, Country Representatives, Deputies and every volunteer leader. Each has five lessons you can listen to, then a ten-question quiz. Score 7 or more to earn a certificate.') }),
      h('div', { class: 'pdx-meter', role: 'img', 'aria-label': tf('{1} of 12 courses passed', passed) }, h('span', { style: `width:${(passed / 12) * 100}%` })),
      h('b', { text: tf('{1} of 12 courses passed', passed) }),
      st.place && C.data.courses.find((x) => x.no === st.place.course) ? h('a', { class: 'btn', href: `#/courses/${st.place.course}${st.place.part ? '/' + st.place.part : ''}` }, icon('play', 18), t('Continue where you stopped'), h('small', { style: 'opacity:.8;margin-inline-start:6px', text: st.place.part === 'quiz' ? tf('Course {1}: quiz', st.place.course) : st.place.part ? tf('Lesson {1}.{2}', st.place.course, st.place.part) : tf('Course {1} of 12', st.place.course) })) : null,
      st.programme ? h('a', { class: 'btn btn--lime', href: `#/certificate/${st.programme.code}` }, icon('award', 18), t('Your programme certificate')) : null),
    h('img', { class: 'pdx-hero__img', src: hero(st, C.net) || '/assets/logos/woddi-disc.png', alt: '', loading: 'lazy' }));
  const grid = h('div', { class: 'pdx-grid' }, C.data.courses.map((c) => {
    const s = st.courses.find((x) => x.no === c.no); const n = s.modules_done.length;
    const badge = s.passed ? [t('Passed'), 'tag--wnnn'] : n === 5 ? [t('Quiz ready'), 'tag--wgmn'] : n ? [t('In progress'), ''] : [t('Not started'), ''];
    return h('a', { class: 'pdx-card', href: `#/courses/${c.no}` },
      h('div', { class: 'pdx-card__img' }, thumb(st, C.net, c.no) ? h('img', { src: thumb(st, C.net, c.no), alt: '', loading: 'lazy' }) : null, h('span', { class: 'pdx-card__no', text: pad(c.no) })),
      h('div', { class: 'pdx-card__body' }, h('b', { text: c.title }),
        h('div', { class: 'pdx-meter pdx-meter--s' }, h('span', { style: `width:${s.passed ? 100 : n * 18}%` })),
        h('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap' }, h('span', { class: 'tag ' + badge[1], text: badge[0] }), h('small', { class: 'faint', text: tf('{1} of 5 lessons', n) }),
          s.certificate ? h('span', { class: 'tag tag--wnnn' }, icon('award', 13), ' ', t('Certificate')) : null)));
  }));
  return h('div', { style: 'display:grid;gap:18px' }, head, grid,
    h('p', { class: 'faint', style: 'margin:0', text: t('A certificate shows that you completed the course. It does not appoint anyone to a leadership role: appointments remain with WODDI Headquarters.') }));
}

/* -------------------------------------------------------------- course -- */
function courseView(C, st, c, s) {
  const n = s.modules_done.length;
  const next = n < 5 ? n + 1 : 0;
  const intro = new Audio(`/assets/courses/voice/${C.used}/c${pad(c.no)}-intro.mp3`); intro.preload = 'none';
  const listen = h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { if (intro.paused) { intro.play().catch(() => {}); listen.replaceChildren(icon('pause', 16), t('Pause')); } else { intro.pause(); listen.replaceChildren(icon('play', 16), t('Listen to the introduction')); } } }, icon('play', 16), t('Listen to the introduction'));
  intro.onended = () => listen.replaceChildren(icon('play', 16), t('Listen to the introduction'));
  const steps = h('ol', { class: 'pdx-steps' }, c.modules.map((m) => {
    const done = s.modules_done.includes(m.no); const open = m.no === 1 || s.modules_done.includes(m.no - 1);
    return h('li', { class: 'pdx-step' + (done ? ' is-done' : open ? ' is-open' : ' is-locked') },
      h('span', { class: 'pdx-step__dot' }, done ? icon('check', 16) : open ? String(m.no) : icon('shield', 14)),
      open ? h('a', { href: `#/courses/${c.no}/${m.no}`, class: 'pdx-step__link' }, h('b', { text: m.title }), h('small', { class: 'faint', text: done ? t('Finished. Open it again any time.') : t('About 5 minutes, with a narrated video') }))
        : h('div', { class: 'pdx-step__link' }, h('b', { text: m.title }), h('small', { class: 'faint', text: t('Opens when the lesson before it is finished') })));
  }));
  const quiz = n === 5 ? h('a', { class: 'btn btn--lime', href: `#/courses/${c.no}/quiz` }, icon('check', 18), s.passed ? t('Take the quiz again') : t('Take the quiz'))
    : h('button', { class: 'btn', type: 'button', disabled: true }, icon('shield', 16), t('The quiz opens after the five lessons'));
  return h('div', { style: 'display:grid;gap:18px' },
    h('a', { href: '#/courses', class: 'btn btn--quiet btn--s', style: 'justify-self:start' }, icon('back', 16), t('All courses')),
    h('section', { class: 'card pdx-hero' },
      h('div', { class: 'pdx-hero__text' }, h('span', { class: 'gmc__kicker', text: tf('Course {1} of 12', c.no) }), h('h2', { class: 'display', text: c.title }), h('p', { text: c.purpose }),
        h('div', { class: 'pdx-process' }, h('small', { class: 'faint', text: t('The leadership process in this course') }), h('b', { text: c.process.replace(/->/g, '→') })),
        C.data.t.outcomes ? h('p', { class: 'faint', text: C.data.t.outcomes }) : null,
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, next ? h('a', { class: 'btn btn--lime', href: `#/courses/${c.no}/${next}` }, icon('play', 18), n ? t('Continue the course') : t('Start the course')) : quiz, listen)),
      h('img', { class: 'pdx-hero__img', src: thumb(st, C.net, c.no) || '/assets/logos/woddi-disc.png', alt: '', loading: 'lazy' })),
    card(t('Lessons'), tf('{1} of 5 finished', n), steps),
    card(t('Quiz and certificate'), tf('Ten questions. Score {1} or more to pass. You can try again after reviewing the lessons.', st.pass), h('div', { style: 'display:grid;gap:12px' },
      s.attempts ? h('p', { style: 'margin:0' }, h('b', { text: tf('Best score: {1} of 10', s.best) }), ' ', h('span', { class: 'faint', text: tf('({1} attempts)', s.attempts) })) : null,
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, quiz, s.certificate ? h('a', { class: 'btn btn--ghost', href: `#/certificate/${s.certificate.code}` }, icon('award', 18), t('View your certificate')) : null))),
    h('p', { class: 'notice', text: c.boundaries }));
}

/* -------------------------------------------------------------- lesson -- */
function lessonView(C, st, c, s, mNo) {
  const m = c.modules.find((x) => x.no === mNo); const T = C.data.t; const tp = m.topic || topic(m.title);
  const k0 = `c${pad(c.no)}-${mNo}`; const nt = C.net.toLowerCase();
  // the whole lesson, in the order it is written below: the idea, why it matters, the steps, the example for her network,
  // questions to think about, her practice, how to answer, taking it into service, and the learning evidence
  const keys = [k0, `g-why-${nt}`, 'g-steps', `${k0}-${nt}-ex`, 'g-think', `${k0}-${nt}-pr`, 'g-parts', 'g-reflect', 'g-evidence'].filter((k) => C.cues[k]);
  const starts = []; let total = 0; keys.forEach((k) => { starts.push(total); total += C.cues[k].d; });
  // photographs for this lesson: the course set first, then the other courses' sets, so the picture keeps changing
  const imgs = [nt === 'wnnn' ? st.photos[`course/wnnn-c${pad(c.no)}`] : ''].concat([0, 1, 2, 3, 4, 5, 6, 7].map((i) => photo(st, c.no, mNo + i))).concat([1, 2, 3, 4].map((i) => photo(st, ((c.no + i - 1) % 12) + 1, mNo))).filter((x, i, a) => x && a.indexOf(x) === i);
  // moving pictures: the course's Pexels video clips (and young people's clips for WNNN) are mixed between the photographs;
  // on a data-saver or a slow (2G) connection the lesson keeps to photographs only
  const cn = navigator.connection || {}; const lite = cn.saveData || /(^|-)2g$/.test(cn.effectiveType || '');
  const vids = lite ? [] : [...((st.videos || {})['c' + c.no] || []), ...(nt === 'wnnn' ? ((st.videos || {}).wnnn || []).filter((x, i) => (i + c.no + mNo) % 3 === 0) : [])];
  const seq = []; let vi = (mNo - 1) % Math.max(1, vids.length); imgs.forEach((src, i) => { if (vids.length && i % 2 === 0) { seq.push({ v: vids[vi % vids.length] }); vi++; } seq.push({ i: src }); });
  if (!imgs.length) vids.forEach((v) => seq.push({ v }));
  const frames = seq.map((x, i) => x.v ? h('video', { class: 'pdx-vid__img' + (i === 0 ? ' is-on' : ''), src: x.v, muted: true, loop: true, playsinline: true, preload: i === 0 ? 'auto' : 'none', 'aria-hidden': 'true' }) : h('img', { class: 'pdx-vid__img' + (i === 0 ? ' is-on' : ''), src: x.i, alt: '', loading: i ? 'lazy' : 'eager' }));
  frames.forEach((f) => { if (f.tagName === 'VIDEO') { f.muted = true; f.defaultMuted = true; f.setAttribute('muted', ''); f.setAttribute('playsinline', ''); } });
  const cap = h('div', { class: 'pdx-vid__cap', dir: 'auto', 'aria-live': 'polite' }, h('span', { text: m.title }));
  const bar = h('div', { class: 'pdx-vid__bar' }, h('span'));
  const clock = h('small', { class: 'pdx-vid__clock', text: total ? `${Math.round(total / 60)} min` : '' });
  let part = 0; const audio = new Audio(); audio.preload = 'metadata';
  const load = (i) => { part = i; audio.src = `/assets/courses/voice/${C.used}/${keys[i]}.mp3`; };
  if (keys.length) load(0);
  const play = h('button', { class: 'pdx-vid__play', type: 'button', 'aria-label': t('Play the lesson') }, icon('play', 34));
  const title = h('div', { class: 'pdx-vid__title' }, h('small', { text: tf('Lesson {1}.{2}', c.no, mNo) }), h('b', { text: m.title }));
  const stage = h('div', { class: 'pdx-vid' + (frames.length ? '' : ' pdx-vid--plain') }, ...frames, h('div', { class: 'pdx-vid__shade' }), title, cap, play, bar, clock);
  let shown = '', frame = 0;
  const paint = () => {
    const cue = C.cues[keys[part]]; if (!cue) return; const tm = audio.currentTime; const at = starts[part] + tm;
    bar.firstChild.style.width = `${Math.min(100, (at / (total || 1)) * 100)}%`;
    let i = 0; while (i + 1 < cue.c.length && cue.c[i + 1][0] <= tm) i++;
    const id = part + ':' + i; if (id !== shown) { shown = id; cap.replaceChildren(h('span', { text: cue.c[i][1] })); }
    const f = frames.length ? Math.min(frames.length - 1, Math.floor((at / (total || 1)) * frames.length)) : 0;
    if (f !== frame && frames[f]) { const o = frames[frame]; o?.classList.remove('is-on'); if (o?.tagName === 'VIDEO') o.pause(); frames[f].classList.add('is-on'); if (frames[f].tagName === 'VIDEO' && !audio.paused) frames[f].play().catch(() => {}); frame = f; }
  };
  audio.ontimeupdate = paint;
  const playing = (on) => { const fv = frames[frame]; if (fv && fv.tagName === 'VIDEO') { if (on) fv.play().catch(() => {}); else fv.pause(); } stage.classList.toggle('is-playing', on); play.replaceChildren(icon(on ? 'pause' : 'play', 34)); play.setAttribute('aria-label', on ? t('Pause the lesson') : t('Play the lesson')); };
  const toggle = () => { if (!keys.length) { toast(t('The voice did not play'), t('Read the lesson below. The voice may still be downloading.'), 'info'); return; } if (audio.paused) { audio.play().then(() => playing(true)).catch(() => toast(t('The voice did not play'), t('Read the lesson below. The voice may still be downloading.'), 'info')); } else { audio.pause(); playing(false); } };
  play.onclick = toggle; stage.addEventListener('click', (e) => { if (e.target === stage || e.target.classList.contains('pdx-vid__shade') || e.target.classList.contains('pdx-vid__img')) toggle(); });
  audio.onended = () => { if (part + 1 < keys.length) { load(part + 1); audio.play().catch(() => playing(false)); } else { playing(false); bar.firstChild.style.width = '100%'; load(0); } };
  window.addEventListener('hashchange', () => audio.pause(), { once: true });

  // her answer is kept with her account, so it is there on any phone or computer she signs in on
  const noteKey = `wdos.pdnote.${c.no}.${mNo}`; let timer = 0;
  const saveNote = (v) => api(`/courses/${c.no}/notes/${mNo}`, { body: v }).then(() => { noteState.textContent = t('Saved to your account'); }).catch(() => { noteState.textContent = t('Not saved yet. It will try again when you type.'); });
  const noteState = h('small', { class: 'faint', 'aria-live': 'polite' });
  const notes = h('textarea', { class: 'input', rows: 5, maxlength: 4000, placeholder: t('Write your answer here. It is saved to your account.'), oninput: (e) => { noteState.textContent = t('Saving…'); clearTimeout(timer); timer = setTimeout(() => saveNote(e.target.value), 800); } });
  api(`/courses/${c.no}/notes/${mNo}`).then((r) => {
    let local = ''; try { local = localStorage.getItem(noteKey) || ''; } catch { /* private mode */ }
    if (r.body) notes.value = r.body; else if (local) { notes.value = local; saveNote(local); }
    try { localStorage.removeItem(noteKey); } catch { /* */ }
  }).catch(() => {});
  // the place she stopped: this lesson, and the second of the narration (saved every few seconds while it plays)
  const placeAt = () => Math.round((starts[part] || 0) + (audio.currentTime || 0));
  const savePlace = () => api('/courses/place', { course: c.no, part: String(mNo), seconds: placeAt() }).catch(() => {});
  savePlace(); let lastSaved = 0;
  audio.addEventListener('timeupdate', () => { const n = placeAt(); if (Math.abs(n - lastSaved) >= 10) { lastSaved = n; savePlace(); } });
  audio.addEventListener('pause', savePlace);
  const resumeAt = st.place && st.place.course === c.no && st.place.part === String(mNo) ? st.place.seconds : 0;
  if (resumeAt > 5 && keys.length) { let acc = 0; for (let i = 0; i < keys.length; i++) { if (acc + C.cues[keys[i]].d > resumeAt) { load(i); audio.addEventListener('loadedmetadata', () => { try { audio.currentTime = resumeAt - acc; } catch { /* */ } paint(); }, { once: true }); break; } acc += C.cues[keys[i]].d; } }
  const done = s.modules_done.includes(mNo);
  const finish = h('button', { class: 'btn btn--lime', type: 'button', onclick: async () => {
    finish.disabled = true;
    try { await api(`/courses/${c.no}/modules/${mNo}`, {}); audio.pause(); go(mNo < 5 ? `#/courses/${c.no}/${mNo + 1}` : `#/courses/${c.no}/quiz`); toast(t('Lesson finished'), mNo < 5 ? t('The next lesson is open.') : t('All five lessons are finished. The quiz is open.'), 'ok'); }
    catch (e) { finish.disabled = false; toast(t('Not saved'), e.message, 'bad'); }
  } }, icon('check', 18), mNo < 5 ? t('I have finished this lesson') : t('Finish and go to the quiz'));
  const sec = (ic, title2, ...body) => h('section', { class: 'pdx-sec' }, h('h3', {}, icon(ic, 18), ' ', h('span', { text: title2 })), ...body);
  return h('div', { style: 'display:grid;gap:16px' },
    h('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap' }, h('a', { href: `#/courses/${c.no}`, class: 'btn btn--quiet btn--s' }, icon('back', 16), c.title),
      h('div', { class: 'pdx-dots', 'aria-label': tf('Lesson {1} of 5', mNo) }, [1, 2, 3, 4, 5].map((i) => h('span', { class: (s.modules_done.includes(i) ? 'is-done' : '') + (i === mNo ? ' is-here' : '') })))),
    stage,
    h('section', { class: 'card pdx-lesson' },
      sec('book', t('Understand the idea'), h('p', { class: 'pdx-lead', text: m.concept })),
      sec('spark', t('Why it matters for a volunteer leader'), h('p', { text: T.why.replace('{topic}', m.title) })),
      sec('route', t('How to apply it, step by step'), h('ol', { class: 'pdx-howto' }, T.steps.map((x) => h('li', { text: x.replace('{topic}', tp) })))),
      sec('users', t('A WODDI leadership example'), h('blockquote', { class: 'pdx-quote', text: m.scenario }), h('p', { class: 'faint', text: T.think })),
      sec('tasks', t('Your practice'), h('p', { class: 'pdx-lead', text: m.exercise }), h('p', { class: 'faint', text: T.parts }), notes, noteState),
      sec('target', t('Take it into your service'), h('p', { text: T.reflect.replace('{topic}', tp) }), T.evidence ? h('p', { class: 'faint', text: T.evidence.replace('{topic}', tp) }) : null),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;margin-top:6px' },
        mNo > 1 ? h('a', { class: 'btn btn--ghost', href: `#/courses/${c.no}/${mNo - 1}` }, icon('back', 16), t('Previous lesson')) : null, finish,
        done ? h('small', { class: 'faint', style: 'align-self:center', text: t('You finished this lesson before.') }) : null)));
}

/* ---------------------------------------------------------------- quiz -- */
function quizView(C, st, c, s) {
  if (s.modules_done.length < 5) { setTimeout(() => go(`#/courses/${c.no}`), 0); return h('div'); }
  const ans = Array(10).fill(null); let i = 0;
  const keep = () => api(`/courses/${c.no}/draft`, { answers: ans.map((a) => a || '-').join('') }).catch(() => {});
  api('/courses/place', { course: c.no, part: 'quiz', seconds: 0 }).catch(() => {});
  api(`/courses/${c.no}/draft`).then((r) => { [...(r.answers || '')].forEach((x, k) => { if ('ABCD'.includes(x) && k < 10) ans[k] = x; }); const n = ans.findIndex((a) => !a); i = n < 0 ? 9 : n; paint(); }).catch(() => {});
  const wrap = h('div', { style: 'display:grid;gap:16px' });
  const paint = () => {
    const q = c.quiz[i];
    const opts = q.o.map((o, k) => { const L = 'ABCD'[k];
      return h('button', { type: 'button', class: 'pdx-opt' + (ans[i] === L ? ' is-on' : ''), 'aria-pressed': String(ans[i] === L), onclick: () => { ans[i] = L; keep(); paint(); } }, h('span', { class: 'pdx-opt__l', text: L }), h('span', { text: o })); });
    wrap.replaceChildren(
      h('a', { href: `#/courses/${c.no}`, class: 'btn btn--quiet btn--s', style: 'justify-self:start' }, icon('back', 16), c.title),
      h('section', { class: 'card pdx-quiz' },
        h('div', { class: 'pdx-dots pdx-dots--q' }, ans.map((a, k) => h('span', { class: (a ? 'is-done' : '') + (k === i ? ' is-here' : '') }))),
        h('small', { class: 'gmc__kicker', text: tf('Question {1} of 10', i + 1) }),
        h('h2', { class: 'pdx-q', dir: 'auto', text: q.q }),
        h('div', { class: 'pdx-opts' }, opts),
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;justify-content:space-between' },
          i > 0 ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: () => { i--; paint(); } }, icon('back', 16), t('Previous')) : h('span'),
          i < 9 ? h('button', { class: 'btn', type: 'button', disabled: !ans[i], onclick: () => { i++; paint(); } }, t('Next'), icon('fwd', 16))
            : h('button', { class: 'btn btn--lime', type: 'button', disabled: ans.some((a) => !a), onclick: submit }, icon('check', 18), t('Submit my answers')))));
  };
  const submit = async () => {
    let r; try { r = await api(`/courses/${c.no}/quiz`, { answers: ans }); } catch (e) { toast(t('Not submitted'), e.message, 'bad'); return; }
    const lessons = [...new Set(r.wrong.map((k) => Math.floor(k / 2) + 1))];
    wrap.replaceChildren(
      h('a', { href: `#/courses/${c.no}`, class: 'btn btn--quiet btn--s', style: 'justify-self:start' }, icon('back', 16), c.title),
      h('section', { class: 'card pdx-result ' + (r.passed ? 'is-pass' : 'is-retry') },
        h('div', { class: 'pdx-score' }, h('b', { text: String(r.score) }), h('small', { text: t('out of 10') })),
        h('h2', { class: 'display', text: r.passed ? t('Well done. You passed.') : t('Not yet. Review and try again.') }),
        h('p', { class: 'muted', text: r.passed ? t('Your certificate is ready. Anyone can check it with the QR code printed on it.') : tf('You need {1} or more. The questions you missed come from the lessons below. Read them again, then retake the quiz.', r.pass) }),
        r.wrong.length ? h('div', { style: 'display:grid;gap:8px' }, h('b', { text: tf('Questions to look at again: {1}', r.wrong.map((k) => k + 1).join(', ')) }),
          h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, lessons.map((l) => h('a', { class: 'btn btn--ghost btn--s', href: `#/courses/${c.no}/${l}` }, tf('Lesson {1}: {2}', l, c.modules[l - 1].title))))) : null,
        h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' },
          r.certificate ? h('a', { class: 'btn btn--lime', href: `#/certificate/${r.certificate.code}` }, icon('award', 18), t('View my certificate')) : null,
          r.programme ? h('a', { class: 'btn btn--lime', href: `#/certificate/${r.programme.code}` }, icon('award', 18), t('My programme certificate')) : null,
          !r.passed ? h('button', { class: 'btn', type: 'button', onclick: () => { ans.fill(null); i = 0; paint(); } }, t('Retake the quiz')) : null,
          h('a', { class: 'btn btn--ghost', href: '#/courses' }, t('All courses')))));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  paint();
  return wrap;
}
