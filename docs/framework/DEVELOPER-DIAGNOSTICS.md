# Developer Diagnostics

Complete provides Operations / Debug. Core/plain excludes these tools.

Set `APP_ENV=development` and `APP_DEBUG=true` locally. Sign in as a developer
with `operations.view`; `panel.settings.write` is additionally required to change
settings. Toolbar and Request history are independent switches. Both default
to disabled (`DEBUG_TOOLBAR=false`, `DEBUG_REQUEST_HISTORY=false`). A saved panel
switch overrides its environment default. Refresh cached configuration after
editing environment variables.

History records only authorized developer requests, including JSON and failed
responses. It stores timestamp, normalized HTTP method, status, duration and
peak memory, never paths, IDs, query strings, SQL, bindings, headers, cookies,
request/response bodies or exception messages. The table shows newest first.
PHP peak memory is process-scoped; long-lived concurrent workers are not supported.

`config/debug.php` sets `history.max_entries` (default 200, maximum 1000) and
`history.retention_seconds` (default 3600, maximum 86400). Entries expire on the
next history read/write, not via a background timer. Disable history to erase
entries or select Clear history and Save. The save/redirect requests themselves
may appear when recording remains enabled. Clear before production deployment.

Private state is in `storage/framework/developer/request-history.json`, outside
the public web root and excluded from source exports. Corrupt/unwritable optional
telemetry must not interrupt application requests. Clear-history and settings
mutations are CSRF protected. Guests, staging and production are not recorded.
No shared-browser session, payload viewer, production APM or remote telemetry
service is included.
