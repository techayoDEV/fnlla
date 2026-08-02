# FNLLA Runtime Intelligence Bundle

`resources/fnlla-ai-runtime` is the integrated private runtime intelligence
bundle for FNLLA.

It is intentionally separate from `public/vendor/fnlla-runtime/`:

- the UI runtime is a public browser asset
- this runtime is server-side project knowledge and must not be served directly
- approved learning records stay under `storage/`, not inside this bundle

## Included files

- `VERSION`
- `MANIFEST.json`
- `profile.json`
- `intents/`
- `knowledge/`

## How it is loaded

`Fnlla\Php\Ai\LocalRuntimeAssistant` reads this bundle when
`AI_RUNTIME_LOAD_INTEGRATED=true`. Project configuration in `config/ai.php`
can add or override intents and knowledge without editing the integrated
runtime files.

## Version

2.0.0
