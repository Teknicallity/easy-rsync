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
    private static ?Logger $instance = null;
    private LogLevel $logLevel;

    private function __construct(LogLevel $logLevel = LogLevel::INFO, ?string $loglevelString = null) {
        $this->logLevel = $logLevel;

        if ($loglevelString !== null) {
            $this->logLevel = match (strtolower($loglevelString)) {
                "debug" => LogLevel::DEBUG,
                "error" => LogLevel::ERROR,
                "warning" => LogLevel::WARNING,
                default => LogLevel::INFO,
            };
        }
    }

    public static function getLogger(LogLevel $logLevel = LogLevel::INFO, ?string $loglevelString = null): Logger {
        if (self::$instance === null) {
            self::$instance = new Logger($logLevel, $loglevelString);
        }
        return self::$instance;
    }

    public static function resetInstance(): void {
        self::$instance = null;
    }

    public function debug(string $message): void {
        if ($this->logLevel->value <= LogLevel::DEBUG->value) {
            LogHandler::writeToPluginLog("[Debug] " . $message);
        }
    }

    public function info(string $message): void {
        if ($this->logLevel->value <= LogLevel::INFO->value) {
            LogHandler::writeToPluginLog("[Info] " . $message);
        }
    }

    public function warning(string $message): void {
        if ($this->logLevel->value <= LogLevel::WARNING->value) {
            LogHandler::writeToPluginLog("[Warning] " . $message);
        }
    }

    public function error(string $message): void {
        if ($this->logLevel->value <= LogLevel::ERROR->value) {
            LogHandler::writeToPluginLog("[Error] " . $message);
        }
    }

}