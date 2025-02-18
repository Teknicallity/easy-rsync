<?php

namespace unraid\plugins\EasyRsync;

require_once "/usr/local/emhttp/plugins/dynamix/include/Wrappers.php";

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

    public static function getUserConfig() {
        return parse_plugin_cfg(self::$appName);
    }

    public static function saveUserConfig(array $userConfig) {
        $ini_contents = self::arrayToIni($userConfig);
        $configFilePath = self::$configDir .'/easy.rsync.cfg';
        return file_put_contents($configFilePath, $ini_contents);
    }

    private static function arrayToIni(array $array): string {
        $iniContents = '';
        
        foreach ($array as $key => $value) {
            // echo "key: '$key' ". gettype($value) ." value: $value\n";
            
            $iniContents .= match (gettype($value)) {
                'boolean' => "$key=\"" . ($value ? 'true' : 'false') . "\"\n",
                'integer' => "$key=$value\n",
                'string'  => "$key=\"$value\"\n",
                'array'   => "\n[$key]\n" . arrayToIni($value),
                default   => "",
            };
        }
        
        return $iniContents;
    }

    public static function getPathsJsonFilePath() {
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
        return file_put_contents(self::getPathsJsonFilePath(), json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function getPaths() {
        $filePath = self::getPathsJsonFilePath();
        
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
        if (empty($sources) && empty($destinations)) {
            return;
        }
    
        $paths = self::getPaths();
    
        if (!empty($sources)) {
            $trimmedSources = array_map('trim', $sources);
            $paths['sources'] = array_filter($trimmedSources, 'strlen');
        }
    
        if (!empty($destinations)) {
            $trimmedDestinations = array_map('trim', $destinations);
            $paths['destinations'] = array_filter($trimmedDestinations, 'strlen');
        }
    
        self::savePaths($paths);
    }
}
