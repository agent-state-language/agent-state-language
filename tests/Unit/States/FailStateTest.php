<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\FailState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;

class FailStateTest extends TestCase
{
    public function testExecuteEndsWorkflowWithFailure(): void
    {
        $definition = [
            'Type' => 'Fail',
            'Error' => 'ValidationError',
            'Cause' => 'Input validation failed'
        ];
        
        $state = new FailState('TestFail', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isEnd());
        $this->assertNull($result->getNextState());
    }

    public function testErrorAndCauseInOutput(): void
    {
        $definition = [
            'Type' => 'Fail',
            'Error' => 'CustomError',
            'Cause' => 'Something went wrong'
        ];
        
        $state = new FailState('TestFail', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('CustomError', $result->getError());
        $this->assertEquals('Something went wrong', $result->getCause());
    }

    public function testErrorPath(): void
    {
        $definition = [
            'Type' => 'Fail',
            'ErrorPath' => '$.error.code',
            'CausePath' => '$.error.message'
        ];
        
        $state = new FailState('TestFail', $definition);
        $context = new ExecutionContext([
            'error' => [
                'code' => 'E001',
                'message' => 'Dynamic error message'
            ]
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('E001', $result->getError());
        $this->assertEquals('Dynamic error message', $result->getCause());
    }

    public function testGetType(): void
    {
        $state = new FailState('TestFail', [
            'Type' => 'Fail',
            'Error' => 'Error',
            'Cause' => 'Cause'
        ]);
        
        $this->assertEquals('Fail', $state->getType());
    }

    public function testGetName(): void
    {
        $state = new FailState('MyFailState', [
            'Type' => 'Fail',
            'Error' => 'Error',
            'Cause' => 'Cause'
        ]);
        
        $this->assertEquals('MyFailState', $state->getName());
    }

    public function testGetComment(): void
    {
        $state = new FailState('TestFail', [
            'Type' => 'Fail',
            'Comment' => 'Handle critical failure',
            'Error' => 'Error',
            'Cause' => 'Cause'
        ]);
        
        $this->assertEquals('Handle critical failure', $state->getComment());
    }

    public function testIsAlwaysEnd(): void
    {
        $state = new FailState('TestFail', [
            'Type' => 'Fail',
            'Error' => 'Error',
            'Cause' => 'Cause'
        ]);
        
        $this->assertTrue($state->isEnd());
    }

    public function testGetNextStateIsNull(): void
    {
        $state = new FailState('TestFail', [
            'Type' => 'Fail',
            'Error' => 'Error',
            'Cause' => 'Cause'
        ]);
        
        $this->assertNull($state->getNextState());
    }

    public function testStaticErrorAndCause(): void
    {
        $definition = [
            'Type' => 'Fail',
            'Error' => 'TimeoutError',
            'Cause' => 'Operation timed out after 30 seconds'
        ];
        
        $state = new FailState('TestFail', $definition);
        
        $this->assertEquals('TimeoutError', $state->getErrorCode());
        $this->assertEquals('Operation timed out after 30 seconds', $state->getCauseMessage());
    }

    public function testFailStateMarksResultAsFailed(): void
    {
        $definition = [
            'Type' => 'Fail',
            'Error' => 'ProcessingError',
            'Cause' => 'Failed to process request'
        ];
        
        $state = new FailState('TestFail', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isFailed());
    }

    public function testOutputContainsErrorInfo(): void
    {
        $definition = [
            'Type' => 'Fail',
            'Error' => 'NotFoundError',
            'Cause' => 'Resource not found'
        ];
        
        $state = new FailState('TestFail', $definition);
        $context = new ExecutionContext(['originalData' => 'value']);
        
        $result = $state->execute($context);
        
        $output = $result->getOutput();
        
        $this->assertArrayHasKey('Error', $output);
        $this->assertArrayHasKey('Cause', $output);
        $this->assertEquals('NotFoundError', $output['Error']);
    }

    public function testMissingErrorUsesDefault(): void
    {
        $definition = [
            'Type' => 'Fail'
            // No Error or Cause specified
        ];
        
        $state = new FailState('TestFail', $definition);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        // Should have some default error
        $this->assertNotNull($result->getError());
    }
}
