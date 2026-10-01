#!/usr/bin/env python3
"""Build a local release ZIP only after provenance and the complete matrix pass."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'qtrad'

def verify():
    subprocess.run(['python3', str(ROOT / 'tests/assets.py')], check=True)
    cells = json.loads((ROOT / 'tests/matrix/cells.json').read_text())
    runtime = {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
               for path in PLUGIN.rglob('*') if path.is_file() and path.suffix != '.md' and path.name != 'readme.txt'}
    for cell in cells:
        path = ROOT / 'tests/results/matrix' / cell['id'] / 'report.json'
        if not path.exists(): raise RuntimeError('Missing result: ' + cell['id'])
        report = json.loads(path.read_text())
        if report.get('cell') != cell or report.get('passed') is not True: raise RuntimeError('Matrix cell did not pass: ' + cell['id'])
        tested_runtime = {name: digest for name, digest in report.get('source_sha256', {}).items()
                          if Path(name).suffix != '.md' and Path(name).name != 'readme.txt'}
        if tested_runtime != runtime: raise RuntimeError('Stale tested source/inventory: ' + cell['id'])
        if report.get('test_sha256') != test_fingerprint(): raise RuntimeError('Stale test harness: ' + cell['id'])
        suites = report['suites']
        for name in ('codec', 'wordpress', 'seo', 'seo-http', 'yoast-http', 'rankmath-http'):
            if name not in suites: raise RuntimeError('Missing suite: ' + cell['id'] + ' ' + name)
            result = suites[name]
            cases = result if isinstance(result, list) else result.get('cases', [])
            if not cases or not all(case.get('pass') is True for case in cases): raise RuntimeError('Failed assertions: ' + cell['id'] + ' ' + name)
        if cell.get('browser'):
            browser = suites.get('browser', {})
            if not browser.get('checks') or browser.get('errors') or not all(check.get('pass') is True for check in browser['checks']): raise RuntimeError('Missing/failing browser validation: ' + cell['id'])
            if not browser.get('accessibility') or any(result.get('violations') for result in browser['accessibility']): raise RuntimeError('Missing/failing axe validation: ' + cell['id'])
            network = suites.get('multisite', {})
            if not network.get('cases') or not all(check.get('pass') is True for check in network['cases']): raise RuntimeError('Missing/failing multisite validation: ' + cell['id'])
    print('Provenance and all %d matrix cells verified against current runtime/test hashes.' % len(cells))

def test_fingerprint():
    return {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
            for path in sorted((ROOT / 'tests').rglob('*')) if path.is_file()
            and not set(path.relative_to(ROOT / 'tests').parts).intersection({'results', 'node_modules', '__pycache__', 'unloaded-plugins'})}

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verify-only', action='store_true')
    args = parser.parse_args()
    try:
        verify()
        if not args.verify_only:
            source = (PLUGIN / 'qtrad.php').read_text()
            version = re.search(r'^ \* Version: ([0-9.]+)$', source, re.MULTILINE).group(1)
            output = ROOT / 'dist' / ('qtrad-' + version + '.zip')
            output.parent.mkdir(exist_ok=True)
            with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
                for path in sorted(PLUGIN.rglob('*')):
                    if path.is_file():
                        info = zipfile.ZipInfo(str(path.relative_to(ROOT)), (2026, 1, 1, 0, 0, 0))
                        info.compress_type = zipfile.ZIP_DEFLATED
                        info.external_attr = 0o100644 << 16
                        archive.writestr(info, path.read_bytes())
            digest = hashlib.sha256(output.read_bytes()).hexdigest()
            output.with_suffix('.zip.sha256').write_text(digest + '  ' + output.name + '\n')
            print(str(output))
    except (RuntimeError, subprocess.CalledProcessError) as error:
        parser.exit(1, str(error) + '\n')
