/* Announcements. HQ composes and sends (in-app + push in one step); everyone else reads what was addressed to them. */
import { h, icon, card, sk, empty, ago, fmt, toast, netTag, TONES } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';

export async function render(root, params) {
  const hq = app.user.is_hq; const list = h('div', {}, sk.rows(4));
  const load = async () => {
    let r; try { r = await api(hq ? '/hq/announcements' : '/announcements'); } catch (e) { list.replaceChildren(empty('x', 'Announcements did not load', e.message)); return; }
    if (!r.items.length) { list.replaceChildren(empty('megaphone', 'No announcements yet', hq ? 'Write the first one. It reaches every member it is addressed to, in the app and as a phone notification.' : 'When WODDI HQ writes to your network, it appears here.')); return; }
    list.replaceChildren(h('div', { class: 'reveal', style: 'display:grid;gap:14px' }, r.items.map((a) => h('article', { class: 'panel', id: 'a' + a.id, style: String(params.id) === String(a.id) ? 'border-color:var(--accent)' : '' },
      a.tone ? h('img', { class: 'annpic', src: `/assets/inspiration/messages/${a.tone}.webp`, alt: '', loading: 'lazy' }) : null,
      h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { style: 'font-size:16px;flex:1;min-width:200px', text: a.title }), a.audience_network !== 'ALL' ? netTag(a.audience_network) : h('span', { class: 'tag', text: 'Both networks' }), a.audience_role === 'leaders' ? h('span', { class: 'tag', text: 'Leaders only' }) : null),
      h('p', { style: 'white-space:pre-wrap;max-width:75ch', text: a.body }),
      h('small', { class: 'faint', text: [ago(a.created_at), hq && a.author ? 'by ' + a.author : '', hq ? `delivered to ${fmt(a.recipients)}` : ''].filter(Boolean).join(' · ') })))));
    if (params.id) document.getElementById('a' + params.id)?.scrollIntoView({ block: 'center' });
  };
  if (hq) {
    const title = h('input', { class: 'input', id: 'an-title', maxlength: 160, required: true });
    const body = h('textarea', { class: 'input', id: 'an-body', maxlength: 4000, required: true });
    const net = h('select', { class: 'input', id: 'an-net' }, [['ALL', 'Both networks'], ['WGMN', 'WGMN only'], ['WNNN', 'WNNN only']].map(([v, l]) => h('option', { value: v, text: l })));
    const role = h('select', { class: 'input', id: 'an-role' }, [['all', 'Every member'], ['leaders', 'Volunteer leaders only']].map(([v, l]) => h('option', { value: v, text: l })));
    const country = h('select', { class: 'input', id: 'an-country' }, h('option', { value: '', text: 'All countries' }));
    api('/geo/countries').then((r) => country.append(...r.countries.map((c) => h('option', { value: c.code, text: c.name })))).catch(() => {});
    let tone = '';
    const tones = h('div', { class: 'tones', role: 'group', 'aria-label': 'Picture' });
    const pick = (v) => { tone = v; tones.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.v === v))); };
    tones.append(h('button', { type: 'button', class: 'tone tone--none', 'data-v': '', 'aria-pressed': 'true', onclick: () => pick('') }, 'No picture'),
      ...TONES.map(([v, l]) => h('button', { type: 'button', class: 'tone', 'data-v': v, 'aria-pressed': 'false', onclick: () => pick(v) }, h('img', { src: `/assets/inspiration/messages/${v}.webp`, alt: '', loading: 'lazy' }), h('span', { text: l }))));
    const status = h('p', { class: 'okmsg', role: 'status' }); const btn = h('button', { class: 'btn', type: 'submit' }, icon('send', 17), 'Send announcement');
    const form = h('form', { style: 'display:grid;gap:14px' },
      h('label', { class: 'field', for: 'an-title' }, h('span', { text: 'Title' }), title), h('label', { class: 'field', for: 'an-body' }, h('span', { text: 'Message' }), body),
      h('div', { class: 'grid2', style: 'grid-template-columns:repeat(3,minmax(0,1fr))' }, h('label', { class: 'field', for: 'an-net' }, h('span', { text: 'Network' }), net), h('label', { class: 'field', for: 'an-role' }, h('span', { text: 'Who' }), role), h('label', { class: 'field', for: 'an-country' }, h('span', { text: 'Country' }), country)),
      h('div', { class: 'field' }, h('span', { text: 'Picture (optional)' }), tones),
      status, h('div', {}, btn));
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); status.className = 'okmsg'; status.textContent = ''; btn.disabled = true;
      try {
        const r = await api('/hq/announcements', { title: title.value, body: body.value, network: net.value, role: role.value, country: country.value, tone });
        status.textContent = `Delivered to ${fmt(r.recipients)} in-app ${r.recipients === 1 ? 'inbox' : 'inboxes'}. Sending phone notifications…`;
        title.value = ''; body.value = ''; pick(''); load();
        let sent = 0, guard = 0, d;                       // send the queued pushes in batches until the queue is empty
        do { d = await api('/push/drain', {}); sent += d.sent; guard++; if (d.configured === false) break; } while (d.left > 0 && guard < 60);
        status.textContent = `Delivered to ${fmt(r.recipients)} in-app ${r.recipients === 1 ? 'inbox' : 'inboxes'}` + (d.configured === false ? '. Phone notifications are off until the push keys are added in /api/setup.' : ` and ${fmt(sent)} ${sent === 1 ? 'device' : 'devices'}.`);
        toast('Announcement sent', '', 'ok');
      } catch (ex) { status.className = 'err'; status.textContent = ex.message; }
      btn.disabled = false;
    });
    root.append(h('div', { class: 'row row--2' }, card('New announcement', 'Goes to the app inbox and to every phone with notifications on', form), card('Sent', 'Most recent first', list)));
  } else root.append(card('Announcements', 'From WODDI HQ to your network', list));
  load();
}
