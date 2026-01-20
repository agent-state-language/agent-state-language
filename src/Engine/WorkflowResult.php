<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

/**
 * Result of a workflow execution.
 */
class WorkflowResult
{
    private bool $success;
    /** @var array<string, mixed> */
    private array $output;
    private ?string $error;
    private ?string $errorCause;
    /** @var array<array<string, mixed>> */
    private array $trace;
    private float $duration;
    private int $tokensUsed;
    private float $cost;

    /**
     * @param array<string, mixed> $output
     * @param array<array<string, mixed>> $trace
     */
    public function __construct(
        bool $success,
        array $output = [],
        ?string $error = null,
        ?string $errorCause = null,
        array $trace = [],
        float $duration = 0.0,
        int $tokensUsed = 0,
        float $cost = 0.0
    ) {
        $this->success = $success;
        $this->output = $output;
        $this->error = $error;
        $this->errorCause = $errorCause;
        $this->trace = $trace;
        $this->duration = $duration;
        $this->tokensUsed = $tokensUsed;
        $this->cost = $cost;
    }

    public function isSuccess(): bool
    {
        return $this->success;
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
}
