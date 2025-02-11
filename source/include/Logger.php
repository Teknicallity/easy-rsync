<?php
namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/LogHandler.php";

use unraid\plugins\EasyRsync\LogHandler;

enum LogLevel: int {
    case DEBUG = 0;
    case INFO = 1;
    case WARNING = 2;
    case ERROR = 3;
}

class Logger {
    private $logLevel;

    public function __construct(LogLevel $logLevel = LogLevel::INFO) {
        $this->logLevel = $logLevel;
    }

    public function logDebug(string $message): void {
        LogHandler::writeToPluginLog("[Debug] " . $message);
    }

    public function logInfo(string $message): void {
        if ($this->logLevel >= LogLevel::INFO) {
            LogHandler::writeToPluginLog("[Info] " . $message);
        }
    }

    public function logWarning(string $message): void {
        if ($this->logLevel >= LogLevel::WARNING) {
            LogHandler::writeToPluginLog("[Warning] " . $message);
        }
    }

    public function logError(string $message): void {
        if ($this->logLevel >= LogLevel::ERROR) {
            LogHandler::writeToPluginLog("[Error] " . $message);
        }
    }

}