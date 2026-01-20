<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;

/**
 * Fail state that terminates execution with failure.
 */
class FailState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $error = $this->definition['Error'] ?? 'States.Failed';
        $cause = $this->definition['Cause'] ?? 'Workflow failed';

        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Fail',
        ]);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'success' => false,
            'terminal' => true,
            'error' => $error,
            'cause' => $cause,
        ]);

        return StateResult::error($error, $cause, $input);
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
