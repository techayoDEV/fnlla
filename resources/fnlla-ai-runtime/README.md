# FNLLA Runtime Intelligence Bundle

`resources/fnlla-ai-runtime` is the integrated server-side runtime intelligence
bundle for FNLLA.

It is intentionally separate from `public/vendor/fnlla-runtime/`:

- the UI runtime is a public browser asset
- this runtime is generic server-side framework knowledge and must not be served directly
- approved learning records stay under `storage/`, not inside this bundle

## Included files

- `VERSION`
- `MANIFEST.json`
- `profile.json`
- `intents/`
- `knowledge/`
- `prompts/registry.json`
- `evals/`

The prompt registry stores reusable review, triage, release and migration
prompts as versioned local data. Eval fixtures define the minimum shape expected
from `ai:ask`, `ai:triage`, `ai:explain-log`, `ai:brief` and future provider
adapters before any remote runtime is considered.

## How it is loaded

`Fnlla\Php\Ai\LocalRuntimeAssistant` reads this bundle when
`AI_RUNTIME_LOAD_INTEGRATED=true`. Project configuration in `config/ai.php`
can add or override intents and knowledge without editing the integrated
runtime files.

## Version

2.2.3

## Security boundary

This bundle is safe to commit because it contains generic framework-owned
defaults, fixtures and local review prompts. It must not contain:

- real user conversations
- customer records
- provider API keys
- secrets copied from `.env`
- production logs or exception traces
- downstream business strategy that should remain private

Projects that enable local learning should store reviewed records under
`storage/` and decide separately whether those records can be backed up,
exported or reviewed by humans. The integrated bundle remains read-only
framework material.

FIONN AI is created by TechAyo; its gateway is built into the full starter.
FIONN AI-specific memory, private knowledge, model packages, evals, learning queue
state and service implementation do not belong in this repository. FNLLA keeps
only a small opt-in HTTP bridge contract in `FionnRuntimeBridge`; the FIONN AI
system itself is reached through an explicit API/service boundary with endpoint
policy checks, redaction and accounting fields.

## Maintainer checklist

When changing this bundle:

- keep `MANIFEST.json`, `VERSION` and command output in sync
- update `docs/AI-CONTEXT.md` when public behavior changes
- keep eval fixtures deterministic and small
- avoid provider-specific prompts unless a stable provider contract exists
- run `php scripts/test.php` and `php scripts/lint.php`
