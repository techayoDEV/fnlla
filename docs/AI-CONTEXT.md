# FNLLA AI Context

FNLLA is an AI-ready web framework, not a local AI engine. The full starter
includes a gateway to **FIONN AI, Persistent Personal Intelligence created by
TechAyo**, plus optional OpenAI API / Anthropic API integrations. FIONN AI
requires an appropriate developer account, API access and explicit configuration.
Its service and memory are not bundled with the framework. External calls are
disabled by default.

![AI integration boundary: the built-in FNLLA gateway connects to the separately operated FIONN AI service only after explicit activation with a developer account and API access. Optional OpenAI API and Anthropic API calls are disabled by default.](assets/brand/fnlla-ai-boundary.png)

FNLLA also includes local context-export and deterministic reference tools.
They prepare information for review; they do not run a language model:

```bash
php fnlla ai:context
php fnlla ai:context --json
php fnlla ai:context --output storage/framework/cache/my-ai-context.json
php fnlla ai:review-pack --target=2.2.0
php fnlla ai:upgrade-brief --target=2.2.0
php fnlla ai:redact --input storage/framework/cache/ai-review-pack.json
php fnlla ai:ask "How do I check release readiness?"
php fnlla ai:triage --input="route 404 on controller" --json
php fnlla ai:explain-log storage/logs/app.log
php fnlla ai:brief
php fnlla ai:providers --json
```

`ai:context` makes no external service calls. It writes a
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

Daily commands have different processing boundaries:

- `ai:ask` calls the selected provider. The default `local` driver matches stored
  reference answers without AI inference; an enabled `fionn`, `openai` or
  `anthropic` driver sends the question to its configured external service.
- `ai:triage` maps a short problem statement locally to likely areas and next tests.
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

## Commercial Review Workflow

For a real downstream application, treat AI context as supporting evidence, not
as approval to release. A useful review loop is:

1. Run the framework and project checks:

```bash
php fnlla project:acceptance --json
php fnlla security:audit --strict
php fnlla ops:backup-plan --verify
php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000
php scripts/test.php
php scripts/lint.php
```

2. Generate a redacted review pack:

```bash
php fnlla ai:review-pack --target=2.2.0
php fnlla ai:redact --input storage/framework/cache/ai-review-pack.json
```

3. Ask for focused review questions:

- security risks around auth, session, CSRF, upload and redirects
- missing tests for roles, CRUD, forms and maintenance preview
- performance regressions against documented baseline targets
- upgrade risks for files that are not part of the public API

4. Record human decisions in the downstream project issue, pull request or
release notes. Do not let AI output replace a maintainer sign-off, a staging
restore drill or product E2E test evidence.

## Sharing Rules

Before sharing a context pack outside the machine that generated it:

- run `ai:redact` even when the file was already generated by `ai:context`
- inspect the JSON for project names, private route names and internal comments
- remove generated logs, request payloads and customer data from attachments
- prefer private review systems over public paste services
- never commit `storage/framework/cache/*ai*`, `storage/logs/*` or provider
  transcripts

The context pack is intentionally compact so it can support review without
becoming a second copy of the application source.

## Runtime AI

FNLLA also includes a separate runtime AI surface for application features:
`runtime_ai()`. The helper resolves the provider selected by
`AI_RUNTIME_DRIVER` and returns `RuntimeAiProviderInterface`.

The compatibility driver `local` is a deterministic reference lookup, not AI
inference. It uses configured intents and stored answers without calling model
providers or remote APIs. A project can shape its rules through
`config/ai.php` and `.env`:

```php
$answer = runtime_ai()->answer("How do I reset my password?", [
    "page" => "support",
]);
```

For this local driver, the payload includes the stored answer, a match score in
the legacy `confidence` field, matched intent, suggested actions and local
sources. Optional record persistence is off by default. When
`AI_RUNTIME_LEARNING_ENABLED=true`, `remember()` can persist approved knowledge
records under `storage/` so the application can grow its own guided knowledge
base without committing user data or depending on a vendor. The setting's
historical name "learning" means storing approved records, not training a model.
These local records are not automatically sent to FIONN AI.

