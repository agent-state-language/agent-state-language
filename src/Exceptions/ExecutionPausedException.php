<?php

declare(strict_types=1);

namespace AgentStateLanguage\Exceptions;

/**
 * Exception thrown when workflow execution is paused.
 *
 * This is used for human-in-the-loop scenarios where the workflow
 * needs to wait for external input before continuing.
 */
class ExecutionPausedException extends ASLException
{
    private string $stateName;
    private array $checkpointData;
    private ?array $pendingInput;

    /**
     * @param string $stateName The state where execution paused
     * @param array<string, mixed> $checkpointData Data needed to resume execution
     * @param array<string, mixed>|null $pendingInput Details about what input is needed
     */
    public function __construct(
        string $stateName,
        array $checkpointData = [],
        ?array $pendingInput = null,
        string $message = 'Execution paused waiting for input'
    ) {
        parent::__construct($message, 'States.ExecutionPaused');
        $this->stateName = $stateName;
        $this->checkpointData = $checkpointData;
        $this->pendingInput = $pendingInput;
    }

    /**
     * Get the state name where execution paused.
     */
    public function getStateName(): string
    {
        return $this->stateName;
    }

    /**
     * Get the checkpoint data for resuming execution.
     *
     * @return array<string, mixed>
     */
    public function getCheckpointData(): array
    {
        return $this->checkpointData;
    }

    /**
     * Get details about what input is needed.
     *
     * @return array<string, mixed>|null
     */
    public function getPendingInput(): ?array
    {
        return $this->pendingInput;
    }
}
