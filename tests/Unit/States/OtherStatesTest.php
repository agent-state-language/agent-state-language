<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentInterface;
use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\ApprovalState;
use AgentStateLanguage\States\CheckpointState;
use AgentStateLanguage\States\DebateState;
use AgentStateLanguage\States\FailState;
use AgentStateLanguage\States\MapState;
use AgentStateLanguage\States\ParallelState;
use AgentStateLanguage\States\StateFactory;
use AgentStateLanguage\States\SucceedState;
use AgentStateLanguage\States\WaitState;
use AgentStateLanguage\Tests\TestCase;

class OtherStatesTest extends TestCase
{
    private function createContext(): ExecutionContext
    {
        return new ExecutionContext('TestWorkflow');
    }

    // SucceedState Tests
    public function testSucceedStateExecute(): void
    {
        $state = new SucceedState('Done', ['Type' => 'Succeed']);
        $result = $state->execute(['data' => 'value'], $this->createContext());
        
        $this->assertTrue($result->isTerminal());
        $this->assertFalse($result->hasError());
        $this->assertEquals(['data' => 'value'], $result->getOutput());
    }

    public function testSucceedStateIsEnd(): void
    {
        $state = new SucceedState('Done', ['Type' => 'Succeed']);
        $this->assertTrue($state->isEnd());
    }

    public function testSucceedStateGetNext(): void
    {
        $state = new SucceedState('Done', ['Type' => 'Succeed']);
        $this->assertNull($state->getNext());
    }

    // FailState Tests
    public function testFailStateExecute(): void
    {
        $state = new FailState('Failed', [
            'Type' => 'Fail',
            'Error' => 'CustomError',
            'Cause' => 'Something went wrong'
        ]);
        
        $result = $state->execute(['data' => 'value'], $this->createContext());
        
        $this->assertTrue($result->isTerminal());
        $this->assertTrue($result->hasError());
        $this->assertEquals('CustomError', $result->getError());
        $this->assertEquals('Something went wrong', $result->getErrorCause());
    }

    public function testFailStateIsEnd(): void
    {
        $state = new FailState('Failed', ['Type' => 'Fail', 'Error' => 'Error']);
        $this->assertTrue($state->isEnd());
    }

    public function testFailStateGetNext(): void
    {
        $state = new FailState('Failed', ['Type' => 'Fail', 'Error' => 'Error']);
        $this->assertNull($state->getNext());
    }

    // WaitState Tests
    public function testWaitStateWithSeconds(): void
    {
        $state = new WaitState('Wait', [
            'Type' => 'Wait',
            'Seconds' => 0, // Use 0 for testing
            'Next' => 'Continue'
        ]);
        
        $result = $state->execute(['data' => 'value'], $this->createContext());
        
        $this->assertFalse($result->isTerminal());
        $this->assertEquals('Continue', $result->getNextState());
        $this->assertEquals(['data' => 'value'], $result->getOutput());
    }

    public function testWaitStateGetNext(): void
    {
        $state = new WaitState('Wait', [
            'Type' => 'Wait',
            'Seconds' => 10,
            'Next' => 'NextState'
        ]);
        
        $this->assertEquals('NextState', $state->getNext());
    }

    public function testWaitStateIsEnd(): void
    {
        $state = new WaitState('Wait', [
            'Type' => 'Wait',
            'Seconds' => 10,
            'End' => true
        ]);
        
        $this->assertTrue($state->isEnd());
    }

