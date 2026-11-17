# Voice every tour line in five languages: Kokoro v1.0 (full model) for en/fr/pt, Piper for sw/ar (Kokoro has no Swahili or Arabic). mp3, mono, 48 kbps.
import sys, os, wave, subprocess, numpy as np, soundfile as sf
sys.path.insert(0,'/home/claude/tour'); from texts import GREET, LEADER, ENVOY, MEMBER
OUT='/home/claude/wdos-php/assets/tour/voice'
ONLY=sys.argv[1:] or ['en','fr','pt','sw','ar']
jobs={L:[('hello',GREET['welcome'][L]),('glad',GREET['glad'][L])]+[('leader-'+i,t[L]) for i,t in LEADER]+[('envoy-'+i,t[L]) for i,t in ENVOY]+[('member-'+i,t[L]) for i,t in MEMBER] for L in ONLY}
def mp3(y,sr,path):
    y=y.astype(np.float32); y=y/(np.max(np.abs(y)) or 1)*0.89
    sf.write('/tmp/_t.wav',y,sr,subtype='PCM_16')
    subprocess.run(['ffmpeg','-y','-loglevel','error','-i','/tmp/_t.wav','-af','silenceremove=start_periods=1:start_threshold=-50dB,areverse,silenceremove=start_periods=1:start_threshold=-50dB,areverse,apad=pad_dur=0.15','-ac','1','-ar','24000','-b:a','48k',path],check=True)
K=None
# the system's name is said "W-DOS" (the letter W, then DOS as one word); lines naming it are voiced again with WREDO=1
SAYW={'en':'W-Doss','fr':'W-Dosse','pt':'W-Dós','sw':'Dablyu-Dos','ar':'دبليو-دوس'}
if os.environ.get('WREDO'):
    for L in ONLY:
        for k,txt in jobs[L]:
            if 'WDOS' in txt and os.path.exists(f'{OUT}/{L}/{k}.mp3'): os.remove(f'{OUT}/{L}/{k}.mp3')
jobs={L:[(k,t.replace('WDOS',SAYW[L])) for k,t in jobs[L]] for L in ONLY}
for L in ONLY:
    os.makedirs(f'{OUT}/{L}',exist_ok=True)
    if L in ('en','fr','pt'):
        if K is None:
            from kokoro_onnx import Kokoro; K=Kokoro('/home/claude/tts/kokoro-v1.0.onnx','/home/claude/tts/voices-v1.0.bin')   # full model: the int8 one sounded robotic
        v,code={'en':('af_heart','en-us'),'fr':('ff_siwis','fr-fr'),'pt':('pf_dora','pt-br')}[L]
        for k,txt in jobs[L]:
            if os.path.exists(f'{OUT}/{L}/{k}.mp3') and not os.environ.get('REDO'): continue
            y,sr=K.create(txt,voice=v,speed=1.0,lang=code); mp3(y,sr,f'{OUT}/{L}/{k}.mp3')
    else:
        from piper import PiperVoice, SynthesisConfig
        m={'sw':'/home/claude/tts/vits-piper-sw_CD-lanfrica-medium/sw_CD-lanfrica-medium.onnx','ar':'/home/claude/tts/vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium.onnx'}[L]
        V=PiperVoice.load(m); cfg=SynthesisConfig(length_scale=0.82,noise_scale=0.6,noise_w_scale=0.75) if L=='ar' else SynthesisConfig(length_scale=0.95,noise_scale=0.6,noise_w_scale=0.75)
        for k,txt in jobs[L]:
            if os.path.exists(f'{OUT}/{L}/{k}.mp3') and not os.environ.get('REDO'): continue
            with wave.open('/tmp/_p.wav','wb') as w: V.synthesize_wav(txt,w,syn_config=cfg)
            y,sr=sf.read('/tmp/_p.wav'); mp3(y,sr,f'{OUT}/{L}/{k}.mp3')
    print(L,'done',len(jobs[L]))
