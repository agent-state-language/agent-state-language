<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\SucceedState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;

class SucceedStateTest extends TestCase
{
    public function testExecuteEndsWorkflow(): void
    {
        $definition = [
            'Type' => 'Succeed'
        ];
        
        $state = new SucceedState('TestSucceed', $definition);
        $context = new ExecutionContext(['data' => 'value']);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->isEnd());
        $this->assertNull($result->getNextState());
    }

    public function testPassthroughData(): void
    {
        $definition = [
            'Type' => 'Succeed'
        ];
        
        $state = new SucceedState('TestSucceed', $definition);
        $context = new ExecutionContext([
            'result' => 'completed',
            'details' => ['key' => 'value']
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([
            'result' => 'completed',
            'details' => ['key' => 'value']
        ], $result->getOutput());
    }

    public function testInputPath(): void
    {
        $definition = [
            'Type' => 'Succeed',
            'InputPath' => '$.final'
        ];
        
        $state = new SucceedState('TestSucceed', $definition);
        $context = new ExecutionContext([
            'final' => ['status' => 'done'],
            'other' => 'excluded'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals(['status' => 'done'], $result->getOutput());
    }

    public function testOutputPath(): void
    {
        $definition = [
            'Type' => 'Succeed',
            'OutputPath' => '$.summary'
        ];
        
        $state = new SucceedState('TestSucceed', $definition);
        $context = new ExecutionContext([
            'summary' => 'All tasks completed',
            'details' => 'verbose info'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('All tasks completed', $result->getOutput());
    }

    public function testGetType(): void
    {
        $state = new SucceedState('TestSucceed', ['Type' => 'Succeed']);
        
        $this->assertEquals('Succeed', $state->getType());
    }

    public function testGetName(): void
    {
        $state = new SucceedState('MySucceedState', ['Type' => 'Succeed']);
        
        $this->assertEquals('MySucceedState', $state->getName());
    }

    public function testGetComment(): void
    {
        $state = new SucceedState('TestSucceed', [
            'Type' => 'Succeed',
            'Comment' => 'Workflow completed successfully'
        ]);
        
        $this->assertEquals('Workflow completed successfully', $state->getComment());
    }

    public function testIsAlwaysEnd(): void
    {
        $state = new SucceedState('TestSucceed', ['Type' => 'Succeed']);
        
        $this->assertTrue($state->isEnd());
    }

    public function testGetNextStateIsNull(): void
    {
        $state = new SucceedState('TestSucceed', ['Type' => 'Succeed']);
        
        $this->assertNull($state->getNextState());
    }

    public function testWithEmptyInput(): void
    {
        $definition = [
            'Type' => 'Succeed'
        ];
        
        $state = new SucceedState('TestSucceed', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals([], $result->getOutput());
    }
}
