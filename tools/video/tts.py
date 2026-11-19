# Re-voice chosen guide steps. wav index n = GUIDE[n-1]; index 0 is the intro.
import sys, numpy as np, soundfile as sf, subprocess, wave
sys.path.insert(0,'/home/claude/vid2'); from texts import GUIDE
steps=[int(x) for x in sys.argv[1].split(',')]
def rs(y,sr):
    if sr==24000: return y
    sf.write('/tmp/_i.wav',y,sr); subprocess.run(['ffmpeg','-y','-loglevel','error','-i','/tmp/_i.wav','-ar','24000','/tmp/_o.wav'],check=True); return sf.read('/tmp/_o.wav')[0]
def norm(y):
    y=y.astype(np.float32); pk=np.max(np.abs(y)) or 1; return (y/pk*0.89).astype(np.float32)
from kokoro_onnx import Kokoro
K=Kokoro('/home/claude/tts/kokoro.onnx','/home/claude/tts/voices.bin')
for L,v,code in [('en','bf_emma','en-gb'),('fr','ff_siwis','fr-fr'),('pt','pf_dora','pt-br')]:
    for n in steps:
        y,sr=K.create(GUIDE[L][n-1],voice=v,speed=0.92 if L=='en' else 0.95,lang=code)
        y=rs(y,sr)
        sf.write(f'/home/claude/vid2/wav/{L}-{n}.wav',norm(y),24000,subtype='PCM_16'); print(L,n,round(len(y)/24000,1))
from piper import PiperVoice
for L,m in [('sw','/home/claude/tts/vits-piper-sw_CD-lanfrica-medium/sw_CD-lanfrica-medium.onnx'),('ar','/home/claude/tts/vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium.onnx')]:
    V=PiperVoice.load(m)
    for n in steps:
        raw=f'/home/claude/vid2/wav/{L}-{n}.raw.wav'
        with wave.open(raw,'wb') as w: V.synthesize_wav(GUIDE[L][n-1],w)
        y,sr=sf.read(raw); y=rs(y.astype(np.float32),sr)
        sf.write(f'/home/claude/vid2/wav/{L}-{n}.wav',norm(y),24000,subtype='PCM_16'); print(L,n,round(len(y)/24000,1))
