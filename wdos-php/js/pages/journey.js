/* The seven-day member journey. Self-service from the first day to the automatic result: no call, no code, nobody to wait for.
 * Every step can be read or listened to. */
import { h, icon, card, sk, empty, fmtDate, toast, listenBtn, meter, CH_TAG } from '../ui.js';
import { api } from '../api.js';
import { kpi } from './hq.js';
import { app, refreshUser } from '../app.js';

const ST = { in_progress: ['In progress', ''], retry: ['Something to finish', 'tag--wgmn'], review: ['Being looked at by HQ', ''], restricted: ['Being looked at by HQ', ''], active: ['Active member', 'tag--wnnn'] };

export async function render(root) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(5))); root.append(box);
  let shown = 0;
  const paint = (d) => {
    if (!d.journey) {
      box.replaceChildren(card('Your seven-day journey', 'You joined before the seven-day journey began, so you are already an active member.', h('div', { style: 'display:grid;gap:14px' },
        h('p', { class: 'muted', style: 'margin:0', text: 'You are welcome to take the seven short steps if you wish. They introduce WODDI, your chapter and how to invite other women.' }),
        h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async () => { try { paint(await api('/journey/start', {})); refreshUser(); } catch (e) { toast('Not started', e.message, 'bad'); } } }, 'Start the seven steps'))))); return; }
    const j = d.journey; const st = ST[j.status] || ST.in_progress; const active = j.status === 'active';
    if (!shown) shown = (d.days.find((x) => x.open && !x.done) || d.days[Math.max(0, j.open_days - 1)]).day;
    const act = async (day, body) => { try { const r = await api('/journey/' + day, body || {}); paint(r); refreshUser(); if (r.result) toast(r.result.status === 'active' ? 'You are now an active member' : 'Result ready', r.result.status === 'active' ? 'Welcome to WODDI.' : 'See what is left below.', r.result.status === 'active' ? 'ok' : undefined); } catch (e) { toast('Not saved', e.message, 'bad'); } };
    const head = h('section', { class: 'card jhead' }, h('div', { class: 'card__body', style: 'display:grid;gap:14px' },
      h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, h('div', { class: 'grow' }, h('h2', { class: 'display', style: 'margin:0;font-size:26px', text: active ? 'You are an active WODDI member' : 'Your seven days with WODDI' }),
        h('p', { class: 'muted', style: 'margin:4px 0 0', text: active ? 'You finished your seven days. Your chapter and your network are open to you.' : 'One short step each day. Read it or press Listen. WDOS does the checking by itself.' })),
        h('span', { class: 'tag ' + st[1], text: st[0] }), listenBtn(() => [active ? 'You are an active WODDI member' : 'Your seven days with WODDI', ...d.days.filter((x) => x.open && !x.done).slice(0, 1).map((x) => x.title + '. ' + x.text)])),
      h('div', { class: 'jbar', role: 'img', 'aria-label': tf('{1} of {2} steps done', j.done, j.total) }, d.days.map((x) => h('span', { class: x.done ? 'is-done' : x.open ? 'is-open' : '' }))),
      h('small', { class: 'faint', text: tf('{1} of {2} steps done', j.done, j.total) + (active ? '' : ` · ${j.extensions ? 'Extended to' : 'Finish by'} ${fmtDate(j.deadline)}`) }),
      j.reasons.length && !active ? h('div', { class: 'notice' }, h('b', { text: j.status === 'retry' ? 'What is left to do' : 'Please note' }), h('ul', { style: 'margin:8px 0 0;padding-inline-start:20px' }, j.reasons.map((r) => h('li', { text: r.text })))) : null));
    const dayCard = (x) => {
      const body = h('div', { style: 'display:grid;gap:12px' }, h('p', { style: 'margin:0;font-size:18px;font-weight:600;line-height:1.45;max-width:60ch', text: x.summary }), h('p', { class: 'muted', style: 'margin:0;font-size:16px;line-height:1.55;max-width:60ch', text: x.text }));
      const done = (label = 'Done') => h('button', { class: 'btn', type: 'button', onclick: () => act(x.day) }, icon('check', 18), label);
      if (!x.open) body.append(h('small', { class: 'faint', text: 'Opens on ' + fmtDate(x.opens_on) }));
      else if (x.kind === 'referral') {
        const url = location.origin + '/#/r/' + d.referrals.code; const r = d.referrals;
        body.append(h('div', { class: 'jref' }, h('code', { text: url }), h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async () => { try { if (navigator.share) await navigator.share({ title: 'WODDI', text: 'Join me in WODDI', url }); else { await navigator.clipboard.writeText(url); toast('Link copied', 'Paste it in WhatsApp or a text message.', 'ok'); } } catch { /* closed the share sheet */ } } }, icon('send', 16), 'Share my link')),
          h('div', { class: 'jcount' }, [['Opened my link', r.opened], ['Registered', r.registered], ['Completed seven days', r.verified]].map(([l, n]) => h('div', {}, h('b', { class: 'num', text: String(n) }), h('small', { text: l })))),
          h('small', { class: 'faint', text: tf('Your aim is {1} women. You can keep inviting after this step.', r.target) }));
        if (!x.done) body.append(h('div', {}, done('I understand')));
      } else if (x.kind === 'chapter') {
        body.append(d.chapter ? h('div', { class: 'panel', style: 'display:grid;gap:8px' }, h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { style: 'font-size:18px', text: d.chapter.name }), h('span', { class: 'tag ' + CH_TAG[d.chapter.status], text: d.chapter.status_label })), meter(d.chapter.members, d.chapter.capacity, d.chapter.minimum), h('small', { class: 'faint', text: tf('{1} of {2} women', d.chapter.members, d.chapter.capacity) }), h('div', {}, h('a', { class: 'btn btn--quiet btn--s', href: '#/chapter', text: 'Open my chapter' })))
          : h('div', { class: 'notice' }, h('b', { text: 'Your chapter placement is pending' }), h('p', { style: 'margin:6px 0 0', text: d.chapter_pending }), h('div', { style: 'margin-top:10px' }, h('a', { class: 'btn btn--quiet btn--s', href: '#/settings', text: 'Open Settings' }))));
        if (!x.done) body.append(h('div', {}, done()));
      } else if (x.kind === 'screening') {
        const ans = { ...(x.answers || {}) }; const qs = h('div', { style: 'display:grid;gap:14px' });
        d.questions.forEach((qq) => { const row = h('div', { class: 'jq' }); const opts = Object.entries(qq.options); const btns = opts.map(([v, l]) => { const b = h('button', { type: 'button', class: 'jq__b' }, l); b.onclick = () => set(v); return [v, b]; });
          const set = (v) => { ans[qq.key] = v; btns.forEach(([bv, b]) => b.setAttribute('aria-pressed', String(bv === v))); }; set(ans[qq.key] || '');
          row.append(h('div', { class: 'jq__t' }, h('span', { text: qq.text }), listenBtn([qq.text, ...opts.map((o) => o[1])], 'Listen')), h('div', { class: 'jq__yn', style: opts.some((o) => o[1].length > 6) ? 'grid-template-columns:1fr' : '' }, btns.map((x) => x[1]))); qs.append(row); });
        body.append(qs, h('div', {}, h('button', { class: 'btn', type: 'button', onclick: () => { if (d.questions.some((qq) => !ans[qq.key])) { toast('Please answer every question', '', 'bad'); return; } act(5, { answers: ans }); } }, x.done ? 'Save my answers again' : 'Save my answers')));
      } else if (x.kind === 'final') {
        if (!active) body.append(h('div', {}, h('button', { class: 'btn', type: 'button', onclick: () => act(7) }, icon('check', 18), 'Check my seven days')));
      } else if (x.kind === 'read' && !x.done) body.append(h('div', {}, done()));
      else if (x.kind === 'read' && x.day === 6) body.append(h('div', {}, h('a', { class: 'btn btn--quiet btn--s', href: 'https://woddiinstitute.com', target: '_blank', rel: 'noopener', text: 'Open the WODDI Institute' })));
      return h('section', { class: 'card jday' + (x.done ? ' is-done' : x.open ? ' is-open' : ' is-locked') }, h('div', { class: 'card__body', style: 'display:grid;gap:12px' },
        h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, h('span', { class: 'jday__n', text: x.done ? '' : String(x.day) }, x.done ? icon('check', 20) : null), h('div', { class: 'grow' }, h('small', { class: 'faint', text: 'Day ' + x.day }), h('h3', { style: 'margin:0;font-size:20px', text: x.title })),
          x.open ? listenBtn([x.title, x.summary, x.text]) : null), body));
    };
    /* the prototype's layout: four figures, the seven days to pick from, the chosen day, and the chapter placement beside it */
    const r = d.referrals; const pick = h('div', { class: 'jdays', role: 'tablist', 'aria-label': 'The seven days' }, d.days.map((x) => { const b = h('button', { type: 'button', class: x.done ? 'is-done' : '', 'aria-pressed': String(x.day === shown) }, h('b', { text: String(x.day) }), h('small', { text: x.done ? 'Done' : x.open ? 'Open' : 'Later' })); b.onclick = () => { shown = x.day; paint(d); }; return b; }));
    const ch = d.chapter; const place = card('My chapter placement', ch ? 'Placed automatically by WDOS' : 'Pending placement', ch ? h('div', { class: 'kv' }, [['Network', ch.network], ['Community', ch.community || ch.lga || ch.state_region], ['Chapter', ch.name], ['Active seats', `${ch.members} / ${ch.capacity}`], ['Status', ch.status_label]].map(([a, b]) => h('div', {}, h('span', { text: a }), h('b', { text: b }))))
      : h('p', { class: 'notice', style: 'margin:0', text: d.chapter_pending }), h('a', { class: 'btn btn--ghost btn--s', href: '#/chapter', text: 'Open' }));
    place.append(h('div', { class: 'card__body', style: 'padding-top:0' }, h('small', { class: 'faint', text: 'A full chapter never blocks registration. WDOS places you in another matching chapter or shows a pending placement.' })));
    box.replaceChildren(h('div', { class: 'row row--4' }, kpi('route', 'Journey progress', `${j.done}/${j.total}`, [h('span', { text: 'Steps completed' })]), kpi('send', 'Referrals', `${r.registered}/${r.target}`, [h('span', { text: tf('{1} completed their seven days', r.verified) })]), kpi('check', 'Screening', 'Self-service', [h('span', { text: 'Day 5. No call, no approval' })]), kpi('award', 'Activation', active ? 'Active' : 'Rule-based', [h('span', { text: active ? 'Decided by WDOS' : 'Automatic on day 7' })])),
      head, h('section', { class: 'card' }, h('div', { class: 'card__body', style: 'display:grid;gap:10px' }, h('div', { style: 'display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap' }, h('b', { text: 'Seven-day journey' }), h('span', { class: 'tag tag--wnnn', text: 'No routine human intervention' })), pick)),
      h('div', { class: 'row row--2-1' }, dayCard(d.days.find((x) => x.day === shown) || d.days[0]), place));
  };
  try { paint(await api('/journey')); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'Your journey did not load', e.message))); }
}
