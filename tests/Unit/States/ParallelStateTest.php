<?php

declare(strict_types=1);

namespace AgentStateLanguage\Tests\Unit\States;

use AgentStateLanguage\Agents\AgentRegistry;
use AgentStateLanguage\Engine\ExecutionContext;
use AgentStateLanguage\States\ParallelState;
use AgentStateLanguage\States\StateResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ParallelStateTest extends TestCase
{
    private AgentRegistry|MockObject $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistry::class);
    }

    public function testExecuteWithTwoBranches(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'BranchA',
                    'States' => [
                        'BranchA' => [
                            'Type' => 'Pass',
                            'Result' => ['branch' => 'A'],
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'BranchB',
                    'States' => [
                        'BranchB' => [
                            'Type' => 'Pass',
                            'Result' => ['branch' => 'B'],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext(['input' => 'data']);
        
        $result = $state->execute($context);
        
        $this->assertInstanceOf(StateResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->getOutput());
        $this->assertEquals('NextState', $result->getNextState());
    }

    public function testBranchesReceiveSameInput(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Branch1',
                    'States' => [
                        'Branch1' => [
                            'Type' => 'Pass',
                            'Parameters' => [
                                'received.$' => '$.input'
                            ],
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'Branch2',
                    'States' => [
                        'Branch2' => [
                            'Type' => 'Pass',
                            'Parameters' => [
                                'received.$' => '$.input'
                            ],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext(['input' => 'shared value']);
        
        $result = $state->execute($context);
        
        $outputs = $result->getOutput();
        
        // Both branches should have received the same input
        $this->assertEquals('shared value', $outputs[0]['received']);
        $this->assertEquals('shared value', $outputs[1]['received']);
    }

    public function testResultPath(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Branch1',
                    'States' => [
                        'Branch1' => [
                            'Type' => 'Pass',
                            'Result' => ['data' => 1],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'ResultPath' => '$.parallelResults',
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext(['original' => 'preserved']);
        
        $result = $state->execute($context);
        
        // The output is the array of branch results
        $this->assertIsArray($result->getOutput());
    }

    public function testMultiBranchResults(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'A',
                    'States' => [
                        'A' => [
                            'Type' => 'Pass',
                            'Result' => 'result A',
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'B',
                    'States' => [
                        'B' => [
                            'Type' => 'Pass',
                            'Result' => 'result B',
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'C',
                    'States' => [
                        'C' => [
                            'Type' => 'Pass',
                            'Result' => 'result C',
                            'End' => true
                        ]
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertEquals(['result A', 'result B', 'result C'], $result->getOutput());
    }

    public function testRetryConfiguration(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Branch',
                    'States' => [
                        'Branch' => ['Type' => 'Pass', 'End' => true]
                    ]
                ]
            ],
            'Retry' => [
                [
                    'ErrorEquals' => ['States.ALL'],
                    'MaxAttempts' => 3,
                    'IntervalSeconds' => 1,
                    'BackoffRate' => 2.0
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        
        $this->assertNotEmpty($state->getRetryConfig());
    }

    public function testCatchConfiguration(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Branch',
                    'States' => [
                        'Branch' => ['Type' => 'Pass', 'End' => true]
                    ]
                ]
            ],
            'Catch' => [
                [
                    'ErrorEquals' => ['States.ALL'],
                    'Next' => 'ErrorHandler',
                    'ResultPath' => '$.error'
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        
        $this->assertNotEmpty($state->getCatchConfig());
    }

    public function testAsEndState(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Branch',
                    'States' => [
                        'Branch' => ['Type' => 'Pass', 'End' => true]
                    ]
                ]
            ],
            'End' => true
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isEnd());
        $this->assertNull($result->getNextState());
    }

    public function testInputPath(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'InputPath' => '$.nested',
            'Branches' => [
                [
                    'StartAt' => 'Branch',
                    'States' => [
                        'Branch' => [
                            'Type' => 'Pass',
                            'Parameters' => [
                                'value.$' => '$.data'
                            ],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext([
            'nested' => ['data' => 'filtered'],
            'other' => 'excluded'
        ]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([['value' => 'filtered']], $result->getOutput());
    }

    public function testOutputPath(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'OutputPath' => '$[0]',
            'Branches' => [
                [
                    'StartAt' => 'Branch1',
                    'States' => [
                        'Branch1' => [
                            'Type' => 'Pass',
                            'Result' => ['first' => 'result'],
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'Branch2',
                    'States' => [
                        'Branch2' => [
                            'Type' => 'Pass',
                            'Result' => ['second' => 'result'],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        // OutputPath should select only the first branch result
        $this->assertEquals(['first' => 'result'], $result->getOutput());
    }

    public function testGetType(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [],
            'Next' => 'Next'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        
        $this->assertEquals('Parallel', $state->getType());
    }

    public function testGetName(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [],
            'Next' => 'Next'
        ];
        
        $state = new ParallelState('MyParallel', $definition, $this->registry);
        
        $this->assertEquals('MyParallel', $state->getName());
    }

    public function testGetComment(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Comment' => 'Execute branches in parallel',
            'Branches' => [],
            'Next' => 'Next'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        
        $this->assertEquals('Execute branches in parallel', $state->getComment());
    }

    public function testComplexBranchWorkflows(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [
                [
                    'StartAt' => 'Step1A',
                    'States' => [
                        'Step1A' => [
                            'Type' => 'Pass',
                            'Result' => ['step' => '1A'],
                            'Next' => 'Step2A'
                        ],
                        'Step2A' => [
                            'Type' => 'Pass',
                            'Result' => ['step' => '2A'],
                            'End' => true
                        ]
                    ]
                ],
                [
                    'StartAt' => 'Step1B',
                    'States' => [
                        'Step1B' => [
                            'Type' => 'Pass',
                            'Result' => ['step' => '1B'],
                            'End' => true
                        ]
                    ]
                ]
            ],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->getOutput());
    }

    public function testEmptyBranchesReturnsEmptyArray(): void
    {
        $definition = [
            'Type' => 'Parallel',
            'Branches' => [],
            'Next' => 'NextState'
        ];
        
        $state = new ParallelState('TestParallel', $definition, $this->registry);
        $context = new ExecutionContext([]);
        
        $result = $state->execute($context);
        
        $this->assertEquals([], $result->getOutput());
    }
}
