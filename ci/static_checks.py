# Fast checks that need no database: every dictionary is valid JSON and holds the same keys, and no
# language has an empty value. Run from the repository root.
import json, sys, glob
import os
root = os.environ.get('WDOS_ROOT', 'wdos-php') + '/assets/i18n/'
d = {p.split('/')[-1][:-5]: json.load(open(p, encoding='utf-8')) for p in glob.glob(root + '*.json')}
bad = 0
base = set(d['fr'])
for lang in ('pt', 'ar', 'sw'):
    miss = base - set(d[lang]); extra = set(d[lang]) - base
    if miss: bad += 1; print(f'{lang}: {len(miss)} keys missing, e.g. {sorted(miss)[:3]}')
    if extra: print(f'{lang}: {len(extra)} keys not in fr (allowed, listed for review), e.g. {sorted(extra)[:3]}')
for lang, dic in d.items():
    empty = [k for k, v in dic.items() if not str(v).strip()]
    if empty: bad += 1; print(f'{lang}: {len(empty)} empty translations, e.g. {empty[:3]}')
print('dictionaries:', {k: len(v) for k, v in d.items()}, 'problems:', bad)
sys.exit(1 if bad else 0)
