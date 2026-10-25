<?php

namespace App\Support\Access;

use RuntimeException;

// Thrown by the access services when an action is refused. The reason code is stable and safe to log.
class AccessDenied extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Access denied: {$reasonCode}");
    }
}
