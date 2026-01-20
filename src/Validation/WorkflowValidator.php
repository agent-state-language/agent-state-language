<?php

declare(strict_types=1);

namespace AgentStateLanguage\Validation;

use AgentStateLanguage\Exceptions\ValidationException;

/**
 * Validates ASL workflow definitions.
 */
class WorkflowValidator
{
    /** @var array<string> */
    private array $errors = [];

    /**
     * Validate a workflow definition.
     *
     * @param array<string, mixed> $definition
     * @return bool True if valid
     * @throws ValidationException If validation fails
     */
    public function validate(array $definition): bool
    {
        $this->errors = [];

        // Check required fields
        $this->validateRequiredFields($definition);

        // Validate StartAt
        $this->validateStartAt($definition);

        // Validate States
        $this->validateStates($definition);

        // Check for unreachable states
        $this->validateReachability($definition);

        if (!empty($this->errors)) {
            throw new ValidationException(
                'Workflow validation failed',
                $this->errors
            );
        }

        return true;
    }

    /**
     * Validate required top-level fields.
     *
     * @param array<string, mixed> $definition
     */
    private function validateRequiredFields(array $definition): void
    {
        if (!isset($definition['StartAt'])) {
            $this->errors[] = 'Missing required field: StartAt';
        }

        if (!isset($definition['States'])) {
            $this->errors[] = 'Missing required field: States';
        }

        if (isset($definition['States']) && !is_array($definition['States'])) {
            $this->errors[] = 'States must be an object';
        }

        if (isset($definition['States']) && empty($definition['States'])) {
            $this->errors[] = 'States cannot be empty';
        }
    }

    /**
     * Validate StartAt references a valid state.
     *
     * @param array<string, mixed> $definition
     */
    private function validateStartAt(array $definition): void
    {
        $startAt = $definition['StartAt'] ?? null;
        $states = $definition['States'] ?? [];

        if ($startAt !== null && !isset($states[$startAt])) {
            $this->errors[] = "StartAt references non-existent state: {$startAt}";
        }
    }

    /**
     * Validate all states.
     *
     * @param array<string, mixed> $definition
     */
    private function validateStates(array $definition): void
    {
        $states = $definition['States'] ?? [];

        foreach ($states as $name => $state) {
            $this->validateState($name, $state, $states);
        }
    }

