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
