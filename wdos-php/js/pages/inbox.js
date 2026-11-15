/* Messages between people in WDOS: one person, or a group chosen by network, leadership level, position and region.
 * A send can be undone for 30 seconds; nobody is told before then. Own messages can be deleted, conversations hidden and restored, drafts saved. */
import { h, t, icon, avatar, card, sk, empty, ago, fmt, toast, input, field, tabs } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';

const LEVELS = [['', 'Every level'], ['country', 'Country'], ['state', 'State or region'], ['lga', 'LGA, district or city'], ['community_cluster', 'Community cluster'], ['chapter', 'Chapter']];
const WHO = { leaders: 'Volunteer leaders', members: 'Members', envoys: 'WODDI Envoys', staff: 'HQ staff', everyone: 'Everyone I can reach' };

/** A bar at the foot of the screen with a countdown and an Undo button. */
export function undoBar(text, seconds, onUndo) {
  document.querySelector('.undobar')?.remove();
  let left = seconds; const count = h('b', { text: String(left) });
  const bar = h('div', { class: 'undobar', role: 'status', 'aria-live': 'polite' }, h('span', { class: 'grow' }, text, ' ', h('small', {}, t('Delivered in'), ' ', count, ' s')),
    h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { clearInterval(tm); bar.remove(); await onUndo(); } }, t('Undo')));
  const tm = setInterval(() => { left -= 1; count.textContent = String(left); if (left <= 0) { clearInterval(tm); bar.remove(); } }, 1000);
  document.body.append(bar); return bar;
}

export async function render(root, params) {
  const seg = params.tab || '';
  if (/^\d+$/.test(seg)) return thread(root, Number(seg));
  if (seg === 'new' || seg === 'draft' || seg === 'to') return compose(root, { draft: seg === 'draft' ? Number(params.id) : 0, to: seg === 'to' ? Number(params.id) : 0 });
  if (seg === 'drafts') return drafts(root);
  return list(root, seg === 'deleted' ? 'deleted' : 'inbox');
}

function head(root, cur) {
  root.append(h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px' },
    tabs([['', 'Inbox'], ['deleted', 'Deleted'], ['drafts', 'Drafts']], cur, '#/inbox'), h('span', { class: 'grow' }),
    h('a', { class: 'btn', href: '#/inbox/new' }, icon('plus', 18), t('New message'))));
}

async function list(root, view) {
  head(root, view === 'deleted' ? 'deleted' : '');
  const box = h('div', {}, sk.rows(5)); const bulk = h('div', { class: 'toolbar', hidden: true });
  root.append(card(view === 'deleted' ? 'Deleted conversations' : 'Conversations', view === 'deleted' ? 'Restore a conversation to bring it back to your inbox. Nothing here is lost.' : 'Messages from HQ, from the leaders above you and from the people in your area.', h('div', { style: 'display:grid;gap:12px' }, bulk, box)));
  const picked = new Set();
  const paintBulk = () => { bulk.hidden = !picked.size; bulk.replaceChildren(h('span', { class: 'tag', text: tf('{1} selected', picked.size) }),
    view === 'deleted' ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => hide(false) }, t('Restore')) : h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { await api('/dm/read', { ids: [...picked] }); picked.clear(); load(); } }, t('Mark as read')),
    view === 'deleted' ? null : h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => hide(true) }, t('Delete'))); };
  async function hide(on) {
    const ids = [...picked]; try { await api('/dm/hide', { ids, hidden: on }); } catch (e) { toast('Not changed', e.message, 'bad'); return; }
    picked.clear(); load();
    if (on) toast(tf('{1} conversations deleted', ids.length), t('Tap here to undo.'), 'ok', async () => { await api('/dm/hide', { ids, hidden: false }); load(); });
  }
  async function load() {
    let d; try { d = await api('/dm' + (view === 'deleted' ? '?view=deleted' : '')); } catch (e) { box.replaceChildren(empty('x', 'Messages did not load', e.message, h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Try again'))); return; }
    paintBulk();
    if (!d.items.length) { box.replaceChildren(empty('send', view === 'deleted' ? 'Nothing deleted' : 'No messages yet', view === 'deleted' ? 'Conversations you delete wait here, so you can restore them.' : 'When someone writes to you, the conversation appears here. You are also told by phone notification and by email.')); return; }
    box.replaceChildren(h('div', { class: 'list reveal' }, d.items.map((c) => {
      const cb = h('input', { type: 'checkbox', 'aria-label': tf('Select the conversation with {1}', c.person.first_name), onclick: (e) => e.stopPropagation() }); cb.checked = picked.has(c.id);
      cb.addEventListener('change', () => { if (cb.checked) picked.add(c.id); else picked.delete(c.id); paintBulk(); });
      return h('div', { class: 'dmrow' + (c.unread ? ' is-unread' : '') }, cb,
        h('a', { class: 'grow dmrow__a', href: '#/inbox/' + c.id }, avatar(c.person, 40),
          h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: `${c.person.first_name} ${c.person.last_name}` }), h('small', { class: 'faint', style: 'display:block', text: c.person.title }),
            h('small', { style: 'display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap' }, c.mine_last ? t('You:') + ' ' : '', c.subject ? h('b', { text: c.subject + ' · ' }) : null, c.snippet)),
          h('div', { style: 'text-align:right;flex:none' }, h('small', { class: 'faint', text: ago(c.last_at) }), c.unread ? h('span', { class: 'badge', style: 'display:block;margin-top:4px', text: String(c.unread) }) : null)));
    })));
  }
  load();
}

