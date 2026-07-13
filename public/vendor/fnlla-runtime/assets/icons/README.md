# FNLLA Icons

This directory contains the internal `FNLLA Icons` bundle used by FNLLA's integrated UI surface.

`FNLLA Icons` is a branded local distribution based on the official Lucide static asset package.

Source package:

- `lucide-static` version `1.21.0`
- official project: `https://lucide.dev/`

Purpose:

- keep FNLLA integrated UI surface icon usage fully offline
- avoid any external CDN, font or icon requests
- provide both local individual SVG files and a local SVG sprite
- preserve upstream licensing and attribution inside the vendored bundle

Contents:

- individual SVG files in the root of this directory
- `sprite.svg` local SVG sprite
- `LICENSE` original Lucide ISC license plus embedded Feather/MIT notice where applicable
- `NOTICE.md` FNLLA integrated UI surface rebrand and attribution notice

Integration rule for the integrated FNLLA UI surface:

- use only files from this directory when rendering FNLLA Icons
- use runtime paths such as `assets/icons/search.svg` or `assets/icons/sprite.svg#settings-2` in project markup
- do not replace these references with `cdn`, `unpkg`, `jsdelivr` or other external hosts
- if the package is updated, keep the original Lucide license file and the FNLLA notice in place
