<?php

namespace unraid\plugins\EasyRsync;

require_once dirname(__DIR__) ."/include/ERHelper.php";
require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once dirname(__DIR__) ."/include/notifications/Notification.php";
require_once dirname(__DIR__) ."/include/notifications/NotificationLevel.php";
require_once dirname(__DIR__) ."/include/paths/Destination.php";
require_once dirname(__DIR__) ."/include/paths/PathHelper.php";
require_once dirname(__DIR__) ."/include/sync_list/RsyncOptions.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncEntry.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncList.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncResult.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncStatus.php";
require_once dirname(__DIR__) ."/include/syncer/RsyncSyncer.php";

use DateTime;

$userConfig = ERSettings::getUserConfig();

$logger = Logger::getLogger();

$shortopts = "n";

$longopts = array(
    "dry-run",
);

$options = getopt($shortopts, $longopts);

$dryRunMode = isset($options['n']) || isset($options['dry-run']);

$useEmojis = true;

$backupStartedTime = new DateTime();
$logger->debug('Backup script called'. $backupStartedTime->format('c'));

if (ERHelper::isBackupRunning()) {
    Notification::simpleNotify("Sync Already Running", "Cannot start another sync operation. Sync is still running.");
    $logger->warning("Backup already running. Cannot start new backup");
    exit();
}
$logger->debug("Backup is not already running");

// if tempfolder exists, rotate the previous run's logs (keep one generation)
if (file_exists(ERSettings::getTempDir())) {
    LogHandler::rotateLogs();
    $logger->info("Rotated previous logs");
}

// Remove dangling abort status
$abortFilePath = ERSettings::getStateRsyncAbortedFilePath();
if (file_exists($abortFilePath)) {
    $logger->info("Removing previous aborted status");
    unlink($abortFilePath);
}

$logger->info("Saving process id". (string) getmypid());
file_put_contents(ERSettings::getStateRsyncRunningFilePath(), getmypid());

// initial log message
$logger->info('Welcome test message');

// check if array is online
if (!ERHelper::isArrayOnline()) {
    $logger->error('Array is not online.');
    Notification::simpleNotify('Array is not online', 'Cannot sync. Array is not running.');
    exit();
}
$logger->debug('Array is online');

// check if config file exists
if (!file_exists(ERSettings::getPathsJsonFilePath())) {
    $logger->error('Cannot find path list config file to read from.');
    cleanup();
    exit();
}
$logger->debug('Paths list file exists');

// Get user defined synclist
$syncList = SyncList::fromFile();
$logger->info('Successfully parsed paths');

// Todo ensure no paths are empty

$notification = new Notification(
    "Sync started",
    "Sync started at " . $backupStartedTime->format("Y/m/d H:i:s"),
    level: NotificationLevel::NORMAL
);
$notification->send();

$syncList->syncer = new RsyncSyncer();
$syncList->syncAll(doDryRun: $dryRunMode);

handleFinalSummary($syncList, $useEmojis);

function handleFinalSummary(SyncList $syncList, bool $useEmojis): never {
    global $logger, $backupStartedTime, $userConfig;

    $backupFinishedTime = new DateTime();
    $backupTime = $backupStartedTime->diff($backupFinishedTime);
    $duration = $backupTime->format('%H:%I:%S');

    $subject = match ($syncList->finalStatus) {
        SyncStatus::Success => "Sync Completed",
        SyncStatus::Failed => "Sync Completed with Errors",
        SyncStatus::Skipped => "Sync Aborted",
    };

    $doSummaryNotification = $userConfig["notificationMode"] === "both" || $userConfig["notificationMode"] === "summary";
    if ($doSummaryNotification) {
        $notificationLevel = match ($syncList->finalStatus) {
            SyncStatus::Success => NotificationLevel::NORMAL,
            SyncStatus::Failed => NotificationLevel::ALERT,
            SyncStatus::Skipped => NotificationLevel::WARNING,
        };

        $notification = new Notification(
            $subject,
            "Completed in $duration",
            message: $syncList->generateSummaryMessage($useEmojis),
            level: $notificationLevel
        );

        $notification->send();
    }

    $logMessage = $subject . ". Took " . $duration;
    switch ($syncList->finalStatus) {
        case SyncStatus::Failed:
            $logger->error($logMessage);
            break;
        case SyncStatus::Skipped:
            $logger->warning($logMessage);
            break;
        default:
            $logger->info($logMessage);
    }

    cleanup();
    exit($syncList->finalStatus == SyncStatus::Success ? 0 : 1);
}

function cleanup(): void {
    global $logger;

    $logger->info("Cleaning up");

    if (file_exists(ERSettings::getStateRsyncAbortedFilePath())) {
        unlink(ERSettings::getStateRsyncAbortedFilePath());
        $logger->debug("Removed abort status file");
    }

    if (file_exists(ERSettings::getStateRsyncRunningFilePath())) {
        unlink(ERSettings::getStateRsyncRunningFilePath());
        $logger->debug("Removed running status file");
    }

    $logger->info("Cleanup complete");
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