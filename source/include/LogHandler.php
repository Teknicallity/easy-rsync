<?php
namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/ERSettings.php";
require_once __DIR__ ."/ERHelper.php";

use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\ERHelper;
use Exception;


class LogHandler {
    public static function getBackupStatus(): bool {
        // Logic to get backup status
        return ERHelper::isBackupRunning();
    }

    public static function getPluginLog(): string {
        $logFile = ERSettings::getLogFilePath();
        return self::getLogContents($logFile);
    }

    public static function getRsyncLog(): string {
        $logFile = ERSettings::getRsyncLogFilePath();
        return self::getLogContents($logFile);
    }

    /**
     * Retrieves and formats the contents of a log file.
     * @param string $logFilePath The path to the log file.
     * @return string The formatted content of the log file.
     * @throws Exception If the specified log file does not exist.
     */
    private static function getLogContents(string $logFilePath): string {
        if (!file_exists($logFilePath)) {
            return nl2br("Log file does not exist");
        }
        
        return nl2br(file_get_contents($logFilePath));
    }

    /**
     * Writes a message to the log file. Creates the log file if it doesn't already exist.
     * @param string $message The message to be written to the log.
     * @throws Exception If there is an error writing to or creating the log file.
     */
    public static function writeToPluginLog(string $message): void {
        $logFilePath = ERSettings::getLogFilePath();

        // Open the log file in append mode, creating it if necessary
        $handle = fopen($logFilePath, 'a');
        if ($handle === false) {
            throw new Exception("Failed to open or create the log file at path {$logFilePath}");
        }

        try {
            // Write the message to the log file
            fwrite($handle, $message . PHP_EOL);
        } catch (Exception $e) {
            throw new Exception("Error writing to log file: " . $e->getMessage());
        } finally {
            fclose($handle);  // Ensure the file is closed properly
        }
    }
}
