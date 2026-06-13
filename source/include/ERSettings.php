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
    private static string $stateRsyncPidFileName = 'rsync.pid';
    public static string $emhttpVars = '/var/local/emhttp/var.ini';

    public static function getConfigDir() : string {
        return getenv('EASY_RSYNC_CONFIG_DIR') ?: '/boot/config/plugins/' . self::$appName;
    }
    private static function getCronFileName() : string { return self::$appName . '.cron'; }
    public static function getTempDir() : string {
        return getenv('EASY_RSYNC_TEMP_DIR') ?: '/tmp/' . self::$appName;
    }

    /**
     * URL of this plugin's page, used for the "Open" link on Unraid notifications.
     * Unraid resolves the page by name regardless of the menu-section prefix, so
     * the common `/Settings/<Page>` convention is used. Derived from $appName so it
     * stays correct for the beta build (the package builder rewrites $appName to
     * 'easy.rsync.beta' and renames the page to EasyRsync.Beta).
     */
    public static function getPluginPageUrl(): string {
        $page = 'EasyRsync' . (str_ends_with(self::$appName, '.beta') ? '.Beta' : '');
        return '/Settings/' . $page;
    }

    public static function getUserConfig(): array{
        return parse_plugin_cfg(self::$appName);
    }

    /**
     * Path to the persisted user config file. Must match the filename Unraid's
     * parse_plugin_cfg() looks at: /boot/config/plugins/<plugin>/<plugin>.cfg.
     */
    public static function getUserConfigFilePath(): string {
        return self::getConfigDir() . '/' . self::$appName . '.cfg';
    }

    public static function saveUserConfig(array $userConfig): bool|int {
        $ini_contents = self::arrayToIni($userConfig);
        return file_put_contents(self::getUserConfigFilePath(), $ini_contents);
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

    /** Path to the file holding the PID of the rsync process currently running. */
    public static function getStateRsyncPidFilePath(): string {
        return self::getTempDir() . '/' . self::$stateRsyncPidFileName;
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

    public static function buildCronString(array $userConfig): ?string {
        $frequency = $userConfig['backupFrequency'] ?? null;
        $minute = $userConfig['frequencyMinute'] ?? '';
        $hour = $userConfig['frequencyHour'] ?? '';

        $schedule = match ($frequency) {
            'custom'  => $userConfig['frequencyCustom'] ?? '',
            'daily'   => "$minute $hour * * *",
            'weekly'  => "$minute $hour * * " . ($userConfig['frequencyWeekday'] ?? ''),
            'monthly' => "$minute $hour " . ($userConfig['frequencyDayOfMonth'] ?? '') . ' * *',
            default   => null,
        };

        return ($schedule === null || $schedule === '') ? null : $schedule;
    }

    public static function updateCron(): array {
        $schedule = self::buildCronString(self::getUserConfig());
        $cronFilePath = self::getConfigDir() . '/' . self::getCronFileName();

        if ($schedule !== null) {
            $cronContents = "# Easy Rsync cron settings" . PHP_EOL
                . $schedule . " php " . dirname(__DIR__) . "/scripts/rsync_backup.php > /dev/null 2>&1"
                . PHP_EOL . PHP_EOL;
            file_put_contents($cronFilePath, $cronContents);
        } elseif (file_exists($cronFilePath)) {
            unlink($cronFilePath);
        }

        $outString = $returnCode = 0;
        exec("update_cron 2>&1", $outString, $returnCode);
        return [$outString, $returnCode];
    }
}
