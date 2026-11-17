"""What each lesson says aloud, as small clips that play one after another.
Shared clips (course intro, the idea of each lesson, and the general teaching parts) are made once per language;
the example and the practice are made per network (WGMN, WNNN). The lesson page plays them as one video."""
import json, re
GEN = {'en': ('this topic', 'This topic'), 'fr': ('ce sujet', 'Ce sujet'), 'pt': ('este tema', 'Este tema'), 'ar': ('هذا الموضوع', 'هذا الموضوع'),
       'sw': ('mada hii', 'Mada hii'), 'ha': ('wannan darasi', 'Wannan darasi'), 'yo': ('ẹ̀kọ́ yìí', 'Ẹ̀kọ́ yìí'), 'ig': ('ihe ọmụmụ a', 'Ihe ọmụmụ a')}
LINK = {'en': {'lesson': 'Lesson', 'course': 'Course', 'example': 'Here is an example.', 'practice': 'Your practice:', 'steps': 'How to apply it, step by step.', 'process': 'The process in this course:', 'step': 'Step'},
        'fr': {'lesson': 'Leçon', 'course': 'Cours', 'example': 'Voici un exemple.', 'practice': 'Votre exercice :', 'steps': "Comment l'appliquer, étape par étape.", 'process': 'Le processus de ce cours :', 'step': 'Étape'},
        'pt': {'lesson': 'Lição', 'course': 'Curso', 'example': 'Eis um exemplo.', 'practice': 'A sua prática:', 'steps': 'Como aplicar, passo a passo.', 'process': 'O processo deste curso:', 'step': 'Passo'},
        'sw': {'lesson': 'Somo', 'course': 'Kozi', 'example': 'Huu ni mfano.', 'practice': 'Zoezi lako:', 'steps': 'Jinsi ya kulitumia, hatua kwa hatua.', 'process': 'Mchakato wa kozi hii:', 'step': 'Hatua'},
        'ar': {'lesson': 'الدرس', 'course': 'الدورة', 'example': 'إليكِ مثالًا.', 'practice': 'تمرينك:', 'steps': 'كيف تطبّقينه خطوة بخطوة.', 'process': 'مسار هذه الدورة:', 'step': 'الخطوة'},
        'ha': {'lesson': 'Darasi', 'course': 'Kwas', 'example': 'Ga misali.', 'practice': 'Aikinki:', 'steps': 'Yadda ake amfani da shi, mataki-mataki.', 'process': 'Tsarin wannan kwas:', 'step': 'Mataki'},
        'yo': {'lesson': 'Ẹ̀kọ́', 'course': 'Kọ́ọ̀sì', 'example': 'Àpẹẹrẹ rèé.', 'practice': 'Iṣẹ́ ìdánrawò rẹ:', 'steps': 'Bí o ṣe máa lò ó, ní ìgbésẹ̀ kọ̀ọ̀kan.', 'process': 'Ìlànà kọ́ọ̀sì yìí:', 'step': 'Ìgbésẹ̀'},
        'ig': {'lesson': 'Nkuzi', 'course': 'Ọmụmụ', 'example': 'Lee ihe atụ.', 'practice': 'Omume gị:', 'steps': 'Otu esi etinye ya n’ọrụ, nzọụkwụ n’nzọụkwụ.', 'process': 'Usoro ọmụmụ a:', 'step': 'Nzọụkwụ'}}
def sentences(t): return [s.strip() for s in re.split(r'(?<=[.!?؟])\s+', t or '') if s.strip()]
def generic(L, s):
    g, G = GEN[L]
    for a in ('« {topic} »', '«{topic}»', '"{topic}"'): s = s.replace(a, G)
    return s.replace('{topic} ', G + ' ', 1) if s.startswith('{topic}') else s.replace('{topic}', g)
def plan(L, wg, wn):
    """wg, wn: the language's WGMN and WNNN course files. Returns {clip_key: [sentences]}."""
    W = LINK[L]; T = wg['t']; J = {}
    for net, D in (('wgmn', wg), ('wnnn', wn)):
        J[f'g-why-{net}'] = sentences(generic(L, D['t']['why']))
    J['g-steps'] = [W['steps']] + [f"{W['step']} {i + 1}. {generic(L, s)}" for i, s in enumerate(T['steps'])]
    J['g-think'] = sentences(T['think']); J['g-parts'] = sentences(T['parts'])
    J['g-reflect'] = sentences(generic(L, T['reflect'])); J['g-evidence'] = sentences(generic(L, T.get('evidence', '')))
    for c, cn in zip(wg['courses'], wn['courses']):
        n = c['no']
        J[f'c{n:02d}-intro'] = [f"{W['course']} {n}. {c['title']}."] + sentences(c['purpose']) + [W['process'] + ' ' + c['process'].replace(' -> ', ', ') + '.'] + sentences(T.get('outcomes', '')) + sentences(c['boundaries'])
        for m, mn in zip(c['modules'], cn['modules']):
            k = f"c{n:02d}-{m['no']}"
            J[k] = [f"{W['lesson']} {n}.{m['no']}. {m['title']}."] + sentences(m['concept'])
            for net, mm in (('wgmn', m), ('wnnn', mn)):
                J[f'{k}-{net}-ex'] = [W['example']] + sentences(mm['scenario'])
                J[f'{k}-{net}-pr'] = [W['practice']] + sentences(mm['exercise'])
    return J
def playlist(n, m, net):
    k = f'c{n:02d}-{m}'
    return [k, f'g-why-{net}', 'g-steps', f'{k}-{net}-ex', 'g-think', f'{k}-{net}-pr', 'g-parts', 'g-reflect', 'g-evidence']
