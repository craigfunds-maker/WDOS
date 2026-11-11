/* A chapter: the final operational level. Its own space, its roster, its leaders. A member sees her own chapter;
 * the chapter's leaders and WODDI HQ see contact details and the tools. The other network's members are never listed. */
import { h, icon, avatar, card, sk, empty, ago, fmtDate, toast, netTag, flag, meter, CH_TAG, linkify, input } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';
import { kpi } from './hq.js';

export async function render(root, params) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(5))); root.append(box);
  let id = params.id;
  if (!id) {
    let m; try { m = await api('/my/chapter'); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'Your chapter did not load', e.message))); return; }
    id = m.chapter ? m.chapter.id : m.leads;
    if (!id) { box.replaceChildren(card('My chapter', null, h('div', { style: 'display:grid;gap:12px' }, h('div', { class: 'notice' }, h('b', { text: app.user.is_hq ? 'HQ staff are not placed in a chapter' : 'Your chapter placement is pending' }), h('p', { style: 'margin:6px 0 0', text: app.user.is_hq ? 'Open the chapter directory to see every chapter.' : m.pending })), h('div', {}, h('a', { class: 'btn btn--quiet', href: app.user.is_hq || app.user.is_leader ? '#/chapters' : '#/settings', text: app.user.is_hq || app.user.is_leader ? 'Open the chapter directory' : 'Open Settings' }))))); return; }
  }
  async function load() {
    let d; try { d = await api('/chapters/' + id); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This chapter did not open', e.message))); return; }
    const c = d.chapter, lead = d.access !== 'member', hq = d.access === 'hq'; const picked = new Set();
    const head = h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('small', { class: 'faint', text: [c.community, c.lga, c.state_region, c.country].filter(Boolean).join(', ') }), h('h1', { class: 'display', text: c.name }),
      h('div', { class: 'hero__meta' }, flag(c.country_code), netTag(c.network), h('span', { class: 'tag ' + CH_TAG[c.status], text: c.status_label }), h('span', { class: 'tag num', text: c.code }), c.diaspora ? h('span', { class: 'tag', text: 'Diaspora' }) : null),
      h('div', { style: 'max-width:420px;margin-top:14px;display:grid;gap:6px' }, meter(c.members, c.capacity, c.minimum), h('small', { text: `${c.members} of ${c.capacity} women. A chapter is active from ${c.minimum}.` }))),
      lead && c.plan_next ? h('div', { class: 'notice', style: 'max-width:300px' }, h('b', { text: 'Plan the next chapter' }), h('p', { style: 'margin:6px 0 0', text: `This chapter is close to ${c.capacity}. WDOS opens the next one by itself when it is full.` })) : null);
    const parts = [head];
    if (d.stats) parts.push(h('div', { class: 'row row--4' }, kpi('users', 'Members', c.members, [h('span', { text: `${d.stats.active_7d} active this week` })]), kpi('check', 'Completed seven days', d.stats.journey_active, [h('span', { text: `${d.stats.journey_open} still on the journey` })]), kpi('route', 'Referrals by members', d.stats.referrals, [h('span', { text: 'Women who registered' })]), kpi('plus', 'Waiting nearby', d.stats.pending_nearby, [h('span', { text: 'Same area, not yet placed' })])));
    /* the chapter space */
    const text = h('textarea', { class: 'input', rows: 3, placeholder: 'Write to your chapter', maxlength: 2000 }); const notice = h('input', { type: 'checkbox', id: 'cp-notice' });
    const send = h('button', { class: 'btn', type: 'button', onclick: async () => { send.disabled = true; try { await api(`/chapters/${id}/posts`, { body: text.value, notice: notice.checked }); load(); } catch (e) { toast('Not posted', e.message, 'bad'); send.disabled = false; } } }, icon('send', 17), 'Post');
    const posts = d.posts.length ? h('div', { class: 'chat', style: 'max-height:none' }, d.posts.map((p) => h('div', { class: 'bubble' + (p.kind === 'notice' ? ' is-hq' : '') + (p.hidden_at ? ' is-hidden' : '') }, h('small', { text: `${p.first_name} ${p.last_name}` + (p.kind === 'notice' ? ' · Chapter notice' : '') + ' · ' + ago(p.created_at) + (p.hidden_at ? ' · Hidden' : '') }), h('p', {}, linkify(p.body)),
      h('div', { class: 'bubble__act' }, Number(p.user_id) !== app.user.id && !p.hidden_at ? h('button', { type: 'button', class: 'linkbtn', onclick: async () => { try { await api(`/chapters/posts/${p.id}/report`, { reason: '' }); toast('Reported', 'The chapter leaders and HQ will look at it.', 'ok'); load(); } catch (e) { toast('Not reported', e.message, 'bad'); } } }, 'Report') : null,
        lead ? h('button', { type: 'button', class: 'linkbtn', onclick: async () => { try { await api(`/chapters/posts/${p.id}/hide`, { restore: !!p.hidden_at }); load(); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, p.hidden_at ? 'Show again' : 'Hide') : null, lead && p.reports ? h('small', { class: 'faint', text: `${p.reports} reported` }) : null))))
      : empty('send', 'Nothing here yet', 'Greet your chapter. Notices from your chapter leaders appear here too.');
    parts.push(h('div', { class: 'row row--2' },
      card('Chapter space', 'For this chapter only. Be kind and respectful.', h('div', { style: 'display:grid;gap:14px' }, h('div', { style: 'display:grid;gap:8px' }, text, h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, send, lead ? h('label', { class: 'check', for: 'cp-notice' }, notice, h('span', { text: 'Send as a chapter notice to every member' })) : null)), posts)),
      h('div', { style: 'display:grid;gap:18px;align-content:start' },
        card('Chapter leaders', null, d.leaders.length ? h('div', { class: 'list' }, d.leaders.map((l) => h('div', {}, avatar(l, 40), h('div', { class: 'grow' }, h('b', { text: `${l.first_name} ${l.last_name}` }), h('small', { text: l.title + (Number(l.he_for_she) ? ' · He-for-She Champion' : '') }))))) : empty('users', 'No leader appointed yet', 'WODDI HQ appoints chapter leaders. The chapter works meanwhile.')),
        card('Coming up', 'Chapter meetings', d.meetings.length ? h('div', { class: 'list' }, d.meetings.map((m) => h('a', { class: 'list__link', href: '#/meetings/' + m.id }, h('div', { class: 'nrow__ico' }, icon('calendar', 18)), h('div', { class: 'grow' }, h('b', { text: m.title }), h('small', { text: fmtDate(m.starts_at, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }))))) : h('p', { class: 'muted', style: 'margin:0', text: 'No chapter meeting is scheduled.' })))));
    /* roster */
    const sibs = h('select', { class: 'input', style: 'max-width:280px' }, h('option', { value: 'new', text: 'A new chapter in this area (split)' }));
    if (hq) api(`/chapters?network=${c.network}&country=${c.country_code}&lga=${encodeURIComponent(c.lga || '(none)')}&community=${encodeURIComponent(c.community || '(none)')}`).then((r) => sibs.append(...r.items.filter((x) => x.id !== c.id).map((x) => h('option', { value: x.id, text: `${x.name} (${x.members} of ${x.capacity})` })))).catch(() => {});
    const move = h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { if (!picked.size) { toast('Tick the members to move', '', 'bad'); return; } move.disabled = true;
      try { if (sibs.value === 'new') { const r = await api(`/chapters/${id}/split`, { user_ids: [...picked] }); toast('New chapter opened', `${r.moved} moved. Their history is kept.`, 'ok'); } else { for (const uid of picked) await api(`/chapters/${sibs.value}/transfer`, { user_id: uid }); toast('Members moved', 'Their history is kept.', 'ok'); } load(); } catch (e) { toast('Not moved', e.message, 'bad'); move.disabled = false; } } }, 'Move ticked members');
    parts.push(card('Members', d.full ? `${d.roster.length} women in this chapter. Contact details are for the chapter leaders and HQ.` : `${d.roster.length} women in your chapter`, h('div', { class: 'roster' }, d.roster.map((m) => h('div', { class: 'roster__m' }, hq ? h('input', { type: 'checkbox', 'aria-label': 'Select', onchange: (e) => (e.target.checked ? picked.add(m.id) : picked.delete(m.id)) }) : null, avatar(m, 40),
      h('div', { class: 'grow' }, hq ? h('a', { href: '#/members/' + m.id, text: `${m.first_name} ${m.last_name}` }) : h('b', { text: `${m.first_name} ${m.last_name}` }), d.full ? h('small', { text: [m.member_no, m.phone, m.last_seen_at ? 'seen ' + ago(m.last_seen_at) : 'not yet signed in'].filter(Boolean).join(' · ') }) : h('small', { text: 'Joined ' + fmtDate(m.started_at) }))))),
      hq ? h('div', { class: 'toolbar' }, sibs, move) : null));
    if (hq) {
      const into = input({ type: 'number', placeholder: 'Chapter number, e.g. 12', style: 'max-width:220px', min: 1 });
      parts.push(card('HQ tools', 'Every change is logged. Membership history is kept.', h('div', { style: 'display:flex;gap:12px;flex-wrap:wrap;align-items:center' },
        c.status === 'eligible' ? h('button', { class: 'btn btn--s', type: 'button', onclick: async () => { try { await api(`/chapters/${id}/activate`, {}); toast('Chapter activated', '', 'ok'); load(); } catch (e) { toast('Not activated', e.message, 'bad'); } } }, 'Activate this chapter') : null,
        into, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api(`/chapters/${id}/merge`, { into: Number(into.value) }); toast('Chapters merged', '', 'ok'); location.hash = '#/chapter/' + Number(into.value); } catch (e) { toast('Not merged', e.message, 'bad'); } } }, 'Merge this chapter into that one'),
        h('small', { class: 'faint', text: `The number is the last part of the code. This chapter is ${c.id}.` }))));
      if (d.history && d.history.length) parts.push(card('Membership history', 'Women who were in this chapter before', h('div', { class: 'list' }, d.history.map((x) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: `${x.first_name} ${x.last_name}` }), h('small', { text: `${fmtDate(x.started_at)} to ${fmtDate(x.ended_at)} · ${x.end_reason || 'left'}` })))))));
    }
    parts.push(h('small', { class: 'faint', text: 'Figures as of ' + fmtDate(d.as_of, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + ' (GMT), counted from membership records.' }));
    box.replaceChildren(...parts);
  }
  load();
}
