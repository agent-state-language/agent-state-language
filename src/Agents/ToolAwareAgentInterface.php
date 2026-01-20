<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents;

/**
 * Interface for agents that can use tools.
 */
interface ToolAwareAgentInterface extends AgentInterface
{
    /**
     * Set the list of allowed tools.
     *
     * @param array<string> $tools
     */
    public function setAllowedTools(array $tools): void;

    /**
     * Set the tool executor callback.
     *
     * @param callable(string, array<string, mixed>): mixed $executor
     */
    public function setToolExecutor(callable $executor): void;
}
