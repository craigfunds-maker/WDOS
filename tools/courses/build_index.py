import json,glob,sys
ROOT='/home/claude/wdos-php/assets/courses'
EN=json.load(open(f'{ROOT}/en/index.json'))
D_EN=['Prioritise public recognition over responsible delivery','Treat all decisions as outside approved governance','Assume no further communication or review is needed']
L={
'fr':dict(
 t={"why":"« {topic} » influence la qualité des décisions, des relations et du suivi. Dans WGMN et WNNN, les responsables bénévoles coordonnent souvent des personnes qui ont d'autres responsabilités, vivent dans des communautés différentes et communiquent surtout par le numérique. Le but est de transformer cette leçon en un comportement visible : une décision claire, une conversation utile, une action documentée ou une orientation faite à temps. Apprenez le principe avant de l'appliquer ; l'enthousiasme ne remplace ni les preuves ni l'autorité.",
 "steps":["Repérez une situation actuelle où {topic} compte. Décrivez le problème en une phrase, sans parler des personnalités.","Notez ce que vous savez, ce qu'il reste à confirmer et qui est concerné.","Choisissez une petite réponse adaptée à votre rôle et aux procédures de WODDI.","Convenez d'une prochaine action raisonnable, d'une responsable et d'un moment pour faire le point.","Notez ce qui s'est passé et ce que vous amélioreriez."],
 "think":"Quel résultat voulez-vous dans cette situation ? Qu'est-ce qui dépend de la responsable ? Que pourrait-il arriver si elle agit sans écouter, vérifier ou obtenir l'approbation ? Quelle prochaine étape serait la plus respectueuse, réaliste et mesurable ?",
 "parts":"Faites l'exercice en quatre parties : (a) votre situation ou point de départ, (b) l'amélioration voulue, (c) l'action que vous allez mener, (d) comment vous saurez que cela a marché. Restez brève et n'ajoutez pas d'informations personnelles inutiles sur d'autres personnes.",
 "reflect":"Que feriez-vous autrement dans votre responsabilité bénévole actuelle après avoir étudié {topic} ? Écrivez une prochaine action concrète."},
 selfcheck="Puis-je expliquer les notions avec mes propres mots ? Puis-je distinguer une action WODDI responsable d'une action non autorisée ? Ai-je fait au moins un exercice pratique ? Est-ce que je sais qui contacter si ma situation dépasse mon rôle ?",
 q1="Quelle action montre LE MIEUX {topic} dans le leadership bénévole ?",q2="Voici une situation : {s} Quel est le principe de leadership le plus approprié ?",ok="Mettre en pratique {topic} de façon responsable",
 d=["Privilégier la reconnaissance publique plutôt qu'une action responsable","Considérer toutes les décisions comme hors de la gouvernance approuvée","Supposer qu'aucune autre communication ni vérification n'est nécessaire"]),
'pt':dict(
 t={"why":"«{topic}» influencia a qualidade das decisões, das relações e do acompanhamento. Na WGMN e na WNNN, as líderes voluntárias coordenam muitas vezes pessoas com outras responsabilidades, que vivem em comunidades diferentes e comunicam sobretudo por meios digitais. O objetivo é transformar esta lição num comportamento visível: uma decisão clara, uma conversa útil, uma ação registada ou um encaminhamento a tempo. Aprenda o princípio antes de o aplicar; o entusiasmo não substitui provas nem autoridade.",
 "steps":["Identifique uma situação atual em que {topic} é importante. Descreva o problema numa frase, sem falar de personalidades.","Anote o que sabe, o que ainda precisa de confirmar e quem é afetado.","Escolha uma pequena resposta adequada à sua função e aos procedimentos da WODDI.","Combine uma próxima ação razoável, uma responsável e um momento para fazer o ponto da situação.","Registe o que aconteceu e o que melhoraria."],
 "think":"Que resultado quer nesta situação? O que depende da líder? O que pode correr mal se ela agir sem escutar, verificar ou ter aprovação? Que próximo passo seria o mais respeitoso, realista e mensurável?",
 "parts":"Faça o exercício em quatro partes: (a) a sua situação ou ponto de partida, (b) a melhoria que quer, (c) a ação que vai fazer, (d) como saberá que resultou. Seja breve e não inclua informação pessoal desnecessária sobre outras pessoas.",
 "reflect":"O que faria de forma diferente na sua responsabilidade de voluntária depois de estudar {topic}? Escreva uma próxima ação concreta."},
 selfcheck="Consigo explicar os conceitos por palavras minhas? Consigo distinguir uma ação responsável da WODDI de uma ação não autorizada? Fiz pelo menos um exercício prático? Sei a quem recorrer se a minha situação ultrapassar a minha função?",
 q1="Que ação mostra MELHOR {topic} na liderança voluntária?",q2="Veja esta situação: {s} Qual é o princípio de liderança mais adequado?",ok="Pôr em prática {topic} com responsabilidade",
 d=["Dar mais importância ao reconhecimento público do que a um trabalho responsável","Tratar todas as decisões como fora da governação aprovada","Supor que não é preciso mais comunicação nem revisão"]),
'sw':dict(
 t={"why":"«{topic}» huathiri ubora wa maamuzi, mahusiano na ufuatiliaji. Katika WGMN na WNNN, viongozi wajitolea mara nyingi huratibu watu wenye majukumu mengine, wanaoishi katika jamii tofauti na wanaotegemea mawasiliano ya kidijitali. Lengo ni kugeuza somo hili kuwa tabia moja inayoonekana: uamuzi wazi, mazungumzo yenye manufaa, kitendo kilichoandikwa au rufaa ya wakati. Jifunze kanuni kabla ya kuitumia; hamasa haichukui nafasi ya ushahidi wala mamlaka.",
 "steps":["Tambua hali ya sasa ambapo {topic} ni muhimu. Eleza tatizo kwa sentensi moja bila kuzungumzia tabia za watu.","Andika unachojua, unachohitaji kuthibitisha na nani anaathirika.","Chagua jibu dogo linalolingana na jukumu lako na taratibu za WODDI.","Kubalianeni hatua inayofuata inayofaa, mhusika na wakati wa kufuatilia.","Andika kilichotokea na ungeboresha nini."],
 "think":"Unataka matokeo gani katika hali hii? Nini kiko ndani ya uwezo wa kiongozi? Nini kinaweza kwenda vibaya akitenda bila kusikiliza, kuthibitisha au kupata idhini? Hatua moja ipi inayofuata ingekuwa ya heshima zaidi, halisi na inayopimika?",
 "parts":"Kamilisha zoezi katika sehemu nne: (a) hali yako ya sasa au mahali unapoanzia, (b) uboreshaji unaoutaka, (c) hatua utakayochukua, na (d) utajuaje kuwa imefanikiwa. Jibu kwa ufupi na usiweke taarifa binafsi zisizo za lazima kuhusu wengine.",
 "reflect":"Ungefanya nini tofauti katika jukumu lako la sasa la kujitolea baada ya kujifunza {topic}? Andika hatua moja halisi inayofuata."},
 selfcheck="Je, naweza kueleza dhana hizi kwa maneno yangu? Je, naweza kutofautisha kitendo cha WODDI cha kuwajibika na kisichoidhinishwa? Je, nimejaribu angalau zoezi moja la vitendo? Je, najua nimwone nani hali yangu ikizidi jukumu langu?",
 q1="Ni kitendo kipi kinaonyesha VIZURI ZAIDI {topic} katika uongozi wa kujitolea?",q2="Fikiria hali hii: {s} Ni kanuni gani ya uongozi inayofaa zaidi?",ok="Kutumia {topic} kwa uwajibikaji",
 d=["Kutanguliza umaarufu kuliko utekelezaji wa kuwajibika","Kuchukulia maamuzi yote kuwa nje ya utawala ulioidhinishwa","Kudhani hakuna haja ya mawasiliano zaidi wala mapitio"]),
'ar':dict(
 t={"why":"«{topic}» يؤثر في جودة القرارات والعلاقات والمتابعة. في شبكتي WGMN وWNNN تنسق القائدات المتطوعات غالبًا مع أشخاص لديهم مسؤوليات أخرى، ويعيشون في مجتمعات مختلفة، ويعتمدون على التواصل الرقمي. الهدف تحويل هذا الدرس إلى سلوك واحد ملحوظ: قرار واضح، أو محادثة مفيدة، أو إجراء موثق، أو توجيه في الوقت المناسب. تعلّمي المبدأ قبل تطبيقه، فالحماس لا يغني عن الدليل ولا عن الصلاحية.",
 "steps":["حددي موقفًا حاليًا يكون فيه موضوع {topic} مهمًا. صفي المشكلة في جملة واحدة دون الحديث عن الشخصيات.","دوّني ما تعرفينه، وما يلزم التأكد منه، ومن المتأثر.","اختاري ردًا صغيرًا يناسب دورك وإجراءات WODDI.","اتفقي على خطوة تالية معقولة ومسؤولة عنها وموعد للمتابعة.","سجّلي ما حدث وما الذي ستحسّنينه."],
 "think":"ما النتيجة التي تريدينها في هذا الموقف؟ ما الذي في يد القائدة؟ ما الذي قد يسوء إذا تصرفت دون استماع أو تحقق أو موافقة؟ ما الخطوة التالية الأكثر احترامًا وواقعية وقابلية للقياس؟",
 "parts":"أكملي التمرين في أربعة أجزاء: (أ) وضعك الحالي أو نقطة البداية، (ب) التحسين الذي تريدينه، (ج) الإجراء الذي ستتخذينه، (د) كيف ستعرفين أنه نجح. اجعلي إجابتك قصيرة ولا تضيفي معلومات شخصية غير ضرورية عن الآخرين.",
 "reflect":"ما الذي ستفعلينه بشكل مختلف في مسؤوليتك التطوعية الحالية بعد دراسة {topic}؟ اكتبي خطوة تالية محددة."},
 selfcheck="هل أستطيع شرح المفاهيم بكلماتي؟ هل أستطيع التمييز بين تصرف مسؤول في WODDI وتصرف غير مأذون؟ هل جربت تمرينًا عمليًا واحدًا على الأقل؟ هل أعرف بمن أتصل إذا تجاوز وضعي حدود دوري؟",
 q1="أي تصرف يُظهر على أفضل وجه {topic} في القيادة التطوعية؟",q2="تأملي هذا الموقف: {s} ما مبدأ القيادة الأنسب؟",ok="تطبيق {topic} بمسؤولية",
 d=["تقديم الظهور العام على الأداء المسؤول","اعتبار كل القرارات خارج الحوكمة المعتمدة","افتراض أنه لا حاجة إلى مزيد من التواصل أو المراجعة"]),
}
def build(lang):
  P=L[lang]; T={}
  for f in sorted(glob.glob(f'/home/claude/courses/tr/{lang}_*.json')): T.update(json.load(open(f)))
  assert len(T)==12,(lang,len(T))
  out={'lang':lang,'t':P['t'],'courses':[]}
  for c in EN['courses']:
    x=T[str(c['no'])]; mods=[]; quiz=[]
    for i,(m,tm) in enumerate(zip(c['modules'],x['m'])):
      title,topic,concept,scen,ex,opts=tm; assert len(opts)==4
      mods.append({'no':m['no'],'title':title,'topic':topic,'concept':concept,'scenario':scen,'exercise':ex})
      quiz.append({'q':P['q1'].replace('{topic}',topic),'o':opts})
      eo=c['quiz'][2*i+1]['o']; o=[]
      for s in eo:
        if s.startswith('Use the practice of'): o.append(P['ok'].replace('{topic}',topic))
        else: o.append(P['d'][D_EN.index(s)])
      quiz.append({'q':P['q2'].replace('{s}',scen),'o':o})
    out['courses'].append({'no':c['no'],'title':x['title'],'purpose':x['purpose'],'process':x['process'],'boundaries':x['boundaries'],'selfcheck':P['selfcheck'],'modules':mods,'quiz':quiz})
  import os; os.makedirs(f'{ROOT}/{lang}',exist_ok=True)
  json.dump(out,open(f'{ROOT}/{lang}/index.json','w'),ensure_ascii=False,separators=(',',':'))
  print(lang,'ok',os.path.getsize(f'{ROOT}/{lang}/index.json'))
for l in (sys.argv[1:] or L): build(l)
