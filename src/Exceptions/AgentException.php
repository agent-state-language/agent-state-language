<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

/**
 * Exception thrown by agents during execution.
 */
class AgentException extends ASLException
{
    private string $agentName;

    public function __construct(
        string $message,
        string $agentName = '',
        string $errorCode = 'Agent.Error',
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->agentName = $agentName;
        parent::__construct($message, $errorCode, $code, $previous);
    }

    public function getAgentName(): string
    {
        return $this->agentName;
    }
}
