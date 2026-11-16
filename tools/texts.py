# Words for the guided tour inside WDOS: the greeting, then one line per screen. Each line is shown as a caption
# and spoken in the member's language. Keep them short: one screen, one action, the exact button to tap.

GREET = {
 'welcome': {'en': 'Welcome to WDOS.', 'fr': 'Bienvenue dans WDOS.', 'pt': 'Bem-vinda ao WDOS.', 'sw': 'Karibu WDOS.', 'ar': 'مرحبًا بكِ في WDOS.'},
 'hello': {'en': 'Hello,', 'fr': 'Bonjour,', 'pt': 'Olá,', 'sw': 'Habari,', 'ar': 'مرحبًا،'},
 'glad': {
  'en': 'We are glad you are ready to serve and make a generational impact in your community, Africa and the diaspora. This short guide walks you through your own dashboard, step by step, and shows you exactly what to tap.',
  'fr': "Nous sommes heureux que vous soyez prête à servir et à avoir un impact sur plusieurs générations dans votre communauté, en Afrique et dans la diaspora. Ce court guide parcourt votre propre tableau de bord, étape par étape, et vous montre exactement où appuyer.",
  'pt': 'Estamos felizes por estar pronta para servir e causar um impacto que atravessa gerações na sua comunidade, em África e na diáspora. Este guia curto percorre o seu próprio painel, passo a passo, e mostra exatamente onde tocar.',
  'sw': 'Tunafurahi kwamba uko tayari kutumikia na kuleta athari ya vizazi katika jamii yako, Afrika na diaspora. Mwongozo huu mfupi unapitia dashibodi yako mwenyewe, hatua kwa hatua, na kukuonyesha mahali hasa pa kubonyeza.',
  'ar': 'يسعدنا أنكِ مستعدة للخدمة وصنع أثر يمتد عبر الأجيال في مجتمعك وفي أفريقيا والمهجر. يأخذكِ هذا الدليل القصير في جولة على لوحتك أنتِ، خطوة بخطوة، ويريكِ أين تضغطين بالضبط.',
 },
}

