"""Narrate every clip of voice_plan for one language, with caption timings.
  python voice_make.py <lang> <assets/courses dir> <models dir>
Kokoro v1.0 (af_heart, ff_siwis, pf_dora) for English, French, Portuguese; Piper for Swahili and Arabic;
Meta MMS (facebook/mms-tts-hau, -yor, -ibo) for Hausa, Yoruba and Igbo. Skips clips already made."""
import sys, os, json, wave, subprocess, numpy as np, soundfile as sf
sys.path.insert(0, os.path.dirname(__file__)); from voice_plan import plan
L, ROOT, MOD = sys.argv[1], sys.argv[2], sys.argv[3]
wg = json.load(open(f'{ROOT}/{L}/wgmn.json')); wn = json.load(open(f'{ROOT}/{L}/wnnn.json'))
J = plan(L, wg, wn); out = f'{ROOT}/voice/{L}'; os.makedirs(out, exist_ok=True)
cues = json.load(open(f'{out}/cues.json')) if os.path.exists(f'{out}/cues.json') else {}
if L in ('en', 'fr', 'pt'):
    from kokoro_onnx import Kokoro; K = Kokoro(f'{MOD}/kokoro-v1.0.onnx', f'{MOD}/voices-v1.0.bin')
    v, code = {'en': ('af_heart', 'en-us'), 'fr': ('ff_siwis', 'fr-fr'), 'pt': ('pf_dora', 'pt-br')}[L]
    say = lambda s: K.create(s, voice=v, speed=1.0, lang=code)
elif L in ('sw', 'ar'):
    from piper import PiperVoice, SynthesisConfig
    m = {'sw': f'{MOD}/vits-piper-sw_CD-lanfrica-medium/sw_CD-lanfrica-medium.onnx', 'ar': f'{MOD}/vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium.onnx'}[L]
    V = PiperVoice.load(m); cfg = SynthesisConfig(length_scale=0.82 if L == 'ar' else 0.95, noise_scale=0.6, noise_w_scale=0.75)
    def say(s):
        with wave.open(f'/tmp/_c_{L}.wav', 'wb') as w: V.synthesize_wav(s, w, syn_config=cfg)
        return sf.read(f'/tmp/_c_{L}.wav')
else:
    import torch; from transformers import VitsModel, AutoTokenizer
    name = {'ha': 'facebook/mms-tts-hau', 'yo': 'facebook/mms-tts-yor', 'ig': 'facebook/mms-tts-ibo'}[L]
    M = VitsModel.from_pretrained(name); TK = AutoTokenizer.from_pretrained(name); torch.manual_seed(7)
    M.speaking_rate = 0.95
    def say(s):
        with torch.no_grad(): y = M(**TK(s, return_tensors='pt')).waveform[0].numpy()
        return y, M.config.sampling_rate
made = 0
for k, ss in J.items():
    if k in cues and os.path.exists(f'{out}/{k}.mp3'): continue
    parts = []; t = 0.0; cue = []; sr = 24000
    for i, s in enumerate(ss):
        y, sr = say(s); y = np.asarray(y, dtype=np.float32)
        if y.ndim > 1: y = y.mean(axis=1)
        nz = np.where(np.abs(y) > 0.01)[0]; y = y[max(0, nz[0] - int(0.03 * sr)):nz[-1] + int(0.05 * sr)] if len(nz) else y
        gap = np.zeros(int(sr * (0.5 if i == 0 else 0.3)), dtype=np.float32)
        cue.append([round(t, 2), s]); parts += [y, gap]; t += (len(y) + len(gap)) / sr
    y = np.concatenate(parts); y = y / (np.max(np.abs(y)) or 1) * 0.89
    sf.write(f'/tmp/_cc_{L}.wav', y, sr, subtype='PCM_16')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', f'/tmp/_cc_{L}.wav', '-ac', '1', '-ar', '22050', '-b:a', '40k', f'{out}/{k}.mp3'], check=True)
    cues[k] = {'d': round(t, 2), 'c': cue}; made += 1
    if made % 20 == 0: json.dump(cues, open(f'{out}/cues.json', 'w'), ensure_ascii=False, separators=(',', ':')); print(L, made, flush=True)
# drop clips the plan no longer has (old single-file lessons)
for k in list(cues):
    if k not in J: cues.pop(k, None); p = f'{out}/{k}.mp3'; os.path.exists(p) and os.remove(p)
json.dump(cues, open(f'{out}/cues.json', 'w'), ensure_ascii=False, separators=(',', ':'))
print(L, 'done', len(J), 'clips,', made, 'new', flush=True)
