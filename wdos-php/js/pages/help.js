/* Help: self-service answers, the private report lane, and the member's own membership choices. */
import { h, card, sk, empty, fmtDate, toast, input, field, listenBtn, t } from '../ui.js';
import { api } from '../api.js';
import { app, signOut } from '../app.js';

const TOPICS = [
  ['I forgot my PIN or password', 'On the sign-in page choose Forgot password. If you added your fingerprint or face, sign in with it and choose a new PIN in Settings. If neither works, write to WODDI HQ from another member\'s phone or ask your chapter leader to contact HQ. Nobody will ever ask you for a code.'],
  ['I share this phone with someone else', 'Always choose Sign out when you finish. After you sign out, nothing about you stays on this phone. The other person signs in with her own phone number and PIN.'],
  ['I have no chapter yet', 'You are still a full member. WDOS places you as soon as a chapter near you has room. Check that your LGA or city and your community are filled in under Settings.'],
  ['I missed a day of my seven days', 'Nothing is lost. Open My seven-day journey and continue from where you stopped. WDOS gives more time by itself. You do not need to call anyone.'],
  ['I cannot play the video or the audio', 'Every step also has words you can read. Reading is equal to listening. You will not be held back.'],
  ['I want a different language', 'Use the language list at the top of every screen. You can change it at any time.'],
  ['I moved to another place', 'Open Settings and change your state, LGA or city and community. WDOS moves you to a chapter in your new place and keeps your history.'],
  ['I want to stop messages for a while', 'Use Pause my membership below. You stay a member and you can come back at any time.'],
];
export async function render(root) {
  const mine = h('div', {}, sk.rows(2)), life = h('div', {}, sk.rows(2)); const u = app.user;
  const help = h('div', { class: 'list list--stack' }, TOPICS.map(([q, a]) => h('details', { class: 'list__link', style: 'display:block' }, h('summary', { style: 'cursor:pointer;font-weight:600;min-height:44px;display:flex;align-items:center', text: q }), h('p', { class: 'muted', style: 'margin:8px 0', text: a }), listenBtn(() => t(q) + '. ' + t(a)))));
  const cat = h('select', { class: 'input' }, h('option', { value: '', text: 'Choose one' })); const about = input({ maxlength: 190, placeholder: 'Name or membership number, if you know it' });
  const myLeader = h('input', { type: 'checkbox' }); const body = h('textarea', { class: 'input', rows: 5, maxlength: 6000, placeholder: 'Write what happened, in your own words' }); const err = h('p', { class: 'err', role: 'alert' });
  api('/cases/options').then((r) => cat.append(...Object.entries(r.categories).map(([k, l]) => h('option', { value: k, text: l })))).catch(() => {});
  const send = h('button', { class: 'btn', type: 'button', onclick: async () => {
    err.textContent = ''; if (!cat.value) { err.textContent = t('Choose what this is about.'); cat.focus(); return; }
    send.disabled = true;
    try { const r = await api('/cases', { category: cat.value, about: about.value, about_my_leader: myLeader.checked, body: body.value }); toast('Your private report was received', 'Reference ' + r.ref, 'ok'); body.value = ''; about.value = ''; myLeader.checked = false; cat.value = ''; loadMine(); }
    catch (e) { err.textContent = e.message; } finally { send.disabled = false; }
  } }, 'Send privately to WODDI HQ');
  const form = h('div', { style: 'display:grid;gap:14px' },
    h('p', { class: 'muted', style: 'margin:0', text: 'Only WODDI HQ case handlers can read this. Your chapter leader and other leaders cannot. If your report is about a person, that person never sees it. Nothing is decided automatically.' }),
    field('What is this about?', cat), field('Who is it about? (optional)', about), h('label', { style: 'display:flex;gap:10px;align-items:center;min-height:44px' }, myLeader, h('span', { text: 'It is about my chapter leader' })), field('What happened?', body), err, h('div', {}, send));
  async function loadMine() {
    try { const r = await api('/my/cases'); mine.replaceChildren(r.items.length ? h('div', { class: 'list' }, r.items.map((c) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: c.ref + ' · ' + t(c.category) }), h('small', { text: t('Sent') + ' ' + fmtDate(c.created_at) + (c.outcome ? ' · ' + c.outcome : '') })), h('span', { class: 'tag ' + (c.status === 'resolved' || c.status === 'closed' ? 'tag--wnnn' : 'tag--wgmn'), text: c.status_text })))) : empty('check', 'You have sent no private reports', '')); }
    catch (e) { mine.replaceChildren(empty('x', 'This did not load', e.message)); }
  }
  async function loadLife() {
    let d; try { d = await api('/me/lifecycle'); } catch (e) { life.replaceChildren(empty('x', 'This did not load', e.message)); return; }
    const pin = input({ type: 'password', autocomplete: 'current-password', placeholder: 'Your PIN or password', style: 'max-width:240px' }); const why = input({ maxlength: 200, placeholder: 'Reason (optional)', style: 'max-width:320px' }); const erase = h('input', { type: 'checkbox' });
    const closeBox = h('div', { hidden: true, style: 'display:grid;gap:10px;margin-top:10px' }, h('p', { class: 'muted', style: 'margin:0', text: d.is_leader ? 'You hold a leadership seat. Closing your membership also ends your appointment and your seat becomes vacant.' : 'Your membership number and your history are kept so that you can come back. You will be signed out on every phone.' }),
      pin, why, h('label', { style: 'display:flex;gap:10px;align-items:center;min-height:44px' }, erase, h('span', { text: 'Also ask WODDI HQ to remove my personal information' })),
      h('div', {}, h('button', { class: 'btn btn--danger', type: 'button', onclick: async () => { try { await api('/me/withdraw', { password: pin.value, reason: why.value, erase: erase.checked }); toast('Your membership is closed', 'You can come back by writing to WODDI HQ.', 'ok'); signOut(); } catch (e) { toast('Not closed', e.message, 'bad'); } } }, 'Close my membership')));
    life.replaceChildren(h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'kv' }, h('div', {}, h('span', { text: 'My membership now' }), h('b', { text: d.text })), h('div', {}, h('span', { text: 'My Person ID' }), h('b', { text: u.person_id || '' }))),
      d.history.length ? h('div', { class: 'list' }, d.history.map((x) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: x.what })), h('small', { class: 'faint', text: fmtDate(x.at) })))) : null,
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' },
        h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { try { await api('/me/pause', { pause: !d.paused }); toast(d.paused ? 'Welcome back' : 'Your membership is paused', d.paused ? '' : 'You will receive only security messages and replies from HQ.', 'ok'); loadLife(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, d.paused ? 'Come back to my membership' : 'Pause my membership'),
        h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { try { const r = await api('/me/export'); const a = h('a', { href: URL.createObjectURL(new Blob([JSON.stringify(r, null, 2)], { type: 'application/json' })), download: 'my-wdos-record.json' }); document.body.append(a); a.click(); a.remove(); } catch (e) { toast('Not downloaded', e.message, 'bad'); } } }, 'Download everything WDOS holds about me'),
        h('button', { class: 'btn btn--quiet', type: 'button', onclick: () => { closeBox.hidden = !closeBox.hidden; } }, 'Close my membership…')), closeBox));
  }
  root.append(card('Answers you can use now', 'No call and no code needed', help), card('Send a private report', 'A complaint, a safeguarding concern, or a problem with your account', form), card('My private reports', 'Keep the reference. You can follow each one here.', mine), card('My membership', 'Pause, come back, download your record, or close your membership', life));
  loadMine(); loadLife();
}
