# Immutable release deployment

Optional local package; no public registry publication. Requires PHP 8.3 and a
local filesystem with advisory locks and atomic same-directory rename. Network
filesystems and power-loss durability are not certified. This rolls back code,
NOT databases, queues, uploads, environment values or external effects.

```text
deployment/current.json
deployment/deploy.lock
deployment/releases/<unique-id>/
deployment/shared/.env
deployment/shared/storage/
deployment/shared/release-cache/<unique-id>/
deployment/shared/public/uploads/
webroot/index.php
```

Run from a build/operator workspace, not an active release:

```powershell
php packages/fnlla-deploy/bin/fnlla-deploy.php stage ../deployment ../build/artifact release-001
php packages/fnlla-deploy/bin/fnlla-deploy.php activate ../deployment release-001 -
php packages/fnlla-deploy/bin/fnlla-deploy.php stage ../deployment ../build/next release-002
php packages/fnlla-deploy/bin/fnlla-deploy.php activate ../deployment release-002 release-001
php packages/fnlla-deploy/bin/fnlla-deploy.php rollback ../deployment release-002
php packages/fnlla-deploy/bin/fnlla-deploy.php status ../deployment
```

Provision shared/.env and migrate storage/uploads under an operator-controlled
maintenance window. Staging never copies .env, storage, uploads or .git. Install
production Composer dependencies in the artifact before staging. Symbolic links
are rejected. Keep deployment outside webroot; never modify or reuse release IDs.
Failed staging removes its temporary tree and does not reserve the release ID.

Artifacts must supply .fnlla/deployment-check.php: trusted application code that
receives $release and returns true after readiness checks. Do not write business
data during validation. False, exceptions and interrupted validation leave the old
pointer unchanged. There is no sandbox or automatic database restore. File hashes
are verified before and after validation. The expected-current argument prevents
overwriting a different active release without inspecting its state.

The separate webroot/index.php loads the operator-installed package:

```php
require '/operator/vendor/autoload.php';
\Fnlla\Deploy\Gateway::run('/deploy/site');
```

Route ALL application and asset requests through this gateway. Locally:
php -S 127.0.0.1:8080 -t webroot webroot/index.php. Configure nginx/Apache with
equivalent front-controller rewriting and webroot as document root. Never expose
deployment/releases or shared directly. URLs are root-mounted, not subdirectory-mounted.

Requests select one immutable release. Generated asset URLs contain its ID, so old
HTML retains its CSS/JS after activation. Only allowlisted static extensions are
served. PHP, dotfiles, traversal and symlink escapes are blocked. Shared uploads
are not immutable; potentially active formats are attachments with a restrictive CSP.
FNLLA shares environment/storage but isolates bootstrap caches per release.
In-place framework updates refuse sealed releases.

Use normal PHP request isolation, not concurrent long-lived HTTP workers. Retain
old releases until requests, queue workers and cached HTML no longer reference
them. Cleanup is manual. Use backwards-compatible expand/contract database
migrations. Business-data recovery requires a verified backup and an explicit
maintenance decision; no generic code switch can reverse payments or sent email.
