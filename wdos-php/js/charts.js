/* WDOS charts: hand-built SVG, no library. Colour comes from the design tokens
 * (validated for colour-blind separation on both themes), text always wears ink
 * tokens, and every chart ships a hover layer and a text alternative. */
import { h, fmt } from './ui.js';
const NS = 'http://www.w3.org/2000/svg';
const s = (tag, attrs = {}, ...kids) => { const n = document.createElementNS(NS, tag); for (const [k, v] of Object.entries(attrs)) if (v !== null && v !== undefined) n.setAttribute(k, v); n.append(...kids); return n; };
const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
export const seriesColor = { WGMN: 'var(--s-wgmn)', WNNN: 'var(--s-wnnn)' };

/** Count a number up once, when it first appears. */
export function countUp(node, to, ms = 900) {
  to = Number(to) || 0;
  if (still() || to === 0) { node.textContent = fmt(to); return; }
  const t0 = performance.now();
  const tick = (t) => { const p = Math.min(1, (t - t0) / ms); node.textContent = fmt(Math.round(to * (1 - Math.pow(1 - p, 3)))); if (p < 1) requestAnimationFrame(tick); };
  requestAnimationFrame(tick);
}
const niceMax = (v) => { if (v <= 4) return 4; const p = Math.pow(10, Math.floor(Math.log10(v))); const n = v / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p; };

