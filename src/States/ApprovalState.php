<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;
use AgentStateLanguage\Exceptions\ExecutionPausedException;

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

        // Get editable fields if any
        $editable = $this->definition['Editable'] ?? null;

        // Get timeout
        $timeout = $this->definition['Timeout'] ?? '24h';

        // Check if we're resuming with user input
        $resumeData = $context->getResumeData();
        if ($resumeData !== null && isset($resumeData['approval'])) {
            // We have the approval decision from resume
            $approval = $resumeData['approval'];
            $approver = $resumeData['approver'] ?? 'user';
            $comment = $resumeData['comment'] ?? null;
            $editedContent = $resumeData['edited_content'] ?? null;
        } elseif ($context->hasApprovalHandler()) {
            // Use the approval handler
            $handler = $context->getApprovalHandler();
            assert($handler !== null); // Already checked via hasApprovalHandler()

            $request = [
                'prompt' => $prompt,
                'options' => $options,
                'state' => $this->name,
                'timeout' => $timeout,
                'input' => $filteredInput,
            ];

            if ($editable !== null) {
                $request['editable'] = $editable['Fields'] ?? [];
            }

            $response = $handler->requestApproval($request);

            if ($response === null) {
                // Handler returned null, meaning we need to pause and wait
                throw new ExecutionPausedException(
                    stateName: $this->name,
                    checkpointData: $input,
                    pendingInput: [
                        'type' => 'approval',
                        'prompt' => $prompt,
                        'options' => $options,
                        'editable' => $editable,
                        'timeout' => $timeout,
                    ]
                );
            }

            // Handler returned a decision
            $approval = $response['approval'];
            $approver = $response['approver'] ?? 'handler';
            $comment = $response['comment'] ?? null;
            $editedContent = $response['edited_content'] ?? null;
        } else {
            // No handler configured, simulate auto-approval
            $approval = $this->simulateApproval($options);
            $approver = 'system';
            $comment = null;
            $editedContent = null;
        }

        // Build approval result
        $approvalResult = [
            'approval' => $approval,
            'approver' => $approver,
            'timestamp' => date('c'),
            'prompt' => $prompt,
        ];

        if ($comment !== null) {
            $approvalResult['comment'] = $comment;
        }

        // Handle edited content
        if ($editedContent !== null && $editable !== null) {
            $resultPath = $editable['ResultPath'] ?? '$.editedContent';
            $input = JsonPath::set($resultPath, $input, $editedContent);
        }

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
     * Used when no approval handler is configured.
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
