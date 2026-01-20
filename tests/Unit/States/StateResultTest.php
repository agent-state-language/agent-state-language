<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\States\StateResult;
use AgentStateLanguage\Tests\TestCase;

class StateResultTest extends TestCase
{
    public function testNextResult(): void
    {
        $output = ['message' => 'Hello'];
        $result = StateResult::next($output, 'NextState');
        
        $this->assertEquals($output, $result->getOutput());
        $this->assertEquals('NextState', $result->getNextState());
        $this->assertFalse($result->isTerminal());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getError());
        $this->assertNull($result->getErrorCause());
    }

    public function testEndResult(): void
    {
        $output = ['done' => true];
        $result = StateResult::end($output);
        
        $this->assertEquals($output, $result->getOutput());
        $this->assertNull($result->getNextState());
        $this->assertTrue($result->isTerminal());
        $this->assertFalse($result->hasError());
    }

    public function testErrorResult(): void
    {
        $output = ['input' => 'data'];
        $result = StateResult::error('States.TaskFailed', 'Agent failed', $output);
        
        $this->assertEquals($output, $result->getOutput());
        $this->assertNull($result->getNextState());
        $this->assertTrue($result->isTerminal());
        $this->assertTrue($result->hasError());
        $this->assertEquals('States.TaskFailed', $result->getError());
        $this->assertEquals('Agent failed', $result->getErrorCause());
    }

    public function testNextWithTokensAndCost(): void
    {
        $result = StateResult::next(['data' => 'value'], 'NextState', 500, 0.02);
        
        $this->assertEquals(500, $result->getTokensUsed());
        $this->assertEquals(0.02, $result->getCost());
    }

    public function testEndWithTokensAndCost(): void
    {
        $result = StateResult::end(['data' => 'value'], 1000, 0.05);
        
        $this->assertEquals(1000, $result->getTokensUsed());
        $this->assertEquals(0.05, $result->getCost());
    }

    public function testDefaultTokensAndCost(): void
    {
        $result = StateResult::next([], 'Next');
        
        $this->assertEquals(0, $result->getTokensUsed());
        $this->assertEquals(0.0, $result->getCost());
    }
}
