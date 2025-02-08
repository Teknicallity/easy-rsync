<?php

namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/ERSettings.php";
use unraid\plugins\EasyRsync\ERSettings;
use Exception;


class LogHandler {
    public static function getBackupStatus() {
        // Logic to get backup status
        return "Backup Status 1";
    }

    public static function getPluginLog() {
        $logFile = ERSettings::getLogFilePath();
        return self::getLogContents($logFile);
    }

    public static function getRsyncLog() {
        $logFile = ERSettings::getRsyncLogFilePath();
        return self::getLogContents($logFile);
    }

    private static function getLogContents(string $logFilePath): string {
        if (!file_exists($logFilePath)) {
            throw new Exception("The log file at path {$logFilePath} does not exist.");
        }
        
        return nl2br(file_get_contents($logFilePath));
    }

    public static function writeToLog(string $message) {
        file_put_contents(ERSettings::getLogFilePath(), $message . PHP_EOL, FILE_APPEND);
    }
}
