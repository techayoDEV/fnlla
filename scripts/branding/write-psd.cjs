"use strict";
// Bitmap previews and native horizontal type layers share the verified PDF layout.
const fs = require("node:fs");
const path = require("node:path");
const [manifest, modules, destination] = process.argv.slice(2);
const { PNG } = require(path.join(modules, "pngjs"));
const { writePsdBuffer, readPsd } = require(path.join(modules, "ag-psd"));
const pixels = file => {
  const image = PNG.sync.read(fs.readFileSync(file));
  return { width: image.width, height: image.height, data: new Uint8ClampedArray(image.data) };
};
fs.mkdirSync(destination, { recursive: true });
for (const scene of JSON.parse(fs.readFileSync(manifest, "utf8"))) {
  const children = [{ name: "Artwork - vector masters supplied separately", imageData: pixels(path.join(scene.directory, "artwork.png")) }];
  for (const layer of scene.layers) {
    const [left, top, right, bottom] = layer.bounds;
    children.push({
      name: layer.text.slice(0, 100), left, top, right, bottom,
      imageData: pixels(path.join(scene.directory, layer.bitmap)),
      text: {
        text: layer.text, transform: [1, 0, 0, 1, ...layer.origin],
        shapeType: "point", orientation: "horizontal", antiAlias: "sharp",
        useFractionalGlyphWidths: true,
        style: { font: { name: layer.font }, fontSize: layer.size,
          fauxBold: false, fauxItalic: false, autoKerning: false, tracking: 0,
          fillColor: { r: (layer.color >> 16) & 255, g: (layer.color >> 8) & 255, b: layer.color & 255 } },
      },
    });
  }
  const data = writePsdBuffer({ width: scene.width, height: scene.height,
    imageResources: { resolutionInfo: {
      horizontalResolution: scene.ppi, horizontalResolutionUnit: "PPI", widthUnit: "Centimeters",
      verticalResolution: scene.ppi, verticalResolutionUnit: "PPI", heightUnit: "Centimeters",
    } },
    imageData: pixels(path.join(scene.directory, "composite.png")), children }, { generateThumbnail: false });
  const decoded = readPsd(data, { skipLayerImageData: true, skipCompositeImageData: true, skipThumbnail: true });
  const type = decoded.children.filter(layer => layer.text);
  if (decoded.imageResources?.resolutionInfo?.horizontalResolution !== scene.ppi ||
      type.length !== scene.layers.length || type.some((layer, i) => layer.text.text !== scene.layers[i].text)) {
    throw new Error(`Editable text roundtrip failed: ${scene.name}`);
  }
  const target = path.join(destination, `${scene.name}.psd`);
  fs.writeFileSync(`${target}.tmp`, data);
  fs.renameSync(`${target}.tmp`, target);
  process.stdout.write(`${scene.name}: ${type.length} editable text layers, ${data.length} bytes\n`);
}
