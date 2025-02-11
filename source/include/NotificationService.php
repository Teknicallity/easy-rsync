<?php

namespace unraid\plugins\EasyRsync;

enum NotificaitonLevel: string {
    case NORMAL = 'normal';
    case ALERT = 'alert';
    case WARNING = 'warning';
}

class NotificationService {
    /**
     * Send a message to the system notification system
     *
     * @param string $subject One line topic of notification
     * @param string $description Short description
     * @param string $message Lengthy description
     * @param NotificaitonLevel $level one of 'normal', 'alert', or 'warning'
     *
     * @return void
     */
    public static function notify(
        string $subject,
        string $description,
        string $message = "",
        NotificaitonLevel $level = NotificaitonLevel::NORMAL) {

        $command = '/usr/local/emhttp/webGui/scripts/notify -e "Easy Rsync" -s "' . escapeshellarg($subject) . '" ' .
                '-d "' . escapeshellarg($description) . '" -m "' . escapeshellarg($message) . '" -i "' . $level . '" ' .
                '-l "/Settings/AB.Main"';
        shell_exec($command);
    }
}