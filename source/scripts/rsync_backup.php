<?php

use unraid\plugins\EasyRsync\ERHelper;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\NotificationService;

$logger = new Logger();

$backupStarted = new DateTime();

if (ERHelper::isBackupRunning()) {
    NotificationService::notify("Easy Rsync", "Backup Running");
    exit;
}

// Remove dangling abort status
$abortFilePath = ERSettings::getStateRsyncAbortedFilePath();
if (file_exists($abortFilePath)) {
    unlink($abortFilePath);
}

// if tempfolder exists, remove all logs
if (file_exists(ERSettings::$tempFolder)) {
    exec("rm " . ERSettings::$tempFolder . '/*.log');
}

file_put_contents(ERSettings::getStateRsyncRunningFilePath(), getmypid());

// initial log spitout "hi, welcome to rsync script"
$logger->logInfo('Welcome test message');

// check if array is online
if (!ERHelper::isArrayOnline()) {
    $logger->logError('Array is not online.');
}

$paths = ERSettings::getPaths();

$sources = $paths['sources'];
if (empty($sources)) {
    $logger->logError('At least one source for backup is needed');
}
$destinations = $paths['destinations'];
if (empty($destinations)) {
    $logger->logError('At least one destination is needed');
}

// check if config file exists
if (!file_exists(ERSettings::getConfigFilePath())) {
    $logger->logError('Cannot find path list config file to read from.');
}

//Summary map for storing final log/notifier messages

$rsyncOptions = BackupHelper::buildRsyncOptions(doDryRun: true);

//Test all sources and destinations

// TODO: Remove this
$sources = ['/mnt/user/project_demos'];
$destination = ['sheputa@10.1.1.225:/home/sheputa/rsyncTesting'];

foreach ($sources as $source) {
    foreach ($destinations as $destination) {
        // Construct and execute the rsync command.
        $command = "rsync $rsyncOptions '$source' '$destination' --log-file='" . ERSettings::getRsyncLogFilePath() . "'";
        $logger->logInfo("Current command: $command");
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            $logger->logError("Failed to sync '$source' with '$destination'.");
        } else {
            $logger->logInfo("Successfully synced '$source' with '$destination'.");
        }
    }
}

$logger->logInfo("Finished syncing");
