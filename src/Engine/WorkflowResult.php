<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

/**
 * Result of a workflow execution.
 */
class WorkflowResult
{
    private bool $success;
    private bool $paused;
    /** @var array<string, mixed> */
    private array $output;
    private ?string $error;
    private ?string $errorCause;
    /** @var array<array<string, mixed>> */
    private array $trace;
    private float $duration;
    private int $tokensUsed;
    private float $cost;

    // Pause-related data
    private ?string $pausedAtState;
    /** @var array<string, mixed> */
    private array $checkpointData;
    /** @var array<string, mixed>|null */
    private ?array $pendingInput;

    /**
     * @param array<string, mixed> $output
     * @param array<array<string, mixed>> $trace
     * @param array<string, mixed> $checkpointData
     * @param array<string, mixed>|null $pendingInput
     */
    public function __construct(
        bool $success,
        array $output = [],
        ?string $error = null,
        ?string $errorCause = null,
        array $trace = [],
        float $duration = 0.0,
        int $tokensUsed = 0,
        float $cost = 0.0,
        bool $paused = false,
        ?string $pausedAtState = null,
        array $checkpointData = [],
        ?array $pendingInput = null
    ) {
        $this->success = $success;
        $this->output = $output;
        $this->error = $error;
        $this->errorCause = $errorCause;
        $this->trace = $trace;
        $this->duration = $duration;
        $this->tokensUsed = $tokensUsed;
        $this->cost = $cost;
        $this->paused = $paused;
        $this->pausedAtState = $pausedAtState;
        $this->checkpointData = $checkpointData;
        $this->pendingInput = $pendingInput;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorCause(): ?string
    {
        return $this->errorCause;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getTokensUsed(): int
    {
        return $this->tokensUsed;
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    /**
     * Get the state where execution paused.
     */
    public function getPausedAtState(): ?string
    {
        return $this->pausedAtState;
    }

    /**
     * Get the checkpoint data for resuming.
     *
     * @return array<string, mixed>
     */
    public function getCheckpointData(): array
    {
        return $this->checkpointData;
    }

    /**
     * Get pending input specification.
     *
     * @return array<string, mixed>|null
     */
    public function getPendingInput(): ?array
    {
        return $this->pendingInput;
    }

    /**
     * Create a successful result.
     *
     * @param array<string, mixed> $output
     * @param array<array<string, mixed>> $trace
     */
    public static function success(
        array $output,
        array $trace = [],
        float $duration = 0.0,
        int $tokensUsed = 0,
        float $cost = 0.0
    ): self {
        return new self(true, $output, null, null, $trace, $duration, $tokensUsed, $cost);
    }

    /**
     * Create a failed result.
     *
     * @param array<array<string, mixed>> $trace
     */
    public static function failure(
        string $error,
        string $cause,
        array $trace = [],
        float $duration = 0.0
    ): self {
        return new self(false, [], $error, $cause, $trace, $duration);
    }

    /**
     * Create a paused result (waiting for input).
     *
     * @param string $pausedAtState The state where execution paused
     * @param array<string, mixed> $checkpointData Data to restore when resuming
     * @param array<string, mixed>|null $pendingInput Specification of what input is needed
     * @param array<array<string, mixed>> $trace Execution trace
     * @param float $duration Duration so far
     */
    public static function paused(
        string $pausedAtState,
        array $checkpointData,
        ?array $pendingInput = null,
        array $trace = [],
        float $duration = 0.0
    ): self {
        return new self(
            success: false,
            output: [],
            error: null,
            errorCause: null,
            trace: $trace,
            duration: $duration,
            tokensUsed: 0,
            cost: 0.0,
            paused: true,
            pausedAtState: $pausedAtState,
            checkpointData: $checkpointData,
            pendingInput: $pendingInput
        );
    }
}
