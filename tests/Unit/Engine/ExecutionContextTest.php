<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\ExecutionContext;
use PHPUnit\Framework\TestCase;

class ExecutionContextTest extends TestCase
{
    public function testGetExecutionId(): void
    {
        $context = new ExecutionContext('TestWorkflow');
        
        $this->assertNotEmpty($context->getExecutionId());
    }

    public function testGetWorkflowName(): void
    {
        $context = new ExecutionContext('TestWorkflow');
        
        $this->assertEquals('TestWorkflow', $context->getWorkflowName());
    }

    public function testGetStartTime(): void
    {
        $before = date('c');
        $context = new ExecutionContext();
        $after = date('c');
        
        $this->assertGreaterThanOrEqual($before, $context->getStartTime());
        $this->assertLessThanOrEqual($after, $context->getStartTime());
    }

    public function testEnterState(): void
    {
        $context = new ExecutionContext();
        
        $context->enterState('TaskState');
        
        $this->assertEquals('TaskState', $context->getCurrentState());
        $this->assertEquals(0, $context->getRetryCount());
    }

    public function testIncrementRetry(): void
    {
        $context = new ExecutionContext();
        $context->enterState('TaskState');
        
        $context->incrementRetry();
        $context->incrementRetry();
        
        $this->assertEquals(2, $context->getRetryCount());
    }

    public function testRetryResetOnEnterState(): void
    {
        $context = new ExecutionContext();
        $context->enterState('State1');
        $context->incrementRetry();
        $context->incrementRetry();
        
        $context->enterState('State2');
        
        $this->assertEquals(0, $context->getRetryCount());
    }

    public function testMapContext(): void
    {
        $context = new ExecutionContext();
        
        $context->setMapContext(2, ['name' => 'John']);
        
        $this->assertEquals(2, $context->getMapItemIndex());
        $this->assertEquals(['name' => 'John'], $context->getMapItemValue());
    }

    public function testClearMapContext(): void
    {
        $context = new ExecutionContext();
        $context->setMapContext(1, 'value');
        
        $context->clearMapContext();
        
        $this->assertNull($context->getMapItemIndex());
        $this->assertNull($context->getMapItemValue());
    }

    public function testAddAndGetTrace(): void
    {
        $context = new ExecutionContext();
        $context->enterState('TestState');
        
        $context->addTraceEntry(['action' => 'test', 'data' => 'value']);
        
        $trace = $context->getTrace();
        
        $this->assertCount(1, $trace);
        $this->assertEquals('test', $trace[0]['action']);
        $this->assertEquals('TestState', $trace[0]['state']);
        $this->assertArrayHasKey('timestamp', $trace[0]);
    }

    public function testTokenTracking(): void
    {
        $context = new ExecutionContext();
        
        $context->addTokens(500);
        $context->addTokens(300);
        
        $this->assertEquals(800, $context->getTotalTokens());
    }

    public function testCostTracking(): void
    {
        $context = new ExecutionContext();
        
        $context->addCost(0.05);
        $context->addCost(0.10);
        
        $this->assertEqualsWithDelta(0.15, $context->getTotalCost(), 0.0001);
    }

    public function testToContextObject(): void
    {
        $context = new ExecutionContext('MyWorkflow');
        $context->enterState('CurrentState');
        
        $obj = $context->toContextObject();
        
        $this->assertArrayHasKey('Execution', $obj);
        $this->assertArrayHasKey('State', $obj);
        $this->assertEquals('MyWorkflow', $obj['Execution']['Name']);
        $this->assertEquals('CurrentState', $obj['State']['Name']);
    }

    public function testToContextObjectWithMapContext(): void
    {
        $context = new ExecutionContext();
        $context->setMapContext(5, ['item' => 'value']);
        
        $obj = $context->toContextObject();
        
        $this->assertArrayHasKey('Map', $obj);
        $this->assertEquals(5, $obj['Map']['Item']['Index']);
        $this->assertEquals(['item' => 'value'], $obj['Map']['Item']['Value']);
    }

    public function testToContextObjectWithoutMapContext(): void
    {
        $context = new ExecutionContext();
        
        $obj = $context->toContextObject();
        
        $this->assertArrayNotHasKey('Map', $obj);
    }

    public function testStateEnteredTime(): void
    {
        $context = new ExecutionContext();
        
        $context->enterState('State1');
        $time1 = $context->getStateEnteredTime();
        
        usleep(1000); // Small delay
        
        $context->enterState('State2');
        $time2 = $context->getStateEnteredTime();
        
        $this->assertNotEmpty($time1);
        $this->assertNotEmpty($time2);
    }

    public function testDefaultWorkflowName(): void
    {
        $context = new ExecutionContext();
        
        $this->assertEquals('', $context->getWorkflowName());
    }
}
