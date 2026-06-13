<?php

require_once __DIR__ . "/LogHandler.php";
require_once __DIR__ . "/ERSettings.php";
require_once __DIR__ . "/ERHelper.php";
require_once __DIR__ . "/Logger.php";
require_once __DIR__ . "/notifications/Notification.php";
require_once __DIR__ . "/notifications/NotificationLevel.php";

use unraid\plugins\EasyRsync\LogHandler;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\ERHelper;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\Notification;
use unraid\plugins\EasyRsync\NotificationLevel;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        handlePostAction($_POST['action']);
    } else {
        sendError('No action specified', 400); // Bad Request
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        handleGetAction($_GET['action']);
    } else {
        sendError('No action specified', 400); // Bad Request
    }
} else {
    sendError('Invalid request method', 405); // Method Not Allowed
}

function handlePostAction(string $action): void {
    switch ($action) {
        case 'abort':
            if (ERHelper::isBackupRunning()) {
                file_put_contents(ERSettings::getStateRsyncAbortedFilePath(), '1');
                Logger::getLogger()->info("Graceful stop requested. The current sync will finish, then the remaining syncs will be skipped.");
                exec('logger -t EasyRsync Graceful stop requested');
                Notification::simpleNotify(
                    "Backup stop requested",
                    "The sync in progress will finish, then the remaining jobs are skipped.",
                    level: NotificationLevel::WARNING
                );
                sendResponse(['msg' => 'Graceful stop requested. The backup will stop after the current sync finishes.']);
            } else {
                sendResponse(['msg' => 'No backup is running.']);
            }
            break;
        case 'abortNow':
            if (ERHelper::isBackupRunning()) {
                // Set the abort flag first so the run stops even if the kill races/misses.
                file_put_contents(ERSettings::getStateRsyncAbortedFilePath(), '1');
                $killed = ERHelper::killRunningRsync();
                Logger::getLogger()->warning("Force stop requested. Killing the running rsync; remaining syncs will be skipped.");
                exec('logger -t EasyRsync Force stop requested');
                Notification::simpleNotify(
                    "Backup force stopped",
                    $killed ? "The running transfer was killed; remaining jobs skipped."
                            : "Force stop requested; remaining jobs will be skipped.",
                    level: NotificationLevel::WARNING
                );
                sendResponse(['msg' => $killed
                    ? 'Force stop: the running transfer was killed; remaining jobs skipped.'
                    : 'Force stop requested; remaining jobs will be skipped.']);
            } else {
                sendResponse(['msg' => 'No backup is running.']);
            }
            break;
        case 'manualBackup':
            exec('php ' . dirname(__DIR__) . '/scripts/rsync_backup.php > /dev/null &');
            exec('logger -t EasyRsync Started backup');
            sendResponse(['msg' => 'Starting sync']);
            break;
        case 'manualDryBackup':
            exec('php ' . dirname(__DIR__) . '/scripts/rsync_backup.php --dry-run > /dev/null &');
            exec('logger -t EasyRsync Started dry backup');
            sendResponse(['msg' => 'Starting sync']);
            break;
        default:
            sendError('Invalid post action: ' . htmlspecialchars($action), 400);
    }
}

function handleGetAction(string $action): void {
    switch ($action) {
        case 'getBackupStatus':
            try {
                $running = LogHandler::getBackupStatus();
                
                $data = [
                    'running' => $running,
                ];
                sendResponse($data);
            } catch (Exception $e) {
                sendError('Failed to get backup status: ' . htmlspecialchars($e->getMessage()), 500);
            }
            break;
        case 'getPluginLog':
            try {
                $log = LogHandler::getPluginLog();
                $running = LogHandler::getBackupStatus();
                
                $data = [
                    'log' => $log,
                    'running' => $running,
                ];
                sendResponse($data);
            } catch (Exception $e) {
                sendError('Failed to get Easy Rsync log: ' . htmlspecialchars($e->getMessage()), 500);
            }
            break;
        case 'getRsyncLog':
            try {
                $log = LogHandler::getRsyncLog();
                $running = LogHandler::getBackupStatus();
                
                $data = [
                    'log' => $log,
                    'running' => $running,
                ];
                sendResponse($data);
            } catch (Exception $e) {
                sendError('Failed to get RSync log: ' . htmlspecialchars($e->getMessage()), 500);
            }
            break;
        default:
            sendError('Invalid get action: ' . htmlspecialchars($action), 400);
    }
}

function sendError(string $error, int $code = 404): never {
    http_response_code($code); // Set the HTTP status code.
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $error]);
    exit();
}

function sendResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}