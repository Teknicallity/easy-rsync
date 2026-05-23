<?php

namespace unraid\plugins\EasyRsync;

$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

require_once "$docroot/webGui/include/Wrappers.php";

class ERSettings {

    public static string $appName = 'easy.rsync';
    private static string $pathsFileName = 'backup_paths.json';
    private static string $logFileName = 'easy-rsync.log';
    private static string $rsyncLogFileName = 'rsync.log';
    private static string $stateRsyncRunningFileName = 'running';
    private static string $stateRsyncAbortedFileName = 'aborted';
    public static string $emhttpVars = '/var/local/emhttp/var.ini';

    public static function getConfigDir() : string {
        return getenv('EASY_RSYNC_CONFIG_DIR') ?: '/boot/config/plugins/' . self::$appName;
    }
    private static function getCronFileName() : string { return self::$appName . '.cron'; }
    public static function getTempDir() : string {
        return getenv('EASY_RSYNC_TEMP_DIR') ?: '/tmp/' . self::$appName;
    }

    public static function getUserConfig(): array{
        return parse_plugin_cfg(self::$appName);
    }

    public static function saveUserConfig(array $userConfig): bool|int {
        $ini_contents = self::arrayToIni($userConfig);
        $configFilePath = self::getConfigDir() .'/easy.rsync.cfg';
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
                'array'   => "\n[$key]\n" . self::arrayToIni($value),
                default   => "",
            };
        }
        
        return $iniContents;
    }

    public static function getPathsJsonFilePath(): string {
        return self::getConfigDir() . '/' . self::$pathsFileName;
    }
    
    public static function getLogFilePath(): string {
        return self::getTempDir() . '/' . self::$logFileName;
    }

    public static function getRsyncLogFilePath(): string {
        return self::getTempDir() . '/' . self::$rsyncLogFileName;
    }

    public static function getStateRsyncRunningFilePath(): string {
        return self::getTempDir() . '/' . self::$stateRsyncRunningFileName;
    }

    public static function getStateRsyncAbortedFilePath(): string {
        return self::getTempDir() . '/' . self::$stateRsyncAbortedFileName;
    }

    private static function savePaths(array $paths): bool|int {
        return file_put_contents(self::getPathsJsonFilePath(), json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function getPaths(): array {
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

    public static function saveSourcesAndDestinations(array $sources = null, array $destinations = null): void {
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

    public static function updateCron(): array {
        $cronContents = "# Easy Rsync cron settings" . PHP_EOL;
        $userConfig = ERSettings::getUserConfig();
        $cronContents .= match ($userConfig["backupFrequency"]) {
            "custom" => $userConfig["frequencyCustom"],
            "daily" => $userConfig["frequencyMinute"] ." ". $userConfig["frequencyHour"] ." * * *",
            "weekly" => $userConfig["frequencyMinute"] ." ". $userConfig["frequencyHour"] ." * * ".  $userConfig["frequencyWeekday"],
            "monthly" => $userConfig["frequencyMinute"] ." ". $userConfig["frequencyHour"] . " " .  $userConfig["frequencyDayOfMonth"] ." * *",
            default => $cronContents = ''
        };

        $cronFilePath = self::getConfigDir() . '/' . self::getCronFileName();
        if (!empty($cronContents)) {
            $cronContents .= " php ". dirname(__DIR__) ."/scripts/rsync_backup.php > /dev/null 2>&1";
            file_put_contents($cronFilePath, $cronContents . PHP_EOL . PHP_EOL);
        } elseif (file_exists($cronFilePath)) {
            unlink($cronFilePath);
        }

        $outString = $returnCode = 0;
        exec("update_cron", $outString, $returnCode);
        return [$outString, $returnCode];
    }
}
