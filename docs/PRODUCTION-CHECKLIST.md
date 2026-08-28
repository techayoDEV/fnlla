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
php fnlla release:prepare --major --target=2.1.0
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
