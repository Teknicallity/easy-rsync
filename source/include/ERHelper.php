<?php

namespace unraid\plugins\EasyRsync;

require_once __DIR__ . "/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;

class ERHelper {

    public static function isBackupRunning(): bool {
        $runningPath = ERSettings::getStateRsyncRunningFilePath();
        $pid = file_exists($runningPath) ? file_get_contents($runningPath) : false;

        if ($pid === false) {
            return false;
        }

        $pid = preg_replace("/\D/", '', $pid);

        if (file_exists('/proc/' . $pid)) {
            return true;
        } else {
            unlink($runningPath);
            return false;
        }
    }

    public static function isArrayOnline(): bool {
        $emhttpVars = parse_ini_file(ERSettings::$emhttpVars);
        if ($emhttpVars && $emhttpVars['fsState'] == 'Started') {
            return true;
        }
        return false;
    }

    public static function isAbortRequested(): bool {
        return file_exists(ERSettings::getStateRsyncAbortedFilePath());
    }

    /**
     * Immediately stops the rsync process recorded in the pid file (Force Stop).
     * Sends SIGTERM (lets rsync clean up its in-progress temp file), then SIGKILL
     * if it hasn't exited after a short grace period. Integer signals avoid a
     * dependency on the pcntl constants; posix_kill is provided by the posix ext.
     *
     * @return bool true if a live rsync process was signalled, false otherwise.
     */
    public static function killRunningRsync(): bool {
        $pidFile = ERSettings::getStateRsyncPidFilePath();
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));
        if ($pid <= 1 || !file_exists("/proc/$pid")) {
            @unlink($pidFile); // stale
            return false;
        }

        posix_kill($pid, 15); // SIGTERM

        // Wait up to ~2s for a clean exit, then force it.
        for ($i = 0; $i < 20 && file_exists("/proc/$pid"); $i++) {
            usleep(100_000);
        }
        if (file_exists("/proc/$pid")) {
            posix_kill($pid, 9); // SIGKILL
        }

        return true;
    }
}