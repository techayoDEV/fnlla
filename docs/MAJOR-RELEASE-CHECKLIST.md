# FNLLA Major Release Checklist

Use this checklist before tagging a public major release.

## Contract

- Public helpers and CLI commands are documented.
- JSON schemas from `doctor`, `security:audit`, `perf:*`, `upgrade:*`,
  `app:map` and `ai:*` are stable enough for CI usage.
- Release metadata contains no assistant-vendor markers that could imply the
  framework was built by a specific external tooling product.
- Downstream `make:project` exports include the same supported commands as the
  maintainer launcher where appropriate.

## Validation

```bash
php scripts/build-docs.php
php scripts/test.php --suite all
php scripts/lint.php
php scripts/validate-fnlla-runtime.php
php scripts/validate-version-manifest.php
php scripts/validate-release-metadata.php
php scripts/build-docs.php --check
php scripts/static-analysis.php
php fnlla upgrade:check --target=2.0.0
php fnlla app:map
php fnlla ai:review-pack --target=2.0.0
php fnlla release:prepare --major --target=2.0.0
```

## Performance

- Capture a stable local baseline with `php fnlla perf:baseline:update`.
- Compare after major-release changes with `php fnlla perf:compare`.
- Record representative benchmark numbers in release notes.

## Security And Privacy

- Run `php fnlla security:audit`.
- Review CORS, session, trusted host, trusted proxy and debug posture for the
  intended release environment.
- Review the configured mail transport before any public form notification
  workflow is enabled.
- Keep generated review artefacts local unless explicitly reviewed and approved.
- Do not commit generated cache, sessions, queues, logs, SBOMs or benchmark
  outputs.

## Publication

- Version metadata is synchronized.
- `CHANGELOG.md` and `docs/MIGRATION.md` match the final tag.
- SBOM and SHA-256 checksum artefacts are generated.
- Runtime surface validation passes after any final asset sync.
