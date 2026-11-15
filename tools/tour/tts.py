# Voice every tour line in five languages: Kokoro for en/fr/pt, Piper for sw/ar. mp3, mono, 32 kbps (small for low data).
import sys, os, wave, subprocess, numpy as np, soundfile as sf
sys.path.insert(0,'/home/claude/tour'); from texts import GREET, LEADER, ENVOY
OUT='/home/claude/wdos-php/assets/tour/voice'
ONLY=sys.argv[1:] or ['en','fr','pt','sw','ar']
jobs={L:[('hello',GREET['hello'][L]),('glad',GREET['glad'][L])]+[('leader-'+i,t[L]) for i,t in LEADER]+[('envoy-'+i,t[L]) for i,t in ENVOY] for L in ONLY}
def mp3(y,sr,path):
    y=y.astype(np.float32); y=y/(np.max(np.abs(y)) or 1)*0.89
    sf.write('/tmp/_t.wav',y,sr,subtype='PCM_16')
    subprocess.run(['ffmpeg','-y','-loglevel','error','-i','/tmp/_t.wav','-af','silenceremove=start_periods=1:start_threshold=-50dB,areverse,silenceremove=start_periods=1:start_threshold=-50dB,areverse,apad=pad_dur=0.25','-ac','1','-ar','24000','-b:a','32k',path],check=True)
K=None
for L in ONLY:
    os.makedirs(f'{OUT}/{L}',exist_ok=True)
    if L in ('en','fr','pt'):
        if K is None:
            from kokoro_onnx import Kokoro; K=Kokoro('/home/claude/tts/kokoro.onnx','/home/claude/tts/voices.bin')
        v,code={'en':('bf_emma','en-gb'),'fr':('ff_siwis','fr-fr'),'pt':('pf_dora','pt-br')}[L]
        for k,txt in jobs[L]:
            if os.path.exists(f'{OUT}/{L}/{k}.mp3') and not os.environ.get('REDO'): continue
            y,sr=K.create(txt,voice=v,speed=0.92 if L=='en' else 0.95,lang=code); mp3(y,sr,f'{OUT}/{L}/{k}.mp3')
    else:
        from piper import PiperVoice, SynthesisConfig
        m={'sw':'/home/claude/tts/vits-piper-sw_CD-lanfrica-medium/sw_CD-lanfrica-medium.onnx','ar':'/home/claude/tts/vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium.onnx'}[L]
        V=PiperVoice.load(m); cfg=SynthesisConfig(length_scale=0.88,noise_scale=0.55,noise_w_scale=0.7) if L=='ar' else None
        for k,txt in jobs[L]:
            if os.path.exists(f'{OUT}/{L}/{k}.mp3') and not os.environ.get('REDO'): continue
            with wave.open('/tmp/_p.wav','wb') as w: V.synthesize_wav(txt,w,syn_config=cfg)
            y,sr=sf.read('/tmp/_p.wav'); mp3(y,sr,f'{OUT}/{L}/{k}.mp3')
    print(L,'done',len(jobs[L]))
