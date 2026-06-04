# Easy Rsync

A web-based UI for configuring and running rsync backups on Unraid. Set up source paths, remote destinations, and a backup schedule from the Settings page -- the plugin handles invoking rsync, logging, and notifications.

## Features

- Multiple sync entries, each with its own sources, destinations, and rsync flags
- Daily / weekly / monthly schedules, or a custom cron expression
- Manual backup and dry-run buttons in the UI
- Abort a running backup from the UI
- Per-job rsync log + plugin status log, viewable in their own tabs
- Notifications via Unraid's notification system (per-job, summary, or off)

## Beta install

Build a beta package and ship it to your Unraid server:

```bash
sudo ./pkg_build.sh -b -u root@<unraid-host> -y -v .b1
```

- `-b` builds a beta (installs alongside any stable version).
- `-u root@<unraid-host>` targets your Unraid box. Requires key-based root SSH.
- `-y` skips confirmation prompts.
- `-v .b1` is the version suffix; the full version becomes `YYYY.MM.DD<suffix>` (e.g. `2026.05.25.b1`). Beta builds must use a `.bN` suffix; a plain letter (e.g. `a`) is the *stable* format and is rejected with `-b`.

### Installing on the Unraid box

After the script finishes, SSH into the Unraid server or use the web UI:

1. **Web UI:** Plugins -> Install Plugin -> enter `/boot/easy.rsync.beta.plg` -> Install.
2. **CLI:** `plugin install /boot/easy.rsync.beta.plg`

The plugin appears under Settings -> Easy Rsync (Beta).

## Releasing

Ready to deploy a stable or beta release? See [RELEASING.md](RELEASING.md).
