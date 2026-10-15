"""Start the PHP development server against a temporary DB and test HTTP behavior."""
import json, os, subprocess, tempfile, time, urllib.request, urllib.error, socket
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN", "php")
def run():
    with tempfile.TemporaryDirectory() as d:
        env = dict(os.environ, INVENTORY_DB=str(Path(d)/"stock.sqlite"))
        subprocess.run([PHP, "bin/seed.php"], cwd=ROOT, env=env, check=True)
        with socket.socket() as sock:
            sock.bind(("127.0.0.1",0)); port=sock.getsockname()[1]
        base = f"http://127.0.0.1:{port}"
        with open(Path(d)/"server.log", "w") as log:
            server=subprocess.Popen([PHP,"-S",f"127.0.0.1:{port}","public/router.php"],cwd=ROOT,env=env,stdout=log,stderr=log)
            def request(path, data=None, raw=None):
                body=raw if raw is not None else (json.dumps(data).encode() if data is not None else None)
                req=urllib.request.Request(base+path,data=body,headers={"Content-Type":"application/json"})
                try:
                    with urllib.request.urlopen(req,timeout=2) as r: return r.status,r.read().decode()
                except urllib.error.HTTPError as e: return e.code,e.read().decode()
            try:
                for _ in range(50):
                    try:
                        if request('/health')[0]==200: break
                    except OSError: time.sleep(.05)
                else: raise RuntimeError('server failed to start')
                checks=0
                def check(ok):
                    nonlocal checks
                    checks+=1
                    if not ok: raise AssertionError(f'HTTP check {checks} failed')
                order={'request_id':'http-1','sku':'DEMO-SHOE','quantity':8}
                check(request('/reservations',order)[0]==201)
                check(request('/reservations',order)[0]==200)
                check(request('/reservations',dict(order,quantity=9))[0]==409)
                check(request('/reservations',dict(order,request_id='oversell',quantity=3))[0]==409)
                check(request('/reservations',dict(order,request_id='float',quantity=1.5))[0]==400)
                check(request('/reservations',raw=b'{broken')[0]==400)
                check(request('/reservations',raw=b'x'*8193)[0]==400)
                check(request('/missing')[0]==404)
                stock=json.loads(request('/stock')[1]);check(next(x for x in stock if x['sku']=='DEMO-SHOE')['quantity']==2)
                metrics=request('/metrics')[1];check('inventory_conflicts_total 2' in metrics)
                exported=Path(d)/'metrics.txt';exported.write_text(metrics)
                alert=subprocess.run([PHP,'bin/check-alerts.php',str(exported)],cwd=ROOT,capture_output=True,text=True)
                check(alert.returncode==1 and 'LOW_STOCK' in alert.stdout)
                exported.write_text('inventory_available{sku="DEMO"} 9\n')
                check(subprocess.run([PHP,'bin/check-alerts.php',str(exported)],cwd=ROOT,capture_output=True).returncode==0)
                exported.write_text('broken')
                check(subprocess.run([PHP,'bin/check-alerts.php',str(exported)],cwd=ROOT,capture_output=True).returncode==2)
                print(f'PASS: {checks} HTTP/alert checks')
            finally:
                server.terminate();server.wait(timeout=5)
if __name__=='__main__': run()
