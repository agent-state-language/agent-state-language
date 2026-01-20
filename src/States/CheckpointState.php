<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;

/**
 * Checkpoint state for creating resumable save points.
 */
class CheckpointState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Checkpoint',
        ]);

        // Get checkpoint configuration
        $checkpointName = $this->definition['Name'] ?? $this->name;
        $storage = $this->definition['Storage'] ?? 'default';
        $ttl = $this->definition['TTL'] ?? '7d';

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Create checkpoint data
        $checkpointData = [
            'name' => $checkpointName,
            'executionId' => $context->getExecutionId(),
            'timestamp' => date('c'),
            'state' => $filteredInput,
            'context' => [
                'currentState' => $this->name,
                'trace' => $context->getTrace(),
                'totalTokens' => $context->getTotalTokens(),
                'totalCost' => $context->getTotalCost(),
            ],
            'ttl' => $ttl,
        ];

        // In a real implementation, this would save to the configured storage
        // For now, we just add the checkpoint info to the output
        $checkpointResult = [
            'checkpoint' => [
                'name' => $checkpointName,
                'id' => $context->getExecutionId() . '-' . $checkpointName,
                'createdAt' => date('c'),
            ],
        ];

        // Apply ResultPath
        $output = $this->applyResultPath($input, $checkpointResult);

        // Apply OutputPath
        $output = $this->applyOutputPath($output, $context);

        $context->addTraceEntry([
            'type' => 'checkpoint_created',
            'checkpointName' => $checkpointName,
            'storage' => $storage,
        ]);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
        ]);

        return $this->createResult($output);
    }
}
