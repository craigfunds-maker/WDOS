/* WODDI Community: a space for every level a member belongs to (network, country, state or region, LGA or city,
 * community, chapter), plus topic circles, mentorship spaces and events. Each level space is for one network,
 * so the other network's members are never shown. Posts can be reported; leaders and HQ can hide them. */
import { h, icon, avatar, card, sk, empty, ago, fmtDate, toast, netTag, meter, CH_TAG, linkify, input, field } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';

export async function render(root, params) {
  if (params.tab === 'moderation' && app.user.is_hq) return moderation(root);
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(5))); root.append(box);
  let d; try { d = await api('/community'); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'The community did not load', e.message))); return; }
  const feed = h('div'); let current = '';
  async function open(key, title, sub) {
    current = key; feed.replaceChildren(card(title, sub, sk.rows(3))); feed.scrollIntoView({ behavior: 'smooth', block: 'start' });
    let s; try { s = await api('/community/space?key=' + encodeURIComponent(key)); } catch (e) { feed.replaceChildren(card(title, sub, empty('x', 'This space did not open', e.message))); return; }
    const mod = s.access !== 'member'; const text = h('textarea', { class: 'input', rows: 3, maxlength: 2000, placeholder: 'Write to this space' });
    const send = h('button', { class: 'btn', type: 'button', onclick: async () => { send.disabled = true; try { const r = await api('/community/space', { key, body: text.value }); if (r.held) toast('Waiting for a leader', 'Your post will show once a leader or HQ has looked at it.'); open(key, title, sub); } catch (e) { toast('Not posted', e.message, 'bad'); send.disabled = false; } } }, icon('send', 17), 'Post');
    feed.replaceChildren(card(title, sub, h('div', { style: 'display:grid;gap:14px' }, h('div', { style: 'display:grid;gap:8px' }, text, h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, send, h('a', { class: 'linkbtn', href: '#/messages/new', text: 'Raise a concern privately with WODDI HQ' }))),
      s.posts.length ? h('div', { class: 'chat', style: 'max-height:none' }, s.posts.map((p) => h('div', { class: 'bubble' + (Number(p.user_id) === app.user.id ? ' is-mine' : '') + (p.hidden_at ? ' is-hidden' : '') }, h('small', { text: `${p.first_name} ${p.last_name} · ${ago(p.created_at)}` + (p.hidden_at ? (Number(p.held) ? ' · Waiting for a leader' : ' · Hidden') : '') }), h('p', {}, linkify(p.body)),
        h('div', { class: 'bubble__act' }, Number(p.user_id) !== app.user.id && !p.hidden_at ? h('button', { type: 'button', class: 'linkbtn', onclick: async () => { try { await api(`/community/posts/${p.id}/report`, {}); toast('Reported', 'The leaders of this space and HQ will look at it.', 'ok'); } catch (e) { toast('Not reported', e.message, 'bad'); } } }, 'Report') : null,
          mod ? h('button', { type: 'button', class: 'linkbtn', onclick: async () => { try { await api(`/community/posts/${p.id}/hide`, { restore: !!p.hidden_at }); open(key, title, sub); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, p.hidden_at ? 'Show' : 'Hide') : null))))
        : empty('send', 'Nothing here yet', 'Be the first to greet this space.'))));
  }
  const ch = d.chapter; const parts = [];
  parts.push(card('My chapter', 'Your local WODDI family', ch ? h('a', { class: 'list__link', href: '#/chapter', style: 'padding:0' }, h('div', { class: 'nrow__ico' }, icon('users', 18)), h('div', { class: 'grow' }, h('b', { text: ch.name }), h('small', { text: `${ch.members} of ${ch.capacity} women` }), h('div', { style: 'max-width:260px;margin-top:6px' }, meter(ch.members, ch.capacity, ch.minimum))), h('span', { class: 'tag ' + CH_TAG[ch.status], text: ch.status_label }))
    : h('p', { class: 'muted', style: 'margin:0', text: d.is_hq ? 'HQ staff are not placed in a chapter. Open any chapter from the chapter directory.' : d.chapter_pending })));
  if (d.levels.length) parts.push(card('My WODDI community', 'From your whole network down to your own community. Select one to read and write.', h('div', { class: 'list list--stack' }, d.levels.map((l) => h('button', { type: 'button', class: 'list__link', onclick: () => open(l.key, l.label, l.level + ' space for your network') }, h('div', { class: 'nrow__ico' }, icon('grid', 18)), h('div', { class: 'grow' }, h('b', { text: l.label }), h('small', { text: l.level })), h('span', { class: 'tag num', text: `${l.posts} ${l.posts === 1 ? 'post' : 'posts'}` }))))));
  const circleRow = (c) => h('div', { style: 'flex-wrap:wrap;gap:10px' }, h('div', { class: 'nrow__ico' }, icon(c.kind === 'mentorship' ? 'award' : 'spark', 18)), h('div', { class: 'grow', style: 'min-width:180px' }, h('b', { text: c.name }), h('small', { text: [c.kind === 'mentorship' ? 'Mentorship space' : 'Topic circle', c.about, `${c.members} joined`].filter(Boolean).join(' · ') })), c.network !== 'ALL' ? netTag(c.network) : null,
    c.joined || d.is_hq ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { if (!c.joined) await api(`/community/circles/${c.id}/join`, {}).catch(() => {}); open('sp:' + c.id, c.name, c.about || (c.kind === 'mentorship' ? 'Mentorship space' : 'Topic circle')); } }, 'Open') : null,
    h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api(`/community/circles/${c.id}/${c.joined ? 'leave' : 'join'}`, {}); toast(c.joined ? 'You left the space' : 'You joined', '', 'ok'); location.hash = '#/community'; root.replaceChildren(); render(root, params); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, c.joined ? 'Leave' : 'Join'));
  parts.push(card('Topic circles and mentorship', 'Spaces approved and opened by WODDI HQ. Join the ones that interest you.', d.circles.length ? h('div', { class: 'list' }, d.circles.map(circleRow)) : empty('spark', 'No circles yet', 'WODDI HQ opens topic circles and mentorship spaces. They appear here.')));
  const ev = h('div', {}, sk.rows(2)); parts.push(card('Events and meetings', 'What is coming up for you', ev, h('a', { class: 'btn btn--ghost btn--s', href: '#/meetings', text: 'All meetings' })));
  api('/meetings').then((r) => { const up = (r.items || []).filter((m) => new Date(m.starts_at.replace(' ', 'T') + 'Z') > new Date()).slice(0, 5); ev.replaceChildren(up.length ? h('div', { class: 'list' }, up.map((m) => h('a', { class: 'list__link', href: '#/meetings/' + m.id }, h('div', { class: 'nrow__ico' }, icon('calendar', 18)), h('div', { class: 'grow' }, h('b', { text: m.title }), h('small', { text: fmtDate(m.starts_at, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }))))) : h('p', { class: 'muted', style: 'margin:0', text: 'Nothing is scheduled for you yet.' })); }).catch(() => ev.replaceChildren(h('p', { class: 'muted', style: 'margin:0', text: 'Nothing is scheduled for you yet.' })));
  if (d.is_hq) {
    const nm = input({ maxlength: 160 }), ab = input({ maxlength: 600 }); const kind = h('select', { class: 'input' }, h('option', { value: 'circle', text: 'Topic circle' }), h('option', { value: 'mentorship', text: 'Mentorship space' })); const net = h('select', { class: 'input' }, [['ALL', 'Both networks'], ['WGMN', 'WGMN only'], ['WNNN', 'WNNN only']].map(([v, l]) => h('option', { value: v, text: l })));
    parts.push(card('Open a new space', 'Topic circles and mentorship spaces are opened by HQ, so each one is an approved space.', h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, field('Name', nm), field('Kind', kind), field('What it is for', ab), field('Who can join', net)),
      h('div', { style: 'display:flex;gap:12px;flex-wrap:wrap' }, h('button', { class: 'btn', type: 'button', onclick: async () => { try { await api('/community/circles', { name: nm.value, about: ab.value, kind: kind.value, network: net.value }); toast('Space opened', '', 'ok'); root.replaceChildren(); render(root, params); } catch (e) { toast('Not opened', e.message, 'bad'); } } }, 'Open space'), h('a', { class: 'btn btn--ghost', href: '#/community/moderation', text: 'Posts waiting for review' })))));
  }
  box.replaceChildren(...parts, feed);
}

