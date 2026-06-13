<?php

namespace unraid\plugins\EasyRsync\Exceptions;

class RsyncFailureException extends \Exception
{
    public function __construct($message = "Rsync failed to sync", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /** Maps common rsync exit codes to human meanings. See `man rsync` EXIT VALUES. */
    public static function describeExitCode(int $code): string
    {
        return match ($code) {
            1  => 'Syntax or usage error',
            2  => 'Protocol incompatibility',
            3  => 'Errors selecting input/output files, dirs',
            5  => 'Error starting client-server protocol',
            10 => 'Error in socket I/O',
            11 => 'Error in file I/O (often: destination parent directory does not exist)',
            12 => 'Error in rsync protocol data stream (broken connection or remote rsync error)',
            13 => 'Errors with program diagnostics',
            20 => 'Received SIGINT/SIGTERM (transfer interrupted, e.g. by Force Stop)',
            23 => 'Partial transfer due to error (e.g. missing destination path or permission denied)',
            24 => 'Partial transfer due to vanished source files',
            30 => 'Timeout in data send/receive',
            35 => 'Timeout waiting for daemon connection',
            127 => 'Remote command not found — rsync may not be installed on the remote host (or --rsync-path is wrong)',
            255 => 'SSH/remote-shell connection failed — host unreachable, refused, auth failed, or host key mismatch',
            default => 'Unknown rsync error',
        };
    }
}