# The narrated join guide, one per language, built from the real WDOS screens in that language.
from PIL import Image, ImageDraw, ImageFont
import subprocess, sys, soundfile as sf, numpy as np
sys.path.insert(0,'/home/claude/vid2'); from texts import *
W,H,FPS=720,1280,8
BG=(24,10,20); MAG=(212,0,106); LIME=(124,181,24); INK=(250,244,248)
logo=Image.open('/home/claude/wdos-php/assets/logos/woddi.png').convert('RGBA')
CAP={'en':{'t':'How to join the WODDI Digital Operating System','s':'WDOS, step by step','m':'No Woman Is Left Out','e':'Choose your language and begin.',
  'steps':[('Choose your language','Pick it from the list, then press Continue.'),('Choose your network','WGMN: 30 and above. WNNN: 18 to 29. Then press Continue.'),('Type your details','Name, date of birth and phone number. Email if you have one.'),('Say where you live','Country, state, LGA, ward and chapter.'),('Agree and choose a PIN','At least six letters or numbers. No code is sent to you.'),('You are a member','Your seven days begin. One small step each day.'),('Need help?','Forgot password on the sign-in page, or open Help.')]},
 'fr':{'t':'Comment rejoindre le système numérique de WODDI','s':'WDOS, étape par étape','m':"Aucune femme n'est laissée de côté",'e':'Choisissez votre langue et commencez.',
  'steps':[('Choisissez votre langue','Dans la liste, puis appuyez sur Continuer.'),('Choisissez votre réseau','WGMN : 30 ans et plus. WNNN : 18 à 29 ans. Puis Continuer.'),('Écrivez vos informations','Nom, date de naissance et téléphone. E-mail si vous en avez un.'),('Dites où vous vivez','Pays, région, district, quartier et chapitre.'),('Acceptez et choisissez un PIN','Au moins six lettres ou chiffres. Aucun code ne vous est envoyé.'),('Vous êtes membre','Vos sept jours commencent. Une petite étape par jour.'),("Besoin d'aide ?","Mot de passe oublié sur la page de connexion, ou ouvrez Aide.")]},
 'pt':{'t':'Como aderir ao sistema digital da WODDI','s':'WDOS, passo a passo','m':'Nenhuma mulher fica para trás','e':'Escolha a sua língua e comece.',
  'steps':[('Escolha a sua língua','Na lista, e depois toque em Continuar.'),('Escolha a sua rede','WGMN: 30 anos ou mais. WNNN: 18 a 29 anos. Depois Continuar.'),('Escreva os seus dados','Nome, data de nascimento e telefone. E-mail se tiver.'),('Diga onde vive','País, estado, distrito, bairro e capítulo.'),('Aceite e escolha um PIN','Pelo menos seis letras ou números. Nenhum código é enviado.'),('Já é membro','Os seus sete dias começam. Um pequeno passo por dia.'),('Precisa de ajuda?','Esqueci-me da palavra-passe na página de entrada, ou abra Ajuda.')]}}
