# Narrate every course lesson in the learner's language, sentence by sentence, with caption timings for the lesson video.
# Kokoro v1.0 (full model, clearest voices: af_heart, ff_siwis, pf_dora) for en/fr/pt; Piper for sw/ar (Kokoro has no
# Swahili or Arabic). Output: assets/courses/voice/<lang>/cNN-M.mp3 and cNN-intro.mp3, plus cues.json with sentence times.
import sys, os, re, json, wave, subprocess, numpy as np, soundfile as sf
ROOT='/home/claude/wdos-php/assets/courses'
LINK={'en':{'lesson':'Lesson','course':'Course','example':'Here is an example.','practice':'Your practice:'},
      'fr':{'lesson':'Leçon','course':'Cours','example':'Voici un exemple.','practice':'Votre exercice :'},
      'pt':{'lesson':'Lição','course':'Curso','example':'Eis um exemplo.','practice':'A sua prática:'},
      'sw':{'lesson':'Somo','course':'Kozi','example':'Huu ni mfano.','practice':'Zoezi lako:'},
      'ar':{'lesson':'الدرس','course':'الدورة','example':'إليكِ مثالًا.','practice':'تمرينك:'}}
def sentences(t): return [s.strip() for s in re.split(r'(?<=[.!?؟])\s+', t) if s.strip()]
def run(L):
    D=json.load(open(f'{ROOT}/{L}/index.json')); out=f'{ROOT}/voice/{L}'; os.makedirs(out,exist_ok=True); W=LINK[L]
    cues=json.load(open(f'{out}/cues.json')) if os.path.exists(f'{out}/cues.json') and not os.environ.get('REDO') else {}
    if L in ('en','fr','pt'):
        from kokoro_onnx import Kokoro; K=Kokoro('/home/claude/tts/kokoro-v1.0.onnx','/home/claude/tts/voices-v1.0.bin')
        v,code={'en':('af_heart','en-us'),'fr':('ff_siwis','fr-fr'),'pt':('pf_dora','pt-br')}[L]
        say=lambda s: K.create(s,voice=v,speed=1.0,lang=code)
    else:
        from piper import PiperVoice, SynthesisConfig
        m={'sw':'/home/claude/tts/vits-piper-sw_CD-lanfrica-medium/sw_CD-lanfrica-medium.onnx','ar':'/home/claude/tts/vits-piper-ar_JO-kareem-medium/ar_JO-kareem-medium.onnx'}[L]
        V=PiperVoice.load(m); cfg=SynthesisConfig(length_scale=0.82 if L=='ar' else 0.95,noise_scale=0.6,noise_w_scale=0.75)
        def say(s):
            with wave.open(f'/tmp/_c_{L}.wav','wb') as w: V.synthesize_wav(s,w,syn_config=cfg)
            return sf.read(f'/tmp/_c_{L}.wav')
    jobs=[]
    for c in D['courses']:
        jobs.append((f"c{c['no']:02d}-intro", [f"{W['course']} {c['no']}. {c['title']}."]+sentences(c['purpose'])))
        for m in c['modules']:
            jobs.append((f"c{c['no']:02d}-{m['no']}", [f"{W['lesson']} {c['no']}.{m['no']}. {m['title']}."]+sentences(m['concept'])+[W['example']]+sentences(m['scenario'])+[W['practice']]+sentences(m['exercise'])))
    for k,ss in jobs:
        if k in cues and os.path.exists(f'{out}/{k}.mp3'): continue
        parts=[]; t=0.0; cue=[]; sr=24000
        for i,s in enumerate(ss):
            y,sr=say(s); y=np.asarray(y,dtype=np.float32)
            if y.ndim>1: y=y.mean(axis=1)
            nz=np.where(np.abs(y)>0.01)[0]; y=y[max(0,nz[0]-int(0.03*sr)):nz[-1]+int(0.05*sr)] if len(nz) else y
            gap=np.zeros(int(sr*(0.55 if i==0 else 0.32)),dtype=np.float32)
            cue.append([round(t,2),s]); parts+= [y,gap]; t+=(len(y)+len(gap))/sr
        y=np.concatenate(parts); y=y/(np.max(np.abs(y)) or 1)*0.89
        sf.write(f'/tmp/_cc_{L}.wav',y,sr,subtype='PCM_16')
        subprocess.run(['ffmpeg','-y','-loglevel','error','-i',f'/tmp/_cc_{L}.wav','-ac','1','-ar','24000','-b:a','48k',f'{out}/{k}.mp3'],check=True)
        cues[k]={'d':round(t,2),'c':cue}
        json.dump(cues,open(f'{out}/cues.json','w'),ensure_ascii=False,separators=(',',':'))
    print(L,'done',len(jobs),flush=True)
for L in (sys.argv[1:] or ['en']): run(L)
