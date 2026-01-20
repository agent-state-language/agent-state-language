<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\TaskState;
use AgentStateLanguage\States\StateResult;
use AgentStateLanguage\Exceptions\AgentException;
use AgentStateLanguage\Exceptions\TimeoutException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TaskStateTest extends TestCase
{
    private AgentRegistry|MockObject $registry;
    private AgentInterface|MockObject $agent;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistry::class);
        $this->agent = $this->createMock(AgentInterface::class);
    }

    public function testExecuteWithAgent(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Parameters' => [
                'input.$' => '$.data'
            ],
            'Next' => 'NextState'
        ];
        
        $this->registry->expects($this->once())
            ->method('get')
            ->with('TestAgent')
            ->willReturn($this->agent);
        
        $this->agent->expects($this->once())
            ->method('run')
            ->with(['input' => 'test value'])
            ->willReturn(['result' => 'success']);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext(['data' => 'test value']);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertEquals(['result' => 'success'], $result->getOutput());
        $this->assertEquals('NextState', $result->getNextState());
        $this->assertTrue($result->isSuccess());
    }

    public function testExecuteWithResultPath(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'ResultPath' => '$.agentOutput',
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->method('run')->willReturn(['result' => 'data']);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext(['original' => 'data']);
        
        $result = $state->execute($context);
        
        $this->assertEquals(['result' => 'data'], $result->getOutput());
    }

    public function testExecuteWithInputPath(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'InputPath' => '$.nested.data',
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->expects($this->once())
            ->method('run')
            ->with(['value' => 'test'])
            ->willReturn(['result' => 'success']);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([
            'nested' => ['data' => ['value' => 'test']]
        ]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isSuccess());
    }

    public function testExecuteAsEndState(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'End' => true
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->method('run')->willReturn(['final' => 'result']);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertNull($result->getNextState());
        $this->assertTrue($result->isEnd());
    }

    public function testRetryOnError(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Retry' => [
                [
                    'ErrorEquals' => ['TransientError'],
                    'MaxAttempts' => 3,
                    'IntervalSeconds' => 0,
                    'BackoffRate' => 2
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        
        $callCount = 0;
        $this->agent->method('run')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new AgentException('TransientError', 'Temporary failure');
            }
            return ['result' => 'success'];
        });
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertEquals(3, $callCount);
        $this->assertTrue($result->isSuccess());
    }

    public function testCatchOnError(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Catch' => [
                [
                    'ErrorEquals' => ['States.ALL'],
                    'Next' => 'ErrorHandler',
                    'ResultPath' => '$.error'
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->method('run')->willThrowException(
            new AgentException('CustomError', 'Something went wrong')
        );
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertEquals('ErrorHandler', $result->getNextState());
        $this->assertArrayHasKey('Error', $result->getOutput());
        $this->assertEquals('CustomError', $result->getOutput()['Error']);
    }

    public function testToolsRestriction(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Tools' => [
                'Allowed' => ['tool_a', 'tool_b'],
                'Denied' => ['dangerous_tool']
            ],
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->method('run')->willReturn(['result' => 'success']);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $this->assertEquals(['tool_a', 'tool_b'], $state->getAllowedTools());
        $this->assertEquals(['dangerous_tool'], $state->getDeniedTools());
    }

    public function testTimeoutSeconds(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'TimeoutSeconds' => 30,
            'Next' => 'NextState'
        ];
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $this->assertEquals(30, $state->getTimeoutSeconds());
    }

    public function testHeartbeatSeconds(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'HeartbeatSeconds' => 10,
            'Next' => 'NextState'
        ];
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $this->assertEquals(10, $state->getHeartbeatSeconds());
    }

    public function testParametersResolution(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Parameters' => [
                'name.$' => '$.user.name',
                'age.$' => '$.user.age',
                'static' => 'value',
                'nested' => [
                    'computed.$' => '$.data'
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $expectedParams = [
            'name' => 'John',
            'age' => 30,
            'static' => 'value',
            'nested' => ['computed' => 'test']
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->expects($this->once())
            ->method('run')
            ->with($expectedParams)
            ->willReturn(['result' => 'success']);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([
            'user' => ['name' => 'John', 'age' => 30],
            'data' => 'test'
        ]);
        
        $state->execute($context);
    }

    public function testAgentNotFound(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'NonExistentAgent',
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')
            ->willThrowException(new \InvalidArgumentException('Agent not found'));
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $this->expectException(AgentException::class);
        $state->execute($context);
    }

    public function testReasoningConfiguration(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Reasoning' => [
                'Required' => true,
                'Format' => 'chain_of_thought',
                'Store' => '$.reasoning'
            ],
            'Next' => 'NextState'
        ];
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $reasoning = $state->getReasoningConfig();
        
        $this->assertTrue($reasoning['Required']);
        $this->assertEquals('chain_of_thought', $reasoning['Format']);
        $this->assertEquals('$.reasoning', $reasoning['Store']);
    }

    public function testMemoryConfiguration(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Memory' => [
                'Read' => [
                    'Keys' => ['user_history'],
                    'InjectAt' => '$.history'
                ],
                'Write' => [
                    'Key' => 'session_data',
                    'Value.$' => '$.result'
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $memory = $state->getMemoryConfig();
        
        $this->assertEquals(['user_history'], $memory['Read']['Keys']);
        $this->assertEquals('session_data', $memory['Write']['Key']);
    }

    public function testGetName(): void
    {
        $definition = ['Type' => 'Task', 'Agent' => 'Test', 'Next' => 'Next'];
        $state = new TaskState('MyTaskState', $definition, $this->registry);
        
        $this->assertEquals('MyTaskState', $state->getName());
    }

    public function testGetType(): void
    {
        $definition = ['Type' => 'Task', 'Agent' => 'Test', 'Next' => 'Next'];
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $this->assertEquals('Task', $state->getType());
    }

    public function testGetComment(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'Test',
            'Comment' => 'This is a task state',
            'Next' => 'Next'
        ];
        $state = new TaskState('TestTask', $definition, $this->registry);
        
        $this->assertEquals('This is a task state', $state->getComment());
    }

    public function testCostTracking(): void
    {
        $definition = [
            'Type' => 'Task',
            'Agent' => 'TestAgent',
            'Next' => 'NextState'
        ];
        
        $this->registry->method('get')->willReturn($this->agent);
        $this->agent->method('run')->willReturn([
            'result' => 'success',
            '_metadata' => [
                'cost' => 0.05,
                'tokens' => 500
            ]
        ]);
        
        $state = new TaskState('TestTask', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $metadata = $result->getMetadata();
        $this->assertEquals(0.05, $metadata['cost'] ?? null);
        $this->assertEquals(500, $metadata['tokens'] ?? null);
    }
}