    // ParallelState Tests
    public function testParallelStateExecution(): void
    {
        $registry = new AgentRegistry();
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'MockAgent'; }
            public function execute(array $parameters): array { 
                return ['processed' => true, 'input' => $parameters]; 
            }
        };
        $registry->register('BranchAgent', $mockAgent);
        
        $factory = new StateFactory($registry);
        
        $state = new ParallelState('Parallel', [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Branch1',
                    'States' => [
                        'Branch1' => [
                            'Type' => 'Task',
                            'Agent' => 'BranchAgent',
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'Branch2',
                    'States' => [
                        'Branch2' => [
                            'Type' => 'Pass',
                            'Result' => ['branch' => 2],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'End' => true
        ], $factory);
        
        $result = $state->execute(['input' => 'data'], $this->createContext());
        
        $this->assertTrue($result->isTerminal());
        $this->assertIsArray($result->getOutput());
    }

    public function testParallelStateGetNext(): void
    {
        $factory = new StateFactory(new AgentRegistry());
        $state = new ParallelState('Parallel', [
            'Type' => 'Parallel',
            'Branches' => [],
            'Next' => 'NextState'
        ], $factory);
        
        $this->assertEquals('NextState', $state->getNext());
    }

    // MapState Tests
    public function testMapStateExecution(): void
    {
        $registry = new AgentRegistry();
        $factory = new StateFactory($registry);
        
        $state = new MapState('Map', [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => [
                'StartAt' => 'Process',
                'States' => [
                    'Process' => [
                        'Type' => 'Pass',
                        'Parameters' => [
                            'processed.$' => '$$.Map.Item.Value',
                            'index.$' => '$$.Map.Item.Index'
                        ],
                        'End' => true
                    ]
                ]
            ],
            'End' => true
        ], $factory);
        
        $result = $state->execute(['items' => ['a', 'b', 'c']], $this->createContext());
        
        $this->assertTrue($result->isTerminal());
        $this->assertIsArray($result->getOutput());
        $this->assertCount(3, $result->getOutput());
    }

    public function testMapStateGetNext(): void
    {
        $factory = new StateFactory(new AgentRegistry());
        $state = new MapState('Map', [
            'Type' => 'Map',
            'ItemsPath' => '$.items',
            'Iterator' => ['StartAt' => 'Process', 'States' => []],
            'Next' => 'NextState'
        ], $factory);
        
        $this->assertEquals('NextState', $state->getNext());
    }

    // ApprovalState Tests
    public function testApprovalStateExecution(): void
    {
        $state = new ApprovalState('Approval', [
            'Type' => 'Approval',
            'Prompt' => [
                'Title' => 'Please Approve',
                'Description' => 'Review this request'
            ],
            'Options' => ['approve', 'reject'],
            'Next' => 'Continue'
        ]);
        
        // Approval states return with approval data
        $result = $state->execute(['request' => 'data'], $this->createContext());
        
        // The state should return output
        $this->assertIsArray($result->getOutput());
    }

    public function testApprovalStateGetNext(): void
    {
        $state = new ApprovalState('Approval', [
            'Type' => 'Approval',
            'Prompt' => ['Title' => 'Test'],
            'Next' => 'NextState'
        ]);
        
        $this->assertEquals('NextState', $state->getNext());
    }

    // CheckpointState Tests
    public function testCheckpointStateExecution(): void
    {
        $state = new CheckpointState('Checkpoint', [
            'Type' => 'Checkpoint',
            'Next' => 'Continue'
        ]);
        
        $result = $state->execute(['data' => 'value'], $this->createContext());
        
        $this->assertFalse($result->isTerminal());
        $this->assertEquals('Continue', $result->getNextState());
    }

    public function testCheckpointStateGetNext(): void
    {
        $state = new CheckpointState('Checkpoint', [
            'Type' => 'Checkpoint',
            'Next' => 'NextState'
        ]);
        
        $this->assertEquals('NextState', $state->getNext());
    }

    // DebateState Tests
    public function testDebateStateExecution(): void
    {
        $registry = new AgentRegistry();
        $mockAgent = new class implements AgentInterface {
            public function getName(): string { return 'DebateAgent'; }
            public function execute(array $parameters): array { 
                return ['response' => 'My position is...', 'confidence' => 0.8]; 
            }
        };
        $registry->register('Agent1', $mockAgent);
        $registry->register('Agent2', $mockAgent);
        
        $state = new DebateState('Debate', [
            'Type' => 'Debate',
            'Agents' => ['Agent1', 'Agent2'],
            'MaxRounds' => 2,
            'End' => true
        ], $registry);
        
        $result = $state->execute(['topic' => 'Test topic'], $this->createContext());
        
        $this->assertIsArray($result->getOutput());
    }

    public function testDebateStateGetNext(): void
    {
        $state = new DebateState('Debate', [
            'Type' => 'Debate',
            'Agents' => [],
            'Next' => 'NextState'
        ], new AgentRegistry());
        
        $this->assertEquals('NextState', $state->getNext());
    }
}
