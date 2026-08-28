# Production Checklist

Use this checklist before tagging a production release or deploying a downstream
FNLLA application. It is written as a gate: unchecked items are release
blockers unless the release owner records an explicit exception.

## Environment

- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `APP_URL` uses `https://`.
- `.env` exists only on the target environment and is not committed.
- `APP_KEY` is unique per environment.
- File permissions allow the web process to write only required storage paths.

## Production Readiness Levels

Use these labels during handover:

- `not-ready`: any required command below fails.
- `staging-ready`: the project passes acceptance, tests, lint and strict
  security audit on a staging-like environment.
- `release-ready`: staging-ready plus verified backup restore, current
  performance budget and tagged source state.
- `production-ready`: release-ready plus live HTTPS, host/proxy configuration,
  monitoring, backup retention and rollback access.

Do not call a deployment production-ready only because the homepage renders.

## HTTP Security

- HTTPS is enforced at the reverse proxy or web server.
- Trusted hosts are configured for every public hostname.
- Trusted proxies are configured when traffic passes through a load balancer,
  CDN or platform proxy.
- Secure cookies are enabled.
- SameSite cookie policy is set deliberately.
- CORS allows only known origins.
- CSP is enabled with a nonce-aware policy.
- Error pages do not expose stack traces.

## Auth And Sessions

- Admin/operator/client roles are explicitly tested.
- Protected routes have unauthorized-flow tests.
- Password reset and first-password setup links expire.
- Session files or Redis keys are excluded from backups.
- Client preview passwords are rotated before sharing with a real client.

## Data And Storage

- Migrations have run successfully on a staging copy.
- Destructive migrations have a rollback or manual recovery plan.
- Database writes that span multiple tables use `db()->transaction()`.
- Uploaded files are validated by size and extension.
- Upload download routes are protected by auth and gates.
- Logs, queues, sessions and cache are not included in release commits.

## Backup And Restore

Generate a redacted operational plan:

```bash
php fnlla ops:backup-plan --verify --output=framework/backup-plan.json
```

The real runbook must cover:

- database dump command and restore command;
- storage include paths;
- storage exclude paths;
- retention policy;
- encryption and access controls for backup archives;
- restore order;
- verification commands after restore.

Before deployment, verify the latest backup by restoring it to a non-production
environment, then run `php fnlla project:acceptance --json` on the restored
copy.

Restore evidence should record:

- source tag or commit;
- dump timestamp;
- storage archive timestamp;
- restore operator;
- target environment;
- post-restore command output summaries;
- known exceptions and follow-up actions.

## Runtime And Performance

- `php fnlla optimize:warm` completes successfully.
- `php fnlla project:acceptance --json` passes on the deployable source tree or
  restored staging copy.
- `php fnlla perf:baseline:update --iterations=7` has a current baseline.
- `php fnlla perf:budget --iterations=5 --max-regression=20 --max-regression-ms=1000`
  passes against that baseline.
- Baseline coverage includes command listing, route listing, `/`, `/api/health`
  and project export timing where the application deployment pipeline can run
  those probes.

## Runtime AI And Fionn

- Keep `AI_RUNTIME_DRIVER=local` unless the product explicitly needs Fionn.
- If `AI_RUNTIME_DRIVER=fionn`, keep `AI_FIONN_BRIDGE_ENABLED=true` only on
  environments where a reviewed Fionn service is available.
- Pin `AI_FIONN_ALLOWED_HOSTS` to the exact Fionn service host.
- Use HTTPS and `AI_FIONN_API_TOKEN` for every non-local Fionn endpoint.
- Use plain HTTP only for `localhost` or `127.0.0.1` development and staging
  drills where `AI_FIONN_ALLOW_INSECURE_LOCALHOST=true` is deliberate.
- Keep Fionn learning, training, admin and queue endpoints outside FNLLA
  application calls.
- Verify `php fnlla ai:providers --json` before release; the Fionn provider must
  report `provider_ready=true` and `endpoint_allowed=true` when selected.
- Run `php fnlla security:audit --strict`; it fails selected Fionn unless the
  endpoint policy passes.

## Release Gate

Run the production gate locally or in CI:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-fnlla-runtime.php
php scripts/validate-version-manifest.php
php scripts/validate-release-metadata.php
php scripts/build-docs.php --check
php fnlla doctor
php fnlla security:audit --strict
php fnlla project:acceptance --json
php fnlla release:prepare --major --target=2.1.1
```

Strict security audit is a production blocker. If it fails, fix the
configuration or document why the release is not production-ready.

## Deployment

- Deploy from a tagged release.
- Deploy the exact source state that passed the release gate.
- Keep production `.env`, storage, uploads and hosting files outside framework
  update replacement paths.
- Warm caches after deploy.
- Check `/api/health` after deploy.
- Keep a rollback tag, database backup and storage backup available.

## Post-Deploy Verification

After deployment, verify:

- `APP_URL` resolves over HTTPS;
- `/api/health` returns JSON and the expected readiness state;
- protected pages reject guests;
- maintenance/client preview mode can be enabled and unlocked with the current
  credentials;
- logs are writable and do not expose secrets;
- form notifications use the intended mail transport;
- backup jobs can read the expected include paths.
