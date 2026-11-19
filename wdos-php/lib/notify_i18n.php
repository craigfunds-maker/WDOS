<?php
declare(strict_types=1);
/* Automatic messages in the member's own language. Each entry matches the English message WDOS writes and gives the
 * same message in Arabic, French, Portuguese and Swahili. {1}, {2} are the parts that change (a name, a number).
 * These are working translations and need review by native speakers before launch. A message with no entry stays in English. */
const NOTE_LANGS = ['ar', 'fr', 'pt', 'sw'];
const NOTE_TEMPLATES = [
  ['/^Happy birthday, (.+)$/u', '/^Everyone at WODDI HQ is celebrating you today\./u', [
    'ar' => ['عيد ميلاد سعيد يا {1}', 'الجميع في مقر WODDI يحتفل بك اليوم. شكراً لكل ما تقدمينه لشبكتك.'],
    'fr' => ['Joyeux anniversaire, {1}', 'Tout le siège de WODDI vous célèbre aujourd\'hui. Merci pour tout ce que vous apportez à votre réseau.'],
    'pt' => ['Feliz aniversário, {1}', 'Toda a sede da WODDI celebra consigo hoje. Obrigada por tudo o que dá à sua rede.'],
    'sw' => ['Heri ya siku ya kuzaliwa, {1}', 'Kila mtu katika Makao Makuu ya WODDI anakusherehekea leo. Asante kwa yote unayoutolea mtandao wako.']]],
  ['/^Your next WODDI step is ready$/u', '/^(.+), one short step is waiting/u', [
    'ar' => ['خطوتك التالية في WODDI جاهزة', '{1}، هناك خطوة قصيرة بانتظارك في رحلة الأيام السبعة. يمكنك قراءتها أو الاستماع إليها.'],
    'fr' => ['Votre prochaine étape WODDI est prête', '{1}, une courte étape vous attend dans votre parcours de sept jours. Vous pouvez la lire ou l\'écouter.'],
    'pt' => ['O seu próximo passo na WODDI está pronto', '{1}, um pequeno passo espera por si na sua jornada de sete dias. Pode ler ou ouvir.'],
    'sw' => ['Hatua yako inayofuata ya WODDI iko tayari', '{1}, hatua moja fupi inakusubiri katika safari yako ya siku saba. Unaweza kuisoma au kuisikiliza.']]],
  ['/^You have more time for your seven days$/u', '/^(.+), WDOS has given you (\d+) more days/u', [
    'ar' => ['لديك وقت إضافي لأيامك السبعة', '{1}، منحك WDOS تلقائياً {2} أيام إضافية. افتحي رحلتك وتابعي من حيث توقفتِ.'],
    'fr' => ['Vous avez plus de temps pour vos sept jours', '{1}, WDOS vous a donné automatiquement {2} jours de plus. Ouvrez votre parcours et reprenez là où vous vous étiez arrêtée.'],
    'pt' => ['Tem mais tempo para os seus sete dias', '{1}, o WDOS deu-lhe automaticamente mais {2} dias. Abra a sua jornada e continue de onde parou.'],
    'sw' => ['Umepewa muda zaidi kwa siku zako saba', '{1}, WDOS imekupa yenyewe siku {2} zaidi. Fungua safari yako na uendelee pale ulipoishia.']]],
  ['/^You are now an active WODDI member$/u', '/^You finished your seven days, (.+)\. Welcome\./u', [
    'ar' => ['أنتِ الآن عضوة فاعلة في WODDI', 'أكملتِ أيامك السبعة يا {1}. أهلاً بك. فرعك وشبكتك مفتوحان لك.'],
    'fr' => ['Vous êtes maintenant membre active de WODDI', 'Vous avez terminé vos sept jours, {1}. Bienvenue. Votre chapitre et votre réseau vous sont ouverts.'],
    'pt' => ['É agora um membro ativo da WODDI', 'Concluiu os seus sete dias, {1}. Bem-vinda. O seu capítulo e a sua rede estão abertos para si.'],
    'sw' => ['Sasa wewe ni mwanachama hai wa WODDI', 'Umemaliza siku zako saba, {1}. Karibu. Tawi lako na mtandao wako viko wazi kwako.']]],
  ['/^(.+) completed the seven days$/u', '/^A woman you invited is now an active member\./u', [
    'ar' => ['{1} أكملت الأيام السبعة', 'امرأة دعوتِها أصبحت الآن عضوة فاعلة. تُحتسب هذه دعوة مؤكدة.'],
    'fr' => ['{1} a terminé les sept jours', 'Une femme que vous avez invitée est maintenant membre active. Cela compte comme un parrainage vérifié.'],
    'pt' => ['{1} concluiu os sete dias', 'Uma mulher que convidou é agora membro ativo. Conta como uma indicação verificada.'],
    'sw' => ['{1} amemaliza siku saba', 'Mwanamke uliyemwalika sasa ni mwanachama hai. Hii inahesabiwa kama mwaliko uliothibitishwa.']]],
  ['/^Welcome to (WGMN|WNNN), (.+)$/u', '/^Your membership number is (\S+)\./u', [
    'ar' => ['مرحباً بك في {1} يا {2}', 'رقم عضويتك هو {1}. أضيفي صورتك وفعّلي الإشعارات لإكمال الإعداد.'],
    'fr' => ['Bienvenue à {1}, {2}', 'Votre numéro de membre est {1}. Ajoutez votre photo et activez les notifications pour terminer.'],
    'pt' => ['Bem-vinda à {1}, {2}', 'O seu número de membro é {1}. Adicione a sua fotografia e ative as notificações para concluir.'],
    'sw' => ['Karibu {1}, {2}', 'Namba yako ya uanachama ni {1}. Ongeza picha yako na uwashe arifa ili kukamilisha.']]],
  ['/^You have moved to a new chapter$/u', '/^WODDI HQ moved you to (.+)\. Your membership history is kept\./u', [
    'ar' => ['انتقلتِ إلى فرع جديد', 'نقلك مقر WODDI إلى {1}. سجل عضويتك محفوظ.'],
    'fr' => ['Vous avez changé de chapitre', 'Le siège de WODDI vous a transférée vers {1}. L\'historique de votre adhésion est conservé.'],
    'pt' => ['Mudou para um novo capítulo', 'A sede da WODDI transferiu-a para {1}. O histórico da sua adesão é mantido.'],
    'sw' => ['Umehamia tawi jipya', 'Makao Makuu ya WODDI yamekuhamishia {1}. Historia ya uanachama wako imehifadhiwa.']]],
  ['/^You have been placed in a chapter$/u', '/^Your chapter is (.+)\. Open it to meet your chapter\./u', [
    'ar' => ['تم تعيينك في فرع', 'فرعك هو {1}. افتحيه لتتعرفي على فرعك.'],
    'fr' => ['Vous avez été placée dans un chapitre', 'Votre chapitre est {1}. Ouvrez-le pour rencontrer votre chapitre.'],
    'pt' => ['Foi colocada num capítulo', 'O seu capítulo é {1}. Abra-o para conhecer o seu capítulo.'],
    'sw' => ['Umepangwa kwenye tawi', 'Tawi lako ni {1}. Lifungue ili ukutane na tawi lako.']]],
  ['/^Due today: (.+)$/u', '/^This task is due today\./u', [
    'ar' => ['مستحقة اليوم: {1}', 'هذه المهمة مستحقة اليوم.'],
    'fr' => ['À faire aujourd\'hui : {1}', 'Cette tâche est à rendre aujourd\'hui.'],
    'pt' => ['Vence hoje: {1}', 'Esta tarefa vence hoje.'],
    'sw' => ['Inatakiwa leo: {1}', 'Kazi hii inatakiwa kukamilika leo.']]],
  ['/^Add your photograph$/u', '/^(.+), your profile still has no photograph\./u', [
    'ar' => ['أضيفي صورتك', '{1}، ملفك ما زال بلا صورة. يستغرق ذلك دقيقة ويساعد قائداتك على التعرّف عليك.'],
    'fr' => ['Ajoutez votre photo', '{1}, votre profil n\'a pas encore de photo. Cela prend une minute et aide vos responsables à vous reconnaître.'],
    'pt' => ['Adicione a sua fotografia', '{1}, o seu perfil ainda não tem fotografia. Demora um minuto e ajuda as suas líderes a reconhecê-la.'],
    'sw' => ['Ongeza picha yako', '{1}, wasifu wako bado hauna picha. Inachukua dakika moja na huwasaidia viongozi wako kukutambua.']]],
  // v109.13: meeting notes lead with the meeting's own name
  ['/^(.+): approved$/u', '/^(.+)\. The Join button is ready now\.$/u', [
    'ar' => ['{1}: تمت الموافقة', '{1}. زر الانضمام جاهز الآن.'], 'fr' => ['{1} : approuvée', '{1}. Le bouton Rejoindre est prêt dès maintenant.'], 'pt' => ['{1}: aprovada', '{1}. O botão Entrar já está pronto.'], 'sw' => ['{1}: imeidhinishwa', '{1}. Kitufe cha Jiunge kiko tayari sasa.']]],
  ['/^You are invited: (.+)$/u', '/^(.*)$/u', [
    'ar' => ['أنتِ مدعوّة: {1}', '{1}'], 'fr' => ['Vous êtes invitée : {1}', '{1}'], 'pt' => ['Está convidada: {1}', '{1}'], 'sw' => ['Umealikwa: {1}', '{1}']]],
  ['/^(.+): not approved$/u', '/^(.*)$/su', [
    'ar' => ['{1}: لم تتم الموافقة', '{1}'], 'fr' => ['{1} : non approuvée', '{1}'], 'pt' => ['{1}: não aprovada', '{1}'], 'sw' => ['{1}: haijaidhinishwa', '{1}']]],
  ['/^(.+): (meeting request|cancelled)$/u', '/^(.*)$/u', [
    'ar' => ['{1}: {2}', '{1}'], 'fr' => ['{1} : {2}', '{1}'], 'pt' => ['{1}: {2}', '{1}'], 'sw' => ['{1}: {2}', '{1}']]],
  ['/^Cancelled: (.+)$/u', '/^(.*)$/su', [
    'ar' => ['أُلغي: {1}', '{1}'], 'fr' => ['Annulée : {1}', '{1}'], 'pt' => ['Cancelada: {1}', '{1}'], 'sw' => ['Umeghairiwa: {1}', '{1}']]],
  ['/^Minutes: (.+)$/u', '/^(\d+) attended\.$/u', [
    'ar' => ['محضر: {1}', 'عدد الحاضرين: {1}.'], 'fr' => ['Compte rendu : {1}', '{1} participants.'], 'pt' => ['Ata: {1}', '{1} participantes.'], 'sw' => ['Kumbukumbu: {1}', 'Waliohudhuria {1}.']]],
  ['/^Meeting flier ready: (.+)$/u', '/^Open the meeting to see and share it\.$/u', [
    'ar' => ['ملصق الاجتماع جاهز: {1}', 'افتحي الاجتماع لتريه وتشاركيه.'], 'fr' => ['Affiche de la réunion prête : {1}', 'Ouvrez la réunion pour la voir et la partager.'], 'pt' => ['Cartaz da reunião pronto: {1}', 'Abra a reunião para o ver e partilhar.'], 'sw' => ['Bango la mkutano liko tayari: {1}', 'Fungua mkutano ulione na kulishiriki.']]],
  ['/^(.+): tomorrow$/u', '/^(.*)$/u', [
    'ar' => ['{1}: غدًا', '{1}'], 'fr' => ['{1} : demain', '{1}'], 'pt' => ['{1}: amanhã', '{1}'], 'sw' => ['{1}: kesho', '{1}']]],
  ['/^(.+): in 1 hour$/u', '/^(.*)$/u', [
    'ar' => ['{1}: بعد ساعة', '{1}'], 'fr' => ['{1} : dans 1 heure', '{1}'], 'pt' => ['{1}: daqui a 1 hora', '{1}'], 'sw' => ['{1}: baada ya saa 1', '{1}']]],
  ['/^(.+): starting now$/u', '/^Open WDOS and tap Join the video meeting\.$/u', [
    'ar' => ['{1}: يبدأ الآن', 'افتحي WDOS واضغطي على الانضمام إلى الاجتماع بالفيديو.'], 'fr' => ['{1} : commence maintenant', 'Ouvrez WDOS et appuyez sur Rejoindre la réunion vidéo.'], 'pt' => ['{1}: começa agora', 'Abra o WDOS e toque em Entrar na reunião por vídeo.'], 'sw' => ['{1}: inaanza sasa', 'Fungua WDOS na ubonyeze Jiunge na mkutano wa video.']]],
  ['/^Due in 2 days: (.+)$/u', '/^Finish it and submit it before the due date\.$/u', [
    'ar' => ['موعدها بعد يومين: {1}', 'أنجزيها وأرسليها قبل الموعد.'], 'fr' => ['À rendre dans 2 jours : {1}', 'Terminez-la et soumettez-la avant la date prévue.'], 'pt' => ['Prazo em 2 dias: {1}', 'Termine e envie antes do prazo.'], 'sw' => ['Inatakiwa baada ya siku 2: {1}', 'Ikamilishe na uiwasilishe kabla ya tarehe.']]],
  ['/^Overdue: (.+)$/u', '/^This task was due yesterday\.(.*)$/su', [
    'ar' => ['متأخرة: {1}', 'كان موعد هذه المهمة أمس. أنجزيها أو أخبري من أسندها إليكِ بما يؤخّرها.'], 'fr' => ['En retard : {1}', "Cette tâche était à rendre hier. Terminez-la ou dites à la personne qui l'a confiée ce qui bloque."], 'pt' => ['Atrasada: {1}', 'Esta tarefa era para ontem. Termine-a ou diga a quem a deu o que a está a atrasar.'], 'sw' => ['Imechelewa: {1}', 'Kazi hii ilitakiwa jana. Ikamilishe au mwambie aliyekupa kinachokuchelewesha.']]],
  ['/^Waiting for your review: (.+)$/u', '/^(.+) submitted this task two days ago\.$/u', [
    'ar' => ['بانتظار مراجعتك: {1}', 'أرسلت {1} هذه المهمة قبل يومين.'], 'fr' => ['En attente de votre examen : {1}', '{1} a soumis cette tâche il y a deux jours.'], 'pt' => ['À espera da sua revisão: {1}', '{1} enviou esta tarefa há dois dias.'], 'sw' => ['Inasubiri ukaguzi wako: {1}', '{1} aliwasilisha kazi hii siku mbili zilizopita.']]],
  ['/^Unread message from (.+)$/u', '/^Open Messages in WDOS to read it and reply\.$/u', [
    'ar' => ['رسالة غير مقروءة من {1}', 'افتحي الرسائل في WDOS لقراءتها والرد عليها.'], 'fr' => ['Message non lu de {1}', 'Ouvrez Messages dans WDOS pour le lire et répondre.'], 'pt' => ['Mensagem não lida de {1}', 'Abra Mensagens no WDOS para a ler e responder.'], 'sw' => ['Ujumbe haujasomwa kutoka kwa {1}', 'Fungua Ujumbe katika WDOS uusome na ujibu.']]],
  ['/^Reminder: (.+)$/u', '/^Please read this announcement and press I have read this\.$/u', [
    'ar' => ['تذكير: {1}', 'يرجى قراءة هذا الإعلان والضغط على قرأت هذا.'], 'fr' => ['Rappel : {1}', "Lisez cette annonce et appuyez sur J'ai lu ce message."], 'pt' => ['Lembrete: {1}', 'Leia este anúncio e toque em Li esta mensagem.'], 'sw' => ['Kikumbusho: {1}', 'Tafadhali soma tangazo hili na ubonyeze Nimesoma hii.']]],
  // v109.13: voice calls
  ['/^Voice call from (.+)$/u', '/^(.*)$/u', [
    'ar' => ['مكالمة صوتية من {1}', '{1}'], 'fr' => ['Appel vocal de {1}', '{1}'], 'pt' => ['Chamada de voz de {1}', '{1}'], 'sw' => ['Simu ya sauti kutoka kwa {1}', '{1}']]],
  ['/^Missed call from (.+)$/u', '/^Open Calls in WDOS to ring back\.$/u', [
    'ar' => ['مكالمة فائتة من {1}', 'افتحي المكالمات في WDOS لمعاودة الاتصال.'], 'fr' => ['Appel manqué de {1}', 'Ouvrez Appels dans WDOS pour rappeler.'], 'pt' => ['Chamada perdida de {1}', 'Abra Chamadas no WDOS para ligar de volta.'], 'sw' => ['Simu uliyokosa kutoka kwa {1}', 'Fungua Simu katika WDOS ili upige tena.']]],
  // v109.22: the daily thought
  ['/^Thought for today: (.+)$/u', '/^(.*)$/su', [
    'ar' => ['فكرة اليوم: {1}', '{1}'], 'fr' => ['Pensée du jour : {1}', '{1}'], 'pt' => ['Pensamento do dia: {1}', '{1}'], 'sw' => ['Wazo la leo: {1}', '{1}']]],
  // v109.24: group calls
  ['/^Group call from (.+)$/u', '/^(?:(.+)\. )?Tap to join the call\.$/u', [
    'ar' => ['مكالمة جماعية من {1}', 'اضغطي للانضمام إلى المكالمة.'], 'fr' => ['Appel de groupe de {1}', "Touchez pour rejoindre l'appel."], 'pt' => ['Chamada de grupo de {1}', 'Toque para entrar na chamada.'], 'sw' => ['Simu ya kikundi kutoka kwa {1}', 'Gusa ili ujiunge na simu.']]],
  ['/^Missed group call from (.+)$/u', '/^(.*)$/su', [
    'ar' => ['مكالمة جماعية فائتة من {1}', 'افتحي المكالمات في WDOS للانضمام إن كانت المكالمة جارية.'], 'fr' => ['Appel de groupe manqué de {1}', "Ouvrez Appels dans WDOS pour rejoindre l'appel s'il est encore en cours."], 'pt' => ['Chamada de grupo perdida de {1}', 'Abra Chamadas no WDOS para entrar se a chamada ainda estiver a decorrer.'], 'sw' => ['Simu ya kikundi uliyokosa kutoka kwa {1}', 'Fungua Simu ndani ya WDOS ili ujiunge kama simu bado inaendelea.']]],
];
/* One entry per template above, in the same order: its key, its name on the Communications page, the English wording,
 * and what {1} and {2} stand for. WODDI HQ can replace any wording in any language; the change is logged. */
