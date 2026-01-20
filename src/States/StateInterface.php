<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;

/**
 * Interface for all state implementations.
 */
interface StateInterface
{
    /**
     * Get the state name.
     */
    public function getName(): string;

    /**
     * Get the state type.
     */
    public function getType(): string;

    /**
     * Execute the state.
     *
     * @param array<string, mixed> $input Current state input
     * @param ExecutionContext $context Execution context
     * @return StateResult Result of execution
     */
    public function execute(array $input, ExecutionContext $context): StateResult;

    /**
     * Get the next state name.
     *
     * @return string|null Next state name, or null if terminal
     */
    public function getNext(): ?string;

    /**
     * Check if this is a terminal state.
     */
    public function isEnd(): bool;
}
