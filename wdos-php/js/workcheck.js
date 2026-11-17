/* v109.12: the AI work check on a task or report. Shows what to correct, and lets HQ mark the work as done anyway. */
import { h, t, tf, icon, toast, fmtDate } from './ui.js';
import { api } from './api.js';
import { app } from './app.js';

export function checkCard(c, reload) {
  if (!c) return null;
  if (c.verdict === 'ok') return h('div', { class: 'notice wc wc--ok' }, icon('check', 18), h('span', { text: t('The WDOS work check found nothing missing in what was handed in.') }));
  const open = c.status === 'open';
  const hq = app.user?.is_hq;
  const actions = hq && open ? h('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' },
    h('button', { class: 'btn btn--lime', type: 'button', onclick: async (e) => { const note = prompt(t('A short note for the volunteer (optional)')); if (note === null) return; e.target.disabled = true; try { await api(`/hq/work-checks/${c.id}/done`, { note }); toast(t('Marked as done'), t('The volunteer leader has been told.'), 'ok'); reload(); } catch (x) { e.target.disabled = false; toast(t('Not saved'), x.message, 'bad'); } } }, icon('check', 18), t('Mark as done')),
    h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { try { await api(`/hq/work-checks/${c.id}/recheck`, {}); toast(t('Checking again'), t('Open this page again in a minute.'), 'info'); } catch (x) { toast(t('Not started'), x.message, 'bad'); } } }, t('Check again'))) : null;
  return h('section', { class: 'card wc wc--flag' }, h('div', { class: 'card__body', style: 'display:grid;gap:10px' },
    h('div', { class: 'tagrow' }, h('span', { class: 'tag tag--bad', text: open ? t('Needs correction') : c.status === 'overridden' ? t('Marked as done by HQ') : t('Corrected') }), h('small', { class: 'faint', text: tf('WDOS work check · {1}', fmtDate(c.created_at)) })),
    c.summary ? h('b', { text: c.summary }) : null,
    h('ol', { class: 'wc__points' }, c.points.map((p) => h('li', { text: p }))),
    open && !hq ? h('p', { class: 'faint', text: t('Correct these points, then hand the work in again. WODDI HQ can see this check and can also mark the work as done.') }) : null,
    open && hq ? h('p', { class: 'faint', text: t('This is an automatic check. If the work is in fact complete, mark it as done.') }) : null,
    actions));
}
