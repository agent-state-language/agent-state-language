<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

/**
 * Context available during workflow execution.
 */
class ExecutionContext
{
    private string $executionId;
    private string $workflowName;
    private string $startTime;
    private string $currentState;
    private string $stateEnteredTime;
    private int $retryCount = 0;
    private ?int $mapItemIndex = null;
    /** @var mixed */
    private mixed $mapItemValue = null;
    /** @var array<array<string, mixed>> */
    private array $trace = [];
    private int $totalTokens = 0;
    private float $totalCost = 0.0;

    public function __construct(string $workflowName = '')
    {
        $this->executionId = $this->generateId();
        $this->workflowName = $workflowName;
        $this->startTime = date('c');
        $this->currentState = '';
        $this->stateEnteredTime = date('c');
    }

    private function generateId(): string
    {
        return sprintf(
            '%s-%s',
            date('Ymd-His'),
            bin2hex(random_bytes(8))
        );
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getWorkflowName(): string
    {
        return $this->workflowName;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    public function getStateEnteredTime(): string
    {
        return $this->stateEnteredTime;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getMapItemIndex(): ?int
    {
        return $this->mapItemIndex;
    }

    /**
     * @return mixed
     */
    public function getMapItemValue(): mixed
    {
        return $this->mapItemValue;
    }

    public function enterState(string $stateName): void
    {
        $this->currentState = $stateName;
        $this->stateEnteredTime = date('c');
        $this->retryCount = 0;
    }

    public function incrementRetry(): void
    {
        $this->retryCount++;
    }

    /**
     * @param mixed $value
     */
    public function setMapContext(int $index, mixed $value): void
    {
        $this->mapItemIndex = $index;
        $this->mapItemValue = $value;
    }

    public function clearMapContext(): void
    {
        $this->mapItemIndex = null;
        $this->mapItemValue = null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function addTraceEntry(array $entry): void
    {
        $this->trace[] = array_merge($entry, [
            'timestamp' => date('c'),
            'state' => $this->currentState,
        ]);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    public function addTokens(int $tokens): void
    {
        $this->totalTokens += $tokens;
    }

    public function addCost(float $cost): void
    {
        $this->totalCost += $cost;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    /**
     * Get the context object for JSONPath $$ references.
     *
     * @return array<string, mixed>
     */
    public function toContextObject(): array
    {
        $context = [
            'Execution' => [
                'Id' => $this->executionId,
                'Name' => $this->workflowName,
                'StartTime' => $this->startTime,
            ],
            'State' => [
                'Name' => $this->currentState,
                'EnteredTime' => $this->stateEnteredTime,
                'RetryCount' => $this->retryCount,
            ],
        ];

        if ($this->mapItemIndex !== null) {
            $context['Map'] = [
                'Item' => [
                    'Index' => $this->mapItemIndex,
                    'Value' => $this->mapItemValue,
                ],
            ];
        }

        return $context;
    }
}