The integrated FNLLA bundle is not private customer data. It is server-side
framework seed data: generic intents, generic knowledge, local prompts and eval
fixtures. Project-specific learned records, support conversations and customer
knowledge must stay outside Git unless a downstream project deliberately creates
its own reviewed, non-sensitive package.

## Runtime Bundle Structure

The local runtime has its own integrated server-side bundle:

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

This is a deterministic reference and routing layer for project-owned answers,
not a local AI engine. The legacy runtime names describe API compatibility,
not model capabilities.

## Controlled FIONN AI Bridge

The full starter includes a built-in gateway to **FIONN AI, Persistent Personal
Intelligence created by TechAyo**. A FIONN developer account with suitable API
access and credentials is required for the service. FNLLA does not register or
link accounts automatically, include API credit or provide a local FIONN model.
The gateway is shipped code; the brain runs as a separately configured service.
Its HTTP connection remains opt-in. Built-in does not mean bundled model weights
or an already-running service, and the core-only plain profile excludes this surface.

The external provider owns its data, models and implementation. FNLLA ships only
an explicit HTTP adapter and its validation policy. The fionn driver name and
AI_FIONN_* configuration keys are retained as existing compatibility identifiers;
they do not require a particular organization to operate the application.

The bridge is `Fnlla\Php\Ai\FionnRuntimeBridge`. It only calls the configured
chat endpoint, sends `learning_mode=false`, drops sensitive context keys and
returns a normalized `fnlla.runtime_ai.answer.v1` payload. FIONN AI response fields
that expose private service paths, sessions, memory files or queue files are not
returned by FNLLA.

Only known boolean privacy/grounding flags are forwarded, never arbitrary nested
service metadata. FIONN AI confidence is service-reported when supplied and otherwise
unmeasured; FNLLA does not invent a score. FIONN AI token counts are local estimates,
not billing figures, and its unknown cost is `null`.

Configure the approved endpoint and credentials supplied for the developer
account. The hostname below is illustrative, not a public FIONN API address:

```dotenv
AI_RUNTIME_DRIVER=fionn
AI_FIONN_BRIDGE_ENABLED=true
AI_FIONN_ENDPOINT=https://fionn-api.example.com
AI_FIONN_CHAT_PATH=/api/chat
AI_FIONN_ALLOWED_HOSTS=fionn-api.example.com
AI_FIONN_API_TOKEN=replace-with-account-api-token
AI_FIONN_ALLOW_INSECURE_LOCALHOST=false
AI_FIONN_TIMEOUT_SECONDS=10
AI_FIONN_REPLY_MODE=memory_assisted
AI_FIONN_KNOWLEDGE_MODE=auto
```

The configured chat path must match the endpoint's supported FNLLA bridge
contract. No public developer portal URL is supplied until it exists. The
localhost exception is only for an explicitly provisioned development/test
service; it is not a local model included with FNLLA:

```dotenv
AI_FIONN_ENDPOINT=http://127.0.0.1:8765
AI_FIONN_ALLOWED_HOSTS=127.0.0.1
AI_FIONN_ALLOW_INSECURE_LOCALHOST=true
```

`Fnlla\Php\Ai\RuntimeAiProviderRegistry` and
`php fnlla ai:providers --json` expose provider readiness for operators and CI.
The status includes whether external calls are enabled, whether the endpoint
policy passed, the configured chat path and the accounting contract. Strict
security audit allows `fionn` only when that endpoint policy passes.
Readiness is a configuration-policy result, not proof of a successful account
login, live endpoint availability or API entitlement. Status checks do not call
the service or validate its token remotely.

### First Connection And Review

Obtain a developer account, an approved endpoint and API credentials through
TechAyo's FIONN AI access process. There is no public signup URL yet; contact
[`hello@techayo.co.uk`](mailto:hello@techayo.co.uk) about availability. FNLLA does
not provision access or promise account eligibility.

After configuring `AI_RUNTIME_DRIVER=fionn` and the approved bridge policy, run
`php fnlla ai:providers --json` to check local configuration. Then deliberately
send a non-sensitive request:

```console
php fnlla ai:ask "Reply with a short acknowledgement for this connection test." --json
```

Unlike the status check, this sends an API request and may consume service quota.
Confirm that the returned `driver` is `fionn` and review the reply; a local-driver
answer does not verify FIONN access. Authentication failures and timeouts remain
errors, not a silent switch to another provider. Saving settings never sends a
test request. Start subsequent use with a bounded architecture question, review
the answer and apply any code changes yourself.

