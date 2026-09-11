"""Check the actual CSS cascade of a fresh Full export, without a public HTTP server."""
from pathlib import Path
from urllib.parse import unquote, urlparse
import argparse
import json
import mimetypes
from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('project', type=Path)
parser.add_argument('fixtures', type=Path)
parser.add_argument('--channel', default=None, help='Optional installed Chromium channel, e.g. msedge')
parser.add_argument('--screens', nargs='+', help='Optional focused rerun; CI runs all fixtures')
args = parser.parse_args()
public = (args.project / 'public').resolve()
screens = json.loads((args.fixtures / 'screens.json').read_text())
responses = json.loads((args.fixtures / 'responses.json').read_text())
if args.screens:
    if not set(args.screens) <= set(screens): raise ValueError('Unknown screen fixture')
    screens = args.screens
proof = args.fixtures / 'screenshots'
proof.mkdir(exist_ok=True)
errors, results, missing = [], [], []
allowed_aborted_paths = {'/developer/panel/debug/live'}

# Resolve translucent surfaces before measuring text or input boundaries.
metrics = r'''(e) => {
  if (typeof e === 'string') e = document.querySelector(e);
  const s = getComputedStyle(e);
  const rgba = value => { const n = value.match(/[\d.]+/g).map(Number); return [...n.slice(0,3), n[3] ?? 1]; };
  const over = (fg,bg) => fg.slice(0,3).map((x,i)=>x*fg[3]+bg[i]*(1-fg[3])).concat(1);
  const bg = node => {
    const layers=[];
    for(let n=node;n;n=n.parentElement) layers.push(rgba(getComputedStyle(n).backgroundColor));
    return layers.reverse().reduce((b,a)=>over(a,b),[255,255,255,1]);
  };
  const lum = c => c.slice(0,3).map(x=>x/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4).reduce((s,x,i)=>s+x*[.2126,.7152,.0722][i],0);
  const ratio = (a,b) => (Math.max(lum(a),lum(b))+.05)/(Math.min(lum(a),lum(b))+.05);
  return { contrast: ratio(over(rgba(s.color),bg(e)),bg(e)),
    border: Math.min(ratio(rgba(s.borderTopColor),bg(e)),ratio(rgba(s.borderTopColor),bg(e.parentElement))),
    font:s.fontFamily, size:parseFloat(s.fontSize), width:e.getBoundingClientRect().width,
    height:e.getBoundingClientRect().height };
}'''

