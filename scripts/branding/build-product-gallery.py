"""Capture actual FNLLA screens and render the LinkedIn product-media artboards.

Use product-fixtures.php against a fresh disposable export first. No network
requests leave the fixture browser. Only reviewed PNGs belong in the brand kit.
"""
from pathlib import Path
from urllib.parse import unquote, urlparse
import argparse
import html
import json
import mimetypes
import shutil
import zipfile

from PIL import Image, ImageStat
from playwright.sync_api import sync_playwright
from product_copy import build_copy, check_copy

ROOT = Path(__file__).resolve().parents[2]
DESTINATION = ROOT / 'branding/assets/social/linkedin-product'
ARTBOARDS = [
    ('01-developer-panel', 'dashboard', 'DEVELOPER PANEL',
     'Your project. One operational view.',
     'Framework status, access shortcuts and update tools in one place.',
     'Full starter / configured demo account / dashboard detail'),
    ('02-project-setup', 'setup', 'PROJECT SETUP',
     "Start with your project's identity.",
     'Set its identity. Create your own developer access.',
     'Full starter / fresh first-run setup / cropped view'),
    ('03-diagnostics', 'diagnostics', 'DIAGNOSTICS',
     'See what your requests are doing.',
     'Inspect request timing, status and memory during development.',
     'Configured demo / local request sample, not a benchmark'),
    ('04-framework-updates', 'updates', 'FRAMEWORK UPDATES',
     'Check. Dry-run. Then decide.',
     'Review framework changes before applying an update.',
     'Configured demo / update controls / cropped view'),
    ('05-project-workspace', 'workspace', 'PROJECT WORKSPACE',
     'Keep delivery work in view.',
     'A shared Kanban board alongside your developer tools.',
     'Optional workspace module / synthetic tasks / cropped view'),
]


def capture(browser, project, fixtures):
    public = (project / 'public').resolve()
    screens = json.loads((fixtures / 'screens.json').read_text())
    responses = json.loads((fixtures / 'responses.json').read_text())
    proof = fixtures / 'screenshots'
    proof.mkdir(exist_ok=True)
    failures = []
    page = browser.new_page(viewport={'width': 1440, 'height': 1000}, device_scale_factor=1)
    page.on('pageerror', lambda error: failures.append(str(error)))

    def route(request):
        url = urlparse(request.request.url)
        path = unquote(url.path)
        name = path.strip('/')
        if url.netloc == 'example-app.test' and name in screens:
            response = responses[name]
            request.fulfill(path=str(fixtures / (name + '.html')), content_type='text/html',
                            status=response['status'], headers=response['headers'])
            return
        target = (public / path.lstrip('/')).resolve()
        if url.netloc == 'example-app.test' and target.is_relative_to(public) and target.is_file():
            request.fulfill(path=str(target), content_type=mimetypes.guess_type(target)[0]
                            or 'application/octet-stream')
        else:
            failures.append('Blocked unexpected asset: ' + url.netloc + path)
            request.abort()

    page.route('**/*', route)
    for name in screens:
        page.goto('http://example-app.test/' + name)
        page.evaluate('window.FNLLARUNTIME.setTheme("default")')
        page.evaluate('document.fonts.ready')
        if page.evaluate('document.documentElement.scrollWidth > innerWidth + 1'):
            failures.append(name + ': horizontal overflow')
        if not page.evaluate('document.fonts.check(\'16px "Space Grotesk"\')'):
            failures.append(name + ': missing brand font')
        text = page.locator('body').inner_text()
        # Fail closed before a screenshot can expose workstation paths or real account data.
        for forbidden in ('C:\\', 'C:/', '/Users/', '/home/', 'Brand Regression'):
            if forbidden.lower() in text.lower():
                failures.append(name + ': review unexpected private/maintainer content: ' + forbidden)
        (fixtures / (name + '.txt')).write_text(text, encoding='utf-8')
        page.screenshot(path=str(proof / (name + '.png')), full_page=True, animations='disabled')
        if name == 'dashboard':
            page.set_viewport_size({'width': 1920, 'height': 900})
            page.locator('[aria-label="Environment status"]').evaluate(
                '(e) => window.scrollTo(0, e.getBoundingClientRect().top + scrollY - 110)')
            page.screenshot(path=str(proof / (name + '-detail.png')), animations='disabled')
        elif name == 'setup':
            page.screenshot(path=str(proof / (name + '-detail.png')),
                            clip={'x': 72, 'y': 64, 'width': 1296, 'height': 820}, animations='disabled')
        elif name in ('diagnostics', 'updates', 'workspace'):
            selector = {'diagnostics': '[aria-labelledby="debug-title"]',
                        'updates': '[aria-label="GitHub release channel controls"]',
                        'workspace': '[aria-label="Kanban board"]'}[name]
            if name == 'workspace':
                page.set_viewport_size({'width': 1920, 'height': 1000})
            element = page.locator(selector)
            box = element.bounding_box()
            if name == 'diagnostics':
                box = {'x': box['x'] - 24, 'y': box['y'] - 24,
                       'width': box['width'] + 48, 'height': box['height'] + 48}
            box['height'] = min(box['height'], 770)
            page.screenshot(path=str(proof / (name + '-detail.png')), clip=box,
                            full_page=True, animations='disabled')
        page.set_viewport_size({'width': 1440, 'height': 1000})
        print('Captured', name, flush=True)
    page.close()
    if failures:
        raise RuntimeError('\n'.join(failures))
    return proof


