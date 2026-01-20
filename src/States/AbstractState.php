<?php

declare(strict_types=1);

namespace AgentStateLanguage\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Engine\JsonPath;

/**
 * Base class for state implementations.
 */
abstract class AbstractState implements StateInterface
{
    protected string $name;
    /** @var array<string, mixed> */
    protected array $definition;

    /**
     * @param string $name State name
     * @param array<string, mixed> $definition State definition from ASL
     */
    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->definition = $definition;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->definition['Type'] ?? 'Unknown';
    }

    public function getNext(): ?string
    {
        return $this->definition['Next'] ?? null;
    }

    public function isEnd(): bool
    {
        return $this->definition['End'] ?? false;
    }

    /**
     * Get the comment/description.
     */
    public function getComment(): ?string
    {
        return $this->definition['Comment'] ?? null;
    }

    /**
     * Apply InputPath to filter input.
     *
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return array<string, mixed>
     */
    protected function applyInputPath(array $input, ExecutionContext $context): array
    {
        $inputPath = $this->definition['InputPath'] ?? null;

        if ($inputPath === null) {
            return $input;
        }

        $result = JsonPath::evaluate($inputPath, $input, $context->toContextObject());

        return is_array($result) ? $result : ['value' => $result];
    }

    /**
     * Apply ResultPath to merge result into input.
     *
     * @param array<string, mixed> $input Original input
     * @param mixed $result Task result
     * @return array<string, mixed>
     */
    protected function applyResultPath(array $input, mixed $result): array
    {
        $resultPath = $this->definition['ResultPath'] ?? '$';

        if ($resultPath === null) {
            // Discard result, return original input
            return $input;
        }

        return JsonPath::set($resultPath, $input, $result);
    }

    /**
     * Apply OutputPath to filter output.
     *
     * @param array<string, mixed> $output
     * @param ExecutionContext $context
     * @return array<string, mixed>
     */
    protected function applyOutputPath(array $output, ExecutionContext $context): array
    {
        $outputPath = $this->definition['OutputPath'] ?? null;

        if ($outputPath === null) {
            return $output;
        }

        $result = JsonPath::evaluate($outputPath, $output, $context->toContextObject());

        return is_array($result) ? $result : ['value' => $result];
    }

    /**
     * Resolve Parameters with JSONPath interpolation.
     *
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return array<string, mixed>
     */
    protected function resolveParameters(array $input, ExecutionContext $context): array
    {
        $parameters = $this->definition['Parameters'] ?? null;

        if ($parameters === null) {
            return $input;
        }

        return JsonPath::resolveParameters(
            $parameters,
            $input,
            $context->toContextObject()
        );
    }

    /**
     * Apply ResultSelector to transform result.
     *
     * @param mixed $result
     * @param array<string, mixed> $input
     * @param ExecutionContext $context
     * @return mixed
     */
    protected function applyResultSelector(
        mixed $result,
        array $input,
        ExecutionContext $context
    ): mixed {
        $resultSelector = $this->definition['ResultSelector'] ?? null;

        if ($resultSelector === null) {
            return $result;
        }

        // Create context with result available
        $combined = is_array($result) 
            ? array_merge($input, $result)
            : array_merge($input, ['result' => $result]);

        return JsonPath::resolveParameters(
            $resultSelector,
            $combined,
            $context->toContextObject()
        );
    }

    /**
     * Create the result for transitioning to next state.
     *
     * @param array<string, mixed> $output
     */
    protected function createResult(
        array $output,
        int $tokensUsed = 0,
        float $cost = 0.0
    ): StateResult {
        if ($this->isEnd()) {
            return StateResult::end($output, $tokensUsed, $cost);
        }

        $next = $this->getNext();
        if ($next === null) {
            return StateResult::end($output, $tokensUsed, $cost);
        }

        return StateResult::next($output, $next, $tokensUsed, $cost);
    }
}
