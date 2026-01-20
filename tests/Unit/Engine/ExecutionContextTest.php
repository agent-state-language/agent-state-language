<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\ExecutionContext;
use PHPUnit\Framework\TestCase;

class ExecutionContextTest extends TestCase
{
    public function testCreateWithInput(): void
    {
        $input = ['name' => 'John', 'age' => 30];
        $context = new ExecutionContext($input);
        
        $this->assertEquals($input, $context->getData());
    }

    public function testGetExecutionId(): void
    {
        $context = new ExecutionContext([]);
        
        $this->assertNotEmpty($context->getExecutionId());
        $this->assertMatchesRegularExpression('/^exec-[0-9a-f]+$/', $context->getExecutionId());
    }

    public function testGetStartTime(): void
    {
        $before = new \DateTimeImmutable();
        $context = new ExecutionContext([]);
        $after = new \DateTimeImmutable();
        
        $this->assertGreaterThanOrEqual($before, $context->getStartTime());
        $this->assertLessThanOrEqual($after, $context->getStartTime());
    }

    public function testSetAndGetCurrentState(): void
    {
        $context = new ExecutionContext([]);
        
        $context->setCurrentState('TaskState');
        
        $this->assertEquals('TaskState', $context->getCurrentState());
    }

    public function testSetData(): void
    {
        $context = new ExecutionContext(['old' => 'value']);
        
        $context->setData(['new' => 'data']);
        
        $this->assertEquals(['new' => 'data'], $context->getData());
    }

    public function testMergeData(): void
    {
        $context = new ExecutionContext(['existing' => 'value']);
        
        $context->mergeData(['new' => 'data']);
        
        $this->assertEquals([
            'existing' => 'value',
            'new' => 'data'
        ], $context->getData());
    }

    public function testMergeDataOverwrites(): void
    {
        $context = new ExecutionContext(['key' => 'old']);
        
        $context->mergeData(['key' => 'new']);
        
        $this->assertEquals(['key' => 'new'], $context->getData());
    }

    public function testSetResultPath(): void
    {
        $context = new ExecutionContext(['input' => 'value']);
        
        $context->setResultPath('$.output', ['result' => 'data']);
        
        $this->assertEquals([
            'input' => 'value',
            'output' => ['result' => 'data']
        ], $context->getData());
    }

    public function testSetNestedResultPath(): void
    {
        $context = new ExecutionContext(['data' => ['existing' => 'value']]);
        
        $context->setResultPath('$.data.result', 'new value');
        
        $this->assertEquals([
            'data' => [
                'existing' => 'value',
                'result' => 'new value'
            ]
        ], $context->getData());
    }

    public function testGetValue(): void
    {
        $context = new ExecutionContext([
            'user' => ['name' => 'John']
        ]);
        
        $result = $context->getValue('$.user.name');
        
        $this->assertEquals('John', $result);
    }

    public function testGetValueWithDefault(): void
    {
        $context = new ExecutionContext([]);
        
        $result = $context->getValue('$.missing', 'default');
        
        $this->assertEquals('default', $result);
    }

    public function testResolveParameters(): void
    {
        $context = new ExecutionContext([
            'user' => ['name' => 'John', 'age' => 30]
        ]);
        
        $parameters = [
            'name.$' => '$.user.name',
            'age.$' => '$.user.age',
            'static' => 'value'
        ];
        
        $result = $context->resolveParameters($parameters);
        
        $this->assertEquals([
            'name' => 'John',
            'age' => 30,
            'static' => 'value'
        ], $result);
    }

    public function testApplyInputPath(): void
    {
        $context = new ExecutionContext([
            'wrapper' => ['data' => ['value' => 'test']]
        ]);
        
        $context->applyInputPath('$.wrapper.data');
        
        $this->assertEquals(['value' => 'test'], $context->getData());
    }

    public function testApplyOutputPath(): void
    {
        $context = new ExecutionContext([
            'result' => ['value' => 'test'],
            'other' => 'data'
        ]);
        
        $context->applyOutputPath('$.result');
        
        $this->assertEquals(['value' => 'test'], $context->getData());
    }

