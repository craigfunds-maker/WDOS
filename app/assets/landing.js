/* ═══════════════════════════════════════════
   WODDI INSTITUTE — Landing Page (v178)
   Premium, app-like home page. On phones it behaves like a native app: slim top bar that tightens on
   scroll, a fixed bottom tab bar, swipeable rails with snap, sheet-style sections and large tap targets.
   Signature element: the two networks as phone "story" cards with network-coloured rings.
   Inter throughout, Font Awesome icons, live numbers from public_platform_stats() (no invented figures).
   Hooks other modules rely on are kept: #landing, .lp-nav, .lp-hero h1, [data-lp-stat], .lp-foot .grid,
   #networks, #learn, #how, #marketplace-cta, #theme-icon-landing, #nav-get-started-sm.
═══════════════════════════════════════════ */

var LP_STATS_FALLBACK = { courses: 210, gmn: 137, nnn: 73, languages: 50, learners: 184, certified: 27, countries: 8 };
/* v184: courses per network, counted from the app's own course catalogues (the same lists each network's Course Hub shows) */
function lpCourseCounts() {
  var gmn = LP_STATS_FALLBACK.gmn, nnn = LP_STATS_FALLBACK.nnn;
  try { if (typeof getAllSkills === 'function') { var a = getAllSkills(); if (a && a.length) gmn = a.length; } } catch (e) {}
  try {
    var b = window.WODDI_WNNN_BLUEPRINT_COURSES || (typeof WODDI_WNNN_BLUEPRINT_COURSES !== 'undefined' ? WODDI_WNNN_BLUEPRINT_COURSES : null);
    var el = window.WODDI_WNNN_ELECTIVE_COURSES || (typeof WODDI_WNNN_ELECTIVE_COURSES !== 'undefined' ? WODDI_WNNN_ELECTIVE_COURSES : null);
    var n = (b && b.length || 0) + (el && el.length || 0); if (n) nnn = n;
  } catch (e) {}
  return { gmn: gmn, nnn: nnn, total: gmn + nnn };
}
/* languages: 5 on the bar + Hausa, Yoruba, Igbo and the other African languages in the dropdown */
function lpLangCount() { try { return window.WODDI_LP_LANG_COUNT || LP_STATS_FALLBACK.languages; } catch (e) { return LP_STATS_FALLBACK.languages; } }

function lpPicture(name, alt, w, h, eager) {
  return '<picture>' +
    '<source srcset="assets/landing/' + name + '.webp" type="image/webp">' +
    '<img src="assets/landing/' + name + '.jpg" alt="' + alt + '" width="' + w + '" height="' + h + '"' +
    (eager ? ' fetchpriority="high"' : ' loading="lazy"') + ' decoding="async">' +
    '</picture>';
}
function lpLogo(net, size) {
  var f = net === 'nnn' ? 'logo-nurture-nextgen-network' : 'logo-good-mother-network';
  var alt = net === 'nnn' ? 'WODDI Nurture NextGen Network (WNNN) logo' : 'WODDI Good Mother Network (WGMN) logo';
  return '<picture><source srcset="assets/landing/' + f + '.webp" type="image/webp">' +
    '<img src="assets/landing/' + f + '.png" alt="' + alt + '" width="' + size + '" height="' + size + '" loading="lazy" decoding="async"></picture>';
}

