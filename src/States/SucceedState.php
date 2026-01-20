<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;

/**
 * Succeed state that terminates execution successfully.
 */
class SucceedState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Succeed',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Apply OutputPath
        $output = $this->applyOutputPath($filteredInput, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'success' => true,
            'terminal' => true,
        ]);

        return StateResult::end($output);
    }

    public function isEnd(): bool
    {
        return true;
    }

    public function getNext(): ?string
    {
        return null;
    }
}
