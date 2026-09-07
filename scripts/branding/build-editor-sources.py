"""Build layered Photoshop sources from the same layouts as the brand exports."""

import argparse
import importlib.util
import json
import math
from io import BytesIO
from pathlib import Path
import subprocess
import tempfile

import fitz
from PIL import Image
from reportlab.lib.pagesizes import A3, landscape

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("brand", Path(__file__).with_name("build-guide.py"))
brand = importlib.util.module_from_spec(spec)
spec.loader.exec_module(brand)


def scene(data, directory, ppi=72):
    directory.mkdir()
    scale = ppi / 72
    font_names = {
        "SpaceGrotesk-Regular": "Brand", "SpaceGrotesk-SemiBold": "BrandBold",
        "JetBrainsMono-Regular": "Code", "JetBrainsMono-SemiBold": "CodeBold",
    }
    with fitz.open(stream=data, filetype="pdf") as document:
        page = document[0]
        width, height = math.ceil(page.rect.width * scale), math.ceil(page.rect.height * scale)
        page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False).save(directory / "composite.png")
        spans = [span for block in page.get_text("dict")["blocks"] if "lines" in block
                 for line in block["lines"] for span in line["spans"]
                 if span["text"].strip() and not set(span["text"].strip()) <= set("01 ")]
        layers = []
        for index, span in enumerate(spans):
            x, baseline = (value * scale for value in span["origin"])
            size, text = span["size"] * scale, span["text"]
            font = font_names.get(span["font"])
            if font is None:
                raise ValueError(f"Unknown editable font: {span['font']}")
            bounds = [max(0, math.floor(span["bbox"][0] * scale)-2), max(0, math.floor(span["bbox"][1] * scale)-2),
                      min(width, math.ceil(span["bbox"][2] * scale)+2), min(height, math.ceil(span["bbox"][3] * scale)+2)]
            color = f"#{span['color']:06x}"
            bitmap = brand.render_art(width, height,
                lambda p: p.text(text, x, baseline-size, size, font, color), transparent=True)
            filename = f"text-{index:03d}.png"
            Image.open(BytesIO(bitmap)).crop(bounds).save(directory / filename)
            layers.append({"text": text, "font": span["font"], "size": size,
                           "origin": [x, baseline], "color": span["color"], "bounds": bounds, "bitmap": filename})
            page.add_redact_annot(fitz.Rect(span["bbox"]), fill=False)
        # Remove text only. Keep logo paths, diagrams, QR modules and decorative microtype.
        page.apply_redactions(images=0, graphics=0, text=0)
        page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False).save(directory / "artwork.png")
    return {"directory": str(directory), "width": width, "height": height, "ppi": ppi, "layers": layers}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--node-modules", required=True, type=Path,
                        help="External tool directory containing ag-psd 31.0.2 and pngjs 7.0.0")
    parser.add_argument("--node", default="node")
    args = parser.parse_args()
    brand.register_fonts()
    brand.campaign_outputs()
    brand.repository_outputs()
    layouts = dict(brand.SOURCE_ART)
    layouts["poster-a3"] = brand.build_poster(landscape(A3))
    guide = fitz.open(stream=brand.build_pdf(), filetype="pdf")
    cover = fitz.open()
    cover.insert_pdf(guide, from_page=0, to_page=0)
    layouts["guidebook-cover"] = cover.tobytes()
    with tempfile.TemporaryDirectory(prefix="fnlla-editable-") as temp:
        temp = Path(temp)
        scenes = [{"name": name, **scene(data, temp / name, ppi=300 if name == "poster-a3" else 72)}
                  for name, data in layouts.items()]
        manifest = temp / "scenes.json"
        manifest.write_text(json.dumps(scenes), encoding="utf-8")
        subprocess.run([args.node, str(Path(__file__).with_name("write-psd.cjs")), str(manifest),
                        str(args.node_modules.resolve()), str(ROOT / "branding/source/photoshop")], check=True)


if __name__ == "__main__":
    main()
