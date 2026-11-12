/* Institute and volunteer development. The WODDI Institute stays the owner of enrolment, progress, assessment and
 * certificates. WDOS is the gateway to it, and where a member chooses to volunteer. */
import { h, icon, card, toast } from '../ui.js';
import { api } from '../api.js';
import { app, refreshUser } from '../app.js';

const SITE = 'https://woddiinstitute.com';
export async function render(root) {
  const u = app.user;
  const stage = (name, about) => h('div', { class: 'panel', style: 'display:grid;gap:8px' }, h('small', { class: 'faint', text: 'WODDI Institute' }), h('b', { style: 'font-size:18px', text: name }), h('p', { class: 'muted', style: 'margin:0', text: about }), h('small', { class: 'faint', text: 'Your progress is kept by the Institute.' }), h('div', {}, h('a', { class: 'btn btn--quiet btn--s', href: SITE, target: '_blank', rel: 'noopener', text: 'Open at the Institute' })));
  const vol = h('div');
  const paintVol = () => vol.replaceChildren(u.is_hq ? h('p', { class: 'muted', style: 'margin:0', text: 'Members choose to volunteer here. HQ sees volunteers on each chapter roster.' })
    : app.user.volunteer ? h('div', { style: 'display:grid;gap:10px' }, h('p', { class: 'okmsg', style: 'margin:0', text: 'You are a WODDI volunteer. Your development assignments are in your task list.' }), h('div', {}, h('a', { class: 'btn btn--quiet btn--s', href: '#/tasks', text: 'Open my assignments' })))
    : h('div', { style: 'display:grid;gap:10px' }, h('p', { class: 'muted', style: 'margin:0', text: 'Volunteers help their chapter and community. When you choose to volunteer, WDOS gives you three short development assignments. A leadership seat is a separate step, made by appointment.' }),
      h('div', {}, h('button', { class: 'btn', type: 'button', onclick: async (e) => { e.target.disabled = true; try { await api('/me/volunteer', {}); await refreshUser(); toast('Thank you for volunteering', 'Three assignments are in your task list.', 'ok'); paintVol(); } catch (ex) { toast('Not saved', ex.message, 'bad'); e.target.disabled = false; } } }, 'I want to volunteer'))));
  paintVol();
  root.append(card('Learning at the WODDI Institute', 'The Institute remains the source of truth for enrolment, academic progress, assessment and certification. WDOS takes you there.', h('div', { class: 'row row--3' }, stage('Rooting', 'Introductory foundation'), stage('Personal Blueprint', 'Identity, mandate and leadership'), stage('Electives', 'Specialist development'))),
    h('div', { class: 'row row--2' }, card('Role-based volunteer pathways', 'What each role learns', h('div', { class: 'list' }, [['Member', 'WODDI values and participation'], ['Volunteer', 'Service and teamwork'], ['Leader', 'Governance, reporting and safeguarding']].map(([a, b]) => h('div', {}, h('div', { class: 'nrow__ico' }, icon('award', 18)), h('div', { class: 'grow' }, h('b', { text: a }), h('small', { text: b })))))),
      card('Volunteering', 'From member, to volunteer, to an appointed leader', vol)),
    h('p', { class: 'notice', text: 'WDOS does not yet read course progress from the Institute. Each person has one WODDI Person ID (yours is ' + (u.person_id || '') + '), which the Institute can use once it is connected.' }));
}
