<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;
use AgentStateLanguage\Exceptions\StateException;

/**
 * Map state for iterating over arrays.
 */
class MapState extends AbstractState
{
    private StateFactory $factory;

    /**
     * @param string $name State name
     * @param array<string, mixed> $definition State definition
     * @param StateFactory $factory State factory for creating iterator states
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
            'stateType' => 'Map',
        ]);

        // Apply InputPath
        $filteredInput = $this->applyInputPath($input, $context);

        // Get items to iterate
        $itemsPath = $this->definition['ItemsPath'] ?? '$.items';
        $items = JsonPath::evaluate($itemsPath, $filteredInput, $context->toContextObject());

        if (!is_array($items)) {
            throw new StateException(
                "ItemsPath '{$itemsPath}' did not resolve to an array",
                $this->name,
                'States.TaskFailed'
            );
        }

        // Get iterator definition
        $iteratorDef = $this->definition['Iterator'] ?? null;
        if ($iteratorDef === null) {
            throw new StateException(
                'Map state missing required Iterator field',
                $this->name,
                'States.TaskFailed'
            );
        }

        // Create iterator states
        $iteratorStates = $this->factory->createAll($iteratorDef['States'] ?? []);
        $startAt = $iteratorDef['StartAt'] ?? null;
        if ($startAt === null || !isset($iteratorStates[$startAt])) {
            throw new StateException(
                'Map Iterator missing valid StartAt',
                $this->name,
                'States.TaskFailed'
            );
        }

        // Get max concurrency (for now, we process sequentially)
        // $maxConcurrency = $this->definition['MaxConcurrency'] ?? 1;

        // Process each item
        $results = [];
        $totalTokens = 0;
        $totalCost = 0.0;

        foreach ($items as $index => $item) {
            $itemIndex = (int) $index;
            $context->setMapContext($itemIndex, $item);

            // Prepare item input
            $itemInput = $this->prepareItemInput($filteredInput, $item, $itemIndex, $context);

            // Execute iterator workflow
            $itemResult = $this->executeIterator(
                $iteratorStates,
                $startAt,
                $itemInput,
                $context
            );

            $results[] = $itemResult['output'];
            $totalTokens += $itemResult['tokens'];
            $totalCost += $itemResult['cost'];
        }

        $context->clearMapContext();

        // Apply ResultSelector if present
        $result = $this->applyResultSelector($results, $filteredInput, $context);

        // Apply ResultPath
        $output = $this->applyResultPath($input, $result);

        // Apply OutputPath
        $output = $this->applyOutputPath($output, $context);

        $context->addTraceEntry([
            'type' => 'state_exit',
            'stateName' => $this->name,
            'itemsProcessed' => count($items),
            'tokensUsed' => $totalTokens,
        ]);

        return $this->createResult($output, $totalTokens, $totalCost);
    }

    /**
     * Prepare input for a single item.
     *
     * @param array<string, mixed> $parentInput
     * @param mixed $item
     * @param int $index
     * @param ExecutionContext $context
     * @return array<string, mixed>
     */
    private function prepareItemInput(
        array $parentInput,
        mixed $item,
        int $index,
        ExecutionContext $context
    ): array {
        $itemSelector = $this->definition['ItemSelector'] ?? null;

        if ($itemSelector !== null) {
            // Use ItemSelector to create item input
            return JsonPath::resolveParameters(
                $itemSelector,
                $parentInput,
                $context->toContextObject()
            );
        }

        // Default: use item as input
        if (is_array($item)) {
            return $item;
        }

        return ['value' => $item, 'index' => $index];
    }

    /**
     * Execute the iterator workflow for one item.
     *
     * @param array<string, StateInterface> $states
     * @param string $startAt
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return array{output: array<string, mixed>, tokens: int, cost: float}
     */
    private function executeIterator(
        array $states,
        string $startAt,
        array $input,
        ExecutionContext $context
    ): array {
        $currentState = $startAt;
        $currentInput = $input;
        $totalTokens = 0;
        $totalCost = 0.0;

        while ($currentState !== null) {
            if (!isset($states[$currentState])) {
                throw new StateException(
                    "Iterator state '{$currentState}' not found",
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
                // For now, propagate error
                throw new StateException(
                    $result->getErrorCause() ?? 'Iterator state failed',
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
