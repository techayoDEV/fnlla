# FNLLA Documentation Map

Start with [Creating a project](./STARTING-A-NEW-PROJECT.md), then
[Building applications](./BUILDING-WITH-FNLLA.md). Full is the normal integrated
FNLLA experience; plain is an advanced core-only export of the same framework.

## Project Development

- [Project creation and first-run setup](./STARTING-A-NEW-PROJECT.md)
- [Routes, controllers, forms and persistence](./BUILDING-WITH-FNLLA.md)
- [Environment configuration](./ENVIRONMENT.md)
- [Public API and compatibility](./PUBLIC-API.md)
- [CLI and runtime contracts](framework/RUNTIME-CONTRACTS.md)
- [Project scripts](./PROJECT-SCRIPTS-REFERENCE.md)
- [Business application reference](./BUSINESS-APP-REFERENCE.md)

## Operations And Security

- [Developer Panel](./DEVELOPER-PANEL.md)
- [Developer account recovery](framework/DEVELOPER-RECOVERY.md)
- [Developer diagnostics](framework/DEVELOPER-DIAGNOSTICS.md)
- [Production checklist](./PRODUCTION-CHECKLIST.md)
- [Backup and recovery](./RECOVERY.md)
- [Migration and compatibility](./MIGRATION.md)
- [Release operations](./RELEASE-AND-OPERATIONS.md)
- [Performance measurement](./PERFORMANCE.md)
- [Local AI review tooling and optional adapters](./AI-CONTEXT.md)

## Framework Maintenance

- [Architecture and development](./ARCHITECTURE-ROADMAP.md)
- [Current acceptance status](./MODERNIZATION-STATUS.md)
- [Technical debt](./TECH-DEBT-AND-FUTURE-PROOFING.md)
- [Release acceptance checklist](./RELEASE-AND-OPERATIONS.md#release-acceptance-checklist)

The JSON ledger is the sole live acceptance register. Architecture explains
decisions; guides explain procedures. Do not create parallel stage reports or
mix application incident records with reusable framework documentation.

## Documentation Policy

Use neutral application names and reserved example domains. Never commit real
credentials, private endpoints, customer records, local usernames, workstation
paths, backup locations or access links. Keep public copyright attribution,
official repository/package identifiers and actual API/configuration names accurate.

Markdown is maintained source. Rebuild registered HTML and validate hygiene:

```console
php scripts/build-docs.php
php scripts/build-docs.php --check
php scripts/check-docs.php
```

Examples must match shipped commands and distinguish full-only operations.
Keep compatibility in the migration guide and release history in CHANGELOG,
not a growing collection of one-off upgrade files.

Website: [fnlla.com](https://fnlla.com).
Source: [techayoDEV/fnlla](https://github.com/techayoDEV/fnlla).
Support: support@fnlla.com.
Copyright and license: [LICENSE.md](../LICENSE.md).
