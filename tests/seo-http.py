#!/usr/bin/env python3
"""Check actual HTML/XML over HTTP on the disposable localhost matrix fixture."""
import html.parser
import json
from pathlib import Path
import sys
import time
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET

fixtures = json.load(open(sys.argv[1]))['fixtures']
owner = sys.argv[2] if len(sys.argv) > 2 else 'core'
base = 'http://127.0.0.1:8931'
checks = []
class Head(html.parser.HTMLParser):
    def __init__(self):
        super().__init__(); self.links=[]; self.meta=[]; self.schemas=[]; self.capture=False; self.buffer=''
    def handle_starttag(self, tag, attrs):
        attrs=dict(attrs)
        if tag=='link':self.links.append(attrs)
        if tag=='meta':self.meta.append(attrs)
        if tag=='script' and attrs.get('type')=='application/ld+json':self.capture=True;self.buffer=''
    def handle_data(self,data):
        if self.capture:self.buffer+=data
    def handle_endtag(self,tag):
        if tag=='script' and self.capture:
            self.schemas.append(json.loads(self.buffer));self.capture=False
def check(name, passed): checks.append({'case':name,'pass':bool(passed)})
def fetch(url, headers=None):
    response=urllib.request.urlopen(urllib.request.Request(url,headers=headers or {}),timeout=30)
    return response.status,response.headers,response.read().decode('utf-8')
