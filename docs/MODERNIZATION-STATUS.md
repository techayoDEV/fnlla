# FNLLA Modernization Status

## Release Boundary

Target: stable **2.2.0**. Source and runtime metadata identify the **2.2.0 candidate**;
the last public release remains **2.1.3**. Do not retag or reuse a published version.

The [JSON ledger](../resources/modernization-tasks.json) is the sole live register.
Current totals: **19 done, 4 partial, 2 open, 1 blocked**. Document consolidation
does not change acceptance or erase unfinished work.

## Remaining Acceptance

| Status | Area | Required evidence |
| --- | --- | --- |
| Partial | Complete packages | Independent modules and versioned assets/config/routes publication and removal; package mode remains opt-in. |
| Partial | Business recovery | Application-specific offsite schedule, secrets, consistent restore and external-effect reconciliation. |
| Partial | HTTP edge matrix | Supported proxy/SAPI topologies and serving OPcache invalidation beyond local transport/cache tests. |
| Partial | Published upgrades | Official 2.1.3-to-candidate merge, rollback boundary, post-install checks and application preservation pass locally and in Linux integration CI. Published 2.2.0 consumer installation remains required. |
| Open | HTTP workers | Sequential/concurrent globals, statics and session isolation; normal PHP requests remain supported. |
| Open | Comparative benchmarks | Pinned equivalent applications, cold/warm latency percentiles and memory. |
| Blocked | Public packages | Immutable artifacts, registry metadata and verified consumer installation. |

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
