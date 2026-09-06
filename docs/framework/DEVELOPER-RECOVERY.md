# Developer Account Recovery

The Complete starter includes a split Developer Panel sign-in screen, email
password recovery and a server-owner CLI fallback. Core / plain includes none
of these panel files. No database tables or additional Composer dependencies
are required for recovery.

## Enable Email Recovery

1. Configure the existing named developer accounts in `DEVELOPER_ACCESS_USERS`.
2. Set `APP_URL` to the project's canonical HTTPS base URL, including any base
   path. Reset links never use the incoming Host header. Loopback HTTP is allowed
   only in `development` and `testing`.
3. Configure a real `MAIL_MAILER` transport: `http`, explicitly enabled `native`,
   or `adapter` with a registered transport (including optional `fnlla-smtp`).
   Configure `MAIL_FROM_ADDRESS` and verify delivery before enabling production
   access. The default `log` driver writes local test messages, not real email;
   production recovery refuses it.
4. Run `php fnlla queue:work 50` regularly, for example every minute through cron
   or Windows Task Scheduler. This command drains a batch and exits; it is not a
   permanent worker. Set its working directory to the project root. The default
   file queue works without Redis; all web/worker processes must share its storage.
5. Keep `.env`, its parent directory, and private `storage` writable by the
   application and worker accounts. Protect their filesystem permissions. Web
   document roots must point only to `public`, never to the project root.

Optional settings (configuration cache must be rebuilt after changing settings):

```dotenv
DEVELOPER_ACCESS_RECOVERY_ENABLED=true
DEVELOPER_ACCESS_RECOVERY_TTL_MINUTES=30
```

The lifetime is bounded to 5-60 minutes. The switch disables both HTTP recovery
and issuing CLI links; it does not remove the panel. With a custom entry path,
`/private-tools`, the forms live at `/private-tools/forgot-password` and
`/private-tools/reset-password`.

The user selects **Forgot password?**, requests a link, opens the email, and
chooses and confirms a new password. New passwords must occupy 12-72 bytes and
cannot contain null bytes or leading/trailing whitespace. Non-ASCII characters
can occupy several bytes. The user then signs in normally, including 2FA.

## Local Development

With `APP_ENV=development`, `MAIL_MAILER=log` and an explicit loopback `APP_URL`,
submit the form and run `php fnlla queue:work 50`. The message is in the configured
mail log (default `storage/mail/YYYYMMDD.log`). That file contains a live bearer
link: never expose it publicly, commit it, or attach it to a support ticket.

## Server-Owner Fallback

When email delivery is unavailable, an authorized operator with shell access
can run:

```sh
php fnlla developer:recovery-link developer@example.com
```

This prints a private, expiring link for an existing account. Open it in the
browser and choose the new password there. Passwords are never CLI arguments or
terminal output. Treat the link as a temporary password: do not use this command
in CI, shared terminal recordings or HTTP command-execution endpoints. This
fallback does not require a queue worker or mail transport.

If 2FA was also lost, password recovery deliberately does not bypass it. Another
authorized lead developer must follow the project's identity-verification and
MFA recovery procedure. Do not delete all accounts or disable authentication as
a password-reset workaround.

## Security And Operations

- Public requests queue the same job for valid known and unknown email addresses;
  account lookup and mail delivery happen outside the HTTP request. Responses do
  not reveal whether an account exists. No bearer token is placed in a queue job.
- Requests are limited atomically to 3 per email and 5 per IP per hour, with
  additional route throttles and CSRF protection. These limits do not lock the
  developer out of ordinary sign-in. Jobs older than an hour are discarded.
- Tokens contain 256 random bits; private token state stores only SHA-256 digests
  and credential fingerprints. A newer link supersedes an older one. Opening a
  link does not consume it, so mail scanners cannot reset a password.
- On opening the link, the token moves to the private browser session and the
  browser redirects to a clean URL. Pages are no-store and no-referrer. Configure
  your web server, reverse proxy and observability tools not to retain query
  strings on the initial reset request; FNLLA cannot sanitize upstream logs.
- Reset changes only the password, preserves account roles and MFA, and revokes
  all existing developer sessions through the existing credential fingerprint.
  Other outstanding recovery links also become invalid after credential changes.
  A separate notification is sent after success where a mail transport is allowed.
- Environment writes use a shared lock and atomic replacement. Recovery compares
  the current on-disk account configuration before writing, rejects duplicates
  and stale changes, and never replaces unrelated environment keys. Developer
  credentials are refreshed even when other configuration is cached.
- Credentials supplied through externally injected environment variables must
  match the writable `.env` value. If they differ, recovery fails closed. For
  immutable secret-store deployments, rotate the authoritative secret through
  your deployment process; this local `.env` workflow is not a secret-store adapter.
- Check private logs for `developer_recovery_queue_failed`,
  `developer_recovery_write_failed` and `developer_recovery_mail_failed`, and
  inspect failed queue jobs for generic delivery errors. Check `APP_URL`, mail
  transport, worker scheduling and storage permissions when no message arrives.
- Use shared private recovery storage across all application instances; local
  per-host token files are not a distributed recovery backend. Short-lived worker
  batches reload configuration; restart any custom long-running workers after
  credential changes. Secure and rotate local mail logs and expired session data.

Security design reference:
[OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html).
