<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;

/**
 * Approval state for human-in-the-loop workflows.
 */
class ApprovalState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Approval',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Get prompt
        $prompt = $this->getPrompt($filteredInput, $context);

        // Get options
        $options = $this->definition['Options'] ?? ['approve', 'reject'];

        // In a real implementation, this would:
        // 1. Send notification to approvers
        // 2. Wait for response (with timeout handling)
        // 3. Return the decision

        // For now, we simulate auto-approval
        // A real implementation would integrate with a task queue or callback system
        $approval = $this->simulateApproval($options);

        // Build approval result
        $approvalResult = [
            'approval' => $approval,
            'approver' => 'system',
            'timestamp' => date('c'),
            'prompt' => $prompt,
        ];

        // Apply ResultPath
        $output = $this->applyResultPath($input, $approvalResult);

        // Check for Choices-based routing
        $choices = $this->definition['Choices'] ?? null;
        if ($choices !== null) {
            foreach ($choices as $choice) {
                if ($this->evaluateChoice($choice, $output, $context)) {
                    $nextState = $choice['Next'] ?? null;
                    if ($nextState !== null) {
                        $context->addTraceEntry([
                            'type' => 'state_exit',
                            'stateName' => $this->name,
                            'approval' => $approval,
                            'nextState' => $nextState,
                        ]);

                        return StateResult::next($output, $nextState);
                    }
                }
            }

            // No choice matched, use Default
            $default = $this->definition['Default'] ?? null;
            if ($default !== null) {
                $context->addTraceEntry([
                    'type' => 'state_exit',
                    'stateName' => $this->name,
                    'approval' => $approval,
                    'nextState' => $default,
                ]);

                return StateResult::next($output, $default);
            }
        }

        // Apply OutputPath
        $output = $this->applyOutputPath($output, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'approval' => $approval,
        ]);

        return $this->createResult($output);
    }

    /**
     * Get the prompt text.
     *
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return string
     */
    private function getPrompt(array $input, ExecutionContext $context): string
    {
        $prompt = $this->definition['Prompt'] ?? 'Approval required';

        if (is_array($prompt)) {
            // Structured prompt - extract title and description
            $title = $prompt['Title'] ?? 'Approval';
            $description = $prompt['Description'] ?? '';

            if (isset($prompt['Description.$'])) {
                $description = (string) JsonPath::evaluate(
                    $prompt['Description.$'],
                    $input,
                    $context->toContextObject()
                );
            }

            return "{$title}: {$description}";
        }

        // Check for dynamic prompt
        if (isset($this->definition['Prompt.$'])) {
            $resolved = JsonPath::evaluate(
                $this->definition['Prompt.$'],
                $input,
                $context->toContextObject()
            );
            return (string) $resolved;
        }

        return $prompt;
    }

    /**
     * Simulate an approval decision.
     * In a real implementation, this would wait for human input.
     *
     * @param array<string> $options
     * @return string
     */
    private function simulateApproval(array $options): string
    {
        // Default to first option (usually 'approve')
        return $options[0] ?? 'approve';
    }

    /**
     * Evaluate a choice rule.
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
        $variable = $choice['Variable'] ?? null;
        if ($variable === null) {
            return false;
        }

        $value = JsonPath::evaluate($variable, $input, $context->toContextObject());

        if (isset($choice['StringEquals'])) {
            return $value === $choice['StringEquals'];
        }

        return false;
    }
}
