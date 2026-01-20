<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Exceptions\ASLException;
use AgentStateLanguage\Exceptions\ExecutionPausedException;
use AgentStateLanguage\Exceptions\ValidationException;
use AgentStateLanguage\Handlers\ApprovalHandlerInterface;
use AgentStateLanguage\States\StateFactory;
use AgentStateLanguage\States\StateInterface;
use AgentStateLanguage\Validation\WorkflowValidator;

/**
 * Main engine for executing ASL workflows.
 */
class WorkflowEngine
{
    /** @var array<string, mixed> */
    private array $definition;
    private AgentRegistry $registry;
    private StateFactory $factory;
    /** @var array<string, StateInterface> */
    private array $states = [];
    private ?WorkflowValidator $validator = null;

    // Approval handler for human-in-the-loop
    private ?ApprovalHandlerInterface $approvalHandler = null;

    // State lifecycle callbacks
    /** @var callable|null */
    private $onStateEnterCallback = null;
    /** @var callable|null */
    private $onStateExitCallback = null;

    // Pause state tracking
    private bool $isPaused = false;
    private ?string $pausedAtState = null;
    /** @var array<string, mixed> */
    private array $pausedCheckpointData = [];

    /**
     * @param array<string, mixed> $definition Workflow definition
     * @param AgentRegistry $registry Agent registry
     */
    public function __construct(array $definition, AgentRegistry $registry)
    {
        $this->definition = $definition;
        $this->registry = $registry;
        $this->factory = new StateFactory($registry);
        $this->initializeStates();
    }

    /**
     * Create engine from an array definition.
     *
     * @param array<string, mixed> $definition Workflow definition
     * @param AgentRegistry $registry Agent registry
     * @return self
     */
    public static function fromArray(array $definition, AgentRegistry $registry): self
    {
        return new self($definition, $registry);
    }

    /**
     * Create engine from a JSON file.
     *
     * @param string $path Path to ASL JSON file
     * @param AgentRegistry $registry Agent registry
     * @return self
     * @throws ASLException If file cannot be read or parsed
     */
    public static function fromFile(string $path, AgentRegistry $registry): self
    {
        if (!file_exists($path)) {
            throw new ASLException(
                "Workflow file not found: {$path}",
                'States.FileNotFound'
            );
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new ASLException(
                "Unable to read workflow file: {$path}",
                'States.FileReadError'
            );
        }

        $definition = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ASLException(
                "Invalid JSON in workflow file: " . json_last_error_msg(),
                'States.ParseError'
            );
        }

