# Testing Workflows

This guide covers strategies for testing ASL workflows.

## Unit Testing States

### Mock Agents

```php
<?php

use AgentStateLanguage\Agents\AgentInterface;

class MockAgent implements AgentInterface
{
    private array $responses;
    private int $callCount = 0;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function execute(array $parameters): array
    {
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
}
```

### Test Example

```php
<?php

use PHPUnit\Framework\TestCase;
use AgentStateLanguage\Engine\WorkflowEngine;
use AgentStateLanguage\Agents\AgentRegistry;

class WorkflowTest extends TestCase
{
    public function testSimpleWorkflow(): void
    {
        $registry = new AgentRegistry();
        $registry->register('TestAgent', new MockAgent([
            ['result' => 'success']
        ]));

        $engine = WorkflowEngine::fromFile('test-workflow.asl.json', $registry);
        $result = $engine->run(['input' => 'data']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('success', $result->getOutput()['result']);
    }
}
```

## Testing Choice States

```php
public function testChoiceRouting(): void
{
    $workflow = [
        'StartAt' => 'Route',
        'States' => [
            'Route' => [
                'Type' => 'Choice',
                'Choices' => [
                    ['Variable' => '$.score', 'NumericGreaterThan' => 80, 'Next' => 'High'],
                ],
                'Default' => 'Low'
            ],
            'High' => ['Type' => 'Pass', 'Result' => 'high', 'End' => true],
            'Low' => ['Type' => 'Pass', 'Result' => 'low', 'End' => true]
        ]
    ];

    $engine = new WorkflowEngine($workflow, new AgentRegistry());

    // Test high score path
    $result = $engine->run(['score' => 90]);
    $this->assertEquals('high', $result->getOutput()['value']);

    // Test low score path
    $result = $engine->run(['score' => 50]);
    $this->assertEquals('low', $result->getOutput()['value']);
}
```

## Testing Error Handling

```php
public function testRetryBehavior(): void
{
    $failingAgent = new class implements AgentInterface {
        private int $attempts = 0;
        
        public function execute(array $params): array
        {
            $this->attempts++;
            if ($this->attempts < 3) {
                throw new \Exception('Transient failure');
            }
            return ['success' => true, 'attempts' => $this->attempts];
        }
        
        public function getName(): string
        {
            return 'FailingAgent';
        }
    };

    $registry = new AgentRegistry();
    $registry->register('FailingAgent', $failingAgent);

    $workflow = [
        'StartAt' => 'Flaky',
        'States' => [
            'Flaky' => [
                'Type' => 'Task',
                'Agent' => 'FailingAgent',
                'Retry' => [['ErrorEquals' => ['States.ALL'], 'MaxAttempts' => 5]],
                'End' => true
            ]
        ]
    ];

    $engine = new WorkflowEngine($workflow, $registry);
    $result = $engine->run([]);

    $this->assertTrue($result->isSuccess());
    $this->assertEquals(3, $result->getOutput()['attempts']);
}
```

## Validation Testing

```php
public function testWorkflowValidation(): void
{
    $invalidWorkflow = [
        'StartAt' => 'MissingState',
        'States' => [
            'OnlyState' => ['Type' => 'Pass', 'End' => true]
        ]
    ];

    $this->expectException(ValidationException::class);
    
    $engine = new WorkflowEngine($invalidWorkflow, new AgentRegistry());
    $engine->validate();
}
```

## Integration Testing

```php
public function testFullWorkflow(): void
{
    // Use real agents with test configuration
    $registry = new AgentRegistry();
    $registry->register('RealAgent', new RealAgent(['testMode' => true]));

    $engine = WorkflowEngine::fromFile('production-workflow.asl.json', $registry);
    $result = $engine->run(['testInput' => 'value']);

    $this->assertTrue($result->isSuccess());
    $this->assertArrayHasKey('expectedOutput', $result->getOutput());
}
```

## Test Fixtures

Create reusable test data:

```php
trait WorkflowTestFixtures
{
    protected function simpleWorkflow(): array
    {
        return [
            'StartAt' => 'Process',
            'States' => [
                'Process' => ['Type' => 'Task', 'Agent' => 'TestAgent', 'End' => true]
            ]
        ];
    }

    protected function mockRegistry(array $responses): AgentRegistry
    {
        $registry = new AgentRegistry();
        foreach ($responses as $name => $data) {
            $registry->register($name, new MockAgent([$data]));
        }
        return $registry;
    }
}
```