async function thread(root, id) {
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/inbox', style: 'margin-bottom:12px' }, t('Back to messages')));
  const box = h('div', {}, sk.rows(4)); root.append(box);
  const reply = h('textarea', { class: 'input', rows: 3, maxlength: 6000, placeholder: t('Write your reply…'), 'aria-label': t('Your reply') });
  async function load() {
    let d; try { d = await api('/dm/' + id); } catch (e) { box.replaceChildren(card('Messages', null, empty('x', 'This conversation did not open', e.message))); return; }
    const p = d.thread.person;
    const msgs = h('div', { class: 'dmthread' }, d.messages.map((m) => h('div', { class: 'dmmsg' + (m.mine ? ' is-mine' : '') },
      m.subject ? h('b', { style: 'display:block', text: m.subject }) : null,
      m.deleted_at ? h('i', { class: 'faint', text: t('This message was deleted.') }) : h('p', { style: 'margin:0;white-space:pre-wrap;overflow-wrap:anywhere', text: m.body }),
      h('small', { class: 'faint' }, ago(m.created_at), m.to_many && m.mine ? ' · ' + t('sent to a group') : '', m.can_undo ? ' · ' + t('not delivered yet') : ''),
      m.mine && !m.deleted_at ? h('button', { class: 'linkbtn', type: 'button', onclick: async () => { if (!confirm(t('Delete this message? The other person will see that a message was deleted.'))) return; try { await api('/dm/message/' + m.id + '/delete', {}); load(); } catch (e) { toast('Not deleted', e.message, 'bad'); } } }, t('Delete')) : null)));
    const send = h('button', { class: 'btn', type: 'button', onclick: async () => {
      const text = reply.value.trim(); if (!text) { reply.focus(); return; } send.disabled = true;
      try { const r = await api(`/dm/${id}/reply`, { body: text }); reply.value = ''; load();
        undoBar(t('Reply sent.'), r.undo_seconds, async () => { try { const u = await api('/dm/undo/' + r.batch_id, {}); reply.value = u.body || text; load(); toast('Not sent', 'Your words are back in the box.', 'info'); } catch (e) { toast('Too late to undo', e.message, 'bad'); } }); }
      catch (e) { toast('Not sent', e.message, 'bad'); } send.disabled = false; } }, icon('send', 18), t('Send'));
    box.replaceChildren(card(`${p.first_name} ${p.last_name}`, p.title, h('div', { style: 'display:grid;gap:14px' }, msgs,
      d.thread.active ? h('div', { style: 'display:grid;gap:8px' }, reply, h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, send, h('small', { class: 'faint', style: 'align-self:center', text: t('The other person is told in the app, on the phone and by email.') }))) : h('p', { class: 'faint', text: t('This person no longer has an active WDOS account.') })), avatar(p, 44)));
    msgs.lastElementChild?.scrollIntoView({ block: 'end' });
  }
  load();
}