var LP_CSS = `
#landing{--lp-bg:#FFFCFD;--lp-surface:#FFFFFF;--lp-soft:#FBEFF4;--lp-ink:#1F1219;--lp-muted:#665A62;--lp-line:#F0E2EA;
  --lp-mag:#D4006A;--lp-mag-deep:#A80054;--lp-mag-soft:#FCE7F1;--lp-lem:#7CB518;--lp-lem-deep:#4F7F0B;--lp-lem-soft:#EEF7DF;
  --lp-plum:#2A0A1D;--lp-plum-2:#3D0F2B;--lp-shadow:0 24px 48px -28px rgba(70,10,45,.45);
  --lp-tab:64px;
  font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--lp-bg);color:var(--lp-ink);
  -webkit-font-smoothing:antialiased;overflow-x:hidden;overflow-x:clip;-webkit-tap-highlight-color:transparent}
html.dark #landing{--lp-bg:#120A10;--lp-surface:#1C1219;--lp-soft:#24171F;--lp-ink:#F7EEF3;--lp-muted:#B9A9B3;--lp-line:#33232D;
  --lp-mag:#FF3D96;--lp-mag-deep:#E0127A;--lp-mag-soft:#3A1428;--lp-lem:#9FD84A;--lp-lem-deep:#A6E07C;--lp-lem-soft:#1F2C12;
  --lp-plum:#1E0714;--lp-plum-2:#2E0B20;--lp-shadow:0 24px 48px -28px rgba(0,0,0,.8)}
#landing *{box-sizing:border-box}
#landing img{max-width:100%;display:block}
#landing a{color:inherit}
#landing .lp-wrap{width:min(1200px,100%);margin:0 auto;padding-inline:20px}
#landing button,#landing a{touch-action:manipulation}
#landing button:focus-visible,#landing a:focus-visible{outline:3px solid var(--lp-mag);outline-offset:3px;border-radius:12px}

/* top bar */
#landing .lp-nav{position:sticky;top:0;z-index:40;background:color-mix(in srgb,var(--lp-bg) 92%,transparent);border-bottom:1px solid transparent;transition:border-color .2s,box-shadow .2s;padding-top:env(safe-area-inset-top,0px)}
#landing .lp-nav.scrolled{border-bottom-color:var(--lp-line);box-shadow:0 8px 24px -18px rgba(0,0,0,.25)}
#landing .lp-nav .lp-wrap{display:flex;align-items:center;gap:14px;min-height:96px;padding-block:6px;transition:min-height .2s}
#landing .lp-nav.scrolled .lp-wrap{min-height:88px}
#landing .lp-brand{display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0}
#landing .lp-brand img{width:78px;height:78px;border-radius:18px;object-fit:contain;background:#fff;box-shadow:0 0 0 1px var(--lp-line)}
#landing .lp-brand b{font-weight:800;font-size:20px;letter-spacing:-.02em}
#landing .lp-brand b span{color:var(--lp-mag)}
#landing .lp-links{display:flex;gap:2px;margin-left:14px}
#landing .lp-links a{text-decoration:none;font-weight:600;font-size:14.5px;color:var(--lp-muted);padding:9px 13px;border-radius:999px}
#landing .lp-links a:hover{color:var(--lp-ink);background:var(--lp-soft)}
#landing .lp-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
#landing .lp-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:48px;padding:0 22px;border-radius:16px;
  font:inherit;font-weight:700;font-size:15px;border:1.5px solid transparent;cursor:pointer;text-decoration:none;transition:transform .12s,background .15s,border-color .15s;white-space:nowrap}
#landing .lp-btn:active{transform:scale(.97)}
#landing .lp-btn.primary{background:var(--lp-mag);color:#fff;box-shadow:0 12px 26px -12px rgba(212,0,106,.75)}
#landing .lp-btn.primary:hover{background:var(--lp-mag-deep)}
#landing .lp-btn.ghost{background:transparent;color:var(--lp-ink);border-color:var(--lp-line)}
#landing .lp-btn.ghost:hover{border-color:var(--lp-mag);color:var(--lp-mag)}
#landing .lp-btn.light{background:#fff;color:#A80054}
#landing .lp-btn.glass{background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.28)}
#landing .lp-btn.glass:hover{background:rgba(255,255,255,.18)}
#landing .lp-btn.sm{min-height:40px;padding:0 16px;font-size:14px;border-radius:13px}
#landing .lp-icon{width:44px;height:44px;border-radius:14px;border:1.5px solid var(--lp-line);background:transparent;color:var(--lp-ink);cursor:pointer;display:grid;place-items:center}

/* hero stage */
#landing .lp-hero{position:relative;background:var(--lp-plum);color:#fff;overflow:hidden;isolation:isolate}
#landing .lp-hero:before{content:"";position:absolute;inset:-30% -10% auto auto;width:780px;height:780px;border-radius:50%;
  background:radial-gradient(closest-side,rgba(212,0,106,.55),rgba(212,0,106,0));z-index:-1}
#landing .lp-hero:after{content:"";position:absolute;left:-200px;bottom:-260px;width:620px;height:620px;border-radius:50%;
  background:radial-gradient(closest-side,rgba(124,181,24,.25),rgba(124,181,24,0));z-index:-1}
#landing .lp-hero .lp-wrap{display:grid;grid-template-columns:1.08fr .92fr;gap:48px;align-items:center;padding-block:64px 120px}
#landing .lp-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.1);
  border:1px solid rgba(255,255,255,.16);font-weight:600;font-size:13.5px;color:#FFE3F0}
#landing .lp-pill i{color:#B9EC6B}
#landing .lp-h1{font-size:clamp(40px,5.4vw,72px);line-height:1.02;letter-spacing:-.04em;font-weight:800;margin:22px 0 20px;color:#fff;max-width:15ch;text-wrap:balance}
#landing .lp-tagline{font-size:clamp(18px,1.75vw,22px);line-height:1.35;font-weight:700;letter-spacing:-.01em;color:#fff;max-width:34ch;margin:0 0 10px;text-wrap:balance}
#landing .lp-lede{font-size:clamp(16.5px,1.5vw,19px);line-height:1.6;color:#F2D9E6;max-width:56ch;margin:0}
#landing .lp-ctas{display:flex;gap:10px;flex-wrap:wrap;margin-top:30px}
#landing .lp-ctas .lp-btn{min-height:54px;padding:0 26px;font-size:16px}
#landing .lp-trust{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:26px;font-size:14px;color:#E9C8D9}
#landing .lp-trust a,#landing .lp-trust span{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:600}
#landing .lp-trust a{color:#fff}
#landing .lp-trust i{color:#B9EC6B}

/* signature: network story cards */
#landing .lp-stories{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:end}
#landing .lp-story{position:relative;display:flex;flex-direction:column;gap:12px;width:100%;border:0;padding:0;background:none;cursor:pointer;text-align:left;color:#fff;font:inherit;transition:transform .25s}
#landing .lp-story.nnn{transform:translateY(-22px)}
#landing .lp-story.gmn{transform:translateY(12px)}
#landing .lp-story:hover{transform:translateY(-6px)}
#landing .lp-story .head{display:flex;align-items:center;gap:14px;min-height:100px}
#landing .lp-story .head img{width:96px;height:96px;border-radius:50%;background:#fff;flex-shrink:0;box-shadow:0 0 0 3px #fff,0 10px 22px -10px rgba(0,0,0,.7);display:block}
#landing .lp-story.nnn .head img{box-shadow:0 0 0 3px #9FD84A,0 10px 22px -10px rgba(0,0,0,.7)}
#landing .lp-story.gmn .head img{box-shadow:0 0 0 3px #FF5CA8,0 10px 22px -10px rgba(0,0,0,.7)}
#landing .lp-story .ht{display:flex;flex-direction:column;align-items:flex-start;gap:6px;min-width:0}
#landing .lp-story .ht b{display:block;font-size:15.5px;font-weight:800;line-height:1.22;letter-spacing:-.01em;text-wrap:balance}
#landing .lp-story .age{display:inline-flex;align-items:center;gap:7px;padding:5px 11px;border-radius:999px;background:rgba(255,255,255,.94);color:#2A0A1D;font-weight:800;font-size:12.5px;white-space:nowrap}
#landing .lp-story.nnn .age i{color:#4F7F0B}#landing .lp-story.gmn .age i{color:#D4006A}
#landing .lp-story .frame{position:relative;display:block;width:100%;aspect-ratio:9/13;border-radius:28px;overflow:hidden;background:#000;box-shadow:0 30px 60px -30px rgba(0,0,0,.8);transition:transform .25s}
#landing .lp-story.nnn .frame{transform:rotate(-3deg)}
#landing .lp-story.gmn .frame{transform:rotate(3deg)}
#landing .lp-story:hover .frame{transform:rotate(0)}
#landing .lp-story .frame:before{content:"";position:absolute;inset:0;border-radius:28px;padding:4px;z-index:2;pointer-events:none;
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude}
#landing .lp-story.nnn .frame:before{background:linear-gradient(160deg,#B9EC6B,#7CB518 45%,#2E6B00)}
#landing .lp-story.gmn .frame:before{background:linear-gradient(160deg,#FF7AB8,#D4006A 45%,#7A0040)}
#landing .lp-story .frame picture,#landing .lp-story .frame img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
#landing .lp-story .frame:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.35) 0%,rgba(0,0,0,0) 20%);z-index:1}
#landing .lp-bars{position:absolute;left:16px;right:16px;top:14px;display:flex;gap:5px;z-index:3}
#landing .lp-bars i{flex:1;height:3px;border-radius:3px;background:rgba(255,255,255,.35);overflow:hidden;position:relative}
#landing .lp-bars i:first-child:after{content:"";position:absolute;inset:0;background:#fff;transform-origin:left;animation:lpBar 5s linear infinite}
@keyframes lpBar{from{transform:scaleX(0)}to{transform:scaleX(1)}}
#landing .lp-tapnote{grid-column:1/-1;margin:26px 0 0;text-align:center;color:#F2D9E6;font-size:15px;font-weight:700}
#landing .lp-tapnote i{color:#B9EC6B;margin-right:6px}

/* live numbers as app widgets, lifted over the stage */
#landing .lp-stats{position:relative;z-index:2;margin-top:-70px}
#landing .lp-stats .lp-wrap{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
#landing .lp-stat{background:var(--lp-surface);border:1px solid var(--lp-line);border-radius:22px;padding:18px 18px 16px;box-shadow:var(--lp-shadow);display:flex;flex-direction:column;gap:4px}
#landing .lp-stat i{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:var(--lp-mag-soft);color:var(--lp-mag);font-size:16px;margin-bottom:8px}
#landing .lp-stat:nth-child(even) i{background:var(--lp-lem-soft);color:var(--lp-lem-deep)}
#landing .lp-stat b{font-size:clamp(28px,3vw,38px);font-weight:800;letter-spacing:-.03em;font-variant-numeric:tabular-nums;line-height:1}
#landing .lp-stat span{font-size:13.5px;font-weight:600;color:var(--lp-muted)}
#landing .lp-stat .lp-sub{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
#landing .lp-stat .lp-sub em{font-style:normal;font-size:11.5px;font-weight:700;padding:3px 8px;border-radius:999px;background:var(--lp-mag-soft);color:var(--lp-mag);white-space:nowrap}
#landing .lp-stat .lp-sub em+em{background:var(--lp-lem-soft);color:var(--lp-lem-deep)}

/* sections */
#landing .lp-sec{padding-block:88px}
#landing .lp-h2{font-size:clamp(28px,3.6vw,46px);line-height:1.08;letter-spacing:-.03em;font-weight:800;margin:0 0 14px;text-wrap:balance}
#landing .lp-head{max-width:640px;margin-bottom:36px}
#landing .lp-head p{color:var(--lp-muted);font-size:17px;line-height:1.6;margin:0}
#landing .lp-head.center{text-align:center;margin-inline:auto}

/* networks */
#landing .lp-nets{display:grid;grid-template-columns:1fr 1fr;gap:18px}
#landing .lp-net{position:relative;border-radius:30px;padding:28px;overflow:hidden;display:flex;flex-direction:column;gap:16px}
#landing .lp-net.gmn{background:linear-gradient(165deg,var(--lp-mag-soft),var(--lp-surface) 60%);border:1px solid color-mix(in srgb,var(--lp-mag) 22%,var(--lp-line))}
#landing .lp-net.nnn{background:linear-gradient(165deg,var(--lp-lem-soft),var(--lp-surface) 60%);border:1px solid color-mix(in srgb,var(--lp-lem) 30%,var(--lp-line))}
#landing .lp-net-top{display:flex;align-items:center;gap:16px}
#landing .lp-net-top img{width:96px;height:96px;border-radius:50%;background:#fff;box-shadow:0 10px 24px -14px rgba(0,0,0,.4)}
#landing .lp-net-top picture{flex-shrink:0;display:block;line-height:0}
#landing .lp-net h3{font-size:21px;font-weight:800;letter-spacing:-.02em;margin:0;line-height:1.2}
#landing .lp-age{display:inline-flex;margin-top:6px;font-size:13px;font-weight:700;padding:4px 11px;border-radius:999px}
#landing .lp-net.gmn .lp-age{background:var(--lp-mag);color:#fff}#landing .lp-net.nnn .lp-age{background:var(--lp-lem);color:#fff}
#landing .lp-net .tag{font-size:16.5px;font-weight:700;margin:0;letter-spacing:-.01em}
#landing .lp-net ul{list-style:none;margin:0;padding:0;display:grid;gap:10px}
#landing .lp-net li{display:flex;gap:11px;align-items:flex-start;font-size:15px;line-height:1.45}
#landing .lp-net li i{margin-top:3px}
#landing .lp-net.gmn li i{color:var(--lp-mag)}#landing .lp-net.nnn li i{color:var(--lp-lem-deep)}
#landing .lp-net .lp-btn{margin-top:auto;align-self:flex-start}
#landing .lp-net.nnn .lp-btn.primary{background:#5E9612;box-shadow:0 12px 26px -12px rgba(94,150,18,.8)}
#landing .lp-net.nnn .lp-btn.primary:hover{background:#4F7F0B}

/* learn: app showcase */
#landing .lp-split{display:grid;grid-template-columns:.85fr 1.15fr;gap:56px;align-items:center}
#landing .lp-phone-wrap{position:relative;max-width:330px;margin:0 auto}
#landing .lp-phone{border-radius:44px;padding:11px;background:#16080F;box-shadow:0 50px 80px -40px rgba(60,5,35,.8),0 0 0 1px rgba(255,255,255,.06) inset}
#landing .lp-phone img{border-radius:34px;width:100%;height:auto}
#landing .lp-float{position:absolute;display:flex;align-items:center;gap:10px;padding:10px 14px 10px 10px;border-radius:18px;background:var(--lp-surface);
  border:1px solid var(--lp-line);box-shadow:var(--lp-shadow);font-size:12.5px;color:var(--lp-muted);max-width:230px}
#landing .lp-float b{display:block;color:var(--lp-ink);font-size:13.5px}
#landing .lp-float .dot{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;background:var(--lp-mag);color:#fff;flex-shrink:0}
#landing .lp-float.f1{left:-56px;top:22%}
#landing .lp-float.f2{right:-60px;bottom:16%}
#landing .lp-float.f2 .dot{background:var(--lp-lem-deep)}
#landing .lp-copy{color:var(--lp-muted);font-size:17px;line-height:1.65;margin:0;max-width:58ch}
#landing .lp-feats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:28px}
#landing .lp-feat{display:grid;grid-template-columns:44px 1fr;gap:4px 14px;padding:16px;border-radius:20px;background:var(--lp-soft)}
#landing .lp-feat i{grid-row:span 2;width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:var(--lp-surface);color:var(--lp-mag);font-size:18px}
#landing .lp-feat b{font-size:15.5px;font-weight:800;letter-spacing:-.01em}
#landing .lp-feat span{font-size:14px;line-height:1.5;color:var(--lp-muted)}

/* courses: real catalogue, swipe on phones */
#landing .lp-courses{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;list-style:none;margin:0;padding:0}
#landing .lp-course{display:flex;flex-direction:column;width:100%;text-align:left;padding:0;border:1px solid var(--lp-line);border-radius:22px;overflow:hidden;
  background:var(--lp-surface);color:var(--lp-ink);font:inherit;cursor:pointer;transition:transform .2s ease,box-shadow .2s ease}
#landing .lp-course:hover{transform:translateY(-3px);box-shadow:var(--lp-shadow)}
#landing .lp-course:focus-visible{outline:3px solid var(--lp-mag);outline-offset:3px}
#landing .lp-course picture{display:block;aspect-ratio:16/9;background:var(--lp-soft)}
#landing .lp-course img{width:100%;height:100%;object-fit:cover;display:block}
#landing .lp-course .body{display:flex;flex-direction:column;gap:10px;padding:14px 16px 16px;flex:1}
#landing .lp-course b{font-size:15.5px;font-weight:800;line-height:1.3;letter-spacing:-.01em}
#landing .lp-course .meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:auto}
#landing .lp-course .meta span{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;background:var(--lp-soft);color:var(--lp-muted)}
#landing .lp-course .meta span.free{background:var(--lp-lem-soft);color:var(--lp-lem-deep)}
#landing .lp-courses-foot{display:flex;justify-content:center;margin-top:28px}
@media (max-width:1020px){#landing .lp-courses{grid-template-columns:repeat(2,1fr)}}
@media (max-width:720px){
  #landing .lp-courses{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px;margin-inline:-16px;padding:0 16px 6px;scrollbar-width:none}
  #landing .lp-courses::-webkit-scrollbar{display:none}
  #landing .lp-courses li{flex:0 0 74%;scroll-snap-align:start;display:flex}
  #landing .lp-course:hover{transform:none}
  #landing .lp-courses-foot .lp-btn{width:100%}
}

/* journey: a real sequence, so it is numbered */
#landing .lp-journey{background:var(--lp-soft);border-radius:40px 40px 0 0}
#landing .lp-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;position:relative;list-style:none;margin:0;padding:0;counter-reset:st}
#landing .lp-steps:before{content:"";position:absolute;left:28px;right:28px;top:27px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--lp-mag),var(--lp-lem))}
#landing .lp-step{position:relative;display:flex;flex-direction:column;gap:10px}
#landing .lp-step .n{width:56px;height:56px;border-radius:50%;display:grid;place-items:center;background:var(--lp-surface);border:3px solid var(--lp-mag);
  color:var(--lp-mag);font-size:18px;position:relative;z-index:1;box-shadow:0 0 0 6px var(--lp-soft)}
#landing .lp-step:nth-child(n+3) .n{border-color:var(--lp-lem);color:var(--lp-lem-deep)}
#landing .lp-step small{font-size:13px;font-weight:800;color:var(--lp-muted)}
#landing .lp-step h3{margin:0;font-size:22px;font-weight:800;letter-spacing:-.02em}
#landing .lp-step p{margin:0;color:var(--lp-muted);font-size:17px;line-height:1.6}
#landing #how .lp-head p{font-size:19.5px;line-height:1.6}

/* after you graduate: marketplace + alumni */
#landing .lp-grad{display:grid;grid-template-columns:1.25fr .75fr;gap:18px}
#landing .lp-gcard{border-radius:30px;padding:28px;display:flex;flex-direction:column;gap:14px;border:1px solid var(--lp-line);background:var(--lp-surface)}
#landing .lp-gcard.market{background:linear-gradient(150deg,#2A0A1D,#5C0E3A);color:#fff;border:0}
#landing .lp-gcard h2{font-size:clamp(24px,2.6vw,34px);font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:0}
#landing .lp-gcard p{margin:0;font-size:15.5px;line-height:1.6;color:var(--lp-muted)}
#landing .lp-gcard.market p{color:#F2D9E6}
#landing .lp-gico{width:48px;height:48px;border-radius:15px;display:grid;place-items:center;font-size:20px;background:var(--lp-mag-soft);color:var(--lp-mag)}
#landing .lp-gcard.market .lp-gico{background:rgba(255,255,255,.12);color:#fff}
#landing .lp-chips{display:flex;gap:8px;flex-wrap:wrap}
#landing .lp-chips button{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 14px;border-radius:999px;border:1px solid rgba(255,255,255,.22);
  background:rgba(255,255,255,.08);color:#fff;font:inherit;font-weight:600;font-size:13.5px;cursor:pointer}
#landing .lp-chips button:hover{background:rgba(255,255,255,.16)}
#landing .lp-gcard .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:auto}

/* closing call */
#landing .lp-cta{margin-block:88px;border-radius:36px;background:var(--lp-plum);color:#fff;padding:52px 28px;text-align:center;position:relative;overflow:hidden;isolation:isolate}
#landing .lp-cta:before{content:"";position:absolute;inset:auto -120px -220px auto;width:520px;height:520px;border-radius:50%;background:radial-gradient(closest-side,rgba(212,0,106,.5),transparent);z-index:-1}
#landing .lp-netbtn{display:inline-flex;align-items:center;gap:14px;padding:10px 22px 10px 10px;border-radius:999px;text-align:left;min-height:80px;max-width:100%}
#landing .lp-netbtn img{width:60px;height:60px;border-radius:50%;background:#fff;flex-shrink:0;box-shadow:0 0 0 3px rgba(255,255,255,.9)}
#landing .lp-netbtn picture,#landing .lp-story .head picture{flex-shrink:0;display:block;line-height:0}
#landing .lp-netbtn span{display:flex;flex-direction:column;gap:2px;min-width:0}
#landing .lp-netbtn b{font-size:16px;font-weight:800;line-height:1.2}
#landing .lp-netbtn small{font-size:13px;font-weight:600;opacity:.9}
#landing .lp-cta h2{font-size:clamp(30px,4vw,50px);font-weight:800;letter-spacing:-.035em;margin:0 0 10px;line-height:1.05}
#landing .lp-cta p{margin:0 auto 24px;color:#F2D9E6;font-size:17px;max-width:48ch;line-height:1.6}
#landing .lp-cta .row{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
#landing .lp-btn.gmnb{background:#D4006A;color:#fff}#landing .lp-btn.nnnb{background:#7CB518;color:#fff}

/* footer */
#landing .lp-foot{background:#1A0712;color:#E9D6E0;padding:56px 0 calc(28px + env(safe-area-inset-bottom,0px))}
#landing .lp-foot .grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:28px}
#landing .lp-foot p{font-size:14px;line-height:1.6;color:#C7B0BD;max-width:34ch}
#landing .lp-foot h4{margin:0 0 12px;font-size:14px;font-weight:800;color:#fff}
#landing .lp-foot ul{list-style:none;margin:0;padding:0;display:grid;gap:9px}
#landing .lp-foot a{text-decoration:none;color:#C7B0BD;font-size:14px}
#landing .lp-foot a:hover{color:#fff}
#landing .lp-foot .nets{display:flex;gap:10px;margin-top:14px}
#landing .lp-foot .nets img{width:48px;height:48px;border-radius:50%;background:#fff}
#landing .lp-foot .bottom{display:flex;flex-wrap:wrap;gap:8px 20px;justify-content:space-between;border-top:1px solid rgba(255,255,255,.1);margin-top:36px;padding-top:20px;font-size:13px;color:#A8909D}

/* app tab bar (phones) */
#landing .lp-tabbar{display:none}

/* one orchestrated entrance: the story cards rise in */
@media (prefers-reduced-motion:no-preference){
  #landing .lp-story{animation:lpRise .8s cubic-bezier(.2,.9,.25,1) both}
  #landing .lp-story.gmn{animation-delay:.12s}
  @keyframes lpRise{from{opacity:0;translate:0 60px}to{opacity:1;translate:0 0}}
}
@media (prefers-reduced-motion:reduce){#landing .lp-bars i:first-child:after{animation:none;transform:scaleX(.6)}}

/* tablet */
@media (max-width:1020px){
  #landing .lp-links{display:none}
  #landing .lp-hero .lp-wrap{grid-template-columns:1fr;gap:36px;padding-block:44px 110px}
  #landing .lp-h1{max-width:16ch}
  #landing .lp-stories{max-width:620px}
  #landing .lp-stats .lp-wrap{grid-template-columns:repeat(3,1fr)}
  #landing .lp-split{grid-template-columns:1fr;gap:40px}
  #landing .lp-steps{grid-template-columns:1fr 1fr;row-gap:28px}
  #landing .lp-steps:before{display:none}
  #landing .lp-grad{grid-template-columns:1fr}
  #landing .lp-foot .grid{grid-template-columns:1fr 1fr}
}

/* phones: native-app behaviour */
@media (max-width:720px){
  #landing{padding-bottom:calc(var(--lp-tab) + env(safe-area-inset-bottom,0px))}
  #landing .lp-wrap{padding-inline:16px}
  #landing .lp-nav .lp-wrap{min-height:72px;gap:8px;padding-block:4px}
  #landing .lp-nav.scrolled .lp-wrap{min-height:68px}
  #landing .lp-brand img{width:58px;height:58px;border-radius:14px}
  #landing .lp-brand b{font-size:17px}
  #landing #nav-get-started-sm{display:none}
  #landing .lp-actions .lp-btn{min-height:40px;padding:0 14px;font-size:14px;border-radius:12px}
  #landing .lp-icon{width:40px;height:40px;border-radius:12px}
  #landing .lp-hero .lp-wrap{padding-block:28px 96px;gap:26px}
  #landing .lp-h1{font-size:clamp(36px,10.5vw,46px);margin:16px 0 14px}
  #landing .lp-tagline{font-size:18px}
  #landing .lp-lede{font-size:15.5px}
  #landing .lp-ctas .lp-btn{flex:1;min-height:52px;padding:0 16px}
  #landing .lp-trust{gap:8px 14px;font-size:13px}
  /* story cards become a swipeable rail, edge to edge */
  #landing .lp-stories{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;margin-inline:-16px;padding:6px 16px 4px;scrollbar-width:none}
  #landing .lp-stories::-webkit-scrollbar{display:none}
  #landing .lp-story{flex:0 0 76%;scroll-snap-align:center}
  #landing .lp-story.nnn,#landing .lp-story.gmn,#landing .lp-story:hover,#landing .lp-story .frame,#landing .lp-story:hover .frame{transform:none}
  #landing .lp-story .frame{aspect-ratio:9/12}
  #landing .lp-story .head img{width:76px;height:76px}
  #landing .lp-tapnote{flex:0 0 100%;margin:14px 0 0;font-size:14px}
  #landing .lp-stats{margin-top:-64px}
  #landing .lp-stats .lp-wrap{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;scrollbar-width:none;padding-bottom:4px}
  #landing .lp-stats .lp-wrap::-webkit-scrollbar{display:none}
  #landing .lp-stat{flex:0 0 42%;scroll-snap-align:start;padding:14px;border-radius:18px}
  #landing .lp-sec{padding-block:56px}
  #landing .lp-head{margin-bottom:24px}
  #landing .lp-head p{font-size:15.5px}
  #landing .lp-nets{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px;margin-inline:-16px;padding:0 16px 6px;scrollbar-width:none}
  #landing .lp-nets::-webkit-scrollbar{display:none}
  #landing .lp-net{flex:0 0 88%;scroll-snap-align:center;padding:22px;border-radius:26px}
  #landing .lp-net-top img{width:76px;height:76px}
  #landing .lp-net h3{font-size:18px}
  #landing .lp-float.f1{left:-8px}#landing .lp-float.f2{right:-8px}
  #landing .lp-phone-wrap{max-width:260px}
  #landing .lp-feats{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;margin-inline:-16px;padding:0 16px 6px;scrollbar-width:none}
  #landing .lp-feats::-webkit-scrollbar{display:none}
  #landing .lp-feat{flex:0 0 78%;scroll-snap-align:start;grid-template-columns:1fr}
  #landing .lp-feat i{grid-row:auto;margin-bottom:6px}
  #landing .lp-journey{border-radius:28px 28px 0 0}
  #landing .lp-steps{grid-template-columns:1fr;gap:0}
  #landing .lp-step{display:grid;grid-template-columns:56px 1fr;column-gap:14px;padding-bottom:22px;position:relative}
  #landing .lp-step:not(:last-child):after{content:"";position:absolute;left:27px;top:60px;bottom:4px;width:3px;border-radius:3px;background:linear-gradient(var(--lp-mag),var(--lp-lem))}
  #landing .lp-step .n{grid-row:span 3}
  #landing .lp-gcard{padding:22px;border-radius:26px}
  #landing .lp-chips{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;margin-inline:-22px;padding:0 22px}
  #landing .lp-chips::-webkit-scrollbar{display:none}
  #landing .lp-chips button{flex-shrink:0}
  #landing .lp-cta{margin-block:56px;border-radius:28px;padding:36px 20px}
  #landing .lp-cta .row .lp-btn{width:100%}
  #landing .lp-net .lp-btn,#landing .lp-cta .lp-btn,#landing .lp-gcard .lp-btn{white-space:normal;text-align:center;line-height:1.25;padding-block:10px;height:auto}
  #landing .lp-net .lp-btn{align-self:stretch}
  #landing .lp-cta .lp-netbtn{justify-content:flex-start;text-align:left;border-radius:22px;padding:10px 16px 10px 10px}
  #landing .lp-netbtn img{width:56px;height:56px}
  #landing .lp-netbtn b{font-size:15px}
  #landing .lp-split>.lp-phone-wrap{order:2}
  #landing .lp-float{position:static;margin:12px auto 0;max-width:none}
  #landing .lp-phone-wrap{display:flex;flex-direction:column;align-items:stretch}
  #landing .lp-phone{max-width:240px;margin:0 auto}
  #landing .lp-foot .grid{grid-template-columns:1fr 1fr;gap:22px}
  #landing .lp-foot .grid>div:first-child{grid-column:1/-1}
  body:has(#landing .lp-tabbar) #woddi-top-btn{bottom:calc(80px + env(safe-area-inset-bottom,0px))!important}
  /* bottom tab bar */
  #landing .lp-tabbar{display:grid;grid-template-columns:repeat(5,1fr);position:fixed;left:0;right:0;bottom:0;z-index:60;
    background:color-mix(in srgb,var(--lp-surface) 96%,transparent);border-top:1px solid var(--lp-line);
    padding:6px 6px calc(6px + env(safe-area-inset-bottom,0px));box-shadow:0 -10px 30px -20px rgba(0,0,0,.35)}
  #landing .lp-tabbar a,#landing .lp-tabbar button{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;min-height:52px;border:0;background:none;
    font:inherit;font-size:11px;font-weight:700;color:var(--lp-muted);text-decoration:none;border-radius:14px;cursor:pointer;position:relative}
  #landing .lp-tabbar i{font-size:19px}
  #landing .lp-tabbar .on{color:var(--lp-mag)}
  #landing .lp-tabbar .on:before{content:"";position:absolute;top:-6px;width:28px;height:3px;border-radius:0 0 3px 3px;background:var(--lp-mag)}
  #landing .lp-tabbar .join{color:#fff}
  #landing .lp-tabbar .join span.b{width:46px;height:34px;border-radius:12px;background:var(--lp-mag);display:grid;place-items:center;box-shadow:0 8px 18px -8px rgba(212,0,106,.8)}
  #landing .lp-tabbar .join i{font-size:16px}
  #landing .lp-tabbar .join em{font-style:normal;color:var(--lp-mag)}
}
/* ── Founder card and Academic Board (v189) ── */
#landing .lp-founder{display:grid;grid-template-columns:1fr;background:var(--lp-surface);border:1px solid var(--lp-line);border-radius:28px;box-shadow:var(--lp-shadow);overflow:hidden}
#landing .lp-founder-photo{position:relative;display:flex;align-items:center;justify-content:center;background:linear-gradient(160deg,var(--lp-mag),var(--lp-mag-deep));padding:28px}
#landing .lp-founder-photo img{display:block;width:100%;max-width:400px;aspect-ratio:1/1;object-fit:cover;border-radius:22px;box-shadow:0 18px 40px rgba(0,0,0,.28)}
#landing .lp-founder-photo .tag{position:absolute;left:22px;bottom:22px;background:#fff;color:var(--lp-mag-deep);font-weight:800;font-size:12.5px;padding:6px 12px;border-radius:999px;box-shadow:0 6px 18px rgba(0,0,0,.2)}
#landing .lp-founder-text{padding:30px 24px 32px}
#landing .lp-kicker{font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--lp-mag);margin:0 0 8px}
#landing .lp-founder-name{font-size:clamp(22px,3vw,30px);font-weight:900;letter-spacing:-.02em;line-height:1.2;margin:0}
#landing .lp-founder-role{color:var(--lp-mag);font-weight:800;margin:6px 0 0}
#landing .lp-rule{width:56px;height:5px;border-radius:5px;background:linear-gradient(90deg,var(--lp-lem) 0 50%,var(--lp-mag) 50%);margin:18px 0}
#landing .lp-founder-text p{margin:0 0 12px;color:var(--lp-muted);max-width:64ch}
#landing .lp-founder-text p.lead{font-size:17px;font-weight:500;color:var(--lp-ink)}
#landing .lp-motto{font-weight:800;color:var(--lp-mag-deep)!important;margin-top:16px!important}
#landing .lp-founder-more{margin-top:18px}
@media (min-width:860px){ #landing .lp-founder{grid-template-columns:minmax(0,2fr) minmax(0,3fr)} #landing .lp-founder-text{padding:44px 44px 40px} }

#landing .lp-panel-intro{max-width:760px;margin:0 auto 34px;text-align:center}
#landing .lp-panel-intro p{color:var(--lp-muted);font-size:16.5px;margin:0}
#landing .lp-pgrid{display:grid;gap:22px;grid-template-columns:1fr;max-width:1040px;margin:0 auto}
@media (min-width:760px){ #landing .lp-pgrid{grid-template-columns:repeat(2,minmax(0,1fr))} }
@media (min-width:1100px){ #landing .lp-pgrid{grid-template-columns:repeat(3,minmax(0,1fr));max-width:1140px} }
#landing .lp-pinit{position:absolute;inset:0;display:grid;place-items:center;font-size:64px;font-weight:900;color:#fff;background:linear-gradient(150deg,var(--lp-mag),var(--lp-plum));letter-spacing:-.02em}
#landing .lp-pinit::after{display:none}
#landing .lp-pcard{display:flex;flex-direction:column;background:var(--lp-surface);border:1px solid var(--lp-line);border-radius:24px;overflow:hidden;box-shadow:var(--lp-shadow);transition:transform .3s ease,box-shadow .3s ease}
#landing .lp-pcard:hover{transform:translateY(-4px);box-shadow:0 30px 56px -28px rgba(70,10,45,.55)}
#landing .lp-pphoto{position:relative;aspect-ratio:4/3;background:var(--lp-plum);overflow:hidden}
#landing .lp-pphoto img{width:100%;height:100%;object-fit:cover;object-position:center 20%;transition:transform .6s ease}
#landing .lp-pcard:hover .lp-pphoto img{transform:scale(1.04)}
#landing .lp-pphoto::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(20,6,14,.78) 100%)}
#landing .lp-pname{position:absolute;left:18px;right:18px;bottom:14px;z-index:1;color:#fff;margin:0;font-size:19px;line-height:1.25;font-weight:800;letter-spacing:-.01em}
#landing .lp-pbody{padding:18px 20px 20px;display:flex;flex-direction:column;gap:10px;flex:1}
#landing .lp-prole{font-size:13.5px;font-weight:700;color:var(--lp-mag);margin:0;line-height:1.45}
#landing .lp-psum{font-size:14.5px;color:var(--lp-muted);margin:0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
#landing .lp-prow{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:6px}
#landing .lp-socials{display:flex;gap:8px}
#landing .lp-socials a{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:var(--lp-soft);color:var(--lp-ink);text-decoration:none;transition:background .2s ease,color .2s ease,transform .2s ease}
#landing .lp-socials a:hover{background:var(--lp-lem);color:#fff;transform:translateY(-2px)}
#landing .lp-socials i{font-size:16px}
#landing .lp-pcta{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 16px;border-radius:14px;background:var(--lp-mag);color:#fff;font-weight:800;font-size:14.5px;text-decoration:none;box-shadow:0 12px 24px -14px rgba(212,0,106,.8);transition:background .2s ease,transform .2s ease}
#landing .lp-pcta:hover{background:var(--lp-mag-deep);transform:translateY(-1px)}
@supports (animation-timeline: view()){
  #landing .lp-rv{animation:lpRise linear both;animation-timeline:view();animation-range:entry 0% cover 28%}
}
@keyframes lpRise{from{opacity:0;transform:translateY(26px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){ #landing .lp-rv,#landing .lp-pcard,#landing .lp-pphoto img{animation:none!important;transition:none!important;transform:none!important} }
`;

