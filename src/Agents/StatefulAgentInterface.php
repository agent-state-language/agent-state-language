<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents;

/**
 * Interface for agents that maintain state between calls.
 */
interface StatefulAgentInterface extends AgentInterface
{
    /**
     * Get the current agent state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array;

    /**
     * Set the agent state.
     *
     * @param array<string, mixed> $state
     */
    public function setState(array $state): void;

    /**
     * Reset the agent state.
     */
    public function resetState(): void;
}
