"""Build the optional brand handoff from Markdown, tokens and vector masters."""

from __future__ import annotations

import argparse
from hashlib import sha256
from html import escape
from io import BytesIO
import json
import os
from pathlib import Path
import re
import tempfile
import xml.etree.ElementTree as ET

import fitz
from reportlab.graphics import renderPDF
from reportlab.graphics.barcode.qr import QrCodeWidget
from reportlab.graphics.shapes import Drawing
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A3, A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph
from svglib.svglib import svg2rlg
from PIL import Image
import zxingcpp
from fontTools.ttLib import TTFont as WebFont
from fontTools.pens.reportLabPen import ReportLabPen
from fontTools.pens.transformPen import TransformPen


ROOT = Path(__file__).resolve().parents[2]
BRAND = ROOT / "branding"
TOKENS = json.loads((BRAND / "tokens.json").read_text(encoding="utf-8"))
COLORS = TOKENS["color"]
W, H = 960, 640
PAGES = 14
MAINTAINER = "Created & maintained by " + TOKENS["maintainer"]
LEAD = "Lead Developer / Product Manager - " + TOKENS["lead"]
DOMAIN = TOKENS["domain"]
CREATOR_DOMAIN = TOKENS["maintainer_domain"]
BINARY_SIGNATURE = "".join(f"{value:08b}" for value in b"FNLLA")
SOURCE_ART = {}
OUTLINE_STYLES = {
    "blue": ("blue", None), "black": ("logo_black", None), "grey": ("logo_grey", None),
    "on-blue": ("white", "blue"), "on-black": ("white", "logo_black"), "on-grey": ("white", "logo_grey"),
}


def outline_svg(name, style):
    """Recolour the compound paths without changing any outline or counter geometry."""
    ET.register_namespace("", "http://www.w3.org/2000/svg")
    root = ET.parse(BRAND / "assets/logo" / f"{name}.svg").getroot()
    ns = {"svg": "http://www.w3.org/2000/svg"}
    foreground, background = OUTLINE_STYLES[style]
    root.find("svg:title", ns).text = f"FNLLA outline {name} / {style}"
    root.find("svg:desc", ns).text = "Original FNLLA outline geometry in an approved colour treatment."
    for group in root.findall("svg:g", ns):
        path = group.find("svg:path", ns)
        path.set("fill", COLORS[foreground])
    if background:
        _, _, width, height = root.attrib["viewBox"].split()
        root.insert(2, ET.Element("{http://www.w3.org/2000/svg}rect", {
            "width": width, "height": height, "fill": COLORS[background]}))
    return ET.tostring(root, encoding="utf-8", xml_declaration=True)


def register_fonts():
    for name, file in [("Brand", "SpaceGrotesk-Regular.ttf"),
                       ("BrandBold", "SpaceGrotesk-SemiBold.ttf"),
                       ("Code", "JetBrainsMono-Regular.ttf"),
                       ("CodeBold", "JetBrainsMono-SemiBold.ttf")]:
        with WebFont(BRAND / "assets/fonts" / file) as source:
            expected_weight = 600 if name.endswith("Bold") else 400
            if source["OS/2"].usWeightClass != expected_weight:
                raise ValueError(f"Incorrect source font weight: {file}")
        pdfmetrics.registerFont(TTFont(name, str(BRAND / "assets/fonts" / file)))


def colour(name):
    return HexColor(COLORS.get(name, name))


def contrast(foreground, background="white"):
    def luminance(name):
        rgb = colour(name)
        channels = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4
                    for c in (rgb.red, rgb.green, rgb.blue)]
        return sum(c * k for c, k in zip(channels, (0.2126, 0.7152, 0.0722)))
    values = sorted((luminance(foreground), luminance(background)))
    return (values[1] + 0.05) / (values[0] + 0.05)


