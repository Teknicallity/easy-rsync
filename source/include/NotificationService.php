<?php

namespace unraid\plugins\EasyRsync;

use InvalidArgumentException;

class NotificationService {
    /**
     * Send a message to the system notification system
     *
     * @param string $subject
     * @param string $description
     * @param string $message
     * @param string $type one of 'normal', 'alert', or 'warning'
     *
     * @return void
     */
    public static function notify($subject, $description, $message = "", $type = "normal") {
        $allowedTypes = ['normal', 'alert', 'warning'];

        if (!in_array($type, $allowedTypes)) {
            throw new InvalidArgumentException("Invalid notification type: {$type}. Allowed types are normal, alert, and warning.");
        }

        $command = '/usr/local/emhttp/webGui/scripts/notify -e "Easy Rsync" -s "' . escapeshellarg($subject) . '" ' .
                '-d "' . escapeshellarg($description) . '" -m "' . escapeshellarg($message) . '" -i "' . $type . '" ' .
                '-l "/Settings/AB.Main"';
        shell_exec($command);
    }
}