    /**
     * Validate a single state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateState(string $name, array $state, array $allStates): void
    {
        // Check Type field
        if (!isset($state['Type'])) {
            $this->errors[] = "State '{$name}' is missing required Type field";
            return;
        }

        $type = $state['Type'];

        // Validate by type
        match ($type) {
            'Task' => $this->validateTaskState($name, $state, $allStates),
            'Choice' => $this->validateChoiceState($name, $state, $allStates),
            'Map' => $this->validateMapState($name, $state, $allStates),
            'Parallel' => $this->validateParallelState($name, $state, $allStates),
            'Pass' => $this->validatePassState($name, $state, $allStates),
            'Wait' => $this->validateWaitState($name, $state, $allStates),
            'Succeed', 'Fail' => null, // Terminal states need no Next
            'Approval' => $this->validateApprovalState($name, $state, $allStates),
            'Debate' => $this->validateDebateState($name, $state, $allStates),
            'Checkpoint' => $this->validateTransitionState($name, $state, $allStates),
            default => $this->errors[] = "State '{$name}' has unknown Type: {$type}",
        };
    }

    /**
     * Validate Task state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateTaskState(string $name, array $state, array $allStates): void
    {
        if (!isset($state['Agent'])) {
            $this->errors[] = "Task state '{$name}' is missing required Agent field";
        }

        $this->validateTransitionState($name, $state, $allStates);
    }

    /**
     * Validate Choice state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateChoiceState(string $name, array $state, array $allStates): void
    {
        if (!isset($state['Choices']) || !is_array($state['Choices'])) {
            $this->errors[] = "Choice state '{$name}' is missing required Choices array";
            return;
        }

        if (empty($state['Choices'])) {
            $this->errors[] = "Choice state '{$name}' has empty Choices array";
        }

        foreach ($state['Choices'] as $index => $choice) {
            if (!isset($choice['Next'])) {
                $this->errors[] = "Choice state '{$name}' choice {$index} is missing Next";
            } elseif (!isset($allStates[$choice['Next']])) {
                $this->errors[] = "Choice state '{$name}' choice {$index} references non-existent state: {$choice['Next']}";
            }
        }

        // Validate Default if present
        if (isset($state['Default']) && !isset($allStates[$state['Default']])) {
            $this->errors[] = "Choice state '{$name}' Default references non-existent state: {$state['Default']}";
        }
    }

    /**
     * Validate Map state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateMapState(string $name, array $state, array $allStates): void
    {
        if (!isset($state['ItemsPath'])) {
            $this->errors[] = "Map state '{$name}' is missing required ItemsPath";
        }

        if (!isset($state['Iterator'])) {
            $this->errors[] = "Map state '{$name}' is missing required Iterator";
        } else {
            // Validate iterator as a nested state machine
            $iterator = $state['Iterator'];
            if (!isset($iterator['StartAt'])) {
                $this->errors[] = "Map state '{$name}' Iterator is missing StartAt";
            }
            if (!isset($iterator['States']) || empty($iterator['States'])) {
                $this->errors[] = "Map state '{$name}' Iterator is missing States";
            }
        }

        $this->validateTransitionState($name, $state, $allStates);
    }

    /**
     * Validate Parallel state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateParallelState(string $name, array $state, array $allStates): void
    {
        if (!isset($state['Branches']) || !is_array($state['Branches'])) {
            $this->errors[] = "Parallel state '{$name}' is missing required Branches array";
            return;
        }

        if (empty($state['Branches'])) {
            $this->errors[] = "Parallel state '{$name}' has empty Branches array";
        }

        foreach ($state['Branches'] as $index => $branch) {
            if (!isset($branch['StartAt'])) {
                $this->errors[] = "Parallel state '{$name}' branch {$index} is missing StartAt";
            }
            if (!isset($branch['States']) || empty($branch['States'])) {
                $this->errors[] = "Parallel state '{$name}' branch {$index} is missing States";
            }
        }

        $this->validateTransitionState($name, $state, $allStates);
    }

    /**
     * Validate Pass state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validatePassState(string $name, array $state, array $allStates): void
    {
        $this->validateTransitionState($name, $state, $allStates);
    }

    /**
     * Validate Wait state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateWaitState(string $name, array $state, array $allStates): void
    {
        $hasWaitConfig = isset($state['Seconds'])
            || isset($state['SecondsPath'])
            || isset($state['Timestamp'])
            || isset($state['TimestampPath']);

        if (!$hasWaitConfig) {
            $this->errors[] = "Wait state '{$name}' must have Seconds, SecondsPath, Timestamp, or TimestampPath";
        }

        $this->validateTransitionState($name, $state, $allStates);
    }

    /**
     * Validate Approval state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateApprovalState(string $name, array $state, array $allStates): void
    {
        if (!isset($state['Prompt'])) {
            $this->errors[] = "Approval state '{$name}' is missing required Prompt";
        }

        // Validate Choices if present
        if (isset($state['Choices'])) {
            foreach ($state['Choices'] as $index => $choice) {
                if (isset($choice['Next']) && !isset($allStates[$choice['Next']])) {
                    $this->errors[] = "Approval state '{$name}' choice {$index} references non-existent state: {$choice['Next']}";
                }
            }
        }

        // Validate Default if present
        if (isset($state['Default']) && !isset($allStates[$state['Default']])) {
            $this->errors[] = "Approval state '{$name}' Default references non-existent state: {$state['Default']}";
        }

        // If no Choices, must have Next or End
        if (!isset($state['Choices'])) {
            $this->validateTransitionState($name, $state, $allStates);
        }
    }

    /**
     * Validate Debate state.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateDebateState(string $name, array $state, array $allStates): void
    {
        if (!isset($state['Agents']) || !is_array($state['Agents'])) {
            $this->errors[] = "Debate state '{$name}' is missing required Agents array";
        } elseif (count($state['Agents']) < 2) {
            $this->errors[] = "Debate state '{$name}' requires at least 2 agents";
        }

        $this->validateTransitionState($name, $state, $allStates);
    }

    /**
     * Validate state has valid Next or End.
     *
     * @param string $name
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $allStates
     */
    private function validateTransitionState(string $name, array $state, array $allStates): void
    {
        $hasNext = isset($state['Next']);
        $hasEnd = isset($state['End']) && $state['End'] === true;

        if (!$hasNext && !$hasEnd) {
            $this->errors[] = "State '{$name}' must have either Next or End: true";
        }

        if ($hasNext && $hasEnd) {
            $this->errors[] = "State '{$name}' cannot have both Next and End: true";
        }

        if ($hasNext && !isset($allStates[$state['Next']])) {
            $this->errors[] = "State '{$name}' references non-existent state: {$state['Next']}";
        }
    }

    /**
     * Validate all states are reachable from StartAt.
     *
     * @param array<string, mixed> $definition
     */
    private function validateReachability(array $definition): void
    {
        $startAt = $definition['StartAt'] ?? null;
        $states = $definition['States'] ?? [];

        if ($startAt === null || empty($states)) {
            return;
        }

        $reachable = $this->findReachableStates($startAt, $states);
        $unreachable = array_diff(array_keys($states), $reachable);

        foreach ($unreachable as $stateName) {
            $this->errors[] = "State '{$stateName}' is not reachable from StartAt";
        }
    }

    /**
     * Find all states reachable from a starting state.
     *
     * @param string $startAt
     * @param array<string, array<string, mixed>> $states
     * @return array<string>
     */
    private function findReachableStates(string $startAt, array $states): array
    {
        $reachable = [];
        $queue = [$startAt];

        while (!empty($queue)) {
            $current = array_shift($queue);

            if (in_array($current, $reachable, true)) {
                continue;
            }

            $reachable[] = $current;

            if (!isset($states[$current])) {
                continue;
            }

            $state = $states[$current];

            // Add Next state
            if (isset($state['Next']) && !in_array($state['Next'], $reachable, true)) {
                $queue[] = $state['Next'];
            }

            // Add Default state
            if (isset($state['Default']) && !in_array($state['Default'], $reachable, true)) {
                $queue[] = $state['Default'];
            }

            // Add Choice targets
            if (isset($state['Choices']) && is_array($state['Choices'])) {
                foreach ($state['Choices'] as $choice) {
                    if (isset($choice['Next']) && !in_array($choice['Next'], $reachable, true)) {
                        $queue[] = $choice['Next'];
                    }
                }
            }

            // Add Catch targets
            if (isset($state['Catch']) && is_array($state['Catch'])) {
                foreach ($state['Catch'] as $catcher) {
                    if (isset($catcher['Next']) && !in_array($catcher['Next'], $reachable, true)) {
                        $queue[] = $catcher['Next'];
                    }
                }
            }
        }

        return $reachable;
    }
}
