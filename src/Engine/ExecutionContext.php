<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

use AgentStateLanguage\Handlers\ApprovalHandlerInterface;

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

    // Approval handler for human-in-the-loop
    private ?ApprovalHandlerInterface $approvalHandler = null;

    // State lifecycle callbacks
    /** @var callable|null */
    private $onStateEnterCallback = null;
    /** @var callable|null */
    private $onStateExitCallback = null;

    // Pause/resume support
    private bool $isPaused = false;
    /** @var array<string, mixed> */
    private array $checkpointData = [];
    /** @var array<string, mixed>|null */
    private ?array $resumeData = null;

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

        // Call the onStateEnter callback if set
        if ($this->onStateEnterCallback !== null) {
            ($this->onStateEnterCallback)($stateName, $this->checkpointData);
        }
    }

    /**
     * Notify that a state has exited.
     *
     * @param string $stateName The name of the state that exited
     * @param mixed $output The output from the state
     */
    public function exitState(string $stateName, mixed $output = null): void
    {
        $duration = $this->getStateDuration();

        // Call the onStateExit callback if set
        if ($this->onStateExitCallback !== null) {
            ($this->onStateExitCallback)($stateName, $output, $duration);
        }
    }

    /**
     * Get the duration of the current state in seconds.
     */
    public function getStateDuration(): float
    {
        $entered = strtotime($this->stateEnteredTime);
        return $entered ? (microtime(true) - $entered) : 0.0;
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

    // =====================
    // Approval Handler
    // =====================

    /**
     * Set the approval handler for human-in-the-loop workflows.
     */
    public function setApprovalHandler(ApprovalHandlerInterface $handler): void
    {
        $this->approvalHandler = $handler;
    }

    /**
     * Get the approval handler.
     */
    public function getApprovalHandler(): ?ApprovalHandlerInterface
    {
        return $this->approvalHandler;
    }

    /**
     * Check if an approval handler is configured.
     */
    public function hasApprovalHandler(): bool
    {
        return $this->approvalHandler !== null;
    }

    // =====================
    // State Lifecycle Callbacks
    // =====================

    /**
     * Set callback for when a state is entered.
     *
     * @param callable(string, array<string, mixed>): void $callback
     */
    public function onStateEnter(callable $callback): void
    {
        $this->onStateEnterCallback = $callback;
    }

    /**
     * Set callback for when a state is exited.
     *
     * @param callable(string, mixed, float): void $callback
     */
    public function onStateExit(callable $callback): void
    {
        $this->onStateExitCallback = $callback;
    }

    // =====================
    // Pause/Resume Support
    // =====================

    /**
     * Mark the execution as paused.
     */
    public function markPaused(): void
    {
        $this->isPaused = true;
    }

    /**
     * Check if execution is paused.
     */
    public function isPaused(): bool
    {
        return $this->isPaused;
    }

    /**
     * Set checkpoint data for resume.
     *
     * @param array<string, mixed> $data
     */
    public function setCheckpointData(array $data): void
    {
        $this->checkpointData = $data;
    }

    /**
     * Get checkpoint data.
     *
     * @return array<string, mixed>
     */
    public function getCheckpointData(): array
    {
        return $this->checkpointData;
    }

    /**
     * Set resume data (used when resuming from a paused state).
     *
     * @param array<string, mixed>|null $data
     */
    public function setResumeData(?array $data): void
    {
        $this->resumeData = $data;
    }

    /**
     * Get resume data.
     *
     * @return array<string, mixed>|null
     */
    public function getResumeData(): ?array
    {
        return $this->resumeData;
    }

    /**
     * Check if this is a resume execution.
     */
    public function isResuming(): bool
    {
        return $this->resumeData !== null;
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
