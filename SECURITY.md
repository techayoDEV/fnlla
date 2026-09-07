# FNLLA Security Policy

FNLLA is maintained as a public MIT-licensed framework by TechAyo LTD (techayo.co.uk).
Product website: [fnlla.com](https://fnlla.com). Policy edition: **2.2.0**.

## Version And Support Boundary

The source tree identifies the 2.2.0 candidate. A source version is not evidence
of a published release or a security certification. Consult the repository's
[releases](https://github.com/techayoDEV/fnlla/releases) and security advisories
for available updates; do not assume an unpublished build has release support.
Report vulnerabilities in supported published releases and the current candidate.
There is no contractual response SLA or independently certified enterprise tier.

If you believe you have found a security issue in FNLLA or in its integrated UI surface boundary, please report it privately.

## Reporting routes

Use one of these routes:

1. GitHub private vulnerability reporting for this repository, when available.
2. TechAyo LTD's contact route at `https://techayo.co.uk`, clearly marked `FNLLA SECURITY`.

## Please include

- a clear description of the issue
- affected file, route, feature or bootstrap path
- environment details if relevant
- reproduction steps
- impact assessment
- any temporary mitigation already identified

## Immediate operator checks

If you operate a downstream project and suspect a security issue, collect local
evidence before making changes:

```bash
php fnlla security:audit --strict
php fnlla project:acceptance --json
php fnlla ops:backup-plan --verify
php scripts/test.php
php scripts/lint.php
```

Do not paste `.env`, database dumps, access tokens, session cookies or raw
customer data into public issues. Redact hostnames, private route names and
business identifiers when they are not needed to reproduce the framework issue.

## Deployment Boundaries

- Serve only `public/`. Never expose the repository root, `database/`, `storage/`,
  Composer credentials, backup archives or environment templates through HTTP.
- Use HTTPS, `APP_DEBUG=false`, production environment settings, trusted host/proxy
  allowlists and secure session cookies. Enable diagnostics only deliberately.
- Project Setup creates Developer Panel access, not application accounts. Protect
  application, developer and customer identities as separate authorization domains.
- Default seeders create no users. Older demo accounts are not removed by a code
  update: audit and revoke or rotate them explicitly after checking ownership.
- Only trusted deployment/runtime identities may write private storage. POSIX
  creation modes do not configure Windows ACLs; verify the host's actual permissions.
  Git ignore rules prevent accidental commits, not HTTP or filesystem access.
- Long-lived/concurrent HTTP workers are not an established isolation model.
  Use ordinary isolated PHP requests until the worker acceptance suite is complete.
- File rollback does not reverse schema changes, payments or delivered messages.
  Verify application backups and reconciliation procedures before deployment.

See [runtime contracts](docs/framework/RUNTIME-CONTRACTS.md) and
[release operations](docs/RELEASE-AND-OPERATIONS.md) for configuration, session
locking, cache lifecycle and restore verification. Run dependency audits against
the installed lockfile; framework tests cannot validate application business rules.

## Response expectations

TechAyo LTD will aim to:

- acknowledge a credible report in a reasonable timeframe
- investigate impact and reproduction
- decide whether the issue belongs to core FNLLA, the integrated UI surface or the boundary between them
- coordinate remediation before public disclosure where disclosure is appropriate

Downstream hosting, application code, third-party scripts, analytics wiring, cookie-classification decisions, server hardening, secret management and operational monitoring remain the responsibility of the team operating that separate deployment.

## Disclosure expectations

Please give TechAyo LTD a reasonable opportunity to investigate and remediate before public disclosure.