class Page:
    def __init__(self, pdf, width=W, height=H):
        self.pdf, self.w, self.h = pdf, width, height

    def rect(self, x, y, w, h, fill="white", stroke=None, radius=0):
        self.pdf.setFillColor(colour(fill))
        self.pdf.setStrokeColor(colour(stroke or fill))
        self.pdf.setLineWidth(0.7)
        if radius:
            self.pdf.roundRect(x, self.h-y-h, w, h, radius, fill=1, stroke=bool(stroke))
        else:
            self.pdf.rect(x, self.h-y-h, w, h, fill=1, stroke=bool(stroke))

    def line(self, x, y, xx, yy, ink="border", weight=0.8, dash=None):
        self.pdf.saveState()
        self.pdf.setStrokeColor(colour(ink))
        self.pdf.setLineWidth(weight)
        self.pdf.setDash(dash or [])
        self.pdf.line(x, self.h-y, xx, self.h-yy)
        self.pdf.restoreState()

    def text(self, value, x, y, size=12, font="Brand", ink="ink"):
        value = str(value)
        if value in (DOMAIN, CREATOR_DOMAIN, "FIONN AI"):
            font = "CodeBold"
        elif value == TOKENS["maintainer_email"]:
            font = "Code"
        width = pdfmetrics.stringWidth(value, font, size)
        if x < 0 or x + width > self.w + 0.1 or y < 0 or y + size > self.h:
            raise ValueError(f"Text outside page: {value}")
        self.pdf.setFillColor(colour(ink))
        self.pdf.setFont(font, size)
        self.pdf.drawString(x, self.h-y-size, value)

    def paragraph(self, value, x, y, width, height, size=10.5, leading=15, ink="text", font="Brand"):
        style = ParagraphStyle("copy", fontName=font, fontSize=size, leading=leading,
                               textColor=colour(ink), spaceAfter=0)
        literal = r"(?<![\w@])(?:hello@techayo\.co\.uk|techayo\.co\.uk|fnlla\.com|FIONN AI)(?![\w@])"
        copy = re.sub(literal, lambda match: '<font name="Code">' + match.group() + '</font>', escape(value))
        paragraph = Paragraph(copy, style)
        _, needed = paragraph.wrap(width, height)
        if needed > height:
            raise ValueError(f"Copy exceeds its frame ({needed} > {height}): {value[:75]}")
        paragraph.drawOn(self.pdf, x, self.h-y-needed)

    def logo(self, name, x, y, width, style=None):
        source = BytesIO(outline_svg(name, style)) if style else str(BRAND / "assets/logo" / f"{name}.svg")
        drawing = svg2rlg(source)
        factor = width / drawing.width
        height = drawing.height * factor
        drawing.scale(factor, factor)
        renderPDF.draw(drawing, self.pdf, x, self.h-y-height)
        return height

    def binary_field(self, x, y, width, height, size=9, ink="binary_light", continuous=False):
        """Grouped octets form a quiet signal field, never a live-data or memory claim."""
        self.pdf.saveState()
        clip = self.pdf.beginPath()
        clip.rect(x, self.h-y-height, width, height)
        self.pdf.clipPath(clip, stroke=0, fill=0)
        row_step, column_step = (1.4, 0.85) if continuous else (1.7, 0.95)
        rows, cols = int(height/(size*row_step)), int(width/(size*column_step))
        for row in range(rows):
            for col in range(cols):
                if not continuous and (col % 10 >= 8 or row % 7 == 6):
                    continue
                seed = (row*31 + col*17 + (row ^ col)*13) % 23
                edge = min(col+1, cols-col, row+1, rows-row)
                if not continuous and seed < 2 and edge < 3:
                    continue
                strength = 1 if continuous else min(1, edge/3) * (0.55 if (row // 7 + col // 10) % 3 else 1)
                self.pdf.setFillAlpha(strength)
                index = row*cols+col if continuous else row*8 + col-col//10*2
                bit = BINARY_SIGNATURE[index % len(BINARY_SIGNATURE)]
                self.text(bit, x+col*size*column_step, y+row*size*row_step, size, "Code", ink)
        self.pdf.restoreState()

    def binary_signature(self, x, y, width, height, size=7):
        """Clip the decorative FNLLA bytes to actual Mono glyphs, not a substitute logo."""
        class ClipPen(ReportLabPen):
            def _closePath(self):
                self.path.close()

        self.pdf.saveState()
        glyph_size = min(width / pdfmetrics.stringWidth("01", "CodeBold", 1), height / 0.76)
        path = self.pdf.beginPath()
        with WebFont(BRAND / "assets/fonts/JetBrainsMono-SemiBold.ttf") as font:
            glyphs, cmap = font.getGlyphSet(), font.getBestCmap()
            scale = glyph_size / font["head"].unitsPerEm
            cursor = x
            for char in "01":
                glyph = glyphs[cmap[ord(char)]]
                glyph.draw(TransformPen(ClipPen(glyphs, path), (scale, 0, 0, scale, cursor, self.h-y-height)))
                cursor += glyph.width*scale
        self.pdf.setFillColor(colour("blue"))
        self.pdf.setFillAlpha(0.18)
        self.pdf.drawPath(path, fill=1, stroke=0)
        self.pdf.setFillAlpha(1)
        self.pdf.clipPath(path, stroke=0, fill=0)
        self.binary_field(x, y, width, height, size=size, ink="blue", continuous=True)
        self.pdf.restoreState()

    def qr(self, target, x, y, size):
        self.rect(x, y, size, size, "white")
        drawing = Drawing(size, size)
        drawing.add(QrCodeWidget(target, barWidth=size, barHeight=size, barBorder=4, barLevel="M"))
        renderPDF.draw(drawing, self.pdf, x, self.h-y-size)
        self.pdf.linkURL(target, (x, self.h-y-size, x+size, self.h-y), relative=0)


def read_sections():
    source = (BRAND / "BRAND-GUIDE.md").read_text(encoding="utf-8")
    sections = []
    chunks = re.split(r"^## (\d{2}) \| (.+)$", source.split("\n## Sources")[0], flags=re.M)
    for pos in range(1, len(chunks), 3):
        number, title, body = chunks[pos:pos+3]
        parts = re.split(r"^### (.+)$", body, flags=re.M)
        blocks = [(parts[i], " ".join(parts[i+1].split())) for i in range(1, len(parts), 2)]
        if len(blocks) != 3:
            raise ValueError(f"Section {number} requires three concise rule blocks")
        sections.append((int(number), title, " ".join(parts[0].split()), blocks))
    if [s[0] for s in sections] != list(range(1, PAGES+1)):
        raise ValueError(f"Expected guide sections 01 through {PAGES}")
    return sections


def header(page, number, title, lead):
    page.rect(0, 0, W, H)
    if number != 1:
        page.binary_field(8, 163, 24, 419, size=6)
    page.binary_field(928, 163, 24, 419, size=6)
    page.text("FNLLA  /  BRAND STANDARDS", 48, 28, 10, "BrandBold")
    page.text(f"EDITION {TOKENS['edition']}     /     {number:02d}", 752, 28, 10, "Code", "muted")
    page.line(48, 53, 912, 53)
    page.text(title, 48, 73, 34, "BrandBold")
    page.paragraph(lead, 48, 121, 850, 38, size=12, leading=17)
    page.line(48, 603, 912, 603)
    page.text(DOMAIN, 48, 612, 14, "BrandBold", "blue")
    page.text(MAINTAINER, 220, 608, 8.5, "BrandBold", "text")
    page.text(CREATOR_DOMAIN, 450, 607, 10, "BrandBold", "blue")
    page.text(TOKENS["maintainer_email"], 614, 609, 8.5, ink="text")
    page.text(LEAD, 220, 623, 8.5, ink="muted")
    page.text(f"{number:02d} / {PAGES}", 858, 615, 9, "Code", "muted")


def rule_blocks(page, blocks):
    for i, (title, text) in enumerate(blocks):
        x = 48 + 296*i
        page.line(x, 432, x+32, 432, "blue", 2)
        page.paragraph(title, x, 446, 250, 34, size=12, leading=16, ink="ink", font="BrandBold")
        page.paragraph(text, x, 484, 270, 112, leading=14)


def illustration(page, number):
    # Preserve established technical plates while introducing origin and AI chapters early.
    number = {1: 1, 2: 13, 3: 2, 4: 14, **{n: n-2 for n in range(5, 15)}}[number]
    if number == 1:
        page.logo("wordmark", 58, 177, 625)
        page.line(747, 185, 747, 397)
        page.text("WEB FRAMEWORK", 777, 192, 9, "Code", "muted")
        page.text("PHP foundation", 777, 225, 13, "BrandBold")
        page.text("Developer tools", 777, 251, 13)
        page.line(777, 284, 912, 284)
        page.text("FIONN AI", 777, 303, 22, "BrandBold", "blue")
        page.paragraph("Persistent Personal Intelligence by TechAyo.", 777, 343, 135, 44, size=11, leading=14)
        page.text("API account required", 777, 398, 9, ink="muted")
    elif number == 2:
        page.rect(48, 179, 864, 226, "surface")
        page.text("ONE FRAMEWORK", 78, 199, 10, "Code", "muted")
        page.text("FNLLA", 78, 234, 39, "BrandBold")
        page.text("PHP foundation", 80, 294, 15)
        page.line(315, 272, 436, 272, "blue", 1.5)
        page.line(436, 230, 436, 343, "blue", 1.5)
        for y, title, sub in [(211, "Full", "Integrated project experience"),
                              (323, "Plain", "Advanced core-only choice")]:
            page.line(436, y+20, 482, y+20, "blue", 1.5)
            page.text(title, 506, y-3, 24, "BrandBold")
            page.text(sub, 620, y+6, 12, ink="text")
    elif number == 3:
        for i, style in enumerate(OUTLINE_STYLES):
            x, y = 48+(i % 3)*296, 177+(i // 3)*116
            _, background = OUTLINE_STYLES[style]
            page.rect(x, y, 272, 106, background or "surface")
            page.logo("wordmark", x+12, y+13, 180, style=style)
            page.logo("monogram", x+207, y+25, 50, style=style)
            page.text(style.upper().replace("ON-", "WHITE / "), x+12, y+83, 9, "Code", "white" if background else "text")
    elif number == 4:
        width = 390
        height = width*590/1841
        margin = height*TOKENS["clear_space_height_ratio"]
        page.rect(49, 179, 514, 227, "tint")
        page.logo("wordmark", 100, 229, width)
        for y in (229-margin, 229, 229+height, 229+height+margin):
            page.line(67, y, 544, y, "blue", 0.5, [3, 3])
        for x in (100-margin, 100, 490, 490+margin):
            page.line(x, 185, x, 398, "blue", 0.5, [3, 3])
        page.text("H / 4", 300, 190, 10, "Code", "blue")
        page.text("DIGITAL MINIMUM WIDTH", 610, 191, 10, "Code", "muted")
        for i, (name, width) in enumerate(TOKENS["minimum_logo_width_px"].items()):
            page.text(name.capitalize(), 610, 236+i*48, 15, "BrandBold")
            page.text(f"{width} px", 813, 236+i*48, 15, "Code", "blue")
        page.text("Use the dedicated asset for favicons.", 610, 387, 11, ink="muted")
    elif number == 5:
        swatches = [("blue", "Blueprint Blue"), ("ink", "Ink"), ("text", "Text"), ("muted", "Muted"),
                    ("tint", "Tint"), ("binary_light", "Binary field"), ("success_text", "Success text"), ("danger_text", "Error text")]
        for i, (name, label) in enumerate(swatches):
            x, y = 48+(i%4)*224, 180+(i//4)*119
            page.rect(x, y, 192, 37, name)
            page.text(label, x, y+45, 12, "BrandBold")
            page.text(COLORS[name], x, y+65, 10, "Code", "muted")
            ratio = "Decorative only / not text" if name in ("tint", "binary_light") else f"{contrast(name):.2f}:1 on white"
            page.text(ratio, x, y+83, 10, ink="text")
    elif number == 6:
        page.text("Space Grotesk", 48, 180, 44, "BrandBold")
        page.text("Readable by design.", 48, 247, 30)
        page.text("Aa Bb Cc 0123456789", 48, 304, 22)
        page.text("Regular 400  /  SemiBold 600", 48, 363, 11, "Code", "muted")
        page.line(526, 180, 526, 403)
        page.text("JetBrains Mono", 558, 187, 23, "Code")
        page.rect(554, 238, 358, 104, "surface")
        page.text("$ php fnlla route:list", 576, 262, 15, "Code", "blue")
        page.text("$ php fnlla config:doctor", 576, 299, 13, "Code")
        page.text("fnlla.com  /  hello@techayo.co.uk", 558, 357, 13, "CodeBold", "blue")
        page.text("URLs, email, code, metadata and FIONN AI", 558, 387, 11, ink="muted")
    elif number == 7:
        for x, label in [(48, "01 / SIGNATURE"), (344, "02 / FIELD"), (640, "03 / BAND")]:
            page.text(label, x, 182, 12, "Code", "blue")
        page.binary_signature(62, 232, 232, 129, size=4.4)
        page.binary_field(354, 231, 252, 126, size=8)
        page.rect(640, 227, 272, 139, "campaign_surface")
        page.rect(640, 227, 4, 139, "blue")
        page.text("Blue identifies.", 663, 249, 20, "BrandBold", "blue")
        page.text("Grey carries.", 663, 287, 20, "BrandBold")
        page.text(DOMAIN, 663, 335, 12, "CodeBold", "blue")
        page.text("Campaigns only. Not a new logo.", 48, 391, 10, ink="text")
        page.text("Decorative bytes. Not live data.", 344, 391, 10, ink="text")
        page.text("Reading first. Colour by role.", 640, 391, 10, ink="text")
    elif number == 8:
        page.rect(48, 179, 864, 226, "surface")
        page.rect(48, 179, 434, 226, "tint")
        page.logo("wordmark", 82, 207, 314)
        page.text("Build from blueprint.", 84, 331, 20, "BrandBold")
        page.text("Illustrative access-screen composition", 84, 374, 9, ink="muted")
        page.text("Developer access", 522, 195, 18, "BrandBold")
        page.text("Email", 522, 231, 10, "BrandBold")
        page.rect(522, 250, 340, 34, "white", "control_border", 4)
        page.text("developer@example.com", 534, 259, 11, "Code")
        page.rect(520, 305, 159, 40, "white", "blue", 6)
        page.rect(523, 308, 153, 34, "blue", radius=4)
        page.text("Sign in", 578, 317, 11, "BrandBold", "white")
        page.text("Recover access", 703, 317, 11, ink="blue")
        page.text("Visible focus and a clear recovery path", 522, 374, 10, ink="text")
    elif number == 9:
        page.pdf.drawImage(image_reader(social_outputs()[0]),
                           48, H-179-226, width=430.5, height=226)
        page.line(515, 179, 515, 405)
        page.pdf.drawImage(image_reader(social_outputs()[1]), 550, H-179-172, width=172, height=172)
        page.text("1200 x 630", 763, 209, 17, "Code", "blue")
        page.text("Open Graph", 763, 238, 12)
        page.text("1080 x 1080", 763, 290, 17, "Code", "blue")
        page.text("Avatar", 763, 320, 12)
        page.text("Built from the same master assets", 550, 381, 11, ink="muted")
    elif number == 10:
        page.text("CLEAR", 48, 184, 10, "Code", "success_text")
        page.text("Project created.", 48, 217, 30, "BrandBold")
        page.text("Run the application checks before deployment.", 48, 276, 12, ink="text")
        page.line(48, 312, 457, 312)
        page.text("A concrete outcome and a useful next action.", 48, 347, 12, ink="muted")
        page.rect(510, 179, 402, 226, "surface")
        page.text("AVOID UNVERIFIABLE CLAIMS", 538, 204, 10, "Code", "danger_text")
        page.paragraph("Enterprise-ready. Zero configuration. Faster than every alternative.",
                       538, 249, 340, 96, size=23, leading=30, ink="muted")
        page.text("Qualify scope. Publish evidence. Name limits.", 538, 359, 11)
    elif number == 11:
        samples = [("Aa", "blue", "white", "Link / light surface"),
                   ("Saved", "success_text", "success_surface", "Success / labelled state"),
                   ("Error", "danger_text", "danger_surface", "Error / labelled state")]
        for i, (label, foreground, background, note) in enumerate(samples):
            x = 48+i*296
            page.rect(x, 179, 272, 147, background, "border")
            page.text(label, x+24, 214, 35, "BrandBold", foreground)
            page.text(f"{contrast(foreground, background):.2f}:1", x+24, 275, 14, "Code", foreground)
            page.text(note, x, 342, 11, "BrandBold")
        page.text("Ratio checks are one part of accessibility, not a conformance claim.", 48, 386, 12, ink="muted")
    elif number == 12:
        page.text("ONE MAINTAINED KIT", 48, 184, 10, "Code", "blue")
        page.text("Source. Build. Review.", 48, 221, 29, "BrandBold")
        page.text("Brand rules + tokens + vector masters", 48, 277, 14)
        page.text("Reproducible exports. Verified links. Visual proof.", 48, 311, 12, ink="text")
        for x, domain, label in [(634, TOKENS["domain"], "THE FRAMEWORK"),
                                  (784, TOKENS["maintainer_domain"], "THE CREATOR")]:
            page.qr("https://" + domain, x, 183, 112)
            page.text(label, x, 308, 9, "Code", "muted")
            page.text(domain, x, 329, 12, "CodeBold", "blue")
        refs = [("WCAG: text contrast", "https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html"),
                ("WCAG: non-text contrast", "https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html"),
                ("Space Grotesk", "https://github.com/google/fonts/tree/main/ofl/spacegrotesk"),
                ("JetBrains Mono", "https://github.com/JetBrains/JetBrainsMono")]
        for x, (label, url) in zip((48, 300, 580, 764), refs):
            page.text(label, x, 381, 10, ink="blue")
            page.pdf.linkURL(url, (x, H-398, x+pdfmetrics.stringWidth(label, "Brand", 10), H-378), relative=0)
    elif number == 13:
        page.rect(48, 180, 864, 225, "tint")
        page.text("FINELLA GARDENS", 76, 207, 13, "Code", "blue")
        page.text("Dundee, Scotland.", 76, 242, 36, "BrandBold")
        page.text("Where the idea became a framework.", 78, 312, 16)
        page.text("A name with a real beginning.", 78, 367, 12, ink="muted")
        page.line(552, 205, 552, 378, "blue", 1)
        page.logo("wordmark", 593, 226, 280)
        page.text("FINELLA  >  FNLLA", 601, 353, 15, "Code", "blue")
    elif number == 14:
        page.text("BUILD WITH CONTINUITY.", 48, 177, 15, "Code", "blue")
        page.text("FIONN AI", 48, 217, 43, "BrandBold")
        page.text("Persistent Personal Intelligence", 48, 278, 20, "BrandBold", "blue")
        page.text("Created by TechAyo. Connected through FNLLA.", 48, 317, 13)
        page.text("Built-in gateway. Developer account + FIONN API access required.", 48, 350, 11)
        page.line(635, 219, 635, 372)
        page.text("OPTIONAL INTEGRATIONS", 670, 224, 10, "Code", "muted")
        page.text("OpenAI API", 670, 257, 21, "BrandBold")
        page.text("Anthropic API", 670, 294, 21, "BrandBold")
        page.text("Your keys. Your models. Opt-in.", 670, 345, 11)
        page.text("No local AI model is bundled. FIONN AI memory and permissions belong to the connected service.", 48, 393, 11, ink="muted")


def image_reader(data):
    from reportlab.lib.utils import ImageReader
    return ImageReader(BytesIO(data))


_SOCIAL = None


def social_outputs():
    global _SOCIAL
    if _SOCIAL is not None:
        return _SOCIAL
    output = []
    for width, height in [(1200, 630), (1080, 1080)]:
        stream = BytesIO()
        pdf = canvas.Canvas(stream, pagesize=(width, height), invariant=1, pageCompression=1)
        p = Page(pdf, width, height)
        p.rect(0, 0, width, height)
        if width == 1200:
            p.rect(0, 0, 16, height, "blue")
            p.binary_signature(805, 146, 290, 145, size=5.8)
            p.logo("wordmark", 80, 73, 570)
            p.text("WEB FRAMEWORK", 913, 90, 16, "Code", "muted")
            p.text("Build with continuity.", 80, 328, 54, "BrandBold")
            p.text(TOKENS["foundation"], 84, 409, 23, ink="text")
            p.text("FIONN AI by TechAyo / Persistent Personal Intelligence", 84, 457, 19, "BrandBold")
            p.line(80, 521, 1120, 521)
            p.text(DOMAIN, 80, 551, 24, "BrandBold", "blue")
            p.text("FIONN developer account and API access required", 483, 556, 15, ink="text")
        else:
            logo_width = 654
            logo_height = logo_width*594/817
            p.logo("monogram", (width-logo_width)/2, (height-logo_height)/2, logo_width)
        pdf.save()
        with fitz.open(stream=stream.getvalue(), filetype="pdf") as document:
            output.append(document[0].get_pixmap(alpha=False).tobytes("png"))
    _SOCIAL = tuple(output)
    return _SOCIAL


def render_art(width, height, draw, transparent=False, background=None, source_name=None):
    stream = BytesIO()
    pdf = canvas.Canvas(stream, pagesize=(width, height), invariant=1, pageCompression=1)
    page = Page(pdf, width, height)
    if background:
        page.rect(0, 0, width, height, background)
    draw(page)
    pdf.save()
    if source_name:
        SOURCE_ART[source_name] = stream.getvalue()
    with fitz.open(stream=stream.getvalue(), filetype="pdf") as document:
        return document[0].get_pixmap(alpha=transparent).tobytes("png")


def validate_outline_png(data, name, style):
    image = Image.open(BytesIO(data)).convert("RGBA")
    foreground, background = OUTLINE_STYLES[style]
    rgba = lambda key: tuple(round(value*255) for value in colour(key).rgb()) + (255,)
    # PDF rasterization can quantize an opaque sRGB channel by one level.
    matches = lambda actual, expected: actual[3] == expected[3] and all(abs(a-b) <= 1 for a, b in zip(actual[:3], expected[:3]))
    expected_background = rgba(background) if background else (0, 0, 0, 0)
    scale = image.width / (1841 if name == "wordmark" else 817)
    if not matches(image.getpixel((int(23*scale), int(200*scale))), rgba(foreground)):
        raise ValueError(f"Outline {name}/{style} lost its F edge")
    if not matches(image.getpixel((int(80*scale), int(200*scale))), expected_background):
        raise ValueError(f"Outline {name}/{style} must retain an open F interior")
    if not matches(image.getpixel((1, image.height-1)), expected_background):
        raise ValueError(f"Outline {name}/{style} has an incorrect background or alpha channel")
    if name == "wordmark" and not matches(image.getpixel((int(1644*scale), int(350*scale))), expected_background):
        raise ValueError(f"Outline {name}/{style} lost its A counter")


def validate_social_qr(data, displayed_width):
    image = Image.open(BytesIO(data)).convert("RGB")
    for width in (image.width, displayed_width):
        preview = image.resize((width, round(image.height*width/image.width)), Image.Resampling.LANCZOS)
        codes = [code.text for code in zxingcpp.read_barcodes(preview)]
        if codes != ["https://" + TOKENS["domain"]]:
            raise ValueError(f"Social QR must decode at both upload and {displayed_width}px display size")


def validate_cover_copy(name):
    """Keep headers readable at social display sizes, not just on the master artboard."""
    with fitz.open(stream=SOURCE_ART[name], filetype="pdf") as document:
        lines = [line.strip() for line in document[0].get_text().splitlines()
                 if line.strip() and not set(line.strip()) <= set("01 ")]
    if len(lines) > 8 or sum(len(line.split()) for line in lines) > 36:
        raise ValueError(f"Social cover copy exceeds its eight-line/36-word budget: {name}")
    if not any("account + API access required" in line for line in lines):
        raise ValueError(f"Social cover must retain the FIONN account requirement: {name}")


def repository_outputs():
    """Three complementary reading aids; the Markdown retains every important claim."""
    def repository(p):
        p.rect(0, 0, p.w, p.h)
        p.rect(0, 0, p.w, 8, "blue")
        p.text("FNLLA / AI-READY WEB FRAMEWORK", 72, 43, 21, "Code", "text")
        p.text(DOMAIN, 1305, 40, 28, "CodeBold", "blue")
        p.logo("wordmark", 70, 136, 770)
        p.binary_signature(1060, 148, 456, 280, size=8)
        p.text("Build from blueprint.", 72, 441, 72, "BrandBold")
        p.text("Setup. Private panel. Diagnostics. Updates.", 76, 552, 32, ink="text")
        p.text("FIONN AI", 76, 604, 24, "CodeBold", "blue")
        p.text("gateway / Developer account + API access required.", 222, 604, 24, ink="text")
        p.rect(0, 654, p.w, 146, "campaign_surface")
        p.rect(72, 687, 4, 71, "blue")
        p.text("YOUR APPLICATION. YOUR IDENTITY.", 97, 683, 24, "Code")
        p.text("One framework. A complete starting point.", 97, 726, 25, ink="text")
        p.text(MAINTAINER, 1040, 684, 23, "BrandBold")
        p.text(CREATOR_DOMAIN, 1040, 726, 27, "CodeBold", "blue")

    def workflow(p):
        p.rect(0, 0, p.w, p.h)
        p.text("From first setup to daily operations.", 64, 38, 46, "BrandBold")
        p.binary_field(1280, 35, 253, 73, size=10)
        for x, number, title, first, second in [
            (64, "01", "Start", "Project setup. Private access.", "Your application identity."),
            (568, "02", "Build", "Routes. Controllers. PHP views.", "Data, sessions and queues."),
            (1072, "03", "Operate", "Developer Panel. Diagnostics.", "Updates and release checks."),
        ]:
            p.line(x, 136, x+456, 136, "border", 2)
            p.text(number, x, 166, 58, "CodeBold", "blue")
            p.text(title, x+112, 180, 36, "BrandBold")
            p.text(first, x, 270, 26)
            p.text(second, x, 314, 26, ink="text")
        p.rect(0, 408, p.w, 72, "campaign_surface")
        p.text("Full: the integrated starter. Plain: an advanced core-only option.", 64, 432, 24, ink="text")

    def ai_boundary(p):
        p.rect(0, 0, p.w, p.h)
        p.text("FNLLA / CONNECTED INTELLIGENCE", 64, 30, 21, "Code", "muted")
        p.text("FIONN AI", 64, 86, 53, "CodeBold", "blue")
        p.text("Persistent Personal Intelligence, created by TechAyo.", 64, 167, 29)
        p.binary_field(1280, 46, 253, 144, size=10)
        p.rect(64, 252, 484, 234, "campaign_surface")
        p.rect(64, 252, 4, 234, "blue")
        p.text("Your FNLLA application", 88, 278, 32, "BrandBold")
        p.text("Built-in gateway", 88, 344, 30, "BrandBold", "blue")
        p.text("No local AI model bundled.", 88, 426, 25, ink="text")
        p.line(586, 361, 978, 361, "blue", 3)
        p.line(960, 349, 978, 361, "blue", 3)
        p.line(960, 373, 978, 361, "blue", 3)
        p.text("Explicit activation", 604, 313, 25, "BrandBold")
        p.text("Developer account + API access", 593, 392, 23, ink="text")
        p.text("FIONN AI", 1030, 278, 36, "CodeBold", "blue")
        p.text("A separately operated service.", 1030, 344, 26)
        p.text("Memory and permissions", 1030, 396, 26, ink="text")
        p.text("belong to your connected account.", 1030, 433, 26, ink="text")
        p.line(64, 535, 1536, 535)
        p.text("OPTIONAL INTEGRATIONS", 64, 568, 23, "Code", "muted")
        p.text("OpenAI API / Anthropic API", 608, 559, 34, "BrandBold")
        p.text("Your keys and models. External calls disabled by default.", 608, 613, 25, ink="text")
        p.text("No automatic repository ingestion or memory writes.", 64, 700, 25, ink="text")

    cover = Image.open(BytesIO(render_art(1600, 800, repository, source_name="readme-cover"))).convert("RGB")
    buffer = BytesIO()
    cover.save(buffer, "JPEG", quality=92, subsampling=0, optimize=True)
    outputs = {
        ROOT / "docs/assets/brand/fnlla-cover.jpg": buffer.getvalue(),
        ROOT / "docs/assets/brand/fnlla-workflow.png": render_art(1600, 480, workflow, source_name="workflow"),
        ROOT / "docs/assets/brand/fnlla-ai-boundary.png": render_art(1600, 760, ai_boundary, source_name="ai-boundary"),
    }
    for path, data in outputs.items():
        image = Image.open(BytesIO(data)).convert("RGB")
        if len(data) > 350_000 or image.getextrema()[0][1] - image.getextrema()[0][0] < 100:
            raise ValueError(f"Oversized or blank repository artwork: {path.name}")
    return outputs


def campaign_outputs(cover_band="campaign_surface"):
    outputs = {}
    for name, width, height in [("wordmark", 2400, 770), ("monogram", 1200, 873)]:
        for style in OUTLINE_STYLES:
            svg = outline_svg(name, style)
            master = ET.parse(BRAND / f"assets/logo/{name}.svg").getroot()
            geometry = lambda root: [(e.get("d"), e.get("fill-rule")) for e in root.iter() if e.tag.endswith("}path")]
            if geometry(ET.fromstring(svg)) != geometry(master):
                raise ValueError(f"Outline geometry changed: {name}/{style}")
            outputs[BRAND / f"assets/logo/outline/{name}-{style}.svg"] = svg
            png = render_art(
                width, height, lambda p, n=name, s=style, w=width: p.logo(n, 0, 0, w, style=s),
                transparent=OUTLINE_STYLES[style][1] is None, background=OUTLINE_STYLES[style][1])
            validate_outline_png(png, name, style)
            outputs[BRAND / f"assets/logo/outline/{name}-{style}.png"] = png
    avatar = Image.open(BytesIO(social_outputs()[1]))
    for platform, size in [("linkedin", 400), ("facebook", 1024)]:
        buffer = BytesIO()
        avatar.resize((size, size), Image.Resampling.LANCZOS).save(buffer, "PNG")
        outputs[BRAND / f"assets/social/{platform}-avatar.png"] = buffer.getvalue()
    outputs[BRAND / "assets/logo/wordmark-transparent.png"] = render_art(
        2400, 770, lambda p: p.logo("wordmark", 0, 0, 2400), transparent=True)
    outputs[BRAND / "assets/logo/monogram-transparent.png"] = render_art(
        1200, 873, lambda p: p.logo("monogram", 0, 0, 1200), transparent=True)

    band_text = "white" if cover_band == "blue" else "ink"
    band_accent = "white" if cover_band == "blue" else "blue"
    def cover(p):
        # Identity, one product promise and one qualified connection; details belong in the profile.
        factor = p.w/2100
        p.pdf.scale(factor, factor)
        q = Page(p.pdf, 2100, p.h/factor)
        q.rect(0, 0, q.w, q.h)
        q.rect(0, 0, q.w, 9, "blue")
        q.binary_signature(40, 65, 220, 136, size=4.2)
        q.binary_field(1946, 36, 125, 165, size=12)
        q.logo("wordmark", 350, 46, 330, style="blue")
        q.text(DOMAIN, 350, 179, 34, "BrandBold", "blue")
        q.text(TOKENS["descriptor"], 756, 51, 44, "BrandBold")
        q.text(TOKENS["foundation"], 759, 117, 24)
        q.text("FIONN AI gateway", 760, 181, 26, "CodeBold", "blue")
        q.text("Developer account + API access required.", 1052, 187, 20, ink="text")
        q.qr("https://" + TOKENS["domain"], 1732, 35, 168)
        q.rect(0, 238, 2100, 112, cover_band)
        q.text(MAINTAINER, 350, 279, 22, "BrandBold", band_text)
        q.text(CREATOR_DOMAIN, 1130, 273, 30, "CodeBold", band_accent)
    linkedin = render_art(4200, 700, cover, source_name="linkedin-cover")
    validate_cover_copy("linkedin-cover")
    validate_social_qr(linkedin, 1128)
    outputs[BRAND / "assets/social/linkedin-cover.png"] = linkedin
    # Facebook crop-safe campaign preset; confirm the current Page preview before posting.
    def facebook(p):
        p.rect(0, 0, p.w, p.h)
        p.rect(0, 0, p.w, 12, "blue")
        p.binary_signature(46, 78, 210, 138, size=4.6)
        p.binary_field(46, 276, 210, 106, size=12)
        p.binary_field(1424, 38, 180, 348, size=13)
        p.logo("wordmark", 330, 38, 460, style="blue")
        p.text(DOMAIN, 965, 92, 48, "BrandBold", "blue")
        p.text(TOKENS["descriptor"], 330, 232, 51, "BrandBold")
        p.text(TOKENS["foundation"], 334, 305, 24)
        p.qr("https://" + TOKENS["domain"], 1170, 226, 160)
        p.rect(0, 419, p.w, 205, cover_band)
        p.text("FIONN AI gateway", 334, 445, 30, "CodeBold", band_accent)
        p.text("Developer account + API access required.", 334, 493, 22, ink=band_text)
        p.text(MAINTAINER, 334, 567, 22, "BrandBold", band_text)
        p.text(CREATOR_DOMAIN, 965, 563, 28, "CodeBold", band_accent)
    facebook_image = render_art(1640, 624, facebook, source_name="facebook-cover")
    validate_cover_copy("facebook-cover")
    validate_social_qr(facebook_image, 820)
    outputs[BRAND / "assets/social/facebook-cover.png"] = facebook_image
    return outputs


def webfont_outputs():
    outputs = {}
    target = ROOT / "public/assets/brand/fnlla/fonts"
    for name in ("SpaceGrotesk-Regular", "SpaceGrotesk-SemiBold", "JetBrainsMono-Regular", "JetBrainsMono-SemiBold"):
        font = WebFont(BRAND / f"assets/fonts/{name}.ttf", recalcTimestamp=False)
        font.flavor = "woff2"
        buffer = BytesIO()
        font.save(buffer, reorderTables=False)
        outputs[target / f"{name}.woff2"] = buffer.getvalue()
    for name in ("SpaceGrotesk-OFL.txt", "JetBrainsMono-OFL.txt"):
        outputs[target / name] = (BRAND / "assets/fonts" / name).read_bytes()
    return outputs


def source_outputs(guide, posters):
    """Keep text and vector geometry editable; do not disguise PDF bytes as an AI file."""
    outputs = {}
    documents = {"guidebook": guide, **SOURCE_ART, **posters}
    for name, data in documents.items():
        with fitz.open(stream=data, filetype="pdf") as document:
            for index, page in enumerate(document):
                suffix = f"-{index+1:02d}" if len(document) > 1 else ""
                svg = page.get_svg_image(text_as_path=False)
                root = ET.fromstring(svg)
                for element in root.iter():
                    if element.get("font-weight") == "bold":
                        element.set("font-weight", "600")
                    if element.tag.endswith("}tspan") and element.get("x"):
                        element.set("x", element.get("x").split()[0])
                    if element.tag.endswith("}text"):
                        element.set("style", "font-kerning:none;letter-spacing:0")
                title = ET.Element("{http://www.w3.org/2000/svg}title")
                title.text = f"FNLLA {name}{suffix} / editable vector source"
                root.insert(0, title)
                outputs[BRAND / f"source/vector/{name}{suffix}.svg"] = ET.tostring(root, encoding="utf-8", xml_declaration=True)
    return outputs


def verify_masters():
    version = (ROOT / "VERSION").read_text(encoding="utf-8").splitlines()[0].strip()
    if TOKENS["edition"] != version or TOKENS["domain"] != "fnlla.com":
        raise ValueError("Brand edition/domain must match the framework target and official website")
    if TOKENS["identity"] != "outline":
        raise ValueError("Only the original outline identity is approved")
    production = ROOT / "public/assets/brand/fnlla"
    for master, runtime in [("monogram.svg", "fnlla-monogram-outline-v3.svg"),
                            ("lockup.svg", "fnlla-recommended-lockup-outline-v3.svg"),
                            ("favicon.svg", "favicon.svg")]:
        if (BRAND / "assets/logo" / master).read_bytes() != (production / runtime).read_bytes():
            raise ValueError(f"Guide and runtime identity differ: {master}")
    for file in (BRAND / "assets/logo").glob("*.svg"):
        tree = ET.parse(file)
        if "viewBox" not in tree.getroot().attrib or any(e.tag.endswith("script") for e in tree.iter()):
            raise ValueError(f"Invalid logo master: {file.name}")
    for fg, bg in [("blue", "white"), ("text", "white"), ("muted", "white"),
                   ("blue", "campaign_surface"), ("ink", "campaign_surface"),
                   ("success_text", "success_surface"), ("danger_text", "danger_surface")]:
        if contrast(fg, bg) < 4.5:
            raise ValueError(f"Text contrast below 4.5:1: {fg} / {bg}")
    # Catch clipping/font regressions that leave an otherwise populated cover without its motif.
    signature = Image.open(BytesIO(render_art(
        300, 200, lambda page: page.binary_signature(10, 10, 280, 170), background="white"))).convert("RGB")
    for left in (0, 150):
        pixels = signature.crop((left, 0, left+150, 200)).get_flattened_data()
        blue_pixels = sum(b > r+40 and b > g+25 for r, g, b in pixels)
        if not 100 < blue_pixels < 15_000:
            raise ValueError("The decorative 01 signature must retain two nonblank, digit-built glyphs")


def build_pdf():
    stream = BytesIO()
    pdf = canvas.Canvas(stream, pagesize=(W, H), invariant=1, pageCompression=1)
    pdf.setTitle("FNLLA Brand Standards | Edition " + TOKENS["edition"])
    pdf.setAuthor("FNLLA / TechAyo")
    pdf.setSubject("Identity, typography, colour, product communication and asset handoff")
    pdf.setCreator("FNLLA brand tooling")
    for number, title, lead, blocks in read_sections():
        page = Page(pdf)
        pdf.bookmarkPage(f"section-{number}")
        pdf.addOutlineEntry(f"{number:02d} {title}", f"section-{number}")
        header(page, number, title, lead)
        illustration(page, number)
        rule_blocks(page, blocks)
        pdf.showPage()
    pdf.save()
    return stream.getvalue()


def validate_pdf(data):
    with fitz.open(stream=data, filetype="pdf") as document:
        if len(document) != PAGES:
            raise ValueError("Incomplete guidebook")
        for i, page in enumerate(document):
            text = page.get_text()
            if any(value not in text for value in (MAINTAINER, LEAD, DOMAIN, CREATOR_DOMAIN, TOKENS["maintainer_email"])):
                raise ValueError(f"Missing creator/maintainer contact or credit on page {i+1}")
            if len(text) < 800 or "fnlla.dev" in text or "defineBlueprint" in text:
                raise ValueError(f"Missing or obsolete content on page {i+1}")
            for x0, y0, x1, y1, *_ in page.get_text("blocks"):
                if x0 < 0 or y0 < 0 or x1 > W+0.5 or y1 > H+0.5:
                    raise ValueError(f"Content outside page {i+1}")
        ai_page = document[3].get_text()
        for expected in ("Persistent Personal Intelligence", "FIONN API access required", "No local AI model is bundled"):
            if expected not in ai_page:
                raise ValueError(f"Missing FIONN AI product boundary: {expected}")
        image = Image.open(BytesIO(document[-1].get_pixmap(dpi=150, alpha=False).tobytes("png")))
        if sorted(code.text for code in zxingcpp.read_barcodes(image)) != sorted(
                "https://" + TOKENS[key] for key in ("domain", "maintainer_domain")):
            raise ValueError("Guide handoff must contain scannable framework and creator QRs")


def build_poster(pagesize):
    stream = BytesIO()
    pdf = canvas.Canvas(stream, pagesize=pagesize, invariant=1, pageCompression=1)
    edition = TOKENS["edition"]
    pdf.setTitle(f"FNLLA | Build from blueprint. | Edition {edition}")
    pdf.setAuthor("FNLLA / TechAyo")
    pdf.setCreator("FNLLA brand tooling")
    pdf.setSubject("Wall poster with vector artwork and direct links to FNLLA and TechAyo")
    # One A3 composition scales uniformly; the QR quiet zone scales with its modules.
    base = landscape(A3)
    scale = min(pagesize[0]/base[0], pagesize[1]/base[1])
    pdf.scale(scale, scale)
    page = Page(pdf, *base)
    width, height = base
    left, right = 54, width-54
    page.rect(0, 0, width, height)
    page.rect(0, 0, 12, height, "blue")
    page.binary_field(1080, 108, 55, 211, size=10)
    page.binary_field(1036, 355, 99, 109, size=10)
    page.text("WEB FRAMEWORK / PHP FOUNDATION", left, 43, 13, "Code")
    label = f"EDITION {edition}"
    page.text(label, right-pdfmetrics.stringWidth(label, "Code", 12), 43, 12, "Code", "muted")
    page.logo("wordmark", left, 105, 620)
    page.rect(788, 112, 160, 31, "blue")
    page.text("AI-READY", 802, 119, 12, "Code", "white")
    page.text("FIONN AI", 784, 164, 40, "BrandBold", "blue")
    page.text("Persistent Personal", 786, 222, 23, "BrandBold")
    page.text("Intelligence.", 786, 252, 23, "BrandBold")
    page.text("Created by TechAyo.", 788, 296, 14)
    page.text("Build from blueprint.", left, 349, 65, "BrandBold")
    page.text("Build with continuity. Connect FIONN AI to your developer workflow.", left+2, 442, 22, ink="text")
    page.rect(12, 514, width-12, 201, "blue")
    for x, title, lines in [
        (left, "01 / BUILD", ["Project setup. Routing. Data.", "An integrated PHP foundation."]),
        (320, "02 / CONNECT", ["Built-in FIONN AI gateway.", "Your account. Your approved context."]),
        (596, "03 / OPERATE", ["Developer Panel. Diagnostics.", "Updates and release checks."]),
    ]:
        page.text(title, x, 544, 17, "Code", "white")
        for i, text in enumerate(lines):
            page.text(text, x, 586+i*25, 13, ink="white")
    page.text("Optional integrations: OpenAI API + Anthropic API. Your API keys, models and provider terms.", left, 662, 10, ink="white")
    page.text("FIONN developer account + API access required. Memory depends on service permissions. No local AI model bundled.", left, 685, 10, ink="white")
    for x, domain, label in [(916, TOKENS["domain"], "FRAMEWORK"), (1034, TOKENS["maintainer_domain"], "CREATOR")]:
        page.qr("https://" + domain, x, 545, 100)
        page.text(label, x+10, 656, 9, "Code", "white")
    page.text(DOMAIN, left, 738, 31, "BrandBold", "blue")
    page.text("BUILD WHAT'S NEXT.", 353, 742, 24, "BrandBold")
    page.text("Born in Dundee, Scotland.", 848, 752, 13, ink="text")
    page.line(left, 790, right, 790)
    page.text(MAINTAINER, left, 806, 10, "BrandBold")
    page.text(CREATOR_DOMAIN, 344, 804, 13, "BrandBold", "blue")
    page.text(LEAD, 650, 806, 10)
    pdf.save()
    return stream.getvalue()


def validate_poster(data, pagesize):
    with fitz.open(stream=data, filetype="pdf") as document:
        if len(document) != 1:
            raise ValueError("A wall poster must be exactly one page")
        page = document[0]
        if any(abs(actual-expected) > 0.1 for actual, expected in zip(page.rect[2:], pagesize)):
            raise ValueError("Incorrect print page size")
        text = page.get_text()
        for expected in (TOKENS["edition"], DOMAIN, CREATOR_DOMAIN, "blueprint.", TOKENS["maintainer"], LEAD):
            if expected not in text:
                raise ValueError(f"Missing poster text: {expected}")
        margin = 12
        for x0, y0, x1, y1, *_ in page.get_text("blocks"):
            if x0 < margin or y0 < margin or x1 > page.rect.width-margin or y1 > page.rect.height-margin:
                raise ValueError("Poster content outside printable area")
        for font in page.get_fonts():
            if font[2] != "Type1" and not document.extract_font(font[0])[3]:
                raise ValueError("Print font is not embedded")
        image = Image.open(BytesIO(page.get_pixmap(dpi=150, alpha=False).tobytes("png")))
        codes = zxingcpp.read_barcodes(image)
        if sorted(code.text for code in codes) != sorted("https://" + TOKENS[key] for key in ("domain", "maintainer_domain")):
            raise ValueError("Printed QRs must decode to the framework and creator websites")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="Compare generated output without writing files")
    args = parser.parse_args()
    register_fonts()
    verify_masters()
    pdf = build_pdf()
    validate_pdf(pdf)
    social, avatar = social_outputs()
    outputs = {BRAND / "guide/FNLLA-Brand-Guidebook.pdf": pdf,
               BRAND / "assets/social/open-graph.png": social,
               BRAND / "assets/social/avatar.png": avatar,
               ROOT / "public/assets/brand/fnlla/fnlla-open-graph-v3-1200x630.png": social}
    outputs.update(campaign_outputs())
    outputs.update(repository_outputs())
    outputs.update(webfont_outputs())
    signature = render_art(640, 360, lambda page: page.binary_signature(12, 12, 616, 326, size=10),
                           transparent=True, source_name="binary-signature")
    outputs[BRAND / "assets/patterns/binary-signature.png"] = signature
    outputs[ROOT / "public/assets/brand/fnlla/binary-signature.png"] = signature
    outputs[ROOT / "public/assets/brand/fnlla/wordmark.svg"] = outline_svg("wordmark", "blue")
    outputs[ROOT / "public/assets/brand/fnlla/wordmark-on-black.svg"] = outline_svg("wordmark", "on-black")
    posters = {}
    for name, size in [("A3", landscape(A3)), ("A4", landscape(A4))]:
        poster = build_poster(size)
        validate_poster(poster, size)
        outputs[BRAND / f"print/FNLLA-{TOKENS['edition']}-{name}.pdf"] = poster
        posters[f"poster-{name.lower()}"] = poster
    outputs.update(source_outputs(pdf, posters))
    for target, data in outputs.items():
        relative = target.relative_to(ROOT).as_posix()
        if args.check:
            if not target.is_file() or sha256(target.read_bytes()).digest() != sha256(data).digest():
                raise SystemExit(f"Stale brand output: {relative}")
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            if not target.is_file() or target.read_bytes() != data:
                # Publish a complete asset; an interrupted build must not truncate the previous export.
                temporary = None
                try:
                    with tempfile.NamedTemporaryFile(dir=target.parent, prefix=".brand-", suffix=".tmp", delete=False) as handle:
                        temporary = Path(handle.name)
                        handle.write(data)
                    os.replace(temporary, target)
                finally:
                    if temporary is not None:
                        temporary.unlink(missing_ok=True)
        print(f"{'Checked' if args.check else 'Built'} {relative} ({len(data):,} bytes)")
    if not args.check:
        proof = Path(tempfile.mkdtemp(prefix="fnlla-brand-proof-"))
        with fitz.open(stream=pdf, filetype="pdf") as document:
            for i, page in enumerate(document):
                page.get_pixmap(matrix=fitz.Matrix(1.5, 1.5), alpha=False).save(proof / f"page-{i+1:02d}.png")
        print(f"Review all {PAGES} rendered pages: {proof}")
        for target, data in outputs.items():
            if target.parent.name == "print":
                with fitz.open(stream=data, filetype="pdf") as document:
                    document[0].get_pixmap(dpi=110, alpha=False).save(proof / f"{target.stem}.png")
        print(f"Review both print sizes and test a physical QR proof: {proof}")


if __name__ == "__main__":
    main()
