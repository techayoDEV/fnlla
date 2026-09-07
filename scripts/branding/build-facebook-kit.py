"""Render Facebook campaign artboards and package the offline copy companion."""
from pathlib import Path
import html
import json
import shutil
import zipfile

from PIL import Image, ImageStat
from playwright.sync_api import sync_playwright
from product_copy import build_copy, check_copy

ROOT = Path(__file__).resolve().parents[2]
BRAND = ROOT / 'branding'
OUTPUT = BRAND / 'assets/social/facebook-kit'
WORK = ROOT / 'dist/facebook-kit-220'
POSTS = [
    ('01-launch', 'WEB FRAMEWORK / PHP FOUNDATION', 'Build your next\nweb application.',
     'Project setup. Private developer tools.\nA foundation you can make your own.', 'dashboard',
     'Actual FNLLA 2.2.0 / configured demo / dashboard detail'),
    ('02-setup', 'PROJECT SETUP', 'Your project.\nYour identity.',
     'Start locally with your own application\nname and developer access.', 'setup',
     'Actual full starter / fresh first-run setup / cropped view'),
    ('03-diagnostics', 'DEVELOPMENT DIAGNOSTICS', 'Understand\neach request.',
     'Inspect status, timing and memory\nfrom your development workspace.', 'diagnostics',
     'Configured demo / local request sample, not a benchmark'),
    ('04-updates', 'CONTROLLED FRAMEWORK UPDATES', 'Check. Dry-run.\nThen decide.',
     'Review framework changes\nbefore applying an update.', 'updates',
     'Actual update controls / configured demo / cropped view'),
    ('05-workspace', 'OPTIONAL PROJECT WORKSPACE', 'Keep delivery\nwork in view.',
     'Tasks, priorities and review stages.\nAlongside your developer tools.', 'workspace',
     'Optional workspace module / synthetic tasks / cropped view'),
    ('06-fionn-ai', 'AI CONNECTIONS / BY CHOICE', 'A built-in gateway.\nA separate AI service.',
     'Connect to FIONN AI by TechAyo.\nNo language model bundled with FNLLA.', None,
     'Requires a suitable FIONN developer account and API access.'),
]


