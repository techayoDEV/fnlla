# FNLLA Integrated UI Surface Support Policy

## Support boundary

The integrated FNLLA UI surface is released publicly under the MIT License as part of FNLLA, so anyone may use it, fork it and build self-service projects on top of it.

The official FNLLA framework website is `https://fnlla.com`. Framework support
contact metadata uses `support@fnlla.com`; that mailbox is not a guaranteed SLA
or implementation contract.

That permission does not create any obligation for TechAyo LTD to provide support, maintenance, implementation help, security review, custom development or release work for third-party projects.

## What TechAyo does and does not promise

TechAyo LTD may continue to maintain, extend, harden or publish FNLLA and its integrated UI surface when TechAyo decides it is useful or necessary.

TechAyo LTD does not promise:

- support for third-party self-managed projects
- support for forks, downstream redistributions or modified copies
- a fixed maintenance cadence
- a fixed release cadence
- SLA, uptime, compatibility or upgrade guarantees
- acceptance of external feature requests, pull requests or roadmap proposals

## Deployment and security responsibility

If a third party deploys, extends, modifies or operates a project built on FNLLA, that third party is responsible for the resulting application, hosting, browser integrations, cookie usage, security controls, secret handling, monitoring, backups, patching and incident response.

That remains true even when the deployment uses the integrated FNLLA UI surface exactly as published.

TechAyo LTD does not operate, audit, monitor or secure third-party deployments by default and does not accept responsibility for intrusions, data loss, compliance failures, outages or downstream damages affecting those separate deployments.

## Official support scope

Support may be provided only when TechAyo LTD separately agrees to provide it, for example through direct delivery work, a private agreement or an explicit maintenance arrangement.

Without such an agreement, the public repository, issues and documentation should be treated as best-effort resources rather than a support contract.

## Recommended public routing

- Framework website and product reference: `https://fnlla.com`
- Bug or regression reports: GitHub Issues in `techayoDEV/fnlla`
- Framework support mailbox: `support@fnlla.com`
- Security reports: `SECURITY.md`
- Repository conduct concerns: `CODE_OF_CONDUCT.md`
- Business, partnership or commercial implementation requests: `https://techayo.co.uk`

## Runtime-specific report checklist

When reporting an integrated UI surface issue, include:

- FNLLA version and runtime `VERSION`
- affected asset path, selector, component or page state
- browser and viewport where the issue was observed
- whether `php scripts/validate-fnlla-runtime.php` passes
- a minimal downstream view snippet only when it is necessary to reproduce the
  issue

## Release cadence

FNLLA and its integrated UI surface are updated when TechAyo LTD decides that an update is appropriate.

Releases are not guaranteed to follow a regular public schedule.