        return new self($definition, $registry);
    }

    /**
     * Create engine from a JSON string.
     *
     * @param string $json JSON workflow definition
     * @param AgentRegistry $registry Agent registry
     * @return self
     */
    public static function fromJson(string $json, AgentRegistry $registry): self
    {
        $definition = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ASLException(
                "Invalid JSON: " . json_last_error_msg(),
                'States.ParseError'
            );
        }

        return new self($definition, $registry);
    }

    /**
     * Register an agent.
     *
     * @param string $name Agent name
     * @param AgentInterface $agent Agent instance
     * @return self
     */
    public function registerAgent(string $name, AgentInterface $agent): self
    {
        $this->registry->register($name, $agent);
        return $this;
    }

    /**
     * Set the approval handler for human-in-the-loop workflows.
     *
     * @param ApprovalHandlerInterface $handler The approval handler
     * @return self
     */
    public function setApprovalHandler(ApprovalHandlerInterface $handler): self
    {
        $this->approvalHandler = $handler;
        return $this;
    }

    /**
     * Set callback for when a state is entered.
     *
     * @param callable(string, array<string, mixed>): void $callback
     * @return self
     */
    public function onStateEnter(callable $callback): self
    {
        $this->onStateEnterCallback = $callback;
        return $this;
    }

    /**
     * Set callback for when a state is exited.
     *
     * @param callable(string, mixed, float): void $callback
     * @return self
     */
    public function onStateExit(callable $callback): self
    {
        $this->onStateExitCallback = $callback;
        return $this;
    }

    /**
     * Check if the workflow is paused waiting for input.
     */
    public function isPaused(): bool
    {
        return $this->isPaused;
    }

    /**
     * Get the state where execution paused.
     */
    public function getPausedAtState(): ?string
    {
        return $this->pausedAtState;
    }

    /**
     * Get the checkpoint data from when execution paused.
     *
     * @return array<string, mixed>
     */
    public function getPausedCheckpointData(): array
    {
        return $this->pausedCheckpointData;
    }

    /**
     * Validate the workflow definition.
     *
     * @return bool True if valid
     * @throws ValidationException If validation fails
     */
    public function validate(): bool
    {
        if ($this->validator === null) {
            $this->validator = new WorkflowValidator();
        }

        return $this->validator->validate($this->definition);
    }

    /**
     * Run the workflow with the given input.
     *
     * @param array<string, mixed> $input Initial input data
     * @param string|null $startFromState Optional state to start/resume from
     * @param array<string, mixed>|null $resumeData Optional data for resuming (e.g., approval response)
     * @return WorkflowResult Execution result
     */
    public function run(
        array $input = [],
        ?string $startFromState = null,
        ?array $resumeData = null
    ): WorkflowResult {
        $startTime = microtime(true);

        // Reset pause state
        $this->isPaused = false;
        $this->pausedAtState = null;
        $this->pausedCheckpointData = [];

        // Create execution context
        $workflowName = $this->definition['Comment'] ?? 'Unnamed Workflow';
        $context = new ExecutionContext($workflowName);

        // Configure context with approval handler
        if ($this->approvalHandler !== null) {
            $context->setApprovalHandler($this->approvalHandler);
        }

        // Configure context with callbacks
        if ($this->onStateEnterCallback !== null) {
            $context->onStateEnter($this->onStateEnterCallback);
        }
        if ($this->onStateExitCallback !== null) {
            $context->onStateExit($this->onStateExitCallback);
        }

        // Set resume data if provided
        if ($resumeData !== null) {
            $context->setResumeData($resumeData);
        }

        $context->addTraceEntry([
            'type' => 'workflow_start',
            'input' => $input,
            'resumeFrom' => $startFromState,
        ]);

        try {
            // Get starting state
            $startAt = $startFromState ?? $this->definition['StartAt'] ?? null;
            if ($startAt === null) {
                throw new ASLException(
                    'Workflow missing required StartAt field',
                    'States.ValidationError'
                );
            }

            // Execute states
            $currentState = $startAt;
            $currentInput = $input;

            while ($currentState !== null) {
                if (!isset($this->states[$currentState])) {
                    throw new ASLException(
                        "State '{$currentState}' not found in workflow",
                        'States.StateNotFound'
                    );
                }

                $state = $this->states[$currentState];

                // Store checkpoint data before entering state
                $context->setCheckpointData($currentInput);
                $context->enterState($currentState);

                // Execute state with retry handling
                $result = $this->executeWithRetry($state, $currentInput, $context);

                // Notify state exit
                $context->exitState($currentState, $result->getOutput());

                // Handle errors with catch
                if ($result->hasError()) {
                    $catchResult = $this->handleCatch(
                        $state,
                        $result,
                        $currentInput,
                        $context
                    );

                    if ($catchResult !== null) {
                        $result = $catchResult;
                    } else {
                        // No catch handler, fail workflow
                        $duration = microtime(true) - $startTime;
                        return WorkflowResult::failure(
                            $result->getError() ?? 'States.TaskFailed',
                            $result->getErrorCause() ?? 'State execution failed',
                            $context->getTrace(),
                            $duration
                        );
                    }
                }

                $currentInput = $result->getOutput();

                if ($result->isTerminal()) {
                    break;
                }

                $currentState = $result->getNextState();
            }

            $duration = microtime(true) - $startTime;

            $context->addTraceEntry([
                'type' => 'workflow_complete',
                'success' => true,
                'duration' => $duration,
            ]);

            return WorkflowResult::success(
                $currentInput,
                $context->getTrace(),
                $duration,
                $context->getTotalTokens(),
                $context->getTotalCost()
            );
        } catch (ExecutionPausedException $e) {
            // Workflow paused for human input
            $duration = microtime(true) - $startTime;

            $this->isPaused = true;
            $this->pausedAtState = $e->getStateName();
            $this->pausedCheckpointData = $e->getCheckpointData();

            $context->markPaused();
            $context->addTraceEntry([
                'type' => 'workflow_paused',
                'state' => $e->getStateName(),
                'pendingInput' => $e->getPendingInput(),
            ]);

            return WorkflowResult::paused(
                $e->getStateName(),
                $e->getCheckpointData(),
                $e->getPendingInput(),
                $context->getTrace(),
                $duration
            );
        } catch (ASLException $e) {
            $duration = microtime(true) - $startTime;

            $context->addTraceEntry([
                'type' => 'workflow_error',
                'error' => $e->getErrorCode(),
                'cause' => $e->getMessage(),
            ]);

            return WorkflowResult::failure(
                $e->getErrorCode(),
                $e->getMessage(),
                $context->getTrace(),
                $duration
            );
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            $context->addTraceEntry([
                'type' => 'workflow_error',
                'error' => 'States.Error',
                'cause' => $e->getMessage(),
            ]);

            return WorkflowResult::failure(
                'States.Error',
                $e->getMessage(),
                $context->getTrace(),
                $duration
            );
        }
    }

    /**
     * Initialize all states from the definition.
     */
    private function initializeStates(): void
    {
        $states = $this->definition['States'] ?? [];
        $this->states = $this->factory->createAll($states);
    }

    /**
     * Execute a state with retry handling.
     *
     * @param StateInterface $state
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return \AgentStateLanguage\States\StateResult
     */
    private function executeWithRetry(
        StateInterface $state,
        array $input,
        ExecutionContext $context
    ): \AgentStateLanguage\States\StateResult {
        $result = $state->execute($input, $context);

        if (!$result->hasError()) {
            return $result;
        }

        // Get retry configuration from state definition
        $stateDef = $this->definition['States'][$state->getName()] ?? [];
        $retriers = $stateDef['Retry'] ?? [];

        foreach ($retriers as $retrier) {
            $errorEquals = $retrier['ErrorEquals'] ?? [];

            // Check if this retrier matches the error
            if (!$this->errorMatches($result->getError(), $errorEquals)) {
                continue;
            }

            $maxAttempts = $retrier['MaxAttempts'] ?? 3;
            $intervalSeconds = $retrier['IntervalSeconds'] ?? 1;
            $backoffRate = $retrier['BackoffRate'] ?? 2.0;
            $maxDelay = $retrier['MaxDelaySeconds'] ?? 300;

            // Retry loop
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $context->incrementRetry();

                // Calculate delay
                $delay = min(
                    $intervalSeconds * pow($backoffRate, $attempt - 1),
                    $maxDelay
                );

                // Add jitter if configured
                $jitter = $retrier['JitterStrategy'] ?? 'NONE';
                if ($jitter === 'FULL') {
                    $delay = mt_rand(0, (int) ($delay * 1000)) / 1000;
                }

                $context->addTraceEntry([
                    'type' => 'retry',
                    'attempt' => $attempt,
                    'delay' => $delay,
                    'error' => $result->getError(),
                ]);

                sleep((int) $delay);

                // Retry execution
                $result = $state->execute($input, $context);

                if (!$result->hasError()) {
                    return $result;
                }
            }

            // All retries exhausted
            break;
        }

        return $result;
    }

    /**
     * Handle error with catch configuration.
     *
     * @param StateInterface $state
     * @param \AgentStateLanguage\States\StateResult $result
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return \AgentStateLanguage\States\StateResult|null
     */
    private function handleCatch(
        StateInterface $state,
        \AgentStateLanguage\States\StateResult $result,
        array $input,
        ExecutionContext $context
    ): ?\AgentStateLanguage\States\StateResult {
        $stateDef = $this->definition['States'][$state->getName()] ?? [];
        $catchers = $stateDef['Catch'] ?? [];

        foreach ($catchers as $catcher) {
            $errorEquals = $catcher['ErrorEquals'] ?? [];

            if (!$this->errorMatches($result->getError(), $errorEquals)) {
                continue;
            }

            // Build error object
            $errorInfo = [
                'Error' => $result->getError(),
                'Cause' => $result->getErrorCause(),
            ];

            // Apply ResultPath
            $resultPath = $catcher['ResultPath'] ?? '$.error';
            $output = JsonPath::set($resultPath, $input, $errorInfo);

            // Transition to catch state
            $nextState = $catcher['Next'] ?? null;
            if ($nextState === null) {
                continue;
            }

            $context->addTraceEntry([
                'type' => 'catch',
                'error' => $result->getError(),
                'nextState' => $nextState,
            ]);

            return \AgentStateLanguage\States\StateResult::next($output, $nextState);
        }

        return null;
    }

    /**
     * Check if an error matches a list of error patterns.
     *
     * @param string|null $error
     * @param array<string> $patterns
     * @return bool
     */
    private function errorMatches(?string $error, array $patterns): bool
    {
        if ($error === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === 'States.ALL') {
                return true;
            }

            if ($pattern === $error) {
                return true;
            }

            // Support prefix matching (e.g., "Agent." matches "Agent.Error")
            if (str_ends_with($pattern, '.') && str_starts_with($error, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the workflow definition.
     *
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return $this->definition;
    }

    /**
     * Get the list of state names.
     *
     * @return array<string>
     */
    public function getStateNames(): array
    {
        return array_keys($this->states);
    }
}
