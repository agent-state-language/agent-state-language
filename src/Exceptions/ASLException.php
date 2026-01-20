<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

use Exception;

/**
 * Base exception for Agent State Language errors.
 */
class ASLException extends Exception
{
    protected string $errorCode;

    public function __construct(
        string $message,
        string $errorCode = 'States.Error',
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