### Continuity Without Hidden Memory Writes

FIONN AI's persistent personal intelligence can help a developer revisit approved
architecture decisions, reuse coding preferences and ask questions with less
repeated background, **when that context is available and authorised in the
connected FIONN account**. This is a service capability, not a persistence layer
implemented by FNLLA.

The current gateway requests `memory_assisted` replies by default, but always
sends `learning_mode=false`. It does not index repositories, upload source files
implicitly, synchronise memory or maintain persistent per-project chat sessions.
It exposes no memory-write API. A reply cannot be assumed to have saved a new
decision. The integration is not a replacement for versioned documentation.

Use a dedicated, least-privilege service account where available. Do not use a
developer's personal memory as shared end-user context. A multi-tenant product
must design and verify identity, consent and memory isolation in its application
and service before exposing the integration to customers.

The bridge deliberately forbids:

- training mutations
- learning queue writes
- secret forwarding
- session cookie forwarding
- admin endpoint calls

The host allowlist is mandatory, including on localhost. Redirects are not
followed, response bodies are bounded to 1 MiB and raw upstream errors are not
returned. Context filtering is a defensive key filter, not a guarantee that
arbitrary text is free of secrets: callers must review values before sending.
FIONN AI's service memory, models and availability remain a separate operational
responsibility. FNLLA does not call learning or administration endpoints.

## OpenAI API And Anthropic API

The full starter includes two opt-in, server-side text-generation adapters:
`OpenAiRuntimeProvider` uses OpenAI Responses; `AnthropicRuntimeProvider` uses
the Claude Platform Messages API. These are API integrations, not ChatGPT/Claude
chat-app subscriptions, bundled models, endorsements or autonomous agents.
The plain profile remains core-only and does not include the AI surface.

Configure Developer Panel > Integrations > AI providers with
`panel.settings.write` permission, or use `.env`:

```dotenv
AI_RUNTIME_ENABLED=true
AI_RUNTIME_DRIVER=openai
AI_OPENAI_ENABLED=true
AI_OPENAI_MODEL=replace-with-an-available-model-id
AI_OPENAI_API_KEY=replace-with-your-server-side-api-key
AI_OPENAI_MAX_OUTPUT_TOKENS=1024
AI_OPENAI_TIMEOUT_SECONDS=30
```

For Anthropic API use `AI_RUNTIME_DRIVER=anthropic` and the equivalent
`AI_ANTHROPIC_ENABLED`, `AI_ANTHROPIC_MODEL`, `AI_ANTHROPIC_API_KEY`,
`AI_ANTHROPIC_MAX_OUTPUT_TOKENS` and `AI_ANTHROPIC_TIMEOUT_SECONDS` keys.
No model is silently selected. Use a text-capable model supported by the
provider endpoint and your account. PHP cURL with a current CA trust store is
required for cloud requests; the local provider has no such requirement.