def render(browser, proof, fixtures):
    tokens = json.loads((ROOT / 'branding/tokens.json').read_text())
    colors = tokens['color']
    template = (ROOT / 'branding/source/linkedin-product.html').read_text(encoding='utf-8')
    DESTINATION.mkdir(parents=True, exist_ok=True)
    raw = DESTINATION / 'screenshots'
    raw.mkdir(exist_ok=True)
    for _, screen, *_ in ARTBOARDS:
        shutil.copyfile(proof / (screen + '.png'), raw / (screen + '.png'))
        shutil.copyfile(proof / (screen + '-detail.png'), raw / (screen + '-detail.png'))
    page = browser.new_page(viewport={'width': 1920, 'height': 1080}, device_scale_factor=1)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))

    def route(request):
        url = urlparse(request.request.url)
        target = (ROOT / unquote(url.path).lstrip('/')).resolve()
        allowed = (ROOT / 'branding').resolve()
        if url.netloc == 'product-artwork.test' and target.is_relative_to(allowed) and target.is_file():
            request.fulfill(path=str(target), content_type=mimetypes.guess_type(target)[0]
                            or 'application/octet-stream')
        else:
            errors.append('Unexpected artwork asset: ' + url.path)
            request.abort()

    page.route('**/*', route)
    report = []
    for index, (slug, screen, label, title, subtitle, disclosure) in enumerate(ARTBOARDS, start=1):
        values = {'NUMBER': f'{index:02}', 'LABEL': label, 'TITLE': title, 'SUBTITLE': subtitle,
                  'DISCLOSURE': disclosure, 'SCREEN': screen,
                  'EDITION': tokens['edition'],
                  'COLOR_TOKENS': ';'.join('--' + key.replace('_', '-') + ':' + value for key, value in colors.items())}
        content = template
        for key, value in values.items():
            content = content.replace('{{' + key + '}}', html.escape(value, quote=True))
        page.set_content(content)
        page.evaluate('document.fonts.ready')
        page.locator('.product-shot').evaluate('(e) => e.decode()')
        page.locator('.wordmark').evaluate('(e) => e.decode()')
        if not page.evaluate('document.fonts.check(\'600 56px "Space Grotesk"\') && document.fonts.check(\'600 18px "JetBrains Mono"\')'):
            errors.append(slug + ': fonts not ready')
        if page.evaluate('document.documentElement.scrollWidth !== 1920 || document.documentElement.scrollHeight !== 1080'):
            errors.append(slug + ': artboard dimensions/overflow')
        for selector in ('.headline', '.subtitle', '.disclosure', '.credit'):
            if page.locator(selector).evaluate('(e) => e.scrollWidth > e.clientWidth + 1 || e.scrollHeight > e.clientHeight + 1'):
                errors.append(slug + ': overflowing ' + selector)
        box = page.locator('.wordmark').bounding_box()
        if box['width'] < 240:
            errors.append(slug + ': wordmark below brand minimum')
        path = DESTINATION / (slug + '.png')
        page.screenshot(path=str(path), animations='disabled')
        with Image.open(path) as bitmap:
            if bitmap.size != (1920, 1080) or max(ImageStat.Stat(bitmap.convert('RGB')).stddev) < 10:
                errors.append(slug + ': blank image or incorrect pixel dimensions')
        if path.stat().st_size > 3_000_000:
            errors.append(slug + ': image exceeds the 3 MB delivery budget')
        report.append({'file': path.name, 'width': 1920, 'height': 1080, 'bytes': path.stat().st_size,
                       'source': screen + '-detail.png', 'disclosure': disclosure})
        print('Rendered', path.name, flush=True)
    page.close()
    (fixtures / 'gallery-validation.json').write_text(json.dumps({'images': report, 'errors': errors}, indent=2))
    if errors:
        raise RuntimeError('\n'.join(errors))


