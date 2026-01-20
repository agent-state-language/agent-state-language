<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\PassState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;

class PassStateTest extends TestCase
{
    public function testExecuteWithStaticResult(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Result' => ['message' => 'Hello, World!'],
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext(['input' => 'data']);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertEquals(['message' => 'Hello, World!'], $result->getOutput());
        $this->assertEquals('NextState', $result->getNextState());
        $this->assertTrue($result->isSuccess());
    }

    public function testExecuteWithParameters(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Parameters' => [
                'name.$' => '$.user.name',
                'greeting' => 'Hello'
            ],
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext([
            'user' => ['name' => 'John']
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([
            'name' => 'John',
            'greeting' => 'Hello'
        ], $result->getOutput());
    }

    public function testExecuteWithResultPath(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Result' => ['value' => 42],
            'ResultPath' => '$.computed',
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext(['original' => 'data']);
        
        $result = $state->execute($context);
        
        // Result should be placed at ResultPath
        $this->assertEquals(['value' => 42], $result->getOutput());
    }

    public function testExecuteWithInputPath(): void
    {
        $definition = [
            'Type' => 'Pass',
            'InputPath' => '$.nested.data',
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext([
            'nested' => ['data' => ['value' => 'test']]
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals(['value' => 'test'], $result->getOutput());
    }

    public function testExecuteWithOutputPath(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Result' => [
                'extracted' => 'value',
                'other' => 'data'
            ],
            'OutputPath' => '$.extracted',
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext([]);
        
        // Note: OutputPath should filter the result
        $result = $state->execute($context);
        
        // OutputPath extracts just the 'extracted' value
        $this->assertEquals('value', $result->getOutput());
    }

    public function testExecuteAsEndState(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Result' => ['final' => 'result'],
            'End' => true
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertNull($result->getNextState());
        $this->assertTrue($result->isEnd());
    }

    public function testPassthroughWhenNoResultOrParameters(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext(['passthrough' => 'data']);
        
        $result = $state->execute($context);
        
        $this->assertEquals(['passthrough' => 'data'], $result->getOutput());
    }

    public function testGetName(): void
    {
        $state = new PassState('MyPassState', ['Type' => 'Pass', 'Next' => 'Next']);
        
        $this->assertEquals('MyPassState', $state->getName());
    }

    public function testGetType(): void
    {
        $state = new PassState('TestPass', ['Type' => 'Pass', 'Next' => 'Next']);
        
        $this->assertEquals('Pass', $state->getType());
    }

    public function testIsEnd(): void
    {
        $endState = new PassState('EndPass', ['Type' => 'Pass', 'End' => true]);
        $continueState = new PassState('ContinuePass', ['Type' => 'Pass', 'Next' => 'Next']);
        
        $this->assertTrue($endState->isEnd());
        $this->assertFalse($continueState->isEnd());
    }

    public function testGetNextState(): void
    {
        $state = new PassState('TestPass', ['Type' => 'Pass', 'Next' => 'TargetState']);
        
        $this->assertEquals('TargetState', $state->getNextState());
    }

    public function testParametersWithIntrinsicFunctions(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Parameters' => [
                'uuid.$' => 'States.UUID()',
                'formatted.$' => "States.Format('User: {}', $.name)"
            ],
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext(['name' => 'John']);
        
        $result = $state->execute($context);
        
        $output = $result->getOutput();
        
        $this->assertArrayHasKey('uuid', $output);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $output['uuid']);
        $this->assertEquals('User: John', $output['formatted']);
    }

    public function testMergeWithExistingData(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Parameters' => [
                'new' => 'value',
                'preserved.$' => '$.existing'
            ],
            'ResultPath' => '$.transformed',
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext([
            'existing' => 'data',
            'other' => 'info'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([
            'new' => 'value',
            'preserved' => 'data'
        ], $result->getOutput());
    }

    public function testNullResultPath(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Result' => ['discarded' => 'result'],
            'ResultPath' => null,
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        $context = new ExecutionContext(['preserved' => 'data']);
        
        $result = $state->execute($context);
        
        // With null ResultPath, original data is preserved
        $this->assertEquals(['preserved' => 'data'], $result->getOutput());
    }

    public function testComment(): void
    {
        $definition = [
            'Type' => 'Pass',
            'Comment' => 'This is a test state',
            'Next' => 'NextState'
        ];
        
        $state = new PassState('TestPass', $definition);
        
        $this->assertEquals('This is a test state', $state->getComment());
    }
}