with sync_playwright() as p:
    browser = p.chromium.launch(channel=args.channel, headless=True)
    page = browser.new_page()
    page.on('pageerror', lambda error: errors.append(str(error)))
    def route(r):
        path = unquote(urlparse(r.request.url).path)
        if path.strip('/') in screens:
            response = responses[path.strip('/')]
            r.fulfill(path=str(args.fixtures / (path.strip('/')+'.html')), content_type='text/html',
                      status=response['status'], headers=response['headers'])
            return
        target = (public / path.lstrip('/')).resolve()
        if target.is_relative_to(public) and target.is_file():
            r.fulfill(path=str(target), content_type=mimetypes.guess_type(target)[0] or 'application/octet-stream')
        else:
            if path in allowed_aborted_paths:
                r.abort()
                return
            # No network requests leave this fixture browser, including optional integrations.
            missing.append(path)
            r.abort()
    page.route('**/*', route)
    def require(condition, message):
        if not condition: errors.append(f'{name}/{width}/{theme}: {message}')
    for width in (1440, 768, 390, 320):
        page.set_viewport_size({'width': width, 'height': 1000})
        for name in screens:
            for theme in ('default', 'dark'):
                page.goto('http://brand-regression.test/'+name)
                page.add_style_tag(content='*, *::before, *::after { transition: none !important; animation: none !important; }')
                page.evaluate('Promise.all([document.fonts.load(\'400 16px "Space Grotesk"\'), document.fonts.load(\'600 16px "Space Grotesk"\'), document.fonts.load(\'400 14px "JetBrains Mono"\'), document.fonts.load(\'600 14px "JetBrains Mono"\')])')
                page.evaluate('theme => window.FNLLARUNTIME.setTheme(theme)', theme)
                page.evaluate('document.fonts.ready')
                if name == 'panel-technical-debt': page.locator('.debt-row').last.evaluate('(e)=>e.open=true')
                if name == 'debug-active': page.locator('#fnlla-debug-toolbar').evaluate('(e)=>e.open=true')
                overflow = page.evaluate('document.documentElement.scrollWidth > innerWidth + 1')
                require(not overflow, 'horizontal page overflow')
                require(page.evaluate('document.fonts.check(\'16px "Space Grotesk"\') && document.fonts.check(\'14px "JetBrains Mono"\')'), 'brand fonts unavailable')
                if name == 'client-preview':
                    require(page.evaluate(metrics, '.client-preview-icon-svg')['width'] <= 24, 'preview icon is unbounded')
                    require(not page.locator('[data-client-preview-fallback]').is_visible(), 'unexpected fallback form')
                    page.screenshot(path=str(proof / f'{name}-{width}-{theme}.png'), full_page=True, animations='disabled')
                    page.locator('[data-client-preview-modal-launch] button').click()
                    require(page.locator('#client-preview-unlock-modal').is_visible(), 'preview unlock modal did not open')
                    page.keyboard.press('Escape')
                    require(page.locator('#client-preview-unlock-modal').is_visible(), 'locked preview dialog dismissed unexpectedly')
                if name == 'panel-tasks':
                    require(page.locator('.developer-kanban-task').count() >= 8, 'populated Kanban fixture missing')
                for selector in ('.fnlla-cookie-title', '.fnlla-cookie-text', '.developer-kanban-task-head strong',
                                 '.developer-kanban-task p', '.customer-kanban-card h3', '.customer-kanban-card p',
                                 '.developer-analytics-blueprint-copy h3', '.developer-analytics-blueprint-copy p',
                                 '.framework-update-stage .content-text', '.framework-update-channel-card .contact-text',
                                 '.client-preview-note-text'):
                    for item in page.locator(selector).all():
                        if item.is_visible():
                            require(item.evaluate(metrics)['contrast'] >= 4.5, f'{selector} text contrast')
                if name == 'login':
                    for selector in ('input[type=email]', 'input[type=password]'):
                        require(page.evaluate(metrics, selector)['border'] >= 3, f'{selector} boundary contrast')
                if name == 'debug-active':
                    require('JetBrains Mono' in page.evaluate(metrics, '#fnlla-debug-toolbar')['font'], 'debug font')
                for logo in page.locator('.fnlla-framework-wordmark img:visible').all():
                    require(logo.bounding_box()['width'] >= 239, 'wordmark below approved minimum')
                if name == 'home':
                    require(page.locator('h1').inner_text() == 'Brand Regression', 'project identity lost')
                    require('local runtime AI' not in page.locator('body').inner_text(), 'false local model claim')
                if name in ('maintenance-update', 'panel-framework-updates'):
                    require(page.locator('#framework-upgrade-target').input_value() == (args.project / 'VERSION').read_text().splitlines()[0].strip(), 'installed update target missing or stale')
                if name == 'contact':
                    page.locator('.fnlla-cookie-banner [data-fnlla-cookie-settings-open]').click()
                    require(page.locator('#fnlla-cookie-settings-modal').is_visible(), 'cookie settings did not open')
                    for selector in ('#fnlla-cookie-settings-title', '.fnlla-cookie-option small'):
                        require(page.evaluate(metrics, selector)['contrast'] >= 4.5, f'{selector} modal contrast')
                    page.locator('[data-fnlla-cookie-settings-close]').click()
                suffix = '-unlock' if name == 'client-preview' else ''
                page.screenshot(path=str(proof / f'{name}{suffix}-{width}-{theme}.png'), full_page=True, animations='disabled')
                results.append({'screen':name,'width':width,'theme':theme,'overflow':overflow})
        print('Completed viewport', width, flush=True)
    if 'client-preview' in screens:
        fallback = browser.new_page(java_script_enabled=False, viewport={'width': 390, 'height': 1000})
        fallback.route('**/*', route)
        fallback.goto('http://brand-regression.test/client-preview')
        if not fallback.locator('[data-client-preview-fallback]').is_visible():
            errors.append('client-preview/no-js: fallback form unavailable')
        fallback.screenshot(path=str(proof / 'client-preview-no-js.png'), full_page=True)
        fallback.close()
    browser.close()
report = {'results':results, 'errors':errors, 'missing_assets':sorted(set(missing))}
(args.fixtures / 'results.json').write_text(json.dumps(report, indent=2))
if missing: errors.append('Missing assets: ' + ', '.join(sorted(set(missing))))
for error in errors: print(error)
print(f'{len(results)} screen variants, {len(errors)} failures.')
raise SystemExit(1 if errors else 0)
