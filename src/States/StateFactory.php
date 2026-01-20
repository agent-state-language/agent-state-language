<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Exceptions\ASLException;

/**
 * Factory for creating state instances.
 */
class StateFactory
{
    private AgentRegistry $registry;

    public function __construct(AgentRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Create a state from its definition.
     *
     * @param string $name State name
     * @param array<string, mixed> $definition State definition
     * @return StateInterface
     * @throws ASLException If state type is unknown
     */
    public function create(string $name, array $definition): StateInterface
    {
        $type = $definition['Type'] ?? null;

        if ($type === null) {
            throw new ASLException(
                "State '{$name}' is missing required Type field",
                'States.ValidationError'
            );
        }

        return match ($type) {
            'Task' => new TaskState($name, $definition, $this->registry),
            'Choice' => new ChoiceState($name, $definition),
            'Pass' => new PassState($name, $definition),
            'Wait' => new WaitState($name, $definition),
            'Succeed' => new SucceedState($name, $definition),
            'Fail' => new FailState($name, $definition),
            'Map' => new MapState($name, $definition, $this),
            'Parallel' => new ParallelState($name, $definition, $this),
            'Approval' => new ApprovalState($name, $definition),
            'Debate' => new DebateState($name, $definition, $this->registry),
            'Checkpoint' => new CheckpointState($name, $definition),
            default => throw new ASLException(
                "Unknown state type: '{$type}'",
                'States.ValidationError'
            ),
        };
    }

    /**
     * Create all states from a workflow definition.
     *
     * @param array<string, array<string, mixed>> $states
     * @return array<string, StateInterface>
     */
    public function createAll(array $states): array
    {
        $result = [];

        foreach ($states as $name => $definition) {
            $result[$name] = $this->create($name, $definition);
        }

        return $result;
    }
}
