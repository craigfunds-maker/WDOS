# a stand-in AI provider for local tests only: OpenAI-shaped chat and embeddings, and a log of what it was sent
import json,sys,hashlib
from http.server import BaseHTTPRequestHandler,HTTPServer
LOG='/tmp/aimock.log'
class H(BaseHTTPRequestHandler):
    def log_message(self,*a): pass
    def do_POST(self):
        b=json.loads(self.rfile.read(int(self.headers.get('Content-Length',0))) or b'{}'); open(LOG,'a').write(json.dumps({'path':self.path,'body':b})+'\n')
        if open('/tmp/aimock.mode').read().strip()=='down' and 'smtp' not in self.path: self.send_response(503); self.end_headers(); self.wfile.write(b'{"error":{"message":"busy"}}'); return
        if 'smtp' in self.path:
            d=b'{"messageId":"mock"}'; self.send_response(201); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(d); return
        if self.path.endswith('/chat/completions'):
            sysm=b['messages'][0]['content']; out='ready' if 'one word' in sysm else ('DONE\n- Mock digest line.\nSTUCK\n- Mock.\nREPEATING ISSUES\n- Mock.' if 'DONE' in sysm else 'GROWTH\n- Mock.\nACTIVITY\n- Mock.\nCONCERNS\n- Mock.\nWINS\n- Mock.')
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
