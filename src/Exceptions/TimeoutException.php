<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

/**
 * Exception thrown when a state or workflow times out.
 */
class TimeoutException extends ASLException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 'States.Timeout', $code, $previous);
    }
}
