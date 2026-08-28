# FNLLA Bug Log

This file is the lightweight maintenance log for framework-level defects,
regressions, export mismatches and release-quality follow-up work discovered
while building downstream projects on top of `techayoDEV/fnlla`.

Use it when:
- a downstream project exposes a real framework bug
- a generated project export behaves differently than the maintainer repository
- docs, tests or version contracts drift from actual framework behavior
- a fix should be tracked before it is implemented in `techayoDEV/fnlla`

Entry template:

```md
## YYYY-MM-DD - Short title
- Status: open | fixed | deferred
- Found in: fnlla | downstream project name
- Area: export | routing | auth | docs | tests | runtime | release
- Symptom: concise observable problem
- Root cause: confirmed cause or `unknown`
- Action: fix made, planned change, or reason for deferral
- Evidence: file paths, command output, test name, or repro note
```

Current state:
- No open framework defects are intentionally parked here right now.
- The preferred workflow is still to fix confirmed `techayoDEV/fnlla` issues
  immediately when the change is low-risk and well understood.

## Triage checklist

Before logging a defect here, confirm:

- the issue reproduces on the maintained FNLLA framework or on a clean
  `make:project` export
- the failing behavior is not caused by downstream product code
- `php scripts/test.php`, `php scripts/lint.php` or a focused route/manual
  check provides useful evidence
- the defect belongs to framework source, export behavior, integrated runtime,
  docs, release metadata or public API compatibility

For security issues, use `SECURITY.md` instead of this public log until
disclosure is appropriate.
