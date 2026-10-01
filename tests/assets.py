#!/usr/bin/env python3
"""Offline release gate: every bundled image has a pinned source and matching hash."""
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[1] / 'qtrad/flags'
manifest = json.loads((root / 'manifest.json').read_text())
assert manifest['commit'] == 'fe15c16e7463d0c66d6c5730e9d0e832438d98e1'
assert hashlib.sha256((root / manifest['license_file']).read_bytes()).hexdigest() == manifest['license_sha256']
assert 'Copyright (c) 2013 Panayiotis Lipiridis' in (root / manifest['license_file']).read_text()
listed = set()
for asset in manifest['assets']:
    path = root / asset['file']
    assert path.name == asset['file'] and path.name not in listed
    listed.add(path.name)
    assert asset['license'] == 'MIT' and asset['modifications'] == 'none'
    assert manifest['commit'] in asset['source_url']
    assert hashlib.sha256(path.read_bytes()).hexdigest() == asset['sha256'], path
    document = ET.fromstring(path.read_bytes())
    assert document.tag == '{http://www.w3.org/2000/svg}svg'
    # Bundled SVGs are static artwork, loaded as images; no active/external content.
    for element in document.iter():
        assert element.tag.rsplit('}', 1)[-1] not in ('script', 'foreignObject', 'image', 'a')
        for key, value in element.attrib.items():
            assert not key.lower().startswith('on')
            if key.rsplit('}', 1)[-1] == 'href': assert value.startswith('#')
assert {p.name for p in root.iterdir() if p.suffix.lower() in ('.png', '.gif', '.jpg', '.jpeg', '.svg', '.webp')} == listed
assert all(value in listed for value in manifest['legacy_aliases'].values())
print(json.dumps({'passed': True, 'assets': len(listed), 'license': 'MIT', 'commit': manifest['commit']}))
