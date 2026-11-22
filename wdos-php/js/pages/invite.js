/* My 3 referrals: a member's own link, how far it has travelled, and who came through it. */
import { h, icon, card, sk, empty, fmtDate, toast, listenBtn, t, tf } from '../ui.js';
import { app } from '../app.js';

/* v109.49: each link speaks for its own platform (Azeez). The WDOS invitation is about WDOS, membership and her chapter;
 * the Institute card is about learning at the Institute. They are never mixed in one message. */
export const INSTITUTE = 'https://woddiinstitute.com';
const NETS = { WGMN: 'the WODDI Good Mother Network', WNNN: 'the WODDI Nurture NextGen Network' };
export const inviteLead = () => tf('Join me on WDOS, the WODDI Digital Operating System. Register free as a member of {1}, get your membership number, find your chapter near you and connect with women in your community.', t(NETS[app.user?.network] || 'WODDI'));
export const inviteText = (url) => inviteLead() + '\n' + t('Register here:') + ' ' + url;
export const instituteText = () => t('Learn with the WODDI Institute. Free courses for WODDI members and leaders:') + ' ' + INSTITUTE;
import { api } from '../api.js';

export async function render(root) {
  const box = h('div', { style: 'display:grid;gap:18px' }, h('div', { class: 'card' }, sk.rows(4))); root.append(box);
  let d; try { d = await api('/my/referrals'); } catch (e) { box.replaceChildren(h('div', { class: 'card' }, empty('x', 'Your referrals did not load', e.message))); return; }
  const r = d.referrals; const url = location.origin + '/#/r/' + r.code;
  const share = async () => { try { if (navigator.share) await navigator.share({ title: 'WDOS', text: inviteText(url) }); else { await navigator.clipboard.writeText(inviteText(url)); toast('Invitation copied', 'Paste it in WhatsApp or a text message.', 'ok'); } } catch { /* closed */ } };
  box.replaceChildren(card('Invite three women', 'WODDI grows when each woman brings three more. Share your own link.', h('div', { style: 'display:grid;gap:14px' },
      r.code ? h('div', { class: 'jref' }, h('code', { text: url }), h('button', { class: 'btn', type: 'button', onclick: share }, icon('send', 17), 'Share my link'), h('a', { class: 'btn btn--ghost', href: 'https://wa.me/?text=' + encodeURIComponent(inviteText(url)), target: '_blank', rel: 'noopener' }, t('Send on WhatsApp')), h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => { try { await navigator.clipboard.writeText(url); toast('Link copied', '', 'ok'); } catch { toast('Copy it by hand', url); } } }, 'Copy')) : h('p', { class: 'notice', text: 'Finish your profile to get your own link.' }),
      h('div', { class: 'jcount' }, [['Opened my link', r.opened], ['Registered', r.registered], ['Completed seven days', r.verified]].map(([l, n]) => h('div', {}, h('b', { class: 'num', text: String(n) }), h('small', { text: l })))),
      h('div', { class: 'jbar', role: 'img', 'aria-label': `${Math.min(r.registered, r.target)} of ${r.target}` }, [0, 1, 2].map((i) => h('span', { class: i < r.verified ? 'is-done' : i < r.registered ? 'is-open' : '' }))),
      h('small', { class: 'faint', text: 'An invitation that was only opened is not counted as a member. Registered and completed are counted separately.' })), listenBtn(['Invite three women', 'WODDI grows when each woman brings three more. Share your own link.'])),
    card('Learn with the WODDI Institute', 'Courses for members and leaders. Share it with the women you invite.', h('div', { style: 'display:grid;gap:12px' },
      h('div', { class: 'jref' }, h('code', { text: INSTITUTE }),
        h('a', { class: 'btn', href: INSTITUTE, target: '_blank', rel: 'noopener' }, icon('book', 17), t('Open the Institute')),
        h('a', { class: 'btn btn--ghost', href: 'https://wa.me/?text=' + encodeURIComponent(instituteText()), target: '_blank', rel: 'noopener' }, t('Send on WhatsApp'))))),
    card('Women who came through my link', null, d.invited.length ? h('div', { class: 'list' }, d.invited.map((x) => h('div', {}, h('div', { class: 'nrow__ico' }, icon('users', 18)), h('div', { class: 'grow' }, h('b', { text: x.name }), h('small', { text: 'Joined ' + fmtDate(x.joined) })), h('span', { class: 'tag' + (x.status === 'Completed seven days' ? ' tag--wnnn' : ''), text: x.status }))))
      : empty('send', 'Nobody yet', 'When a woman registers through your link, her first name appears here.')));
}
