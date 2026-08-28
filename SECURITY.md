# FNLLA Security Policy

FNLLA is maintained as a public MIT-licensed framework by TechAyo LTD (techayo.co.uk).

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

## Response expectations

TechAyo LTD will aim to:

- acknowledge a credible report in a reasonable timeframe
- investigate impact and reproduction
- decide whether the issue belongs to core FNLLA, the integrated UI surface or the boundary between them
- coordinate remediation before public disclosure where disclosure is appropriate

Downstream hosting, application code, third-party scripts, analytics wiring, cookie-classification decisions, server hardening, secret management and operational monitoring remain the responsibility of the team operating that separate deployment.

## Disclosure expectations

Please give TechAyo LTD a reasonable opportunity to investigate and remediate before public disclosure.
