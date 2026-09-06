# FNLLA Technical Debt and Future Proofing

This guide defines how framework debt is recorded and verified. It is not a
second architecture backlog or a historical implementation report. Use the
[architecture guide](ARCHITECTURE-ROADMAP.md) for design boundaries, the
[changelog](../CHANGELOG.md) for released changes and the
[modernization status](MODERNIZATION-STATUS.md) for outstanding acceptance.

## One Acceptance Ledger

Framework modernization criteria live in
[resources/modernization-tasks.json](../resources/modernization-tasks.json).
Keep status, evidence and remaining work there; do not create per-session reports
or copy its task list into another guide. Application-specific issues belong in
the application's private issue tracker, not FNLLA source documentation.

An empty source-marker scan does not mean architecture work is complete.
Validate the ledger with:

```bash
php scripts/check-modernization.php
php scripts/check-modernization.php --require-complete
```

The second command must pass before claiming all modernization criteria are
finished. A local passing test does not establish remote integration, production
recovery, public package installation or performance parity with another framework.

## Defect Triage

Confirm that the issue reproduces in maintained source or a clean project export,
and distinguish framework defects from application behavior. Fix a small,
well-understood defect with a regression test. For work that must be deferred,
record its owner, area, affected contract, reproduction, risk, acceptance criteria
and evidence in the appropriate issue tracker or the existing architecture ledger.

Use the [security policy](../SECURITY.md) for undisclosed vulnerabilities. Do not
publish credentials, account identifiers, customer data, workstation paths or
private recovery evidence. Public reproductions should use synthetic fixtures.

## Documentation Maintenance

Update the affected guide when behavior changes. Keep one canonical procedure
and link to it from other guides; retain compatibility instructions while they
remain supported. Keep license attribution and public API names accurate.
Generate HTML from Markdown rather than maintaining two independent versions.

Before release, follow [release operations](RELEASE-AND-OPERATIONS.md), including:

```bash
php scripts/build-docs.php
php scripts/build-docs.php --check
php scripts/check-docs.php
php fnlla tech-debt:update
php fnlla tech-debt:update --check
```

The debt command writes `storage/framework/cache/technical-debt-report.json` for
local evidence and refreshes the bounded snapshot below. Its schema is
`fnlla.technical_debt_report.v1`. A freshness check validates that snapshot,
not the truth of every acceptance claim. Keep generated operational evidence
outside source distributions; the snapshot intentionally omits local residue paths.

<!-- FNLLA_TECH_DEBT_REPORT:BEGIN -->
## Self-Checking Debt Snapshot

Refresh this section with:

```bash
php fnlla tech-debt:update
php fnlla tech-debt:update --check
```

The command rebuilds a machine-readable report, rewrites this bounded
snapshot and returns a non-zero exit code when `--check` sees stale
documentation.

| Check | Status | Detail |
| --- | --- | --- |
| `explicit-debt-markers` | `pass` | No explicit debt markers found in release-facing source files. |
| `runtime-residue` | `runtime` | Runtime data must be excluded from source artifacts, not deleted from a working application. |
| `generated-docs-sync` | `pass` | Generated HTML docs match Markdown sources. |
| `release-documentation` | `pass` | Required release, AI, operations and technical-debt documents are present. |
| `ai-product-runtime` | `pass` | Local runtime AI and the reserved Fionn bridge contract are present. |
| `technical-debt-public-contract` | `pass` | Technical-debt command and schema are present in the public API lock. |
| `modernization-ledger` | `warn` | 8 modernization criteria remain unfinished. See docs/MODERNIZATION-STATUS.md. |

Generated actions:

- Run php scripts/check-modernization.php --require-complete and close outstanding acceptance criteria before claiming modernization is complete.

Snapshot summary: `5` pass, `1` warn, `0` fail, `1` info.
<!-- FNLLA_TECH_DEBT_REPORT:END -->
