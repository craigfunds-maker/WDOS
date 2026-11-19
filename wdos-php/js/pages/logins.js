/* v109.25: logins for many leaders at once. HQ chooses a country, a network and the levels, ticks the people, and WDOS gives each a
 * new temporary password. The passwords come back once, in this page only, and are turned into a PDF on this computer.
 * Nothing here can read an existing password: there is none to read. */
import { h, t, tf, icon, card, empty, sk, toast, ago, netTag, fmt } from '../ui.js';
import { api } from '../api.js';
import { tablePdf, download } from '../pdfmini.js';

export async function render(root, params = {}) {
  const onlyEnvoys = params.only === 'envoy';
  const sel = (opts, v) => { const s = h('select', { class: 'input' }, opts.map(([k, l]) => h('option', { value: k, text: t(l) }))); if (v !== undefined) s.value = v; return s; };
  const country = sel([['', 'All countries']]); const network = sel([['', 'Both networks'], ['WGMN', 'WGMN'], ['WNNN', 'WNNN']]);
  const levelBox = h('div', { style: 'display:flex;gap:14px;flex-wrap:wrap' }); const lv = new Map();
  const list = h('div', {}, sk.rows(5)); const bar = h('div', { class: 'toolbar', style: 'flex-wrap:wrap' }); const done = h('div');
  const picked = new Set(); let d = null, loaded = false;
  root.append(card(onlyEnvoys ? 'Sign-in details for many envoys at once' : 'Logins for many leaders at once', onlyEnvoys ? 'Tick the envoys, and WDOS gives each a temporary password. Each envoy signs in with the phone number shown (or her email if she has one). You get the details once, as a PDF made on this computer. Each envoy chooses her own password when she first signs in.' : 'Choose the country, the network and the levels, tick the people, and WDOS gives each a new temporary password. You get them once, as a PDF made on this computer. Each person chooses her own password when she first signs in.',
    h('div', { style: 'display:grid;gap:14px' }, h('div', { class: 'grid2' }, country, network), levelBox,
      h('p', { class: 'muted', style: 'margin:0', text: t('Their current password stops working, and they are signed out of WDOS on every device. People who sign in with Google can keep doing so.') }), bar, list)), done);
  const paintBar = () => {
    const ok = d ? d.people.filter((p) => picked.has(p.id)).length : 0;
    const agree = h('input', { type: 'checkbox', style: 'width:20px;height:20px' });
    const go = h('button', { class: 'btn', type: 'button', disabled: true, onclick: () => issue(go) }, icon('key', 16), ' ', tf('Create {1} logins and the PDF', ok));
    agree.addEventListener('change', () => { go.disabled = !agree.checked || !ok; });
    bar.replaceChildren(h('span', { class: 'tag', text: tf('{1} chosen', ok) }),
      d && d.people.length ? h('button', { class: 'btn btn--quiet btn--s', type: 'button', onclick: () => { const all = d.people.filter((p) => p.can_sign_in); const on = all.every((p) => picked.has(p.id)); all.forEach((p) => on ? picked.delete(p.id) : picked.add(p.id)); paint(); } }, t('Tick or untick all')) : null,
      h('label', { style: 'display:flex;gap:8px;align-items:center' }, agree, h('span', { text: t('I understand their current passwords will stop working') })), go);
  };
  function paint() {
    paintBar();
    list.replaceChildren(d.people.length ? h('div', { class: 'list list--stack' }, d.people.map((p) => {
      const box = h('input', { type: 'checkbox', checked: picked.has(p.id), disabled: !p.can_sign_in, style: 'width:22px;height:22px;flex:none', 'aria-label': tf('Choose {1}', p.name),
        onchange: (e) => { e.target.checked ? picked.add(p.id) : picked.delete(p.id); paintBar(); } });
      return h('label', { class: 'list__link', style: 'display:flex;gap:10px;align-items:center;cursor:pointer' }, box,
        h('div', { class: 'grow', style: 'min-width:0' }, h('b', { text: p.name }), h('small', { text: p.position }),
          h('small', { class: p.can_sign_in ? 'muted' : 'bad', style: 'display:block', text: p.can_sign_in ? `${p.kind}: ${p.signs_in_with}` : t('No email or phone number to sign in with. Add one on her profile first.') })),
        h('div', { style: 'flex:none;display:grid;gap:4px;justify-items:end' }, netTag(p.network), h('small', { text: p.last_seen_at ? tf('Seen {1}', ago(p.last_seen_at)) : t('Never signed in') }), p.google ? h('small', { class: 'muted', text: t('Uses Google') }) : null));
    })) : empty('users', 'Nobody here', 'No seated leader or envoy matches these choices.'));
  }
  async function load() {
    const levels = [...lv].filter(([, c]) => c.checked).map(([k]) => k);
    list.replaceChildren(sk.rows(5));
    try { d = await api('/hq/leader-logins/bulk?' + new URLSearchParams({ country: country.value, network: network.value, levels: levels.join(',') })); }
    catch (e) { list.replaceChildren(empty('x', 'The list did not load', e.message)); return; }
    if (!loaded) {
      loaded = true; d.countries.forEach((c) => country.append(h('option', { value: c.cc, text: c.name })));
      if (d.countries.some((c) => c.cc === 'NG')) { country.value = 'NG'; return load(); }
    }
    picked.clear(); d.people.filter((p) => p.can_sign_in).forEach((p) => picked.add(p.id)); paint();
  }
  async function issue(btn) {
    const ids = d.people.filter((p) => picked.has(p.id)).map((p) => p.id); if (!ids.length) return;
    btn.disabled = true;
    let r; try { r = await api('/hq/leader-logins/bulk', { country: country.value, network: network.value, levels: [...lv].filter(([, c]) => c.checked).map(([k]) => k), ids, confirm: true }); }
    catch (e) { toast(t('No logins were created'), e.message, 'error'); btn.disabled = false; return; }
    const place = country.value ? country.options[country.selectedIndex].text : t('All countries');
    const when = new Date(r.at.replace(' ', 'T') + 'Z').toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    const host = r.site.replace(/^https?:\/\//, '').replace(/\/$/, '');
    const pdf = tablePdf({ title: `${onlyEnvoys ? 'WDOS envoy sign-in details' : 'WDOS leader logins'}: ${place}${network.value ? ' · ' + network.value : ''}`, subtitle: `Made ${when} by ${r.by}. ${r.issued.length} logins.`,
      note: 'Confidential. Give each person only her own line, privately. These temporary passwords are not kept anywhere else and cannot be shown again.',
      footer: `Sign in at ${host} with the email or phone number and the temporary password. WDOS then asks you to choose your own password.`,
      columns: [{ label: '#', width: 3 }, { label: 'Name', width: 17, bold: true }, { label: 'Position', width: 30 }, { label: 'Network', width: 7 }, { label: 'Sign in with', width: 25 }, { label: 'Temporary password', width: 14, mono: true }],
      rows: r.issued.map((x, i) => [String(i + 1), x.name, x.position, x.network || '', x.sign_in_with, x.temporary_password]) });
    const name = `WDOS-${onlyEnvoys ? 'envoy-sign-in' : 'logins'}-${(country.value || 'all')}${network.value ? '-' + network.value : ''}-${new Date().toISOString().slice(0, 10)}.pdf`;
    download(pdf, name);
    done.replaceChildren(card(tf('{1} logins created', fmt(r.issued.length)), 'The PDF has downloaded. Keep it safe: the temporary passwords cannot be shown again. If it did not download, press the button below now, before you leave this page.',
      h('div', { style: 'display:grid;gap:10px' }, h('button', { class: 'btn', type: 'button', onclick: () => download(pdf, name) }, icon('book', 16), ' ', t('Download the PDF again')),
        r.skipped.length ? h('div', { class: 'panel' }, h('b', { text: tf('{1} not done', r.skipped.length) }), ...r.skipped.map((s) => h('small', { style: 'display:block', text: `${s.name}: ${t(s.why)}` }))) : null)));
    done.scrollIntoView({ behavior: 'smooth', block: 'start' });
    load();
  }
  const first = await api('/hq/leader-logins/bulk?levels=country').catch(() => null);
  Object.entries(first ? first.levels : { country: 'Country', state_region: 'State or region' }).forEach(([k, l]) => {
    const c = h('input', { type: 'checkbox', checked: onlyEnvoys ? k === 'envoy' : (k === 'country' || k === 'state_region' || k === 'envoy'), style: 'width:20px;height:20px' }); lv.set(k, c);
    c.addEventListener('change', load); levelBox.append(h('label', { style: 'display:flex;gap:6px;align-items:center' }, c, h('span', { text: t(l) })));
  });
  [country, network].forEach((x) => x.addEventListener('change', load));
  load();
}
