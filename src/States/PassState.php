<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;

/**
 * Pass state that passes input to output, optionally transforming data.
 */
class PassState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Pass',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Get result - either from Parameters, Result, or pass through input
        $result = $filteredInput;

        // If Parameters is set, use it to create result
        if (isset($this->definition['Parameters'])) {
            $result = $this->resolveParameters($filteredInput, $context);
        }

        // If Result is set, use it as static result
        if (array_key_exists('Result', $this->definition)) {
            $result = $this->definition['Result'];
        }

        // Apply ResultPath
        $output = $this->applyResultPath($input, $result);

        // Apply OutputPath
        $output = $this->applyOutputPath($output, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'success' => true,
        ]);

        return $this->createResult($output);
    }
}
