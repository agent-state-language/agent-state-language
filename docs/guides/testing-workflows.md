# Testing Workflows

This guide covers strategies for testing ASL workflows, from unit tests for individual states to full integration tests.

## Overview

Testing ASL workflows involves:

1. **Unit Tests** - Test individual states and agents in isolation
2. **Integration Tests** - Test complete workflows with mock agents
3. **Validation Tests** - Verify workflow structure and configuration
4. **End-to-End Tests** - Test with real agents in controlled environments

## Quick Start

Here's a minimal test example:

```php
<?php

use PHPUnit\Framework\TestCase;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

class QuickWorkflowTest extends TestCase
{
    public function testBasicWorkflow(): void
    {
        $workflow = [
            'StartAt' => 'Greet',
            'States' => [
                'Greet' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'message' => 'Hello, World!'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, new AgentRegistry());
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello, World!', $result->getOutput()['message']);
    }
}
```

## Mock Agents

### Basic Mock Agent

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;

class MockAgent implements AgentInterface
{
    private array $responses;
    private int $callCount = 0;
    private array $receivedParameters = [];

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function execute(array $parameters): array
    {
        $this->receivedParameters[] = $parameters;
        return $this->responses[$this->callCount++] ?? end($this->responses);
    }

    public function getName(): string
    {
        return 'MockAgent';
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
    
    public function getReceivedParameters(): array
    {
        return $this->receivedParameters;
    }
}
```

### Configurable Mock Agent

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Exceptions\WorkflowException;

class ConfigurableMockAgent implements AgentInterface
{
    private string $name;
    private \Closure|array $responseGenerator;
    private int $callCount = 0;
    private int $failUntilAttempt = 0;
    private string $failureError = 'States.TaskFailed';

    public function __construct(
        string $name,
        \Closure|array $responseGenerator
    ) {
        $this->name = $name;
        $this->responseGenerator = $responseGenerator;
    }
    
    public function failUntil(int $attempt, string $error = 'States.TaskFailed'): self
    {
        $this->failUntilAttempt = $attempt;
        $this->failureError = $error;
        return $this;
    }

    public function execute(array $parameters): array
    {
        $this->callCount++;
        
        // Simulate failures for retry testing
        if ($this->callCount < $this->failUntilAttempt) {
            throw new WorkflowException(
                $this->failureError,
                "Simulated failure on attempt {$this->callCount}"
            );
        }
        
        if ($this->responseGenerator instanceof \Closure) {
            return ($this->responseGenerator)($parameters, $this->callCount);
        }
        
        return $this->responseGenerator;
    }

    public function getName(): string
    {
        return $this->name;
    }
    
    public function getCallCount(): int
    {
        return $this->callCount;
    }
}
```

## Complete Test File Example

Here's a complete test file demonstrating various testing patterns:

```php
<?php

namespace Tests\Unit\Workflows;

use PHPUnit\Framework\TestCase;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Exceptions\WorkflowException;
use AgentStateLanguage\Exceptions\ValidationException;

class DocumentProcessingWorkflowTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AgentRegistry();
    }

    // =========================================================================
    // Unit Tests
    // =========================================================================

    public function testExtractorAgentReceivesCorrectParameters(): void
    {
        $receivedParams = null;
        
        $extractor = new ConfigurableMockAgent('Extractor', function($params) use (&$receivedParams) {
            $receivedParams = $params;
            return ['extracted' => ['title' => 'Test Doc']];
        });
        
        $this->registry->register('Extractor', $extractor);
        
        $workflow = $this->buildSimpleWorkflow('Extractor');
        $engine = new WorkflowEngine($workflow, $this->registry);
        
        $engine->run(['document' => 'Sample content', 'format' => 'pdf']);
        
        $this->assertEquals('Sample content', $receivedParams['document']);
        $this->assertEquals('pdf', $receivedParams['format']);
    }

    // =========================================================================
    // Choice State Tests
    // =========================================================================

    public function testChoiceRoutesToHighScorePath(): void
    {
        $workflow = $this->buildChoiceWorkflow();
        $engine = new WorkflowEngine($workflow, $this->registry);

        $result = $engine->run(['score' => 90]);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('high', $result->getOutput()['category']);
    }

    public function testChoiceRoutesToLowScorePath(): void
    {
        $workflow = $this->buildChoiceWorkflow();
        $engine = new WorkflowEngine($workflow, $this->registry);

        $result = $engine->run(['score' => 50]);
        
        $this->assertEquals('low', $result->getOutput()['category']);
    }

    public function testChoiceUsesDefaultPath(): void
    {
        $workflow = $this->buildChoiceWorkflow();
        $engine = new WorkflowEngine($workflow, $this->registry);

        $result = $engine->run(['score' => 70]); // Between thresholds
        
        $this->assertEquals('medium', $result->getOutput()['category']);
    }

    // =========================================================================
    // Error Handling Tests
    // =========================================================================

    public function testRetrySucceedsAfterTransientFailure(): void
    {
        $flaky = new ConfigurableMockAgent('FlakyAgent', ['success' => true]);
        $flaky->failUntil(3, 'States.Timeout'); // Fail first 2 attempts
        
        $this->registry->register('FlakyAgent', $flaky);

        $workflow = [
            'StartAt' => 'Flaky',
            'States' => [
                'Flaky' => [
                    'Type' => 'Task',
                    'Agent' => 'FlakyAgent',
                    'Retry' => [[
                        'ErrorEquals' => ['States.Timeout'],
                        'MaxAttempts' => 5,
                        'IntervalSeconds' => 0 // No delay in tests
                    ]],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(3, $flaky->getCallCount());
    }

    public function testCatchHandlerReceivesError(): void
    {
        $failing = new ConfigurableMockAgent('FailingAgent', function() {
            throw new WorkflowException('CustomError', 'Something broke');
        });
        
        $this->registry->register('FailingAgent', $failing);

        $workflow = [
            'StartAt' => 'MightFail',
            'States' => [
                'MightFail' => [
                    'Type' => 'Task',
                    'Agent' => 'FailingAgent',
                    'Catch' => [[
                        'ErrorEquals' => ['CustomError'],
                        'ResultPath' => '$.error',
                        'Next' => 'HandleError'
                    ]],
                    'End' => true
                ],
                'HandleError' => [
                    'Type' => 'Pass',
                    'Parameters' => [
                        'handled' => true,
                        'errorType.$' => '$.error.error'
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getOutput()['handled']);
        $this->assertEquals('CustomError', $result->getOutput()['errorType']);
    }

    // =========================================================================
    // Parallel State Tests
    // =========================================================================

    public function testParallelExecutesAllBranches(): void
    {
        $this->registry->register('BranchA', new ConfigurableMockAgent('BranchA', ['a' => 1]));
        $this->registry->register('BranchB', new ConfigurableMockAgent('BranchB', ['b' => 2]));
        $this->registry->register('BranchC', new ConfigurableMockAgent('BranchC', ['c' => 3]));

        $workflow = [
            'StartAt' => 'Parallel',
            'States' => [
                'Parallel' => [
                    'Type' => 'Parallel',
                    'Branches' => [
                        ['StartAt' => 'A', 'States' => ['A' => ['Type' => 'Task', 'Agent' => 'BranchA', 'End' => true]]],
                        ['StartAt' => 'B', 'States' => ['B' => ['Type' => 'Task', 'Agent' => 'BranchB', 'End' => true]]],
                        ['StartAt' => 'C', 'States' => ['C' => ['Type' => 'Task', 'Agent' => 'BranchC', 'End' => true]]]
                    ],
                    'End' => true
                ]
            ]
        ];

        $engine = new WorkflowEngine($workflow, $this->registry);
        $result = $engine->run([]);

        $this->assertTrue($result->isSuccess());
        $output = $result->getOutput();
        $this->assertCount(3, $output);
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    public function testValidationFailsForMissingStartState(): void
    {
        $invalidWorkflow = [
            'StartAt' => 'NonExistent',
            'States' => [
                'OnlyState' => ['Type' => 'Pass', 'End' => true]
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('StartAt');
        
        $engine = new WorkflowEngine($invalidWorkflow, $this->registry);
        $engine->validate();
    }

    public function testValidationFailsForUnreachableState(): void
    {
        $invalidWorkflow = [
            'StartAt' => 'First',
            'States' => [
                'First' => ['Type' => 'Pass', 'End' => true],
                'Unreachable' => ['Type' => 'Pass', 'End' => true]
            ]
        ];

        $engine = new WorkflowEngine($invalidWorkflow, $this->registry);
        $errors = $engine->validate();
        
        // Should have warning about unreachable state
        $this->assertNotEmpty($errors);
    }

    // =========================================================================
    // Integration Test
    // =========================================================================

    public function testCompleteDocumentProcessingWorkflow(): void
    {
        // Set up all required agents
        $this->registry->register('Extractor', new ConfigurableMockAgent('Extractor', 
            fn($p) => ['fields' => ['title' => 'Test', 'author' => 'John']]
        ));
        $this->registry->register('Validator', new ConfigurableMockAgent('Validator',
            fn($p) => ['valid' => true, 'errors' => []]
        ));
        $this->registry->register('Transformer', new ConfigurableMockAgent('Transformer',
            fn($p) => ['transformed' => ['Title' => strtoupper($p['data']['title'] ?? '')]]
        ));

        $engine = WorkflowEngine::fromFile(
            __DIR__ . '/fixtures/document-processing.asl.json',
            $this->registry
        );
        
        $result = $engine->run([
            'document' => 'Sample document content',
            'outputFormat' => 'json'
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('transformed', $result->getOutput());
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function buildSimpleWorkflow(string $agentName): array
    {
        return [
            'StartAt' => 'Process',
            'States' => [
                'Process' => [
                    'Type' => 'Task',
                    'Agent' => $agentName,
                    'End' => true
                ]
            ]
        ];
    }

    private function buildChoiceWorkflow(): array
    {
        return [
            'StartAt' => 'Route',
            'States' => [
                'Route' => [
                    'Type' => 'Choice',
                    'Choices' => [
                        ['Variable' => '$.score', 'NumericGreaterThan' => 80, 'Next' => 'High'],
                        ['Variable' => '$.score', 'NumericLessThan' => 60, 'Next' => 'Low']
                    ],
                    'Default' => 'Medium'
                ],
                'High' => ['Type' => 'Pass', 'Result' => ['category' => 'high'], 'End' => true],
                'Medium' => ['Type' => 'Pass', 'Result' => ['category' => 'medium'], 'End' => true],
                'Low' => ['Type' => 'Pass', 'Result' => ['category' => 'low'], 'End' => true]
            ]
        ];
    }
}
```

## Test Fixtures

Create reusable test data and helpers:

### WorkflowTestCase Base Class

```php
<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

abstract class WorkflowTestCase extends TestCase
{
    protected AgentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AgentRegistry();
    }

    protected function createMockAgent(string $name, array|callable $response): void
    {
        $this->registry->register($name, new ConfigurableMockAgent($name, $response));
    }

    protected function runWorkflow(array $workflow, array $input = []): mixed
    {
        $engine = new WorkflowEngine($workflow, $this->registry);
        return $engine->run($input);
    }

    protected function runWorkflowFromFile(string $path, array $input = []): mixed
    {
        $engine = WorkflowEngine::fromFile($path, $this->registry);
        return $engine->run($input);
    }

    protected function assertWorkflowSucceeds(array $workflow, array $input = []): void
    {
        $result = $this->runWorkflow($workflow, $input);
        $this->assertTrue($result->isSuccess(), 'Workflow should succeed');
    }

    protected function assertWorkflowFails(array $workflow, array $input = []): void
    {
        $result = $this->runWorkflow($workflow, $input);
        $this->assertFalse($result->isSuccess(), 'Workflow should fail');
    }
}
```

### Using the Base Class

```php
<?php

namespace Tests\Workflows;

use Tests\WorkflowTestCase;

class MyWorkflowTest extends WorkflowTestCase
{
    public function testMyFeature(): void
    {
        $this->createMockAgent('MyAgent', ['result' => 'success']);
        
        $workflow = [
            'StartAt' => 'Test',
            'States' => [
                'Test' => ['Type' => 'Task', 'Agent' => 'MyAgent', 'End' => true]
            ]
        ];
        
        $this->assertWorkflowSucceeds($workflow, ['input' => 'data']);
    }
}
```

## Testing Map States

```php
public function testMapStateProcessesAllItems(): void
{
    $processor = new ConfigurableMockAgent('ItemProcessor', function($params) {
        return ['processed' => $params['item'] * 2];
    });
    
    $this->registry->register('ItemProcessor', $processor);

    $workflow = [
        'StartAt' => 'ProcessItems',
        'States' => [
            'ProcessItems' => [
                'Type' => 'Map',
                'ItemsPath' => '$.items',
                'ItemSelector' => [
                    'item.$' => '$$.Map.Item.Value'
                ],
                'Iterator' => [
                    'StartAt' => 'Process',
                    'States' => [
                        'Process' => [
                            'Type' => 'Task',
                            'Agent' => 'ItemProcessor',
                            'End' => true
                        ]
                    ]
                ],
                'End' => true
            ]
        ]
    ];

    $engine = new WorkflowEngine($workflow, $this->registry);
    $result = $engine->run(['items' => [1, 2, 3, 4, 5]]);

    $this->assertTrue($result->isSuccess());
    $this->assertEquals(5, $processor->getCallCount());
    
    $output = $result->getOutput();
    $this->assertEquals(2, $output[0]['processed']);
    $this->assertEquals(10, $output[4]['processed']);
}
```

## Testing with Assertions on Trace

```php
public function testWorkflowExecutesExpectedStates(): void
{
    $this->createMockAgent('Agent1', ['step' => 1]);
    $this->createMockAgent('Agent2', ['step' => 2]);
    
    $workflow = [
        'StartAt' => 'Step1',
        'States' => [
            'Step1' => ['Type' => 'Task', 'Agent' => 'Agent1', 'Next' => 'Step2'],
            'Step2' => ['Type' => 'Task', 'Agent' => 'Agent2', 'End' => true]
        ]
    ];

    $result = $this->runWorkflow($workflow);
    $trace = $result->getTrace();
    
    $stateNames = array_column($trace, 'stateName');
    
    $this->assertContains('Step1', $stateNames);
    $this->assertContains('Step2', $stateNames);
    
    // Verify order
    $step1Index = array_search('Step1', $stateNames);
    $step2Index = array_search('Step2', $stateNames);
    $this->assertLessThan($step2Index, $step1Index);
}
```

## Best Practices

### 1. Test in Isolation

Each test should be independent:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->registry = new AgentRegistry(); // Fresh registry each test
}
```

### 2. Use Descriptive Test Names

```php
public function testRefundWorkflowDeniesRequestWhenAmountExceedsPolicy(): void
public function testChoiceStateRoutesToEscalationWhenSeverityIsHigh(): void
public function testRetryExhaustsAllAttemptsBeforeFailing(): void
```

### 3. Test Edge Cases

```php
public function testEmptyInputHandledGracefully(): void
public function testNullValuesInParametersDoNotCrash(): void
public function testMaximumDepthPreventsInfiniteRecursion(): void
```

### 4. Mock External Dependencies

```php
// Bad: Uses real API
$agent = new RealAPIAgent($apiKey);

// Good: Uses mock in tests
$agent = new ConfigurableMockAgent('API', ['data' => 'mocked']);
```

### 5. Test Error Paths

```php
public function testWorkflowHandlesAgentTimeout(): void
public function testCatchAllHandlesUnexpectedErrors(): void
public function testBudgetExceededTriggersCorrectBehavior(): void
```

## Running Tests

```bash
# Run all workflow tests
./vendor/bin/phpunit tests/Workflows

# Run with coverage
./vendor/bin/phpunit tests/Workflows --coverage-html coverage

# Run specific test
./vendor/bin/phpunit --filter testRetrySucceedsAfterTransientFailure
```

## Related

- [Tutorial 11: Error Handling](../tutorials/11-error-handling.md)
- [Production Deployment](production-deployment.md)
- [Best Practices](best-practices.md)