const LP_COURSES = [
  ['detergent-cleaning-products-production', 'Detergent & Cleaning Products Production', 'Production', 'fa-pump-soap'],
  ['core-catering-basics-and-food-safety', 'Catering Basics & Food Safety', 'Food', 'fa-utensils'],
  ['fashion-basics-clothing-alterations', 'Fashion Basics & Clothing Alterations', 'Fashion', 'fa-shirt'],
  ['core-content-creation-canva-and-phone-tools', 'Content Creation (Canva & Phone Tools)', 'Digital', 'fa-mobile-screen'],
  ['core-natural-skincare-basic-creams-and-oils', 'Natural Skincare (Basic Creams & Oils)', 'Beauty', 'fa-spa'],
  ['core-social-media-marketing-for-business', 'Social Media Marketing for Business', 'Digital', 'fa-bullhorn'],
  ['core-savings-spending-and-budgeting-basics', 'Savings, Spending & Budgeting Basics', 'Money', 'fa-piggy-bank'],
  ['core-confidence-building-and-self-belief', 'Confidence Building and Self-Belief', 'Personal growth', 'fa-heart'],
];
function lpCourseCard(c) {
  return '<li><button class="lp-course" type="button" onclick="openAuth(\'signup\')" aria-label="' + c[1] + '. Create a free account to start this course">' +
    '<picture><source srcset="assets/landing/courses/' + c[0] + '.webp" type="image/webp"><img src="assets/landing/courses/' + c[0] + '.jpg" alt="" width="560" height="315" loading="lazy" decoding="async"></picture>' +
    '<span class="body"><b>' + c[1] + '</b><span class="meta"><span class="free"><i class="fa-solid fa-gift" aria-hidden="true"></i> Free</span><span><i class="fa-solid ' + c[3] + '" aria-hidden="true"></i> ' + c[2] + '</span></span></span>' +
    '</button></li>';
}

