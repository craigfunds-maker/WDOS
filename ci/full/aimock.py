# a stand-in AI provider for local tests only: OpenAI-shaped chat and embeddings, and a log of what it was sent
import json,sys,hashlib
from http.server import BaseHTTPRequestHandler,HTTPServer
LOG='/tmp/aimock.log'
class H(BaseHTTPRequestHandler):
    def log_message(self,*a): pass
    def sfu(self,b):
        # a stand-in for Cloudflare Realtime SFU (v109.27 tests): sessions, publish, pull, renegotiate, close
        import uuid
        if self.headers.get('Authorization')!='Bearer test-sfu-token': return 401,{'errorCode':'unauthorized'}
        p=self.path
        if p.endswith('/sessions/new'): return 201,{'sessionId':uuid.uuid4().hex}
        if p.endswith('/tracks/new'):
            if b.get('sessionDescription'): return 200,{'sessionDescription':{'type':'answer','sdp':'v=0 mock-answer'},'tracks':[{'mid':t.get('mid'),'trackName':t.get('trackName')} for t in b.get('tracks',[])]}
            return 200,{'requiresImmediateRenegotiation':True,'sessionDescription':{'type':'offer','sdp':'v=0 mock-offer'},'tracks':[{'mid':str(i+1),'trackName':t.get('trackName'),'sessionId':t.get('sessionId')} for i,t in enumerate(b.get('tracks',[]))]}
        if p.endswith('/renegotiate'): return 200,{}
        if p.endswith('/tracks/close'): return 200,{'tracks':[{'mid':t.get('mid')} for t in b.get('tracks',[])]}
        return 404,{'errorCode':'not_found'}
    def do_PUT(self): self.do_POST()
    def do_POST(self):
        b=json.loads(self.rfile.read(int(self.headers.get('Content-Length',0))) or b'{}'); open(LOG,'a').write(json.dumps({'path':self.path,'method':self.command,'body':b})+'\n')
        if '/v1/apps/' in self.path:
            c,r=self.sfu(b); d=json.dumps(r).encode(); self.send_response(c); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d); return
        if open('/tmp/aimock.mode').read().strip()=='down' and 'smtp' not in self.path: self.send_response(503); self.end_headers(); self.wfile.write(b'{"error":{"message":"busy"}}'); return
        if 'smtp' in self.path:
            d=b'{"messageId":"mock"}'; self.send_response(201); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d); return
        if self.path.endswith('/chat/completions'):
            sysm=b['messages'][0]['content']; usr=b['messages'][1]['content']
            if 'words printed on a WODDI meeting flier into' in sysm:
                lang=sysm.split('flier into ')[1].split('.')[0]; src=json.loads(usr)
                out=json.dumps([('['+lang[:2].upper()+'] '+x) for x in src],ensure_ascii=False)
                r={'choices':[{'message':{'role':'assistant','content':out}}]}; d=json.dumps(r).encode(); self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d); return
            if 'You translate a WODDI meeting flier into' in sysm:
                lang=sysm.split('flier into ')[1].split('.')[0]; src=json.loads(usr)
                out=json.dumps({'headline':'['+lang+'] '+src.get('headline',''),'body':'['+lang+'] '+src.get('body','')},ensure_ascii=False)
                r={'choices':[{'message':{'role':'assistant','content':out}}]}; d=json.dumps(r).encode(); self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d); return
            if 'You check volunteer work' in sysm:
                out=json.dumps({'verdict':'needs_correction','summary':'The attendance list asked for is missing.','points':['Attach the attendance list for the meeting.','Say how many women attended.']}) if 'MOCK-INCOMPLETE' in usr else json.dumps({'verdict':'ok','summary':'Complete.','points':[]})
                r={'choices':[{'message':{'role':'assistant','content':out}}]}; d=json.dumps(r).encode(); self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d); return
            out='ready' if 'one word' in sysm else ('DONE\n- Mock digest line.\nSTUCK\n- Mock.\nREPEATING ISSUES\n- Mock.' if 'DONE' in sysm else 'GROWTH\n- Mock.\nACTIVITY\n- Mock.\nCONCERNS\n- Mock.\nWINS\n- Mock.')
            r={'choices':[{'message':{'role':'assistant','content':out}}]}
        else:
            def vec(t):
                v=[0.0]*16
                for w in t.lower().replace('.',' ').split():
                    w={'money':'finance','budget':'finance','funds':'finance','conduct':'behaviour','behave':'behaviour'}.get(w,w); v[int(hashlib.md5(w.encode()).hexdigest(),16)%16]+=1.0
                return v
            r={'data':[{'embedding':vec(t)} for t in b['input']]}
        d=json.dumps(r).encode(); self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d)
open('/tmp/aimock.mode','w').write('up'); HTTPServer(('127.0.0.1',8099),H).serve_forever()
