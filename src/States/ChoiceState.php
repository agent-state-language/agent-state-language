<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;
use AgentStateLanguage\Exceptions\StateException;

/**
 * Choice state for conditional branching.
 */
class ChoiceState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Choice',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Evaluate choices
        $choices = $this->definition['Choices'] ?? [];
        $default = $this->definition['Default'] ?? null;

        foreach ($choices as $choice) {
            if ($this->evaluateChoice($choice, $filteredInput, $context)) {
                $nextState = $choice['Next'] ?? null;
                if ($nextState === null) {
                    throw new StateException(
                        'Choice rule missing Next field',
                        $this->name,
                        'States.TaskFailed'
                    );
                }

                $context->addTraceEntry([
                    'type' => 'state_exit',
                    'stateName' => $this->name,
                    'choiceMatched' => true,
                    'nextState' => $nextState,
                ]);

                // Apply OutputPath
                $output = $this->applyOutputPath($filteredInput, $context);

                return StateResult::next($output, $nextState);
            }
        }

        // No choice matched, use default
        if ($default === null) {
            throw new StateException(
                'No choice matched and no default specified',
                $this->name,
                'States.NoChoiceMatched'
            );
        }

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'choiceMatched' => false,
            'nextState' => $default,
        ]);

        // Apply OutputPath
        $output = $this->applyOutputPath($filteredInput, $context);

        return StateResult::next($output, $default);
    }

    /**
     * Evaluate a single choice rule.
     *
     * @param array<string, mixed> $choice
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return bool
     */
    private function evaluateChoice(
        array $choice,
        array $input,
        ExecutionContext $context
    ): bool {
        // Handle compound operators
        if (isset($choice['And'])) {
            foreach ($choice['And'] as $subChoice) {
                if (!$this->evaluateChoice($subChoice, $input, $context)) {
                    return false;
                }
            }
            return true;
        }

        if (isset($choice['Or'])) {
            foreach ($choice['Or'] as $subChoice) {
                if ($this->evaluateChoice($subChoice, $input, $context)) {
                    return true;
                }
            }
            return false;
        }

        if (isset($choice['Not'])) {
            return !$this->evaluateChoice($choice['Not'], $input, $context);
        }

        // Handle Variable comparisons
        $variable = $choice['Variable'] ?? null;
        if ($variable === null) {
            return false;
        }

        $value = JsonPath::evaluate($variable, $input, $context->toContextObject());

        // String comparisons
        if (isset($choice['StringEquals'])) {
            return $value === $choice['StringEquals'];
        }
        if (isset($choice['StringEqualsPath'])) {
            $compareTo = JsonPath::evaluate($choice['StringEqualsPath'], $input, $context->toContextObject());
            return $value === $compareTo;
        }
        if (isset($choice['StringGreaterThan'])) {
            return is_string($value) && $value > $choice['StringGreaterThan'];
        }
        if (isset($choice['StringGreaterThanEquals'])) {
            return is_string($value) && $value >= $choice['StringGreaterThanEquals'];
        }
        if (isset($choice['StringLessThan'])) {
            return is_string($value) && $value < $choice['StringLessThan'];
        }
        if (isset($choice['StringLessThanEquals'])) {
            return is_string($value) && $value <= $choice['StringLessThanEquals'];
        }
        if (isset($choice['StringMatches'])) {
            return $this->matchGlob($value, $choice['StringMatches']);
        }

        // Numeric comparisons
        if (isset($choice['NumericEquals'])) {
            return is_numeric($value) && $value == $choice['NumericEquals'];
        }
        if (isset($choice['NumericEqualsPath'])) {
            $compareTo = JsonPath::evaluate($choice['NumericEqualsPath'], $input, $context->toContextObject());
            return is_numeric($value) && $value == $compareTo;
        }
        if (isset($choice['NumericGreaterThan'])) {
            return is_numeric($value) && $value > $choice['NumericGreaterThan'];
        }
        if (isset($choice['NumericGreaterThanEquals'])) {
            return is_numeric($value) && $value >= $choice['NumericGreaterThanEquals'];
        }
        if (isset($choice['NumericLessThan'])) {
            return is_numeric($value) && $value < $choice['NumericLessThan'];
        }
        if (isset($choice['NumericLessThanEquals'])) {
            return is_numeric($value) && $value <= $choice['NumericLessThanEquals'];
        }

        // Boolean comparison
        if (isset($choice['BooleanEquals'])) {
            return $value === $choice['BooleanEquals'];
        }
        if (isset($choice['BooleanEqualsPath'])) {
            $compareTo = JsonPath::evaluate($choice['BooleanEqualsPath'], $input, $context->toContextObject());
            return $value === $compareTo;
        }

        // Type checks
        if (isset($choice['IsNull'])) {
            return ($value === null) === $choice['IsNull'];
        }
        if (isset($choice['IsPresent'])) {
            return ($value !== null) === $choice['IsPresent'];
        }
        if (isset($choice['IsNumeric'])) {
            return is_numeric($value) === $choice['IsNumeric'];
        }
        if (isset($choice['IsString'])) {
            return is_string($value) === $choice['IsString'];
        }
        if (isset($choice['IsBoolean'])) {
            return is_bool($value) === $choice['IsBoolean'];
        }

        return false;
    }

    /**
     * Match a glob pattern.
     *
     * @param mixed $value
     * @param string $pattern
     * @return bool
     */
    private function matchGlob(mixed $value, string $pattern): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Convert glob to regex
        $regex = '/^' . str_replace(
            ['\\*', '\\?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/';

        return preg_match($regex, $value) === 1;
    }

    public function getNext(): ?string
    {
        // Choice state doesn't have a single Next
        return null;
    }

    public function isEnd(): bool
    {
        return false;
    }
}
