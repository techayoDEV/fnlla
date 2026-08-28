# Environment Configuration

FNLLA 2.1.1 uses two environment templates on purpose:

- `.env.example` is the short starter for a new project.
- `.env.full.example` is the complete reference for operators and maintainers.

New developers should start with `.env.example`. Production owners, CI
maintainers and teams enabling optional adapters should review
`.env.full.example` and this document before deployment.

## Why The Starter Is Short

A new FNLLA application should not begin with a wall of advanced switches. The
first decisions are usually:

- local or production environment;
- application URL and optional asset URL;
- database credentials;
- mail transport;
- maintenance/client-preview password;
- developer maintenance access;
- whether runtime AI stays local or uses the Fionn bridge.

Everything else has framework defaults in `config/*.php`. Keep `.env` focused on
values that really differ per environment.

## File Responsibilities

`.env.example` is safe to copy into `.env` during local development. It contains
no secrets and should stay readable enough for a first project export.

`.env.full.example` is a catalogue. It names advanced keys for framework
updates, Redis, session hardening, CORS, CSP, mail HTTP relays, observability,
release signing and runtime AI tuning. Do not copy it blindly into production.
Use it to discover available knobs, then move only needed values into the real
environment.

The real `.env` belongs to the target machine, hosting secret store or CI secret
configuration. It must not be committed.

## Configuration Layers

FNLLA applications have three practical layers:

- Framework layer: `bootstrap/`, `src/`, `config/`, `public/vendor/fnlla-runtime/`
  and project-facing CLI commands. This layer is updated from official FNLLA
  releases.
- Application layer: product routes, controllers, views, migrations, seeders,
  repositories, tests and user-facing content. This layer belongs to the
  downstream product team.
- Environment layer: `.env`, storage, logs, queue state, uploads, backups,
  deployment secrets and hosting configuration. This layer is never overwritten
  by framework updates.

`make:project` exports a working application base. After `project:claim`, the
project should treat FNLLA as the lower framework/runtime layer and build the
commercial product above it.

## Local Development Profile

Recommended local starter values:

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8080
SESSION_SECURE=false
DB_HOST=127.0.0.1
DB_DATABASE=fnlla
DB_USERNAME=root
DB_PASSWORD=
MAIL_MAILER=log
```

Start the local server:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

Then run:

```bash
php fnlla project:acceptance --json
php scripts/test.php
php scripts/lint.php
```

## Production Profile

Production `.env` should be minimal and strict:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
SESSION_SECURE=true
TRUSTED_HOSTS=example.com,www.example.com
TRUSTED_PROXIES=
SECURITY_HEADERS_PRESET=strict
STRICT_TRANSPORT_SECURITY=max-age=31536000; includeSubDomains
MAIL_MAILER=http
MAIL_HTTP_ENDPOINT=https://mail-relay.example.com/send
MAIL_HTTP_TOKEN=replace-with-secret
MAIL_HTTP_ALLOWED_HOSTS=mail-relay.example.com
```

Production-specific secrets should come from the platform secret store where
possible. A committed file should never contain database passwords, API tokens,
private keys, customer data, backup credentials or Fionn service tokens.

## Client Preview

Client preview is maintenance mode with a clear user-facing purpose: the site is
online for private review, but public access is password-protected.

Minimal preview setup:

```dotenv
MAINTENANCE_MODE_ENABLED=true
MAINTENANCE_ACCESS_PASSWORD=replace-with-client-preview-password
CLIENT_PREVIEW_ENABLED=true
CLIENT_PREVIEW_TITLE=Private client preview is active
CLIENT_PREVIEW_STATUS_TITLE=Password-protected preview mode is enabled
```

Rotate the preview password before sharing with a real client and again before
public launch.

## Runtime AI

The default runtime AI driver is local:

```dotenv
AI_RUNTIME_ENABLED=true
AI_RUNTIME_DRIVER=local
AI_RUNTIME_PATH=resources/fnlla-ai-runtime
AI_RUNTIME_LEARNING_ENABLED=false
```

Local runtime AI uses framework and project-owned knowledge only. It does not
call a model provider.

## Fionn Bridge

Fionn is intentionally more provocative than a generic chatbot integration:
FNLLA exposes a contract for connecting a business application to a separate,
owned intelligence service without surrendering the application boundary to a
third-party SDK. The idea is simple but opinionated: your web framework should
know how to talk to intelligence, but it should not contain the intelligence.

The public FNLLA repository contains only the bridge:

- selected provider resolution through `runtime_ai()`;
- normalized `fnlla.runtime_ai.answer.v1` responses;
- `POST` calls to the configured chat path;
- `learning_mode=false` on FNLLA-originated requests;
- sensitive context-key redaction;
- endpoint host allowlisting;
- HTTPS and service-token requirements for non-local endpoints;
- `ai:providers` status for CI and operators;
- `security:audit --strict` policy checks.

The Fionn brain stays outside FNLLA:

- model files;
- memory;
- reviewed knowledge;
- learning queues;
- evals;
- admin/training controls;
- private implementation code.

Local Fionn bridge example:

```dotenv
AI_RUNTIME_DRIVER=fionn
AI_FIONN_BRIDGE_ENABLED=true
AI_FIONN_ENDPOINT=http://127.0.0.1:8765
AI_FIONN_CHAT_PATH=/api/chat
AI_FIONN_ALLOWED_HOSTS=127.0.0.1,localhost
AI_FIONN_ALLOW_INSECURE_LOCALHOST=true
```

Non-local Fionn bridge example:

```dotenv
AI_RUNTIME_DRIVER=fionn
AI_FIONN_BRIDGE_ENABLED=true
AI_FIONN_ENDPOINT=https://fionn.internal.example
AI_FIONN_CHAT_PATH=/api/chat
AI_FIONN_ALLOWED_HOSTS=fionn.internal.example
AI_FIONN_API_TOKEN=replace-with-service-token
AI_FIONN_ALLOW_INSECURE_LOCALHOST=false
```

Before release, verify:

```bash
php fnlla ai:providers --json
php fnlla security:audit --strict
```

If Fionn is selected, the provider must report `provider_ready=true` and
`endpoint_allowed=true`.

## Operational Rule

Keep committed environment templates useful but boring. Real secrets, runtime
state and customer data belong in environment storage, not in Git. Optional
capabilities should be visible in `.env.full.example`, explained in docs and
enabled in production only after the matching audit command passes.