/** Change over time. series: [{name, color, values[]}], labels: ISO dates. One y axis, crosshair + tooltip. */
export function lineChart(host, { labels, series, height = 250 }) {
  host.classList.add('chart'); host.replaceChildren();
  const tip = h('div', { class: 'tip', role: 'presentation' });
  const draw = () => {
    host.querySelector('svg')?.remove();
    const W = Math.max(280, host.clientWidth), H = height, L = 34, R = 10, T = 12, B = 26;
    const max = niceMax(Math.max(1, ...series.flatMap((x) => x.values)));
    const x = (i) => L + (labels.length === 1 ? 0.5 : i / (labels.length - 1)) * (W - L - R);
    const y = (v) => T + (1 - v / max) * (H - T - B);
    const svg = s('svg', { viewBox: `0 0 ${W} ${H}`, height: H, role: 'img', 'aria-label': series.map((q) => `${q.name}: ${q.values.reduce((a, b) => a + b, 0)} in this period`).join('. ') });
    for (let i = 0; i <= 4; i++) {
      const v = (max / 4) * i, yy = y(v);
      svg.append(s('line', { class: 'grid', x1: L, x2: W - R, y1: yy, y2: yy }), s('text', { class: 'axis', x: L - 8, y: yy + 4, 'text-anchor': 'end' }, document.createTextNode(Number.isInteger(v) ? fmt(v) : '')));
    }
    const every = Math.max(1, Math.ceil(labels.length / Math.max(2, Math.floor((W - L - R) / 74))));
    labels.forEach((d, i) => { if (i % every === 0 || i === labels.length - 1) { if (i !== labels.length - 1 && labels.length - 1 - i < every) return; svg.append(s('text', { class: 'axis', x: x(i), y: H - 6, 'text-anchor': i === 0 ? 'start' : i === labels.length - 1 ? 'end' : 'middle' }, document.createTextNode(new Date(d + 'T00:00:00Z').toLocaleDateString(loc(), { day: 'numeric', month: 'short', timeZone: 'UTC' })))); } });
    const defs = s('defs'); svg.append(defs);
    series.forEach((q, si) => {
      const pts = q.values.map((v, i) => [x(i), y(v)]);
      const d = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
      const gid = 'g' + si + Math.random().toString(36).slice(2, 7);
      defs.append(s('linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 }, s('stop', { offset: '0', 'stop-color': q.color, 'stop-opacity': '.22' }), s('stop', { offset: '1', 'stop-color': q.color, 'stop-opacity': '0' })));
      const area = s('path', { d: `${d} L${pts.at(-1)[0]} ${y(0)} L${pts[0][0]} ${y(0)} Z`, fill: `url(#${gid})`, opacity: still() ? 1 : 0 });
      const line = s('path', { d, fill: 'none', stroke: q.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' });
      svg.append(area, line);
      if (!still()) {
        const len = line.getTotalLength ? line.getTotalLength() : 1000;
        line.style.strokeDasharray = len; line.style.strokeDashoffset = len; line.getBoundingClientRect();
        line.style.transition = `stroke-dashoffset 1.1s cubic-bezier(.2,.7,.2,1) ${si * 0.12}s`; line.style.strokeDashoffset = 0;
        area.style.transition = `opacity .7s ease ${0.5 + si * 0.12}s`; requestAnimationFrame(() => { area.style.opacity = 1; });
      }
      // direct label at the line's end: identity is never colour alone
      const last = pts.at(-1);
      svg.append(s('circle', { cx: last[0], cy: last[1], r: 4, fill: q.color, stroke: 'var(--surface)', 'stroke-width': 2 }));
    });
    const cross = s('line', { class: 'cross', y1: T, y2: H - B });
    const dots = series.map((q) => s('circle', { r: 5, fill: q.color, stroke: 'var(--surface)', 'stroke-width': 2, opacity: 0 }));
    const hit = s('rect', { x: L, y: 0, width: W - L - R, height: H, fill: 'transparent', tabindex: 0, 'aria-label': 'Chart. Use left and right arrow keys to read each day.' });
    svg.append(cross, ...dots, hit);
    let at = -1;
    const show = (i) => {
      at = Math.max(0, Math.min(labels.length - 1, i));
      cross.setAttribute('x1', x(at)); cross.setAttribute('x2', x(at)); cross.style.opacity = 1;
      series.forEach((q, k) => { dots[k].setAttribute('cx', x(at)); dots[k].setAttribute('cy', y(q.values[at])); dots[k].setAttribute('opacity', 1); });
      tip.replaceChildren(h('b', { text: new Date(labels[at] + 'T00:00:00Z').toLocaleDateString(loc(), { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' }) }),
        ...series.map((q) => h('div', {}, h('span', { style: 'display:inline-flex;align-items:center;gap:7px' }, h('i', { class: 'key', style: `background:${q.color}` }), q.name), h('span', { text: fmt(q.values[at]) }))));
      const px = (x(at) / W) * host.clientWidth; tip.classList.add('is-on');
      tip.style.left = Math.min(host.clientWidth - tip.offsetWidth - 4, Math.max(4, px + (px > host.clientWidth * 0.6 ? -tip.offsetWidth - 14 : 14))) + 'px'; tip.style.top = '8px';
    };
    const hide = () => { cross.style.opacity = 0; dots.forEach((d) => d.setAttribute('opacity', 0)); tip.classList.remove('is-on'); };
    hit.addEventListener('pointermove', (e) => { const r = svg.getBoundingClientRect(); const px = ((e.clientX - r.left) / r.width) * W; show(Math.round(((px - L) / (W - L - R)) * (labels.length - 1))); });
    hit.addEventListener('pointerleave', hide); hit.addEventListener('blur', hide);
    hit.addEventListener('keydown', (e) => { if (e.key === 'ArrowRight') { show(at + 1); e.preventDefault(); } if (e.key === 'ArrowLeft') { show(at < 0 ? labels.length - 1 : at - 1); e.preventDefault(); } });
    host.prepend(svg);
  };
  host.append(tip); draw();
  let w = host.clientWidth; const ro = new ResizeObserver(() => { if (Math.abs(host.clientWidth - w) > 8) { w = host.clientWidth; draw(); } }); ro.observe(host);
}

/** Part of a whole, two or three parts only. items: [{name,color,value}] */
export function donut(host, { items, label }) {
  host.replaceChildren(); host.classList.add('donut', 'chart');
  const total = items.reduce((a, b) => a + Number(b.value), 0);
  const R = 70, C = 2 * Math.PI * R, gap = total && items.filter((i) => i.value > 0).length > 1 ? 3 : 0;
  const mid = s('text', { class: 'donut__mid', x: 90, y: 92, 'text-anchor': 'middle' }, document.createTextNode('0'));
  const svg = s('svg', { viewBox: '0 0 180 180', role: 'img', 'aria-label': items.map((i) => `${i.name}: ${i.value}`).join(', ') },
    s('circle', { cx: 90, cy: 90, r: R, fill: 'none', stroke: 'var(--raised)', 'stroke-width': 18 }));
  const tip = h('div', { class: 'tip' });
  let off = 0;
  items.forEach((it, i) => {
    const frac = total ? it.value / total : 0, len = Math.max(0, frac * C - gap);
    const arc = s('circle', { cx: 90, cy: 90, r: R, fill: 'none', stroke: it.color, 'stroke-width': 18, 'stroke-linecap': 'butt', transform: `rotate(${-90 + (off / C) * 360} 90 90)`, 'stroke-dasharray': `0 ${C}`, style: 'cursor:pointer;transition:stroke-dasharray 1s cubic-bezier(.2,.7,.2,1) ' + i * 0.15 + 's, stroke-width .15s' });
    arc.addEventListener('pointerenter', () => { arc.setAttribute('stroke-width', 22); tip.replaceChildren(h('b', { text: it.name }), h('div', {}, h('span', { text: 'Members' }), h('span', { text: fmt(it.value) })), h('div', {}, h('span', { text: 'Share' }), h('span', { text: total ? Math.round(frac * 100) + '%' : '0%' }))); tip.classList.add('is-on'); tip.style.left = '50%'; tip.style.top = '4px'; tip.style.transform = 'translateX(-50%)'; });
    arc.addEventListener('pointerleave', () => { arc.setAttribute('stroke-width', 18); tip.classList.remove('is-on'); });
    svg.append(arc); off += frac * C;
    if (still()) arc.setAttribute('stroke-dasharray', `${len} ${C}`); else requestAnimationFrame(() => requestAnimationFrame(() => arc.setAttribute('stroke-dasharray', `${len} ${C}`)));
  });
  svg.append(mid, s('text', { class: 'donut__sub', x: 90, y: 112, 'text-anchor': 'middle' }, document.createTextNode(label)));
  countUp(mid, total);
  host.append(svg, tip, h('div', { class: 'legend' }, items.map((it) => h('span', {}, h('i', { class: 'key', style: `background:${it.color}` }), it.name, h('b', { text: fmt(it.value) }), h('span', { class: 'faint', text: total ? Math.round((it.value / total) * 100) + '%' : '0%' })))));
}

/** Magnitude by category, horizontal. rows: [{label, lead?:Node, segments:[{name,color,value}]}] */
export function bars(host, { rows }) {
  host.replaceChildren(); host.classList.add('bars', 'chart');
  const max = Math.max(1, ...rows.map((r) => r.segments.reduce((a, b) => a + Number(b.value), 0)));
  const tip = h('div', { class: 'tip' }); const segs = [];
  rows.forEach((r) => {
    const total = r.segments.reduce((a, b) => a + Number(b.value), 0);
    const track = h('div', { class: 'bar__track' });
    r.segments.filter((g) => Number(g.value) > 0).forEach((g) => { const el = h('div', { class: 'bar__seg', style: `background:${g.color}` }); segs.push([el, (Number(g.value) / max) * 100]); track.append(el); });
    const row = h('div', { class: 'bar', tabindex: 0, 'aria-label': `${r.label}: ${total}. ` + r.segments.map((g) => `${g.name} ${g.value}`).join(', ') },
      h('div', { class: 'bar__label' }, r.lead || null, h('span', { text: r.label, title: r.label })), track, h('div', { class: 'bar__val', text: fmt(total) }));
    const on = () => { tip.replaceChildren(h('b', { text: r.label }), ...r.segments.map((g) => h('div', {}, h('span', { style: 'display:inline-flex;align-items:center;gap:7px' }, h('i', { class: 'key', style: `background:${g.color}` }), g.name), h('span', { text: fmt(g.value) })))); tip.classList.add('is-on'); tip.style.top = row.offsetTop + row.offsetHeight + 6 + 'px'; tip.style.right = '0px'; };
    const offf = () => tip.classList.remove('is-on');
    row.addEventListener('pointerenter', on); row.addEventListener('pointerleave', offf); row.addEventListener('focus', on); row.addEventListener('blur', offf);
    host.append(row);
  });
  host.append(tip);
  const grow = () => segs.forEach(([el, w]) => { el.style.width = w + '%'; });
  if (still()) { segs.forEach(([el]) => { el.style.transition = 'none'; }); grow(); } else requestAnimationFrame(() => requestAnimationFrame(grow));
}

/** A small trend beside a headline number. No axes, no tooltip: the tile carries the value. */
export function sparkline(values, color = 'var(--accent)', w = 96, hgt = 30) {
  const max = Math.max(1, ...values), n = values.length;
  const pts = values.map((v, i) => [(i / Math.max(1, n - 1)) * (w - 4) + 2, hgt - 3 - (v / max) * (hgt - 6)]);
  return s('svg', { viewBox: `0 0 ${w} ${hgt}`, width: w, height: hgt, 'aria-hidden': 'true' }, s('path', { d: pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' '), fill: 'none', stroke: color, 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }), s('circle', { cx: pts.at(-1)[0], cy: pts.at(-1)[1], r: 3, fill: color }));
}
export function legend(items) { return h('div', { class: 'legend' }, items.map((i) => h('span', {}, h('i', { class: 'key', style: `background:${i.color}` }), i.name))); }
