<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Exceptions;

use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\Exceptions\ASLException;
use AgentStateLanguage\Exceptions\BudgetExceededException;
use AgentStateLanguage\Exceptions\StateException;
use AgentStateLanguage\Exceptions\TimeoutException;
use AgentStateLanguage\Exceptions\ValidationException;
use AgentStateLanguage\Tests\TestCase;

class ExceptionsTest extends TestCase
{
    // ASLException Tests
    public function testASLExceptionMessage(): void
    {
        $exception = new ASLException('Test message', 'States.Error');
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals('States.Error', $exception->getErrorCode());
    }

    public function testASLExceptionWithPreviousException(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new ASLException('Wrapper message', 'States.Error', 0, $previous);
        
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testASLExceptionDefaultErrorCode(): void
    {
        $exception = new ASLException('Test message');
        
        // Default error code is 'States.Error'
        $this->assertEquals('States.Error', $exception->getErrorCode());
    }

    // AgentException Tests
    public function testAgentException(): void
    {
        $exception = new AgentException('Agent failed', 'MyAgent', 'Agent.ExecutionError');
        
        $this->assertEquals('Agent failed', $exception->getMessage());
        $this->assertEquals('MyAgent', $exception->getAgentName());
        $this->assertEquals('Agent.ExecutionError', $exception->getErrorCode());
    }

    public function testAgentExceptionDefaultErrorCode(): void
    {
        $exception = new AgentException('Agent failed');
        
        $this->assertEquals('Agent.Error', $exception->getErrorCode());
    }

    public function testAgentExceptionGetAgentName(): void
    {
        $exception = new AgentException('Error', 'TestAgent');
        
        $this->assertEquals('TestAgent', $exception->getAgentName());
    }

    // StateException Tests
    public function testStateException(): void
    {
        $exception = new StateException('State error', 'MyState', 'States.TaskFailed');
        
        $this->assertEquals('State error', $exception->getMessage());
        $this->assertEquals('MyState', $exception->getStateName());
        $this->assertEquals('States.TaskFailed', $exception->getErrorCode());
    }

    public function testStateExceptionGetStateName(): void
    {
        $exception = new StateException('Error', 'ProcessData', 'Error');
        
        $this->assertEquals('ProcessData', $exception->getStateName());
    }

    // ValidationException Tests
    public function testValidationException(): void
    {
        $errors = [
            'Missing StartAt field',
            'State "Foo" has no Next or End'
        ];
        
        $exception = new ValidationException('Validation failed', $errors);
        
        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function testValidationExceptionWithSingleError(): void
    {
        $exception = new ValidationException('Invalid workflow', ['Single error']);
        
        $this->assertCount(1, $exception->getErrors());
    }

    // TimeoutException Tests
    public function testTimeoutException(): void
    {
        $exception = new TimeoutException('Operation timed out');
        
        $this->assertEquals('Operation timed out', $exception->getMessage());
        $this->assertEquals('States.Timeout', $exception->getErrorCode());
    }

    public function testTimeoutExceptionErrorCode(): void
    {
        $exception = new TimeoutException('Timeout');
        
        $this->assertEquals('States.Timeout', $exception->getErrorCode());
    }

    // BudgetExceededException Tests
    public function testBudgetExceededException(): void
    {
        $exception = new BudgetExceededException('Budget exceeded', 5.00, 4.50);
        
        $this->assertEquals('Budget exceeded', $exception->getMessage());
        $this->assertEquals(5.00, $exception->getBudgetLimit());
        $this->assertEquals(4.50, $exception->getCurrentUsage());
        $this->assertEquals('States.BudgetExceeded', $exception->getErrorCode());
    }

    public function testBudgetExceededExceptionGetters(): void
    {
        $exception = new BudgetExceededException('Over budget', 10.00, 10.50);
        
        $this->assertEquals(10.00, $exception->getBudgetLimit());
        $this->assertEquals(10.50, $exception->getCurrentUsage());
    }
}
