"""Narrate every clip of voice_plan for one language, with caption timings.
  python voice_make.py <lang> <assets/courses dir> <models dir>
Kokoro v1.0 (af_heart, ff_siwis, pf_dora) for English, French, Portuguese; Piper for Swahili and Arabic;
VITS Open Bible voices (Hugging Face, multilingual-tts) for Hausa, Yoruba and Igbo. Skips clips already made."""
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
    # Hausa, Yoruba, Igbo: VITS Open Bible voices (Coqui TTS, trained on studio Bible readings by native speakers, CC BY-SA 4.0).
    # They read the language's own spelling (tone marks included), so they speak naturally where Meta MMS sounded robotic or garbled.
    import torch; from huggingface_hub import hf_hub_download
    from TTS.utils.synthesizer import Synthesizer; from TTS.tts.utils.speakers import SpeakerManager
    repo = {'ha': 'multilingual-tts/VITS-OpenBible-Hausa', 'yo': 'multilingual-tts/VITS-OpenBible-Yoruba', 'ig': 'multilingual-tts/VITS-OpenBible-Igbo'}[L]
    spk_file = hf_hub_download(repo, 'speakers.pth')
    SY = Synthesizer(tts_checkpoint=hf_hub_download(repo, 'model_last.pth'), tts_config_path=hf_hub_download(repo, 'config.json'), tts_speakers_file=spk_file, use_cuda=False)
    if SY.tts_model.speaker_manager is None: SY.tts_model.speaker_manager = SpeakerManager(speaker_id_file_path=spk_file)
    names = sorted(SY.tts_model.speaker_manager.speaker_names); SPK = os.environ.get('OB_SPEAKER') or names[0]
    print(L, 'speakers', names, 'using', SPK, flush=True)
    def say(s):
        y = SY.tts(text=s, speaker_name=SPK, split_sentences=False)
        return np.asarray(y, dtype=np.float32), SY.output_sample_rate
# how the name of the system is said aloud: "W-DOS" (the letter W, then DOS as one word), never spelled out letter by letter
SAY = {'en': 'W-Doss', 'fr': 'W-Dosse', 'pt': 'W-Dós', 'sw': 'Dablyu-Dos', 'ar': 'دبليو-دوس', 'ha': 'Dablyu-Dos', 'yo': 'Dabulu-Dọọsi', 'ig': 'Dabliu-Dos'}[L]
def spoken(s): return s.replace('WDOS', SAY)
made = 0
for k, ss in J.items():
    if k in cues and os.path.exists(f'{out}/{k}.mp3') and not (any('WDOS' in x for x in ss) and cues[k].get('say') != SAY): continue
    parts = []; t = 0.0; cue = []; sr = 24000
    for i, s in enumerate(ss):
        y, sr = say(spoken(s)); y = np.asarray(y, dtype=np.float32)
        if y.ndim > 1: y = y.mean(axis=1)
        nz = np.where(np.abs(y) > 0.01)[0]; y = y[max(0, nz[0] - int(0.03 * sr)):nz[-1] + int(0.05 * sr)] if len(nz) else y
        gap = np.zeros(int(sr * (0.5 if i == 0 else 0.3)), dtype=np.float32)
        cue.append([round(t, 2), s]); parts += [y, gap]; t += (len(y) + len(gap)) / sr
    y = np.concatenate(parts); y = y / (np.max(np.abs(y)) or 1) * 0.89
    sf.write(f'/tmp/_cc_{L}.wav', y, sr, subtype='PCM_16')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', f'/tmp/_cc_{L}.wav', '-ac', '1', '-ar', '22050', '-b:a', '40k', f'{out}/{k}.mp3'], check=True)
    cues[k] = {'d': round(t, 2), 'c': cue, 'say': SAY}; made += 1
    if made % 20 == 0: json.dump(cues, open(f'{out}/cues.json', 'w'), ensure_ascii=False, separators=(',', ':')); print(L, made, flush=True)
# drop clips the plan no longer has (old single-file lessons)
for k in list(cues):
    if k not in J: cues.pop(k, None); p = f'{out}/{k}.mp3'; os.path.exists(p) and os.remove(p)
json.dump(cues, open(f'{out}/cues.json', 'w'), ensure_ascii=False, separators=(',', ':'))
print(L, 'done', len(J), 'clips,', made, 'new', flush=True)