async function moderation(root) {
  const box = h('div', {}, sk.rows(4)); root.append(h('a', { class: 'btn btn--ghost btn--s', href: '#/community', style: 'justify-self:start' }, 'Back to the community'), card('Posts waiting for review', 'Held by the word list, hidden after three reports, or reported at least once. Nothing is deleted; each decision is logged.', box));
  async function load() {
    let d; try { d = await api('/hq/moderation'); } catch (e) { box.replaceChildren(empty('x', 'The list did not load', e.message)); return; }
    const row = (p, kind) => h('div', { style: 'flex-wrap:wrap;gap:10px' }, h('div', { class: 'grow', style: 'min-width:220px' }, h('b', { text: `${p.first_name} ${p.last_name}` }), h('small', { text: [kind === 'chapter' ? p.place : 'Community space', Number(p.held) ? 'held by the word list' : '', `${p.reports} ${Number(p.reports) === 1 ? 'report' : 'reports'}`, p.hidden_at ? 'hidden' : 'still showing', ago(p.created_at)].filter(Boolean).join(' · ') }), h('p', { style: 'margin:6px 0 0;white-space:pre-wrap', text: p.body })),
      h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { try { await api(kind === 'chapter' ? `/chapters/posts/${p.id}/hide` : `/community/posts/${p.id}/hide`, { restore: !!p.hidden_at }); load(); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, p.hidden_at ? 'Allow and show' : 'Hide'), h('a', { class: 'btn btn--ghost btn--s', href: '#/members/' + p.user_id, text: 'Member record' }));
    const all = [...d.spaces.map((p) => row(p, 'space')), ...d.chapters.map((p) => row(p, 'chapter'))];
    box.replaceChildren(all.length ? h('div', { class: 'list' }, all) : empty('check', 'Nothing is waiting', 'No post is held, hidden or reported.'));
  }
  load();
}
