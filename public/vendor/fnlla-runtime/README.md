# FNLLA integrated UI surface export

This directory is the integrated FNLLA UI surface handoff for downstream projects.

In the integrated `techayoDEV/fnlla` maintainer repository, this directory is the authoritative vendored UI surface consumed by FNLLA itself.

## Included files

- `assets/css/fnlla-runtime.css`
- `assets/js/fnlla-runtime.js`
- `assets/icons/`
- `MANIFEST.json`
- `LICENSE.md`
- `SUPPORT.md`
- `TRADEMARKS.md`
- `VERSION`

## How to use it

1. Copy this directory into the downstream project.
2. Link `assets/css/fnlla-runtime.css` from the page head.
3. Load `assets/js/fnlla-runtime.js` near the end of `body`.
4. Keep `assets/icons/` next to the runtime so local icon paths continue to work.

## Version

1.0.20

## Maintainer notes

- In this repository, `public/vendor/fnlla-runtime/` is the integrated UI surface that `fnlla-runtime:sync`, validation and downstream exports consume directly.
- `scripts/sync-fnlla-runtime.ps1` can sync from this integrated vendored runtime in a local `techayoDEV/fnlla` checkout or from a dedicated runtime export rooted elsewhere.
- If you maintain a separate runtime source checkout outside this repository, sync from its published export rather than from partial source fragments.
- Current framework metadata remains governed by README.md, VERSION, LICENSE.md, SUPPORT.md and TRADEMARKS.md in the repository root.
- Support and trademark boundaries remain governed by SUPPORT.md and TRADEMARKS.md in the repository root plus the integrated UI surface metadata shipped here.
- Machine-readable UI surface metadata remains governed by MANIFEST.json in this directory and the repository root.
- The repository root of `techayoDEV/fnlla` remains the authoritative maintainer workspace and documentation source of truth.