# (id, line in en, fr, pt, sw, ar)
LEADER = [
 ('dash', {
  'en': 'This is your dashboard. It shows your area at a glance: your members, your chapters, and what needs you today.',
  'fr': "Voici votre tableau de bord. Il montre votre zone d'un coup d'œil : vos membres, vos chapitres, et ce qui vous attend aujourd'hui.",
  'pt': 'Este é o seu painel. Mostra a sua área num relance: os seus membros, os seus capítulos e o que precisa de si hoje.',
  'sw': 'Hii ni dashibodi yako. Inaonyesha eneo lako kwa haraka: wanachama wako, matawi yako, na kinachokuhitaji leo.',
  'ar': 'هذه لوحتك. تُريكِ منطقتك بنظرة واحدة: عضواتك، وفروعك، وما ينتظرك اليوم.'}),
 ('more', {
  'en': 'To move around, tap More, at the bottom right of the screen. It opens the full menu.',
  'fr': "Pour vous déplacer, appuyez sur Plus, en bas à droite de l'écran. Le menu complet s'ouvre.",
  'pt': 'Para navegar, toque em Mais, no canto inferior direito do ecrã. Abre o menu completo.',
  'sw': 'Ili kuzunguka, bonyeza Zaidi, chini upande wa kulia wa skrini. Inafungua menyu kamili.',
  'ar': 'للتنقل، اضغطي على المزيد في أسفل الشاشة. تُفتح القائمة كاملة.'}),
 ('menu', {
  'en': 'The menu lists every page. Tap Tasks to see the work given to you.',
  'fr': 'Le menu liste toutes les pages. Appuyez sur Tâches pour voir le travail qui vous est confié.',
  'pt': 'O menu lista todas as páginas. Toque em Tarefas para ver o trabalho que lhe foi dado.',
  'sw': 'Menyu inaorodhesha kurasa zote. Bonyeza Kazi kuona kazi uliyopewa.',
  'ar': 'تعرض القائمة كل الصفحات. اضغطي على المهام لتري العمل المُسند إليكِ.'}),
 ('brief', {
  'en': 'Open a task and read what is asked. Tap View to open the document attached to it.',
  'fr': 'Ouvrez une tâche et lisez ce qui est demandé. Appuyez sur Voir pour ouvrir le document joint.',
  'pt': 'Abra uma tarefa e leia o que é pedido. Toque em Ver para abrir o documento anexado.',
  'sw': 'Fungua kazi na usome kinachoombwa. Bonyeza Tazama kufungua hati iliyoambatishwa.',
  'ar': 'افتحي المهمة واقرئي المطلوب. اضغطي على عرض لفتح المستند المرفق.'}),
 ('work', {
  'en': 'When the work is done, tap Upload the finished work, and choose the file from your phone.',
  'fr': 'Quand le travail est fait, appuyez sur Téléverser le travail terminé, et choisissez le fichier sur votre téléphone.',
  'pt': 'Quando o trabalho estiver feito, toque em Carregar o trabalho concluído e escolha o ficheiro no seu telemóvel.',
  'sw': 'Kazi ikikamilika, bonyeza Pakia kazi iliyokamilika, kisha chagua faili kwenye simu yako.',
  'ar': 'عند انتهاء العمل، اضغطي على رفع العمل المنجز، واختاري الملف من هاتفك.'}),
 ('tsubmit', {
  'en': 'Then tap Submit for review. The person who gave you the task is told at once.',
  'fr': 'Puis appuyez sur Soumettre pour examen. La personne qui vous a confié la tâche est prévenue aussitôt.',
  'pt': 'Depois toque em Enviar para revisão. A pessoa que lhe deu a tarefa é avisada de imediato.',
  'sw': 'Kisha bonyeza Wasilisha kwa mapitio. Aliyekupa kazi anaarifiwa mara moja.',
  'ar': 'ثم اضغطي على إرسال للمراجعة. تُبلَّغ من أسندت إليكِ المهمة فورًا.'}),
 ('rtab', {
  'en': 'For your monthly report, tap Reporting, at the bottom of the screen.',
  'fr': "Pour votre rapport mensuel, appuyez sur Rapports, en bas de l'écran.",
  'pt': 'Para o seu relatório mensal, toque em Relatórios, em baixo no ecrã.',
  'sw': 'Kwa ripoti yako ya mwezi, bonyeza Ripoti, chini ya skrini.',
  'ar': 'لتقريرك الشهري، اضغطي على التقارير في أسفل الشاشة.'}),
 ('rdoc', {
  'en': 'Fill in the report. Then tap Upload the report document, and choose your PDF or Word file.',
  'fr': 'Remplissez le rapport. Puis appuyez sur Téléverser le document du rapport, et choisissez votre fichier PDF ou Word.',
  'pt': 'Preencha o relatório. Depois toque em Carregar o documento do relatório e escolha o seu ficheiro PDF ou Word.',
  'sw': 'Jaza ripoti. Kisha bonyeza Pakia hati ya ripoti, na uchague faili yako ya PDF au Word.',
  'ar': 'املئي التقرير. ثم اضغطي على رفع مستند التقرير، واختاري ملف PDF أو Word.'}),
 ('rsubmit', {
  'en': 'Tap Submit report. WODDI headquarters is told by notification and email.',
  'fr': 'Appuyez sur Soumettre le rapport. Le siège de WODDI est prévenu par notification et par email.',
  'pt': 'Toque em Enviar relatório. A sede da WODDI é avisada por notificação e por email.',
  'sw': 'Bonyeza Wasilisha ripoti. Makao makuu ya WODDI yanaarifiwa kwa taarifa na barua pepe.',
  'ar': 'اضغطي على تقديم التقرير. يُبلَّغ مقر WODDI بإشعار وبريد إلكتروني.'}),
 ('dmnew', {
  'en': 'To write to someone, open Messages, and tap New message.',
  'fr': "Pour écrire à quelqu'un, ouvrez Messages, et appuyez sur Nouveau message.",
  'pt': 'Para escrever a alguém, abra Mensagens e toque em Nova mensagem.',
  'sw': 'Kumwandikia mtu, fungua Ujumbe, kisha bonyeza Ujumbe mpya.',
  'ar': 'للكتابة إلى أحد، افتحي الرسائل واضغطي على رسالة جديدة.'}),
 ('dmto', {
  'en': 'Choose who receives it: one person, or a group by network, leadership level or region. Write, then tap Send. You have thirty seconds to undo.',
  'fr': 'Choisissez qui la reçoit : une personne, ou un groupe par réseau, niveau de direction ou région. Écrivez, puis appuyez sur Envoyer. Vous avez trente secondes pour annuler.',
  'pt': 'Escolha quem a recebe: uma pessoa, ou um grupo por rede, nível de liderança ou região. Escreva e toque em Enviar. Tem trinta segundos para anular.',
  'sw': 'Chagua atakayeupokea: mtu mmoja, au kikundi kwa mtandao, ngazi ya uongozi au eneo. Andika, kisha bonyeza Tuma. Una sekunde thelathini kutengua.',
  'ar': 'اختاري من يستلمها: شخصًا واحدًا، أو مجموعة حسب الشبكة أو مستوى القيادة أو المنطقة. اكتبي ثم اضغطي على إرسال. لديكِ ثلاثون ثانية للتراجع.'}),
 ('mrask', {
  'en': 'To hold a video meeting, open Video meetings in the menu, and tap Ask for a video meeting. Fill in the form, and send it to headquarters.',
  'fr': 'Pour tenir une réunion vidéo, ouvrez Réunions vidéo dans le menu, et appuyez sur Demander une réunion vidéo. Remplissez le formulaire et envoyez-le au siège.',
  'pt': 'Para fazer uma reunião por vídeo, abra Reuniões por vídeo no menu e toque em Pedir uma reunião por vídeo. Preencha o formulário e envie-o à sede.',
  'sw': 'Kufanya mkutano wa video, fungua Mikutano ya video kwenye menyu, kisha bonyeza Omba mkutano wa video. Jaza fomu na uitume makao makuu.',
  'ar': 'لعقد اجتماع بالفيديو، افتحي الاجتماعات بالفيديو من القائمة، واضغطي على طلب اجتماع بالفيديو. املئي النموذج وأرسليه إلى المقر.'}),
 ('mrflier', {
  'en': 'You can add a meeting flier to your request: tap Upload the meeting flier, and choose a picture or a PDF. Everyone invited sees it on the meeting page.',
  'fr': "Vous pouvez joindre une affiche à votre demande : appuyez sur Téléverser l'affiche de la réunion, et choisissez une image ou un PDF. Toutes les personnes invitées la voient sur la page de la réunion.",
  'pt': 'Pode juntar um cartaz ao seu pedido: toque em Carregar o cartaz da reunião e escolha uma imagem ou um PDF. Todas as pessoas convidadas o veem na página da reunião.',
  'sw': 'Unaweza kuongeza bango la mkutano kwenye ombi lako: bonyeza Pakia bango la mkutano, kisha chagua picha au PDF. Kila aliyealikwa analiona kwenye ukurasa wa mkutano.',
  'ar': 'يمكنكِ إرفاق ملصق للاجتماع بطلبك: اضغطي على رفع ملصق الاجتماع، واختاري صورة أو ملف PDF. يراه كل المدعوّين في صفحة الاجتماع.'}),
 ('mrjoin', {
  'en': 'As soon as headquarters approves, the meeting is open. Tap Join the video meeting. The call opens inside WDOS. Allow your camera and microphone.',
  'fr': "Dès que le siège approuve, la réunion est ouverte. Appuyez sur Rejoindre la réunion vidéo. L'appel s'ouvre dans WDOS. Autorisez la caméra et le micro.",
  'pt': 'Assim que a sede aprova, a reunião fica aberta. Toque em Entrar na reunião por vídeo. A chamada abre dentro do WDOS. Permita a câmara e o microfone.',
  'sw': 'Mara makao makuu yanapoidhinisha, mkutano unafunguka. Bonyeza Jiunge na mkutano wa video. Simu inafunguka ndani ya WDOS. Ruhusu kamera na kipaza sauti.',
  'ar': 'بمجرد موافقة المقر يُفتح الاجتماع. اضغطي على الانضمام إلى الاجتماع بالفيديو. تُفتح المكالمة داخل WDOS. اسمحي بالكاميرا والميكروفون.'}),
 ('mrtell', {
  'en': 'Tell your people from the same page. Choose people in WDOS, and tap Notify the people I chose. They are told in the app, on the phone and by email.',
  'fr': "Prévenez vos membres depuis la même page. Choisissez des personnes dans WDOS, et appuyez sur Prévenir les personnes choisies. Elles sont prévenues dans l'application, sur le téléphone et par email.",
  'pt': 'Avise as suas pessoas na mesma página. Escolha pessoas no WDOS e toque em Avisar as pessoas escolhidas. São avisadas na aplicação, no telemóvel e por email.',
  'sw': 'Waarifu watu wako kutoka ukurasa huo huo. Chagua watu katika WDOS, kisha bonyeza Arifu watu niliowachagua. Wanaarifiwa kwenye programu, kwenye simu na kwa barua pepe.',
  'ar': 'أبلغي فريقك من الصفحة نفسها. اختاري أشخاصًا في WDOS واضغطي على إبلاغ الأشخاص الذين اخترتهم. يُبلَّغون في التطبيق وعلى الهاتف وبالبريد الإلكتروني.'}),
 ('mrcopy', {
  'en': 'Or tap Copy meeting link, and paste it in WhatsApp or any chat.',
  'fr': 'Ou appuyez sur Copier le lien de la réunion, et collez-le dans WhatsApp ou une autre discussion.',
  'pt': 'Ou toque em Copiar o link da reunião e cole-o no WhatsApp ou noutra conversa.',
  'sw': 'Au bonyeza Nakili kiungo cha mkutano, kisha ukibandike WhatsApp au mazungumzo mengine.',
  'ar': 'أو اضغطي على نسخ رابط الاجتماع، والصقيه في واتساب أو أي محادثة.'}),
 ('mrminutes', {
  'en': 'After the meeting, write the minutes here. Tick the leaders who attended, add the minutes document or a recording, and send. The leaders and headquarters receive them in the app and by email.',
  'fr': "Après la réunion, écrivez le compte rendu ici. Cochez les responsables présentes, ajoutez le document ou un enregistrement, et envoyez. Les responsables et le siège le reçoivent dans l'application et par email.",
  'pt': 'Depois da reunião, escreva a ata aqui. Marque as líderes que participaram, junte o documento ou uma gravação e envie. As líderes e a sede recebem-na na aplicação e por email.',
  'sw': 'Baada ya mkutano, andika kumbukumbu hapa. Weka alama kwa viongozi waliohudhuria, ongeza hati au rekodi, kisha tuma. Viongozi na makao makuu wanazipokea kwenye programu na kwa barua pepe.',
  'ar': 'بعد الاجتماع، اكتبي المحضر هنا. ضعي علامة على القائدات الحاضرات، وأضيفي المستند أو تسجيلًا، ثم أرسلي. تستلمه القائدات والمقر في التطبيق وبالبريد الإلكتروني.'}),
 ('bell', {
  'en': 'The bell at the top shows what is new. Tap it to see your notifications.',
  'fr': 'La cloche en haut montre les nouveautés. Appuyez dessus pour voir vos notifications.',
  'pt': 'O sino no topo mostra as novidades. Toque nele para ver as suas notificações.',
  'sw': 'Kengele iliyo juu inaonyesha mapya. Ibonyeze kuona taarifa zako.',
  'ar': 'الجرس في الأعلى يُريكِ الجديد. اضغطي عليه لتري إشعاراتك.'}),
 ('lang', {
  'en': 'Change your language here, at the top of every page.',
  'fr': 'Changez de langue ici, en haut de chaque page.',
  'pt': 'Mude de língua aqui, no topo de cada página.',
  'sw': 'Badilisha lugha yako hapa, juu ya kila ukurasa.',
  'ar': 'غيّري لغتك من هنا، في أعلى كل صفحة.'}),
 ('listen', {
  'en': 'Tap Listen to hear the page read aloud.',
  'fr': 'Appuyez sur Écouter pour entendre la page lue à voix haute.',
  'pt': 'Toque em Ouvir para ouvir a página em voz alta.',
  'sw': 'Bonyeza Sikiliza kusikia ukurasa ukisomwa kwa sauti.',
  'ar': 'اضغطي على استماع لتسمعي الصفحة بصوت عالٍ.'}),
 ('end', {
  'en': 'You are ready. You can open this guide again from the menu, under How to use WDOS. No woman is left out.',
  'fr': 'Vous êtes prête. Vous pouvez rouvrir ce guide depuis le menu, sous Comment utiliser WDOS. Aucune femme n\'est laissée de côté.',
  'pt': 'Está pronta. Pode abrir este guia de novo no menu, em Como usar o WDOS. Nenhuma mulher fica de fora.',
  'sw': 'Uko tayari. Unaweza kufungua mwongozo huu tena kwenye menyu, chini ya Jinsi ya kutumia WDOS. Hakuna mwanamke anayeachwa nyuma.',
  'ar': 'أنتِ جاهزة. يمكنكِ فتح هذا الدليل مرة أخرى من القائمة، تحت كيفية استخدام WDOS. لا تُترك أي امرأة خارج الركب.'}),
]

