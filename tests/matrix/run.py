#!/usr/bin/env python3
"""Run one/all cells in disposable Docker containers; never touches a real site."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import time
import urllib.request
import uuid
import zipfile
import datetime

ROOT = Path(__file__).resolve().parents[2]
CELLS = json.loads((ROOT / 'tests/matrix/cells.json').read_text())

def command(args, *, check=True):
    result = subprocess.run(args, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if check and result.returncode:
        raise RuntimeError(f'{args[0:3]} exited {result.returncode}:\n{result.stdout}\n{result.stderr}')
    return result

def run_cell(cell, browser=False):
    output = ROOT / 'tests/results/matrix' / cell['id']
    output.mkdir(parents=True, exist_ok=True)
    for previous in output.iterdir():
        if previous.is_file(): previous.unlink()
    report = {'cell': cell, 'passed': False, 'suites': {}, 'source_sha256': {
        str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest()
        for p in sorted((ROOT / 'qtrad').rglob('*')) if p.is_file()
    }}
    report['started_utc'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    report['test_sha256'] = {
        str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest()
        for p in sorted((ROOT / 'tests').rglob('*')) if p.is_file()
        and not set(p.relative_to(ROOT / 'tests').parts).intersection({'results', 'node_modules', '__pycache__', 'unloaded-plugins'})
    }
    token = 'qtn-' + uuid.uuid4().hex[:12]
    work = Path(tempfile.mkdtemp(prefix=token + '-'))
    network, db, server = token + '-net', token + '-db', token + '-http'
    image = 'qtn-matrix-php:' + cell['php']
    try:
        print(f"{cell['id']}: preparing isolated runtime", flush=True)
        build = command(['docker', 'build', '--build-arg', 'PHP_VERSION=' + cell['php'], '-t', image, str(ROOT / 'tests/matrix')])
        (output / 'build.log').write_text(build.stdout + build.stderr)
        report['php_image'] = command(['docker', 'image', 'inspect', image, '--format', '{{.Id}}']).stdout.strip()
        cache = Path(tempfile.gettempdir()) / 'qtn-matrix-downloads'
        cache.mkdir(exist_ok=True)
        archive = cache / ('wordpress-' + cell['wordpress'] + '.tar.gz')
        if not archive.exists():
            urllib.request.urlretrieve('https://wordpress.org/' + archive.name, archive)
        report['wordpress_archive_sha256'] = hashlib.sha256(archive.read_bytes()).hexdigest()
        with tarfile.open(archive) as package:
            # WordPress packages contain ordinary files/directories under wordpress/.
            for entry in package.getmembers():
                if entry.issym() or entry.islnk() or not (entry.name == 'wordpress' or entry.name.startswith('wordpress/')) or '..' in Path(entry.name).parts:
                    raise RuntimeError('Unexpected archive member: ' + entry.name)
            package.extractall(work)
        site = work / 'wordpress'
        (site / '.qtn-disposable').touch()
        shutil.copytree(ROOT / 'qtrad', site / 'wp-content/plugins/qtrad')
        (site / 'wp-content/mu-plugins').mkdir(exist_ok=True)
        shutil.copy(ROOT / 'tests/fixtures/wordpress-mu.php', site / 'wp-content/mu-plugins/qtn-test.php')
        (site / 'wp-config.php').write_text("""<?php