CAP.update({
 'sw':{'t':'Jinsi ya kujiunga na mfumo wa kidijitali wa WODDI','s':'WDOS, hatua kwa hatua','m':'Hakuna mwanamke anayeachwa nyuma','e':'Chagua lugha yako uanze.',
  'steps':[('Chagua lugha yako','Kwenye orodha, kisha bonyeza Endelea.'),('Chagua mtandao wako','WGMN: miaka 30 na zaidi. WNNN: miaka 18 hadi 29. Kisha Endelea.'),('Andika taarifa zako','Jina, tarehe ya kuzaliwa na simu. Barua pepe kama unayo.'),('Sema unapoishi','Nchi, jimbo, wilaya, kata na tawi.'),('Kubali na uchague PIN','Angalau herufi au namba sita. Hakuna msimbo unaotumwa.'),('Wewe ni mwanachama','Siku zako saba zinaanza. Hatua moja ndogo kila siku.'),('Unahitaji msaada?','Umesahau nenosiri kwenye ukurasa wa kuingia, au fungua Msaada.')]},
 'ar':{'t':'كيف تنضمين إلى نظام WODDI الرقمي','s':'WDOS، خطوة بخطوة','m':'لا تُترك أي امرأة','e':'اختاري لغتك وابدئي.',
  'steps':[('اختاري لغتك','من القائمة، ثم اضغطي متابعة.'),('اختاري شبكتك','WGMN: من 30 فما فوق. WNNN: من 18 إلى 29. ثم متابعة.'),('اكتبي بياناتك','الاسم، تاريخ الميلاد والهاتف. والبريد إن وُجد.'),('حدّدي مكان سكنك','البلد، الولاية، المنطقة، الحي والفرع.'),('وافقي واختاري رقماً سرياً','ستة أحرف أو أرقام على الأقل. لا يُرسل أي رمز.'),('أنتِ الآن عضوة','تبدأ أيامك السبعة. خطوة صغيرة كل يوم.'),('تحتاجين مساعدة؟','نسيت كلمة المرور في صفحة الدخول، أو افتحي المساعدة.')]},
 'ha':{'t':'Yadda ake shiga Tsarin Aiki na Dijital na WODDI','s':'WDOS, mataki-mataki','m':'Ba a bar wata mace a baya ba','e':'Zaɓi harshenki ki fara.',
  'steps':[('Zaɓi harshenki','Daga jerin, sannan ki danna Ci gaba.'),('Zaɓi ƙungiyarki','WGMN: shekaru 30 zuwa sama. WNNN: 18 zuwa 29. Sannan Ci gaba.'),('Rubuta bayananki','Suna, ranar haihuwa da waya. Imel idan kina da shi.'),('Faɗi inda kike zaune','Ƙasa, jiha, ƙaramar hukuma, mazaba da rukuni.'),('Yarda ki zaɓi PIN','Aƙalla haruffa ko lambobi shida. Ba a aika wata lamba.'),('Kin zama memba','Kwanakinki bakwai sun fara. Ƙaramin mataki ɗaya kowace rana.'),('Kina buƙatar taimako?','An manta kalmar sirri a shafin shiga, ko ki buɗe Taimako.')]},
 'yo':{'t':'Bí a ṣe ń darapọ̀ mọ́ Ètò Iṣẹ́ Oní-nọ́ńbà WODDI','s':'WDOS, ní ìgbésẹ̀ kọ̀ọ̀kan','m':'Kò sí obìnrin tí a fi sílẹ̀','e':'Yan èdè rẹ kí o sì bẹ̀rẹ̀.',
  'steps':[('Yan èdè rẹ','Nínú àtòjọ, lẹ́yìn náà tẹ Tẹ̀síwájú.'),('Yan ẹgbẹ́ rẹ','WGMN: ọmọ ọdún 30 sókè. WNNN: 18 sí 29. Lẹ́yìn náà Tẹ̀síwájú.'),('Tẹ àwọn àlàyé rẹ','Orúkọ, ọjọ́ ìbí àti fóònù. Ímeèlì bí o bá ní.'),('Sọ ibi tí o ń gbé','Orílẹ̀-èdè, ìpínlẹ̀, ìjọba ìbílẹ̀, wọ́ọ̀dù àti ẹ̀ka.'),('Gbà kí o sì yan PIN','Ó kéré tán lẹ́tà tàbí nọ́ńbà mẹ́fà. A kì í fi kóòdù ránṣẹ́.'),('O ti di ọmọ ẹgbẹ́','Ọjọ́ méje rẹ bẹ̀rẹ̀. Ìgbésẹ̀ kékeré kan lójoojúmọ́.'),('Ṣé o nílò ìrànlọ́wọ́?','Mo gbàgbé ọ̀rọ̀ aṣínà ní ojú ìwé ìwọlé, tàbí ṣí Ìrànlọ́wọ́.')]},
 'ig':{'t':'Otu esi abanye na Usoro Ọrụ Dijitalụ nke WODDI','s':'WDOS, nzọụkwụ site na nzọụkwụ','m':"Ọ dịghị nwanyị a hapụrụ n'azụ",'e':'Họrọ asụsụ gị ma malite.',
  'steps':[('Họrọ asụsụ gị',"Na ndepụta, mgbe ahụ pịa Gaa n'ihu."),('Họrọ otu gị',"WGMN: afọ 30 gbagowe. WNNN: 18 ruo 29. Mgbe ahụ Gaa n'ihu."),('Dee nkọwa gị','Aha, ụbọchị ọmụmụ na ekwentị. Email ma i nwere.'),('Kwuo ebe i bi','Mba, steeti, ọchịchị ime obodo, wọọdụ na ngalaba.'),('Kwenye ma họrọ PIN','Opekata mpe mkpụrụ edemede ma ọ bụ ọnụọgụ isii. A naghị eziga koodu.'),('Ị bụzi onye otu','Ụbọchị asaa gị amalitela. Otu obere nzọụkwụ kwa ụbọchị.'),('Ị chọrọ enyemaka?','Echefuru m okwuntughe na peeji mbanye, ma ọ bụ meghee Enyemaka.')]}})