function renderLanding() {
  const app = document.getElementById('app');
  if (app.querySelector('#landing')) {
    const themeIcon = document.getElementById('theme-icon-landing');
    if (themeIcon) themeIcon.className = 'fa-solid ' + (AppState.theme === 'dark' ? 'fa-sun' : 'fa-moon');
    return;
  }
  const old = document.getElementById('lp-css'); if (old) old.remove();
  const st = document.createElement('style'); st.id = 'lp-css'; st.textContent = LP_CSS; document.head.appendChild(st);
  const s = LP_STATS_FALLBACK;
  const cc = lpCourseCounts();
  const check = f => `<li><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>${f}</span></li>`;
  app.innerHTML = `
  <div id="landing">

    <nav class="lp-nav" aria-label="Main">
      <div class="lp-wrap">
        <a class="lp-brand" href="#top" aria-label="WODDI Institute home" data-i18n-skip>
          <img src="assets/woddi-logo.jpeg" alt="" width="78" height="78">
          <b>WODDI <span>Institute</span></b>
        </a>
        <div class="lp-links">
          <a href="#networks">Networks</a>
          <a href="#learn">Learning</a>
          <a href="#how">How it works</a>
          <a href="/marketplace">Marketplace</a>
        </div>
        <div class="lp-actions">
          <button class="lp-btn ghost sm" type="button" onclick="openAuth('signin')"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in</button>
          <button class="lp-btn primary sm" type="button" id="nav-get-started-sm" onclick="openAuth('signup')"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Get started</button>
          <button class="lp-icon" id="theme-toggle-landing" type="button" onclick="toggleTheme(); updateThemeIcon();" aria-label="Switch light or dark theme">
            <i class="fa-solid ${AppState.theme === 'dark' ? 'fa-sun' : 'fa-moon'}" id="theme-icon-landing" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    </nav>

    <header class="lp-hero" id="top">
      <div class="lp-wrap">
        <div>
          <span class="lp-pill"><i class="fa-solid fa-earth-africa" aria-hidden="true"></i> Empowering women. Transforming Africa.</span>
          <h1 class="lp-h1">Become the woman you were designed to be.</h1>
          <p class="lp-tagline">Learn practical skills. Discover your potential. Build your future.</p>
          <p class="lp-lede">Access practical courses, supportive mentors, career and entrepreneurship guidance, and opportunities to turn your skills into income. Learn at your own pace, in your preferred language, and earn certificates that showcase what you have achieved.</p>
          <div class="lp-ctas">
            <button class="lp-btn primary" type="button" onclick="openAuth('signup')"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Get started</button>
            <button class="lp-btn glass" type="button" onclick="openAuth('signin')"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in</button>
          </div>
          <div class="lp-trust">
            <a href="https://play.google.com/store/apps/details?id=org.thewoddi.institute" target="_blank" rel="noopener"><i class="fa-brands fa-google-play" aria-hidden="true"></i> Get the app on Google Play</a>
            <span><i class="fa-solid fa-wifi" aria-hidden="true"></i> Works offline</span>
            <span><i class="fa-solid fa-language" aria-hidden="true"></i> ${lpLangCount()} languages</span>
          </div>
        </div>

        <div class="lp-stories" role="group" aria-label="Choose your network">
          <button class="lp-story nnn" type="button" onclick="openAuth('signup','nnn')" aria-label="Join the WODDI Nurture NextGen Network (WNNN), for young women aged 18 to 29">
            <span class="head">${lpLogo('nnn', 96)}<span class="ht"><span class="age"><i class="fa-solid fa-seedling" aria-hidden="true"></i> Ages 18 to 29</span><b data-i18n-skip>WODDI Nurture NextGen Network (WNNN)</b></span></span>
            <span class="frame">${lpPicture('learner-phone', 'A young woman in her twenties smiling as she watches a WODDI lesson on her phone', 864, 1080, true)}<span class="lp-bars" aria-hidden="true"><i></i><i></i><i></i></span></span>
          </button>
          <button class="lp-story gmn" type="button" onclick="openAuth('signup','gmn')" aria-label="Join the WODDI Good Mother Network (WGMN), for women aged 30 and above">
            <span class="head">${lpLogo('gmn', 96)}<span class="ht"><span class="age"><i class="fa-solid fa-heart" aria-hidden="true"></i> Ages 30 and above</span><b data-i18n-skip>WODDI Good Mother Network (WGMN)</b></span></span>
            <span class="frame">${lpPicture('learner-notes', 'A woman in a green head wrap taking notes during a WODDI lesson on her phone', 864, 1080, true)}<span class="lp-bars" aria-hidden="true"><i></i><i></i><i></i></span></span>
          </button>
          <p class="lp-tapnote"><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>Tap on your preferred network</p>
        </div>
      </div>
    </header>

    <section class="lp-stats" aria-label="WODDI Institute in numbers">
      <div class="lp-wrap">
        <div class="lp-stat"><i class="fa-solid fa-book-open" aria-hidden="true"></i><b data-lp-stat="courses" data-target="${cc.total}">0</b><span>Courses</span><small class="lp-sub"><em data-i18n-skip>WGMN ${cc.gmn}</em><em data-i18n-skip>WNNN ${cc.nnn}</em></small></div>
        <div class="lp-stat"><i class="fa-solid fa-language" aria-hidden="true"></i><b data-lp-stat="languages" data-target="${lpLangCount()}">0</b><span>Languages</span><small class="lp-sub"><em>5 main</em><em>${lpLangCount() - 5} more African</em></small></div>
        <div class="lp-stat"><i class="fa-solid fa-people-group" aria-hidden="true"></i><b data-lp-stat="learners" data-target="${s.learners}">0</b><span>Women learning</span></div>
        <div class="lp-stat"><i class="fa-solid fa-award" aria-hidden="true"></i><b data-lp-stat="certified" data-target="${s.certified}">0</b><span>Women certified</span></div>
        <div class="lp-stat"><i class="fa-solid fa-earth-africa" aria-hidden="true"></i><b data-lp-stat="africa" data-target="55">0</b><span>African countries</span></div>
      </div>
    </section>

    <section class="lp-sec" id="networks">
      <div class="lp-wrap">
        <div class="lp-head">
          <h2 class="lp-h2">Two networks. One purpose. A place for you.</h2>
          <p>Grow through learning at WODDI Institute and connect with a community for your stage of life. Join WODDI Nurture NextGen Network (WNNN) for young women aged 18 to 29, or WODDI Good Mother Network (WGMN) for women aged 30 and above. No woman is left out.</p>
        </div>
        <div class="lp-nets" id="pathways">
          <article class="lp-net gmn">
            <div class="lp-net-top">${lpLogo('gmn', 96)}<div><h3 data-i18n-skip>WODDI Good Mother Network (WGMN)</h3><span class="lp-age">Women aged 30 and above</span></div></div>
            <p class="tag">Nurturing women. Shaping generations.</p>
            <ul>${['Identity, purpose and inner clarity', 'Healthy family structure and relationships', 'Leadership training you put into practice', 'One-to-one mentorship', 'Practical skills and certificates', 'A community of women who lift each other'].map(check).join('')}</ul>
            <button class="lp-btn primary" type="button" onclick="openAuth('signup','gmn')">Join WODDI Good Mother Network (WGMN)</button>
          </article>
          <article class="lp-net nnn">
            <div class="lp-net-top">${lpLogo('nnn', 96)}<div><h3 data-i18n-skip>WODDI Nurture NextGen Network (WNNN)</h3><span class="lp-age">Young women aged 18 to 29</span></div></div>
            <p class="tag">Becoming more. Becoming ready.</p>
            <ul>${['Discover your purpose and strengths', 'Leadership foundations for life and work', 'Skills for the jobs of the future', 'Mentors who guide your next steps', 'Career clarity and early positioning', 'Peers who keep you accountable'].map(check).join('')}</ul>
            <button class="lp-btn primary" type="button" onclick="openAuth('signup','nnn')">Join WODDI Nurture NextGen Network (WNNN)</button>
          </article>
        </div>
      </div>
    </section>

    <section class="lp-sec" id="learn" style="padding-top:0">
      <div class="lp-wrap lp-split">
        <div class="lp-phone-wrap">
          <div class="lp-phone">${lpPicture('app-home', 'The WODDI Institute app home screen with a day streak, a tip of the day and the next course', 432, 936, false)}</div>
          <div class="lp-float f1"><span class="dot"><i class="fa-solid fa-circle-play" aria-hidden="true"></i></span><span><b>Lesson in progress</b>Short video lessons</span></div>
          <div class="lp-float f2"><span class="dot"><i class="fa-solid fa-award" aria-hidden="true"></i></span><span><b>Certificate earned</b>Verified with a QR code</span></div>
        </div>
        <div>
          <h2 class="lp-h2" id="about">Learn on your device, at your own pace</h2>
          <p class="lp-copy">WODDI is raising a generation of women who know who they are, build strong families and lead in their communities. Every course is built for real life: short videos, practical activities and quizzes you can finish between your other responsibilities.</p>
          <div class="lp-feats">
            <div class="lp-feat"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><b>Made for your device</b><span>Short video lessons that work on any smartphone, with an app that keeps working offline.</span></div>
            <div class="lp-feat"><i class="fa-solid fa-language" aria-hidden="true"></i><b>Your language</b><span>Lessons in English, French, Portuguese, Arabic, Swahili, Hausa, Yoruba and Igbo.</span></div>
            <div class="lp-feat"><i class="fa-solid fa-hands-holding-circle" aria-hidden="true"></i><b>Hands-on skills</b><span>Practical courses you can turn into income, from production to digital work.</span></div>
            <div class="lp-feat"><i class="fa-solid fa-qrcode" aria-hidden="true"></i><b>Certificates that count</b><span>Each certificate has a unique ID and a QR code anyone can check online.</span></div>
          </div>
        </div>
      </div>
    </section>

    <section class="lp-sec" id="landing-course-preview" style="padding-top:0">
      <div class="lp-wrap">
        <div class="lp-head">
          <h2 class="lp-h2">Skills that change your story</h2>
          <p>A few of the practical courses waiting for you. Every course is free and ends with a certificate you can share.</p>
        </div>
        <ul class="lp-courses">${LP_COURSES.map(lpCourseCard).join('')}</ul>
        <div class="lp-courses-foot"><button class="lp-btn primary" type="button" onclick="openAuth('signup')"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create a free account to see every course</button></div>
      </div>
    </section>

    <section class="lp-sec lp-journey" id="how">
      <div class="lp-wrap">
        <div class="lp-head">
          <h2 class="lp-h2">Rooted in purpose. Equipped for impact.</h2>
          <p>Four connected stages to discover who you are, shape your future, build practical skills and earn your certificate, with a community to grow alongside you.</p>
        </div>
        <ol class="lp-steps">
          ${[
            { icon: 'fa-seedling', title: 'Rooting', desc: 'Settle your identity, values and foundation. Everything else grows from here.' },
            { icon: 'fa-compass-drafting', title: 'Blueprint', desc: 'Design your life, family and leadership plan, with a clear picture of where you are going.' },
            { icon: 'fa-screwdriver-wrench', title: 'Skills Hub', desc: 'Choose practical and digital skills courses and learn them step by step.' },
            { icon: 'fa-award', title: 'Certificate and community', desc: 'Earn a verifiable certificate, join the alumni community and sell your skills on WODDI Marketplace.' },
          ].map((c, i) => `<li class="lp-step"><span class="n" aria-hidden="true"><i class="fa-solid ${c.icon}"></i></span><small>${i + 1} / 4</small><h3>${c.title}</h3><p>${c.desc}</p></li>`).join('')}
        </ol>
      </div>
    </section>

    <section class="lp-sec" id="founder" aria-labelledby="founder-name">
      <div class="lp-wrap">
        <div class="lp-founder lp-rv">
          <div class="lp-founder-photo">
            <img src="/img/founder/founder.jpg" alt="Portrait of H.E. Zinariya Nneoma Nkechi Rochas Okorocha" width="900" height="900" loading="lazy" decoding="async">
            <span class="tag">Founder</span>
          </div>
          <div class="lp-founder-text">
            <div class="lp-kicker">Our Founder</div>
            <h2 class="lp-founder-name" id="founder-name">H.E. Zinariya Nneoma Nkechi Rochas Okorocha</h2>
            <p class="lp-founder-role">Founder, Women of Divine Destiny Initiative (WODDI)</p>
            <div class="lp-rule" aria-hidden="true"></div>
            <p class="lead">Driven by compassion and the belief that every life deserves dignity and opportunity, H.E. Zinariya Nneoma Nkechi Rochas Okorocha founded WODDI to nurture, empower and transform the lives of women, youth, adolescents and vulnerable individuals across Africa.</p>
            <p>Through education, mentorship, skills acquisition, leadership development and humanitarian support, WODDI strengthens individuals, families and communities.</p>
            <p>Her vision finds expression through the WODDI Good Mother Network (WGMN) for women aged 30 and above, and the WODDI Nurture NextGen Network (WNNN) for young women aged 18–29, alongside broader humanitarian initiatives.</p>
            <p>The WODDI Institute advances this vision through free, accessible online learning, equipping women with the knowledge, values and skills to transform their lives, strengthen families and positively influence their communities.</p>
            <p class="lp-motto">Nurturing Lives. Empowering Generations. Transforming Africa.</p>
            <a class="lp-btn primary lp-founder-more" href="/founder">Read More About Our Founder</a>
          </div>
        </div>
      </div>
    </section>

    <section class="lp-sec" id="panel" aria-labelledby="panel-title">
      <div class="lp-wrap">
        <div class="lp-head lp-panel-intro lp-rv">
          <h2 class="lp-h2" id="panel-title">WODDI Institute Curriculums is backed by a team of professional academics.</h2>
          <p>Our Academic Board is made up of academics, researchers and professional leaders from universities, polytechnics, research institutes and professional bodies. They review our course content for accuracy, depth and relevance to the women who learn with us.</p>
        </div>
        <div class="lp-pgrid">
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/nnamdi-nwaodu.jpg" alt="Portrait of Prof. Nnamdi Okechukwu Nwaodu" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Prof. Nnamdi Okechukwu Nwaodu</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Visiting Professor of International Relations, Veritas University, Abuja</p>
              <p class="lp-psum">Director General of the Centre for African American Research Studies (CAARS) and President of the Good Health for All Society. Convener of more than 30 continental and international conferences.</p>
              <div class="lp-prow">
                <div class="lp-socials">
                  <a href="https://ng.linkedin.com/in/nnamdi-nwaodu" target="_blank" rel="noopener" aria-label="Prof. Nnamdi Okechukwu Nwaodu on LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                </div>
                <a class="lp-pcta" href="/panelists/nnamdi-nwaodu.html">Read profile and CV <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/ikechukwu-nwosu.jpg" alt="Portrait of Prof. Ikechukwu Jude Donald Nwosu" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Prof. Ikechukwu Jude Donald Nwosu</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Professor of Political Science and International Relations, Imo State University, Owerri</p>
              <p class="lp-psum">Dean of the Faculty of Social Sciences at Imo State University, with over 18 years of university teaching and more than 40 scholarly works.</p>
              <div class="lp-prow">
                <div class="lp-socials">
                  <a href="https://www.facebook.com/nwosu.ike.7/" target="_blank" rel="noopener" aria-label="Prof. Ikechukwu Jude Donald Nwosu on Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                  <a href="https://ng.linkedin.com/in/ikechukwu-nwosu-4a7096155" target="_blank" rel="noopener" aria-label="Prof. Ikechukwu Jude Donald Nwosu on LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                </div>
                <a class="lp-pcta" href="/panelists/ikechukwu-nwosu.html">Read profile and CV <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/rabiat-bello.jpg" alt="Portrait of Dr. Rabiat Muhtar Bello" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Dr. Rabiat Muhtar Bello</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Deputy Director, Programs and Head of Abuja Liaison Office, National Board for Technical Education (NBTE)</p>
              <p class="lp-psum">Academic, researcher and development practitioner in public administration, security studies and governance, and a strong advocate for women’s education and gender-inclusive policy.</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/rabiat-bello.html">Read profile <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/shamsuddeen-ahmad.jpg" alt="Portrait of Dr. Shamsuddeen Musa Ahmad" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Dr. Shamsuddeen Musa Ahmad</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Principal Lecturer, Computer Science, Kaduna Polytechnic</p>
              <p class="lp-psum">Lecturer and researcher in computer science and information technology, with work in artificial intelligence, cybersecurity and educational data analytics.</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/shamsuddeen-ahmad.html">Read profile and CV <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/talatu-umar.jpg" alt="Portrait of Dr. Talatu Raiya Umar" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Dr. Talatu Raiya Umar</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Chief Lecturer, Department of Management Studies, Kaduna Polytechnic</p>
              <p class="lp-psum">Management scholar and expert in organisational behaviour, human resource management and leadership, who has supervised undergraduate, MBA and PhD research.</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/talatu-umar.html">Read profile and CV <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/sani-dangana.jpg" alt="Portrait of Dr. Sani Alhaji Dangana" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Dr. Sani Alhaji Dangana</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Chief Lecturer and Head of Section, Automobile Technology, Kaduna Polytechnic</p>
              <p class="lp-psum">Distinguished teacher and researcher in vocational technical education, with long service as an external examiner and a respected voice on sound examination standards.</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/sani-dangana.html">Read profile <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/ahmed-balarabe-said.jpg" alt="Portrait of Ahmed Balarabe Sa’id" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Ahmed Balarabe Sa’id</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Communications strategist and lecturer; Faculty Head, Kaduna Media Academy</p>
              <p class="lp-psum">Over 17 years of combined industry and academic experience in communications, media and public relations, and author of Aren’t We All PR People?</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/ahmed-balarabe-said.html">Read profile <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/kelechi-nwaodu.jpg" alt="Portrait of Dr. Kelechi Nnamdi Nwaodu" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Dr. Kelechi Nnamdi Nwaodu</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Lecturer, Division of General Studies, European University of Nigeria, Abuja</p>
              <p class="lp-psum">Educator and researcher with over fifteen years of teaching and administrative experience in English language, literary studies and curriculum development.</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/kelechi-nwaodu.html">Read profile and CV <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
          <article class="lp-pcard lp-rv">
            <div class="lp-pphoto">
              <img src="/assets/panelists/murtala-sakwa.jpg" alt="Portrait of Dr. Murtala Aliyu Sakwa" width="800" height="800" loading="lazy" decoding="async">
              <h3 class="lp-pname">Dr. Murtala Aliyu Sakwa</h3>
            </div>
            <div class="lp-pbody">
              <p class="lp-prole">Director, Inspectorate Department, National Board for Technical Education (NBTE), Kaduna</p>
              <p class="lp-psum">Nurse educator, educational administrator and researcher with over 20 years of experience in nursing education, quality assurance and educational leadership.</p>
              <div class="lp-prow">
                <div class="lp-socials"></div>
                <a class="lp-pcta" href="/panelists/murtala-sakwa.html">Read profile and CV <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="lp-sec" id="marketplace-cta">
      <div class="lp-wrap lp-grad">
        <div class="lp-gcard market">
          <span class="lp-gico"><i class="fa-solid fa-store" aria-hidden="true"></i></span>
          <h2>Hire skilled women. Buy what they make.</h2>
          <p>Products and services from certified WODDI graduates. Send a request, agree directly, then rate their work with stars.</p>
          <div class="lp-chips">
            <button type="button" onclick="openGuestMarketplace('cleaning')"><i class="fa-solid fa-pump-soap" aria-hidden="true"></i> Soap and cleaning</button>
            <button type="button" onclick="openGuestMarketplace('fashion')"><i class="fa-solid fa-shirt" aria-hidden="true"></i> Fashion</button>
            <button type="button" onclick="openGuestMarketplace('food')"><i class="fa-solid fa-utensils" aria-hidden="true"></i> Food</button>
            <button type="button" onclick="openGuestMarketplace('digital')"><i class="fa-solid fa-laptop" aria-hidden="true"></i> Digital skills</button>
            <button type="button" onclick="openGuestMarketplace('coaching')"><i class="fa-solid fa-comments" aria-hidden="true"></i> Coaching</button>
            <button type="button" onclick="openGuestMarketplace('design')"><i class="fa-solid fa-palette" aria-hidden="true"></i> Creative</button>
            <button type="button" onclick="openGuestMarketplace('business')"><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Business</button>
          </div>
          <div class="row">
            <button class="lp-btn light" type="button" onclick="openGuestMarketplace()"><i class="fa-solid fa-store" aria-hidden="true"></i> Browse marketplace</button>
          </div>
        </div>
        <div class="lp-gcard">
          <span class="lp-gico"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></span>
          <h2>Alumni community</h2>
          <p>A private space for women who passed a WODDI course. Share wins, find mentors and collaborators, and hear about jobs, grants and events first.</p>
          <div class="row"><a class="lp-btn ghost" href="/alumni"><i class="fa-solid fa-people-group" aria-hidden="true"></i> Visit the alumni community</a></div>
        </div>
      </div>
    </section>

    <section class="lp-wrap" aria-label="Join WODDI">
      <div class="lp-cta">
        <h2>Your journey starts today</h2>
        <p>Choose your network, create a free account and start your first lesson in a few minutes.</p>
        <div class="row">
          <button class="lp-btn gmnb lp-netbtn" type="button" onclick="openAuth('signup','gmn')">${lpLogo('gmn', 60)}<span><b data-i18n-skip>WODDI Good Mother Network (WGMN)</b><small>Women aged 30 and above</small></span></button>
          <button class="lp-btn nnnb lp-netbtn" type="button" onclick="openAuth('signup','nnn')">${lpLogo('nnn', 60)}<span><b data-i18n-skip>WODDI Nurture NextGen Network (WNNN)</b><small>Young women aged 18 to 29</small></span></button>
        </div>
      </div>
    </section>

    <footer class="lp-foot">
      <div class="lp-wrap">
        <div class="grid">
          <div>
            <a class="lp-brand" href="#top" style="color:#fff"><img src="assets/woddi-logo.jpeg" alt="" width="42" height="42"><b>WODDI <span>Institute</span></b></a>
            <p>Identity, leadership, practical skills and legacy building for women across Africa.</p>
            <div class="nets">${lpLogo('gmn', 48)}${lpLogo('nnn', 48)}</div>
          </div>
          <div><h4>Networks</h4><ul><li><a href="#networks" data-i18n-skip>WODDI Good Mother Network (WGMN)</a></li><li><a href="#networks" data-i18n-skip>WODDI Nurture NextGen Network (WNNN)</a></li><li><a href="membership.html">Membership</a></li><li><a href="volunteer.html">Volunteer</a></li></ul></div>
          <div><h4>Learning</h4><ul><li><a href="#how">How it works</a></li><li><a href="#learn">Courses and languages</a></li><li><a href="verify.html">Verify a certificate</a></li><li><a href="/marketplace">Marketplace</a></li><li><a href="/alumni">Alumni community</a></li></ul></div>
          <div><h4>WODDI</h4><ul><li><a href="https://thewoddi.org" target="_blank" rel="noopener">thewoddi.org</a></li><li><a href="https://play.google.com/store/apps/details?id=org.thewoddi.institute" target="_blank" rel="noopener">Android app</a></li><li><a href="/privacy.html">Privacy policy</a></li></ul></div>
        </div>
        <div class="bottom"><span>© 2026 WODDI Institute. Empowering women, transforming nations.</span><span>Made with care for Africa</span></div>
      </div>
    </footer>

    <nav class="lp-tabbar" aria-label="Quick navigation">
      <a href="#top" data-tab="top" class="on"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Home</span></a>
      <a href="#networks" data-tab="networks"><i class="fa-solid fa-people-roof" aria-hidden="true"></i><span>Networks</span></a>
      <button type="button" class="join" onclick="openAuth('signup')"><span class="b"><i class="fa-solid fa-plus" aria-hidden="true"></i></span><em>Get started</em></button>
      <a href="#learn" data-tab="learn"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><span>Learning</span></a>
      <a href="/marketplace"><i class="fa-solid fa-store" aria-hidden="true"></i><span>Market</span></a>
    </nav>
  </div>`;

  lpEnsureIcons();
  lpCountUp();
  lpLoadStats();
  lpAppFeel();
}

