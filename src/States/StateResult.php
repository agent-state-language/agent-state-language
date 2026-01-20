<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

/**
 * Result of a state execution.
 */
class StateResult
{
    /** @var array<string, mixed> */
    private array $output;
    private ?string $nextState;
    private bool $isTerminal;
    private ?string $error;
    private ?string $errorCause;
    private int $tokensUsed;
    private float $cost;

    /**
     * @param array<string, mixed> $output
     */
    public function __construct(
        array $output,
        ?string $nextState = null,
        bool $isTerminal = false,
        ?string $error = null,
        ?string $errorCause = null,
        int $tokensUsed = 0,
        float $cost = 0.0
    ) {
        $this->output = $output;
        $this->nextState = $nextState;
        $this->isTerminal = $isTerminal;
        $this->error = $error;
        $this->errorCause = $errorCause;
        $this->tokensUsed = $tokensUsed;
        $this->cost = $cost;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    public function getNextState(): ?string
    {
        return $this->nextState;
    }

    public function isTerminal(): bool
    {
        return $this->isTerminal;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorCause(): ?string
    {
        return $this->errorCause;
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
     * Create a result that transitions to next state.
     *
     * @param array<string, mixed> $output
     */
    public static function next(
        array $output,
        string $nextState,
        int $tokensUsed = 0,
        float $cost = 0.0
    ): self {
        return new self($output, $nextState, false, null, null, $tokensUsed, $cost);
    }

    /**
     * Create a terminal result.
     *
     * @param array<string, mixed> $output
     */
    public static function end(
        array $output,
        int $tokensUsed = 0,
        float $cost = 0.0
    ): self {
        return new self($output, null, true, null, null, $tokensUsed, $cost);
    }

    /**
     * Create an error result.
     *
     * @param array<string, mixed> $output
     */
    public static function error(
        string $error,
        string $cause,
        array $output = []
    ): self {
        return new self($output, null, true, $error, $cause);
    }
}
