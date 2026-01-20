<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\States\TaskState;
use AgentStateLanguage\Tests\TestCase;

class TaskStateTest extends TestCase
{
    private function createContext(): ExecutionContext
    {
        return new ExecutionContext('TestWorkflow');
    }

    private function createTestAgent(array $result): AgentInterface
    {
        return new class($result) implements AgentInterface {
            private array $result;
            public function __construct(array $result) { $this->result = $result; }
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array { return $this->result; }
        };
    }

    private function createErrorAgent(string $message): AgentInterface
    {
        return new class($message) implements AgentInterface {
            private string $message;
            public function __construct(string $message) { $this->message = $message; }
            public function getName(): string { return 'FailingAgent'; }
            public function execute(array $parameters): array { 
                throw new AgentException($this->message, 'Agent.Error');
            }
        };
    }

    public function testBasicExecution(): void
    {
        $registry = new AgentRegistry();
        $registry->register('TestAgent', $this->createTestAgent(['result' => 'success']));
        
        $state = new TaskState('TestTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'End' => true
        ], $registry);
        
        $result = $state->execute(['input' => 'data'], $this->createContext());
        
        $this->assertEquals(['result' => 'success'], $result->getOutput());
        $this->assertTrue($result->isTerminal());
    }

    public function testWithParameters(): void
    {
        $capturedParams = null;
        $agent = new class($capturedParams) implements AgentInterface {
            private mixed $captured;
            public function __construct(mixed &$captured) { $this->captured = &$captured; }
            public function getName(): string { return 'CaptureAgent'; }
            public function execute(array $parameters): array { 
                $this->captured = $parameters;
                return ['processed' => true];
            }
        };
        
        $registry = new AgentRegistry();
        $registry->register('CaptureAgent', $agent);
        
        $state = new TaskState('TestTask', [
            'Type' => 'Task',
            'Agent' => 'CaptureAgent',
            'Parameters' => [
                'key1.$' => '$.input',
                'key2' => 'static'
            ],
            'End' => true
        ], $registry);
        
        $result = $state->execute(['input' => 'dynamic'], $this->createContext());
        
        $this->assertTrue($result->isTerminal());
        $this->assertEquals(['processed' => true], $result->getOutput());
    }

    public function testWithResultPath(): void
    {
        $registry = new AgentRegistry();
        $registry->register('TestAgent', $this->createTestAgent(['value' => 42]));
        
        $state = new TaskState('TestTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'ResultPath' => '$.result',
            'Next' => 'NextState'
        ], $registry);
        
        $result = $state->execute(['existing' => 'data'], $this->createContext());
        
        $this->assertEquals('NextState', $result->getNextState());
        $this->assertEquals('data', $result->getOutput()['existing']);
        $this->assertEquals(42, $result->getOutput()['result']['value']);
    }

    public function testAgentError(): void
    {
        $registry = new AgentRegistry();
        $registry->register('FailAgent', $this->createErrorAgent('Agent failed'));
        
        $state = new TaskState('TestTask', [
            'Type' => 'Task',
            'Agent' => 'FailAgent',
            'End' => true
        ], $registry);
        
        $result = $state->execute(['input' => 'data'], $this->createContext());
        
        $this->assertTrue($result->hasError());
        $this->assertEquals('Agent.Error', $result->getError());
    }

    public function testNextTransition(): void
    {
        $registry = new AgentRegistry();
        $registry->register('TestAgent', $this->createTestAgent(['done' => true]));
        
        $state = new TaskState('TestTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Next' => 'FollowingState'
        ], $registry);
        
        $result = $state->execute([], $this->createContext());
        
        $this->assertFalse($result->isTerminal());
        $this->assertEquals('FollowingState', $result->getNextState());
    }

    public function testGetName(): void
    {
        $registry = new AgentRegistry();
        $state = new TaskState('MyTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'End' => true
        ], $registry);
        
        $this->assertEquals('MyTask', $state->getName());
    }

    public function testGetType(): void
    {
        $registry = new AgentRegistry();
        $state = new TaskState('MyTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'End' => true
        ], $registry);
        
        $this->assertEquals('Task', $state->getType());
    }

    public function testIsEnd(): void
    {
        $registry = new AgentRegistry();
        
        $endState = new TaskState('EndTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'End' => true
        ], $registry);
        $this->assertTrue($endState->isEnd());
        
        $nextState = new TaskState('NextTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Next' => 'Other'
        ], $registry);
        $this->assertFalse($nextState->isEnd());
    }

    public function testGetNext(): void
    {
        $registry = new AgentRegistry();
        
        $state = new TaskState('MyTask', [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Next' => 'NextState'
        ], $registry);
        
        $this->assertEquals('NextState', $state->getNext());
    }
}
