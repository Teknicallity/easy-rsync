<?php

namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/NotificationLevel.php";

class Notification {
    private string $event = "Easy Rsync";
    private string $subject;
    private string $description;
    private string $message;
    private NotificationLevel $level;
    private string $link = "/Settings/AB.Main";

    public function __construct($subject = "", $description = "", $message = "", $level = NotificationLevel::NORMAL) {
        $this->subject = $subject;
        $this->description = $description;
        $this->message = $message;
        $this->level = $level;
        return $this;
    }

    public function setSubject(string $subject): Notification {
        $this->subject = $subject;
        return $this;
    }

    public function setDescription(string $description): Notification {
        $this->description = $description;
        return $this;
    }

    public function setMessage(string $message): Notification {
        $this->message = $message;
        return $this;
    }

    public function setLevel(NotificationLevel $level): Notification {
        $this->level = $level;
        return $this;
    }

    public function send(): void {
        if ($this->subject === ""){
            return;
        }

        $command = '/usr/local/emhttp/webGui/scripts/notify -e ' . escapeshellarg($this->event) .
            ' -s ' . escapeshellarg($this->subject) .
            ' -d ' . escapeshellarg($this->description) .
            ' -m ' . escapeshellarg($this->message) .
            ' -i ' . escapeshellarg($this->level->value) .
            ' -l ' . escapeshellarg($this->link);
        shell_exec($command);
    }

    /**
     * Send a message to the system notification system
     *
     * @param string $subject One line topic of notification
     * @param string $description Short description
     * @param string $message Lengthy description
     * @param NotificationLevel $level one of 'normal', 'alert', or 'warning'
     *
     * @return void
     */
    public static function simpleNotify(
        string            $subject,
        string            $description,
        string            $message = "",
        NotificationLevel $level = NotificationLevel::NORMAL): void {

        $command = '/usr/local/emhttp/webGui/scripts/notify -e "Easy Rsync" -s ' . escapeshellarg($subject) . ' ' .
            '-d ' . escapeshellarg($description) . ' -m ' . escapeshellarg($message) . ' -i ' . $level->value . ' ' .
            '-l "/Settings/AB.Main"';
        shell_exec($command);
    }
}