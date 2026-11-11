/* Members. HQ sees everyone and can appoint, reset and suspend; a leader sees her own area, read-only. */
import { h, clear, icon, avatar, flag, netTag, sk, empty, ago, fmt, fmtDate, toast } from '../ui.js';
import { api, squarePhoto } from '../api.js';
import { app, go } from '../app.js';

export async function render(root, params) {
  const leader = params.scope === 'leader'; const hq = !leader;
  const state = { q: '', network: '', kind: '', page: 1 };
  const tbody = h('tbody'); const pager = h('div', { class: 'pager' }); const holder = h('div');
  const cols = hq ? ['Member', 'Network', 'Location', 'Role', 'Joined'] : ['Member', 'Phone', 'Location', 'Joined'];
  const table = h('div', { class: 'tablewrap' }, h('table', { class: 'table' }, h('thead', {}, h('tr', {}, cols.map((c, i) => h('th', { class: i > 1 && i < cols.length - 1 ? 'hide-s' : '', text: c })))), tbody));
  const search = h('input', { class: 'input', type: 'search', placeholder: hq ? 'Search name, email, phone or membership number' : 'Search name or membership number', 'aria-label': 'Search members' });
  let timer; search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { state.q = search.value.trim(); state.page = 1; load(); }, 280); });
  const segNet = h('div', { class: 'seg', role: 'group', 'aria-label': 'Network' }, [['', 'All'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']].map(([v, l]) => h('button', { type: 'button', 'aria-pressed': String(v === ''), onclick: (e) => { state.network = v; state.page = 1; segNet.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget))); load(); }, text: l })));
  const kind = h('select', { class: 'input', style: 'width:auto;min-width:170px', 'aria-label': 'Show' }, [['', 'Everyone'], ['leaders', 'Volunteer leaders'], ['hq', 'HQ staff'], ['suspended', 'Suspended']].map(([v, l]) => h('option', { value: v, text: l })));
  kind.addEventListener('change', () => { state.kind = kind.value; state.page = 1; load(); });
  const count = h('span', { class: 'faint', style: 'margin-left:auto;font-size:13.5px' });
  root.append(h('section', { class: 'card' }, h('div', { class: 'toolbar' }, h('div', { class: 'search' }, icon('search', 18), search), hq ? segNet : null, hq ? kind : null, count), holder, pager));

  async function load() {
    holder.replaceChildren(table); tbody.replaceChildren(...sk.table(cols.length, 8)); pager.replaceChildren();
    let r;
    try { r = await api((hq ? '/hq/members?' : '/leader/members?') + new URLSearchParams({ q: state.q, network: state.network, kind: state.kind, page: state.page, ...(params.as ? { as: params.as } : {}) })); }
    catch (e) { holder.replaceChildren(empty('x', 'Members did not load', e.message, h('button', { class: 'btn btn--quiet btn--s', onclick: load }, 'Try again'))); return; }
    count.textContent = fmt(r.total) + (r.total === 1 ? ' member' : ' members') + (r.scope ? ' · ' + r.scope : '');
    if (!r.items.length) {
      const filtered = state.q || state.network || state.kind;
      holder.replaceChildren(filtered ? empty('search', 'No one matches', 'Try a different name, or clear the filters.', h('button', { class: 'btn btn--quiet btn--s', onclick: () => { search.value = ''; state.q = ''; state.network = ''; state.kind = ''; kind.value = ''; segNet.querySelectorAll('button').forEach((b, i) => b.setAttribute('aria-pressed', String(i === 0))); load(); } }, 'Clear filters'))
        : empty('users', leader ? 'No members in your area yet' : 'No members yet', leader ? 'Members appear here as women in your area join your network.' : 'Members appear here as soon as they register on the network page.'));
      return;
    }
    tbody.replaceChildren(...r.items.map((m) => {
      const who = h('div', { class: 'who' }, avatar(m, 38), h('div', {}, h('b', { text: `${m.first_name} ${m.last_name}` }), h('small', { text: hq ? m.email : m.member_no || '' })));
      const loc = h('span', { style: 'display:inline-flex;align-items:center;gap:8px' }, flag(m.country_code), [m.lga, m.state_region].filter(Boolean).join(', '));
      const tr = hq ? h('tr', { tabindex: 0 }, h('td', {}, who), h('td', {}, netTag(m.network) || h('span', { class: 'faint', text: 'HQ' })), h('td', { class: 'hide-s' }, loc),
        h('td', { class: 'hide-s' }, m.status === 'suspended' ? h('span', { class: 'tag tag--bad', text: 'Suspended' }) : m.status === 'pending_profile' ? h('span', { class: 'tag', text: 'Profile not finished' }) : m.seat_title ? h('span', { text: m.seat_title }) : h('span', { class: 'faint', text: m.role === 'member' ? 'Member' : 'HQ administrator' })), h('td', { class: 'faint', text: ago(m.created_at) }))
        : h('tr', {}, h('td', {}, who), h('td', {}, h('a', { href: 'tel:' + m.phone, text: m.phone })), h('td', { class: 'hide-s' }, loc), h('td', { class: 'faint', text: ago(m.created_at) }));
      if (hq) { const open = () => { history.replaceState(null, '', '#/members/' + m.id); openMember(m.id, load); }; tr.addEventListener('click', open); tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); }); } else tr.style.cursor = 'default';
      return tr;
    }));
    pager.replaceChildren(h('span', { text: `Page ${r.page} of ${r.pages}` }), h('div', { style: 'display:flex;gap:8px' },
      h('button', { class: 'btn btn--ghost btn--s', disabled: r.page <= 1, onclick: () => { state.page--; load(); } }, 'Previous'), h('button', { class: 'btn btn--ghost btn--s', disabled: r.page >= r.pages, onclick: () => { state.page++; load(); } }, 'Next')));
  }
  await load();
  if (hq && params.id) openMember(params.id, load);
}

