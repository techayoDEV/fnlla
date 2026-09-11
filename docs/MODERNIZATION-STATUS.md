# FNLLA Modernization Status

## Release Boundary

This ledger describes the architecture acceptance for edition **2.2.0**.
[GitHub Releases](https://github.com/techayoDEV/fnlla/releases) records published
versions; the ledger is not an availability announcement. Do not retag or reuse a published version.

The [JSON ledger](../resources/modernization-tasks.json) is the sole live register.
Current totals: **21 done, 2 partial, 2 open, 1 blocked**. Document consolidation
does not change acceptance or erase unfinished work. In particular, the 2.2.0
database defaults, updated security policy, tooling fixes and unified brand kit
are implemented improvements, not evidence that the five remaining architecture
criteria have passed.

The v2.2.0 GitHub release and CI evidence close the previous upgrade and HTTP
edge criteria: the maintained workflow consumes the accepted source archive,
exercises the Full, Plain and package previews through Nginx TLS ingress, an
origin proxy, PHP-FPM 8.3 and OPcache, and the release assets are public. This
does not close public Composer registry installation, application-owned recovery,
long-lived worker isolation, package uninstallability or comparative framework
benchmarking. Notification delivery and website hosting are separate operational
checks, not inferred from repository configuration.

## Remaining Acceptance

| Status | Area | Required evidence |
| --- | --- | --- |
| Partial | Complete packages | Independent modules and versioned assets/config/routes publication and removal; package mode remains opt-in. |
| Partial | Business recovery | Application-specific offsite schedule, secrets, consistent restore and external-effect reconciliation. |
| Open | HTTP workers | Sequential/concurrent globals, statics and session isolation; normal PHP requests remain supported. |
| Open | Comparative benchmarks | Pinned equivalent applications, cold/warm latency percentiles and memory. |
| Blocked | Public packages | Composer registry metadata, registry installation and verified consumer installation. |

Implemented criteria and source/test evidence remain in the ledger. See
[Architecture](ARCHITECTURE-ROADMAP.md), [Runtime contracts](framework/RUNTIME-CONTRACTS.md)
and [release operations](RELEASE-AND-OPERATIONS.md#backup-and-recovery) for
reusable procedures and constraints.

Redis sessions now implement strict ID validation, bounded token-owned locking,
atomic stale-owner rejection and lazy TTL refresh. Real Redis integration covers
parallel process writes, termination recovery and HTTP authentication. Local
service acceptance uses MySQL 8.0 and a Redis 7.4 Windows port with phpredis 6.3;
it is not evidence for the remote Linux matrix.

The complete remote Core Quality matrix subsequently passed for commit
`6c8127b`: Windows/Linux PHP 8.3, 8.4 and 8.5, plus Linux MySQL/Redis integrations
on all three PHP versions. [Recorded run](https://github.com/techayoDEV/fnlla/actions/runs/34041394523).
This closes the remote matrix criterion, not the publication gate. The subsequent
Windows private-state rename fix passed all workflows for commit `b101a42`:
[Core Quality](https://github.com/techayoDEV/fnlla/actions/runs/34041649549),
[Hardening](https://github.com/techayoDEV/fnlla/actions/runs/34041649518),
[Release Gate](https://github.com/techayoDEV/fnlla/actions/runs/34041649523).
The later `5c1edb2` Core Quality run passed the source archive, production HTTP,
brand UI, PHP matrix and services jobs:
[Core Quality](https://github.com/techayoDEV/fnlla/actions/runs/34153884273).
Every later candidate must pass all workflows before release approval.

## Evidence Policy

Unit, integration, export, UI and dependency-audit results are distinct evidence.
A previous local run does not certify a new candidate or remote matrix. Retain the
exact commit, toolchain, dependency lock, command, outcome and artifact hashes in
release records. Do not publish credentials, customer domains, workstation paths
or private evidence locations in framework documentation.

Synthetic restores do not prove production RPO/RTO. Local packages do not prove
registry installation. Module toggles are not uninstallers. Artifacts prepared
with --skip-tests do not establish release readiness.

```console
php scripts/check-modernization.php
php scripts/check-modernization.php --require-complete
```

The first command validates accounting/evidence paths; the second fails while any
task is unfinished. Neither substitutes for executing acceptance tests.
