/* v109.47: region chats and forums. One room for every region (country, state or region, LGA or district, ward or community cluster) in
 * each network. Its members are the leaders serving there; the State Envoy joins her country and state rooms; HQ opens every room.
 * Chat: messages with links, documents, photos and videos. Forum: topics with replies, for things to keep and come back to.
 * The server decides who may open what; this page draws what it is given. */
import { h, t, tf, icon, avatarOn, card, sk, empty, agoIn, fmtDate, toast, linkify, netTag, flag } from '../ui.js';
import { api } from '../api.js';
import { app, go } from '../app.js';
import { uploadButton } from '../files.js';
import { mapUrl } from './leadmap.js';

const KEYS = ['network', 'cc', 'state', 'lga', 'comm'];
const qs = () => new URLSearchParams(location.hash.split('?')[1] || '');
export const roomUrl = (net, place, extra = {}) => { const p = new URLSearchParams({ network: net }); ['cc', 'state', 'lga', 'comm'].forEach((k) => place[k] && p.set(k, place[k])); Object.entries(extra).forEach(([k, v]) => v && p.set(k, v)); return '#/rooms/r?' + p.toString(); };
const kb = (n) => n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';

function filesView(files) {
  if (!files || !files.length) return null;
  return h('div', { class: 'rm-files' }, files.map((f) => {
    const url = '/api/files/' + f.id;
    if (/^image\//.test(f.mime)) return h('a', { href: url, target: '_blank', rel: 'noopener' }, h('img', { src: url, alt: f.name, loading: 'lazy' }));
    if (/^video\//.test(f.mime)) return h('video', { src: url, controls: true, preload: 'metadata' });
    if (/^audio\//.test(f.mime)) return h('audio', { src: url, controls: true, preload: 'none' });
    return h('a', { class: 'rm-file', href: url + '?dl=1', target: '_blank', rel: 'noopener' }, icon('book', 16), h('span', { text: `${f.name} · ${kb(f.size)}` }));
  }));
}
/** What she is about to send: the text, the files she attached. */
function composer(placeholder, onSend, opts = {}) {
  const files = []; const list = h('div', { class: 'rm-files' });
  const title = opts.title ? h('input', { class: 'input', placeholder: t('Topic title'), 'aria-label': t('Topic title') }) : null;
  const text = h('textarea', { class: 'input', rows: opts.rows || 2, placeholder: t(placeholder), 'aria-label': t(placeholder) });
  const up = uploadButton(t('Attach a document, photo or video'), 'room', async (f) => { files.push(f.id); list.append(h('span', { class: 'rm-file' }, icon('check', 14), h('span', { text: f.name }))); });
  const send = h('button', { class: 'btn btn--primary', type: 'button' }, icon('send', 16), h('span', { text: opts.title ? t('Post the topic') : t('Send') }));
  send.addEventListener('click', async () => {
    send.disabled = true;
    try { await onSend({ title: title ? title.value : '', body: text.value, files: [...files] }); text.value = ''; if (title) title.value = ''; files.length = 0; list.replaceChildren(); }
    catch (e) { toast(t('Not sent'), e.message, 'bad'); } finally { send.disabled = false; }
  });
  if (!opts.title) text.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) send.click(); });
  return h('div', { class: 'rm-compose' }, title, h('div', { class: 'rm-compose__row' }, text, send), list, up,
    h('small', { class: 'faint', text: 'Paste a web link and it becomes tappable. A video larger than 10 MB: share its link instead.' }));
}
function msgView(m, onRemove, mine) {
  return h('div', { class: 'rm-msg' + (mine ? ' is-me' : '') }, avatarOn(m.author, 34),
    h('div', { class: 'rm-bubble' }, h('small', {}, h('b', { text: `${m.author.first_name} ${m.author.last_name}` }), ' · ' + agoIn(m.created_at)),
      m.body ? h('p', {}, linkify(m.body)) : null, filesView(m.files),
      m.can_remove ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', style: 'justify-self:start', onclick: () => onRemove(m) }, t('Remove')) : null));
}

export async function render(root, params) {
  if (params.room) return room(root);
  const box = h('div', {}, sk.rows(4));
  root.append(card('Region chats and forums', 'One chat and one forum for each region you serve in. Everyone serving in the region is in it. Share links, documents, photos and videos.', box));
  let d; try { d = await api('/rooms'); } catch (e) { box.replaceChildren(empty('send', 'Region chats did not open', e.message)); return; }
  if (!d.rooms.length) { box.replaceChildren(empty('send', 'No region chat yet', d.is_hq ? 'Open any region on the leadership map and choose Region chat. Rooms where something was said appear here.' : 'Your region chats appear when WODDI HQ records your seat.', d.is_hq ? h('a', { class: 'btn btn--quiet btn--s', href: '#/leadmap', text: 'Open the leadership map' }) : null)); return; }
  box.replaceChildren(h('div', { class: 'rm-list' }, d.rooms.map((r) => h('a', { class: 'rm-item', href: roomUrl(r.network, r.place) },
    h('div', { class: 'nrow__ico' }, icon('users', 20)),
    h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: `${r.name} · ${t(r.level)}` }), h('small', { class: 'faint', style: 'display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap', text: r.last ? `${r.last.by}: ${r.last.text} · ${agoIn(r.last.at)}` : tf('{1} leaders · nothing said yet', r.members_count) })),
    netTag(r.network), r.unread ? h('span', { class: 'rm-badge', text: String(r.unread) }) : null))));
}

