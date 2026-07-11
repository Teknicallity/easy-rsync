<?php

/**
 * Snapshots the plugin's persisted config on the server before a test class
 * mutates it, and restores it afterwards (running update_cron so the crontab
 * matches again).
 *
 * Safety properties:
 *  - The snapshot lives on /boot (persistent), not /tmp, so it survives a
 *    server reboot mid-run.
 *  - It is taken in a single remote command that writes a .complete marker
 *    last; restore() refuses to act on a snapshot without the marker, so a
 *    partial snapshot can never cause config files to be deleted.
 *  - If a completed snapshot already exists when snapshot() is called, a
 *    previous run died mid-test: the real config is restored from it first.
 *    A partial (marker-less) snapshot is discarded instead - mutations only
 *    ever start after a completed snapshot, so the live config is pristine.
 */
class ConfigGuard {
    private array $files;
    private string $configDir;
    private string $snapshotDir;
    private string $marker;
    private bool $active = false;

    public function __construct(private RemoteShell $ssh, string $plugin) {
        $this->configDir = "/boot/config/plugins/$plugin";
        $this->snapshotDir = "/boot/config/plugins/$plugin.e2e-snapshot";
        $this->marker = "$this->snapshotDir/.complete";
        $this->files = ["$plugin.cfg", 'backup_paths.json', "$plugin.cron"];
    }

    public function snapshot(): void {
        if ($this->ssh->fileExists($this->snapshotDir)) {
            if ($this->ssh->fileExists($this->marker)) {
                // Leftover from a crashed run: put the real config back first.
                $this->active = true;
                $this->restore();
            } else {
                $this->ssh->mustRun('rm -rf ' . escapeshellarg($this->snapshotDir));
            }
        }

        $script = 'mkdir -p ' . escapeshellarg($this->snapshotDir);
        foreach ($this->files as $file) {
            $src = escapeshellarg("$this->configDir/$file");
            $dst = escapeshellarg("$this->snapshotDir/$file");
            $script .= " && { [ ! -e $src ] || cp -p $src $dst; }";
        }
        $script .= ' && touch ' . escapeshellarg($this->marker);
        $this->ssh->mustRun($script);
        $this->active = true;
    }

    /** Restore the snapshot, delete it, and re-sync the crontab via update_cron. */
    public function restore(): void {
        if (!$this->active) {
            return;
        }
        if (!$this->ssh->fileExists($this->marker)) {
            throw new RuntimeException(
                "config snapshot at $this->snapshotDir is incomplete; refusing to restore from it"
            );
        }
        foreach ($this->files as $file) {
            $snap = escapeshellarg("$this->snapshotDir/$file");
            $target = escapeshellarg("$this->configDir/$file");
            $this->ssh->mustRun("if [ -e $snap ]; then cp -p $snap $target; else rm -f $target; fi");
        }
        $this->ssh->mustRun('update_cron');
        $this->ssh->mustRun('rm -rf ' . escapeshellarg($this->snapshotDir));
        $this->active = false;
    }
}
