"""Build an offline, copy-friendly companion from the maintained product Markdown."""
import base64
from pathlib import Path

import markdown

ROOT = Path(__file__).resolve().parents[2]


def build_copy(delivery, document='LINKEDIN-PRODUCT', platform='LINKEDIN'):
    parser = markdown.Markdown(extensions=['tables', 'fenced_code', 'toc'])
    content = parser.convert((ROOT / f'branding/{document}.md').read_text(encoding='utf-8'))
    template = render_document(content, parser.toc, document, platform)
    target = delivery / f'{document}.html'
    target.write_text(template, encoding='utf-8')
    return target


def render_document(content, toc, document, platform, switcher='', actions=None, local_assets=None):
    template = (ROOT / 'branding/source/linkedin-product-copy.html').read_text(encoding='utf-8')
    fonts = []
    for family, filename in [('Space Grotesk', 'SpaceGrotesk'), ('JetBrains Mono', 'JetBrainsMono')]:
        for weight, suffix in [(400, 'Regular'), (600, 'SemiBold')]:
            if local_assets:
                source = f'{local_assets}/fonts/{filename}-{suffix}.ttf'
            else:
                encoded = base64.b64encode((ROOT / f'branding/assets/fonts/{filename}-{suffix}.ttf').read_bytes()).decode('ascii')
                source = 'data:font/ttf;base64,' + encoded
            fonts.append(f"@font-face{{font-family:'{family}';font-weight:{weight};src:url('{source}')}}")
    if local_assets:
        logo = local_assets + '/logo/outline/wordmark-blue.svg'
    else:
        logo = 'data:image/svg+xml;base64,' + base64.b64encode((ROOT / 'branding/assets/logo/outline/wordmark-blue.svg').read_bytes()).decode('ascii')
    if actions is None:
        actions = f'<a href="index.html">Galeria</a><a href="{document}.md" download>Markdown</a>'
    for key, value in {'CONTENT': content, 'TOC': toc, 'FONTS': '\n'.join(fonts), 'LOGO_URL': logo,
                       'PLATFORM': platform, 'DOCUMENT': document, 'SWITCHER': switcher, 'ACTIONS': actions}.items():
        template = template.replace('{{' + key + '}}', value)
    return template


def check_copy(browser, delivery, fixtures, document='LINKEDIN-PRODUCT'):
    page = browser.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto((delivery / f'{document}.html').as_uri())
    page.evaluate('document.fonts.ready')
    # Assert exact clipboard payloads without modifying the operator's system clipboard.
    page.evaluate("Object.defineProperty(navigator, 'clipboard', {configurable:true, value:{writeText:async text=>window.copiedText=text}})")
    buttons = page.locator('button[data-copy]')
    if buttons.count() < 20:
        raise RuntimeError('Expected copy controls for snippets, captions and profile fields')
    for button in buttons.all():
        expected = button.get_attribute('data-copy')
        button.click()
        if page.evaluate('window.copiedText') != expected:
            raise RuntimeError('Copy payload differs from the displayed source')
    page.evaluate("Object.defineProperty(navigator, 'clipboard', {configurable:true, value:{writeText:async()=>{throw Error('denied')}}});document.execCommand=()=>false")
    button = buttons.first
    button.click()
    if page.locator('#manual-text').input_value() != button.get_attribute('data-copy'):
        raise RuntimeError('Manual selection fallback failed')
    if not page.locator('#manual-copy').is_visible():
        raise RuntimeError('Manual clipboard fallback is not visible')
    page.locator('#manual-copy button').click()
    for width in (1280, 390):
        page.set_viewport_size({'width': width, 'height': 900})
        page.goto((delivery / f'{document}.html').as_uri())
        page.evaluate('document.fonts.ready')
        if page.evaluate('document.documentElement.scrollWidth > innerWidth + 1'):
            raise RuntimeError('Copy companion overflows at ' + str(width))
        page.screenshot(path=str(fixtures / f'copy-preview-{width}.png'), full_page=False)
    if errors:
        raise RuntimeError('\n'.join(errors))
    page.close()
