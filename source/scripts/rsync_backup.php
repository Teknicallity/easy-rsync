<?php

require_once dirname(__DIR__) ."/include/BackupHelper.php";
require_once dirname(__DIR__) ."/include/ERHelper.php";
require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once dirname(__DIR__) ."/include/notifications/Notification.php";
require_once dirname(__DIR__) ."/include/paths/Destination.php";
require_once dirname(__DIR__) ."/include/paths/PathHelper.php";

use unraid\plugins\EasyRsync\BackupHelper;
use unraid\plugins\EasyRsync\Destination;
use unraid\plugins\EasyRsync\ERHelper;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\Notification;
use unraid\plugins\EasyRsync\NotificationLevel;
use unraid\plugins\EasyRsync\PathHelper;

$userConfig = ERSettings::getUserConfig();

$logger = new Logger(loglevelString: $userConfig["logLevel"]);

$shortopts = "n";

$longopts = array(
    "dry-run",
);

$options = getopt($shortopts, $longopts);

$dryRunMode = isset($options['n']) || isset($options['dry-run']);


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

// Parse paths
$paths = ERSettings::getPaths();
$logger->logInfo('Successfully parsed paths');

// Ensure paths are present
$sources = $paths['sources'];
$logger->logDebug("'". implode(',', $sources) ."'");
if (empty($sources)) {
    $logger->logError('At least one source for backup is needed');
    cleanup(failure: true);
}
$destinations = $paths['destinations'];
$logger->logDebug("'". implode(',', $destinations) ."'");
if (empty($destinations)) {
    $logger->logError('At least one destination is needed');
    cleanup(failure: true);
}

//Summary map for storing final log/notifier messages for each source and or destination
$syncSummary = array(array());
foreach ($sources as $source) {
    foreach ($destinations as $destination) {
        $syncSummary[$source][$destination] = "Skipped";
    }
}
$logger->logDebug("Sync summary\n". json_encode($syncSummary));

$rsyncOptions = BackupHelper::buildRsyncOptions(doDryRun: $dryRunMode);
$logger->logDebug($rsyncOptions);
//Test all sources and destinations

foreach ($sources as $source) {
    foreach ($destinations as $destination) {
        if (ERHelper::isAbortRequested()) {
            handleAbortRequest();
        }
        // Construct and execute the rsync command.
        $command = "rsync $rsyncOptions '$source' '$destination' --log-file='" . ERSettings::getRsyncLogFilePath() . "'";
        $logger->logInfo("Current command: $command");
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            $logger->logError("Failed to sync '$source' with '$destination'. Check Rsync Log.");

            $syncSummary[$source][$destination] = "FAILED";
        } else {
            $logger->logInfo("Successfully synced '$source' with '$destination'.");

            $syncSummary[$source][$destination] = "Success";
        }
    }
}
handleEnd();


function handleAbortRequest(): never {
    global $logger;
    $notification = new Notification(
        "Sync Aborted",
        "The sync operation was aborted by user",
        level: NotificationLevel::WARNING
    );
    $logger->logWarning("Sync aborted");
    // which sources were synced until which destinations?
    cleanup(failure: true, notification: $notification);
}

function handleEnd(): never {
    global $logger;
    $backupFinishedTime = new DateTime();
    global $backupStartedTime;
    $backupTime = $backupStartedTime->diff($backupFinishedTime);
    $backupDuration = $backupTime->format('%H:%I:%S');

    $notification = new Notification("Sync Completed", "Completed in $backupDuration");

    $logger->logInfo("Finished syncing in $backupDuration");
    cleanup(notification: $notification);
}

function cleanup(bool $failure = false, Notification $notification = null): never {
    global $logger;
    global $syncSummary;
    global $sources;
    global $destinations;

    $logger->logInfo("Cleaning up");
    if (file_exists(ERSettings::getStateRsyncAbortedFilePath())) {
        unlink(ERSettings::getStateRsyncAbortedFilePath());
        $logger->logDebug("Removed abort status file");
    }
    unlink(ERSettings::getStateRsyncRunningFilePath());
    $logger->logDebug("Remove running status file");

    if (!empty($syncSummary)) {
        $message = "";
        foreach ($sources as $source) {
            foreach ($destinations as $destination) {
                $sourceParts = PathHelper::deconstructPath($source);
                $destinationParts = new Destination($destination);

                $sourcePathEnd = $sourceParts[array_key_last($sourceParts)] ?? 'null';
                $destinationHostPath = $destinationParts->host . ':' . $destinationParts->fullPath;

                $message .= $sourcePathEnd . ' -> ' . $destinationHostPath . ' ' . $syncSummary[$source][$destination] . "\n";
            }
        }

        $notification?->setMessage($message);
    }

    $notification?->send();

    if ($failure) {
        $logger->logWarning("Something went wrong");
        exit(1);
    } else {
        $logger->logInfo("Finished syncing");
        exit(0);
    }
}