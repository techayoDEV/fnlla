# FNLLA Release Notes Template

Use plain ASCII in GitHub release notes so file paths and bullets stay stable across shells, terminals and browsers.

Template:

```md
FNLLA <version>
Status: draft until the exact-commit gates, artifact checks and publication approval are complete.

Highlights
- Stable HTTP application foundation with routing, middleware, request and response abstractions, dependency injection and controllers
- MySQL-first database layer with PDO access, query builder, migrations, rollback, seeders and factories
- Sessions, cookies, CSRF protection, authentication, authorization and structured exception and logging flow
- FNLLA Runtime runtime contract enforcement with publish -> sync flow under public/vendor/fnlla-runtime/

Operational notes
- README.md, VERSION, LICENSE.md and docs/framework policy documents are aligned for the release line
- release metadata, documentation hygiene and published runtime export have been validated for the release line
- Follow-up cleanup and hardening work is tracked in GitHub after publication when needed
- Supported scope: normal isolated PHP requests; Full starter and explicit Plain preset
- Package mode is a bundled-path preview, not a public registry installation promise
- Long-lived HTTP worker isolation and comparative framework benchmarks are outside this release
- Checksums and CI evidence do not constitute a security certification
```

Before publishing:

- replace `<version>` with the actual version tag
- download and verify the draft attachments before publication; retain exact commit and CI links
- confirm private security reporting and notification delivery
- do not turn unfinished architecture tasks into completed release claims
- keep runtime paths exactly as shown above
- avoid smart quotes, special bullets and non-ASCII separators
