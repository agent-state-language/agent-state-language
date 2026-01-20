<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents;

/**
 * Interface that all agents must implement.
 */
interface AgentInterface
{
    /**
     * Execute the agent with the given parameters.
     *
     * @param array<string, mixed> $parameters Parameters from the workflow
     * @return array<string, mixed> Result to store in the workflow state
     */
    public function execute(array $parameters): array;

    /**
     * Get the agent's name for registration.
     */
    public function getName(): string;
}
