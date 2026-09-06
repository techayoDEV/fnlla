# FNLLA Backup And Recovery

## Scope

Code rollback and business recovery are separate. The framework updater restores
managed files and its lock, not databases, uploads, emails, payments or remote
effects. Recovery policy and private evidence belong to the application operator.

Define acceptable data loss (RPO), restore time (RTO), retention, encryption,
offsite retrieval, access ownership and reconciliation before production.
Framework test timings are not service guarantees.

## Isolated File Exercise

The maintainer tool snapshots files and restores them into a new directory without
booting application code. Stop traffic and all writers. Create a private backup
parent outside the repository/web root, with owner-only permissions or restricted
ACLs. Supply paths from a private environment:

```powershell
php scripts/recovery-drill.php --source="$env:RECOVERY_SOURCE" --output="$env:RECOVERY_OUTPUT" --quiesced --rpo-seconds=86400 --rto-seconds=3600
```

Policy values above are illustrative, not approved production defaults. The output
must be new and outside the source. The tool verifies hashes, rejects source
changes and does not overwrite existing destinations. Snapshots can include secrets:
never publish them, their reports or locations in Git or public artifact stores.

Dependencies, logs, caches, sessions, queues and updater journals are excluded.
Reinstall pinned dependencies before booting a restored application. This is not
a database backup utility, scheduler or production recovery orchestrator.

## Database And External Effects

1. Freeze outbound workers/integrations and record the recovery boundary.
2. Retrieve a verified database backup and matching upload snapshot.
3. Restore into an isolated database with least-privilege credentials.
4. Compare structure, row counts, critical invariants and application compatibility.
5. Reconcile completed/pending effects with provider records and idempotency keys.
   Never blindly replay queues after restoring a database.
6. Verify secret recovery, credentials, sessions, login, permissions, forms,
   uploads, health checks and application read/write workflows.
7. Resume deliberately and record restore time and observed data loss.

MySQL DDL and external effects may not be transactional. Preserve failed recovery
evidence; do not conceal errors by replacing damaged data.

## Evidence

Keep backup timestamps, targets, toolchain, hashes, integrity results, measured
time and reconciliation decisions in restricted application records. Redact
identifiers before sharing summaries. Synthetic fixtures validate a mechanism,
not offsite availability, backup freshness or a real application's RPO/RTO.
See [Production checklist](PRODUCTION-CHECKLIST.md).
