<?php

namespace unraid\plugins\EasyRsync\Exceptions;

class RsyncFailureException extends \Exception
{
    public function __construct($message = "Rsync failed to sync", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}