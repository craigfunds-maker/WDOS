# WODDI birthday voice: asks WDOS which birthdays (today to three days ahead) still need their spoken greeting, speaks each
# one with Kokoro (af_heart, its clearest voice; ff_siwis in French, pf_dora in Portuguese) and sends the MP3 back.
# It proves who it is with GitHub's signed OIDC token, so no password or key is kept anywhere.
import json, os, subprocess, sys, urllib.request, numpy as np, soundfile as sf
from kokoro_onnx import Kokoro

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

def main(models):
    items = call('/voice/birthdays')['items']
    print(len(items), 'birthday greetings to speak')
    if not items: return
    K = Kokoro(f'{models}/kokoro-v1.0.onnx', f'{models}/voices-v1.0.bin')
    bad = 0
    for it in items:
        try:
            y, sr = K.create(it['text'], voice=it['voice'], speed=0.92, lang=it['lang'])
            y = (y / (np.max(np.abs(y)) or 1) * 0.89).astype(np.float32)
            pad = np.zeros(int(sr * 0.35), dtype=np.float32); y = np.concatenate([pad, y, pad])
            sf.write('/tmp/b.wav', y, sr, subtype='PCM_16')
            subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', '/tmp/b.wav', '-ac', '1', '-ar', '24000', '-codec:a', 'libmp3lame', '-b:a', '48k', '/tmp/b.mp3'], check=True)
            call(f"/voice/birthdays/{it['id']}/{it['year']}", open('/tmp/b.mp3', 'rb').read(), 'audio/mpeg')
            print('sent', it['id'], it['year'], round(len(y) / sr, 1), 's')
        except Exception as e:
            bad += 1; print('failed', it.get('id'), e, file=sys.stderr)
    if bad: sys.exit(1)

if __name__ == '__main__': main(sys.argv[1] if len(sys.argv) > 1 else '/tmp/m')
