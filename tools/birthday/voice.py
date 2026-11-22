# WODDI birthday voice: asks WDOS which birthdays (today to three days ahead) still need their spoken greeting, speaks each one
# in the member's own language and sends the MP3 back (v109.44).
#   Kokoro v1.0 (af_heart, its clearest voice; ff_siwis in French; pf_dora in Portuguese) for English, French and Portuguese;
#   Kokoro has no Swahili, Arabic, Hausa, Yoruba or Igbo, so those use the voices of the WDOS courses: Piper for Swahili and
#   Arabic, the VITS Open Bible voices (native readers) for Hausa, Yoruba and Igbo.
# It proves who it is with GitHub's signed OIDC token, so no password or key is kept anywhere.
#   python voice.py list            -> /tmp/bday_items.json and the engines needed, one per line, in /tmp/bday_engines
#   python voice.py speak <models>  -> speaks and sends them
import json, os, subprocess, sys, urllib.error, urllib.request, wave

SITE = os.environ.get('WDOS_SITE', 'https://woddicrm.org').rstrip('/')
AUD = 'woddicrm-birthday-voice'

def oidc():
    url = os.environ['ACTIONS_ID_TOKEN_REQUEST_URL'] + '&audience=' + AUD
    req = urllib.request.Request(url, headers={'Authorization': 'bearer ' + os.environ['ACTIONS_ID_TOKEN_REQUEST_TOKEN']})
    return json.load(urllib.request.urlopen(req, timeout=30))['value']

def call(path, data=None, ctype=None):
    h = {'X-GitHub-OIDC': oidc(), 'X-WDOS': '1', 'User-Agent': 'WODDI-birthday-voice'}
    if ctype: h['Content-Type'] = ctype
    req = urllib.request.Request(SITE + '/api' + path, data=data, headers=h, method='POST' if data is not None else 'GET')
    with urllib.request.urlopen(req, timeout=60) as r: return json.load(r)

def listing():
    try:
        items = call('/voice/birthdays')['items']
    except urllib.error.HTTPError as e:
        # 404: the live WDOS does not have the birthday voice service yet (released with v109.44); 5xx: the site is busy.
        # Neither is this job's fault, so it waits for the next run instead of failing and emailing the repo owner.
        if e.code == 404 or e.code >= 500:
            print(f'WDOS answered {e.code}; nothing to speak this run'); items = []
        else: raise
    except urllib.error.URLError as e:
        print('WDOS could not be reached:', e.reason, '- trying again next run'); items = []
    json.dump(items, open('/tmp/bday_items.json', 'w'))
    engines = sorted({it.get('engine', 'kokoro') for it in items})
    open('/tmp/bday_engines', 'w').write('\n'.join(engines))
    print(len(items), 'birthday greetings to speak;', 'engines:', ' '.join(engines) or 'none')

def speakers(models):
    import numpy as np, soundfile as sf
    cache = {}
    def get(it):
        eng, v, code = it.get('engine', 'kokoro'), it['voice'], it['lang']
        key = (eng, v)
        if key in cache: return cache[key]
        if eng == 'kokoro':
            from kokoro_onnx import Kokoro
            K = cache.get('K') or Kokoro(f'{models}/kokoro-v1.0.onnx', f'{models}/voices-v1.0.bin'); cache['K'] = K
            fn = lambda s: K.create(s, voice=v, speed=0.92, lang=code)
        elif eng == 'piper':
            from piper import PiperVoice, SynthesisConfig
            m = {'sw': f'{models}/vits-piper-sw_CD-lanfrica-medium/sw_CD-lanfrica-medium.onnx', 'ar': f'{models}/vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium.onnx'}[v]
            V = PiperVoice.load(m); cfg = SynthesisConfig(length_scale=0.9 if v == 'ar' else 1.0, noise_scale=0.6, noise_w_scale=0.75)
            def fn(s):
                with wave.open('/tmp/_p.wav', 'wb') as w: V.synthesize_wav(s, w, syn_config=cfg)
                return sf.read('/tmp/_p.wav')
        else:
            from huggingface_hub import hf_hub_download
            from TTS.utils.synthesizer import Synthesizer; from TTS.tts.utils.speakers import SpeakerManager
            repo = {'ha': 'multilingual-tts/VITS-OpenBible-Hausa', 'yo': 'multilingual-tts/VITS-OpenBible-Yoruba', 'ig': 'multilingual-tts/VITS-OpenBible-Igbo'}[v]
            spk = hf_hub_download(repo, 'speakers.pth')
            SY = Synthesizer(tts_checkpoint=hf_hub_download(repo, 'model_last.pth'), tts_config_path=hf_hub_download(repo, 'config.json'), tts_speakers_file=spk, use_cuda=False)
            multi = bool(getattr(SY.tts_config, 'use_speaker_embedding', False) or getattr(getattr(SY.tts_config, 'model_args', None), 'use_speaker_embedding', False))
            if multi and SY.tts_model.speaker_manager is None: SY.tts_model.speaker_manager = SpeakerManager(speaker_id_file_path=spk)
            if not multi: SY.tts_model.speaker_manager = None
            name = sorted(SY.tts_model.speaker_manager.speaker_names)[0] if multi else None
            fn = lambda s: (np.asarray(SY.tts(text=s, split_sentences=True, **({'speaker_name': name} if name else {})), dtype=np.float32), SY.output_sample_rate)
        cache[key] = fn; return fn
    return get

def speak(models):
    import numpy as np, soundfile as sf
    items = json.load(open('/tmp/bday_items.json'))
    if not items: return
    get = speakers(models); bad = 0
    for it in items:
        try:
            y, sr = get(it)(it['text']); y = np.asarray(y, dtype=np.float32)
            if y.ndim > 1: y = y.mean(axis=1)
            y = (y / (np.max(np.abs(y)) or 1) * 0.89).astype(np.float32)
            pad = np.zeros(int(sr * 0.35), dtype=np.float32); y = np.concatenate([pad, y, pad])
            sf.write('/tmp/b.wav', y, sr, subtype='PCM_16')
            subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', '/tmp/b.wav', '-ac', '1', '-ar', '24000', '-codec:a', 'libmp3lame', '-b:a', '48k', '/tmp/b.mp3'], check=True)
            call(f"/voice/birthdays/{it['id']}/{it['year']}", open('/tmp/b.mp3', 'rb').read(), 'audio/mpeg')
            print('sent', it['id'], it['year'], it.get('engine'), it['lang'], round(len(y) / sr, 1), 's')
        except Exception as e:
            bad += 1; print('failed', it.get('id'), it.get('lang'), e, file=sys.stderr)
    if bad: sys.exit(1)

if __name__ == '__main__':
    if sys.argv[1:2] == ['list']: listing()
    else: speak(sys.argv[2] if len(sys.argv) > 2 else '/tmp/m')
