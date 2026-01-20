<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\Engine;

use AgentStateLanguage\Engine\WorkflowResult;
use AgentStateLanguage\Tests\TestCase;

class WorkflowResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $output = ['message' => 'Hello'];
        $trace = [['type' => 'test']];
        
        $result = WorkflowResult::success($output, $trace, 1.5, 100, 0.05);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals($output, $result->getOutput());
        $this->assertEquals($trace, $result->getTrace());
        $this->assertEquals(1.5, $result->getDuration());
        $this->assertEquals(100, $result->getTokensUsed());
        $this->assertEquals(0.05, $result->getCost());
        $this->assertNull($result->getError());
        $this->assertNull($result->getErrorCause());
    }

    public function testFailureResult(): void
    {
        $trace = [['type' => 'error']];
        
        $result = WorkflowResult::failure('States.Error', 'Something went wrong', $trace, 0.5);
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals([], $result->getOutput());
        $this->assertEquals('States.Error', $result->getError());
        $this->assertEquals('Something went wrong', $result->getErrorCause());
        $this->assertEquals($trace, $result->getTrace());
        $this->assertEquals(0.5, $result->getDuration());
    }

    public function testConstructorDefaults(): void
    {
        $result = new WorkflowResult(true);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals([], $result->getOutput());
        $this->assertNull($result->getError());
        $this->assertNull($result->getErrorCause());
        $this->assertEquals([], $result->getTrace());
        $this->assertEquals(0.0, $result->getDuration());
        $this->assertEquals(0, $result->getTokensUsed());
        $this->assertEquals(0.0, $result->getCost());
    }

    public function testSuccessWithMinimalArgs(): void
    {
        $result = WorkflowResult::success(['data' => 'value']);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['data' => 'value'], $result->getOutput());
        $this->assertEquals([], $result->getTrace());
        $this->assertEquals(0.0, $result->getDuration());
    }

    public function testFailureWithMinimalArgs(): void
    {
        $result = WorkflowResult::failure('TestError', 'Test cause');
        
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('TestError', $result->getError());
        $this->assertEquals('Test cause', $result->getErrorCause());
    }
}
