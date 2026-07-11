# End-to-end tests

Drives a **real Unraid server** from the dev machine over ssh + https and
exercises the *installed* plugin (`easy.rsync.beta` by default) through the
genuine emhttp stack: nginx, session auth, CSRF, php-fpm, `update_cron`, real
`rsync`, and the Unraid notification system.

```
composer test:e2e
```

## Setup

1. Key-based ssh for root must work non-interactively:
   `ssh root@<server> true` with no password prompt.
2. `cp tests/e2e/.env.example tests/e2e/.env` and set `E2E_HOST`.
3. The beta plugin must be installed and the array started.

No web password is needed: the suite authenticates by injecting a session file
over ssh (`/var/lib/php/sess_*`, removed afterwards) and reads the CSRF token
from `/var/local/emhttp/var.ini`.

## What it touches on the server

- **Scratch data** under `/tmp/easy-rsync-e2e/` (created and removed).
- **The beta plugin's config** (`/boot/config/plugins/easy.rsync.beta/`):
  snapshotted before and restored after each mutating test class
  (`ConfigGuard`), including re-running `update_cron`. If a run dies hard,
  the next run restores the on-server snapshot before doing anything else.
- **The plugin's state/logs** under `/tmp/easy.rsync.beta/` (cleared per test).
- The stable `easy.rsync` plugin is never touched.

The suite refuses to start if a backup is currently running on the target.

## Side effect: notifications

Backup runs send real Unraid notifications ("Sync started", stop requests) -
these are **not** gated by `notificationMode`, so configured notification
agents (email/Discord/...) may fire during an e2e run. The dedicated
notification test is therefore opt-in (`E2E_ALLOW_NOTIFICATIONS=1`); it cleans
up the notification files it causes.

## Optional coverage

- `E2E_REMOTE_DEST=user@host:/path` - a destination the *Unraid server* can
  reach over key-based ssh. Enables the rsync-over-ssh and ssh connection-test
  success paths.

## Known-version failures

`AbortE2ETest` fails against builds up to `2026.06.20.b1`: the deployed backup
script fatals on `RsyncFailureException` (missing `require`) whenever a sync
fails or is force-stopped, so the run never logs its "Sync Aborted" summary.
Fixed in the source tree after that release; the test goes green once a fixed
build is deployed.

## Future: .plg lifecycle tests

Install/upgrade/remove of the packaged plugin (`.txz` + `.plg`) is not covered
yet. The building blocks are here: `RemoteShell` for running
`plugin install`/`removepkg`, `ConfigGuard` for protecting config across
reinstalls, and `pkg_build.sh -u` for deploying a test build. A future
lifecycle suite should gate on an explicit env flag (e.g.
`E2E_ALLOW_PLG_LIFECYCLE=1`) since it swaps the installed plugin.
