<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Exceptions\StateException;

/**
 * Parallel state for concurrent branch execution.
 */
class ParallelState extends AbstractState
{
    private StateFactory $factory;

    /**
     * @param string $name State name
     * @param array<string, mixed> $definition State definition
     * @param StateFactory $factory State factory for creating branch states
     */
    public function __construct(
        string $name,
        array $definition,
        StateFactory $factory
    ) {
        parent::__construct($name, $definition);
        $this->factory = $factory;
    }

    public function execute(array $input, ExecutionContext $context): StateResult
    {
        $context->addTraceEntry([
            'type' => 'state_enter',
            'stateName' => $this->name,
            'stateType' => 'Parallel',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Get branches
        $branches = $this->definition['Branches'] ?? [];
        if (empty($branches)) {
            throw new StateException(
                'Parallel state missing required Branches field',
                $this->name,
                'States.TaskFailed'
            );
        }

        // Execute each branch (sequentially for now - could be parallelized)
        $results = [];
        $totalTokens = 0;
        $totalCost = 0.0;

        foreach ($branches as $index => $branchDef) {
            $branchResult = $this->executeBranch(
                $branchDef,
                $filteredInput,
                $context,
                $index
            );

            $results[] = $branchResult['output'];
            $totalTokens += $branchResult['tokens'];
            $totalCost += $branchResult['cost'];
        }

        // Apply ResultSelector if present
        $result = $this->applyResultSelector($results, $filteredInput, $context);

        // Apply ResultPath
        $output = $this->applyResultPath($input, $result);

        // Apply OutputPath
        $output = $this->applyOutputPath($output, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'branchesExecuted' => count($branches),
            'tokensUsed' => $totalTokens,
        ]);

        return $this->createResult($output, $totalTokens, $totalCost);
    }

    /**
     * Execute a single branch.
     *
     * @param array<string, mixed> $branchDef
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @param int $branchIndex
     * @return array{output: array<string, mixed>, tokens: int, cost: float}
     */
    private function executeBranch(
        array $branchDef,
        array $input,
        ExecutionContext $context,
        int $branchIndex
    ): array {
        $states = $this->factory->createAll($branchDef['States'] ?? []);
        $startAt = $branchDef['StartAt'] ?? null;

        if ($startAt === null || !isset($states[$startAt])) {
            throw new StateException(
                "Branch {$branchIndex} missing valid StartAt",
                $this->name,
                'States.TaskFailed'
            );
        }

        $currentState = $startAt;
        $currentInput = $input;
        $totalTokens = 0;
        $totalCost = 0.0;

        while ($currentState !== null) {
            if (!isset($states[$currentState])) {
                throw new StateException(
                    "Branch state '{$currentState}' not found",
                    $this->name,
                    'States.TaskFailed'
                );
            }

            $state = $states[$currentState];
            $context->enterState($currentState);

            $result = $state->execute($currentInput, $context);

            $totalTokens += $result->getTokensUsed();
            $totalCost += $result->getCost();

            if ($result->hasError()) {
                throw new StateException(
                    $result->getErrorCause() ?? 'Branch state failed',
                    $currentState,
                    $result->getError() ?? 'States.TaskFailed'
                );
            }

            $currentInput = $result->getOutput();

            if ($result->isTerminal()) {
                break;
            }

            $currentState = $result->getNextState();
        }

        return [
            'output' => $currentInput,
            'tokens' => $totalTokens,
            'cost' => $totalCost,
        ];
    }
}
