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

    /** True if $value is a non-negative integer string within [$min, $max]. */
    private static function isIntInRange(mixed $value, int $min, int $max): bool {
        $s = trim((string)($value ?? ''));
        if ($s === '' || !ctype_digit($s)) {
            return false;
        }
        $n = (int)$s;
        return $n >= $min && $n <= $max;
    }

    /**
     * Validate a user-supplied custom cron expression. Returns the trimmed
     * expression if it is a single @-shortcut (@daily, @hourly, ...) or exactly five
     * whitespace-separated time fields; otherwise null. Rejects newlines so a value
     * cannot inject extra cron lines or produce a malformed file.
     */
    private static function validateCustomCron(string $expr): ?string {
        $expr = trim($expr);
        if ($expr === '' || strpbrk($expr, "\r\n") !== false) {
            return null;
        }
        if ($expr[0] === '@') {
            return preg_match('/^@\w+$/', $expr) === 1 ? $expr : null;
        }
        return count(preg_split('/\s+/', $expr)) === 5 ? $expr : null;
    }

    /**
     * Build the cron time-field string for the configured frequency. Every field is
     * validated server-side; if any required field is missing or out of range the
     * method returns null so updateCron() removes the cron entry instead of writing a
     * malformed line (e.g. an empty minute would yield " 0 * * *", which cron rejects
     * -- silently disabling the scheduled backup).
     */
    public static function buildCronString(array $userConfig): ?string {
        $frequency = $userConfig['backupFrequency'] ?? null;
        $minute = trim((string)($userConfig['frequencyMinute'] ?? ''));
        $hour = trim((string)($userConfig['frequencyHour'] ?? ''));

        $timeOk = self::isIntInRange($minute, 0, 59) && self::isIntInRange($hour, 0, 23);

        switch ($frequency) {
            case 'custom':
                return self::validateCustomCron((string)($userConfig['frequencyCustom'] ?? ''));
            case 'daily':
                return $timeOk ? "$minute $hour * * *" : null;
            case 'weekly':
                $weekday = trim((string)($userConfig['frequencyWeekday'] ?? ''));
                return ($timeOk && self::isIntInRange($weekday, 0, 7)) ? "$minute $hour * * $weekday" : null;
            case 'monthly':
                $dayOfMonth = trim((string)($userConfig['frequencyDayOfMonth'] ?? ''));
                return ($timeOk && self::isIntInRange($dayOfMonth, 1, 31)) ? "$minute $hour $dayOfMonth * *" : null;
            default:
                return null;
        }
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
