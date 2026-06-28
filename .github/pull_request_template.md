## Summary

Describe the change in a few direct sentences and explain why it belongs in FNLLA PHP now.

If this change may be security-sensitive, stop and follow `SECURITY.md` instead of opening a normal PR.

## Scope

- HTTP kernel:
- Routing or middleware:
- Auth or session flow:
- Database or migrations:
- Views or rendering:
- FNLLA UI contract:
- Release surface:

## Validation

- [ ] `php scripts/test.php` was run where behavior changed
- [ ] `php scripts/lint.php` was run
- [ ] `php scripts/validate-fnlla-ui.php` still passes when UI-facing templates or layout changed
- [ ] CLI behavior was checked where command or migration flow changed

## Release Notes

- [ ] No release note needed
- [ ] Follow-up for the next release milestone
- [ ] Requires update to `README.md`, `VERSION`, `LICENSE.md`, `SUPPORT.md` or `TRADEMARKS.md`

## Checklist

- [ ] The change is scoped to the framework rather than one app's private copy
- [ ] New config, helper or command surface is intentional and consistent with existing naming
- [ ] I checked whether this change affects current GitHub issues or release work
- [ ] I reviewed `.github/CONTRIBUTING.md` when the change affects workflow or contribution expectations