    public function testGetContextVariables(): void
    {
        $context = new ExecutionContext([]);
        $context->setCurrentState('TestState');
        
        $variables = $context->getContextVariables();
        
        $this->assertArrayHasKey('Execution', $variables);
        $this->assertArrayHasKey('State', $variables);
        $this->assertEquals('TestState', $variables['State']['Name']);
        $this->assertArrayHasKey('Id', $variables['Execution']);
        $this->assertArrayHasKey('StartTime', $variables['Execution']);
    }

    public function testTrackCost(): void
    {
        $context = new ExecutionContext([]);
        
        $context->trackCost(0.50);
        $context->trackCost(0.25);
        
        $this->assertEquals(0.75, $context->getTotalCost());
    }

    public function testTrackTokens(): void
    {
        $context = new ExecutionContext([]);
        
        $context->trackTokens(1000);
        $context->trackTokens(500);
        
        $this->assertEquals(1500, $context->getTotalTokens());
    }

    public function testClone(): void
    {
        $context = new ExecutionContext(['original' => 'data']);
        $context->setCurrentState('OriginalState');
        
        $clone = $context->clone();
        $clone->setData(['new' => 'data']);
        $clone->setCurrentState('NewState');
        
        // Original should be unchanged
        $this->assertEquals(['original' => 'data'], $context->getData());
        $this->assertEquals('OriginalState', $context->getCurrentState());
        
        // Clone should have new values
        $this->assertEquals(['new' => 'data'], $clone->getData());
        $this->assertEquals('NewState', $clone->getCurrentState());
    }

    public function testCheckpoints(): void
    {
        $context = new ExecutionContext(['step1' => 'complete']);
        
        $checkpoint = $context->createCheckpoint('checkpoint1');
        
        $this->assertArrayHasKey('id', $checkpoint);
        $this->assertArrayHasKey('data', $checkpoint);
        $this->assertArrayHasKey('timestamp', $checkpoint);
        $this->assertEquals('checkpoint1', $checkpoint['id']);
    }

    public function testRestoreFromCheckpoint(): void
    {
        $context = new ExecutionContext(['step1' => 'complete']);
        $checkpoint = $context->createCheckpoint('checkpoint1');
        
        // Modify context
        $context->setData(['step2' => 'complete']);
        $this->assertEquals(['step2' => 'complete'], $context->getData());
        
        // Restore from checkpoint
        $context->restoreFromCheckpoint($checkpoint);
        
        $this->assertEquals(['step1' => 'complete'], $context->getData());
    }

    public function testStateHistory(): void
    {
        $context = new ExecutionContext([]);
        
        $context->enterState('State1', ['input' => 'data']);
        $context->exitState('State1', ['output' => 'result']);
        
        $history = $context->getStateHistory();
        
        $this->assertCount(1, $history);
        $this->assertEquals('State1', $history[0]['state']);
        $this->assertArrayHasKey('enteredAt', $history[0]);
        $this->assertArrayHasKey('exitedAt', $history[0]);
        $this->assertArrayHasKey('duration', $history[0]);
    }

    public function testMapIteratorContext(): void
    {
        $context = new ExecutionContext(['items' => [1, 2, 3]]);
        
        $context->setMapContext(1, ['value' => 2]);
        
        $mapContext = $context->getMapContext();
        
        $this->assertEquals(1, $mapContext['Item']['Index']);
        $this->assertEquals(['value' => 2], $mapContext['Item']['Value']);
    }

    public function testMetadata(): void
    {
        $context = new ExecutionContext([]);
        
        $context->setMetadata('custom_key', 'custom_value');
        
        $this->assertEquals('custom_value', $context->getMetadata('custom_key'));
        $this->assertNull($context->getMetadata('missing'));
    }

    public function testToArray(): void
    {
        $context = new ExecutionContext(['key' => 'value']);
        $context->setCurrentState('TestState');
        
        $array = $context->toArray();
        
        $this->assertArrayHasKey('executionId', $array);
        $this->assertArrayHasKey('currentState', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('totalCost', $array);
        $this->assertArrayHasKey('totalTokens', $array);
        $this->assertEquals('TestState', $array['currentState']);
    }
}