import arabic_reshaper
from bidi.algorithm import get_display
CUR='en'
def F(s,w='Bold'):
    if CUR=='ar': return ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' if w=='Bold' else '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', int(s*0.92))
    return ImageFont.truetype(f'/usr/share/fonts/opentype/inter/Inter-{w}.otf', s)
from PIL import features
RAQM=features.check('raqm')
def shp(t): return t if (CUR!='ar' or RAQM) else get_display(arabic_reshaper.reshape(t))
def base():
    im=Image.new('RGB',(W,H),BG); d=ImageDraw.Draw(im)
    for y in range(0,360): d.line([(0,y),(W,y)],fill=tuple(int(BG[i]+(c-BG[i])*(1-y/360)*0.28) for i,c in enumerate(MAG)))
    return im
def wrap(d,text,font,maxw):
    out=[];line=''
    for w in text.split():
        t=(line+' '+w).strip()
        if d.textlength(t,font=font)<=maxw: line=t
        else: out.append(line); line=w
    out.append(line); return out
def step(L,i,title,sub):
    im=base(); d=ImageDraw.Draw(im)
    s=Image.open(f'/home/claude/vid2/{L}-s{i}.png').convert('RGB'); sw=540; sh=int(s.height*sw/s.width); s=s.resize((sw,sh),Image.LANCZOS)
    mask=Image.new('L',(sw,sh),0); ImageDraw.Draw(mask).rounded_rectangle([0,0,sw,sh],34,fill=255)
    x=(W-sw)//2; y=26; d.rounded_rectangle([x-6,y-6,x+sw+6,y+sh+6],40,fill=(60,30,50)); im.paste(s,(x,y),mask)
    cy=y+sh+26; d.rounded_rectangle([24,cy,W-24,H-34],28,fill=(40,18,34),outline=(90,45,75),width=2)
    d.ellipse([48,cy+22,112,cy+86],fill=MAG); n=str(i).rstrip('b'); f=F(36); d.text((80-d.textlength(n,font=f)/2,cy+32),n,font=f,fill='white')
    ft=F(36 if d.textlength(shp(title),font=F(36))<W-190 else 29); tl=wrap(d,title,ft,W-190)
    for q,ln in enumerate(tl[:2]): d.text((130,cy+(30 if len(tl)==1 else 14)+q*36),shp(ln),font=ft,fill=INK)
    yy=cy+102
    for ln in wrap(d,sub,F(28,'Medium'),W-110): d.text((52,yy),shp(ln),font=F(28,'Medium'),fill=(225,205,218)); yy+=38
    return im
