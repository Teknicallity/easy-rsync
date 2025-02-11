<?php
namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;

class ERHelper {
    
    public static function isBackupRunning() {
        $runningPath = ERSettings::getStateRsyncRunningFilePath();
        $pid = file_exists($runningPath) ? file_get_contents($runningPath) : false;

        if ($pid === false) {
            return false;
        }

        $pid = preg_replace("/\D/", '', $pid);

        if (file_exists('/proc/' . $pid)){
            return true;
        } else {
            unlink($runningPath);
            return false;
        }
    }

    public static function isArrayOnline() {
        $emhttpVars = parse_ini_file(ERSettings::$emhttpVars);
        if ($emhttpVars && $emhttpVars['fsState'] == 'Started') {
            return true;
        }
        return false;
    }

    public static function isAbortRequested() {
        return file_exists(ERSettings::getStateRsyncAbortedFilePath());
    }
}