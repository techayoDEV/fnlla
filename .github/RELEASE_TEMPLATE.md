# FNLLA Release Notes Template

Use plain ASCII in GitHub release notes so file paths and bullets stay stable across shells, terminals and browsers.

Template:

```md
FNLLA <version> is the current stable release of the maintained FNLLA framework.

Highlights
- Stable HTTP application foundation with routing, middleware, request and response abstractions, dependency injection and controllers
- MySQL-first database layer with PDO access, query builder, migrations, rollback, seeders and factories
- Sessions, cookies, CSRF protection, authentication, authorization and structured exception and logging flow
- FNLLA Runtime runtime contract enforcement with publish -> sync flow under public/vendor/fnlla-runtime/

Operational notes
- README.md, VERSION, LICENSE.md, SUPPORT.md and TRADEMARKS.md are aligned for the release line
- release metadata, docs sync and published runtime export have been validated for the release line
- Follow-up cleanup and hardening work is tracked in GitHub after publication when needed
```

Before publishing:

- replace `<version>` with the actual version tag
- keep runtime paths exactly as shown above
- avoid smart quotes, special bullets and non-ASCII separators