ENVOY = [
 ('dash', {
  'en': 'This is your WODDI Envoy dashboard. Everything you need is on this one page.',
  'fr': 'Voici votre tableau de bord d\'Envoyée WODDI. Tout ce dont vous avez besoin est sur cette page.',
  'pt': 'Este é o seu painel de Enviada WODDI. Tudo o que precisa está nesta página.',
  'sw': 'Hii ni dashibodi yako ya Balozi wa WODDI. Kila unachohitaji kiko kwenye ukurasa huu mmoja.',
  'ar': 'هذه لوحتك كسفيرة WODDI. كل ما تحتاجينه موجود في هذه الصفحة.'}),
 ('copy', {
  'en': 'This is your own referral link. Tap Copy link, then paste it in WhatsApp, a text or an email.',
  'fr': 'Voici votre propre lien de parrainage. Appuyez sur Copier le lien, puis collez-le dans WhatsApp, un SMS ou un email.',
  'pt': 'Este é o seu link de convite. Toque em Copiar link e cole-o no WhatsApp, numa mensagem ou num email.',
  'sw': 'Hiki ni kiungo chako cha rufaa. Bonyeza Nakili kiungo, kisha ukibandike WhatsApp, ujumbe mfupi au barua pepe.',
  'ar': 'هذا رابط الإحالة الخاص بكِ. اضغطي على نسخ الرابط ثم الصقيه في واتساب أو رسالة نصية أو بريد إلكتروني.'}),
 ('wa', {
  'en': 'Or tap Send on WhatsApp to share it straight away with your groups.',
  'fr': 'Ou appuyez sur Envoyer sur WhatsApp pour le partager tout de suite avec vos groupes.',
  'pt': 'Ou toque em Enviar no WhatsApp para o partilhar logo com os seus grupos.',
  'sw': 'Au bonyeza Tuma kwa WhatsApp kukishiriki moja kwa moja na vikundi vyako.',
  'ar': 'أو اضغطي على إرسال عبر واتساب لمشاركته فورًا مع مجموعاتك.'}),
 ('people', {
  'en': 'Everyone who joins through your link appears here, with how far she is in her three days.',
  'fr': 'Chaque personne qui rejoint par votre lien apparaît ici, avec son avancée dans ses trois jours.',
  'pt': 'Cada pessoa que entra pelo seu link aparece aqui, com o progresso nos seus três dias.',
  'sw': 'Kila anayejiunga kupitia kiungo chako anaonekana hapa, pamoja na alipofika katika siku zake tatu.',
  'ar': 'تظهر هنا كل من تنضم عبر رابطك، ومدى تقدّمها في أيامها الثلاثة.'}),
 ('msg', {
  'en': 'Tap Message under her name to encourage her. She is told in the app, on her phone and by email.',
  'fr': "Appuyez sur Message sous son nom pour l'encourager. Elle est prévenue dans l'application, sur son téléphone et par email.",
  'pt': 'Toque em Mensagem por baixo do nome dela para a encorajar. Ela é avisada na aplicação, no telemóvel e por email.',
  'sw': 'Bonyeza Ujumbe chini ya jina lake kumtia moyo. Anaarifiwa kwenye programu, kwenye simu na kwa barua pepe.',
  'ar': 'اضغطي على رسالة تحت اسمها لتشجيعها. تُبلَّغ في التطبيق وعلى هاتفها وبالبريد الإلكتروني.'}),
 ('tasks', {
  'en': 'Tap Tasks at the bottom to see what headquarters has given you. Do it, and submit it.',
  'fr': "Appuyez sur Tâches en bas pour voir ce que le siège vous a confié. Faites-le, puis soumettez-le.",
  'pt': 'Toque em Tarefas em baixo para ver o que a sede lhe deu. Faça e envie.',
  'sw': 'Bonyeza Kazi chini kuona ulichopewa na makao makuu. Kifanye, kisha ukiwasilishe.',
  'ar': 'اضغطي على المهام في الأسفل لتري ما أسنده إليكِ المقر. أنجزيه ثم أرسليه.'}),
 ('bell', {
  'en': 'The bell at the top shows what is new. Tap it to see your notifications.',
  'fr': 'La cloche en haut montre les nouveautés. Appuyez dessus pour voir vos notifications.',
  'pt': 'O sino no topo mostra as novidades. Toque nele para ver as suas notificações.',
  'sw': 'Kengele iliyo juu inaonyesha mapya. Ibonyeze kuona taarifa zako.',
  'ar': 'الجرس في الأعلى يُريكِ الجديد. اضغطي عليه لتري إشعاراتك.'}),
 ('lang', {
  'en': 'Change your language here, at the top of every page. Tap Listen to hear the page read aloud.',
  'fr': 'Changez de langue ici, en haut de chaque page. Appuyez sur Écouter pour entendre la page lue à voix haute.',
  'pt': 'Mude de língua aqui, no topo de cada página. Toque em Ouvir para ouvir a página em voz alta.',
  'sw': 'Badilisha lugha hapa, juu ya kila ukurasa. Bonyeza Sikiliza kusikia ukurasa ukisomwa kwa sauti.',
  'ar': 'غيّري لغتك من هنا، في أعلى كل صفحة. اضغطي على استماع لتسمعي الصفحة بصوت عالٍ.'}),
 ('end', {
  'en': 'You are ready. You can open this guide again from the menu, under How to use WDOS. No woman is left out.',
  'fr': 'Vous êtes prête. Vous pouvez rouvrir ce guide depuis le menu, sous Comment utiliser WDOS. Aucune femme n\'est laissée de côté.',
  'pt': 'Está pronta. Pode abrir este guia de novo no menu, em Como usar o WDOS. Nenhuma mulher fica de fora.',
  'sw': 'Uko tayari. Unaweza kufungua mwongozo huu tena kwenye menyu, chini ya Jinsi ya kutumia WDOS. Hakuna mwanamke anayeachwa nyuma.',
  'ar': 'أنتِ جاهزة. يمكنكِ فتح هذا الدليل مرة أخرى من القائمة، تحت كيفية استخدام WDOS. لا تُترك أي امرأة خارج الركب.'}),
]

