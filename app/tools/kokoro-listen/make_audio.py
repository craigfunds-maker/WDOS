"""Record the Listen narration for course lessons with Kokoro (voice af_heart, the English voice of the course videos).

Run on a computer or in GitHub Actions, not on cPanel:
    pip install kokoro-onnx soundfile numpy
    curl -LO https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/kokoro-v1.0.onnx
    curl -LO https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/voices-v1.0.bin
    python make_audio.py ../../content/courses ../../assets/audio/courses core-scam-awareness-and-digital-trust
Leave the last argument out to record every course (about 200 hours of audio: run it in batches).
Then upload the assets/audio/courses folder to the site. The Listen button uses a recording when it finds one
and the phone's own voice when it does not, so you can add courses a few at a time."""
import sys, os, json, re, glob, subprocess, numpy as np, soundfile as sf
from kokoro_onnx import Kokoro
src, out = sys.argv[1], sys.argv[2]; only = sys.argv[3:]
k = Kokoro('kokoro-v1.0.onnx', 'voices-v1.0.bin')
def clean(t):
    t = re.sub(r'\*\*', '', str(t)).replace('—', ', ').replace('–', ', ').replace('&', ' and ')
    return re.sub(r'\s+', ' ', t).strip()
def lines(lesson):
    yield lesson.get('title', '')
    for o in lesson.get('objectives', []): yield o
    for b in lesson.get('blocks', []):
        for key in ('title', 'text', 'body', 'caption'):
            if isinstance(b.get(key), str): yield b[key]
        for key in ('items', 'steps', 'rows'):
            for x in b.get(key, []) or []:
                if isinstance(x, str): yield x
                elif isinstance(x, dict): yield '. '.join(str(v) for v in x.values() if isinstance(v, str))
                elif isinstance(x, list): yield ', '.join(str(v) for v in x)
    for o in lesson.get('takeaways', []): yield o
for f in sorted(glob.glob(os.path.join(src, '*.json'))):
    cid = os.path.basename(f)[:-5]
    if only and cid not in only: continue
    c = json.load(open(f, encoding='utf8'))
    for mi, m in enumerate(c['modules']):
        for li, l in enumerate(m['lessons']):
            lid = l.get('id') or f"{m.get('id', 'm' + str(mi + 1))}-l{li + 1}"; dst = os.path.join(out, cid, 'en', lid + '.mp3')
            if os.path.exists(dst): continue
            os.makedirs(os.path.dirname(dst), exist_ok=True); audio = []
            for t in lines(l):
                t = clean(t)
                if len(t) < 2: continue
                s, sr = k.create(t if t[-1] in '.!?' else t + '.', voice='af_heart', speed=0.95, lang='en-us'); audio += [s, np.zeros(int(sr * 0.3), dtype=s.dtype)]
            if not audio: continue
            sf.write('tmp.wav', np.concatenate(audio), sr)
            subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', 'tmp.wav', '-ac', '1', '-ar', '24000', '-b:a', '40k', dst], check=True); print(dst, flush=True)
