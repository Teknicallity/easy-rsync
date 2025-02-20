<?php
namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/ERSettings.php";
require_once "/usr/local/emhttp/plugins/dynamix/include/Wrappers.php";

use unraid\plugins\EasyRsync\ERSettings;

class BackupHelper {
    private static ?array $config = null; // Initialize as null

    protected static function loadConfig(): void {
        if (self::$config === null) {
            self::$config = ERSettings::getUserConfig();
        }
    }

    //TODO: advanced mode override. direct rsync options injector
    /*TODO
     * regular:
     *  --perms
     *  --owner
     *  --group
     *  --bwlimit=KBps
     * advanced:
     *  --acls
     *  --xattrs
     * other:
     *  --include
     *  --exclude
     *
     */

    public static function buildRsyncOptions($doDryRun = false): string {
        // Ensure the configuration is loaded
        self::loadConfig();

        $options = "";
        
        if (self::$config['rsyncRecursive'] == "true") {
            $options .= ' --recursive';
        }
        if (self::$config['rsyncTimes'] == "true") {
            $options .= ' --times';
        }
        if (self::$config['rsyncVerbose'] == "true") {
            $options .= ' --verbose';
        }
        if (self::$config['rsyncHumanReadable'] == "true") {
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
        if (self::$config['rsyncCompress'] == "true") {
            $options .= ' --compress';
        }
        if ($doDryRun) {
            $options .= ' --dry-run';
        }
        return $options;
    }
}