def card(lines,small):
    im=base(); d=ImageDraw.Draw(im); lg=logo.resize((300,int(logo.height*300/logo.width)),Image.LANCZOS)
    d.rounded_rectangle([(W-360)//2,300,(W+360)//2,300+lg.height+50],28,fill='white'); im.paste(lg,((W-300)//2,325),lg)
    y=300+lg.height+110
    for ln,sz,col in lines:
        for l in wrap(d,ln,F(sz),W-100): d.text(((W-d.textlength(shp(l),font=F(sz)))/2,y),shp(l),font=F(sz),fill=col); y+=int(sz*1.25)
        y+=18
    d.text(((W-d.textlength(shp(small),font=F(26,'Medium')))/2,H-150),shp(small),font=F(26,'Medium'),fill=(210,190,205)); return im
VOICED=['en','fr','pt','sw','ar']
for L in sys.argv[1:] or ['en','fr','pt','sw','ar','ha','yo','ig']:
    CUR=L; c=CAP[L]; voiced=L in VOICED; sr=24000
    if voiced:
        wav=[sf.read(f'/home/claude/vid2/wav/{L}-{i}.wav') for i in range(8)]; sr=wav[0][1]; durs=[len(w[0])/sr+(0.6 if i==0 else 0.5) for i,w in enumerate(wav)]
        audio=np.concatenate([np.concatenate([w[0],np.zeros(int(sr*(0.6 if i==0 else 0.5)),dtype=w[0].dtype)]) for i,w in enumerate(wav)]+[np.zeros(int(sr*3.0),dtype=wav[0][0].dtype)])
    else:
        durs=[4.0]+[8.0]*7; audio=np.zeros(int(sr*(sum(durs)+3.0)),dtype=np.float32)       # no voice exists for this language yet: captions only
    slides=[(card([(c['t'],44,INK),(c['s'],32,(235,215,228))],c['m']),durs[0])]
    slides+=[(step(L,i+1,*c['steps'][i]),durs[i+1]) for i in range(7)]
    # step 4 shows the world map first, then the location lists (country, state, LGA, ward, chapter)
    import os
    if os.path.exists(f'/home/claude/vid2/{L}-s4b.png'):
        k=4; im4,d4=slides[k]; slides[k]=(im4,d4*0.4); slides.insert(k+1,(step(L,'4b',*c['steps'][3]),d4*0.6))
    slides+=[(card([(c['m'],48,INK),(c['e'],34,(190,230,120))],'wdos'),3.0)]
    sf.write(f'/home/claude/vid2/{L}.wav',audio,sr); slides[0][0].save(f'/home/claude/wdos-php/assets/video/wdos-join-guide-{L}.jpg',quality=82)
    counts=[]; acc=0.0; done_frames=0
    for _,d in slides: acc+=d; n=round(acc*FPS)-done_frames; counts.append(n); done_frames+=n
    total=sum(counts); out=f'/home/claude/wdos-php/assets/video/wdos-join-guide-{L}.mp4'
    cmd=['ffmpeg','-y','-loglevel','error','-f','rawvideo','-pix_fmt','rgb24','-s',f'{W}x{H}','-r',str(FPS),'-i','-']+(['-i',f'/home/claude/vid2/{L}.wav'] if voiced else [])+['-c:v','libx264','-preset','veryslow','-crf','30','-pix_fmt','yuv420p','-g','48']+(['-c:a','aac','-b:a','48k','-ac','1','-shortest'] if voiced else ['-an'])+['-movflags','+faststart',out]
    p=subprocess.Popen(cmd,stdin=subprocess.PIPE)
    k0=0; XF=3
    for k,(im,_) in enumerate(slides):
        n=counts[k]
        for f in range(n):
            fr=im
            if k+1<len(slides) and f>=n-XF: fr=Image.blend(im,slides[k+1][0],(f-(n-XF)+1)/(XF+1))
            fr=fr.copy(); d=ImageDraw.Draw(fr); t=(k0+f)/total; d.rectangle([0,H-10,W,H],fill=(60,30,50)); d.rectangle([0,H-10,int(W*t),H],fill=LIME); p.stdin.write(fr.tobytes())
        k0+=n
    p.stdin.close(); p.wait(); print(L, round(total/FPS,1),'s', 'voice' if voiced else 'captions only')
