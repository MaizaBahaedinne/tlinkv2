<?php

namespace App\Support;

use RuntimeException;

class ApprovalDecisionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}
