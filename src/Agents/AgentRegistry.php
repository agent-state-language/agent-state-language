<?php

declare(strict_types=1);

namespace AgentStateLanguage\Agents;

use AgentStateLanguage\Exceptions\ASLException;

/**
 * Registry for managing agent instances.
 */
class AgentRegistry
{
    /** @var array<string, AgentInterface> */
    private array $agents = [];

    /**
     * Register an agent with the given name.
     */
    public function register(string $name, AgentInterface $agent): self
    {
        $this->agents[$name] = $agent;
        return $this;
    }

    /**
     * Get an agent by name.
     *
     * @throws ASLException If agent is not found
     */
    public function get(string $name): AgentInterface
    {
        if (!isset($this->agents[$name])) {
            throw new ASLException(
                "Agent '{$name}' is not registered",
                'States.AgentNotFound'
            );
        }

        return $this->agents[$name];
    }

    /**
     * Check if an agent is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->agents[$name]);
    }

    /**
     * Get all registered agent names.
     *
     * @return array<string>
     */
    public function getRegisteredNames(): array
    {
        return array_keys($this->agents);
    }

    /**
     * Remove an agent from the registry.
     */
    public function remove(string $name): self
    {
        unset($this->agents[$name]);
        return $this;
    }

    /**
     * Clear all registered agents.
     */
    public function clear(): self
    {
        $this->agents = [];
        return $this;
    }

    /**
     * Create a registry from a factory.
     *
     * @param AgentFactoryInterface $factory
     * @param array<string, array<string, mixed>> $agentConfigs Map of agent names to configs
     * @return self
     */
    public static function fromFactory(
        AgentFactoryInterface $factory,
        array $agentConfigs
    ): self {
        $registry = new self();

        foreach ($agentConfigs as $name => $config) {
            $agent = $factory->create($name, $config);
            $registry->register($name, $agent);
        }

        return $registry;
    }
}
