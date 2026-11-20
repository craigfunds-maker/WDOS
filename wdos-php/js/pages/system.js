/* v109.35: System health for HQ. The daily self-check notification opens it. Each item opens the place to fix it.
 * Email delivery shows Brevo's own reason for failures, tests the connection without sending anything, and sends the
 * failed ones again once the cause is fixed (Azeez, 20 Nov 2026). */
import { h, t, tf, icon, card, sk, empty, toast, fmt, fmtDate, ago } from '../ui.js';
import { api } from '../api.js';

export async function render(root, params = {}) {
  const box = h('div', { style: 'display:grid;gap:20px' }, h('div', { class: 'card' }, sk.rows(5)));
  root.append(h('section', { class: 'hero' }, h('div', { class: 'grow' }, h('h1', { class: 'display', text: t('System health') }), h('p', { text: t('What the daily self-check found, and where to fix each item. Email delivery is below.') }))), box);
  async function load() {
    let d; try { d = await api('/hq/system'); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'This page did not load', e.message))); return; }
    const e = d.email;
    const items = h('div', { class: 'list list--stack' }, d.items.map((x) => h('a', { class: 'list__link', href: x.href, onclick: (ev) => { if (x.href === '#/hq/system/email') { ev.preventDefault(); emailCard.scrollIntoView({ behavior: 'smooth', block: 'start' }); } } },
      h('div', { class: 'nrow__ico' }, icon(x.n ? 'bell' : 'check', 18)), h('div', { class: 'grow' }, h('b', { text: t(x.label) })),
      h('span', { class: 'tag ' + (x.n ? (x.soft ? 'tag--wgmn' : 'tag--bad') : 'tag--wnnn'), text: x.n ? fmt(x.n) : t('All clear') }), icon('chevright', 16))));
    const testOut = h('div');
    const showTest = (r) => testOut.replaceChildren(r ? h('div', { class: 'panel', role: 'status', style: 'display:grid;gap:4px' }, h('b', { class: r.ok ? '' : 'bad', text: r.ok ? t('Brevo is working') : t('Brevo is refusing this server') }), h('small', { text: r.message }), h('small', { class: 'faint', text: tf('Checked {1}', ago(r.at)) })) : null);
    showTest(e.test);
    const testBtn = h('button', { class: 'btn btn--quiet', type: 'button', onclick: async () => { testBtn.disabled = true; try { showTest(await api('/hq/system/email-test', {})); load(); } catch (x) { toast(t('The check did not run'), x.message, 'bad'); } testBtn.disabled = false; } }, icon('shield', 16), ' ', t('Check the email connection'));
    const retryBtn = h('button', { class: 'btn', type: 'button', disabled: !e.failed_7d, onclick: async () => { retryBtn.disabled = true; try { const r = await api('/hq/system/email-retry', {}); toast(t('Sending again'), tf('{1} emails are going out again, a few at a time.', fmt(r.queued)), 'ok'); load(); } catch (x) { toast(t('Not sent again'), x.message, 'bad'); retryBtn.disabled = false; } } }, icon('send', 16), ' ', tf('Send the {1} failed emails again', fmt(e.failed_7d)));
    const fix = e.ip_block ? h('div', { class: 'panel', style: 'display:grid;gap:8px;border-color:var(--bad,#c0392b)' },
      h('b', { text: t('Why the emails failed: Brevo does not recognise this server') }),
      h('p', { style: 'margin:0', text: tf('WDOS now runs on QServers, at the address {1}. Brevo blocks email sent from an address it has not been told about. Nothing is wrong with the messages themselves.', e.ip_block.ip) }),
      h('ol', { style: 'margin:0;padding-left:20px;display:grid;gap:4px' }, h('li', { text: t('Open Brevo, Security, Authorised IPs (the button below), signed in to the WODDI Brevo account.') }), h('li', { text: tf('Add {1} as an authorised IP address and save.', e.ip_block.ip) }), h('li', { text: t('Come back here, press Check the email connection, then Send the failed emails again.') })),
      h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('a', { class: 'btn btn--s', href: e.ip_block.fix_url, target: '_blank', rel: 'noopener' }, t('Open Brevo authorised IPs')))) : null;
    const emailCard = card('Email delivery', e.on ? tf('Last 7 days. The last email that went out: {1}.', e.last_sent ? fmtDate(e.last_sent) + ' (' + ago(e.last_sent) + ')' : t('none yet')) : t('Email is switched off.'),
      h('div', { style: 'display:grid;gap:14px' },
        h('div', { class: 'metrics' }, h('div', {}, h('div', { class: 'metrics__n num', text: fmt(e.sent_7d) }), h('small', { text: t('Sent') })), h('div', {}, h('div', { class: 'metrics__n num bad', text: fmt(e.failed_7d) }), h('small', { text: t('Failed') })), h('div', {}, h('div', { class: 'metrics__n num', text: fmt(e.waiting) }), h('small', { text: t('Waiting to go out') }))),
        fix,
        e.reasons.length ? h('div', {}, h('b', { text: t("Brevo's reason") }), h('ul', { style: 'margin:6px 0 0;padding-left:20px' }, e.reasons.map((r) => h('li', {}, h('small', { text: `${r.reason} (${fmt(r.n)})` }))))) : null,
        e.subjects.length ? h('div', {}, h('b', { text: t('What failed') }), h('div', { class: 'list list--stack', style: 'margin-top:6px' }, e.subjects.map((s) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: s.subject }), h('small', { text: tf('Last tried {1}', ago(s.last_at)) })), h('span', { class: 'tag tag--bad', text: fmt(s.n) }))))) : h('p', { class: 'muted', style: 'margin:0', text: t('No email failed in the last 7 days.') }),
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, testBtn, retryBtn), testOut));
    const pushCard = card('Phone notifications', tf('{1} devices can receive them.', fmt(d.push.devices)), h('div', { class: 'metrics' }, h('div', {}, h('div', { class: 'metrics__n num', text: fmt(d.push.sent_7d) }), h('small', { text: t('Sent in 7 days') })), h('div', {}, h('div', { class: 'metrics__n num', text: fmt(d.push.failed_7d) }), h('small', { text: t('Failed in 7 days') }))));
    box.replaceChildren(card('Daily self-check', tf('Build {1}. Each line opens the place to fix it.', d.build), items), emailCard, pushCard);
    if (params.part === 'email') setTimeout(() => emailCard.scrollIntoView({ behavior: 'smooth', block: 'start' }), 60);
  }
  load();
}
