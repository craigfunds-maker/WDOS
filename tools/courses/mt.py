"""Hausa, Yoruba and Igbo text for WDOS: the screens (i18n) and the twelve courses for both networks.
Machine translation with Meta NLLB-200 (distilled 1.3B, CTranslate2 int8), run on GitHub's machines.
  python mt.py <lang> <wdos-php dir> <model dir> <out dir>
Placeholders ({1}, {2}) and the names WODDI, WDOS, WGMN, WNNN must come back unchanged, otherwise the English
string is kept and counted. Strings already translated by a person are never replaced."""
import sys, os, re, json, ctranslate2, transformers
L, W, MOD, OUT = sys.argv[1:5]
CODE = {'ha': 'hau_Latn', 'yo': 'yor_Latn', 'ig': 'ibo_Latn'}[L]
os.makedirs(f'{OUT}/{L}', exist_ok=True)
tok = transformers.AutoTokenizer.from_pretrained('facebook/nllb-200-distilled-1.3B', src_lang='eng_Latn')
tr = ctranslate2.Translator(MOD, device='cpu', compute_type='int8', inter_threads=1, intra_threads=os.cpu_count())
KEEP = ['WODDI', 'WDOS', 'WGMN', 'WNNN', 'WhatsApp', 'HQ']
def split(s): return [x for x in re.split(r'(?<=[.!?])\s+', s) if x.strip()]
cache = {}
def run(segs):
    todo = [s for s in dict.fromkeys(segs) if s not in cache]
    for i in range(0, len(todo), 24):
        b = todo[i:i + 24]
        src = [tok.convert_ids_to_tokens(tok.encode(s)) for s in b]
        res = tr.translate_batch(src, target_prefix=[[CODE]] * len(b), beam_size=3, max_decoding_length=400, repetition_penalty=1.1)
        for s, r in zip(b, res): cache[s] = tok.decode(tok.convert_tokens_to_ids(r.hypotheses[0][1:]), skip_special_tokens=True)
        if i % 480 == 0: print(L, len(cache), '/', len(cache) + len(todo) - i, flush=True)
fallback = [0]
def T(s):
    """translate a whole string, sentence by sentence; keep English if a placeholder or a name is lost"""
    if not s or not re.search('[A-Za-z]', s): return s
    if ' -> ' in s: return ' -> '.join(T(p) for p in s.split(' -> '))
    out = ' '.join(cache[x] for x in split(s))
    need = re.findall(r'\{\w+\}', s) + [k for k in KEEP if k in s]
    if any(n not in out for n in need): fallback[0] += 1; return s
    return out
# 1. everything to translate, collected first so it runs in large batches
fr = json.load(open(f'{W}/assets/i18n/fr.json')); have = json.load(open(f'{W}/assets/i18n/{L}.json'))
ui = [k for k in fr if k not in have]
nets = {n: json.load(open(f'{W}/assets/courses/en/{n}.json')) for n in ('wgmn', 'wnnn')}
def leaves(o, acc):
    if isinstance(o, str): acc.append(o)
    elif isinstance(o, list): [leaves(x, acc) for x in o]
    elif isinstance(o, dict): [leaves(v, acc) for k, v in o.items() if k not in ('lang', 'net')]
    return acc
texts = ui + leaves(nets['wgmn'], []) + leaves(nets['wnnn'], [])
segs = []
for s in texts:
    if s and re.search('[A-Za-z]', s):
        for p in (s.split(' -> ') if ' -> ' in s else [s]): segs += split(p)
print(L, 'segments', len(set(segs)), flush=True)
run(segs)
# 2. write back
d = dict(have); n0 = 0
for k in ui:
    v = T(k)
    if v != k: d[k] = v; n0 += 1
json.dump(d, open(f'{OUT}/{L}/i18n.json', 'w'), ensure_ascii=False, indent=0)
def walk(o, key=''):
    if isinstance(o, str): return o if key in ('lang', 'net') else T(o)
    if isinstance(o, list): return [walk(x) for x in o]
    if isinstance(o, dict): return {k: (L if k == 'lang' else walk(v, k)) for k, v in o.items()}
    return o
for n, D in nets.items():
    X = walk(D)
    for m in (mm for c in X['courses'] for mm in c['modules']): m['topic'] = m['title']
    json.dump(X, open(f'{OUT}/{L}/{n}.json', 'w'), ensure_ascii=False, separators=(',', ':'))
print(L, 'screens translated', n0, 'of', len(ui), '| kept in English for a lost placeholder or name:', fallback[0], flush=True)