def meta(head,key):return [m.get('content') for m in head.meta if m.get('name',m.get('property'))==key]
try:
    for attempt in range(30):
        try:fetch(base+'/wp-login.php');break
        except (OSError,urllib.error.URLError):time.sleep(.2)
    translated=fixtures['translated']
    expected_alternates={code:base+prefix+translated['slug']+'/' for code,prefix in [('en-US','/'),('de-DE','/de/'),('es-ES','/es/')]}
    expected_alternates['x-default']=expected_alternates['en-US']
    for language,prefix,title,description in [
        ('en','/','English SEO title','English SEO description'),
        ('de','/de/','Deutscher SEO Titel','Deutsche SEO Beschreibung'),
        ('es','/es/','Título SEO español','Descripción SEO española'),
        ('de','/de/','Deutscher SEO Titel','Deutsche SEO Beschreibung'),
        ('en','/','English SEO title','English SEO description')]:
        url=base+prefix+translated['slug']+'/'
        status,headers,body=fetch(url);head=Head();head.feed(body)
        Path(sys.argv[1]).with_name(owner+'-'+language+'.html').write_text(body)
        canonical=[link.get('href') for link in head.links if link.get('rel')=='canonical']
        check(language+' canonical appears once and matches',canonical==[url])
        check(language+' reciprocal alternates',{link.get('hreflang'):link.get('href') for link in head.links if link.get('hreflang')}==expected_alternates)
        check(language+' no language markers','[:' not in body.split('</head>')[0] and '<!--:' not in body.split('</head>')[0])
        check(language+' one description',len(meta(head,'description'))==1)
        check(language+' localized description',meta(head,'description')==[description] if owner!='core' else meta(head,'description')==[{'en':'English description','de':'Deutsche Beschreibung','es':'Descripción española'}[language]])
        check(language+' localized social URL',meta(head,'og:url')==[url])
        check(language+' schema present',bool(head.schemas))
        if owner!='core':check(language+' localized social title',meta(head,'og:title')==[title])
    missing=base+'/de/'+fixtures['missing']['slug']+'/'
    _,_,body=fetch(missing);head=Head();head.feed(body)
    check('Missing translation noindex',any('noindex' in value for value in meta(head,'robots')))
    check('Missing translation has no alternate claims',not any(l.get('hreflang') for l in head.links))
    _,_,body=fetch(base+'/de/'+fixtures['book']['slug']+'/');head=Head();head.feed(body)
    check('qTrad SEO description override used',meta(head,'description')==['qTrad German description'])
    check('qTrad SEO title override used',any('qTrad German title' in value for value in meta(head,'og:title')))
    slugged=fixtures['slugged'];category=fixtures['category']
    slug_alternates={'en-US':base+'/'+slugged['slug']+'/','de-DE':base+'/de/'+slugged['slugs']['de']+'/','es-ES':base+'/es/'+slugged['slugs']['es']+'/'}
    slug_alternates['x-default']=slug_alternates['en-US']
    for tag in ('en-US','de-DE','es-ES'):
        url=slug_alternates[tag]
        status,_,body=fetch(url);head=Head();head.feed(body)
        check(tag+' translated slug resolves',status==200)
        check(tag+' translated slug canonical',[link.get('href') for link in head.links if link.get('rel')=='canonical']==[url])
        check(tag+' translated slug alternates',{link.get('hreflang'):link.get('href') for link in head.links if link.get('hreflang')}==slug_alternates)
        check(tag+' translated slug social URL',meta(head,'og:url')==[url])
    response=urllib.request.urlopen(base+'/de/'+slugged['slug']+'/',timeout=30)
    check('Stored slug moves to the translated address',response.geturl()==slug_alternates['de-DE'])
    try:
        fetch(base+'/'+slugged['slugs']['de']+'/');check('Slug of another language is not found',False)
    except urllib.error.HTTPError as error:check('Slug of another language is not found',error.code==404)
    translated_category=base+'/de/'+category['bases']['de']+'/'+category['slugs']['de']+'/'
    status,_,body=fetch(translated_category);head=Head();head.feed(body)
    check('Translated category base and slug resolve',status==200)
    check('Translated category canonical',[link.get('href') for link in head.links if link.get('rel')=='canonical']==[translated_category])
    for old_path in ('/de/category/'+category['slugs']['de']+'/','/de/category/'+category['slug']+'/'):
        response=urllib.request.urlopen(base+old_path,timeout=30)
        check('Category address '+old_path+' moves to the translated one',response.geturl()==translated_category)
    index_url=base+('/wp-sitemap.xml' if owner=='core' else '/sitemap_index.xml')
    _,headers,body=fetch(index_url,{'Cookie':'qtrans_front_language=de','Accept-Language':'de'})
    check('Sitemap response sets no language cookie',headers.get('Set-Cookie') is None)
    document=ET.fromstring(body);namespace={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
    children=[loc.text for loc in document.findall('.//s:sitemap/s:loc',namespace)]
    check('Sitemap index populated',bool(children))
    check('Sitemap endpoints remain neutral',all('/de/' not in u and 'lang=' not in u for u in children))
    urls=[]
    for child in children:
        _,_,xml=fetch(child);xml=ET.fromstring(xml)
        entries=[loc.text for loc in xml.findall('.//s:url/s:loc',namespace)]
        check('Sitemap file has unique locs '+child,len(entries)==len(set(entries)))
        urls.extend(entries)
    for tag,url in expected_alternates.items():check('Sitemap contains '+tag,url in urls)
    for tag,url in slug_alternates.items():check('Sitemap contains translated slug '+tag,url in urls)
    check('Sitemap omits the stored slug in other languages',base+'/de/'+slugged['slug']+'/' not in urls)
    check('Sitemap contains translated category slug',translated_category in urls)
    check('Sitemap contains German-only version',base+'/de/'+fixtures['german']['slug']+'/' in urls)
    check('Sitemap omits missing translation',missing not in urls)
    for slug in ('draft','private','password','empty','disabled'):check('Sitemap omits '+slug,not any(fixtures[slug]['slug']+'/' in url for url in urls))
    check('Sitemap translated custom post type',base+'/de/qtrad_book/'+fixtures['book']['slug']+'/' in urls or base+'/de/'+fixtures['book']['slug']+'/' in urls)
    Path(sys.argv[1]).with_name(owner+'-sitemap-urls.json').write_text(json.dumps(urls,indent=2))
    # Yoast includes the homepage in both its post-archive and page providers.
    content_urls=[url for url in urls if url not in (base+'/',base+'/de/',base+'/es/')]
    check('Sitemap content locs are unique',len(content_urls)==len(set(content_urls)))
except Exception as error:
    checks.append({'case':'HTTP execution','pass':False,'error':str(error)})
print(json.dumps({'owner':owner,'passed':sum(c['pass'] for c in checks),'total':len(checks),'cases':checks},indent=2,ensure_ascii=False))
sys.exit(0 if checks and all(c['pass'] for c in checks) else 1)
