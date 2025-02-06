<?php

require_once __DIR__ . "/LogHandler.php";
use unraid\plugins\EasyRsync\LogHandler;

if ((isset($_GET['action']) && !isset($_POST['action'])) || (isset($_POST['action']) && !isset($_GET['action']))) {
    $action = $_GET['action'] ?? $_POST['action'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostAction($action);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetAction($action);
    } else {
        sendError('Invalid request method', 405); // Method Not Allowed
    }
} else {
    sendError('No action specified or both GET and POST actions are set', 400); // Bad Request
}

function handlePostAction(string $action) {
    switch ($action) {
        case 'abort':
            // handleAbortAction();
            break;
        case 'manualBackup':
            exec('php ' . dirname(__DIR__) . '/scripts/rsync_backup.php > /dev/null &');
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
                    'status'=> $status,
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
                    'status'=> $status,
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
                    'status'=> $status,
                ];
                sendResponse($data);
            } catch (Exception $e) {
                sendError('Failed to get RSync log: ' . htmlspecialchars($e->getMessage()), 500);
            }
            break;
        default:
            sendError('Invalid get action: ' . htmlspecialchars($action), 400);
            break;
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