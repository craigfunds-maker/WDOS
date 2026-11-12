/* Automated communications engine. The messages WDOS sends by itself, in every language, as WODDI HQ approved them;
 * who can be reached, from the continent down to one member; and what happened to what was sent. */
import { h, card, sk, empty, fmt, fmtDate, toast, input, LANGS, LANG_NAMES } from '../ui.js';
import { api } from '../api.js';

export async function render(root) {
  const tBox = h('div', {}, sk.rows(6)), dBox = h('div', {}, sk.rows(3)), cBox = h('div');
  root.append(h('div', { class: 'row row--2' },
    card('Who a message can reach', 'Every level, including chapter and one member. In the diaspora: country, state, city, chapter.', h('div', { style: 'display:grid;gap:12px' }, h('div', { class: 'tagrow', style: 'max-width:none;justify-content:flex-start' }, ['Continental', 'Country', 'State or region', 'LGA, district or city', 'Community or cluster', 'Chapter', 'One member', 'By network', 'By role', 'By language'].map((x) => h('span', { class: 'tag', text: x }))),
      h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' }, h('a', { class: 'btn', href: '#/announcements', text: 'Write or schedule a message' }), h('a', { class: 'btn btn--ghost', href: '#/inspiration', text: 'Weekly and monthly inspiration' })))),
    card('How an automatic message travels', 'No officer approves each sending. The wording and the rules are approved once, here.', h('ol', { class: 'flow' }, ['Approved template', 'Audience and chapter filter', 'Scheduled trigger', 'The member\'s preferred language', 'Delivery attempt', 'Delivery status, and a retry on failure'].map((x) => h('li', { text: x }))))),
    card('Approved message templates', 'The messages WDOS sends by itself. Change any wording in any language. Leave both boxes empty and save to go back to the built-in wording. Every change is logged.', tBox),
    h('div', { class: 'row row--2' }, card('Delivery states', 'What WDOS can and cannot know about a message', dBox), card('Recent template changes', null, cBox)));
  async function load() {
    let d, del; try { [d, del] = await Promise.all([api('/hq/templates'), api('/hq/delivery')]); } catch (e) { tBox.replaceChildren(empty('x', 'Templates did not load', e.message)); return; }
    tBox.replaceChildren(h('div', { style: 'display:grid;gap:12px' }, d.templates.map((t) => { const body = h('div', { style: 'display:grid;gap:12px;margin-top:12px' });
      const row = (l) => { const x = t.langs[l]; const ti = input({ value: x.title, maxlength: 160, lang: l, dir: l === 'ar' ? 'rtl' : 'ltr' }); const bo = h('textarea', { class: 'input', rows: 2, maxlength: 600, lang: l, dir: l === 'ar' ? 'rtl' : 'ltr' }); bo.value = x.body;
        return h('div', { class: 'field' }, h('span', {}, `${LANG_NAMES[l]} (${LANGS[l]})`, x.own ? h('span', { class: 'tag tag--wnnn', style: 'margin-inline-start:8px', text: 'Changed by HQ' }) : null), ti, bo,
          h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/hq/templates', { key: t.key, lang: l, title: ti.value, body: bo.value }); toast('Template saved', `${t.name}, ${LANG_NAMES[l]}`, 'ok'); load(); } catch (e) { toast('Not saved', e.message, 'bad'); } } }, 'Save'),
            x.own ? h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: async () => { try { await api('/hq/templates', { key: t.key, lang: l, title: '', body: '' }); load(); } catch (e) { toast('Not changed', e.message, 'bad'); } } }, 'Back to the built-in wording') : null)); };
      body.append(h('small', { class: 'faint', text: 'Parts that change: ' + t.placeholders }), ...['en', 'ar', 'fr', 'pt', 'sw'].map(row));
      return h('details', { class: 'panel' }, h('summary', { style: 'cursor:pointer;font-weight:650' }, t.name, h('small', { class: 'faint', style: 'font-weight:500', text: '  ' + t.langs.en.title })), body); })));
    const p = del.push || {}, m = del.mail || {};
    dBox.replaceChildren(h('div', { class: 'list list--wrap' }, [['Queued', 'Waiting to be sent', `${fmt(p.queued || 0)} phone · ${fmt(m.queued || 0)} email`], ['Sent', 'Accepted by the phone\'s push service or the email service', `${fmt(p.sent || 0)} phone · ${fmt(m.sent || 0)} email`],
      ['Delivered', 'Only known when the service reports it. Phone push services do not report it to WDOS.', 'Not reported'], ['Failed', 'Tried again; a dead device is removed', `${fmt(p.failed || 0)} phone · ${fmt(m.failed || 0)} email`],
      ['Opened in WDOS', 'The member opened the message in the app, last 30 days', `${fmt(del.read)} of ${fmt(del.created)}`], ['Acknowledged', 'The member pressed "I have read this" on an announcement', 'Shown on each announcement']].map(([a, b, c]) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: a }), h('small', { text: b })), h('span', { class: 'tag num', text: c })))));
    cBox.replaceChildren(d.changes.length ? h('div', { class: 'list' }, d.changes.map((x) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: x.detail }), h('small', { text: 'by ' + x.actor })), h('small', { class: 'faint', text: fmtDate(x.created_at, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) })))) : h('p', { class: 'muted', style: 'margin:0', text: 'No template has been changed. WDOS is using its built-in wording.' }));
  }
  load();
}
