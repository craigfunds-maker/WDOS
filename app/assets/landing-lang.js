/* WODDI landing language ribbon (v177).
   Replaces the old language dropdown on the home page with a visible ribbon of all eight languages,
   each shown in its own script with a greeting, and translates the whole landing page (the new v176
   copy was not in the old dictionary). Uses the site's own switchLanguage(), so the choice is
   remembered and carries into the app after sign-in. */
(function () {
  'use strict';
  var LANGS = [
    ['en', 'English', 'Hello', 'EN'], ['fr', 'Français', 'Bonjour', 'FR'], ['pt', 'Português', 'Olá', 'PT'], ['ar', 'العربية', 'مرحبا', 'ع'],
    ['sw', 'Kiswahili', 'Habari', 'SW'], ['ha', 'Hausa', 'Sannu', 'HA'], ['yo', 'Yorùbá', 'Ẹ n lẹ', 'YO'], ['ig', 'Igbo', 'Ndewo', 'IG']
  ];
  /* v179: more African languages. The page is translated by AI the first time anyone picks one, then served from
     the shared translation store (site_i18n) to everyone after that. */
  var MORE = [
    ['am', 'አማርኛ', 'Amharic'], ['om', 'Afaan Oromoo', 'Oromo'], ['so', 'Soomaali', 'Somali'], ['ti', 'ትግርኛ', 'Tigrinya'],
    ['zu', 'isiZulu', 'Zulu'], ['xh', 'isiXhosa', 'Xhosa'], ['af', 'Afrikaans', 'Afrikaans'], ['st', 'Sesotho', 'Sesotho'], ['tn', 'Setswana', 'Setswana'],
    ['nso', 'Sepedi', 'Northern Sotho'], ['ts', 'Xitsonga', 'Tsonga'], ['ss', 'siSwati', 'Swati'], ['ve', 'Tshivenḓa', 'Venda'],
    ['sn', 'chiShona', 'Shona'], ['nd', 'isiNdebele', 'Ndebele'], ['ny', 'Chichewa', 'Chichewa'], ['bem', 'Icibemba', 'Bemba'],
    ['rw', 'Ikinyarwanda', 'Kinyarwanda'], ['rn', 'Ikirundi', 'Kirundi'], ['lg', 'Luganda', 'Luganda'], ['ki', 'Gĩkũyũ', 'Kikuyu'], ['luo', 'Dholuo', 'Luo'],
    ['ln', 'Lingála', 'Lingala'], ['kg', 'Kikongo', 'Kikongo'], ['lua', 'Tshiluba', 'Luba'],
    ['wo', 'Wolof', 'Wolof'], ['ff', 'Pulaar', 'Fula'], ['bm', 'Bamanankan', 'Bambara'], ['mos', 'Mooré', 'Mossi'],
    ['ak', 'Twi', 'Akan Twi'], ['ee', 'Eʋegbe', 'Ewe'], ['gaa', 'Gã', 'Ga'], ['fon', 'Fɔngbe', 'Fon'], ['kri', 'Krio', 'Krio'],
    ['kr', 'Kanuri', 'Kanuri'], ['tiv', 'Tiv', 'Tiv'], ['efi', 'Efik', 'Efik'], ['bin', 'Ẹ̀dó', 'Edo'], ['pcm', 'Naijá', 'Nigerian Pidgin'],
    ['mg', 'Malagasy', 'Malagasy'], ['zgh', 'Tamaziɣt', 'Tamazight'], ['kab', 'Taqbaylit', 'Kabyle']
  ];
  var MORE_MAP = {}; MORE.forEach(function (x) { MORE_MAP[x[0]] = x; });
  /* v184: five languages on the bar; Hausa, Yoruba and Igbo (fully translated) join the other African languages
     in the "More African Languages" dropdown, listed A to Z by English name */
  var MAIN = ['en', 'fr', 'pt', 'ar', 'sw'];
  var IN_DD = { ha: 'Hausa', yo: 'Yoruba', ig: 'Igbo' };
  function ddList() {
    var a = LANGS.filter(function (x) { return IN_DD[x[0]]; }).map(function (x) { return { code: x[0], nat: x[1], en: IN_DD[x[0]], full: true }; })
      .concat(MORE.map(function (m) { return { code: m[0], nat: m[1], en: m[2], full: false }; }));
    return a.sort(function (p, q) { return p.en.localeCompare(q.en, 'en'); });
  }
  window.WODDI_LP_LANG_COUNT = MAIN.length + Object.keys(IN_DD).length + MORE.length;
  function extra() { try { var x = localStorage.getItem('woddi_lang_extra'); return x && MORE_MAP[x] ? x : null; } catch (e) { return null; } }
  var XD = {}, XL = null, inflight = {}, need = {}, xTimer = null, xDone = 0, xTotal = 0;
  function xLoad(code) {
    if (XL === code) return; XL = code; XD = {}; inflight = {}; need = {};
    try { XD = JSON.parse(localStorage.getItem('woddi_i18n_x_' + code) || '{}') || {}; } catch (e) { XD = {}; }
    /* everything already translated for this language by earlier visitors */
    var tries = 0, iv = setInterval(function () {
      var c = window._sb; if (!c && ++tries < 30) return; clearInterval(iv); if (!c || !c.from) return;
      c.from('site_i18n').select('src,text').eq('lang', code).limit(3000).then(function (r) {
        if (XL !== code || !r || !r.data) return; var n = 0;
        r.data.forEach(function (row) { if (!XD[row.src]) { XD[row.src] = row.text; n++; } });
        if (n) { xSave(); var L = document.getElementById('landing'); translate(L); }
      });
    }, 200);
  }
  function xSave() { try { localStorage.setItem('woddi_i18n_x_' + XL, JSON.stringify(XD)); } catch (e) {} }
  function xRequest() {
    clearTimeout(xTimer);
    xTimer = setTimeout(function () {
      var code = XL; if (!code) return;
      var list = Object.keys(need).filter(function (k) { return !XD[k] && !inflight[k]; }); need = {};
      if (!list.length) return;
      var batches = []; for (var i = 0; i < list.length; i += 35) batches.push(list.slice(i, i + 35));
      xTotal += list.length; xProgress();
      var run = function () {
        var b = batches.shift(); if (!b) return;
        b.forEach(function (k) { inflight[k] = 1; });
        fetch('/.netlify/functions/translate', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ texts: b, target: code, scope: 'landing' }) })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) {
            if (XL !== code) return;
            if (j && j.translations) { b.forEach(function (k, n) { if (j.translations[n]) XD[k] = j.translations[n]; }); xSave(); }
            b.forEach(function (k) { delete inflight[k]; }); xDone += b.length; xProgress(!j);
            translate(document.getElementById('landing')); run();
          }).catch(function () { b.forEach(function (k) { delete inflight[k]; }); xDone += b.length; xProgress(true); run(); });
      };
      run(); run(); run();
    }, 150);
  }
  function xProgress(failed) {
    var el = document.getElementById('lp-xbar'); if (!el) return;
    var pct = xTotal ? Math.min(100, Math.round(xDone / xTotal * 100)) : 100;
    el.style.width = pct + '%'; el.parentNode.style.opacity = pct >= 100 ? '0' : '1';
    if (failed) { var w = document.getElementById('lp-xwarn'); if (w) w.hidden = false; }
  }

  var COLS = ['fr', 'pt', 'ar', 'yo', 'ha', 'ig', 'sw'];
  /* [English, French, Portuguese, Arabic, Yoruba, Hausa, Igbo, Swahili] */
  var ROWS = [
    ['Networks', 'Réseaux', 'Redes', 'الشبكات', 'Àwọn Nẹtiwọọki', 'Cibiyoyi', 'Netwọk', 'Mitandao'],
    ['Learning', 'Apprendre', 'Aprender', 'التعلّم', 'Ẹ̀kọ́', 'Koyo', 'Mmụta', 'Kujifunza'],
    ['How it works', 'Comment ça marche', 'Como funciona', 'كيف يعمل', 'Bí ó ṣe ń ṣiṣẹ́', 'Yadda yake aiki', 'Otú o si arụ ọrụ', 'Jinsi inavyofanya kazi'],
    ['Marketplace', 'Marché', 'Mercado', 'السوق', 'Ọjà', 'Kasuwa', 'Ahịa', 'Soko'],
    ['Sign in', 'Se connecter', 'Entrar', 'تسجيل الدخول', 'Wọlé', 'Shiga', 'Banye', 'Ingia'],
    ['Get started', 'Commencer', 'Começar', 'ابدئي الآن', 'Bẹ̀rẹ̀', 'Fara', 'Malite', 'Anza sasa'],
    ['Free learning for women across Africa', 'Formation gratuite pour les femmes de toute l\'Afrique', 'Aprendizagem gratuita para mulheres de toda a África', 'تعلّم مجاني للنساء في جميع أنحاء أفريقيا', 'Ẹ̀kọ́ ọ̀fẹ́ fún àwọn obìnrin jákèjádò Áfíríkà', 'Koyo kyauta ga mata a fadin Afirka', 'Mmụta n\'efu maka ụmụ nwanyị n\'Afrịka niile', 'Mafunzo bure kwa wanawake kote Afrika'],
    ['Become the woman you were', 'Devenez la femme que vous êtes', 'Torne-se a mulher que você foi', 'كوني المرأة التي', 'Di obìnrin tí a', 'Zama macen da aka', 'Bụrụ nwanyị ahụ e', 'Kuwa mwanamke uliyeumbwa'],
    ['designed to be.', 'appelée à devenir.', 'criada para ser.', 'خُلقتِ لتكونيها.', 'dá ọ láti jẹ́.', 'halitta ki zama.', 'kere gị ka ị bụrụ.', 'kuwa.'],
    ['Short lessons on your phone, mentors who care, practical skills you can earn from, and a certificate anyone can verify. Learn at your own pace, in your own language.',
      'Des leçons courtes sur votre téléphone, des mentors attentionnés, des compétences pratiques qui rapportent et un certificat que tout le monde peut vérifier. Apprenez à votre rythme, dans votre langue.',
      'Aulas curtas no seu telemóvel, mentoras que se importam, competências práticas que geram rendimento e um certificado que qualquer pessoa pode verificar. Aprenda ao seu ritmo, na sua língua.',
      'دروس قصيرة على هاتفك، ومرشدات يهتممن بك، ومهارات عملية تكسبين منها، وشهادة يمكن لأي شخص التحقق منها. تعلّمي بالسرعة التي تناسبك وبلغتك.',
      'Ẹ̀kọ́ kúkúrú lórí fóònù rẹ, àwọn olùtọ́nisọ́nà tí ó bìkítà, ọgbọ́n ọwọ́ tí o lè fi pa owó, àti ìwé-ẹ̀rí tí ẹnikẹ́ni lè ṣàyẹ̀wò. Kọ́ ẹ̀kọ́ ní ìwọ̀n tìrẹ, ní èdè rẹ.',
      'Gajerun darussa a wayarki, masu jagora masu kulawa, sana\'o\'i da za ki samu kudi da su, da takardar shaida da kowa zai iya tantancewa. Ki koya a hankali, da harshenki.',
      'Nkuzi dị mkpirikpi na ekwentị gị, ndị ndụmọdụ na-elekọta gị, nka ị ga-eji kpata ego, na asambodo onye ọ bụla nwere ike ịlele. Mụta n\'ọsọ nke gị, n\'asụsụ gị.',
      'Masomo mafupi kwenye simu yako, washauri wanaojali, ujuzi wa vitendo unaokuingizia kipato, na cheti ambacho mtu yeyote anaweza kuthibitisha. Jifunze kwa kasi yako, kwa lugha yako.'],
    ['Women aged', 'Femmes de', 'Mulheres com', 'نساء بعمر', 'Obìnrin ọmọ ọdún', 'Mata masu shekaru', 'Ụmụ nwanyị dị afọ', 'Wanawake wenye umri'],
    ['30 and above', '30 ans et plus', '30 anos ou mais', '30 عامًا فأكثر', '30 sókè', '30 zuwa sama', '30 gaa n\'elu', 'miaka 30 na zaidi'],
    ['Young women aged', 'Jeunes femmes de', 'Jovens mulheres com', 'شابات بعمر', 'Ọ̀dọ́bìnrin ọmọ ọdún', 'Matasan mata masu shekaru', 'Ụmụ agbọghọ dị afọ', 'Wanawake vijana wenye umri'],
    ['18 to 29', '18 à 29 ans', '18 a 29 anos', 'من 18 إلى 29', '18 sí 29', '18 zuwa 29', '18 ruo 29', 'miaka 18 hadi 29'],
    ['Get the app on Google Play', 'Téléchargez l\'application sur Google Play', 'Baixe a aplicação no Google Play', 'حمّلي التطبيق من Google Play', 'Gba ohun èlò náà lórí Google Play', 'Sauke manhajar a Google Play', 'Nweta ngwa ahụ na Google Play', 'Pakua programu kwenye Google Play'],
    ['Works offline', 'Fonctionne hors ligne', 'Funciona offline', 'يعمل دون إنترنت', 'Ó ń ṣiṣẹ́ láìsí íńtánẹ́ẹ̀tì', 'Yana aiki ba tare da intanet ba', 'Na-arụ ọrụ na-enweghị ịntanetị', 'Inafanya kazi bila mtandao'],
    ['8 languages', '8 langues', '8 línguas', '8 لغات', 'Èdè 8', 'Harsuna 8', 'Asụsụ 8', 'Lugha 8'],
    ['Ages 18 to 29', '18 à 29 ans', '18 a 29 anos', 'من 18 إلى 29 عامًا', 'Ọmọ ọdún 18 sí 29', 'Shekaru 18 zuwa 29', 'Afọ 18 ruo 29', 'Miaka 18 hadi 29'],
    ['Young women', 'Jeunes femmes', 'Jovens mulheres', 'شابات', 'Ọ̀dọ́bìnrin', 'Matasan mata', 'Ụmụ agbọghọ', 'Wanawake vijana'],
    ['Ages 30 and above', '30 ans et plus', '30 anos ou mais', '30 عامًا فأكثر', 'Ọmọ ọdún 30 sókè', 'Shekaru 30 zuwa sama', 'Afọ 30 gaa n\'elu', 'Miaka 30 na zaidi'],
    ['Women and mothers', 'Femmes et mères', 'Mulheres e mães', 'نساء وأمهات', 'Obìnrin àti ìyá', 'Mata da iyaye mata', 'Ụmụ nwanyị na ndị nne', 'Wanawake na akina mama'],
    ['Tap the network that fits your age to join', 'Touchez le réseau qui correspond à votre âge pour le rejoindre', 'Toque na rede que corresponde à sua idade para aderir', 'اضغطي على الشبكة التي تناسب عمرك للانضمام', 'Tẹ nẹtiwọọki tó bá ọjọ́-orí rẹ mu láti darapọ̀', 'Danna cibiyar da ta dace da shekarunki don shiga', 'Pịa netwọk kwekọrọ n\'afọ gị ka ịbanye', 'Gusa mtandao unaolingana na umri wako ili ujiunge'],
    ['Courses', 'Cours', 'Cursos', 'دورات', 'Ẹ̀kọ́', 'Kwasa-kwasai', 'Ọmụmụ ihe', 'Kozi'],
    ['Languages', 'Langues', 'Línguas', 'لغات', 'Èdè', 'Harsuna', 'Asụsụ', 'Lugha'],
    ['Women learning', 'Femmes en formation', 'Mulheres a aprender', 'نساء يتعلّمن', 'Obìnrin tó ń kẹ́kọ̀ọ́', 'Mata masu koyo', 'Ụmụ nwanyị na-amụ ihe', 'Wanawake wanaojifunza'],
    ['Countries so far', 'Pays à ce jour', 'Países até agora', 'دولة حتى الآن', 'Orílẹ̀-èdè títí di báyìí', 'Kasashe zuwa yanzu', 'Mba ruo ugbu a', 'Nchi hadi sasa'],
    ['Two networks, one movement', 'Deux réseaux, un seul mouvement', 'Duas redes, um só movimento', 'شبكتان، وحركة واحدة', 'Nẹtiwọọki méjì, ìgbìyànjú kan', 'Cibiyoyi biyu, motsi daya', 'Netwọk abụọ, otu mmegharị', 'Mitandao miwili, vuguvugu moja'],
    ['Choose the network for where you are today', 'Choisissez le réseau qui correspond à votre étape de vie', 'Escolha a rede para a fase em que está hoje', 'اختاري الشبكة التي تناسب مرحلتك اليوم', 'Yan nẹtiwọọki tó bá ibi tí o wà lónìí mu', 'Zabi cibiyar da ta dace da inda kike yau', 'Họrọ netwọk dabara ebe ị nọ taa', 'Chagua mtandao unaofaa hatua uliyopo leo'],
    ['Every woman joins through one of WODDI\'s two networks. Each has its own path, mentors and community, built for her stage of life.',
      'Chaque femme rejoint WODDI par l\'un de ses deux réseaux. Chacun a son parcours, ses mentors et sa communauté, pensés pour son étape de vie.',
      'Cada mulher entra através de uma das duas redes da WODDI. Cada uma tem o seu percurso, mentoras e comunidade, pensados para a sua fase de vida.',
      'تنضم كل امرأة عبر إحدى شبكتي WODDI. ولكل شبكة مسارها ومرشداتها ومجتمعها، المصممة لمرحلتها من الحياة.',
      'Gbogbo obìnrin ló ń darapọ̀ nípasẹ̀ ọ̀kan nínú nẹtiwọọki méjì WODDI. Ọ̀kọ̀ọ̀kan ní ọ̀nà tirẹ̀, àwọn olùtọ́nisọ́nà àti àwùjọ, tí a kọ́ fún ìpele ayé rẹ̀.',
      'Kowace mace tana shiga ta daya daga cikin cibiyoyi biyu na WODDI. Kowacce tana da hanyarta, masu jagora da al\'umma, da aka gina don matakin rayuwarta.',
      'Nwanyị ọ bụla na-abanye site n\'otu n\'ime netwọk abụọ nke WODDI. Nke ọ bụla nwere ụzọ ya, ndị ndụmọdụ na obodo, e wuru maka ọkwa ndụ ya.',
      'Kila mwanamke hujiunga kupitia mojawapo ya mitandao miwili ya WODDI. Kila mmoja una njia yake, washauri na jamii, vilivyoundwa kwa hatua yake ya maisha.'],
    ['Women aged 30 and above', 'Femmes de 30 ans et plus', 'Mulheres com 30 anos ou mais', 'نساء بعمر 30 عامًا فأكثر', 'Obìnrin ọmọ ọdún 30 sókè', 'Mata masu shekaru 30 zuwa sama', 'Ụmụ nwanyị dị afọ 30 gaa n\'elu', 'Wanawake wenye miaka 30 na zaidi'],
    ['Nurturing women. Shaping generations.', 'Accompagner les femmes. Façonner les générations.', 'Cuidar das mulheres. Moldar gerações.', 'نرعى النساء. نصنع الأجيال.', 'A ń tọ́ obìnrin. A ń ṣe ìran.', 'Rainon mata. Gina zuriya.', 'Ịzụ ụmụ nwanyị. Ịkpụzi ọgbọ.', 'Kulea wanawake. Kujenga vizazi.'],
    ['Identity, purpose and inner clarity', 'Identité, raison d\'être et clarté intérieure', 'Identidade, propósito e clareza interior', 'الهوية والهدف والوضوح الداخلي', 'Ìdánimọ̀, èrèdí àti òye inú', 'Asali, manufa da fahimtar kai', 'Njirimara, ebumnuche na nghọta ime', 'Utambulisho, kusudi na uwazi wa ndani'],
    ['Healthy family structure and relationships', 'Une famille et des relations saines', 'Estrutura familiar e relações saudáveis', 'بنية أسرية وعلاقات صحية', 'Ìdílé àti ìbáṣepọ̀ tó dára', 'Tsarin iyali da dangantaka mai kyau', 'Ezinụlọ na mmekọrịta dị mma', 'Familia na mahusiano yenye afya'],
    ['Leadership training you put into practice', 'Une formation au leadership que vous appliquez', 'Formação em liderança que põe em prática', 'تدريب على القيادة تطبّقينه عمليًا', 'Ìdánilẹ́kọ̀ọ́ olórí tí o ń lò', 'Horon jagoranci da kike aiwatarwa', 'Ọzụzụ ndu ị na-etinye n\'ọrụ', 'Mafunzo ya uongozi unayoyatumia'],
    ['One-to-one mentorship', 'Un mentorat individuel', 'Mentoria individual', 'إرشاد فردي', 'Ìtọ́nisọ́nà ẹnìkan-sí-ẹnìkan', 'Jagoranci na mutum da mutum', 'Ndụmọdụ otu na otu', 'Ushauri wa ana kwa ana'],
    ['Practical skills and certificates', 'Compétences pratiques et certificats', 'Competências práticas e certificados', 'مهارات عملية وشهادات', 'Ọgbọ́n ọwọ́ àti ìwé-ẹ̀rí', 'Sana\'o\'i da takardun shaida', 'Nka na asambodo', 'Ujuzi wa vitendo na vyeti'],
    ['A community of women who lift each other', 'Une communauté de femmes qui s\'élèvent mutuellement', 'Uma comunidade de mulheres que se elevam umas às outras', 'مجتمع من النساء يرفعن بعضهن', 'Àwùjọ obìnrin tí ń gbé ara wọn ga', 'Al\'ummar mata masu daga juna', 'Obodo ụmụ nwanyị na-ebuli ibe ha', 'Jamii ya wanawake wanaoinuana'],
    ['Join WODDI Good Mother Network (WGMN)', 'Rejoindre WODDI Good Mother Network (WGMN)', 'Aderir à WODDI Good Mother Network (WGMN)', 'انضمي إلى WODDI Good Mother Network (WGMN)', 'Darapọ̀ mọ́ WODDI Good Mother Network (WGMN)', 'Shiga WODDI Good Mother Network (WGMN)', 'Banye na WODDI Good Mother Network (WGMN)', 'Jiunge na WODDI Good Mother Network (WGMN)'],
    ['Young women aged 18 to 29', 'Jeunes femmes de 18 à 29 ans', 'Jovens mulheres dos 18 aos 29 anos', 'شابات من 18 إلى 29 عامًا', 'Ọ̀dọ́bìnrin ọmọ ọdún 18 sí 29', 'Matasan mata masu shekaru 18 zuwa 29', 'Ụmụ agbọghọ dị afọ 18 ruo 29', 'Wanawake vijana wa miaka 18 hadi 29'],
    ['Becoming more. Becoming ready.', 'Grandir. Se préparer.', 'Crescer. Ficar pronta.', 'نكبر. ونستعدّ.', 'Dàgbà sí i. Múra sílẹ̀.', 'Karuwa. Shiryawa.', 'Na-eto. Na-ejikere.', 'Kukua zaidi. Kujiandaa.'],
    ['Discover your purpose and strengths', 'Découvrez votre raison d\'être et vos forces', 'Descubra o seu propósito e os seus pontos fortes', 'اكتشفي هدفك ونقاط قوتك', 'Ṣàwárí èrèdí àti agbára rẹ', 'Gano manufarki da karfinki', 'Chọpụta ebumnuche gị na ike gị', 'Gundua kusudi na nguvu zako'],
    ['Leadership foundations for life and work', 'Les bases du leadership pour la vie et le travail', 'Bases de liderança para a vida e o trabalho', 'أسس القيادة للحياة والعمل', 'Ìpìlẹ̀ olórí fún ayé àti iṣẹ́', 'Tushen jagoranci don rayuwa da aiki', 'Ntọala ndu maka ndụ na ọrụ', 'Misingi ya uongozi kwa maisha na kazi'],
    ['Skills for the jobs of the future', 'Des compétences pour les métiers de demain', 'Competências para os empregos do futuro', 'مهارات لوظائف المستقبل', 'Ọgbọ́n fún iṣẹ́ ọjọ́ iwájú', 'Sana\'o\'i don ayyukan gaba', 'Nka maka ọrụ ọdịnihu', 'Ujuzi kwa kazi za baadaye'],
    ['Mentors who guide your next steps', 'Des mentors qui guident vos prochaines étapes', 'Mentoras que orientam os seus próximos passos', 'مرشدات يوجّهن خطواتك القادمة', 'Àwọn olùtọ́nisọ́nà tí ń tọ́ ìgbésẹ̀ rẹ', 'Masu jagora da ke shiryar da matakanki', 'Ndị ndụmọdụ na-eduzi nzọụkwụ gị', 'Washauri wanaoongoza hatua zako'],
    ['Career clarity and early positioning', 'Clarté professionnelle et positionnement précoce', 'Clareza de carreira e posicionamento cedo', 'وضوح مهني وتموضع مبكر', 'Òye iṣẹ́ àti ìgbaradì ní kùtùkùtù', 'Fahimtar sana\'a da shiri tun da wuri', 'Nghọta ọrụ na nkwadebe n\'oge', 'Uwazi wa kazi na kujipanga mapema'],
    ['Peers who keep you accountable', 'Des pairs qui vous aident à tenir vos engagements', 'Colegas que a ajudam a manter o compromisso', 'زميلات يساعدنك على الالتزام', 'Àwọn ẹgbẹ́ tí ń jẹ́ kí o dúró ṣinṣin', 'Abokan da ke taimaka miki ki tsaya kan alkawari', 'Ndị ọgbọ na-eme ka ị kwụsi ike', 'Wenzako wanaokusaidia kuwajibika'],
    ['Join WODDI Nurture NextGen Network (WNNN)', 'Rejoindre WODDI Nurture NextGen Network (WNNN)', 'Aderir à WODDI Nurture NextGen Network (WNNN)', 'انضمي إلى WODDI Nurture NextGen Network (WNNN)', 'Darapọ̀ mọ́ WODDI Nurture NextGen Network (WNNN)', 'Shiga WODDI Nurture NextGen Network (WNNN)', 'Banye na WODDI Nurture NextGen Network (WNNN)', 'Jiunge na WODDI Nurture NextGen Network (WNNN)'],
    ['Lesson in progress', 'Leçon en cours', 'Aula em curso', 'درس قيد التقدّم', 'Ẹ̀kọ́ ń lọ lọ́wọ́', 'Darasi yana gudana', 'Nkuzi na-aga n\'ihu', 'Somo linaendelea'],
    ['Short video lessons', 'Courtes leçons vidéo', 'Aulas curtas em vídeo', 'دروس فيديو قصيرة', 'Ẹ̀kọ́ fídíò kúkúrú', 'Gajerun darussan bidiyo', 'Nkuzi vidiyo dị mkpụmkpụ', 'Masomo mafupi ya video'],
    ['Certificate earned', 'Certificat obtenu', 'Certificado obtido', 'تم الحصول على الشهادة', 'Ìwé-ẹ̀rí ti tẹ̀ ẹ́ lọ́wọ́', 'An samu takardar shaida', 'E nwetala asambodo', 'Cheti kimepatikana'],
    ['Verified with a QR code', 'Vérifié par un code QR', 'Verificado com um código QR', 'موثّقة برمز QR', 'A ṣàyẹ̀wò rẹ̀ pẹ̀lú kóòdù QR', 'An tabbatar da lambar QR', 'E jiri koodu QR kwado ya', 'Imethibitishwa kwa msimbo wa QR'],
    ['Learning that fits your life', 'Une formation adaptée à votre vie', 'Aprendizagem que se adapta à sua vida', 'تعلّم يناسب حياتك', 'Ẹ̀kọ́ tó bá ayé rẹ mu', 'Koyo da ya dace da rayuwarki', 'Mmụta dabara na ndụ gị', 'Mafunzo yanayoendana na maisha yako'],
    ['Learn at home, on your phone, at your own pace', 'Apprenez chez vous, sur votre téléphone, à votre rythme', 'Aprenda em casa, no telemóvel, ao seu ritmo', 'تعلّمي في منزلك، على هاتفك، وبالسرعة التي تناسبك', 'Kẹ́kọ̀ọ́ nílé, lórí fóònù rẹ, ní ìwọ̀n tìrẹ', 'Ki koya a gida, a wayarki, a hankalinki', 'Mụta n\'ụlọ, na ekwentị gị, n\'ọsọ nke gị', 'Jifunze nyumbani, kwenye simu yako, kwa kasi yako'],
    ['WODDI is raising a generation of women who know who they are, build strong families and lead in their communities. Every course is built for real life: short videos, practical activities and quizzes you can finish between your other responsibilities.',
      'WODDI forme une génération de femmes qui savent qui elles sont, bâtissent des familles solides et dirigent leurs communautés. Chaque cours est pensé pour la vraie vie : vidéos courtes, activités pratiques et quiz que vous pouvez terminer entre vos autres responsabilités.',
      'A WODDI está a formar uma geração de mulheres que sabem quem são, constroem famílias fortes e lideram nas suas comunidades. Cada curso é feito para a vida real: vídeos curtos, atividades práticas e questionários que pode terminar entre as suas outras responsabilidades.',
      'تُنشئ WODDI جيلًا من النساء يعرفن من هن، ويبنين أسرًا قوية، ويقدن مجتمعاتهن. كل دورة مصمّمة للحياة الواقعية: فيديوهات قصيرة وأنشطة عملية واختبارات يمكنك إنهاؤها بين مسؤولياتك الأخرى.',
      'WODDI ń gbé ìran obìnrin dìde tí wọ́n mọ ẹni tí wọ́n jẹ́, tí wọ́n ń kọ́ ìdílé tó lágbára, tí wọ́n sì ń darí àwùjọ wọn. Gbogbo ẹ̀kọ́ ni a kọ́ fún ayé gidi: fídíò kúkúrú, iṣẹ́ ọwọ́ àti ìdánwò tí o lè parí láàárín àwọn iṣẹ́ rẹ míì.',
      'WODDI tana renon tsarar mata da suka san ko su wanene, suke gina iyalai masu karfi, kuma suke jagoranci a al\'ummominsu. Kowane kwas an gina shi don rayuwa ta gaske: gajerun bidiyo, ayyukan aiki da tambayoyi da za ki iya kammalawa tsakanin sauran ayyukanki.',
      'WODDI na-azụlite ọgbọ ụmụ nwanyị maara onye ha bụ, na-ewu ezinụlọ siri ike ma na-edu obodo ha. E wuru ọmụmụ ihe ọ bụla maka ezi ndụ: vidiyo dị mkpụmkpụ, ọrụ aka na ajụjụ ị ga-emecha n\'etiti ọrụ ndị ọzọ gị.',
      'WODDI inalea kizazi cha wanawake wanaojua wao ni nani, wanaojenga familia imara na kuongoza katika jamii zao. Kila kozi imeundwa kwa maisha halisi: video fupi, kazi za vitendo na maswali unayoweza kumaliza katikati ya majukumu yako mengine.'],
    ['Made for your phone', 'Conçu pour votre téléphone', 'Feito para o seu telemóvel', 'مصمّم لهاتفك', 'A ṣe é fún fóònù rẹ', 'An yi shi don wayarki', 'Emere ya maka ekwentị gị', 'Imetengenezwa kwa simu yako'],
    ['Short video lessons that work on any smartphone, with an app that keeps working offline.', 'De courtes leçons vidéo qui fonctionnent sur tout smartphone, avec une application qui marche hors ligne.', 'Aulas curtas em vídeo que funcionam em qualquer smartphone, com uma aplicação que funciona offline.', 'دروس فيديو قصيرة تعمل على أي هاتف ذكي، مع تطبيق يواصل العمل دون إنترنت.', 'Ẹ̀kọ́ fídíò kúkúrú tó ń ṣiṣẹ́ lórí fóònù èyíkéyìí, pẹ̀lú ohun èlò tó ń ṣiṣẹ́ láìsí íńtánẹ́ẹ̀tì.', 'Gajerun darussan bidiyo da ke aiki a kowace waya, da manhajar da ke aiki ba tare da intanet ba.', 'Nkuzi vidiyo dị mkpụmkpụ na-arụ ọrụ na ekwentị ọ bụla, ya na ngwa na-arụ ọrụ na-enweghị ịntanetị.', 'Masomo mafupi ya video yanayofanya kazi kwenye simu janja yoyote, na programu inayoendelea kufanya kazi bila mtandao.'],
    ['Your language', 'Votre langue', 'A sua língua', 'لغتك', 'Èdè rẹ', 'Harshenki', 'Asụsụ gị', 'Lugha yako'],
    ['Lessons in English, French, Portuguese, Arabic, Swahili, Hausa, Yoruba and Igbo.', 'Des leçons en anglais, français, portugais, arabe, swahili, haoussa, yoruba et igbo.', 'Aulas em inglês, francês, português, árabe, suaíli, hauçá, iorubá e igbo.', 'دروس بالإنجليزية والفرنسية والبرتغالية والعربية والسواحيلية والهوسا واليوروبا والإيغبو.', 'Ẹ̀kọ́ ní Gẹ̀ẹ́sì, Faransé, Potogí, Lárúbáwá, Swahili, Hausa, Yorùbá àti Igbo.', 'Darussa da Turanci, Faransanci, Fotugis, Larabci, Swahili, Hausa, Yarbanci da Ibo.', 'Nkuzi n\'asụsụ Bekee, Frenchi, Potuguiz, Arabik, Swahili, Hausa, Yoruba na Igbo.', 'Masomo kwa Kiingereza, Kifaransa, Kireno, Kiarabu, Kiswahili, Kihausa, Kiyoruba na Kiigbo.'],
    ['Hands-on skills', 'Compétences pratiques', 'Competências práticas', 'مهارات عملية', 'Ọgbọ́n ọwọ́', 'Sana\'o\'in hannu', 'Nka aka', 'Ujuzi wa mikono'],
    ['Practical courses you can turn into income, from production to digital work.', 'Des cours pratiques que vous pouvez transformer en revenus, de la production au travail numérique.', 'Cursos práticos que pode transformar em rendimento, da produção ao trabalho digital.', 'دورات عملية يمكنك تحويلها إلى دخل، من الإنتاج إلى العمل الرقمي.', 'Ẹ̀kọ́ ọwọ́ tí o lè sọ di owó, láti iṣẹ́ ìṣelọ́pọ̀ dé iṣẹ́ ẹ̀rọ ayélujára.', 'Kwasa-kwasai na aiki da za ki mayar da su kudin shiga, daga sarrafa kaya zuwa aikin zamani.', 'Ọmụmụ ihe bara uru ị nwere ike ime ka ọ bụrụ ego, site na nrụpụta ruo ọrụ dijitalụ.', 'Kozi za vitendo unazoweza kuzigeuza kuwa kipato, kuanzia uzalishaji hadi kazi za kidijitali.'],
    ['Certificates that count', 'Des certificats qui comptent', 'Certificados que contam', 'شهادات لها قيمة', 'Ìwé-ẹ̀rí tó níyì', 'Takardun shaida masu daraja', 'Asambodo bara uru', 'Vyeti vyenye thamani'],
    ['Each certificate has a unique ID and a QR code anyone can check online.', 'Chaque certificat a un identifiant unique et un code QR que chacun peut vérifier en ligne.', 'Cada certificado tem um ID único e um código QR que qualquer pessoa pode verificar online.', 'لكل شهادة رقم فريد ورمز QR يمكن لأي شخص التحقق منه عبر الإنترنت.', 'Ìwé-ẹ̀rí kọ̀ọ̀kan ní nọ́ńbà àrà-ọ̀tọ̀ àti kóòdù QR tí ẹnikẹ́ni lè ṣàyẹ̀wò lórí ayélujára.', 'Kowace takardar shaida tana da lamba ta musamman da lambar QR da kowa zai iya duba a intanet.', 'Asambodo ọ bụla nwere nọmba pụrụ iche na koodu QR onye ọ bụla nwere ike ịlele n\'ịntanetị.', 'Kila cheti kina namba ya kipekee na msimbo wa QR ambao mtu yeyote anaweza kukagua mtandaoni.'],
    ['A clear path from first lesson to certificate', 'Un parcours clair de la première leçon au certificat', 'Um caminho claro da primeira aula ao certificado', 'مسار واضح من الدرس الأول إلى الشهادة', 'Ọ̀nà tó ṣe kedere láti ẹ̀kọ́ àkọ́kọ́ dé ìwé-ẹ̀rí', 'Hanya bayyananna daga darasin farko zuwa takardar shaida', 'Ụzọ doro anya site na nkuzi mbụ ruo asambodo', 'Njia iliyo wazi kutoka somo la kwanza hadi cheti'],
    ['Every learner moves through the same four stages, in order, so each one builds on the last.', 'Chaque apprenante suit les mêmes quatre étapes, dans l\'ordre, chacune s\'appuyant sur la précédente.', 'Cada aluna passa pelas mesmas quatro etapas, por ordem, e cada uma assenta na anterior.', 'تمرّ كل متعلّمة بالمراحل الأربع نفسها بالترتيب، فتبني كل مرحلة على سابقتها.', 'Gbogbo akẹ́kọ̀ọ́ ló ń la ìpele mẹ́rin kan náà kọjá, ní ṣísẹ̀-n-tẹ̀lé, kí ọ̀kọ̀ọ̀kan lè dúró lórí èyí tó ṣáájú.', 'Kowace mai koyo tana bin matakai hudu iri daya, bi da bi, kowane yana ginuwa kan na baya.', 'Onye mmụta ọ bụla na-agafe otu ọkwa anọ ahụ, n\'usoro, ka nke ọ bụla wuo n\'elu nke gara aga.', 'Kila mwanafunzi hupitia hatua nne zilezile, kwa mpangilio, ili kila moja ijengwe juu ya iliyotangulia.'],
    ['Rooting', 'Enracinement', 'Enraizamento', 'التجذّر', 'Ìfìdímúlẹ̀', 'Kafuwa', 'Mgbọrọgwụ', 'Kuweka mizizi'],
    ['Settle your identity, values and foundation. Everything else grows from here.', 'Ancrez votre identité, vos valeurs et vos fondations. Tout le reste en découle.', 'Firme a sua identidade, valores e alicerces. Tudo o resto cresce daqui.', 'ثبّتي هويتك وقيمك وأساسك. كل شيء آخر ينمو من هنا.', 'Fìdí ìdánimọ̀, ìlànà àti ìpìlẹ̀ rẹ múlẹ̀. Gbogbo nǹkan míì ń dàgbà láti ibí.', 'Ki tabbatar da asalinki, dabi\'unki da tushenki. Komai yana girma daga nan.', 'Guzosie njirimara gị, ụkpụrụ na ntọala gị ike. Ihe niile ọzọ na-esi ebe a too.', 'Imarisha utambulisho, maadili na msingi wako. Kila kitu kingine hukua kutoka hapa.'],
    ['Blueprint', 'Plan de vie', 'Plano de vida', 'المخطّط', 'Àwòrán ìgbésí ayé', 'Taswira', 'Atụmatụ', 'Ramani ya maisha'],
    ['Design your life, family and leadership plan, with a clear picture of where you are going.', 'Concevez votre plan de vie, de famille et de leadership, avec une vision claire de votre direction.', 'Desenhe o seu plano de vida, família e liderança, com uma imagem clara de para onde vai.', 'صمّمي خطة حياتك وأسرتك وقيادتك، مع صورة واضحة لوجهتك.', 'Ṣe ètò ayé, ìdílé àti olórí rẹ, pẹ̀lú àwòrán tó ṣe kedere ibi tí o ń lọ.', 'Ki tsara shirin rayuwarki, iyali da jagoranci, da hoto bayyananne na inda kike zuwa.', 'Kpụpụta atụmatụ ndụ, ezinụlọ na ndu gị, ya na foto doro anya nke ebe ị na-aga.', 'Buni mpango wa maisha, familia na uongozi wako, ukiwa na picha wazi ya unakoelekea.'],
    ['Skills Hub', 'Pôle compétences', 'Centro de competências', 'مركز المهارات', 'Ibùdó Ọgbọ́n', 'Cibiyar Sana\'o\'i', 'Ebe Nka', 'Kitovu cha Ujuzi'],
    ['Choose practical and digital skills courses and learn them step by step.', 'Choisissez des cours de compétences pratiques et numériques et apprenez pas à pas.', 'Escolha cursos de competências práticas e digitais e aprenda passo a passo.', 'اختاري دورات المهارات العملية والرقمية وتعلّميها خطوة بخطوة.', 'Yan ẹ̀kọ́ ọgbọ́n ọwọ́ àti ẹ̀rọ ayélujára, kí o sì kọ́ wọn ní ìgbésẹ̀-ìgbésẹ̀.', 'Zabi kwasa-kwasan sana\'o\'in hannu da na zamani ki koye su mataki-mataki.', 'Họrọ ọmụmụ ihe nka aka na nka dijitalụ ma mụta ha nwayọọ nwayọọ.', 'Chagua kozi za ujuzi wa vitendo na kidijitali na uzijifunze hatua kwa hatua.'],
    ['Certificate and community', 'Certificat et communauté', 'Certificado e comunidade', 'الشهادة والمجتمع', 'Ìwé-ẹ̀rí àti àwùjọ', 'Takardar shaida da al\'umma', 'Asambodo na obodo', 'Cheti na jamii'],
    ['Earn a verifiable certificate, join the alumni community and sell your skills on WODDI Marketplace.', 'Obtenez un certificat vérifiable, rejoignez la communauté des diplômées et vendez vos compétences sur WODDI Marketplace.', 'Obtenha um certificado verificável, junte-se à comunidade de antigas alunas e venda as suas competências no WODDI Marketplace.', 'احصلي على شهادة موثّقة، وانضمي إلى مجتمع الخريجات، وبيعي مهاراتك في سوق WODDI.', 'Gba ìwé-ẹ̀rí tí a lè ṣàyẹ̀wò, darapọ̀ mọ́ àwùjọ àwọn tó ti yege, kí o sì ta ọgbọ́n rẹ ní WODDI Marketplace.', 'Ki samu takardar shaida da za a iya tantancewa, ki shiga al\'ummar wadanda suka kammala, ki sayar da sana\'arki a WODDI Marketplace.', 'Nweta asambodo a pụrụ ịlele, banye n\'obodo ndị gụsịrị akwụkwọ ma ree nka gị na WODDI Marketplace.', 'Pata cheti kinachothibitishwa, jiunge na jamii ya wahitimu na uuze ujuzi wako kwenye WODDI Marketplace.'],
    ['Hire skilled women. Buy what they make.', 'Engagez des femmes qualifiées. Achetez ce qu\'elles fabriquent.', 'Contrate mulheres qualificadas. Compre o que elas fazem.', 'وظّفي نساء ماهرات. واشتري ما يصنعنه.', 'Gba àwọn obìnrin onímọ̀ṣẹ́ síṣẹ́. Ra ohun tí wọ́n ṣe.', 'Dauki mata masu fasaha aiki. Saya abin da suke yi.', 'Were ụmụ nwanyị nwere nka n\'ọrụ. Zụta ihe ha na-emepụta.', 'Ajiri wanawake wenye ujuzi. Nunua wanachotengeneza.'],
    ['Products and services from certified WODDI graduates. Send a request, agree directly, then rate their work with stars.', 'Produits et services de diplômées certifiées WODDI. Envoyez une demande, mettez-vous d\'accord directement, puis notez leur travail avec des étoiles.', 'Produtos e serviços de diplomadas certificadas da WODDI. Envie um pedido, combine diretamente e depois avalie o trabalho com estrelas.', 'منتجات وخدمات من خريجات WODDI المعتمدات. أرسلي طلبًا، واتفقي مباشرة، ثم قيّمي عملهن بالنجوم.', 'Ọjà àti iṣẹ́ láti ọ̀dọ̀ àwọn tó yege ní WODDI pẹ̀lú ìwé-ẹ̀rí. Fi ìbéèrè ránṣẹ́, ẹ fohùn ṣọ̀kan tààrà, lẹ́yìn náà fi ìràwọ̀ díwọ̀n iṣẹ́ wọn.', 'Kayayyaki da ayyuka daga wadanda suka kammala WODDI da takardar shaida. Aika bukata, ku yi yarjejeniya kai tsaye, sannan ki ba aikinsu taurari.', 'Ngwaahịa na ọrụ sitere n\'aka ndị gụsịrị WODDI nwere asambodo. Ziga arịrịọ, kwekọrịta ozugbo, wee jiri kpakpando nyochaa ọrụ ha.', 'Bidhaa na huduma kutoka kwa wahitimu wa WODDI wenye vyeti. Tuma ombi, kubalianeni moja kwa moja, kisha kadiria kazi yao kwa nyota.'],
    ['Soap and cleaning', 'Savon et nettoyage', 'Sabão e limpeza', 'الصابون والتنظيف', 'Ọṣẹ àti ìmọ́tótó', 'Sabulu da tsafta', 'Ncha na nhicha', 'Sabuni na usafi'],
    ['Fashion', 'Mode', 'Moda', 'الأزياء', 'Aṣọ', 'Kayan sawa', 'Ejiji', 'Mitindo'],
    ['Food', 'Alimentation', 'Comida', 'الطعام', 'Oúnjẹ', 'Abinci', 'Nri', 'Chakula'],
    ['Digital skills', 'Compétences numériques', 'Competências digitais', 'مهارات رقمية', 'Ọgbọ́n ẹ̀rọ ayélujára', 'Sana\'o\'in zamani', 'Nka dijitalụ', 'Ujuzi wa kidijitali'],
    ['Coaching', 'Coaching', 'Coaching', 'التوجيه', 'Ìtọ́sọ́nà', 'Horarwa', 'Nkuzi nduzi', 'Ukocha'],
    ['Creative', 'Création', 'Criativo', 'الإبداع', 'Iṣẹ́ ọnà', 'Kirkira', 'Okike', 'Ubunifu'],
    ['Business', 'Entreprise', 'Negócios', 'الأعمال', 'Òwò', 'Kasuwanci', 'Azụmahịa', 'Biashara'],
    ['Browse marketplace', 'Parcourir le marché', 'Ver o mercado', 'تصفّحي السوق', 'Wo ọjà', 'Duba kasuwa', 'Lelee ahịa', 'Vinjari soko'],
    ['Sell on WODDI', 'Vendre sur WODDI', 'Vender na WODDI', 'بيعي على WODDI', 'Tà ní WODDI', 'Sayar a WODDI', 'Ree na WODDI', 'Uza kwenye WODDI'],
    ['Your journey starts today', 'Votre parcours commence aujourd\'hui', 'A sua jornada começa hoje', 'رحلتك تبدأ اليوم', 'Ìrìnàjò rẹ bẹ̀rẹ̀ lónìí', 'Tafiyarki ta fara yau', 'Njem gị na-amalite taa', 'Safari yako inaanza leo'],
    ['Choose your network, create a free account and start your first lesson in a few minutes.', 'Choisissez votre réseau, créez un compte gratuit et commencez votre première leçon en quelques minutes.', 'Escolha a sua rede, crie uma conta gratuita e comece a primeira aula em poucos minutos.', 'اختاري شبكتك، وأنشئي حسابًا مجانيًا، وابدئي درسك الأول خلال دقائق.', 'Yan nẹtiwọọki rẹ, ṣẹ̀dá àkáǹtì ọ̀fẹ́, kí o sì bẹ̀rẹ̀ ẹ̀kọ́ àkọ́kọ́ rẹ láàárín ìṣẹ́jú díẹ̀.', 'Zabi cibiyarki, bude asusu kyauta, ki fara darasinki na farko cikin mintuna kadan.', 'Họrọ netwọk gị, mepụta akaụntụ n\'efu ma malite nkuzi mbụ gị n\'ime nkeji ole na ole.', 'Chagua mtandao wako, fungua akaunti bure na uanze somo lako la kwanza ndani ya dakika chache.'],
    ['WODDI Nurture NextGen Network (WNNN, 18 to 29)', 'WODDI Nurture NextGen Network (WNNN, 18 à 29 ans)', 'WODDI Nurture NextGen Network (WNNN, 18 a 29 anos)', 'WODDI Nurture NextGen Network (WNNN, من 18 إلى 29)', 'WODDI Nurture NextGen Network (WNNN, 18 sí 29)', 'WODDI Nurture NextGen Network (WNNN, 18 zuwa 29)', 'WODDI Nurture NextGen Network (WNNN, 18 ruo 29)', 'WODDI Nurture NextGen Network (WNNN, miaka 18 hadi 29)'],
    ['Identity, leadership, practical skills and legacy building for women across Africa.', 'Identité, leadership, compétences pratiques et héritage pour les femmes de toute l\'Afrique.', 'Identidade, liderança, competências práticas e construção de legado para mulheres de toda a África.', 'الهوية والقيادة والمهارات العملية وبناء الإرث للنساء في جميع أنحاء أفريقيا.', 'Ìdánimọ̀, olórí, ọgbọ́n ọwọ́ àti kíkọ́ ogún fún obìnrin jákèjádò Áfíríkà.', 'Asali, jagoranci, sana\'o\'i da gina gado ga mata a fadin Afirka.', 'Njirimara, ndu, nka na iwu ihe nketa maka ụmụ nwanyị n\'Afrịka niile.', 'Utambulisho, uongozi, ujuzi wa vitendo na kujenga urithi kwa wanawake kote Afrika.'],
    ['Membership', 'Adhésion', 'Adesão', 'العضوية', 'Ọmọ ẹgbẹ́', 'Zama memba', 'Ndị otu', 'Uanachama'],
    ['Volunteer', 'Bénévolat', 'Voluntariado', 'التطوّع', 'Ìyọ̀ǹda ara ẹni', 'Aikin sa kai', 'Onye ọrụ afọ ofufo', 'Kujitolea'],
    ['Courses and languages', 'Cours et langues', 'Cursos e línguas', 'الدورات واللغات', 'Ẹ̀kọ́ àti èdè', 'Kwasa-kwasai da harsuna', 'Ọmụmụ ihe na asụsụ', 'Kozi na lugha'],
    ['Verify a certificate', 'Vérifier un certificat', 'Verificar um certificado', 'التحقق من شهادة', 'Ṣàyẹ̀wò ìwé-ẹ̀rí', 'Tantance takardar shaida', 'Lelee asambodo', 'Thibitisha cheti'],
    ['Android app', 'Application Android', 'Aplicação Android', 'تطبيق أندرويد', 'Ohun èlò Android', 'Manhajar Android', 'Ngwa Android', 'Programu ya Android'],
    ['Privacy policy', 'Politique de confidentialité', 'Política de privacidade', 'سياسة الخصوصية', 'Ìlànà ìpamọ́', 'Manufar sirri', 'Iwu nzuzo', 'Sera ya faragha'],
    ['© 2026 WODDI Institute. Empowering women, transforming nations.', '© 2026 WODDI Institute. Autonomiser les femmes, transformer les nations.', '© 2026 WODDI Institute. Capacitar mulheres, transformar nações.', '© 2026 WODDI Institute. تمكين المرأة، وتحويل الأمم.', '© 2026 WODDI Institute. A ń fún obìnrin lágbára, a ń yí orílẹ̀-èdè padà.', '© 2026 WODDI Institute. Karfafa mata, sauya kasashe.', '© 2026 WODDI Institute. Ịkwado ụmụ nwanyị, ịgbanwe mba.', '© 2026 WODDI Institute. Kuwawezesha wanawake, kubadilisha mataifa.'],
    ['Made with care for Africa', 'Conçu avec soin pour l\'Afrique', 'Feito com carinho para África', 'صُنع بعناية لأفريقيا', 'A fi ìfẹ́ ṣe é fún Áfíríkà', 'An yi shi da kulawa don Afirka', 'Emere ya n\'ịhụnanya maka Afrịka', 'Imetengenezwa kwa upendo kwa ajili ya Afrika'],
    ['Alumni community', 'Communauté des diplômées', 'Comunidade de antigas alunas', 'مجتمع الخريجات', 'Àwùjọ àwọn tó yege', 'Al\'ummar wadanda suka kammala', 'Obodo ndị gụsịrị akwụkwọ', 'Jamii ya wahitimu'],
    ['Learn in your language', 'Apprenez dans votre langue', 'Aprenda na sua língua', 'تعلّمي بلغتك', 'Kẹ́kọ̀ọ́ ní èdè rẹ', 'Ki koya da harshenki', 'Mụta n\'asụsụ gị', 'Jifunze kwa lugha yako'],
    ['Tap a language. The whole page changes.', 'Touchez une langue. Toute la page change.', 'Toque numa língua. A página inteira muda.', 'اضغطي على لغة، وستتغيّر الصفحة كلها.', 'Tẹ èdè kan. Gbogbo ojú-ewé á yí padà.', 'Danna harshe. Dukan shafin zai canza.', 'Pịa asụsụ. Ibe niile ga-agbanwe.', 'Gusa lugha. Ukurasa wote unabadilika.'],
    ['Your first lesson is free', 'Votre première leçon est gratuite', 'A sua primeira aula é grátis', 'درسك الأول مجاني', 'Ẹ̀kọ́ àkọ́kọ́ rẹ jẹ́ ọ̀fẹ́', 'Darasinki na farko kyauta ne', 'Nkuzi mbụ gị bụ n\'efu', 'Somo lako la kwanza ni bure'],
    ['Hi! I am WODDI. Five minutes a day is all it takes to learn a skill that pays.', 'Bonjour ! Je suis WODDI. Cinq minutes par jour suffisent pour apprendre un métier qui rapporte.', 'Olá! Eu sou a WODDI. Cinco minutos por dia bastam para aprender uma competência que dá rendimento.', 'مرحبًا! أنا WODDI. خمس دقائق يوميًا تكفي لتتعلّمي مهارة تكسبين منها.', 'Ẹ n lẹ! Èmi ni WODDI. Ìṣẹ́jú márùn-ún lójúmọ́ ti tó láti kọ́ ọgbọ́n tó ń mówó wọlé.', 'Sannu! Ni ce WODDI. Minti biyar a rana ya isa ki koyi sana\'a mai kawo kudi.', 'Ndewo! Abụ m WODDI. Nkeji ise kwa ụbọchị ezuola ịmụta nka na-eweta ego.', 'Habari! Mimi ni WODDI. Dakika tano kwa siku zinatosha kujifunza ujuzi unaolipa.'],
    ['Ready to learn a new skill? Your first lesson is free and works on any phone.', 'Prête à apprendre une nouvelle compétence ? Votre première leçon est gratuite et fonctionne sur tout téléphone.', 'Pronta para aprender algo novo? A primeira aula é grátis e funciona em qualquer telemóvel.', 'هل أنتِ مستعدة لتعلّم مهارة جديدة؟ درسك الأول مجاني ويعمل على أي هاتف.', 'Ṣé o ti ṣetán láti kọ́ ọgbọ́n tuntun? Ẹ̀kọ́ àkọ́kọ́ rẹ jẹ́ ọ̀fẹ́, ó sì ń ṣiṣẹ́ lórí fóònù èyíkéyìí.', 'Kin shirya koyon sabuwar sana\'a? Darasinki na farko kyauta ne kuma yana aiki a kowace waya.', 'Ị dịla njikere ịmụta nka ọhụrụ? Nkuzi mbụ gị bụ n\'efu ma na-arụ ọrụ na ekwentị ọ bụla.', 'Uko tayari kujifunza ujuzi mpya? Somo lako la kwanza ni bure na linafanya kazi kwenye simu yoyote.'],
    ['Women across Africa are learning with WODDI today. Join them in two minutes.', 'Des femmes de toute l\'Afrique apprennent avec WODDI aujourd\'hui. Rejoignez-les en deux minutes.', 'Mulheres de toda a África aprendem hoje com a WODDI. Junte-se a elas em dois minutos.', 'نساء من جميع أنحاء أفريقيا يتعلّمن مع WODDI اليوم. انضمي إليهن خلال دقيقتين.', 'Àwọn obìnrin jákèjádò Áfíríkà ń kẹ́kọ̀ọ́ pẹ̀lú WODDI lónìí. Darapọ̀ mọ́ wọn láàárín ìṣẹ́jú méjì.', 'Mata a fadin Afirka suna koyo da WODDI yau. Ki shiga cikinsu cikin minti biyu.', 'Ụmụ nwanyị n\'Afrịka niile na-amụ ihe na WODDI taa. Soro ha n\'ime nkeji abụọ.', 'Wanawake kote Afrika wanajifunza na WODDI leo. Jiunge nao ndani ya dakika mbili.'],
    ['Certificates, mentors and a marketplace for your skills.', 'Certificats, mentors et un marché pour vos compétences.', 'Certificados, mentoras e um mercado para as suas competências.', 'شهادات ومرشدات وسوق لمهاراتك.', 'Ìwé-ẹ̀rí, olùtọ́nisọ́nà àti ọjà fún ọgbọ́n rẹ.', 'Takardun shaida, masu jagora da kasuwa don sana\'arki.', 'Asambodo, ndị ndụmọdụ na ahịa maka nka gị.', 'Vyeti, washauri na soko la ujuzi wako.'],
    ['Start learning free', 'Commencer gratuitement', 'Começar grátis', 'ابدئي التعلّم مجانًا', 'Bẹ̀rẹ̀ ẹ̀kọ́ lọ́fẹ̀ẹ́', 'Fara koyo kyauta', 'Malite mmụta n\'efu', 'Anza kujifunza bure'],
    ['Become the woman you were designed to be.', 'Devenez la femme que vous êtes appelée à devenir.', 'Torne-se a mulher que você foi criada para ser.', 'كوني المرأة التي خُلقتِ لتكونيها.', 'Di obìnrin tí a dá ọ láti jẹ́.', 'Zama macen da aka halitta ki zama.', 'Bụrụ nwanyị ahụ e kere gị ka ị bụrụ.', 'Kuwa mwanamke uliyeumbwa kuwa.'],
    ['Home', 'Accueil', 'Início', 'الرئيسية', 'Ilé', 'Gida', 'Ụlọ', 'Nyumbani'],
    ['Market', 'Marché', 'Mercado', 'السوق', 'Ọjà', 'Kasuwa', 'Ahịa', 'Soko'],
    ['A private space for women who passed a WODDI course. Share wins, find mentors and collaborators, and hear about jobs, grants and events first.', 'Un espace privé pour les femmes qui ont réussi un cours WODDI. Partagez vos réussites, trouvez des mentors et des partenaires, et découvrez en premier les emplois, subventions et événements.', 'Um espaço privado para mulheres que concluíram um curso WODDI. Partilhe conquistas, encontre mentoras e parceiras e saiba primeiro de empregos, bolsas e eventos.', 'مساحة خاصة للنساء اللواتي اجتزن دورة في WODDI. شاركي نجاحاتك، وجدي مرشدات وشريكات، واعرفي أولًا عن الوظائف والمنح والفعاليات.', 'Àyè àdáni fún àwọn obìnrin tí wọ́n yege ẹ̀kọ́ WODDI. Pín àṣeyọrí rẹ, wá olùtọ́nisọ́nà àti alábàáṣiṣẹ́, kí o sì gbọ́ nípa iṣẹ́, ẹ̀bùn owó àti ìṣẹ̀lẹ̀ ṣáájú.', 'Wuri na musamman ga matan da suka ci jarrabawar kwas na WODDI. Raba nasarorinki, nemo masu jagora da abokan aiki, ki fara jin labarin ayyuka, tallafi da taruka.', 'Ebe nzuzo maka ụmụ nwanyị gafere ọmụmụ ihe WODDI. Kesaa mmeri gị, chọta ndị ndụmọdụ na ndị mmekọ, ma nụrụ maka ọrụ, onyinye ego na mmemme mbụ.', 'Nafasi ya faragha kwa wanawake waliofaulu kozi ya WODDI. Shiriki mafanikio, pata washauri na washirika, na upate habari za kazi, ruzuku na matukio kwanza.'],
    ['Visit the alumni community', 'Visiter la communauté des diplômées', 'Visitar a comunidade de antigas alunas', 'زوري مجتمع الخريجات', 'Ṣàbẹ̀wò sí àwùjọ àwọn tó yege', 'Ziyarci al\'ummar wadanda suka kammala', 'Gaa n\'obodo ndị gụsịrị akwụkwọ', 'Tembelea jamii ya wahitimu'],
    ['Not now', 'Pas maintenant', 'Agora não', 'ليس الآن', 'Kì í ṣe báyìí', 'Ba yanzu ba', 'Ọ bụghị ugbu a', 'Si sasa'],
    ['More languages', 'Plus de langues', 'Mais línguas', 'لغات أخرى', 'Àwọn èdè míràn', 'Ƙarin harsuna', 'Asụsụ ndị ọzọ', 'Lugha zaidi'],
    ['African languages', 'langues africaines', 'línguas africanas', 'لغة أفريقية', 'èdè Áfíríkà', 'harsunan Afirka', 'asụsụ Africa', 'lugha za Kiafrika'],
    ['Choose your language', 'Choisissez votre langue', 'Escolha a sua língua', 'اختاري لغتك', 'Yan èdè rẹ', 'Zaɓi harshenki', 'Họrọ asụsụ gị', 'Chagua lugha yako'],
    ['Search: Zulu, Amharic, Twi…', 'Rechercher : zoulou, amharique, twi…', 'Pesquisar: zulu, amárico, twi…', 'ابحثي: الزولو، الأمهرية، التوي…', 'Wá: Zulu, Amharic, Twi…', 'Nema: Zulu, Amharic, Twi…', 'Chọọ: Zulu, Amharic, Twi…', 'Tafuta: Kizulu, Kiamhari, Twi…'],
    ['The first time a language is chosen, AI translates the page in a few seconds. After that it opens instantly for everyone.', 'La première fois qu\'une langue est choisie, l\'IA traduit la page en quelques secondes. Ensuite, elle s\'ouvre instantanément pour tout le monde.', 'Na primeira vez que uma língua é escolhida, a IA traduz a página em poucos segundos. Depois abre logo para todos.', 'في أول مرة تُختار فيها لغة، يترجم الذكاء الاصطناعي الصفحة خلال ثوانٍ. بعد ذلك تفتح فورًا للجميع.', 'Ìgbà àkọ́kọ́ tí a bá yan èdè kan, AI yóò túmọ̀ ojú-ìwé náà ní ìṣẹ́jú-àáyá díẹ̀. Lẹ́yìn náà, yóò ṣí lẹ́sẹ̀kẹsẹ̀ fún gbogbo ènìyàn.', 'A karon farko da aka zaɓi harshe, AI tana fassara shafin cikin daƙiƙoƙi kaɗan. Bayan haka yana buɗewa nan take ga kowa.', 'Oge mbụ a họrọ asụsụ, AI na-atụgharị ibe ahụ n\'ime sekọnd ole na ole. Mgbe nke ahụ gasịrị, ọ na-emepe ozugbo maka onye ọ bụla.', 'Mara ya kwanza lugha inapochaguliwa, AI hutafsiri ukurasa kwa sekunde chache. Baada ya hapo hufunguka papo hapo kwa kila mtu.'],
    ['Back to English', 'Revenir à l\'anglais', 'Voltar ao inglês', 'العودة إلى الإنجليزية', 'Padà sí Gẹ̀ẹ́sì', 'Koma zuwa Turanci', 'Laghachi na Bekee', 'Rudi kwa Kiingereza'],
    ['African countries', 'Pays africains', 'Países africanos', 'دولة أفريقية', 'Orílẹ̀-èdè Áfíríkà', 'Ƙasashen Afirka', 'Mba Africa', 'Nchi za Afrika'],
    ['Learn practical skills. Discover your potential. Build your future.', 'Apprenez des compétences pratiques. Découvrez votre potentiel. Construisez votre avenir.', 'Aprenda competências práticas. Descubra o seu potencial. Construa o seu futuro.', 'تعلّمي مهارات عملية. اكتشفي إمكاناتك. ابني مستقبلك.', 'Kọ́ àwọn ọgbọ́n ìlò. Ṣàwárí agbára rẹ. Kọ́ ọjọ́ iwájú rẹ.', 'Koyi ƙwarewar aiki. Gano baiwarki. Gina makomarki.', 'Mụta nkà bara uru. Chọpụta ikike gị. Wuo ọdịnihu gị.', 'Jifunze ujuzi wa vitendo. Gundua uwezo wako. Jenga mustakabali wako.'],
    ['Access practical courses, supportive mentors, career and entrepreneurship guidance, and opportunities to turn your skills into income. Learn at your own pace, in your preferred language, and earn certificates that showcase what you have achieved.', 'Accédez à des cours pratiques, à des mentors bienveillants, à un accompagnement en carrière et en entrepreneuriat, et à des occasions de transformer vos compétences en revenus. Apprenez à votre rythme, dans la langue de votre choix, et obtenez des certificats qui montrent ce que vous avez accompli.', 'Aceda a cursos práticos, mentoras que apoiam, orientação de carreira e empreendedorismo, e oportunidades para transformar as suas competências em rendimento. Aprenda ao seu ritmo, na língua que preferir, e obtenha certificados que mostram o que conquistou.', 'احصلي على دورات عملية، ومرشدات داعمات، وتوجيه في المسار المهني وريادة الأعمال، وفرص لتحويل مهاراتك إلى دخل. تعلّمي بالسرعة التي تناسبك، وباللغة التي تفضّلينها، واحصلي على شهادات تُظهر ما أنجزتِه.', 'Rí àwọn ẹ̀kọ́ ìlò, àwọn olùtọ́nisọ́nà tó ń ṣàtìlẹ́yìn, ìtọ́sọ́nà nípa iṣẹ́ àti òwò, àti àǹfààní láti sọ ọgbọ́n rẹ di owó. Kọ́ ẹ̀kọ́ ní ìwọ̀n tí ó bá ọ mu, ní èdè tí o fẹ́, kí o sì gba ìwé-ẹ̀rí tí ó fi ohun tí o ti ṣe hàn.', 'Samu kwasa-kwasan aiki, masu jagora masu taimako, shawarwari kan aiki da kasuwanci, da damar mayar da ƙwarewarki kuɗi. Ki koya yadda ya dace da ke, da harshen da kika fi so, ki kuma sami takardun shaida da ke nuna abin da kika cimma.', 'Nweta ọmụmụ ihe bara uru, ndị ndụmọdụ na-akwado gị, ntuziaka gbasara ọrụ na azụmahịa, na ohere iji nkà gị mee ego. Mụta n\'ọsọ gị, n\'asụsụ ị họọrọ, ma nweta asambodo na-egosi ihe ị rụzuru.', 'Pata kozi za vitendo, washauri wanaokuunga mkono, mwongozo wa kazi na ujasiriamali, na fursa za kugeuza ujuzi wako kuwa kipato. Jifunze kwa kasi yako, kwa lugha unayopendelea, na upate vyeti vinavyoonyesha ulichofanikisha.'],
    ['Skills that change your story', 'Des compétences qui changent votre histoire', 'Competências que mudam a sua história', 'مهارات تغيّر قصتك', 'Àwọn ọgbọ́n tí ó ń yí ìtàn rẹ padà', 'Ƙwarewar da ke canza labarinki', 'Nkà ndị na-agbanwe akụkọ gị', 'Ujuzi unaobadilisha hadithi yako'],
    ['A few of the practical courses waiting for you. Every course is free and ends with a certificate you can share.', 'Quelques-uns des cours pratiques qui vous attendent. Chaque cours est gratuit et se termine par un certificat que vous pouvez partager.', 'Alguns dos cursos práticos à sua espera. Todos os cursos são gratuitos e terminam com um certificado que pode partilhar.', 'بعض الدورات العملية التي تنتظرك. كل دورة مجانية وتنتهي بشهادة يمكنك مشاركتها.', 'Díẹ̀ lára àwọn ẹ̀kọ́ ìlò tí ó ń dúró dè ọ́. Gbogbo ẹ̀kọ́ jẹ́ ọ̀fẹ́, ó sì parí pẹ̀lú ìwé-ẹ̀rí tí o lè pín.', 'Wasu daga cikin kwasa-kwasan aiki da ke jiran ki. Kowane kwas kyauta ne kuma yana ƙarewa da takardar shaida da za ki iya rabawa.', 'Ụfọdụ n\'ime ọmụmụ ihe bara uru na-echere gị. Ọmụmụ ihe ọ bụla bụ n\'efu ma na-ejedebe na asambodo ị nwere ike ikesa.', 'Baadhi ya kozi za vitendo zinazokusubiri. Kila kozi ni bure na inamalizika kwa cheti unachoweza kushiriki.'],
    ['Free', 'Gratuit', 'Grátis', 'مجاني', 'Ọ̀fẹ́', 'Kyauta', 'N\'efu', 'Bure'],
    ['Production', 'Production', 'Produção', 'الإنتاج', 'Ìṣelọ́pọ̀', 'Samarwa', 'Mmepụta', 'Uzalishaji'],
    ['Food', 'Alimentation', 'Alimentação', 'الطعام', 'Oúnjẹ', 'Abinci', 'Nri', 'Chakula'],
    ['Fashion', 'Mode', 'Moda', 'الأزياء', 'Aṣọ', 'Kayan sawa', 'Ejiji', 'Mitindo'],
    ['Digital', 'Numérique', 'Digital', 'رقمي', 'Ẹ̀rọ ayélujára', 'Fasahar zamani', 'Dijitalụ', 'Kidijitali'],
    ['Beauty', 'Beauté', 'Beleza', 'الجمال', 'Ẹwà', 'Kyau', 'Ịma mma', 'Urembo'],
    ['Money', 'Argent', 'Dinheiro', 'المال', 'Owó', 'Kuɗi', 'Ego', 'Fedha'],
    ['Personal growth', 'Développement personnel', 'Crescimento pessoal', 'النمو الشخصي', 'Ìdàgbàsókè ara ẹni', 'Ci gaban kai', 'Uto onwe', 'Ukuaji binafsi'],
    ['Create a free account to see every course', 'Créez un compte gratuit pour voir tous les cours', 'Crie uma conta gratuita para ver todos os cursos', 'أنشئي حسابًا مجانيًا لرؤية كل الدورات', 'Ṣẹ̀dá àkáǹtì ọ̀fẹ́ láti rí gbogbo ẹ̀kọ́', 'Ƙirƙiri asusun kyauta don ganin dukkan kwasa-kwasai', 'Mepụta akaụntụ n\'efu ka ị hụ ọmụmụ ihe niile', 'Fungua akaunti ya bure kuona kozi zote'],
    ['Detergent & Cleaning Products Production', 'Fabrication de détergents et produits d\'entretien', 'Produção de detergentes e produtos de limpeza', 'إنتاج المنظفات ومواد التنظيف', 'Ṣíṣe ọṣẹ ìfọṣọ àti ohun ìmọ́tótó', 'Samar da sabulun wanki da kayan tsaftacewa', 'Imepụta ncha na ihe nhicha', 'Utengenezaji wa sabuni na bidhaa za usafi'],
    ['Catering Basics & Food Safety', 'Bases de la restauration et sécurité alimentaire', 'Noções de catering e segurança alimentar', 'أساسيات تقديم الطعام وسلامة الغذاء', 'Ìpìlẹ̀ ìpèsè oúnjẹ àti ààbò oúnjẹ', 'Tushen dafa abinci na taro da tsaftar abinci', 'Ntọala nri ememme na nchekwa nri', 'Misingi ya upishi na usalama wa chakula'],
    ['Fashion Basics & Clothing Alterations', 'Bases de la mode et retouches de vêtements', 'Noções de moda e ajustes de roupa', 'أساسيات الأزياء وتعديل الملابس', 'Ìpìlẹ̀ aṣọ àti àtúnṣe aṣọ', 'Tushen kayan sawa da gyaran tufafi', 'Ntọala ejiji na mgbanwe uwe', 'Misingi ya mitindo na marekebisho ya nguo'],
    ['Content Creation (Canva & Phone Tools)', 'Création de contenu (Canva et outils du téléphone)', 'Criação de conteúdo (Canva e ferramentas do telemóvel)', 'صناعة المحتوى (Canva وأدوات الهاتف)', 'Ṣíṣẹ̀dá àkóónú (Canva àti irinṣẹ́ fóònù)', 'Ƙirƙirar abun ciki (Canva da kayan aikin waya)', 'Imepụta ọdịnaya (Canva na ngwa ekwentị)', 'Uundaji wa maudhui (Canva na zana za simu)'],
    ['Natural Skincare (Basic Creams & Oils)', 'Soins naturels de la peau (crèmes et huiles de base)', 'Cuidados naturais da pele (cremes e óleos básicos)', 'العناية الطبيعية بالبشرة (كريمات وزيوت أساسية)', 'Ìtọ́jú awọ ara àdáyébá (ìpara àti òróró ìpìlẹ̀)', 'Kula da fata ta halitta (man shafawa da mai)', 'Nlekọta akpụkpọ ahụ sitere n\'okike (ude na mmanụ)', 'Utunzaji asili wa ngozi (krimu na mafuta ya msingi)'],
    ['Social Media Marketing for Business', 'Marketing sur les réseaux sociaux pour votre activité', 'Marketing nas redes sociais para negócios', 'التسويق عبر وسائل التواصل الاجتماعي للأعمال', 'Ìpolówó lórí ìkànnì àjọlò fún iṣẹ́ òwò', 'Tallace-tallace ta kafofin sada zumunta don kasuwanci', 'Ahịa na mgbasa ozi ọha maka azụmahịa', 'Masoko ya mitandao ya kijamii kwa biashara'],
    ['Savings, Spending & Budgeting Basics', 'Bases de l\'épargne, des dépenses et du budget', 'Noções de poupança, gastos e orçamento', 'أساسيات الادخار والإنفاق والميزانية', 'Ìpìlẹ̀ ìfowópamọ́, ìnáwó àti ìṣúná', 'Tushen ajiya, kashe kuɗi da kasafin kuɗi', 'Ntọala nchekwa ego, mmefu na atụmatụ mmefu ego', 'Misingi ya kuweka akiba, matumizi na bajeti'],
    ['Confidence Building and Self-Belief', 'Confiance en soi et estime de soi', 'Construir confiança e acreditar em si', 'بناء الثقة والإيمان بالنفس', 'Kíkọ́ ìgboyà àti ìgbàgbọ́ nínú ara ẹni', 'Gina ƙarfin gwiwa da yarda da kai', 'Iwulite obi ike na ikwere na onwe gị', 'Kujenga kujiamini na kujithamini'],
    /* v184 */
    ['Empowering women. Transforming Africa.', 'Autonomiser les femmes. Transformer l\'Afrique.', 'Capacitar mulheres. Transformar África.', 'تمكين المرأة. تحويل أفريقيا.', 'Fífún obìnrin lágbára. Yíyí Áfíríkà padà.', 'Ƙarfafa mata. Sauya Afirka.', 'Ịkwado ụmụ nwanyị. Ịgbanwe Africa.', 'Kuwawezesha wanawake. Kuibadilisha Afrika.'],
    ['50 languages', '50 langues', '50 línguas', '50 لغة', 'Èdè 50', 'Harsuna 50', 'Asụsụ 50', 'Lugha 50'],
    ['Tap on your preferred network', 'Touchez le réseau de votre choix', 'Toque na rede da sua preferência', 'اضغطي على الشبكة التي تفضّلينها', 'Tẹ Nẹtiwọọki tí o fẹ́', 'Taɓa cibiyar da kika fi so', 'Pịa netwọk ị họọrọ', 'Gusa mtandao unaoupenda'],
    ['Women certified', 'Femmes certifiées', 'Mulheres certificadas', 'نساء حاصلات على شهادة', 'Obìnrin tó gba ìwé-ẹ̀rí', 'Mata masu takardar shaida', 'Ụmụ nwanyị nwetara asambodo', 'Wanawake waliopata vyeti'],
    ['5 main', '5 principales', '5 principais', '5 رئيسية', 'Àkọ́kọ́ 5', 'Manyan 5', 'Isi 5', '5 kuu'],
    ['45 more African', '45 autres africaines', '45 outras africanas', '45 لغة أفريقية أخرى', 'Èdè Áfíríkà 45 míràn', 'Ƙarin harsunan Afirka 45', 'Asụsụ Africa 45 ọzọ', 'Lugha 45 zaidi za Kiafrika'],
    ['Two networks. One purpose. A place for you.', 'Deux réseaux. Un seul but. Une place pour vous.', 'Duas redes. Um propósito. Um lugar para si.', 'شبكتان. هدف واحد. مكان لكِ.', 'Nẹtiwọọki méjì. Èrò kan. Àyè kan fún ọ.', 'Cibiyoyi biyu. Manufa ɗaya. Wuri gare ki.', 'Netwọk abụọ. Otu ebumnuche. Ebe dịịrị gị.', 'Mitandao miwili. Lengo moja. Nafasi kwa ajili yako.'],
    ['Grow through learning at WODDI Institute and connect with a community for your stage of life. Join WODDI Nurture NextGen Network (WNNN) for young women aged 18 to 29, or WODDI Good Mother Network (WGMN) for women aged 30 and above. No woman is left out.', 'Grandissez en apprenant à WODDI Institute et rejoignez une communauté adaptée à votre étape de vie. Rejoignez WODDI Nurture NextGen Network (WNNN) pour les jeunes femmes de 18 à 29 ans, ou WODDI Good Mother Network (WGMN) pour les femmes de 30 ans et plus. Aucune femme n\'est laissée de côté.', 'Cresça a aprender na WODDI Institute e ligue-se a uma comunidade para a sua fase da vida. Junte-se à WODDI Nurture NextGen Network (WNNN) para jovens mulheres dos 18 aos 29 anos, ou à WODDI Good Mother Network (WGMN) para mulheres com 30 anos ou mais. Nenhuma mulher fica de fora.', 'انمي بالتعلّم في WODDI Institute وتواصلي مع مجتمع يناسب مرحلتك من الحياة. انضمي إلى WODDI Nurture NextGen Network (WNNN) للشابات من 18 إلى 29 عامًا، أو إلى WODDI Good Mother Network (WGMN) للنساء بعمر 30 عامًا فأكثر. لا تُستثنى أي امرأة.', 'Dàgbà nípa kíkẹ́kọ̀ọ́ ní WODDI Institute, kí o sì darapọ̀ mọ́ àwùjọ tó bá ìpele ayé rẹ mu. Darapọ̀ mọ́ WODDI Nurture NextGen Network (WNNN) fún ọ̀dọ́bìnrin ọmọ ọdún 18 sí 29, tàbí WODDI Good Mother Network (WGMN) fún obìnrin ọmọ ọdún 30 sókè. Kò sí obìnrin tí a fi sílẹ̀.', 'Ki girma ta hanyar koyo a WODDI Institute, ki kuma haɗu da al\'ummar da ta dace da matakin rayuwarki. Shiga WODDI Nurture NextGen Network (WNNN) don matasan mata masu shekaru 18 zuwa 29, ko WODDI Good Mother Network (WGMN) don mata masu shekaru 30 zuwa sama. Babu macen da aka bari a baya.', 'Too site n\'ịmụ ihe na WODDI Institute ma jikọọ na obodo dabara n\'oge ndụ gị. Banye na WODDI Nurture NextGen Network (WNNN) maka ụmụ agbọghọ dị afọ 18 ruo 29, ma ọ bụ WODDI Good Mother Network (WGMN) maka ụmụ nwanyị dị afọ 30 gaa n\'elu. Ọ dịghị nwanyị a hapụrụ.', 'Kua kupitia kujifunza katika WODDI Institute na ujiunge na jumuiya inayofaa hatua yako ya maisha. Jiunge na WODDI Nurture NextGen Network (WNNN) kwa wanawake vijana wa miaka 18 hadi 29, au WODDI Good Mother Network (WGMN) kwa wanawake wa miaka 30 na zaidi. Hakuna mwanamke anayeachwa nyuma.'],
    ['Learn on your device, at your own pace', 'Apprenez sur votre appareil, à votre rythme', 'Aprenda no seu dispositivo, ao seu ritmo', 'تعلّمي على جهازك، وفق وتيرتك', 'Kẹ́kọ̀ọ́ lórí ẹ̀rọ rẹ, bí ó ṣe yá ọ', 'Ki koya a na\'urarki, yadda kike so', 'Mụta ihe na ngwaọrụ gị, n\'ọsọ nke gị', 'Jifunze kwenye kifaa chako, kwa kasi yako'],
    ['Made for your device', 'Conçu pour votre appareil', 'Feito para o seu dispositivo', 'مصمّم لجهازك', 'A ṣe é fún ẹ̀rọ rẹ', 'An yi shi don na\'urarki', 'Emere ya maka ngwaọrụ gị', 'Imetengenezwa kwa ajili ya kifaa chako'],
    ['Rooted in purpose. Equipped for impact.', 'Ancrées dans un but. Préparées à agir.', 'Enraizadas no propósito. Preparadas para o impacto.', 'متجذّرات في الهدف. مؤهّلات للأثر.', 'A fìdí múlẹ̀ nínú èrò. A gbára dì fún ipa.', 'Tushe cikin manufa. Shiri don tasiri.', 'Gbanyere mkpọrọgwụ n\'ebumnuche. Akwadoro maka mmetụta.', 'Imara katika kusudi. Tayari kuleta matokeo.'],
    ['Four connected stages to discover who you are, shape your future, build practical skills and earn your certificate, with a community to grow alongside you.', 'Quatre étapes liées pour découvrir qui vous êtes, façonner votre avenir, acquérir des compétences pratiques et obtenir votre certificat, avec une communauté pour grandir à vos côtés.', 'Quatro etapas ligadas para descobrir quem é, moldar o seu futuro, desenvolver competências práticas e obter o seu certificado, com uma comunidade para crescer ao seu lado.', 'أربع مراحل مترابطة لتكتشفي من أنتِ، وتصنعي مستقبلك، وتبني مهارات عملية، وتحصلي على شهادتك، مع مجتمع ينمو إلى جانبك.', 'Ìpele mẹ́rin tó so pọ̀ láti mọ ẹni tí o jẹ́, láti ṣe àgbékalẹ̀ ọjọ́ iwájú rẹ, láti kọ́ ọgbọ́n tó wúlò, kí o sì gba ìwé-ẹ̀rí rẹ, pẹ̀lú àwùjọ tí yóò máa dàgbà pẹ̀lú rẹ.', 'Matakai huɗu masu alaƙa don gano ko wace ce ke, tsara makomarki, gina ƙwarewar aiki da samun takardar shaidarki, tare da al\'ummar da za ta girma tare da ke.', 'Ọkwa anọ jikọrọ ọnụ iji chọpụta onye ị bụ, kpụọ ọdịnihu gị, mụta nkà bara uru ma nweta asambodo gị, ya na obodo ga-eto n\'akụkụ gị.', 'Hatua nne zilizounganishwa ili kugundua wewe ni nani, kuunda mustakabali wako, kujenga ujuzi wa vitendo na kupata cheti chako, pamoja na jumuiya ya kukua nawe.'],
    ['More African Languages', 'Plus de langues africaines', 'Mais línguas africanas', 'المزيد من اللغات الأفريقية', 'Àwọn èdè Áfíríkà míràn', 'Ƙarin harsunan Afirka', 'Asụsụ Africa ndị ọzọ', 'Lugha zaidi za Kiafrika'],
    ['See how WODDI Institute works', 'Découvrez comment marche WODDI Institute', 'Veja como funciona a WODDI Institute', 'شاهدي كيف يعمل WODDI Institute', 'Wo bí WODDI Institute ṣe ń ṣiṣẹ́', 'Kalli yadda WODDI Institute take aiki', 'Hụ otu WODDI Institute si arụ ọrụ', 'Tazama jinsi WODDI Institute inavyofanya kazi'],
  ];
  var STAGE = { fr: 'ÉTAPE', pt: 'ETAPA', ar: 'المرحلة', yo: 'ÌPELE', ha: 'MATAKI', ig: 'ỌKWA', sw: 'HATUA' };
  var DICT = {};
  ROWS.forEach(function (r) { var m = {}; COLS.forEach(function (l, i) { m[l] = r[i + 1]; }); DICT[r[0].replace(/\s+/g, ' ')] = m; });

  function cur() { try { var l = (localStorage.getItem('woddi_lang') || 'en').slice(0, 2); return LANGS.some(function (x) { return x[0] === l; }) ? l : 'en'; } catch (e) { return 'en'; } }
  function tr(en, l) {
    l = l || cur(); if (l === 'en') return en;
    var m = DICT[String(en).replace(/\s+/g, ' ').trim()]; if (m && m[l]) return m[l];
    var s = /^STAGE (\d)$/.exec(en); if (s && STAGE[l]) return STAGE[l] + ' ' + s[1];
    return en;
  }
  window.WODDI_LP_I18N = { t: tr, lang: cur };

  function translate(root) {
    if (!root) return; var l = cur(), x = extra();
    if (x) xLoad(x); else XL = null;
    var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null), n;
    while ((n = w.nextNode())) {
      var raw = n.nodeValue; if (!raw || !raw.trim()) continue;
      if (n.parentElement && n.parentElement.closest('[data-i18n-skip],script,style')) continue;
      var canon = n.__i18nEn != null ? n.__i18nEn : raw.replace(/\s+/g, ' ').trim();
      var lead = (raw.match(/^\s*/) || [''])[0], trail = (raw.match(/\s*$/) || [''])[0];
      if (x) {
        var tx = XD[canon];
        if (!tx) { if (/[A-Za-z]{2}/.test(canon)) need[canon] = 1; tx = canon; }
        n.__i18nEn = canon; if (n.nodeValue !== lead + tx + trail) n.nodeValue = lead + tx + trail; continue;
      }
      if (l === 'en') { if (n.__i18nEn != null && n.nodeValue !== lead + canon + trail) n.nodeValue = lead + canon + trail; continue; }
      var t = tr(canon, l);
      if (t !== canon) { n.__i18nEn = canon; if (n.nodeValue !== lead + t + trail) n.nodeValue = lead + t + trail; }
    }
    if (x && Object.keys(need).length) xRequest();
  }

  var CSS = '' +
    '#landing .lp-langbar{border-bottom:1px solid var(--lp-line);background:linear-gradient(90deg,var(--lp-mag-soft),var(--lp-bg) 45%,var(--lp-lem-soft))}' +
    '#landing .lp-langbar .lp-wrap{display:flex;align-items:center;gap:16px;padding-block:10px}' +
    '#landing .lp-langlabel{display:flex;align-items:center;gap:10px;flex-shrink:0}' +
    '#landing .lp-langlabel .ic{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:var(--lp-mag);color:#fff;font-size:17px;box-shadow:0 8px 18px -8px rgba(212,0,106,.7)}' +
    '#landing .lp-langlabel b{display:block;font-size:14px;font-weight:800;line-height:1.2}' +
    '#landing .lp-langlabel small{display:block;font-size:12px;color:var(--lp-muted);font-weight:500}' +
    '#landing .lp-langs{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;scroll-snap-type:x proximity;padding:4px 2px;flex:1;' +
      '-webkit-mask-image:linear-gradient(90deg,#000 92%,transparent);mask-image:linear-gradient(90deg,#000 92%,transparent)}' +
    '#landing .lp-langs::-webkit-scrollbar{display:none}' +
    '#landing .lp-langrow{display:flex;align-items:center;gap:8px;flex:1;min-width:0}' +
    '#landing .lp-langrow .lp-lang.more{flex-shrink:0}' +
    '@media (max-width:760px){#landing .lp-langrow{flex-direction:column;align-items:stretch;gap:6px}#landing .lp-langrow .lp-lang.more{justify-content:center;min-height:42px;padding:4px 14px 4px 4px}' +
      '#landing .lp-langrow .lp-lang.more .cd{width:30px;height:30px;flex-shrink:0}#landing .lp-langrow .lp-lang.more .nm{flex-direction:row;align-items:baseline;gap:8px;white-space:nowrap}#landing .lp-langrow .lp-lang.more .nm small{font-size:11px}}' +
    '@media (min-width:761px){#landing .lp-langs{-webkit-mask-image:none;mask-image:none;flex:0 1 auto}#landing .lp-langrow{justify-content:flex-end}}' +
    '@media (min-width:761px){#landing .lp-langlabel small{display:none}#landing .lp-langbar .lp-wrap{gap:12px}#landing .lp-langs{gap:6px}#landing .lp-lang{padding-right:12px}}' +
    '@media (min-width:761px) and (max-width:1180px){#landing .lp-langlabel>span:not(.ic){display:none}#landing .lp-lang .nm small{display:none}}' +
    '#landing .lp-lang{scroll-snap-align:start;flex-shrink:0;display:flex;align-items:center;gap:9px;min-height:46px;padding:5px 14px 5px 5px;border-radius:999px;' +
      'border:1.5px solid var(--lp-line);background:var(--lp-surface);color:var(--lp-ink);cursor:pointer;font:inherit;transition:transform .18s,border-color .18s,box-shadow .18s,background .18s}' +
    '#landing .lp-lang:hover{transform:translateY(-2px);border-color:var(--lp-mag);box-shadow:0 10px 22px -14px rgba(212,0,106,.6)}' +
    '#landing .lp-lang .cd{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:11.5px;font-weight:800;letter-spacing:.02em;' +
      'background:var(--lp-mag-soft);color:var(--lp-mag);transition:background .18s,color .18s}' +
    '#landing .lp-lang .nm{display:flex;flex-direction:column;align-items:flex-start;line-height:1.15;text-align:start}' +
    '#landing .lp-lang .nm b{font-size:14px;font-weight:700}' +
    '#landing .lp-lang .nm small{font-size:11.5px;color:var(--lp-muted);font-weight:500}' +
    '#landing .lp-lang[aria-checked="true"]{background:var(--lp-mag);border-color:var(--lp-mag);color:#fff;box-shadow:0 12px 24px -12px rgba(212,0,106,.75)}' +
    '#landing .lp-lang[aria-checked="true"] .cd{background:#fff;color:var(--lp-mag)}' +
    '#landing .lp-lang[aria-checked="true"] .nm small{color:rgba(255,255,255,.85)}' +
    '#landing .lp-lang .ok{display:none;font-size:13px;margin-left:2px}#landing .lp-lang[aria-checked="true"] .ok{display:inline-block;animation:lpTick .35s cubic-bezier(.2,1.6,.4,1)}' +
    '@keyframes lpTick{from{transform:scale(0)}to{transform:none}}' +
    '#landing .lp-lang.pulse{animation:lpPulse .5s ease}@keyframes lpPulse{40%{transform:scale(.94)}100%{transform:none}}' +
    '#landing .lp-footlangs{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px}' +
    '#landing .lp-footlangs button{border:1px solid rgba(255,255,255,.22);background:transparent;color:inherit;font:inherit;font-size:12.5px;font-weight:600;padding:6px 11px;border-radius:999px;cursor:pointer;opacity:.85}' +
    '#landing .lp-footlangs button[aria-pressed="true"]{background:#fff;color:#A80054;opacity:1}' +
    '@media (max-width:760px){#landing .lp-langbar .lp-wrap{flex-direction:column;align-items:stretch;gap:8px;padding-block:10px 8px}#landing .lp-langlabel small{display:none}#landing .lp-langlabel .ic{width:30px;height:30px;font-size:14px;border-radius:9px}}' +
    '#woddi-landing-lang{display:none!important}' +
    '#landing .lp-langbar{position:relative;z-index:39}' +
    '#landing .lp-lang.more{border-style:dashed}#landing .lp-lang.more .cd{background:var(--lp-lem-soft);color:var(--lp-lem-deep)}' +
    '#landing .lp-xnote{display:flex;align-items:center;justify-content:space-between;gap:4px 12px;flex-wrap:wrap;font-size:12.5px;color:var(--lp-muted);padding-top:0;padding-bottom:8px}' +
    '#landing .lp-xnote b{color:var(--lp-ink)}#landing .lp-xnote button{border:0;background:none;color:var(--lp-mag);font:inherit;font-weight:700;cursor:pointer;padding:4px 0;text-decoration:underline}' +
    '#landing .lp-xprog{position:absolute;left:0;right:0;bottom:0;height:3px;background:var(--lp-line);overflow:hidden;transition:opacity .4s;pointer-events:none}#landing .lp-xprog i{display:block;height:100%;width:0;background:var(--lp-mag);transition:width .3s}' +
    '#landing .lp-xdd{position:absolute;right:max(16px,calc((100% - 1200px)/2));top:calc(100% - 4px);z-index:70;width:min(560px,calc(100% - 32px));max-height:min(70vh,560px);display:flex;flex-direction:column;' +
      'background:var(--lp-surface);color:var(--lp-ink);border:1px solid var(--lp-line);border-radius:20px;box-shadow:0 30px 70px -30px rgba(40,5,25,.55);overflow:hidden}' +
    '#landing .lp-xdd .hd{display:flex;align-items:center;gap:10px;padding:14px 14px 10px}#landing .lp-xdd .hd b{flex:1;font-size:15px}' +
    '#landing .lp-xdd .x{width:36px;height:36px;border-radius:50%;border:0;background:var(--lp-soft);color:var(--lp-ink);cursor:pointer}' +
    '#landing .lp-xdd input{margin:0 14px 8px;min-height:44px;border:1.5px solid var(--lp-line);border-radius:12px;padding:0 12px;font:inherit;font-size:15px;background:var(--lp-soft);color:var(--lp-ink)}' +
    '#landing .lp-xdd .ls{overflow:auto;padding:4px 10px 10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px}' +
    '#landing .lp-xdd .ls button{display:flex;flex-direction:column;align-items:flex-start;text-align:left;gap:1px;min-height:48px;padding:8px 12px;border-radius:12px;border:1px solid var(--lp-line);background:var(--lp-surface);color:var(--lp-ink);font:inherit;cursor:pointer}' +
    '#landing .lp-xdd .ls button:hover,#landing .lp-xdd .ls button[aria-current="true"]{border-color:var(--lp-mag);background:var(--lp-mag-soft)}' +
    '#landing .lp-xdd .ls b{font-size:14px;font-weight:700}#landing .lp-xdd .ls small{font-size:12px;color:var(--lp-muted)}' +
    '#landing .lp-xdd .ft{font-size:12px;color:var(--lp-muted);padding:8px 14px 12px;border-top:1px solid var(--lp-line)}' +
    '@media (max-width:760px){#landing .lp-xdd{position:fixed;left:0;right:0;bottom:0;top:auto;width:auto;max-height:80vh;border-radius:22px 22px 0 0;padding-bottom:env(safe-area-inset-bottom,0px);z-index:90}}';

  function ribbon() {
    var l = cur(), x = extra(); if (x) l = '';
    var xm = x ? MORE_MAP[x] : null;
    return '<div class="lp-langbar"><div class="lp-wrap">' +
      '<div class="lp-langlabel"><span class="ic"><i class="fa-solid fa-language" aria-hidden="true"></i></span><span><b>Learn in your language</b><small>Tap a language. The whole page changes.</small></span></div>' +
      '<div class="lp-langrow" data-i18n-skip><div class="lp-langs" role="radiogroup" aria-label="Language">' + LANGS.filter(function (x) { return MAIN.indexOf(x[0]) >= 0 || x[0] === l; }).map(function (x) {
        return '<button type="button" class="lp-lang" role="radio" aria-checked="' + (x[0] === l) + '" data-lang="' + x[0] + '" lang="' + x[0] + '"' + (x[0] === 'ar' ? ' dir="rtl"' : '') + '>' +
          '<span class="cd">' + x[3] + '</span><span class="nm"><b>' + x[1] + '</b><small>' + x[2] + '</small></span><i class="fa-solid fa-circle-check ok" aria-hidden="true"></i></button>'; }).join('') +
      (xm ? '<button type="button" class="lp-lang" role="radio" aria-checked="true" data-xlang="' + xm[0] + '" lang="' + xm[0] + '"><span class="cd">' + xm[0].slice(0, 3).toUpperCase() + '</span><span class="nm"><b>' + xm[1] + '</b><small>' + xm[2] + '</small></span><i class="fa-solid fa-circle-check ok" aria-hidden="true"></i></button>' : '') +
      '</div><button type="button" class="lp-lang more" data-xmore aria-haspopup="dialog" aria-expanded="false"><span class="cd"><i class="fa-solid fa-earth-africa"></i></span><span class="nm"><b>' + tr('More African Languages') + '</b><small>' + (MORE.length + 3) + ' ' + tr('African languages') + '</small></span><i class="fa-solid fa-chevron-down" aria-hidden="true" style="font-size:11px;margin-left:2px"></i></button>' +
      '</div></div></div>' +
      (xm ? '<div class="lp-wrap lp-xnote"><span><i class="fa-solid fa-wand-magic-sparkles"></i> <span>This page was translated into</span> <b data-i18n-skip>' + xm[1] + '</b> <span>by AI. Some words may not be perfect.</span></span><span class="lp-xprog" style="opacity:0"><i id="lp-xbar"></i></span><span id="lp-xwarn" hidden>Part of the page is still in English. Please check your connection.</span><button type="button" data-lang="en">Back to English</button></div>' : '') +
      '</div>';
  }
  function dropdown() {
    var x = extra();
    return '<div class="lp-xdd" role="dialog" aria-modal="false" aria-label="' + tr('More African Languages') + '" data-i18n-skip><div class="hd"><b>' + tr('More African Languages') + '</b><button type="button" class="x" data-xclose aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>' +
      '<input type="search" id="lp-xq" placeholder="' + tr('Search: Zulu, Amharic, Twi…') + '" aria-label="' + tr('Choose your language') + '" autocomplete="off">' +
      '<div class="ls" id="lp-xls">' + ddList().map(function (m) { var on = m.full ? (!x && cur() === m.code) : m.code === x; return '<button type="button" ' + (m.full ? 'data-lang' : 'data-xlang') + '="' + m.code + '" lang="' + m.code + '"' + (on ? ' aria-current="true"' : '') + ' data-k="' + (m.nat + ' ' + m.en).toLowerCase() + '"><b>' + m.en + '</b>' + (m.nat !== m.en ? '<small>' + m.nat + '</small>' : '') + '</button>'; }).join('') + '</div>' +
      '<div class="ft"><i class="fa-solid fa-wand-magic-sparkles"></i> ' + tr('The first time a language is chosen, AI translates the page in a few seconds. After that it opens instantly for everyone.') + '</div></div>';
  }
  function openDropdown(land) {
    var bar = land.querySelector('.lp-langbar'); if (!bar) return;
    var old = bar.querySelector('.lp-xdd'); if (old) { old.remove(); setExp(land, false); return; }
    var t = document.createElement('div'); t.innerHTML = dropdown(); bar.appendChild(t.firstChild); setExp(land, true);
    var q = bar.querySelector('#lp-xq'); if (q && window.innerWidth > 760) q.focus();
    q.addEventListener('input', function () { var v = q.value.trim().toLowerCase(); [].forEach.call(bar.querySelectorAll('#lp-xls button'), function (b) { b.hidden = v && b.getAttribute('data-k').indexOf(v) < 0; }); });
  }
  function closeDropdown(land) { var d = land && land.querySelector('.lp-xdd'); if (d) d.remove(); setExp(land, false); }
  function setExp(land, on) { [].forEach.call(land.querySelectorAll('[data-xmore]'), function (b) { b.setAttribute('aria-expanded', String(on)); }); }
  function pickExtra(code) {
    var land = document.getElementById('landing'), y = window.scrollY;
    closeDropdown(land);
    if (cur() !== 'en') { if (typeof window.switchLanguage === 'function') window.switchLanguage('en'); else { try { localStorage.setItem('woddi_lang', 'en'); } catch (e) {} } }
    try { localStorage.setItem('woddi_lang_extra', code); } catch (e) {}
    xDone = 0; xTotal = 0;
    document.documentElement.setAttribute('lang', code); document.documentElement.setAttribute('dir', 'ltr');
    setTimeout(function () { var L = document.getElementById('landing'); if (L) L.__lpScrolled = false; mount(true); translate(L); window.scrollTo(0, y); }, 160);
  }
  function footLangs() {
    var l = extra() ? '' : cur(), xm = extra() ? MORE_MAP[extra()] : null;
    return '<div class="lp-footlangs" data-i18n-skip role="group" aria-label="Language">' + LANGS.filter(function (x) { return MAIN.indexOf(x[0]) >= 0 || x[0] === l; }).map(function (x) {
      return '<button type="button" data-lang="' + x[0] + '" aria-pressed="' + (x[0] === l) + '" lang="' + x[0] + '">' + x[1] + '</button>'; }).join('') +
      (xm ? '<button type="button" aria-pressed="true" data-xlang="' + xm[0] + '">' + xm[1] + '</button>' : '') + '<button type="button" data-xmore-foot><i class="fa-solid fa-earth-africa"></i> ' + tr('More African Languages') + '</button></div>';
  }
  function pick(code, btn) {
    if (btn) { btn.classList.remove('pulse'); void btn.offsetWidth; btn.classList.add('pulse'); }
    try { localStorage.removeItem('woddi_lang_extra'); } catch (e) {} XL = null;
    var y = window.scrollY;
    if (typeof window.switchLanguage === 'function') window.switchLanguage(code);
    else { try { localStorage.setItem('woddi_lang', code); } catch (e) {} }
    setTimeout(function () { var L = document.getElementById('landing'); if (L) L.__lpScrolled = false; mount(true); translate(L); window.scrollTo(0, y); }, 140);
  }

  function mount(force) {
    var land = document.getElementById('landing'); if (!land) return;
    if (!document.getElementById('lp-lang-css')) { var s = document.createElement('style'); s.id = 'lp-lang-css'; s.textContent = CSS; document.head.appendChild(s); }
    var nav = land.querySelector('.lp-nav');
    var bar = land.querySelector('.lp-langbar');
    if (force && bar) { bar.remove(); bar = null; }
    if (nav && !bar) { var t = document.createElement('div'); t.innerHTML = ribbon(); nav.insertAdjacentElement('afterend', t.firstChild); }
    var fp = land.querySelector('.lp-foot .grid > div:first-child');
    var fl = land.querySelector('.lp-footlangs');
    if (force && fl) { fl.remove(); fl = null; }
    if (fp && !fl) { var t2 = document.createElement('div'); t2.innerHTML = footLangs(); fp.appendChild(t2.firstChild); }
    var old = document.getElementById('woddi-landing-lang'); if (old && old.parentNode) old.parentNode.style.display = 'none';
    if (!land.__lpLangWired) {
      land.__lpLangWired = true;
      land.addEventListener('click', function (e) {
        if (e.target.closest('[data-xmore]')) { openDropdown(land); return; }
        if (e.target.closest('[data-xmore-foot]')) { window.scrollTo({ top: 0, behavior: 'smooth' }); setTimeout(function () { openDropdown(land); }, 350); return; }
        if (e.target.closest('[data-xclose]')) { closeDropdown(land); return; }
        var xb = e.target.closest('[data-xlang]'); if (xb && land.contains(xb)) { pickExtra(xb.getAttribute('data-xlang')); return; }
        var b = e.target.closest('.lp-lang,[data-lang]'); if (!b || !land.contains(b) || !b.closest('.lp-langs,.lp-footlangs,.lp-xnote,.lp-xdd')) return; if (b.closest('.lp-xdd')) closeDropdown(land); pick(b.getAttribute('data-lang'), b.closest('.lp-xdd') ? null : b);
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDropdown(document.getElementById('landing')); });
      document.addEventListener('click', function (e) { var L = document.getElementById('landing'); if (L && L.querySelector('.lp-xdd') && !e.target.closest('.lp-xdd,[data-xmore],[data-xmore-foot]')) closeDropdown(L); });
      land.addEventListener('keydown', function (e) {
        var b = e.target.closest('.lp-lang'); if (!b || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
        var all = [].slice.call(land.querySelectorAll('.lp-lang')), i = all.indexOf(b) + (e.key === 'ArrowRight' ? 1 : -1);
        if (all[i]) { all[i].focus(); e.preventDefault(); }
      });
    }
    if (extra()) { document.documentElement.setAttribute('lang', extra()); document.documentElement.setAttribute('dir', 'ltr'); }
    var act = land.querySelector('.lp-lang[aria-checked="true"]'); if (act && !land.__lpScrolled) { land.__lpScrolled = true; var p = act.parentNode, pr = p.getBoundingClientRect(), ar = act.getBoundingClientRect(); p.scrollLeft += (ar.left - pr.left) - (pr.width - ar.width) / 2; }
    translate(land);
  }

  var timer = null;
  function schedule() { clearTimeout(timer); timer = setTimeout(function () { mount(false); }, 140); }
  function start() {
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
    window.addEventListener('woddi:languagechange', function () { setTimeout(function () { var L = document.getElementById('landing'); if (L) L.__lpScrolled = false; mount(true); }, 160); });
    schedule();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