def main():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    WORK.mkdir(parents=True, exist_ok=True)
    tokens = json.loads((BRAND / 'tokens.json').read_text())
    if tokens['edition'] != '2.2.0':
        raise RuntimeError('This campaign uses reviewed 2.2.0 screenshots; refresh captures before changing the edition')
    template = (BRAND / 'source/facebook-campaign.html').read_text(encoding='utf-8')
    files = []
    results = []
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.route('https://**/*', lambda route: route.abort())
        page.route('http://**/*', lambda route: route.abort())
        variants = [(post, False) for post in POSTS] + [(POSTS[0], True), (POSTS[3], True)]
        for post, story in variants:
            slug, label, title, lead, screen, note = post
            height = 1920 if story else 1350
            visual = ('<img class="screen" alt="Actual demonstration interface" src="' +
                      (BRAND / f'assets/social/linkedin-product/screenshots/{screen}-detail.png').as_uri() + '">') if screen else (
                '<div class="connection"><h2>FNLLA</h2><p>Built-in gateway</p>'
                '<div class="boundary"><h2>FIONN AI</h2><p>Persistent Personal Intelligence<br>by TechAyo</p>'
                '<small>Separate service / account + API access</small></div></div>')
            values = {'ASSETS': (BRAND / 'assets').as_uri(), 'HEIGHT': str(height), 'CLASS': 'story' if story else '',
                      'EDITION': tokens['edition'], 'LABEL': label, 'TITLE': title, 'LEAD': lead, 'NOTE': note,
                      'COLORS': ';'.join('--' + key.replace('_', '-') + ':' + value for key, value in tokens['color'].items())}
            content = template.replace('{{VISUAL}}', visual)
            for key, value in values.items():
                content = content.replace('{{' + key + '}}', html.escape(value, quote=True))
            source = WORK / 'artboard.html'
            source.write_text(content, encoding='utf-8')
            page.set_viewport_size({'width': 1080, 'height': height})
            page.goto(source.as_uri())
            page.evaluate('document.fonts.ready')
            page.locator('img').evaluate_all('(images)=>Promise.all(images.map(i=>i.decode()))')
            for selector in ('h1', '.lead', '.note', '.label'):
                if page.locator(selector).evaluate('(e)=>e.scrollHeight>e.clientHeight+1 || e.scrollWidth>e.clientWidth+1'):
                    raise RuntimeError(slug + ': overflowing ' + selector)
            if not page.evaluate('document.fonts.check("600 68px Space") && document.fonts.check("600 23px Mono")'):
                raise RuntimeError('Brand fonts missing')
            path = OUTPUT / (('story-' if story else '') + slug + '.png')
            page.screenshot(path=str(path))
            with Image.open(path) as bitmap:
                if bitmap.size != (1080, height) or max(ImageStat.Stat(bitmap.convert('RGB')).stddev) < 10:
                    raise RuntimeError('Blank or incorrectly sized artboard')
            if path.stat().st_size > 3_000_000:
                raise RuntimeError('Delivery image exceeds 3 MB')
            files.append(path)
            results.append({'file': path.name, 'width': 1080, 'height': height, 'bytes': path.stat().st_size})
        page.close()
        if errors:
            raise RuntimeError('\n'.join(errors))
        delivery = WORK / 'FNLLA-2.2.0-Facebook-Kit'
        delivery.mkdir(exist_ok=True)
        files += [BRAND / 'FACEBOOK-KIT.md', BRAND / 'assets/social/facebook-avatar.png', BRAND / 'assets/social/facebook-cover.png']
        for path in files:
            target = delivery / path.relative_to(BRAND)
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(path, target)
        companion = build_copy(delivery, 'FACEBOOK-KIT', 'FACEBOOK')
        cards = ''.join('<figure><a href="' + f.relative_to(BRAND).as_posix() + '"><img src="' +
                        f.relative_to(BRAND).as_posix() + '" alt="' + f.stem + '"></a><figcaption>' + f.name +
                        '</figcaption></figure>' for f in files if f.suffix == '.png')
        preview = delivery / 'index.html'
        preview.write_text('''<!doctype html><html lang="pl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FNLLA / Facebook Kit</title><style>body{margin:24px auto;padding:0 20px;max-width:1100px;font:16px/1.5 Arial;color:#0B1220;background:#F1F3F5}h1{font-size:28px}a{color:#1D4ED8}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}figure{margin:0}img{width:100%;height:auto}figcaption{overflow-wrap:anywhere}nav{margin-bottom:24px}@media(max-width:600px){.grid{grid-template-columns:1fr}}</style>
<h1>FNLLA / Facebook Kit</h1><nav><a href="FACEBOOK-KIT.html">Instrukcja i teksty do kopiowania</a></nav><div class="grid">''' + cards + '</div></html>', encoding='utf-8')
        check_copy(browser, delivery, WORK, 'FACEBOOK-KIT')
        page = browser.new_page()
        for width in (1280, 390):
            page.set_viewport_size({'width': width, 'height': 900})
            page.goto(preview.as_uri())
            page.locator('img').evaluate_all('(images)=>Promise.all(images.map(i=>i.decode()))')
            if page.evaluate('document.documentElement.scrollWidth > innerWidth + 1'):
                raise RuntimeError('Gallery preview overflows')
            page.screenshot(path=str(WORK / f'gallery-{width}.png'))
        page.close()
        browser.close()
    archive = WORK / 'FNLLA-2.2.0-Facebook-Kit.zip'
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as bundle:
        for path in files:
            bundle.write(path, path.relative_to(BRAND).as_posix())
        bundle.write(companion, companion.name)
        bundle.write(preview, preview.name)
    with zipfile.ZipFile(archive) as bundle:
        assert bundle.testzip() is None
    (WORK / 'validation.json').write_text(json.dumps({'images': results, 'errors': []}, indent=2))
    print(companion)
    print(archive)


if __name__ == '__main__':
    main()
