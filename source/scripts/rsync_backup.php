<?php

require_once dirname(__DIR__) ."/include/BackupHelper.php";
require_once dirname(__DIR__) ."/include/ERHelper.php";
require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once dirname(__DIR__) ."/include/NotificationService.php";

use unraid\plugins\EasyRsync\BackupHelper;
use unraid\plugins\EasyRsync\ERHelper;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\LogLevel;
use unraid\plugins\EasyRsync\NotificationService;

$logger = new Logger(LogLevel::DEBUG);
$logger->logDebug("test");
$logger->logInfo("test");
$logger->logWarning("test");
$logger->logError("test");
$backupStartedTime = new DateTime();
$logger->logDebug('Backup script called'. $backupStartedTime->format('c'));

if (ERHelper::isBackupRunning()) {
    NotificationService::notify("Easy Rsync", "Backup Running");
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
    NotificationService::notify('EasyRsync: Array is not online', 'Cannot sync. Array is not running.');
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

$rsyncOptions = BackupHelper::buildRsyncOptions(doDryRun: true);
$logger->logDebug($rsyncOptions);
//Test all sources and destinations

foreach ($sources as $source) {
    foreach ($destinations as $destination) {
        if (ERHelper::isAbortRequested()) {
            handleAbort();
        }
        // Construct and execute the rsync command.
        $command = "rsync $rsyncOptions '$source' '$destination' --log-file='" . ERSettings::getRsyncLogFilePath() . "'";
        $logger->logInfo("Current command: $command");
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            $logger->logError("Failed to sync '$source' with '$destination'. Check Rsync Log.");
        } else {
            $logger->logInfo("Successfully synced '$source' with '$destination'.");
        }
    }
}
handleEnd();


function handleAbort(): never {
    global $logger;
    NotificationService::notify("Easy Rsync", "Sync Aborted", "The sync operation was aborted");
    $logger->logWarning("Sync aborted");
    // which sources were synced until which destinations?
    cleanup(failure: true);
}

function handleEnd(): never {
    global $logger;
    $backupFinishedTime = new DateTime();
    global $backupStartedTime;
    $backupTime = $backupStartedTime->diff($backupFinishedTime);
    $backupDuration = $backupTime->format('%H:%I:%S');

    NotificationService::notify("Easy Rsync", "Sync completed in $backupDuration","");
    $logger->logInfo("Finished syncing in $backupDuration");
    cleanup();
}

function cleanup(bool $failure = false): never {
    global $logger;
    $logger->logInfo("Cleaning up");
    if (file_exists(ERSettings::getStateRsyncAbortedFilePath())) {
        unlink(ERSettings::getStateRsyncAbortedFilePath());
        $logger->logDebug("Removed abort status file");
    }
    
    unlink(ERSettings::getStateRsyncRunningFilePath());
    $logger->logDebug("Remove running status file");
    

    if ($failure) {
        $logger->logWarning("Something went wrong");
        exit(1);
    } else {
        $logger->logInfo("Finished syncing");
        exit(0);
    };
}