/* app behaviour: top bar tightens on scroll, tab bar follows the section in view, smooth in-page jumps */
function lpAppFeel() {
  var land = document.getElementById('landing'); if (!land) return;
  var nav = land.querySelector('.lp-nav');
  var onScroll = function () { if (nav) nav.classList.toggle('scrolled', window.scrollY > 8); };
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  var tabs = land.querySelectorAll('.lp-tabbar [data-tab]');
  if ('IntersectionObserver' in window && tabs.length) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        var id = e.target.id === 'how' ? 'learn' : e.target.id;
        tabs.forEach(function (t) { t.classList.toggle('on', t.getAttribute('data-tab') === id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    ['top', 'networks', 'learn', 'how'].forEach(function (id) { var el = document.getElementById(id); if (el) io.observe(el); });
  }
  land.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#"]'); if (!a) return;
    var el = document.getElementById(a.getAttribute('href').slice(1)); if (!el) return;
    e.preventDefault();
    var y = el.getBoundingClientRect().top + window.scrollY - (nav ? nav.offsetHeight : 0) + (el.id === 'top' ? -200 : 0);
    window.scrollTo({ top: Math.max(0, y), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  });
}

function lpEnsureIcons() {
  try {
    var has = [].some.call(document.querySelectorAll('link[rel="stylesheet"]'), function (l) { return /font-awesome/.test(l.href) && l.media !== 'print'; });
    if (!has) {
      var l = document.createElement('link'); l.rel = 'stylesheet';
      l.href = '/assets/font-awesome/css/all.min.css';
      document.head.appendChild(l);
    }
  } catch (e) {}
}

/* count each number up from 0 when the strip comes into view (and again if live numbers change it) */
function lpAnimate(el, to) {
  var from = parseInt(String(el.textContent).replace(/[^0-9]/g, ''), 10) || 0, dur = 1400, t0 = null;
  if (from === to) from = 0;
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = Number(to).toLocaleString(); return; }
  function step(ts) {
    if (!t0) t0 = ts;
    var k = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - k, 3);
    el.textContent = Math.round(from + (to - from) * e).toLocaleString();
    if (k < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
function lpCountUp() {
  var els = document.querySelectorAll('#landing [data-lp-stat]');
  var run = function (el) { if (el.__lpDone) return; el.__lpDone = true; lpAnimate(el, Number(el.getAttribute('data-target')) || 0); };
  if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
  var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } }); }, { threshold: 0.4 });
  els.forEach(function (el) { io.observe(el); });
}

function lpLoadStats() {
  try {
    var tries = 0;
    var iv = setInterval(function () {
      var c = window._sb;
      if (!c && ++tries < 40) return;
      clearInterval(iv);
      if (!c || !c.rpc) return;
      c.rpc('public_platform_stats').then(function (r) {
        if (!r || r.error || !r.data) return;
        ['learners', 'certified'].forEach(function (k) {
          var el = document.querySelector('[data-lp-stat="' + k + '"]');
          if (!el || r.data[k] == null) return;
          var v = Number(r.data[k]);
          el.setAttribute('data-target', v);
          if (el.__lpDone) lpAnimate(el, v);
        });
      });
    }, 250);
  } catch (e) {}
}

function updateThemeIcon() {
  const icon = document.getElementById('theme-icon-landing');
  if (icon) icon.className = `fa-solid ${AppState.theme === 'dark' ? 'fa-sun' : 'fa-moon'}`;
}