def package(browser, fixtures):
    delivery = fixtures.parent / 'delivery'
    delivery.mkdir(exist_ok=True)
    cards = '\n'.join(
        '<figure><a href="assets/social/linkedin-product/' + slug + '.png">'
        '<img width="1920" height="1080" src="assets/social/linkedin-product/' + slug + '.png" '
        'alt="' + html.escape(title, quote=True) + '"></a><figcaption>'
        + html.escape(slug + '.png') + '</figcaption></figure>'
        for slug, _, _, title, *_ in ARTBOARDS)
    preview = '''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FNLLA LinkedIn Product Kit</title><style>
*{box-sizing:border-box}body{margin:0;background:#F1F3F5;color:#0B1220;font:16px/1.5 Arial,sans-serif}
main{max-width:1144px;padding:24px;margin:auto}h1{font-size:28px;margin:0 0 12px}a{color:#1D4ED8}
nav{display:flex;gap:24px;flex-wrap:wrap;margin-bottom:24px}figure{margin:0 0 32px}
img{display:block;width:100%;height:auto}figcaption{padding:8px 0;font:14px/1.5 monospace;overflow-wrap:anywhere}
</style></head><body><main><h1>FNLLA / LinkedIn Product Kit</h1>
<nav><a href="LINKEDIN-PRODUCT.html">Profile copy and captions</a>
<a href="assets/social/linkedin-avatar.png">Product logo</a>
<a href="https://github.com/techayoDEV/fnlla/releases/tag/v2.2.0">Public release</a></nav>
''' + cards + '</main></body></html>'
    (delivery / 'index.html').write_text(preview, encoding='utf-8')
    files = [ROOT / 'branding/LINKEDIN-PRODUCT.md', ROOT / 'branding/assets/social/linkedin-avatar.png']
    files += [DESTINATION / (slug + '.png') for slug, *_ in ARTBOARDS]
    files += sorted((DESTINATION / 'screenshots').glob('*.png'))
    for path in files:
        target = delivery / path.relative_to(ROOT / 'branding')
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(path, target)
    # The package has an explicit allowlist: never include fixture HTML, credentials or the export.
    copy_page = build_copy(delivery)
    check_copy(browser, delivery, fixtures)
    archive = fixtures.parent / 'FNLLA-2.2.0-LinkedIn-Product-Kit.zip'
    with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as bundle:
        bundle.write(delivery / 'index.html', 'index.html')
        bundle.write(copy_page, copy_page.name)
        for path in files:
            bundle.write(path, path.relative_to(ROOT / 'branding').as_posix())
    with zipfile.ZipFile(archive) as bundle:
        if bundle.testzip() is not None or len(bundle.namelist()) != len(files) + 2:
            raise RuntimeError('Delivery archive verification failed')
    page = browser.new_page()
    for width in (1280, 390):
        page.set_viewport_size({'width': width, 'height': 900})
        page.goto((delivery / 'index.html').as_uri())
        page.locator('figure img').evaluate_all('(images) => Promise.all(images.map(image => image.decode()))')
        if page.evaluate('document.documentElement.scrollWidth > innerWidth'):
            raise RuntimeError('Delivery preview overflows at ' + str(width))
        page.screenshot(path=str(fixtures / f'preview-{width}.png'), full_page=True)
    page.close()
    print('Delivery preview:', delivery / 'index.html')
    print('Upload kit:', archive)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('project', type=Path)
    parser.add_argument('fixtures', type=Path)
    parser.add_argument('--channel', default=None)
    parser.add_argument('--capture-only', action='store_true')
    parser.add_argument('--render-only', action='store_true', help='Recompose previously captured real screenshots')
    parser.add_argument('--package-only', action='store_true', help='Rebuild the handoff without changing screenshots')
    args = parser.parse_args()
    tokens = json.loads((ROOT / 'branding/tokens.json').read_text())
    if (args.project / 'VERSION').read_text().splitlines()[0].strip() != tokens['edition']:
        raise ValueError('Export version and brand edition differ; do not mislabel product screenshots')
    with sync_playwright() as p:
        browser = p.chromium.launch(channel=args.channel, headless=True)
        fixtures = args.fixtures.resolve()
        proof = fixtures / 'screenshots' if args.render_only or args.package_only else capture(browser, args.project.resolve(), fixtures)
        if not args.capture_only:
            if not args.package_only:
                render(browser, proof, fixtures)
            package(browser, fixtures)
        browser.close()


if __name__ == '__main__':
    main()