async function drafts(root) {
  head(root, 'drafts');
  const box = h('div', {}, sk.rows(3)); root.append(card('Drafts', 'Messages you saved to finish later.', box));
  async function load() {
    let d; try { d = await api('/dm/drafts'); } catch (e) { box.replaceChildren(empty('x', 'Drafts did not load', e.message)); return; }
    box.replaceChildren(d.drafts.length ? h('div', { class: 'list' }, d.drafts.map((x) => h('div', {}, h('a', { class: 'grow', href: '#/inbox/draft/' + x.id }, h('b', { text: x.subject || t('(no subject)') }), h('small', { style: 'display:block', text: (x.body || '').slice(0, 120) }), h('small', { class: 'faint', text: ago(x.updated_at) })),
      h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { await api('/dm/drafts/' + x.id + '/delete', {}); load(); } }, t('Delete'))))) : empty('book', 'No drafts', 'Press Save draft while writing a message to keep it here.'));
  }
  load();
}

async function compose(root, opts) {
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/inbox', style: 'margin-bottom:12px' }, t('Back to messages')));
  let o; try { o = await api('/dm/options'); } catch (e) { root.append(card('New message', null, empty('x', 'This page did not load', e.message))); return; }
  let draft = null; if (opts.draft) { try { draft = (await api('/dm/drafts')).drafts.find((d) => d.id === opts.draft) || null; } catch { draft = null; } }
  const aud0 = draft ? (() => { try { return JSON.parse(draft.audience || '{}'); } catch { return {}; } })() : {};
  const mode = h('select', { class: 'input', 'aria-label': t('Send to') }, h('option', { value: 'one', text: t('One person or a few people') }), o.who.length ? h('option', { value: 'group', text: t('A group') }) : null);
  /* one by one */
  const picked = new Map(); const chips = h('div', { class: 'tagrow' }); const results = h('div', { class: 'picklist' });
  const q = input({ type: 'search', placeholder: t('Search by name, email or member number'), 'aria-label': t('Search people') });
  const paintChips = () => { chips.replaceChildren(...[...picked.values()].map((p) => h('span', { class: 'tag' }, `${p.first_name} ${p.last_name}`, h('button', { class: 'tag__x', type: 'button', 'aria-label': t('Remove'), onclick: () => { picked.delete(p.id); paintChips(); count(); } }, icon('x', 12))))); };
  let qt; q.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(async () => { if (q.value.trim().length < 2) { results.replaceChildren(); return; }
    try { const r = await api('/dm/people?q=' + encodeURIComponent(q.value.trim())); results.replaceChildren(...(r.people.length ? r.people.map((p) => h('button', { type: 'button', class: 'pick', onclick: () => { picked.set(p.id, p); paintChips(); q.value = ''; results.replaceChildren(); count(); } }, avatar(p, 30), h('span', {}, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: p.title })))) : [h('p', { class: 'faint', text: t('No one found inside your area.') })])); } catch (e) { results.replaceChildren(h('p', { class: 'faint', text: e.message })); } }, 250); });
  const oneBox = h('div', { style: 'display:grid;gap:8px' }, chips, q, results);
  /* group */
  const sel = (label, list, val) => { const s = h('select', { class: 'input', 'aria-label': t(label) }, list.map(([v, l]) => h('option', { value: v, text: t(l) }))); if (val !== undefined) s.value = val; return s; };
  const who = sel('Who', o.who.map((w) => [w, WHO[w]]), aud0.who || o.who[0]);
  const net = sel('Network', o.network ? [[o.network, o.network]] : [['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']], aud0.network || o.network || '');
  const lvl = sel('Leadership level', LEVELS, aud0.level || '');
  const pos = sel('Position', [['', 'Every position'], ...o.positions.map((p) => [p.code, p.title_wgmn === p.title_wnnn ? p.title_wgmn : `${p.title_wgmn} / ${p.title_wnnn}`])], aud0.position || '');
  const ctry = o.countries.length ? sel('Country', [['', 'Every country'], ...o.countries.map((c) => [c.code, c.name])], aud0.country || '') : null;
  const state = input({ placeholder: t('State or region (optional)'), maxlength: 120, value: aud0.state || '' });
  const groupBox = h('div', { class: 'grid2', hidden: true }, field('Who', who), field('Network', net), field('Leadership level', lvl, 'Only leaders seated at this level.'), field('Position', pos), ctry ? field('Country', ctry) : null, field('State or region', state, 'Type it exactly as members chose it.'));
  const countLine = h('p', { class: 'muted', style: 'margin:0', 'aria-live': 'polite' });
  const audience = () => mode.value === 'one' ? { people: [...picked.keys()] } : { who: who.value, network: net.value, level: lvl.value, position: pos.value, country: ctry ? ctry.value : '', state: state.value.trim() };
  const label = () => mode.value === 'one' ? [...picked.values()].map((p) => p.first_name + ' ' + p.last_name).join(', ') : [t(WHO[who.value]), net.value, lvl.selectedOptions[0]?.text, pos.value ? pos.selectedOptions[0]?.text : '', ctry && ctry.value ? ctry.selectedOptions[0].text : '', state.value].filter((x) => x && x !== t('Every level')).join(' · ');
  let ct; const count = () => { clearTimeout(ct); ct = setTimeout(async () => {
    if (mode.value === 'one' && !picked.size) { countLine.textContent = t('Choose at least one person.'); return; }
    try { const r = await api('/dm/audience', audience()); countLine.textContent = r.count ? tf('{1} people will receive this, each in a private conversation with you.', fmt(r.count)) + (r.sample.length ? ' ' + r.sample.join(', ') + (r.count > r.sample.length ? '…' : '') : '') : t('Nobody matches this choice.'); }
    catch (e) { countLine.textContent = e.message; } }, 300); };
  [who, net, lvl, pos, ctry].filter(Boolean).forEach((s) => s.addEventListener('change', count)); state.addEventListener('input', count);
  mode.addEventListener('change', () => { oneBox.hidden = mode.value !== 'one'; groupBox.hidden = mode.value === 'one'; count(); });
  if (aud0.who) { mode.value = 'group'; mode.dispatchEvent(new Event('change')); }
  if (opts.to) { try { const r = await api('/dm/people?id=' + opts.to); r.people.forEach((p) => picked.set(p.id, p)); paintChips(); } catch { /* not reachable: the box stays empty */ } }
  const subject = input({ maxlength: 190, placeholder: t('Subject (optional)'), value: draft?.subject || '' });
  const body = h('textarea', { class: 'input', rows: 6, maxlength: 6000, placeholder: t('Write your message…') }); body.value = draft?.body || '';
  const err = h('p', { class: 'err', role: 'alert' }); let draftId = draft?.id || 0;
  const send = h('button', { class: 'btn', type: 'button', onclick: async () => {
    err.textContent = ''; if (!body.value.trim()) { err.textContent = t('Write your message first.'); return; }
    send.disabled = true;
    try { const r = await api('/dm/send', { subject: subject.value, body: body.value, audience: audience(), label: label(), draft_id: draftId });
      const keep = { subject: subject.value, body: body.value }; subject.value = ''; body.value = ''; draftId = 0;
      undoBar(r.recipients === 1 ? t('Message sent.') : tf('Message sent to {1} people.', fmt(r.recipients)), r.undo_seconds, async () => {
        try { const u = await api('/dm/undo/' + r.batch_id, {}); subject.value = u.subject || keep.subject; body.value = u.body || keep.body; toast('Not sent', 'Nobody was told. Your words are back in the box.', 'info'); }
        catch (e) { toast('Too late to undo', e.message, 'bad'); } });
      toast('Sent', r.recipients === 1 ? '' : tf('{1} people', fmt(r.recipients)), 'ok'); }
    catch (e) { err.textContent = e.message; } send.disabled = false; } }, icon('send', 18), t('Send'));
  const save = h('button', { class: 'btn btn--quiet', type: 'button', onclick: async () => { try { const r = await api('/dm/drafts', { id: draftId, subject: subject.value, body: body.value, audience: mode.value === 'group' ? audience() : {} }); draftId = r.id; toast('Draft saved', 'Find it under Drafts.', 'ok'); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, t('Save draft'));
  const discard = draft ? h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { await api('/dm/drafts/' + draft.id + '/delete', {}); go('#/inbox/drafts'); } }, t('Delete draft')) : null;
  root.append(card('New message', app.user.is_hq ? 'Write to one person, or to a group: by network, leadership level, position, country or state.' : 'You can write to HQ, to the leaders above you and to the people in your area.',
    h('div', { style: 'display:grid;gap:14px' }, field('Send to', mode), oneBox, groupBox, countLine, field('Subject', subject), field('Message', body), err,
      h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, send, save, discard),
      h('small', { class: 'faint', text: tf('After you press Send you have {1} seconds to undo. Then each person is told in the app, on the phone and by email.', o.undo_seconds) }))));
  count();
}
