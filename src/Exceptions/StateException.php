<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

/**
 * Exception thrown during state execution.
 */
class StateException extends ASLException
{
    private string $stateName;

    public function __construct(
        string $message,
        string $stateName,
        string $errorCode = 'States.TaskFailed',
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->stateName = $stateName;
        parent::__construct($message, $errorCode, $code, $previous);
    }

    public function getStateName(): string
    {
        return $this->stateName;
    }
}