define('DB_NAME', 'qtn'); define('DB_USER', 'qtn'); define('DB_PASSWORD', 'qtn-disposable-only');
define('DB_HOST', 'db:3306'); define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('WP_DEBUG', true); define('WP_DEBUG_DISPLAY', false); define('WP_DEBUG_LOG', '/site/debug.log');
define('DISABLE_WP_CRON', true); define('WP_ENVIRONMENT_TYPE', 'local');
define('AUTH_KEY', 'qtn-fixture-auth'); define('SECURE_AUTH_KEY', 'qtn-fixture-secure');
define('LOGGED_IN_KEY', 'qtn-fixture-login'); define('NONCE_KEY', 'qtn-fixture-nonce');
define('AUTH_SALT', 'qtn-fixture-auth-salt'); define('SECURE_AUTH_SALT', 'qtn-fixture-secure-salt');
define('LOGGED_IN_SALT', 'qtn-fixture-login-salt'); define('NONCE_SALT', 'qtn-fixture-nonce-salt');
$table_prefix = 'qtn_'; if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/'); require ABSPATH . 'wp-settings.php';
""")
        command(['docker', 'network', 'create', network])
        command(['docker', 'run', '-d', '--name', db, '--network', network, '--network-alias', 'db',
                 '-e', 'MYSQL_RANDOM_ROOT_PASSWORD=yes', '-e', 'MYSQL_DATABASE=qtn',
                 '-e', 'MYSQL_USER=qtn', '-e', 'MYSQL_PASSWORD=qtn-disposable-only', cell['database']])
        client = 'mariadb' if cell['database'].startswith('mariadb') else 'mysql'
        for attempt in range(90):
            ready = command(['docker', 'exec', db, client, '-uqtn', '-pqtn-disposable-only', '-e', 'SELECT 1'], check=False)
            if ready.returncode == 0: break
            time.sleep(1)
        else: raise RuntimeError('Disposable database did not become ready')
        report['database_image'] = command(['docker', 'inspect', db, '--format', '{{.Image}}']).stdout.strip()
        php = ['docker', 'run', '--rm', '--network', network, '-v', str(ROOT) + ':/workspace:ro', '-v', str(site) + ':/site', image, 'php']
        command(php + ['tests/matrix/install.php', '/site'])
        for suite, args in [('codec', ['tests/codec.php']), ('wordpress', ['tests/wordpress.php', '/site', '--prepare-browser']),
                            ('seo', ['tests/seo.php', '/site'])]:
            if not (ROOT / args[0]).exists(): raise RuntimeError('Required suite is missing: ' + args[0])
            print(f"{cell['id']}: {suite}", flush=True)
            result = command(php + args, check=False)
            (output / (suite + '.json')).write_text(result.stdout)
            (output / (suite + '.stderr.log')).write_text(result.stderr)
            if result.returncode: raise RuntimeError(f'{suite} failed; see {output}')
            report['suites'][suite] = json.loads(result.stdout)
        command(['docker', 'run', '-d', '--name', server, '--network', network,
                 '-p', '127.0.0.1:8931:8931', '-v', str(ROOT) + ':/workspace:ro', '-v', str(site) + ':/site', image,
                 'php', '-S', '0.0.0.0:8931', '-t', '/site', '/workspace/tests/matrix/router.php'])
        if (ROOT / 'tests/seo-http.py').exists():
            result = command(['python3', str(ROOT / 'tests/seo-http.py'), str(output / 'seo.json')], check=False)
            (output / 'seo-http.json').write_text(result.stdout)
            (output / 'seo-http.stderr.log').write_text(result.stderr)
            if result.returncode: raise RuntimeError('SEO HTTP checks failed')
            report['suites']['seo-http'] = json.loads(result.stdout)
            wp_version = tuple(int(part) for part in cell['wordpress'].split('.'))
            yoast_version = '28.6' if wp_version >= (6, 9) else ('25.9' if wp_version >= (6, 7) else '17.9')
            rank_version = '1.0.279' if wp_version >= (6, 7) else '1.0.76.1'
            report['seo_plugins'] = {}
            for owner, slug, version in [('yoast', 'wordpress-seo', yoast_version), ('rankmath', 'seo-by-rank-math', rank_version)]:
                print(f"{cell['id']}: real {owner} {version}", flush=True)
                archive = cache / (slug + '.' + version + '.zip')
                if not archive.exists(): urllib.request.urlretrieve('https://downloads.wordpress.org/plugin/' + archive.name, archive)
                with zipfile.ZipFile(archive) as package:
                    for name in package.namelist():
                        if not name.startswith(slug + '/') or '..' in Path(name).parts: raise RuntimeError('Unexpected plugin archive member')
                    package.extractall(site / 'wp-content/plugins')
                report['seo_plugins'][owner] = {'version': version, 'archive_sha256': hashlib.sha256(archive.read_bytes()).hexdigest()}
                command(php + ['tests/matrix/select-seo.php', '/site', owner])
                bootstrap = command(php + ['tests/matrix/diagnose.php', '/site', '--refresh'])
                (output / (owner + '-bootstrap.json')).write_text(bootstrap.stdout)
                result = command(['python3', str(ROOT / 'tests/seo-http.py'), str(output / 'seo.json'), owner], check=False)
                (output / (owner + '-http.json')).write_text(result.stdout)
                (output / (owner + '-http.stderr.log')).write_text(result.stderr)
                if result.returncode: raise RuntimeError(owner + ' HTTP checks failed')
                report['suites'][owner + '-http'] = json.loads(result.stdout)
            command(php + ['tests/matrix/select-seo.php', '/site', 'core'])
            command(php + ['tests/matrix/diagnose.php', '/site', '--refresh'])
        if browser and cell.get('browser'):
            # SEO fixtures deliberately change site options; recreate browser context.
            result = command(php + ['tests/wordpress.php', '/site', '--prepare-browser'])
            (output / 'wordpress.json').write_text(result.stdout)
            axe = os.environ.get('QTN_AXE_PATH', str(ROOT / 'tests/node_modules/axe-core/axe.min.js'))
            result = command(['node', str(ROOT / 'tests/browser.cjs'), str(output / 'wordpress.json'), axe], check=False)
            (output / 'browser.json').write_text(result.stdout)
            (output / 'browser.stderr.log').write_text(result.stderr)
            if result.returncode: raise RuntimeError('Browser checks failed')
            report['suites']['browser'] = json.loads(result.stdout)
        if cell.get('browser'):
            command(['docker', 'rm', '-f', server])
            command(php + ['tests/matrix/network.php', '/site'])
            result = command(php + ['tests/multisite.php', '/site'], check=False)
            (output / 'multisite.json').write_text(result.stdout)
            (output / 'multisite.stderr.log').write_text(result.stderr)
            if result.returncode: raise RuntimeError('Multisite checks failed')
            report['suites']['multisite'] = json.loads(result.stdout)
        report['passed'] = True
    except Exception as error:
        report['error'] = str(error)
        print(f"{cell['id']}: FAIL: {error}", flush=True)
    finally:
        if 'php' in locals():
            diagnostic = command(php + ['tests/matrix/diagnose.php', '/site'], check=False)
            (output / 'diagnostic.json').write_text(diagnostic.stdout + diagnostic.stderr)
        for name in (server, db):
            log = command(['docker', 'logs', name], check=False)
            (output / (name.rsplit('-', 1)[1] + '.log')).write_text(log.stdout + log.stderr)
            command(['docker', 'rm', '-fv', name], check=False)
        command(['docker', 'network', 'rm', network], check=False)
        if (work / 'wordpress/debug.log').exists(): shutil.copy(work / 'wordpress/debug.log', output / 'wp-debug.log')
        command(['docker', 'run', '--rm', '-v', str(work) + ':/cleanup', '--entrypoint', 'sh', image, '-c', 'rm -rf /cleanup/*'], check=False)
        shutil.rmtree(work, ignore_errors=True)
        report['completed_utc'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
        (output / 'report.json').write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n')
    print(f"{cell['id']}: {'PASS' if report['passed'] else 'FAIL'}", flush=True)
    return report['passed']

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--cell', choices=[c['id'] for c in CELLS])
    parser.add_argument('--browser', action='store_true')
    args = parser.parse_args()
    selected = [c for c in CELLS if not args.cell or c['id'] == args.cell]
    successes = [run_cell(c, args.browser) for c in selected]
    raise SystemExit(0 if all(successes) else 1)
