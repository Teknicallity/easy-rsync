<?php

namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/Syncer.php";
require_once dirname(__DIR__) . "/ERSettings.php";
require_once dirname(__DIR__) . "/Logger.php";

use unraid\plugins\EasyRsync\Exceptions\RsyncFailureException;
use unraid\plugins\EasyRsync\Syncer;

class RsyncSyncer implements Syncer {

    /**
     * @throws RsyncFailureException
     */
    public function performSync(string $source, string $destination, string $rsyncOptions): void {
        $rsyncLogFilePath = ERSettings::getRsyncLogFilePath();

        // escapeshellarg paths so spaces/quotes/special chars are safe (NOT
        // $rsyncOptions, which is a pre-joined flag string that must word-split).
        // 2>&1: rsync's fatal errors go to stderr, which --log-file does NOT record.
        // Leading "exec ": the shell replaces itself with rsync, so proc_get_status()
        // reports rsync's own PID — that's what Force Stop kills.
        $command = "exec rsync $rsyncOptions "
            . escapeshellarg($source) . " " . escapeshellarg($destination)
            . " --log-file=" . escapeshellarg($rsyncLogFilePath) . " 2>&1";

        Logger::getLogger()->debug("Running rsync command: $command");

        $pidFilePath = ERSettings::getStateRsyncPidFilePath();
        $proc = proc_open($command, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RsyncFailureException(
                message: "Failed to start rsync for '$source' -> '$destination'",
                code: -1
            );
        }

        $procStatus = proc_get_status($proc);
        if (!empty($procStatus['pid'])) {
            @file_put_contents($pidFilePath, (string) $procStatus['pid']);
        }

        // Read the merged stdout/stderr fully; blocks until rsync exits (as exec did).
        $outputText = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $return_var = proc_close($proc);
        @unlink($pidFilePath);

        if ($return_var !== 0) {
            $capturedText = trim($outputText);
            $meaning = RsyncFailureException::describeExitCode($return_var);
            $message = "Rsync failed to sync '$source' -> '$destination' (exit code $return_var: $meaning)";
            if ($capturedText !== '') {
                $message .= "\n" . $capturedText;
            }
            // Make the failure visible in the Rsync Log tab.
            @file_put_contents($rsyncLogFilePath,
                date("Y-m-d H:i:s") . " [ERROR] " . $message . PHP_EOL, FILE_APPEND);

            throw new RsyncFailureException(message: $message, code: $return_var);
        }
    }
}