# FNLLA Support Policy

## Support boundary

FNLLA is released publicly under the MIT License, so anyone may use it, fork it and build self-service projects on top of it.

The official framework website is `https://fnlla.com`. Framework support
contact metadata uses `support@fnlla.com`; that mailbox is not a guaranteed SLA
or implementation contract.

That permission does not create any obligation for TechAyo LTD to provide support, maintenance, implementation help, security review, custom development or release work for third-party projects.

## What TechAyo does and does not promise

TechAyo LTD may continue to maintain, extend, harden or publish FNLLA when TechAyo decides it is useful or necessary.

TechAyo LTD does not promise:

- support for third-party self-managed projects
- support for forks, downstream redistributions or modified copies
- a fixed maintenance cadence
- a fixed release cadence
- SLA, uptime, compatibility or upgrade guarantees
- acceptance of external feature requests, pull requests or roadmap proposals

## Deployment and security responsibility

If a third party deploys, extends, modifies or operates a project built on FNLLA, that third party is responsible for the resulting application, hosting, infrastructure, integrations, cookie usage, security controls, secret handling, monitoring, backups, patching and incident response.

That remains true even when the deployment uses FNLLA exactly as published.

TechAyo LTD does not operate, audit, monitor or secure third-party deployments by default and does not accept responsibility for intrusions, data loss, compliance failures, outages or downstream damages affecting those separate deployments.

## Official support scope

Support may be provided only when TechAyo LTD separately agrees to provide it, for example through direct delivery work, a private agreement or an explicit maintenance arrangement.

Without such an agreement, the public repository, issues and documentation should be treated as best-effort resources rather than a support contract.

## Recommended public routing

- Framework website and product reference: `https://fnlla.com`
- Bug or regression reports: GitHub Issues in `techayoDEV/fnlla`
- Framework support mailbox: `support@fnlla.com`
- Security reports: `SECURITY.md`
- Repository conduct concerns: `.github/CODE_OF_CONDUCT.md`
- Business, partnership or commercial implementation requests: `https://techayo.co.uk`

## Self-service resources

Before opening a public issue, check:

- `README.md` for the short project overview and current stable line
- `docs/README.md` for the complete documentation map
- `docs/STARTING-A-NEW-PROJECT.md` for the supported export workflow
- `docs/RELEASE-AND-OPERATIONS.md#production-readiness-checklist` for production deployment responsibility
- `docs/MIGRATION.md` for consolidated upgrade and compatibility guidance
- GitHub Issues for known framework-level defects, if any are currently tracked

When reporting a confirmed bug, include the FNLLA version, PHP version, command
or route affected, reproduction steps and the smallest relevant output from
`php fnlla doctor` or `php fnlla project:acceptance --json`.

## Release cadence

The 2.2.x security maintenance policy is defined in `SECURITY.md`: once published,
the latest patch is the primary fix target, with no automatic LTS or parallel
backport commitment. Applications should budget for tested patch upgrades.
Business-critical deployments need an explicit maintenance owner and, where
required, a separate support agreement; a framework version is not an SLA.

FNLLA is updated when TechAyo LTD decides that an update is appropriate.

Releases are not guaranteed to follow a regular public schedule.
