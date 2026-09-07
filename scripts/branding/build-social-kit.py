"""Build the single offline social workspace from both maintained Markdown kits."""
from pathlib import Path
import html
import tempfile
import markdown
from markdown.extensions.toc import slugify
from markdown.treeprocessors import Treeprocessor
from playwright.sync_api import sync_playwright
from product_copy import render_document

ROOT = Path(__file__).resolve().parents[2]
BRAND = ROOT / 'branding'


class AssetLinks(Treeprocessor):
    def run(self, root):
        for element in root.iter():
            for attribute in ('href', 'src'):
                value = element.get(attribute, '')
                if value.startswith('assets/'):
                    element.set(attribute, '../' + value)


def main():
    sections, navigation = [], []
    for platform, document in [('linkedin', 'LINKEDIN-PRODUCT'), ('facebook', 'FACEBOOK-KIT')]:
        parser = markdown.Markdown(extensions=['tables', 'fenced_code', 'toc'], extension_configs={
            'toc': {'slugify': lambda text, separator, prefix=platform: prefix + '-' + slugify(text, separator)}})
        parser.treeprocessors.register(AssetLinks(parser), 'asset-links', 5)
        content = parser.convert((BRAND / (document + '.md')).read_text(encoding='utf-8'))
        sections.append(f'<section data-platform="{platform}" id="panel-{platform}" role="tabpanel" aria-labelledby="tab-{platform}">{content}</section>')
        navigation.append(f'<div data-platform="{platform}">{parser.toc}</div>')
    gallery = ['<section data-platform="gallery" id="panel-gallery" role="tabpanel" aria-labelledby="tab-gallery"><h1>Galeria / Social assets</h1>']
    for title, directory in [('LinkedIn', 'linkedin-product'), ('Facebook', 'facebook-kit')]:
        gallery.append(f'<h2>{title}</h2><div class="asset-grid">')
        for image in sorted((BRAND / 'assets/social' / directory).glob('*.png')):
            url = '../' + image.relative_to(BRAND).as_posix()
            gallery.append(f'<figure><a href="{url}"><img src="{url}" loading="lazy" alt="{html.escape(image.stem)}"></a><figcaption><a href="{url}" download>{image.name}</a></figcaption></figure>')
        gallery.append('</div>')
    gallery.append('<h2>Avatary i covery</h2><div class="asset-grid">')
    for name in ('linkedin-avatar', 'linkedin-cover', 'facebook-avatar', 'facebook-cover'):
        url = '../assets/social/' + name + '.png'
        gallery.append(f'<figure><a href="{url}"><img src="{url}" loading="lazy" alt="{name}"></a><figcaption><a href="{url}" download>{name}.png</a></figcaption></figure>')
    gallery.append('</div></section>')
    sections.append(''.join(gallery))
    navigation.append('<div data-platform="gallery"><p>LinkedIn + Facebook</p><a href="../LINKEDIN-PRODUCT.md">LinkedIn Markdown</a><br><a href="../FACEBOOK-KIT.md">Facebook Markdown</a></div>')
    switcher = '<div class="platform-tabs" role="tablist" aria-label="Platforma">' + ''.join(
        f'<button id="tab-{key}" role="tab" aria-controls="panel-{key}" data-tab="{key}">{label}</button>'
        for key, label in [('linkedin', 'LinkedIn'), ('facebook', 'Facebook'), ('gallery', 'Galeria')]) + '</div>'
    output = render_document(''.join(sections), ''.join(navigation), 'SOCIAL-KIT', 'SOCIAL', switcher, '', local_assets='../assets')
    extra = '''<style>
[hidden]{display:none!important}.platform-tabs{position:sticky;top:0;z-index:3;display:flex;gap:8px;padding:12px 24px;background:#fff;border-bottom:1px solid #E5E7EB;height:64px}
.platform-tabs button[aria-selected=true]{background:#2563EB;border-color:#2563EB;color:white}
aside{top:64px;height:calc(100vh - 64px)}html{scroll-padding-top:88px}.actions{display:none}
nav [data-platform]>.toc>ul>li>a{display:none}nav [data-platform]>.toc>ul>li>ul{padding-left:0;border:0}
.asset-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}.asset-grid figure{margin:0}.asset-grid img{width:100%;height:auto}.asset-grid figcaption{font-size:14px;overflow-wrap:anywhere}
@media(max-width:850px){aside{top:0;height:auto}.platform-tabs{padding-inline:20px}.asset-grid{grid-template-columns:1fr}}
@media print{.platform-tabs{display:none}}
</style>'''
    script = '''<script>
const tabs = [...document.querySelectorAll('[data-tab]')];
function activate(key, scroll=false) {
 document.querySelectorAll('[data-platform]').forEach(e=>e.hidden=e.dataset.platform!==key);
 tabs.forEach(t=>{t.setAttribute('aria-selected',t.dataset.tab===key);t.tabIndex=t.dataset.tab===key?0:-1;});
 if(scroll) window.scrollTo(0,0);
}
function fromHash(){const h=location.hash.slice(1);activate(h.startsWith('facebook')?'facebook':h.startsWith('gallery')?'gallery':'linkedin');}
tabs.forEach((tab,index)=>{
 tab.addEventListener('click',()=>{location.hash=tab.dataset.tab;activate(tab.dataset.tab,true);});
 tab.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;e.preventDefault();const next=e.key==='Home'?0:e.key==='End'?tabs.length-1:(index+(e.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;tabs[next].click();tabs[next].focus();});
});
document.querySelector('aside details').open=!matchMedia('(max-width:850px)').matches;
window.addEventListener('hashchange',fromHash);fromHash();
</script>'''
    output = output.replace('</head>', extra + '</head>').replace('</body>', script + '</body>')
    target = BRAND / 'social/index.html'
    target.parent.mkdir(exist_ok=True)
    target.write_text(output, encoding='utf-8')
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(target.as_uri())
        page.evaluate("Object.defineProperty(navigator,'clipboard',{value:{writeText:async text=>window.copiedText=text}})")
        for key in ('linkedin', 'facebook', 'gallery'):
            page.locator(f'#tab-{key}').click()
            for button in page.locator(f'#panel-{key} button[data-copy]').all():
                expected = button.get_attribute('data-copy')
                button.click()
                assert page.evaluate('window.copiedText') == expected
            for width in (1280, 390):
                page.set_viewport_size({'width': width, 'height': 900})
                assert not page.evaluate('document.documentElement.scrollWidth>innerWidth+1')
        for image in page.locator('img').all():
            image.evaluate('(e)=>{e.loading="eager";}')
            image.evaluate('(e)=>e.decode()')
        page.locator('#tab-facebook').click()
        page.locator('#tab-facebook').press('ArrowLeft')
        assert page.locator('#tab-linkedin').get_attribute('aria-selected') == 'true'
        page.goto(target.as_uri())
        page.evaluate('document.fonts.ready')
        page.screenshot(path=str(Path(tempfile.gettempdir()) / 'fnlla-social-mobile.png'))
        page.set_viewport_size({'width': 1440, 'height': 1000})
        page.goto(target.as_uri() + '#facebook')
        page.evaluate('document.fonts.ready')
        assert page.locator('#tab-facebook').get_attribute('aria-selected') == 'true'
        page.screenshot(path=str(Path(tempfile.gettempdir()) / 'fnlla-social-desktop.png'))
        assert not errors, errors
        browser.close()
    print(target)


if __name__ == '__main__':
    main()
