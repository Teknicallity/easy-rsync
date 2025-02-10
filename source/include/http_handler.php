<?php

require_once __DIR__ . "/LogHandler.php";
use unraid\plugins\EasyRsync\LogHandler;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['action'])) {
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

function handlePostAction(string $action) {
    switch ($action) {
        case 'abort':
            // handleAbortAction();
            break;
        case 'manualBackup':
            // exec('php ' . dirname(__DIR__) . '/scripts/rsync_backup.php > /dev/null &');
            exec('logger test-successful');
            sendResponse(['msg' => 'Starting sync']);
            break;
        default:
            sendError('Invalid post action: ' . htmlspecialchars($action), 400);
    }
}

function handleGetAction(string $action) {
    switch ($action) {
        case 'getBackupStatus':
            try {
                $status = LogHandler::getBackupStatus();
                
                $data = [
                    'status' => $status,
                ];
                sendResponse($data);
            } catch (Exception $e) {
                sendError('Failed to get backup status: ' . htmlspecialchars($e->getMessage()), 500);
            }
            break;
        case 'getLog':
            try {
                $log = LogHandler::getPluginLog();
                $status = LogHandler::getBackupStatus();
                
                $data = [
                    'log' => $log,
                    'status' => $status,
                ];
                sendResponse($data);
            } catch (Exception $e) {
                sendError('Failed to get Easy Rsync log: ' . htmlspecialchars($e->getMessage()), 500);
            }
            break;
        case 'getRsyncLog':
            try {
                $log = LogHandler::getRsyncLog();
                $status = LogHandler::getBackupStatus();
                
                $data = [
                    'log' => $log,
                    'status' => $status,
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

function sendError(string $msg, int $code = 404) {
    http_response_code($code); // Set the HTTP status code.
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['msg' => $msg]);
    exit();
}

function sendResponse(array $data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}