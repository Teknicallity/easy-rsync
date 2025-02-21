<?php

namespace unraid\plugins\EasyRsync;

require_once "/usr/local/emhttp/plugins/dynamix/include/Wrappers.php";

class ERSettings {

    public static string $appName = 'easy.rsync';
    public static string $configDir = '/boot/config/plugins/easy.rsync';
    private static string $pathsFile = 'backup_paths.json';
    private static string $cronFile = 'easy.rsync.cron';
    public static string $tempFolder = '/tmp/easy.rsync';
    private static string $logFile = 'easy-rsync.log';
    private static string $rsyncLogFile = 'rsync.log';
    private static string $stateRsyncRunningFile = 'running';
    private static string $stateRsyncAbortedFile = 'aborted';
    public static string $emhttpVars = '/var/local/emhttp/var.ini';

    public static function getUserConfig(): array{
        return parse_plugin_cfg(self::$appName);
    }

    public static function saveUserConfig(array $userConfig): bool|int {
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
                'array'   => "\n[$key]\n" . self::arrayToIni($value),
                default   => "",
            };
        }
        
        return $iniContents;
    }

    public static function getPathsJsonFilePath(): string {
        return self::$configDir . '/' . self::$pathsFile;
    }
    
    public static function getLogFilePath(): string {
        return self::$tempFolder . '/' . self::$logFile;
    }

    public static function getRsyncLogFilePath(): string {
        return self::$tempFolder . '/' . self::$rsyncLogFile;
    }

    public static function getStateRsyncRunningFilePath(): string {
        return self::$tempFolder . '/' . self::$stateRsyncRunningFile;
    }

    public static function getStateRsyncAbortedFilePath(): string {
        return self::$tempFolder . '/' . self::$stateRsyncAbortedFile;
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

        $cronFilePath = self::$configDir . '/' . self::$cronFile;
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
