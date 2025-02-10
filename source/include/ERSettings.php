<?php

namespace unraid\plugins\EasyRsync;

class ERSettings {

    public static $appName = 'easy.rsync';
    public static $configDir = '/boot/config/plugins/easy.rsync';
    public static $pathsFile = 'backup_paths.json';
    public static $cronFile = 'easy-rsync.cron';
    public static $tempFolder = '/tmp/easy.rsync';
    public static $logFile = 'easy-rsync.log';
    public static $rsyncLogFile = 'rsync.log';
    public static $stateRsyncRunningFile = 'running';
    public static $stateRsyncAbortedFile = 'aborted';
    public static $emhttpVars = '/var/local/emhttp/var.ini';

    public static function getConfigFilePath() {
        return self::$configDir . '/' . self::$pathsFile;
    }
    
    public static function getLogFilePath() {
        return self::$tempFolder . '/' . self::$logFile;
    }

    public static function getRsyncLogFilePath() {
        return self::$tempFolder . '/' . self::$rsyncLogFile;
    }

    public static function getStateRsyncRunningFilePath() {
        return self::$tempFolder . '/' . self::$stateRsyncRunningFile;
    }

    public static function getStateRsyncAbortedFilePath() {
        return self::$tempFolder . '/' . self::$stateRsyncAbortedFile;
    }

    private static function savePaths(array $paths) {
        file_put_contents(self::getConfigFilePath(), json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function getPaths() {
        $filePath = self::getConfigFilePath();
        
        if (file_exists($filePath)) {
            $paths = json_decode(file_get_contents($filePath), true);
        }

        $sources = isset($paths['sources']) ? (array) $paths['sources'] : [];
        $destinations = isset($paths['destinations']) ? (array) $paths['destinations'] : [];

        return [
            'sources' => $sources,
            'destinations' => $destinations
        ];
    }

    public static function saveSourcesAndDestinations(array $sources = null, array $destinations = null) {
        $paths = self::getPaths();
        
        $paths['sources'] = $sources ?? $paths['sources'];
        $paths['destinations'] = $destinations ?? $paths['destinations'];

        self::savePaths($paths);
    }
}
