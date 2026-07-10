# Contributing to FNLLA PHP

## First, understand the repository

FNLLA PHP is an MIT-licensed framework maintained by TechAyo LTD (techayo.co.uk).

This repository is public and contributions are welcome, but it remains maintainer-led. Proposed changes are reviewed against framework scope, product direction, maintenance cost, client impact, security posture and long-term coherence.

Before opening work, read:

- [`README.md`](../README.md)
- [`LICENSE.md`](../LICENSE.md)
- [`SUPPORT.md`](../SUPPORT.md)
- [`TRADEMARKS.md`](../TRADEMARKS.md)
- [`SECURITY.md`](../SECURITY.md)
- [`CODE_OF_CONDUCT.md`](../CODE_OF_CONDUCT.md)
- [`.github/SUPPORT.md`](./SUPPORT.md)

## What kinds of contributions are welcome

The most useful contributions are:

- reproducible bug reports
- framework contract and docs parity reports
- small documentation clarifications
- narrowly scoped fixes aligned with the existing HTTP, CLI and FNLLA Web contract
- feature proposals that clearly justify shared framework value

## What usually will not be accepted

The following are commonly declined or redirected:

- one-off project customizations that belong in a downstream application
- broad rewrites without agreed scope
- changes that conflict with the documented framework boundary
- contributions that introduce unclear ownership or third-party IP risk
- large unsolicited pull requests that were not discussed first
- framework changes that attempt to detach the official FNLLA Web integration boundary

## Before writing code

Open an issue or proposal first when the change is non-trivial.

That is especially important for:

- routing, middleware or request lifecycle changes
- configuration contract changes
- authentication, session or security behavior changes
- database abstraction, migration or CLI contract changes
- naming changes
- structural tooling changes
- FNLLA Web guard or sync behavior changes

## Security issues

If the issue may be security-sensitive, stop and follow [`SECURITY.md`](../SECURITY.md) instead of opening a public issue or PR.

## Working rules for changes

When a change is accepted for implementation:

- edit first-party source files under `src/`, `bootstrap/`, `config/`, `routes/`, `views/` and `scripts/` where appropriate
- do not treat generated runtime state under `storage/` as a hand-authored source of truth
- keep `README.md`, `VERSION`, `LICENSE.md`, `SUPPORT.md` and `TRADEMARKS.md` aligned when release-facing behavior changes
- preserve the documented MySQL-only database boundary unless an explicit product decision changes it
- preserve the FNLLA Web runtime boundary under `public/vendor/fnlla-web/`
- keep GitHub as the source of truth for both `techayoDEV/fnlla-php` and `techayoDEV/fnlla-web`

## Maintainer workflow

Recommended local sequence:

```bash
php scripts/test.php
php scripts/lint.php
php scripts/validate-fnlla-web.php
php fnlla fnlla-web:sync
```

## Pull request expectations

A good pull request should:

- explain what changed and why it belongs in FNLLA PHP now
- describe framework, CLI, database, auth or UI-contract impact
- mention validation performed
- call out any release-surface implications

PRs may be closed without merge if they are out of scope, carry ownership risk, duplicate downstream-only needs or conflict with the framework direction.

## Licensing and rights

By submitting a contribution, you represent that:

- you have the right to submit the material
- the material does not knowingly violate another party's IP or confidentiality rights
- the contribution may be reviewed, modified, rejected or incorporated by TechAyo LTD under the repository's MIT licensing model

Submitting a contribution does not transfer ownership of FNLLA PHP branding or change the repository license.

## Support and contact

For general repository help, usage routing and business-boundary questions, use the guidance in [`.github/SUPPORT.md`](./SUPPORT.md).
