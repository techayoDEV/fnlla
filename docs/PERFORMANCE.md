# FNLLA Performance

FNLLA keeps performance work local, reproducible and easy to inspect. The goal
is not to hide runtime behaviour behind a build service. The goal is to make
production readiness explicit before a public release.

## Production Warmup

Run:

```bash
php fnlla optimize:warm
```

The command builds the normal bootstrap caches and adds two production-facing
artefacts:

- `storage/framework/cache/assets.php` is a generated asset manifest used by
  `asset()` so hot requests can avoid repeated `filemtime()` checks.
- `storage/framework/cache/preload.php` is an optional OPcache preload list for
  hosts that support `opcache.preload`.

`optimize:warm` is safe to run during deployment. For source packaging or local
development resets, run:

```bash
php fnlla optimize:clear
```

## Profiling

Run:

```bash
php fnlla perf:profile --iterations=5
php fnlla perf:profile --iterations=5 --json
php fnlla perf:baseline:update --iterations=7
php fnlla perf:compare --iterations=5 --against storage/framework/cache/performance-baseline.json
```

The profiler records:

- CLI timings for `list`, `route:list`, `version:status` and `make:project`
- in-process HTTP probe timings for `/` and `/api/health`
- p50, p95, average, minimum and maximum command times
- source footprint for the main framework directories
- PHP version, environment and peak memory

## HTTP Probe Semantics

The HTTP probes boot the local application in-process. They do not require a web
server and they do not claim to represent public internet latency.

The `/` probe confirms that the public surface responds or redirects into the
expected maintenance/setup flow. The `/api/health` probe confirms that the
machine-facing health route is reachable and returns a deliberate status. A
`503` from `/api/health` can be valid when maintenance mode is actively
restricting API access; it is still useful performance data because the route,
middleware and JSON response path executed correctly.

For real production monitoring, add external probes from your hosting or uptime
platform after deployment.

Use more iterations for release decisions. Use fewer iterations while iterating
locally.

## Baselines And Budgets

Create a local baseline:

```bash
php fnlla perf:profile --iterations=7 --write-baseline
php fnlla perf:baseline:update --iterations=7
```

Then compare future changes:

```bash
php fnlla perf:budget --iterations=7 --max-regression=20
php fnlla perf:budget --iterations=7 --max-regression=20 --max-regression-ms=1000 --json
php fnlla perf:compare --iterations=7 --max-regression=20 --max-regression-ms=1000
```

`--max-regression` is a percentage threshold against saved p95 timings.
`--max-regression-ms` is an absolute tolerance that prevents tiny local timings
from becoming noisy false positives. A comparison fails only when both thresholds
are exceeded. A failed budget exits with code `1`, which makes it suitable for
CI and release gates.

FNLLA 2.1 also ships a baseline policy at
`resources/performance-baselines/2.1-policy.json`. It names the release-decision
targets for command listing, route listing, homepage health, API health and
project export. The built-in profiler measures the CLI/export targets locally;
deployment pipelines should add HTTP probes for `/` and `/api/health` against
the same thresholds.

## Downstream Product Usage

For a commercial application, keep two baselines:

- a framework baseline immediately after `make:project` and `project:claim`;
- an application baseline after the first real product flows exist.

Compare later changes against the application baseline. That separates framework
startup cost from real business code, queries and templates.

## What To Optimize First

The highest-value optimizations are already wired into the framework:

- static route lookup avoids scanning all routes for exact paths
- route and config caches remove repeated bootstrap parsing
- the asset manifest removes per-request asset mtime checks
- lazy session boot avoids unnecessary session work on routes that do not need
  flash, CSRF, auth or session state
- health readiness caching avoids repeating expensive local checks too often

Future large-scale deployments should add adapter-backed infrastructure where
needed: Redis or Memcached cache, external session storage, a distributed queue
store and OpenTelemetry-compatible metrics export.
