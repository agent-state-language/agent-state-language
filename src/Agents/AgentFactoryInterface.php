<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents;

/**
 * Interface for agent factories.
 */
interface AgentFactoryInterface
{
    /**
     * Create an agent with the given name and configuration.
     *
     * @param string $name Agent name
     * @param array<string, mixed> $config Agent configuration
     * @return AgentInterface
     */
    public function create(string $name, array $config = []): AgentInterface;
}
