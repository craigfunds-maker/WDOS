/* Announcements. HQ composes and sends (in-app + push in one step); everyone else reads what was addressed to them. */
import { h, icon, card, sk, empty, ago, fmt, toast, netTag, TONES, tonePhoto, LANGS, LANG_NAMES } from '../ui.js';
import { api } from '../api.js';
import { app } from '../app.js';

export async function render(root, params) {
  const hq = app.user.is_hq; const leader = !hq && app.user.is_leader && !app.user.he_for_she; const list = h('div', {}, sk.rows(4)); let onlyNews = false;
  const load = async () => {
    let r; try { r = await api(hq ? '/hq/announcements' : '/announcements'); } catch (e) { list.replaceChildren(empty('x', 'Announcements did not load', e.message)); return; }
    if (!r.items.length) { list.replaceChildren(empty('megaphone', 'No announcements yet', hq ? 'Write the first one. It reaches every member it is addressed to, in the app and as a phone notification.' : 'When WODDI HQ writes to your network, it appears here.')); return; }
    const news = r.items.filter((a) => a.kind === 'newsletter'); const shown = onlyNews ? news : r.items;
    const filt = news.length ? h('div', { class: 'tagrow', style: 'margin-bottom:12px' }, h('button', { class: 'btn btn--s ' + (onlyNews ? 'btn--ghost' : ''), type: 'button', onclick: () => { onlyNews = false; load(); } }, 'Everything'), h('button', { class: 'btn btn--s ' + (onlyNews ? '' : 'btn--ghost'), type: 'button', id: 'only-news', onclick: () => { onlyNews = true; load(); } }, 'Newsletters')) : null;
    list.replaceChildren(filt, h('div', { class: 'reveal', style: 'display:grid;gap:14px' }, shown.map((a) => h('article', { class: 'panel', id: 'a' + a.id, style: String(params.id) === String(a.id) ? 'border-color:var(--accent)' : '' },
      a.tone ? h('img', { class: 'annpic', src: tonePhoto(a.tone), alt: '', loading: 'lazy', onerror: (e) => e.target.remove() }) : null,
      h('div', { style: 'display:flex;gap:10px;align-items:center;flex-wrap:wrap' }, h('b', { style: 'font-size:16px;flex:1;min-width:200px', text: a.title }), a.kind === 'newsletter' ? h('span', { class: 'tag tag--wgmn', text: 'Newsletter' }) : null, a.audience_network !== 'ALL' ? netTag(a.audience_network) : h('span', { class: 'tag', text: 'Both networks' }), a.audience_role === 'leaders' ? h('span', { class: 'tag', text: 'Leaders only' }) : null, a.audience_chapter ? h('span', { class: 'tag', text: 'Your chapter' }) : null, hq && [a.audience_state, a.audience_lga, a.audience_community].some(Boolean) ? h('span', { class: 'tag', text: [a.audience_community, a.audience_lga, a.audience_state].filter(Boolean).join(', ') }) : null, hq && a.audience_lang ? h('span', { class: 'tag', text: LANG_NAMES[a.audience_lang] || a.audience_lang }) : null),
      h('p', { style: 'white-space:pre-wrap;max-width:75ch', text: a.body }),
      h('div', { style: 'display:flex;gap:12px;align-items:center;flex-wrap:wrap' }, h('small', { class: 'faint', text: [hq && !a.sent_at && a.scheduled_at ? 'Scheduled for ' + new Date(a.scheduled_at.replace(' ', 'T') + 'Z').toLocaleString() : ago(a.created_at), hq && a.author ? 'by ' + a.author : '', hq && a.sent_at ? `sent to ${fmt(a.recipients)} · acknowledged by ${fmt(a.acks)}` : '', hq && a.langs ? 'also in ' + a.langs.split(',').map((l) => LANG_NAMES[l] || l).join(', ') : ''].filter(Boolean).join(' · ') }),
        !hq ? (Number(a.acked) ? h('span', { class: 'tag tag--wnnn', text: 'You confirmed you read this' }) : h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: async (e) => { try { await api(`/announcements/${a.id}/ack`, {}); e.target.replaceWith(h('span', { class: 'tag tag--wnnn', text: 'You confirmed you read this' })); } catch (ex) { toast('Not saved', ex.message, 'bad'); } } }, 'I have read this')) : null)))));
    if (params.id) document.getElementById('a' + params.id)?.scrollIntoView({ block: 'center' });
  };
  if (hq || leader) {
    const title = h('input', { class: 'input', id: 'an-title', maxlength: 160, required: true });
    const kind = h('select', { class: 'input', id: 'an-kind' }, [['announcement', 'Announcement'], ['newsletter', 'Newsletter issue']].map(([v, l]) => h('option', { value: v, text: l })));
    const body = h('textarea', { class: 'input', id: 'an-body', maxlength: 4000, required: true });
    const net = h('select', { class: 'input', id: 'an-net' }, [['ALL', 'Both networks'], ['WGMN', 'WGMN only'], ['WNNN', 'WNNN only']].map(([v, l]) => h('option', { value: v, text: l })));
    const role = h('select', { class: 'input', id: 'an-role' }, [['all', 'Every member'], ['leaders', 'Volunteer leaders only']].map(([v, l]) => h('option', { value: v, text: l })));
    const country = h('select', { class: 'input', id: 'an-country' }, h('option', { value: '', text: 'All countries' }));
    api('/geo/countries').then((r) => country.append(...r.countries.map((c) => h('option', { value: c.code, text: c.name })))).catch(() => {});
    const state = h('input', { class: 'input', id: 'an-state', maxlength: 120, placeholder: 'Any' }), lga = h('input', { class: 'input', id: 'an-lga', maxlength: 120, placeholder: 'Any' }), community = h('input', { class: 'input', id: 'an-com', maxlength: 120, placeholder: 'Any' }), chapter = h('input', { class: 'input', id: 'an-ch', type: 'number', min: 1, placeholder: 'Any' });
    const lang = h('select', { class: 'input', id: 'an-lang' }, h('option', { value: '', text: 'Every language' }), Object.keys(LANGS).map((v) => h('option', { value: v, text: `${LANG_NAMES[v]} (${LANGS[v]})` })));
    const seat = app.user.seat; const scopeNote = leader ? h('p', { class: 'notice', text: app.user.chapter && seat && seat.level === 'chapter' ? 'This goes to the members of your own chapter only.' : `This goes to the ${seat ? seat.network : ''} members in your own area only: ${seat ? seat.title : ''}.` }) : null;
    const when = h('input', { class: 'input', id: 'an-when', type: 'datetime-local' }); const tr = {};
    const trBox = h('details', { class: 'panel' }, h('summary', { style: 'cursor:pointer;font-weight:650', text: 'Add this message in other languages (optional)' }), h('p', { class: 'muted', style: 'font-size:13.5px', text: 'Each member receives the version in the language she chose. Members whose language is left empty receive the main message.' }),
      ['ar', 'fr', 'pt', 'sw'].map((l) => { tr[l] = { title: h('input', { class: 'input', maxlength: 160, placeholder: 'Title', lang: l, dir: l === 'ar' ? 'rtl' : 'ltr' }), body: h('textarea', { class: 'input', maxlength: 4000, placeholder: 'Message', rows: 3, lang: l, dir: l === 'ar' ? 'rtl' : 'ltr' }) }; return h('div', { class: 'field', style: 'margin-top:12px' }, h('span', { text: `${LANG_NAMES[l]} (${LANGS[l]})` }), tr[l].title, tr[l].body); }));
    const one = h('input', { class: 'input', id: 'an-one', type: 'number', min: 1, placeholder: 'Any' });
    let tone = '';
    const tones = h('div', { class: 'tones', role: 'group', 'aria-label': 'Picture' });
    const pick = (v) => { tone = v; tones.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.v === v))); };
    tones.append(h('button', { type: 'button', class: 'tone tone--none', 'data-v': '', 'aria-pressed': 'true', onclick: () => pick('') }, 'No picture'),
      ...TONES.map(([v, l]) => h('button', { type: 'button', class: 'tone', 'data-v': v, 'aria-pressed': 'false', onclick: () => pick(v) }, h('img', { src: tonePhoto(v), alt: '', loading: 'lazy', onerror: (e) => { e.target.style.visibility = 'hidden'; } }), h('span', { text: l }))));
    const status = h('p', { class: 'okmsg', role: 'status' }); const btn = h('button', { class: 'btn', type: 'submit' }, icon('send', 17), 'Send announcement');
    const form = h('form', { style: 'display:grid;gap:14px' },
      hq ? h('label', { class: 'field', for: 'an-kind' }, h('span', { text: 'What is this?' }), kind, h('small', { text: 'A newsletter issue is sent the same way and is kept under the Newsletters filter for members to read again.' })) : null,
      h('label', { class: 'field', for: 'an-title' }, h('span', { text: 'Title' }), title), h('label', { class: 'field', for: 'an-body' }, h('span', { text: 'Message' }), body),
      leader ? scopeNote : h('div', { class: 'grid2 grid3' }, h('label', { class: 'field', for: 'an-net' }, h('span', { text: 'Network' }), net), h('label', { class: 'field', for: 'an-role' }, h('span', { text: 'Who' }), role), h('label', { class: 'field', for: 'an-country' }, h('span', { text: 'Country' }), country),
        h('label', { class: 'field', for: 'an-state' }, h('span', { text: 'State or region' }), state), h('label', { class: 'field', for: 'an-lga' }, h('span', { text: 'LGA, district or city' }), lga), h('label', { class: 'field', for: 'an-com' }, h('span', { text: 'Community or cluster' }), community),
        h('label', { class: 'field', for: 'an-ch' }, h('span', { text: 'Chapter number' }), chapter, h('small', { text: 'The last part of the chapter code.' })), h('label', { class: 'field', for: 'an-lang' }, h('span', { text: 'Preferred language' }), lang), h('label', { class: 'field', for: 'an-one' }, h('span', { text: 'One member only' }), one, h('small', { text: 'The number at the end of the member\'s page address. Leave empty for a group.' }))),
      trBox, hq ? h('label', { class: 'field', for: 'an-when' }, h('span', { text: 'Send later (optional)' }), when, h('small', { text: 'Leave empty to send now. A scheduled message goes out by itself at that time.' })) : null,
      h('div', { class: 'field' }, h('span', { text: 'Picture (optional)' }), tones),
      status, h('div', {}, btn));
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); status.className = 'okmsg'; status.textContent = ''; btn.disabled = true;
      try {
        const r = await api(hq ? '/hq/announcements' : '/leader/announcements', { title: title.value, body: body.value, kind: hq ? kind.value : 'announcement', network: net.value, role: role.value, country: country.value, state: state.value, lga: lga.value, community: community.value, chapter: Number(chapter.value) || 0, member: Number(one.value) || 0, lang: lang.value, tone, scheduled_at: when.value ? new Date(when.value).toISOString() : '', texts: Object.fromEntries(Object.entries(tr).filter(([, v]) => v.title.value.trim() && v.body.value.trim()).map(([l, v]) => [l, { title: v.title.value, body: v.body.value }])) });
        if (r.scheduled_at) { status.textContent = 'Scheduled. It will go out by itself at the time you chose.'; title.value = ''; body.value = ''; when.value = ''; load(); toast('Announcement scheduled', '', 'ok'); btn.disabled = false; return; }
        if (!hq) { status.textContent = `Sent to ${fmt(r.recipients)} ${r.recipients === 1 ? 'member' : 'members'} in the app and by phone notification.`; title.value = ''; body.value = ''; pick(''); load(); toast('Announcement sent', '', 'ok'); btn.disabled = false; return; }
        status.textContent = `Delivered to ${fmt(r.recipients)} in-app ${r.recipients === 1 ? 'inbox' : 'inboxes'}. Sending phone notifications…`;
        title.value = ''; body.value = ''; pick(''); load();
        let sent = 0, guard = 0, d;                       // send the queued pushes in batches until the queue is empty
        do { d = await api('/push/drain', {}); sent += d.sent; guard++; if (d.configured === false) break; } while (d.left > 0 && guard < 60);
        status.textContent = `Delivered to ${fmt(r.recipients)} in-app ${r.recipients === 1 ? 'inbox' : 'inboxes'}` + (d.configured === false ? '. Phone notifications are off until the push keys are added in /api/setup.' : ` and ${fmt(sent)} ${sent === 1 ? 'device' : 'devices'}.`);
        toast('Announcement sent', '', 'ok');
      } catch (ex) { status.className = 'err'; status.textContent = ex.message; }
      btn.disabled = false;
    });
    root.append(h('div', { class: 'row row--2' }, card('New announcement', 'Goes to the app inbox and to every phone with notifications on. Sent does not mean read.', form), card(hq ? 'Sent' : 'Announcements', hq ? 'Most recent first' : 'To you and your area', list)));
  } else root.append(card('Announcements', 'From WODDI HQ to your network', list));
  load();
}
