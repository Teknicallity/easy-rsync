<?php

require_once dirname(__DIR__) ."/include/BackupHelper.php";
require_once dirname(__DIR__) ."/include/ERHelper.php";
require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once dirname(__DIR__) ."/include/notifications/Notification.php";
require_once dirname(__DIR__) ."/include/paths/Destination.php";
require_once dirname(__DIR__) ."/include/paths/PathHelper.php";
require_once dirname(__DIR__) ."/include/sync_list/RsyncOptions.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncEntry.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncList.php";

use unraid\plugins\EasyRsync\BackupHelper;
use unraid\plugins\EasyRsync\Destination;
use unraid\plugins\EasyRsync\ERHelper;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\Notification;
use unraid\plugins\EasyRsync\NotificationLevel;
use unraid\plugins\EasyRsync\PathHelper;
use unraid\plugins\EasyRsync\SyncList;
use unraid\plugins\EasyRsync\SyncEntry;
use unraid\plugins\EasyRsync\RsyncOptions;

$userConfig = ERSettings::getUserConfig();

$logger = new Logger(loglevelString: $userConfig["logLevel"]);

$shortopts = "n";

$longopts = array(
    "dry-run",
);

$options = getopt($shortopts, $longopts);

$dryRunMode = isset($options['n']) || isset($options['dry-run']);

$useEmojis = true;

$backupStartedTime = new DateTime();
$logger->logDebug('Backup script called'. $backupStartedTime->format('c'));

if (ERHelper::isBackupRunning()) {
    Notification::simpleNotify("Sync Already Running", "Cannot start another sync operation. Sync is still running.");
    $logger->logWarning("Backup already running. Cannot start new backup");
    exit();
}
$logger->logDebug("Backup is not already running");

// if tempfolder exists, remove all logs
if (file_exists(ERSettings::$tempFolder)) {
    exec("rm " . ERSettings::$tempFolder . '/*.log');
    $logger->logInfo("Removing previous logs");
}

// Remove dangling abort status
$abortFilePath = ERSettings::getStateRsyncAbortedFilePath();
if (file_exists($abortFilePath)) {
    $logger->logInfo("Removing previous aborted status");
    unlink($abortFilePath);
}

$logger->logInfo("Saving process id". (string) getmypid());
file_put_contents(ERSettings::getStateRsyncRunningFilePath(), getmypid());

// initial log message
$logger->logInfo('Welcome test message');

// check if array is online
if (!ERHelper::isArrayOnline()) {
    $logger->logError('Array is not online.');
    Notification::simpleNotify('Array is not online', 'Cannot sync. Array is not running.');
    exit();
}
$logger->logDebug('Array is online');

// check if config file exists
if (!file_exists(ERSettings::getPathsJsonFilePath())) {
    $logger->logError('Cannot find path list config file to read from.');
    cleanup(failure: true);
}
$logger->logDebug('Paths list file exists');

// Get user defined synclist
$syncList = SyncList::fromFile();
$logger->logInfo('Successfully parsed paths');

// Todo ensure no paths are empty

$allSyncSummaries = [];
$hadFailure = false;

foreach ($syncList->entries as $index => $syncEntry) {
    $syncSummary = [];
    //Summary map for storing final log/notifier messages for each source and or destination
    foreach ($syncEntry->sources as $source) {
        foreach ($syncEntry->destinations as $destination) {
            $syncSummary[$source][$destination] = $useEmojis ? "⏭️" : "Skipped:";
        }
    }

    //TODO change over to entry's rsync options
    $rsyncOptions = BackupHelper::buildRsyncOptions(doDryRun: $dryRunMode);
    $logger->logDebug($rsyncOptions);

    foreach ($syncEntry->sources as $source) {
        foreach ($syncEntry->destinations as $destination) {
            if (ERHelper::isAbortRequested()) {
                handleAbortRequest($allSyncSummaries);
            }

            // Construct and execute the rsync command.
            $command = "rsync $rsyncOptions '$source' '$destination' --log-file='" . ERSettings::getRsyncLogFilePath() . "'";
            $logger->logInfo("Current command: $command");
            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                $logger->logError("Failed to sync '$source' with '$destination'. Check Rsync Log.");
                $syncSummary[$source][$destination] = $useEmojis ? "❌" : "Failure:";
                $hadFailure = true;
            } else {
                $logger->logInfo("Successfully synced '$source' with '$destination'.");
                $syncSummary[$source][$destination] = $useEmojis ? "✅" : "Success:";
            }
        }
    }
    $allSyncSummaries[] = $syncSummary;
}
handleFinalSummary($allSyncSummaries, $hadFailure);


