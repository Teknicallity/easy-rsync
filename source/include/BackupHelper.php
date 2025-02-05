<?php

use unraid\plugins\EasyRsync\ERSettings;

class BackupHelper {
    private static $config = parse_plugin_cfg(ERSettings::$appName);

    //TODO: advanced mode override. direct rsync options injector

    public static function buildRsyncOptions($doDryRun = false) {
        $options = "rsync";
        
        if (self::$config['rsyncRecursive']) {
            $options .= ' --recursive';
        }
        if (self::$config['rsyncTimes']) {
            $options .= ' --times';
        }
        if (self::$config['rsyncVerbose']) {
            $options .= ' --verbose';
        }
        if (self::$config['rsyncHumanReadable']) {
            $options .= ' --human-readable';
        }
        if (self::$config['rsyncDelete'] === "after") {
            $options .= ' --delete-after';
        } elseif (self::$config['rsyncDelete'] === "before") {
            $options .= ' --delete-before';
        } elseif (self::$config['rsyncDelete'] === 'during') {
            $options .= ' --delete-during';
        }elseif (self::$config['rsyncDelete'] === 'delay') {
            $options .= ' --delete-delay';
        }
        // if (self::$config['rsyncRemoteShell']) {
        //     $options .= ' -e "' . escapeshellarg(self::$config['rsyncRemoteShell']) . '"';
        // }
        if (self::$config['rsyncCompress']) {
            $options .= ' --compress';
        }
        if ($doDryRun) {
            $options .= ' --dry-run';
        }
        return $options;
    }
}