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
  ['/^Add your photograph$/u', '/^(.+), your profile still has no photograph\./u', [
    'ar' => ['أضيفي صورتك', '{1}، ملفك ما زال بلا صورة. يستغرق ذلك دقيقة ويساعد قائداتك على التعرّف عليك.'],
    'fr' => ['Ajoutez votre photo', '{1}, votre profil n\'a pas encore de photo. Cela prend une minute et aide vos responsables à vous reconnaître.'],
    'pt' => ['Adicione a sua fotografia', '{1}, o seu perfil ainda não tem fotografia. Demora um minuto e ajuda as suas líderes a reconhecê-la.'],
    'sw' => ['Ongeza picha yako', '{1}, wasifu wako bado hauna picha. Inachukua dakika moja na huwasaidia viongozi wako kukutambua.']]],
];
/** The message in one language, or null when there is no translation for it. */
function note_in(string $lang, string $title, string $body): ?array {
  if (!in_array($lang, NOTE_LANGS, true)) return null;
  foreach (NOTE_TEMPLATES as [$rt, $rb, $tr]) {
    if (!preg_match($rt, $title, $mt) || !preg_match($rb, $body, $mb)) continue;
    $fill = fn(string $s, array $m) => preg_replace_callback('/\{(\d)\}/', fn($x) => $m[(int) $x[1]] ?? '', $s);
    return [$fill($tr[$lang][0], $mt), $fill($tr[$lang][1], $mb)];
  }
  return null;
}