async function room(root) {
  const q = qs(); const params = {}; KEYS.forEach((k) => q.get(k) && (params[k] = q.get(k)));
  const tab = q.get('tab') || 'chat'; const qstr = new URLSearchParams(params).toString();
  const head = h('div', { class: 'card' }, h('div', { class: 'card__body' }, sk.rows(1))); const body = h('div', { style: 'display:grid;gap:16px' });
  root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/rooms', style: 'justify-self:start;margin-bottom:12px', text: 'All my region chats' }), head, body);
  let R; try { R = (await api('/rooms/room?' + qstr)).room; } catch (e) { head.replaceChildren(h('div', { class: 'card__body' }, empty('send', 'This region chat did not open', e.message))); return; }
  head.replaceChildren(h('div', { class: 'card__body', style: 'display:grid;gap:12px' },
    h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, flag(R.place.cc), h('div', { class: 'grow' }, h('h2', { class: 'display', style: 'margin:0;font-size:24px', text: R.name }),
      h('small', { class: 'faint', text: tf('{1} · {2} leaders · {3} online now', t(R.level), R.members.length, R.online) })), netTag(R.network),
      h('a', { class: 'btn btn--ghost btn--s', href: mapUrl(R.place, { network: R.network }), text: 'See this region on the map' })),
    h('div', { class: 'seg rtabs', role: 'tablist' }, [['chat', 'Group chat'], ['forum', 'Forum'], ['people', tf('Members ({1})', R.members.length)]].map(([k, l]) => h('a', { role: 'tab', class: 'seg__a', 'aria-pressed': String(k === tab), href: roomUrl(R.network, R.place, { tab: k }), text: l })))));
  const remove = async (m) => { if (!confirm(t('Remove this message from the room?'))) return; try { await api(`/rooms/msg/${m.id}/remove`, {}); toast(t('Removed'), '', 'ok'); refresh(); } catch (e) { toast(t('Not removed'), e.message, 'bad'); } };
  let refresh = () => {};
  if (tab === 'people') {
    body.append(card('Who is in this room', 'Green: online now. Grey: offline.', h('div', { class: 'list' }, R.members.map((m) => h('div', {}, avatarOn(m, 40), h('div', { class: 'grow' }, h('b', { text: `${m.first_name} ${m.last_name}` }), h('small', { text: `${t(m.title)} · ${m.place}` })),
      h('small', { class: 'faint', text: m.last_seen_at ? agoIn(m.last_seen_at) : t('Not signed in yet') }))))));
    return;
  }
  if (tab === 'forum') {
    const topicId = q.get('topic');
    if (topicId) {
      const tb = h('div', {}, sk.rows(3)); body.append(h('a', { class: 'btn btn--ghost btn--s', href: roomUrl(R.network, R.place, { tab: 'forum' }), style: 'justify-self:start', text: 'All topics' }), tb);
      refresh = async () => {
        let d; try { d = await api('/rooms/topic/' + encodeURIComponent(topicId)); } catch (e) { tb.replaceChildren(empty('x', 'This topic did not open', e.message)); return; }
        const T = d.topic;
        tb.replaceChildren(card(T.title, tf('Started by {1} {2}, {3}', T.author.first_name, T.author.last_name, fmtDate(T.created_at)), h('div', { style: 'display:grid;gap:14px' },
          h('div', { class: 'rm-bubble' }, T.body ? h('p', {}, linkify(T.body)) : null, filesView(T.files), T.can_remove ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', style: 'justify-self:start', onclick: () => remove(T) }, t('Remove the topic')) : null),
          h('b', { text: tf('{1} replies', d.replies.length) }), h('div', { class: 'rm-chat', style: 'max-height:none' }, d.replies.map((m) => msgView(m, remove, m.author.id === app.user.id))),
          composer('Write your reply', async (x) => { await api(`/rooms/topic/${T.id}/reply`, { body: x.body, files: x.files }); refresh(); }, { rows: 3 }))));
      };
      refresh(); return;
    }
    const list = h('div', { class: 'rm-list' }, sk.rows(3));
    body.append(card('Start a topic', 'For plans, reports and questions the whole region should see and come back to. Everyone in the room is told.', composer('What is it about?', async (x) => { const r = await api('/rooms/forum', { ...params, title: x.title, body: x.body, files: x.files }); go(roomUrl(R.network, R.place, { tab: 'forum', topic: r.id })); }, { title: true, rows: 4 })),
      card('Topics', null, list));
    refresh = async () => {
      let d; try { d = await api('/rooms/forum?' + qstr); } catch (e) { list.replaceChildren(empty('x', 'The forum did not open', e.message)); return; }
      list.replaceChildren(...(d.topics.length ? d.topics.map((T) => h('button', { class: 'rm-topic', type: 'button', onclick: () => go(roomUrl(R.network, R.place, { tab: 'forum', topic: T.id })) },
        h('b', { text: T.title }), h('small', { class: 'faint', text: tf('{1} {2} · {3} replies · last {4}', T.author.first_name, T.author.last_name, T.replies, agoIn(T.last_at)) }), T.files.length ? h('small', { class: 'faint', text: tf('{1} file(s) attached', T.files.length) }) : null))
        : [empty('book', 'No topic yet', 'Start the first one above.')]));
    };
    refresh(); return;
  }
  // the group chat: the last 80 messages, then new ones every few seconds while the page is open
  const chat = h('div', { class: 'rm-chat', role: 'log', 'aria-live': 'polite' }, sk.rows(3)); let last = 0, timer = null;
  const add = (ms, initial) => { if (initial) chat.replaceChildren(); ms.forEach((m) => { chat.append(msgView(m, remove, m.author.id === app.user.id)); last = Math.max(last, m.id); }); if (ms.length) chat.scrollTop = chat.scrollHeight; };
  refresh = async () => { try { const d = await api('/rooms/chat?' + qstr); last = 0; add(d.messages, true); if (!d.messages.length) chat.replaceChildren(empty('send', 'No message yet', 'Say hello to the leaders of this region.')); } catch (e) { chat.replaceChildren(empty('x', 'The chat did not open', e.message)); } };
  const poll = async () => { if (!chat.isConnected) { clearInterval(timer); return; } if (document.hidden) return; try { const d = await api('/rooms/chat?' + qstr + '&after=' + last); if (d.messages.length) { if (!last) chat.replaceChildren(); add(d.messages); } } catch { /* the next poll tries again */ } };
  body.append(card('Group chat', tf('Everyone serving in {1} ({2}) reads this.', R.name, R.network), h('div', { style: 'display:grid;gap:12px' }, chat,
    composer('Write a message', async (x) => { await api('/rooms/chat', { ...params, body: x.body, files: x.files }); await poll(); }))));
  await refresh(); timer = setInterval(poll, 6000);
}
