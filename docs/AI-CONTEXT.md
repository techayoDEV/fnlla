# FNLLA AI Context

FNLLA includes a local, privacy-first AI context pack:

```bash
php fnlla ai:context
php fnlla ai:context --json
php fnlla ai:context --output storage/framework/cache/my-ai-context.json
php fnlla ai:review-pack --target=2.0.0
php fnlla ai:upgrade-brief --target=2.0.0
php fnlla ai:redact --input storage/framework/cache/ai-review-pack.json
php fnlla ai:ask "How do I check release readiness?"
php fnlla ai:triage --input="route 404 on controller" --json
php fnlla ai:explain-log storage/logs/app.log
php fnlla ai:brief
php fnlla ai:providers --json
```

The command does not call any external AI service and does not prove or imply
that the repository was built with any particular AI product. It writes a
redacted JSON snapshot that a developer can choose to provide to a private
review tool during code review, release preparation or architecture work.

## What It Includes

The context pack includes:

- framework and PHP version metadata
- application environment posture without raw `.env` values
- route method, path, name, middleware and dynamic-route flags
- selected non-secret configuration posture
- cache, route, asset, preload and performance-baseline artefact presence
- documentation index
- repository footprint by major directory
- recommended review workflows for tool-assisted code review

`ai:review-pack` combines the redacted context, `app:map` output and
`upgrade:check` report into one review artefact. `ai:upgrade-brief` writes a
short Markdown brief for migration review. `ai:redact` redacts sensitive-looking
keys from any local JSON artefact before a developer chooses to share it.

`ai:ask`, `ai:triage`, `ai:explain-log`, `ai:brief` and `ai:providers` are the practical daily commands. They stay local, deterministic and small:

- `ai:ask` answers from configured local knowledge.
- `ai:triage` maps a short problem statement to likely areas and next tests.
- `ai:explain-log` reads the tail of a local log and returns probable cause, likely area and a suggested test filter.
- `ai:brief` gives a short project summary for review handoff.
- `ai:providers` reports configured runtime AI providers, readiness state and whether any provider is allowed to make external calls.

Runtime AI responses and provider status reports include accounting fields for
token estimates, cost and latency. The local driver reports zero cost, but the
fields are still present so future remote adapters cannot skip metering.

## What It Excludes

The context pack intentionally excludes:

- raw `.env` contents
- database credentials
- API tokens
- cookie/session secrets
- raw source file contents
- request logs and user data

Keys that look sensitive are defensively replaced with `[redacted]` even inside
the already allowlisted payload.

## Recommended Use

Good prompts to run against the generated file:

- “Review this FNLLA context for release risks and missing tests.”
- “Find routes or middleware that need extra security review.”
- “Suggest performance improvements based on route shape and runtime artefacts.”
- “Write a PR checklist for these framework changes.”

For enterprise or business use, keep the pack in local CI artefacts or a private
review workspace. Do not commit generated context files.

## Runtime AI

FNLLA also includes a separate runtime AI surface for application features:
`Fnlla\Php\Ai\LocalRuntimeAssistant`, available through `runtime_ai()`.

This runtime assistant is deliberately self-contained. The default driver is
`local`, uses configured intents and project knowledge, and does not call model
providers or remote APIs. A project can shape its direction through
`config/ai.php` and `.env`:

```php
$answer = runtime_ai()->answer("How do I reset my password?", [
    "page" => "support",
]);
```

The returned payload includes the answer, confidence, matched intent, suggested
actions and local sources. Optional learning is off by default. When
`AI_RUNTIME_LEARNING_ENABLED=true`, `remember()` can persist approved knowledge
records under `storage/` so the application can grow its own guided knowledge
base without committing user data or depending on a vendor.

## Runtime Bundle Structure

The local runtime has its own private integrated bundle:

```text
resources/fnlla-ai-runtime/
  VERSION
  MANIFEST.json
  README.md
  profile.json
  intents/
    core.json
  knowledge/
    base.json
  prompts/
    registry.json
  evals/
    runtime-commands.json
```

This mirrors the discipline of the UI runtime, but it is not a public browser
asset. It stays under `resources/` because intents, knowledge and assistant
direction are server-side runtime data. `AI_RUNTIME_PATH` can point to another
project-local bundle, and `AI_RUNTIME_LOAD_INTEGRATED=false` can disable the
integrated records when a project wants a fully custom local knowledge base.

Use this split:

- integrated bundle: stable framework-owned defaults
- `config/ai.php`: project-owned additions and overrides
- `storage/framework/ai/runtime-knowledge.json`: approved learned records
- `resources/fnlla-ai-runtime/prompts/registry.json`: reusable review, triage, release and migration prompts
- `resources/fnlla-ai-runtime/evals/`: shape fixtures for runtime commands and future provider adapters
- public assets: never store runtime intelligence data here

Useful end-user features built on this local runtime include:

- guided FAQ and support routing
- onboarding assistants for dashboards or portals
- form triage before a support ticket is submitted
- product or service recommendation flows based on local project rules
- policy explainers for account, delivery, returns or booking rules
- admin hints that point operators to the right internal page

This is not a general large language model. It is a predictable local
intelligence layer for project-owned knowledge, deterministic routing and
auditable user guidance.

## Future Fionn AI Bridge

FNLLA reserves a provider boundary for a future Fionn AI adapter, but it does
not integrate with Fionn yet.

Fionn is treated as a separate TechAyo-owned intelligence system with memory,
reviewed knowledge, controlled learning, model packages, evals and a local chat
server. FNLLA should not import Fionn code, write to its learning queue or call
its server until a stable service contract is reviewed.

The reserved bridge is `Fnlla\Php\Ai\FionnRuntimeBridge`. Its current status is `reserved`, `provider_ready=false` and `external_calls=false`. It exists so future work can integrate Fionn deliberately behind the same small runtime AI shape instead of scattering Fionn-specific calls through controllers.

`Fnlla\Php\Ai\RuntimeAiProviderRegistry` and `php fnlla ai:providers --json` expose this status for operators and CI. The bridge can be configured with `AI_FIONN_BRIDGE_ENABLED`, `AI_FIONN_ENDPOINT` and `AI_FIONN_TIMEOUT_SECONDS`, but those values do not enable network calls while the integration state remains `reserved`.

Fionn is the only reserved external AI provider boundary for FNLLA. The built-in `local` driver remains a deterministic project-knowledge runtime, while arbitrary third-party provider adapters are blocked by policy.

Before enabling a real Fionn adapter, complete:

- stable Fionn request and response schemas
- authentication and endpoint policy
- redaction and no-secrets forwarding checks
- prompt registry and eval fixtures
- cost, token and latency accounting
- explicit operator controls for reviewed knowledge and controlled learning