Here, cloud means processing by an external provider through its API, not a
third FNLLA product. The FNLLA display label is **Anthropic API**; it connects
directly to the **Claude API**, the name used in the provider's
[official overview](https://platform.claude.com/docs/en/api/overview). This is a
label clarification, not a different API or a connection to the Claude chat app.
Technical identifiers `anthropic`, `AnthropicRuntimeProvider`,
`AI_ANTHROPIC_*`, the `api.anthropic.com` hostname and `anthropic-version` header
remain unchanged for protocol and configuration compatibility.

### Verified HTTP Contracts

The adapters follow the official provider documentation reviewed for edition 2.2.0:

| Integration | Request | Authentication | Completion |
| --- | --- | --- | --- |
| OpenAI API | `POST https://api.openai.com/v1/responses`; `model`, `input`, `max_output_tokens`, `store=false`, `stream=false` | `Authorization: Bearer` | `status=completed`; aggregate assistant `output_text` blocks; reject refusals |
| Anthropic API | `POST https://api.anthropic.com/v1/messages`; `model`, user `messages`, `max_tokens`, `stream=false` | `Authorization: Bearer`; `anthropic-version: 2023-06-01` | `end_turn` or `stop_sequence`; accept text only, reject refusal details and incomplete turns |

Claude Platform still accepts the legacy `x-api-key` header, but FNLLA now uses
the documented Bearer form. Both integrations keep non-text reasoning out of
the displayed answer. They do not implement provider tools or agent loops.
Fixture tests verify these contracts; real credentials, account permissions,
billing and actual model availability require a deployment-specific live check.

```bash
php fnlla ai:providers --json
php fnlla ai:ask "Explain middleware ordering" --json
```

The first command inspects local configuration only. The second sends a billable
request when the selected cloud provider is enabled. No live API call is made
when saving settings. Blank password fields preserve existing keys; removing
a key is an explicit checkbox action. Keys never appear in rendered form values,
status reports, activity messages or validation flash. Store `.env` outside the
web root with restricted permissions; it is not an encrypted credential vault.
After changing deployed environment settings, rebuild cached configuration
and restart long-running application processes.

### Request And Response Boundaries

- Only the explicit `answer($input)` text is transmitted. The cloud adapters
  ignore the optional context array; they never discover files, logs, sessions,
  local knowledge or FIONN AI memory to append. Sanitize the question itself.
- `ai:triage` always uses the local assistant, even if runtime AI selects a
  cloud provider. Review/context pack generation remains local.
- Endpoints are fixed HTTPS URLs. TLS verification is enabled, redirects are
  disabled, total request timeout is capped at 60 seconds and response bodies
  at 1 MiB. Input is rejected, not silently truncated, over its configured
  character limit (hard cap 32,000). Output budgets are 64-8,192 tokens.
- There is no streaming, automatic retry, tool execution, file upload, automatic
  conversation history, model fallback or external learning API in these adapters.
  Add authentication, rate limits, spending budgets and output escaping before
  exposing generation to users. A token cap is not an account spending cap.
- OpenAI requests set `store=false`; this is not a promise of zero retention.
  Provider policies and account settings still apply to transmitted data.
- Only completed text answers are accepted. Refused, truncated, malformed and
  failed responses raise generic exceptions without echoing upstream bodies or
  credentials. Treat returned text as untrusted content, never executable code.
- Usage uses provider-reported tokens; missing counts and unknown cost are
  `null`, not zero. FIONN AI cost is also unknown (`null`). In the existing answer
  schema cloud confidence is `0` with `provider.confidence_measured=false`:
  it means unmeasured, not 0% factual accuracy. The CLI labels it accordingly.
- `provider_ready` means locally configured, not live-verified. Contract tests
  use injected response fixtures, not paid accounts or real model evaluations.

One selected driver handles runtime requests. FIONN AI remains a distinct service
boundary and is never used as a silent fallback for either cloud adapter.
Unknown driver names fail closed instead of silently selecting local knowledge.

Protocol references: [OpenAI text generation](https://developers.openai.com/api/docs/guides/text),
[Claude Platform Messages](https://platform.claude.com/docs/en/api/http/messages/create),
[Claude Platform authentication](https://platform.claude.com/docs/en/manage-claude/authentication),
[Claude Platform stop reasons](https://platform.claude.com/docs/en/build-with-claude/handling-stop-reasons).

## AI Product Worklist

These are the next AI tasks that would strengthen FNLLA without overclaiming:

1. Add a Developer Operations Panel screen that runs `ai:triage`, `ai:brief`,
   `app:map`, `upgrade:check` and `tech-debt:update --check` together for a
   release-candidate review.
2. Add runtime AI eval coverage for customer-facing support answers, onboarding
   guidance and policy explainers, with deterministic fixtures in
   `resources/fnlla-ai-runtime/evals/`.
3. Add a project-owned knowledge import command that accepts reviewed Markdown
   or JSON and writes only sanitized local knowledge records.
4. Extend the fixture-based provider contract tests with opt-in live account
   verification and application-specific answer evaluations. Adapters report
   latency and actual token counts where supplied, not invented costs.
5. Extend FIONN AI endpoint-policy and injected-transport tests with a deployed
   service smoke test covering application-specific authentication.
6. Add a UI flow for approved learning records so operators can review, accept,
   reject and expire local knowledge without editing storage files.
7. Add release-gate evidence that proves AI artifacts contain no raw `.env`,
   source-file bodies, provider transcripts or private customer data.
