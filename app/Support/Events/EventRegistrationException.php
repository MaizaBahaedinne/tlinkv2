<?php

namespace App\Support\Events;

use RuntimeException;

class EventRegistrationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