MEMBER = [
 ('dash', {
  'en': 'This is your home in WDOS. Everything about your journey, your chapter and your network starts here.',
  'fr': 'Voici votre accueil dans WDOS. Tout sur votre parcours, votre chapitre et votre réseau commence ici.',
  'pt': 'Esta é a sua página inicial no WDOS. Tudo sobre o seu percurso, o seu capítulo e a sua rede começa aqui.',
  'sw': 'Hii ni nyumbani kwako katika WDOS. Kila kitu kuhusu safari yako, tawi lako na mtandao wako kinaanzia hapa.',
  'ar': 'هذه صفحتك الرئيسية في WDOS. كل ما يخص رحلتك وفرعك وشبكتك يبدأ من هنا.'}),
 ('journey', {
  'en': 'Tap Seven-day journey to take your next step. One short step each day, and you become an active member.',
  'fr': "Appuyez sur Parcours de sept jours pour faire l'étape suivante. Une courte étape par jour, et vous devenez membre active.",
  'pt': 'Toque em Jornada de sete dias para dar o próximo passo. Um passo curto por dia, e torna-se membro ativa.',
  'sw': 'Bonyeza Safari ya siku saba kuchukua hatua inayofuata. Hatua fupi moja kila siku, na unakuwa mwanachama hai.',
  'ar': 'اضغطي على رحلة الأيام السبعة لتخطي خطوتك التالية. خطوة قصيرة كل يوم، وتصبحين عضوة نشطة.'}),
 ('chapter', {
  'en': 'Tap My chapter to see the women near you, your chapter leaders and what is happening.',
  'fr': 'Appuyez sur Mon chapitre pour voir les femmes près de chez vous, vos responsables et ce qui se passe.',
  'pt': 'Toque em O meu capítulo para ver as mulheres perto de si, as suas líderes e o que está a acontecer.',
  'sw': 'Bonyeza Tawi langu kuona wanawake walio karibu nawe, viongozi wa tawi lako na kinachoendelea.',
  'ar': 'اضغطي على فرعي لتري النساء القريبات منكِ وقائدات فرعك وما يجري.'}),
 ('invite', {
  'en': 'Tap Referral progress to invite three women you trust. Share your own link on WhatsApp.',
  'fr': 'Appuyez sur Progression des parrainages pour inviter trois femmes de confiance. Partagez votre lien sur WhatsApp.',
  'pt': 'Toque em Progresso das indicações para convidar três mulheres de confiança. Partilhe o seu link no WhatsApp.',
  'sw': 'Bonyeza Maendeleo ya mialiko kuwaalika wanawake watatu unaowaamini. Shiriki kiungo chako kwenye WhatsApp.',
  'ar': 'اضغطي على تقدّم الدعوات لدعوة ثلاث نساء تثقين بهن. شاركي رابطك عبر واتساب.'}),
 ('community', {
  'en': 'Tap WODDI Community to meet your chapter and your network, share news and encourage each other.',
  'fr': 'Appuyez sur Communauté WODDI pour retrouver votre chapitre et votre réseau, partager des nouvelles et vous encourager.',
  'pt': 'Toque em Comunidade WODDI para encontrar o seu capítulo e a sua rede, partilhar novidades e encorajar-se umas às outras.',
  'sw': 'Bonyeza Jumuiya ya WODDI kukutana na tawi lako na mtandao wako, kushiriki habari na kutiana moyo.',
  'ar': 'اضغطي على مجتمع WODDI للقاء فرعك وشبكتك ومشاركة الأخبار وتشجيع بعضكن.'}),
 ('messages', {
  'en': 'Tap Messages at the bottom to write to WODDI headquarters. You get the answer here, and by email.',
  'fr': "Appuyez sur Messages en bas pour écrire au siège de WODDI. Vous recevez la réponse ici, et par email.",
  'pt': 'Toque em Mensagens em baixo para escrever à sede da WODDI. Recebe a resposta aqui e por email.',
  'sw': 'Bonyeza Ujumbe chini kuandikia makao makuu ya WODDI. Unapata jibu hapa, na kwa barua pepe.',
  'ar': 'اضغطي على الرسائل في الأسفل للكتابة إلى مقر WODDI. يصلكِ الرد هنا وبالبريد الإلكتروني.'}),
 ('bell', {
  'en': 'The bell at the top shows what is new. Tap it to see your notifications.',
  'fr': 'La cloche en haut montre les nouveautés. Appuyez dessus pour voir vos notifications.',
  'pt': 'O sino no topo mostra as novidades. Toque nele para ver as suas notificações.',
  'sw': 'Kengele iliyo juu inaonyesha mapya. Ibonyeze kuona taarifa zako.',
  'ar': 'الجرس في الأعلى يُريكِ الجديد. اضغطي عليه لتري إشعاراتك.'}),
 ('lang', {
  'en': 'Change your language here, at the top of every page. Tap Listen to hear the page read aloud.',
  'fr': 'Changez de langue ici, en haut de chaque page. Appuyez sur Écouter pour entendre la page lue à voix haute.',
  'pt': 'Mude de língua aqui, no topo de cada página. Toque em Ouvir para ouvir a página em voz alta.',
  'sw': 'Badilisha lugha hapa, juu ya kila ukurasa. Bonyeza Sikiliza kusikia ukurasa ukisomwa kwa sauti.',
  'ar': 'غيّري لغتك من هنا، في أعلى كل صفحة. اضغطي على استماع لتسمعي الصفحة بصوت عالٍ.'}),
 ('end', {
  'en': 'You are ready. You can open this guide again from the menu, under How to use WDOS. No woman is left out.',
  'fr': "Vous êtes prête. Vous pouvez rouvrir ce guide depuis le menu, sous Comment utiliser WDOS. Aucune femme n'est laissée de côté.",
  'pt': 'Está pronta. Pode abrir este guia de novo no menu, em Como usar o WDOS. Nenhuma mulher fica de fora.',
  'sw': 'Uko tayari. Unaweza kufungua mwongozo huu tena kwenye menyu, chini ya Jinsi ya kutumia WDOS. Hakuna mwanamke anayeachwa nyuma.',
  'ar': 'أنتِ جاهزة. يمكنكِ فتح هذا الدليل مرة أخرى من القائمة، تحت كيفية استخدام WDOS. لا تُترك أي امرأة خارج الركب.'}),
]