function handleAbortRequest(array $allSyncSummaries): never {
    global $logger;

    $flatSummary = [];
    foreach ($allSyncSummaries as $summary) {
        $flatSummary = array_merge_recursive($flatSummary, $summary);
    }

    $notification = new Notification(
        "Sync Aborted",
        "The sync operation was aborted by user",
        message: getFinalNotificationPathsMessage($flatSummary),
        level: NotificationLevel::WARNING
    );
    $notification->send();

    $logger->logWarning("Sync aborted");
    cleanup(failure: true);
    exit(1);
}

function handleFinalSummary(array $allSyncSummaries, bool $hadFailure): never {
    global $logger, $backupStartedTime;

    $backupFinishedTime = new DateTime();
    $backupTime = $backupStartedTime->diff($backupFinishedTime);
    $duration = $backupTime->format('%H:%I:%S');

    $flatSummary = [];
    foreach ($allSyncSummaries as $summary) {
        $flatSummary = array_merge_recursive($flatSummary, $summary);
    }

    $notification = new Notification(
        $hadFailure ? "Sync Completed with Errors" : "Sync Completed",
        "Completed in $duration",
        message: getFinalNotificationPathsMessage($flatSummary),
        level: $hadFailure ? NotificationLevel::ALERT : NotificationLevel::NORMAL
    );
    $notification->send();

    cleanup();
    exit($hadFailure ? 1 : 0);
}

function cleanup(bool $failure = false): void {
    global $logger;

    $logger->logInfo("Cleaning up");

    if (file_exists(ERSettings::getStateRsyncAbortedFilePath())) {
        unlink(ERSettings::getStateRsyncAbortedFilePath());
        $logger->logDebug("Removed abort status file");
    }

    if (file_exists(ERSettings::getStateRsyncRunningFilePath())) {
        unlink(ERSettings::getStateRsyncRunningFilePath());
        $logger->logDebug("Removed running status file");
    }

    $logger->logInfo("Cleanup complete");
}

/**
 * @param array $syncSummary
 * @return string
 */
function getFinalNotificationPathsMessage(array $syncSummary): string {
    global $syncList;

    $message = "";
    $count = count($syncList->entries);
    foreach ($syncList->entries as $jobIndex => $syncEntry) {
        $message .= "**Sync Job #" . ($jobIndex + 1) . "**\\n";
        foreach ($syncEntry->sources as $source) {
            $filteredSourcePath = shortenSourcePath($source);

            foreach ($syncEntry->destinations as $destination) {
                $filteredDestination = shortenDestinationPath($destination);

                $message .= $syncSummary[$source][$destination] . ' ' .
                    $filteredSourcePath . ' ->\\n⠀⠀' . // uses '⠀⠀' at end, not spaces
                    $filteredDestination . "\\n";
            }
        }
        $message .= "\\n";
    }
    return $message;
}

function shortenSourcePath(string $source): string {
    $maxLength = 26;
    if (strlen($source) > $maxLength) {
        $sourceParts = PathHelper::extractPathComponents($source);
        $lastComponent = end($sourceParts);

        if (strlen($lastComponent) > $maxLength) {
            $sourcePathEnd = substr($lastComponent, 0, $maxLength - 3) . '...';
        } else {
            $sourcePathEnd = $lastComponent;
        }
    } else {
        $sourcePathEnd = $source;
    }
    return $sourcePathEnd;
}

function shortenDestinationPath(string $destination): string {
    $maxLength = 26;
    $hostAndPath = (new Destination($destination))->hostAndPath();

    if (strlen($hostAndPath) > $maxLength) {
        return substr($hostAndPath, 0, $maxLength - 3) . '...';
    }

    return $hostAndPath;
}