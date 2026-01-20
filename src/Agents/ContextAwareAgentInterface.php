<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents;

/**
 * Interface for agents that need context management.
 */
interface ContextAwareAgentInterface extends AgentInterface
{
    /**
     * Set the context for the agent.
     *
     * @param array<string, mixed> $context
     */
    public function setContext(array $context): void;

    /**
     * Set the maximum context tokens.
     */
    public function setMaxContextTokens(int $tokens): void;
}
