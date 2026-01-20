<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for ASL tests.
 * 
 * Provides common utilities and helpers for testing workflows.
 */
abstract class TestCase extends BaseTestCase
{
    protected AgentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AgentRegistry();
    }

    /**
     * Create a mock agent that returns a fixed result.
     */
    protected function createMockAgent(array $result): AgentInterface
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturn($result);
        return $agent;
    }

    /**
     * Create a mock agent that uses a callback.
     */
    protected function createCallbackAgent(callable $callback): AgentInterface
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willReturnCallback($callback);
        return $agent;
    }

    /**
     * Create a mock agent that throws an exception.
     */
    protected function createFailingAgent(\Throwable $exception): AgentInterface
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('run')->willThrowException($exception);
        return $agent;
    }

    /**
     * Create a minimal valid workflow.
     */
    protected function createMinimalWorkflow(string $stateName = 'Start', array $stateDefinition = null): array
    {
        return [
            'Version' => '1.0',
            'StartAt' => $stateName,
            'States' => [
                $stateName => $stateDefinition ?? ['Type' => 'Succeed']
            ]
        ];
    }

    /**
     * Create a workflow from a state chain.
     */
    protected function createChainWorkflow(array $states): array
    {
        $workflow = [
            'Version' => '1.0',
            'StartAt' => array_key_first($states),
            'States' => []
        ];

        $stateNames = array_keys($states);
        $lastIndex = count($stateNames) - 1;

        foreach ($states as $index => $state) {
            $name = $stateNames[$index];
            $definition = $state;

            // Add Next or End
            if ($index === $lastIndex) {
                $definition['End'] = true;
            } else {
                $definition['Next'] = $stateNames[$index + 1];
            }

            $workflow['States'][$name] = $definition;
        }

        return $workflow;
    }

    /**
     * Assert that a workflow result has specific output.
     */
    protected function assertWorkflowOutput(array $expected, $result): void
    {
        $this->assertTrue($result->isSuccess(), 'Workflow should succeed');
        $this->assertEquals($expected, $result->getOutput());
    }

    /**
     * Assert that a workflow result failed with a specific error.
     */
    protected function assertWorkflowFailed(string $expectedError, $result): void
    {
        $this->assertFalse($result->isSuccess(), 'Workflow should fail');
        $this->assertEquals($expectedError, $result->getError());
    }

    /**
     * Assert that a workflow visited specific states in order.
     */
    protected function assertStateOrder(array $expectedStates, $result): void
    {
        $history = $result->getStateHistory();
        $actualStates = array_column($history, 'state');
        
        $this->assertEquals($expectedStates, $actualStates);
    }

    /**
     * Get a test workflow definition from the examples directory.
     */
    protected function loadExampleWorkflow(string $name): array
    {
        $path = __DIR__ . "/../examples/{$name}/workflow.asl.json";
        
        if (!file_exists($path)) {
            $this->fail("Example workflow not found: {$name}");
        }

        $content = file_get_contents($path);
        $workflow = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Invalid JSON in example workflow: {$name}");
        }

        return $workflow;
    }
}
