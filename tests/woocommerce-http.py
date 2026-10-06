#!/usr/bin/env python3
"""Check rendered WooCommerce pages over HTTP on the disposable matrix fixture."""
import html, json, re, sys, urllib.request
fixtures = json.load(open(sys.argv[1]))['fixtures']
base = 'http://127.0.0.1:8931'
checks = []
def check(name, passed, detail=None):
    checks.append(dict({'case': name, 'pass': bool(passed)}, **({} if passed or detail is None else {'detail': detail})))
def around(text, needle='[:'):
    at = text.find(needle)
    return None if at < 0 else ' '.join(text[max(0, at - 120):at + 60].split())
def visible(body): return html.unescape(re.sub(r'<[^>]+>', ' ', re.sub(r'<(script|style)\b.*?</\1>', ' ', body, flags=re.S)))
try:
    for lang, prefix, name, label, color in (('en', '/', 'Red wine', 'Size', 'Color'), ('de', '/de/', 'Rotwein', 'Größe', 'Farbe')):
        body = urllib.request.urlopen(base + prefix + 'product/' + fixtures['simple'] + '/', timeout=30).read().decode('utf-8')
        text = visible(body)
        check(lang + ' product page shows no language markers', '[:' not in text, around(text))
        check(lang + ' product name', name in text)
        ld = [json.loads(m) for m in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', body, re.S)]
        check(lang + ' structured data has no markers', '[:' not in json.dumps(ld, ensure_ascii=False))
        body = urllib.request.urlopen(base + prefix + 'product/' + fixtures['variable'] + '/', timeout=30).read().decode('utf-8')
        text = visible(body)
        check(lang + ' variable product page shows no language markers', '[:' not in text, around(text))
        check(lang + ' custom attribute label', label in text)
        check(lang + ' global attribute label', color in text)
        options = re.findall(r'<select[^>]*name="attribute_' + re.escape(fixtures['size_key']) + r'"[^>]*>(.*?)</select>', body, re.S)
        check(lang + ' custom option values stay stored for matching', bool(options) and '[:en]Small' in html.unescape(options[0]))
except Exception as error:
    checks.append({'case': 'HTTP execution', 'pass': False, 'error': str(error)})
print(json.dumps({'passed': sum(c['pass'] for c in checks), 'total': len(checks), 'cases': checks}, indent=2, ensure_ascii=False))
sys.exit(0 if checks and all(c['pass'] for c in checks) else 1)
