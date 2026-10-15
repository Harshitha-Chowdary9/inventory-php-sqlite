"""Check independent PHP processes sharing one SQLite database."""
import concurrent.futures, os, subprocess, tempfile
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')
with tempfile.TemporaryDirectory() as d:
    env = dict(os.environ, INVENTORY_DB=str(Path(d)/'stock.sqlite'))
    subprocess.run([PHP,'bin/seed.php'],cwd=ROOT,env=env,check=True)
    def reserve(n):
        return subprocess.run([PHP,'tests/worker.php',f'parallel-{n}'],cwd=ROOT,env=env,
            capture_output=True,text=True,check=True).stdout.strip()
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        results=list(pool.map(reserve,range(40)))
    assert results.count('reserved')==10, results
    assert results.count('conflict')==30, results
    print('PASS: independent PHP writers reserved exactly 10 of 40 requests')