/* ---- HQ: one member in a side drawer ---- */
let drawer = null;
function closeDrawer() { if (!drawer) return; const d = drawer; drawer = null; d.classList.remove('is-open'); setTimeout(() => d.remove(), 320); if (location.hash.startsWith('#/members/')) history.replaceState(null, '', '#/members'); }
async function openMember(id, reload) {
  drawer?.remove();
  const body = h('div', { class: 'drawer__body' }, sk.rows(4));
  drawer = h('aside', { class: 'drawer', role: 'dialog', 'aria-label': 'Member' }, h('div', { class: 'drawer__head' }, h('b', { style: 'flex:1', text: 'Member' }), h('button', { class: 'iconbtn', 'aria-label': 'Close', onclick: closeDrawer }, icon('x'))), body);
  document.body.append(drawer); requestAnimationFrame(() => drawer?.classList.add('is-open'));
  const onEsc = (e) => { if (e.key === 'Escape') { closeDrawer(); document.removeEventListener('keydown', onEsc); } }; document.addEventListener('keydown', onEsc);
  const paint = async () => {
    let d; try { d = await api('/hq/members/' + id); } catch (e) { body.replaceChildren(empty('x', 'Could not open this member', e.message)); return; }
    const m = d.member; const me = app.user;
    const dl = (pairs) => h('dl', { class: 'dl' }, pairs.filter((p) => p[1]).flatMap(([k, v]) => [h('dt', { text: k }), h('dd', {}, v)]));
    const msg = h('p', { class: 'err', role: 'alert' });
    const act = async (fn, okText) => { msg.textContent = ''; try { const r = await fn(); if (okText) toast(okText, '', 'ok'); await paint(); reload(); return r; } catch (e) { msg.textContent = e.message; } };

    /* appoint to a seat */
    const pos = h('select', { class: 'input' }, h('option', { value: '', text: 'Choose a position' }), d.positions.map((p) => h('option', { value: p.code, 'data-level': p.level, text: m.network === 'WNNN' ? p.title_wnnn : p.title_wgmn })));
    const country = h('select', { class: 'input' }, h('option', { value: '', text: 'Country' })); const st = h('select', { class: 'input', hidden: true });
    const area = h('input', { class: 'input', placeholder: 'Name of the LGA, cluster or chapter', hidden: true });
    api('/geo/countries').then((r) => { country.append(...r.countries.map((c) => h('option', { value: c.id, 'data-code': c.code, 'data-children': c.children, text: c.name }))); const mine = r.countries.find((c) => c.code === m.country_code); if (mine) { country.value = mine.id; country.dispatchEvent(new Event('change')); } });
    country.addEventListener('change', async () => { const o = country.selectedOptions[0]; st.hidden = true; clear(st); if (o && Number(o.dataset.children) > 0) { const r = await api('/geo/children/' + o.value); st.append(h('option', { value: '', text: 'Whole country, or choose a state' }), ...r.units.map((x) => h('option', { value: x.id, text: x.name }))); st.hidden = false; const cur = r.units.find((x) => x.name === m.state_region); if (cur && pos.selectedOptions[0]?.dataset.level !== 'country') st.value = cur.id; } });
    pos.addEventListener('change', () => { const lvl = pos.selectedOptions[0]?.dataset.level; area.hidden = !['lga', 'community_cluster', 'chapter'].includes(lvl); if (lvl === 'country') st.value = ''; });
    /* correct network and location (for accounts that came across without them) */
    const dNet = h('select', { class: 'input' }, [['', 'No network (HQ staff)'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']].map(([v, l]) => h('option', { value: v, text: l }))); dNet.value = m.network || '';
    const dCountry = h('select', { class: 'input' }, h('option', { value: '', text: 'Country' })); const dState = h('select', { class: 'input', hidden: true });
    const dStateText = h('input', { class: 'input', placeholder: 'State or region', value: m.state_region || '' }); const dLga = h('input', { class: 'input', placeholder: 'LGA or district', value: m.lga || '' });
    api('/geo/countries').then((r) => { dCountry.append(...r.countries.map((c) => h('option', { value: c.id, 'data-children': c.children, text: c.name }))); const mine = r.countries.find((c) => c.code === m.country_code); if (mine) { dCountry.value = mine.id; dCountry.dispatchEvent(new Event('change')); } });
    dCountry.addEventListener('change', async () => { const o = dCountry.selectedOptions[0]; dState.hidden = true; dStateText.hidden = false; if (o && Number(o.dataset.children) > 0) { const r = await api('/geo/children/' + o.value); dState.replaceChildren(h('option', { value: '', text: 'State or region' }), ...r.units.map((x) => h('option', { value: x.id, text: x.name }))); dState.hidden = false; dStateText.hidden = true; const cur = r.units.find((x) => x.name === m.state_region); if (cur) dState.value = cur.id; } });
    const seatStatus = h('select', { class: 'input' }, [['appointed', 'Appointed'], ['acting', 'Acting'], ['pending', 'Pending confirmation']].map(([v, l]) => h('option', { value: v, text: l })));
    const once = h('div');

    body.replaceChildren(
      h('div', { style: 'display:flex;gap:14px;align-items:center' }, avatar(m, 64), h('div', {}, h('div', { class: 'display', style: 'font-size:24px;line-height:1.15', text: `${m.first_name} ${m.last_name}` }), h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center' }, flag(m.country_code), netTag(m.network), m.status === 'suspended' ? h('span', { class: 'tag tag--bad', text: 'Suspended' }) : null, m.is_hq ? h('span', { class: 'tag', text: 'HQ access' }) : null))),
      dl([['Membership no.', m.member_no], ['Email', h('a', { href: 'mailto:' + m.email, text: m.email })], ['Phone', m.phone && h('a', { href: 'tel:' + m.phone, text: m.phone })], ['Date of birth', fmtDate(m.dob)], ['Location', [m.community, m.lga, m.state_region, m.country].filter(Boolean).join(', ')], ['Leadership seat', m.seat?.title], ['Joined', fmtDate(m.created_at) + ' by ' + (m.signup_method === 'google' ? 'Google' : m.signup_method === 'setup' ? 'setup page' : 'email')], ['Last active', ago(m.last_seen_at)], ['Devices', `${m.devices} with notifications on, ${m.fingerprints} with fingerprint`]]),
      msg,
      h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, m.seat ? h('a', { class: 'btn btn--quiet btn--s', href: '#/leaders/' + id }, 'Open leadership dashboard') : null,
        (() => { const file = h('input', { type: 'file', accept: 'image/*', hidden: true });
          file.addEventListener('change', () => { if (file.files[0]) act(async () => api(`/hq/members/${id}/photo`, { data: await squarePhoto(file.files[0]) }), 'Photograph saved'); });
          return h('span', {}, file, h('button', { class: 'btn btn--ghost btn--s', type: 'button', onclick: () => file.click() }, m.has_photo && m.has_photo !== '0' ? 'Change photograph' : 'Set photograph')); })()),
      m.network ? h('div', { class: 'panel' }, h('b', { text: m.seat ? 'Change leadership seat' : 'Appoint to a leadership seat' }), pos, country, st, area, seatStatus,
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' }, h('button', { class: 'btn btn--s', onclick: () => act(() => api(`/hq/members/${id}/seat`, { position: pos.value, org_unit_id: Number(st.value || country.value), area_label: area.value, status: seatStatus.value }), 'Seat recorded. The member has been notified.') }, m.seat ? 'Save new seat' : 'Appoint'),
          m.seat ? h('button', { class: 'btn btn--ghost btn--s', onclick: () => act(() => api(`/hq/members/${id}/seat`, { vacate: true }), 'Seat closed') }, 'Close this seat') : null)) : null,
      h('details', { class: 'panel', open: !m.country_code || (!m.network && !m.is_hq) }, h('summary', { style: 'cursor:pointer;font-weight:650', text: 'Correct network or location' }),
        h('div', { style: 'display:grid;gap:10px;margin-top:12px' }, dNet, dCountry, dState, dStateText, dLga,
          h('div', {}, h('button', { class: 'btn btn--quiet btn--s', onclick: () => act(() => api(`/hq/members/${id}/details`, { network: dNet.value, country_id: Number(dCountry.value) || 0, state_id: dState.hidden ? 0 : Number(dState.value) || 0, state_region: dStateText.value, lga: dLga.value, community: m.community || '' }), 'Details saved') }, 'Save details')))),
      h('div', { class: 'panel' }, h('b', { text: 'Sign-in' }), h('p', { class: 'muted', style: 'font-size:13.5px', text: 'WDOS never stores or shows an existing password. A reset creates a temporary one, shown here once, and signs the member out everywhere.' }), once,
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
          h('button', { class: 'btn btn--quiet btn--s', onclick: async () => { msg.textContent = ''; try { const r = await api(`/hq/members/${id}/reset-password`, {}); once.replaceChildren(h('div', { class: 'onceonly', text: r.temporary_password }), h('small', { class: 'faint', text: 'Copy it now. It will not be shown again.' })); } catch (e) { msg.textContent = e.message; } } }, icon('key', 16), 'Reset password'),
          m.id !== me.id && m.status !== 'pending_profile' ? h('button', { class: 'btn btn--s ' + (m.status === 'suspended' ? 'btn--quiet' : 'btn--danger'), onclick: () => act(() => api('/hq/members/' + id, { status: m.status === 'suspended' ? 'active' : 'suspended' }), m.status === 'suspended' ? 'Account reinstated' : 'Account suspended') }, m.status === 'suspended' ? 'Reinstate' : 'Suspend') : null,
          me.role === 'super_admin' && m.id !== me.id && m.role !== 'super_admin' ? h('button', { class: 'btn btn--ghost btn--s', onclick: () => act(() => api('/hq/members/' + id, { role: m.is_hq ? 'member' : 'hq_admin' }), m.is_hq ? 'HQ access removed' : 'HQ access given') }, icon('shield', 16), m.is_hq ? 'Remove HQ access' : 'Give HQ access') : null)),
      d.history.length ? h('div', {}, h('b', { text: 'Seat history' }), h('div', { class: 'list', style: 'margin-top:6px' }, d.history.map((s) => h('div', {}, h('div', { class: 'grow' }, h('b', { text: `${s.title}, ${s.area_label || s.unit}` }), h('small', { text: `${fmtDate(s.starts_at)} to ${s.ends_at ? fmtDate(s.ends_at) : 'now'}` })), netTag(s.network))))) : null);
  };
  paint();
}
window.addEventListener('hashchange', () => { if (drawer && !location.hash.startsWith('#/members/')) { drawer.remove(); drawer = null; } });
