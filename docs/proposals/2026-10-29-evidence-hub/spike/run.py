"""Run queries.sql against the spike databases and print the median server-side time of 5 runs.

Usage: python3 -I run.py pg|my [--plan NAME]
  pg: database spike_evh in container m503-db-1 (EXPLAIN ANALYZE, "Execution Time")
  my: database spike in container spike-evh-mariadb (ANALYZE FORMAT=JSON, "r_total_time_ms")
"""
import json
import re
import statistics
import subprocess
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
RUNS = 5


def queries():
    text = (HERE / 'queries.sql').read_text()
    capin = 'IN (' + ','.join(str(i) for i in range(2001, 2501)) + ')'
    nowplusday = str(int(time.time()) + 86400)
    out = []
    for block in text.split('-- name: ')[1:]:
        name, _, sql = block.partition('\n')
        sql = sql.strip().rstrip(';').replace('{CAPIN}', capin).replace('{NOWPLUSDAY}', nowplusday)
        out.append((name.strip(), sql))
    return out


def run(engine, sql):
    if engine == 'pg':
        cmd = ['docker', 'exec', '-i', 'm503-db-1', 'psql', '-U', 'moodle', '-d', 'spike_evh', '-At']
        res = subprocess.run(cmd, input='EXPLAIN (ANALYZE, BUFFERS) ' + sql + ';', capture_output=True, text=True, check=True)
        ms = float(re.search(r'Execution Time: ([\d.]+) ms', res.stdout).group(1))
        return ms, res.stdout
    cmd = ['docker', 'exec', '-i', 'spike-evh-mariadb', 'mariadb', '-uroot', '-pspike', 'spike', '-N', '-r']
    res = subprocess.run(cmd, input='ANALYZE FORMAT=JSON ' + sql + ';', capture_output=True, text=True, check=True)
    plan = json.loads(res.stdout)
    return float(plan['query_block']['r_total_time_ms']), json.dumps(plan, indent=1)


def main():
    engine = sys.argv[1]
    showplan = sys.argv[3] if len(sys.argv) > 3 and sys.argv[2] == '--plan' else None
    for name, sql in queries():
        if showplan and not name.startswith(showplan + ' '):
            continue
        times, plan = [], ''
        for _ in range(RUNS):
            ms, plan = run(engine, sql)
            times.append(ms)
        print(f'{engine}  {statistics.median(times):9.1f} ms  (min {min(times):.1f}, max {max(times):.1f})  {name}')
        if showplan:
            print(plan)


if __name__ == '__main__':
    main()
