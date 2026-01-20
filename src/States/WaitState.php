<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;
use AgentStateLanguage\Exceptions\StateException;

/**
 * Wait state that pauses execution.
 */
class WaitState extends AbstractState
{
    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Wait',
        ]);

        // Determine wait duration
        $seconds = $this->getWaitSeconds($input, $context);

        if ($seconds > 0) {
            // In a real implementation, this might use async waiting
            sleep($seconds);
        }

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Apply OutputPath
        $output = $this->applyOutputPath($filteredInput, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'waitedSeconds' => $seconds,
        ]);

        return $this->createResult($output);
    }

    /**
     * Get the number of seconds to wait.
     *
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return int
     */
    private function getWaitSeconds(array $input, ExecutionContext $context): int
    {
        // Static seconds
        if (isset($this->definition['Seconds'])) {
            return (int) $this->definition['Seconds'];
        }

        // Dynamic seconds from path
        if (isset($this->definition['SecondsPath'])) {
            $value = JsonPath::evaluate(
                $this->definition['SecondsPath'],
                $input,
                $context->toContextObject()
            );
            return (int) $value;
        }

        // Static timestamp
        if (isset($this->definition['Timestamp'])) {
            return $this->secondsUntilTimestamp($this->definition['Timestamp']);
        }

        // Dynamic timestamp from path
        if (isset($this->definition['TimestampPath'])) {
            $timestamp = JsonPath::evaluate(
                $this->definition['TimestampPath'],
                $input,
                $context->toContextObject()
            );
            return $this->secondsUntilTimestamp((string) $timestamp);
        }

        throw new StateException(
            'Wait state must have Seconds, SecondsPath, Timestamp, or TimestampPath',
            $this->name,
            'States.TaskFailed'
        );
    }

    /**
     * Calculate seconds until a timestamp.
     *
     * @param string $timestamp ISO 8601 timestamp
     * @return int
     */
    private function secondsUntilTimestamp(string $timestamp): int
    {
        $targetTime = strtotime($timestamp);
        if ($targetTime === false) {
            return 0;
        }

        $now = time();
        $diff = $targetTime - $now;

        return max(0, $diff);
    }
}