const NOTE_META = [
  ['birthday', 'Birthday greeting', 'Happy birthday, {1}', 'Everyone at WODDI HQ is celebrating you today. Thank you for all you give to your network.', '{1} is the first name.'],
  ['journey_reminder', 'Onboarding reminder', 'Your next WODDI step is ready', '{1}, one short step is waiting in your seven-day journey. You can read it or listen to it.', '{1} is the first name.'],
  ['journey_extension', 'Onboarding: more time given', 'You have more time for your seven days', '{1}, WDOS has given you {2} more days by itself. Open your journey and continue where you stopped.', '{1} is the first name, {2} the number of days.'],
  ['activation', 'Activation: now an active member', 'You are now an active WODDI member', 'You finished your seven days, {1}. Welcome. Your chapter and your network are open to you.', '{1} is the first name.'],
  ['referral_verified', 'Referral completed the seven days', '{1} completed the seven days', 'A woman you invited is now an active member. This counts as a verified referral.', '{1} is the invited woman\'s first name.'],
  ['welcome', 'Welcome on registration', 'Welcome to {1}, {2}', 'Your membership number is {1}. Add your photograph and switch on notifications to finish setting up.', 'Title: {1} is the network, {2} the first name. Message: {1} is the membership number.'],
  ['chapter_moved', 'Moved to another chapter', 'You have moved to a new chapter', 'WODDI HQ moved you to {1}. Your membership history is kept.', '{1} is the chapter.'],
  ['chapter_placed', 'Chapter placement confirmation', 'You have been placed in a chapter', 'Your chapter is {1}. Open it to meet your chapter.', '{1} is the chapter.'],
  ['task_due', 'Task deadline notification', 'Due today: {1}', 'This task is due today.', '{1} is the task.'],
  ['photo_nudge', 'Photograph reminder', 'Add your photograph', '{1}, your profile still has no photograph. It takes a minute and helps your leaders recognise you.', '{1} is the first name.'],
  ['meet_approved', 'Video meeting approved', '{1}: approved', '{1}. The Join button is ready now.', 'Title: {1} is the meeting. Message: {1} is the date and time.'],
  ['meet_invited', 'Invited to a video meeting', 'You are invited: {1}', '{1}', 'Title: {1} is the meeting. Message: {1} is the date and time.'],
  ['meet_declined', 'Video meeting not approved', '{1}: not approved', '{1}', 'Title: {1} is the meeting. Message: {1} is the note from HQ.'],
  ['meet_hq', 'Video meeting request or cancellation (HQ)', '{1}: meeting request', '{1}', '{1} is the meeting.'],
  ['meet_cancelled', 'Meeting cancelled', 'Cancelled: {1}', '{1}', '{1} is the meeting.'],
  ['meet_minutes', 'Video meeting minutes', 'Minutes: {1}', '{1} attended.', 'Title: {1} is the meeting. Message: {1} is the number who attended.'],
  ['meet_flier', 'Meeting flier ready', 'Meeting flier ready: {1}', 'Open the meeting to see and share it.', 'Title: {1} is the meeting.'],
  ['meet_day', 'Video meeting reminder: the day before', '{1}: tomorrow', '{1}', 'Title: {1} is the meeting. Message: {1} is the date and time.'],
  ['meet_hour', 'Video meeting reminder: one hour before', '{1}: in 1 hour', '{1}', 'Title: {1} is the meeting. Message: {1} is the date and time.'],
  ['meet_now', 'Video meeting reminder: starting now', '{1}: starting now', 'Open WDOS and tap Join the video meeting.', '{1} is the meeting.'],
  ['task_soon', 'Task due in 2 days', 'Due in 2 days: {1}', 'Finish it and submit it before the due date.', '{1} is the task.'],
  ['task_overdue', 'Task overdue', 'Overdue: {1}', 'This task was due yesterday. Complete it or tell the assigner what is holding it.', '{1} is the task.'],
  ['task_review', 'Submitted task waiting for review', 'Waiting for your review: {1}', '{1} submitted this task two days ago.', 'Title: {1} is the task. Message: {1} is the person.'],
  ['dm_unread', 'Unread message reminder', 'Unread message from {1}', 'Open Messages in WDOS to read it and reply.', '{1} is the sender.'],
  ['ann_reminder', 'Announcement reminder', 'Reminder: {1}', 'Please read this announcement and press I have read this.', '{1} is the announcement.'],
  ['call_in', 'Voice call ringing', 'Voice call from {1}', '{1}', 'Title: {1} is the caller. Message: {1} is her role.'],
  ['call_missed', 'Missed voice call', 'Missed call from {1}', 'Open Calls in WDOS to ring back.', '{1} is the caller.'],
  ['daily_thought', 'Thought for today', 'Thought for today: {1}', '{1}', '{1} is the day\'s headline; the message is its key thought.'],
  ['gcall_in', 'Group call ringing', 'Group call from {1}', 'Tap to join the call.', '{1} is the person calling.'],
  ['gcall_missed', 'Missed group call', 'Missed group call from {1}', 'Open Calls in WDOS to join if the call is still on.', '{1} is the person who called.'],
];
/** Wordings WODDI HQ has approved in place of the built-in ones: [key][lang] = [title, body]. */
function note_overrides(): array {
  static $o = null;
  if ($o === null) { $o = []; try { foreach (rows('SELECT tkey, lang, title, body FROM message_templates') as $r) $o[$r['tkey']][$r['lang']] = [$r['title'], $r['body']]; } catch (PDOException $e) { /* before the table exists */ } }
  return $o;
}
/** The message in one language (English included when HQ has changed the English wording), or null to send it as written. */
function note_in(string $lang, string $title, string $body): ?array {
  foreach (NOTE_TEMPLATES as $i => [$rt, $rb, $tr]) {
    if (!preg_match($rt, $title, $mt) || !preg_match($rb, $body, $mb)) continue;
    $fill = fn(string $s, array $m) => preg_replace_callback('/\{(\d)\}/', fn($x) => $m[(int) $x[1]] ?? '', $s);
    $own = note_overrides()[NOTE_META[$i][0]][$lang] ?? null; $use = $own ?? ($tr[$lang] ?? null);
    return $use ? [$fill($use[0], $mt), $fill($use[1], $mb)] : null;
  }
  return null;
}
