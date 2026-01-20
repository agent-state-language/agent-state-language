<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\WaitState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;

class WaitStateTest extends TestCase
{
    public function testExecuteWithSeconds(): void
    {
        $definition = [
            'Type' => 'Wait',
            'Seconds' => 1, // Using 1 second for testing
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext(['data' => 'value']);
        
        $start = microtime(true);
        $result = $state->execute($context);
        $elapsed = microtime(true) - $start;
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('NextState', $result->getNextState());
        $this->assertGreaterThanOrEqual(0.9, $elapsed); // Allow some tolerance
    }

    public function testExecuteWithSecondsPath(): void
    {
        $definition = [
            'Type' => 'Wait',
            'SecondsPath' => '$.waitTime',
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext(['waitTime' => 1]);
        
        $start = microtime(true);
        $result = $state->execute($context);
        $elapsed = microtime(true) - $start;
        
        $this->assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testExecuteWithTimestamp(): void
    {
        // Set timestamp 1 second in the future
        $futureTime = (new \DateTimeImmutable())->modify('+1 second')->format('c');
        
        $definition = [
            'Type' => 'Wait',
            'Timestamp' => $futureTime,
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([]);
        
        $start = microtime(true);
        $result = $state->execute($context);
        $elapsed = microtime(true) - $start;
        
        $this->assertGreaterThanOrEqual(0.5, $elapsed); // Some tolerance for past timestamp edge
    }

    public function testExecuteWithTimestampPath(): void
    {
        $futureTime = (new \DateTimeImmutable())->modify('+1 second')->format('c');
        
        $definition = [
            'Type' => 'Wait',
            'TimestampPath' => '$.deadline',
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext(['deadline' => $futureTime]);
        
        $start = microtime(true);
        $result = $state->execute($context);
        $elapsed = microtime(true) - $start;
        
        $this->assertGreaterThanOrEqual(0.5, $elapsed);
    }

    public function testPassthroughData(): void
    {
        $definition = [
            'Type' => 'Wait',
            'Seconds' => 0,
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([
            'preserved' => 'data',
            'nested' => ['key' => 'value']
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([
            'preserved' => 'data',
            'nested' => ['key' => 'value']
        ], $result->getOutput());
    }

    public function testPastTimestampDoesNotWait(): void
    {
        $pastTime = (new \DateTimeImmutable())->modify('-1 hour')->format('c');
        
        $definition = [
            'Type' => 'Wait',
            'Timestamp' => $pastTime,
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([]);
        
        $start = microtime(true);
        $result = $state->execute($context);
        $elapsed = microtime(true) - $start;
        
        // Should complete almost immediately
        $this->assertLessThan(0.1, $elapsed);
        $this->assertTrue($result->isSuccess());
    }

    public function testAsEndState(): void
    {
        $definition = [
            'Type' => 'Wait',
            'Seconds' => 0,
            'End' => true
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isEnd());
        $this->assertNull($result->getNextState());
    }

    public function testGetType(): void
    {
        $state = new WaitState('TestWait', ['Type' => 'Wait', 'Seconds' => 1, 'Next' => 'Next']);
        
        $this->assertEquals('Wait', $state->getType());
    }

    public function testGetName(): void
    {
        $state = new WaitState('MyWaitState', ['Type' => 'Wait', 'Seconds' => 1, 'Next' => 'Next']);
        
        $this->assertEquals('MyWaitState', $state->getName());
    }

    public function testGetComment(): void
    {
        $state = new WaitState('TestWait', [
            'Type' => 'Wait',
            'Comment' => 'Wait for processing',
            'Seconds' => 1,
            'Next' => 'Next'
        ]);
        
        $this->assertEquals('Wait for processing', $state->getComment());
    }

    public function testZeroSecondsDoesNotBlock(): void
    {
        $definition = [
            'Type' => 'Wait',
            'Seconds' => 0,
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([]);
        
        $start = microtime(true);
        $result = $state->execute($context);
        $elapsed = microtime(true) - $start;
        
        $this->assertLessThan(0.1, $elapsed);
        $this->assertTrue($result->isSuccess());
    }

    public function testInputPath(): void
    {
        $definition = [
            'Type' => 'Wait',
            'InputPath' => '$.timer',
            'Seconds' => 0,
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([
            'timer' => ['data' => 'filtered'],
            'other' => 'excluded'
        ]);
        
        $result = $state->execute($context);
        
        // InputPath filters what's passed through
        $this->assertEquals(['data' => 'filtered'], $result->getOutput());
    }

    public function testOutputPath(): void
    {
        $definition = [
            'Type' => 'Wait',
            'OutputPath' => '$.important',
            'Seconds' => 0,
            'Next' => 'NextState'
        ];
        
        $state = new WaitState('TestWait', $definition);
        $context = new ExecutionContext([
            'important' => ['key' => 'value'],
            'other' => 'data'
        ]);
        
        $result = $state->execute($context);
        
        // OutputPath filters the output
        $this->assertEquals(['key' => 'value'], $result->getOutput());
    }
}
